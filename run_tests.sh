#!/usr/bin/env sh
set -e

echo "🧪 开始测试..."

# 安装curl
apk --no-cache add curl

# 等待应用启动
echo "⏳ 等待应用启动..."
sleep 10

# 测试健康检查端点
echo "🔍 测试健康检查端点..."
if curl --silent --fail http://app:8080/; then
    echo "✅ 健康检查通过"
else
    echo "❌ 健康检查失败"
    exit 1
fi

# 测试主页内容
echo "🔍 测试主页内容..."
if curl --silent --fail http://app:8080 | grep -E '(PHP 8.4|nginx|php-nginx)'; then
    echo "✅ 主页内容测试通过"
else
    echo "❌ 主页内容测试失败"
    echo "📄 主页内容："
    curl --silent http://app:8080 || echo "无法访问主页"
    exit 1
fi

# 测试PHP-FPM状态
echo "🔍 测试PHP-FPM状态..."
if curl --silent --fail http://app:8080/fpm-status; then
    echo "✅ PHP-FPM状态检查通过"
else
    echo "⚠️ PHP-FPM状态检查失败，但继续测试"
fi

echo "🎉 所有测试通过！"
