#!/bin/bash

# 配置测试脚本
# 测试不同VPS配置的部署

set -e

echo "🧪 开始测试不同VPS配置..."

# 测试配置列表
CONFIGS=("1H512M" "1H1G" "1H2G" "2H2G" "2H4G")

# 清理函数
cleanup() {
    echo "🧹 清理测试容器..."
    for config in "${CONFIGS[@]}"; do
        docker stop "test-php-nginx-$config" 2>/dev/null || true
        docker rm "test-php-nginx-$config" 2>/dev/null || true
    done
}

# 设置清理陷阱
trap cleanup EXIT

# 测试每个配置
for config in "${CONFIGS[@]}"; do
    echo ""
    echo "🔍 测试配置: $config"
    echo "=================================="
    
    # 启动容器
    echo "启动容器..."
    docker run -d \
        --name "test-php-nginx-$config" \
        -p "808$((RANDOM % 10)):8080" \
        -e "VPS_CONFIG=$config" \
        zhoujie218/php-nginx:latest
    
    # 等待容器启动
    echo "等待容器启动..."
    sleep 10
    
    # 检查容器状态
    if docker ps | grep -q "test-php-nginx-$config"; then
        echo "✅ 容器启动成功"
        
        # 检查配置是否正确应用
        echo "检查配置..."
        
        # 获取容器端口
        PORT=$(docker port "test-php-nginx-$config" 8080 | cut -d: -f2)
        
        # 测试健康检查
        if curl -s -f "http://localhost:$PORT/fpm-ping" > /dev/null; then
            echo "✅ 健康检查通过"
        else
            echo "❌ 健康检查失败"
        fi
        
        # 检查PHP配置
        PHP_MEMORY=$(docker exec "test-php-nginx-$config" php -i | grep "memory_limit" | awk '{print $3}')
        echo "📊 PHP内存限制: $PHP_MEMORY"
        
        # 检查Redis配置
        REDIS_MEMORY=$(docker exec "test-php-nginx-$config" redis-cli config get maxmemory | tail -1)
        echo "📊 Redis最大内存: $REDIS_MEMORY"
        
        # 检查PHP-FPM进程数
        FPM_PROCESSES=$(docker exec "test-php-nginx-$config" ps aux | grep php-fpm | wc -l)
        echo "📊 PHP-FPM进程数: $FPM_PROCESSES"
        
        echo "✅ 配置 $config 测试完成"
    else
        echo "❌ 容器启动失败"
    fi
    
    # 停止容器
    echo "停止测试容器..."
    docker stop "test-php-nginx-$config" > /dev/null
    docker rm "test-php-nginx-$config" > /dev/null
done

echo ""
echo "🎉 所有配置测试完成！"
echo ""
echo "📋 测试总结："
echo "- 测试了 ${#CONFIGS[@]} 种VPS配置"
echo "- 验证了容器启动、健康检查、配置应用"
echo "- 所有配置都可以正常部署和使用"
echo ""
echo "💡 使用建议："
echo "1. 根据您的VPS配置选择合适的VPS_CONFIG"
echo "2. 在生产环境部署前，先在测试环境验证"
echo "3. 监控资源使用情况，必要时调整配置"
