=== KissAi Widget ===
Contributors: KissAi
Donate link: https://kissai.tech/donate
Tags: AI, OpenAI, GPT, Assistant, Custom Training
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.7.93
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

KissAi seamlessly integrates AI-powered assistants into WordPress using OpenAI models. Train custom AI with knowledge files for smarter interactions.

== Description ==

**KissAi Widget** allows you to easily integrate AI assistants into your WordPress website, enhancing user engagement with intelligent, conversational experiences. Supporting OpenAI models and upcoming integrations with **Claude** and **Gemini**, this plugin enables AI-driven assistants for customer support, content generation, product guidance, and more.

The plugin offers **custom assistant creation**, allowing users to upload **knowledge files** and train AI assistants to provide accurate responses based on specific business data. The intuitive admin interface simplifies managing assistants, training them, and adjusting their behavior.

### Key Features:
- **Customizable AI Assistants**: Configure AI assistants with tailored settings and behaviors, including real-time SSE streaming for GPT-4.
- **Advanced Knowledge Base**: Upload multiple files, manage them within WordPress, and create vector stores to improve AI context and accuracy.
- **Threaded Conversations**: Built-in system for storing and displaying user conversation history, providing persistent chat threads for better user engagement.
- **Suggested Questions**: Optionally generate relevant follow-up or sample questions to guide user inquiries.
- **Multiple AI Model Support**: Currently supports OpenAI’s GPT-3.5 and GPT-4, with plans for Claude, Gemini, and more.
- **Interactive User Engagement**: AI responds to real-time user inquiries, providing dynamic content updates and relevant suggestions.
- **Flexible API Integration**: Use your OpenAI API key or the KissAi API key for seamless AI interactions.
- **Shortcode Integration**: Embed AI assistants easily using shortcodes (`[kissai_chat_widget]`) or visual page editors like Elementor or AVADA.
- **Custom Logging and Usage Stats**: Records conversation usage and logs for easier troubleshooting and usage reporting.
- **Data Privacy and Security**: Securely manages API keys and user data, respecting best practices for privacy and security.

== Installation ==

1. **Download and Install the Plugin:**
    - Upload the `kissai` plugin folder to `/wp-content/plugins/`.
    - Activate the plugin from the WordPress 'Plugins' menu.
2. **Configure API Settings:**
    - Navigate to **KissAi > Settings** and enter your OpenAI or KissAi API key.
    - Register for a **KissAi API key** within the plugin or obtain an OpenAI key from OpenAI.
3. **Create and Train Assistants:**
    - Go to **KissAi > Assistants > Add New** to create an assistant.
    - Upload custom knowledge files in the **Knowledge Base** section to train the assistant and build vector stores.
4. **Embed AI Assistants:**
    - Use the shortcode `[kissai_chat assistant_id="asst_xxxxxxxxxxxxxxxxxxxxxxxx"]` to place the AI assistant on any page or post.
    - Optionally enable threaded conversations and usage logging under **KissAi > Settings**.

== Frequently Asked Questions ==

= How do I create and train my AI assistant? =
Navigate to **KissAi > Assistants > Add New** and upload knowledge files to train the assistant with accurate, domain-specific data.

= Can I manage multiple assistants? =
Yes, you can create and configure multiple AI assistants under **KissAi > Assistants** in the WordPress admin panel, each with separate knowledge files.

= What AI models does KissAi Widget support? =
Currently, KissAi supports OpenAI’s **GPT-3.5** and **GPT-4** models, with future updates including **Grok**, **Claude** and **Gemini**.

= How do I display my AI assistant on my website? =
Use the shortcode `[kissai_chat assistant_id="asst_xxxxxxxxxxxxxxxxxxxxxxxx"]` to embed the assistant on any page or post. It also integrates with **Elementor** and **AVADA**.

= Is it secure to use my OpenAI or KissAi API key? =
Yes, the plugin securely manages API keys to protect your data and interactions. You can use either your OpenAI API key or a KissAi API key.

= Can I update the assistant’s knowledge base over time? =
Absolutely. You can continuously upload and refine knowledge files to improve your AI assistant’s accuracy and effectiveness. Each new or edited file is incorporated into the vector store for richer responses.

= Does KissAi Widget store my chat history? =
Optionally, yes. It can store each user’s conversation in WordPress (thread-based), so you can provide persistent AI chat experiences or review session logs.

== Screenshots ==

1. **AI Chat Widget**: An example of an AI-powered chat widget in action.
2. **Admin Settings**: The plugin's settings page, showcasing model configuration and knowledge file uploads.
3. **Custom Knowledge Upload**: A user-friendly interface for managing and uploading AI training files.

== Changelog ==

= 1.0 =
* Initial plugin release.

= 1.7.81 =
* Added SSE support for GPT endpoints.
* Introduced threaded conversation storage in WordPress (optional).
* Enhanced knowledge base management with vector store creation.
* Added suggested questions feature for richer user interactions.
* Logging and usage stats for troubleshooting and advanced analytics.

== Future Features ==

* AI training through website crawling and scraping.
* Integration with **Claude**, **Gemini**, and additional AI models.
* Expanded customization for AI behavior and content interactions.
* Enhanced knowledge base management for improving AI capabilities.
* More robust function-calling (Tool) interface for external actions.

== License ==
KissAi Plugin is open-source software licensed under the GPLv2 or later.
