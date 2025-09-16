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

# Copy nginx configuration templates
COPY config/nginx.conf.template /etc/nginx/nginx.conf.template
COPY config/conf.d /etc/nginx/conf.d/

# Copy PHP-FPM configuration templates
COPY config/fpm-pool.conf.template ${PHP_INI_DIR}/php-fpm.d/www.conf.template
COPY config/php.ini.template ${PHP_INI_DIR}/conf.d/custom.ini.template

# Copy Redis configuration template
COPY config/redis.conf.template /etc/redis.conf.template

# Copy configuration script
COPY configure.sh /usr/local/bin/configure.sh
RUN chmod +x /usr/local/bin/configure.sh

# 确保配置文件目录对nobody用户可写
RUN chown -R nobody:nobody /etc/php84/conf.d /etc/php84/php-fpm.d /etc/redis.conf /etc/nginx/nginx.conf /etc/nginx/conf.d

# Copy supervisord configuration
COPY config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
# Create symlink for supervisorctl compatibility
RUN ln -sf /etc/supervisor/conf.d/supervisord.conf /etc/supervisord.conf

# Make sure files/folders needed by the processes are accessable when they run under the nobody user
RUN chown -R nobody:nobody /var/www/html /run /var/lib/nginx /var/log/nginx && \
    mkdir -p /run/supervisor && \
    chown -R nobody:nobody /run/supervisor

# Switch to use a non-root user from here on
USER nobody

# Add application
COPY --chown=nobody src/ /var/www/html/

# Expose the port nginx is reachable on
EXPOSE 8080

# Start with configuration script, which will then start supervisord
CMD ["/usr/local/bin/configure.sh"]

# Configure a healthcheck to validate that everything is up&running
HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1:8080/fpm-ping || exit 1
