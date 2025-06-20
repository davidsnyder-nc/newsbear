// Simple News Brief App JavaScript

class NewsBriefApp {
    constructor() {
        this.isGenerating = false;
        this.statusSteps = [
            'Fetching headlines...',
            'Selecting stories...',
            'Summarizing content...',
            'Generating audio...',
            'Finalizing briefing...'
        ];
        this.currentStep = 0;
        this.debugLogEnabled = false;
        this.logPollingInterval = null;
        this.wittyMessages = [
            "Teaching bears to read the news...",
            "Asking the internet what happened today...",
            "Convincing AI to work for honey...",
            "Sorting through the day's chaos...",
            "Making sense of the headlines...",
            "Brewing up some fresh news...",
            "Hunting for the good stories...",
            "Turning noise into knowledge...",
            "Gathering intel from around the globe...",
            "Cooking up your daily digest...",
            "Fishing for the latest updates...",
            "Separating facts from fiction...",
            "Collecting today's greatest hits...",
            "Assembling your news sandwich...",
            "Herding cats... I mean, headlines...",
            "Putting the 'brief' in news briefing...",
            "Teaching headlines to behave...",
            "Converting chaos into clarity...",
            "Distilling the day's events...",
            "Packaging news with a bow on top...",
            "Making deadlines meet their maker...",
            "Translating reporter-speak to human...",
            "Convincing stories to play nicely together...",
            "Bear-ing the weight of world events..."
        ];
        this.messageInterval = null;
        this.currentMessageIndex = 0;
        this.init();
    }

    init() {
        this.bindEvents();
        this.setupErrorHandling();
        this.initDarkTheme();
        this.checkDebugLogSettings();
    }

    setupErrorHandling() {
        // Handle unhandled promise rejections
        window.addEventListener('unhandledrejection', (event) => {
            console.error('Unhandled promise rejection:', event.reason);
            this.showError('A network error occurred. Please try again.');
            this.switchToBrownLogo();
            this.isGenerating = false;
            this.enableButton();
            event.preventDefault();
        });
        
        // Handle general JavaScript errors
        window.addEventListener('error', (event) => {
            console.error('JavaScript error:', event.error);
            if (this.isGenerating) {
                this.showError('An unexpected error occurred. Please refresh the page.');
                this.switchToBrownLogo();
                this.isGenerating = false;
                this.enableButton();
            }
        });
    }

    bindEvents() {
        // Generate button
        const generateBtn = document.getElementById('generate-btn');
        if (generateBtn) {
            generateBtn.addEventListener('click', () => {
                this.generateBriefing();
            });
        }



        // Copy text button
        const copyBtn = document.getElementById('copy-text-btn');
        if (copyBtn) {
            copyBtn.addEventListener('click', () => this.copyBriefingText());
        }

        // New button
        const newBtn = document.getElementById('new-btn');
        if (newBtn) {
            newBtn.addEventListener('click', () => this.startNewBriefing());
        }

        // Clear log button
        const clearLogBtn = document.getElementById('clear-log-btn');
        if (clearLogBtn) {
            clearLogBtn.addEventListener('click', () => this.clearDebugLog());
        }
    }

