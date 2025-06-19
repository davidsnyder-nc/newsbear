<?php

class NewsAPI {
    private $gnewsKey;
    private $newsApiKey;
    private $guardianKey;
    private $nytKey;
    private $settings;
    
    public function __construct($settings = null) {
        $this->settings = $settings;
        if ($settings) {
            $this->gnewsKey = ($settings['gnewsEnabled'] ?? true) ? ($settings['gnewsApiKey'] ?: getenv('GNEWS_API_KEY')) : null;
            $this->newsApiKey = ($settings['newsApiEnabled'] ?? true) ? ($settings['newsApiKey'] ?: getenv('NEWSAPI_KEY')) : null;
            $this->guardianKey = ($settings['guardianEnabled'] ?? true) ? ($settings['guardianApiKey'] ?: getenv('GUARDIAN_API_KEY')) : null;
            $this->nytKey = ($settings['nytEnabled'] ?? true) ? ($settings['nytApiKey'] ?: getenv('NYT_API_KEY')) : null;
        } else {
            $this->gnewsKey = getenv('GNEWS_API_KEY');
            $this->newsApiKey = getenv('NEWSAPI_KEY');
            $this->guardianKey = getenv('GUARDIAN_API_KEY');
            $this->nytKey = getenv('NYT_API_KEY');
        }
        
        // Log which API keys are available for debugging
        error_log("NewsAPI Constructor - Available keys:");
        error_log("GNews: " . ($this->gnewsKey ? "YES" : "NO"));
        error_log("NewsAPI: " . ($this->newsApiKey ? "YES" : "NO"));
        error_log("Guardian: " . ($this->guardianKey ? "YES" : "NO"));
        error_log("NYT: " . ($this->nytKey ? "YES" : "NO"));
    }
    
