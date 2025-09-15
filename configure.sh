#!/bin/sh

# 环境变量配置脚本
# 用于在容器启动时根据环境变量生成配置文件

set -e

echo "开始配置环境变量..."

# 检查模板文件是否存在
if [ ! -f "/etc/php84/conf.d/custom.ini.template" ]; then
    echo "错误: PHP配置模板文件不存在"
    exit 1
fi

if [ ! -f "/etc/php84/php-fpm.d/www.conf.template" ]; then
    echo "错误: PHP-FPM配置模板文件不存在"
    exit 1
fi

if [ ! -f "/etc/redis.conf.template" ]; then
    echo "错误: Redis配置模板文件不存在"
    exit 1
fi

if [ ! -f "/etc/nginx/nginx.conf.template" ]; then
    echo "错误: Nginx配置模板文件不存在"
    exit 1
fi

# 配置PHP
echo "配置PHP..."
envsubst < /etc/php84/conf.d/custom.ini.template > /etc/php84/conf.d/custom.ini
echo "PHP配置完成"

# 配置PHP-FPM
echo "配置PHP-FPM..."
envsubst < /etc/php84/php-fpm.d/www.conf.template > /etc/php84/php-fpm.d/www.conf
echo "PHP-FPM配置完成"

# 配置Redis
echo "配置Redis..."
envsubst < /etc/redis.conf.template > /etc/redis.conf
echo "Redis配置完成"

# 配置Nginx
echo "配置Nginx..."
envsubst < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
echo "Nginx配置完成"

echo "环境变量配置完成！"

# 显示当前配置摘要
echo "=== 配置摘要 ==="
echo "PHP内存限制: ${PHP_MEMORY_LIMIT:-64M}"
echo "OPcache内存: ${OPCACHE_MEMORY_CONSUMPTION:-32}MB"
echo "PHP-FPM最大进程: ${PHP_FPM_MAX_CHILDREN:-50}"
echo "Redis最大内存: ${REDIS_MAXMEMORY:-64mb}"
echo "Nginx工作进程: ${NGINX_WORKER_PROCESSES:-auto}"
echo "Nginx工作连接: ${NGINX_WORKER_CONNECTIONS:-1024}"
echo "================"
