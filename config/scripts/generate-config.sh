#!/bin/sh

# Dynamic configuration generator for PHP-Nginx container
# This script generates configuration files based on environment variables or preset profiles

set -e

# Configuration directories
TEMPLATES_DIR="/etc/nginx-config-templates"
CONFIG_DIR="/etc"
PRESETS_DIR="/etc/nginx-config-presets"

# Default configuration values
DEFAULT_PROFILE="1h512m"

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
    
    # Use envsubst to replace environment variables
    envsubst < "$template_file" > "$output_file"
    log "Generated configuration: $output_file"
}

# Function to load preset configuration
load_preset() {
    local profile="$1"
    local preset_dir="$PRESETS_DIR/$profile"
    
    if [ -d "$preset_dir" ]; then
        log "Loading preset configuration: $profile"
        
        # Set environment variables from preset
        if [ -f "$preset_dir/env" ]; then
            . "$preset_dir/env"
            log "Loaded environment variables from preset"
        fi
        
        # Copy preset files if they exist
        for config_file in php.ini fpm-pool.conf nginx.conf redis.conf; do
            if [ -f "$preset_dir/$config_file" ]; then
                cp "$preset_dir/$config_file" "$CONFIG_DIR/"
                log "Copied preset file: $config_file"
            fi
        done
    else
        log "WARNING: Preset directory not found: $preset_dir"
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
    
    # Check if custom configuration files are mounted (highest priority)
    if [ -f "$CONFIG_DIR/php84/conf.d/custom.ini" ] && [ ! -L "$CONFIG_DIR/php84/conf.d/custom.ini" ]; then
        log "Using mounted PHP configuration file"
    elif [ -f "$CONFIG_DIR/php84/php-fpm.d/www.conf" ] && [ ! -L "$CONFIG_DIR/php84/php-fpm.d/www.conf" ]; then
        log "Using mounted PHP-FPM configuration file"
    elif [ -f "$CONFIG_DIR/nginx/nginx.conf" ] && [ ! -L "$CONFIG_DIR/nginx/nginx.conf" ]; then
        log "Using mounted Nginx configuration file"
    elif [ -f "$CONFIG_DIR/redis.conf" ] && [ ! -L "$CONFIG_DIR/redis.conf" ]; then
        log "Using mounted Redis configuration file"
    else
        # No mounted configurations, proceed with dynamic generation
        
        # Load preset configuration if specified
        local profile="${RESOURCE_PROFILE:-$DEFAULT_PROFILE}"
        load_preset "$profile"
        
        # Generate configuration from templates
        generate_from_templates
        
        # Validate generated configuration
        validate_config
    fi
    
    log "Configuration generation completed"
}

# Run main function
main "$@"
