# Changelog

All notable changes to NewsBear will be documented in this file.

## [2.0.0] - 2025-06-20

### Added
- **Chatterbox TTS Integration**: Local TTS server support with queue-based processing
- **Session Management**: Automatic cleanup and polling timeout prevention
- **Monitoring System**: Comprehensive guides (MONITORING_GUIDE.md, TROUBLESHOOTING.md)
- **Background Processing**: TTS queue system for 30-60 minute audio generation
- **Session Validation**: Automatic detection and cleanup of orphaned sessions
- **Connection Testing**: Built-in Chatterbox server validation tools

### Enhanced
- **Timeout System**: Removed artificial limits during testing for indefinite runtime
- **Polling Logic**: Smart debug log polling with automatic session expiration
- **Connection Handling**: Improved Chatterbox detection (HTTP 200, 302, 404)
- **Frontend State**: Better session tracking and cleanup on page load/unload
- **Error Handling**: Comprehensive connection and processing error recovery

### Fixed
- **Endless Polling**: Resolved infinite debug log polling from stuck sessions
- **TTS Queue Issues**: Fixed malformed JSON blocking processing
- **Session Cleanup**: Proper cleanup of expired and orphaned sessions  
- **Memory Management**: Unlimited execution time and 1GB memory for audio processing
- **LSP Errors**: Resolved method name conflicts in briefing history

### Technical
- **ChatterboxTTS.php**: Enhanced error handling and connection management
- **Frontend Polling**: Timeout detection with automatic session termination
- **Debug System**: Session expiration validation in debug_log.php
- **Queue Processing**: Improved background job handling and status updates

## [1.5.0] - Previous Version

### Core Features
- AI-powered news briefing generation
- Multiple news source integration (GNews, NewsAPI, Guardian, NYT)
- Google Cloud TTS integration
- Weather and entertainment news
- RSS feed management
- Scheduled briefing system
- Complete briefing history
- Authentication system
- Debug logging and monitoring

### News Sources
- GNews API integration
- NewsAPI comprehensive coverage
- Guardian API quality journalism
- New York Times premium content
- Custom RSS feed support
- OpenWeatherMap weather data
- TMDB entertainment news

### AI Integration
- Google Gemini AI processing
- OpenAI ChatGPT support
- Anthropic Claude integration
- Smart content filtering
- Topic deduplication
- Professional script generation