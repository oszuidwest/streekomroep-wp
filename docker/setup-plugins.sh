#!/bin/bash
set -e

# Wait for WordPress to be ready
until wp core is-installed --allow-root 2>/dev/null; do
    echo "Waiting for WordPress installation..."
    sleep 5
done

echo "WordPress is ready. Installing plugins..."

# Install Yoast SEO Premium
if ! wp plugin is-installed wordpress-seo-premium --allow-root 2>/dev/null; then
    echo "Downloading Yoast SEO Premium..."
    wp plugin install "https://yoast.com/app/uploads/2026/02/wordpress-seo-premium-26.9.zip" --activate --allow-root || echo "Failed to install Yoast SEO Premium"
fi

# Install Yoast News SEO
if ! wp plugin is-installed wpseo-news --allow-root 2>/dev/null; then
    echo "Downloading Yoast News SEO..."
    wp plugin install "https://yoast.com/app/uploads/2025/02/wpseo-news-13.3.zip" --activate --allow-root || echo "Failed to install Yoast News SEO"
fi

# Activate theme
wp theme activate streekomroep --allow-root 2>/dev/null || echo "Theme not found or already active"

echo "Plugin setup complete!"
