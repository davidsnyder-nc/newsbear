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
        require_once __DIR__ . '/APIRateLimit.php';
        $rateLimit = new APIRateLimit();
        
        $allNews = [];
        $apiStatus = [];
        
        // Fetch local news FIRST if requested and zip code provided
        if ($includeLocal && $zipCode) {
            try {
                $localNews = $this->fetchLocalNewsFromRSS($zipCode);
                $allNews = array_merge($allNews, $localNews);
                error_log("Local news fetch: Found " . count($localNews) . " local articles (added first)");
                $apiStatus['local'] = 'success';
            } catch (Exception $e) {
                error_log("Local news fetch error: " . $e->getMessage());
                $apiStatus['local'] = 'failed: ' . $e->getMessage();
            }
        }
        
        // Fetch from RSS feeds only if any are configured
        try {
            require_once __DIR__ . '/RSSFeedHandler.php';
            $rssHandler = new RSSFeedHandler();
            $rssFeeds = $rssHandler->getRssFeeds();
            
            if (!empty($rssFeeds)) {
                // Filter only enabled feeds
                $enabledFeeds = array_filter($rssFeeds, function($feed) {
                    return isset($feed['enabled']) && $feed['enabled'] === true;
                });
                
                if (!empty($enabledFeeds)) {
                    $rssNews = $rssHandler->getAllRssArticles(10);
                    $allNews = array_merge($allNews, $rssNews);
                    error_log("RSS fetch: Found " . count($rssNews) . " articles from " . count($enabledFeeds) . " enabled RSS feeds");
                } else {
                    error_log("RSS fetch: No enabled RSS feeds found, skipping");
                }
            } else {
                error_log("RSS fetch: No RSS feeds configured, skipping");
            }
        } catch (Exception $e) {
            error_log("RSS feed fetch error: " . $e->getMessage());
        }
        
        // Fetch from main news APIs for any supported categories (not just general)
        $supportedCategories = ['general', 'business', 'entertainment', 'health', 'science', 'sports', 'technology'];
        $shouldFetchFromMainAPIs = !empty(array_intersect($categories, $supportedCategories));
        error_log("Should fetch from main APIs: " . ($shouldFetchFromMainAPIs ? "YES" : "NO") . " - Categories: " . implode(', ', $categories));
        
        // Fetch from each enabled source for supported categories
        if ($this->gnewsKey && $shouldFetchFromMainAPIs) {
            if ($rateLimit->isAPIPaused('gnews')) {
                $apiStatus['gnews'] = 'paused due to rate limit';
                error_log("GNews: Skipped - API is paused");
            } else {
                try {
                    $gnewsArticles = $this->fetchFromGNews($categories);
                    $filteredArticles = $this->filterByDate($gnewsArticles);
                    error_log("GNews fetch: Retrieved " . count($gnewsArticles) . " articles, " . count($filteredArticles) . " after date filtering");
                    $allNews = array_merge($allNews, $filteredArticles);
                    $apiStatus['gnews'] = count($filteredArticles) > 0 ? 'success' : 'no results';
                    if (count($filteredArticles) > 0) {
                        $rateLimit->recordSuccess('gnews');
                    }
                } catch (Exception $e) {
                    error_log("GNews fetch error: " . $e->getMessage());
                    $apiStatus['gnews'] = 'failed: ' . $e->getMessage();
                    $rateLimit->recordFailure('gnews', $e->getMessage());
                }
            }
        } else {
            $apiStatus['gnews'] = !$this->gnewsKey ? 'no API key' : 'categories not supported';
        }
        
        if ($this->newsApiKey && $shouldFetchFromMainAPIs) {
            if ($rateLimit->isAPIPaused('newsapi')) {
                $apiStatus['newsapi'] = 'paused due to rate limit';
                error_log("NewsAPI: Skipped - API is paused");
            } else {
                try {
                    $newsApiArticles = $this->fetchFromNewsAPI($categories);
                    $filteredArticles = $this->filterByDate($newsApiArticles);
                    error_log("NewsAPI fetch: Retrieved " . count($newsApiArticles) . " articles, " . count($filteredArticles) . " after date filtering");
                    $allNews = array_merge($allNews, $filteredArticles);
                    $apiStatus['newsapi'] = count($filteredArticles) > 0 ? 'success' : 'no results';
                    if (count($filteredArticles) > 0) {
                        $rateLimit->recordSuccess('newsapi');
                    }
                } catch (Exception $e) {
                    error_log("NewsAPI fetch error: " . $e->getMessage());
                    $apiStatus['newsapi'] = 'failed: ' . $e->getMessage();
                    $rateLimit->recordFailure('newsapi', $e->getMessage());
                }
            }
        } else {
            $apiStatus['newsapi'] = !$this->newsApiKey ? 'no API key' : 'categories not supported';
        }
        
        if ($this->guardianKey && $shouldFetchFromMainAPIs) {
            if ($rateLimit->isAPIPaused('guardian')) {
                $apiStatus['guardian'] = 'paused due to rate limit';
                error_log("Guardian: Skipped - API is paused");
            } else {
                try {
                    $guardianArticles = $this->fetchFromGuardian($categories);
                    $filteredArticles = $this->filterByDate($guardianArticles);
                    error_log("Guardian fetch: Retrieved " . count($guardianArticles) . " articles, " . count($filteredArticles) . " after date filtering");
                    $allNews = array_merge($allNews, $filteredArticles);
                    $apiStatus['guardian'] = count($filteredArticles) > 0 ? 'success' : 'no results';
                    if (count($filteredArticles) > 0) {
                        $rateLimit->recordSuccess('guardian');
                    }
                } catch (Exception $e) {
                    error_log("Guardian fetch error: " . $e->getMessage());
                    $apiStatus['guardian'] = 'failed: ' . $e->getMessage();
                    $rateLimit->recordFailure('guardian', $e->getMessage());
                }
            }
        } else {
            $apiStatus['guardian'] = !$this->guardianKey ? 'no API key' : 'categories not supported';
        }
        
        if ($this->nytKey && $shouldFetchFromMainAPIs) {
            if ($rateLimit->isAPIPaused('nyt')) {
                $apiStatus['nyt'] = 'paused due to rate limit';
                error_log("NYT: Skipped - API is paused");
            } else {
                try {
                    $nytNews = $this->fetchFromNYT($categories);
                    error_log("NYT fetch returned " . count($nytNews) . " articles");
                    $allNews = array_merge($allNews, $nytNews);
                    $apiStatus['nyt'] = count($nytNews) > 0 ? 'success' : 'no results';
                    if (count($nytNews) > 0) {
                        $rateLimit->recordSuccess('nyt');
                    }
                } catch (Exception $e) {
                    error_log("NYT fetch error: " . $e->getMessage());
                    $apiStatus['nyt'] = 'failed: ' . $e->getMessage();
                    $rateLimit->recordFailure('nyt', $e->getMessage());
                }
            }
        } else {
            $apiStatus['nyt'] = !$this->nytKey ? 'no API key' : 'categories not supported';
        }
        
        // AI categorization is now handled in generate.php after all sources are fetched
        
        // Log comprehensive API status report
        // Enhanced logging with session ID if available
        $sessionId = $_POST['session_id'] ?? null;
        
        if ($sessionId) {
            $this->debugLog("=== API STATUS REPORT ===", $sessionId);
        }
        error_log("=== API STATUS REPORT ===");
        foreach ($apiStatus as $api => $status) {
            error_log("$api: $status");
        }
        error_log("Total news articles fetched: " . count($allNews));
        error_log("========================");
        
        // If no articles were fetched, provide detailed error information
        if (empty($allNews)) {
            $failureReasons = [];
            $workingAPIs = [];
            $rateLimitedAPIs = [];
            
            foreach ($apiStatus as $api => $status) {
                if ($status === 'success') {
                    $workingAPIs[] = $api;
                } elseif (strpos($status, 'Rate limit exceeded') !== false) {
                    $rateLimitedAPIs[] = $api;
                } elseif (strpos($status, 'failed:') !== false) {
                    $failureReasons[] = "$api: " . str_replace('failed: ', '', $status);
                }
            }
            
            if (!empty($rateLimitedAPIs)) {
                throw new Exception('Rate limits exceeded for: ' . implode(', ', $rateLimitedAPIs) . '. Please wait before trying again or check your API usage limits.');
            } elseif (!empty($failureReasons)) {
                throw new Exception('API failures detected: ' . implode('; ', $failureReasons));
            } else {
                throw new Exception('No news content available from any enabled sources. Check your internet connection and API keys.');
            }
        }
        
        return $allNews;
    }
    
    /**
     * Filter articles by date to ensure freshness based on user setting
     */
    private function filterByDate($articles, $maxHoursOld = null) {
        // Use setting from user preferences if not specified
        if ($maxHoursOld === null) {
            $maxHoursOld = $this->settings['newsTimeframe'] ?? 24;
        }
        
        $cutoffTime = time() - ($maxHoursOld * 3600);
        $freshArticles = [];
        
        foreach ($articles as $article) {
            $articleTime = null;
            
            // Try to parse various date formats
            if (isset($article['publishedAt'])) {
                $articleTime = strtotime($article['publishedAt']);
            } elseif (isset($article['webPublicationDate'])) {
                $articleTime = strtotime($article['webPublicationDate']);
            } elseif (isset($article['published_date'])) {
                $articleTime = strtotime($article['published_date']);
            } elseif (isset($article['pub_date'])) {
                $articleTime = strtotime($article['pub_date']);
            }
            
            // If we can't determine the date, assume it's old and filter it out
            if ($articleTime && $articleTime >= $cutoffTime) {
                $freshArticles[] = $article;
            } else {
                $hoursOld = $articleTime ? round((time() - $articleTime) / 3600, 1) : 'unknown';
                error_log("FILTERED OLD ARTICLE: " . ($article['title'] ?? 'Unknown') . " - {$hoursOld} hours old (limit: {$maxHoursOld}h)");
            }
        }
        
        error_log("Date filtering: " . count($freshArticles) . " fresh articles from " . count($articles) . " total (within {$maxHoursOld} hours)");
        return $freshArticles;
    }
    
    private function fetchFromGNews($categories) {
        $news = [];
        
        // Validate API key exists
        if (empty($this->gnewsKey)) {
            throw new Exception("GNews API key is not configured");
        }
        
        // Fetch articles for each requested category
        foreach ($categories as $category) {
            $categoryNews = $this->fetchGNewsByCategory($category);
            $news = array_merge($news, $categoryNews);
        }
        
        return $news;
    }
    
    private function fetchGNewsByCategory($category) {
        $news = [];
        
        // Map categories to GNews search terms
        $searchTerms = [
            'general' => 'breaking news',
            'business' => 'business finance economy',
            'entertainment' => 'entertainment celebrity movies',
            'health' => 'health medical healthcare',
            'science' => 'science research technology',
            'sports' => 'sports games tournament championship',
            'technology' => 'technology tech innovation'
        ];
        
        $searchTerm = $searchTerms[$category] ?? 'news';
        
        $url = "https://gnews.io/api/v4/search?" . http_build_query([
            'q' => $searchTerm,
            'lang' => 'en',
            'country' => 'us',
            'max' => 10,
            'apikey' => $this->gnewsKey
        ]);
        
        error_log("GNews API URL for {$category}: " . $url);
        $response = $this->makeRequest($url);
        
        if ($response === null) {
            error_log("GNews API: No response received for {$category}");
            return $news;
        }
        
        if (isset($response['error'])) {
            error_log("GNews API Error for {$category}: " . json_encode($response['error']));
            return $news;
        }
        
        if ($response && isset($response['articles'])) {
            error_log("GNews API: Found " . count($response['articles']) . " {$category} articles");
            foreach ($response['articles'] as $article) {
                $news[] = [
                    'title' => $article['title'],
                    'content' => $article['description'] ?? '',
                    'category' => ucfirst($category),
                    'source' => $article['source']['name'] ?? 'GNews',
                    'publishedAt' => $article['publishedAt'] ?? date('c'),
                    'url' => $article['url'] ?? ''
                ];
            }
        } else {
            error_log("GNews API: No articles array in response for {$category}");
        }
        
        return $news;
    }
    
    public function fetchLocalNews($zipCode) {
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Reduced to 15 seconds
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
            // Log specific error details for debugging
            error_log("API Error - HTTP $httpCode for $url");
            error_log("Response: " . substr($response, 0, 500));
            
            // Check for rate limit errors
            if ($httpCode === 429) {
                throw new Exception("Rate limit exceeded for API");
            } elseif ($httpCode === 401) {
                throw new Exception("API key invalid or unauthorized");
            } elseif ($httpCode === 403) {
                throw new Exception("API access forbidden - check subscription");
            } else {
                throw new Exception("HTTP Error $httpCode for API");
            }
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