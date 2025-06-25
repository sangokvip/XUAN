<?php
/**
 * 查看次数管理类
 * 防止恶意刷新页面增加查看次数
 */

class ViewCountManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->createTableIfNotExists();
    }
    
    /**
     * 创建查看记录表（如果不存在）
     */
    private function createTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS reader_view_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reader_id INT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            session_id VARCHAR(100) DEFAULT NULL,
            user_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reader_ip (reader_id, ip_address),
            INDEX idx_created_at (created_at),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='塔罗师页面查看记录表'";

        try {
            $this->db->query($sql);
            error_log("ViewCount Debug - Table created or already exists");
        } catch (Exception $e) {
            error_log("ViewCount Debug - Table creation error: " . $e->getMessage());
        }

        // 检查readers表是否有view_count字段
        try {
            $this->db->fetchOne("SELECT view_count FROM readers LIMIT 1");
            error_log("ViewCount Debug - readers.view_count field exists");
        } catch (Exception $e) {
            try {
                $this->db->query("ALTER TABLE readers ADD COLUMN view_count INT DEFAULT 0 COMMENT '页面查看次数'");
                error_log("ViewCount Debug - Added view_count field to readers table");
            } catch (Exception $e2) {
                error_log("ViewCount Debug - Failed to add view_count field: " . $e2->getMessage());
            }
        }
    }
    
    /**
     * 记录页面查看并更新查看次数
     * @param int $readerId 塔罗师ID
     * @param int $cooldownMinutes 冷却时间（分钟）
     * @return bool 是否成功增加查看次数
     */
    public function recordView($readerId, $cooldownMinutes = 30) {
        // 确保session已启动
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $ipAddress = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $sessionId = session_id();
        $userId = $_SESSION['user_id'] ?? null;

        // 调试信息（生产环境应该移除）
        error_log("ViewCount Debug - Reader: $readerId, IP: $ipAddress, Session: $sessionId, User: $userId");

        // 检查是否在冷却时间内
        if ($this->isInCooldown($readerId, $ipAddress, $sessionId, $userId, $cooldownMinutes)) {
            error_log("ViewCount Debug - In cooldown, not recording");
            return false; // 在冷却时间内，不增加查看次数
        }

        try {
            // 记录查看日志
            $logId = $this->db->insert('reader_view_logs', [
                'reader_id' => $readerId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'session_id' => $sessionId,
                'user_id' => $userId
            ]);

            error_log("ViewCount Debug - Log inserted with ID: $logId");

            // 更新塔罗师查看次数
            $updateResult = $this->db->query(
                "UPDATE readers SET view_count = COALESCE(view_count, 0) + 1 WHERE id = ?",
                [$readerId]
            );

            error_log("ViewCount Debug - Update result: " . ($updateResult ? 'success' : 'failed'));
            error_log("ViewCount Debug - Successfully recorded view");
            return true;

        } catch (Exception $e) {
            error_log("ViewCount Debug - Error: " . $e->getMessage());
            error_log("ViewCount Debug - Error trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * 检查是否在冷却时间内
     * @param int $readerId 塔罗师ID
     * @param string $ipAddress IP地址
     * @param string $sessionId Session ID
     * @param int|null $userId 用户ID
     * @param int $cooldownMinutes 冷却时间（分钟）
     * @return bool 是否在冷却时间内
     */
    private function isInCooldown($readerId, $ipAddress, $sessionId, $userId, $cooldownMinutes) {
        // 使用数据库时间而不是PHP时间，避免时区问题
        $sql = "SELECT COUNT(*) as count FROM reader_view_logs
                WHERE reader_id = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
        $params = [$readerId, $ipAddress, $cooldownMinutes];

        // 如果是登录用户，同时检查用户ID（更严格）
        if ($userId) {
            $sql = "SELECT COUNT(*) as count FROM reader_view_logs
                    WHERE reader_id = ? AND (ip_address = ? OR user_id = ?) AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
            $params = [$readerId, $ipAddress, $userId, $cooldownMinutes];
        }

        error_log("ViewCount Debug - Cooldown SQL: $sql");
        error_log("ViewCount Debug - Cooldown Params: " . json_encode($params));

        try {
            $result = $this->db->fetchOne($sql, $params);
            $count = $result['count'] ?? 0;
            error_log("ViewCount Debug - Found $count recent views");
            return $count > 0;
        } catch (Exception $e) {
            error_log("ViewCount Debug - Cooldown check error: " . $e->getMessage());
            // 如果查询失败，为了安全起见，假设不在冷却期
            return false;
        }
    }
    
    /**
     * 获取客户端真实IP地址（公共方法）
     * @return string IP地址
     */
    public function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                // 验证IP地址格式
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * 获取塔罗师的查看次数
     * @param int $readerId 塔罗师ID
     * @return int 查看次数
     */
    public function getViewCount($readerId) {
        $result = $this->db->fetchOne("SELECT view_count FROM readers WHERE id = ?", [$readerId]);
        return (int)($result['view_count'] ?? 0);
    }
    
    /**
     * 获取塔罗师的查看统计信息
     * @param int $readerId 塔罗师ID
     * @return array 统计信息
     */
    public function getViewStats($readerId) {
        // 总查看次数
        $totalViews = $this->getViewCount($readerId);
        
        // 今日查看次数（使用数据库时间）
        $todayViews = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM reader_view_logs
             WHERE reader_id = ? AND DATE(created_at) = CURDATE()",
            [$readerId]
        )['count'] ?? 0;

        // 本周查看次数（使用数据库时间）
        $weekViews = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM reader_view_logs
             WHERE reader_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$readerId]
        )['count'] ?? 0;
        
        // 独立访客数（基于IP）
        $uniqueVisitors = $this->db->fetchOne(
            "SELECT COUNT(DISTINCT ip_address) as count FROM reader_view_logs 
             WHERE reader_id = ?",
            [$readerId]
        )['count'] ?? 0;
        
        return [
            'total_views' => $totalViews,
            'today_views' => $todayViews,
            'week_views' => $weekViews,
            'unique_visitors' => $uniqueVisitors
        ];
    }
    
    /**
     * 清理过期的查看记录（管理员功能）
     * @param int $daysToKeep 保留天数
     * @return int 清理的记录数
     */
    public function cleanupOldRecords($daysToKeep = 90) {
        try {
            // 尝试使用存储过程
            $result = $this->db->query("CALL CleanupViewLogs(?)", [$daysToKeep]);
            $row = $result->fetch();
            return $row['deleted_rows'] ?? 0;
        } catch (Exception $e) {
            // 如果存储过程不存在，使用数据库时间直接删除
            $result = $this->db->query(
                "DELETE FROM reader_view_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$daysToKeep]
            );

            return $result->rowCount();
        }
    }
    
    /**
     * 管理员重置塔罗师查看次数
     * @param int $readerId 塔罗师ID
     * @param int $newCount 新的查看次数
     * @return bool 是否成功
     */
    public function resetViewCount($readerId, $newCount = 0) {
        try {
            $this->db->query(
                "UPDATE readers SET view_count = ? WHERE id = ?",
                [$newCount, $readerId]
            );
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 获取查看次数排行榜
     * @param int $limit 返回数量
     * @param string $period 时间周期：'all', 'week', 'month'
     * @return array 排行榜数据
     */
    public function getViewRanking($limit = 10, $period = 'all') {
        if ($period === 'all') {
            $sql = "SELECT r.id, r.full_name, r.view_count, r.is_featured
                    FROM readers r 
                    WHERE r.is_active = 1 
                    ORDER BY r.view_count DESC 
                    LIMIT ?";
            $params = [$limit];
        } else {
            $timeCondition = $period === 'week' ? 'INTERVAL 7 DAY' : 'INTERVAL 30 DAY';
            $sql = "SELECT r.id, r.full_name, r.view_count, r.is_featured,
                           COUNT(vl.id) as period_views
                    FROM readers r 
                    LEFT JOIN reader_view_logs vl ON r.id = vl.reader_id 
                        AND vl.created_at >= DATE_SUB(NOW(), {$timeCondition})
                    WHERE r.is_active = 1 
                    GROUP BY r.id, r.full_name, r.view_count, r.is_featured
                    ORDER BY period_views DESC, r.view_count DESC 
                    LIMIT ?";
            $params = [$limit];
        }
        
        return $this->db->fetchAll($sql, $params);
    }
}
?>
