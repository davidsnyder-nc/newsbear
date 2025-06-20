# NewsBear Monitoring Guide

## What to Look For in Logs

### 1. SUCCESSFUL TEXT GENERATION
Look for this sequence in your server logs:

```
NewsBear Debug [timestamp]: === Starting briefing generation ===
NewsBear Debug [timestamp]: Session ID: briefing_[unique_id]
NewsBear Debug [timestamp]: Selected categories: [category_name]
NewsBear Debug [timestamp]: === STEP 1: FETCHING NEWS ===
NewsBear Debug [timestamp]: STATUS: Fetching headlines... (10%)
NewsAPI Constructor - Available keys: [shows which APIs are enabled]
NewsBear Debug [timestamp]: News APIs returned [X] articles
NewsBear Debug [timestamp]: RSS feeds returned [Y] articles
NewsBear Debug [timestamp]: === STEP 2: GENERATING BRIEFING ===
NewsBear Debug [timestamp]: STATUS: Analyzing content... (40%)
NewsBear Debug [timestamp]: STATUS: Writing briefing... (70%)
NewsBear Debug [timestamp]: === STEP 3: AUDIO GENERATION ===
NewsBear Debug [timestamp]: STATUS: Audio queued for processing (100%)
```

**✅ SUCCESS INDICATORS:**
- Session ID is generated
- Articles are fetched (should see 40+ total articles)
- Briefing text is generated (356-636 words typically)
- "Audio queued for processing (100%)" appears

### 2. SUCCESSFUL TTS PROCESSING
In scheduler logs, look for:

```
Scheduler running at: [timestamp]
Processing TTS queue...
Found job: [session_id] - Status: pending
Connecting to Chatterbox server at http://192.168.1.20:7861/
Chatterbox TTS request sent successfully
Job [session_id] status: processing
Chatterbox generation completed successfully
Saving audio file: [filename].mp3
Audio generation completed successfully for session [session_id]
Job [session_id] status: completed
```

**✅ SUCCESS INDICATORS:**
- "Processing TTS queue..." appears
- "Connecting to Chatterbox server..." shows connection attempt
- "Chatterbox generation completed successfully" confirms audio creation
- "Audio generation completed successfully" shows final completion

### 3. WARNING SIGNS - WHAT TO WATCH FOR

#### Text Generation Issues:
```
❌ "Unable to fetch content from any source"
❌ "Generation failed: [error message]"
❌ "API key missing or invalid"
❌ "No articles found matching criteria"
❌ "Gemini API error: [error details]"
```

#### TTS Processing Issues:
```
❌ "Failed to connect to Chatterbox server"
❌ "Chatterbox returned error: [error message]"
❌ "cURL error [number]: [description]"
❌ "Failed to save audio file"
❌ "TTS processing failed: [error details]"
```

#### Scheduler Issues:
```
❌ "No schedules due to run at this time" (when TTS jobs are pending)
❌ "Error processing TTS queue: [error]"
❌ "Failed to update job status: [error]"
```

## 4. EXPECTED TIMING

### Text Generation (3-5 minutes):
- News fetching: 30-60 seconds
- Content analysis: 60-120 seconds  
- Text generation: 60-180 seconds
- Total: 150-360 seconds

### TTS Processing (30-60 minutes):
- Queue processing: 1-5 seconds
- Chatterbox connection: 5-15 seconds
- Audio generation: 1800-3600 seconds (30-60 minutes)
- File saving: 5-30 seconds

## 5. DEBUGGING STEPS

### If Text Generation Fails:
1. Check API keys in settings
2. Verify internet connection
3. Check RSS feed URLs are accessible
4. Look for Gemini API quota/rate limit errors

### If TTS Processing Fails:
1. Verify Chatterbox is running: `curl http://192.168.1.20:7861/health`
2. Check network connectivity to 192.168.1.20
3. Look for file permission errors in data/audio/
4. Check available disk space

### If Scheduler Isn't Processing:
1. Verify scheduler workflow is running
2. Check data/tts_queue.json exists and is writable
3. Look for PHP errors in scheduler logs
4. Restart scheduler if needed

## 6. FRONTEND STATUS INDICATORS

**During Generation:**
- "Starting generation..." (0%)
- "Fetching headlines..." (10%)
- "Analyzing content..." (40%)
- "Writing briefing..." (70%)
- "Audio queued for processing" (100%)

**During TTS Processing:**
- "Connecting to Chatterbox server..."
- "Generating audio with Chatterbox..."
- "Saving audio file..."
- "Audio generation completed successfully"

## 7. FILES TO MONITOR

### Log Files:
- Server console output (your terminal)
- Scheduler console output
- data/debug_logs/[session_id].log

### Queue Files:
- data/tts_queue.json (shows pending/processing jobs)
- data/briefing_history.json (shows completed briefings)

### Audio Files:
- data/audio/[session_id].mp3 (final output)

## 8. QUICK HEALTH CHECKS

### Test Chatterbox Connection:
```bash
curl -X POST http://192.168.1.20:7861/generate \
  -H "Content-Type: application/json" \
  -d '{"text":"Test audio generation","voice":"default"}'
```

### Check Queue Status:
```bash
cat data/tts_queue.json
```

### Verify Audio Directory:
```bash
ls -la data/audio/
```

This guide will help you identify exactly where in the process things are working or failing.