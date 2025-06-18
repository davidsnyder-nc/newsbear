<?php

class TMDBService {
    private $apiKey;
    private $baseUrl = 'https://api.themoviedb.org/3';
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }
    
    public function getTVContent() {
        if (empty($this->apiKey)) {
            return null;
        }
        
        $content = [];
        
        // Get trending TV shows today
        $trendingTV = $this->makeRequest('/trending/tv/day');
        if ($trendingTV && isset($trendingTV['results'])) {
            $content['trending_tv'] = array_slice($trendingTV['results'], 0, 5);
        }
        
        // Get popular movies
        $popularMovies = $this->makeRequest('/movie/popular');
        if ($popularMovies && isset($popularMovies['results'])) {
            $content['popular_movies'] = array_slice($popularMovies['results'], 0, 5);
        }
        
        // Get TV shows airing today - will filter later in formatting
        $airingToday = $this->makeRequest('/tv/airing_today');
        if ($airingToday && isset($airingToday['results'])) {
            $content['airing_today'] = array_slice($airingToday['results'], 0, 10);
        }
        
        // Get upcoming movies with future dates
        $upcomingMovies = $this->makeRequest('/movie/upcoming');
        if ($upcomingMovies && isset($upcomingMovies['results'])) {
            // Get more results to filter from
            $content['upcoming_movies'] = array_slice($upcomingMovies['results'], 0, 20);
        }
        
        // Also try discover endpoint for future releases
        $futureDate = date('Y-m-d', strtotime('+1 day'));
        $endDate = date('Y-m-d', strtotime('+6 months'));
        $discoverMovies = $this->makeRequest("/discover/movie?primary_release_date.gte={$futureDate}&primary_release_date.lte={$endDate}&sort_by=popularity.desc");
        if ($discoverMovies && isset($discoverMovies['results'])) {
            // Merge with upcoming movies
            $existingMovies = $content['upcoming_movies'] ?? [];
            $newMovies = array_slice($discoverMovies['results'], 0, 10);
            $content['upcoming_movies'] = array_merge($existingMovies, $newMovies);
        }
        
        return $content;
    }
    
    public function formatForBriefing($tvContent) {
        if (!$tvContent) {
            return '';
        }
        
        $briefing = "";
        $segments = [];
        
        // Trending TV Shows - conversational format
        if (isset($tvContent['trending_tv']) && !empty($tvContent['trending_tv'])) {
            $englishShows = array_filter($tvContent['trending_tv'], function($show) {
                return $this->isEnglishContent($show);
            });
            $shows = array_slice($englishShows, 0, 3);
            $showNames = array_map(function($show) {
                return $show['name'] ?? 'Unknown Show';
            }, $shows);
            
            if (count($showNames) > 0) {
                $segments[] = "In trending television, " . $this->formatNaturalList($showNames) . " are capturing audiences today.";
            }
        }
        
        // Shows Airing Today - filter and format naturally
        if (isset($tvContent['airing_today']) && !empty($tvContent['airing_today'])) {
            $significantShows = array_filter($tvContent['airing_today'], function($show) {
                $name = strtolower($show['name'] ?? '');
                $language = $show['original_language'] ?? 'en';
                return !$this->isDailyShow($name) && $this->isEnglishContent($show);
            });
            
            if (!empty($significantShows)) {
                $showNames = array_map(function($show) {
                    return $show['name'] ?? 'Unknown Show';
                }, array_slice($significantShows, 0, 2));
                
                if (count($showNames) > 0) {
                    $segments[] = "Tonight, " . $this->formatNaturalList($showNames) . " premiere" . (count($showNames) === 1 ? 's' : '') . " with new episodes.";
                }
            }
        }
        
        // Popular Movies - conversational format
        if (isset($tvContent['popular_movies']) && !empty($tvContent['popular_movies'])) {
            $englishMovies = array_filter($tvContent['popular_movies'], function($movie) {
                return $this->isEnglishContent($movie);
            });
            $movies = array_slice($englishMovies, 0, 3);
            $movieTitles = array_map(function($movie) {
                return $movie['title'] ?? 'Unknown Movie';
            }, $movies);
            
            if (count($movieTitles) > 0) {
                $segments[] = "In theaters and streaming, " . $this->formatNaturalList($movieTitles) . " continue to draw viewers.";
            }
        }
        
        // Upcoming Movies - only include movies with future release dates
        if (isset($tvContent['upcoming_movies']) && !empty($tvContent['upcoming_movies'])) {
            $upcoming = array_slice($tvContent['upcoming_movies'], 0, 10); // Get more to filter from
            $releaseInfo = [];
            $today = date('Y-m-d'); // Use date string comparison for accuracy
            
            foreach ($upcoming as $movie) {
                $title = $movie['title'] ?? 'Unknown Movie';
                $releaseDate = $movie['release_date'] ?? '';
                
                if ($releaseDate && $releaseDate > $today) {
                    // Only include movies with future release dates
                    $formattedDate = date('M j', strtotime($releaseDate));
                    $releaseInfo[] = $title . " on " . $formattedDate;
                }
            }
            
            // Only add upcoming movies section if we have actual future releases
            if (!empty($releaseInfo)) {
                $releaseInfo = array_slice($releaseInfo, 0, 2); // Limit to 2 upcoming movies
                $segments[] = "Coming soon to theaters: " . $this->formatNaturalList($releaseInfo) . ".";
            }
        }
        
        return implode(" ", $segments);
    }
    
    private function filterSignificantShows($shows) {
        return array_filter($shows, function($show) {
            $name = strtolower($show['name'] ?? '');
            return !$this->isDailyShow($name);
        });
    }
    
    private function isDailyShow($showName) {
        $dailyKeywords = [
            'news', 'tonight show', 'late night', 'morning', 'good morning',
            'the view', 'jeopardy', 'wheel of fortune', 'price is right',
            'talk show', 'daily', 'live', 'today', 'this morning',
            'late show', 'jimmy', 'stephen colbert', 'saturday night live',
            'snl', 'ellen', 'oprah', 'dr. phil', 'family feud',
            'game show', 'quiz show', 'soap opera', 'general hospital',
            'days of our lives', 'young and the restless', 'bold and beautiful'
        ];
        
        foreach ($dailyKeywords as $keyword) {
            if (strpos($showName, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isEnglishContent($item) {
        $language = $item['original_language'] ?? '';
        
        // Only include English content
        if ($language === 'en') {
            return true;
        }
        
        // Also check for common English-speaking country codes
        $englishCountries = ['US', 'GB', 'CA', 'AU', 'NZ', 'IE'];
        $originCountry = $item['origin_country'][0] ?? '';
        
        return in_array($originCountry, $englishCountries);
    }
    
    private function formatNaturalList($items) {
        if (empty($items)) {
            return '';
        }
        
        if (count($items) === 1) {
            return $items[0];
        }
        
        if (count($items) === 2) {
            return $items[0] . " and " . $items[1];
        }
        
        $lastItem = array_pop($items);
        return implode(", ", $items) . ", and " . $lastItem;
    }
    
    private function makeRequest($endpoint) {
        $url = $this->baseUrl . $endpoint . '?api_key=' . $this->apiKey;
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'NewsBear/1.0'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return null;
        }
        
        return json_decode($response, true);
    }
}
?>