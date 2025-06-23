<?php
/**
 * Tata Coin管理系统
 * 处理Tata Coin的所有操作：消费、获得、转账等
 */
class TataCoinManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 获取用户的Tata Coin余额
     * @param int $userId 用户ID
     * @param string $userType 用户类型：'user' 或 'reader'
     * @return int 余额
     */
    public function getBalance($userId, $userType = 'user') {
        $table = $userType === 'reader' ? 'readers' : 'users';
        $result = $this->db->fetchOne("SELECT tata_coin FROM {$table} WHERE id = ?", [$userId]);
        return $result ? (int)$result['tata_coin'] : 0;
    }
    
    /**
     * 消费Tata Coin
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @param int $amount 消费金额
     * @param string $description 消费描述
     * @param int $relatedUserId 关联用户ID（如塔罗师ID）
     * @param string $relatedUserType 关联用户类型
     * @return bool 是否成功
     */
    public function spend($userId, $userType, $amount, $description, $relatedUserId = null, $relatedUserType = null) {
        if ($amount <= 0) {
            throw new Exception('消费金额必须大于0');
        }
        
        $currentBalance = $this->getBalance($userId, $userType);
        if ($currentBalance < $amount) {
            throw new Exception('Tata Coin余额不足');
        }
        
        $newBalance = $currentBalance - $amount;
        
        try {
            $this->db->beginTransaction();
            
            // 更新用户余额
            $table = $userType === 'reader' ? 'readers' : 'users';
            $this->db->query("UPDATE {$table} SET tata_coin = ? WHERE id = ?", [$newBalance, $userId]);
            
            // 记录交易
            $this->recordTransaction($userId, $userType, 'spend', -$amount, $newBalance, $description, $relatedUserId, $relatedUserType);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 获得Tata Coin
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @param int $amount 获得金额
     * @param string $description 获得描述
     * @param int $relatedUserId 关联用户ID
     * @param string $relatedUserType 关联用户类型
     * @return bool 是否成功
     */
    public function earn($userId, $userType, $amount, $description, $relatedUserId = null, $relatedUserType = null) {
        if ($amount <= 0) {
            throw new Exception('获得金额必须大于0');
        }
        
        $currentBalance = $this->getBalance($userId, $userType);
        $newBalance = $currentBalance + $amount;
        
        try {
            $this->db->beginTransaction();
            
            // 更新用户余额
            $table = $userType === 'reader' ? 'readers' : 'users';
            $this->db->query("UPDATE {$table} SET tata_coin = ? WHERE id = ?", [$newBalance, $userId]);
            
            // 记录交易
            $this->recordTransaction($userId, $userType, 'earn', $amount, $newBalance, $description, $relatedUserId, $relatedUserType);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 管理员调整用户余额
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @param int $amount 调整金额（正数为增加，负数为减少）
     * @param string $description 调整描述
     * @return bool 是否成功
     */
    public function adminAdjust($userId, $userType, $amount, $description) {
        if ($amount == 0) {
            throw new Exception('调整金额不能为0');
        }
        
        $currentBalance = $this->getBalance($userId, $userType);
        $newBalance = $currentBalance + $amount;
        
        if ($newBalance < 0) {
            throw new Exception('调整后余额不能为负数');
        }
        
        try {
            $this->db->beginTransaction();
            
            // 更新用户余额
            $table = $userType === 'reader' ? 'readers' : 'users';
            $this->db->query("UPDATE {$table} SET tata_coin = ? WHERE id = ?", [$newBalance, $userId]);
            
            // 记录交易
            $transactionType = $amount > 0 ? 'admin_add' : 'admin_subtract';
            $this->recordTransaction($userId, $userType, $transactionType, $amount, $newBalance, $description);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 查看塔罗师联系方式（付费功能）
     * @param int $userId 用户ID
     * @param int $readerId 塔罗师ID
     * @return array 包含是否成功和塔罗师信息
     */
    public function viewReaderContact($userId, $readerId) {
        // 获取塔罗师信息
        $reader = $this->db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);
        if (!$reader) {
            throw new Exception('塔罗师不存在');
        }
        
        // 检查是否已经付费查看过
        $existingRecord = $this->db->fetchOne(
            "SELECT * FROM user_browse_history WHERE user_id = ? AND reader_id = ? AND browse_type = 'paid'",
            [$userId, $readerId]
        );
        
        if ($existingRecord) {
            // 已经付费查看过，直接返回联系方式
            return [
                'success' => true,
                'already_paid' => true,
                'reader' => $reader,
                'cost' => 0
            ];
        }
        
        // 确定费用
        $cost = $reader['is_featured'] ? $this->getSetting('featured_reader_cost', 30) : $this->getSetting('normal_reader_cost', 10);
        
        // 检查用户余额
        $userBalance = $this->getBalance($userId, 'user');
        if ($userBalance < $cost) {
            throw new Exception("Tata Coin余额不足，需要 {$cost} 个Tata Coin");
        }
        
        try {
            $this->db->beginTransaction();

            // 计算分成
            $commissionRate = $this->getSetting('reader_commission_rate', 50);
            $readerEarning = intval($cost * $commissionRate / 100);

            // 获取当前余额
            $userCurrentBalance = $this->getBalance($userId, 'user');
            $readerCurrentBalance = $this->getBalance($readerId, 'reader');

            // 计算新余额
            $userNewBalance = $userCurrentBalance - $cost;
            $readerNewBalance = $readerCurrentBalance + $readerEarning;

            // 更新用户余额
            $this->db->query("UPDATE users SET tata_coin = ? WHERE id = ?", [$userNewBalance, $userId]);

            // 更新塔罗师余额
            if ($readerEarning > 0) {
                $this->db->query("UPDATE readers SET tata_coin = ? WHERE id = ?", [$readerNewBalance, $readerId]);
            }

            // 记录用户交易
            $this->recordTransaction($userId, 'user', 'spend', -$cost, $userNewBalance, "查看塔罗师 {$reader['full_name']} 的联系方式", $readerId, 'reader');

            // 记录塔罗师收益
            if ($readerEarning > 0) {
                $this->recordTransaction($readerId, 'reader', 'earn', $readerEarning, $readerNewBalance, "用户查看联系方式分成", $userId, 'user');

                // 处理塔罗师收益的邀请返点
                try {
                    require_once __DIR__ . '/InvitationManager.php';
                    $invitationManager = new InvitationManager();
                    if ($invitationManager->isInstalled()) {
                        $invitationManager->processReaderEarningsCommission($readerId, $readerEarning);
                    }
                } catch (Exception $e) {
                    // 邀请返点失败不影响主流程
                    error_log("Reader earnings commission failed: " . $e->getMessage());
                }
            }

            // 记录浏览历史
            $this->db->query(
                "INSERT INTO user_browse_history (user_id, reader_id, browse_type, cost) VALUES (?, ?, 'paid', ?)",
                [$userId, $readerId, $cost]
            );

            $this->db->commit();

            return [
                'success' => true,
                'already_paid' => false,
                'reader' => $reader,
                'cost' => $cost,
                'reader_earning' => $readerEarning
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 记录免费浏览
     * @param int $userId 用户ID
     * @param int $readerId 塔罗师ID
     */
    public function recordFreeBrowse($userId, $readerId) {
        $this->db->query(
            "INSERT INTO user_browse_history (user_id, reader_id, browse_type, cost) VALUES (?, ?, 'free', 0)",
            [$userId, $readerId]
        );
    }
    
    /**
     * 获取用户交易记录
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 交易记录
     */
    public function getTransactionHistory($userId, $userType, $limit = 20, $offset = 0) {
        return $this->db->fetchAll(
            "SELECT * FROM tata_coin_transactions 
             WHERE user_id = ? AND user_type = ? 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?",
            [$userId, $userType, $limit, $offset]
        );
    }
    
    /**
     * 获取用户浏览记录
     * @param int $userId 用户ID
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 浏览记录
     */
    public function getBrowseHistory($userId, $limit = 20, $offset = 0) {
        return $this->db->fetchAll(
            "SELECT h.*, r.full_name, r.photo, r.photo_circle, r.specialties, r.is_featured
             FROM user_browse_history h
             JOIN readers r ON h.reader_id = r.id
             WHERE h.user_id = ?
             ORDER BY h.created_at DESC
             LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );
    }
    
    /**
     * 记录交易
     */
    private function recordTransaction($userId, $userType, $transactionType, $amount, $balanceAfter, $description, $relatedUserId = null, $relatedUserType = null) {
        $this->db->query(
            "INSERT INTO tata_coin_transactions (user_id, user_type, transaction_type, amount, balance_after, description, related_user_id, related_user_type) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$userId, $userType, $transactionType, $amount, $balanceAfter, $description, $relatedUserId, $relatedUserType]
        );
    }
    
    /**
     * 获取网站设置
     */
    public function getSetting($key, $default = null) {
        try {
            $result = $this->db->fetchOne("SELECT setting_value FROM site_settings WHERE setting_key = ?", [$key]);
            return $result ? (int)$result['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
    
    /**
     * 为新用户初始化Tata Coin
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     */
    public function initializeNewUser($userId, $userType = 'user') {
        if ($userType === 'user') {
            // 检查是否已经初始化过
            $existingTransaction = $this->db->fetchOne(
                "SELECT id FROM tata_coin_transactions WHERE user_id = ? AND user_type = ? AND description = '新用户注册赠送'",
                [$userId, $userType]
            );

            if (!$existingTransaction) {
                $amount = $this->getSetting('new_user_tata_coin', 100);
                if ($amount > 0) {
                    $this->earn($userId, $userType, $amount, '新用户注册赠送');
                }
            }
        }
    }

    /**
     * 检查Tata Coin系统是否已安装
     * @return bool 是否已安装
     */
    public function isInstalled() {
        try {
            // 检查tata_coin字段是否存在
            $userCoinExists = $this->db->fetchOne("SHOW COLUMNS FROM users LIKE 'tata_coin'");
            $readerCoinExists = $this->db->fetchOne("SHOW COLUMNS FROM readers LIKE 'tata_coin'");
            $transactionTableExists = $this->db->fetchOne("SHOW TABLES LIKE 'tata_coin_transactions'");

            return $userCoinExists && $readerCoinExists && $transactionTableExists;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取塔罗师收益统计
     * @param int $readerId 塔罗师ID
     * @return array 收益统计
     */
    public function getReaderEarnings($readerId) {
        try {
            $stats = $this->db->fetchOne("
                SELECT
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_earnings,
                    SUM(CASE WHEN amount > 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount ELSE 0 END) as monthly_earnings,
                    COUNT(CASE WHEN amount > 0 THEN 1 END) as total_transactions
                FROM tata_coin_transactions
                WHERE user_id = ? AND user_type = 'reader'
            ", [$readerId]);

            return [
                'total_earnings' => (int)($stats['total_earnings'] ?? 0),
                'monthly_earnings' => (int)($stats['monthly_earnings'] ?? 0),
                'total_transactions' => (int)($stats['total_transactions'] ?? 0)
            ];
        } catch (Exception $e) {
            return [
                'total_earnings' => 0,
                'monthly_earnings' => 0,
                'total_transactions' => 0
            ];
        }
    }
}
