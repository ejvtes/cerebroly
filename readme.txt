=== Cerebroly ===
Contributors: ejvtes
Tags: ai, chatbot, openai, fine-tuning, rag
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.5.3
License: AGPL-3.0-or-later
License URI: https://www.gnu.org/licenses/agpl-3.0.html

AI-powered chat plugin for WordPress with RAG and fine-tuning support. Train the AI on your own content and embed the chat widget anywhere.

== Description ==

Cerebroly turns your WordPress site into a conversational AI agent powered by OpenAI. Train the AI on your own content and deploy the chat widget inside your site or on external sites.

Two AI modes, configurable from the settings panel:

**RAG (Retrieval-Augmented Generation)** — indexes posts, pages, WooCommerce products, and uploaded files into a vector database. Each query retrieves the most relevant content chunks and passes them to the model to generate a grounded response. No retraining required when content changes.

**Fine-tuning** — generates a JSONL dataset from your site content, uploads it to OpenAI, and creates a custom model. Requires retraining after significant content changes.

Additional features:

* Embeddable chat widget via shortcode `[cerebroly_chat]` or external iframe
* 3 built-in visual themes (light, dark, corporate)
* Per-minute rate limiting
* CORS domain whitelist for external embeds
* REST API for third-party integration
* Included translations: EN, ES, FR, PT

== Installation ==

1. Upload the `cerebroly/` folder to `/wp-content/plugins/`.
2. Activate the plugin from **Plugins → Installed Plugins**.
3. Go to **Cerebroly → Settings** and enter your OpenAI API key.
4. Choose a mode (RAG or Fine-tuning) and configure it.
5. Add `[cerebroly_chat]` to any page or post.

== Frequently Asked Questions ==

= Do I need an OpenAI account? =

Yes. Cerebroly uses the OpenAI API for fine-tuning and embeddings. You provide the API key; all calls are made from your server under your account.

= Can I use the widget on an external site? =

Yes. Add the external domain to the allowed domains list, enable CORS in Settings, and paste the embed snippet into the external site's HTML.

= Does it work without WP-Cron? =

RAG indexing uses WP-Cron for background batch processing. If your hosting does not run WP-Cron automatically, configure a real cron job pointing to `wp-cron.php`.

= How is the API key resolved? =

The plugin looks for the key in this order: (1) `OPENAI_API_KEY` environment variable, (2) constant defined in `wp-config.php`, (3) option stored in the database. If defined via environment or constant, the database option is removed automatically.

= The widget does not appear =

Verify that the selected mode (RAG or Fine-tuning) is configured and active, and that the shortcode is on the correct page.

= Error "No active model" =

For RAG: run the indexing process. For Fine-tuning: wait for training to complete and mark the model as active from the dashboard.

== Screenshots ==

1. Main dashboard with system status.
2. RAG mode configuration.
3. Fine-tuning dataset editor (Monaco).
4. Widget appearance settings.
5. Chat widget on the frontend.

== Changelog ==

= 1.5.2 =
* Initial public release.

== Upgrade Notice ==

= 1.5.2 =
First release.
