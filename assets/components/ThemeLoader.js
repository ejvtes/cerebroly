/**
 * ThemeLoader Mixin - Provides common theme loading functionality
 * for all chat components to avoid code duplication
 */
const ThemeLoader = {
    /**
     * Loads the theme styles for Shadow DOM encapsulation
     */
    async loadThemeStyles() {
        // Start with default styles always
        this.themeStyles = this.getDefaultStyles();
        
        try {
            // Get base URL
            let baseUrl = '';
            
            if (typeof ftcData !== 'undefined' && ftcData.apiUrl) {
                // Get base URL from API URL
                const url = new URL(ftcData.apiUrl);
                baseUrl = url.origin;
            } else {
                // If no ftcData, try to get from URL params (iframe context)
                const urlParams = new URLSearchParams(window.location.search);
                const siteUrl = urlParams.get('site');
                if (siteUrl) {
                    baseUrl = decodeURIComponent(siteUrl);
                }
            }
            
            if (!baseUrl) {
                console.warn('ThemeLoader: No base URL found, using default styles only');
                return;
            }
            
            // Get theme configuration from backend
            let selectedTheme = 'intelliwp-theme';
            try {
                const themeResponse = await fetch(`${baseUrl}/wp-json/ftc/v1/appearance-config`);
                if (themeResponse.ok) {
                    const themeData = await themeResponse.json();
                    if (themeData.success && themeData.theme) {
                        selectedTheme = themeData.theme;
                        if (!window.themeLoaderLoggedThemes) window.themeLoaderLoggedThemes = [];
                        if (!window.themeLoaderLoggedThemes.includes(selectedTheme)) {
                            console.log('ThemeLoader: Using theme:', selectedTheme);
                            window.themeLoaderLoggedThemes.push(selectedTheme);
                        }
                    }
                }
            } catch (error) {
                console.warn('ThemeLoader: Could not load theme config, using default:', error);
            }
            
            // Try to load additional CSS files (non-blocking)
            const [chatCss, themeCss] = await Promise.all([
                this.loadCssFile(`${baseUrl}/wp-content/plugins/intelliWP/assets/css/chat.css`),
                this.loadCssFile(`${baseUrl}/wp-content/plugins/intelliWP/assets/css/themes/${selectedTheme}.css`)
            ]);
            
            // If we got additional CSS, append it to default styles
            if (chatCss || themeCss) {
                this.themeStyles = this.getDefaultStyles() + '\n' + chatCss + '\n' + themeCss;
                if (!window.themeLoaderCssLogged) {
                    console.log('ThemeLoader: Loaded CSS files successfully');
                    window.themeLoaderCssLogged = true;
                }
            }
        } catch (error) {
            console.warn('Could not load external CSS, using default styles:', error);
            // Default styles already set above
        }
    },
    
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
};

// Export the mixin for use in components
window.ThemeLoader = ThemeLoader;