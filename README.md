# NewsBear 🐻 - AI-Powered News Briefing Application

NewsBear is a cutting-edge personalized news briefing application that leverages advanced AI technologies to create intelligent, adaptive audio and text reports tailored to user preferences.

## Features

### 🎯 Core Functionality
- **Personalized News Briefings**: Generate custom news reports based on your preferences
- **Audio Generation**: Convert text briefings to high-quality MP3 audio files
- **Multi-Source Integration**: Aggregate news from multiple authentic sources
- **Smart Content Filtering**: AI-powered article selection and relevance ranking
- **Local News Integration**: Automatic local news from RSS feeds based on ZIP code
- **Weather Integration**: Current weather conditions and forecasts
- **Entertainment Content**: TV shows, movies, and entertainment news

### 🤖 AI-Powered Features
- **Zero Synthetic Content Policy**: All news content sourced from authentic APIs only
- **Intelligent Article Selection**: AI chooses the most relevant stories
- **Natural Language Generation**: Professional news script creation
- **Smart Briefing History**: Prevents topic repetition across time periods
- **Fuzzy Matching**: Advanced local news story matching and selection

### 🎵 Audio Features
- **High-Quality TTS**: Google Text-to-Speech integration with Neural2-D voice
- **Professional Audio Settings**: 24kHz sample rate, optimized for news delivery
- **In-Browser Playback**: Built-in audio player for immediate listening
- **Downloadable MP3s**: Save briefings for offline listening
- **Retroactive Audio Generation**: Convert existing text briefings to audio

### 📱 User Interface
- **Responsive Design**: Works seamlessly on desktop and mobile
- **Clean, Modern Interface**: Tailwind CSS with NewsBear branding
- **Intuitive Navigation**: Easy-to-use settings and history management
- **Progress Tracking**: Real-time generation status with progress indicators
- **Smart Button Management**: Dynamic interface based on content state

## Supported APIs

### News Sources
- **GNews API**: Global news aggregation
- **NewsAPI.org**: Comprehensive news source
- **The Guardian API**: Quality journalism
- **New York Times API**: Premium news content
- **Local RSS Feeds**: Automatic local news discovery

### AI Services
- **OpenAI GPT**: Advanced language processing
- **Google Gemini**: Intelligent content generation
- **Claude (Anthropic)**: Professional text generation

### Other Integrations
- **OpenWeatherMap**: Weather data and forecasts
- **TMDB (The Movie Database)**: Entertainment content
- **Google Text-to-Speech**: High-quality audio generation

## Installation

1. **Clone the repository**
```bash
git clone https://github.com/yourusername/newsbear.git
cd newsbear
```

2. **Set up a PHP server**
```bash
# Using PHP built-in server
php -S 0.0.0.0:5000

# Or use Apache/Nginx with document root pointing to project directory
```

3. **Configure directories**
Ensure these directories exist and are writable:
```
data/
data/history/
downloads/
config/
```

4. **Set up API keys**
Navigate to Settings and configure your API keys for the services you want to use.

## Configuration

### Required API Keys
To use NewsBear, you'll need API keys from the services you want to enable:

