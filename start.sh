#!/bin/sh

# 主启动脚本
# 确保配置完成后再启动服务

echo "=== 容器启动脚本 ==="

# 等待配置脚本完成
echo "等待配置脚本完成..."
while [ ! -f /tmp/configure_complete ]; do
    sleep 1
done
echo "配置脚本已完成"

# 启动服务
echo "启动服务..."

# 启动PHP-FPM
echo "启动PHP-FPM..."
php-fpm84 -F &
PHP_FPM_PID=$!

# 启动Redis
echo "启动Redis..."
redis-server /etc/redis.conf &
REDIS_PID=$!

# 等待服务启动
sleep 2

# 启动Nginx
echo "启动Nginx..."
nginx -g 'daemon off;' &
NGINX_PID=$!

echo "所有服务已启动"
echo "PHP-FPM PID: $PHP_FPM_PID"
echo "Redis PID: $REDIS_PID"
echo "Nginx PID: $NGINX_PID"

# 等待所有进程
wait
