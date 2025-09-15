#!/bin/bash

# 测试环境变量配置脚本

echo "=== 测试环境变量配置 ==="

# 设置测试环境变量
export PHP_MEMORY_LIMIT=128M
export OPCACHE_MEMORY_CONSUMPTION=64
export PHP_FPM_MAX_CHILDREN=80
export REDIS_MAXMEMORY=128mb
export NGINX_WORKER_PROCESSES=2

echo "测试环境变量:"
echo "PHP_MEMORY_LIMIT=$PHP_MEMORY_LIMIT"
echo "OPCACHE_MEMORY_CONSUMPTION=$OPCACHE_MEMORY_CONSUMPTION"
echo "PHP_FPM_MAX_CHILDREN=$PHP_FPM_MAX_CHILDREN"
echo "REDIS_MAXMEMORY=$REDIS_MAXMEMORY"
echo "NGINX_WORKER_PROCESSES=$NGINX_WORKER_PROCESSES"

# 测试envsubst命令
echo ""
echo "=== 测试envsubst命令 ==="

# 创建测试模板
echo "memory_limit = \${PHP_MEMORY_LIMIT:-64M}" > test.template
echo "opcache.memory_consumption=\${OPCACHE_MEMORY_CONSUMPTION:-32}" >> test.template

# 使用envsubst处理模板
envsubst < test.template > test.output

echo "模板内容:"
cat test.template
echo ""
echo "处理后的内容:"
cat test.output

# 清理测试文件
rm test.template test.output

echo ""
echo "=== 测试完成 ==="
