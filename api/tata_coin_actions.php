<?php
session_start();
require_once '../config/config.php';
require_once '../includes/TataCoinManager.php';

header('Content-Type: application/json');

// 检查用户是否登录
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$tataCoinManager = new TataCoinManager();

try {
    switch ($action) {
        case 'daily_checkin':
            // 每日签到
            $result = $tataCoinManager->dailyCheckIn($userId);
            echo json_encode($result);
            break;
            
        case 'check_checkin_status':
            // 检查签到状态
            $today = date('Y-m-d');
            $db = Database::getInstance();
            
            $todayCheckIn = $db->fetchOne(
                "SELECT * FROM daily_check_ins WHERE user_id = ? AND check_in_date = ?",
                [$userId, $today]
            );
            
            $lastCheckIn = $db->fetchOne(
                "SELECT * FROM daily_check_ins WHERE user_id = ? ORDER BY check_in_date DESC LIMIT 1",
                [$userId]
            );
            
            $consecutiveDays = 0;
            if ($lastCheckIn) {
                if ($todayCheckIn) {
                    $consecutiveDays = $todayCheckIn['consecutive_days'];
                } else {
                    $lastDate = new DateTime($lastCheckIn['check_in_date']);
                    $todayDate = new DateTime($today);
                    $daysDiff = $todayDate->diff($lastDate)->days;
                    
                    if ($daysDiff == 1) {
                        $consecutiveDays = $lastCheckIn['consecutive_days'];
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'checked_in_today' => !empty($todayCheckIn),
                'consecutive_days' => $consecutiveDays
            ]);
            break;
            
        case 'browse_reward':
            // 浏览页面奖励
            $pageUrl = $input['page_url'] ?? '';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            
            if (empty($pageUrl)) {
                echo json_encode(['success' => false, 'message' => '页面URL不能为空']);
                break;
            }
            
            $result = $tataCoinManager->browsePageReward($userId, $pageUrl, $ipAddress);
            echo json_encode($result);
            break;
            
        case 'profile_completion_reward':
            // 完善资料奖励
            $result = $tataCoinManager->profileCompletionReward($userId, $userType);
            echo json_encode($result);
            break;
            
        case 'get_balance':
            // 获取余额和等级信息
            $balance = $tataCoinManager->getBalance($userId, $userType);
            $levelInfo = $tataCoinManager->getUserLevel($userId, $userType);
            $dailyLimit = $tataCoinManager->getDailyEarningsLimit($userId, $userType);
            
            echo json_encode([
                'success' => true,
                'balance' => $balance,
                'level_info' => $levelInfo,
                'daily_limit' => $dailyLimit
            ]);
            break;
            
        case 'get_transaction_history':
            // 获取交易记录
            $limit = (int)($input['limit'] ?? 20);
            $offset = (int)($input['offset'] ?? 0);
            
            $transactions = $tataCoinManager->getTransactionHistory($userId, $userType, $limit, $offset);
            
            echo json_encode([
                'success' => true,
                'transactions' => $transactions
            ]);
            break;
            
        case 'get_browse_history':
            // 获取浏览记录（仅用户）
            if ($userType !== 'user') {
                echo json_encode(['success' => false, 'message' => '权限不足']);
                break;
            }
            
            $limit = (int)($input['limit'] ?? 20);
            $offset = (int)($input['offset'] ?? 0);
            
            $browseHistory = $tataCoinManager->getBrowseHistory($userId, $limit, $offset);
            
            echo json_encode([
                'success' => true,
                'browse_history' => $browseHistory
            ]);
            break;
            
        case 'get_reader_earnings':
            // 获取塔罗师收益统计（仅塔罗师）
            if ($userType !== 'reader') {
                echo json_encode(['success' => false, 'message' => '权限不足']);
                break;
            }
            
            $earnings = $tataCoinManager->getReaderEarnings($userId);
            $levelInfo = $tataCoinManager->getUserLevel($userId, 'reader');
            
            echo json_encode([
                'success' => true,
                'earnings' => $earnings,
                'level_info' => $levelInfo
            ]);
            break;
            
        case 'calculate_discounted_price':
            // 计算折扣价格
            $originalPrice = (int)($input['original_price'] ?? 0);
            
            if ($originalPrice <= 0) {
                echo json_encode(['success' => false, 'message' => '价格无效']);
                break;
            }
            
            $discountedPrice = $tataCoinManager->calculateDiscountedPrice($userId, $originalPrice);
            $levelInfo = $tataCoinManager->getUserLevel($userId, 'user');
            
            echo json_encode([
                'success' => true,
                'original_price' => $originalPrice,
                'discounted_price' => $discountedPrice,
                'discount_rate' => $levelInfo['discount_rate'] ?? 0,
                'savings' => $originalPrice - $discountedPrice
            ]);
            break;
            
        case 'get_daily_stats':
            // 获取每日统计
            $db = Database::getInstance();
            $today = date('Y-m-d');
            
            // 今日签到状态
            $todayCheckIn = $db->fetchOne(
                "SELECT * FROM daily_check_ins WHERE user_id = ? AND check_in_date = ?",
                [$userId, $today]
            );
            
            // 今日浏览奖励次数
            $todayBrowseCount = $db->fetchOne(
                "SELECT COUNT(*) as count FROM page_browse_rewards 
                 WHERE user_id = ? AND DATE(created_at) = ?",
                [$userId, $today]
            )['count'];
            
            // 今日收益
            $todayEarnings = $db->fetchOne(
                "SELECT SUM(amount) as total FROM tata_coin_transactions 
                 WHERE user_id = ? AND user_type = ? AND amount > 0 AND DATE(created_at) = ?",
                [$userId, $userType, $today]
            )['total'] ?? 0;
            
            echo json_encode([
                'success' => true,
                'today_stats' => [
                    'checked_in' => !empty($todayCheckIn),
                    'checkin_reward' => $todayCheckIn['reward_coins'] ?? 0,
                    'browse_count' => (int)$todayBrowseCount,
                    'browse_remaining' => max(0, 10 - $todayBrowseCount),
                    'total_earned_today' => (int)$todayEarnings
                ]
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => '无效的操作']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
