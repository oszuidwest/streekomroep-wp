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

    # Install dependencies and build theme assets
    THEME_DIR=/var/www/html/wp-content/themes/streekomroep
    echo "Installing Composer dependencies..."
    composer install --no-dev --no-interaction --working-dir="$THEME_DIR"
    echo "Installing npm dependencies and building CSS..."
    npm install --prefix "$THEME_DIR"
    npm run build:tailwind --prefix "$THEME_DIR"

    install_secure_custom_fields() {
        echo "Installing/updating Secure Custom Fields latest stable..."
        for plugin in advanced-custom-fields advanced-custom-fields-pro; do
            if wp plugin is-installed "$plugin" --allow-root 2>/dev/null; then
                wp plugin deactivate "$plugin" --allow-root || true
                wp plugin delete "$plugin" --allow-root || echo "Failed to remove legacy plugin $plugin"
            fi
        done

        wp plugin install secure-custom-fields --force --activate --allow-root || echo "Failed to install Secure Custom Fields"
        SCF_INSTALLED_VERSION=$(wp plugin get secure-custom-fields --field=version --allow-root 2>/dev/null || true)
        if [ -n "$SCF_INSTALLED_VERSION" ]; then
            echo "Secure Custom Fields ${SCF_INSTALLED_VERSION} installed."
        fi
    }

    # Wait for database
    sleep 3

    # Install WordPress if not already installed
    WORDPRESS_WAS_INSTALLED=1
    if ! wp core is-installed --allow-root 2>/dev/null; then
        WORDPRESS_WAS_INSTALLED=0
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

        # Install plugins before language pack to avoid WP-CLI locale conflicts
        YOAST_SEO_VERSION="27.6"
        echo "Installing Yoast SEO ${YOAST_SEO_VERSION}..."
        wp plugin install wordpress-seo --version="$YOAST_SEO_VERSION" --activate --allow-root || echo "Failed to install Yoast SEO"

        echo "Installing Yoast SEO Premium ${YOAST_SEO_VERSION}..."
        wp plugin install "https://yoast.com/app/uploads/2026/05/wordpress-seo-premium-${YOAST_SEO_VERSION}.zip" --activate --allow-root || echo "Failed to install Yoast SEO Premium"

        # Secure Custom Fields provides the ACF-compatible APIs the theme needs.
        # It MUST be installed before the theme is activated: functions.php returns
        # early (registering no menus/post types) when the ACF API is missing, which
        # would make the "wp menu location assign" calls below fail on a fresh install.
        install_secure_custom_fields

        echo "Installing Classic Editor..."
        wp plugin install classic-editor --activate --allow-root

        # Install Dutch language pack and set as default
        wp language core install nl_NL --allow-root
        wp site switch-language nl_NL --allow-root

        # Activate theme if exists
        wp theme activate streekomroep --allow-root 2>/dev/null || true

        # Configure Yoast social profiles
        echo "Configuring social profiles..."
        wp option patch update wpseo_social facebook_site 'https://www.facebook.com/ZuidWestUpdate' --allow-root
        wp option patch update wpseo_social twitter_site 'zwupdate' --allow-root
        wp option patch update wpseo_social other_social_urls --format=json '["https://www.instagram.com/zuidwestupdate/","https://www.tiktok.com/@zuidwestupdate"]' --allow-root

        # Create top menu (icons for Radio, TV, Search)
        echo "Creating top menu..."
        wp menu create "Top" --allow-root
        wp menu location assign Top top --allow-root
        wp menu item add-custom Top "Radio" "http://localhost:8080/radio/" --classes="icon-radio" --allow-root
        wp menu item add-custom Top "TV" "http://localhost:8080/tv/" --classes="icon-tv" --allow-root
        wp menu item add-custom Top "Zoeken" "http://localhost:8080/zoeken/" --classes="icon-search" --allow-root

        # Create main menu
        echo "Creating main menu..."
        wp menu create "Main" --allow-root
        wp menu location assign Main main --allow-root
        wp menu item add-custom Main "Nieuws" "http://localhost:8080/nieuws/" --allow-root
        wp menu item add-custom Main "Sport" "http://localhost:8080/sport/" --allow-root
        wp menu item add-custom Main "112" "http://localhost:8080/112/" --allow-root
        wp menu item add-custom Main "Cultuur" "http://localhost:8080/cultuur/" --allow-root
        wp menu item add-custom Main "Economie" "http://localhost:8080/economie/" --allow-root
        wp menu item add-custom Main "Politiek" "http://localhost:8080/politiek/" --allow-root

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

    echo "Updating WordPress to the latest security release..."
    wp core update --minor --allow-root
    wp core update-db --allow-root

    # On restarts of an existing install the block above is skipped, so (re)install
    # SCF here too, ensuring existing installs pick up updates.
    if [ "$WORDPRESS_WAS_INSTALLED" -eq 1 ]; then
        install_secure_custom_fields
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
