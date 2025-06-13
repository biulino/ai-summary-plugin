# AI Summary WordPress Plugin

A comprehensive WordPress plugin that generates AI-powered summaries, key points, and FAQs for posts and WooCommerce products using OpenRouter or Google Gemini APIs.

## Features

- **AI-powered content analysis** - Generate summaries, key points, and FAQs automatically
- **Multiple AI providers** - Support for OpenRouter and Google Gemini APIs
- **WooCommerce integration** - Works with both regular posts and WooCommerce products
- **REST API endpoints** - Access summaries via JSON REST API
- **Structured data output** - Generates valid JSON-LD for SEO
- **Admin interface** - Easy-to-use meta boxes and settings pages
- **Automatic generation** - Optional auto-generation on post save
- **Manual controls** - Regenerate summaries with a single click
- **SEO friendly** - Includes robots.txt integration for AI crawlers
- **Shortcode support** - Display summaries anywhere with `[ai_summary]`

## Installation

1. **Upload the plugin files** to the `/wp-content/plugins/ai-summary/` directory
2. **Activate the plugin** through the 'Plugins' screen in WordPress
3. **Configure the API settings** by going to Settings → AI Summaries
4. **Add your API key** and select your preferred AI provider

## Configuration

### API Settings

1. Navigate to **Settings → AI Summaries** in your WordPress admin
2. Configure the following settings:

   - **API Key**: Your OpenRouter or Google Gemini API key
   - **Provider**: Choose between OpenRouter or Gemini
   - **Model**: Specify the AI model (e.g., `gpt-4o-mini`, `gemini-pro`)
   - **Temperature**: Control creativity (0.0 = deterministic, 1.0 = very creative)
   - **Auto-generate**: Enable automatic generation on post save
   - **Robots.txt Integration**: Allow AI crawlers to access summary endpoints

### Getting API Keys

