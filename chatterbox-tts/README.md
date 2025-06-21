# Chatterbox TTS Development Server

This is a **development/testing server** that mimics the Chatterbox TTS API for NewsBear integration testing.

## What it does:
- Provides the same API endpoints as Chatterbox TTS
- Generates simple audio tones (440Hz sine wave) instead of speech
- Useful for testing the complete NewsBear workflow without requiring actual TTS models

## API Endpoints:
- `GET /` - Service information
- `GET /health` - Health check
- `POST /generate` - Start audio generation job
- `GET /status/<job_id>` - Check job status
- `GET /download/<job_id>` - Download completed audio
- `POST /cancel/<job_id>` - Cancel job
- `GET /jobs` - List all jobs

## For Production:
Replace this server with actual Chatterbox TTS from:
https://huggingface.co/spaces/ResembleAI/Chatterbox

## Generated Audio:
- Creates 2-second 440Hz tone as placeholder
- WAV format, 44.1kHz, 16-bit, mono
- Real implementation would generate speech from text