#### News APIs (choose one or more)
- **GNews**: Free tier available at [gnews.io](https://gnews.io)
- **NewsAPI**: Free tier at [newsapi.org](https://newsapi.org/register)
- **Guardian**: Free at [open-platform.theguardian.com](https://open-platform.theguardian.com/access/)
- **NY Times**: Free tier at [developer.nytimes.com](https://developer.nytimes.com/get-started)

#### AI Services (choose one or more)
- **OpenAI**: API key from [platform.openai.com](https://platform.openai.com/api-keys)
- **Google Gemini**: Free API key from [Google AI Studio](https://makersuite.google.com/app/apikey)
- **Claude**: API key from [console.anthropic.com](https://console.anthropic.com/)

#### Optional Services
- **OpenWeatherMap**: Free tier at [openweathermap.org](https://openweathermap.org/api)
- **TMDB**: Free at [themoviedb.org](https://www.themoviedb.org/settings/api)
- **Google TTS**: Use same key as Gemini or separate key

### Settings Configuration

Access the Settings page to configure:

#### Basic Settings
- **Time Frame**: Auto, Morning, Afternoon, Evening
- **Audio Length**: 3-5, 5-10, 10-15, or 15-20 minutes
- **ZIP Code**: For local news and weather
- **Content Types**: Weather, Local News, TV/Movies
- **Audio Generation**: Enable/disable MP3 creation

#### Advanced Settings
- **AI Service Selection**: Choose different AI services for article selection vs content generation
- **Custom Prompts**: Customize AI behavior for each service
- **Blocked Terms**: Filter out unwanted content
- **News Categories**: Select preferred news topics

## Usage

### Generating a Briefing

1. **Configure Settings**: Set up your API keys and preferences
2. **Generate**: Click "Generate News Briefing" on the main page
3. **Wait**: Watch the progress indicator as content is gathered and processed
4. **Enjoy**: Listen to your audio briefing or read the text version

### Managing History

- **View Past Briefings**: Access the History page to see all previous briefings
- **Replay Audio**: Click play buttons to listen to past briefings
- **Generate Audio**: Convert text-only briefings to MP3 format
- **Smart Filtering**: System prevents topic repetition across different time periods

### Starting Fresh

- **New Button**: After generating a briefing, use the "New" button to start over
- **Reset Interface**: Automatically returns to the generation interface

## File Structure

```
newsbear/
├── api/                    # API endpoints
│   ├── generate.php       # Main briefing generation
│   ├── generate_audio.php # Audio-only generation
│   └── status.php         # Generation status checking
├── includes/              # Core classes
│   ├── NewsAPI.php       # News source integration
│   ├── AIService.php     # AI service management
│   ├── TMDBService.php   # Entertainment content
│   ├── TTSService.php    # Text-to-speech conversion
│   └── BriefingHistory.php # History management
├── config/               # Configuration files
│   └── user_settings.json # User preferences
├── data/                 # Application data
│   └── history/          # Briefing history storage
├── downloads/            # Generated audio files
├── attached_assets/      # Images and logos
├── index.php            # Main application page
├── settings.php         # Settings management
├── history.php          # History viewer
├── style.css           # Application styling
└── script.js           # Frontend JavaScript
```

## Technical Details

### Architecture
- **Backend**: PHP with modular class structure
- **Frontend**: Vanilla JavaScript with Tailwind CSS
- **Storage**: Flat file system for simplicity and portability
- **APIs**: RESTful endpoints for generation and status

### Data Flow
1. **Collection**: Gather content from enabled news APIs
2. **Validation**: Ensure all content is from authentic sources
3. **Selection**: AI chooses most relevant articles
4. **Generation**: AI creates natural news script
5. **Audio**: Convert to speech using Google TTS
6. **Storage**: Save text and audio with source tracking

### Security Features
- **Source Validation**: Strict checking of content authenticity
- **API Key Protection**: Secure storage and transmission
- **Input Sanitization**: Proper handling of user inputs
- **Rate Limiting**: Respectful API usage patterns

## Development

### Contributing
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

### Code Style
- Follow PSR-4 autoloading standards
- Use descriptive variable and function names
- Comment complex logic thoroughly
- Maintain the zero synthetic content policy

### Testing
- Test with different API key combinations
- Verify audio generation quality
- Check responsive design on various devices
- Validate news source authenticity

## Troubleshooting

### Common Issues

**No content generated**
- Check API key configuration
- Verify internet connectivity
- Ensure at least one news source is enabled

**Audio generation fails**
- Confirm Google TTS API key is valid
- Check file permissions in downloads directory
- Verify sufficient disk space

**Local news not appearing**
- Ensure ZIP code is configured correctly
- Check that local news option is enabled
- Verify RSS feeds are accessible

### Support
For issues and questions:
1. Check the troubleshooting section
2. Review API documentation for enabled services
3. Verify all required permissions are set
4. Check server error logs for detailed information

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

- **News Providers**: GNews, NewsAPI, The Guardian, NY Times
- **AI Services**: OpenAI, Google, Anthropic
- **Weather Data**: OpenWeatherMap
- **Entertainment**: The Movie Database
- **Audio**: Google Text-to-Speech
- **Design**: Tailwind CSS framework

---

**NewsBear** - Your personalized AI news companion 🐻📰