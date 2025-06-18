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

        try {
            const endpoint = 'api/generate.php';
            
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({})
            });

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
            this.showError(error.message || 'Failed to generate briefing');
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
        document.getElementById('progress-bar').style.width = progress + '%';
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
        this.switchToBlueLogo();
        document.getElementById('status-container').classList.add('hidden');
        document.getElementById('error-container').classList.add('hidden');
        document.getElementById('success-container').classList.remove('hidden');

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
        this.switchToBlueLogo();
        document.getElementById('status-container').classList.add('hidden');
        document.getElementById('success-container').classList.add('hidden');
        document.getElementById('error-container').classList.remove('hidden');
        document.getElementById('error-text').textContent = message;
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
        this.switchToBlueLogo();
        
        // Reset any form state if needed
        this.isGenerating = false;
        this.currentStep = 0;
        
        // Show a confirmation message
        this.showToast('Ready to generate a new briefing!', 'success');
    }

    switchToRedLogo() {
        const logoImg = document.querySelector('img[src*="newsbear_blue_logo.png"], img[src*="newsbear_red_logo.png"]');
        console.log('Switching to red logo, found element:', logoImg);
        if (logoImg) {
            console.log('Current src:', logoImg.src);
            logoImg.src = 'attached_assets/newsbear_red_logo.png';
            console.log('New src:', logoImg.src);
        }
    }

    switchToBlueLogo() {
        const logoImg = document.querySelector('img[src*="newsbear_blue_logo.png"], img[src*="newsbear_red_logo.png"]');
        if (logoImg) {
            logoImg.src = 'attached_assets/newsbear_blue_logo.png';
        }
    }
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new NewsBriefApp();
});