    public function fetchFromAllSources($categories, $zipCode = null, $includeLocal = false) {
        $allNews = [];
        
        // Fetch local news FIRST if requested and zip code provided
        if ($includeLocal && $zipCode) {
            try {
                $localNews = $this->fetchLocalNewsFromRSS($zipCode);
                $allNews = array_merge($allNews, $localNews);
                error_log("Local news fetch: Found " . count($localNews) . " local articles (added first)");
            } catch (Exception $e) {
                error_log("Local news fetch error: " . $e->getMessage());
            }
        }
        
        // Then fetch from RSS feeds 
        try {
            require_once __DIR__ . '/RSSFeedHandler.php';
            $rssHandler = new RSSFeedHandler();
            $rssNews = $rssHandler->getAllRssArticles(10);
            $allNews = array_merge($allNews, $rssNews);
            error_log("RSS fetch: Found " . count($rssNews) . " articles from RSS feeds (added after local)");
        } catch (Exception $e) {
            error_log("RSS feed fetch error: " . $e->getMessage());
        }
        
        // Only fetch from general news APIs if 'general' category is selected
        $shouldFetchGeneral = in_array('general', $categories);
        error_log("Should fetch general news: " . ($shouldFetchGeneral ? "YES" : "NO") . " - Categories: " . implode(', ', $categories));
        
        // Fetch from each enabled source (only if general category is selected)
        if ($this->gnewsKey && $shouldFetchGeneral) {
            try {
                $gnewsArticles = $this->fetchFromGNews($categories);
                error_log("GNews fetch: Retrieved " . count($gnewsArticles) . " articles");
                if (!empty($gnewsArticles)) {
                    foreach ($gnewsArticles as $article) {
                        error_log("GNews article: " . $article['title'] . " from " . $article['source']);
                    }
                }
                $allNews = array_merge($allNews, $gnewsArticles);
            } catch (Exception $e) {
                error_log("GNews fetch error: " . $e->getMessage());
            }
        } else {
            error_log("GNews: " . (!$this->gnewsKey ? "API key not available" : "Skipped - general category not selected"));
        }
        
        if ($this->newsApiKey && $shouldFetchGeneral) {
            try {
                $newsApiArticles = $this->fetchFromNewsAPI($categories);
                error_log("NewsAPI fetch: Retrieved " . count($newsApiArticles) . " articles");
                if (!empty($newsApiArticles)) {
                    foreach ($newsApiArticles as $article) {
                        error_log("NewsAPI article: " . $article['title'] . " from " . $article['source']);
                    }
                }
                $allNews = array_merge($allNews, $newsApiArticles);
            } catch (Exception $e) {
                error_log("NewsAPI fetch error: " . $e->getMessage());
            }
        } else {
            error_log("NewsAPI: " . (!$this->newsApiKey ? "API key not available" : "Skipped - general category not selected"));
        }
        
        if ($this->guardianKey && $shouldFetchGeneral) {
            try {
                $guardianArticles = $this->fetchFromGuardian($categories);
                error_log("Guardian fetch: Retrieved " . count($guardianArticles) . " articles");
                $allNews = array_merge($allNews, $guardianArticles);
            } catch (Exception $e) {
                error_log("Guardian fetch error: " . $e->getMessage());
            }
        } else {
            error_log("Guardian: " . (!$this->guardianKey ? "API key not available" : "Skipped - general category not selected"));
        }
        
        if ($this->nytKey && $shouldFetchGeneral) {
            try {
                $nytNews = $this->fetchFromNYT($categories);
                error_log("NYT fetch returned " . count($nytNews) . " articles");
                if (!empty($nytNews)) {
                    foreach ($nytNews as $article) {
                        error_log("NYT article: " . $article['title'] . " from " . $article['source']);
                    }
                }
                $allNews = array_merge($allNews, $nytNews);
            } catch (Exception $e) {
                error_log("NYT fetch error: " . $e->getMessage());
            }
        } else {
            error_log("NYT: " . (!$this->nytKey ? "API key not available" : "Skipped - general category not selected"));
        }
        
        // Use AI to classify articles that came in as "general"
        try {
            require_once __DIR__ . '/CategoryClassifier.php';
            $classifier = new CategoryClassifier($this->settings);
            $allNews = $classifier->classifyArticles($allNews);
            error_log("Applied AI categorization to news articles");
        } catch (Exception $e) {
            error_log("Category classification error: " . $e->getMessage());
        }
        
        error_log("Total news articles before return: " . count($allNews));
        
        // If no articles were fetched and no RSS content, throw error
        if (empty($allNews)) {
            $enabledSources = [];
            if ($this->gnewsKey) $enabledSources[] = 'GNews';
            if ($this->newsApiKey) $enabledSources[] = 'NewsAPI';
            if ($this->guardianKey) $enabledSources[] = 'Guardian';
            if ($this->nytKey) $enabledSources[] = 'New York Times';
            
            if (empty($enabledSources)) {
                throw new Exception('No news API sources are enabled. Please configure at least one news API in settings.');
            } else {
                throw new Exception('Unable to fetch content from enabled sources: ' . implode(', ', $enabledSources) . '. Please check your API keys and internet connection.');
            }
        }
        
        return $allNews;
    }
    
    private function fetchFromGNews($categories) {
        $news = [];
        
        // Validate API key exists
        if (empty($this->gnewsKey)) {
            throw new Exception("GNews API key is not configured");
        }
        
        // Use only one request to get general news to reduce API calls
        $url = "https://gnews.io/api/v4/top-headlines?" . http_build_query([
            'lang' => 'en',
            'country' => 'us',
            'max' => 30, // Get more articles in one request
            'apikey' => $this->gnewsKey
        ]);
        
        error_log("GNews API URL: " . $url);
        $response = $this->makeRequest($url);
        
        if ($response === null) {
            error_log("GNews API: No response received");
            return $news;
        }
        
        if (isset($response['error'])) {
            error_log("GNews API Error: " . json_encode($response['error']));
            return $news;
        }
        
        if ($response && isset($response['articles'])) {
            error_log("GNews API: Found " . count($response['articles']) . " articles");
            foreach ($response['articles'] as $article) {
                $news[] = [
                    'title' => $article['title'],
                    'content' => $article['description'] ?? '',
                    'category' => 'general', // Assign general category
                    'source' => $article['source']['name'] ?? 'GNews',
                    'publishedAt' => $article['publishedAt'] ?? date('c'),
                    'url' => $article['url'] ?? ''
                ];
            }
        } else {
            error_log("GNews API: No articles array in response");
        }
        
        return $news;
    }
    
