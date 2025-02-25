<?php
require_once 'config.php';
require_once 'Database.php';

// 设置更大的内存限制和执行时间
ini_set('memory_limit', '512M');
set_time_limit(300);

// 获取导出格式
$format = $_GET['format'] ?? 'txt';
if (!in_array($format, ['txt', 'csv'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid format');
}

try {
    $db = new Database();
    // 获取所有数据
    $data = $db->getAllData(1, PHP_INT_MAX);
    
    if ($format === 'txt') {
        // 导出TXT
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="短剧数据_' . date('Y-m-d') . '.txt"');
        
        // 写入标题行
        echo "标题\t链接\t更新时间\n";
        
        // 写入数据行
        foreach ($data as $item) {
            echo implode("\t", [
                $item['title'],
                $item['cover'],
                $item['created_at']
            ]) . "\n";
        }
    } else {
        // 导出CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="短剧数据_' . date('Y-m-d') . '.csv"');
        
        // 添加 BOM 以支持 Excel 中文显示
        echo "\xEF\xBB\xBF";
        
        // 创建一个输出流
        $output = fopen('php://output', 'w');
        
        // 写入标题行
        fputcsv($output, ['标题', '链接', '更新时间']);
        
        // 写入数据行
        foreach ($data as $item) {
            fputcsv($output, [
                $item['title'],
                $item['cover'],
                $item['created_at']
            ]);
        }
        
        fclose($output);
    }
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Export failed: ' . $e->getMessage());
} 