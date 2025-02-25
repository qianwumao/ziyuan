<?php
class Database {
    private $db;
    private $ziyuanDb;
    private $pageSize = 20;

    public function __construct() {
        try {
            // 连接短剧数据库
            $this->ziyuanDb = new PDO('sqlite:ziyuan.db');
            $this->ziyuanDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 连接影视数据库（保留但不使用）
            $this->db = new PDO('sqlite:yingshi.db');
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 初始化数据库表
            $this->initDatabase();
        } catch (PDOException $e) {
            throw new Exception('数据库连接失败: ' . $e->getMessage());
        }
    }

    private function initDatabase() {
        // 创建短剧表
        $this->ziyuanDb->exec('CREATE TABLE IF NOT EXISTS ziyuan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            cover TEXT NOT NULL,
            episodes INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
    }

    // 添加数据
    public function addData($title, $cover, $episodes = 0) {
        try {
            $stmt = $this->ziyuanDb->prepare('INSERT INTO ziyuan (title, cover, episodes) VALUES (:title, :cover, :episodes)');
            return $stmt->execute([
                ':title' => $title,
                ':cover' => $cover,
                ':episodes' => $episodes
            ]);
        } catch (Exception $e) {
            error_log('添加数据失败: ' . $e->getMessage());
            return false;
        }
    }

    // 更新数据
    public function updateData($id, $title, $cover, $episodes = 0) {
        try {
            $stmt = $this->ziyuanDb->prepare('UPDATE ziyuan SET title = :title, cover = :cover, episodes = :episodes WHERE id = :id');
            return $stmt->execute([
                ':id' => $id,
                ':title' => $title,
                ':cover' => $cover,
                ':episodes' => $episodes
            ]);
        } catch (Exception $e) {
            error_log('更新数据失败: ' . $e->getMessage());
            return false;
        }
    }

    // 删除数据
    public function deleteData($id) {
        try {
            $stmt = $this->ziyuanDb->prepare('DELETE FROM ziyuan WHERE id = :id');
            return $stmt->execute([':id' => $id]);
        } catch (Exception $e) {
            error_log('删除数据失败: ' . $e->getMessage());
            return false;
        }
    }

    // 获取所有数据
    public function getAllData($page = 1, $pageSize = 20) {
        try {
            $offset = ($page - 1) * $pageSize;
            $stmt = $this->ziyuanDb->prepare('SELECT * FROM ziyuan ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
            $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('获取数据失败: ' . $e->getMessage());
            return [];
        }
    }

    // 搜索数据
    public function searchData($keyword, $page = 1, $pageSize = 20) {
        try {
            $offset = ($page - 1) * $pageSize;
            $keyword = '%' . $keyword . '%';
            
            $stmt = $this->ziyuanDb->prepare('SELECT * FROM ziyuan WHERE title LIKE :keyword ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
            $stmt->bindValue(':keyword', $keyword, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('搜索数据失败: ' . $e->getMessage());
            return [];
        }
    }

    // 获取总记录数
    public function getTotalCount() {
        try {
            return $this->ziyuanDb->query('SELECT COUNT(*) FROM ziyuan')->fetchColumn();
        } catch (Exception $e) {
            error_log('获取总记录数失败: ' . $e->getMessage());
            return 0;
        }
    }

    // 获取搜索结果数量
    public function searchCount($keyword) {
        try {
            $keyword = '%' . $keyword . '%';
            $stmt = $this->ziyuanDb->prepare('SELECT COUNT(*) FROM ziyuan WHERE title LIKE :keyword');
            $stmt->bindValue(':keyword', $keyword, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log('获取搜索结果数量失败: ' . $e->getMessage());
            return 0;
        }
    }

    public function getYingshi($keyword, $page = 1) {
        try {
            $results = [];
            $keyword = '%' . $keyword . '%';
            
            // 查询短剧数据库
            $stmt = $this->ziyuanDb->prepare('SELECT * FROM ziyuan WHERE title LIKE :keyword ORDER BY created_at DESC');
            $stmt->bindParam(':keyword', $keyword, PDO::PARAM_STR);
            $stmt->execute();
            $dbResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 格式化数据库结果
            $results = [];
            foreach ($dbResults as $row) {
                $results[] = [
                    'title' => $row['title'],
                    'data_url' => $row['cover'],
                    'source' => 'ziyuan'
                ];
            }

            // 计算总数
            $totalCount = count($results);

            // 处理分页
            $offset = ($page - 1) * $this->pageSize;
            $pagedResults = array_slice($results, $offset, $this->pageSize);

            return [
                'data' => $pagedResults,
                'count' => $totalCount,
                'page' => $page,
                'pageSize' => $this->pageSize
            ];

        } catch (Exception $e) {
            throw new Exception('获取数据失败: ' . $e->getMessage());
        }
    }

    public function getStats() {
        try {
            // 获取短剧数据库统计
            $total = $this->ziyuanDb->query('SELECT COUNT(*) FROM ziyuan')->fetchColumn();
            $today = $this->ziyuanDb->query('SELECT COUNT(*) FROM ziyuan WHERE date(created_at) = date("now")')->fetchColumn();
            $lastUpdate = $this->ziyuanDb->query('SELECT created_at FROM ziyuan ORDER BY created_at DESC LIMIT 1')->fetchColumn();
            
            return [
                'total' => $total,
                'today' => $today,
                'lastUpdate' => $lastUpdate ? date('Y-m-d H:i:s', strtotime($lastUpdate)) : '无数据'
            ];
        } catch (Exception $e) {
            error_log("获取统计数据失败: " . $e->getMessage());
            return [
                'total' => 0,
                'today' => 0,
                'lastUpdate' => '获取失败'
            ];
        }
    }

    public function getConnection() {
        return $this->ziyuanDb;
    }

    // 获取所有数据（不分页）
    public function getAllDataWithoutPaging() {
        try {
            $stmt = $this->ziyuanDb->prepare('SELECT * FROM ziyuan ORDER BY created_at DESC');
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('获取所有数据失败: ' . $e->getMessage());
            return [];
        }
    }

    // 搜索所有匹配的数据（不分页）
    public function searchAllData($keyword) {
        try {
            $keyword = '%' . $keyword . '%';
            $stmt = $this->ziyuanDb->prepare('SELECT * FROM ziyuan WHERE title LIKE :keyword ORDER BY created_at DESC');
            $stmt->bindValue(':keyword', $keyword, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('搜索所有数据失败: ' . $e->getMessage());
            return [];
        }
    }
} 