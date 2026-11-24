# Streekomroep WordPress theme

The WordPress theme for [Streekomroep ZuidWest](https://www.zuidwestupdate.nl/), built with [Timber](https://timber.github.io/docs/v2/), Twig and Tailwind CSS. It supports regional news, radio, television and video.

For installation, updates and site setup, see [INSTALL.md](INSTALL.md).

## Development

The Docker environment has WordPress 7.1 on PHP 8.5, MariaDB, phpMyAdmin, WP-CLI and the plugins the theme needs.

```bash
docker compose up -d

# Use another PHP version
PHP_VERSION=8.4 docker compose up -d --build
```

The first start installs WordPress, creates the default menus and activates the theme. Every start installs the Composer and npm packages, builds the CSS and puts the latest Secure Custom Fields in place. ACF is removed from the container if you installed it.

- WordPress: <http://localhost:8080> (`admin` / `admin`)
- phpMyAdmin: <http://localhost:8081> (`wordpress` / `wordpress`)

Stop the environment with `docker compose down`. To delete the local site and database and start over, use `docker compose down -v`.

To work on the theme outside Docker:

```bash
composer install
npm install
npm run watch:tailwind
```

### Build

```bash
composer install --prefer-dist --no-dev --optimize-autoloader
npm install
npm run build:tailwind
```

The last command compiles minified CSS from `assets/` into `dist/`.

### Quality checks

Run these before submitting changes:

```bash
vendor/bin/phpcs --standard=phpcs.xml .
composer lint:twig
composer test
git diff --check
```

The Docker container installs Composer packages in your checkout without the development packages. Run `composer install` again if `vendor/bin/phpcs` is missing.

Use `composer fix:twig` to fix supported Twig formatting issues. Verify frontend changes in Docker and rebuild the CSS.

## Project structure

| Path | Purpose |
| --- | --- |
| `src/` | PSR-4 classes in the `Streekomroep\` namespace |
| `lib/` | WordPress hooks, post types, taxonomies and integrations |
| `templates/` | Twig views |
| `assets/` / `dist/` | Tailwind sources and generated CSS |
| `static/` | Browser-side JavaScript |
| `icons/` | SVG icons |
| `streekomroep-acf-json/` | Version-controlled SCF/ACF field groups |
| `tests/` | Regression tests |
| `docker/` | Local WordPress environment |

WordPress templates in the repository root render views from `templates/`. Commit updated JSON in `streekomroep-acf-json/` whenever SCF or ACF fields change.

## ZuidWest plugins

These plugins work with the theme:

- [NLPO Dashboard API](https://github.com/oszuidwest/nlpo-dashboard-api) provides article and traffic data to the NLPO audience dashboard.
- [TekstTV](https://github.com/oszuidwest/teksttv-wp-plugin) manages Tekst TV slides and provides the JSON feed for the playout application.
- [TekstTV Extensions](https://github.com/oszuidwest/teksttv-wp-extensions) adds radio and television schedule ticker messages from this theme to TekstTV.
- [ZuidWest Cache Manager](https://github.com/oszuidwest/zw-cacheman) purges and warms the Cloudflare cache when content changes.
- [ZuidWest GR 2026](https://github.com/oszuidwest/zw-gr26-wp) adds pages for the 2026 municipal elections to the website and app.
- [ZuidWest Knabbel](https://github.com/oszuidwest/zw-knabbel-wp) sends WordPress posts to the Babbel API for radio news.
- [ZuidWest Liveblog](https://github.com/oszuidwest/zw-liveblog) embeds 24LiveBlog liveblogs and adds structured data.
- [ZuidWest Pangram](https://github.com/oszuidwest/zw-pangram) checks posts with the Pangram Labs AI text detector.
- [ZuidWest Poll](https://github.com/oszuidwest/zw-poll) adds anonymous polls. The theme gives them the house style.
- [ZuidWest Staart](https://github.com/oszuidwest/zw-staart) adds related reading and podcast promotion below articles.
- [ZuidWest Stipt](https://github.com/oszuidwest/zw-stipt) keeps scheduled posts from ending up as "Missed schedule".
- [ZuidWest Webapp](https://github.com/oszuidwest/zw-webapp) adds push notifications and PWA support.

Other optional plugins are in [INSTALL.md](INSTALL.md#optional-plugins).

## REST API

`GET /wp-json/zw/v1/broadcast_data` is public and returns the radio and television schedule:

```json
{
  "fm": {
    "now": "Programme 1",
    "next": "Programme 2",
    "schedule": {
      "current": {
        "name": "Programme 1",
        "start": 1795593600,
        "end": 1795597200,
        "start_time": "09:00",
        "end_time": "10:00",
        "label": null,
        "show": {
          "title": "Programme 1",
          "link": "https://example.com/fm/programme-1/",
          "makers": [
            {
              "name": "Maker 1",
              "photo": {
                "src": "https://example.com/maker-44.jpg",
                "srcset": "https://example.com/maker-88.jpg 2x"
              }
            }
          ],
          "makers_label": "Maker 1"
        }
      },
      "upcoming": [],
      "refresh_after": 1800
    }
  },
  "tv": {
    "today": ["Programme 3"],
    "tomorrow": ["Programme 3", "Programme 4"]
  }
}
```

- `now`, `next` and `current` can be `null`. Gaps between programmes count as broadcasts too, with `show` set to `null`.
- `upcoming` holds at most two programmes in the same format as `current`, without the gaps.
- `start` and `end` are Unix timestamps. `start_time` and `end_time` are in the site's time zone.
- `label` is `null` for today, `morgen` for tomorrow, and otherwise the Dutch name of the weekday.
- `photo` is `null` when a maker has no photo.
- `refresh_after` is the number of seconds until the current broadcast ends, with a minimum of 1. It is 30 when `current` is `null`.

The theme also adds fields to the core REST API responses of dossiers, fragments, television shows and radio shows. They are registered in `zw_rest_api_init()` in `functions.php`.

## License

Mozilla Public License 2.0, see [LICENSE](LICENSE).
