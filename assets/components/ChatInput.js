// Global TypewriterManager instance - shared across all ChatInput instances
window.typewriterManagerModule = window.typewriterManagerModule || null;
window.typewriterManagerInstance = window.typewriterManagerInstance || null;

/**
 * Try to import TypewriterManager as an ESM module
 * @async
 * @returns {Object|null} The imported module or null if import fails
 */
const importTypewriterManager = async () => {
    if (window.typewriterManagerModule) return window.typewriterManagerModule;

    try {
        window.typewriterManagerModule = await import('./TypewriterManager.esm.min.js');
        if (!window.typewriterManagerLoadLogged) {
            console.log('TypewriterManager loaded successfully');
            window.typewriterManagerLoadLogged = true;
        }
        return window.typewriterManagerModule;
    } catch (error) {
        console.error('Error importing TypewriterManager:', error);
        return null;
    }
};

/**
 * Custom element for chat input with message sending functionality
 * @class
 * @extends HTMLElement
 */
class ChatInput extends HTMLElement {
    /**
     * Attributes to observe for changes
     * @static
     * @returns {string[]} List of attributes to watch
     */
    static get observedAttributes() {
        return ['endpoint', 'placeholder', 'api-key', 'disabled'];
    }

    /**
     * Initialize the component WITH shadow DOM for proper encapsulation
     */
    constructor() {
        super();
        // Create shadow DOM for proper encapsulation
        this.shadow = this.attachShadow({ mode: 'open' });

        // Internal state
        this.isSending = false;
        this.isTyping = false;
        this.currentTypewriter = null;
        this.themeStyles = '';

        // Initialize TypewriterManager after constructor
        this.initTypewriterManager();
    }

    /**
     * Called when the element is inserted into the DOM
     * Sets up event listeners and shows welcome message
     */
    async connectedCallback() {
        // Apply theme class if available
        if (typeof cerebrolData !== 'undefined' && cerebrolData.selectedTheme) {
            this.classList.add(`theme-${cerebrolData.selectedTheme}`);
        }

        // Load theme styles from DOM into Shadow DOM
        await this.loadThemeFromDOM();
        this.render();
        this._setupEventListeners();

        // Welcome message will be shown when chat opens via updateButtonStateOnShow()
    }

    /**
     * Load theme styles from DOM stylesheets into Shadow DOM
     */
    async loadThemeFromDOM() {
        this.themeStyles = this.getDefaultStyles();

        try {
            let chatCss = '';
            let themeCss = '';

            // Get all loaded stylesheets
            const stylesheets = document.querySelectorAll('link[rel="stylesheet"]');

            for (const link of stylesheets) {
                const href = link.href;

                // Find chat.css
                if (href.includes('/css/chat.css')) {
                    const response = await fetch(href);
                    if (response.ok) {
                        chatCss = await response.text();
                    }
                }

                // Find theme CSS (cerebroly-theme.css or dark-theme.css)
                if (href.includes('/css/themes/')) {
                    const response = await fetch(href);
                    if (response.ok) {
                        themeCss = await response.text();
                    }
                }
            }

            // Combine styles for Shadow DOM
            if (chatCss || themeCss) {
                this.themeStyles = this.getDefaultStyles() + '\n' + chatCss + '\n' + themeCss;
            }

        } catch (error) {
            // Keep default styles
        }
    }

