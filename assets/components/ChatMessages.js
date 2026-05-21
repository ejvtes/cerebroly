/**
 * Custom element that creates a container for chat messages with improved scrolling.
 * @class
 * @extends HTMLElement
 */
class ChatMessages extends HTMLElement {
    /**
     * Initializes the ChatMessages instance.
     * Sets up the initial state and renders the component structure.
     */
    constructor() {
        super();
        this.shadow = this.attachShadow({ mode: 'open' });

        this.isScrolledToBottom = true;
        this.autoScrollEnabled = true;
        this.scrollThreshold = 100;
        this.themeStyles = '';

        this.classList.add('chat-messages');
    }

    /**
     * Lifecycle method called when the element is added to the DOM.
     * Loads theme styles and renders the component.
     */
    async connectedCallback() {
        // Apply theme class if available
        if (typeof cerebrolData !== 'undefined' && cerebrolData.selectedTheme) {
            this.classList.add(`theme-${cerebrolData.selectedTheme}`);
        }
        
        // Load theme styles from DOM into Shadow DOM
        await this.loadThemeFromDOM();
        this.render();
        this.setupScrollListeners();
        this.scrollToBottom();
        this.observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    const hasNewMessage = Array.from(mutation.addedNodes).some(
                        node => node.tagName === 'CHAT-MESSAGE'
                    );
                    if (hasNewMessage && this.autoScrollEnabled) {
                        requestAnimationFrame(() => {
                            this.scrollToBottom();
                        });
                    }
                }
            });
        });

        this.observer.observe(this, { childList: true, subtree: false });
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
     * Provides structural styles for ChatMessages - colors handled by external CSS
     */
    getDefaultStyles() {
        return `
            :host {
                display: flex;
                flex-direction: column;
                padding: 20px 10px;
                width: 100%;
                max-width: 700px;
                margin: auto;
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                box-sizing: border-box;
                justify-content: flex-start;
                height: 100%;
                overflow-y: auto;
                overflow-x: hidden;
                scrollbar-width: thin;
                scroll-behavior: smooth;
            }
            
            .messages-content-wrapper {
                max-width: 700px;
                margin: 0 auto;
                width: 100%;
            }
            
            .loading-indicator {
                display: none;
                align-items: center;
                justify-content: center;
                padding: 20px;
                opacity: 0.7;
            }
            
            .loading-indicator.active {
                display: flex;
            }
            
            .loading-dots {
                display: flex;
                gap: 4px;
            }
            
            .loading-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.5);
                animation: loadingPulse 1.4s infinite ease-in-out;
            }
            
            .loading-dot:nth-child(1) { 
                animation-delay: -0.32s; 
            }

            .loading-dot:nth-child(2) { 
                animation-delay: -0.16s; 
            }
            
            @keyframes loadingPulse {
                0%, 80%, 100% {
                    transform: scale(0.8);
                    opacity: 0.5;
                }
                40% {
                    transform: scale(1);
                    opacity: 1;
                }
            }
            
            .scroll-indicator {
                position: absolute;
                top: 20px;
                right: 10px;
                background: #ecf7fd;
                color: #123c5d;
                border: none;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                z-index: 100;
                opacity: 0;
                transform: translateY(10px);
                transition: all 0.3s ease;
                backdrop-filter: blur(10px);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
                font-size: 14px;
            }
            
            /* Dark theme scroll indicator */
            :host(.theme-dark-theme) .scroll-indicator {
                background: rgba(127, 90, 240, 0.9);
                color: white;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            }
            
            .scroll-indicator.visible {
                opacity: 1;
                transform: translateY(0);
            }
            
            /* Chat message structural styles */
            .chat-message {
                position: relative;
                width: 100%;
                margin-bottom: 15px;
                display: flex;
                min-height: auto;
                animation: messageSlideIn 0.3s ease-out;
            }
            
            .chat-message:last-child {
                margin-bottom: 0;
            }
            
            .user-message {
                justify-content: flex-end;
            }
            
            .bot-message {
                justify-content: flex-start;
            }
            
            @keyframes messageSlideIn {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .message-content {
                padding: 12px 15px;
                max-width: 75%;
                border-radius: 6px;
                position: relative;
                margin: 0 50px;
                display: inline-block;
                word-break: break-word;
                border: 1px solid rgba(0, 0, 0, 0.1);
                line-height: 1.4rem;
                font-size: 1rem;
            }
            
            /* User Messages */
            .user-message .message-content {
                background-color: #f0fbe8;
                color: #2b5c14;
                text-align: right;
            }

            /* Bot Messages */
            .bot-message .message-content {
                background-color: #ecf7fd;
                color: #123c5d;
                text-align: left;
            }
            
            /* Dark theme message styles */
            :host(.theme-dark-theme) .user-message .message-content {
                background-color: hsl(251, 40%, 45.1%);
                color: #ffffff;
                border: none;
            }

            :host(.theme-dark-theme) .bot-message .message-content {
                background-color: rgba(89, 69, 161, 0.2);
                color: #ffffff;
                border: none;
            }
            
            .icon-user,
            .icon-bot {
                position: absolute;
                top: 0;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                font-size: 14px;
                z-index: 2;
                border: 1px solid rgba(0, 0, 0, 0.1);
            }
            
            .icon-user {
                background-color: #f0fbe8;
                color: #2b5c14;
                right: 0;
            }
            
            .icon-bot {
                background-color: #ecf7fd;
                color: #123c5d;
                left: 0;
            }
            
            /* Dark theme avatars */
            :host(.theme-dark-theme) .icon-user {
                background-color: hsl(251, 40.2%, 54.1%);
                color: #ffffff;
                border: none;
            }

            :host(.theme-dark-theme) .icon-bot {
                background-color: hsl(60, 2.6%, 7.6%);
                color: #ffffff;
                border: none;
            }
            
            .typing-indicator {
                display: inline-block;
            }
        `;
    }

    /**
     * Renders the component HTML structure in Shadow DOM
     */
    render() {
        this.shadow.innerHTML = `
            <style>
                ${this.themeStyles}
            </style>
            
            <div class="messages-content-wrapper">
                <div id="messages-container"></div>
                <div class="loading-indicator" id="loading-indicator">
                    <div class="loading-dots">
                        <div class="loading-dot"></div>
                        <div class="loading-dot"></div>
                        <div class="loading-dot"></div>
                    </div>
                </div>
            </div>
            <button class="scroll-indicator" id="scroll-indicator" title="Scroll to bottom" aria-label="Scroll to the bottom of the messages">
                ↓
            </button>
        `;
    }


    /**
     * Lifecycle method called when the element is removed from the DOM.
     * Disconnects the MutationObserver and removes scroll listeners.
     */
    disconnectedCallback() {
        if (this.observer) {
            this.observer.disconnect();
        }
        this.removeScrollListeners();
    }

    /**
     * Sets up scroll event listeners and the scroll indicator button.
     */
    setupScrollListeners() {
        const scrollIndicator = this.shadow.querySelector('#scroll-indicator');
        
        // Scroll event listener
        this.scrollHandler = this.throttle(() => {
            this.updateScrollState();
        }, 100);

        this.addEventListener('scroll', this.scrollHandler);

        // Scroll indicator click
        scrollIndicator.addEventListener('click', () => {
            this.scrollToBottom(true);
        });
    }

    /**
     * Removes scroll event listeners.
     */
    removeScrollListeners() {
        if (this.scrollHandler) {
            this.removeEventListener('scroll', this.scrollHandler);
        }
    }

    /**
     * Updates the scroll state and toggles the visibility of the scroll indicator.
     */
    updateScrollState() {
        const scrollTop = this.scrollTop;
        const scrollHeight = this.scrollHeight;
        const clientHeight = this.clientHeight;
        
        // Check if we're near the bottom
        const distanceFromBottom = scrollHeight - scrollTop - clientHeight;
        this.isScrolledToBottom = distanceFromBottom <= this.scrollThreshold;
        
        // Show/hide scroll indicator
        const scrollIndicator = this.shadow.querySelector('#scroll-indicator');
        if (this.isScrolledToBottom) {
            scrollIndicator.classList.remove('visible');
        } else {
            scrollIndicator.classList.add('visible');
        }

        // Update auto-scroll preference
        this.autoScrollEnabled = this.isScrolledToBottom;
    }

    /**
     * Scrolls to the bottom of the messages container.
     * @param {boolean} force - Force scroll even if auto-scroll is disabled.
     */
    scrollToBottom(force = false) {
        if (!force && !this.autoScrollEnabled) {
            return;
        }

        requestAnimationFrame(() => {
            const scrollHeight = this.scrollHeight;
            const clientHeight = this.clientHeight;
            const maxScroll = scrollHeight - clientHeight;
            
            if (maxScroll > 0) {
                this.scrollTo({
                    top: maxScroll,
                    behavior: 'smooth'
                });
            }
            
            // Update state after scroll
            setTimeout(() => {
                this.updateScrollState();
            }, 100);
        });
    }

    /**
     * Scrolls to a specific message element.
     * @param {HTMLElement} messageElement - The message element to scroll to.
     */
    scrollToMessage(messageElement) {
        if (messageElement && this.contains(messageElement)) {
            messageElement.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }
    }

    /**
     * Shows the loading indicator.
     */
    showLoading() {
        const loadingIndicator = this.shadow.querySelector('#loading-indicator');
        if (loadingIndicator) {
            loadingIndicator.classList.add('active');
            this.scrollToBottom();
        }
    }

    /**
     * Hides the loading indicator.
     */
    hideLoading() {
        const loadingIndicator = this.shadow.querySelector('#loading-indicator');
        if (loadingIndicator) {
            loadingIndicator.classList.remove('active');
        }
    }

    /**
     * Creates and adds a bot message to the chat.
     * @param {string} text - The message content.
     * @param {boolean} typingEffect - Whether to display a typing animation.
     * @returns {HTMLElement} - The created message element.
     */
    addBotMessage(text, typingEffect = false) {
        const messagesContainer = this.shadow.querySelector('#messages-container');
        
        const messageEl = document.createElement('div');
        messageEl.className = 'chat-message bot-message';
        messageEl.setAttribute('data-sender', 'bot');
        
        if (typingEffect) {
            messageEl.innerHTML = `
                <div class="message-content">
                    <span class="typing-indicator"></span>
                </div>
                <div class="icon-bot">AI</div>
            `;
            
            // Store reference to typing container for external access
            messageEl.typingContainer = messageEl.querySelector('.typing-indicator');
        } else {
            messageEl.innerHTML = `
                <div class="message-content">${text}</div>
                <div class="icon-bot">AI</div>
            `;
        }
        
        // Add message to Shadow DOM directly
        messagesContainer.appendChild(messageEl);
        
        if (this.autoScrollEnabled) {
            this.scrollToBottom();
        }
        
        return messageEl;
    }

    /**
     * Creates and adds a user message to the chat.
     * @param {string} text - The message content.
     * @returns {HTMLElement} - The created message element.
     */
    addUserMessage(text) {
        const messagesContainer = this.shadow.querySelector('#messages-container');
        
        const messageEl = document.createElement('div');
        messageEl.className = 'chat-message user-message';
        messageEl.setAttribute('data-sender', 'user');
        
        messageEl.innerHTML = `
            <div class="message-content">${text}</div>
            <div class="icon-user">TU</div>
        `;
        
        // Add message to Shadow DOM directly
        messagesContainer.appendChild(messageEl);
        
        this.scrollToBottom();
        
        return messageEl;
    }

    /**
     * Removes all messages from the chat.
     */
    clearMessages() {
        const messagesContainer = this.shadow.querySelector('#messages-container');
        if (messagesContainer) {
            messagesContainer.innerHTML = '';
        }
        
        this.isScrolledToBottom = true;
        this.autoScrollEnabled = true;
        this.updateScrollState();
    }

    /**
     * Gets all message elements.
     * @returns {NodeList} - List of message elements.
     */
    getMessages() {
        const messagesContainer = this.shadow.querySelector('#messages-container');
        return messagesContainer ? messagesContainer.querySelectorAll('.chat-message') : [];
    }

    /**
     * Gets the last message element.
     * @returns {HTMLElement|null} - The last message element.
     */
    getLastMessage() {
        const messages = this.getMessages();
        return messages.length > 0 ? messages[messages.length - 1] : null;
    }

    /**
     * Enable or disable auto-scroll.
     * @param {boolean} enabled - Whether auto-scroll should be enabled.
     */
    setAutoScroll(enabled) {
        this.autoScrollEnabled = enabled;
    }

    /**
     * Check if the user is at the bottom of the chat.
     * @returns {boolean} - True if scrolled to bottom.
     */
    isAtBottom() {
        return this.isScrolledToBottom;
    }

    /**
     * Utility function to throttle function calls.
     * @param {Function} func - Function to throttle.
     * @param {number} wait - Wait time in milliseconds.
     * @returns {Function} - Throttled function.
     */
    throttle(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// ChatMessages now loads themes directly without external dependencies

// Register the component only if not already registered
if (!customElements.get('chat-messages')) {
    customElements.define('chat-messages', ChatMessages);
}