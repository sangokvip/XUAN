<?php
/**
 * 页面浏览奖励管理类
 * 处理用户浏览页面获得Tata Coin奖励的功能
 */
class BrowseRewardManager {
    private $db;
    private $maxDailyRewards = 30; // 每日最大奖励数量
    private $minBrowseTime = 5; // 最小浏览时间（秒）
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 记录页面浏览并给予奖励
     * @param int $userId 用户ID
     * @param string $userType 用户类型 user/reader
     * @param string $pageUrl 页面URL
     * @param string $pageTitle 页面标题
     * @param int $browseTime 浏览时长（秒）
     * @return array 结果
     */
    public function recordBrowseAndReward($userId, $userType, $pageUrl, $pageTitle, $browseTime) {
        if ($browseTime < $this->minBrowseTime) {
            return [
                'success' => false,
                'message' => '浏览时间不足',
                'reward' => 0
            ];
        }
        
        try {
            $this->db->beginTransaction();
            
            $today = date('Y-m-d');
            $field = $userType === 'user' ? 'user_id' : 'reader_id';
            
            // 检查今日是否已浏览过此页面
            $existingRecord = $this->db->fetchOne(
                "SELECT id, reward_given FROM page_browse_records 
                 WHERE {$field} = ? AND user_type = ? AND page_url = ? AND browse_date = ?",
                [$userId, $userType, $pageUrl, $today]
            );
            
            if ($existingRecord) {
                $this->db->rollback();
                return [
                    'success' => false,
                    'message' => '今日已浏览过此页面',
                    'reward' => 0
                ];
            }
            
            // 检查今日奖励是否已达上限
            $todayRewards = $this->getTodayRewardCount($userId, $userType);
            if ($todayRewards >= $this->maxDailyRewards) {
                // 记录浏览但不给奖励
                $this->recordBrowse($userId, $userType, $pageUrl, $pageTitle, $browseTime, false);
                $this->db->commit();
                
                return [
                    'success' => true,
                    'message' => '今日奖励已达上限',
                    'reward' => 0
                ];
            }
            
            // 记录浏览并给奖励
            $this->recordBrowse($userId, $userType, $pageUrl, $pageTitle, $browseTime, true);
            
            // 发放奖励
            require_once 'TataCoinManager.php';
            $tataCoinManager = new TataCoinManager();
            $tataCoinManager->addTransaction(
                $userId,
                $userType,
                1,
                'browse',
                "浏览页面奖励：{$pageTitle}"
            );
            
            // 更新每日统计
            $this->updateDailyStats($userId, $userType, $today, true);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => '获得浏览奖励',
                'reward' => 1
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => '记录失败：' . $e->getMessage(),
                'reward' => 0
            ];
        }
    }
    
    /**
     * 记录页面浏览
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @param string $pageUrl 页面URL
     * @param string $pageTitle 页面标题
     * @param int $browseTime 浏览时长
     * @param bool $rewardGiven 是否给予奖励
     */
    private function recordBrowse($userId, $userType, $pageUrl, $pageTitle, $browseTime, $rewardGiven) {
        $field = $userType === 'user' ? 'user_id' : 'reader_id';
        $insertData = [
            $field => $userId,
            'user_type' => $userType,
            'page_url' => $pageUrl,
            'page_title' => $pageTitle,
            'browse_date' => date('Y-m-d'),
            'browse_time' => $browseTime,
            'reward_given' => $rewardGiven ? 1 : 0
        ];
        
        $this->db->insert('page_browse_records', $insertData);
    }
    
    /**
     * 获取今日奖励数量
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @return int
     */
    public function getTodayRewardCount($userId, $userType) {
        $field = $userType === 'user' ? 'user_id' : 'reader_id';
        $today = date('Y-m-d');
        
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM page_browse_records 
             WHERE {$field} = ? AND user_type = ? AND browse_date = ? AND reward_given = 1",
            [$userId, $userType, $today]
        );
        
        return $result['count'] ?? 0;
    }
    
    /**
     * 更新每日统计
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @param string $date 日期
     * @param bool $rewardGiven 是否给予奖励
     */
    private function updateDailyStats($userId, $userType, $date, $rewardGiven) {
        $field = $userType === 'user' ? 'user_id' : 'reader_id';
        
        // 检查是否已有记录
        $existing = $this->db->fetchOne(
            "SELECT id, total_pages, total_rewards FROM daily_browse_stats 
             WHERE {$field} = ? AND user_type = ? AND browse_date = ?",
            [$userId, $userType, $date]
        );
        
        if ($existing) {
            // 更新现有记录
            $newTotalPages = $existing['total_pages'] + 1;
            $newTotalRewards = $existing['total_rewards'] + ($rewardGiven ? 1 : 0);
            
            $this->db->update(
                'daily_browse_stats',
                [
                    'total_pages' => $newTotalPages,
                    'total_rewards' => $newTotalRewards
                ],
                'id = ?',
                [$existing['id']]
            );
        } else {
            // 创建新记录
            $insertData = [
                $field => $userId,
                'user_type' => $userType,
                'browse_date' => $date,
                'total_pages' => 1,
                'total_rewards' => $rewardGiven ? 1 : 0
            ];
            
            $this->db->insert('daily_browse_stats', $insertData);
        }
    }
    
    /**
     * 获取浏览统计
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @return array
     */
    public function getBrowseStats($userId, $userType) {
        $field = $userType === 'user' ? 'user_id' : 'reader_id';
        $today = date('Y-m-d');
        
        // 今日统计
        $todayStats = $this->db->fetchOne(
            "SELECT total_pages, total_rewards FROM daily_browse_stats 
             WHERE {$field} = ? AND user_type = ? AND browse_date = ?",
            [$userId, $userType, $today]
        );
        
        // 总统计
        $totalStats = $this->db->fetchOne(
            "SELECT SUM(total_pages) as total_pages, SUM(total_rewards) as total_rewards 
             FROM daily_browse_stats WHERE {$field} = ? AND user_type = ?",
            [$userId, $userType]
        );
        
        return [
            'today_pages' => $todayStats['total_pages'] ?? 0,
            'today_rewards' => $todayStats['total_rewards'] ?? 0,
            'today_remaining' => max(0, $this->maxDailyRewards - ($todayStats['total_rewards'] ?? 0)),
            'total_pages' => $totalStats['total_pages'] ?? 0,
            'total_rewards' => $totalStats['total_rewards'] ?? 0,
            'max_daily_rewards' => $this->maxDailyRewards
        ];
    }
    
    /**
     * 获取浏览历史
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @param int $limit 限制数量
     * @return array
     */
    public function getBrowseHistory($userId, $userType, $limit = 50) {
        $field = $userType === 'user' ? 'user_id' : 'reader_id';
        
        return $this->db->fetchAll(
            "SELECT * FROM page_browse_records 
             WHERE {$field} = ? AND user_type = ? 
             ORDER BY created_at DESC 
             LIMIT ?",
            [$userId, $userType, $limit]
        );
    }
    
    /**
     * 检查页面是否可以获得奖励
     * @param int $userId 用户ID
     * @param string $userType 用户类型
     * @param string $pageUrl 页面URL
     * @return bool
     */
    public function canGetReward($userId, $userType, $pageUrl) {
        $today = date('Y-m-d');
        $field = $userType === 'user' ? 'user_id' : 'reader_id';
        
        // 检查今日是否已浏览过此页面
        $existingRecord = $this->db->fetchOne(
            "SELECT id FROM page_browse_records 
             WHERE {$field} = ? AND user_type = ? AND page_url = ? AND browse_date = ?",
            [$userId, $userType, $pageUrl, $today]
        );
        
        if ($existingRecord) {
            return false;
        }
        
        // 检查今日奖励是否已达上限
        $todayRewards = $this->getTodayRewardCount($userId, $userType);
        return $todayRewards < $this->maxDailyRewards;
    }
}
?>
