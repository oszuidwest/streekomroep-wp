# Installation

This is a WordPress theme with some hard dependencies. You can't run it without these dependencies.

1. Install WordPress 7.0+
2. Install and activate the hard dependencies
3. Upload the theme to your `wp-content/themes`
4. Switch to the `streekomroep` theme

## Requirements

- WordPress 7.0 or higher
- PHP 8.3 or higher

## Hard dependencies

Install these before activating the theme:

- Timber 2.5.1: Bundled via Composer, no separate installation needed
- Secure Custom Fields 6.9+ or Advanced Custom Fields Pro 6.8+: Docker uses [Secure Custom Fields](https://wordpress.org/plugins/secure-custom-fields/) for development; licensed environments may use [ACF Pro](https://www.advancedcustomfields.com/pro/).
- Yoast SEO 27.7+ (free or Premium): [free download](https://wordpress.org/plugins/wordpress-seo/) or [purchase Premium](https://yoast.com/wordpress/plugins/seo/)

## Soft dependencies

These optional plugins complement the theme:

- Classic Editor 1.7.x: [free download](https://wordpress.org/plugins/classic-editor/)
- Contact Form 7 6.1.x: [free download](https://wordpress.org/plugins/contact-form-7/)
- Disable Comments 2.7.x: [free download](https://wordpress.org/plugins/disable-comments/)

## Migrating an existing Tekst TV installation

Tekst TV is now provided by the separate [TekstTV plugin](https://github.com/oszuidwest/teksttv-wp-plugin). For an existing installation:

1. Back up the WordPress database.
2. Install and activate the TekstTV plugin, configure its channels and content, and verify its feed at `GET /wp-json/teksttv/v1/slides?channel=<channel-slug>`.
3. Point every playout device at the new feed and verify that it is polling successfully. The old theme route `GET /wp-json/zw/v1/teksttv` is not retained by this theme.
4. Deploy this theme version.
5. Preview the old theme data that will be removed. On multisite, include `--url=<site-url>` and repeat this step for each site:

   ```bash
   wp eval-file scripts/clean-teksttv-acf-fields.php
   ```

6. Review every listed option name, metadata key, and ACF post ID. Then remove the old data. On multisite, include `--url=<site-url>` and repeat this step for each site:

   ```bash
   wp eval-file scripts/clean-teksttv-acf-fields.php delete
   ```

The cleanup is intentionally a dry run unless the `delete` argument is present. It removes the old theme-owned ACF data and three legacy `ttvgpt_*` options, but preserves the replacement plugin's `teksttv_*` configuration.

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
