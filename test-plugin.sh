#!/bin/bash

# Set variables
CONTAINER_NAME="wp-test-container"
MYSQL_CONTAINER_NAME="wp-mysql"
WORDPRESS_PORT=8090
PLUGIN_DIR="content-insights-for-editors"
NETWORK_NAME="wordpress-network"

# WordPress setup variables
WP_TITLE="Test"
WP_ADMIN_USER="super"
WP_ADMIN_PASSWORD="test"
WP_ADMIN_EMAIL="admin@example.com"

# Create network if it doesn't exist
if ! docker network inspect $NETWORK_NAME >/dev/null 2>&1; then
    docker network create $NETWORK_NAME
fi

# Stop and remove existing containers if they exist
docker stop $CONTAINER_NAME $MYSQL_CONTAINER_NAME 2>/dev/null
docker rm $CONTAINER_NAME $MYSQL_CONTAINER_NAME 2>/dev/null

# Create and start MariaDB container
docker run -d --name $MYSQL_CONTAINER_NAME --network $NETWORK_NAME \
    -e MYSQL_ROOT_PASSWORD=rootpassword \
    -e MYSQL_DATABASE=exampledb \
    -e MYSQL_USER=exampleuser \
    -e MYSQL_PASSWORD=examplepass \
    mariadb:latest

echo "Waiting for MariaDB to initialize..."
sleep 20

# Create and start WordPress container
docker run -d --name $CONTAINER_NAME -p $WORDPRESS_PORT:80 \
    -v "$(pwd)/$PLUGIN_DIR":/var/www/html/wp-content/plugins/$PLUGIN_DIR \
    -e WORDPRESS_DB_HOST=$MYSQL_CONTAINER_NAME \
    -e WORDPRESS_DB_USER=exampleuser \
    -e WORDPRESS_DB_PASSWORD=examplepass \
    -e WORDPRESS_DB_NAME=exampledb \
    -e WORDPRESS_DEBUG=1 \
    -e WORDPRESS_CONFIG_EXTRA="define( 'WP_DEBUG_LOG', true ); define( 'WP_DEBUG_DISPLAY', true ); @ini_set('display_errors', 1);" \
    --network $NETWORK_NAME \
    wordpress:latest

echo "Waiting for WordPress to start..."
sleep 20

# Install WP-CLI
docker exec $CONTAINER_NAME curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
docker exec $CONTAINER_NAME chmod +x wp-cli.phar
docker exec $CONTAINER_NAME mv wp-cli.phar /usr/local/bin/wp

# Install WordPress
docker exec $CONTAINER_NAME wp core install \
    --url=http://localhost:$WORDPRESS_PORT \
    --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email \
    --allow-root

# Install and activate the plugin
docker exec $CONTAINER_NAME wp plugin activate $PLUGIN_DIR --allow-root

# Check plugin files
echo "Checking plugin files in Docker container..."
docker exec $CONTAINER_NAME find /var/www/html/wp-content/plugins/$PLUGIN_DIR -type f -print0 | docker exec -i $CONTAINER_NAME xargs -0 ls -l

# Check specific files
echo "Checking specific plugin files..."
docker exec $CONTAINER_NAME ls -l /var/www/html/wp-content/plugins/$PLUGIN_DIR/assets/css/admin.css
docker exec $CONTAINER_NAME ls -l /var/www/html/wp-content/plugins/$PLUGIN_DIR/assets/js/admin.js

# Check and fix uploads directory
echo "Checking and fixing uploads directory..."
docker exec $CONTAINER_NAME mkdir -p /var/www/html/wp-content/uploads
docker exec $CONTAINER_NAME chown -R www-data:www-data /var/www/html/wp-content/uploads
docker exec $CONTAINER_NAME chmod -R 755 /var/www/html/wp-content/uploads

# Check PHP upload settings
echo "Checking PHP upload settings..."
docker exec $CONTAINER_NAME php -i | grep -i upload

docker exec $CONTAINER_NAME apachectl restart

# Check and update WordPress URLs
echo "Checking and updating WordPress URLs..."
docker exec $CONTAINER_NAME wp option update siteurl "http://localhost:$WORDPRESS_PORT" --allow-root
docker exec $CONTAINER_NAME wp option update home "http://localhost:$WORDPRESS_PORT" --allow-root

# Check WordPress debug log
echo "Checking WordPress debug log..."
docker exec $CONTAINER_NAME cat /var/www/html/wp-content/debug.log || echo "Debug log is empty or not created yet."

# Print access information
echo "WordPress test site is now running!"
echo "Access the site at: http://localhost:$WORDPRESS_PORT"
echo "Access wp-admin at: http://localhost:$WORDPRESS_PORT/wp-admin"
echo "Username: $WP_ADMIN_USER"
echo "Password: $WP_ADMIN_PASSWORD"

# Open the WordPress admin page in the default browser
open http://localhost:$WORDPRESS_PORT/wp-admin