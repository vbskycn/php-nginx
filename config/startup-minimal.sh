#!/bin/sh

# 最小化启动脚本 - 用于测试
echo "🚀 最小化启动脚本开始..."

# 设置默认环境变量
export PHP_MEMORY_LIMIT=${PHP_MEMORY_LIMIT:-"64M"}
export OPCACHE_MEMORY=${OPCACHE_MEMORY:-"32"}
export OPCACHE_INTERNED_STRINGS=${OPCACHE_INTERNED_STRINGS:-"4"}
export OPCACHE_MAX_FILES=${OPCACHE_MAX_FILES:-"2000"}
export REDIS_MAXMEMORY=${REDIS_MAXMEMORY:-"64mb"}
export PHP_FPM_PM=${PHP_FPM_PM:-"ondemand"}
export PHP_FPM_MAX_CHILDREN=${PHP_FPM_MAX_CHILDREN:-"20"}
export PHP_FPM_START_SERVERS=${PHP_FPM_START_SERVERS:-"3"}
export PHP_FPM_MIN_SPARE=${PHP_FPM_MIN_SPARE:-"1"}
export PHP_FPM_MAX_SPARE=${PHP_FPM_MAX_SPARE:-"10"}
export PHP_FPM_IDLE_TIMEOUT=${PHP_FPM_IDLE_TIMEOUT:-"10s"}
export PHP_FPM_MAX_REQUESTS=${PHP_FPM_MAX_REQUESTS:-"1000"}

echo "📋 环境变量已设置"

# 生成配置文件
echo "🔧 生成配置文件..."
envsubst < /etc/php84/conf.d/custom.ini.template > /etc/php84/conf.d/custom.ini
envsubst < /etc/redis.conf.template > /etc/redis.conf
envsubst < /etc/php84/php-fpm.d/www.conf.template > /etc/php84/php-fpm.d/www.conf
echo "✅ 配置文件已生成"

# 启动supervisord
echo "🚀 启动supervisord..."

# 等待服务启动
sleep 5

# 检查服务状态
echo "📊 检查服务状态..."
supervisorctl status || echo "supervisorctl状态检查失败"

# 检查端口监听
echo "📊 检查端口监听..."
netstat -tlnp | grep :8080 || echo "端口8080未监听"

# 检查PHP-FPM socket
echo "📊 检查PHP-FPM socket..."
ls -la /run/php-fpm.sock || echo "PHP-FPM socket不存在"

# 测试健康检查端点
echo "📊 测试健康检查端点..."
curl -f http://localhost:8080/ && echo "健康检查通过" || echo "健康检查失败"

# 启动supervisord
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