    /**
     * Provides complete styles for ChatInput including theme application
     */
    getDefaultStyles() {
        return `
            :host {
                --primary-blue: #0066ff;
                --green-600: #4d9c1f;
                --text-100: #1f1c26;
                
                position: absolute;
                bottom: 0;
                background-color: var(--primary-blue);
                color: var(--text-100);
                padding: 0;
                display: none;
                z-index: 10;
                max-width: 100%;
                width: 100%;
                border-radius: 1rem;
                margin: auto;
                box-sizing: border-box;
            }
            
            /* Dark theme override */
            :host(.theme-dark-theme) {
                background-color: #1a1427;
            }
            
            :host(.medium),
            :host(.fullscreen) {
                display: block !important;
            }
            
            .container-input {
                max-width: 800px;
                margin: 0 auto;
                width: 100%;
                padding: 10px 20px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                position: relative;
                box-sizing: border-box;
                gap: 10px;
            }
            
            input {
                flex: 1;
                padding: 8px;
                border: 2px solid transparent;
                border-radius: 1rem;
                margin-right: 0px;
                background-color: #fff;
                color: var(--text-100);
                font-size: 16px;
                outline: none;
            }
            
            /* Dark theme input override */
            :host(.theme-dark-theme) input {
                border: 1px solid rgba(255, 255, 255, 0.1);
                background-color: #202020;
                color: #ffffff;
            }
            
            input:focus {
                outline: 1px solid #fff;
                outline-offset: 1px;
            }
            
            :host(.theme-dark-theme) input:focus {
                outline: 1px solid rgba(127, 90, 240, 1);
                outline-offset: 1px;
            }
            
            button {
                background-color: white;
                border: none;
                padding: 8px 16px;
                font-weight: 600;
                border-radius: 1rem;
                cursor: pointer;
                display: flex;
                align-items: center;
                height: 44px;
                transition: all 0.2s ease;
                flex-shrink: 0;
            }
            
            /* Dark theme button override */
            :host(.theme-dark-theme) button {
                background: rgba(127, 90, 240, 1);
                color: white;
            }
            
            button:hover {
                background-color: white;
            }
            
            :host(.theme-dark-theme) button:hover {
                background: rgba(127, 90, 240, 0.9);
            }
            
            button:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            
            /* Button Icon States */
            button svg.icon {
                fill: #6b7280;
                transition: fill 0.2s ease;
                width: 16px;
                height: auto;
            }

            /* Send Button State */
            button.btn-send svg.icon {
                fill: var(--primary-blue) !important;
            }

            button.btn-send:hover svg.icon {
                fill: var(--green-600);
            }
            
            /* Dark theme icon overrides */
            :host(.theme-dark-theme) button svg.icon {
                fill: white !important;
            }

            :host(.theme-dark-theme) button.btn-send svg.icon {
                fill: white !important;
            }

            :host(.theme-dark-theme) button.btn-send:hover svg.icon {
                fill: white !important;
            }

            /* Stop Button State */
            button.btn-stop svg.icon {
                fill: #9ca3af !important;
            }

            button.btn-stop:hover svg.icon {
                fill: #dc2626;
            }

            /* Default Button State */
            button.btn-default svg.icon,
            button:not(.btn-send):not(.btn-stop) svg.icon {
                fill: #3b82f6;
            }

            button.btn-default:hover svg.icon,
            button:not(.btn-send):not(.btn-stop):hover svg.icon {
                fill: #2563eb;
            }
            
            .typing-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: transparent;
                backdrop-filter: blur(2px);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 2;
                border-radius: 1rem;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s ease;
            }
            
            .typing-overlay.active {
                opacity: 1;
            }
            
            .spinner {
                width: 10px;
                height: 10px;
                border: 2px solid rgba(0, 0, 0, 0.384);
                border-radius: 50%;
                border-top-color: var(--primary-blue);
                animation: spin 1s linear infinite;
            }
            
            /* Dark theme spinner override */
            :host(.theme-dark-theme) .spinner {
                border: 2px solid rgba(255, 255, 255, 0.486);
                border-top-color: rgba(108, 91, 185, 1);
            }
            
            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }
        `;
    }

    /**
     * Initialize the TypewriterManager instance (shared globally)
     * @async
     */
    async initTypewriterManager() {
        // Use global instance if it exists
        if (window.typewriterManagerInstance) {
            this.typewriterManager = window.typewriterManagerInstance;
            return;
        }

        try {
            const module = await importTypewriterManager();
            if (module && module.TypewriterManager) {
                // Create global instance only once
                window.typewriterManagerInstance = new module.TypewriterManager({
                    typeSpeed: 30,
                    deleteSpeed: 20,
                    allowHTML: true,
                    pauseCharacters: { ',': 500, '.': 700, '!': 700, '?': 700 }
                });
                this.typewriterManager = window.typewriterManagerInstance;
                if (!window.typewriterManagerInitLogged) {
                    console.log('TypewriterManager initialized successfully');
                    window.typewriterManagerInitLogged = true;
                }
            } else {
                console.warn('Could not initialize TypewriterManager');
            }
        } catch (error) {
            console.error('Error initializing TypewriterManager:', error);
        }
    }

