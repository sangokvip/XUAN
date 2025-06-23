<?php
/**
 * 消息管理系统
 * 处理管理员消息的发送、接收和阅读状态
 */
class MessageManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 获取用户未读消息数量
     * @param int $userId 用户ID
     * @param string $userType 用户类型：'user' 或 'reader'
     * @return int 未读消息数量
     */
    public function getUnreadCount($userId, $userType) {
        try {
            // 获取针对该用户类型的所有消息
            $targetCondition = $userType === 'user' ? 
                "target_type IN ('user', 'all')" : 
                "target_type IN ('reader', 'all')";
            
            $result = $this->db->fetchOne("
                SELECT COUNT(*) as count 
                FROM admin_messages m 
                WHERE {$targetCondition}
                AND m.id NOT IN (
                    SELECT mr.message_id 
                    FROM message_reads mr 
                    WHERE mr.user_id = ? AND mr.user_type = ?
                )
            ", [$userId, $userType]);
            
            return (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * 获取用户的消息列表
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 消息列表
     */
    public function getUserMessages($userId, $userType, $limit = 20, $offset = 0) {
        try {
            $targetCondition = $userType === 'user' ? 
                "target_type IN ('user', 'all')" : 
                "target_type IN ('reader', 'all')";
            
            $messages = $this->db->fetchAll("
                SELECT m.*, 
                       CASE WHEN mr.id IS NOT NULL THEN 1 ELSE 0 END as is_read,
                       mr.read_at
                FROM admin_messages m 
                LEFT JOIN message_reads mr ON m.id = mr.message_id 
                    AND mr.user_id = ? AND mr.user_type = ?
                WHERE {$targetCondition}
                ORDER BY m.created_at DESC 
                LIMIT ? OFFSET ?
            ", [$userId, $userType, $limit, $offset]);
            
            return $messages;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * 标记消息为已读
     * @param int $messageId 消息ID
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @return bool 是否成功
     */
    public function markAsRead($messageId, $userId, $userType) {
        try {
            // 检查是否已经标记为已读
            $existing = $this->db->fetchOne(
                "SELECT id FROM message_reads WHERE message_id = ? AND user_id = ? AND user_type = ?",
                [$messageId, $userId, $userType]
            );
            
            if (!$existing) {
                $this->db->insert('message_reads', [
                    'message_id' => $messageId,
                    'user_id' => $userId,
                    'user_type' => $userType
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 批量标记消息为已读
     * @param array $messageIds 消息ID数组
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @return bool 是否成功
     */
    public function markMultipleAsRead($messageIds, $userId, $userType) {
        if (empty($messageIds)) {
            return true;
        }
        
        try {
            $this->db->beginTransaction();
            
            foreach ($messageIds as $messageId) {
                $this->markAsRead($messageId, $userId, $userType);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    /**
     * 获取消息详情
     * @param int $messageId 消息ID
     * @param int $userId 用户ID（用于检查权限）
     * @param string $userType 用户类型
     * @return array|null 消息详情
     */
    public function getMessageDetail($messageId, $userId, $userType) {
        try {
            $targetCondition = $userType === 'user' ? 
                "target_type IN ('user', 'all')" : 
                "target_type IN ('reader', 'all')";
            
            $message = $this->db->fetchOne("
                SELECT m.*, 
                       CASE WHEN mr.id IS NOT NULL THEN 1 ELSE 0 END as is_read,
                       mr.read_at
                FROM admin_messages m 
                LEFT JOIN message_reads mr ON m.id = mr.message_id 
                    AND mr.user_id = ? AND mr.user_type = ?
                WHERE m.id = ? AND {$targetCondition}
            ", [$userId, $userType, $messageId]);
            
            return $message;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * 检查消息系统是否已安装
     * @return bool 是否已安装
     */
    public function isInstalled() {
        try {
            $this->db->fetchOne("SELECT 1 FROM admin_messages LIMIT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 获取消息统计信息（管理员用）
     * @return array 统计信息
     */
    public function getMessageStats() {
        try {
            $stats = $this->db->fetchOne("
                SELECT 
                    COUNT(*) as total_messages,
                    COUNT(CASE WHEN target_type = 'user' THEN 1 END) as user_messages,
                    COUNT(CASE WHEN target_type = 'reader' THEN 1 END) as reader_messages,
                    COUNT(CASE WHEN target_type = 'all' THEN 1 END) as all_messages,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_messages
                FROM admin_messages
            ");
            
            // 获取总阅读数
            $readStats = $this->db->fetchOne("
                SELECT COUNT(*) as total_reads
                FROM message_reads
            ");
            
            $stats['total_reads'] = $readStats['total_reads'] ?? 0;
            
            return $stats;
        } catch (Exception $e) {
            return [
                'total_messages' => 0,
                'user_messages' => 0,
                'reader_messages' => 0,
                'all_messages' => 0,
                'recent_messages' => 0,
                'total_reads' => 0
            ];
        }
    }
    
    /**
     * 删除消息（管理员用）
     * @param int $messageId 消息ID
     * @return bool 是否成功
     */
    public function deleteMessage($messageId) {
        try {
            $this->db->beginTransaction();
            
            // 删除阅读记录
            $this->db->delete('message_reads', 'message_id = ?', [$messageId]);
            
            // 删除消息
            $this->db->delete('admin_messages', 'id = ?', [$messageId]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    /**
     * 获取消息的详细阅读统计（管理员用）
     * @param int $messageId 消息ID
     * @return array 阅读统计
     */
    public function getMessageReadStats($messageId) {
        try {
            $message = $this->db->fetchOne("SELECT * FROM admin_messages WHERE id = ?", [$messageId]);
            if (!$message) {
                return null;
            }
            
            $readCount = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM message_reads WHERE message_id = ?",
                [$messageId]
            )['count'];
            
            // 计算目标用户总数
            $targetCount = 0;
            switch ($message['target_type']) {
                case 'user':
                    $targetCount = $this->db->fetchOne("SELECT COUNT(*) as count FROM users")['count'];
                    break;
                case 'reader':
                    $targetCount = $this->db->fetchOne("SELECT COUNT(*) as count FROM readers")['count'];
                    break;
                case 'all':
                    $userCount = $this->db->fetchOne("SELECT COUNT(*) as count FROM users")['count'];
                    $readerCount = $this->db->fetchOne("SELECT COUNT(*) as count FROM readers")['count'];
                    $targetCount = $userCount + $readerCount;
                    break;
            }
            
            return [
                'message' => $message,
                'read_count' => $readCount,
                'target_count' => $targetCount,
                'read_rate' => $targetCount > 0 ? round(($readCount / $targetCount) * 100, 1) : 0
            ];
        } catch (Exception $e) {
            return null;
        }
    }
}
