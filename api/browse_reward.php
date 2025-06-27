<?php
session_start();
require_once '../config/config.php';
require_once '../includes/BrowseRewardManager.php';

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

// 获取请求参数
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => '无效的请求数据']);
    exit;
}

$pageUrl = $input['page_url'] ?? '';
$pageTitle = $input['page_title'] ?? '';
$browseTime = (int)($input['browse_time'] ?? 0);

if (empty($pageUrl) || $browseTime <= 0) {
    echo json_encode(['success' => false, 'message' => '参数不完整']);
    exit;
}

try {
    $browseRewardManager = new BrowseRewardManager();
    
    // 记录浏览并给予奖励
    $result = $browseRewardManager->recordBrowseAndReward(
        $userId,
        $userType,
        $pageUrl,
        $pageTitle,
        $browseTime
    );
    
    // 获取最新统计
    $stats = $browseRewardManager->getBrowseStats($userId, $userType);
    
    echo json_encode([
        'success' => $result['success'],
        'message' => $result['message'],
        'reward' => $result['reward'],
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    error_log("Browse reward error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '系统错误'
    ]);
}
?>
