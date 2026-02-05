# Installation

This is a WordPress theme with some hard dependencies. You can't run it without these dependencies.

1. Install WordPress 6.9+
2. Install and activate the hard dependencies
3. Upload the theme to your `wp-content/themes`
4. Switch to the `streekomroep` theme

## Requirements
- WordPress 6.9 or higher
- PHP 8.3 or higher

## Hard dependencies
Install these before activating the theme:
- Timber 2.3: Bundled via Composer, no separate installation needed
- Advanced Custom Fields Pro 6.3.x: [[purchase](https://www.advancedcustomfields.com/pro/)]
- Classic Editor 1.x: [[free download](https://wordpress.org/plugins/classic-editor/)] _(we are giving the block editor more time to stabilize)_
- Yoast SEO Premium 24.x: [[purchase](https://yoast.com/wordpress/plugins/seo/)]

## Soft dependencies
These are tested plugins and are great additions to the theme:
- Contact Form 7 6.0.x: [[free download](https://wordpress.org/plugins/contact-form-7/)]
- Disable Comments 2.x: [[free download](https://wordpress.org/plugins/disable-comments/)]

## REST API Endpoints

The theme provides the following REST API endpoints:

### Tekst TV
```
GET /wp-json/zw/v1/teksttv?channel={channel}
```
Returns slides and ticker messages for the Tekst TV system. The `channel` parameter is required and must match a configured channel (e.g., `tv1`).

Response format:
```json
{
  "slides": [...],
  "ticker": [...]
}
```
