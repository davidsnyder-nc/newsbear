# Changelog

All notable changes to NewsBear will be documented in this file.

## [2.0.0] - 2025-06-20

### Added
- **Chatterbox TTS Integration**: Local TTS server support with queue-based processing
- **Async Audio Processing**: Background job system for longer TTS generation times
- **Sample Audio File Support**: Voice cloning capabilities with Chatterbox TTS
- **Connection Testing**: Built-in test tool for Chatterbox server validation
- **Multiple API Endpoint Detection**: Auto-discovery of Chatterbox API formats
- **Enhanced Settings Interface**: Chatterbox configuration with voice styles
- **Queue Management**: Real-time status tracking and progress updates
- **Background Processing**: Automatic job completion handling

### Enhanced
- **TTS Provider System**: Dual support for Google TTS and Chatterbox TTS
- **Queue Status Polling**: 10-second intervals with 10-minute timeout
- **Error Handling**: Comprehensive error reporting and recovery
- **Documentation**: Complete deployment and setup guides

### Fixed
- **LSP Errors**: Resolved method name conflicts in briefing history
- **File Processing**: Corrected pending briefing completion workflow
- **Configuration Management**: Proper handling of Chatterbox settings

### Technical
- **Architecture**: Improved separation between TTS providers
- **Performance**: Async processing prevents timeout issues
- **Reliability**: Enhanced error detection and logging
- **Scalability**: Queue system handles multiple concurrent requests

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