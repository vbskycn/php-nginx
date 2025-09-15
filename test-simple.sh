#!/bin/bash

# 简单测试脚本 - 只测试配置生成

echo "=== 简单配置测试 ==="

# 设置测试环境变量
export PHP_MEMORY_LIMIT=128M
export OPCACHE_MEMORY_CONSUMPTION=64
export PHP_FPM_MAX_CHILDREN=80
export REDIS_MAXMEMORY=128mb
export NGINX_WORKER_PROCESSES=2

echo "测试环境变量设置完成"

# 创建测试模板
echo "memory_limit = \${PHP_MEMORY_LIMIT}" > test-php.ini.template
echo "opcache.memory_consumption=\${OPCACHE_MEMORY_CONSUMPTION}" >> test-php.ini.template

echo "pm.max_children = \${PHP_FPM_MAX_CHILDREN}" > test-fpm.conf.template
echo "pm.process_idle_timeout = \${PHP_FPM_PROCESS_IDLE_TIMEOUT}" >> test-fpm.conf.template

echo "maxmemory \${REDIS_MAXMEMORY}" > test-redis.conf.template

echo "worker_processes \${NGINX_WORKER_PROCESSES};" > test-nginx.conf.template

# 测试envsubst
echo ""
echo "=== 测试envsubst处理 ==="

echo "PHP配置:"
envsubst < test-php.ini.template

echo ""
echo "PHP-FPM配置:"
envsubst < test-fpm.conf.template

echo ""
echo "Redis配置:"
envsubst < test-redis.conf.template

echo ""
echo "Nginx配置:"
envsubst < test-nginx.conf.template

# 清理
rm test-*.template

echo ""
echo "=== 测试完成 ==="