    /**
     * Show welcome message if TypewriterManager is available
     * @private
     */
    _showWelcomeMessage() {
        // Check if welcome messages have already been shown in this session
        if (window.CerebrolyWelcomeShown) {
            return;
        }

        // Mark as shown to prevent duplicates
        window.CerebrolyWelcomeShown = true;

        // Only show welcome message if TypewriterManager is available
        if (this.typewriterManager) {
            // Get welcome messages from configuration or use defaults
            let messages = [];

            if (typeof cerebrolData !== 'undefined' && cerebrolData.welcomeMessages && Array.isArray(cerebrolData.welcomeMessages)) {
                messages = cerebrolData.welcomeMessages.filter(msg => msg.trim() !== '');
            }

            // Fallback to default messages if none configured
            if (messages.length === 0) {
                messages = [
                    "Hi! 👋 I'm an information assistant here to help you with your questions and provide details on various topics",
                ];
            }

            // Display only the first message
            this.showBotMessageWithTyping(messages[0]);

            const calculateTypewriterDelay = (message) => {
                const typingTime = message.length * 40;
                const pauseTime = 1500;
                return typingTime + pauseTime;
            };

            const delays = [0];
            let totalDelay = calculateTypewriterDelay(messages[0]);

            for (let i = 1; i < messages.length; i++) {
                delays.push(totalDelay);
                totalDelay += calculateTypewriterDelay(messages[i]);
            }

            // Schedule each message with its corresponding time
            for (let i = 1; i < messages.length; i++) {
                setTimeout(() => {
                    this.showBotMessageWithTyping(messages[i]);
                }, delays[i]);
            }
        }
    }

    /**
     * Called when observed attributes change
     * @param {string} name - The attribute name
     * @param {string} oldValue - The old attribute value
     * @param {string} newValue - The new attribute value
     */
    attributeChangedCallback(name, oldValue, newValue) {
        if (name === 'disabled') {
            const input = this.shadow?.querySelector('#message-input');
            const button = this.shadow?.querySelector('#send-button');

            if (input && button) {
                const isDisabled = newValue !== null;
                input.disabled = isDisabled;
                button.disabled = isDisabled || (input.value.trim() === '');
            }
        } else if (name === 'placeholder' && oldValue !== newValue) {
            const input = this.shadow?.querySelector('#message-input');
            if (input) {
                input.placeholder = newValue || 'Type a message...';
            }
        }
    }

    /**
     * Update button state when component becomes visible
     */
    updateButtonStateOnShow() {
        // If we're currently typing, make sure the button shows stop
        if (this.isTyping && this.currentTypewriter) {
            this._updateButtonIcon('stop');
        } else {
            this._updateButtonIcon('send');
        }

        // Show welcome message only once when chat becomes visible for the first time
        if (!window.CerebrolyWelcomeShown) {
            this._showWelcomeMessage();
        }
    }

    /**
     * Renders the component based on current attributes
     */
    render() {
        const placeholder = this.getAttribute('placeholder') || 'Type a message...';
        const isDisabled = this.hasAttribute('disabled');

        // Render in Shadow DOM with embedded styles
        this.shadow.innerHTML = `
            <style>
                ${this.themeStyles}
            </style>
            
            <div class="container-input">
                <input type="text" id="message-input" placeholder="${placeholder}" ${isDisabled ? 'disabled' : ''}>
                
                <button id="send-button" class="btn-send" ${isDisabled ? 'disabled' : ''}>
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                        <path d="M498.1 5.3c10.1 7 15.4 19.1 13.5 31.2l-64 416c-1.5 9.7-7.4 18.2-16 23s-18.9 5.4-28 1.6L284 427.7l-68.5 74.1c-8.9 9.7-22.9 12.9-35.2 8.1S160 493.2 160 480V396.4c0-4 1.5-7.8 4.2-10.7L331.8 202.8c5.8-6.3 5.6-16-.4-22s-15.7-6.4-22-.7L106 360.8 17.7 316.6C7.1 311.3 .3 300.7 0 288.9s5.9-22.8 16.1-28.7l448-256c10.7-6.1 23.9-5.5 34 1.4z"></path>
                    </svg>
                </button>
                
                <!-- Typing spinner overlay -->
                <div id="typing-overlay" class="typing-overlay">
                    <div class="spinner"></div>
                </div>
            </div>
        `;
    }

