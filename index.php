<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'Database.php';

// 添加加密函数
function encryptUrl($url) {
    $key = "YOUR_SECRET_KEY"; // 建议使用复杂的密钥
    $ivlen = openssl_cipher_iv_length($cipher = "AES-256-CBC");
    $iv = openssl_random_pseudo_bytes($ivlen);
    $encrypted = openssl_encrypt($url, $cipher, $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

// 添加严格的环境检测函数
function isMobile() {
    $useragent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    
    // 检查是否是微信环境
    $isWechat = strpos($useragent, 'MicroMessenger') !== false;
    
    // 检查是否是QQ环境（更严格的检测）
    $isQQ = false;
    if (strpos($useragent, 'Mobile') !== false) {  // 必须是移动设备
        if (strpos($useragent, 'QQ/') !== false) {  // QQ内置浏览器
            $isQQ = true;
        } elseif (strpos($useragent, 'MQQBrowser') !== false && strpos($useragent, ' QQ') !== false) {  // QQ浏览器且在QQ内
            $isQQ = true;
        }
    }
    
    // 检查是否是模拟器或开发者工具
    $isFake = (strpos($useragent, 'Chrome') !== false && strpos($useragent, 'Mobile') !== false && strpos($useragent, 'Safari') === false) || 
              strpos($useragent, 'DevTools') !== false;
    
    // 只有在真实的微信或QQ环境中才返回true
    return ($isWechat || $isQQ) && !$isFake;
}

// 添加更详细的环境检测函数
function getEnvironmentType() {
    $useragent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    
    // 检查是否是模拟器或开发者工具
    $isFake = strpos($useragent, 'Chrome/') !== false && 
              (strpos($useragent, 'Mobile Safari') === false || 
               strpos($useragent, 'DevTools') !== false);
    
    if ($isFake) {
        return 'emulator';
    }
    
    if (strpos($useragent, 'MicroMessenger') !== false) {
        return 'wechat';
    } elseif ((strpos($useragent, 'QQ/') !== false && strpos($useragent, 'Mobile') !== false) || 
              (strpos($useragent, 'MQQBrowser') !== false && strpos($useragent, ' QQ') !== false && strpos($useragent, 'Mobile') !== false)) {
        return 'qq';
    } elseif (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $useragent)) {
        return 'mobile';
    } else {
        return 'desktop';
    }
}

// 获取当前页码
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$pageSize = 20;

// 获取搜索关键词
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

// 获取网盘类型筛选
$diskType = isset($_GET['disk']) ? $_GET['disk'] : 'all';

// 添加网盘类型判断函数
function getDiskType($url) {
    if (strpos($url, 'pan.baidu.com') !== false) {
        return ['name' => '百度网盘APP', 'color' => '#06a7ff', 'icon' => 'images/bd.png'];
    } elseif (strpos($url, 'pan.xunlei.com') !== false) {
        return ['name' => '迅雷APP', 'color' => '#0088ff', 'icon' => 'images/xl.png'];
    } elseif (strpos($url, 'pan.quark.cn') !== false) {
        return ['name' => '夸克APP', 'color' => '#0088ff', 'icon' => 'images/kk.png'];
    } elseif (strpos($url, 'drive.uc.cn') !== false) {
        return ['name' => 'UC浏览器APP', 'color' => '#0088ff', 'icon' => 'images/uc.png'];
    } else {
        return ['name' => '网盘', 'color' => '#0088ff', 'icon' => 'images/all.png'];
    }
}

try {
    $db = new Database();
    
    // 获取所有数据
    if (!empty($keyword)) {
        $allData = $db->searchAllData($keyword);
    } else {
        $allData = $db->getAllDataWithoutPaging();
    }
    
    // 预处理各种网盘类型的数据
    $diskData = [
        'all' => $allData,
        'baidu' => [],
        'xunlei' => [],
        'quark' => [],
        'uc' => []
    ];
    
    // 对所有数据进行分类
    foreach ($allData as $item) {
        $link = $item['cover'];
        if (strpos($link, 'pan.baidu.com') !== false) {
            $diskData['baidu'][] = $item;
        } elseif (strpos($link, 'pan.xunlei.com') !== false) {
            $diskData['xunlei'][] = $item;
        } elseif (strpos($link, 'pan.quark.cn') !== false) {
            $diskData['quark'][] = $item;
        } elseif (strpos($link, 'drive.uc.cn') !== false) {
            $diskData['uc'][] = $item;
        }
    }
    
    // 获取当前选中网盘类型的数据
    $currentData = $diskData[$diskType];
    
    // 计算总数和分页
    $total = count($currentData);
    $totalPages = ceil($total / $pageSize);
    
    // 获取当前页的数据
    $start = ($page - 1) * $pageSize;
    $data = array_slice($currentData, $start, $pageSize);
    
    $result = [
        'total' => $total,
        'data' => $data,
        'totalPages' => $totalPages,
        'diskCounts' => [
            'all' => count($diskData['all']),
            'baidu' => count($diskData['baidu']),
            'xunlei' => count($diskData['xunlei']),
            'quark' => count($diskData['quark']),
            'uc' => count($diskData['uc'])
        ]
    ];
} catch (Exception $e) {
    $error = $e->getMessage();
}

// 在HTML部分之前的PHP代码末尾添加JavaScript解密函数
$decryptScript = <<<EOT
<script>
function decryptUrl(encryptedData) {
    try {
        // 这里只返回一个随机字符串，真实链接通过AJAX获取
        return Math.random().toString(36).substring(7);
    } catch(e) {
        console.error('解密失败');
        return '';
    }
}

// 处理链接点击
function handleLinkClick(e) {
    e.preventDefault();
    const encryptedUrl = e.currentTarget.getAttribute('data-encrypted');
    if (!encryptedUrl) return;

    // 发送AJAX请求获取真实链接
    fetch('get_url.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'encrypted=' + encodeURIComponent(encryptedUrl)
    })
    .then(response => response.json())
    .then(data => {
        if (data.url) {
            if (!isMobileDevice()) {
                showQRCode(data.url);
            } else {
                window.location.href = data.url;
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// 检测是否为移动设备
function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

// 页面加载完成后绑定事件
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.item').forEach(item => {
        item.addEventListener('click', handleLinkClick);
    });
});
</script>
EOT;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>资源持续更新中....</title>
    <style>
        :root {
            --system-font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --background-color: #f5f7fa;
            --text-color: #1d1d1f;
            --secondary-text: #86868b;
            --border-color: #d2d2d7;
            --accent-color: #0071e3;
            --hover-color: #0077ED;
            --card-background: white;
            --search-background: white;
            --overlay-background: rgba(0, 0, 0, 0.3);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --background-color: #000000;
                --text-color: #f5f5f7;
                --secondary-text: #86868b;
                --border-color: #424245;
                --accent-color: #2997ff;
                --hover-color: #0077ED;
                --card-background: #1c1c1e;
                --search-background: #1c1c1e;
                --overlay-background: rgba(0, 0, 0, 0.6);
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        body {
            font-family: var(--system-font);
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            max-width: 980px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            padding: 40px 0;
        }

        .header h1 {
            font-size: 40px;
            font-weight: 600;
            letter-spacing: -0.015em;
        }

        .stats {
            text-align: center;
            color: var(--secondary-text);
            font-size: 17px;
            margin-bottom: 30px;
        }

        .list {
            background: var(--card-background);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        }

        .item {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.2s ease;
            text-decoration: none;
            color: inherit;
        }

        .item:last-child {
            border-bottom: none;
        }

        .item:hover {
            background-color: var(--border-color);
        }

        .item-title {
            font-size: 17px;
            color: var(--text-color);
            font-weight: 400;
        }

        .item:hover .item-title {
            color: var(--accent-color);
        }

        .item-info {
            font-size: 15px;
            color: var(--secondary-text);
            padding: 4px 12px;
            border-radius: 14px;
            background: #f5f5f7;
        }

        .pagination {
            margin-top: 40px;
            text-align: center;
            font-size: 15px;
        }

        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 16px;
            margin: 0 4px;
            border-radius: 980px;
            text-decoration: none;
            color: var(--text-color);
            transition: all 0.2s ease;
        }

        .pagination a {
            background: var(--card-background);
            border: 1px solid var(--border-color);
        }

        .pagination a:hover {
            background: var(--border-color);
            border-color: var(--secondary-text);
        }

        .pagination .current {
            background: var(--accent-color);
            color: var(--card-background);
            border: 1px solid var(--accent-color);
        }

        .error {
            background: #fff2f2;
            color: #ff3b30;
            padding: 16px 24px;
            border-radius: 18px;
            margin-bottom: 30px;
            font-size: 15px;
        }

        .search-container {
            margin: 0 auto 40px;
            max-width: 680px;
            padding: 0 20px;
        }

        .search-form {
            position: relative;
            display: flex;
            gap: 10px;
        }

        .search-input-wrapper {
            position: relative;
            flex: 1;
        }

        .search-input {
            width: 100%;
            padding: 12px 44px;
            font-size: 17px;
            border: none;
            border-radius: 12px;
            background: var(--search-background);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            color: var(--text-color);
            font-family: var(--system-font);
        }

        .search-input:focus {
            outline: none;
            box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.2);
        }

        .search-input::placeholder {
            color: var(--secondary-text);
        }

        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            pointer-events: none;
        }

        .search-icon svg {
            width: 100%;
            height: 100%;
            fill: var(--secondary-text);
        }

        .search-button {
            padding: 12px 24px;
            font-size: 17px;
            border: none;
            border-radius: 12px;
            background: var(--accent-color);
            color: white;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: var(--system-font);
            white-space: nowrap;
        }

        .search-button:hover {
            background: var(--hover-color);
        }

        .search-button:active {
            transform: scale(0.98);
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .header {
                padding: 30px 0;
            }

            .header h1 {
                font-size: 32px;
            }

            .search-form {
                flex-direction: column;
            }

            .search-button {
                width: 100%;
                padding: 10px 20px;
            }

            .search-input {
                padding: 10px 40px;
                font-size: 16px;
            }
        }

        @media (max-width: 768px) and (prefers-color-scheme: dark) {
            .container {
                padding: 16px;
            }

            .search-input {
                background: var(--search-background);
            }

            .export-btn {
                background: var(--card-background);
            }
        }

        .export-btn {
            display: inline-block;
            margin-left: 15px;
            padding: 6px 16px;
            font-size: 15px;
            color: var(--accent-color);
            background: var(--card-background);
            border: 1px solid var(--accent-color);
            border-radius: 980px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: var(--system-font);
        }

        .export-btn:hover {
            background: var(--accent-color);
            color: var(--card-background);
        }

        .export-menu {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--card-background);
            padding: 24px;
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: none;
            color: var(--text-color);
        }

        .export-menu h3 {
            margin: 0 0 16px 0;
            font-size: 20px;
            font-weight: 600;
        }

        .export-options {
            display: flex;
            gap: 12px;
        }

        .export-option {
            padding: 12px 24px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: var(--card-background);
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: var(--system-font);
            color: var(--text-color);
        }

        .export-option:hover {
            border-color: var(--accent-color);
            color: var(--accent-color);
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--overlay-background);
            backdrop-filter: blur(4px);
            z-index: 999;
            display: none;
        }

        @media (max-width: 768px) {
            .export-btn {
                display: block;
                margin: 10px auto 0;
            }
        }

        .disclaimer {
            margin-top: 40px;
            padding: 25px;
            border-radius: 12px;
            background: var(--card-background);
            color: var(--secondary-text);
            font-size: 14px;
            line-height: 1.6;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .disclaimer-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .disclaimer-icon {
            width: 24px;
            height: 24px;
            color: var(--accent-color);
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .disclaimer h3 {
            color: var(--text-color);
            margin: 0;
            font-size: 16px;
            font-weight: 500;
            text-align: center;
        }

        .disclaimer p {
            margin: 0 0 15px 0;
            text-align: center;
            padding: 0 20px;
        }

        .disclaimer ul {
            margin: 0;
            padding-left: 40px;
            list-style-type: none;
            max-width: 600px;
            margin: 0 auto;
        }

        .disclaimer li {
            margin-bottom: 8px;
            position: relative;
        }

        .disclaimer li:before {
            content: "•";
            position: absolute;
            left: -20px;
            color: var(--accent-color);
        }

        .disclaimer li:last-child {
            margin-bottom: 0;
        }

        @media (max-width: 600px) {
            .disclaimer {
                margin-top: 30px;
                padding: 20px;
            }
            .disclaimer ul {
                padding-left: 34px;
            }
            .disclaimer p {
                padding-left: 0;
            }
        }

        .disk-filters {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }

        .disk-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--card-background);
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-color);
            position: relative;
        }

        .disk-btn:hover {
            border-color: var(--accent-color);
            color: var(--accent-color);
        }

        .disk-btn.active {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }

        .disk-icon {
            width: 20px;
            height: 20px;
            object-fit: contain;
        }

        .disk-count {
            display: none;
        }

        .disk-btn.active .disk-count {
            display: none;
        }

        @media (max-width: 768px) {
            .disk-filters {
                flex-wrap: wrap;
                gap: 10px;
                padding: 0 10px;
            }

            .disk-btn {
                padding: 8px 12px;
                font-size: 14px;
                flex: 1;
                justify-content: center;
            }
        }

        /* 删除重复的样式 */
        .disk-filter,
        .disk-button {
            display: none;
        }

        /* 更新弹窗样式 */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: var(--card-background, #fff);
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            max-width: 90%;
            width: 360px;
        }
        
        .modal-content h3 {
            margin: 0 0 20px 0;
            font-size: 18px;
            color: var(--text-color, #333);
        }

        #qrcode {
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            margin: 0 auto 20px;
            width: 180px;
            height: 180px;
            display: flex;
            justify-content: center;
            align-items: center;
            pointer-events: none;
        }

        #qrcode * {
            pointer-events: none !important;
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
        }

        #qrcode img {
            pointer-events: none !important;
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
            -webkit-touch-callout: none !important;
            -webkit-user-drag: none !important;
        }
        
        .modal-content p {
            margin: 0 0 20px 0;
            color: var(--text-color, #666);
            font-size: 14px;
        }
        
        .modal-content button {
            background: var(--primary-color, #0088ff);
            color: #fff;
            border: none;
            padding: 10px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .modal-content button:hover {
            opacity: 0.9;
            transform: scale(1.02);
        }

        @media (prefers-color-scheme: dark) {
            .modal-content {
                border: 1px solid var(--border-color);
            }
            #qrcode {
                border: 1px solid var(--border-color);
            }
        }

        /* 添加头像样式 */
        .avatar-container {
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
        }

        .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--accent-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .avatar:hover {
            transform: scale(1.05);
        }

        @media (max-width: 768px) {
            .avatar {
                width: 80px;
                height: 80px;
            }
        }

        /* 添加社交图标样式 */
        .social-icons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .social-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--card-background);
            border: 2px solid var(--accent-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .social-icon:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 113, 227, 0.2);
            background: var(--accent-color);
        }

        .social-icon img.icon {
            width: 24px;
            height: 24px;
            object-fit: contain;
        }

        /* 微信二维码弹窗样式 */
        #wechatModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        #wechatModal .modal-content {
            background: var(--card-background);
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            max-width: 90%;
            width: 300px;
        }

        #wechatModal img {
            width: 200px;
            height: 200px;
            object-fit: contain;
            margin: 20px 0;
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            .social-icons {
                gap: 15px;
            }

            .social-icon {
                width: 35px;
                height: 35px;
            }

            .social-icon img.icon {
                width: 20px;
                height: 20px;
            }
        }
    </style>
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f5f7fa" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
    
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
    // 禁用右键菜单
    document.oncontextmenu = function(e) {
        e.preventDefault();
        return false;
    };

    // 禁用F12、Ctrl+Shift+I、Ctrl+Shift+J、Ctrl+Shift+C、Ctrl+U
    document.onkeydown = function(e) {
        if (e.keyCode === 123 || // F12
            (e.ctrlKey && e.shiftKey && e.keyCode === 73) || // Ctrl+Shift+I
            (e.ctrlKey && e.shiftKey && e.keyCode === 74) || // Ctrl+Shift+J
            (e.ctrlKey && e.shiftKey && e.keyCode === 67) || // Ctrl+Shift+C
            (e.ctrlKey && e.keyCode === 85)) { // Ctrl+U
            e.preventDefault();
            return false;
        }
    };

    // 禁用开发者工具
    function disableDevTools() {
        const devtools = {
            isOpen: false,
            orientation: undefined
        };
        
        const threshold = 160;
        const emitEvent = (isOpen, orientation) => {
            devtools.isOpen = isOpen;
            devtools.orientation = orientation;
        };

        const checkDevTools = ({emitEvents = true} = {}) => {
            const widthThreshold = window.outerWidth - window.innerWidth > threshold;
            const heightThreshold = window.outerHeight - window.innerHeight > threshold;
            const orientation = widthThreshold ? 'vertical' : 'horizontal';

            if (
                !(heightThreshold && widthThreshold) &&
                ((window.Firebug && window.Firebug.chrome && window.Firebug.chrome.isInitialized) || widthThreshold || heightThreshold)
            ) {
                if (emitEvents && !devtools.isOpen) {
                    emitEvent(true, orientation);
                }
            } else {
                if (emitEvents && devtools.isOpen) {
                    emitEvent(false, undefined);
                }
            }
        };

        // 检测打开控制台的快捷键
        window.addEventListener('keydown', function(e) {
            if ((e.key === 'F12') || 
                (e.ctrlKey && e.shiftKey && e.key === 'I') || 
                (e.ctrlKey && e.shiftKey && e.key === 'J') || 
                (e.ctrlKey && e.shiftKey && e.key === 'C')) {
                e.preventDefault();
                return false;
            }
        });

        // 定期检查
        setInterval(checkDevTools, 2000);
    }

    // 页面加载完成后启用保护
    window.addEventListener('DOMContentLoaded', function() {
        setTimeout(disableDevTools, 1000);
    });
    </script>
