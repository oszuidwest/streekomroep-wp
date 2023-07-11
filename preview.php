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
<html class="h-full bg-gray-50">
<head>
    <link rel="stylesheet" href="dist/style.css"/>
</head>
<body class="grid min-h-full place-items-center p-6">
<iframe class="h-full shadow w-[400px]" src="<?= htmlspecialchars($_GET['url']); ?>"></iframe>
</body>
</html>
