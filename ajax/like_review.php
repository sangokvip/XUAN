<?php
session_start();
require_once '../config/config.php';
require_once '../includes/ReviewManager.php';

header('Content-Type: application/json');

// 检查用户登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

// 获取POST数据
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['review_id']) || !is_numeric($input['review_id'])) {
    echo json_encode(['success' => false, 'message' => '无效的评价ID']);
    exit;
}

$reviewId = (int)$input['review_id'];
$userId = $_SESSION['user_id'];

try {
    $reviewManager = new ReviewManager();
    
    // 检查评价是否存在
    $db = Database::getInstance();
    $review = $db->fetchOne("SELECT id FROM reader_reviews WHERE id = ?", [$reviewId]);
    
    if (!$review) {
        echo json_encode(['success' => false, 'message' => '评价不存在']);
        exit;
    }
    
    // 执行点赞/取消点赞
    $liked = $reviewManager->likeReview($reviewId, $userId);
    
    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'message' => $liked ? '点赞成功' : '取消点赞成功'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '操作失败：' . $e->getMessage()
    ]);
}
?>
