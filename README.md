# NewsBear 🐻 - AI-Powered News Briefing Application

A cutting-edge personalized news briefing application that leverages advanced AI technologies to create intelligent, adaptive audio and text reports tailored to user preferences.

![NewsBear Logo](attached_assets/newsbear_brown_logo.png)

## ✨ Features

### Core Functionality
- **AI-Powered Content Generation**: Uses OpenAI, Gemini, or Claude for intelligent news curation and script generation
- **Multi-Source News Integration**: Aggregates from GNews, NewsAPI, Guardian, NY Times, RSS feeds, and custom sources
- **Audio Report Generation**: Text-to-Speech with multiple voice options (American, British, Australian)
- **Automated Scheduling**: Create recurring daily briefings with custom times and content preferences
- **Personalized Briefings**: Customizable length (3-20 minutes), categories, and content filters
- **Weather Integration**: Local weather reports included in briefings based on ZIP code
- **Entertainment Content**: TV shows and movie updates via TMDB API
- **Local News**: ZIP code-based local news integration
- **Gaming News**: Dedicated gaming category with RSS feed integration from gaming sources

### Scheduling System
- **Multiple Daily Schedules**: Set up different briefings throughout the day
- **Category-Specific Scheduling**: Each schedule can target specific news categories
- **Flexible Content Options**: Include/exclude weather, local news, TV/movies, and specific categories
- **Audio/Text Output**: Choose format per schedule (MP3 audio or text-only)
- **Day-of-Week Selection**: Run schedules on specific days or daily
- **Real-time Execution**: Automatic briefing generation at scheduled times

### Technical Features
- **Tabbed Settings Interface**: Organized configuration across Basic, Content, API, AI Services, Advanced, and Scheduling tabs
- **Briefing History**: Access and replay previous news briefings with audio playback
- **Responsive Design**: Mobile-friendly interface with Tailwind CSS
- **Content Filtering**: Block unwanted terms and customize content
- **Multiple AI Providers**: Fallback support across different AI services
- **RSS Feed Integration**: Custom RSS feeds for specialized content categories
- **Schedule Management**: Create, edit, delete, and toggle scheduled briefings

## 🚀 Quick Start

