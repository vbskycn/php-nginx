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
            $result = restartService('php-fpm84', 'php-fpm');
            break;
        case 'restart_nginx':
            $result = restartService('nginx', 'nginx');
            break;
        case 'restart_redis':
            $result = restartService('redis-server', 'redis');
            break;
        case 'restart_all':
            $result = restartAllServices();
            break;
        case 'status':
            // 直接使用 supervisorctl status 命令
            $result = shell_exec('supervisorctl status 2>&1');
            if (!$result || strpos($result, 'error') !== false) {
                $result = "获取服务状态失败: " . ($result ?: "未知错误");
            }
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
        case 'container_restart':
            $result = restartContainer();
            break;
        case 'diagnose':
            $result = diagnoseSystem();
            break;
    }
}

// 获取服务状态
function getServiceStatus() {
    $services = [];
    
    // 在容器环境中使用正确的 supervisord 配置路径
    $supervisor_config = '/etc/supervisor/conf.d/supervisord.conf';
    
    // 尝试使用正确的配置文件路径
    $status_cmd = "supervisorctl -c {$supervisor_config} status 2>&1";
    $status = shell_exec($status_cmd);
    
    // 如果失败，尝试默认路径
    if (!$status || strpos($status, 'error') !== false || strpos($status, 'not read config') !== false) {
        $status_cmd = 'supervisorctl status 2>&1';
        $status = shell_exec($status_cmd);
    }
    
    // 如果 supervisord 命令失败，尝试直接检查进程
    if (!$status || strpos($status, 'error') !== false || strpos($status, 'not read config') !== false || 
        strpos($status, '.ini file does not include supervisorctl section') !== false ||
        strpos($status, 'no such file') !== false ||
        strpos($status, 'unix:///run/supervisor.sock') !== false ||
        strpos($status, 'could not read config file') !== false) {
        // 直接检查进程状态
        $services = checkProcessStatus();
    } else {
        // 解析 supervisord 状态
        if ($status) {
            $lines = explode("\n", trim($status));
            foreach ($lines as $line) {
                if (trim($line) && !strpos($line, 'error') && !strpos($line, 'not read config')) {
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
    }
    
    return $services;
}

// 直接检查进程状态
function checkProcessStatus() {
    $services = [];
    
    // 检查 PHP-FPM
    $php_status = shell_exec('pgrep -f "php-fpm84" > /dev/null 2>&1 && echo "RUNNING" || echo "STOPPED"');
    $services[] = [
        'name' => 'php-fpm',
        'status' => trim($php_status) === 'RUNNING' ? 'RUNNING' : 'STOPPED',
        'description' => trim($php_status) === 'RUNNING' ? 'PHP-FPM 进程正在运行' : 'PHP-FPM 进程未运行'
    ];
    
    // 检查 Nginx
    $nginx_status = shell_exec('pgrep -f "nginx" > /dev/null 2>&1 && echo "RUNNING" || echo "STOPPED"');
    $services[] = [
        'name' => 'nginx',
        'status' => trim($nginx_status) === 'RUNNING' ? 'RUNNING' : 'STOPPED',
        'description' => trim($nginx_status) === 'RUNNING' ? 'Nginx 进程正在运行' : 'Nginx 进程未运行'
    ];
    
    // 检查 Redis
    $redis_status = shell_exec('pgrep -f "redis-server" > /dev/null 2>&1 && echo "RUNNING" || echo "STOPPED"');
    $services[] = [
        'name' => 'redis',
        'status' => trim($redis_status) === 'RUNNING' ? 'RUNNING' : 'STOPPED',
        'description' => trim($redis_status) === 'RUNNING' ? 'Redis 进程正在运行' : 'Redis 进程未运行'
    ];
    
    return $services;
}

// 重启单个服务
function restartService($process_name, $service_name) {
    // 直接使用 supervisorctl 命令，现在配置已经正确
    $result = shell_exec("supervisorctl restart {$service_name} 2>&1");
    
    // 如果命令执行失败，返回错误信息
    if (!$result || strpos($result, 'error') !== false) {
        $result = "重启 {$service_name} 失败: " . ($result ?: "未知错误");
    }
    
    return $result;
}

// 重启所有服务
function restartAllServices() {
    // 直接使用 supervisorctl restart all 命令
    $result = shell_exec("supervisorctl restart all 2>&1");
    
    // 如果命令执行失败，返回错误信息
    if (!$result || strpos($result, 'error') !== false) {
        $result = "重启所有服务失败: " . ($result ?: "未知错误");
    }
    
    return $result;
}

// 重启容器（最安全的方法）
function restartContainer() {
    $result = '';
    
    // 检查是否在容器环境中
    if (file_exists('/.dockerenv')) {
        $result .= "检测到容器环境，尝试重启容器...\n";
        
        // 方法1: 尝试使用 docker 命令（如果可用）
        $docker_result = shell_exec('docker restart $(hostname) 2>&1');
        if ($docker_result) {
            $result .= "Docker 重启结果: {$docker_result}\n";
        } else {
            // 方法2: 发送信号给主进程
            $result .= "Docker 命令不可用，尝试其他方法...\n";
            
            // 获取主进程 PID (supervisord)
            $main_pid = shell_exec('pgrep -f "supervisord" 2>/dev/null | head -1');
            if ($main_pid) {
                $main_pid = trim($main_pid);
                $result .= "找到主进程 PID: {$main_pid}\n";
                
                // 发送 TERM 信号给主进程，让容器优雅退出
                $signal_result = shell_exec("kill -TERM {$main_pid} 2>&1");
                $result .= "发送 TERM 信号: {$signal_result}\n";
                $result .= "容器将优雅退出，Docker 会自动重启容器\n";
            } else {
                $result .= "未找到主进程，无法重启容器\n";
            }
        }
    } else {
        $result .= "不在容器环境中，无法执行容器重启\n";
    }
    
    return $result;
}

// 系统诊断功能
function diagnoseSystem() {
    $result = '';
    
    $result .= "=== 系统诊断报告 ===\n\n";
    
    // 检查 supervisord 进程
    $result .= "1. 检查 supervisord 进程:\n";
    $supervisord_process = shell_exec('ps aux | grep supervisord | grep -v grep 2>&1');
    if ($supervisord_process) {
        $result .= "✓ Supervisord 进程正在运行:\n{$supervisord_process}\n";
    } else {
        $result .= "✗ Supervisord 进程未运行\n";
    }
    
    // 检查 socket 文件
    $result .= "\n2. 检查 socket 文件:\n";
    $socket_files = [
        '/run/supervisor.sock',
        '/run/supervisord.sock',
        '/tmp/supervisor.sock'
    ];
    
    foreach ($socket_files as $socket) {
        if (file_exists($socket)) {
            $result .= "✓ Socket 文件存在: {$socket}\n";
            $perms = shell_exec("ls -la {$socket} 2>&1");
            $result .= "  权限: {$perms}";
        } else {
            $result .= "✗ Socket 文件不存在: {$socket}\n";
        }
    }
    
    // 检查配置文件
    $result .= "\n3. 检查配置文件:\n";
    $config_files = [
        '/etc/supervisor/conf.d/supervisord.conf',
        '/etc/supervisord.conf'
    ];
    
    foreach ($config_files as $config) {
        if (file_exists($config)) {
            $result .= "✓ 配置文件存在: {$config}\n";
            if (is_link($config)) {
                $link_target = readlink($config);
                $result .= "  → 符号链接指向: {$link_target}\n";
            }
        } else {
            $result .= "✗ 配置文件不存在: {$config}\n";
        }
    }
    
    // 检查符号链接
    $result .= "\n4. 检查符号链接:\n";
    if (file_exists('/etc/supervisord.conf') && is_link('/etc/supervisord.conf')) {
        $link_target = readlink('/etc/supervisord.conf');
        $result .= "✓ 符号链接存在: /etc/supervisord.conf → {$link_target}\n";
    } else {
        $result .= "✗ 符号链接不存在或无效: /etc/supervisord.conf\n";
    }
    
    // 检查服务进程
    $result .= "\n5. 检查服务进程:\n";
    $services = ['php-fpm', 'nginx', 'redis-server'];
    foreach ($services as $service) {
        $process = shell_exec("pgrep -f '{$service}' 2>/dev/null");
        if ($process) {
            $result .= "✓ {$service} 进程正在运行 (PID: " . trim($process) . ")\n";
            
            // 显示进程详细信息
            $ps_info = shell_exec("ps aux | grep '{$service}' | grep -v grep 2>/dev/null");
            if ($ps_info) {
                $result .= "  进程详情:\n";
                $lines = explode("\n", trim($ps_info));
                foreach ($lines as $line) {
                    if (trim($line)) {
                        $result .= "    " . trim($line) . "\n";
                    }
                }
            }
            
            // 测试内存占用检测
            $memory_result = getProcessMemory($service);
            $result .= "  内存占用检测结果: {$memory_result}\n";
        } else {
            $result .= "✗ {$service} 进程未运行\n";
        }
    }
    
    // 显示所有进程列表（用于调试）
    $result .= "\n6. 所有进程列表（调试用）:\n";
    $all_processes = shell_exec("ps aux 2>/dev/null");
    if ($all_processes) {
        $lines = explode("\n", $all_processes);
        $relevant_processes = [];
        foreach ($lines as $line) {
            if (stripos($line, 'php') !== false || 
                stripos($line, 'nginx') !== false || 
                stripos($line, 'redis') !== false) {
                $relevant_processes[] = trim($line);
            }
        }
        if (!empty($relevant_processes)) {
            foreach ($relevant_processes as $proc) {
                $result .= "  " . $proc . "\n";
            }
        } else {
            $result .= "  未找到相关进程\n";
        }
    }
    
    // 检查端口
    $result .= "\n7. 检查端口:\n";
    $ports = ['8080', '6379'];
    foreach ($ports as $port) {
        $port_check = shell_exec("netstat -tlnp 2>/dev/null | grep :{$port} || ss -tlnp 2>/dev/null | grep :{$port}");
        if ($port_check) {
            $result .= "✓ 端口 {$port} 正在监听:\n{$port_check}\n";
        } else {
            $result .= "✗ 端口 {$port} 未监听\n";
        }
    }
    
    // 检查权限
    $result .= "\n7. 检查目录权限:\n";
    $dirs = ['/run', '/run/supervisor', '/var/www/html'];
    foreach ($dirs as $dir) {
        if (is_dir($dir)) {
            $perms = shell_exec("ls -ld {$dir} 2>&1");
            $result .= "✓ 目录 {$dir} 存在:\n{$perms}";
        } else {
            $result .= "✗ 目录 {$dir} 不存在\n";
        }
    }
    
    $result .= "\n=== 诊断完成 ===\n";
    
    return $result;
}

// 获取容器运行时间
function getContainerUptime() {
    // 尝试从 /proc/uptime 获取容器运行时间
    $uptime_file = '/proc/uptime';
    if (file_exists($uptime_file)) {
        $uptime_data = file_get_contents($uptime_file);
        if ($uptime_data) {
            $parts = explode(' ', trim($uptime_data));
            $uptime_seconds = floatval($parts[0]);
            
            // 转换为可读格式
            $days = floor($uptime_seconds / 86400);
            $hours = floor(($uptime_seconds % 86400) / 3600);
            $minutes = floor(($uptime_seconds % 3600) / 60);
            $seconds = floor($uptime_seconds % 60);
            
            if ($days > 0) {
                return "容器运行时间: {$days}天 {$hours}小时 {$minutes}分钟";
            } elseif ($hours > 0) {
                return "容器运行时间: {$hours}小时 {$minutes}分钟";
            } elseif ($minutes > 0) {
                return "容器运行时间: {$minutes}分钟 {$seconds}秒";
            } else {
                return "容器运行时间: {$seconds}秒";
            }
        }
    }
    
    // 备用方案：尝试使用 uptime 命令但显示为容器信息
    $uptime = shell_exec('uptime 2>&1');
    if ($uptime) {
        return "容器状态: " . trim($uptime);
    }
    
    return "无法获取容器运行时间";
}

// 获取容器 ID
function getContainerId() {
    // 尝试从 /proc/self/cgroup 获取容器 ID
    $cgroup_file = '/proc/self/cgroup';
    if (file_exists($cgroup_file)) {
        $cgroup_data = file_get_contents($cgroup_file);
        if ($cgroup_data) {
            $lines = explode("\n", $cgroup_data);
            foreach ($lines as $line) {
                if (strpos($line, 'docker') !== false || strpos($line, 'containerd') !== false) {
                    $parts = explode('/', $line);
                    if (count($parts) >= 3) {
                        $container_id = $parts[2];
                        // 截取前12位作为短ID
                        return substr($container_id, 0, 12);
                    }
                }
            }
        }
    }
    
    // 备用方案：尝试从 hostname 获取
    $hostname = shell_exec('hostname 2>/dev/null');
    if ($hostname) {
        return trim($hostname);
    }
    
    return "未知";
}

// 获取进程内存占用
function getProcessMemory($process_name) {
    // 尝试多种方式查找进程
    $commands = [
        "ps -o pid,rss,comm -C {$process_name} --no-headers 2>/dev/null",
        "ps aux | grep '{$process_name}' | grep -v grep 2>/dev/null",
        "pgrep -f '{$process_name}' | xargs ps -o pid,rss,comm --no-headers 2>/dev/null",
        "ps aux | grep -E '(php-fpm|nginx|redis)' | grep -v grep 2>/dev/null" // 更宽泛的搜索
    ];
    
    $memory_info = '';
    foreach ($commands as $cmd) {
        $memory_info = shell_exec($cmd);
        if ($memory_info && trim($memory_info)) {
            break;
        }
    }
    
    if (!$memory_info || !trim($memory_info)) {
        // 如果还是找不到，尝试使用supervisorctl检查状态
        $supervisor_status = shell_exec("supervisorctl status 2>/dev/null");
        if ($supervisor_status && strpos($supervisor_status, 'RUNNING') !== false) {
            return '运行中';
        }
        return '未运行';
    }
    
    $lines = explode("\n", trim($memory_info));
    $total_memory = 0;
    $process_count = 0;
    
    foreach ($lines as $line) {
        if (trim($line)) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) >= 2) {
                // 处理不同的 ps 输出格式
                $rss_index = 1;
                if (count($parts) >= 3 && is_numeric($parts[1]) && is_numeric($parts[2])) {
                    // ps aux 格式: USER PID %CPU %MEM VSZ RSS TTY STAT START TIME COMMAND
                    $rss_index = 5; // RSS 在第6列
                } elseif (count($parts) >= 2 && is_numeric($parts[1])) {
                    // ps -o 格式: PID RSS COMM
                    $rss_index = 1; // RSS 在第2列
                }
                
                if (isset($parts[$rss_index]) && is_numeric($parts[$rss_index])) {
                    $total_memory += intval($parts[$rss_index]); // RSS in KB
                    $process_count++;
                }
            }
        }
    }
    
    if ($total_memory > 0) {
        // 转换为 MB
        $memory_mb = round($total_memory / 1024, 2);
        return "{$memory_mb}MB ({$process_count}个进程)";
    }
    
    return '未运行';
}

