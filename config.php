<?php
// 定义系统访问标记
if (!defined('IN_SYSTEM')) {
    define('IN_SYSTEM', true);
}
// 管理员密码配置
define('ADMIN_PASSWORD', '设置你的密码'); // 建议修改为更安全的密码
// 数据库配置
define('DB_FILE', __DIR__ . '/ziyuan.db');

// 分页配置
define('PAGE_SIZE', 10);

// API响应配置
define('API_SUCCESS', 200);
define('API_ERROR', 500);

// 夸克网盘配置
define('QUARK_COOKIE', '设置你的cookie'); // 替换为实际的cookie值 