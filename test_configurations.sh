#!/bin/bash

# 多资源配置测试脚本
# 测试不同资源配置的兼容性和正确性

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 测试配置
PROFILES=("1h512m" "1h1g" "1h2g" "2h2g" "2h4g")
TEST_PORTS=(8090 8091 8092 8093 8094)

# 函数：打印带颜色的消息
print_message() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# 函数：检查容器是否运行
check_container_running() {
    local container_name=$1
    if docker ps | grep -q "$container_name"; then
        return 0
    else
        return 1
    fi
}

# 函数：检查服务健康状态
check_service_health() {
    local port=$1
    local max_attempts=30
    local attempt=1
    
    print_message $BLUE "检查服务健康状态 (端口: $port)..."
    
    while [ $attempt -le $max_attempts ]; do
        if curl --silent --fail "http://localhost:$port/fpm-ping" > /dev/null 2>&1; then
            print_message $GREEN "✓ 服务健康检查通过 (端口: $port)"
            return 0
        fi
        
        print_message $YELLOW "尝试 $attempt/$max_attempts: 等待服务启动..."
        sleep 2
        attempt=$((attempt + 1))
    done
    
    print_message $RED "✗ 服务健康检查失败 (端口: $port)"
    return 1
}

# 函数：测试配置生成
test_config_generation() {
    local profile=$1
    local container_name="test-config-$profile"
    
    print_message $BLUE "测试配置生成: $profile"
    
    # 启动测试容器
    docker run -d \
        --name "$container_name" \
        -e RESOURCE_PROFILE="$profile" \
        -p "${TEST_PORTS[0]}:8080" \
        zhoujie218/php-nginx:latest > /dev/null 2>&1
    
    # 等待容器启动
    sleep 5
    
    # 检查容器是否运行
    if check_container_running "$container_name"; then
        print_message $GREEN "✓ 容器启动成功: $container_name"
        
        # 检查配置是否正确生成
        if docker exec "$container_name" test -f /etc/php84/conf.d/custom.ini; then
            print_message $GREEN "✓ PHP配置生成成功"
        else
            print_message $RED "✗ PHP配置生成失败"
        fi
        
        if docker exec "$container_name" test -f /etc/nginx/nginx.conf; then
            print_message $GREEN "✓ Nginx配置生成成功"
        else
            print_message $RED "✗ Nginx配置生成失败"
        fi
        
        if docker exec "$container_name" test -f /etc/redis.conf; then
            print_message $GREEN "✓ Redis配置生成成功"
        else
            print_message $RED "✗ Redis配置生成失败"
        fi
        
        # 检查服务健康状态
        check_service_health "${TEST_PORTS[0]}"
        
    else
        print_message $RED "✗ 容器启动失败: $container_name"
    fi
    
    # 清理测试容器
    docker stop "$container_name" > /dev/null 2>&1
    docker rm "$container_name" > /dev/null 2>&1
    
    print_message $BLUE "清理测试容器: $container_name"
}

# 函数：测试环境变量配置
test_environment_variables() {
    local container_name="test-env-vars"
    
    print_message $BLUE "测试环境变量配置"
    
    # 启动测试容器
    docker run -d \
        --name "$container_name" \
        -e PHP_MEMORY_LIMIT=256M \
        -e OPCACHE_MEMORY=128 \
        -e REDIS_MAXMEMORY=256mb \
        -e FPM_MAX_CHILDREN=40 \
        -p "${TEST_PORTS[1]}:8080" \
        zhoujie218/php-nginx:latest > /dev/null 2>&1
    
    # 等待容器启动
    sleep 5
    
    # 检查容器是否运行
    if check_container_running "$container_name"; then
        print_message $GREEN "✓ 环境变量配置容器启动成功"
        
        # 检查PHP配置
        local php_memory=$(docker exec "$container_name" php -i | grep "memory_limit" | awk '{print $3}')
        if [ "$php_memory" = "256M" ]; then
            print_message $GREEN "✓ PHP内存限制配置正确: $php_memory"
        else
            print_message $RED "✗ PHP内存限制配置错误: $php_memory"
        fi
        
        # 检查服务健康状态
        check_service_health "${TEST_PORTS[1]}"
        
    else
        print_message $RED "✗ 环境变量配置容器启动失败"
    fi
    
    # 清理测试容器
    docker stop "$container_name" > /dev/null 2>&1
    docker rm "$container_name" > /dev/null 2>&1
    
    print_message $BLUE "清理测试容器: $container_name"
}

