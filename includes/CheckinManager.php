<?php
/**
 * 签到管理类
 * 处理用户和占卜师的每日签到功能
 */
class CheckinManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 检查今日是否已签到
     * @param int $userId 用户ID
     * @param string $userType 用户类型 user/reader
     * @return bool
     */
    public function hasCheckedInToday($userId, $userType = 'user') {
        $today = date('Y-m-d');

        if ($userType === 'user') {
            $result = $this->db->fetchOne(
                "SELECT id FROM daily_checkins WHERE user_id = ? AND user_type = ? AND checkin_date = ?",
                [$userId, $userType, $today]
            );
        } else {
            $result = $this->db->fetchOne(
                "SELECT id FROM daily_checkins WHERE reader_id = ? AND user_type = ? AND checkin_date = ?",
                [$userId, $userType, $today]
            );
        }

        return !empty($result);
    }
    
    /**
     * 执行签到
     * @param int $userId 用户ID
     * @param string $userType 用户类型 user/reader
     * @return array 签到结果
     */
    public function checkin($userId, $userType = 'user') {
        if ($this->hasCheckedInToday($userId, $userType)) {
            return [
                'success' => false,
                'message' => '今日已签到',
                'reward' => 0,
                'consecutive_days' => $this->getConsecutiveDays($userId, $userType)
            ];
        }
        
        try {
            $this->db->beginTransaction();
            
            // 获取连续签到天数
            $consecutiveDays = $this->calculateConsecutiveDays($userId, $userType);
            
            // 计算奖励
            $reward = $this->calculateReward($consecutiveDays);
            
            // 记录签到
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            if ($userType === 'user') {
                $insertData = [
                    'user_id' => $userId,
                    'reader_id' => null,
                    'user_type' => $userType,
                    'checkin_date' => date('Y-m-d'),
                    'consecutive_days' => $consecutiveDays,
                    'reward_amount' => $reward,
                    'ip_address' => $ipAddress
                ];
            } else {
                $insertData = [
                    'user_id' => null,
                    'reader_id' => $userId,
                    'user_type' => $userType,
                    'checkin_date' => date('Y-m-d'),
                    'consecutive_days' => $consecutiveDays,
                    'reward_amount' => $reward,
                    'ip_address' => $ipAddress
                ];
            }

            $this->db->insert('daily_checkins', $insertData);
            
            // 发放奖励
            if ($reward > 0) {
                require_once 'TataCoinManager.php';
                $tataCoinManager = new TataCoinManager();
                $tataCoinManager->addTransaction(
                    $userId,
                    $userType,
                    $reward,
                    'checkin',
                    "每日签到奖励（连续{$consecutiveDays}天）"
                );
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => '签到成功！',
                'reward' => $reward,
                'consecutive_days' => $consecutiveDays
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => '签到失败：' . $e->getMessage(),
                'reward' => 0,
                'consecutive_days' => 0
            ];
        }
    }
    
    /**
     * 计算连续签到天数
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @return int
     */
    private function calculateConsecutiveDays($userId, $userType) {
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // 检查昨天是否签到
        if ($userType === 'user') {
            $yesterdayCheckin = $this->db->fetchOne(
                "SELECT consecutive_days FROM daily_checkins
                 WHERE user_id = ? AND user_type = ? AND checkin_date = ?",
                [$userId, $userType, $yesterday]
            );
        } else {
            $yesterdayCheckin = $this->db->fetchOne(
                "SELECT consecutive_days FROM daily_checkins
                 WHERE reader_id = ? AND user_type = ? AND checkin_date = ?",
                [$userId, $userType, $yesterday]
            );
        }

        if ($yesterdayCheckin) {
            // 连续签到，天数+1
            return $yesterdayCheckin['consecutive_days'] + 1;
        } else {
            // 不连续，重新开始
            return 1;
        }
    }
    
    /**
     * 获取当前连续签到天数
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @return int
     */
    public function getConsecutiveDays($userId, $userType = 'user') {
        $today = date('Y-m-d');

        if ($userType === 'user') {
            $result = $this->db->fetchOne(
                "SELECT consecutive_days FROM daily_checkins
                 WHERE user_id = ? AND user_type = ? AND checkin_date = ?",
                [$userId, $userType, $today]
            );
        } else {
            $result = $this->db->fetchOne(
                "SELECT consecutive_days FROM daily_checkins
                 WHERE reader_id = ? AND user_type = ? AND checkin_date = ?",
                [$userId, $userType, $today]
            );
        }

        if ($result) {
            return $result['consecutive_days'];
        }

        // 如果今天没签到，检查昨天的连续天数
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        if ($userType === 'user') {
            $yesterdayResult = $this->db->fetchOne(
                "SELECT consecutive_days FROM daily_checkins
                 WHERE user_id = ? AND user_type = ? AND checkin_date = ?",
                [$userId, $userType, $yesterday]
            );
        } else {
            $yesterdayResult = $this->db->fetchOne(
                "SELECT consecutive_days FROM daily_checkins
                 WHERE reader_id = ? AND user_type = ? AND checkin_date = ?",
                [$userId, $userType, $yesterday]
            );
        }

        return $yesterdayResult ? $yesterdayResult['consecutive_days'] : 0;
    }
    
    /**
     * 计算签到奖励
     * @param int $consecutiveDays 连续签到天数
     * @return int 奖励金额
     */
    private function calculateReward($consecutiveDays) {
        // 签到奖励规则
        $rewards = [
            1 => 5,   // 第1天：5个
            2 => 6,   // 第2天：6个
            3 => 7,   // 第3天：7个
            4 => 8,   // 第4天：8个
            5 => 9,   // 第5天：9个
            6 => 10,  // 第6天：10个
            7 => 12   // 第7天：12个
        ];
        
        // 7天一个周期，超过7天按第7天计算
        $day = $consecutiveDays > 7 ? 7 : $consecutiveDays;
        
        return $rewards[$day] ?? 5;
    }
    
    /**
     * 获取签到统计
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @return array
     */
    public function getCheckinStats($userId, $userType = 'user') {
        // 总签到天数
        if ($userType === 'user') {
            $totalDays = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM daily_checkins WHERE user_id = ? AND user_type = ?",
                [$userId, $userType]
            )['count'];

            // 总奖励
            $totalReward = $this->db->fetchOne(
                "SELECT SUM(reward_amount) as total FROM daily_checkins WHERE user_id = ? AND user_type = ?",
                [$userId, $userType]
            )['total'] ?? 0;
        } else {
            $totalDays = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM daily_checkins WHERE reader_id = ? AND user_type = ?",
                [$userId, $userType]
            )['count'];

            // 总奖励
            $totalReward = $this->db->fetchOne(
                "SELECT SUM(reward_amount) as total FROM daily_checkins WHERE reader_id = ? AND user_type = ?",
                [$userId, $userType]
            )['total'] ?? 0;
        }
        
        // 本月签到天数
        $thisMonth = date('Y-m');
        if ($userType === 'user') {
            $monthlyDays = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM daily_checkins
                 WHERE user_id = ? AND user_type = ? AND checkin_date LIKE ?",
                [$userId, $userType, $thisMonth . '%']
            )['count'];
        } else {
            $monthlyDays = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM daily_checkins
                 WHERE reader_id = ? AND user_type = ? AND checkin_date LIKE ?",
                [$userId, $userType, $thisMonth . '%']
            )['count'];
        }
        
        return [
            'total_days' => $totalDays,
            'total_reward' => $totalReward,
            'monthly_days' => $monthlyDays,
            'consecutive_days' => $this->getConsecutiveDays($userId, $userType),
            'checked_in_today' => $this->hasCheckedInToday($userId, $userType)
        ];
    }
    
    /**
     * 获取签到历史
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @param int $limit 限制数量
     * @return array
     */
    public function getCheckinHistory($userId, $userType = 'user', $limit = 30) {
        if ($userType === 'user') {
            return $this->db->fetchAll(
                "SELECT * FROM daily_checkins
                 WHERE user_id = ? AND user_type = ?
                 ORDER BY checkin_date DESC
                 LIMIT ?",
                [$userId, $userType, $limit]
            );
        } else {
            return $this->db->fetchAll(
                "SELECT * FROM daily_checkins
                 WHERE reader_id = ? AND user_type = ?
                 ORDER BY checkin_date DESC
                 LIMIT ?",
                [$userId, $userType, $limit]
            );
        }
    }
}
?>