    private function fetchLocalNews($zipCode) {
        $news = [];
        
        // Convert zip code to city name for better search results
        $cityName = $this->zipCodeToCity($zipCode);
        $searchQuery = $cityName ? "$cityName local news" : "$zipCode local news";
        
        $url = "https://gnews.io/api/v4/search?" . http_build_query([
            'q' => $searchQuery,
            'lang' => 'en',
            'country' => 'us',
            'max' => 5,
            'apikey' => $this->gnewsKey
        ]);
        
        $response = $this->makeRequest($url);
        
        if ($response && isset($response['articles'])) {
            foreach ($response['articles'] as $article) {
                $news[] = [
                    'title' => $article['title'],
                    'content' => $article['description'] ?? '',
                    'category' => 'local',
                    'source' => $article['source']['name'] ?? 'Local News',
                    'publishedAt' => $article['publishedAt'] ?? date('c'),
                    'url' => $article['url'] ?? ''
                ];
            }
        }
        
        return $news;
    }
    
    private function zipCodeToCity($zipCode) {
        // Simple zip code to city mapping for major US cities
        $zipCityMap = [
            '10001' => 'New York',
            '90210' => 'Beverly Hills',
            '60601' => 'Chicago',
            '77001' => 'Houston',
            '85001' => 'Phoenix',
            '19101' => 'Philadelphia',
            '78701' => 'Austin',
            '94101' => 'San Francisco',
            '98101' => 'Seattle',
            '80201' => 'Denver',
            '33101' => 'Miami',
            '30301' => 'Atlanta',
            '02101' => 'Boston',
            '89101' => 'Las Vegas',
            '20001' => 'Washington DC'
        ];
        
        // Return mapped city or null for generic search
        return $zipCityMap[$zipCode] ?? null;
    }
    
    private function fetchFromNewsAPI($categories) {
        $news = [];
        
        foreach ($categories as $category) {
            $url = "https://newsapi.org/v2/top-headlines?" . http_build_query([
                'category' => $category,
                'language' => 'en',
                'country' => 'us',
                'pageSize' => 10,
                'apiKey' => $this->newsApiKey
            ]);
            
            error_log("NewsAPI URL for category $category: " . $url);
            $response = $this->makeRequest($url);
            
            if ($response === null) {
                error_log("NewsAPI: No response received for category $category");
                continue;
            }
            
            if (isset($response['error'])) {
                error_log("NewsAPI Error for category $category: " . json_encode($response['error']));
                continue;
            }
            
            if ($response && isset($response['articles'])) {
                error_log("NewsAPI: Found " . count($response['articles']) . " articles for category $category");
                foreach ($response['articles'] as $article) {
                    $news[] = [
                        'title' => $article['title'],
                        'content' => $article['description'] ?? '',
                        'category' => $category,
                        'source' => $article['source']['name'] ?? 'NewsAPI',
                        'publishedAt' => $article['publishedAt'] ?? date('c'),
                        'url' => $article['url'] ?? ''
                    ];
                }
            } else {
                error_log("NewsAPI: No articles array in response for category $category");
            }
        }
        
        return $news;
    }
    