#### OpenRouter
1. Visit [OpenRouter.ai](https://openrouter.ai/)
2. Sign up for an account
3. Generate an API key in your dashboard
4. Add credits to your account

#### Google Gemini
1. Visit [Google AI Studio](https://makersuite.google.com/)
2. Create a new project
3. Generate an API key
4. Enable the Generative Language API

## Usage

### Manual Summary Generation

1. **Edit any post or product** in WordPress
2. **Scroll to the "AI Summary" meta box**
3. **Click "Regenerate Summary"** to generate new content
4. **Edit the generated content** as needed
5. **Save the post** to store the summary

### Automatic Generation

1. **Enable auto-generate** in Settings → AI Summaries
2. **Publish or update posts** - summaries will be generated automatically
3. **Check the meta box** to see the generated content

### Displaying Summaries

#### Using the Shortcode

```php
// Basic usage
[ai_summary]

// Show only key points
[ai_summary show_faq="no"]

// For a specific post
[ai_summary post_id="123"]
```

#### Using the REST API

```bash
# Get summary by post slug
GET /wp-json/ai/v1/summary/{post-slug}

# Get summary by post ID
GET /wp-json/ai/v1/summary/id/{post-id}
```

#### Using the URL Endpoint

```bash
# Access structured data directly
GET /post-slug/ai-summary/
```

## API Endpoints

### REST API Routes

- `GET /wp-json/ai/v1/summary/{slug}` - Get summary by post slug
- `GET /wp-json/ai/v1/summary/id/{id}` - Get summary by post ID

### URL Endpoints

- `/{post-slug}/ai-summary/` - Direct JSON-LD output

## JSON-LD Output Structure

```json
{
  "@context": "https://schema.org",
  "@type": "Article",
  "name": "Post Title",
  "url": "https://example.com/post-slug/",
  "datePublished": "2025-01-01T00:00:00+00:00",
  "dateModified": "2025-01-01T00:00:00+00:00",
  "description": "AI-generated summary",
  "author": {
    "@type": "Person",
    "name": "Author Name"
  },
  "keyPoints": [
    "Key point 1",
    "Key point 2"
  ],
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Frequently asked question?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Answer to the question"
      }
    }
  ]
}
```

## Supported Post Types

- **Posts** (`post`)
- **Pages** (`page`)
- **WooCommerce Products** (`product`) - when WooCommerce is installed

## Requirements

- **WordPress 5.0+**
- **PHP 7.4+**
- **cURL extension** (for API calls)
- **JSON extension** (for data processing)
- **WooCommerce** (optional, for product support)

## File Structure

```
ai-summary/
├── plugin-main.php              # Main plugin file
├── includes/
│   ├── Class-Settings.php       # Settings page handler
│   ├── Class-MetaBox.php        # Post/product meta box
│   ├── Class-Generator.php      # AI summary generation
│   └── Class-Endpoint.php       # REST API endpoints
├── admin/
│   ├── js/
│   │   ├── meta-box.js         # Meta box JavaScript
│   │   └── settings.js         # Settings page JavaScript
│   └── css/
│       ├── meta-box.css        # Meta box styles
│       └── settings.css        # Settings page styles
├── template-ai-summary.php      # Summary template
├── uninstall.php               # Cleanup on uninstall
└── README.md                   # This file
```

## Maintenance Tools

The plugin includes several maintenance tools accessible from Settings → AI Summaries:

- **Flush Rewrite Rules** - Refresh URL rewriting for summary endpoints
- **Regenerate All Summaries** - Bulk regenerate summaries for all posts

## Logging and Debugging

The plugin logs activities to `/wp-content/uploads/ai-summary-logs/ai-summary.log`:

- API requests and responses
- Generation successes and failures
- Error messages and debugging information

## SEO and Crawlers

The plugin automatically adds robots.txt rules to allow AI crawlers:

```
User-agent: GPTBot
User-agent: Google-Extended
User-agent: PerplexityBot
Allow: /*/ai-summary/
Allow: /wp-json/ai/v1/
```

## Security Features

- **Nonce verification** for all AJAX requests
- **Capability checks** for admin functions
- **Data sanitization** for all inputs
- **SQL injection protection** using prepared statements

## Performance Considerations

- **Content chunking** for large posts (8000 character limit)
- **Response caching** (1 hour cache headers)
- **Efficient database queries** with proper indexing
- **Background processing** for bulk operations

## Troubleshooting

### Common Issues

1. **"No API key configured"**
   - Add your API key in Settings → AI Summaries

2. **"API request failed"**
   - Check your API key validity
   - Verify you have sufficient credits
   - Check your internet connection

3. **"No summary generated"**
   - Ensure the post has sufficient content
   - Check the error logs in `/wp-content/uploads/ai-summary-logs/`

4. **URL endpoints not working**
   - Go to Settings → AI Summaries and click "Flush Rewrite Rules"
   - Check your permalink structure in Settings → Permalinks

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Hooks and Filters

### Actions

- `ai_summary_before_generation` - Fired before generating a summary
- `ai_summary_after_generation` - Fired after successful generation
- `ai_summary_generation_failed` - Fired when generation fails

### Filters

- `ai_summary_prompt` - Modify the AI prompt before sending
- `ai_summary_content` - Filter the content before processing
- `ai_summary_response` - Filter the AI response before parsing
- `ai_summary_json_ld` - Modify the JSON-LD output

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## License

GPL v2 or later. See the WordPress Plugin License for details.

## Support

For support and questions:

1. Check the troubleshooting section above
2. Review the plugin logs
3. Create an issue on the plugin repository
4. Contact the plugin author

## Changelog

### Version 1.0.0
- Initial release
- OpenRouter and Gemini API support
- WordPress and WooCommerce integration
- REST API endpoints
- JSON-LD structured data output
- Admin interface and meta boxes
- Shortcode support
- Automatic and manual generation
- SEO and robots.txt integration
