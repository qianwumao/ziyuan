<?php
// 定义系统访问标记
define('IN_SYSTEM', true);

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// API密钥配置
define('API_KEY', '设置你的秘钥'); // 建议使用更复杂的密钥

// 引入数据库类
require_once 'Database.php';

// 验证API密钥
function verifyApiKey() {
    // 先检查URL参数中的authorization
    if (isset($_GET['authorization']) && $_GET['authorization'] === API_KEY) {
        return true;
    }
    
    // 再检查请求头中的Authorization
    $headers = getallheaders();
    $apiKey = $headers['Authorization'] ?? '';
    
    if (empty($apiKey)) {
        return false;
    }
    
    // 移除Bearer前缀
    $apiKey = str_replace('Bearer ', '', $apiKey);
    return $apiKey === API_KEY;
}

// 响应函数
function sendResponse($success, $data = null, $message = '', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ]);
    exit;
}

// 获取请求数据
function getRequestData() {
    // 如果是GET请求且有相关参数，优先使用URL参数
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['title'])) {
        return [
            'title' => $_GET['title'] ?? '',
            'cover' => $_GET['cover'] ?? '',
            'episodes' => isset($_GET['episodes']) ? intval($_GET['episodes']) : 0
        ];
    }
    
    // 否则尝试获取POST数据
    return json_decode(file_get_contents('php://input'), true);
}

try {
    // 验证API密钥
    if (!verifyApiKey()) {
        sendResponse(false, null, '未授权访问', 401);
    }

    // 实例化数据库类
    $db = new Database();
    
    // 获取请求方法
    $method = $_SERVER['REQUEST_METHOD'];
    
    // 特殊处理：如果是GET请求但包含title参数，视为添加数据的请求
    if ($method === 'GET' && isset($_GET['title'])) {
        // 添加数据
        $data = getRequestData();
        if (empty($data['title']) || empty($data['cover'])) {
            sendResponse(false, null, '标题和链接不能为空', 400);
        }
        
        $success = $db->addData($data['title'], $data['cover'], $data['episodes']);
        if ($success) {
            sendResponse(true, null, '数据添加成功');
        } else {
            sendResponse(false, null, '数据添加失败', 500);
        }
        exit;
    }
    
    // 处理常规API请求
    switch ($method) {
        case 'GET':
            // 获取数据列表或统计信息
            if (isset($_GET['stats'])) {
                // 获取统计信息
                $stats = [
                    'total' => $db->getTotalCount(),
                    'today' => $db->getTodayCount()
                ];
                sendResponse(true, $stats);
            } else {
                // 获取数据列表
                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                $pageSize = isset($_GET['pageSize']) ? max(1, intval($_GET['pageSize'])) : 20;
                $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
                
                if ($keyword) {
                    $data = $db->searchData($keyword, $page, $pageSize);
                    $totalCount = $db->searchCount($keyword);
                } else {
                    $data = $db->getAllData($page, $pageSize);
                    $totalCount = $db->getTotalCount();
                }
                
                sendResponse(true, [
                    'list' => $data,
                    'total' => $totalCount,
                    'page' => $page,
                    'pageSize' => $pageSize
                ]);
            }
            break;
            
        case 'POST':
            // 添加数据
            $data = getRequestData();
            if (!isset($data['title']) || !isset($data['cover'])) {
                sendResponse(false, null, '标题和链接不能为空', 400);
            }
            
            $success = $db->addData($data['title'], $data['cover'], $data['episodes'] ?? 0);
            if ($success) {
                sendResponse(true, null, '数据添加成功');
            } else {
                sendResponse(false, null, '数据添加失败', 500);
            }
            break;
            
        case 'PUT':
            // 更新数据
            $data = getRequestData();
            if (!isset($data['id']) || !isset($data['title']) || !isset($data['cover'])) {
                sendResponse(false, null, '缺少必要参数', 400);
            }
            
            $success = $db->updateData($data['id'], $data['title'], $data['cover'], $data['episodes'] ?? 0);
            if ($success) {
                sendResponse(true, null, '数据更新成功');
            } else {
                sendResponse(false, null, '数据更新失败', 500);
            }
            break;
            
        case 'DELETE':
            // 删除数据
            $data = getRequestData();
            if (!isset($data['id'])) {
                sendResponse(false, null, '缺少ID参数', 400);
            }
            
            $success = $db->deleteData($data['id']);
            if ($success) {
                sendResponse(true, null, '数据删除成功');
            } else {
                sendResponse(false, null, '数据删除失败', 500);
            }
            break;
            
        default:
            sendResponse(false, null, '不支持的请求方法', 405);
    }
    
} catch (Exception $e) {
    sendResponse(false, null, $e->getMessage(), 500);
} 