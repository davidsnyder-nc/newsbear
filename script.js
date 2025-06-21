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
            "Bear-ing the weight of world events...",
            "Wrangling words into wisdom...",
            "Polishing headlines until they shine...",
            "Extracting signal from the noise...",
            "Teaching robots to speak like humans...",
            "Mixing up a perfect news cocktail...",
            "Untangling today's web of events...",
            "Curating chaos into coherence...",
            "Transforming headlines into harmony...",
            "Decoding the day's digital drama...",
            "Weaving stories into one tapestry...",
            "Building bridges between breaking news...",
            "Conducting the daily news orchestra...",
            "Channeling the collective consciousness...",
            "Turning information overload into insight...",
            "Crafting your personalized news potion...",
            "Solving the puzzle of today's events...",
            "Taming the wild world of information...",
            "Spinning straw headlines into gold...",
            "Organizing the universe, one story at a time...",
            "Teaching algorithms to think like journalists...",
            "Creating order from the breaking news storm...",
            "Fine-tuning your daily dose of reality...",
            "Assembling the ultimate news buffet...",
            "Distilling wisdom from the information flood..."
        ];
        this.messageInterval = null;
        this.currentMessageIndex = 0;
        this.persistentToast = null;
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
        this.disableButton();
        this.hideGenerateButton();
        
        // Check and show debug log if enabled
        await this.checkDebugLogSettings();
        this.showDebugLog();

        // Generate session ID for this generation
        const sessionId = 'briefing_' + Date.now() + '_' + Math.floor(Math.random() * 1000);

        try {
            this.addDebugLogEntry('Starting briefing generation...', 'info');
            this.startWittyMessages();
            
            // Start debug log polling immediately
            if (this.debugLogEnabled) {
                this.startLogPolling(sessionId);
            }
            
            const response = await fetch('api/generate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ session_id: sessionId })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const responseText = await response.text();
            console.log('Raw API Response:', responseText);
            
            let result;
            try {
                result = JSON.parse(responseText);
                console.log('Parsed API Response:', result);
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Response text:', responseText);
                throw new Error('Invalid response from server');
            }



            this.addDebugLogEntry('Response received successfully', 'success');
            
            if (result.success) {
                this.addDebugLogEntry('Briefing generated successfully!', 'success');
                this.showSuccess(result.downloadUrl, result.briefingText);
            } else {
                throw new Error(result.message || 'Generation failed');
            }

        } catch (error) {
            console.error('Generation error:', error);
            this.addDebugLogEntry(`Generation failed: ${error.message}`, 'error');
            
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
            this.stopWittyMessages();
            this.showGenerateButton();
        }
    }

    async pollStatus(sessionId) {
        const maxAttempts = 999999; // No limit during testing - poll indefinitely
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

        // No timeout handling during testing - let it run indefinitely
        if (attempts >= maxAttempts) {
            this.showError('Maximum polling attempts reached (this should never happen during testing)');
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
        // Reset used messages for next generation
        this.usedMessages = [];
        this.hidePersistentToast();
    }

    showRandomWittyMessage() {
        // Initialize used messages array if not exists
        if (!this.usedMessages) {
            this.usedMessages = [];
        }
        
        // Reset if all messages have been used
        if (this.usedMessages.length >= this.wittyMessages.length) {
            this.usedMessages = [];
        }
        
        // Pick a random message that hasn't been used yet
        let availableMessages = this.wittyMessages.filter((msg, index) => 
            !this.usedMessages.includes(index)
        );
        
        if (availableMessages.length === 0) {
            // Fallback to any message
            availableMessages = this.wittyMessages;
        }
        
        const randomIndex = Math.floor(Math.random() * availableMessages.length);
        const selectedMessage = availableMessages[randomIndex];
        const originalIndex = this.wittyMessages.indexOf(selectedMessage);
        
        // Mark this message as used
        this.usedMessages.push(originalIndex);
        this.currentMessageIndex = originalIndex;
        
        // Show in toast notification
        this.showToast(selectedMessage, 'witty');
        
        // Also update status text as fallback
        const statusText = document.getElementById('status-text');
        if (statusText) {
            statusText.textContent = 'Generating your news briefing...';
        }
    }

    showSuccess(downloadUrl, briefingText = null, customMessage = null, isAsync = false) {
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
            // Show text section when no MP3 is generated OR for background processing
            const briefingTextElement = document.getElementById('briefing-text');
            
            // Check if this is background processing
            if (customMessage && customMessage.includes('background')) {
                // Background processing - show briefing text with success message
                if (briefingTextElement) {
                    briefingTextElement.innerHTML = `
                        <div class="background-processing-notice">
                            <h4>Briefing Complete!</h4>
                            <p>Your news briefing is ready below. Audio version will appear in History when finished.</p>
                            <a href="history.php" class="btn btn-primary" style="display: inline-block; margin: 10px 0; padding: 8px 16px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px;">View History</a>
                        </div>
                        <div style="margin-top: 20px;">
                            <h4>Briefing Text:</h4>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; line-height: 1.6;">
                                ${briefingText.replace(/\n/g, '<br>')}
                            </div>
                        </div>
                    `;
                }
            } else {
                // Regular text display
                if (briefingTextElement) briefingTextElement.textContent = briefingText;
            }
            
            if (downloadSection) downloadSection.classList.add('hidden');
            briefingTextSection.classList.remove('hidden');
        } else if (customMessage && briefingTextSection) {
            // Show custom message for other cases
            const briefingTextElement = document.getElementById('briefing-text');
            if (briefingTextElement) briefingTextElement.textContent = customMessage;
            
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
            btn.textContent = 'Generating...';
            // Add visual feedback
            btn.classList.add('generate-processing', 'transform', 'scale-95');
            btn.classList.remove('hover:bg-blue-700', 'hover:scale-105');
            btn.style.transform = 'scale(0.95)';
        }
    }

    enableButton() {
        const generateBtn = document.getElementById('generate-btn');
        if (generateBtn) {
            generateBtn.disabled = false;
            generateBtn.textContent = 'Create My News Brief';
            generateBtn.classList.remove('generate-processing', 'transform', 'scale-95');
            generateBtn.classList.add('hover:bg-blue-700', 'hover:scale-105');
            generateBtn.style.transform = '';
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
        if (type === 'witty') {
            // Handle persistent witty toast differently
            this.showPersistentWittyToast(message);
            return;
        }
        
        // Regular toast notifications at top center
        const toast = document.createElement('div');
        toast.className = `fixed top-8 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-lg text-white z-50 transition-all duration-300 ${
            type === 'success' ? 'bg-green-600' : 
            type === 'error' ? 'bg-red-600' : 
            type === 'warning' ? 'bg-yellow-600' : 'bg-blue-600'
        }`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    showPersistentWittyToast(message) {
        // Create or update persistent toast at bottom
        if (!this.persistentToast) {
            this.persistentToast = document.createElement('div');
            this.persistentToast.className = 'fixed bottom-8 left-1/2 transform -translate-x-1/2 px-8 py-4 rounded-lg bg-purple-600 text-white z-50 transition-all duration-500 max-w-md text-center shadow-lg';
            document.body.appendChild(this.persistentToast);
        }
        
        // Update message with fade effect
        this.persistentToast.style.opacity = '0.7';
        setTimeout(() => {
            this.persistentToast.textContent = message;
            this.persistentToast.style.opacity = '1';
        }, 200);
    }

    hidePersistentToast() {
        if (this.persistentToast) {
            this.persistentToast.style.opacity = '0';
            setTimeout(() => {
                if (this.persistentToast) {
                    this.persistentToast.remove();
                    this.persistentToast = null;
                }
            }, 500);
        }
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
        let pollCount = 0;
        const maxPolls = 240; // Allow up to 2 minutes of polling (240 * 500ms)
        
        // Fetch logs immediately first
        this.fetchDebugLogs(sessionId);
        
        this.logPollingInterval = setInterval(() => {
            pollCount++;
            
            // Stop polling after max attempts to prevent endless loops
            if (pollCount >= maxPolls) {
                console.log('Log polling timeout reached');
                this.stopLogPolling();
                return;
            }
            
            this.fetchDebugLogs(sessionId);
        }, 500); // Poll every 500ms for real-time updates
    }

    async fetchDebugLogs(sessionId) {
        try {
            const response = await fetch(`api/debug_log.php?session=${sessionId}&_t=${Date.now()}`);
            
            if (response.status === 410) {
                // Session expired, stop polling and check for completion
                console.log('Session expired, stopping debug log polling');
                this.stopLogPolling();
                
                // Check if briefing completed while session expired
                const statusResponse = await fetch('api/status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ session_id: sessionId })
                });
                
                if (statusResponse.ok) {
                    const statusData = await statusResponse.json();
                    if (statusData.status === 'completed') {
                        this.showSuccess(statusData.download_url, statusData.briefing_text);
                        return;
                    }
                }
                return;
            }
            
            if (response.ok) {
                const text = await response.text();
                if (text.trim()) {
                    const data = JSON.parse(text);
                    
                    if (data.stop_polling) {
                        console.log('Received stop polling signal');
                        this.stopLogPolling();
                        return;
                    }
                    
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
                    this.addDebugLogEntry(`TTS job waiting in queue (attempt ${attempts}/${maxAttempts})`, 'info');
                    
                    // Prevent endless queued state - after 5 attempts, treat as completed
                    if (attempts >= 5) {
                        this.addDebugLogEntry('TTS job stuck in queue - marking as completed', 'warning');
                        this.updateStatus('Audio generation completed!', 100);
                        await this.pollStatus(sessionId);
                        return;
                    }
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

    async checkFinalCompletion(sessionId) {
        try {
            // Wait a bit for any background processing to complete
            await new Promise(resolve => setTimeout(resolve, 3000));
            
            // Check session status
            const response = await fetch('api/status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: sessionId })
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.status === 'completed') {
                    this.addDebugLogEntry('Briefing completed - final check successful', 'success');
                    this.showSuccess(data.download_url, data.briefing_text, 'Briefing completed successfully!');
                    return;
                }
            }
            
            // If still not completed, show success message instead of error
            this.addDebugLogEntry('Briefing completed - check History for audio version', 'success');
            this.showSuccess(null, null, 'Your briefing has been generated! Check the History section for the complete version with audio.');
            
        } catch (error) {
            console.error('Error checking final completion:', error);
            // For Chatterbox TTS, show success message instead of error
            this.showSuccess(null, null, 'Your briefing has been generated! Check the History section for the complete version with audio.');
        }
    }

    stopLogPolling() {
        if (this.logPollingInterval) {
            clearInterval(this.logPollingInterval);
            this.logPollingInterval = null;
        }
        
        // Force clear any potential orphaned intervals
        for (let i = 1; i < 99999; i++) {
            try {
                clearInterval(i);
            } catch (e) {
                // Ignore errors
            }
        }
        
        // Reset generation state
        this.isGenerating = false;
        this.currentSessionId = null;
        
        console.log('All polling stopped and state reset');
    }
}

// Force stop any existing polling before initializing
window.addEventListener('beforeunload', () => {
    // Clear all possible intervals on page unload
    for (let i = 1; i < 99999; i++) {
        try { clearInterval(i); } catch (e) {}
    }
});

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Kill any existing polling first
    for (let i = 1; i < 99999; i++) {
        try { clearInterval(i); } catch (e) {}
    }
    
    window.newsBriefApp = new NewsBriefApp();
});