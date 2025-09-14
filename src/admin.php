<?php
header('Content-Type: text/html; charset=utf-8');

// 简单的安全验证（生产环境建议使用更安全的认证方式）
$admin_key = 'admin123'; // 可以设置环境变量或配置文件
$is_authenticated = isset($_GET['key']) && $_GET['key'] === $admin_key;

// 处理管理操作
if ($is_authenticated && isset($_POST['action'])) {
    $action = $_POST['action'];
    $result = '';
    
    switch ($action) {
        case 'restart_php':
            $result = shell_exec('supervisorctl restart php-fpm 2>&1');
            break;
        case 'restart_nginx':
            $result = shell_exec('supervisorctl restart nginx 2>&1');
            break;
        case 'restart_redis':
            $result = shell_exec('supervisorctl restart redis 2>&1');
            break;
        case 'restart_all':
            $result = shell_exec('supervisorctl restart all 2>&1');
            break;
        case 'status':
            $result = shell_exec('supervisorctl status 2>&1');
            break;
        case 'clear_redis':
            try {
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379);
                $redis->flushAll();
                $result = 'Redis 缓存已清空';
            } catch (Exception $e) {
                $result = '清空 Redis 失败: ' . $e->getMessage();
            }
            break;
        case 'php_info':
            ob_start();
            phpinfo();
            $result = ob_get_clean();
            break;
    }
}

// 获取服务状态
function getServiceStatus() {
    $status = shell_exec('supervisorctl status 2>&1');
    $services = [];
    
    if ($status) {
        $lines = explode("\n", trim($status));
        foreach ($lines as $line) {
            if (trim($line)) {
                $parts = preg_split('/\s+/', trim($line), 3);
                if (count($parts) >= 3) {
                    $services[] = [
                        'name' => $parts[0],
                        'status' => $parts[1],
                        'description' => $parts[2] ?? ''
                    ];
                }
            }
        }
    }
    
    return $services;
}

// 获取系统信息
function getSystemInfo() {
    $info = [];
    
    // PHP 信息
    $info['php_version'] = PHP_VERSION;
    $info['php_memory_limit'] = ini_get('memory_limit');
    $info['php_max_execution_time'] = ini_get('max_execution_time');
    
    // 系统信息
    $info['uptime'] = shell_exec('uptime 2>&1') ?: '无法获取';
    $info['memory_usage'] = shell_exec('free -h 2>&1') ?: '无法获取';
    $info['disk_usage'] = shell_exec('df -h / 2>&1') ?: '无法获取';
    
    // Redis 信息
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis_info = $redis->info();
        $info['redis_version'] = $redis_info['redis_version'] ?? '未知';
        $info['redis_memory'] = $redis_info['used_memory_human'] ?? '未知';
        $info['redis_connected_clients'] = $redis_info['connected_clients'] ?? '未知';
    } catch (Exception $e) {
        $info['redis_error'] = $e->getMessage();
    }
    
    return $info;
}

