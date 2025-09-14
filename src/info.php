<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redis 监控面板</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .card {
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <?php
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            
            // 测试写入
            $redis->set('test_key', 'Hello Redis!');
            
            // 测试读取
            $value = $redis->get('test_key');
            
            // 获取 Redis 信息
            $info = $redis->info();
            
            // 连接状态卡片
            echo '<div class="mb-8">';
            echo '<div class="bg-white rounded-lg shadow-lg p-6 card">';
            echo '<h2 class="text-2xl font-bold text-gray-800 mb-4">Redis 连接状态</h2>';
            echo '<div class="flex items-center space-x-4">';
            echo '<div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">';
            echo '<svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
            echo '</div>';
            echo '<div>';
            echo '<p class="text-green-600 font-semibold">连接测试成功！</p>';
            echo '<p class="text-gray-600">测试值: ' . htmlspecialchars($value) . '</p>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            // ================== 分组归类 ==================
            // 1. 服务器信息
            $serverInfo = [
                'redis_version' => 'Redis 服务器版本',
                'redis_mode' => '运行模式',
                'os' => '操作系统',
                'arch_bits' => '系统架构位数',
                'process_id' => '进程ID',
                'tcp_port' => 'TCP端口',
                'uptime_in_seconds' => '运行时间(秒)',
                'uptime_in_days' => '运行时间(天)',
                'hz' => '服务器频率',
                'lru_clock' => 'LRU时钟',
                'config_file' => '配置文件路径',
            ];
            // 2. 客户端信息
            $clientInfo = [
                'connected_clients' => '当前连接数',
                'blocked_clients' => '阻塞客户端数',
                'client_recent_max_input_buffer' => '客户端最大输入缓冲区',
                'client_recent_max_output_buffer' => '客户端最大输出缓冲区',
            ];
            // 3. 内存信息
            $memoryInfo = [
                'used_memory_human' => '已用内存',
                'used_memory_peak_human' => '内存峰值',
                'used_memory_lua' => 'Lua引擎内存',
                'used_memory_scripts' => '脚本内存',
                'maxmemory_human' => '最大内存限制',
                'mem_fragmentation_ratio' => '内存碎片率',
                'mem_allocator' => '内存分配器',
                'active_defrag_running' => '主动碎片整理',
            ];
            // 4. 持久化
            $persistenceInfo = [
                'rdb_changes_since_last_save' => '距离上次保存的更改数',
                'rdb_bgsave_in_progress' => '是否正在bgsave',
                'rdb_last_save_time' => '最后保存时间',
                'aof_enabled' => 'AOF是否启用',
                'aof_rewrite_in_progress' => 'AOF重写进行中',
                'aof_last_rewrite_time_sec' => 'AOF最后重写耗时',
            ];
            // 5. 统计信息
            $statsInfo = [
                'total_commands_processed' => '总命令处理数',
                'instantaneous_ops_per_sec' => '每秒操作数',
                'keyspace_hits' => '命中次数',
                'keyspace_misses' => '未命中次数',
                'expired_keys' => '过期键数',
                'evicted_keys' => '被驱逐键数',
                'rejected_connections' => '拒绝连接数',
                'sync_full' => '完全同步次数',
                'sync_partial_ok' => '部分同步成功',
                'sync_partial_err' => '部分同步失败',
            ];
            // 6. 复制
            $replicationInfo = [
                'role' => '角色',
                'connected_slaves' => '已连接从节点数',
                'master_replid' => '主复制ID',
                'master_repl_offset' => '主复制偏移量',
                'slave_repl_offset' => '从复制偏移量',
                'repl_backlog_active' => '复制积压激活',
                'repl_backlog_size' => '复制积压大小',
            ];
            // 7. CPU
            $cpuInfo = [
                'used_cpu_sys' => '系统CPU',
                'used_cpu_user' => '用户CPU',
                'used_cpu_sys_children' => '子进程系统CPU',
                'used_cpu_user_children' => '子进程用户CPU',
            ];
            // 8. 集群
            $clusterInfo = [
                'cluster_enabled' => '集群是否启用',
            ];
            // 9. 网络
            $networkInfo = [
                'total_connections_received' => '总连接数',
                'total_net_input_bytes' => '总输入流量',
                'total_net_output_bytes' => '总输出流量',
                'instantaneous_input_kbps' => '当前输入速率(KB/s)',
                'instantaneous_output_kbps' => '当前输出速率(KB/s)',
            ];
            // 10. Keyspace
            $keyspaceInfo = [];
            foreach ($info as $k => $v) {
                if (strpos($k, 'db') === 0) {
                    $keyspaceInfo[$k] = '数据库信息';
                }
            }

            // ========== 卡片渲染函数 ==========
            function renderCard($title, $fields, $info) {
                $hasData = false;
                $html = '<div class="bg-white rounded-lg shadow-lg p-6 card"><h2 class="text-xl font-bold text-gray-800 mb-4">' . $title . '</h2><div class="space-y-3">';
                foreach ($fields as $key => $label) {
                    if (isset($info[$key])) {
                        $hasData = true;
                        $value = htmlspecialchars($info[$key]);
                        // 特殊处理字节单位
                        if (strpos($key, 'bytes') !== false && is_numeric($info[$key])) {
                            $value = round($info[$key] / 1024 / 1024, 2) . ' MB';
                        }
                        $html .= '<div class="flex justify-between"><span class="text-gray-600">' . $label . '</span><span class="font-semibold">' . $value . '</span></div>';
                    }
                }
                $html .= '</div></div>';
                return $hasData ? $html : '';
            }

            // ========== 其他参数收集 ==========
            $allGroupedKeys = array_merge(
                array_keys($serverInfo),
                array_keys($clientInfo),
                array_keys($memoryInfo),
                array_keys($persistenceInfo),
                array_keys($statsInfo),
                array_keys($replicationInfo),
                array_keys($cpuInfo),
                array_keys($clusterInfo),
                array_keys($networkInfo),
                array_keys($keyspaceInfo)
            );
            $otherInfo = [];
            foreach ($info as $k => $v) {
                if (!in_array($k, $allGroupedKeys)) {
                    $otherInfo[$k] = $k;
                }
            }

            // ========== 页面渲染 ==========
            echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">';
            echo renderCard('服务器信息', $serverInfo, $info);
            echo renderCard('客户端信息', $clientInfo, $info);
            echo renderCard('内存信息', $memoryInfo, $info);
            echo renderCard('持久化', $persistenceInfo, $info);
            echo renderCard('统计信息', $statsInfo, $info);
            echo renderCard('复制', $replicationInfo, $info);
            echo renderCard('CPU信息', $cpuInfo, $info);
            echo renderCard('集群', $clusterInfo, $info);
            echo renderCard('网络统计', $networkInfo, $info);
            echo renderCard('数据库', $keyspaceInfo, $info);
            if (!empty($otherInfo)) {
                echo renderCard('其他参数', $otherInfo, $info);
            }
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">';
            echo '<strong class="font-bold">错误: </strong>';
            echo '<span class="block sm:inline">' . htmlspecialchars($e->getMessage()) . '</span>';
            echo '</div>';
        }
        ?>
    </div>
</body>
</html> 