### Prerequisites
- PHP 8.0 or higher
- Web server (Apache, Nginx, or PHP built-in server)
- API keys for desired services (see Configuration section)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/newsbear.git
   cd newsbear
   ```

2. **Set up directories**
   ```bash
   mkdir -p config data/history downloads
   chmod 755 config data downloads
   ```

3. **Start the application**
   ```bash
   # Start the web server
   php -S 0.0.0.0:5000 -t . &
   
   # Start the scheduler daemon (for automated briefings)
   php scheduler.php &
   ```

4. **Access the application**
   - Open http://localhost:5000 in your browser
   - Navigate to Settings to configure your API keys
   - Set up automated schedules in the Scheduling tab

## ⚙️ Configuration

### Required API Keys

The application requires API keys for various services. Configure them in the Settings page:

#### News Sources
- **GNews API**: Free tier available at [gnews.io](https://gnews.io)
- **NewsAPI**: Free tier at [newsapi.org](https://newsapi.org/register)
- **Guardian API**: Free at [open-platform.theguardian.com](https://open-platform.theguardian.com/access/)
- **NY Times API**: Free tier at [developer.nytimes.com](https://developer.nytimes.com/get-started)

#### AI Services
- **OpenAI API**: Get key at [platform.openai.com](https://platform.openai.com/api-keys)
- **Gemini API**: Free tier at [Google AI Studio](https://makersuite.google.com/app/apikey)
- **Claude API**: Get key at [console.anthropic.com](https://console.anthropic.com/)

#### Additional Services
- **OpenWeatherMap**: Free tier at [openweathermap.org](https://openweathermap.org/api)
- **TMDB (Movies/TV)**: Free at [themoviedb.org](https://www.themoviedb.org/settings/api)
- **Google TTS**: Set up at [Google Cloud Console](https://console.cloud.google.com/)

### Environment Variables (Alternative)

Instead of using the settings interface, you can set environment variables:

```bash
export OPENAI_API_KEY="your_openai_key"
export GEMINI_API_KEY="your_gemini_key"
export GNEWS_API_KEY="your_gnews_key"
export NEWSAPI_KEY="your_newsapi_key"
export WEATHER_API_KEY="your_weather_key"
# ... and so on
```

## 📖 Usage

### Creating Your First Briefing

1. **Configure Settings**
   - Go to Settings → Basic Settings
   - Set your ZIP code for local news
   - Choose audio length (3-20 minutes)
   - Select voice preference

2. **Add API Keys**
   - Go to Settings → API Keys
   - Add keys for news sources and AI services
   - Enable the services you want to use

3. **Customize Content**
   - Go to Settings → Content & Categories
   - Select news categories of interest
   - Add any terms you want to block

4. **Generate Briefing**
   - Click "Create My News Brief" on the homepage
   - Wait for processing (usually 30-60 seconds)
   - Download MP3 or read text version

### Setting Up Automated Schedules

1. **Create Schedule**
   - Go to Settings → Scheduling
   - Click "Add New Schedule"
   - Set time (24-hour format)
   - Choose days of the week

2. **Configure Content**
   - Select specific categories for this schedule
   - Choose whether to include weather, local news, TV/movies
   - Set output format (MP3 audio or text-only)

3. **Manage Schedules**
   - Toggle schedules on/off without deleting
   - Edit existing schedules
   - View schedule status and last run time
   - Delete schedules no longer needed

### Managing Briefings

- **History**: Access all previous briefings via the History page
- **Audio Playback**: Built-in audio player with progress control
- **Text Export**: Copy briefing text to clipboard
- **Delete**: Remove unwanted briefings
- **Scheduled Content**: View briefings generated automatically by schedules

## 🏗️ Architecture

### Project Structure

```
newsbear/
├── api/                    # API endpoints
│   ├── generate.php        # Main briefing generation
│   ├── status.php         # Status checking
│   └── scheduling.php     # Schedule management API
├── includes/              # Core PHP classes
│   ├── AIService.php      # AI provider integration
│   ├── NewsAPI.php        # News source aggregation
│   ├── TTSService.php     # Text-to-speech conversion
│   ├── WeatherService.php # Weather data integration
│   ├── TMDBService.php    # Movie/TV data
│   ├── BriefingHistory.php # History management
│   └── ScheduleManager.php # Scheduling system
├── config/                # Configuration files
├── data/                  # Generated briefings and history
│   └── schedules/         # Schedule configuration files
├── attached_assets/       # Logos and static assets
├── scheduler.php          # Background scheduling daemon
├── index.php             # Main application
├── settings.php          # Configuration interface
├── history.php           # Briefing history
└── style.css             # Application styling
```

### Key Components

- **Frontend**: Vanilla JavaScript with Tailwind CSS
- **Backend**: PHP with modular class structure
- **AI Integration**: Multi-provider support with fallbacks (OpenAI, Gemini, Claude)
- **Audio Generation**: Google Text-to-Speech integration
- **Data Storage**: JSON-based file storage
- **News Aggregation**: Multiple API sources with deduplication
- **Scheduling Engine**: Background daemon for automated briefing generation
- **RSS Integration**: Custom RSS feed parsing for specialized content

## 🛠️ Development

### Adding New Features

1. **New News Source**
   - Extend `NewsAPI.php` class
   - Add API integration method
   - Update settings interface

2. **New AI Provider**
   - Extend `AIService.php` class
   - Implement provider-specific methods
   - Add to settings configuration

3. **New Voice Provider**
   - Extend `TTSService.php` class
   - Implement voice generation
   - Update voice selection options

### Testing

The application includes comprehensive error handling and logging:

- Check PHP error logs for backend issues
- Use browser console for frontend debugging
- Enable debug mode in Advanced Settings

## 🔒 Security

### API Key Management
- API keys are stored in `config/user_settings.json`
- File is excluded from git via `.gitignore`
- Environment variables supported as alternative
- No hardcoded keys in source code

### Content Safety
- All user inputs are sanitized
- API responses are validated
- Content filtering options available
- News sources are verified and authentic

## 📝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines
- Follow PSR-4 autoloading standards
- Add proper error handling
- Update documentation for new features
- Test with multiple API providers

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

### Common Issues

**"No API key configured"**
- Add required API keys in Settings → API Keys
- Ensure keys are valid and have sufficient quota

**"Generation failed"**
- Check API key validity
- Verify internet connection
- Try different AI provider in Settings → AI Services

**"Audio generation failed"**
- Ensure Google TTS API key is configured
- Check TTS service quota
- Verify voice selection compatibility

**"Scheduled briefings not running"**
- Verify scheduler daemon is running: `ps aux | grep scheduler.php`
- Check file permissions on config and data directories
- Ensure at least one schedule is enabled and properly configured
- Review schedule times are in correct timezone format

**"Schedule not found" or "Cannot update schedule"**
- Check data/schedules directory exists and is writable
- Verify schedule files are valid JSON format
- Restart scheduler daemon after configuration changes

### Getting Help

- Check the Issues page for known problems
- Create a new issue with detailed error information
- Include relevant log entries and configuration (without API keys)

## 🙏 Acknowledgments

- OpenAI, Google, and Anthropic for AI services
- News API providers for reliable news data
- Tailwind CSS for responsive design
- Font Awesome for icons

---

Built with ❤️ for informed communities everywhere.