$services = getServiceStatus();
$systemInfo = getSystemInfo();
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统管理面板</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .status-running {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        .status-stopped {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        .status-starting {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        .btn {
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: scale(1.05);
        }
        .result-box {
            max-height: 400px;
            overflow-y: auto;
            background: #1f2937;
            color: #f9fafb;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            line-height: 1.5;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- 标题区域 -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">
                <i class="fas fa-cogs text-blue-600"></i>
                系统管理面板
            </h1>
            <p class="text-lg text-gray-600">
                PHP-Nginx 容器服务管理工具
            </p>
        </div>

        <?php if (!$is_authenticated): ?>
            <!-- 登录表单 -->
            <div class="max-w-md mx-auto">
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">管理员登录</h2>
                    <form method="GET" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">访问密钥</label>
                            <input type="password" name="key" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入访问密钥" required>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            登录
                        </button>
                    </form>
                    <div class="mt-4 text-center text-sm text-gray-500">
                        <p>默认密钥: admin123</p>
                        <p class="mt-2">生产环境请修改默认密钥</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- 管理面板 -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- 服务状态 -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-lg p-6 card">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6">
                            <i class="fas fa-server text-blue-600 mr-2"></i>
                            服务状态
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <?php foreach ($services as $service): ?>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="font-semibold text-gray-800">
                                            <?php 
                                            $icons = [
                                                'php-fpm' => 'fab fa-php',
                                                'nginx' => 'fas fa-globe',
                                                'redis' => 'fas fa-database'
                                            ];
                                            $icon = $icons[$service['name']] ?? 'fas fa-cog';
                                            ?>
                                            <i class="<?php echo $icon; ?> mr-2"></i>
                                            <?php echo ucfirst($service['name']); ?>
                                        </h3>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium text-white
                                            <?php 
                                            if (strpos($service['status'], 'RUNNING') !== false) echo 'status-running';
                                            elseif (strpos($service['status'], 'STOPPED') !== false) echo 'status-stopped';
                                            else echo 'status-starting';
                                            ?>">
                                            <?php echo $service['status']; ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600"><?php echo $service['description']; ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- 操作按钮 -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="restart_php">
                                <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 btn">
                                    <i class="fas fa-redo mr-2"></i>
                                    重启 PHP
                                </button>
                            </form>
                            
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="restart_nginx">
                                <button type="submit" class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 btn">
                                    <i class="fas fa-redo mr-2"></i>
                                    重启 Nginx
                                </button>
                            </form>
                            
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="restart_redis">
                                <button type="submit" class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 btn">
                                    <i class="fas fa-redo mr-2"></i>
                                    重启 Redis
                                </button>
                            </form>
                            
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="restart_all">
                                <button type="submit" class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 btn">
                                    <i class="fas fa-power-off mr-2"></i>
                                    重启全部
                                </button>
                            </form>
                        </div>

                        <!-- 其他操作 -->
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="status">
                                <button type="submit" class="w-full bg-gray-600 text-white py-2 px-4 rounded-md hover:bg-gray-700 btn">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    查看状态
                                </button>
                            </form>
                            
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="clear_redis">
                                <button type="submit" class="w-full bg-yellow-600 text-white py-2 px-4 rounded-md hover:bg-yellow-700 btn">
                                    <i class="fas fa-trash mr-2"></i>
                                    清空 Redis
                                </button>
                            </form>
                            
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="php_info">
                                <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 btn">
                                    <i class="fas fa-code mr-2"></i>
                                    PHP 信息
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 系统信息 -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-lg p-6 card">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6">
                            <i class="fas fa-info-circle text-green-600 mr-2"></i>
                            系统信息
                        </h2>
                        
                        <div class="space-y-4">
                            <div class="bg-blue-50 rounded-lg p-4">
                                <h3 class="font-semibold text-blue-800 mb-2">PHP 信息</h3>
                                <p class="text-sm text-blue-700">版本: <?php echo $systemInfo['php_version']; ?></p>
                                <p class="text-sm text-blue-700">内存限制: <?php echo $systemInfo['php_memory_limit']; ?></p>
                                <p class="text-sm text-blue-700">执行时间: <?php echo $systemInfo['php_max_execution_time']; ?>s</p>
                            </div>
                            
                            <div class="bg-green-50 rounded-lg p-4">
                                <h3 class="font-semibold text-green-800 mb-2">Redis 信息</h3>
                                <?php if (isset($systemInfo['redis_error'])): ?>
                                    <p class="text-sm text-red-600"><?php echo $systemInfo['redis_error']; ?></p>
                                <?php else: ?>
                                    <p class="text-sm text-green-700">版本: <?php echo $systemInfo['redis_version']; ?></p>
                                    <p class="text-sm text-green-700">内存: <?php echo $systemInfo['redis_memory']; ?></p>
                                    <p class="text-sm text-green-700">连接数: <?php echo $systemInfo['redis_connected_clients']; ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="bg-purple-50 rounded-lg p-4">
                                <h3 class="font-semibold text-purple-800 mb-2">系统状态</h3>
                                <p class="text-sm text-purple-700">运行时间: <?php echo $systemInfo['uptime']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 操作结果 -->
            <?php if (isset($result) && $result): ?>
                <div class="mt-8">
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-terminal text-gray-600 mr-2"></i>
                            操作结果
                        </h2>
                        <div class="result-box p-4 rounded-lg">
                            <pre><?php echo htmlspecialchars($result); ?></pre>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 返回链接 -->
            <div class="mt-8 text-center">
                <a href="index.html" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    返回首页
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // 自动刷新页面（每30秒）
        setTimeout(function() {
            if (window.location.search.includes('key=')) {
                window.location.reload();
            }
        }, 30000);
    </script>
</body>
</html>
