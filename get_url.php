<?php
header('Content-Type: application/json');
require_once 'config.php';

// 解密函数
function decryptUrl($encryptedData) {
    try {
        $key = "YOUR_SECRET_KEY"; // 使用与加密相同的密钥
        $data = base64_decode($encryptedData);
        $ivlen = openssl_cipher_iv_length($cipher = "AES-256-CBC");
        $iv = substr($data, 0, $ivlen);
        $encrypted = substr($data, $ivlen);
        return openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
    } catch (Exception $e) {
        return false;
    }
}

// 验证请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['encrypted'])) {
    http_response_code(400);
    echo json_encode(['error' => '无效请求']);
    exit;
}

// 解密URL
$decryptedUrl = decryptUrl($_POST['encrypted']);
if ($decryptedUrl === false) {
    http_response_code(400);
    echo json_encode(['error' => '解密失败']);
    exit;
}

// 返回解密后的URL
echo json_encode(['url' => $decryptedUrl]); 