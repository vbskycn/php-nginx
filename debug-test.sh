#!/bin/bash

# 调试测试脚本

echo "=== 调试容器状态 ==="

# 检查容器是否正在运行
echo "检查容器状态..."
docker ps -a | grep php-nginx

echo ""
echo "=== 检查容器日志 ==="
docker logs php-nginx-app-1

echo ""
echo "=== 测试网络连接 ==="
# 进入测试容器并手动测试
docker run --rm --network php-nginx_default alpine:3.21 sh -c "
echo '安装curl...'
apk --no-cache add curl
echo '测试网络连接...'
ping -c 3 app
echo '测试HTTP连接...'
curl -v http://app:8080/
echo '测试PHP页面...'
curl -v http://app:8080/php.php
echo '检查PHP版本...'
curl -s http://app:8080/php.php | grep -i 'php 8.4' || echo '未找到PHP 8.4字符串'
"
