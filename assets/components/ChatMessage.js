/**
 * Custom element representing a single chat message.
 * It supports dynamic updates for sender and avatar text attributes.
 * @class
 * @extends HTMLElement
 */
class ChatMessage extends HTMLElement {
    /**
     * Specifies the attributes to observe for changes.
     * @returns {string[]} - List of observed attributes.
     */
    static get observedAttributes() {
        return ['sender', 'avatar-text'];
    }

    /**
     * Initializes the ChatMessage instance.
     */
    constructor() {
        super();
        // Create Shadow DOM for proper encapsulation
        this.shadow = this.attachShadow({ mode: 'open' });
        this.themeStyles = '';
    }

    /**
     * Lifecycle method called when the element is added to the DOM.
     * Sets up the initial structure and adjusts the avatar based on attributes.
     */
    async connectedCallback() {
        // Apply theme class if available
        if (typeof cerebrolData !== 'undefined' && cerebrolData.selectedTheme) {
            this.classList.add(`theme-${cerebrolData.selectedTheme}`);
        }
        
        // Load theme styles from DOM into Shadow DOM
        await this.loadThemeFromDOM();
        this.render();
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
     * Provides structural styles for ChatMessage - colors handled by external CSS
     */
    getDefaultStyles() {
        return `
            :host {
                position: relative;
                width: 100%;
                margin-bottom: 15px;
                display: flex;
                min-height: auto;
                animation: messageSlideIn 0.3s ease-out;
            }
            
            :host(:last-child) {
                margin-bottom: 0;
            }
            
            :host([sender="user"]) {
                justify-content: flex-end;
            }
            
            :host([sender="bot"]) {
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
            :host([sender="user"]) .message-content {
                background-color: #f0fbe8;
                color: #2b5c14;
                text-align: right;
            }

            /* Bot Messages */
            :host([sender="bot"]) .message-content {
                background-color: #ecf7fd;
                color: #123c5d;
                text-align: left;
            }
            
            /* Dark theme message styles */
            :host(.theme-dark-theme[sender="user"]) .message-content {
                background-color: hsl(251, 40%, 45.1%);
                color: #ffffff;
                border: none;
            }

            :host(.theme-dark-theme[sender="bot"]) .message-content {
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
     * Renders the message in Shadow DOM
     */
    render() {
        const sender = this.getAttribute('sender') || 'bot';
        const avatarText = this.getAttribute('avatar-text') || (sender === 'user' ? 'YOU' : 'AI');
        const avatarClass = sender === 'user' ? 'icon-user' : 'icon-bot';
        
        // Get content from Light DOM
        const content = this.innerHTML;
        
        this.shadow.innerHTML = `
            <style>
                ${this.themeStyles}
            </style>
            
            <div class="message-content">
                ${content}
            </div>
            
            <div class="${avatarClass}">
                ${avatarText}
            </div>
        `;
    }

    /**
     * Lifecycle method called when an observed attribute changes.
     * Re-renders the component when attributes change.
     * @param {string} name - The name of the changed attribute.
     * @param {string|null} oldValue - The previous value of the attribute.
     * @param {string|null} newValue - The new value of the attribute.
     */
    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue !== newValue && this.shadow) {
            this.render();
        }
    }
}

// ChatMessage now loads themes directly without external dependencies

// Define the custom element only if not already registered
if (!customElements.get('chat-message')) {
    customElements.define('chat-message', ChatMessage);
}
