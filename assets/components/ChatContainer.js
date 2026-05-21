/**
 * ChatContainer is a custom HTML element that provides a chat interface
 * with three states: closed, medium, and fullscreen.
 * It manages its own rendering, state transitions, and event listeners.
 */
class ChatContainer extends HTMLElement {
    /**
     * Initializes the ChatContainer instance.
     * Sets the default state to 'closed' and stores the original content.
     */
    constructor() {
        super();
        this.state = 'closed'; // Possible states: 'closed', 'medium', 'fullscreen'
        this.originalContent = '';
        
        // Create Shadow DOM for proper encapsulation
        this.shadow = this.attachShadow({ mode: 'open' });
        this.themeStyles = '';
        
    }

    /**
     * Lifecycle method called when the element is added to the DOM.
     * Preserves the original content, renders the component, and sets the initial state.
     */
    async connectedCallback() {
        // Preserve the original content before rendering
        this.originalContent = this.innerHTML;
        
        // Load theme styles from DOM into Shadow DOM
        await this.loadThemeFromDOM();
        
        this.render();
        this.updateState(this.state);
        
        // Apply icon configuration from backend
        this.applyIconConfiguration();
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
     * Helper to load CSS files
     */
    async loadCssFile(url) {
        try {
            const response = await fetch(url);
            if (response.ok) {
                return await response.text();
            }
            throw new Error(`Failed to load ${url}`);
        } catch (error) {
            console.warn(`Failed to load CSS file ${url}:`, error);
            return '';
        }
    }

    /**
     * Provides complete styles for ChatContainer including theme application
     */
    getDefaultStyles() {
        return `
            :host {
                --primary-blue: #0066ff;
                --accent-pro-100: hsl(251, 40.2%, 54.1%);
                --color-bg-dark: #0a0709;
                
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 9999;
                background: #fff;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
                border-radius: 1rem;
                overflow: hidden;
                transition: all 0.3s ease;
                display: flex;
                flex-direction: column;
                font-family: Inter, sans-serif;
            }
            
            /* Dark theme variables override */
            :host(.theme-dark-theme) {
                background: var(--color-bg-dark);
            }
            
            :host(.closed) {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: white;
                justify-content: center;
                align-items: center;
                cursor: pointer;
                z-index: 10;
            }
            
            :host(.theme-dark-theme.closed) {
                background: var(--accent-pro-100);
            }
            
            :host(.closed)::after {
                content: "💬";
                font-size: 24px;
                color: white;
            }
            
            :host(.medium) {
                width: 400px;
                height: 500px;
                border-radius: 1rem;
            }
            
            :host(.fullscreen) {
                right: 0;
                bottom: 0;
                width: 100vw;
                height: 100vh;
                border-radius: 0;
                overflow-y: auto;
            }
            
            .controls {
                display: flex;
                justify-content: space-between;
                padding: 15px;
                background: var(--primary-blue);
                box-shadow: 0px 0px 15px rgba(0, 102, 255, 0.8);
            }
            
            :host(.theme-dark-theme) .controls {
                background: #1a1427;
                box-shadow: 0px 0px 20px rgba(0, 0, 0, 0.8);
            }
            
            .controls div {
                display: flex;
            }

            .controls .logoIntelli {
                font-weight: 800;
                color: white;
                text-decoration: none;
            }

            .controls .logoIntelli img {
                position: relative;
                top: 5px;
            }
            
            .controls button {
                background: transparent;
                color: #fff;
                border: none;
                margin-left: 5px;
                cursor: pointer;
                font-size: 14px;
                padding: 4px;
                border-radius: 4px;
                transition: all 0.2s ease;
                height: 24px;
            }
            
            .controls button:hover {
                background: rgba(255, 255, 255, 1);
                color: var(--primary-blue) !important;
            }

            .controls button:hover svg {
                fill: var(--primary-blue) !important;
            }
            
            /* Dark theme hover overrides */
            :host(.theme-dark-theme) .controls button:hover {
                background: rgba(127, 90, 240, 0.9);
                color: white !important;
            }

            :host(.theme-dark-theme) .controls button:hover svg {
                fill: white !important;
            }
            
            .content {
                flex: 1;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                position: relative;
            }
            
            /* Ensure input is at bottom */
            .content ::slotted(chat-input) {
                order: 2;
            }
            
            .content ::slotted(.container-messages) {
                order: 1;
                flex: 1;
                overflow: hidden;
            }
            
            .hidden {
                display: none;
            }
        `;
    }

    /**
     * Renders the HTML structure of the ChatContainer.
     * Preserves the original content and sets up event listeners.
     */
    render() {
        this.shadow.innerHTML = `
            <style>
                ${this.themeStyles}
            </style>
            
            <div class="controls hidden">
                <div>
                    <a class="logoIntelli" target="_blank" href="https://cerebroly.com">
                        Cerebroly.com
                    </a>
                </div>
                <div>
                    <button id="minimize">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <line x1="6" y1="12" x2="18" y2="12"></line>
                        </svg>
                    </button>
                    <button id="fullscreen">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
                        </svg>
                    </button>
                    <button id="close">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="content hidden">
                <slot></slot>
            </div>
        `;

        // Move original content to light DOM for slot projection
        if (this.originalContent) {
            this.innerHTML = this.originalContent;
        }

        this.setupListeners();
    }

    /**
     * Sets up event listeners for the ChatContainer's buttons and click events.
     */
    setupListeners() {
        this.addEventListener('click', (e) => {
            // Only open when closed, ignore clicks when medium or fullscreen
            if (this.state === 'closed') {
                e.stopPropagation();
                this.updateState('medium');
            }
        });

        this.shadow.querySelector('#minimize').addEventListener('click', (e) => {
            e.stopPropagation();
            this.updateState('medium');
        });

        this.shadow.querySelector('#fullscreen').addEventListener('click', (e) => {
            e.stopPropagation();
            this.updateState('fullscreen');
        });

        this.shadow.querySelector('#close').addEventListener('click', (e) => {
            e.stopPropagation();
            this.updateState('closed');
        });
    }

    /**
     * Updates the state of the ChatContainer and adjusts the UI accordingly.
     * @param {string} newState - The new state to transition to ('closed', 'medium', 'fullscreen').
     */
    updateState(newState) {
        this.classList.remove('closed', 'medium', 'fullscreen');
        this.classList.add(newState);
        this.state = newState;

        // Manage page scroll behavior for fullscreen state
        if (newState === 'fullscreen') {
            document.body.classList.add('fullscreen-chat');
        } else {
            document.body.classList.remove('fullscreen-chat');
        }

        const controls = this.shadow.querySelector('.controls');
        const content = this.shadow.querySelector('.content');
        const minimizeBtn = this.shadow.querySelector('#minimize');
        const fullscreenBtn = this.shadow.querySelector('#fullscreen');
        const closeBtn = this.shadow.querySelector('#close');

        if (newState === 'closed') {
            controls.classList.add('hidden');
            content.classList.add('hidden');
        } else {
            controls.classList.remove('hidden');
            content.classList.remove('hidden');
            
            // Show/hide buttons based on the current state
            if (newState === 'medium') {
                minimizeBtn.style.display = 'none';
                fullscreenBtn.style.display = 'block';
                closeBtn.style.display = 'block';
            } else if (newState === 'fullscreen') {
                minimizeBtn.style.display = 'block';
                fullscreenBtn.style.display = 'none';
                closeBtn.style.display = 'block';
            }
        }
        
        // Update child components state
        this.updateChildrenState(newState);
        
        // Update button state when chat becomes visible
        if (newState !== 'closed') {
            setTimeout(() => {
                const chatInput = this.querySelector('chat-input');
                if (chatInput && typeof chatInput.updateButtonStateOnShow === 'function') {
                    chatInput.updateButtonStateOnShow();
                }
            }, 100);
        }
    }

    /**
     * Apply icon configuration and theme class from cerebrolData
     */
    applyIconConfiguration() {
        if (typeof cerebrolData === 'undefined') {
            return;
        }

        // Apply theme class to component
        if (cerebrolData.selectedTheme) {
            this.classList.add(`theme-${cerebrolData.selectedTheme}`);
        }

        let iconCSS = '';
        
        // Priority 1: Custom uploaded image
        if (cerebrolData.customIconUrl && cerebrolData.customIconUrl.trim() !== '') {
            iconCSS = `
                :host(.closed)::after {
                    content: '' !important;
                    background-image: url('${cerebrolData.customIconUrl}') !important;
                    background-size: cover !important;
                    background-position: center !important;
                    background-repeat: no-repeat !important;
                    width: 40px !important;
                    height: 40px !important;
                    border-radius: 50% !important;
                }
            `;
        }
        // Priority 2: Emoji from backend configuration  
        else if (cerebrolData.customIcon && cerebrolData.customIcon !== 'default') {
            const iconMap = {
                'robot': '🤖',
                'message': '💭', 
                'help': '❓',
                'support': '🎧'
            };
            
            const selectedEmoji = iconMap[cerebrolData.customIcon] || '💬';
            iconCSS = `
                :host(.closed)::after {
                    content: "${selectedEmoji}" !important;
                }
            `;
        }
        
        // Apply the CSS if we have any
        if (iconCSS) {
            const existingStyle = this.shadow.querySelector('#dynamic-icon-style');
            if (existingStyle) {
                existingStyle.remove();
            }
            
            const styleElement = document.createElement('style');
            styleElement.id = 'dynamic-icon-style';
            styleElement.textContent = iconCSS;
            this.shadow.appendChild(styleElement);
        }
    }
    
    /**
     * Updates child components to reflect container state
     * @param {string} state - The current container state
     */
    updateChildrenState(state) {
        // Apply theme class to all child components
        const themeClass = cerebrolData?.selectedTheme ? `theme-${cerebrolData.selectedTheme}` : '';
        
        // Find chat-input component and update its class
        const chatInput = this.querySelector('chat-input');
        if (chatInput) {
            chatInput.classList.remove('medium', 'fullscreen', 'closed');
            if (state !== 'closed') {
                chatInput.classList.add(state);
            }
            if (themeClass) {
                chatInput.classList.add(themeClass);
            }
        }
        
        // Find chat-messages component and update its class
        const chatMessages = this.querySelector('chat-messages');
        if (chatMessages) {
            chatMessages.classList.remove('medium', 'fullscreen', 'closed');
            if (state !== 'closed') {
                chatMessages.classList.add(state);
            }
            if (themeClass) {
                chatMessages.classList.add(themeClass);
            }
        }

        // Apply theme to individual chat-message elements
        const chatMessageElements = this.querySelectorAll('chat-message');
        chatMessageElements.forEach(msg => {
            if (themeClass) {
                msg.classList.add(themeClass);
            }
        });
    }
}

// ChatContainer now loads themes directly without external dependencies

// Define the custom element only if not already registered
if (!customElements.get('chat-container')) {
    customElements.define('chat-container', ChatContainer);
}