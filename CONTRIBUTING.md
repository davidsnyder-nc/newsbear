# Contributing to NewsBear

Thank you for your interest in contributing to NewsBear! This document provides guidelines for contributing to the project.

## Getting Started

1. Fork the repository
2. Clone your fork locally
3. Create a new branch for your feature/fix
4. Make your changes
5. Test thoroughly
6. Submit a pull request

## Development Setup

```bash
# Clone your fork
git clone https://github.com/yourusername/newsbear.git
cd newsbear

# Create required directories
mkdir -p config data/history downloads

# Copy example configuration
cp config/user_settings.example.json config/user_settings.json

# Edit configuration with your API keys
# Note: Never commit real API keys to the repository

# Start development server
php -S localhost:5000 -t .
```

## Code Style

### PHP
- Follow PSR-12 coding standards
- Use meaningful variable and function names
- Add proper docblocks for classes and methods
- Handle errors gracefully with try-catch blocks

### JavaScript
- Use vanilla JavaScript (no frameworks)
- Follow modern ES6+ standards
- Add comments for complex logic
- Use consistent indentation (2 spaces)

### CSS
- Use Tailwind CSS classes when possible
- Organize custom CSS in logical sections
- Use meaningful class names for custom styles

## Pull Request Guidelines

### Before Submitting
- Ensure code follows style guidelines
- Test all functionality thoroughly
- Update documentation if needed
- Add appropriate error handling
- No API keys or sensitive data in commits

### PR Description
- Describe what the PR does
- Link to any related issues
- Include screenshots for UI changes
- List any breaking changes

## Testing

### Manual Testing
- Test with multiple API providers
- Verify audio generation works
- Check settings persistence
- Test error scenarios

### API Testing
- Use actual API keys for testing
- Verify rate limiting handling
- Test network failure scenarios
- Validate response parsing

## Feature Development

### Adding New News Sources
1. Extend `NewsAPI.php` class
2. Add new `fetch[Source]News()` method
3. Update settings interface
4. Add to documentation
5. Test with real API

### Adding New AI Providers
1. Extend `AIService.php` class
2. Implement provider-specific methods
3. Add configuration options
4. Update settings interface
5. Test thoroughly

### Adding New TTS Providers
1. Extend `TTSService.php` class
2. Implement voice generation
3. Add voice options to settings
4. Update documentation
5. Test audio quality

## Bug Reports

### Include in Bug Reports
- Steps to reproduce
- Expected vs actual behavior
- Browser/PHP version
- Error messages (without API keys)
- Configuration details (sanitized)

### Bug Fix Process
1. Reproduce the issue
2. Identify root cause
3. Implement fix
4. Test thoroughly
5. Update tests if applicable

## Documentation

### Code Documentation
- Add docblocks for new functions
- Update inline comments
- Document complex algorithms
- Include usage examples

### User Documentation
- Update README for new features
- Add configuration examples
- Include troubleshooting tips
- Update API requirements

## Security

### Security Guidelines
- Never commit API keys or secrets
- Validate all user inputs
- Sanitize data before storage
- Use HTTPS for API calls
- Follow secure coding practices

### Reporting Security Issues
- Do not open public issues for security vulnerabilities
- Contact maintainers directly
- Provide detailed reproduction steps
- Allow time for responsible disclosure

## Code Review

### What Reviewers Look For
- Code quality and style
- Security considerations
- Performance implications
- Documentation completeness
- Test coverage

### Addressing Feedback
- Respond to all review comments
- Make requested changes promptly
- Ask questions if unclear
- Test changes after modifications

## Release Process

### Version Numbering
- Follow semantic versioning (semver)
- Major: Breaking changes
- Minor: New features, backward compatible
- Patch: Bug fixes, backward compatible

### Release Checklist
- Update version numbers
- Update CHANGELOG.md
- Test all functionality
- Update documentation
- Create release notes

## Community Guidelines

### Code of Conduct
- Be respectful and inclusive
- Provide constructive feedback
- Help newcomers learn
- Focus on the code, not the person

### Communication
- Use clear, professional language
- Provide detailed explanations
- Be patient with questions
- Share knowledge freely

## Getting Help

### Where to Ask Questions
- GitHub Discussions for general questions
- Issues for bugs and feature requests
- Pull request comments for code-specific questions

### Resources
- README.md for setup and usage
- Code comments for implementation details
- API documentation for integrations

Thank you for contributing to NewsBear!