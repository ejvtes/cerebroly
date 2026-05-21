(function(){"use strict";class p extends HTMLElement{constructor(){super(),this.state="closed",this.originalContent="",this.shadow=this.attachShadow({mode:"open"}),this.themeStyles=""}async connectedCallback(){this.originalContent=this.innerHTML,await this.loadThemeFromDOM(),this.render(),this.updateState(this.state),this.applyIconConfiguration()}async loadThemeFromDOM(){this.themeStyles=this.getDefaultStyles();try{let e="",t="";const s=document.querySelectorAll('link[rel="stylesheet"]');for(const i of s){const n=i.href;if(n.includes("/css/chat.css")){const o=await fetch(n);o.ok&&(e=await o.text())}if(n.includes("/css/themes/")){const o=await fetch(n);o.ok&&(t=await o.text())}}(e||t)&&(this.themeStyles=this.getDefaultStyles()+`
`+e+`
`+t)}catch{}}async loadCssFile(e){try{const t=await fetch(e);if(t.ok)return await t.text();throw new Error(`Failed to load ${e}`)}catch(t){return console.warn(`Failed to load CSS file ${e}:`,t),""}}getDefaultStyles(){return`
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
        `}render(){this.shadow.innerHTML=`
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
        `,this.originalContent&&(this.innerHTML=this.originalContent),this.setupListeners()}setupListeners(){this.addEventListener("click",e=>{this.state==="closed"&&(e.stopPropagation(),this.updateState("medium"))}),this.shadow.querySelector("#minimize").addEventListener("click",e=>{e.stopPropagation(),this.updateState("medium")}),this.shadow.querySelector("#fullscreen").addEventListener("click",e=>{e.stopPropagation(),this.updateState("fullscreen")}),this.shadow.querySelector("#close").addEventListener("click",e=>{e.stopPropagation(),this.updateState("closed")})}updateState(e){this.classList.remove("closed","medium","fullscreen"),this.classList.add(e),this.state=e,e==="fullscreen"?document.body.classList.add("fullscreen-chat"):document.body.classList.remove("fullscreen-chat");const t=this.shadow.querySelector(".controls"),s=this.shadow.querySelector(".content"),i=this.shadow.querySelector("#minimize"),n=this.shadow.querySelector("#fullscreen"),o=this.shadow.querySelector("#close");e==="closed"?(t.classList.add("hidden"),s.classList.add("hidden")):(t.classList.remove("hidden"),s.classList.remove("hidden"),e==="medium"?(i.style.display="none",n.style.display="block",o.style.display="block"):e==="fullscreen"&&(i.style.display="block",n.style.display="none",o.style.display="block")),this.updateChildrenState(e),e!=="closed"&&setTimeout(()=>{const r=this.querySelector("chat-input");r&&typeof r.updateButtonStateOnShow=="function"&&r.updateButtonStateOnShow()},100)}applyIconConfiguration(){if(typeof cerebrolData>"u")return;cerebrolData.selectedTheme&&this.classList.add(`theme-${cerebrolData.selectedTheme}`);let e="";if(cerebrolData.customIconUrl&&cerebrolData.customIconUrl.trim()!==""?e=`
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
            `:cerebrolData.customIcon&&cerebrolData.customIcon!=="default"&&(e=`
                :host(.closed)::after {
                    content: "${{robot:"🤖",message:"💭",help:"❓",support:"🎧"}[cerebrolData.customIcon]||"💬"}" !important;
                }
            `),e){const t=this.shadow.querySelector("#dynamic-icon-style");t&&t.remove();const s=document.createElement("style");s.id="dynamic-icon-style",s.textContent=e,this.shadow.appendChild(s)}}updateChildrenState(e){const t=cerebrolData!=null&&cerebrolData.selectedTheme?`theme-${cerebrolData.selectedTheme}`:"",s=this.querySelector("chat-input");s&&(s.classList.remove("medium","fullscreen","closed"),e!=="closed"&&s.classList.add(e),t&&s.classList.add(t));const i=this.querySelector("chat-messages");i&&(i.classList.remove("medium","fullscreen","closed"),e!=="closed"&&i.classList.add(e),t&&i.classList.add(t)),this.querySelectorAll("chat-message").forEach(o=>{t&&o.classList.add(t)})}}customElements.get("chat-container")||customElements.define("chat-container",p);class g extends HTMLElement{constructor(){super(),this.shadow=this.attachShadow({mode:"open"}),this.isScrolledToBottom=!0,this.autoScrollEnabled=!0,this.scrollThreshold=100,this.themeStyles="",this.classList.add("chat-messages")}async connectedCallback(){typeof cerebrolData<"u"&&cerebrolData.selectedTheme&&this.classList.add(`theme-${cerebrolData.selectedTheme}`),await this.loadThemeFromDOM(),this.render(),this.setupScrollListeners(),this.scrollToBottom(),this.observer=new MutationObserver(e=>{e.forEach(t=>{t.type==="childList"&&t.addedNodes.length>0&&Array.from(t.addedNodes).some(i=>i.tagName==="CHAT-MESSAGE")&&this.autoScrollEnabled&&requestAnimationFrame(()=>{this.scrollToBottom()})})}),this.observer.observe(this,{childList:!0,subtree:!1})}async loadThemeFromDOM(){this.themeStyles=this.getDefaultStyles();try{let e="",t="";const s=document.querySelectorAll('link[rel="stylesheet"]');for(const i of s){const n=i.href;if(n.includes("/css/chat.css")){const o=await fetch(n);o.ok&&(e=await o.text())}if(n.includes("/css/themes/")){const o=await fetch(n);o.ok&&(t=await o.text())}}(e||t)&&(this.themeStyles=this.getDefaultStyles()+`
`+e+`
`+t)}catch{}}getDefaultStyles(){return`
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
        `}render(){this.shadow.innerHTML=`
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
        `}disconnectedCallback(){this.observer&&this.observer.disconnect(),this.removeScrollListeners()}setupScrollListeners(){const e=this.shadow.querySelector("#scroll-indicator");this.scrollHandler=this.throttle(()=>{this.updateScrollState()},100),this.addEventListener("scroll",this.scrollHandler),e.addEventListener("click",()=>{this.scrollToBottom(!0)})}removeScrollListeners(){this.scrollHandler&&this.removeEventListener("scroll",this.scrollHandler)}updateScrollState(){const e=this.scrollTop,t=this.scrollHeight,s=this.clientHeight,i=t-e-s;this.isScrolledToBottom=i<=this.scrollThreshold;const n=this.shadow.querySelector("#scroll-indicator");this.isScrolledToBottom?n.classList.remove("visible"):n.classList.add("visible"),this.autoScrollEnabled=this.isScrolledToBottom}scrollToBottom(e=!1){!e&&!this.autoScrollEnabled||requestAnimationFrame(()=>{const t=this.scrollHeight,s=this.clientHeight,i=t-s;i>0&&this.scrollTo({top:i,behavior:"smooth"}),setTimeout(()=>{this.updateScrollState()},100)})}scrollToMessage(e){e&&this.contains(e)&&e.scrollIntoView({behavior:"smooth",block:"nearest"})}showLoading(){const e=this.shadow.querySelector("#loading-indicator");e&&(e.classList.add("active"),this.scrollToBottom())}hideLoading(){const e=this.shadow.querySelector("#loading-indicator");e&&e.classList.remove("active")}addBotMessage(e,t=!1){const s=this.shadow.querySelector("#messages-container"),i=document.createElement("div");return i.className="chat-message bot-message",i.setAttribute("data-sender","bot"),t?(i.innerHTML=`
                <div class="message-content">
                    <span class="typing-indicator"></span>
                </div>
                <div class="icon-bot">AI</div>
            `,i.typingContainer=i.querySelector(".typing-indicator")):i.innerHTML=`
                <div class="message-content">${e}</div>
                <div class="icon-bot">AI</div>
            `,s.appendChild(i),this.autoScrollEnabled&&this.scrollToBottom(),i}addUserMessage(e){const t=this.shadow.querySelector("#messages-container"),s=document.createElement("div");return s.className="chat-message user-message",s.setAttribute("data-sender","user"),s.innerHTML=`
            <div class="message-content">${e}</div>
            <div class="icon-user">TU</div>
        `,t.appendChild(s),this.scrollToBottom(),s}clearMessages(){const e=this.shadow.querySelector("#messages-container");e&&(e.innerHTML=""),this.isScrolledToBottom=!0,this.autoScrollEnabled=!0,this.updateScrollState()}getMessages(){const e=this.shadow.querySelector("#messages-container");return e?e.querySelectorAll(".chat-message"):[]}getLastMessage(){const e=this.getMessages();return e.length>0?e[e.length-1]:null}setAutoScroll(e){this.autoScrollEnabled=e}isAtBottom(){return this.isScrolledToBottom}throttle(e,t){let s;return function(...n){const o=()=>{clearTimeout(s),e(...n)};clearTimeout(s),s=setTimeout(o,t)}}}customElements.get("chat-messages")||customElements.define("chat-messages",g);class m extends HTMLElement{static get observedAttributes(){return["sender","avatar-text"]}constructor(){super(),this.shadow=this.attachShadow({mode:"open"}),this.themeStyles=""}async connectedCallback(){typeof cerebrolData<"u"&&cerebrolData.selectedTheme&&this.classList.add(`theme-${cerebrolData.selectedTheme}`),await this.loadThemeFromDOM(),this.render()}async loadThemeFromDOM(){this.themeStyles=this.getDefaultStyles();try{let e="",t="";const s=document.querySelectorAll('link[rel="stylesheet"]');for(const i of s){const n=i.href;if(n.includes("/css/chat.css")){const o=await fetch(n);o.ok&&(e=await o.text())}if(n.includes("/css/themes/")){const o=await fetch(n);o.ok&&(t=await o.text())}}(e||t)&&(this.themeStyles=this.getDefaultStyles()+`
`+e+`
`+t)}catch{}}getDefaultStyles(){return`
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
        `}render(){const e=this.getAttribute("sender")||"bot",t=this.getAttribute("avatar-text")||(e==="user"?"YOU":"AI"),s=e==="user"?"icon-user":"icon-bot",i=this.innerHTML;this.shadow.innerHTML=`
            <style>
                ${this.themeStyles}
            </style>
            
            <div class="message-content">
                ${i}
            </div>
            
            <div class="${s}">
                ${t}
            </div>
        `}attributeChangedCallback(e,t,s){t!==s&&this.shadow&&this.render()}}customElements.get("chat-message")||customElements.define("chat-message",m),window.typewriterManagerModule=window.typewriterManagerModule||null,window.typewriterManagerInstance=window.typewriterManagerInstance||null;const f=async()=>{if(window.typewriterManagerModule)return window.typewriterManagerModule;try{return window.typewriterManagerModule=await Promise.resolve().then(()=>x),window.typewriterManagerLoadLogged||(console.log("TypewriterManager loaded successfully"),window.typewriterManagerLoadLogged=!0),window.typewriterManagerModule}catch(c){return console.error("Error importing TypewriterManager:",c),null}};class y extends HTMLElement{static get observedAttributes(){return["endpoint","placeholder","api-key","disabled"]}constructor(){super(),this.shadow=this.attachShadow({mode:"open"}),this.isSending=!1,this.isTyping=!1,this.currentTypewriter=null,this.themeStyles="",this.initTypewriterManager()}async connectedCallback(){typeof cerebrolData<"u"&&cerebrolData.selectedTheme&&this.classList.add(`theme-${cerebrolData.selectedTheme}`),await this.loadThemeFromDOM(),this.render(),this._setupEventListeners()}async loadThemeFromDOM(){this.themeStyles=this.getDefaultStyles();try{let e="",t="";const s=document.querySelectorAll('link[rel="stylesheet"]');for(const i of s){const n=i.href;if(n.includes("/css/chat.css")){const o=await fetch(n);o.ok&&(e=await o.text())}if(n.includes("/css/themes/")){const o=await fetch(n);o.ok&&(t=await o.text())}}(e||t)&&(this.themeStyles=this.getDefaultStyles()+`
`+e+`
`+t)}catch{}}getDefaultStyles(){return`
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
        `}async initTypewriterManager(){if(window.typewriterManagerInstance){this.typewriterManager=window.typewriterManagerInstance;return}try{const e=await f();e&&e.TypewriterManager?(window.typewriterManagerInstance=new e.TypewriterManager({typeSpeed:30,deleteSpeed:20,allowHTML:!0,pauseCharacters:{",":500,".":700,"!":700,"?":700}}),this.typewriterManager=window.typewriterManagerInstance,window.typewriterManagerInitLogged||(console.log("TypewriterManager initialized successfully"),window.typewriterManagerInitLogged=!0)):console.warn("Could not initialize TypewriterManager")}catch(e){console.error("Error initializing TypewriterManager:",e)}}_showWelcomeMessage(){if(!window.CerebrolyWelcomeShown&&(window.CerebrolyWelcomeShown=!0,this.typewriterManager)){let e=[];typeof cerebrolData<"u"&&cerebrolData.welcomeMessages&&Array.isArray(cerebrolData.welcomeMessages)&&(e=cerebrolData.welcomeMessages.filter(n=>n.trim()!=="")),e.length===0&&(e=["Hi! 👋 I'm an information assistant here to help you with your questions and provide details on various topics"]),this.showBotMessageWithTyping(e[0]);const t=n=>n.length*40+1500,s=[0];let i=t(e[0]);for(let n=1;n<e.length;n++)s.push(i),i+=t(e[n]);for(let n=1;n<e.length;n++)setTimeout(()=>{this.showBotMessageWithTyping(e[n])},s[n])}}attributeChangedCallback(e,t,s){var i,n,o;if(e==="disabled"){const r=(i=this.shadow)==null?void 0:i.querySelector("#message-input"),l=(n=this.shadow)==null?void 0:n.querySelector("#send-button");if(r&&l){const a=s!==null;r.disabled=a,l.disabled=a||r.value.trim()===""}}else if(e==="placeholder"&&t!==s){const r=(o=this.shadow)==null?void 0:o.querySelector("#message-input");r&&(r.placeholder=s||"Type a message...")}}updateButtonStateOnShow(){this.isTyping&&this.currentTypewriter?this._updateButtonIcon("stop"):this._updateButtonIcon("send"),window.CerebrolyWelcomeShown||this._showWelcomeMessage()}render(){const e=this.getAttribute("placeholder")||"Type a message...",t=this.hasAttribute("disabled");this.shadow.innerHTML=`
            <style>
                ${this.themeStyles}
            </style>
            
            <div class="container-input">
                <input type="text" id="message-input" placeholder="${e}" ${t?"disabled":""}>
                
                <button id="send-button" class="btn-send" ${t?"disabled":""}>
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                        <path d="M498.1 5.3c10.1 7 15.4 19.1 13.5 31.2l-64 416c-1.5 9.7-7.4 18.2-16 23s-18.9 5.4-28 1.6L284 427.7l-68.5 74.1c-8.9 9.7-22.9 12.9-35.2 8.1S160 493.2 160 480V396.4c0-4 1.5-7.8 4.2-10.7L331.8 202.8c5.8-6.3 5.6-16-.4-22s-15.7-6.4-22-.7L106 360.8 17.7 316.6C7.1 311.3 .3 300.7 0 288.9s5.9-22.8 16.1-28.7l448-256c10.7-6.1 23.9-5.5 34 1.4z"></path>
                    </svg>
                </button>
                
                <!-- Typing spinner overlay -->
                <div id="typing-overlay" class="typing-overlay">
                    <div class="spinner"></div>
                </div>
            </div>
        `}_setupEventListeners(){const e=this.shadow.querySelector("#message-input"),t=this.shadow.querySelector("#send-button");t.addEventListener("click",()=>{this.isTyping?this._handleStop():this._handleSend()}),e.addEventListener("keypress",s=>{s.key==="Enter"&&!this.isTyping&&this._handleSend()}),e.addEventListener("input",()=>{this.isTyping||(t.disabled=e.value.trim()===""||this.isSending||this.hasAttribute("disabled"))})}async _handleSend(){if(this.isSending)return;const e=this.shadow.querySelector("#message-input"),t=e.value.trim();if(t==="")return;const s=this.getAttribute("endpoint");if(!s){console.error("Endpoint not configured for chat-input");const r=typeof cerebrolData<"u"&&cerebrolData.errorMessage?cerebrolData.errorMessage:"I'm sorry, but I'm having trouble processing your request right now. Please try again in a moment.",l=new CustomEvent("error",{detail:{message:r},bubbles:!0,composed:!0});this.dispatchEvent(l);return}const i=document.querySelector("chat-messages");if(i){const r=i.addUserMessage(t);typeof cerebrolData<"u"&&cerebrolData.selectedTheme&&r.classList.add(`theme-${cerebrolData.selectedTheme}`)}const n=new CustomEvent("message-sent",{detail:{message:t},bubbles:!0,composed:!0});this.dispatchEvent(n),e.value="",this.shadow.querySelector("#send-button").disabled=!0,e.disabled=!0,this.showTypingIndicator(),this.isSending=!0;const o=this.getAttribute("api-key");try{const r={"Content-Type":"application/json"};o&&(r.Authorization=`Bearer ${o}`);const l=await fetch(s,{method:"POST",headers:r,body:JSON.stringify({messages:[{role:"user",content:t}],model:"gpt-3.5-turbo"})});if(!l.ok)throw new Error(`Error in server response: ${l.status}`);const a=await l.json();let h="";a.choices&&a.choices.length>0&&a.choices[0].message?h=a.choices[0].message.content:h=typeof cerebrolData<"u"&&cerebrolData.errorMessage?cerebrolData.errorMessage:"I'm sorry, but I'm having trouble processing your request right now. Please try again in a moment.",this.showBotMessageWithTyping(h);const u=new CustomEvent("response-received",{detail:{message:h,rawResponse:a},bubbles:!0,composed:!0});this.dispatchEvent(u)}catch(r){if(console.error("Error sending message:",r),this.typewriterManager){const a=typeof cerebrolData<"u"&&cerebrolData.errorMessage?cerebrolData.errorMessage:"I'm sorry, but I'm having trouble processing your request right now. Please try again in a moment.";this.showBotMessageWithTyping(a)}else{const a=document.querySelector("chat-messages");if(a){const h=typeof cerebrolData<"u"&&cerebrolData.errorMessage?cerebrolData.errorMessage:"I'm sorry, but I'm having trouble processing your request right now. Please try again in a moment.",u=a.addBotMessage(h);typeof cerebrolData<"u"&&cerebrolData.selectedTheme&&u.classList.add(`theme-${cerebrolData.selectedTheme}`)}}const l=new CustomEvent("error",{detail:{message:"Error communicating with server",error:r.message},bubbles:!0,composed:!0});this.dispatchEvent(l),this.setInputEnabled(!0),this.hideTypingIndicator()}finally{this.isSending=!1}}_handleStop(){this.currentTypewriter&&(this.currentTypewriter.stop(!1),this.currentTypewriter=null),this.isTyping=!1,this._updateButtonIcon("send"),this.setInputEnabled(!0),this.hideTypingIndicator()}_pauseTypewriter(){this.currentTypewriter&&this.currentTypewriter.pause()}_resumeTypewriter(){this.currentTypewriter&&this.currentTypewriter.resume()}getTypewriterState(){return this.currentTypewriter?this.currentTypewriter.getState():null}isTypewriterActive(){return this.isTyping&&this.currentTypewriter!==null}_updateButtonIcon(e){const t=this.shadow.querySelector("#send-button");t&&(t.classList.remove("btn-send","btn-stop","btn-default"),e==="stop"?(t.innerHTML=`
                <svg class="icon icon-stop" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" style="width: 13px; height: 13px;">
                    <path d="M0 128C0 92.7 28.7 64 64 64H320c35.3 0 64 28.7 64 64V384c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V128z"/>
                </svg>
            `,t.classList.add("btn-stop"),t.disabled=!1):(t.innerHTML=`
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                    <path d="M498.1 5.3c10.1 7 15.4 19.1 13.5 31.2l-64 416c-1.5 9.7-7.4 18.2-16 23s-18.9 5.4-28 1.6L284 427.7l-68.5 74.1c-8.9 9.7-22.9 12.9-35.2 8.1S160 493.2 160 480V396.4c0-4 1.5-7.8 4.2-10.7L331.8 202.8c5.8-6.3 5.6-16-.4-22s-15.7-6.4-22-.7L106 360.8 17.7 316.6C7.1 311.3 .3 300.7 0 288.9s5.9-22.8 16.1-28.7l448-256c10.7-6.1 23.9-5.5 34 1.4z"></path>
                </svg>
            `,t.classList.add("btn-send")))}async showBotMessageWithTyping(e){const t=document.querySelector("chat-messages");if(!t){console.error("chat-messages component not found"),this.hideTypingIndicator(),this.setInputEnabled(!0);return}if(this.showTypingIndicator(),this.setInputEnabled(!1),!this.typewriterManager){console.warn("TypewriterManager not available, showing message without effect");const s=t.addBotMessage(e);typeof cerebrolData<"u"&&cerebrolData.selectedTheme&&s.classList.add(`theme-${cerebrolData.selectedTheme}`),this.hideTypingIndicator(),this.setInputEnabled(!0),this._updateButtonIcon("send"),this.isTyping=!1;return}setTimeout(async()=>{try{const s=t.addBotMessage("",!0);typeof cerebrolData<"u"&&cerebrolData.selectedTheme&&s.classList.add(`theme-${cerebrolData.selectedTheme}`);const i=s.typingContainer;if(!i){console.error("Container for typing effect not found");const o=s.querySelector(".message-content");o&&(o.textContent=e),this.hideTypingIndicator(),this.setInputEnabled(!0),this._updateButtonIcon("send"),this.isTyping=!1;return}const n=this.typewriterManager.create(i,{text:[e],mode:"typeOnly",loop:!1,allowHTML:!0,cursor:{character:"",type:"none",hideWhenDone:!0},callbacks:{onComplete:o=>{const r=i.querySelector(".typewriter-cursor");r&&r.remove(),this.currentTypewriter===o&&(this.isTyping=!1,this.currentTypewriter=null,this._updateButtonIcon("send"),this.setInputEnabled(!0),this.hideTypingIndicator())},onStart:o=>{this.isTyping=!0,this._updateButtonIcon("stop"),this.hideTypingIndicator()},onPause:o=>{},onResume:o=>{},onTextChange:(o,r)=>{t.scrollToBottom()}}});this.currentTypewriter=n,n.start()}catch(s){console.error("Error applying typewriter effect:",s);const i=t.addBotMessage(e);typeof cerebrolData<"u"&&cerebrolData.selectedTheme&&i.classList.add(`theme-${cerebrolData.selectedTheme}`),this.hideTypingIndicator(),this.setInputEnabled(!0)}},1e3)}showTypingIndicator(){const e=this.shadow.querySelector("#typing-overlay");e&&e.classList.add("active")}hideTypingIndicator(){const e=this.shadow.querySelector("#typing-overlay");e&&e.classList.remove("active")}setInputEnabled(e){const t=this.shadow.querySelector("#message-input"),s=this.shadow.querySelector("#send-button");e?(this.removeAttribute("disabled"),t.disabled=!1,s.disabled=t.value.trim()===""):(this.setAttribute("disabled",""),t.disabled=!0,s.disabled=!0)}setEndpoint(e){this.setAttribute("endpoint",e)}setApiKey(e){this.setAttribute("api-key",e)}showError(e){const t=document.getElementById("status-indicator");t&&(t.textContent=e,t.style.display="block",setTimeout(()=>{t.style.display="none"},3e3))}async sendMessage(e){if(console.log("ChatInput.sendMessage called with:",e),!e||e.trim()==="")return console.warn("Cannot send empty message"),!1;this.isTypewriterActive()&&(this._handleStop(),await new Promise(s=>setTimeout(s,500))),this.isSending&&(console.log("Currently sending, waiting..."),await new Promise(s=>{const i=setInterval(()=>{this.isSending||(clearInterval(i),s())},100)}));const t=this.shadowRoot.querySelector("#message-input");return t?(t.value=e.trim(),this.setInputEnabled(!0),setTimeout(()=>{this._handleSend()},100),!0):(console.error("Could not find input element in shadow DOM"),!1)}}customElements.get("chat-input")||customElements.define("chat-input",y),window.chatBundleLoadLogged||(console.log("Chat Bundle Loaded"),window.chatBundleLoadLogged=!0);class d{constructor(e,t={}){if(this.element=typeof e=="string"?document.querySelector(e):e,!this.element)throw Error("Element not found");this.element.textContent==="Loading..."&&(this.element.textContent=""),this.options={text:[],speed:50,typeSpeed:50,deleteSpeed:30,delay:1e3,pauseCharacters:{",":500,".":700,"!":700,"?":700},cursor:{character:"|",type:"blinking"},mode:"typeAndDelete",loop:!1,smartPause:!0,allowHTML:!1,effects:{},callbacks:{onStart:null,onPause:null,onResume:null,onComplete:null,onTextChange:null}},this.t(t),this.state={currentTextIndex:0,currentCharIndex:0,currentText:"",isTyping:!1,isDeleting:!1,isPaused:!1,originalTexts:[...this.options.text],id:t.id||"typewriter-"+Math.floor(1e4*Math.random()),inSequence:!1,sequenceName:null},this.i()}t(e){for(const t in e)if(e[t]instanceof Object&&!Array.isArray(e[t])&&this.options[t])for(const s in e[t])this.options[t][s]=e[t][s];else this.options[t]=e[t]}i(){this.element.innerHTML="",this.textContainer=document.createElement("span"),this.element.appendChild(this.textContainer),this.options.cursor&&(this.cursorContainer=document.createElement("span"),this.cursorContainer.textContent=this.options.cursor.character,this.cursorContainer.style.opacity="0.7",this.options.cursor.type==="blinking"&&(this.h(),this.cursorContainer.style.animation="typewriter-blink 0.7s infinite"),this.element.appendChild(this.cursorContainer))}h(){if(!document.querySelector("style[data-typewriter-blink]")){const e=document.createElement("style");e.setAttribute("data-typewriter-blink","true"),e.textContent=`
                @keyframes typewriter-blink {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0; }
                }
            `,document.head.appendChild(e)}}start(){return this.state.currentText="",typeof this.options.callbacks.onStart=="function"&&this.options.callbacks.onStart(this),this.state.isTyping||(this.state.isTyping=!0,this.state.isPaused=!1,this.u()),this}u(){if(!this.state.isTyping||this.state.isPaused)return;const e=this.options.text[this.state.currentTextIndex];this.options.mode==="typeOnly"?this.o(e):this.state.isDeleting?this.l(e):this.o(e)}o(e){if(e.length>this.state.currentCharIndex){const t=e.charAt(this.state.currentCharIndex);this.state.currentText=(this.state.currentText||"")+t,this.options.allowHTML?this.textContainer.innerHTML=this.state.currentText:this.textContainer.textContent=this.state.currentText,typeof this.options.callbacks.onTextChange=="function"&&this.options.callbacks.onTextChange(this.textContainer.textContent,this);let s=this.options.typeSpeed;this.options.smartPause&&this.options.pauseCharacters[t]&&(s+=this.options.pauseCharacters[t]),this.state.currentCharIndex++,setTimeout(()=>this.u(),s)}else this.options.mode==="typeOnly"?this.p():setTimeout(()=>{this.state.isDeleting=!0,this.state.currentCharIndex=e.length,this.u()},this.options.delay)}l(e){this.state.currentCharIndex>0?(this.state.currentText=this.state.currentText.slice(0,-1),this.options.allowHTML?this.textContainer.innerHTML=this.state.currentText:this.textContainer.textContent=this.state.currentText,typeof this.options.callbacks.onTextChange=="function"&&this.options.callbacks.onTextChange(this.textContainer.textContent,this),this.state.currentCharIndex--,setTimeout(()=>this.u(),this.options.deleteSpeed)):this.p()}p(){if(this.state.currentTextIndex++,this.state.isDeleting=!1,this.state.currentText="",this.state.currentTextIndex>=this.options.text.length){if(!this.options.loop)return this.state.isTyping=!1,void(typeof this.options.callbacks.onComplete=="function"&&this.options.callbacks.onComplete(this));this.state.currentTextIndex=0}setTimeout(()=>this.u(),this.options.delay)}pause(){return this.state.isTyping&&!this.state.isPaused&&(this.state.isPaused=!0,typeof this.options.callbacks.onPause=="function"&&this.options.callbacks.onPause(this)),this}resume(){return this.state.isTyping&&this.state.isPaused&&(this.state.isPaused=!1,typeof this.options.callbacks.onResume=="function"&&this.options.callbacks.onResume(this),this.u()),this}stop(e=!0){return this.state.isTyping=!1,this.state.isPaused=!1,this.state.currentText="",e&&(this.textContainer.textContent="",this.options.allowHTML&&(this.textContainer.innerHTML="")),this.state.currentTextIndex=0,this.state.currentCharIndex=0,this}reset(){return this.stop(!0),this.options.text=[...this.state.originalTexts],this.state.currentText="",this.start(),this}addText(e){return Array.isArray(e)?this.options.text.push(...e):this.options.text.push(e),this}skip(){return this.state.isTyping&&(this.state.currentTextIndex++,this.options.text.length>this.state.currentTextIndex||(this.state.currentTextIndex=this.options.loop?0:this.options.text.length-1),this.state.currentCharIndex=0,this.state.isDeleting=!1,this.state.currentText="",this.textContainer.textContent="",this.options.allowHTML&&(this.textContainer.innerHTML=""),this.u()),this}changeTexts(e,t=!0){return this.options.text=e,this.state.originalTexts=[...e],this.state.currentTextIndex=0,this.state.currentCharIndex=0,this.state.currentText="",t&&(this.stop(!0),this.start()),this}updateOptions(e){return this.t(e),this}destroy(){this.stop(!0),this.textContainer&&this.element.removeChild(this.textContainer),this.cursorContainer&&this.element.removeChild(this.cursorContainer),this.state.currentText=""}getElement(){return this.element}getId(){return this.state.id}getState(){return{...this.state}}}class b{constructor(e={}){this.instances=new Map,this.globalOptions=e,this.sequences={},this.activeSequences=new Set}create(e,t={}){const s={...this.globalOptions,...t},i=new d(e,s);return this.instances.set(i.getId(),i),i}get(e){return this.instances.get(e)||null}getAll(){return this.instances}m(e,t,s=[]){const i=this.instances.get(e);return i&&typeof i[t]=="function"&&i[t](...s),this}T(e,t=[]){return this.instances.forEach(s=>{typeof s[e]=="function"&&s[e](...t)}),this}start(e){return this.m(e,"start")}startAll(){return this.T("start")}pause(e){return this.m(e,"pause")}pauseAll(){return this.T("pause")}resume(e){return this.m(e,"resume")}resumeAll(){return this.T("resume")}stop(e,t=!0){return this.m(e,"stop",[t])}stopAll(e=!0){return this.T("stop",[e])}remove(e){const t=this.instances.get(e);return t&&(t.destroy(),this.instances.delete(e)),this}removeAll(){return this.instances.forEach(e=>e.destroy()),this.instances.clear(),this.sequences={},this.activeSequences.clear(),this}updateGlobalOptions(e){return this.globalOptions={...this.globalOptions,...e},this}updateInstanceOptions(e,t){return this.m(e,"updateOptions",[t])}createSequence(e,t,s=500){return this.sequences[e]={ids:t,delay:s,isPlaying:!1,isPaused:!1,currentIndex:0,currentInstance:null},this}playSequence(e){const t=this.sequences[e];if(!t||t.isPlaying)return this;t.isPlaying=!0,t.isPaused=!1,t.currentIndex=0,this.activeSequences.add(e);const s=()=>{if(t.isPlaying&&!t.isPaused)if(t.ids.length>t.currentIndex){const i=this.instances.get(t.ids[t.currentIndex]);if(i){i.stop(!0),i.state.inSequence=!0,i.state.sequenceName=e,t.currentInstance=i;const n=i.options.callbacks.onComplete;i.options.callbacks.onComplete=o=>{typeof n=="function"&&n(o),t.isPlaying&&!t.isPaused&&(t.currentIndex++,setTimeout(()=>s(),t.delay))},i.start()}else t.currentIndex++,setTimeout(s,0)}else t.isPlaying=!1,t.isPaused=!1,t.currentIndex=0,t.currentInstance=null,this.activeSequences.delete(e)};return s(),this}pauseSequence(e){const t=this.sequences[e];return t&&t.isPlaying&&(t.isPaused=!0,t.currentInstance&&t.currentInstance.pause()),this}resumeSequence(e){const t=this.sequences[e];return t&&t.isPlaying&&t.isPaused&&(t.isPaused=!1,t.currentInstance&&t.currentInstance.getState().isPaused?t.currentInstance.resume():this.playSequence(e)),this}stopSequence(e){const t=this.sequences[e];return t&&(t.isPlaying=!1,t.isPaused=!1,t.ids&&Array.isArray(t.ids)&&t.ids.forEach(s=>{const i=this.instances.get(s);i&&(i.state.inSequence=!1,i.state.sequenceName=null,i.getState().isTyping&&i.stop(!0))}),t.currentInstance=null,this.activeSequences.delete(e)),this}getActiveSequenceInstance(e){const t=this.sequences[e];return t?t.currentInstance:null}createBulk(e,t={},s=!1){const i=document.querySelectorAll(e),n=[];return i.forEach((o,r)=>{const l={...t,id:t.id?`${t.id}-${r}`:void 0};typeof t.textGenerator=="function"&&(l.text=t.textGenerator(o,r));const a=this.create(o,l);n.push(a.getId()),s&&a.start()}),n}syncStart(e){return e.forEach(t=>this.stop(t,!0)),e.forEach(t=>this.start(t)),this}}class w extends d{constructor(e,t={}){super(e,t)}}const x=Object.freeze(Object.defineProperty({__proto__:null,TypewriterEffect:d,TypewriterJS:w,TypewriterManager:b},Symbol.toStringTag,{value:"Module"}))})();
