#!/bin/bash

echo "🚀 Setting up Archetype WordPress Plugin Development Environment..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker and try again."
    exit 1
fi

# Create themes directory if it doesn't exist
mkdir -p themes

# Start the development environment
echo "📦 Starting WordPress development environment..."
docker-compose up -d

# Wait for WordPress to be ready
echo "⏳ Waiting for WordPress to be ready..."
while ! curl -s http://localhost:8080 > /dev/null; do
    sleep 2
done

echo "✅ WordPress is ready!"

# Install WordPress
echo "🔧 Setting up WordPress..."
docker-compose exec wpcli wp core install \
    --url="http://localhost:8080" \
    --title="Archetype Plugin Development" \
    --admin_user="admin" \
    --admin_password="password" \
    --admin_email="dev@archetype.com" \
    --skip-email

# Activate all plugins
echo "🔌 Activating plugins..."
docker-compose exec wpcli wp plugin activate wp-ant-media-stream-access
docker-compose exec wpcli wp plugin activate wp-video-library
docker-compose exec wpcli wp plugin activate wp-rocket-chat-embed

# Install useful development plugins
echo "🛠️ Installing development tools..."
docker-compose exec wpcli wp plugin install query-monitor --activate
docker-compose exec wpcli wp plugin install debug-bar --activate

# Set development theme (Twenty Twenty-Three with debugging features)
docker-compose exec wpcli wp theme activate twentytwentythree

# Enable WordPress debugging
docker-compose exec wpcli wp config set WP_DEBUG true --raw
docker-compose exec wpcli wp config set WP_DEBUG_LOG true --raw
docker-compose exec wpcli wp config set WP_DEBUG_DISPLAY false --raw
docker-compose exec wpcli wp config set SCRIPT_DEBUG true --raw

echo ""
echo "🎉 Development environment is ready!"
echo ""
echo "📋 Access Information:"
echo "   WordPress Site: http://localhost:8080"
echo "   Admin Panel: http://localhost:8080/wp-admin"
echo "   Username: admin"
echo "   Password: password"
echo "   phpMyAdmin: http://localhost:8081"
echo ""
echo "🔄 Your plugins are now hot-reloaded!"
echo "   Any changes you make to the plugin files will be immediately visible."
echo ""
echo "📝 Useful Commands:"
echo "   Start: docker-compose up -d"
echo "   Stop: docker-compose down"
echo "   Logs: docker-compose logs -f wordpress"
echo "   WP-CLI: docker-compose exec wpcli wp [command]"
echo "" 