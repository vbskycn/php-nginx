#!/bin/bash

# 调试容器脚本
# 用于获取容器启动失败的详细信息

echo "🔍 开始调试容器..."

# 设置环境变量
export IMAGE_NAME=${IMAGE_NAME:-zhoujie218/php-nginx}
export IMAGE_TAG=${IMAGE_TAG:-latest}

echo "📋 环境变量:"
echo "  IMAGE_NAME: $IMAGE_NAME"
echo "  IMAGE_TAG: $IMAGE_TAG"

# 清理旧的容器
echo "🧹 清理旧的容器..."
docker compose -f docker-compose.test.yml down 2>/dev/null || true

# 启动容器
echo "🚀 启动容器..."
docker compose -f docker-compose.test.yml up -d app

# 等待一下让容器启动
sleep 5

# 检查容器状态
echo "📊 容器状态:"
docker compose -f docker-compose.test.yml ps

# 获取容器日志
echo "📄 容器日志:"
docker compose -f docker-compose.test.yml logs app

# 如果容器还在运行，进入容器调试
if docker compose -f docker-compose.test.yml ps | grep -q "Up"; then
    echo "✅ 容器正在运行"
else
    echo "❌ 容器已退出，尝试进入容器调试..."
    # 尝试启动一个临时容器进行调试
    docker run --rm -it --entrypoint sh $IMAGE_NAME:$IMAGE_TAG
fi

# 清理
echo "🧹 清理容器..."
docker compose -f docker-compose.test.yml down
