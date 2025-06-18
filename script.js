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
    }

    async generateBriefing() {
        if (this.isGenerating) return;

        this.isGenerating = true;
        this.currentStep = 0;
        this.hideResults();
        this.showStatus();
        this.disableButton();
        this.switchToRedLogo();

        try {
            const endpoint = 'api/generate.php';
            
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout
            
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({}),
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.status === 'processing') {
                await this.pollStatus(result.sessionId);
            } else if (result.status === 'success') {
                this.showSuccess(result.downloadUrl, result.briefingText);
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
        const maxAttempts = 60;
        let attempts = 0;

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
                    this.updateStatus(result.message || this.statusSteps[this.currentStep], result.progress || (this.currentStep / this.statusSteps.length) * 100);
                    
                    if (this.currentStep < this.statusSteps.length - 1) {
                        this.currentStep++;
                    }
                }

                await new Promise(resolve => setTimeout(resolve, 2000));
                attempts++;

            } catch (error) {
                console.error('Polling error:', error);
                this.showError(error.message || 'Status check failed');
                return;
            }
        }

        if (attempts >= maxAttempts) {
            this.showError('Generation timed out. Please try again.');
        }
    }

    showStatus() {
        document.getElementById('status-container').classList.remove('hidden');
        document.getElementById('success-container').classList.add('hidden');
        document.getElementById('error-container').classList.add('hidden');
        // Hide the generate button during generation
        document.getElementById('generate-btn').style.display = 'none';
        document.getElementById('demo-btn').style.display = 'none';
        this.startWittyMessages();
        this.switchToRedLogo();
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
        document.getElementById('status-text').textContent = this.wittyMessages[this.currentMessageIndex];
    }

    showSuccess(downloadUrl, briefingText = null) {
        this.stopWittyMessages();
        this.switchToBrownLogo();
        document.getElementById('status-container').classList.add('hidden');
        document.getElementById('error-container').classList.add('hidden');
        document.getElementById('success-container').classList.remove('hidden');
        // Show buttons again after successful generation
        document.getElementById('generate-btn').style.display = 'flex';
        document.getElementById('demo-btn').style.display = 'block';

        const downloadSection = document.getElementById('download-section');
        const briefingTextSection = document.getElementById('briefing-text-section');

        if (downloadUrl) {
            // Show download section for MP3 files - set up audio player and download
            const audioPlayer = document.getElementById('briefing-player');
            const audioSource = document.getElementById('audio-source');
            const downloadLink = document.getElementById('download-link');
            
            audioSource.src = downloadUrl;
            audioPlayer.load();
            downloadLink.href = 'download.php?file=' + encodeURIComponent(downloadUrl);
            downloadSection.classList.remove('hidden');
            briefingTextSection.classList.add('hidden');
            
            // Initialize custom audio player for main page
            this.initializeMainAudioPlayer();
        } else if (briefingText) {
            // Show text section when no MP3 is generated
            document.getElementById('briefing-text').textContent = briefingText;
            downloadSection.classList.add('hidden');
            briefingTextSection.classList.remove('hidden');
        } else {
            // Hide both if neither is available
            downloadSection.classList.add('hidden');
            briefingTextSection.classList.add('hidden');
        }

        // Hide generate button and show new button
        this.hideGenerateButton();
        this.showNewButton();
    }

    showError(message) {
        this.stopWittyMessages();
        this.switchToBrownLogo();
        document.getElementById('status-container').classList.add('hidden');
        document.getElementById('success-container').classList.add('hidden');
        document.getElementById('error-container').classList.remove('hidden');
        document.getElementById('error-text').textContent = message;
        // Show buttons again after error
        document.getElementById('generate-btn').style.display = 'flex';
        document.getElementById('demo-btn').style.display = 'block';
    }

    hideResults() {
        document.getElementById('status-container').classList.add('hidden');
        document.getElementById('success-container').classList.add('hidden');
        document.getElementById('error-container').classList.add('hidden');
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
        const briefingText = document.getElementById('briefing-text').textContent;
        if (briefingText) {
            navigator.clipboard.writeText(briefingText).then(() => {
                this.showToast('Text copied to clipboard!', 'success');
            }).catch(() => {
                this.showToast('Failed to copy text', 'error');
            });
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

    switchToRedLogo() {
        const logoImg = document.querySelector('img[alt="NewsBear Logo"]');
        if (logoImg) {
            logoImg.src = 'attached_assets/newsbear_red_logo.png?t=' + Date.now();
        }
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
            if (!isDragging) {
                const progress = (audio.currentTime / audio.duration) * 100;
                progressBar.style.width = progress + '%';
                progressHandle.style.left = progress + '%';
                currentTimeSpan.textContent = this.formatTime(audio.currentTime);
            }
        });
        
        // Load duration when metadata is loaded
        audio.addEventListener('loadedmetadata', () => {
            durationSpan.textContent = this.formatTime(audio.duration);
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
        volumeBtn.addEventListener('click', () => {
            volumeContainer.classList.toggle('hidden');
        });
        
        volumeSlider.addEventListener('input', () => {
            audio.volume = volumeSlider.value / 100;
            this.updateVolumeIcon(volumeBtn, audio.volume);
        });
        
        // Reset when audio ends
        audio.addEventListener('ended', () => {
            playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
            progressBar.style.width = '0%';
            progressHandle.style.left = '0%';
            currentTimeSpan.textContent = '0:00';
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
        const icon = volumeBtn.querySelector('i');
        
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
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new NewsBriefApp();
});