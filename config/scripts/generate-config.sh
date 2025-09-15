#!/bin/sh

# Dynamic configuration generator for PHP-Nginx container
# This script generates configuration files based on environment variables or preset profiles

set -e

# Configuration directories
TEMPLATES_DIR="/etc/nginx-config-templates"
CONFIG_DIR="/etc"

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >&2
}

# Function to substitute environment variables in template
substitute_template() {
    local template_file="$1"
    local output_file="$2"
    
    if [ ! -f "$template_file" ]; then
        log "ERROR: Template file not found: $template_file"
        return 1
    fi
    
    log "Processing template: $template_file -> $output_file"
    
    # Debug: show environment variables before substitution
    log "DEBUG: REDIS_MAXMEMORY=$REDIS_MAXMEMORY"
    log "DEBUG: PHP_MEMORY_LIMIT=$PHP_MEMORY_LIMIT"
    
    # Use envsubst to replace environment variables
    envsubst < "$template_file" > "$output_file"
    
    # Debug: show generated content
    log "Generated configuration: $output_file"
    if [ -f "$output_file" ]; then
        log "File size: $(wc -c < "$output_file") bytes"
        log "First few lines:"
        head -5 "$output_file" | while read line; do
            log "  $line"
        done
    fi
}


# Function to generate configuration from templates
generate_from_templates() {
    log "Generating configuration from templates"
    
    # Generate PHP configuration
    substitute_template "$TEMPLATES_DIR/php.ini.template" "$CONFIG_DIR/php84/conf.d/custom.ini"
    
    # Generate PHP-FPM configuration
    substitute_template "$TEMPLATES_DIR/fpm-pool.conf.template" "$CONFIG_DIR/php84/php-fpm.d/www.conf"
    
    # Generate Nginx configuration
    substitute_template "$TEMPLATES_DIR/nginx.conf.template" "$CONFIG_DIR/nginx/nginx.conf"
    
    # Generate Redis configuration
    substitute_template "$TEMPLATES_DIR/redis.conf.template" "$CONFIG_DIR/redis.conf"
}

# Function to validate configuration
validate_config() {
    log "Validating generated configurations"
    
    # Test PHP configuration
    if ! php -m > /dev/null 2>&1; then
        log "WARNING: PHP configuration validation failed"
    fi
    
    # Test Nginx configuration
    if ! nginx -t > /dev/null 2>&1; then
        log "WARNING: Nginx configuration validation failed"
    fi
    
    # Test Redis configuration
    if ! redis-server --test-memory 1 > /dev/null 2>&1; then
        log "WARNING: Redis configuration validation failed"
    fi
}

# Main execution
main() {
    log "Starting dynamic configuration generation"
    
    # Generate configuration from environment variables
    log "Using environment variables for configuration"
    
    # Debug: show environment variables
    log "DEBUG: Environment variables:"
    log "  PHP_MEMORY_LIMIT=$PHP_MEMORY_LIMIT"
    log "  OPCACHE_MEMORY=$OPCACHE_MEMORY"
    log "  REDIS_MAXMEMORY=$REDIS_MAXMEMORY"
    log "  FPM_PM_MODE=$FPM_PM_MODE"
    log "  FPM_MAX_CHILDREN=$FPM_MAX_CHILDREN"
    
    # Generate configuration from templates
    generate_from_templates
    
    # Validate generated configuration
    validate_config
    
    log "Configuration generation completed"
}

# Run main function
main "$@"