    private function fetchFromGuardian($categories) {
        $news = [];
        
        foreach ($categories as $category) {
            $section = $this->mapCategoryToGuardianSection($category);
            $url = "https://content.guardianapis.com/search?" . http_build_query([
                'section' => $section,
                'show-fields' => 'headline,trailText,bodyText',
                'page-size' => 10,
                'api-key' => $this->guardianKey
            ]);
            
            $response = $this->makeRequest($url);
            
            if ($response && isset($response['response']['results'])) {
                foreach ($response['response']['results'] as $article) {
                    $news[] = [
                        'title' => $article['webTitle'],
                        'content' => $article['fields']['trailText'] ?? '',
                        'category' => $category,
                        'source' => 'The Guardian',
                        'publishedAt' => $article['webPublicationDate'] ?? date('c'),
                        'url' => $article['webUrl'] ?? ''
                    ];
                }
            }
        }
        
        return $news;
    }
    
    private function fetchFromNYT($categories) {
        $news = [];
        
        if (!$this->nytKey) {
            error_log("NYT API key is missing or empty");
            return $news;
        }
        
        error_log("NYT API Key present: " . substr($this->nytKey, 0, 10) . "...");
        
        foreach ($categories as $category) {
            $section = $this->mapCategoryToNYTSection($category);
            $url = "https://api.nytimes.com/svc/topstories/v2/{$section}.json?" . http_build_query([
                'api-key' => $this->nytKey
            ]);
            
            error_log("NYT API URL: " . $url);
            $response = $this->makeRequest($url);
            
            if ($response) {
                error_log("NYT API Response received: " . json_encode(array_keys($response)));
                if (isset($response['results'])) {
                    error_log("NYT API found " . count($response['results']) . " results");
                } else {
                    error_log("NYT API response missing 'results' key");
                }
            } else {
                error_log("NYT API response is null or false");
            }
            
            if ($response && isset($response['results'])) {
                $count = 0;
                foreach ($response['results'] as $article) {
                    if ($count >= 10) break;
                    
                    $newsItem = [
                        'title' => $article['title'],
                        'content' => $article['abstract'] ?? '',
                        'category' => $category,
                        'source' => 'New York Times',
                        'publishedAt' => $article['published_date'] ?? date('c'),
                        'url' => $article['url'] ?? ''
                    ];
                    
                    error_log("Adding NYT article: " . $newsItem['title']);
                    $news[] = $newsItem;
                    $count++;
                }
                error_log("Added " . $count . " NYT articles from " . $section . " section");
            }
        }
        
        return $news;
    }
    
    private function mapCategoryToGuardianSection($category) {
        $mapping = [
            'general' => 'world',
            'business' => 'business',
            'entertainment' => 'culture',
            'health' => 'society',
            'science' => 'science',
            'sports' => 'sport',
            'technology' => 'technology'
        ];
        
        return $mapping[$category] ?? 'world';
    }
    
    private function mapCategoryToNYTSection($category) {
        $mapping = [
            'general' => 'world',
            'business' => 'business',
            'entertainment' => 'arts',
            'health' => 'health',
            'science' => 'science',
            'sports' => 'sports',
            'technology' => 'technology'
        ];
        
        return $mapping[$category] ?? 'world';
    }
    
