<?php

class WeatherService {
    private $apiKey;
    
    public function __construct($settings = null) {
        if ($settings) {
            $this->apiKey = ($settings['weatherEnabled'] ?? true) ? ($settings['weatherApiKey'] ?: getenv('WEATHER_API_KEY')) : null;
        } else {
            $this->apiKey = getenv('WEATHER_API_KEY');
        }
    }
    
    public function getWeather($zipCode) {
        try {
            // Use OpenWeatherMap API
            $url = "https://api.openweathermap.org/data/2.5/weather?" . http_build_query([
                'zip' => $zipCode . ',US',
                'appid' => $this->apiKey,
                'units' => 'imperial'
            ]);
            
            $response = $this->makeRequest($url);
            
            if ($response && isset($response['main'])) {
                return $this->formatWeatherReport($response);
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Weather fetch error: " . $e->getMessage());
            return null;
        }
    }
    
    private function formatWeatherReport($data) {
        $location = $data['name'] ?? 'your area';
        $temp = round($data['main']['temp']) ?? 'unknown';
        $feelsLike = round($data['main']['feels_like']) ?? 'unknown';
        $humidity = $data['main']['humidity'] ?? 'unknown';
        $description = $data['weather'][0]['description'] ?? 'unknown conditions';
        $windSpeed = round($data['wind']['speed'] ?? 0);
        
        $report = "Current weather in {$location}: ";
        $report .= "It's {$temp} degrees Fahrenheit with {$description}. ";
        $report .= "It feels like {$feelsLike} degrees. ";
        $report .= "Humidity is at {$humidity} percent";
        
        if ($windSpeed > 0) {
            $report .= " with winds at {$windSpeed} miles per hour";
        }
        
        $report .= ".";
        
        // Add weather-appropriate clothing suggestion
        if ($temp < 40) {
            $report .= " Bundle up if you're heading outside.";
        } elseif ($temp > 80) {
            $report .= " Stay cool and hydrated.";
        } elseif (strpos(strtolower($description), 'rain') !== false) {
            $report .= " Don't forget your umbrella.";
        }
        
        return $report;
    }
    
    private function makeRequest($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'NewsBrief/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            error_log("Weather API cURL error: " . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Weather API HTTP error: {$httpCode}");
            return null;
        }
        
        return json_decode($response, true);
    }
    
    public function getWeatherBriefing($zipCode) {
        error_log("WeatherService: getWeatherBriefing called with zip: " . ($zipCode ?? 'null'));
        error_log("WeatherService: API key available: " . ($this->apiKey ? 'YES' : 'NO'));
        
        if (!$zipCode) {
            error_log("WeatherService: No zip code provided");
            return [];
        }
        
        if (!$this->apiKey) {
            error_log("WeatherService: No API key available");
            return [];
        }
        
        $weather = $this->getWeather($zipCode);
        if (!$weather) {
            error_log("WeatherService: getWeather returned null");
            return [];
        }
        
        error_log("WeatherService: Successfully generated weather briefing");
        return [[
            'title' => 'Local Weather Update',
            'content' => $weather,
            'category' => 'weather',
            'source' => 'Weather Service',
            'publishedAt' => date('c')
        ]];
    }
}
?>
