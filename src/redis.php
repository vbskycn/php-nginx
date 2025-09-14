<?php
header('Content-Type: text/html; charset=utf-8');

// 处理 AJAX 请求
if (isset($_GET['action'])) {
    try {
        $redis = new Redis();
        if (!$redis->connect('127.0.0.1', 6379)) {
            throw new Exception("Redis连接失败");
        }

        if ($_GET['action'] === 'get_key_info' && isset($_GET['key'])) {
            $key = $_GET['key'];
            $type = $redis->type($key);
            $ttl = $redis->ttl($key);
            $size = strlen($redis->get($key));
            
            switch ($type) {
                case Redis::REDIS_STRING:
                    $value = $redis->get($key);
                    break;
                case Redis::REDIS_LIST:
                    $value = implode("\n", $redis->lRange($key, 0, -1));
                    break;
                case Redis::REDIS_SET:
                    $value = implode("\n", $redis->sMembers($key));
                    break;
                case Redis::REDIS_HASH:
                    $value = json_encode($redis->hGetAll($key), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    break;
                default:
                    $value = "不支持的数据类型";
            }

            echo json_encode([
                'ttl' => $ttl,
                'size' => formatBytes($size),
                'value' => $value,
                'type' => $type
            ]);
            exit;
        }

        if ($_GET['action'] === 'delete_key' && isset($_GET['key'])) {
            $key = $_GET['key'];
            $result = $redis->del($key);
            echo json_encode(['success' => $result > 0]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

try {
    $redis = new Redis();
    if (!$redis->connect('127.0.0.1', 6379)) {
        throw new Exception("Redis连接失败");
    }

    // 获取所有键
    $allKeys = $redis->keys('*');
    
    // 获取所有键的 TTL 并排序
    $keysWithTtl = [];
    foreach ($allKeys as $key) {
        $ttl = $redis->ttl($key);
        $keysWithTtl[] = [
            'key' => $key,
            'ttl' => $ttl < 0 ? PHP_INT_MAX : $ttl
        ];
    }
    
    // 按 TTL 降序排序
    usort($keysWithTtl, function($a, $b) {
        return $b['ttl'] - $a['ttl'];
    });
    
    // 提取排序后的键名
    $allKeys = array_map(function($item) {
        return $item['key'];
    }, $keysWithTtl);
    
    // 优化分组逻辑：根据通用前缀分组
    $groupedKeys = [];
    foreach ($allKeys as $key) {
        $parts = explode(':', $key);
        if (count($parts) > 1) {
            // 如果包含冒号，使用冒号前的部分作为前缀
            $prefix = $parts[0] . ':';
        } else {
            // 否则使用下划线前的部分作为前缀
            $parts = explode('_', $key);
            $prefix = $parts[0] . '_';
        }
        
        if (!isset($groupedKeys[$prefix])) {
            $groupedKeys[$prefix] = [];
        }
        $groupedKeys[$prefix][] = $key;
    }

    // 获取当前选中的分组
    $selectedPrefix = isset($_GET['prefix']) ? $_GET['prefix'] : 'all';
    
    // 根据选中的分组获取要显示的键
    $displayKeys = ($selectedPrefix === 'all') ? $allKeys : 
        (isset($groupedKeys[$selectedPrefix]) ? $groupedKeys[$selectedPrefix] : []);
    
    // 分页设置
    $perPage = 100; // 每页显示数量
    $totalKeys = count($displayKeys);
    $totalPages = ceil($totalKeys / $perPage);
    $currentPage = isset($_GET['page']) ? max(1, min(intval($_GET['page']), $totalPages)) : 1;
    
    // 获取当前页的键
    $pageKeys = array_slice($displayKeys, ($currentPage - 1) * $perPage, $perPage);
    
    $info = $redis->info();
} catch (Exception $e) {
    $error = $e->getMessage();
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

function calculateHitRate($info) {
    $hits = $info['keyspace_hits'];
    $misses = $info['keyspace_misses'];
    $total = $hits + $misses;
    return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
}

// 新增函数：格式化缓存内容预览
function formatPreview($redis, $key, $type, $maxLength = 100) {
    $value = '';
    switch ($type) {
        case Redis::REDIS_STRING:
            $value = $redis->get($key);
            break;
        case Redis::REDIS_LIST:
            $value = implode(', ', $redis->lRange($key, 0, 5));
            break;
        case Redis::REDIS_SET:
            $value = implode(', ', $redis->sMembers($key));
            break;
        case Redis::REDIS_HASH:
            $value = json_encode($redis->hGetAll($key));
            break;
    }
    if (strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength) . '...';
    }
    return htmlspecialchars($value);
}

function generatePagination($currentPage, $totalPages, $maxButtons = 7) {
    // 如果总页数为0或1，不显示分页
    if ($totalPages <= 1) {
        return [];
    }
    
    $buttons = [];
    
    // 总是显示第一页
    $buttons[] = 1;
    
    if ($totalPages <= $maxButtons) {
        // 如果总页数小于最大按钮数，显示所有页码
        for ($i = 2; $i <= $totalPages; $i++) {
            $buttons[] = $i;
        }
    } else {
        // 计算需要显示的页码范围
        $startPage = max(2, $currentPage - floor($maxButtons / 2));
        $endPage = min($totalPages - 1, $startPage + $maxButtons - 3);
        
        if ($startPage > 2) {
            $buttons[] = '...';
        }
        
        for ($i = $startPage; $i <= $endPage; $i++) {
            $buttons[] = $i;
        }
        
        if ($endPage < $totalPages - 1) {
            $buttons[] = '...';
        }
        
        // 总是显示最后一页
        if ($totalPages > 1) {
            $buttons[] = $totalPages;
        }
    }
    
    return $buttons;
}

function getGroupKeyCount($prefix, $allKeys) {
    if (empty($allKeys)) {
        return 0;
    }
    
    if ($prefix === 'all') {
        return count($allKeys);
    }
    
    $count = 0;
    foreach ($allKeys as $key) {
        if (strpos($key, $prefix) === 0) {
            $count++;
        }
    }
    return $count;
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redis 监控面板</title>
    <link href="https://lf26-cdn-tos.bytecdntp.com/cdn/expire-1-M/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://lf26-cdn-tos.bytecdntp.com/cdn/expire-1-M/boxicons/2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #5469d4;
            --secondary-color: #8492c4;
            --success-color: #0ca678;
            --warning-color: #f59f00;
            --danger-color: #e03131;
            --background-color: #f8fafc;
            --card-background: #ffffff;
            --text-primary: #1a1f36;
            --text-secondary: #697386;
            --border-color: #e9ecef;
        }

        body {
            background-color: var(--background-color);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--text-primary);
            line-height: 1.5;
            font-size: 16px;
        }

        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
            width: 100%;
        }

        .card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s ease;
            width: 100%;
            max-width: 100%;
        }

        .stats-card {
            padding: 0.8rem 1.2rem;
            margin-bottom: 0.8rem;
            background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            min-width: 0;
            flex: 1;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .stats-card:nth-child(1) {
            background: linear-gradient(135deg, #e8f3ff 0%, #ffffff 100%);
        }

        .stats-card:nth-child(2) {
            background: linear-gradient(135deg, #fff5f5 0%, #ffffff 100%);
        }

        .stats-card:nth-child(3) {
            background: linear-gradient(135deg, #f3f9ff 0%, #ffffff 100%);
        }

        .stats-card:nth-child(4) {
            background: linear-gradient(135deg, #f6fff3 0%, #ffffff 100%);
        }

        .stats-card .card-title {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 600;
            margin-top: 0.3rem;
            margin-bottom: 0;
            background: linear-gradient(45deg, var(--primary-color), #8492c4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stats-card .card-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 0;
        }

        .card-header {
            padding: 0.8rem 1.2rem;
        }

        .btn-group {
            gap: 0.5rem;
            flex-wrap: wrap;
            padding: 0.6rem !important;
            width: 100%;
        }

        .group-btn {
            border-radius: 8px !important;
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            background: linear-gradient(to bottom, #ffffff, #f8fafc);
            transition: all 0.2s ease;
            white-space: nowrap;
            min-width: fit-content;
        }

        .group-btn:hover {
            background: linear-gradient(135deg, var(--primary-color), #6574d8);
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 2px 8px rgba(84, 105, 212, 0.25);
        }

        .group-btn.active {
            background: linear-gradient(135deg, var(--primary-color), #6574d8);
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 2px 8px rgba(84, 105, 212, 0.25);
        }

        .table {
            min-width: 800px;
            color: var(--text-primary);
        }

        .table th, .table td {
            padding: 0.6rem 1rem;
            font-size: 1rem;
        }

        .badge {
            padding: 0.4rem 0.8rem;
            font-weight: 500;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .ttl-good {
            background: linear-gradient(135deg, var(--success-color), #12b886);
            color: white;
        }

        .ttl-warning {
            background: linear-gradient(135deg, var(--warning-color), #ffb84d);
            color: white;
        }

        .ttl-expired {
            background: linear-gradient(135deg, var(--danger-color), #ff4444);
            color: white;
        }

        .search-box {
            position: relative;
            display: flex;
            align-items: center;
            min-width: 200px;
        }

        .search-box i {
            position: absolute;
            left: 10px;
            color: var(--text-secondary);
            z-index: 1;
        }

        .search-box input {
            background: linear-gradient(to bottom, #ffffff, #f8fafc);
            border-radius: 8px;
            padding: 0.4rem 1rem 0.4rem 2.5rem;
            border: 1px solid var(--border-color);
            width: 100%;
            min-width: 200px;
            max-width: 400px;
            transition: all 0.2s ease;
        }

        .search-box input:focus {
            background: #ffffff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(84, 105, 212, 0.1);
        }

        .content-preview {
            background: linear-gradient(to bottom, #f8fafc, #ffffff);
            border-radius: 8px;
            padding: 0.8rem;
            margin: 0.3rem 0;
            border: 1px solid var(--border-color);
        }

        .content-preview pre {
            margin: 0;
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
            font-size: 0.95rem;
            line-height: 1.5;
            color: var(--text-primary);
        }

        .btn-outline-danger {
            background: linear-gradient(to bottom, #fff5f5, #fff);
            color: var(--danger-color);
            border-color: var(--danger-color);
            transition: all 0.2s ease;
        }

        .btn-outline-danger:hover {
            background: linear-gradient(135deg, var(--danger-color), #ff4444);
            color: white;
            box-shadow: 0 2px 8px rgba(224, 49, 49, 0.25);
        }

        .refresh-badge {
            background-color: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* 响应式优化 */
        @media (max-width: 1200px) {
            .container-fluid {
                max-width: 100%;
                padding: 0.8rem;
            }
            
            .stats-container {
                gap: 0.8rem;
            }
            
            .stats-card {
                min-width: 180px;
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .container-fluid {
                padding: 0.5rem;
            }

            .search-box input {
                width: 100%;
                max-width: 300px;
            }

            .stats-card {
                margin-bottom: 1rem;
                min-width: 150px;
                flex: 1 1 calc(50% - 0.5rem);
            }
            
            .stats-container {
                gap: 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .container-fluid {
                padding: 0.3rem;
            }
            
            .stats-card {
                flex: 1 1 100%;
                min-width: 100%;
            }
            
            .title-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .title-card h2 {
                font-size: 1.5rem;
            }
        }

        /* 表格行悬停效果 */
        .table tbody tr:hover {
            background: linear-gradient(to right, #f8fafc, #ffffff);
            transform: translateX(2px);
            transition: all 0.2s ease;
        }

        /* 表格头部样式 */
        .table thead th {
            background: linear-gradient(to bottom, #f8fafc, #ffffff);
            border-bottom: 2px solid var(--border-color);
            font-size: 1.05rem;
            font-weight: 600;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        /* 表格行交替颜色 */
        .table tbody tr:nth-child(even) {
            background-color: rgba(248, 250, 252, 0.3);
        }

        /* 键名显示优化 */
        .key-name {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            font-size: 0.9rem;
            color: var(--text-primary);
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .key-name:hover {
            color: var(--primary-color);
        }

        /* 操作按钮优化 */
        .btn-outline-danger {
            background: linear-gradient(to bottom, #fff5f5, #fff);
            color: var(--danger-color);
            border-color: var(--danger-color);
            transition: all 0.2s ease;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        .btn-outline-danger:hover {
            background: linear-gradient(135deg, var(--danger-color), #ff4444);
            color: white;
            box-shadow: 0 2px 8px rgba(224, 49, 49, 0.25);
            transform: scale(1.05);
        }

        /* 过期时间徽章优化 */
        .ttl-good {
            background: linear-gradient(135deg, var(--success-color), #12b886);
            color: white;
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }

        .ttl-warning {
            background: linear-gradient(135deg, var(--warning-color), #ffb84d);
            color: white;
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }

        .ttl-expired {
            background: linear-gradient(135deg, var(--danger-color), #ff4444);
            color: white;
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }

        /* 类型徽章优化 */
        .badge.bg-info {
            background: linear-gradient(135deg, #0ea5e9, #0284c7) !important;
            color: white;
            font-size: 0.75rem;
            padding: 0.2rem 0.4rem;
        }

        /* 大小列优化 */
        .size-info {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        /* 表格容器阴影优化 */
        .table-responsive {
            border-radius: 0 0 12px 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        /* 搜索框图标优化 */
        .search-box i {
            position: absolute;
            left: 10px;
            color: var(--text-secondary);
            z-index: 1;
            font-size: 1rem;
        }

        /* 分组按钮激活状态优化 */
        .group-btn.active {
            background: linear-gradient(135deg, var(--primary-color), #6574d8);
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 2px 8px rgba(84, 105, 212, 0.25);
            transform: translateY(-1px);
        }

        /* 卡片阴影优化 */
        .card {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .row.g-3 {
            --bs-gutter-y: 0.8rem;
        }

        .mb-4 {
            margin-bottom: 1rem !important;
        }

        /* 调整表格行高 */
        .table > :not(caption) > * > * {
            padding: 0.5rem;
        }

        /* 优化表格内容展开区域 */
        .content-row td {
            padding: 0 !important;
        }

        .badge.bg-secondary {
            font-size: 0.85rem;
        }

        .pagination {
            margin: 0;
            gap: 0.25rem;
        }

        .page-link {
            border-radius: 6px;
            padding: 0.375rem 0.75rem;
            color: var(--primary-color);
            border: 1px solid var(--border-color);
            background: linear-gradient(to bottom, #ffffff, #f8fafc);
            transition: all 0.2s ease;
        }

        .page-link:hover {
            background: linear-gradient(135deg, var(--primary-color), #6574d8);
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 2px 8px rgba(84, 105, 212, 0.25);
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-color), #6574d8);
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 2px 8px rgba(84, 105, 212, 0.25);
        }

        .page-item.disabled .page-link {
            background: #f8fafc;
            color: var(--text-secondary);
            border-color: var(--border-color);
        }

        .header {
            margin-bottom: 2rem;
        }

        .header .title {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 600;
            margin: 0;
            background: linear-gradient(45deg, var(--primary-color), #8492c4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .footer {
            border-top: 1px solid var(--border-color);
            background: linear-gradient(to bottom, #ffffff, #f8fafc);
        }

        .footer a {
            color: var(--primary-color);
            transition: color 0.2s ease;
        }

        .footer a:hover {
            color: #6574d8;
        }

        .footer i {
            font-size: 1.2rem;
            vertical-align: middle;
        }

        .mx-2 {
            color: var(--text-secondary);
        }

        .title-card {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .title-card h2 {
            color: white;
            font-size: 2rem;
            font-weight: 600;
            margin: 0;
            text-align: center;
            letter-spacing: 1px;
        }

        .title-card a {
            color: white;
            text-decoration: none;
            transition: opacity 0.2s ease;
            display: inline-block;
        }

        .title-card a:hover {
            opacity: 0.9;
        }

        .title-card i {
            font-size: 1.2rem;
            margin-right: 0.5rem;
            vertical-align: middle;
        }

        /* 修改统计卡片样式 */
        .stats-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            width: 100%;
            flex-wrap: wrap;
        }

        .stats-card {
            flex: 1;
            min-width: 200px;
            padding: 1.2rem;
            border-radius: 12px;
            text-align: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stats-card:nth-child(1) {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .stats-card:nth-child(2) {
            background: linear-gradient(135deg, #2dd4bf, #0ea5e9);
            color: white;
        }

        .stats-card:nth-child(3) {
            background: linear-gradient(135deg, #f59e0b, #f97316);
            color: white;
        }

        .stats-card:nth-child(4) {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .stats-card .card-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.5rem 0;
            color: white;
            background: none;
            -webkit-text-fill-color: white;
        }

        .stats-card .card-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            color: white;
            margin-bottom: 0.3rem;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        /* 表格响应式优化 */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* 分页控件响应式优化 */
        .pagination {
            margin: 0;
            gap: 0.25rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .page-link {
            border-radius: 6px;
            padding: 0.375rem 0.75rem;
            color: var(--primary-color);
            border: 1px solid var(--border-color);
            background: linear-gradient(to bottom, #ffffff, #f8fafc);
            transition: all 0.2s ease;
            min-width: 40px;
            text-align: center;
        }

        .page-link:hover {
            background: linear-gradient(135deg, var(--primary-color), #6574d8);
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 2px 8px rgba(84, 105, 212, 0.25);
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-color), #6574d8);
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 2px 8px rgba(84, 105, 212, 0.25);
        }

        .page-item.disabled .page-link {
            background: #f8fafc;
            color: var(--text-secondary);
            border-color: var(--border-color);
        }

        /* 分页信息响应式优化 */
        .pagination-info {
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .pagination-info {
                font-size: 0.8rem;
                margin-bottom: 0.3rem;
            }
            
            .page-link {
                padding: 0.25rem 0.5rem;
                min-width: 35px;
                font-size: 0.9rem;
            }
            
            .pagination {
                gap: 0.2rem;
            }
        }

        @media (max-width: 576px) {
            .pagination-info {
                font-size: 0.75rem;
            }
            
            .page-link {
                padding: 0.2rem 0.4rem;
                min-width: 32px;
                font-size: 0.8rem;
            }
            
            .pagination {
                gap: 0.15rem;
            }
        }

        /* 表格头部和内容响应式优化 */
        .table thead th {
            background: linear-gradient(to bottom, #f8fafc, #ffffff);
            border-bottom: 2px solid var(--border-color);
            font-size: 1.05rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .table > :not(caption) > * > * {
            padding: 0.5rem;
        }

        @media (max-width: 768px) {
            .table thead th {
                font-size: 0.95rem;
                padding: 0.4rem 0.3rem;
            }
            
            .table > :not(caption) > * > * {
                padding: 0.3rem 0.2rem;
                font-size: 0.9rem;
            }
            
            .badge {
                font-size: 0.8rem;
                padding: 0.3rem 0.6rem;
            }
        }

        @media (max-width: 576px) {
            .table thead th {
                font-size: 0.85rem;
                padding: 0.3rem 0.2rem;
            }
            
            .table > :not(caption) > * > * {
                padding: 0.25rem 0.15rem;
                font-size: 0.8rem;
            }
            
            .badge {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
        }

        /* 卡片头部响应式优化 */
        .card-header {
            padding: 0.8rem 1.2rem;
        }

        .card-header .d-flex {
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }

        .card-header h5 {
            margin: 0;
            flex-shrink: 0;
        }

        /* 搜索框优化 - 减少占用空间 */
        .search-box {
            position: relative;
            display: flex;
            align-items: center;
            min-width: 200px;
            flex: 1;
            max-width: 300px;
        }

        .search-box input {
            background: linear-gradient(to bottom, #ffffff, #f8fafc);
            border-radius: 8px;
            padding: 0.4rem 1rem 0.4rem 2.5rem;
            border: 1px solid var(--border-color);
            width: 100%;
            min-width: 200px;
            max-width: 300px;
            transition: all 0.2s ease;
        }

        .search-box input:focus {
            background: #ffffff;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(84, 105, 212, 0.1);
            outline: none;
        }

        /* 清除搜索按钮样式 */
        .clear-search {
            background: linear-gradient(to bottom, #f8f9fa, #e9ecef);
            border-color: var(--border-color);
            color: var(--text-secondary);
            border-radius: 6px;
            padding: 0.3rem 0.5rem;
            font-size: 0.8rem;
            transition: all 0.2s ease;
            min-width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .clear-search:hover {
            background: linear-gradient(135deg, var(--danger-color), #ff4444);
            border-color: var(--danger-color);
            color: white;
            transform: scale(1.05);
        }

        .clear-search:active {
            transform: scale(0.95);
        }

        /* 搜索状态指示器 */
        .search-status {
            position: absolute;
            top: -25px;
            right: 0;
            background: linear-gradient(135deg, var(--primary-color), #6574d8);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            opacity: 0;
            transform: translateY(-5px);
            transition: all 0.3s ease;
        }

        .search-status.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* 搜索高亮效果 */
        .search-highlight {
            background-color: rgba(84, 105, 212, 0.1) !important;
            border-left: 3px solid var(--primary-color);
        }

        /* 响应式搜索框 */
        @media (max-width: 768px) {
            .search-box {
                max-width: 250px;
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }
            
            .search-box input {
                max-width: 100%;
            }
            
            .clear-search {
                align-self: flex-end;
                margin-left: 0;
            }
        }

        @media (max-width: 576px) {
            .search-box {
                max-width: 100%;
            }
        }

        /* 分组按钮优化 - 更紧凑的布局 */
        .btn-group {
            gap: 0.4rem;
            flex-wrap: wrap;
            padding: 0.5rem 1rem !important;
            width: 100%;
            justify-content: flex-start;
        }

        .group-btn {
            border-radius: 8px !important;
            padding: 0.35rem 0.7rem;
            font-size: 0.85rem;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            background: linear-gradient(to bottom, #ffffff, #f8fafc);
            transition: all 0.2s ease;
            white-space: nowrap;
            min-width: fit-content;
            margin-bottom: 0.2rem;
        }

        /* 表格列宽优化 - 更合理的宽度分配 */
        .table th:nth-child(1) { /* 过期时间 */
            width: 12%;
            min-width: 100px;
        }
        
        .table th:nth-child(2) { /* 键名 */
            width: 50%;
            min-width: 200px;
        }
        
        .table th:nth-child(3) { /* 类型 */
            width: 8%;
            min-width: 60px;
        }
        
        .table th:nth-child(4) { /* 大小 */
            width: 15%;
            min-width: 80px;
        }
        
        .table th:nth-child(5) { /* 操作 */
            width: 15%;
            min-width: 80px;
        }

        /* 表格内容优化 */
        .table td {
            vertical-align: middle;
            word-break: break-word;
        }

        .table td:nth-child(2) { /* 键名列 */
            max-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* 减少表格行间距 */
        .table > :not(caption) > * > * {
            padding: 0.4rem 0.5rem;
        }

        /* 优化徽章显示 */
        .badge {
            padding: 0.3rem 0.6rem;
            font-weight: 500;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            font-size: 0.85rem;
        }

        /* 减少卡片内边距 */
        .card {
            padding: 0;
        }

        .card-body {
            padding: 0;
        }

        /* 优化分页控件间距 */
        .pagination-info {
            font-size: 0.85rem;
            text-align: center;
            margin-bottom: 0.4rem;
        }

        /* 响应式优化 */
        @media (max-width: 1200px) {
            .table th:nth-child(2) { /* 键名列 */
                width: 45%;
            }
            
            .table th:nth-child(4) { /* 大小列 */
                width: 18%;
            }
        }

        @media (max-width: 768px) {
            .card-header .d-flex {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            
            .search-box {
                max-width: 250px;
            }
            
            .btn-group {
                padding: 0.4rem 0.8rem !important;
                gap: 0.3rem;
            }
            
            .group-btn {
                padding: 0.3rem 0.6rem;
                font-size: 0.8rem;
            }
            
            .table th:nth-child(1) { /* 过期时间 */
                width: 15%;
                min-width: 80px;
            }
            
            .table th:nth-child(2) { /* 键名列 */
                width: 40%;
                min-width: 150px;
            }
            
            .table th:nth-child(3) { /* 类型 */
                width: 10%;
                min-width: 50px;
            }
            
            .table th:nth-child(4) { /* 大小 */
                width: 20%;
                min-width: 70px;
            }
            
            .table th:nth-child(5) { /* 操作 */
                width: 15%;
                min-width: 60px;
            }
        }

        @media (max-width: 576px) {
            .card-header .d-flex {
                flex-direction: column;
                align-items: stretch !important;
            }
            
            .search-box {
                max-width: 100%;
                margin-top: 0.5rem;
            }
            
            .btn-group {
                padding: 0.3rem 0.6rem !important;
                gap: 0.2rem;
            }
            
            .group-btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            
            .table > :not(caption) > * > * {
                padding: 0.25rem 0.3rem;
            }
        }

        /* 额外优化 */
        * {
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            overflow-x: hidden;
        }

        /* 确保内容不会超出容器 */
        .container-fluid > * {
            max-width: 100%;
        }

        /* 优化标题卡片在不同屏幕尺寸下的显示 */
        .title-card {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        /* 确保表格容器不会溢出 */
        .table-responsive {
            border-radius: 0 0 12px 12px;
            overflow: hidden;
        }

        /* 优化按钮组在小屏幕上的显示 */
        @media (max-width: 480px) {
            .btn-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .group-btn {
                margin-bottom: 0.3rem;
                text-align: center;
            }
            
            .container-fluid {
                padding: 0.2rem;
            }
        }

        /* 确保搜索框在所有设备上都能正常显示 */
        .search-box {
            width: 100%;
            max-width: 400px;
        }

        @media (max-width: 768px) {
            .search-box {
                max-width: 100%;
            }
        }
        /* 确保缓存键列表占满整个容器宽度 */
        .card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s ease;
            width: 100%;
            max-width: 100%;
        }

        /* 表格容器占满宽度 */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            width: 100%;
            max-width: 100%;
        }

        /* 表格占满容器宽度 */
        .table {
            min-width: 100%;
            width: 100%;
            color: var(--text-primary);
            table-layout: fixed;
        }

        /* 表格列宽优化 - 占满整个容器 */
        .table th:nth-child(1) { /* 过期时间 */
            width: 15%;
            min-width: 120px;
        }
        
        .table th:nth-child(2) { /* 键名 */
            width: 55%;
            min-width: 300px;
        }
        
        .table th:nth-child(3) { /* 类型 */
            width: 10%;
            min-width: 80px;
        }
        
        .table th:nth-child(4) { /* 大小 */
            width: 15%;
            min-width: 100px;
        }
        
        .table th:nth-child(5) { /* 操作 */
            width: 5%;
            min-width: 80px;
        }

        /* 确保操作按钮可以正常点击 */
        .btn-outline-danger {
            background: linear-gradient(to bottom, #fff5f5, #fff);
            color: var(--danger-color);
            border-color: var(--danger-color);
            transition: all 0.2s ease;
            padding: 0.4rem 0.6rem;
            font-size: 0.9rem;
            cursor: pointer;
            position: relative;
            z-index: 5;
            min-width: 40px;
            min-height: 36px;
        }

        .btn-outline-danger:hover {
            background: linear-gradient(135deg, var(--danger-color), #ff4444);
            color: white;
            box-shadow: 0 2px 8px rgba(224, 49, 49, 0.25);
            transform: scale(1.05);
        }

        .btn-outline-danger:active {
            transform: scale(0.95);
        }

        /* 操作列确保有足够空间 */
        .table td:last-child {
            text-align: center;
            vertical-align: middle;
            padding: 0.5rem !important;
        }

        /* 表格行确保操作按钮可见 */
        .table tbody tr {
            position: relative;
        }

        .table tbody tr td:last-child {
            position: relative;
            z-index: 10;
        }

        /* 自动刷新状态指示器 */
        .auto-refresh-status {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, var(--success-color), #12b886);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .auto-refresh-status .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* 响应式优化 */
        @media (max-width: 1200px) {
            .table th:nth-child(2) { /* 键名列 */
                width: 50%;
            }
            
            .table th:nth-child(4) { /* 大小列 */
                width: 18%;
            }
        }

        @media (max-width: 768px) {
            .table th:nth-child(1) { /* 过期时间 */
                width: 18%;
                min-width: 100px;
            }
            
            .table th:nth-child(2) { /* 键名列 */
                width: 45%;
                min-width: 200px;
            }
            
            .table th:nth-child(3) { /* 类型 */
                width: 12%;
                min-width: 60px;
            }
            
            .table th:nth-child(4) { /* 大小 */
                width: 20%;
                min-width: 80px;
            }
            
            .table th:nth-child(5) { /* 操作 */
                width: 5%;
                min-width: 60px;
            }
            
            .auto-refresh-status {
                top: 10px;
                right: 10px;
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- 自动刷新状态指示器 -->
        <div class="auto-refresh-status" id="autoRefreshStatus">
            <div class="spinner"></div>
            <span>自动刷新中...</span>
        </div>

        <!-- 标题卡片 -->
        <div class="title-card">
            <h2>
                <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>">
                    <i class='bx bx-data'></i>Redis 监控面板
                </a>
            </h2>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <i class='bx bx-error-circle'></i> <?php echo $error; ?>
            </div>
        <?php else: ?>
            <!-- 统计卡片 -->
            <div class="stats-container">
                <div class="stats-card">
                    <h6 class="card-subtitle">缓存键数量</h6>
                    <h2 class="card-title"><?php echo count($allKeys); ?></h2>
                </div>
                <div class="stats-card">
                    <h6 class="card-subtitle">已用内存</h6>
                    <h2 class="card-title"><?php echo formatBytes($info['used_memory']); ?></h2>
                </div>
                <div class="stats-card">
                    <h6 class="card-subtitle">命中率</h6>
                    <h2 class="card-title"><?php echo calculateHitRate($info); ?>%</h2>
                </div>
                <div class="stats-card">
                    <h6 class="card-subtitle">客户端连接数</h6>
                    <h2 class="card-title"><?php echo $info['connected_clients']; ?></h2>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">缓存键列表</h5>
                        <div class="search-box">
                            <i class='bx bx-search'></i>
                            <input type="text" class="form-control" placeholder="搜索键名..." id="keySearch">
                            <button type="button" class="btn btn-sm btn-outline-secondary clear-search" 
                                    id="clearSearch" style="display: none; margin-left: 0.5rem;">
                                <i class='bx bx-x'></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- 分组按钮 -->
                <div class="btn-group w-100">
                    <?php foreach ($groupedKeys as $prefix => $keys): ?>
                    <button class="btn group-btn <?php echo $selectedPrefix === $prefix ? 'active' : ''; ?>" 
                            data-prefix="<?php echo $prefix; ?>">
                        <?php echo $prefix; ?> 
                        <span class="badge bg-secondary"><?php echo count($keys); ?></span>
                    </button>
                    <?php endforeach; ?>
                    <button class="btn group-btn <?php echo $selectedPrefix === 'all' ? 'active' : ''; ?>" 
                            data-prefix="all">
                        全部 
                        <span class="badge bg-secondary"><?php echo count($allKeys); ?></span>
                    </button>
                </div>

                <!-- 表格部分 -->
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="text-center">过期时间</th>
                                <th>键名</th>
                                <th class="text-center">类型</th>
                                <th class="text-center">大小</th>
                                <th class="text-center">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pageKeys as $key): 
                                $parts = explode(':', $key);
                                $prefix = count($parts) > 1 ? $parts[0] . ':' : (explode('_', $key)[0] . '_');
                                $ttl = $redis->ttl($key);
                                $type = $redis->type($key);
                                $size = strlen($redis->get($key));
                                
                                $ttlClass = 'ttl-good';
                                if ($ttl < 0) {
                                    $ttlClass = 'ttl-expired';
                                } elseif ($ttl < 300) {
                                    $ttlClass = 'ttl-warning';
                                }
                            ?>
                            <tr class="key-row" data-key="<?php echo htmlspecialchars($key); ?>" 
                                data-prefix="<?php echo $prefix; ?>">
                                <td class="text-center">
                                    <span class="badge <?php echo $ttlClass; ?>">
                                        <?php echo $ttl < 0 ? '永久' : $ttl . '秒'; ?>
                                    </span>
                                </td>
                                <td class="key-name" title="<?php echo htmlspecialchars($key); ?>">
                                    <?php echo htmlspecialchars($key); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?php echo $type; ?></span>
                                </td>
                                <td class="text-center size-info">
                                    <?php echo formatBytes($size); ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-danger delete-key" title="删除此键" 
                                            onclick="deleteKey('<?php echo htmlspecialchars($key); ?>', this)">
                                        <i class='bx bx-trash'></i>
                                    </button>
                                </td>
                            </tr>
                            <tr class="content-row" style="display: none;">
                                <td colspan="5">
                                    <div class="content-preview">
                                        <pre class="mb-0"></pre>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <!-- 分页控件 -->
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center p-3 border-top">
                        <div class="pagination-info mb-2 mb-md-0">
                            共 <span class="total-keys"><?php echo $totalKeys; ?></span> 个键
                            <?php if ($totalPages > 0): ?>
                             / 第 <span class="current-page"><?php echo $currentPage; ?></span> 页
                             / 共 <span class="total-pages"><?php echo $totalPages; ?></span> 页
                            <?php endif; ?>
                        </div>
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="页面导航" class="d-flex justify-content-center">
                            <ul class="pagination mb-0">
                                <?php if ($currentPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?php echo $selectedPrefix !== 'all' ? '&prefix=' . urlencode($selectedPrefix) : ''; ?>" aria-label="首页">
                                        <i class='bx bx-chevrons-left'></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $currentPage - 1; ?><?php echo $selectedPrefix !== 'all' ? '&prefix=' . urlencode($selectedPrefix) : ''; ?>" aria-label="上一页">
                                        <i class='bx bx-chevron-left'></i>
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php foreach (generatePagination($currentPage, $totalPages) as $page): ?>
                                    <?php if ($page === '...'): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php else: ?>
                                        <li class="page-item <?php echo $page === $currentPage ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page; ?><?php echo $selectedPrefix !== 'all' ? '&prefix=' . urlencode($selectedPrefix) : ''; ?>">
                                                <?php echo $page; ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <?php if ($currentPage < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $currentPage + 1; ?><?php echo $selectedPrefix !== 'all' ? '&prefix=' . urlencode($selectedPrefix) : ''; ?>" aria-label="下一页">
                                        <i class='bx bx-chevron-right'></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo $selectedPrefix !== 'all' ? '&prefix=' . urlencode($selectedPrefix) : ''; ?>" aria-label="末页">
                                        <i class='bx bx-chevrons-right'></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 添加页脚 -->
        <footer class="footer mt-4 py-3 text-center">
            <div class="container">
                <span class="text-muted">© <?php echo date('Y'); ?> Redis Monitor</span>
                <span class="mx-2">|</span>
                <a href="https://github.com/vbskycn" target="_blank" class="text-decoration-none">
                    <i class='bx bxl-github'></i> GitHub
                </a>
            </div>
        </footer>
    </div>

    <script src="https://lf26-cdn-tos.bytecdntp.com/cdn/expire-1-M/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // 键行点击事件
        document.querySelectorAll('.key-row').forEach(row => {
            row.addEventListener('click', function(e) {
                if (e.target.closest('.delete-key')) return;
                
                const key = this.dataset.key;
                const contentRow = this.nextElementSibling;
                const allContentRows = document.querySelectorAll('.content-row');
                
                // 关闭其他已打开的内容行
                allContentRows.forEach(r => {
                    if (r !== contentRow) r.style.display = 'none';
                });

                if (contentRow.style.display === 'none') {
                    // 加载内容
                    fetch(`?action=get_key_info&key=${encodeURIComponent(key)}`)
                        .then(response => response.json())
                        .then(data => {
                            contentRow.querySelector('pre').textContent = data.value;
                            contentRow.style.display = 'table-row';
                            // 添加展开动画
                            contentRow.style.opacity = '0';
                            contentRow.style.transform = 'translateY(-10px)';
                            setTimeout(() => {
                                contentRow.style.transition = 'all 0.3s ease';
                                contentRow.style.opacity = '1';
                                contentRow.style.transform = 'translateY(0)';
                            }, 10);
                        })
                        .catch(error => {
                            console.error('加载键信息失败:', error);
                            contentRow.querySelector('pre').textContent = '加载失败，请重试';
                            contentRow.style.display = 'table-row';
                        });
                } else {
                    // 添加收起动画
                    contentRow.style.transition = 'all 0.3s ease';
                    contentRow.style.opacity = '0';
                    contentRow.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        contentRow.style.display = 'none';
                    }, 300);
                }
            });
        });

        // 删除键
        function deleteKey(key, button) {
            if (!confirm('确定要删除这个键吗？')) return;

            const row = button.closest('.key-row');

            fetch(`?action=delete_key&key=${encodeURIComponent(key)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 添加删除动画
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(-100px)';
                        setTimeout(() => {
                            row.nextElementSibling.remove();
                            row.remove();
                            // 更新统计信息
                            updateStats();
                        }, 300);
                    } else {
                        alert('删除失败：' + (data.error || '未知错误'));
                    }
                })
                .catch(error => {
                    console.error('删除键失败:', error);
                    alert('删除失败，请重试');
                });
        }

        // 搜索功能优化
        let currentSearchText = '';
        let isSearching = false;

        document.getElementById('keySearch').addEventListener('input', function() {
            const searchText = this.value.toLowerCase();
            currentSearchText = searchText;
            isSearching = searchText.length > 0;
            
            performSearch(searchText);
        });

        // 执行搜索
        function performSearch(searchText) {
            const rows = document.querySelectorAll('.key-row');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const key = row.dataset.key.toLowerCase();
                const contentRow = row.nextElementSibling;
                const isVisible = key.includes(searchText);
                
                row.style.display = isVisible ? '' : 'none';
                if (contentRow) {
                    contentRow.style.display = 'none';
                }
                
                if (isVisible) {
                    visibleCount++;
                    // 添加搜索高亮效果
                    if (searchText) {
                        row.classList.add('search-highlight');
                        // 高亮匹配的文本
                        highlightSearchText(row, searchText);
                    } else {
                        row.classList.remove('search-highlight');
                        row.style.backgroundColor = '';
                        row.style.borderLeft = '';
                    }
                } else {
                    row.classList.remove('search-highlight');
                    row.style.backgroundColor = '';
                    row.style.borderLeft = '';
                }
            });
            
            // 更新可见行数显示
            updateVisibleCount(visibleCount);
            
            // 更新搜索状态指示器
            updateSearchStatus(searchText);
            
            // 显示搜索结果统计
            showSearchResults(visibleCount, searchText);
        }

        // 高亮搜索文本
        function highlightSearchText(row, searchText) {
            const keyCell = row.querySelector('td:nth-child(2)'); // 键名列
            if (keyCell && searchText) {
                const originalText = keyCell.textContent;
                const highlightedText = originalText.replace(
                    new RegExp(searchText, 'gi'),
                    match => `<mark style="background-color: #ffeb3b; padding: 0.1rem 0.2rem; border-radius: 3px;">${match}</mark>`
                );
                keyCell.innerHTML = highlightedText;
            }
        }

        // 显示搜索结果统计
        function showSearchResults(count, searchText) {
            if (searchText && count > 0) {
                // 在搜索框下方显示搜索结果统计
                let statusElement = document.querySelector('.search-results-status');
                if (!statusElement) {
                    statusElement = document.createElement('div');
                    statusElement.className = 'search-results-status';
                    statusElement.style.cssText = `
                        position: absolute;
                        top: 100%;
                        left: 0;
                        right: 0;
                        background: linear-gradient(135deg, var(--success-color), #12b886);
                        color: white;
                        padding: 0.3rem 0.8rem;
                        border-radius: 0 0 8px 8px;
                        font-size: 0.8rem;
                        text-align: center;
                        z-index: 100;
                        margin-top: 2px;
                    `;
                    document.querySelector('.search-box').appendChild(statusElement);
                }
                statusElement.textContent = `找到 ${count} 个匹配结果`;
                statusElement.style.display = 'block';
                
                // 3秒后自动隐藏
                setTimeout(() => {
                    statusElement.style.display = 'none';
                }, 3000);
            } else if (searchText && count === 0) {
                // 显示无结果提示
                let noResultsElement = document.querySelector('.search-no-results');
                if (!noResultsElement) {
                    noResultsElement = document.createElement('div');
                    noResultsElement.className = 'search-no-results';
                    noResultsElement.style.cssText = `
                        position: absolute;
                        top: 100%;
                        left: 0;
                        right: 0;
                        background: linear-gradient(135deg, var(--warning-color), #ffb84d);
                        color: white;
                        padding: 0.3rem 0.8rem;
                        border-radius: 0 0 8px 8px;
                        font-size: 0.8rem;
                        text-align: center;
                        z-index: 100;
                        margin-top: 2px;
                    `;
                    document.querySelector('.search-box').appendChild(noResultsElement);
                }
                noResultsElement.textContent = `未找到包含 "${searchText}" 的键`;
                noResultsElement.style.display = 'block';
                
                // 3秒后自动隐藏
                setTimeout(() => {
                    noResultsElement.style.display = 'none';
                }, 3000);
            }
        }

        // 更新搜索状态
        function updateSearchStatus(searchText) {
            const searchBox = document.getElementById('keySearch');
            const clearSearchBtn = document.getElementById('clearSearch');
            if (searchText) {
                searchBox.style.borderColor = 'var(--primary-color)';
                searchBox.style.boxShadow = '0 0 0 3px rgba(84, 105, 212, 0.1)';
                clearSearchBtn.style.display = 'inline-block';
            } else {
                searchBox.style.borderColor = 'var(--border-color)';
                searchBox.style.boxShadow = 'none';
                clearSearchBtn.style.display = 'none';
            }
        }

        // 更新可见行数
        function updateVisibleCount(count) {
            const totalElement = document.querySelector('.total-keys');
            if (totalElement) {
                if (isSearching) {
                    totalElement.textContent = `${count} / ${document.querySelectorAll('.key-row').length}`;
                    totalElement.style.color = 'var(--primary-color)';
                    totalElement.style.fontWeight = '600';
                } else {
                    totalElement.textContent = count;
                    totalElement.style.color = '';
                    totalElement.style.fontWeight = '';
                }
            }
        }

        // 更新统计信息
        function updateStats() {
            if (isSearching) {
                // 如果正在搜索，不更新总数
                return;
            }
            
            const visibleRows = document.querySelectorAll('.key-row[style*="display: none"]');
            const totalRows = document.querySelectorAll('.key-row').length;
            const visibleCount = totalRows - visibleRows.length;
            
            updateVisibleCount(visibleCount);
        }

        // 分组按钮点击事件
        document.querySelectorAll('.group-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const prefix = this.dataset.prefix;
                const url = new URL(window.location.href);
                url.searchParams.set('prefix', prefix);
                url.searchParams.delete('page'); // 切换分组时重置页码
                window.location.href = url.toString();
            });
        });

        // 清除搜索按钮点击事件
        document.getElementById('clearSearch').addEventListener('click', function() {
            document.getElementById('keySearch').value = '';
            currentSearchText = '';
            isSearching = false;
            performSearch(''); // 重新执行搜索，确保所有行都显示
            updateSearchStatus('');
            updateVisibleCount(document.querySelectorAll('.key-row').length); // 显示所有行
        });

        // 更新数据函数
        function updateData() {
            const currentUrl = new URL(window.location.href);
            const activeGroup = document.querySelector('.group-btn.active');
            const selectedPrefix = activeGroup ? activeGroup.dataset.prefix : 'all';
            
            fetch(currentUrl)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // 更新统计卡片
                    document.querySelectorAll('.card-title').forEach((title, index) => {
                        const newTitle = doc.querySelectorAll('.card-title')[index];
                        if (newTitle) {
                            title.innerHTML = newTitle.innerHTML;
                        }
                    });
                    
                    // 更新分组按钮上的数字
                    const newButtons = doc.querySelectorAll('.group-btn');
                    document.querySelectorAll('.group-btn').forEach((btn, index) => {
                        const newBadge = newButtons[index]?.querySelector('.badge');
                        if (newBadge) {
                            btn.querySelector('.badge').textContent = newBadge.textContent;
                        }
                    });
                    
                    // 更新表格内容
                    const tbody = document.querySelector('tbody');
                    const newTbody = doc.querySelector('tbody');
                    if (tbody && newTbody) {
                        // 保存当前展开的行
                        const expandedKeys = Array.from(document.querySelectorAll('.content-row'))
                            .filter(row => row.style.display !== 'none')
                            .map(row => row.previousElementSibling.dataset.key);
                        
                        // 更新内容
                        tbody.innerHTML = newTbody.innerHTML;
                        
                        // 重新绑定事件
                        bindEvents();
                        
                        // 恢复展开的行
                        expandedKeys.forEach(key => {
                            const row = document.querySelector(`.key-row[data-key="${key}"]`);
                            if (row && row.style.display !== 'none') {
                                const contentRow = row.nextElementSibling;
                                if (contentRow) {
                                    contentRow.style.display = 'table-row';
                                    // 重新加载内容
                                    fetch(`?action=get_key_info&key=${encodeURIComponent(key)}`)
                                        .then(response => response.json())
                                        .then(data => {
                                            contentRow.querySelector('pre').textContent = data.value;
                                        });
                                }
                            }
                        });
                        
                        // 如果正在搜索，重新应用搜索过滤
                        if (isSearching && currentSearchText) {
                            setTimeout(() => {
                                performSearch(currentSearchText);
                            }, 100);
                        }
                    }
                    
                    // 更新自动刷新状态
                    updateRefreshStatus();
                })
                .catch(error => {
                    console.error('自动刷新失败:', error);
                    updateRefreshStatus(false);
                });
        }

        // 更新自动刷新状态
        function updateRefreshStatus(success = true) {
            const statusElement = document.getElementById('autoRefreshStatus');
            if (statusElement) {
                if (success) {
                    statusElement.innerHTML = '<div class="spinner"></div><span>自动刷新中...</span>';
                    statusElement.style.background = 'linear-gradient(135deg, var(--success-color), #12b886)';
                } else {
                    statusElement.innerHTML = '<i class="bx bx-error"></i><span>刷新失败</span>';
                    statusElement.style.background = 'linear-gradient(135deg, var(--danger-color), #ff4444)';
                }
            }
        }

        // 绑定所有事件
        function bindEvents() {
            // 重新绑定行点击事件
            document.querySelectorAll('.key-row').forEach(row => {
                row.addEventListener('click', function(e) {
                    if (e.target.closest('.delete-key')) return;
                    
                    const key = this.dataset.key;
                    const contentRow = this.nextElementSibling;
                    const allContentRows = document.querySelectorAll('.content-row');
                    
                    // 关闭其他已打开的内容行
                    allContentRows.forEach(r => {
                        if (r !== contentRow) r.style.display = 'none';
                    });

                    if (contentRow && contentRow.style.display === 'none') {
                        // 加载内容
                        fetch(`?action=get_key_info&key=${encodeURIComponent(key)}`)
                            .then(response => response.json())
                            .then(data => {
                                contentRow.querySelector('pre').textContent = data.value;
                                contentRow.style.display = 'table-row';
                                // 添加展开动画
                                contentRow.style.opacity = '0';
                                contentRow.style.transform = 'translateY(-10px)';
                                setTimeout(() => {
                                    contentRow.style.transition = 'all 0.3s ease';
                                    contentRow.style.opacity = '1';
                                    contentRow.style.transform = 'translateY(0)';
                                }, 10);
                            });
                    } else if (contentRow) {
                        // 添加收起动画
                        contentRow.style.transition = 'all 0.3s ease';
                        contentRow.style.opacity = '0';
                        contentRow.style.transform = 'translateY(-10px)';
                        setTimeout(() => {
                            contentRow.style.display = 'none';
                        }, 300);
                    }
                });
            });
        }

        // 初始绑定事件
        bindEvents();

        // 每秒更新一次数据
        setInterval(updateData, 1000);
    </script>
</body>
</html> 