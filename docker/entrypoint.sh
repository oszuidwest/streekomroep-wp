#!/bin/bash
set -e

# Run WordPress setup in background after Apache starts
(
    # Wait for WordPress files to be ready
    while [ ! -f /var/www/html/wp-includes/version.php ]; do
        sleep 2
    done

    # Create directories
    mkdir -p /var/www/html/wp-content/uploads
    mkdir -p /var/www/html/wp-content/plugins
    mkdir -p /var/www/html/wp-content/upgrade

    # Wait for database
    sleep 3

    # Install WordPress if not already installed
    if ! wp core is-installed --allow-root 2>/dev/null; then
        echo "Installing WordPress..."
        wp core install \
            --url="http://localhost:8080" \
            --title="Streekomroep" \
            --admin_user="admin" \
            --admin_password="admin" \
            --admin_email="admin@example.com" \
            --locale="nl_NL" \
            --skip-email \
            --allow-root

        # Install Dutch language pack and set as default
        wp language core install nl_NL --allow-root
        wp site switch-language nl_NL --allow-root

        # Install Yoast plugins
        echo "Installing Yoast plugins..."
        wp plugin install "https://yoast.com/app/uploads/2026/02/wordpress-seo-premium-26.9.zip" --activate --allow-root || echo "Failed to install Yoast SEO Premium"
        wp plugin install "https://yoast.com/app/uploads/2025/02/wpseo-news-13.3.zip" --activate --allow-root || echo "Failed to install Yoast News SEO"

        # Activate theme if exists
        wp theme activate streekomroep --allow-root 2>/dev/null || true

        echo "WordPress installed successfully!"
    fi

    # Fix permissions AFTER installation
    chown -R www-data:www-data /var/www/html/wp-content/uploads
    chown -R www-data:www-data /var/www/html/wp-content/plugins
    chown -R www-data:www-data /var/www/html/wp-content/upgrade

    # Set default ACLs so new files/dirs are automatically owned by www-data
    setfacl -R -d -m u:www-data:rwX /var/www/html/wp-content/uploads
    setfacl -R -d -m u:www-data:rwX /var/www/html/wp-content/plugins

    echo "Permissions fixed!"
) &

# Call original WordPress entrypoint
exec docker-entrypoint.sh "$@"
