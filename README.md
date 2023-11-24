
# The Streekomroep WordPress Theme

This is a WordPress theme created for Streekomroep ZuidWest in the Netherlands. It utilizes Timber and Tailwind CSS, offering functionality for regional news, radio, and TV broadcasts.

## How to build/install
Instructions for macOS:
- Install Homebrew ([https://brew.sh](https://brew.sh))
- Install Composer and Node with `brew install composer node`
- Download the theme from GitHub to a local folder
- Open the folder in a terminal and execute the following commands:

```bash
NODE_ENV=production npx tailwindcss build assets/style.css -o dist/style.css --minify
composer install --prefer-dist --no-dev --optimize-autoloader
```
- Upload the theme to `/wp-content/themes/` and activate it.

For Linux users, use `apt` or `yum` instead of Homebrew. This theme has not been tested on Windows, but should work if your Composer and Node versions are up-to-date. To build remotely, consider using GitHub Actions or [Buddy CI/CD](https://buddy.works/) for the building and uploading process.

### Hard dependencies
Install these before activating the theme:
- Timber 2.0: [Use composer](https://timber.github.io/docs/v2/installation/installation/)]
- Advanced Custom Fields Pro 6.2.x: [[purchase](https://www.advancedcustomfields.com/pro/)]
- Classic Editor 1.x: [[free download](https://wordpress.org/plugins/classic-editor/)] _(we are giving the block editor more time to stabilize)_
- Yoast SEO Premium 21.x: [[purchase](https://yoast.com/wordpress/plugins/seo/)]

### Soft dependencies
These tested plugins enhance the theme:
- Contact Form 7 5.8.x: [[free download](https://wordpress.org/plugins/contact-form-7/)]
- Disable Comments 2.x: [[free download](https://wordpress.org/plugins/disable-comments/)]

## Extra functionality with first-party plugins
Some first-party plugins developed by Streekomroep ZuidWest add extra functionality to this theme. They are optional and can be installed separately:
- ZuidWest Webapp [[on GitHub](https://github.com/oszuidwest/zw-webapp)]: Adds push messages and functionality for a progressive web app using the service Progressier.
- Tekst TV GPT [[on GitHub](https://github.com/oszuidwest/teksttvgpt)]: Adds a button that generates 'tekst tv' summaries for articles using OpenAI GPT models.

## What's here?
`static/`: Store your static front-end scripts, styles, or images here, including Sass files, JS files, fonts, and SVGs.

`templates/`: Contains all Twig templates, corresponding closely with the PHP files in the WordPress template hierarchy. Each PHP template ends with a `Timber::render()` function, linking to the Twig file where the data (or `$context`) is used.
