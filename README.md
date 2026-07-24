# Streekomroep WordPress Theme

The WordPress theme for [Streekomroep ZuidWest](https://www.zuidwestupdate.nl/), built with [Timber](https://timber.github.io/docs/v2/), Twig, and Tailwind CSS. It supports regional news, radio, television, video, and Tekst TV.

## Requirements

- WordPress 7.0+
- PHP 8.3+
- Secure Custom Fields 6.8.x or Advanced Custom Fields Pro 6.x
- Classic Editor 1.x
- Yoast SEO Premium 27.x

Timber 2.5.x and other PHP libraries are bundled through Composer. Secure Custom Fields (or ACF Pro) and Yoast SEO must be active before activating the theme.

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

There is no dedicated automated test suite. Run the PHP and Twig linters before submitting changes:

```bash
vendor/bin/phpcs --standard=phpcs.xml .
composer lint:twig
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

## Integrations

### imgproxy

Optional [imgproxy](https://imgproxy.net/) support can be configured under **Settings > Media** with `zw_imgproxy_key`, `zw_imgproxy_salt`, and `zw_imgproxy_url`. If any value is missing, the theme falls back to Timber image resizing.

Existing deployments can use the `IMGPROXY_KEY`, `IMGPROXY_SALT`, and `IMGPROXY_URL` constants in `wp-config.php` as fallbacks.

### REST API

The theme provides two public read-only endpoints:

- `GET /wp-json/zw/v1/broadcast_data` returns the current and next radio programme and the television schedules for today and tomorrow.
- `GET /wp-json/zw/v1/teksttv?channel=tv1` returns slides and ticker messages for a channel configured in `ZW_TEKSTTV_CHANNELS`.

Optional first-party extensions include [ZuidWest Webapp](https://github.com/oszuidwest/zw-webapp) for push notifications and PWA support, and [Tekst TV GPT](https://github.com/oszuidwest/teksttvgpt) for AI-generated Tekst TV summaries.

## License

See [LICENSE](LICENSE).