    /**
     * Set up event listeners for input and button
     * @private
     */
    _setupEventListeners() {
        const input = this.shadow.querySelector('#message-input');
        const button = this.shadow.querySelector('#send-button');

        // Send message or stop typing when clicking the button
        button.addEventListener('click', () => {
            if (this.isTyping) {
                this._handleStop();
            } else {
                this._handleSend();
            }
        });

        // Send message when pressing Enter (only if not typing)
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !this.isTyping) {
                this._handleSend();
            }
        });

        // Enable/disable button based on input content
        input.addEventListener('input', () => {
            if (!this.isTyping) {
                button.disabled = input.value.trim() === '' || this.isSending || this.hasAttribute('disabled');
            }
        });
    }

    /**
     * Handle the sending of a message
     * @private
     * @async
     */
    async _handleSend() {
        if (this.isSending) return;

        const input = this.shadow.querySelector('#message-input');
        const message = input.value.trim();

        if (message === '') return;

        // Get the configured endpoint
        const endpoint = this.getAttribute('endpoint');

        if (!endpoint) {
            console.error('Endpoint not configured for chat-input');

            // Show error with custom text from config
            const errorMessage = (typeof cerebrolData !== 'undefined' && cerebrolData.errorMessage)
                ? cerebrolData.errorMessage
                : "I'm sorry, but I'm having trouble processing your request right now. Please try again in a moment.";

            const errorEvent = new CustomEvent('error', {
                detail: { message: errorMessage },
                bubbles: true,
                composed: true
            });

            this.dispatchEvent(errorEvent);
            return;
        }

        // Find ChatMessages component to add the user message
        const chatMessages = document.querySelector('chat-messages');
        if (chatMessages) {
            const userMessage = chatMessages.addUserMessage(message);
            // Apply theme class to new message
            if (typeof cerebrolData !== 'undefined' && cerebrolData.selectedTheme) {
                userMessage.classList.add(`theme-${cerebrolData.selectedTheme}`);
            }
        }

        // Create message sent event
        const event = new CustomEvent('message-sent', {
            detail: { message },
            bubbles: true,
            composed: true
        });

        this.dispatchEvent(event);

        // Clear input
        input.value = '';

        // Disable button and input
        this.shadow.querySelector('#send-button').disabled = true;
        input.disabled = true;

        // Show typing spinner overlay
        this.showTypingIndicator();

        // Set sending state
        this.isSending = true;

        // Get API key if configured
        const apiKey = this.getAttribute('api-key');

        try {
            // Configure headers for the request
            const headers = {
                'Content-Type': 'application/json'
            };

            // Add API key if configured
            if (apiKey) {
                headers['Authorization'] = `Bearer ${apiKey}`;
            }

            // Make request to the endpoint
            const response = await fetch(endpoint, {
                method: 'POST',
                headers,
                body: JSON.stringify({
                    messages: [{ role: 'user', content: message }],
                    model: 'gpt-3.5-turbo'
                })
            });

            if (!response.ok) {
                throw new Error(`Error in server response: ${response.status}`);
            }

            const data = await response.json();

            // Extract model response
            let botResponse = '';

            if (data.choices && data.choices.length > 0 && data.choices[0].message) {
                botResponse = data.choices[0].message.content;
            } else {
                botResponse = (typeof cerebrolData !== 'undefined' && cerebrolData.errorMessage)
                    ? cerebrolData.errorMessage
                    : "I'm sorry, but I'm having trouble processing your request right now. Please try again in a moment.";
            }

            // Show bot response with typing effect
            this.showBotMessageWithTyping(botResponse);

            // Dispatch response received event
            const responseEvent = new CustomEvent('response-received', {
                detail: {
                    message: botResponse,
                    rawResponse: data
                },
                bubbles: true,
                composed: true
            });

            this.dispatchEvent(responseEvent);

        } catch (error) {
            console.error('Error sending message:', error);
            // Show error message with custom text from config
            if (this.typewriterManager) {
                const errorMessage = (typeof cerebrolData !== 'undefined' && cerebrolData.errorMessage)
                    ? cerebrolData.errorMessage
                    : "I'm sorry, but I'm having trouble processing your request right now. Please try again in a moment.";
                this.showBotMessageWithTyping(errorMessage);
            } else {
                // If no typewriter, show error directly
                const chatMessages = document.querySelector('chat-messages');
                if (chatMessages) {
                    const errorMessage = (typeof cerebrolData !== 'undefined' && cerebrolData.errorMessage)
                        ? cerebrolData.errorMessage
                        : "I'm sorry, but I'm having trouble processing your request right now. Please try again in a moment.";
                    const messageEl = chatMessages.addBotMessage(errorMessage);
                    // Apply theme class to error message
                    if (typeof cerebrolData !== 'undefined' && cerebrolData.selectedTheme) {
                        messageEl.classList.add(`theme-${cerebrolData.selectedTheme}`);
                    }
                }
            }

            // Dispatch error event
            const errorEvent = new CustomEvent('error', {
                detail: {
                    message: 'Error communicating with server',
                    error: error.message
                },
                bubbles: true,
                composed: true
            });

            this.dispatchEvent(errorEvent);

            // Enable input again in case of error
            this.setInputEnabled(true);
            this.hideTypingIndicator();

        } finally {
            // Reset sending state
            this.isSending = false;
        }
    }

    /**
     * Handle stopping the current typewriter effect
     * @private
     */
    _handleStop() {
        if (this.currentTypewriter) {
            // Stop the typewriter effect without clearing text (false parameter)
            this.currentTypewriter.stop(false);
            this.currentTypewriter = null;
        }

        // Reset typing state
        this.isTyping = false;

        // Update button back to send mode
        this._updateButtonIcon('send');

        // Enable input again
        this.setInputEnabled(true);
        this.hideTypingIndicator();
    }

    /**
     * Pause the current typewriter effect
     * @private
     */
    _pauseTypewriter() {
        if (this.currentTypewriter) {
            this.currentTypewriter.pause();
        }
    }

    /**
     * Resume the current typewriter effect
     * @private
     */
    _resumeTypewriter() {
        if (this.currentTypewriter) {
            this.currentTypewriter.resume();
        }
    }

    /**
     * Get typewriter state
     * @returns {Object} Current state of the typewriter
     */
    getTypewriterState() {
        if (this.currentTypewriter) {
            return this.currentTypewriter.getState();
        }
        return null;
    }

    /**
     * Check if typewriter is currently active
     * @returns {boolean} True if typewriter is active
     */
    isTypewriterActive() {
        return this.isTyping && this.currentTypewriter !== null;
    }

    /**
     * Update button icon between send and stop
     * @private
     * @param {string} mode - 'send' or 'stop'
     */
    _updateButtonIcon(mode) {
        const button = this.shadow.querySelector('#send-button');
        if (!button) return;

        // Remove all state classes
        button.classList.remove('btn-send', 'btn-stop', 'btn-default');

        if (mode === 'stop') {
            // Change to stop icon and add stop class
            button.innerHTML = `
                <svg class="icon icon-stop" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" style="width: 13px; height: 13px;">
                    <path d="M0 128C0 92.7 28.7 64 64 64H320c35.3 0 64 28.7 64 64V384c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V128z"/>
                </svg>
            `;
            button.classList.add('btn-stop');
            button.disabled = false;
        } else {
            // Change back to send icon and add send class
            button.innerHTML = `
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                    <path d="M498.1 5.3c10.1 7 15.4 19.1 13.5 31.2l-64 416c-1.5 9.7-7.4 18.2-16 23s-18.9 5.4-28 1.6L284 427.7l-68.5 74.1c-8.9 9.7-22.9 12.9-35.2 8.1S160 493.2 160 480V396.4c0-4 1.5-7.8 4.2-10.7L331.8 202.8c5.8-6.3 5.6-16-.4-22s-15.7-6.4-22-.7L106 360.8 17.7 316.6C7.1 311.3 .3 300.7 0 288.9s5.9-22.8 16.1-28.7l448-256c10.7-6.1 23.9-5.5 34 1.4z"></path>
                </svg>
            `;
            button.classList.add('btn-send');
        }
    }

    /**
     * Show a bot message with typing effect
     * @async
     * @param {string} text - The message content to display
     */
    async showBotMessageWithTyping(text) {
        const chatMessages = document.querySelector('chat-messages');
        if (!chatMessages) {
            console.error('chat-messages component not found');
            this.hideTypingIndicator();
            this.setInputEnabled(true);
            return;
        }

        // Show the spinner while "thinking"
        this.showTypingIndicator();

        // Disable input while the bot is "typing"
        this.setInputEnabled(false);

        // If TypewriterManager is not available, show message without effect
        if (!this.typewriterManager) {
            console.warn('TypewriterManager not available, showing message without effect');
            const messageEl = chatMessages.addBotMessage(text);
            // Apply theme class to new bot message
            if (typeof cerebrolData !== 'undefined' && cerebrolData.selectedTheme) {
                messageEl.classList.add(`theme-${cerebrolData.selectedTheme}`);
            }
            this.hideTypingIndicator();
            this.setInputEnabled(true);
            // Reset button to send mode
            this._updateButtonIcon('send');
            this.isTyping = false;
            return;
        }

        // Simulate "thinking" time before typing
        setTimeout(async () => {
            try {
                // Create message element
                const messageEl = chatMessages.addBotMessage('', true);

                // Apply theme class to new bot message
                if (typeof cerebrolData !== 'undefined' && cerebrolData.selectedTheme) {
                    messageEl.classList.add(`theme-${cerebrolData.selectedTheme}`);
                }

                // Find container for typewriter effect text
                const typingContainer = messageEl.typingContainer;
                if (!typingContainer) {
                    console.error('Container for typing effect not found');
                    // Fallback: update message content directly
                    const messageContent = messageEl.querySelector('.message-content');
                    if (messageContent) {
                        messageContent.textContent = text;
                    }
                    this.hideTypingIndicator();
                    this.setInputEnabled(true);
                    // Reset button to send mode
                    this._updateButtonIcon('send');
                    this.isTyping = false;
                    return;
                }

                // Create typewriter effect
                const typewriter = this.typewriterManager.create(typingContainer, {
                    text: [text],
                    mode: 'typeOnly',
                    loop: false,
                    allowHTML: true,
                    cursor: {
                        character: '',
                        type: 'none',       // Cursor type ('none', 'static', 'blinking')
                        hideWhenDone: true  // Hide cursor when finished
                    },
                    callbacks: {
                        onComplete: (typewriterInstance) => {

                            // Remove cursor if it exists
                            const cursorElement = typingContainer.querySelector('.typewriter-cursor');
                            if (cursorElement) {
                                cursorElement.remove();
                            }

                            // Reset typing state and button only if this is still our current typewriter
                            if (this.currentTypewriter === typewriterInstance) {
                                this.isTyping = false;
                                this.currentTypewriter = null;
                                this._updateButtonIcon('send');

                                // Enable input again
                                this.setInputEnabled(true);
                                this.hideTypingIndicator();
                            }
                        },
                        onStart: (typewriterInstance) => {
                            // IMMEDIATELY set typing state and show stop button when typing starts
                            this.isTyping = true;
                            this._updateButtonIcon('stop');

                            // Hide spinner when typing begins
                            this.hideTypingIndicator();
                        },
                        onPause: (typewriterInstance) => {
                        },
                        onResume: (typewriterInstance) => {
                        },
                        onTextChange: (currentText, typewriterInstance) => {
                            // Scroll to bottom while typing
                            chatMessages.scrollToBottom();
                        }
                    }
                });

                // Store reference to current typewriter
                this.currentTypewriter = typewriter;
                typewriter.start();
            } catch (error) {
                console.error('Error applying typewriter effect:', error);
                // In case of error, show message without effect
                const messageEl = chatMessages.addBotMessage(text);
                // Apply theme class to new bot message
                if (typeof cerebrolData !== 'undefined' && cerebrolData.selectedTheme) {
                    messageEl.classList.add(`theme-${cerebrolData.selectedTheme}`);
                }
                this.hideTypingIndicator();
                this.setInputEnabled(true);
            }
        }, 1000);
    }

    /**
     * Show the typing indicator overlay
     */
    showTypingIndicator() {
        const overlay = this.shadow.querySelector('#typing-overlay');
        if (overlay) {
            overlay.classList.add('active');
        }
    }

    /**
     * Hide the typing indicator overlay
     */
    hideTypingIndicator() {
        const overlay = this.shadow.querySelector('#typing-overlay');
        if (overlay) {
            overlay.classList.remove('active');
        }
    }

    /**
     * Enable or disable the input
     * @param {boolean} enabled - Whether the input should be enabled
     */
    setInputEnabled(enabled) {
        const input = this.shadow.querySelector('#message-input');
        const button = this.shadow.querySelector('#send-button');

        if (enabled) {
            this.removeAttribute('disabled');
            input.disabled = false;
            button.disabled = input.value.trim() === '';
        } else {
            this.setAttribute('disabled', '');
            input.disabled = true;
            button.disabled = true;
        }
    }

    /**
     * Set the endpoint URL
     * @param {string} url - The endpoint URL
     */
    setEndpoint(url) {
        this.setAttribute('endpoint', url);
    }

    /**
     * Set the API key
     * @param {string} key - The API key
     */
    setApiKey(key) {
        this.setAttribute('api-key', key);
    }

    /**
     * Show a temporary error message
     * @param {string} message - The error message to display
     */
    showError(message) {
        // Find the status indicator
        const statusIndicator = document.getElementById('status-indicator');
        if (statusIndicator) {
            statusIndicator.textContent = message;
            statusIndicator.style.display = 'block';

            setTimeout(() => {
                statusIndicator.style.display = 'none';
            }, 3000);
        }
    }
    // Agregar este método al final de la clase ChatInput, antes del cierre de la clase

    /**
     * Public method to programmatically send a message
     * @param {string} message - Message to send
     * @returns {Promise<boolean>} - Promise that resolves to true if message was sent
     */
    async sendMessage(message) {
        console.log('ChatInput.sendMessage called with:', message);

        if (!message || message.trim() === '') {
            console.warn('Cannot send empty message');
            return false;
        }

        // Stop any active typewriter
        if (this.isTypewriterActive()) {
            this._handleStop();
            // Wait a bit for it to stop
            await new Promise(resolve => setTimeout(resolve, 500));
        }

        // Wait if currently sending
        if (this.isSending) {
            console.log('Currently sending, waiting...');
            await new Promise(resolve => {
                const checkSending = setInterval(() => {
                    if (!this.isSending) {
                        clearInterval(checkSending);
                        resolve();
                    }
                }, 100);
            });
        }

        // Set the message in the input
        const input = this.shadowRoot.querySelector('#message-input');
        if (input) {
            input.value = message.trim();

            // Enable components if disabled
            this.setInputEnabled(true);

            // Send the message
            setTimeout(() => {
                this._handleSend();
            }, 100);

            return true;
        }

        console.error('Could not find input element in shadow DOM');
        return false;
    }
}

// ChatInput now loads themes directly without external dependencies

// Register the component only if not already registered
if (!customElements.get('chat-input')) {
    customElements.define('chat-input', ChatInput);
}