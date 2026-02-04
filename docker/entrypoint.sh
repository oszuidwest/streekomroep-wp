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

        # Install Yoast plugins (sequential to avoid conflicts)
        echo "Installing Yoast SEO Premium..."
        wp plugin install "https://yoast.com/app/uploads/2026/02/wordpress-seo-premium-26.9.zip" --activate --allow-root || echo "Failed to install Yoast SEO Premium"

        echo "Installing Yoast News SEO..."
        wp plugin install "https://yoast.com/app/uploads/2025/02/wpseo-news-13.3.zip" --activate --allow-root || echo "Failed to install Yoast News SEO"

        echo "Installing Classic Editor..."
        wp plugin install classic-editor --activate --allow-root

        # Activate theme if exists
        wp theme activate streekomroep --allow-root 2>/dev/null || true

        # Configure Yoast social profiles
        echo "Configuring social profiles..."
        wp option patch update wpseo_social facebook_site 'https://www.facebook.com/ZuidWestUpdate' --allow-root
        wp option patch update wpseo_social twitter_site 'zwupdate' --allow-root
        wp option patch update wpseo_social other_social_urls --format=json '["https://www.instagram.com/zuidwestupdate/","https://www.tiktok.com/@zuidwestupdate"]' --allow-root

        # Create footer menu
        echo "Creating footer menu..."
        wp menu create "Footer" --allow-root
        wp menu location assign Footer footer --allow-root

        # Over ons section
        wp menu item add-custom Footer "Over ons" "#" --allow-root
        OVER_ONS=$(wp menu item list Footer --fields=db_id --format=csv --allow-root | tail -1)
        wp menu item add-custom Footer "Algemene informatie" "http://localhost:8080/algemene-info/" --parent-id=$OVER_ONS --allow-root
        wp menu item add-custom Footer "Bestuur en Toezicht" "http://localhost:8080/bestuur/" --parent-id=$OVER_ONS --allow-root
        wp menu item add-custom Footer "Frequenties" "http://localhost:8080/frequenties/" --parent-id=$OVER_ONS --allow-root
        wp menu item add-custom Footer "PBO" "http://localhost:8080/pbo/" --parent-id=$OVER_ONS --allow-root
        wp menu item add-custom Footer "Managementteam" "http://localhost:8080/management-team/" --parent-id=$OVER_ONS --allow-root
        wp menu item add-custom Footer "Colofon" "http://localhost:8080/colofon/" --parent-id=$OVER_ONS --allow-root

        # Adverteren section
        wp menu item add-custom Footer "Adverteren" "#" --allow-root
        ADVERTEREN=$(wp menu item list Footer --fields=db_id --format=csv --allow-root | tail -1)
        wp menu item add-custom Footer "Reclame" "http://localhost:8080/reclame/" --parent-id=$ADVERTEREN --allow-root
        wp menu item add-custom Footer "Videoproducties" "http://localhost:8080/video-producties/" --parent-id=$ADVERTEREN --allow-root
        wp menu item add-custom Footer "Webinars" "http://localhost:8080/webinars/" --parent-id=$ADVERTEREN --allow-root

        # Contact section
        wp menu item add-custom Footer "Contact" "#" --allow-root
        CONTACT=$(wp menu item list Footer --fields=db_id --format=csv --allow-root | tail -1)
        wp menu item add-custom Footer "Tip de redactie" "http://localhost:8080/tip-de-redactie/" --parent-id=$CONTACT --allow-root
        wp menu item add-custom Footer "Vacatures" "http://localhost:8080/vacatures/" --parent-id=$CONTACT --allow-root
        wp menu item add-custom Footer "Klachtenprocedure" "http://localhost:8080/klachtenprocedure/" --parent-id=$CONTACT --allow-root
        wp menu item add-custom Footer "Storing melden" "http://localhost:8080/storing-melden/" --parent-id=$CONTACT --allow-root

        # Nieuws section (regional links)
        wp menu item add-custom Footer "Nieuws" "#" --allow-root
        NIEUWS=$(wp menu item list Footer --fields=db_id --format=csv --allow-root | tail -1)
        wp menu item add-custom Footer "Roosendaal" "http://localhost:8080/regio/roosendaal/" --parent-id=$NIEUWS --allow-root
        wp menu item add-custom Footer "Bergen op Zoom" "http://localhost:8080/regio/bergen-op-zoom/" --parent-id=$NIEUWS --allow-root
        wp menu item add-custom Footer "Etten-Leur" "http://localhost:8080/regio/etten-leur/" --parent-id=$NIEUWS --allow-root
        wp menu item add-custom Footer "Woensdrecht" "http://localhost:8080/regio/woensdrecht/" --parent-id=$NIEUWS --allow-root
        wp menu item add-custom Footer "Moerdijk" "http://localhost:8080/regio/moerdijk/" --parent-id=$NIEUWS --allow-root
        wp menu item add-custom Footer "Halderberge" "http://localhost:8080/regio/halderberge/" --parent-id=$NIEUWS --allow-root
        wp menu item add-custom Footer "Steenbergen" "http://localhost:8080/regio/steenbergen/" --parent-id=$NIEUWS --allow-root
        wp menu item add-custom Footer "Tholen" "http://localhost:8080/regio/tholen/" --parent-id=$NIEUWS --allow-root
        wp menu item add-custom Footer "Rucphen" "http://localhost:8080/regio/rucphen/" --parent-id=$NIEUWS --allow-root
        wp menu item add-custom Footer "Zundert" "http://localhost:8080/regio/zundert/" --parent-id=$NIEUWS --allow-root
        wp menu item add-custom Footer "West-Brabant" "http://localhost:8080/regio/west-brabant/" --parent-id=$NIEUWS --allow-root

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
