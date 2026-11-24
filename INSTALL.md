# Installation

This is a WordPress theme with some hard dependencies. You can't run it without these dependencies.

1. Install WordPress 7.1+
2. Install and activate the hard dependencies
3. Upload the theme to your `wp-content/themes`
4. Switch to the `streekomroep` theme

## Requirements

- WordPress 7.1 or higher
- PHP 8.4 or higher

## Hard dependencies

Install these before activating the theme:

- Timber 2.5.1: Bundled via Composer, no separate installation needed
- Secure Custom Fields 6.9+ or Advanced Custom Fields Pro 6.8+: Docker uses [Secure Custom Fields](https://wordpress.org/plugins/secure-custom-fields/) for development; licensed environments may use [ACF Pro](https://www.advancedcustomfields.com/pro/).
- Yoast SEO 27.7+ (free or Premium): [free download](https://wordpress.org/plugins/wordpress-seo/) or [purchase Premium](https://yoast.com/wordpress/plugins/seo/)

## Soft dependencies

These optional plugins complement the theme:

- Classic Editor 1.7.x: [free download](https://wordpress.org/plugins/classic-editor/)
- Co-Authors Plus 4.1.x: [free download](https://wordpress.org/plugins/co-authors-plus/) for multi-author bylines and guest-author profiles
- Contact Form 7 6.1.x: [free download](https://wordpress.org/plugins/contact-form-7/)
- Disable Comments 2.7.x: [free download](https://wordpress.org/plugins/disable-comments/)

## REST API Endpoints

The theme provides the following REST API endpoint:

### Broadcast Data

```text
GET /wp-json/zw/v1/broadcast_data
```

Returns the current and next radio broadcast, plus today's and tomorrow's TV schedule.
The `fm.now` and `fm.next` values are program names when broadcasts are
available, and `null` otherwise.

Response format:

```json
{
  "fm": {
    "now": "Program Name",
    "next": null
  },
  "tv": {
    "today": ["Show 1", "Show 2"],
    "tomorrow": ["Show 1", "Show 2"]
  }
}
```
