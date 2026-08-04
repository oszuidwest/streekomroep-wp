# Streekomroep WordPress Theme

The WordPress theme for [Streekomroep ZuidWest](https://www.zuidwestupdate.nl/), built with [Timber](https://timber.github.io/docs/v2/), Twig, and Tailwind CSS. It supports regional news, radio, television, and video.

## Requirements

- WordPress 7.0+
- PHP 8.3+
- Secure Custom Fields 6.9+ or Advanced Custom Fields Pro 6.8+
- Yoast SEO 27.7+ (free or Premium)

Timber 2.5.x and other PHP libraries are bundled through Composer. An ACF-compatible plugin and Yoast SEO must be active before activating the theme.

## Installation

For production, download the latest archive from [GitHub Releases](https://github.com/oszuidwest/streekomroep-wp/releases), install the required plugins, and upload the theme through WordPress or extract it to `wp-content/themes/streekomroep`. Release archives include Composer dependencies and compiled CSS.

To build the theme from source:

```bash
composer install --prefer-dist --no-dev --optimize-autoloader
npm install
npm run build:tailwind
```

## Development

The Docker environment provides WordPress 7.0 on PHP 8.3, MariaDB, phpMyAdmin, WP-CLI, and the required development plugins.

```bash
docker compose up -d
```

On first startup it installs WordPress, builds the theme, creates the default menus, and activates the theme.

- WordPress: <http://localhost:8080> (`admin` / `admin`)
- phpMyAdmin: <http://localhost:8081> (`wordpress` / `wordpress`)

Stop the environment with `docker compose down`. To delete the local database and start over, use `docker compose down -v`.

For development without the automatic Docker build:

```bash
composer install
npm install
npm run watch:tailwind
```

Run `npm run build:tailwind` to compile minified CSS from `assets/` into `dist/`.

### Quality checks

There is no dedicated automated test suite. Run the baseline checks before submitting changes:

```bash
vendor/bin/phpcs --standard=phpcs.xml .
composer lint:twig
git diff --check
```

Use `composer fix:twig` to fix supported Twig formatting issues. Verify frontend changes in Docker and rebuild the CSS.

## Project structure

| Path | Purpose |
| --- | --- |
| `src/` | PSR-4 classes in the `Streekomroep\` namespace |
| `lib/` | WordPress hooks, post types, taxonomies, and integrations |
| `templates/` | Twig views |
| `assets/` / `dist/` | Tailwind sources and generated CSS |
| `static/` | Browser-side JavaScript |
| `streekomroep-acf-json/` | Version-controlled SCF/ACF field groups |
| `docker/` | Local WordPress environment |

WordPress template entrypoints live in the repository root and render views from `templates/`. Commit updated JSON in `streekomroep-acf-json/` whenever SCF or ACF fields change.

## Soft dependencies

These optional plugins complement the theme:

- [Classic Editor](https://wordpress.org/plugins/classic-editor/) 1.7.x
- [Contact Form 7](https://wordpress.org/plugins/contact-form-7/) 6.1.x
- [Disable Comments](https://wordpress.org/plugins/disable-comments/) 2.7.x

## Extra functionality with first-party plugins

- [NLPO Dashboard API](https://github.com/oszuidwest/nlpo-dashboard-api) provides article and traffic data to the NLPO audience dashboard.
- [TekstTV](https://github.com/oszuidwest/teksttv-wp-plugin) manages Tekst TV slides and provides the JSON feed for the playout application.
- [TekstTV Streekomroep Extensions](https://github.com/oszuidwest/teksttv-wp-extensions) adds radio and television schedule ticker messages from this theme to TekstTV.
- [ZuidWest Cache Manager](https://github.com/oszuidwest/zw-cacheman) purges and warms the Cloudflare cache when content changes.
- [ZuidWest GR 2026](https://github.com/oszuidwest/zw-gr26-wp) adds pages for the 2026 municipal elections to the website and app.
- [ZuidWest Knabbel](https://github.com/oszuidwest/zw-knabbel-wp) sends WordPress posts to the Babbel API for radio news.
- [ZuidWest Liveblog](https://github.com/oszuidwest/zw-liveblog) embeds 24LiveBlog liveblogs and adds structured data.
- [ZuidWest Staart](https://github.com/oszuidwest/zw-staart) adds related reading and podcast promotion below articles.
- [ZuidWest Webapp](https://github.com/oszuidwest/zw-webapp) adds push notifications and PWA support.

## Integrations

### imgproxy

Optional [imgproxy](https://imgproxy.net/) support can be configured under **Settings > Media** with `zw_imgproxy_key`, `zw_imgproxy_salt`, and `zw_imgproxy_url`. If any value is missing, the theme falls back to Timber image resizing.

Existing deployments can use the `IMGPROXY_KEY`, `IMGPROXY_SALT`, and `IMGPROXY_URL` constants in `wp-config.php` as fallbacks.

### REST API

The theme provides one public read-only endpoint:

- `GET /wp-json/zw/v1/broadcast_data` returns the current and next radio programme and the television schedules for today and tomorrow.

## License

See [LICENSE](LICENSE).
