
# The Streekomroep WordPress theme

This is a WordPress theme made for Streekomroep ZuidWest in The Netherlands. It's made using Timber and Tailwind CSS and provides functionality for regional news, radio and tv broadcasts. 

## Installation
This is a WordPress theme with some hard dependencies. You can't run it without these dependencies.

1. Install WordPress 5.6.x
2. Install and activate the hard dependencies
3. Upload the theme to your `wp-content/themes`
4. Switch to the `streekomroep` theme

### Hard dependencies
Install these before activating the theme:
- Timber 1.x [[free download](https://wordpress.org/plugins/timber-library/)]
- Advanced Custom Fields Pro 5.x [[purchace](https://www.advancedcustomfields.com/pro/)]
- Classic Editor 1.x [[free download](https://wordpress.org/plugins/classic-editor/)] _(we are giving the block editor a bit more time to stabilize)_

### Soft dependencies
These are tested plug-ins and are great additions to the theme:
- Contact Form 7 5.3.x [[free download](https://wordpress.org/plugins/contact-form-7/)]
- Disable Comments 2.x [[free download](https://wordpress.org/plugins/disable-comments/)]
- Yoast SEO Premium 15.x [[purchace](https://yoast.com/wordpress/plugins/seo/)]

## What's here?

`static/` is where you can keep your static front-end scripts, styles, or images. In other words, your Sass files, JS files, fonts, and SVGs would live here.

`templates/` contains all of your Twig templates. These pretty much correspond 1 to 1 with the PHP files that respond to the WordPress template hierarchy. At the end of each PHP template, you'll notice a `Timber::render()` function whose first parameter is the Twig file where that data (or `$context`) will be used. Just an FYI.

`bin/` and `tests/` ... basically don't worry about (or remove) these unless you know what they are and want to.
