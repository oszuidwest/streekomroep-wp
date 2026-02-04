FROM wordpress:6.9.1-php8.3-apache

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Install unzip for plugin installation and acl for default permissions
RUN apt-get update && apt-get install -y unzip acl && rm -rf /var/lib/apt/lists/*

# Copy setup script
COPY setup-plugins.sh /usr/local/bin/setup-plugins.sh
RUN chmod +x /usr/local/bin/setup-plugins.sh

# Copy and set entrypoint that fixes permissions
COPY entrypoint.sh /usr/local/bin/custom-entrypoint.sh
RUN chmod +x /usr/local/bin/custom-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/custom-entrypoint.sh"]
CMD ["apache2-foreground"]
