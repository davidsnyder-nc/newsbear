<?php

class RSSFeedHandler {
    private $configFile;
    
    public function __construct($configFile = null) {
        if ($configFile === null) {
            // Determine correct path based on current directory
            $configFile = file_exists('config/user_settings.json') 
                ? 'config/user_settings.json' 
                : '../config/user_settings.json';
        }
        $this->configFile = $configFile;
    }
    
    public function getRssFeeds() {
        if (!file_exists($this->configFile)) {
            return [];
        }
        
        $settings = json_decode(file_get_contents($this->configFile), true);
        return $settings['rssFeeds'] ?? [];
    }
    
    public function fetchRssFeed($feedUrl) {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'NewsBear RSS Reader/1.0'
                ]
            ]);
            
            $rssContent = file_get_contents($feedUrl, false, $context);
            if ($rssContent === false) {
                return false;
            }
            
            $xml = simplexml_load_string($rssContent);
            if ($xml === false) {
                return false;
            }
            
            return $xml;
        } catch (Exception $e) {
            error_log("RSS Feed Error for $feedUrl: " . $e->getMessage());
            return false;
        }
    }
    
    public function parseRssItems($xml, $feedConfig) {
        $articles = [];
        
        try {
            // Handle RSS 2.0 format
            if (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    $article = $this->parseRssItem($item, $feedConfig);
                    if ($article) {
                        $articles[] = $article;
                    }
                }
            }
            // Handle Atom format
            elseif (isset($xml->entry)) {
                foreach ($xml->entry as $entry) {
                    $article = $this->parseAtomEntry($entry, $feedConfig);
                    if ($article) {
                        $articles[] = $article;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("RSS Parse Error: " . $e->getMessage());
        }
        
        return $articles;
    }
    
    private function parseRssItem($item, $feedConfig) {
        try {
            $title = (string)$item->title;
            $description = (string)$item->description;
            $link = (string)$item->link;
            $pubDate = (string)$item->pubDate;
            
            if (empty($title) || empty($link)) {
                return null;
            }
            
            // Clean up description (remove HTML tags)
            $content = strip_tags($description);
            $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
            
            // Determine category
            $category = $feedConfig['category'];
            if ($category === 'custom' && !empty($feedConfig['customCategory'])) {
                $category = $feedConfig['customCategory'];
            }
            
            return [
                'title' => $title,
                'description' => $content,
                'content' => $content,
                'url' => $link,
                'source' => $feedConfig['name'],
                'category' => $category,
                'publishedAt' => $this->parseDate($pubDate),
                'urlToImage' => $this->extractImageFromContent($description),
                'isRss' => true
            ];
        } catch (Exception $e) {
            error_log("RSS Item Parse Error: " . $e->getMessage());
            return null;
        }
    }
    
    private function parseAtomEntry($entry, $feedConfig) {
        try {
            $title = (string)$entry->title;
            $summary = (string)$entry->summary;
            $content = isset($entry->content) ? (string)$entry->content : $summary;
            
            // Get link
            $link = '';
            if (isset($entry->link)) {
                if (is_object($entry->link) && isset($entry->link['href'])) {
                    $link = (string)$entry->link['href'];
                } else {
                    $link = (string)$entry->link;
                }
            }
            
            $updated = (string)$entry->updated;
            
            if (empty($title) || empty($link)) {
                return null;
            }
            
            // Clean up content
            $cleanContent = strip_tags($content);
            $cleanContent = html_entity_decode($cleanContent, ENT_QUOTES, 'UTF-8');
            
            // Determine category
            $category = $feedConfig['category'];
            if ($category === 'custom' && !empty($feedConfig['customCategory'])) {
                $category = $feedConfig['customCategory'];
            }
            
            return [
                'title' => $title,
                'description' => $cleanContent,
                'content' => $cleanContent,
                'url' => $link,
                'source' => $feedConfig['name'],
                'category' => $category,
                'publishedAt' => $this->parseDate($updated),
                'urlToImage' => $this->extractImageFromContent($content),
                'isRss' => true
            ];
        } catch (Exception $e) {
            error_log("Atom Entry Parse Error: " . $e->getMessage());
            return null;
        }
    }
    
    private function parseDate($dateString) {
        if (empty($dateString)) {
            return date('c');
        }
        
        try {
            $timestamp = strtotime($dateString);
            if ($timestamp === false) {
                return date('c');
            }
            return date('c', $timestamp);
        } catch (Exception $e) {
            return date('c');
        }
    }
    
    private function extractImageFromContent($content) {
        // Try to extract image URL from content
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            return $matches[1];
        }
        
        // Try to find image in media:thumbnail or enclosure
        if (preg_match('/url=["\']([^"\']+\.(jpg|jpeg|png|gif|webp))["\'][^>]*>/i', $content, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    public function getAllRssArticles($maxArticlesPerFeed = 10) {
        $allArticles = [];
        $rssFeeds = $this->getRssFeeds();
        
        foreach ($rssFeeds as $feedConfig) {
            $xml = $this->fetchRssFeed($feedConfig['url']);
            if ($xml) {
                $articles = $this->parseRssItems($xml, $feedConfig);
                
                // Limit articles per feed
                $articles = array_slice($articles, 0, $maxArticlesPerFeed);
                $allArticles = array_merge($allArticles, $articles);
            }
        }
        
        // Sort by publication date (newest first)
        usort($allArticles, function($a, $b) {
            return strtotime($b['publishedAt']) - strtotime($a['publishedAt']);
        });
        
        return $allArticles;
    }
    
    public function getCustomCategories() {
        $categories = [];
        $rssFeeds = $this->getRssFeeds();
        
        // Standard news categories
        $standardCategories = ['general', 'business', 'entertainment', 'health', 'science', 'sports', 'technology'];
        
        foreach ($rssFeeds as $feed) {
            $category = $feed['category'];
            
            // If it's not a standard category, it's a custom category
            if (!in_array(strtolower($category), array_map('strtolower', $standardCategories)) && !in_array($category, $categories)) {
                $categories[] = $category;
            }
            
            // Also check for explicit custom categories
            if ($category === 'custom' && !empty($feed['customCategory'])) {
                $categoryName = $feed['customCategory'];
                if (!in_array($categoryName, $categories)) {
                    $categories[] = $categoryName;
                }
            }
        }
        
        return $categories;
    }
}