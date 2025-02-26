<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'Database.php';

// 添加设备检测函数
function isMobile() {
    $useragent = $_SERVER['HTTP_USER_AGENT'];
    return preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4));
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
        let devtools = {
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
                    window.location.reload();
                }
            } else {
                if (emitEvents && devtools.isOpen) {
                    emitEvent(false, undefined);
                }
            }

            if (window.devtools.isOpen) {
                window.location.reload();
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

        // 定期检查，但降低检查频率
        setInterval(checkDevTools, 2000);
    }

    // 页面加载完成后启用保护
    window.onload = function() {
        setTimeout(disableDevTools, 1000);
    };
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
            <h1>持续更新中...</h1>
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
  <div class="stats">
            共 <?php echo number_format($result['total']); ?> 条数据
            <button id="exportBtn" class="export-btn">导出所有数据到本地</button>
        </div>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <div class="list">
                <?php foreach ($result['data'] as $item): ?>
                    <?php
                        // 清理百度网盘链接
                        $cleanLink = $item['cover'];
                        if (strpos($cleanLink, 'pan.baidu.com') !== false) {
                            // 匹配百度网盘链接，提取链接和提取码部分
                            if (preg_match('/(https:\/\/pan\.baidu\.com\/s\/[^\s]+?)(?:\s+提取码:\s*([^\s]+))?$/i', $cleanLink, $matches)) {
                                // 如果链接中已经包含了提取码参数，直接使用链接
                                if (strpos($matches[1], '?pwd=') !== false) {
                                    $cleanLink = $matches[1];
                                } 
                                // 如果链接中没有提取码参数，但是有单独的提取码，添加到链接中
                                else if (isset($matches[2])) {
                                    $cleanLink = $matches[1] . '?pwd=' . $matches[2];
                                }
                                // 如果只有链接，直接使用
                                else {
                                    $cleanLink = $matches[1];
                                }
                            }
                        }
                    ?>
                    <a href="<?php echo htmlspecialchars($cleanLink); ?>" 
                       class="item" 
                       data-url="<?php echo htmlspecialchars($cleanLink); ?>"
                       <?php echo !isMobile() ? '' : 'target="_blank"'; ?>>
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

    <div id="overlay" class="overlay"></div>
    <div id="exportMenu" class="export-menu">
        <h3>选择导出格式</h3>
        <div class="export-options">
            <button class="export-option" data-format="txt">导出为TXT</button>
            <button class="export-option" data-format="csv">导出为CSV</button>
        </div>
    </div>

    <script>
    document.getElementById('exportBtn').addEventListener('click', function() {
        document.getElementById('overlay').style.display = 'block';
        document.getElementById('exportMenu').style.display = 'block';
    });

    document.getElementById('overlay').addEventListener('click', function() {
        this.style.display = 'none';
        document.getElementById('exportMenu').style.display = 'none';
    });

    document.querySelectorAll('.export-option').forEach(button => {
        button.addEventListener('click', function() {
            const format = this.dataset.format;
            exportData(format);
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('exportMenu').style.display = 'none';
        });
    });

    function exportData(format) {
        // 显示加载中状态
        const exportBtn = document.getElementById('exportBtn');
        const originalText = exportBtn.textContent;
        exportBtn.textContent = '导出中...';
        exportBtn.disabled = true;

        // 发起导出请求
        fetch(`export.php?format=${format}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(response.statusText || '导出失败');
                }
                return response.blob();
            })
            .then(blob => {
                // 创建下载链接
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                const extension = format === 'txt' ? 'txt' : 'csv';
                a.href = url;
                a.download = `资源数据_${new Date().toISOString().split('T')[0]}.${extension}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            })
            .catch(error => {
                console.error('导出错误:', error);
                alert('导出失败：' + error.message);
            })
            .finally(() => {
                // 恢复按钮状态
                exportBtn.textContent = originalText;
                exportBtn.disabled = false;
            });
    }

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
        const qrcode = new QRCode(qrcodeContainer, {
            text: url,
            width: 180,
            height: 180,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H,
            title: ''
        });

        // 移除二维码图片的所有提示和事件
        const observer = new MutationObserver((mutations) => {
            const qrImage = qrcodeContainer.querySelector('img');
            if (qrImage) {
                qrImage.removeAttribute('title');
                qrImage.removeAttribute('alt');
                qrImage.style.pointerEvents = 'none';
                qrImage.addEventListener('mouseover', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }, true);
                observer.disconnect();
            }
        });

        observer.observe(qrcodeContainer, {
            childList: true,
            subtree: true
        });

        // 阻止容器的默认事件
        qrcodeContainer.addEventListener('mouseover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }, true);

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
                if (!isMobile()) {
                    e.preventDefault();
                    const url = this.getAttribute('data-url');
                    showModal(url);
                }
            });
        });
    });
    </script>
</body>
</html> 
