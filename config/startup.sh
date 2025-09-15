#!/bin/sh

# 配置生成脚本 - 根据环境变量生成配置文件
# 支持多种VPS配置预设

# set -e  # 暂时禁用，避免调试命令导致脚本退出

# 默认配置（1H512M）
DEFAULT_CONFIG="1H512M"

# 获取VPS配置类型
VPS_CONFIG=${VPS_CONFIG:-$DEFAULT_CONFIG}

echo "🚀 启动配置生成器 - VPS配置: $VPS_CONFIG"

# 基本调试信息
echo "🔍 启动信息:"
echo "  环境变量: VPS_CONFIG=$VPS_CONFIG"

# 配置预设
case "$VPS_CONFIG" in
    "1H512M")
        echo "📋 使用1H512M配置预设"
        export PHP_MEMORY_LIMIT="64M"
        export OPCACHE_MEMORY="32"
        export OPCACHE_INTERNED_STRINGS="4"
        export OPCACHE_MAX_FILES="2000"
        export REDIS_MAXMEMORY="64mb"
        export PHP_FPM_PM="ondemand"
        export PHP_FPM_MAX_CHILDREN="20"
        export PHP_FPM_START_SERVERS="3"
        export PHP_FPM_MIN_SPARE="1"
        export PHP_FPM_MAX_SPARE="10"
        export PHP_FPM_IDLE_TIMEOUT="10s"
        export PHP_FPM_MAX_REQUESTS="1000"
        ;;
    "1H1G")
        echo "📋 使用1H1G配置预设"
        export PHP_MEMORY_LIMIT="128M"
        export OPCACHE_MEMORY="64"
        export OPCACHE_INTERNED_STRINGS="8"
        export OPCACHE_MAX_FILES="4000"
        export REDIS_MAXMEMORY="128mb"
        export PHP_FPM_PM="dynamic"
        export PHP_FPM_MAX_CHILDREN="40"
        export PHP_FPM_START_SERVERS="4"
        export PHP_FPM_MIN_SPARE="2"
        export PHP_FPM_MAX_SPARE="20"
        export PHP_FPM_IDLE_TIMEOUT="10s"
        export PHP_FPM_MAX_REQUESTS="1000"
        ;;
    "1H2G")
        echo "📋 使用1H2G配置预设"
        export PHP_MEMORY_LIMIT="256M"
        export OPCACHE_MEMORY="128"
        export OPCACHE_INTERNED_STRINGS="16"
        export OPCACHE_MAX_FILES="8000"
        export REDIS_MAXMEMORY="256mb"
        export PHP_FPM_PM="dynamic"
        export PHP_FPM_MAX_CHILDREN="60"
        export PHP_FPM_START_SERVERS="8"
        export PHP_FPM_MIN_SPARE="4"
        export PHP_FPM_MAX_SPARE="40"
        export PHP_FPM_IDLE_TIMEOUT="10s"
        export PHP_FPM_MAX_REQUESTS="1000"
        ;;
    "2H2G")
        echo "📋 使用2H2G配置预设"
        export PHP_MEMORY_LIMIT="256M"
        export OPCACHE_MEMORY="128"
        export OPCACHE_INTERNED_STRINGS="16"
        export OPCACHE_MAX_FILES="8000"
        export REDIS_MAXMEMORY="256mb"
        export PHP_FPM_PM="static"
        export PHP_FPM_MAX_CHILDREN="100"
        export PHP_FPM_START_SERVERS="8"
        export PHP_FPM_MIN_SPARE="4"
        export PHP_FPM_MAX_SPARE="40"
        export PHP_FPM_IDLE_TIMEOUT="10s"
        export PHP_FPM_MAX_REQUESTS="1000"
        ;;
    "2H4G")
        echo "📋 使用2H4G配置预设"
        export PHP_MEMORY_LIMIT="512M"
        export OPCACHE_MEMORY="256"
        export OPCACHE_INTERNED_STRINGS="32"
        export OPCACHE_MAX_FILES="16000"
        export REDIS_MAXMEMORY="512mb"
        export PHP_FPM_PM="static"
        export PHP_FPM_MAX_CHILDREN="200"
        export PHP_FPM_START_SERVERS="16"
        export PHP_FPM_MIN_SPARE="8"
        export PHP_FPM_MAX_SPARE="80"
        export PHP_FPM_IDLE_TIMEOUT="10s"
        export PHP_FPM_MAX_REQUESTS="1000"
        ;;
    *)
        echo "⚠️  未知的VPS配置: $VPS_CONFIG，使用默认配置"
        export PHP_MEMORY_LIMIT=${PHP_MEMORY_LIMIT:-"64M"}
        export OPCACHE_MEMORY=${OPCACHE_MEMORY:-"32"}
        export OPCACHE_INTERNED_STRINGS=${OPCACHE_INTERNED_STRINGS:-"4"}
        export OPCACHE_MAX_FILES=${OPCACHE_MAX_FILES:-"2000"}
        export REDIS_MAXMEMORY=${REDIS_MAXMEMORY:-"64mb"}
        export PHP_FPM_PM=${PHP_FPM_PM:-"ondemand"}
        export PHP_FPM_MAX_CHILDREN=${PHP_FPM_MAX_CHILDREN:-"20"}
        export PHP_FPM_START_SERVERS=${PHP_FPM_START_SERVERS:-"2"}
        export PHP_FPM_MIN_SPARE=${PHP_FPM_MIN_SPARE:-"1"}
        export PHP_FPM_MAX_SPARE=${PHP_FPM_MAX_SPARE:-"10"}
        export PHP_FPM_IDLE_TIMEOUT=${PHP_FPM_IDLE_TIMEOUT:-"10s"}
        export PHP_FPM_MAX_REQUESTS=${PHP_FPM_MAX_REQUESTS:-"1000"}
        ;;
esac

# 显示当前配置
echo "📊 当前配置参数:"
echo "  PHP内存限制: $PHP_MEMORY_LIMIT"
echo "  OPcache内存: ${OPCACHE_MEMORY}MB"
echo "  Redis最大内存: $REDIS_MAXMEMORY"
echo "  PHP-FPM进程管理: $PHP_FPM_PM"
echo "  PHP-FPM最大进程: $PHP_FPM_MAX_CHILDREN"

# 生成配置文件
echo "🔧 生成配置文件..."

# 生成PHP配置
echo "  📝 生成PHP配置..."
envsubst < /etc/php84/conf.d/custom.ini.template > /etc/php84/conf.d/custom.ini
echo "  ✅ PHP配置已生成"

# 生成Redis配置
echo "  📝 生成Redis配置..."
envsubst < /etc/redis.conf.template > /etc/redis.conf
echo "  ✅ Redis配置已生成"

# 生成PHP-FPM配置
echo "  📝 生成PHP-FPM配置..."
envsubst < /etc/php84/php-fpm.d/www.conf.template > /etc/php84/php-fpm.d/www.conf
echo "  ✅ PHP-FPM配置已生成"

echo "🎉 配置生成完成！"

# 重新启用错误检查
set -e

# 启动supervisord
echo "🚀 启动服务..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