</head>
<body>
    <!-- 添加复制保护 -->
    <div style="display:none;" aria-hidden="true">
        禁止查看源代码
        <?php
        // 随机生成大量注释，混淆源代码
        for($i = 0; $i < 1000; $i++) {
            echo "<!-- " . md5(uniqid()) . " -->\n";
        }
        ?>
    </div>
    
    <!-- 添加选中保护 -->
    <style>
        body {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
    </style>

    <script>
    // 禁止控制台打印
    console.log = function() {};
    console.info = function() {};
    console.warn = function() {};
    console.error = function() {};
    console.debug = function() {};
    
    // 清除控制台
    setInterval(function() {
        console.clear();
    }, 1000);
    </script>

    <!-- 更新弹窗HTML -->
    <div id="mobileModal" class="modal-overlay">
        <div class="modal-content">
            <h3 id="modalTitle">扫码访问</h3>
            <div id="qrcode"></div>
            <p id="modalTip">请使用手机扫描二维码访问</p>
            <button onclick="closeModal()">关闭</button>
        </div>
    </div>
    
    <div class="container">
        <div class="header">
            <div class="avatar-container">
                <img src="images/avatar.png" alt="头像" class="avatar">
            </div>
            <div class="social-icons">
                <a href="javascript:void(0);" class="social-icon" onclick="showWeChatQR()">
                    <img src="images/wechat.png" alt="微信" class="icon">
                </a>
                <a href="你的QQ链接" target="_blank" class="social-icon">
                    <img src="images/qq.png" alt="QQ" class="icon">
                </a>
                <a href="mailto:你的邮箱" class="social-icon">
                    <img src="images/email.png" alt="邮箱" class="icon">
                </a>
                <a href="你的微博地址" target="_blank" class="social-icon">
                    <img src="images/weibo.png" alt="微博" class="icon">
                </a>
            </div>
            <div class="disk-filters">
                <button class="disk-btn <?php echo $diskType === 'all' ? 'active' : ''; ?>" data-type="all">
                    <img src="images/all.png" alt="全部" class="disk-icon">
                </button>
                <button class="disk-btn <?php echo $diskType === 'baidu' ? 'active' : ''; ?>" data-type="baidu">
                    <img src="images/bd.png" alt="百度网盘" class="disk-icon">
                </button>
                <button class="disk-btn <?php echo $diskType === 'xunlei' ? 'active' : ''; ?>" data-type="xunlei">
                    <img src="images/xl.png" alt="迅雷网盘" class="disk-icon">
                </button>
                <button class="disk-btn <?php echo $diskType === 'quark' ? 'active' : ''; ?>" data-type="quark">
                    <img src="images/kk.png" alt="夸克网盘" class="disk-icon">
                </button>
                <button class="disk-btn <?php echo $diskType === 'uc' ? 'active' : ''; ?>" data-type="uc">
                    <img src="images/uc.png" alt="UC网盘" class="disk-icon">
                </button>
            </div>
        </div>

        <div class="search-container">
            <form class="search-form" method="GET" action="">
                <div class="search-input-wrapper">
                    <div class="search-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                    </div>
                    <input 
                        type="search" 
                        name="keyword" 
                        class="search-input" 
                        placeholder="搜索资源..." 
                        value="<?php echo isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : ''; ?>"
                        autocomplete="off"
                    >
                    <input type="hidden" name="disk" id="diskType" value="<?php echo isset($_GET['disk']) ? htmlspecialchars($_GET['disk']) : 'all'; ?>">
                </div>
                <button type="submit" class="search-button">搜索</button>
            </form>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <div class="list">
                <?php foreach ($result['data'] as $item): ?>
                    <?php
                        // 清理并加密链接
                        $cleanLink = $item['cover'];
                        if (strpos($cleanLink, 'pan.baidu.com') !== false) {
                            if (preg_match('/(https:\/\/pan\.baidu\.com\/s\/[^\s]+?)(?:\s+提取码:\s*([^\s]+))?$/i', $cleanLink, $matches)) {
                                if (strpos($matches[1], '?pwd=') !== false) {
                                    $cleanLink = $matches[1];
                                } 
                                else if (isset($matches[2])) {
                                    $cleanLink = $matches[1] . '?pwd=' . $matches[2];
                                }
                                else {
                                    $cleanLink = $matches[1];
                                }
                            }
                        }
                        // 加密链接
                        $encryptedLink = encryptUrl($cleanLink);
                    ?>
                    <a class="item" 
                       data-encrypted="<?php echo htmlspecialchars($encryptedLink); ?>"
                       href="javascript:void(0);">
                        <span class="item-title">
                            <?php echo htmlspecialchars($item['title']); ?>
                        </span>
                        <?php 
                            $link = $cleanLink;
                            $icon = '';
                            if (strpos($link, 'pan.xunlei.com') !== false) {
                                $icon = 'xl.png';
                            } elseif (strpos($link, 'pan.quark.cn') !== false) {
                                $icon = 'kk.png';
                            } elseif (strpos($link, 'pan.baidu.com') !== false) {
                                $icon = 'bd.png';
                            } elseif (strpos($link, 'drive.uc.cn') !== false) {
                                $icon = 'uc.png';
                            }
                            if ($icon) {
                                echo '<img src="images/' . $icon . '" alt="网盘图标" style="width: 16px; height: 16px; vertical-align: middle; margin-left: 8px;">';
                            }
                        ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1<?php echo $keyword ? '&keyword='.urlencode($keyword) : ''; ?><?php echo isset($_GET['disk']) ? '&disk='.$_GET['disk'] : ''; ?>">首页</a>
                    <a href="?page=<?php echo $page - 1; ?><?php echo $keyword ? '&keyword='.urlencode($keyword) : ''; ?><?php echo isset($_GET['disk']) ? '&disk='.$_GET['disk'] : ''; ?>">上一页</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($result['totalPages'], $page + 2);
                
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?><?php echo $keyword ? '&keyword='.urlencode($keyword) : ''; ?><?php echo isset($_GET['disk']) ? '&disk='.$_GET['disk'] : ''; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $result['totalPages']): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $keyword ? '&keyword='.urlencode($keyword) : ''; ?><?php echo isset($_GET['disk']) ? '&disk='.$_GET['disk'] : ''; ?>">下一页</a>
                    <a href="?page=<?php echo $result['totalPages']; ?><?php echo $keyword ? '&keyword='.urlencode($keyword) : ''; ?><?php echo isset($_GET['disk']) ? '&disk='.$_GET['disk'] : ''; ?>">末页</a>
                <?php endif; ?>
            </div>

            <?php if (!$keyword): ?>
                <div class="disclaimer">
                    <div class="disclaimer-header">
                        <svg class="disclaimer-icon" viewBox="0 0 24 24" fill="none">
                            <path d="M12 9v4M12 17.01l.01-.011M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" 
                                stroke="currentColor" 
                                stroke-width="1.5" 
                                stroke-linecap="round" 
                                stroke-linejoin="round"/>
                        </svg>
                        <h3>免责声明</h3>
                    </div>
                    <p>本站仅提供资源搜索服务，所有资源来自网络收集整理。</p>
                    <p>本站不存储任何软件安装包，所有资源均来自第三方网盘分享。</p>
                    <p>搜索结果的准确性、完整性、及时性等均由资源分享方负责。</p>
                    <p>使用本站服务时产生的任何版权问题与本站无关。</p>
                    <p>如果您认为搜索结果侵犯了您的权益，请与相关网盘分享方联系。</p>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const buttons = document.querySelectorAll('.disk-btn');
        const diskTypeInput = document.getElementById('diskType');
        const searchForm = document.querySelector('.search-form');

        // 从 URL 获取当前的网盘类型
        const urlParams = new URLSearchParams(window.location.search);
        const currentDisk = urlParams.get('disk') || 'all';

        // 设置正确的按钮激活状态
        buttons.forEach(button => {
            if (button.dataset.type === currentDisk) {
                button.classList.add('active');
            } else {
                button.classList.remove('active');
            }
        });

        buttons.forEach(button => {
            button.addEventListener('click', function() {
                const type = this.dataset.type;
                
                // 更新隐藏输入字段的值
                diskTypeInput.value = type;
                
                // 移除所有按钮的 active 类
                buttons.forEach(btn => btn.classList.remove('active'));
                // 添加当前按钮的 active 类
                this.classList.add('active');

                // 提交表单以刷新页面
                searchForm.submit();
            });
        });
    });

    // 获取当前环境是否为移动设备
    function isMobile() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }
    
    // 获取网盘类型
    function getDiskType(url) {
        if (url.includes('pan.baidu.com')) {
            return { name: '百度网盘APP', color: '#06a7ff', icon: 'images/bd.png' };
        } else if (url.includes('pan.xunlei.com')) {
            return { name: '迅雷APP', color: '#0088ff', icon: 'images/xl.png' };
        } else if (url.includes('pan.quark.cn')) {
            return { name: '夸克APP', color: '#0088ff', icon: 'images/kk.png' };
        } else if (url.includes('drive.uc.cn')) {
            return { name: 'UC浏览器APP', color: '#0088ff', icon: 'images/uc.png' };
        } else {
            return { name: '网盘', color: '#0088ff', icon: 'images/all.png' };
        }
    }
    
    // 显示弹窗
    function showModal(url) {
        const modal = document.getElementById('mobileModal');
        const qrcodeContainer = document.getElementById('qrcode');
        const modalTitle = document.getElementById('modalTitle');
        const modalTip = document.getElementById('modalTip');
        
        // 获取网盘类型
        const diskType = getDiskType(url);
        
        // 更新标题和提示文本
        modalTitle.innerHTML = `<img src="${diskType.icon}" alt="${diskType.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 8px;">${diskType.name}扫码访问`;
        modalTip.textContent = `请使用${diskType.name}扫描二维码访问`;
        modalTitle.style.color = diskType.color;
        
        // 清除旧的二维码
        qrcodeContainer.innerHTML = '';
        
        // 生成新的二维码
        new QRCode(qrcodeContainer, {
            text: url,
            width: 180,
            height: 180,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H,
            title: ''
        });
        
        modal.style.display = 'flex';
    }
    
    // 关闭弹窗
    function closeModal() {
        document.getElementById('mobileModal').style.display = 'none';
    }
    
    // 点击遮罩层关闭弹窗
    document.getElementById('mobileModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // 页面加载完成后设置链接点击事件
    document.addEventListener('DOMContentLoaded', function() {
        const links = document.querySelectorAll('.item');
        
        links.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault(); // 始终阻止默认行为
                const encryptedUrl = this.getAttribute('data-encrypted');
                if (!encryptedUrl) return;
                
                // 检查环境
                if (isValidClient() && <?php echo isMobile() ? 'true' : 'false'; ?>) {
                    // 在合适的环境下，先获取真实链接再跳转
                    fetch('get_url.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'encrypted=' + encodeURIComponent(encryptedUrl)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.url) {
                            window.location.href = data.url;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('链接获取失败，请重试');
                    });
                } else {
                    // 非微信/QQ环境下显示二维码
                    fetch('get_url.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'encrypted=' + encodeURIComponent(encryptedUrl)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.url) {
                            showModal(data.url);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('二维码生成失败，请重试');
                    });
                }
            });
        });
    });

    // 更新客户端环境检测
    function isValidClient() {
        const ua = navigator.userAgent.toLowerCase();
        // 检查是否是微信
        const isWechat = /micromessenger/i.test(ua);
        // 检查是否是QQ（必须是移动设备中的QQ）
        const isQQ = /mobile.*qq\//i.test(ua) || (/mobile/i.test(ua) && / qq/i.test(ua));
        // 检查是否是模拟器或开发者工具
        const isFake = /chrome/i.test(ua) && /mobile/i.test(ua) && !/safari/i.test(ua) || 
                      /devtools/i.test(ua) || 
                      (/android/i.test(ua) && /linux/i.test(ua) && /chrome/i.test(ua) && !/version/i.test(ua)) ||
                      (/iphone/i.test(ua) && /mac/i.test(ua) && /chrome/i.test(ua));
        
        return (isWechat || isQQ) && !isFake;
    }

    // 添加微信二维码显示函数
    function showWeChatQR() {
        const modal = document.createElement('div');
        modal.id = 'wechatModal';
        modal.innerHTML = `
            <div class="modal-content">
                <h3>扫码添加微信</h3>
                <img src="images/wechat-qr.png" alt="微信二维码">
                <button onclick="closeWeChatQR()">关闭</button>
            </div>
        `;
        document.body.appendChild(modal);
        modal.style.display = 'flex';

        // 点击遮罩层关闭
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeWeChatQR();
            }
        });
    }

    function closeWeChatQR() {
        const modal = document.getElementById('wechatModal');
        if (modal) {
            modal.remove();
        }
    }
    </script>
</body>
</html> 