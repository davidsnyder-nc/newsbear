#!/usr/bin/env python3
"""
Simple Chatterbox-style TTS Server for NewsBear Testing
Mimics the Chatterbox TTS API endpoints for development testing
"""

from flask import Flask, request, jsonify, send_file
import os
import json
import uuid
import time
from pathlib import Path
import threading
import logging

app = Flask(__name__)
logging.basicConfig(level=logging.INFO)

# Simple in-memory job storage
jobs = {}
audio_dir = Path("audio_output")
audio_dir.mkdir(exist_ok=True)

class TTSJob:
    def __init__(self, job_id, text, voice="default"):
        self.job_id = job_id
        self.text = text
        self.voice = voice
        self.status = "pending"
        self.progress = 0
        self.audio_file = None
        self.created_at = time.time()

def simulate_tts_generation(job):
    """Simulate TTS generation with progress updates"""
    try:
        job.status = "processing"
        
        # Simulate processing time with progress updates
        for i in range(1, 11):
            time.sleep(0.5)  # Quick for testing
            job.progress = i * 10
            if job.status == "cancelled":
                return
                
        # Create a simple audio file with actual tone
        audio_file = audio_dir / f"{job.job_id}.wav"
        
        # Generate a simple tone as audio (440Hz for 2 seconds)
        import struct
        import math
        
        sample_rate = 44100
        duration = 2.0
        frequency = 440.0
        num_samples = int(sample_rate * duration)
        
        # Generate sine wave samples
        samples = []
        for i in range(num_samples):
            t = i / sample_rate
            amplitude = 0.3 * math.sin(2 * math.pi * frequency * t)
            sample = int(amplitude * 32767)
            samples.append(struct.pack('<h', sample))
        
        # Create WAV file
        with open(audio_file, 'wb') as f:
            # WAV header
            f.write(b'RIFF')
            f.write(struct.pack('<I', 36 + len(samples) * 2))
            f.write(b'WAVE')
            f.write(b'fmt ')
            f.write(struct.pack('<I', 16))  # Subchunk1Size
            f.write(struct.pack('<H', 1))   # AudioFormat (PCM)
            f.write(struct.pack('<H', 1))   # NumChannels (mono)
            f.write(struct.pack('<I', sample_rate))
            f.write(struct.pack('<I', sample_rate * 2))  # ByteRate
            f.write(struct.pack('<H', 2))   # BlockAlign
            f.write(struct.pack('<H', 16))  # BitsPerSample
            f.write(b'data')
            f.write(struct.pack('<I', len(samples) * 2))
            
            # Write audio data
            for sample in samples:
                f.write(sample)
        
        job.audio_file = str(audio_file)
        job.status = "completed"
        job.progress = 100
        
        logging.info(f"TTS job {job.job_id} completed")
        
    except Exception as e:
        job.status = "failed"
        logging.error(f"TTS job {job.job_id} failed: {e}")

@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({"status": "healthy", "service": "chatterbox-tts-dev"})

@app.route('/', methods=['GET'])
def root():
    return jsonify({
        "service": "Chatterbox TTS Development Server",
        "status": "running",
        "endpoints": ["/generate", "/status/<job_id>", "/download/<job_id>", "/health"]
    })

@app.route('/generate', methods=['POST'])
def generate_audio():
    try:
        data = request.get_json()
        if not data or 'text' not in data:
            return jsonify({"error": "Missing 'text' parameter"}), 400
            
        text = data['text']
        voice = data.get('voice', 'default')
        
        if len(text.strip()) == 0:
            return jsonify({"error": "Text cannot be empty"}), 400
            
        job_id = str(uuid.uuid4())
        job = TTSJob(job_id, text, voice)
        jobs[job_id] = job
        
        # Start processing in background
        thread = threading.Thread(target=simulate_tts_generation, args=(job,))
        thread.daemon = True
        thread.start()
        
        return jsonify({
            "job_id": job_id,
            "status": "accepted",
            "message": "TTS generation started"
        })
        
    except Exception as e:
        logging.error(f"Error in generate_audio: {e}")
        return jsonify({"error": str(e)}), 500

@app.route('/status/<job_id>', methods=['GET'])
def get_status(job_id):
    if job_id not in jobs:
        return jsonify({"error": "Job not found"}), 404
        
    job = jobs[job_id]
    response = {
        "job_id": job_id,
        "status": job.status,
        "progress": job.progress
    }
    
    if job.status == "completed" and job.audio_file:
        response["download_url"] = f"/download/{job_id}"
        
    return jsonify(response)

@app.route('/download/<job_id>', methods=['GET'])
def download_audio(job_id):
    if job_id not in jobs:
        return jsonify({"error": "Job not found"}), 404
        
    job = jobs[job_id]
    if job.status != "completed" or not job.audio_file:
        return jsonify({"error": "Audio not ready"}), 404
        
    if not os.path.exists(job.audio_file):
        return jsonify({"error": "Audio file not found"}), 404
        
    return send_file(job.audio_file, as_attachment=True, download_name=f"audio_{job_id}.wav")

@app.route('/cancel/<job_id>', methods=['POST'])
def cancel_job(job_id):
    if job_id not in jobs:
        return jsonify({"error": "Job not found"}), 404
        
    job = jobs[job_id]
    if job.status in ["pending", "processing"]:
        job.status = "cancelled"
        return jsonify({"job_id": job_id, "status": "cancelled"})
    else:
        return jsonify({"job_id": job_id, "status": job.status, "message": "Cannot cancel"})

@app.route('/jobs', methods=['GET'])
def list_jobs():
    job_list = []
    for job_id, job in jobs.items():
        job_list.append({
            "job_id": job_id,
            "status": job.status,
            "progress": job.progress,
            "created_at": job.created_at
        })
    return jsonify({"jobs": job_list})

if __name__ == '__main__':
    print("🎙️ Starting Chatterbox TTS Development Server")
    print("📡 Server will run on http://0.0.0.0:8000")
    print("🔧 This is a development/testing server")
    app.run(host='0.0.0.0', port=8000, debug=False, threaded=True)