    private function fetchLocalNewsFromRSS($zipCode) {
        try {
            $cityName = $this->getCityFromZip($zipCode);
            $searchQuery = $cityName ? "$cityName local news" : "$zipCode local news";
            
            $encodedQuery = urlencode($searchQuery);
            $rssUrl = "https://news.google.com/rss/search?q={$encodedQuery}&hl=en-US&gl=US&ceid=US:en";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Mozilla/5.0 (compatible; NewsBot/1.0)'
                ]
            ]);
            
            $rssContent = @file_get_contents($rssUrl, false, $context);
            
            if ($rssContent === false) {
                error_log("Failed to fetch RSS content from: $rssUrl");
                return [];
            }
            
            libxml_use_internal_errors(true);
            
            $xml = @simplexml_load_string($rssContent);
            
            // Handle both RSS and Atom formats
            $items = [];
            if ($xml) {
                if (isset($xml->channel->item)) {
                    $items = $xml->channel->item;
                } else if (isset($xml->entry)) {
                    $items = $xml->entry;
                }
            }
            
            if (empty($items)) {
                return [];
            }
            
            $allStories = [];
            
            foreach ($items as $item) {
                $title = isset($item->title) ? (string)$item->title : '';
                $url = '';
                
                // Extract URL
                if (isset($item->link)) {
                    $url = (string)$item->link;
                } else if (isset($item->guid)) {
                    $url = (string)$item->guid;
                }
                
                // Skip weather-only entries and very short titles
                if (stripos($title, 'weather') !== false && strlen($title) < 25) {
                    continue;
                }
                
                // Skip old articles based on content analysis - but be less aggressive
                $isOldNews = false;
                $oldNewsPatterns = [
                    'hurricane florence', 'florence.*flooding', 'cut off.*flooding.*florence',
                    '\b201[0-8]\b', '\b2019\b', '\b2020\b', '\b2021\b', '\b2022\b', '\b2023\b',
                    'years? ago', 'last year', 'previous year'
                ];
                
                foreach ($oldNewsPatterns as $pattern) {
                    if (preg_match('/' . $pattern . '/i', $title)) {
                        $isOldNews = true;
                        break;
                    }
                }
                
                if ($isOldNews) {
                    continue;
                }
                
                // Clean up title and remove news source patterns
                $title = trim($title);
                $title = preg_replace('/ - [A-Za-z\s]+News[A-Za-z\s]*$/', '', $title);
                $title = preg_replace('/ - [A-Za-z\s]+Star[A-Za-z\s]*$/', '', $title);
                $title = preg_replace('/ - [A-Za-z\s]+Times[A-Za-z\s]*$/', '', $title);
                $title = preg_replace('/ - [A-Za-z\s]+Post[A-Za-z\s]*$/', '', $title);
                $title = preg_replace('/ - [A-Za-z\s]+Herald[A-Za-z\s]*$/', '', $title);
                $title = preg_replace('/ - [A-Za-z\s]+Gazette[A-Za-z\s]*$/', '', $title);
                
                // Extract meaningful stories with URLs
                if (strlen($title) > 20) {
                    $allStories[] = [
                        'title' => $title,
                        'url' => $url
                    ];
                }
            }
            
            // Use AI to select the most relevant local news stories
            $selectedStories = $this->selectLocalNewsWithAI($allStories, $cityName);
            
            // Convert to news format
            $news = [];
            foreach ($selectedStories as $index => $story) {
                $news[] = [
                    'title' => $story['title'],
                    'content' => $story['title'],
                    'category' => 'local',
                    'source' => 'Local News',
                    'publishedAt' => date('c'),
                    'url' => $story['url']
                ];
                
                if ($index >= 4) break; // Increase limit to 5 stories
            }
            
            return $news;
            
        } catch (Exception $e) {
            error_log("Local news fetch error: " . $e->getMessage());
            return [];
        }
    }
    
    private function selectLocalNewsWithAI($allStories, $cityName) {
        if (empty($allStories)) {
            return [];
        }
        
        if (count($allStories) >= 1) {
            // Load settings for AI service
            $settings = [];
            if (file_exists('../config/user_settings.json')) {
                $settingsJson = file_get_contents('../config/user_settings.json');
                $settings = json_decode($settingsJson, true) ?: [];
            } else if (file_exists('config/user_settings.json')) {
                $settingsJson = file_get_contents('config/user_settings.json');
                $settings = json_decode($settingsJson, true) ?: [];
            }
            
            // Initialize AI service with fallback priority
            $aiServices = [];
            if (!empty($settings['geminiApiKey']) && $settings['geminiEnabled']) {
                $aiServices[] = 'gemini';
            }
            if (!empty($settings['openaiApiKey']) && $settings['openaiEnabled']) {
                $aiServices[] = 'openai';
            }
            
            if (!empty($aiServices)) {
                require_once 'AIService.php';
                $aiService = new AIService($settings);
                
                // Extract titles for AI selection - take more stories
                $titles = array_map(function($story) { return $story['title']; }, array_slice($allStories, 0, 20));
                $newsListText = implode("\n", $titles);
                $location = $cityName ?: "your local area";
                
                $prompt = "From this list of local news headlines for {$location}, select the 5 most important and relevant LOCAL news stories. Return only the selected headlines, one per line, without numbering or additional text:\n\n{$newsListText}";
                
                // Try each AI service until one works
                foreach ($aiServices as $modelName) {
                    try {
                        $aiResponse = $aiService->generateText($prompt, $modelName);
                        if ($aiResponse) {
                            $selectedTitles = array_filter(explode("\n", trim($aiResponse)));
                            if (count($selectedTitles) >= 1) {
                                // Map selected titles back to story objects with URLs
                                $selectedStories = [];
                                foreach ($selectedTitles as $selectedTitle) {
                                    $selectedTitle = trim($selectedTitle);
                                    $found = false;
                                    
                                    // First try exact match
                                    foreach ($allStories as $story) {
                                        if (trim(strtolower($story['title'])) === trim(strtolower($selectedTitle))) {
                                            $selectedStories[] = $story;
                                            $found = true;
                                            break;
                                        }
                                    }
                                    
                                    // If no exact match, try partial matching
                                    if (!$found) {
                                        foreach ($allStories as $story) {
                                            $storyTitle = trim(strtolower($story['title']));
                                            $searchTitle = trim(strtolower($selectedTitle));
                                            
                                            // Check if 80% of the words match
                                            $storyWords = explode(' ', $storyTitle);
                                            $searchWords = explode(' ', $searchTitle);
                                            $matches = 0;
                                            
                                            foreach ($searchWords as $word) {
                                                if (strlen($word) > 3 && in_array($word, $storyWords)) {
                                                    $matches++;
                                                }
                                            }
                                            
                                            if (count($searchWords) > 0 && ($matches / count($searchWords)) >= 0.6) {
                                                $selectedStories[] = $story;
                                                break;
                                            }
                                        }
                                    }
                                }

                                return array_slice($selectedStories, 0, 5);
                            }
                        }
                    } catch (Exception $e) {
                        // Continue to next AI service
                    }
                }
            }
        }
        
        // Fallback to first 3 stories if AI fails
        return array_slice($allStories, 0, 3);
    }
    
    private function getCityFromZip($zipCode) {
        $zipCityMap = [
            '10001' => 'New York',
            '90210' => 'Beverly Hills',
            '60601' => 'Chicago',
            '77001' => 'Houston',
            '85001' => 'Phoenix',
            '19101' => 'Philadelphia',
            '78701' => 'Austin',
            '94101' => 'San Francisco',
            '98101' => 'Seattle',
            '80201' => 'Denver',
            '33101' => 'Miami',
            '30301' => 'Atlanta',
            '02101' => 'Boston',
            '89101' => 'Las Vegas',
            '20001' => 'Washington DC',
            '28411' => 'Wilmington'
        ];
        
        return $zipCityMap[$zipCode] ?? null;
    }

    private function makeRequest($url) {
        // Use cURL for better compatibility
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'NewsBot/1.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($error)) {
            throw new Exception("Failed to fetch from URL: $url. CURL Error: $error");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error $httpCode for URL: $url. Response: $response");
        }
        
        $decoded = json_decode($response, true);
        
        // Check for API error responses
        if (isset($decoded['errors'])) {
            throw new Exception("API Error: " . implode(', ', $decoded['errors']));
        }
        
        return $decoded;
    }
}
?>