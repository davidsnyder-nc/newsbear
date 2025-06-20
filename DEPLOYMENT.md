# NewsBear Local Deployment Guide

This guide covers deploying NewsBear locally for testing and development.

## Prerequisites

- PHP 8.0 or higher
- PostgreSQL database (optional - falls back to file storage)
- Web server (Apache, Nginx, or PHP built-in server)
- Internet connection for news APIs

## Installation Methods

### Method 1: Auto-Installer (Recommended)

```bash
git clone https://github.com/yourusername/newsbear.git
cd newsbear
php install.php
php start.php
```

### Method 2: Composer

```bash
composer create-project newsbear/newsbear
cd newsbear
composer start
```

### Method 3: npm

```bash
git clone https://github.com/yourusername/newsbear.git
cd newsbear
npm install
npm start
```

All methods will:
- Check system requirements automatically
- Create all required directories
- Set proper permissions
- Generate default configuration
- Test internet connectivity
- Display next steps

### Configuration

1. Open the displayed URL in your browser
2. Go to Settings
3. Enter API keys for the news services you want
4. Save and start generating briefings

**Zero manual configuration required** - everything is done through the web interface.

### 4. Database Setup (Optional)

**PostgreSQL (Recommended):**
```bash
# Create database
createdb newsbear

# Set environment variables
export DATABASE_URL="postgresql://username:password@localhost:5432/newsbear"
export PGDATABASE="newsbear"
export PGUSER="your_username"
export PGPASSWORD="your_password"
export PGHOST="localhost"
export PGPORT="5432"
```

**File Storage (Fallback):**
No database setup required - uses JSON files in `data/` directory.

### 5. Advanced Server Options

**Option 1: PHP Built-in Server (Any Port)**
```bash
# Default port 5000
php -S 0.0.0.0:5000 -t .

# Custom port (e.g., 8080)
php -S 0.0.0.0:8080 -t .

# Localhost only
php -S localhost:3000 -t .
```

**Option 2: Apache/Nginx**
Point document root to the project directory and configure virtual host.

### 6. Access and Configure

Open your browser and navigate to:
- `http://localhost:5000` (default port)
- `http://localhost:8080` (if using port 8080)
- `http://localhost` (Apache/Nginx)

**First-time Setup:**
1. Go to Settings
2. Configure your API keys
3. Test news generation
4. Set up TTS provider if desired

## Chatterbox TTS Integration

### Setup Local Chatterbox Server

1. Install Chatterbox-TTS on your system
2. Start the server:
   ```bash
   # Example - adjust for your installation
   python -m chatterbox_tts --port 8000
   ```
3. Update configuration:
   ```json
   {
       "ttsProvider": "chatterbox",
       "chatterboxServerUrl": "http://localhost:8000"
   }
   ```
4. Test connection in Settings → TTS Provider

### Sample Audio Files

**Option 1: Local Upload**
- Place sample audio file in `data/` directory
- Set filename in settings: `"chatterboxSampleFile": "sample.wav"`

**Option 2: Server Path**
- Store file on Chatterbox server
- Set full path in settings: `"chatterboxSampleFile": "/path/to/sample.wav"`

## API Key Setup

### Required APIs (Free Tiers Available)

1. **GNews API**: https://gnews.io/
2. **NewsAPI**: https://newsapi.org/
3. **Guardian API**: https://open-platform.theguardian.com/
4. **NY Times API**: https://developer.nytimes.com/
5. **OpenWeatherMap**: https://openweathermap.org/api
6. **TMDB**: https://www.themoviedb.org/settings/api

### AI Services (Choose One)

1. **Google Gemini**: https://ai.google.dev/
2. **OpenAI**: https://platform.openai.com/
3. **Anthropic Claude**: https://console.anthropic.com/

### TTS Services

1. **Google Cloud TTS**: https://cloud.google.com/text-to-speech
2. **Chatterbox TTS**: Local installation

## Scheduler Setup

The scheduler runs automatically but you can also run it manually:

```bash
# Run scheduler once
php scheduler.php

# Run continuous scheduler (Linux/Mac)
while true; do php scheduler.php; sleep 60; done

# Run continuous scheduler (Windows)
# Use Task Scheduler or run in PowerShell loop
```

## File Permissions

Ensure the following directories are writable:

```bash
chmod 755 data/
chmod 755 data/history/
chmod 755 data/cache/
chmod 755 downloads/
chmod 644 config/user_settings.json
```

## Environment Variables

For production deployment, set these environment variables:

```bash
# Database
export DATABASE_URL="postgresql://user:pass@localhost:5432/newsbear"

# Optional: Custom configuration
export NEWSBEAR_CONFIG_PATH="/path/to/config"
export NEWSBEAR_DATA_PATH="/path/to/data"
```

## Troubleshooting

### Common Issues

**News Generation Fails**
- Verify API keys are correct
- Check internet connectivity
- Enable debug mode in settings

**Audio Generation Fails**
- Verify TTS provider configuration
- Check Google Cloud TTS quota
- Test Chatterbox server connection

**Database Connection Fails**
- Verify PostgreSQL is running
- Check DATABASE_URL format
- Ensure database exists and is accessible

**File Permission Errors**
- Check directory permissions
- Ensure web server user can write to data directories

### Debug Mode

Enable debug logging in settings:
```json
{
    "debugMode": true,
    "verboseLogging": true,
    "showLogWindow": true
}
```

Check PHP error logs and browser console for detailed error information.

## Security Considerations

### Production Deployment

1. **Environment Variables**: Use environment variables for API keys
2. **File Permissions**: Restrict access to config and data directories
3. **Authentication**: Enable authentication in settings
4. **HTTPS**: Use SSL/TLS in production
5. **Database Security**: Use secure database credentials

### Authentication

Default credentials: `admin` / `mindless`

Change in Settings → Authentication or modify directly in config:
```json
{
    "authEnabled": true
}
```

## Performance Optimization

### Caching
- News content cached automatically
- Adjust cache duration in settings
- Clear cache: delete `data/cache/` contents

### Database
- PostgreSQL recommended for better performance
- Regular cleanup of old briefings
- Index optimization for large datasets

### File Storage
- Regular cleanup of old audio files
- Monitor disk space usage
- Archive old briefings as needed

## Support

For issues and questions:
- Check this deployment guide
- Review troubleshooting section
- Enable debug mode for detailed logs
- Check GitHub issues for known problems