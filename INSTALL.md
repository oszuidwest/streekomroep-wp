# Installation

For local development, the project structure and the REST API, see [README.md](README.md).

## Requirements

- WordPress 7.1+
- PHP 8.4+
- [Secure Custom Fields](https://wordpress.org/plugins/secure-custom-fields/) 6.9+ or [Advanced Custom Fields Pro](https://www.advancedcustomfields.com/pro/) 6.8+
- [Yoast SEO](https://wordpress.org/plugins/wordpress-seo/) 27.7+, with or without [Premium](https://yoast.com/wordpress/plugins/seo/)

## Install

1. Install and activate the required plugins.
2. Download `streekomroep-wp-theme-<version>.zip` from the latest [release](https://github.com/oszuidwest/streekomroep-wp/releases). It holds one `streekomroep` folder with the PHP libraries and the compiled CSS.
3. Upload it under Appearance > Themes, or extract it in `wp-content/themes`.
4. Activate the Streekomroep theme.

The source code archives on GitHub are not built. To install from source, clone the repository to `wp-content/themes/streekomroep` and [build it](README.md#build).

## Update

Check the release notes for breaking changes. Then upload the new zip under Appearance > Themes and let WordPress replace the installed theme.

## Set up

### Menus and logo

Assign menus to Main menu, Top menu and Footer Menu. A Top menu item with the CSS class `icon-radio`, `icon-tv` or `icon-search` shows that icon.

Set the logo and site icon in the Customizer. The footer's social links come from the social profiles in Yoast SEO.

### Pages

Create a page for each template:

| Template | Content |
| --- | --- |
| FM Player | Live radio |
| FM Guide | Radio schedule |
| TV Player | Live television. Use one page per channel, and set the stream and frequencies on the page itself. |
| TV Guide | Television schedule |
| Search | Search form |
| Colofon | Staff, chosen on the page itself |

### Settings

The menu has three settings pages for administrators.

Radio instellingen holds the live streams, frequencies, first day of the week, name for hours without a show, live track titles and the audio logger for listening back. The radio schedule itself comes from the radio shows.

TV instellingen holds the weekly schedule and the Bunny Stream settings. Desking holds the blocks on the front page and the placeholder for articles without a region.

### Bunny Stream

Television episodes and video fragments are hosted on [Bunny Stream](https://bunny.net/stream/). Audio fragments link to an MP3 file. Fill in the hostname, library ID and API key of both libraries under TV instellingen, and the ID of the Bunny collection on each television show. WP-Cron looks for new episodes every ten minutes.

### imgproxy

The theme can resize images with [imgproxy](https://imgproxy.net/). Fill in `imgproxy_key`, `imgproxy_salt` and `imgproxy_url` under Settings > Media. If one is empty, Timber resizes the images.

## Optional plugins

- [Classic Editor](https://wordpress.org/plugins/classic-editor/). On save, the theme limits content without blocks to the HTML in `lib/input_sanitizer.php`. Content with blocks is saved as is.
- [Co-Authors Plus](https://wordpress.org/plugins/co-authors-plus/) adds multi-author bylines and guest-author profiles.
- [Contact Form 7](https://wordpress.org/plugins/contact-form-7/). The theme styles its forms.
- [Disable Comments](https://wordpress.org/plugins/disable-comments/). The theme has no comment templates.

The plugins made by ZuidWest are in [README.md](README.md#zuidwest-plugins).
