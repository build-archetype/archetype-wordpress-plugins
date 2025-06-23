#!/bin/bash

# WordPress Development Helper Scripts

# Function to show help
show_help() {
    echo "🛠️  Archetype WordPress Development Helper"
    echo ""
    echo "Usage: ./dev-helpers.sh [command]"
    echo ""
    echo "Commands:"
    echo "  start         Start the development environment"
    echo "  stop          Stop the development environment"
    echo "  restart       Restart the development environment"
    echo "  logs          Show WordPress logs"
    echo "  shell         Open shell in WordPress container"
    echo "  db-shell      Open MySQL shell"
    echo "  wp [cmd]      Run WP-CLI commands"
    echo "  reset         Reset WordPress (fresh install)"
    echo "  backup        Create database backup"
    echo "  restore [file] Restore database from backup"
    echo "  plugin [name] Activate/deactivate plugin"
    echo "  debug-on      Enable debug mode"
    echo "  debug-off     Disable debug mode"
    echo "  status        Show environment status"
    echo ""
}

# Function to check if environment is running
check_running() {
    if ! docker-compose ps | grep -q "Up"; then
        echo "❌ Development environment is not running. Use './dev-helpers.sh start' first."
        exit 1
    fi
}

# Parse commands
case "$1" in
    start)
        echo "🚀 Starting development environment..."
        docker-compose up -d
        ;;
    stop)
        echo "🛑 Stopping development environment..."
        docker-compose down
        ;;
    restart)
        echo "🔄 Restarting development environment..."
        docker-compose restart
        ;;
    logs)
        echo "📋 Showing WordPress logs..."
        docker-compose logs -f wordpress
        ;;
    shell)
        check_running
        echo "🐚 Opening WordPress container shell..."
        docker-compose exec wordpress bash
        ;;
    db-shell)
        check_running
        echo "🗄️  Opening MySQL shell..."
        docker-compose exec db mysql -u wordpress -pwordpress wordpress
        ;;
    wp)
        check_running
        shift
        docker-compose exec wpcli wp "$@"
        ;;
    reset)
        echo "⚠️  This will reset your WordPress installation!"
        read -p "Are you sure? (y/N): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            docker-compose down -v
            docker-compose up -d
            echo "⏳ Waiting for WordPress..."
            sleep 10
            ./dev-setup.sh
        fi
        ;;
    backup)
        check_running
        backup_file="backup-$(date +%Y%m%d-%H%M%S).sql"
        echo "💾 Creating backup: $backup_file"
        docker-compose exec db mysqldump -u wordpress -pwordpress wordpress > "$backup_file"
        echo "✅ Backup created: $backup_file"
        ;;
    restore)
        check_running
        if [ -z "$2" ]; then
            echo "❌ Please specify backup file: ./dev-helpers.sh restore backup.sql"
            exit 1
        fi
        echo "📥 Restoring from: $2"
        docker-compose exec -T db mysql -u wordpress -pwordpress wordpress < "$2"
        echo "✅ Database restored"
        ;;
    plugin)
        check_running
        if [ -z "$2" ]; then
            echo "📦 Available plugins:"
            docker-compose exec wpcli wp plugin list
        else
            echo "🔌 Toggling plugin: $2"
            docker-compose exec wpcli wp plugin toggle "$2"
        fi
        ;;
    debug-on)
        check_running
        echo "🐛 Enabling debug mode..."
        docker-compose exec wpcli wp config set WP_DEBUG true --raw
        docker-compose exec wpcli wp config set WP_DEBUG_LOG true --raw
        docker-compose exec wpcli wp config set WP_DEBUG_DISPLAY true --raw
        ;;
    debug-off)
        check_running
        echo "🚫 Disabling debug mode..."
        docker-compose exec wpcli wp config set WP_DEBUG false --raw
        docker-compose exec wpcli wp config set WP_DEBUG_LOG false --raw
        docker-compose exec wpcli wp config set WP_DEBUG_DISPLAY false --raw
        ;;
    status)
        echo "📊 Environment Status:"
        docker-compose ps
        echo ""
        if docker-compose ps | grep -q "Up"; then
            echo "🌐 WordPress: http://localhost:8080"
            echo "🔧 Admin: http://localhost:8080/wp-admin (admin/password)"
            echo "🗄️  phpMyAdmin: http://localhost:8082"
        fi
        ;;
    *)
        show_help
        ;;
esac 