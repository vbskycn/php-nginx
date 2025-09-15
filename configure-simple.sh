#!/bin/sh

# 简化的配置脚本，用于测试

set -e

echo "开始配置环境变量..."

# 设置默认环境变量
export PHP_MEMORY_LIMIT=${PHP_MEMORY_LIMIT:-64M}
export OPCACHE_MEMORY_CONSUMPTION=${OPCACHE_MEMORY_CONSUMPTION:-32}
export OPCACHE_INTERNED_STRINGS_BUFFER=${OPCACHE_INTERNED_STRINGS_BUFFER:-4}
export OPCACHE_MAX_ACCELERATED_FILES=${OPCACHE_MAX_ACCELERATED_FILES:-2000}
export OPCACHE_REVALIDATE_FREQ=${OPCACHE_REVALIDATE_FREQ:-60}
export PHP_FPM_MAX_CHILDREN=${PHP_FPM_MAX_CHILDREN:-50}
export PHP_FPM_START_SERVERS=${PHP_FPM_START_SERVERS:-2}
export PHP_FPM_MIN_SPARE_SERVERS=${PHP_FPM_MIN_SPARE_SERVERS:-1}
export PHP_FPM_MAX_SPARE_SERVERS=${PHP_FPM_MAX_SPARE_SERVERS:-10}
export PHP_FPM_PROCESS_IDLE_TIMEOUT=${PHP_FPM_PROCESS_IDLE_TIMEOUT:-10s}
export PHP_FPM_MAX_REQUESTS=${PHP_FPM_MAX_REQUESTS:-1000}
export REDIS_MAXMEMORY=${REDIS_MAXMEMORY:-64mb}
export REDIS_MAXMEMORY_POLICY=${REDIS_MAXMEMORY_POLICY:-allkeys-lru}
export NGINX_WORKER_PROCESSES=${NGINX_WORKER_PROCESSES:-auto}
export NGINX_WORKER_CONNECTIONS=${NGINX_WORKER_CONNECTIONS:-1024}

echo "环境变量设置完成:"
echo "PHP_MEMORY_LIMIT=$PHP_MEMORY_LIMIT"
echo "OPCACHE_MEMORY_CONSUMPTION=$OPCACHE_MEMORY_CONSUMPTION"
echo "PHP_FPM_MAX_CHILDREN=$PHP_FPM_MAX_CHILDREN"
echo "REDIS_MAXMEMORY=$REDIS_MAXMEMORY"
echo "NGINX_WORKER_PROCESSES=$NGINX_WORKER_PROCESSES"
echo "NGINX_WORKER_CONNECTIONS=$NGINX_WORKER_CONNECTIONS"

# 检查模板文件
echo "检查模板文件..."
ls -la /etc/php84/conf.d/custom.ini.template
ls -la /etc/php84/php-fpm.d/www.conf.template
ls -la /etc/redis.conf.template
ls -la /etc/nginx/nginx.conf.template

# 检查目标文件权限
echo "检查目标文件权限..."
ls -la /etc/php84/conf.d/
ls -la /etc/php84/php-fpm.d/
ls -la /etc/redis.conf
ls -la /etc/nginx/nginx.conf

# 生成配置文件
echo "生成配置文件..."
envsubst < /etc/php84/conf.d/custom.ini.template > /etc/php84/conf.d/custom.ini
envsubst < /etc/php84/php-fpm.d/www.conf.template > /etc/php84/php-fpm.d/www.conf
envsubst < /etc/redis.conf.template > /etc/redis.conf
envsubst < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

echo "配置文件生成完成！"

# 显示生成的配置
echo "=== 生成的PHP配置 ==="
cat /etc/php84/conf.d/custom.ini

echo "=== 生成的PHP-FPM配置 ==="
head -20 /etc/php84/php-fpm.d/www.conf

echo "=== 生成的Redis配置 ==="
head -10 /etc/redis.conf

echo "=== 生成的Nginx配置 ==="
head -10 /etc/nginx/nginx.conf

echo "配置完成！"