// 获取系统信息
function getSystemInfo() {
    $info = [];
    
    // PHP 信息
    $info['php_version'] = PHP_VERSION;
    $info['php_memory_limit'] = ini_get('memory_limit');
    $info['php_max_execution_time'] = ini_get('max_execution_time');
    $info['php_memory_usage'] = getProcessMemory('php-fpm84');
    
    // Nginx 信息
    $info['nginx_memory_usage'] = getProcessMemory('nginx');
    
    // 系统信息
    $info['uptime'] = getContainerUptime();
    $info['container_id'] = getContainerId();
    $info['memory_usage'] = shell_exec('free -h 2>&1') ?: '无法获取';
    $info['disk_usage'] = shell_exec('df -h / 2>&1') ?: '无法获取';
    
    // Redis 信息
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis_info = $redis->info();
        $info['redis_version'] = $redis_info['redis_version'] ?? '未知';
        $info['redis_memory'] = $redis_info['used_memory_human'] ?? '未知';
        $info['redis_memory_usage'] = getProcessMemory('redis-server');
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
                            <?php 
                            // 确保显示所有三个服务
                            $expected_services = ['php-fpm', 'nginx', 'redis'];
                            $display_services = [];
                            
                            // 从实际状态中获取服务
                            foreach ($expected_services as $expected) {
                                $found = false;
                                foreach ($services as $service) {
                                    if ($service['name'] === $expected) {
                                        $display_services[] = $service;
                                        $found = true;
                                        break;
                                    }
                                }
                                if (!$found) {
                                    // 如果没找到，添加默认状态
                                    $display_services[] = [
                                        'name' => $expected,
                                        'status' => 'UNKNOWN',
                                        'description' => '状态未知'
                                    ];
                                }
                            }
                            
                            foreach ($display_services as $service): 
                            ?>
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
                                            <?php 
                                            $display_names = [
                                                'php-fpm' => 'PHP-FPM',
                                                'nginx' => 'Nginx',
                                                'redis' => 'Redis'
                                            ];
                                            echo $display_names[$service['name']] ?? ucfirst($service['name']); 
                                            ?>
                                        </h3>
                                        <span class="px-2 py-1 rounded-full text-xs font-medium text-white
                                            <?php 
                                            if (strpos($service['status'], 'RUNNING') !== false) echo 'status-running';
                                            elseif (strpos($service['status'], 'STOPPED') !== false) echo 'status-stopped';
                                            elseif (strpos($service['status'], 'STARTING') !== false) echo 'status-starting';
                                            else echo 'bg-gray-500';
                                            ?>">
                                            <?php 
                                            $status_display = [
                                                'RUNNING' => '运行中',
                                                'STOPPED' => '已停止',
                                                'STARTING' => '启动中',
                                                'UNKNOWN' => '未知'
                                            ];
                                            echo $status_display[$service['status']] ?? $service['status']; 
                                            ?>
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
                                <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 btn" onclick="return confirm('确定要重启 PHP-FPM 服务吗？')">
                                    <i class="fas fa-redo mr-2"></i>
                                    重启 PHP
                                </button>
                            </form>
                            
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="restart_nginx">
                                <button type="submit" class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 btn" onclick="return confirm('确定要重启 Nginx 服务吗？这可能会暂时中断网站访问。')">
                                    <i class="fas fa-redo mr-2"></i>
                                    重启 Nginx
                                </button>
                            </form>
                            
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="restart_redis">
                                <button type="submit" class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 btn" onclick="return confirm('确定要重启 Redis 服务吗？这可能会丢失未保存的缓存数据。')">
                                    <i class="fas fa-redo mr-2"></i>
                                    重启 Redis
                                </button>
                            </form>
                            
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="restart_all">
                                <button type="submit" class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 btn" onclick="return confirm('确定要重启所有服务吗？这可能会暂时中断网站访问。')">
                                    <i class="fas fa-power-off mr-2"></i>
                                    重启全部
                                </button>
                            </form>
                        </div>

                        <!-- 其他操作 -->
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-5 gap-3">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="status">
                                <button type="submit" class="w-full bg-gray-600 text-white py-2 px-4 rounded-md hover:bg-gray-700 btn">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    查看状态
                                </button>
                            </form>
                            
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="diagnose">
                                <button type="submit" class="w-full bg-teal-600 text-white py-2 px-4 rounded-md hover:bg-teal-700 btn">
                                    <i class="fas fa-stethoscope mr-2"></i>
                                    系统诊断
                                </button>
                            </form>
                            
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="clear_redis">
                                <button type="submit" class="w-full bg-yellow-600 text-white py-2 px-4 rounded-md hover:bg-yellow-700 btn" onclick="return confirm('确定要清空所有 Redis 缓存数据吗？')">
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
                            
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="container_restart">
                                <button type="submit" class="w-full bg-orange-600 text-white py-2 px-4 rounded-md hover:bg-orange-700 btn" onclick="return confirm('确定要重启整个容器吗？这将重启所有服务，网站会暂时不可访问。')">
                                    <i class="fas fa-sync-alt mr-2"></i>
                                    重启容器
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
                                <h3 class="font-semibold text-blue-800 mb-2">
                                    <i class="fas fa-code text-blue-600 mr-1"></i>PHP 信息
                                </h3>
                                <p class="text-sm text-blue-700">版本: <?php echo $systemInfo['php_version']; ?></p>
                                <p class="text-sm text-blue-700">内存限制: <?php echo $systemInfo['php_memory_limit']; ?></p>
                                <p class="text-sm text-blue-700">执行时间: <?php echo $systemInfo['php_max_execution_time']; ?>s</p>
                                <p class="text-sm text-blue-700 font-medium">
                                    <i class="fas fa-memory mr-1"></i>实际占用: <?php echo $systemInfo['php_memory_usage']; ?>
                                </p>
                            </div>
                            
                            <div class="bg-orange-50 rounded-lg p-4">
                                <h3 class="font-semibold text-orange-800 mb-2">
                                    <i class="fas fa-server text-orange-600 mr-1"></i>Nginx 信息
                                </h3>
                                <p class="text-sm text-orange-700 font-medium">
                                    <i class="fas fa-memory mr-1"></i>实际占用: <?php echo $systemInfo['nginx_memory_usage']; ?>
                                </p>
                            </div>
                            
                            <div class="bg-green-50 rounded-lg p-4">
                                <h3 class="font-semibold text-green-800 mb-2">
                                    <i class="fas fa-database text-green-600 mr-1"></i>Redis 信息
                                </h3>
                                <?php if (isset($systemInfo['redis_error'])): ?>
                                    <p class="text-sm text-red-600"><?php echo $systemInfo['redis_error']; ?></p>
                                <?php else: ?>
                                    <p class="text-sm text-green-700">版本: <?php echo $systemInfo['redis_version']; ?></p>
                                    <p class="text-sm text-green-700">内存: <?php echo $systemInfo['redis_memory']; ?></p>
                                    <p class="text-sm text-green-700 font-medium">
                                        <i class="fas fa-memory mr-1"></i>实际占用: <?php echo $systemInfo['redis_memory_usage']; ?>
                                    </p>
                                    <p class="text-sm text-green-700">连接数: <?php echo $systemInfo['redis_connected_clients']; ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="bg-purple-50 rounded-lg p-4">
                                <h3 class="font-semibold text-purple-800 mb-2">
                                    <i class="fas fa-info-circle text-purple-600 mr-1"></i>容器状态
                                </h3>
                                <p class="text-sm text-purple-700"><?php echo $systemInfo['uptime']; ?></p>
                                <p class="text-sm text-purple-700">容器 ID: <?php echo $systemInfo['container_id']; ?></p>
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
