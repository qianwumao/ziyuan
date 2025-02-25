<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'config.php';

// API配置
const API_CONFIG = [
    'BASE_URL' => 'https://drive-pc.quark.cn',
    'DRIVE_URL' => 'https://drive.quark.cn',
    'ENDPOINTS' => [
        'TOKEN' => '/1/clouddrive/share/sharepage/token',
        'DETAIL' => '/1/clouddrive/share/sharepage/detail',
        'TASK' => '/1/clouddrive/task',
        'FILE' => '/1/clouddrive/file',
        'SORT' => '/1/clouddrive/file/sort',
        'USER_INFO' => 'https://pan.quark.cn/account/info',
        'SAVE' => '/1/clouddrive/share/sharepage/save'
    ]
];

// 默认请求头
const DEFAULT_HEADERS = [
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.71 Safari/537.36 Core/1.94.225.400 QQBrowser/12.2.5544.400',
    'origin' => 'https://pan.quark.cn',
    'referer' => 'https://pan.quark.cn/',
    'accept-language' => 'zh-CN,zh;q=0.9',
    'accept' => 'application/json, text/plain, */*'
];

// 工具类
class Utils {
    // 获取13位时间戳
    public static function getTimestamp() {
        return round(microtime(true) * 1000);
    }
    
    // 从分享链接中提取pwd_id
    public static function getPwdId($shareUrl) {
        try {
            $parts = explode('?', $shareUrl);
            $path = explode('/s/', $parts[0]);
            return end($path);
        } catch (Exception $e) {
            error_log('提取pwd_id失败: ' . $e->getMessage());
            return null;
        }
    }
    
    // 格式化Cookie字符串
    public static function formatCookie($cookieStr) {
        try {
            if (empty($cookieStr)) {
                return '';
            }
            
            // 如果是标准格式的cookie字符串
            if (strpos($cookieStr, '=') !== false && strpos($cookieStr, ';') !== false) {
                $pairs = array_filter(array_map('trim', explode(';', $cookieStr)));
                return implode('; ', array_filter($pairs, function($pair) {
                    return strpos($pair, '=') !== false;
                }));
            }
            
            return $cookieStr;
        } catch (Exception $e) {
            error_log('格式化Cookie失败: ' . $e->getMessage());
            return $cookieStr;
        }
    }

    // 添加新的工具方法
    public static function generateRandomString($length = 8) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    // 添加日志记录方法
    public static function logDebug($message, $data = null) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message";
        if ($data !== null) {
            $logMessage .= "\nData: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        error_log($logMessage);
    }
}

// 夸克网盘管理类
class QuarkManager {
    private $cookie;
    private $headers = [
        'accept' => 'application/json, text/plain, */*',
        'accept-language' => 'zh-CN,zh;q=0.9',
        'content-type' => 'application/json;charset=UTF-8',
        'origin' => 'https://pan.quark.cn',
        'referer' => 'https://pan.quark.cn/',
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36'
    ];
    
    public function __construct($cookie = '') {
        $this->cookie = $cookie;
    }
    
