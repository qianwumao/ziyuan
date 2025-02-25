<?php
// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 引入配置文件
require_once 'config.php';

// 验证访问密码
$isLoggedIn = false;

if (isset($_POST['password']) && $_POST['password'] === ADMIN_PASSWORD) {
    $isLoggedIn = true;
    setcookie('admin_logged_in', 'true', time() + 3600); // 1小时过期
} elseif (isset($_COOKIE['admin_logged_in']) && $_COOKIE['admin_logged_in'] === 'true') {
    $isLoggedIn = true;
}

$message = '';
$error = '';

// 数据库操作类
class ziyuanDB {
    private $db;
    private $pageSize = 20;

    public function __construct() {
        try {
            $this->db = new PDO('sqlite:ziyuan.db');
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initDatabase();
        } catch (PDOException $e) {
            throw new Exception('数据库连接失败: ' . $e->getMessage());
        }
    }

    private function initDatabase() {
        $this->db->exec('CREATE TABLE IF NOT EXISTS ziyuan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            cover TEXT NOT NULL,
            episodes INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
    }

    // 获取所有数据
    public function getAllData($page = 1, $pageSize = 20) {
        $offset = ($page - 1) * $pageSize;
        $stmt = $this->db->prepare('SELECT * FROM ziyuan ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 获取总记录数
    public function getTotalCount() {
        return $this->db->query('SELECT COUNT(*) FROM ziyuan')->fetchColumn();
    }

    // 添加数据
    public function addData($title, $cover, $episodes) {
        $stmt = $this->db->prepare('INSERT INTO ziyuan (title, cover, episodes) VALUES (:title, :cover, :episodes)');
        return $stmt->execute([
            ':title' => $title,
            ':cover' => $cover,
            ':episodes' => $episodes
        ]);
    }

    // 更新数据
    public function updateData($id, $title, $cover, $episodes) {
        $stmt = $this->db->prepare('UPDATE ziyuan SET title = :title, cover = :cover, episodes = :episodes WHERE id = :id');
        return $stmt->execute([
            ':title' => $title,
            ':cover' => $cover,
            ':episodes' => $episodes,
            ':id' => $id
        ]);
    }

    // 删除数据
    public function deleteData($id) {
        $stmt = $this->db->prepare('DELETE FROM ziyuan WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    // 获取单条数据
    public function getData($id) {
        $stmt = $this->db->prepare('SELECT * FROM ziyuan WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 搜索数据
    public function searchData($keyword, $page = 1, $pageSize = 20) {
        $offset = ($page - 1) * $pageSize;
        $keyword = '%' . $keyword . '%';
        
        $stmt = $this->db->prepare('SELECT * FROM ziyuan WHERE title LIKE :keyword ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':keyword', $keyword, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 搜索数据计数
    public function searchCount($keyword) {
        $keyword = '%' . $keyword . '%';
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM ziyuan WHERE title LIKE :keyword');
        $stmt->bindValue(':keyword', $keyword, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
}

if ($isLoggedIn) {
    try {
        $db = new ziyuanDB();
        
        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action'])) {
                switch ($_POST['action']) {
                    case 'add':
                        if ($db->addData($_POST['title'], $_POST['cover'], $_POST['episodes'])) {
                            $message = "数据添加成功";
                        } else {
                            $error = "数据添加失败";
                        }
                        break;

                    case 'update':
                        if ($db->updateData($_POST['id'], $_POST['title'], $_POST['cover'], $_POST['episodes'])) {
                            $message = "数据更新成功";
                        } else {
                            $error = "数据更新失败";
                        }
                        break;

                    case 'delete':
                        if ($db->deleteData($_POST['id'])) {
                            $message = "数据删除成功";
                        } else {
                            $error = "数据删除失败";
                        }
                        break;
                }
            }
        }

        // 获取当前页码
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $pageSize = 20;
        $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

        // 获取数据列表
        if ($keyword) {
            $data = $db->searchData($keyword, $page, $pageSize);
            $totalCount = $db->searchCount($keyword);
        } else {
            $data = $db->getAllData($page, $pageSize);
            $totalCount = $db->getTotalCount();
        }
        $totalPages = ceil($totalCount / $pageSize);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>短剧数据管理</title>
    <style>
        :root {
            --primary-color: #4a9eff;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --background-color: #f8f9fa;
            --border-color: #dee2e6;
            --text-color: #333;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: var(--background-color);
            color: var(--text-color);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            background: #d4edda;
            color: #155724;
        }

        .error {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            background: #f8d7da;
            color: #721c24;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        input[type="text"],
        input[type="number"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            opacity: 0.9;
        }

        .btn-danger {
            background: var(--danger-color);
        }

        .btn-success {
            background: var(--success-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            table-layout: fixed;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            word-break: break-all;
        }

        th:nth-child(1), td:nth-child(1) { width: 5%; }
        th:nth-child(2), td:nth-child(2) { width: 20%; }
        th:nth-child(3), td:nth-child(3) { width: 35%; }
        th:nth-child(4), td:nth-child(4) { width: 8%; }
        th:nth-child(5), td:nth-child(5) { width: 17%; }
        th:nth-child(6), td:nth-child(6) { width: 15%; }

        th {
            background: var(--background-color);
            font-weight: 500;
        }

        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 8px;
        }

        .pagination a {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            text-decoration: none;
            color: var(--text-color);
        }

        .pagination a.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            margin: 50px auto;
        }

        .close {
            float: right;
            cursor: pointer;
            font-size: 24px;
        }

        .actions {
            display: flex;
            gap: 4px;
            flex-wrap: nowrap;
        }

        .actions button {
            padding: 4px 8px;
            font-size: 12px;
            white-space: nowrap;
            min-width: 40px;
        }

        .search-form input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(74, 158, 255, 0.2);
        }

        .btn-reset:hover {
            opacity: 0.9;
        }

        .search-form button {
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            body {
                padding: 0;
                background: #f0f2f5;
            }

            .container {
                padding: 10px;
                margin: 0;
                border-radius: 0;
                box-shadow: none;
            }

            /* 顶部按钮和搜索栏布局 */
            .top-actions {
                flex-direction: column;
                gap: 10px;
                margin-bottom: 15px;
            }

            .action-buttons {
                display: flex;
                gap: 10px;
                width: 100%;
            }

            .action-buttons button {
                flex: 1;
                white-space: nowrap;
            }

            .search-form {
                width: 100%;
                display: grid;
                grid-template-columns: 1fr auto;
                gap: 8px;
            }

            .search-form input[type="text"] {
                width: 100%;
                margin: 0;
            }

            /* 卡片式布局优化 */
            table {
                border-spacing: 0 10px;
                margin-top: 0;
            }

            table tbody tr {
                background: white;
                margin-bottom: 10px;
                border: none;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }

            table tbody td {
                padding: 8px 12px;
                font-size: 14px;
                border-bottom: 1px solid #eee;
            }

            table tbody td:last-child {
                border-bottom: none;
            }

            /* 标签样式优化 */
            table tbody td::before {
                content: attr(data-label);
                font-weight: 500;
                color: #666;
                width: 80px;
                min-width: 80px;
            }

            /* 链接单元格优化 */
            .link-cell {
                display: flex !important;
                flex-wrap: wrap;
                gap: 8px;
                padding-right: 12px !important;
            }

            .link-content {
                width: 100%;
                order: 1;
                margin-right: 0;
            }

            .copy-btn {
                order: 2;
                position: static;
                transform: none;
                padding: 4px 12px;
                font-size: 12px;
                margin: 0;
                background: var(--primary-color);
                color: white;
                border-radius: 4px;
            }

            /* 操作按钮优化 */
            .actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                width: 100%;
                margin-top: 0;
            }

            .actions button {
                width: 100%;
                padding: 8px;
                font-size: 14px;
                margin: 0;
            }

            /* 分页优化 */
            .pagination {
                padding: 10px 0;
                justify-content: center;
                flex-wrap: wrap;
                gap: 6px;
            }

            .pagination a {
                padding: 6px 12px;
                font-size: 14px;
                background: white;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            }

            /* 模态框优化 */
            .modal-content {
                margin: 10px;
                padding: 15px;
                max-height: 90vh;
                overflow-y: auto;
            }

            .form-group {
                margin-bottom: 12px;
            }

            .form-group label {
                font-size: 14px;
                margin-bottom: 4px;
            }

            .form-group input {
                font-size: 14px;
                padding: 8px;
            }
        }

        .link-cell {
            max-width: 100%;
            position: relative;
            padding-right: 50px;
        }

        .link-content {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }

        .copy-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            padding: 2px 6px;
            font-size: 12px;
        }

        .link-tooltip {
            min-width: 200px;
            max-width: 400px;
            white-space: normal;
        }

        /* 卡片列表样式 */
        .card-list {
            display: grid;
            gap: 15px;
            margin-top: 20px;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 15px;
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }

        .card-title {
            font-size: 16px;
            font-weight: 500;
            margin: 0;
            flex: 1;
            word-break: break-all;
        }

        .card-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .card-actions button {
            padding: 6px 12px;
            font-size: 14px;
        }

        .card-content {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .card-info {
            display: flex;
            gap: 15px;
            font-size: 14px;
            color: #666;
        }

        .card-link {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
        }

        .card-link-text {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 14px;
        }

        .card-link .copy-btn {
            position: static;
            transform: none;
            padding: 4px 12px;
            font-size: 12px;
            background: var(--primary-color);
            color: white;
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            body {
                padding: 0;
                background: #f0f2f5;
            }

            .container {
                padding: 10px;
                margin: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .card {
                margin: 0;
                border-radius: 8px;
            }

            .card-header {
                flex-direction: column;
            }

            .card-actions {
                width: 100%;
            }

            .card-actions button {
                flex: 1;
            }

            .card-info {
                flex-wrap: wrap;
            }
        }

        /* 隐藏原表格 */
        table, thead, tbody, th, td {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$isLoggedIn): ?>
            <div class="login-form">
                <h2>登录</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="password">密码</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit">登录</button>
                </form>
            </div>
        <?php else: ?>
            <h2>短剧数据管理</h2>
            
            <?php if ($message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- 修改顶部操作区布局 -->
            <div class="top-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div class="action-buttons" style="display: flex; gap: 10px;">
                    <button onclick="showAddModal()">添加数据</button>
                    <button onclick="showQuickAddModal()">快速添加</button>
                </div>
                
                <form method="GET" class="search-form">
                    <input 
                        type="text" 
                        name="keyword" 
                        placeholder="搜索标题..." 
                        value="<?php echo htmlspecialchars($keyword ?? ''); ?>"
                    >
                    <div style="display: flex; gap: 8px;">
                        <button type="submit">搜索</button>
                        <?php if (!empty($keyword)): ?>
                            <a href="?" class="btn-reset">重置</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- 如果有搜索结果，显示结果计数 -->
            <?php if (!empty($keyword)): ?>
                <div style="margin-bottom: 20px; color: var(--text-color);">
                    找到 <?php echo $totalCount; ?> 条相关记录
                </div>
            <?php endif; ?>

            <!-- 替换表格为卡片列表 -->
            <div class="card-list">
                <?php foreach ($data as $row): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                            <div class="card-actions">
                                <button onclick="showEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">编辑</button>
                                <button class="btn-danger" onclick="deleteData(<?php echo $row['id']; ?>)">删除</button>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="card-link">
                                <span class="card-link-text"><?php echo htmlspecialchars($row['cover']); ?></span>
                                <button class="copy-btn" onclick="copyLink(this, '<?php echo htmlspecialchars($row['cover'], ENT_QUOTES); ?>')" title="复制链接">复制</button>
                            </div>
                            <div class="card-info">
                                <span>ID: <?php echo htmlspecialchars($row['id']); ?></span>
                                <span>集数: <?php echo htmlspecialchars($row['episodes']); ?></span>
                                <span>创建时间: <?php echo htmlspecialchars($row['created_at']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- 分页 -->
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?><?php echo $keyword ? '&keyword=' . urlencode($keyword) : ''; ?>" 
                       <?php echo $page == $i ? 'class="active"' : ''; ?>>
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>

            <!-- 添加数据模态框 -->
            <div id="addModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="hideAddModal()">&times;</span>
                    <h3>添加数据</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label for="title">标题</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="cover">链接</label>
                            <input type="text" id="cover" name="cover" required>
                        </div>
                        <div class="form-group">
                            <label for="episodes">集数</label>
                            <input type="number" id="episodes" name="episodes" value="0" min="0">
                        </div>
                        <button type="submit" class="btn-success">保存</button>
                    </form>
                </div>
            </div>

            <!-- 编辑数据模态框 -->
            <div id="editModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="hideEditModal()">&times;</span>
                    <h3>编辑数据</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="form-group">
                            <label for="edit_title">标题</label>
                            <input type="text" id="edit_title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_cover">链接</label>
                            <input type="text" id="edit_cover" name="cover" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_episodes">集数</label>
                            <input type="number" id="edit_episodes" name="episodes" min="0">
                        </div>
                        <button type="submit" class="btn-success">更新</button>
                    </form>
                </div>
            </div>

            <!-- 快速添加模态框 -->
            <div id="quickAddModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="hideQuickAddModal()">&times;</span>
                    <h3>快速添加数据</h3>
                    <div class="form-group">
                        <label for="share_text">粘贴分享文本（支持夸克、迅雷、百度网盘）</label>
                        <textarea id="share_text" style="width: 100%; height: 100px; padding: 8px; margin-bottom: 10px;" placeholder="支持以下格式：&#10;1. 夸克：我用夸克网盘分享了「视频名称」，链接：https://pan.quark.cn/...&#10;2. 迅雷：分享文件：视频名称&#10;链接：https://pan.xunlei.com/...&#10;3. 百度：通过网盘分享的文件：视频名称&#10;链接: https://pan.baidu.com/... 提取码: xxxx&#10;4. UC：「视频名称」来自UC网盘分享&#10;https://drive.uc.cn/..."></textarea>
                        <button onclick="parseAndSave()" class="btn-success">快速保存</button>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <script>
        // 显示添加模态框
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        // 隐藏添加模态框
        function hideAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        // 显示编辑模态框
        function showEditModal(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_title').value = data.title;
            document.getElementById('edit_cover').value = data.cover;
            document.getElementById('edit_episodes').value = data.episodes;
            document.getElementById('editModal').style.display = 'block';
        }

        // 隐藏编辑模态框
        function hideEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // 删除数据
        function deleteData(id) {
            if (confirm('确定要删除这条数据吗？')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // 显示快速添加模态框
        function showQuickAddModal() {
            document.getElementById('quickAddModal').style.display = 'block';
        }

        // 隐藏快速添加模态框
        function hideQuickAddModal() {
            document.getElementById('quickAddModal').style.display = 'none';
        }

        // 解析分享文本并直接保存
        function parseAndSave() {
            const shareText = document.getElementById('share_text').value;
            
            // 提取标题和链接
            let title = '';
            let link = '';
            
            // 尝试匹配夸克格式
            let titleMatch = shareText.match(/「([^」]+)」/);
            let linkMatch = shareText.match(/链接：(https:\/\/pan\.quark\.cn\/[^\s]+)/);
            
            // 尝试匹配迅雷格式
            if (!titleMatch || !linkMatch) {
                titleMatch = shareText.match(/分享文件：([^\n]+)/);
                linkMatch = shareText.match(/链接：(https:\/\/pan\.xunlei\.com\/[^\s#]+)/);
            }
            
            // 尝试匹配百度格式
            if (!titleMatch || !linkMatch) {
                titleMatch = shareText.match(/通过网盘分享的文件：([^\n]+)/);
                if (!titleMatch) {
                    titleMatch = shareText.match(/文件：([^\n]+)/);
                }
                const baiduLinkMatch = shareText.match(/链接:\s*(https:\/\/pan\.baidu\.com\/[^\s]+\?pwd=[a-zA-Z0-9]+)/);
                if (baiduLinkMatch) {
                    linkMatch = [null, baiduLinkMatch[1]];
                }
            }

            // 尝试匹配UC网盘格式
            if (!titleMatch || !linkMatch) {
                titleMatch = shareText.match(/「([^」]+)」来自UC网盘分享/);
                linkMatch = shareText.match(/(https:\/\/drive\.uc\.cn\/[^\s]+)/);
            }
            
            // 提取匹配结果
            if (titleMatch && titleMatch[1]) {
                title = titleMatch[1].trim();
            }
            if (linkMatch && linkMatch[1]) {
                link = linkMatch[1].trim();
            }
            
            if (!title || !link) {
                alert('无法从分享文本中提取标题或链接，请检查格式是否正确');
                return;
            }
            
            // 创建表单并直接提交
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="title" value="${title}">
                <input type="hidden" name="cover" value="${link}">
                <input type="hidden" name="episodes" value="0">
            `;
            document.body.appendChild(form);
            
            // 隐藏快速添加模态框
            hideQuickAddModal();
            
            // 提交表单
            form.submit();
        }

        // 添加复制功能的JavaScript
        function copyLink(button, text) {
            // 创建临时输入框
            const input = document.createElement('input');
            input.value = text;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            
            // 显示复制成功提示
            const originalText = button.textContent;
            button.textContent = '已复制';
            button.style.color = 'var(--success-color)';
            
            // 2秒后恢复按钮文字
            setTimeout(() => {
                button.textContent = originalText;
                button.style.color = 'var(--primary-color)';
            }, 2000);
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html> 