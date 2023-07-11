<?php

// Don't load theme support functionality
define('WP_USE_THEMES', false);

require('../../../wp-load.php');

if (!is_user_logged_in()) {
    wp_die('Only for logged in users');
}

if (!isset($_GET['url'])) {
    wp_die('You need to pass a url to display');
}

?>
<!doctype html>
<html class="h-full">
<head>
    <link rel="stylesheet" href="dist/style.css"/>
    <meta name="viewport" content="width=device-width"/>
    <script>
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark')
            document.documentElement.classList.add('bg-neutral-900')
        } else {
            document.documentElement.classList.add('bg-gray-50')
        }
    </script>
</head>
<body class="flex min-h-full flex-col items-center py-4 gap-4 dark:text-white">
<div>
    <a href="<?= htmlspecialchars($_GET['url']); ?>" class="underline">Open desktop-weergave</a>
</div>
<iframe class="h-full flex-grow shadow w-full rounded" style="max-width: 400px;" src="<?= htmlspecialchars($_GET['url']); ?>"></iframe>
</body>
</html>
