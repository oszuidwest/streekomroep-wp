
# The Streekomroep WordPress theme

This is a WordPress theme made for Streekomroep ZuidWest in the Netherlands. It's made using Timber and Tailwind CSS and provides functionality for regional news, radio and tv broadcasts.

## How to build/install
Instructions for macOS:
- Install Homebrew (https://brew.sh)
- Install Composer and Node with `brew install composer node`
- Download the theme from GitHub to a local folder
- Open the folder in a terminal and execute the following commands:

```bash
NODE_ENV=production npx tailwindcss build assets/style.css -o dist/style.css --minify
composer install --prefer-dist --no-dev --optimize-autoloader
```
- Upload the theme to `/wp-content/themes/` and activate it.

Use `apt` or `yum` instead of Homebrew if you use Linux. This theme was never built on Windows, but you should be able to do so if your Composer and Node version are somewhat up-to-date. If you don't want to build it locally, we suggest using GitHub Actions or [Buddy CI/CD](https://buddy.works/) to handle the building and uploading.

### Hard dependencies
Install these before activating the theme:
- Timber 2.0 [Use composer](https://timber.github.io/docs/v2/installation/installation/)
- Advanced Custom Fields Pro 6.2.x [[purchase](https://www.advancedcustomfields.com/pro/)]
- Classic Editor 1.x [[free download](https://wordpress.org/plugins/classic-editor/)] _(we are giving the block editor a bit more time to stabilize)_
- Yoast SEO Premium 21.x [[purchase](https://yoast.com/wordpress/plugins/seo/)]

### Soft dependencies
These are tested plug-ins and are great additions to the theme:
- Contact Form 7 5.8.x [[free download](https://wordpress.org/plugins/contact-form-7/)]
- Disable Comments 2.x [[free download](https://wordpress.org/plugins/disable-comments/)]

## Extra functionality with first-party plug-ins
There are some first party plug-ins developed by Streekomroep ZuidWest that add extra functionality to this theme. They are optional and can be installed separately:
- ZuidWest Webapp [[on GitHub](https://github.com/oszuidwest/zw-webapp)]: adds push messages and functionality for a progressive web app using the service Progressier.
- Tekst TV GPT [[on GitHub](https://github.com/oszuidwest/teksttvgpt)]: adds a button that generates 'tekst tv' summaries for articles using the OpenAI GPT-models.

## What's here?
`static/` is where you can keep your static front-end scripts, styles, or images. In other words, your Sass files, JS files, fonts, and SVGs would live here.

`templates/` contains all of your Twig templates. These pretty much correspond 1 to 1 with the PHP files that respond to the WordPress template hierarchy. At the end of each PHP template, you'll notice a `Timber::render()` function whose first parameter is the Twig file where that data (or `$context`) will be used. Just an FYI.
