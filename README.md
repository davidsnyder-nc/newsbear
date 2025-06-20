# NewsBear - AI-Powered News Briefing System

**NewsBear** is a comprehensive AI-powered news briefing application that generates personalized audio news reports and text summaries. The system integrates multiple news APIs, AI services, and text-to-speech conversion to deliver professional news briefings.

## Features

### Core Functionality
- **AI-Generated News Scripts**: Professional news briefing scripts using Gemini, ChatGPT, or Claude
- **Multiple TTS Options**: Google TTS (cloud) and Chatterbox TTS (local) support
- **Queue-Based Processing**: Async processing for longer TTS generation times
- **Smart Content Filtering**: Block unwanted terms, prefer specific topics
- **Briefing History**: Complete archive with replay functionality
- **Scheduled Briefings**: Automated briefing generation
- **Weather Integration**: Current conditions and forecasts
- **Entertainment News**: Movie/TV updates via TMDB API

### News Sources
- **GNews API**: Global news aggregation
- **NewsAPI**: Comprehensive news coverage
- **Guardian API**: Quality journalism
- **New York Times API**: Premium news content
- **RSS Feeds**: Custom feed integration
- **Weather Service**: OpenWeatherMap integration
- **TMDB**: Movie and TV entertainment news

### TTS Integration
- **Google Cloud TTS**: Immediate high-quality audio generation
- **Chatterbox TTS**: Local server integration with voice cloning support
- **Queue System**: Background processing for longer TTS jobs
- **Multiple Voice Styles**: News anchor, conversational, dramatic, calm

## Installation

### Requirements
- PHP 8.0+
- PostgreSQL database (auto-configured in Replit)
- API keys for news services
- Optional: Local Chatterbox TTS server

### Quick Start
1. Clone the repository
2. Start the server: `php start.php [port] [host]`
   - Default: `php start.php` (port 5000, all interfaces)
   - Custom port: `php start.php 8080`
   - Localhost only: `php start.php 3000 localhost`
3. Open browser to the displayed URL
4. Go to Settings and configure API keys
5. Generate your first briefing

**Alternative start methods:**
- `php -S 0.0.0.0:5000` (manual server start)
- Configure with Apache/Nginx for production

### Local Chatterbox TTS Setup
1. Install Chatterbox-TTS on your local machine
2. Start the server (typically port 7861 or 8000)
3. In Settings → TTS Provider → "Chatterbox TTS"
4. Enter your server URL: `http://localhost:8000` or `http://media.server:7861`
5. Test connection to verify integration

## Configuration

### API Keys Required
- **GNews API**: Free tier available
- **NewsAPI**: Free tier available  
- **Guardian API**: Free registration required
- **NY Times API**: Free tier available
- **OpenWeatherMap**: Free tier available
- **TMDB API**: Free registration required
- **AI Services**: Gemini, OpenAI, or Claude API keys

### Authentication
- Default credentials: `admin` / `mindless`
- Configure in Settings → Authentication

### Sample Audio Files (Chatterbox TTS)
**Option 1**: Upload to `data/` folder, enter filename in settings
**Option 2**: Store on Chatterbox server, enter full path in settings

## Usage

### Generate Briefing
1. Access the main interface
2. Select briefing length (3-5, 5-10, or 10+ minutes)
3. Choose audio generation or text-only
4. Click "Generate Briefing"
5. Download MP3 or copy text when complete

### Schedule Briefings
1. Go to Settings → Scheduled Briefings
2. Set time, frequency, and preferences
3. Briefings generate automatically
4. Access via History page

### RSS Feeds
1. Settings → RSS Feeds → "Manage RSS Feeds"
2. Add custom news sources
3. Categorize feeds (Technology, Gaming, etc.)
4. Feeds update automatically

## Technical Details

### Architecture
- **Frontend**: Vanilla JavaScript with Tailwind CSS
- **Backend**: PHP with object-oriented structure
- **Database**: PostgreSQL for briefing history
- **File Storage**: Local filesystem for audio files
- **Processing**: Background job queue for TTS

### Security
- Authentication system with session management
- Input sanitization and validation
- Secure API key storage
- Debug logging with privacy controls

### Performance
- Async TTS processing prevents timeouts
- Smart caching of news content
- Topic deduplication to avoid repetition
- Automatic cleanup of old files

## Development

### Project Structure
```
├── api/                 # API endpoints
├── includes/           # Core PHP classes
├── data/              # Data storage and cache
├── config/            # Configuration files  
├── attached_assets/   # Static assets
└── *.php             # Main application files
```

### Key Classes
- `NewsAPI`: News aggregation and processing
- `BriefingGenerator`: Core briefing creation logic
- `ChatterboxTTS`: Local TTS server integration
- `BriefingHistory`: Archive and replay functionality
- `WeatherService`: Weather data integration

## Troubleshooting

### Common Issues
- **TTS Timeouts**: Use Chatterbox TTS for longer content
- **No News Found**: Check API keys and internet connection
- **Audio Generation Fails**: Verify TTS provider configuration
- **Queue Processing Stuck**: Check Chatterbox server status

### Debug Mode
Enable in Settings → Debug Options for detailed logging and error tracking.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## Support

For issues and feature requests, please use the GitHub issue tracker.