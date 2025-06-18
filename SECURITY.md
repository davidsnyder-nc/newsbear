# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability in NewsBear, please report it responsibly:

1. **Do NOT** open a public issue
2. Send details to [security contact email]
3. Include steps to reproduce the vulnerability
4. Allow 48 hours for initial response

## Security Measures

### API Key Protection
- API keys are stored in `config/user_settings.json` (excluded from git)
- Environment variable support for production deployments
- No hardcoded credentials in source code
- Input validation for all configuration values

### Data Handling
- All user inputs are sanitized
- News content is fetched from verified sources only
- No user data is stored beyond local configuration
- Generated briefings are stored locally only

### Content Security
- Content filtering options to block unwanted terms
- All news sources are authenticated and verified
- AI-generated content is clearly identified
- No synthetic news content is generated

### Deployment Security
- Secure file permissions recommended (755 for directories, 644 for files)
- Web server configuration should restrict access to config files
- Regular updates of dependencies recommended
- HTTPS deployment strongly recommended

## Best Practices

### For Users
- Keep API keys confidential
- Use strong, unique API keys
- Monitor API usage and quotas
- Enable content filtering as needed

### For Developers
- Follow secure coding practices
- Validate all external API responses
- Use prepared statements if database is added
- Regular security audits of dependencies

## Known Security Considerations

1. **API Key Storage**: Local file storage is used by default. For production, consider encrypted storage or secure key management services.

2. **Content Filtering**: While content filtering is available, users should review generated content before sharing.

3. **External Dependencies**: The application relies on multiple external APIs. Monitor these services for security updates.

## Updates and Patches

Security updates will be released promptly. Users should:
- Monitor the repository for security releases
- Update installations when patches are available
- Review changelogs for security-related changes