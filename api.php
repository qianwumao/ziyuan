<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';
require_once 'Database.php';

function response($code, $msg, $data = null) {
    echo json_encode([
        'code' => $code,
        'msg' => $msg,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 参数验证
if (!isset($_GET['param']) || $_GET['param'] != '1') {
    response(API_ERROR, 'param参数错误');
}

if (!isset($_GET['name']) || empty($_GET['name'])) {
    response(API_ERROR, 'name参数不能为空');
}

if (!isset($_GET['page']) || !is_numeric($_GET['page']) || $_GET['page'] < 1) {
    response(API_ERROR, 'page参数错误');
}

try {
    $db = new Database();
    $keyword = trim($_GET['name']);
    $page = (int)$_GET['page'];
    
    $data = $db->getYingshi($keyword, $page);
    response(API_SUCCESS, '获取成功', $data);
} catch (Exception $e) {
    response(API_ERROR, $e->getMessage());
} 