    // 发送HTTP请求
    private function sendRequest($url, $method = 'GET', $data = null, $additionalHeaders = []) {
        Utils::logDebug("开始请求", [
            'url' => $url,
            'method' => $method,
            'data' => $data,
            'additionalHeaders' => $additionalHeaders
        ]);

        try {
            // 提取cookie参数
            $cookieParams = [];
            if (!empty($this->cookie)) {
                preg_match('/__sdid=([^;]+)/', $this->cookie, $sdidMatch);
                preg_match('/__kuus=([^;]+)/', $this->cookie, $kuusMatch);
                preg_match('/__uus=([^;]+)/', $this->cookie, $uusMatch);
                preg_match('/guid=([^;]+)/', $this->cookie, $guidMatch);

                if ($sdidMatch) $cookieParams['sdid'] = $sdidMatch[1];
                if ($kuusMatch) $cookieParams['kuus'] = $kuusMatch[1];
                if ($uusMatch) $cookieParams['uus'] = $uusMatch[1];
                if ($guidMatch) $cookieParams['guid'] = $guidMatch[1];
            }

            Utils::logDebug("Cookie参数", $cookieParams);

            // 准备请求头
            $headers = array_merge($this->headers, $additionalHeaders);
            $headers['cookie'] = $this->cookie;

            $curlHeaders = [];
            foreach ($headers as $key => $value) {
                $curlHeaders[] = "$key: $value";
            }

            Utils::logDebug("请求头", $curlHeaders);

            // 初始化CURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
            }

            // 执行请求
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $errno = curl_errno($ch);

            Utils::logDebug("CURL响应", [
                'httpCode' => $httpCode,
                'response' => $response,
                'error' => $error,
                'errno' => $errno
            ]);

            curl_close($ch);

            if ($error) {
                throw new Exception("CURL错误 ($errno): $error");
            }

            if ($httpCode !== 200) {
                throw new Exception("HTTP请求失败: $httpCode");
            }

            $result = json_decode($response, true);
            if ($result === null) {
                throw new Exception("JSON解析失败: " . json_last_error_msg());
            }

            Utils::logDebug("请求成功", $result);
            return $result;

        } catch (Exception $e) {
            Utils::logDebug("请求失败", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    // 获取分享Token
    public function getStoken($pwdId) {
        $params = [
            'pr' => 'ucpro',
            'fr' => 'pc',
            'uc_param_str' => '',
            '__dt' => rand(200, 9999),
            '__t' => Utils::getTimestamp()
        ];
        
        $data = [
            'pwd_id' => $pwdId,
            'passcode' => ''
        ];
        
        $url = API_CONFIG['BASE_URL'] . API_CONFIG['ENDPOINTS']['TOKEN'] . '?' . http_build_query($params);
        $response = $this->sendRequest($url, 'POST', $data);
        
        if (isset($response['data']['stoken'])) {
            return $response['data']['stoken'];
        }
        
        throw new Exception($response['message'] ?? '获取stoken失败');
    }
    
    // 获取分享文件详情
    public function getDetail($pwdId, $stoken, $pdirFid = '0') {
        $params = [
            'pwd_id' => $pwdId,
            'stoken' => $stoken,
            'pdir_fid' => $pdirFid,
            '_page' => '1',
            '_size' => '50',
            '_sort' => 'file_type:asc,updated_at:desc',
            'pr' => 'ucpro',
            'fr' => 'pc',
            '__dt' => rand(200, 9999),
            '__t' => Utils::getTimestamp()
        ];
        
        $url = API_CONFIG['BASE_URL'] . API_CONFIG['ENDPOINTS']['DETAIL'] . '?' . http_build_query($params);
        $response = $this->sendRequest($url);
        
        if ($response['code'] === 0 && isset($response['data'])) {
            return [
                'fileList' => array_map(function($file) {
                    return [
                        'fid' => $file['fid'],
                        'fileName' => $file['file_name'],
                        'fileType' => $file['file_type'],
                        'isDir' => $file['dir'] === 1,
                        'shareFidToken' => $file['share_fid_token'] ?? '',
                        'size' => $file['size']
                    ];
                }, $response['data']['list']),
                'dirInfo' => [
                    'dirName' => $response['data']['dir_name'],
                    'dirLevel' => $response['data']['dir_level'],
                    'dirPath' => $response['data']['dir_path']
                ]
            ];
        }
        
        throw new Exception($response['message'] ?? '获取文件详情失败');
    }
    
    // 添加新的转存方法
    public function saveFiles($pwdId, $stoken, $fileList, $targetFolderId = '0') {
        Utils::logDebug("开始转存文件", [
            'pwdId' => $pwdId,
            'stoken' => $stoken,
            'fileList' => $fileList,
            'targetFolderId' => $targetFolderId
        ]);

        $params = [
            'pr' => 'ucpro',
            'fr' => 'pc',
            'uc_param_str' => '',
            '__dt' => rand(600, 9999),
            '__t' => Utils::getTimestamp()
        ];

        $data = [
            'fid_list' => array_map(function($file) {
                return $file['fid'];
            }, $fileList),
            'fid_token_list' => array_map(function($file) {
                return $file['shareFidToken'] ?? '';
            }, $fileList),
            'to_pdir_fid' => $targetFolderId,
            'pwd_id' => $pwdId,
            'stoken' => $stoken,
            'pdir_fid' => '0',
            'scene' => 'link'
        ];

        Utils::logDebug("转存请求数据", $data);

        $url = API_CONFIG['BASE_URL'] . API_CONFIG['ENDPOINTS']['SAVE'] . '?' . http_build_query($params);
        
        $additionalHeaders = [
            'referer' => 'https://pan.quark.cn/s/' . $pwdId,
            'sec-ch-ua' => '"Not:A-Brand";v="99", "Chromium";v="112"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"'
        ];

        try {
            $response = $this->sendRequest($url, 'POST', $data, $additionalHeaders);
            Utils::logDebug("转存响应", $response);

            if ($response['code'] === 0 && isset($response['data']['task_id'])) {
                return $response['data']['task_id'];
            }

            throw new Exception($response['message'] ?? '创建转存任务失败');
        } catch (Exception $e) {
            Utils::logDebug("转存失败", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    // 查询任务状态
    public function queryTaskStatus($taskId) {
        Utils::logDebug("查询任务状态", ['taskId' => $taskId]);

        $params = [
            'pr' => 'ucpro',
            'fr' => 'pc',
            'task_id' => $taskId,
            '__dt' => rand(200, 9999),
            '__t' => Utils::getTimestamp()
        ];
        
        $url = API_CONFIG['BASE_URL'] . API_CONFIG['ENDPOINTS']['TASK'] . '?' . http_build_query($params);
        
        try {
            $response = $this->sendRequest($url);
            Utils::logDebug("任务状态响应", $response);

            if ($response['code'] === 0) {
                $newFileIds = [];
                if (isset($response['data']['save_as']['save_as_top_fids'])) {
                    $newFileIds = $response['data']['save_as']['save_as_top_fids'];
                } elseif (isset($response['data']['file_list']) && is_array($response['data']['file_list'])) {
                    $newFileIds = array_map(function($file) {
                        return is_array($file) ? ($file['fid'] ?? '') : $file;
                    }, $response['data']['file_list']);
                }
                
                return [
                    'status' => $response['data']['status'],
                    'progress' => $response['data']['progress'] ?? 0,
                    'message' => $response['data']['message'] ?? '',
                    'newFileIds' => array_filter($newFileIds)
                ];
            }

            throw new Exception($response['message'] ?? '查询任务状态失败');
        } catch (Exception $e) {
            Utils::logDebug("查询任务状态失败", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    // 创建分享链接
    public function createShareLink($fileIds) {
        Utils::logDebug("开始创建分享链接", ['fileIds' => $fileIds]);
        try {
            // 1. 创建分享任务
            $taskId = $this->getShareTaskId($fileIds);
            Utils::logDebug("获取分享任务ID成功", ['taskId' => $taskId]);

            // 2. 等待任务完成并获取share_id
            $maxRetries = 30;  // 增加重试次数
            $retryCount = 0;
            $shareId = null;

            while ($retryCount < $maxRetries) {
                sleep(1);  // 每次查询间隔1秒
                
                $params = [
                    'pr' => 'ucpro',
                    'fr' => 'pc',
                    'task_id' => $taskId,
                    '__dt' => rand(600, 9999),
                    '__t' => Utils::getTimestamp()
                ];
                
                $url = API_CONFIG['BASE_URL'] . API_CONFIG['ENDPOINTS']['TASK'] . '?' . http_build_query($params);
                $response = $this->sendRequest($url);
                Utils::logDebug("查询分享任务状态", $response);

                if ($response['code'] === 0 && isset($response['data'])) {
                    if ($response['data']['status'] === 2) { // 任务完成
                        if (isset($response['data']['share_id'])) {
                            $shareId = $response['data']['share_id'];
                            break;
                        }
                    } elseif ($response['data']['status'] === 3) { // 任务失败
                        throw new Exception($response['data']['message'] ?? '分享任务失败');
                    }
                }

                $retryCount++;
            }

            if (!$shareId) {
                throw new Exception('未获取到share_id');
            }

            // 3. 提交分享并获取链接
            $params = [
                'pr' => 'ucpro',
                'fr' => 'pc',
                '__dt' => rand(600, 9999),
                '__t' => Utils::getTimestamp()
            ];

            $data = [
                'share_id' => $shareId,
                'expired_type' => 1,
                'url_type' => 1
            ];

            $url = API_CONFIG['BASE_URL'] . '/1/clouddrive/share/password?' . http_build_query($params);
            
            $additionalHeaders = [
                'referer' => 'https://pan.quark.cn/list',
                'sec-ch-ua' => '"Not:A-Brand";v="99", "Chromium";v="112"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-platform' => '"Windows"'
            ];

            $response = $this->sendRequest($url, 'POST', $data, $additionalHeaders);
            Utils::logDebug("提交分享响应", $response);

            if ($response['code'] === 0 && isset($response['data']['share_url'])) {
                return $response['data']['share_url'];
            }

            throw new Exception($response['message'] ?? '提交分享请求失败');
        } catch (Exception $e) {
            Utils::logDebug("创建分享链接失败", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    // 获取分享任务ID
    private function getShareTaskId($fileIds) {
        $params = [
            'pr' => 'ucpro',
            'fr' => 'pc',
            '__dt' => rand(600, 9999),
            '__t' => Utils::getTimestamp()
        ];

        $data = [
            'fid_list' => $fileIds,
            'title' => '',
            'url_type' => 1,
            'expired_type' => 1,
            'scene' => 'link'
        ];

        $url = API_CONFIG['BASE_URL'] . '/1/clouddrive/share?' . http_build_query($params);
        
        $additionalHeaders = [
            'referer' => 'https://pan.quark.cn/list',
            'sec-ch-ua' => '"Not:A-Brand";v="99", "Chromium";v="112"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"'
        ];

        $response = $this->sendRequest($url, 'POST', $data, $additionalHeaders);

        if ($response['code'] === 0 && isset($response['data']['task_id'])) {
            return $response['data']['task_id'];
        }

        throw new Exception($response['message'] ?? '获取分享任务ID失败');
    }

    // 处理转存请求
    public function handleSaveRequest($shareUrl, $targetFolder = '0', $customTitle = '') {
        Utils::logDebug("处理转存请求", [
            'shareUrl' => $shareUrl,
            'targetFolder' => $targetFolder,
            'customTitle' => $customTitle
        ]);

        try {
            // 获取pwd_id
            $pwdId = Utils::getPwdId($shareUrl);
            if (!$pwdId) {
                throw new Exception('无效的分享链接');
            }

            // 获取stoken
            $stoken = $this->getStoken($pwdId);
            Utils::logDebug("获取到stoken", ['stoken' => $stoken]);

            // 获取文件列表
            $detail = $this->getDetail($pwdId, $stoken);
            Utils::logDebug("获取到文件列表", $detail);

            // 转存文件
            $taskId = $this->saveFiles($pwdId, $stoken, $detail['fileList'], $targetFolder);
            Utils::logDebug("创建转存任务成功", ['taskId' => $taskId]);

            // 等待任务完成
            $maxRetries = 30;
            $retryCount = 0;

            do {
                sleep(2);
                $status = $this->queryTaskStatus($taskId);
                Utils::logDebug("任务状态", $status);

                if ($status['status'] === 2) { // 任务完成
                    if (!empty($status['newFileIds'])) {
                        try {
                            Utils::logDebug("开始创建分享链接", ['fileIds' => $status['newFileIds']]);
                            $shareLink = $this->createShareLink($status['newFileIds']);
                            Utils::logDebug("创建分享链接成功", ['shareLink' => $shareLink]);
                            
                            // 调用API保存数据
                            try {
                                $title = $customTitle ?: $detail['fileList'][0]['fileName'] ?? '';
                                $apiUrl = sprintf(
                                    'https://api.q5url.cn/ziyuan/apiadd.php?authorization=980225717&title=%s&cover=%s&episodes=0',
                                    urlencode($title),
                                    urlencode($shareLink)
                                );
                                
                                Utils::logDebug("调用API保存数据", ['apiUrl' => $apiUrl]);
                                
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $apiUrl);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                                
                                $apiResponse = curl_exec($ch);
                                $apiResult = json_decode($apiResponse, true);
                                curl_close($ch);
                                
                                if (!$apiResult['success']) {
                                    Utils::logDebug("保存到数据库失败", ['error' => $apiResult['message'] ?? '未知错误']);
                                }
                            } catch (Exception $apiError) {
                                Utils::logDebug("调用API失败", ['error' => $apiError->getMessage()]);
                            }
                            
                            return [
                                'code' => 0,
                                'msg' => '转存成功',
                                'data' => [
                                    'taskId' => $taskId,
                                    'newFileIds' => $status['newFileIds'],
                                    'shareLink' => $shareLink
                                ]
                            ];
                        } catch (Exception $e) {
                            Utils::logDebug("创建分享链接失败", ['error' => $e->getMessage()]);
                            // 即使分享链接创建失败，也返回转存成功信息
                            return [
                                'code' => 0,
                                'msg' => '转存成功，但创建分享链接失败：' . $e->getMessage(),
                                'data' => [
                                    'taskId' => $taskId,
                                    'newFileIds' => $status['newFileIds']
                                ]
                            ];
                        }
                    }
                    
                    // 如果没有新文件ID，尝试搜索文件
                    Utils::logDebug("任务完成但未获取到新文件ID，尝试搜索文件");
                    $searchResult = $this->searchFiles($detail['fileList'][0]['fileName'] ?? '');
                    if (!empty($searchResult)) {
                        try {
                            $shareLink = $this->createShareLink($searchResult);
                            return [
                                'code' => 0,
                                'msg' => '转存成功',
                                'data' => [
                                    'taskId' => $taskId,
                                    'newFileIds' => $searchResult,
                                    'shareLink' => $shareLink
                                ]
                            ];
                        } catch (Exception $e) {
                            Utils::logDebug("创建分享链接失败", ['error' => $e->getMessage()]);
                        }
                    }
                    
                    return [
                        'code' => 0,
                        'msg' => '转存成功，但未能获取新文件ID',
                        'data' => [
                            'taskId' => $taskId,
                            'newFileIds' => []
                        ]
                    ];
                } elseif ($status['status'] === 3) { // 任务失败
                    throw new Exception($status['message'] ?? '转存任务失败');
                }

                $retryCount++;
                if ($retryCount >= $maxRetries) {
                    throw new Exception('转存任务超时');
                }
            } while (true);

        } catch (Exception $e) {
            Utils::logDebug("转存请求处理失败", ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    // 搜索文件
    private function searchFiles($fileName) {
        Utils::logDebug("搜索文件", ['fileName' => $fileName]);
        
        $params = [
            'pr' => 'ucpro',
            'fr' => 'pc',
            'pdir_fid' => '0',
            '_page' => '1',
            '_size' => '50',
            '_sort' => 'file_type:asc,updated_at:desc',
            'search_name' => $fileName,
            '__t' => Utils::getTimestamp()
        ];

        try {
            $url = API_CONFIG['BASE_URL'] . '/1/clouddrive/file/search?' . http_build_query($params);
            $response = $this->sendRequest($url);
            
            if ($response['code'] === 0 && isset($response['data']['list'])) {
                return array_map(function($file) {
                    return $file['fid'];
                }, $response['data']['list']);
            }
            
            return [];
        } catch (Exception $e) {
            Utils::logDebug("搜索文件失败", ['error' => $e->getMessage()]);
            return [];
        }
    }
}

// 主处理函数
function handleRequest() {
    try {
        Utils::logDebug("开始处理请求", $_GET);
        
        // 验证请求参数
        if (!isset($_GET['action'])) {
            throw new Exception('缺少action参数');
        }
        
        $action = $_GET['action'];
        $quark = new QuarkManager(QUARK_COOKIE);
        
        switch ($action) {
            case 'save':
                if (!isset($_GET['url'])) {
                    throw new Exception('缺少分享链接参数');
                }
                
                $shareUrl = $_GET['url'];
                $targetFolder = $_GET['folder'] ?? '0';
                $customTitle = $_GET['title'] ?? '';
                
                $result = $quark->handleSaveRequest($shareUrl, $targetFolder, $customTitle);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                break;
                
            case 'status':
                if (!isset($_GET['taskId'])) {
                    throw new Exception('缺少taskId参数');
                }
                
                $status = $quark->queryTaskStatus($_GET['taskId']);
                echo json_encode([
                    'code' => 0,
                    'msg' => '获取任务状态成功',
                    'data' => $status
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception('不支持的操作类型');
        }
        
    } catch (Exception $e) {
        Utils::logDebug("请求处理失败", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        echo json_encode([
            'code' => 500,
            'msg' => $e->getMessage(),
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
    }
}

// 处理请求
handleRequest(); 