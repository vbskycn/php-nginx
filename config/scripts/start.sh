#!/bin/sh

# Main startup script for PHP-Nginx container
# This script handles dynamic configuration generation and service startup

set -e

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >&2
}

# Function to wait for services to be ready
wait_for_services() {
    local max_attempts=30
    local attempt=1
    
    log "Waiting for services to be ready..."
    
    while [ $attempt -le $max_attempts ]; do
        if curl --silent --fail http://127.0.0.1:8080/fpm-ping > /dev/null 2>&1; then
            log "Services are ready"
            return 0
        fi
        
        log "Attempt $attempt/$max_attempts: Services not ready yet, waiting..."
        sleep 2
        attempt=$((attempt + 1))
    done
    
    log "ERROR: Services failed to start within expected time"
    return 1
}

# Main execution
main() {
    log "Starting PHP-Nginx container with dynamic configuration"
    
    # Generate dynamic configuration
    if [ -x "/etc/nginx-config-scripts/generate-config.sh" ]; then
        log "Generating dynamic configuration..."
        /etc/nginx-config-scripts/generate-config.sh
    else
        log "WARNING: Configuration generation script not found, using default configuration"
    fi
    
    # Start supervisord in the background
    log "Starting supervisord..."
    /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf &
    
    # Wait for services to be ready
    wait_for_services
    
    # Keep the container running and monitor services
    log "Container started successfully. Monitoring services..."
    
    # Monitor supervisord process
    while true; do
        if ! pgrep supervisord > /dev/null; then
            log "ERROR: Supervisord process died, exiting container"
            exit 1
        fi
        sleep 10
    done
}

# Handle signals for graceful shutdown
trap 'log "Received shutdown signal, stopping services..."; killall supervisord; exit 0' TERM INT

# Run main function
main "$@"