    async generateBriefing() {
        if (this.isGenerating) return;

        this.isGenerating = true;
        this.currentStep = 0;
        this.hideResults();
        this.showStatus();
        this.showDebugLog();
        this.disableButton();

        try {
            const endpoint = 'api/generate.php';
            
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 300000); // 5 minute timeout for initial generation
            
            // Start generation and immediately begin polling for logs
            const responsePromise = fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({}),
                signal: controller.signal
            });

            const response = await responsePromise;
            
            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            // Start log polling with the actual session ID
            if (result.sessionId) {
                this.startLogPolling(result.sessionId);
            }

            if (result.status === 'processing') {
                await this.pollStatus(result.sessionId);
            } else if (result.status === 'success') {
                this.showSuccess(result.downloadUrl, result.briefingText);
            } else if (result.tts_job_id) {
                // Handle async TTS (Chatterbox)
                this.updateStatus('Audio generation queued...', 90);
                this.addDebugLogEntry(`TTS job queued: ${result.tts_job_id}`, 'info');
                await this.pollTtsStatus(result.tts_job_id, result.sessionId);
            } else {
                throw new Error(result.message || 'Generation failed');
            }

        } catch (error) {
            console.error('Generation error:', error);
            if (error.name === 'AbortError') {
                this.showError('Request timed out. The server may be busy. Please try again.');
            } else if (error.message.includes('Failed to fetch')) {
                this.showError('Network error. Please check your connection and try again.');
            } else {
                this.showError(error.message || 'Failed to generate briefing');
            }
            this.switchToBrownLogo();
            this.stopWittyMessages();
        } finally {
            this.isGenerating = false;
            this.enableButton();
        }
    }

    async pollStatus(sessionId) {
        const maxAttempts = 180; // 6 minutes of polling for TTS completion
        let attempts = 0;
        let consecutiveErrors = 0;

        while (attempts < maxAttempts && this.isGenerating) {
            try {
                const response = await fetch('api/status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ sessionId })
                });

                const result = await response.json();

                if (result.status === 'success') {
                    this.showSuccess(result.downloadUrl, result.briefingText);
                    return;
                } else if (result.status === 'error') {
                    throw new Error(result.message || 'Generation failed');
                } else if (result.status === 'processing') {
                    // Show detailed status messages for TTS processing
                    const statusMsg = result.message || this.statusSteps[this.currentStep];
                    this.updateStatus(statusMsg, result.progress || (this.currentStep / this.statusSteps.length) * 100);
                    this.addDebugLogEntry(`Status: ${statusMsg} (${result.progress || 0}%)`, 'info');
                    
                    // Don't advance steps if we're in TTS processing phase
                    if (!statusMsg.includes('Audio') && this.currentStep < this.statusSteps.length - 1) {
                        this.currentStep++;
                    }
                }

                await new Promise(resolve => setTimeout(resolve, 2000));
                attempts++;

            } catch (error) {
                console.error('Polling error:', error);
                consecutiveErrors++;
                
                // Stop immediately on clear failures
                if (consecutiveErrors >= 2 || error.message.includes('Unable to fetch content') || error.message.includes('API key') || error.message.includes('Generation failed')) {
                    this.addDebugLogEntry(`Generation failed: ${error.message}`, 'error');
                    this.showError(error.message);
                    return;
                }
                
                this.addDebugLogEntry(`Polling error (attempt ${attempts}): ${error.message}`, 'warning');
                await new Promise(resolve => setTimeout(resolve, 2000));
                attempts++;
            }
        }

        if (attempts >= maxAttempts) {
            this.showError('Audio generation is taking longer than expected. The briefing text has been generated and audio processing continues in the background. Check your history in a few minutes for the completed briefing.');
        }
    }

    showStatus() {
        const statusContainer = document.getElementById('status-container');
        const successContainer = document.getElementById('success-container');
        const errorContainer = document.getElementById('error-container');
        const generateBtn = document.getElementById('generate-btn');
        
        if (statusContainer) statusContainer.classList.remove('hidden');
        if (successContainer) successContainer.classList.add('hidden');
        if (errorContainer) errorContainer.classList.add('hidden');
        if (generateBtn) generateBtn.style.display = 'none';
        
        this.startWittyMessages();
    }

    updateStatus(message, progress = 0) {
        // Only update with technical status if not showing witty messages
        if (progress > 80) {
            // Near completion, show actual status
            document.getElementById('status-text').textContent = message;
            this.stopWittyMessages();
        }
    }

    startWittyMessages() {
        this.currentMessageIndex = 0;
        this.showRandomWittyMessage();
        
        this.messageInterval = setInterval(() => {
            this.showRandomWittyMessage();
        }, 2500); // Change message every 2.5 seconds
    }

    stopWittyMessages() {
        if (this.messageInterval) {
            clearInterval(this.messageInterval);
            this.messageInterval = null;
        }
    }

    showRandomWittyMessage() {
        // Pick a random message different from the current one
        let newIndex;
        do {
            newIndex = Math.floor(Math.random() * this.wittyMessages.length);
        } while (newIndex === this.currentMessageIndex && this.wittyMessages.length > 1);
        
        this.currentMessageIndex = newIndex;
        const statusText = document.getElementById('status-text');
        if (statusText) {
            statusText.textContent = this.wittyMessages[this.currentMessageIndex];
        }
    }

    showSuccess(downloadUrl, briefingText = null) {
        this.stopWittyMessages();
        this.switchToBrownLogo();
        
        const statusContainer = document.getElementById('status-container');
        const errorContainer = document.getElementById('error-container');
        const successContainer = document.getElementById('success-container');
        const generateBtn = document.getElementById('generate-btn');
        const demoBtn = document.getElementById('demo-btn');
        const logoLoadingRing = document.getElementById('logo-loading-ring');
        
        if (statusContainer) statusContainer.classList.add('hidden');
        if (errorContainer) errorContainer.classList.add('hidden');
        if (successContainer) successContainer.classList.remove('hidden');
        if (generateBtn) generateBtn.style.display = 'flex';
        if (demoBtn) demoBtn.style.display = 'block';
        if (logoLoadingRing) logoLoadingRing.classList.remove('active');
        
        this.addDebugLogEntry('=== Briefing generation completed successfully ===', 'success');
        this.stopLogPolling();

        const downloadSection = document.getElementById('download-section');
        const briefingTextSection = document.getElementById('text-section');

        if (downloadUrl && downloadSection) {
            // Show download section for MP3 files - set up audio player and download
            const audioPlayer = document.getElementById('briefing-player');
            const audioSource = document.getElementById('audio-source');
            const downloadLink = document.getElementById('download-link');
            
            if (audioSource) audioSource.src = downloadUrl;
            if (audioPlayer) audioPlayer.load();
            if (downloadLink) downloadLink.href = 'download.php?file=' + encodeURIComponent(downloadUrl);
            
            downloadSection.classList.remove('hidden');
            if (briefingTextSection) briefingTextSection.classList.add('hidden');
            
            // Initialize custom audio player for main page
            this.initializeMainAudioPlayer();
        } else if (briefingText && briefingTextSection) {
            // Show text section when no MP3 is generated
            const briefingTextElement = document.getElementById('briefing-text');
            if (briefingTextElement) briefingTextElement.textContent = briefingText;
            
            if (downloadSection) downloadSection.classList.add('hidden');
            briefingTextSection.classList.remove('hidden');
        } else {
            // Hide both if neither is available
            if (downloadSection) downloadSection.classList.add('hidden');
            if (briefingTextSection) briefingTextSection.classList.add('hidden');
        }

        // Hide generate button and show new button
        this.hideGenerateButton();
        this.showNewButton();
    }

    showError(message) {
        this.stopWittyMessages();
        this.switchToBrownLogo();
        
        const statusContainer = document.getElementById('status-container');
        const successContainer = document.getElementById('success-container');
        const errorContainer = document.getElementById('error-container');
        const errorText = document.getElementById('error-text');
        const generateBtn = document.getElementById('generate-btn');
        const logoLoadingRing = document.getElementById('logo-loading-ring');
        
        if (statusContainer) statusContainer.classList.add('hidden');
        if (successContainer) successContainer.classList.add('hidden');
        if (errorContainer) errorContainer.classList.remove('hidden');
        if (errorText) errorText.textContent = message;
        if (generateBtn) generateBtn.style.display = 'flex';
        if (logoLoadingRing) logoLoadingRing.classList.remove('active');
        
        this.addDebugLogEntry(`=== Error occurred: ${message} ===`, 'error');
        this.stopLogPolling();
    }

    hideResults() {
        const statusContainer = document.getElementById('status-container');
        const successContainer = document.getElementById('success-container');
        const errorContainer = document.getElementById('error-container');
        
        if (statusContainer) statusContainer.classList.add('hidden');
        if (successContainer) successContainer.classList.add('hidden');
        if (errorContainer) errorContainer.classList.add('hidden');
    }

    disableButton() {
        const btn = document.getElementById('generate-btn');
        if (btn) {
            btn.disabled = true;
            // Add pulsing animation for main generate button
            btn.classList.add('generate-processing');
            btn.classList.remove('hover:bg-blue-700', 'hover:scale-105');
        }
    }

    enableButton() {
        const generateBtn = document.getElementById('generate-btn');
        if (generateBtn) {
            generateBtn.disabled = false;
            generateBtn.classList.remove('generate-processing');
            generateBtn.classList.add('hover:bg-blue-700', 'hover:scale-105');
        }
    }

    copyBriefingText() {
        const briefingTextElement = document.getElementById('briefing-text');
        if (briefingTextElement) {
            const briefingText = briefingTextElement.textContent;
            if (briefingText) {
                navigator.clipboard.writeText(briefingText).then(() => {
                    this.showToast('Text copied to clipboard!', 'success');
                }).catch(() => {
                    this.showToast('Failed to copy text', 'error');
                });
            }
        }
    }

    showToast(message, type = 'info') {
        // Simple toast notification
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg text-white z-50 ${
            type === 'success' ? 'bg-green-600' : 
            type === 'error' ? 'bg-red-600' : 'bg-blue-600'
        }`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    hideGenerateButton() {
        const generateBtn = document.getElementById('generate-btn');
        if (generateBtn) {
            generateBtn.style.display = 'none';
        }
    }

    showGenerateButton() {
        const generateBtn = document.getElementById('generate-btn');
        if (generateBtn) {
            generateBtn.style.display = 'flex';
        }
    }

    showNewButton() {
        const newBtn = document.getElementById('new-btn');
        if (newBtn) {
            newBtn.classList.remove('hidden');
        }
    }

    hideNewButton() {
        const newBtn = document.getElementById('new-btn');
        if (newBtn) {
            newBtn.classList.add('hidden');
        }
    }

    startNewBriefing() {
        // Reset interface to initial state
        this.hideResults();
        this.showGenerateButton();
        this.hideNewButton();
        this.enableButton();
        this.switchToBrownLogo();
        
        // Reset any form state if needed
        this.isGenerating = false;
        this.currentStep = 0;
        
        // Show a confirmation message
        this.showToast('Ready to generate a new briefing!', 'success');
    }



    switchToBrownLogo() {
        const logoImg = document.querySelector('img[alt="NewsBear Logo"]');
        if (logoImg) {
            logoImg.src = 'attached_assets/newsbear_brown_logo.png?t=' + Date.now();
        }
    }

    initializeMainAudioPlayer() {
        const player = document.getElementById('main-audio-player');
        if (!player || player.initialized) return;
        
        const audio = player.querySelector('audio');
        const playPauseBtn = player.querySelector('.play-pause-btn');
        const progressContainer = player.querySelector('.progress-container');
        const progressBar = player.querySelector('.progress-bar');
        const progressHandle = player.querySelector('.progress-handle');
        const currentTimeSpan = player.querySelector('.current-time');
        const durationSpan = player.querySelector('.duration');
        const volumeBtn = player.querySelector('.volume-btn');
        const volumeSlider = player.querySelector('.volume-slider');
        const volumeContainer = player.querySelector('.volume-slider-container');
        
        // Check if all required elements exist
        if (!audio || !playPauseBtn || !progressContainer || !progressBar || !progressHandle || !currentTimeSpan || !durationSpan) {
            console.warn('Audio player elements missing, skipping initialization');
            return;
        }
        
        let isDragging = false;
        player.initialized = true;
        
        // Play/Pause functionality
        playPauseBtn.addEventListener('click', () => {
            if (audio.paused) {
                audio.play();
                playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
            } else {
                audio.pause();
                playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
            }
        });
        
        // Update progress and time
        audio.addEventListener('timeupdate', () => {
            if (!isDragging && progressBar && progressHandle && currentTimeSpan) {
                const progress = (audio.currentTime / audio.duration) * 100;
                if (!isNaN(progress)) {
                    progressBar.style.width = progress + '%';
                    progressHandle.style.left = progress + '%';
                    currentTimeSpan.textContent = this.formatTime(audio.currentTime);
                }
            }
        });
        
        // Load duration when metadata is loaded
        audio.addEventListener('loadedmetadata', () => {
            if (durationSpan && !isNaN(audio.duration)) {
                durationSpan.textContent = this.formatTime(audio.duration);
            }
        });
        
        // Wait for audio to be ready for seeking
        audio.addEventListener('canplay', () => {
            // Audio is ready for playback and seeking
        });
        
        // Handle seeking events
        audio.addEventListener('seeking', () => {
            // Audio is seeking to a new position
        });
        
        audio.addEventListener('seeked', () => {
            // Audio has finished seeking
        });
        
        // Progress bar clicking and dragging
        progressContainer.addEventListener('mousedown', (e) => {
            isDragging = true;
            e.preventDefault();
            this.updateMainProgress(e, audio, progressContainer, progressBar, progressHandle, currentTimeSpan);
        });
        
        progressContainer.addEventListener('mousemove', (e) => {
            if (isDragging) {
                e.preventDefault();
                this.updateMainProgress(e, audio, progressContainer, progressBar, progressHandle, currentTimeSpan);
            }
        });
        
        document.addEventListener('mouseup', () => {
            if (isDragging) {
                isDragging = false;
            }
        });
        
        // Also handle click events separately for immediate seeking
        progressContainer.addEventListener('click', (e) => {
            if (!isDragging) {
                this.updateMainProgress(e, audio, progressContainer, progressBar, progressHandle, currentTimeSpan);
            }
        });
        
        // Volume control
        if (volumeBtn && volumeContainer) {
            volumeBtn.addEventListener('click', () => {
                volumeContainer.classList.toggle('hidden');
            });
        }
        
        if (volumeSlider) {
            volumeSlider.addEventListener('input', () => {
                audio.volume = volumeSlider.value / 100;
                if (volumeBtn) {
                    this.updateVolumeIcon(volumeBtn, audio.volume);
                }
            });
        }
        
        // Reset when audio ends
        audio.addEventListener('ended', () => {
            if (playPauseBtn) {
                playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
            }
            if (progressBar) {
                progressBar.style.width = '0%';
            }
            if (progressHandle) {
                progressHandle.style.left = '0%';
            }
            if (currentTimeSpan) {
                currentTimeSpan.textContent = '0:00';
            }
        });
        
        // Show handle on hover
        progressContainer.addEventListener('mouseenter', () => {
            progressHandle.style.opacity = '1';
        });
        
        progressContainer.addEventListener('mouseleave', () => {
            if (!isDragging) {
                progressHandle.style.opacity = '0';
            }
        });
    }

    updateMainProgress(e, audio, progressContainer, progressBar, progressHandle, currentTimeSpan) {
        if (!progressContainer || !progressBar || !progressHandle || !currentTimeSpan || !audio) {
            return;
        }
        
        const rect = progressContainer.getBoundingClientRect();
        const pos = (e.clientX - rect.left) / rect.width;
        const clampedPos = Math.max(0, Math.min(1, pos));
        
        if (isNaN(audio.duration) || audio.duration === 0) {
            return;
        }
        
        const newTime = clampedPos * audio.duration;
        
        // Direct seeking without complex pause/resume logic
        if (audio.readyState >= 2) { // HAVE_CURRENT_DATA or higher
            audio.currentTime = newTime;
        }
        
        // Update visual elements
        const progress = clampedPos * 100;
        progressBar.style.width = progress + '%';
        progressHandle.style.left = progress + '%';
        currentTimeSpan.textContent = this.formatTime(newTime);
    }

    updateVolumeIcon(volumeBtn, volume) {
        if (!volumeBtn) return;
        
        const icon = volumeBtn.querySelector('i');
        if (!icon) return;
        
        if (volume === 0) {
            icon.className = 'fas fa-volume-mute';
        } else if (volume < 0.5) {
            icon.className = 'fas fa-volume-down';
        } else {
            icon.className = 'fas fa-volume-up';
        }
    }

    formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }

    initDarkTheme() {
        // Check for saved dark theme preference or system preference
        const savedTheme = localStorage.getItem('darkTheme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedTheme === 'true' || (savedTheme === null && systemPrefersDark)) {
            this.enableDarkTheme();
        }
        
        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (localStorage.getItem('darkTheme') === null) {
                if (e.matches) {
                    this.enableDarkTheme();
                } else {
                    this.disableDarkTheme();
                }
            }
        });
    }

    enableDarkTheme() {
        document.body.classList.add('dark-theme');
        localStorage.setItem('darkTheme', 'true');
        
        // Update toggle switch if it exists
        const darkThemeToggle = document.getElementById('darkTheme');
        if (darkThemeToggle) {
            darkThemeToggle.checked = true;
        }
    }

    disableDarkTheme() {
        document.body.classList.remove('dark-theme');
        localStorage.setItem('darkTheme', 'false');
        
        // Update toggle switch if it exists
        const darkThemeToggle = document.getElementById('darkTheme');
        if (darkThemeToggle) {
            darkThemeToggle.checked = false;
        }
    }

    toggleDarkTheme() {
        if (document.body.classList.contains('dark-theme')) {
            this.disableDarkTheme();
        } else {
            this.enableDarkTheme();
        }
    }

    async checkDebugLogSettings() {
        try {
            const response = await fetch('config/user_settings.json');
            if (response.ok) {
                const settings = await response.json();
                this.debugLogEnabled = settings.showLogWindow || false;
            }
        } catch (error) {
            console.warn('Could not load debug settings:', error);
            this.debugLogEnabled = false;
        }
    }

    showDebugLog() {
        if (!this.debugLogEnabled) return;
        
        const debugContainer = document.getElementById('debug-log-container');
        if (debugContainer) {
            debugContainer.classList.remove('hidden');
            this.clearDebugLog();
            this.addDebugLogEntry('Debug log initialized - waiting for generation to start...', 'info');
        }
    }

    hideDebugLog() {
        const debugContainer = document.getElementById('debug-log-container');
        if (debugContainer) {
            debugContainer.classList.add('hidden');
        }
        this.stopLogPolling();
    }

    addDebugLogEntry(message, type = 'info') {
        if (!this.debugLogEnabled) return;
        
        const logContent = document.getElementById('debug-log-content');
        if (logContent) {
            const timestamp = new Date().toLocaleTimeString();
            const colorClass = {
                'info': 'text-green-400',
                'warning': 'text-yellow-400', 
                'error': 'text-red-400',
                'success': 'text-blue-400'
            }[type] || 'text-green-400';
            
            const logEntry = document.createElement('div');
            logEntry.className = `${colorClass} mb-1`;
            logEntry.innerHTML = `<span class="text-gray-500">[${timestamp}]</span> ${message}`;
            
            logContent.appendChild(logEntry);
            logContent.scrollTop = logContent.scrollHeight;
        }
    }

    clearDebugLog() {
        const logContent = document.getElementById('debug-log-content');
        if (logContent) {
            logContent.innerHTML = '';
        }
        this.lastLogCount = 0;
    }

    startLogPolling(sessionId) {
        if (!this.debugLogEnabled || this.logPollingInterval) return;
        
        // Reset log tracking
        this.lastLogCount = 0;
        
        // Fetch logs immediately first
        this.fetchDebugLogs(sessionId);
        
        this.logPollingInterval = setInterval(() => {
            this.fetchDebugLogs(sessionId);
        }, 500); // Poll every 500ms to reduce server load
    }

    async fetchDebugLogs(sessionId) {
        try {
            const response = await fetch(`api/debug_log.php?session=${sessionId}&_t=${Date.now()}`);
            if (response.ok) {
                const text = await response.text();
                if (text.trim()) {
                    const data = JSON.parse(text);
                    if (data.logs && data.logs.length > this.lastLogCount) {
                        // Only add new logs since last poll
                        const newLogs = data.logs.slice(this.lastLogCount);
                        newLogs.forEach(log => {
                            this.addDebugLogEntry(log.message, log.type.toLowerCase() || 'info');
                        });
                        this.lastLogCount = data.logs.length;
                    }
                }
            }
        } catch (error) {
            // Silently handle JSON parsing errors during polling
        }
    }



    async pollTtsStatus(jobId, sessionId) {
        const maxAttempts = 60; // Poll for up to 10 minutes (60 attempts * 10 seconds)
        let attempts = 0;
        
        while (attempts < maxAttempts && this.isGenerating) {
            try {
                const response = await fetch(`api/tts_status.php?job_id=${encodeURIComponent(jobId)}`);
                const result = await response.json();
                
                if (result.status === 'completed' && result.audio_file) {
                    this.updateStatus('Audio generation completed!', 100);
                    this.addDebugLogEntry(`TTS job completed: ${result.audio_file}`, 'success');
                    
                    // Wait a moment for the background processor to finalize the briefing
                    await new Promise(resolve => setTimeout(resolve, 2000));
                    
                    // Check the session status for final result
                    await this.pollStatus(sessionId);
                    return;
                    
                } else if (result.status === 'failed') {
                    throw new Error('Audio generation failed');
                    
                } else if (result.status === 'processing') {
                    const progress = Math.min(95, 85 + (result.progress || 0) * 0.1);
                    this.updateStatus(`Generating audio... ${result.progress || 0}%`, progress);
                    this.addDebugLogEntry(`TTS processing: ${result.progress || 0}%`, 'info');
                    
                } else if (result.status === 'queued') {
                    this.updateStatus('Audio generation queued...', 85);
                    this.addDebugLogEntry('TTS job waiting in queue', 'info');
                }
                
                attempts++;
                await new Promise(resolve => setTimeout(resolve, 10000)); // Wait 10 seconds
                
            } catch (error) {
                console.error('TTS polling error:', error);
                attempts++;
                if (attempts >= maxAttempts) {
                    throw new Error('Audio generation timeout');
                }
                await new Promise(resolve => setTimeout(resolve, 10000));
            }
        }
        
        throw new Error('Audio generation timed out');
    }

    stopLogPolling() {
        if (this.logPollingInterval) {
            clearInterval(this.logPollingInterval);
            this.logPollingInterval = null;
        }
    }
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new NewsBriefApp();
});