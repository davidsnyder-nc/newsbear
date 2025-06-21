# NewsBear - AI-Powered News Briefing System

NewsBear is a comprehensive AI-powered news briefing application that generates personalized audio news reports and text summaries from multiple sources.

## Features

- **Multi-Source News Aggregation**: Integrates with GNews, NewsAPI, Guardian, NY Times, and custom RSS feeds
- **AI Content Generation**: Supports OpenAI GPT, Google Gemini, and Anthropic Claude for intelligent briefing creation
- **Text-to-Speech**: Google Cloud TTS integration for audio generation
- **Weather Integration**: OpenWeatherMap for local weather updates
- **Entertainment News**: TMDB API for TV and movie news
- **Scheduled Briefings**: Automated briefing generation at specified times
- **Briefing History**: Complete history with search and playback
- **Responsive Design**: Works on desktop and mobile devices

## Prerequisites

- PHP 8.0 or higher
- Web server (Apache, Nginx, or PHP built-in server)
- PostgreSQL database (provided by Replit)
- API keys for desired services (see API Setup below)

## Quick Start

1. **Clone the repository**
   ```bash
   git clone [repository-url]
   cd newsbear
   ```

2. **Set up the database**
   - PostgreSQL database is automatically provided in Replit environment
   - Database URL is available in `DATABASE_URL` environment variable

3. **Configure API keys**
   - Access settings at `http://your-domain/settings.php`
   - Add your API keys in the respective sections
   - Enable/disable services as needed

4. **Start the server**
   ```bash
   php -S 0.0.0.0:5000 -t .
   ```

5. **Access the application**
   - Open `http://localhost:5000` in your browser
   - Generate your first briefing!

## API Setup

### Required for News Sources
- **GNews API**: https://gnews.io/ (Free tier: 100 requests/day)
- **NewsAPI**: https://newsapi.org/ (Free tier: 1000 requests/day)
- **Guardian API**: https://open-platform.theguardian.com/ (Free)
- **NY Times API**: https://developer.nytimes.com/ (Free tier available)

### Required for AI Generation (Choose one)
- **Google Gemini**: https://ai.google.dev/ (Free tier available)
- **OpenAI**: https://platform.openai.com/ (Pay-per-use)
- **Anthropic Claude**: https://console.anthropic.com/ (Pay-per-use)

### Required for Audio Generation
- **Google Cloud TTS**: https://cloud.google.com/text-to-speech (Free tier: 1M characters/month)

### Optional Services
- **OpenWeatherMap**: https://openweathermap.org/api (Free tier: 1000 calls/day)
- **TMDB**: https://www.themoviedb.org/settings/api (Free)

## Configuration

### Basic Settings
- **Time Frame**: Auto-detect or manually set (morning, afternoon, evening, night)
- **Audio Length**: Choose from 2-3, 5-10, or 10-15 minute briefings
- **Categories**: Select news categories (technology, business, health, sports, etc.)
- **Location**: Set zip code for local weather and news

### Advanced Options
- **RSS Feeds**: Add custom RSS feeds for additional news sources
- **AI Prompts**: Customize prompts for different AI services
- **Voice Selection**: Choose from various Google TTS voices
- **Debug Logging**: Enable for troubleshooting

## Scheduling

Set up automated briefings:
1. Go to Settings → Scheduling
2. Create new schedule with desired time and days
3. Configure briefing preferences
4. Enable the schedule

The scheduler runs every minute and will generate briefings automatically.

## File Structure

```
newsbear/
├── api/                    # API endpoints
├── includes/              # Core PHP classes
├── config/               # Configuration files
├── data/                 # User data and cache
├── downloads/            # Generated audio files
├── attached_assets/      # Static assets
├── index.php            # Main application
├── settings.php         # Settings interface
├── history.php          # Briefing history
└── scheduler.php        # Background scheduler
```

## Security Notes

- Never commit API keys to version control
- Use environment variables for sensitive configuration
- Regularly rotate API keys
- Monitor API usage to avoid unexpected charges

## Troubleshooting

### Common Issues

1. **"No API key configured"**
   - Add the required API key in settings
   - Ensure the API service is enabled

2. **"Failed to generate briefing"**
   - Check API key validity
   - Verify API service quotas
   - Enable debug logging for detailed errors

3. **Audio generation fails**
   - Verify Google Cloud TTS API key
   - Check API quotas and billing

4. **Scheduler not running**
   - Ensure scheduler.php is being executed regularly
   - Check server logs for errors

### Debug Mode
Enable debug logging in settings to see detailed error messages and API responses.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For issues and questions:
1. Check the troubleshooting section
2. Review server logs
3. Enable debug logging
4. Create an issue with detailed information

## Version

Current version: 2.0.0

See CHANGELOG.md for detailed version history.