#!/bin/sh

# 简化版启动脚本 - 用于调试
set -e

echo "🚀 简化版启动脚本开始..."

# 显示基本信息
echo "📋 基本信息:"
echo "  用户: $(whoami)"
echo "  目录: $(pwd)"
echo "  环境: VPS_CONFIG=${VPS_CONFIG:-1H512M}"

# 检查关键文件
echo "🔍 检查关键文件:"
echo "  supervisord: $(which supervisord || echo '未找到')"
echo "  envsubst: $(which envsubst || echo '未找到')"
echo "  curl: $(which curl || echo '未找到')"

# 检查模板文件
echo "📄 检查模板文件:"
ls -la /etc/php84/conf.d/custom.ini.template 2>/dev/null || echo "  PHP模板: 不存在"
ls -la /etc/redis.conf.template 2>/dev/null || echo "  Redis模板: 不存在"
ls -la /etc/php84/php-fpm.d/www.conf.template 2>/dev/null || echo "  PHP-FPM模板: 不存在"

# 检查supervisord配置
echo "📄 检查supervisord配置:"
ls -la /etc/supervisor/conf.d/supervisord.conf 2>/dev/null || echo "  supervisord配置: 不存在"

# 尝试直接启动supervisord（跳过配置生成）
echo "🚀 尝试直接启动supervisord..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
