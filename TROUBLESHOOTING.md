# NewsBear Troubleshooting Guide

## Key Log Indicators

### ✅ SUCCESS PATTERNS
```
# Text Generation Working:
NewsBear Debug [timestamp]: === Starting briefing generation ===
NewsBear Debug [timestamp]: News APIs returned 50 articles
NewsBear Debug [timestamp]: STATUS: Audio queued for processing (100%)

# TTS Processing Working:
Chatterbox: Connection test to http://192.168.1.20:7861 - HTTP: 200, Error: none
Chatterbox: Request to http://192.168.1.20:7861/api/predict - HTTP 200
Audio generation completed successfully for session [id]
```

### ❌ FAILURE PATTERNS
```
# Connection Issues:
Chatterbox: Server connection failed - Connection refused
Chatterbox: CURL Error - Couldn't connect to server

# API Issues:
Generation failed: Gemini API error
Unable to fetch content from any source

# Queue Issues:
Scheduler executed, 0 briefings processed (when TTS jobs pending)
```

## Quick Fixes

### 1. Chatterbox Not Connecting
**Check:** Is your Chatterbox server running at http://192.168.1.20:7861?
**Test:** Open browser to http://192.168.1.20:7861
**Fix:** Restart Chatterbox or update server URL in settings

### 2. Text Generation Fails
**Check:** API keys in settings page
**Test:** Generate briefing and watch debug log
**Fix:** Add missing API keys (Gemini, Guardian, etc.)

### 3. Scheduler Not Processing
**Check:** `data/tts_queue.json` for pending jobs
**Test:** Look for "Processing TTS queue..." in scheduler logs
**Fix:** Clear corrupted queue: `echo '[]' > data/tts_queue.json`

### 4. No Audio Files Generated
**Check:** `data/audio/` directory exists and is writable
**Test:** Run `php test_chatterbox.php` 
**Fix:** Create directory or fix permissions

## Test Commands (Run Locally)

```bash
# Test Chatterbox connection
php test_chatterbox.php

# Check TTS queue
cat data/tts_queue.json

# View recent logs
tail -f data/scheduler.log

# Test audio directory
ls -la data/audio/
```

## Expected Timing
- **Text Generation:** 3-5 minutes
- **TTS Processing:** 30-60 minutes
- **File Saving:** 30 seconds

## When to Restart
1. If scheduler shows "0 briefings processed" for 5+ minutes with pending TTS jobs
2. If Chatterbox connection keeps failing but server is running
3. If corrupted JSON in queue files