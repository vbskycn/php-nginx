ARG ALPINE_VERSION=3.21
FROM alpine:${ALPINE_VERSION}
LABEL Maintainer="Tim de Pater <code@trafex.nl>"
LABEL Description="Lightweight container with Nginx 1.26 & PHP 8.4 based on Alpine Linux."
# Setup document root
WORKDIR /var/www/html

# Install packages and create symlink in one layer for better caching
RUN apk add --no-cache \
  curl \
  nginx \
  php84 \
  php84-ctype \
  php84-curl \
  php84-dom \
  php84-fileinfo \
  php84-fpm \
  php84-gd \
  php84-intl \
  php84-mbstring \
  php84-mysqli \
  php84-opcache \
  php84-openssl \
  php84-phar \
  php84-redis \
  php84-session \
  php84-tokenizer \
  php84-xml \
  php84-xmlreader \
  php84-xmlwriter \
  redis \
  supervisor \
  gettext \
  && ln -s /usr/bin/php84 /usr/bin/php

# Configure all services for better caching
ENV PHP_INI_DIR=/etc/php84

# Copy configuration templates and presets
COPY config/templates /etc/nginx-config-templates/
COPY config/presets /etc/nginx-config-presets/
COPY config/scripts /etc/nginx-config-scripts/

# Copy nginx configuration (default/fallback)
COPY config/nginx.conf /etc/nginx/nginx.conf
COPY config/conf.d /etc/nginx/conf.d/

# Copy PHP-FPM configuration (default/fallback)
COPY config/fpm-pool.conf ${PHP_INI_DIR}/php-fpm.d/www.conf
COPY config/php.ini ${PHP_INI_DIR}/conf.d/custom.ini

# Copy Redis configuration (default/fallback)
COPY config/redis.conf /etc/redis.conf

# Copy supervisord configuration
COPY config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
# Create symlink for supervisorctl compatibility
RUN ln -sf /etc/supervisor/conf.d/supervisord.conf /etc/supervisord.conf

# Make sure files/folders needed by the processes are accessable when they run under the nobody user
RUN chown -R nobody:nobody /var/www/html /run /var/lib/nginx /var/log/nginx && \
    mkdir -p /run/supervisor && \
    chown -R nobody:nobody /run/supervisor && \
    chown -R nobody:nobody /etc/nginx-config-templates /etc/nginx-config-presets /etc/nginx-config-scripts

# Make configuration generation script executable (after changing ownership)
RUN chmod +x /etc/nginx-config-scripts/generate-config.sh /etc/nginx-config-scripts/start.sh

# Switch to use a non-root user from here on
USER nobody

# Add application
COPY --chown=nobody src/ /var/www/html/

# Expose the port nginx is reachable on
EXPOSE 8080

# Use custom startup script for dynamic configuration
CMD ["/etc/nginx-config-scripts/start.sh"]

# Configure a healthcheck to validate that everything is up&running
HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1:8080/fpm-ping || exit 1