# 函数：测试配置文件挂载
test_config_mounting() {
    local container_name="test-config-mount"
    
    print_message $BLUE "测试配置文件挂载"
    
    # 创建临时配置文件
    mkdir -p /tmp/test-config
    echo "memory_limit = 512M" > /tmp/test-config/php.ini
    
    # 启动测试容器
    docker run -d \
        --name "$container_name" \
        -v /tmp/test-config/php.ini:/etc/php84/conf.d/custom.ini \
        -p "${TEST_PORTS[2]}:8080" \
        zhoujie218/php-nginx:latest > /dev/null 2>&1
    
    # 等待容器启动
    sleep 5
    
    # 检查容器是否运行
    if check_container_running "$container_name"; then
        print_message $GREEN "✓ 配置文件挂载容器启动成功"
        
        # 检查挂载的配置是否生效
        local php_memory=$(docker exec "$container_name" php -i | grep "memory_limit" | awk '{print $3}')
        if [ "$php_memory" = "512M" ]; then
            print_message $GREEN "✓ 挂载的PHP配置生效: $php_memory"
        else
            print_message $RED "✗ 挂载的PHP配置未生效: $php_memory"
        fi
        
        # 检查服务健康状态
        check_service_health "${TEST_PORTS[2]}"
        
    else
        print_message $RED "✗ 配置文件挂载容器启动失败"
    fi
    
    # 清理测试容器和临时文件
    docker stop "$container_name" > /dev/null 2>&1
    docker rm "$container_name" > /dev/null 2>&1
    rm -rf /tmp/test-config
    
    print_message $BLUE "清理测试容器和临时文件: $container_name"
}

# 函数：性能测试
test_performance() {
    local profile=$1
    local container_name="test-perf-$profile"
    
    print_message $BLUE "性能测试: $profile"
    
    # 启动测试容器
    docker run -d \
        --name "$container_name" \
        -e RESOURCE_PROFILE="$profile" \
        -p "${TEST_PORTS[3]}:8080" \
        zhoujie218/php-nginx:latest > /dev/null 2>&1
    
    # 等待容器启动
    sleep 5
    
    # 检查容器是否运行
    if check_container_running "$container_name"; then
        print_message $GREEN "✓ 性能测试容器启动成功: $container_name"
        
        # 简单的性能测试
        local start_time=$(date +%s%N)
        for i in {1..10}; do
            curl --silent --fail "http://localhost:${TEST_PORTS[3]}/" > /dev/null 2>&1
        done
        local end_time=$(date +%s%N)
        local duration=$(( (end_time - start_time) / 1000000 ))
        
        print_message $GREEN "✓ 性能测试完成: $profile - 10次请求耗时 ${duration}ms"
        
    else
        print_message $RED "✗ 性能测试容器启动失败: $container_name"
    fi
    
    # 清理测试容器
    docker stop "$container_name" > /dev/null 2>&1
    docker rm "$container_name" > /dev/null 2>&1
    
    print_message $BLUE "清理性能测试容器: $container_name"
}

# 主函数
main() {
    print_message $BLUE "开始多资源配置测试..."
    print_message $BLUE "=================================="
    
    # 检查Docker是否运行
    if ! docker info > /dev/null 2>&1; then
        print_message $RED "错误: Docker未运行或无法访问"
        exit 1
    fi
    
    # 检查镜像是否存在
    if ! docker images | grep -q "zhoujie218/php-nginx"; then
        print_message $YELLOW "警告: 镜像 zhoujie218/php-nginx 不存在，请先构建镜像"
        print_message $YELLOW "运行: docker build -t zhoujie218/php-nginx ."
        exit 1
    fi
    
    # 测试预设配置
    print_message $BLUE "测试预设配置..."
    for profile in "${PROFILES[@]}"; do
        test_config_generation "$profile"
        echo
    done
    
    # 测试环境变量配置
    print_message $BLUE "测试环境变量配置..."
    test_environment_variables
    echo
    
    # 测试配置文件挂载
    print_message $BLUE "测试配置文件挂载..."
    test_config_mounting
    echo
    
    # 性能测试
    print_message $BLUE "性能测试..."
    for profile in "${PROFILES[@]}"; do
        test_performance "$profile"
    done
    
    print_message $BLUE "=================================="
    print_message $GREEN "所有测试完成！"
}

# 清理函数
cleanup() {
    print_message $YELLOW "清理测试容器..."
    for profile in "${PROFILES[@]}"; do
        docker stop "test-config-$profile" > /dev/null 2>&1
        docker rm "test-config-$profile" > /dev/null 2>&1
        docker stop "test-perf-$profile" > /dev/null 2>&1
        docker rm "test-perf-$profile" > /dev/null 2>&1
    done
    
    docker stop "test-env-vars" > /dev/null 2>&1
    docker rm "test-env-vars" > /dev/null 2>&1
    docker stop "test-config-mount" > /dev/null 2>&1
    docker rm "test-config-mount" > /dev/null 2>&1
    
    rm -rf /tmp/test-config
}

# 设置清理陷阱
trap cleanup EXIT

# 运行主函数
main "$@"
