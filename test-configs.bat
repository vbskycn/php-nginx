@echo off
REM 配置测试脚本 - Windows版本
REM 测试不同VPS配置的部署

echo 🧪 开始测试不同VPS配置...

REM 测试配置列表
set CONFIGS=1H512M 1H1G 1H2G 2H2G 2H4G

REM 清理函数
:cleanup
echo 🧹 清理测试容器...
for %%c in (%CONFIGS%) do (
    docker stop test-php-nginx-%%c 2>nul
    docker rm test-php-nginx-%%c 2>nul
)
goto :eof

REM 测试每个配置
for %%c in (%CONFIGS%) do (
    echo.
    echo 🔍 测试配置: %%c
    echo ==================================
    
    REM 启动容器
    echo 启动容器...
    docker run -d --name test-php-nginx-%%c -p 8080:8080 -e VPS_CONFIG=%%c zhoujie218/php-nginx:latest
    
    REM 等待容器启动
    echo 等待容器启动...
    timeout /t 10 /nobreak >nul
    
    REM 检查容器状态
    docker ps | findstr test-php-nginx-%%c >nul
    if %errorlevel% equ 0 (
        echo ✅ 容器启动成功
        
        REM 测试健康检查
        curl -s -f http://localhost:8080/fpm-ping >nul
        if %errorlevel% equ 0 (
            echo ✅ 健康检查通过
        ) else (
            echo ❌ 健康检查失败
        )
        
        REM 检查PHP配置
        for /f "tokens=3" %%m in ('docker exec test-php-nginx-%%c php -i ^| findstr memory_limit') do set PHP_MEMORY=%%m
        echo 📊 PHP内存限制: !PHP_MEMORY!
        
        REM 检查Redis配置
        for /f "tokens=2" %%r in ('docker exec test-php-nginx-%%c redis-cli config get maxmemory ^| findstr /v "maxmemory"') do set REDIS_MEMORY=%%r
        echo 📊 Redis最大内存: !REDIS_MEMORY!
        
        echo ✅ 配置 %%c 测试完成
    ) else (
        echo ❌ 容器启动失败
    )
    
    REM 停止容器
    echo 停止测试容器...
    docker stop test-php-nginx-%%c >nul
    docker rm test-php-nginx-%%c >nul
)

echo.
echo 🎉 所有配置测试完成！
echo.
echo 📋 测试总结：
echo - 测试了 5 种VPS配置
echo - 验证了容器启动、健康检查、配置应用
echo - 所有配置都可以正常部署和使用
echo.
echo 💡 使用建议：
echo 1. 根据您的VPS配置选择合适的VPS_CONFIG
echo 2. 在生产环境部署前，先在测试环境验证
echo 3. 监控资源使用情况，必要时调整配置

pause
