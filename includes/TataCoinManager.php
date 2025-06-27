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
     * @param string $userType 用户类型 ('user' 或 'reader')
     * @return array 包含是否成功和塔罗师信息
     */
    public function viewReaderContact($userId, $readerId, $userType = 'user') {
        // 获取塔罗师信息
        $reader = $this->db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);
        if (!$reader) {
            throw new Exception('塔罗师不存在');
        }
        
        // 检查是否已经付费查看过
        $existingRecord = $this->db->fetchOne(
            "SELECT * FROM user_browse_history WHERE user_id = ? AND reader_id = ? AND browse_type = 'paid' AND user_type = ?",
            [$userId, $readerId, $userType]
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
        
        // 确定费用并应用等级折扣
        $originalCost = $reader['is_featured'] ? $this->getSetting('featured_reader_cost', 30) : $this->getSetting('normal_reader_cost', 10);
        $cost = $this->calculateDiscountedPrice($userId, $originalCost);
        
        // 检查用户余额
        $userBalance = $this->getBalance($userId, $userType);
        if ($userBalance < $cost) {
            throw new Exception("Tata Coin余额不足，需要 {$cost} 个Tata Coin");
        }
        
        try {
            $this->db->beginTransaction();

            // 计算分成
            $commissionRate = $this->getSetting('reader_commission_rate', 50);
            $readerEarning = intval($cost * $commissionRate / 100);

            // 获取当前余额
            $userCurrentBalance = $this->getBalance($userId, $userType);
            $readerCurrentBalance = $this->getBalance($readerId, 'reader');

            // 计算新余额
            $userNewBalance = $userCurrentBalance - $cost;
            $readerNewBalance = $readerCurrentBalance + $readerEarning;

            // 更新用户余额
            if ($userType === 'user') {
                $this->db->query("UPDATE users SET tata_coin = ? WHERE id = ?", [$userNewBalance, $userId]);
            } else {
                $this->db->query("UPDATE readers SET tata_coin = ? WHERE id = ?", [$userNewBalance, $userId]);
            }

            // 更新塔罗师余额
            if ($readerEarning > 0) {
                $this->db->query("UPDATE readers SET tata_coin = ? WHERE id = ?", [$readerNewBalance, $readerId]);
            }

            // 记录用户交易
            $this->recordTransaction($userId, $userType, 'spend', -$cost, $userNewBalance, "查看塔罗师 {$reader['full_name']} 的联系方式", $readerId, 'reader');

            // 记录塔罗师收益
            if ($readerEarning > 0) {
                $this->recordTransaction($readerId, 'reader', 'earn', $readerEarning, $readerNewBalance, ($userType === 'user' ? "用户" : "占卜师") . "查看联系方式分成", $userId, $userType);

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
                "INSERT INTO user_browse_history (user_id, reader_id, browse_type, cost, user_type) VALUES (?, ?, 'paid', ?, ?)",
                [$userId, $readerId, $cost, $userType]
            );

            $this->db->commit();

            return [
                'success' => true,
                'already_paid' => false,
                'reader' => $reader,
                'cost' => $cost,
                'original_cost' => $originalCost,
                'discount_applied' => $originalCost - $cost,
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
        // 使用insert方法，它会返回插入的ID
        $transactionId = $this->db->insert('tata_coin_transactions', [
            'user_id' => $userId,
            'user_type' => $userType,
            'transaction_type' => $transactionType,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'related_user_id' => $relatedUserId,
            'related_user_type' => $relatedUserType
        ]);

        // 如果是消费交易，处理邀请返点
        if ($transactionType === 'spend' && $amount < 0) {
            $this->processInvitationCommission($transactionId, $userId, $userType, abs($amount));
        }
    }

    /**
     * 处理邀请返点
     */
    private function processInvitationCommission($transactionId, $spenderId, $spenderType, $amount) {
        try {
            require_once __DIR__ . '/InvitationManager.php';
            $invitationManager = new InvitationManager();
            if ($invitationManager->isInstalled()) {
                $invitationManager->processInvitationCommission($transactionId, $spenderId, $spenderType, $amount);
            }
        } catch (Exception $e) {
            // 邀请返点失败不影响主流程
            error_log("Invitation commission failed: " . $e->getMessage());
        }
    }
    
    /**
     * 获取网站设置
     */
    public function getSetting($key, $default = null) {
        try {
            $result = $this->db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
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
     * 每日签到
     * @param int $userId 用户ID
     * @return array 签到结果
     */
    public function dailyCheckIn($userId) {
        try {
            $this->db->beginTransaction();

            // 检查今天是否已签到
            $today = date('Y-m-d');
            $todayCheckIn = $this->db->fetchOne(
                "SELECT * FROM daily_check_ins WHERE user_id = ? AND check_in_date = ?",
                [$userId, $today]
            );

            if ($todayCheckIn) {
                throw new Exception('今天已经签到过了');
            }

            // 获取最近的签到记录
            $lastCheckIn = $this->db->fetchOne(
                "SELECT * FROM daily_check_ins WHERE user_id = ? ORDER BY check_in_date DESC LIMIT 1",
                [$userId]
            );

            $consecutiveDays = 1;
            if ($lastCheckIn) {
                $lastDate = new DateTime($lastCheckIn['check_in_date']);
                $todayDate = new DateTime($today);
                $daysDiff = $todayDate->diff($lastDate)->days;

                if ($daysDiff == 1) {
                    // 连续签到
                    $consecutiveDays = $lastCheckIn['consecutive_days'] + 1;
                } elseif ($daysDiff > 1) {
                    // 中断了，重新开始
                    $consecutiveDays = 1;
                }
            }

            // 重置连续天数（最多7天）
            if ($consecutiveDays > 7) {
                $consecutiveDays = 1;
            }

            // 计算奖励
            $rewards = [1 => 5, 2 => 6, 3 => 7, 4 => 8, 5 => 9, 6 => 10, 7 => 12];
            $reward = $rewards[$consecutiveDays];

            // 记录签到
            $this->db->query(
                "INSERT INTO daily_check_ins (user_id, check_in_date, consecutive_days, reward_coins) VALUES (?, ?, ?, ?)",
                [$userId, $today, $consecutiveDays, $reward]
            );

            // 发放奖励
            $this->earn($userId, 'user', $reward, "每日签到奖励（连续{$consecutiveDays}天）");

            $this->db->commit();

            return [
                'success' => true,
                'consecutive_days' => $consecutiveDays,
                'reward' => $reward,
                'message' => "签到成功！连续签到{$consecutiveDays}天，获得{$reward}个Tata Coin"
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * 浏览页面奖励
     * @param int $userId 用户ID
     * @param string $pageUrl 页面URL
     * @param string $ipAddress IP地址
     * @return array 奖励结果
     */
    public function browsePageReward($userId, $pageUrl, $ipAddress) {
        try {
            $today = date('Y-m-d');

            // 检查今日同IP浏览奖励次数
            $todayCount = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM page_browse_rewards
                 WHERE ip_address = ? AND DATE(created_at) = ?",
                [$ipAddress, $today]
            )['count'];

            if ($todayCount >= 10) {
                return [
                    'success' => false,
                    'message' => '今日浏览奖励已达上限（10次）'
                ];
            }

            // 检查是否已经浏览过这个页面（防止刷页面）
            $recentBrowse = $this->db->fetchOne(
                "SELECT * FROM page_browse_rewards
                 WHERE user_id = ? AND page_url = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
                [$userId, $pageUrl]
            );

            if ($recentBrowse) {
                return [
                    'success' => false,
                    'message' => '请勿频繁刷新页面'
                ];
            }

            // 记录浏览并发放奖励
            $this->db->query(
                "INSERT INTO page_browse_rewards (user_id, page_url, ip_address, reward_coins) VALUES (?, ?, ?, 1)",
                [$userId, $pageUrl, $ipAddress]
            );

            $this->earn($userId, 'user', 1, "浏览页面奖励");

            return [
                'success' => true,
                'reward' => 1,
                'remaining_today' => 10 - $todayCount - 1,
                'message' => '浏览奖励+1 Tata Coin'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '奖励发放失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 完善个人资料奖励
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @return array 奖励结果
     */
    public function profileCompletionReward($userId, $userType = 'user') {
        try {
            // 检查是否已经获得过完善资料奖励
            $existingReward = $this->db->fetchOne(
                "SELECT * FROM tata_coin_transactions
                 WHERE user_id = ? AND user_type = ? AND description = '完善个人资料奖励'",
                [$userId, $userType]
            );

            if ($existingReward) {
                return [
                    'success' => false,
                    'message' => '已经获得过完善资料奖励'
                ];
            }

            // 检查资料完善度
            $table = $userType === 'reader' ? 'readers' : 'users';
            $user = $this->db->fetchOne("SELECT * FROM {$table} WHERE id = ?", [$userId]);

            if (!$user) {
                throw new Exception('用户不存在');
            }

            $completionScore = 0;
            $requiredFields = [];

            if ($userType === 'user') {
                // 用户资料检查
                if (!empty($user['avatar'])) $completionScore += 1;
                if (!empty($user['gender'])) $completionScore += 1;
                if (!empty($user['phone'])) $completionScore += 1;

                $requiredFields = ['avatar', 'gender', 'phone'];
            } else {
                // 塔罗师资料检查
                if (!empty($user['photo'])) $completionScore += 1;
                if (!empty($user['gender'])) $completionScore += 1;
                if (!empty($user['specialties'])) $completionScore += 1;
                if (!empty($user['description'])) $completionScore += 1;

                $requiredFields = ['photo', 'gender', 'specialties', 'description'];
            }

            // 需要完善至少3个字段才能获得奖励
            if ($completionScore >= 3) {
                $this->earn($userId, $userType, 20, '完善个人资料奖励');

                return [
                    'success' => true,
                    'reward' => 20,
                    'message' => '完善个人资料奖励+20 Tata Coin'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => '请完善更多个人资料信息',
                    'completion_score' => $completionScore,
                    'required_fields' => $requiredFields
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '奖励发放失败：' . $e->getMessage()
            ];
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
     * 邀请奖励
     * @param int $inviterId 邀请人ID
     * @param string $inviterType 邀请人类型
     * @param int $inviteeId 被邀请人ID
     * @param string $inviteeType 被邀请人类型
     * @param string $action 触发动作：'register' 或 'first_consume'
     * @return array 奖励结果
     */
    public function invitationReward($inviterId, $inviterType, $inviteeId, $inviteeType, $action) {
        try {
            $reward = 0;
            $description = '';

            if ($action === 'register' && $inviteeType === 'reader') {
                // 邀请塔罗师注册并通过认证
                $reward = 50;
                $description = '邀请塔罗师注册奖励';
            } elseif ($action === 'first_consume' && $inviteeType === 'user') {
                // 邀请普通用户注册并首次消费
                $reward = 20;
                $description = '邀请用户首次消费奖励';
            }

            if ($reward > 0) {
                // 检查是否已经发放过奖励
                $existingReward = $this->db->fetchOne(
                    "SELECT * FROM tata_coin_transactions
                     WHERE user_id = ? AND user_type = ? AND related_user_id = ? AND related_user_type = ? AND description = ?",
                    [$inviterId, $inviterType, $inviteeId, $inviteeType, $description]
                );

                if (!$existingReward) {
                    $this->earn($inviterId, $inviterType, $reward, $description, $inviteeId, $inviteeType);

                    return [
                        'success' => true,
                        'reward' => $reward,
                        'message' => "邀请奖励+{$reward} Tata Coin"
                    ];
                }
            }

            return [
                'success' => false,
                'message' => '无邀请奖励或已发放'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '邀请奖励发放失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取用户等级信息
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @return array 等级信息
     */
    public function getUserLevel($userId, $userType = 'user') {
        try {
            if ($userType === 'user') {
                // 用户等级基于累计消费
                $totalSpent = $this->db->fetchOne(
                    "SELECT SUM(ABS(amount)) as total FROM tata_coin_transactions
                     WHERE user_id = ? AND user_type = 'user' AND amount < 0",
                    [$userId]
                )['total'] ?? 0;

                $level = 1;
                $levelName = 'L1';
                $discount = 0;

                if ($totalSpent >= 1000) {
                    $level = 5;
                    $levelName = 'L5';
                    $discount = 20;
                } elseif ($totalSpent >= 501) {
                    $level = 4;
                    $levelName = 'L4';
                    $discount = 15;
                } elseif ($totalSpent >= 201) {
                    $level = 3;
                    $levelName = 'L3';
                    $discount = 10;
                } elseif ($totalSpent >= 101) {
                    $level = 2;
                    $levelName = 'L2';
                    $discount = 5;
                }

                return [
                    'level' => $level,
                    'level_name' => $levelName,
                    'total_spent' => (int)$totalSpent,
                    'discount_rate' => $discount,
                    'next_level_requirement' => $this->getNextLevelRequirement($level)
                ];

            } else {
                // 塔罗师等级：只有两种类型
                $reader = $this->db->fetchOne("SELECT is_featured FROM readers WHERE id = ?", [$userId]);

                $level = $reader['is_featured'] ? 2 : 1;
                $levelName = $reader['is_featured'] ? '推荐塔罗师' : '塔罗师';
                $priority = $reader['is_featured'] ? 100 : 0;

                $earnings = $this->getReaderEarnings($userId);

                return [
                    'level' => $level,
                    'level_name' => $levelName,
                    'total_earnings' => $earnings['total_earnings'],
                    'is_featured' => $reader['is_featured'],
                    'priority_score' => $priority
                ];
            }

        } catch (Exception $e) {
            return [
                'level' => 1,
                'level_name' => $userType === 'user' ? '新手' : '新人塔罗师',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 获取下一等级要求
     */
    private function getNextLevelRequirement($currentLevel) {
        $requirements = [
            1 => 101,  // L1 -> L2
            2 => 201,  // L2 -> L3
            3 => 501,  // L3 -> L4
            4 => 1000, // L4 -> L5
            5 => null  // 已是最高等级
        ];

        return $requirements[$currentLevel] ?? null;
    }

    /**
     * 计算用户等级折扣后的价格
     * @param int $userId 用户ID
     * @param int $originalPrice 原价
     * @return int 折扣后价格
     */
    public function calculateDiscountedPrice($userId, $originalPrice) {
        $userLevel = $this->getUserLevel($userId, 'user');
        $discountRate = $userLevel['discount_rate'] ?? 0;

        if ($discountRate > 0) {
            $discountedPrice = $originalPrice * (100 - $discountRate) / 100;
            return max(1, intval($discountedPrice)); // 最低1个coin
        }

        return $originalPrice;
    }

    /**
     * 获取每日收益限制
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @return array 限制信息
     */
    public function getDailyEarningsLimit($userId, $userType = 'user') {
        $today = date('Y-m-d');

        // 获取今日已获得的coin（不包括消费）
        $todayEarnings = $this->db->fetchOne(
            "SELECT SUM(amount) as total FROM tata_coin_transactions
             WHERE user_id = ? AND user_type = ? AND amount > 0 AND DATE(created_at) = ?",
            [$userId, $userType, $today]
        )['total'] ?? 0;

        $maxDaily = 30; // 每日最大获取30个coin
        $remaining = max(0, $maxDaily - $todayEarnings);

        return [
            'max_daily' => $maxDaily,
            'today_earned' => (int)$todayEarnings,
            'remaining' => (int)$remaining,
            'can_earn_more' => $remaining > 0
        ];
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
