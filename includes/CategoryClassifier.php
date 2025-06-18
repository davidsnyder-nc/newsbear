<?php

class CategoryClassifier {
    private $aiService;
    private $settings;
    
    public function __construct($settings) {
        $this->settings = $settings;
        require_once __DIR__ . '/AIService.php';
        $this->aiService = new AIService($settings);
    }
    
    public function classifyArticles($articles) {
        $classifiedArticles = [];
        $articlesToClassify = [];
        
        // Separate articles that need classification from those that don't
        foreach ($articles as $article) {
            $category = strtolower($article['category'] ?? '');
            
            // Skip articles that already have specific categories
            if ($category && $category !== 'general') {
                $classifiedArticles[] = $article;
                continue;
            }
            
            // Skip system categories that shouldn't be reclassified
            if (in_array($category, ['weather', 'local', 'entertainment'])) {
                $classifiedArticles[] = $article;
                continue;
            }
            
            // Queue for classification
            $articlesToClassify[] = $article;
        }
        
        // Classify articles in batches
        if (!empty($articlesToClassify)) {
            $batchSize = 10; // Process 10 articles at a time
            $batches = array_chunk($articlesToClassify, $batchSize);
            
            foreach ($batches as $batch) {
                $classifiedBatch = $this->classifyBatch($batch);
                $classifiedArticles = array_merge($classifiedArticles, $classifiedBatch);
            }
        }
        
        return $classifiedArticles;
    }
    
    private function classifyBatch($articles) {
        if (empty($articles)) {
            return [];
        }
        
        // Get available categories (standard + RSS custom categories)
        $availableCategories = $this->getAvailableCategories();
        
        // Prepare article summaries for classification
        $articleSummaries = [];
        foreach ($articles as $index => $article) {
            $articleSummaries[] = [
                'id' => $index,
                'title' => $article['title'],
                'content' => substr($article['content'] ?? '', 0, 300) // Limit content length
            ];
        }
        
        $prompt = $this->buildClassificationPrompt($articleSummaries, $availableCategories);
        
        try {
            $response = $this->aiService->generateContent($prompt);
            $classifications = $this->parseClassificationResponse($response);
            
            // Apply classifications to articles
            $classifiedArticles = [];
            foreach ($articles as $index => $article) {
                $newCategory = $classifications[$index] ?? 'general';
                $article['category'] = $newCategory;
                $classifiedArticles[] = $article;
                
                error_log("Classified article '{$article['title']}' as category: $newCategory");
            }
            
            return $classifiedArticles;
            
        } catch (Exception $e) {
            error_log("Category classification error: " . $e->getMessage());
            // Return original articles if classification fails
            return $articles;
        }
    }
    
    private function getAvailableCategories() {
        // Standard news categories
        $categories = [
            'general', 'business', 'entertainment', 'health', 'science', 
            'sports', 'technology', 'politics', 'world'
        ];
        
        // Add RSS custom categories
        try {
            require_once __DIR__ . '/RSSFeedHandler.php';
            $rssHandler = new RSSFeedHandler();
            $customCategories = $rssHandler->getCustomCategories();
            $categories = array_merge($categories, $customCategories);
        } catch (Exception $e) {
            // Continue without RSS categories if there's an error
        }
        
        return array_unique($categories);
    }
    
    private function buildClassificationPrompt($articles, $categories) {
        $prompt = "You are a news categorization expert. Classify each news article into the most appropriate category.\n\n";
        
        $prompt .= "AVAILABLE CATEGORIES (use exactly these names):\n";
        
        // Group categories for better understanding
        $standardCategories = ['general', 'business', 'entertainment', 'health', 'science', 'sports', 'technology', 'politics', 'world'];
        $customCategories = array_diff($categories, $standardCategories);
        
        $prompt .= "Standard categories:\n";
        foreach ($standardCategories as $cat) {
            if (in_array($cat, $categories)) {
                $prompt .= "- $cat: " . $this->getCategoryDescription($cat) . "\n";
            }
        }
        
        if (!empty($customCategories)) {
            $prompt .= "\nCustom categories (user-defined):\n";
            foreach ($customCategories as $cat) {
                $prompt .= "- $cat: Content specifically related to $cat topics\n";
            }
        }
        
        $prompt .= "\nCLASSIFICATION RULES:\n";
        $prompt .= "1. Choose the MOST SPECIFIC category that fits the article\n";
        $prompt .= "2. Use 'general' only if no other category fits\n";
        $prompt .= "3. Custom categories take priority when content clearly matches\n";
        $prompt .= "4. Use exact category names as listed above (lowercase)\n\n";
        
        $prompt .= "ARTICLES TO CLASSIFY:\n";
        
        foreach ($articles as $article) {
            $prompt .= "ID {$article['id']}: {$article['title']}\n";
            if (!empty($article['content'])) {
                $prompt .= "Content: {$article['content']}\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "Respond with only the category name for each article, one per line, in the format:\n";
        $prompt .= "0: category_name\n1: category_name\n2: category_name\n";
        $prompt .= "Use lowercase category names exactly as listed above.";
        
        return $prompt;
    }
    
    private function parseClassificationResponse($response) {
        $classifications = [];
        $lines = explode("\n", trim($response));
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^(\d+):\s*(.+)$/', $line, $matches)) {
                $id = (int)$matches[1];
                $category = strtolower(trim($matches[2]));
                $classifications[$id] = $category;
            }
        }
        
        return $classifications;
    }
    
    private function getCategoryDescription($category) {
        $descriptions = [
            'general' => 'General news and current events that don\'t fit other categories',
            'business' => 'Business, finance, economics, markets, corporate news',
            'entertainment' => 'Movies, TV shows, music, celebrities, pop culture',
            'health' => 'Medical news, healthcare, wellness, disease, research',
            'science' => 'Scientific research, discoveries, space, environment',
            'sports' => 'All sports, athletes, games, competitions',
            'technology' => 'Tech companies, gadgets, software, AI, innovation',
            'politics' => 'Government, elections, policy, political figures',
            'world' => 'International news, global events, foreign affairs'
        ];
        
        return $descriptions[$category] ?? 'News related to ' . $category;
    }
}