<?php
session_start();
require_once '../config/config.php';
require_once '../includes/TataCoinManager.php';

header('Content-Type: application/json');

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '方法不允许']);
    exit;
}

// 检查用户登录状态
$userId = null;
$userType = null;

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $userType = 'user';
} elseif (isset($_SESSION['reader_id'])) {
    $userId = $_SESSION['reader_id'];
    $userType = 'reader';
} else {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

try {
    $db = Database::getInstance();
    $tataCoinManager = new TataCoinManager();
    $today = date('Y-m-d');
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

    // 检查今天是否已签到
    if ($userType === 'user') {
        $existingCheckin = $db->fetchOne(
            "SELECT id FROM daily_checkins WHERE user_id = ? AND user_type = ? AND checkin_date = ?",
            [$userId, $userType, $today]
        );
    } else {
        $existingCheckin = $db->fetchOne(
            "SELECT id FROM daily_checkins WHERE reader_id = ? AND user_type = ? AND checkin_date = ?",
            [$userId, $userType, $today]
        );
    }

    if ($existingCheckin) {
        echo json_encode([
            'success' => false,
            'message' => '今日已签到',
            'reward' => 0,
            'consecutive_days' => 1
        ]);
        exit;
    }

    // 插入签到记录
    if ($userType === 'user') {
        $insertData = [
            'user_id' => $userId,
            'reader_id' => null,
            'user_type' => $userType,
            'checkin_date' => $today,
            'consecutive_days' => 1,
            'reward_amount' => 5,
            'ip_address' => $ipAddress
        ];
    } else {
        $insertData = [
            'user_id' => null,
            'reader_id' => $userId,
            'user_type' => $userType,
            'checkin_date' => $today,
            'consecutive_days' => 1,
            'reward_amount' => 5,
            'ip_address' => $ipAddress
        ];
    }

    $db->insert('daily_checkins', $insertData);

    // 给用户增加Tata coin奖励
    $rewardAmount = 5;
    $description = '每日签到奖励';

    // 使用earn方法给用户增加Tata coin（支持用户和占卜师）
    $tataCoinManager->earn($userId, $userType, $rewardAmount, $description);

    echo json_encode([
        'success' => true,
        'message' => '签到成功！获得5个Tata币',
        'reward' => $rewardAmount,
        'consecutive_days' => 1
    ]);

} catch (Exception $e) {
    error_log("Checkin error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '签到失败：' . $e->getMessage()
    ]);
}
?>
