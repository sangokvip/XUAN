<?php

class InvitationManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 生成邀请链接
     * @param int $inviterId 邀请人ID
     * @param string $inviterType 邀请人类型 (reader/user)
     * @return string 邀请链接token
     */
    public function generateInvitationLink($inviterId, $inviterType) {
        $token = bin2hex(random_bytes(32));
        
        $this->db->insert('invitation_links', [
            'inviter_id' => $inviterId,
            'inviter_type' => $inviterType,
            'token' => $token,
            'expires_at' => null, // 永不过期
            'is_active' => 1
        ]);
        
        return $token;
    }
    
    /**
     * 获取邀请链接
     * @param int $inviterId 邀请人ID
     * @param string $inviterType 邀请人类型
     * @return string|null 邀请链接token
     */
    public function getInvitationLink($inviterId, $inviterType) {
        $link = $this->db->fetchOne(
            "SELECT token FROM invitation_links 
             WHERE inviter_id = ? AND inviter_type = ? AND is_active = 1 
             ORDER BY created_at DESC LIMIT 1",
            [$inviterId, $inviterType]
        );
        
        return $link ? $link['token'] : null;
    }

    /**
     * 根据token获取邀请信息
     * @param string $token 邀请token
     * @return array|null 邀请信息
     */
    public function getInvitationByToken($token) {
        return $this->db->fetchOne(
            "SELECT * FROM invitation_links WHERE token = ? AND is_active = 1",
            [$token]
        );
    }

    /**
     * 验证邀请链接
     * @param string $token 邀请链接token
     * @return array|null 邀请人信息
     */
    public function validateInvitationLink($token) {
        return $this->db->fetchOne(
            "SELECT * FROM invitation_links 
             WHERE token = ? AND is_active = 1 
             AND (expires_at IS NULL OR expires_at > NOW())",
            [$token]
        );
    }
    
    /**
     * 记录邀请关系
     * @param string $token 邀请链接token
     * @param int $inviteeId 被邀请人ID
     * @param string $inviteeType 被邀请人类型
     * @return bool 是否成功
     */
    public function recordInvitationRelation($token, $inviteeId, $inviteeType) {
        $invitation = $this->validateInvitationLink($token);
        if (!$invitation) {
            return false;
        }
        
        try {
            $this->db->beginTransaction();
            
            // 记录邀请关系
            $this->db->insert('invitation_relations', [
                'inviter_id' => $invitation['inviter_id'],
                'inviter_type' => $invitation['inviter_type'],
                'invitee_id' => $inviteeId,
                'invitee_type' => $inviteeType,
                'invitation_token' => $token
            ]);
            
            // 更新被邀请人的邀请人信息
            $table = $inviteeType === 'reader' ? 'readers' : 'users';
            $this->db->update($table, [
                'invited_by' => $invitation['inviter_id'],
                'invited_by_type' => $invitation['inviter_type']
            ], 'id = ?', [$inviteeId]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    /**
     * 处理邀请返点
     * @param int $transactionId 交易ID
     * @param int $spenderId 消费者ID
     * @param string $spenderType 消费者类型
     * @param float $amount 消费金额
     * @return bool 是否处理成功
     */
    public function processInvitationCommission($transactionId, $spenderId, $spenderType, $amount) {
        // 获取消费者的邀请人信息
        $table = $spenderType === 'reader' ? 'readers' : 'users';
        $spender = $this->db->fetchOne(
            "SELECT invited_by, invited_by_type FROM {$table} WHERE id = ?",
            [$spenderId]
        );

        if (!$spender || !$spender['invited_by']) {
            return false; // 没有邀请人
        }

        // 获取返点比例设置
        $commissionRate = $this->getCommissionRate();
        $commissionAmount = $amount * $commissionRate / 100;

        if ($commissionAmount <= 0) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            // 给邀请人增加Tata Coin
            require_once __DIR__ . '/TataCoinManager.php';
            $tataCoinManager = new TataCoinManager();

            $inviterTable = $spender['invited_by_type'] === 'reader' ? 'readers' : 'users';
            $inviterName = $this->db->fetchOne(
                "SELECT full_name FROM {$inviterTable} WHERE id = ?",
                [$spender['invited_by']]
            )['full_name'] ?? '未知用户';

            $spenderName = $this->db->fetchOne(
                "SELECT full_name FROM {$table} WHERE id = ?",
                [$spenderId]
            )['full_name'] ?? '未知用户';

            $description = "邀请返点：{$spenderName}消费{$amount}币，返点{$commissionAmount}币";

            $tataCoinManager->earn(
                $spender['invited_by'],
                $spender['invited_by_type'],
                $commissionAmount,
                $description,
                $spenderId,
                $spenderType
            );

            // 记录返点记录
            $this->db->insert('invitation_commissions', [
                'inviter_id' => $spender['invited_by'],
                'inviter_type' => $spender['invited_by_type'],
                'invitee_id' => $spenderId,
                'invitee_type' => $spenderType,
                'transaction_id' => $transactionId,
                'commission_amount' => $commissionAmount,
                'commission_rate' => $commissionRate,
                'original_amount' => $amount,
                'description' => $description
            ]);

            // 更新交易记录，标记为返点相关
            $this->db->query(
                "UPDATE tata_coin_transactions SET
                 is_commission = TRUE,
                 commission_from_user_id = ?,
                 commission_from_user_type = ?
                 WHERE id = (SELECT MAX(id) FROM tata_coin_transactions WHERE user_id = ? AND user_type = ? AND description LIKE ?)",
                [$spenderId, $spenderType, $spender['invited_by'], $spender['invited_by_type'], "%邀请返点%"]
            );

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Invitation commission error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 处理塔罗师收益时的邀请返点
     * @param int $readerId 塔罗师ID
     * @param float $earnings 塔罗师收益金额
     * @return bool 是否处理成功
     */
    public function processReaderEarningsCommission($readerId, $earnings) {
        // 获取塔罗师的邀请人信息
        $reader = $this->db->fetchOne(
            "SELECT invited_by, invited_by_type, full_name FROM readers WHERE id = ?",
            [$readerId]
        );

        if (!$reader || !$reader['invited_by'] || $reader['invited_by_type'] !== 'reader') {
            return false; // 没有塔罗师邀请人
        }

        // 获取塔罗师邀请返点比例设置
        $commissionRate = $this->getReaderInvitationCommissionRate();
        $commissionAmount = round($earnings * $commissionRate / 100); // 四舍五入取整数

        if ($commissionAmount <= 0) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            // 给邀请塔罗师增加Tata Coin
            require_once __DIR__ . '/TataCoinManager.php';
            $tataCoinManager = new TataCoinManager();

            $inviterName = $this->db->fetchOne(
                "SELECT full_name FROM readers WHERE id = ?",
                [$reader['invited_by']]
            )['full_name'] ?? '未知塔罗师';

            $description = "塔罗师邀请返点：{$reader['full_name']}收益{$earnings}币，返点{$commissionAmount}币";

            $tataCoinManager->earn(
                $reader['invited_by'],
                'reader',
                $commissionAmount,
                $description,
                $readerId,
                'reader'
            );

            // 记录返点记录
            $this->db->insert('invitation_commissions', [
                'inviter_id' => $reader['invited_by'],
                'inviter_type' => 'reader',
                'invitee_id' => $readerId,
                'invitee_type' => 'reader',
                'transaction_id' => 0, // 塔罗师收益返点没有特定的交易ID
                'commission_amount' => $commissionAmount,
                'commission_rate' => $commissionRate,
                'original_amount' => $earnings,
                'description' => $description
            ]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Reader earnings commission error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取邀请返点比例
     * @return float 返点比例
     */
    public function getCommissionRate() {
        $setting = $this->db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'invitation_commission_rate'"
        );

        return $setting ? (float)$setting['setting_value'] : 5.0;
    }

    /**
     * 获取塔罗师邀请塔罗师的返点比例
     * @return float 返点比例
     */
    public function getReaderInvitationCommissionRate() {
        $setting = $this->db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'reader_invitation_commission_rate'"
        );

        return $setting ? (float)$setting['setting_value'] : 20.0;
    }
    
    /**
     * 获取邀请统计
     * @param int $inviterId 邀请人ID
     * @param string $inviterType 邀请人类型
     * @return array 邀请统计数据
     */
    public function getInvitationStats($inviterId, $inviterType) {
        // 邀请的用户数量
        $userCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM invitation_relations 
             WHERE inviter_id = ? AND inviter_type = ? AND invitee_type = 'user'",
            [$inviterId, $inviterType]
        )['count'] ?? 0;
        
        // 邀请的塔罗师数量
        $readerCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM invitation_relations 
             WHERE inviter_id = ? AND inviter_type = ? AND invitee_type = 'reader'",
            [$inviterId, $inviterType]
        )['count'] ?? 0;
        
        // 总返点金额
        $totalCommission = $this->db->fetchOne(
            "SELECT SUM(commission_amount) as total FROM invitation_commissions 
             WHERE inviter_id = ? AND inviter_type = ?",
            [$inviterId, $inviterType]
        )['total'] ?? 0;
        
        // 本月返点金额
        $monthlyCommission = $this->db->fetchOne(
            "SELECT SUM(commission_amount) as total FROM invitation_commissions 
             WHERE inviter_id = ? AND inviter_type = ? 
             AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
             AND YEAR(created_at) = YEAR(CURRENT_DATE())",
            [$inviterId, $inviterType]
        )['total'] ?? 0;
        
        return [
            'invited_users' => $userCount,
            'invited_readers' => $readerCount,
            'total_invitees' => $userCount + $readerCount,
            'total_commission' => $totalCommission,
            'monthly_commission' => $monthlyCommission
        ];
    }
    
    /**
     * 获取返点记录
     * @param int $inviterId 邀请人ID
     * @param string $inviterType 邀请人类型
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 返点记录
     */
    public function getCommissionHistory($inviterId, $inviterType, $limit = 20, $offset = 0) {
        return $this->db->fetchAll(
            "SELECT ic.*, 
                    CASE 
                        WHEN ic.invitee_type = 'user' THEN u.full_name
                        ELSE r.full_name
                    END as invitee_name
             FROM invitation_commissions ic
             LEFT JOIN users u ON ic.invitee_id = u.id AND ic.invitee_type = 'user'
             LEFT JOIN readers r ON ic.invitee_id = r.id AND ic.invitee_type = 'reader'
             WHERE ic.inviter_id = ? AND ic.inviter_type = ?
             ORDER BY ic.created_at DESC
             LIMIT ? OFFSET ?",
            [$inviterId, $inviterType, $limit, $offset]
        );
    }

    /**
     * 获取被邀请用户的详细信息
     * @param int $inviterId 邀请人ID
     * @param string $inviterType 邀请人类型
     * @return array 被邀请用户列表
     */
    public function getInvitedUsersDetails($inviterId, $inviterType) {
        // 获取被邀请的用户
        $invitedUsers = $this->db->fetchAll(
            "SELECT u.id, u.full_name, u.email, u.created_at,
                    COALESCE(SUM(ABS(t.amount)), 0) as total_spent,
                    COUNT(DISTINCT t.id) as transaction_count
             FROM users u
             LEFT JOIN tata_coin_transactions t ON u.id = t.user_id AND t.user_type = 'user' AND t.amount < 0
             WHERE u.invited_by = ? AND u.invited_by_type = ?
             GROUP BY u.id, u.full_name, u.email, u.created_at
             ORDER BY u.created_at DESC",
            [$inviterId, $inviterType]
        );

        // 获取被邀请的塔罗师
        $invitedReaders = $this->db->fetchAll(
            "SELECT r.id, r.full_name, r.email, r.created_at,
                    COALESCE(SUM(t.amount), 0) as total_earned,
                    COUNT(DISTINCT t.id) as transaction_count
             FROM readers r
             LEFT JOIN tata_coin_transactions t ON r.id = t.user_id AND t.user_type = 'reader' AND t.amount > 0
             WHERE r.invited_by = ? AND r.invited_by_type = ?
             GROUP BY r.id, r.full_name, r.email, r.created_at
             ORDER BY r.created_at DESC",
            [$inviterId, $inviterType]
        );

        return [
            'users' => $invitedUsers,
            'readers' => $invitedReaders
        ];
    }

    /**
     * 检查邀请系统是否已安装
     * @return bool
     */
    public function isInstalled() {
        try {
            $this->db->fetchOne("SELECT 1 FROM invitation_links LIMIT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
