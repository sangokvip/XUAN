<?php
session_start();
require_once 'config/config.php';
require_once 'includes/TataCoinManager.php';
require_once 'includes/ReviewManager.php';

// 获取塔罗师ID
$readerId = (int)($_GET['id'] ?? 0);

if (!$readerId) {
    redirect('readers.php');
}

// 获取塔罗师信息
$reader = getReaderById($readerId);

if (!$reader) {
    redirect('readers.php');
}

$tataCoinManager = new TataCoinManager();

// 检查用户是否已登录
$user = null;
$canViewContact = false;
$hasViewedContact = false;
$isAdmin = false;
$userTataCoinBalance = 0;
$contactCost = 0;
$paymentError = '';
$paymentSuccess = '';

// 检查管理员登录状态
if (isset($_SESSION['admin_id'])) {
    $isAdmin = true;
    $canViewContact = true;
    $hasViewedContact = true; // 管理员默认已查看
} elseif (isset($_SESSION['user_id'])) {
    $user = getUserById($_SESSION['user_id']);
    $userTataCoinBalance = $tataCoinManager->getBalance($_SESSION['user_id'], 'user');
    $canViewContact = true;

    // 检查是否已经付费查看过
    $db = Database::getInstance();
    $existingRecord = $db->fetchOne(
        "SELECT * FROM user_browse_history WHERE user_id = ? AND reader_id = ? AND browse_type = 'paid'",
        [$_SESSION['user_id'], $readerId]
    );
    $hasViewedContact = (bool)$existingRecord;

    // 确定查看联系方式的费用
    $contactCost = $reader['is_featured'] ? 30 : 10; // 推荐塔罗师30，普通塔罗师10
}

// 处理查看联系方式请求
$showContact = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['view_contact']) && $canViewContact) {
    if ($isAdmin) {
        // 管理员直接显示联系方式，不记录查看记录
        $showContact = true;
    } elseif ($hasViewedContact) {
        // 已经付费查看过，直接显示
        $showContact = true;
    } elseif (isset($_SESSION['user_id'])) {
        // 需要付费查看
        try {
            $result = $tataCoinManager->viewReaderContact($_SESSION['user_id'], $readerId);
            if ($result['success']) {
                $hasViewedContact = true;
                $showContact = true;
                if (!$result['already_paid']) {
                    $paymentSuccess = "成功支付 {$result['cost']} 个Tata Coin";
                    $userTataCoinBalance = $tataCoinManager->getBalance($_SESSION['user_id'], 'user');
                }
            }
        } catch (Exception $e) {
            $paymentError = $e->getMessage();
        }
    }
}

// 记录免费浏览（如果用户已登录但没有查看联系方式）
if (isset($_SESSION['user_id']) && !$showContact && !$hasViewedContact) {
    try {
        $tataCoinManager->recordFreeBrowse($_SESSION['user_id'], $readerId);
    } catch (Exception $e) {
        // 忽略记录失败
    }
}

// 使用ViewCountManager管理查看次数，防止恶意刷新
require_once 'includes/ViewCountManager.php';
$viewCountManager = new ViewCountManager();

// 记录查看（30分钟冷却时间）
$viewRecorded = $viewCountManager->recordView($readerId, 30);

// 获取查看次数和统计信息
$totalViews = $viewCountManager->getViewCount($readerId);
$viewStats = $viewCountManager->getViewStats($readerId);

$db = Database::getInstance();

// 初始化评价系统
$reviewManager = new ReviewManager();
$reviewError = '';
$reviewSuccess = '';
$questionError = '';
$questionSuccess = '';

// 处理评价提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && (isset($_SESSION['user_id']) || $isAdmin)) {
    try {
        $rating = (int)($_POST['rating'] ?? 0);
        $reviewText = trim($_POST['review_text'] ?? '');
        $isAnonymous = isset($_POST['is_anonymous']);

        // 获取当前用户ID（普通用户或管理员）
        $currentUserId = $_SESSION['user_id'] ?? $_SESSION['admin_id'];
        $reviewManager->addReview($readerId, $currentUserId, $rating, $reviewText, $isAnonymous, $isAdmin);

        // 重定向避免重复提交
        header("Location: reader.php?id={$readerId}&review_success=1");
        exit;
    } catch (Exception $e) {
        $reviewError = $e->getMessage();
    }
}

// 处理问题提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_question']) && (isset($_SESSION['user_id']) || $isAdmin)) {
    try {
        $question = trim($_POST['question'] ?? '');
        $isAnonymous = isset($_POST['question_anonymous']);

        // 获取当前用户ID（普通用户或管理员）
        $currentUserId = $_SESSION['user_id'] ?? $_SESSION['admin_id'];
        $reviewManager->addQuestion($readerId, $currentUserId, $question, $isAnonymous);

        // 重定向避免重复提交
        header("Location: reader.php?id={$readerId}&question_success=1");
        exit;
    } catch (Exception $e) {
        $questionError = $e->getMessage();
    }
}

// 处理回答提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answer']) && (isset($_SESSION['user_id']) || $isAdmin)) {
    try {
        $questionId = (int)($_POST['question_id'] ?? 0);
        $answer = trim($_POST['answer'] ?? '');
        $isAnonymous = isset($_POST['answer_anonymous']);

        // 获取当前用户ID（普通用户或管理员）
        $currentUserId = $_SESSION['user_id'] ?? $_SESSION['admin_id'];
        $reviewManager->addAnswer($questionId, $currentUserId, $answer, $isAnonymous);

        // 重定向避免重复提交
        header("Location: reader.php?id={$readerId}&answer_success=1");
        exit;
    } catch (Exception $e) {
        $questionError = $e->getMessage();
    }
}

// 处理成功消息显示
$reviewSuccess = '';
$questionSuccess = '';

if (isset($_GET['review_success'])) {
    $reviewSuccess = '评价提交成功！';
}

if (isset($_GET['question_success'])) {
    $questionSuccess = '问题提交成功！';
}

if (isset($_GET['answer_success'])) {
    $questionSuccess = '回答提交成功！';
}

// 获取评价数据
$reviewStats = [];
$reviews = [];
$questions = [];

if (class_exists('ReviewManager')) {
    try {
        $reviewStats = $reviewManager->getReviewStats($readerId);
        $reviews = $reviewManager->getReviews($readerId, 10, 0);
        $questions = $reviewManager->getQuestions($readerId, 5, 0);
    } catch (Exception $e) {
        // 如果评价系统出错，使用默认值
        $reviewStats = [
            'total_reviews' => 0,
            'average_rating' => 0,
            'rating_5' => 0,
            'rating_4' => 0,
            'rating_3' => 0,
            'rating_2' => 0,
            'rating_1' => 0
        ];
    }
}

// 检查用户权限
$canReview = false;
$hasReviewed = false;
$hasPurchased = false;

if (isset($_SESSION['user_id']) || $isAdmin) {
    $currentUserId = $_SESSION['user_id'] ?? $_SESSION['admin_id'];

    if ($isAdmin) {
        // 管理员权限：可以评价，但需要检查是否已评价过
        $hasReviewed = $reviewManager->hasUserReviewed($currentUserId, $readerId);
        $canReview = !$hasReviewed;
        $hasPurchased = true; // 管理员视为已购买
    } else {
        // 普通用户权限
        $hasPurchased = $reviewManager->hasUserPurchased($currentUserId, $readerId);
        $hasReviewed = $reviewManager->hasUserReviewed($currentUserId, $readerId);
        $canReview = $hasPurchased && !$hasReviewed;
    }

    // 获取用户点赞状态
    $reviewIds = array_column($reviews, 'id');
    $userLikes = $reviewManager->getUserLikeStatus($currentUserId, $reviewIds);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($reader['full_name']); ?> - 塔罗师详情</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* 强制修复塔罗师照片显示 - 完整显示图片 */
        .reader-photo {
            height: 250px !important;
            overflow: hidden !important;
            position: relative !important;
            background: #f8f9fa !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .reader-photo img {
            max-width: 100% !important;
            max-height: 100% !important;
            width: auto !important;
            height: auto !important;
            object-fit: contain !important;
            transition: transform 0.3s ease !important;
        }

        .reader-photo-large {
            max-width: 100% !important;
            max-height: 400px !important;
            width: auto !important;
            height: auto !important;
            object-fit: contain !important;
            border-radius: 15px !important;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important;
        }

        .default-photo {
            width: calc(100% - 20px) !important;
            height: calc(100% - 20px) !important;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 48px !important;
            color: #6c757d !important;
            border: 2px dashed #d4af37 !important;
            border-radius: 10px !important;
            margin: 10px !important;
            box-sizing: border-box !important;
        }

        .default-photo-large {
            width: 300px !important;
            height: 400px !important;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 80px !important;
            color: #6c757d !important;
            border: 2px dashed #d4af37 !important;
            border-radius: 15px !important;
            box-sizing: border-box !important;
        }

        .price-list-image img {
            max-width: 100% !important;
            height: auto !important;
            object-fit: contain !important;
            border-radius: 10px !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
        }

        /* 联系方式样式 */
        .contact-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 15px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            border-left: 4px solid #d4af37;
            transition: all 0.3s ease;
        }

        .contact-item:hover {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.2);
        }

        .contact-icon {
            font-size: 18px;
            min-width: 20px;
        }

        .contact-value {
            color: #333;
            font-weight: 500;
            word-break: break-all;
        }

        .contact-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #d4af37;
        }

        .contact-details h3 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .contact-text {
            color: #666;
            line-height: 1.6;
        }

        .contact-note {
            background: linear-gradient(135deg, #e7f3ff, #cce7ff);
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #007bff;
        }

        .contact-note p {
            margin: 0;
            color: #004085;
        }

        @media (max-width: 768px) {
            .contact-methods {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .contact-item {
                padding: 10px 12px;
            }
        }

        /* 管理员模式横幅 */
        .admin-mode-banner {
            background: linear-gradient(135deg, #d4af37, #f1c40f);
            color: #1a1a1a;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }

        .admin-banner-content {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .admin-icon {
            font-size: 1.5rem;
        }

        .admin-text {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .admin-note {
            color: #2c3e50;
            flex: 1;
            min-width: 200px;
        }

        .admin-link {
            background: rgba(26, 26, 26, 0.1);
            color: #1a1a1a;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid rgba(26, 26, 26, 0.2);
        }

        .admin-link:hover {
            background: rgba(26, 26, 26, 0.2);
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .admin-banner-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .admin-note {
                min-width: auto;
            }
        }

        /* 评价系统样式 - 优化版 */
        .review-form-section,
        .reviews-list,
        .questions-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .reviews-list h3,
        .questions-section h3 {
            margin: 0 0 15px 0;
            font-size: 1.3rem;
            color: #1f2937;
        }

        .review-form-section h3 {
            margin: 0 0 15px 0;
            font-size: 1.2rem;
            color: #1f2937;
        }



        /* 评价表单 - 紧凑布局 */
        .review-form {
            max-width: 100%;
        }

        .form-row {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .rating-input {
            flex-shrink: 0;
        }

        .rating-input label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
            display: block;
        }

        .star-rating {
            display: flex;
            gap: 3px;
            direction: ltr; /* 从左到右排列 */
        }

        .star-rating input[type="radio"] {
            display: none;
        }

        .star-label {
            font-size: 1.4rem;
            color: #e5e7eb;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        /* 选中状态：当前星星及其前面的星星都高亮 */
        .star-rating input[type="radio"]:checked ~ .star-label {
            color: #f59e0b;
        }

        /* 悬停效果 */
        .star-rating .star-label:hover {
            color: #f59e0b;
        }

        /* 默认5星选中状态 */
        .star-rating input[type="radio"]:checked {
            ~ .star-label {
                color: #f59e0b;
            }
        }

        /* 评分错误提示 */
        .rating-error {
            margin-top: 5px;
            padding: 8px 12px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 4px;
            color: #dc2626;
            font-size: 0.875rem;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
            font-size: 0.9rem;
        }

        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.9rem;
            resize: vertical;
            min-height: 80px;
            transition: border-color 0.3s ease;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .checkbox-group input[type="checkbox"] {
            margin: 0;
        }

        .checkbox-group label {
            margin: 0;
            font-size: 0.9rem;
            color: #6b7280;
            cursor: pointer;
        }

        /* 评价列表 - 紧凑布局 */
        .review-item {
            border-bottom: 1px solid #f3f4f6;
            padding: 15px 0;
        }

        .review-item:last-child {
            border-bottom: none;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .reviewer-info {
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .reviewer-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .reviewer-details {
            min-width: 0;
        }

        .reviewer-name {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.9rem;
            margin-bottom: 2px;
        }

        .reviewer-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }

        .purchased-badge {
            background: #10b981;
            color: white;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .review-rating .star {
            font-size: 0.85rem;
        }

        .review-date {
            color: #6b7280;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .review-content {
            margin: 8px 0;
            line-height: 1.5;
            color: #374151;
            font-size: 0.9rem;
        }

        .review-actions {
            margin-top: 8px;
        }

        .like-btn {
            background: none;
            border: 1px solid #e5e7eb;
            padding: 4px 10px;
            border-radius: 16px;
            cursor: pointer;
            font-size: 0.75rem;
            color: #6b7280;
            transition: all 0.3s ease;
        }

        .like-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .like-btn.liked {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        /* 紧凑评分显示样式 */
        .rating-summary-compact {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rating-display-compact {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .stars-compact {
            display: flex;
            gap: 1px;
        }

        .star-compact {
            font-size: 14px;
            color: #e5e7eb;
        }

        .star-compact.filled {
            color: #f59e0b;
        }

        .rating-text-compact {
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
        }

        /* 问答系统 - 紧凑布局 */
        .question-form-section {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .question-form textarea {
            min-height: 60px;
        }

        .question-item {
            border-bottom: 1px solid #f3f4f6;
            padding: 15px 0;
        }

        .question-item:last-child {
            border-bottom: none;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .questioner-name {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.9rem;
        }

        .question-date,
        .answer-date {
            color: #6b7280;
            font-size: 0.75rem;
        }

        .question-content {
            margin: 8px 0 12px 0;
            line-height: 1.5;
            color: #374151;
            font-size: 0.9rem;
        }

        .answers-list {
            margin: 12px 0 12px 15px;
            border-left: 2px solid #e5e7eb;
            padding-left: 12px;
        }

        .answer-item {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f9fafb;
        }

        .answer-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .answer-header {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 4px;
        }

        .answerer-name {
            font-weight: 500;
            color: #374151;
            font-size: 0.85rem;
        }

        .answer-content {
            line-height: 1.5;
            color: #374151;
            font-size: 0.85rem;
        }

        .answer-form {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #f3f4f6;
        }

        .answer-form textarea {
            min-height: 50px;
            font-size: 0.85rem;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 10px;
        }

        .btn-small {
            padding: 5px 12px;
            font-size: 0.8rem;
            border-radius: 4px;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .review-notice {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            color: #6b7280;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }

        .empty-state {
            text-align: center;
            color: #6b7280;
            padding: 30px 15px;
            font-size: 0.9rem;
        }

        .alert {
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 0.9rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        /* 响应式优化 */
        @media (max-width: 768px) {

            .form-row {
                flex-direction: column;
                gap: 12px;
            }

            .form-bottom {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .reviewer-info {
                gap: 8px;
            }

            .review-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .form-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
        }

        /* 专长标签样式 */
        .specialties-section {
            margin-bottom: 30px;
        }

        .specialties-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .specialty-tags-detail {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .specialty-tag-detail {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .specialty-tag-detail.system-tag {
            border: 1px solid transparent;
        }

        /* 系统标签的特定颜色 */
        .specialty-tag-detail.system-tag[data-specialty="感情"] {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            color: white;
            border-color: #ff6b6b;
        }

        .specialty-tag-detail.system-tag[data-specialty="桃花"] {
            background: linear-gradient(135deg, #ff69b4, #ff91d4);
            color: white;
            border-color: #ff69b4;
        }

        .specialty-tag-detail.system-tag[data-specialty="财运"] {
            background: linear-gradient(135deg, #d4af37, #ffd700);
            color: #000;
            border-color: #d4af37;
        }

        .specialty-tag-detail.system-tag[data-specialty="事业"] {
            background: linear-gradient(135deg, #28a745, #5cb85c);
            color: white;
            border-color: #28a745;
        }

        .specialty-tag-detail.system-tag[data-specialty="运势"] {
            background: linear-gradient(135deg, #ff8c00, #ffa500);
            color: white;
            border-color: #ff8c00;
        }

        .specialty-tag-detail.system-tag[data-specialty="学业"] {
            background: linear-gradient(135deg, #007bff, #4dabf7);
            color: white;
            border-color: #007bff;
        }

        .specialty-tag-detail.system-tag[data-specialty="寻物"] {
            background: linear-gradient(135deg, #6f42c1, #8e44ad);
            color: white;
            border-color: #6f42c1;
        }

        .specialty-tag-detail.custom-tag {
            background: linear-gradient(135deg, #6c757d, #868e96);
            color: white;
            border: 1px solid #6c757d;
        }

        .specialty-tag-detail:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        /* 其他推荐塔罗师部分的标签样式 */
        .related-readers .specialties {
            margin-bottom: 15px;
        }

        .related-readers .specialty-tag {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            margin: 2px;
            transition: all 0.3s ease;
        }

        /* 颜色编码标签样式 - 与readers.php保持一致 */
        .related-readers .specialty-感情 {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            color: white;
        }

        .related-readers .specialty-桃花 {
            background: linear-gradient(135deg, #ff69b4, #ff91d4);
            color: white;
        }

        .related-readers .specialty-财运 {
            background: linear-gradient(135deg, #d4af37, #ffd700);
            color: #000;
        }

        .related-readers .specialty-事业 {
            background: linear-gradient(135deg, #28a745, #5cb85c);
            color: white;
        }

        .related-readers .specialty-运势 {
            background: linear-gradient(135deg, #ff8c00, #ffa500);
            color: white;
        }

        .related-readers .specialty-学业 {
            background: linear-gradient(135deg, #007bff, #4dabf7);
            color: white;
        }

        .related-readers .specialty-寻物 {
            background: linear-gradient(135deg, #6f42c1, #8e44ad);
            color: white;
        }

        .related-readers .specialty-tag:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        /* 其他推荐塔罗师头部样式 */
        .related-readers-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .related-readers-header h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.5rem;
        }

        /* 换一批按钮样式 */
        .btn-refresh {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.2);
        }

        .btn-refresh:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
        }

        .btn-refresh:active {
            transform: translateY(0);
        }

        .btn-refresh.loading {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .refresh-icon {
            font-size: 16px;
            transition: transform 0.3s ease;
        }

        .btn-refresh.loading .refresh-icon {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* 加载状态样式 */
        .readers-grid {
            transition: opacity 0.3s ease;
        }

        .readers-grid.loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* 塔罗师卡片动画 */
        .reader-card {
            transition: all 0.3s ease;
        }

        .reader-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        /* Tata Coin付费界面样式 */
        .contact-payment-section {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 20px;
            border: 2px solid #e5e7eb;
        }

        .user-balance-info {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #d1d5db;
        }

        .balance-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .balance-label {
            font-weight: 600;
            color: #374151;
        }

        .balance-amount {
            font-size: 1.2rem;
            font-weight: 700;
            color: #f59e0b;
        }

        .insufficient-balance {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #fecaca;
        }

        .payment-info {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid #d1d5db;
        }

        .payment-info h3 {
            margin: 0 0 15px 0;
            color: #1f2937;
            font-size: 1.3rem;
        }

        .cost-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            text-align: center;
            margin: 15px 0;
        }

        .cost-amount {
            font-size: 1.4rem;
            font-weight: 700;
            display: block;
        }

        .cost-type {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .cost-note {
            font-size: 0.9rem;
            color: #6b7280;
            text-align: center;
            margin: 10px 0;
        }

        .payment-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .payment-success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
            color: white;
        }

        .insufficient-funds {
            text-align: center;
            padding: 20px;
            background: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 12px;
            color: #92400e;
        }



        @media (max-width: 768px) {
            .specialty-tags-detail {
                gap: 6px;
            }

            .specialty-tag-detail {
                font-size: 12px;
                padding: 4px 8px;
            }

            /* 移动端其他推荐塔罗师标签样式 */
            .related-readers .specialty-tag {
                font-size: 10px;
                padding: 3px 8px;
                margin: 1px;
            }

            /* 移动端换一批按钮样式 */
            .related-readers-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .related-readers-header h2 {
                font-size: 1.3rem;
            }

            .btn-refresh {
                padding: 8px 16px;
                font-size: 13px;
                align-self: center;
            }

            .contact-payment-section {
                padding: 20px 15px;
            }

            .user-balance-info,
            .payment-info {
                padding: 15px;
            }

            .balance-display {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>

    <?php
    // 输出等级标签CSS
    require_once 'includes/level_badge.php';
    outputLevelBadgeCSS();
    ?>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <?php if ($isAdmin): ?>
                <div class="admin-mode-banner">
                    <div class="admin-banner-content">
                        <span class="admin-icon">👑</span>
                        <span class="admin-text">管理员模式</span>
                        <span class="admin-note">您正以管理员身份浏览，可查看所有联系方式</span>
                        <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="admin-link">返回后台</a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="reader-detail">
                <!-- 返回按钮 -->
                <div class="back-link">
                    <a href="<?php echo SITE_URL; ?>/readers.php" class="btn btn-secondary">← 返回塔罗师列表</a>
                </div>
                
                <div class="reader-profile">
                    <div class="reader-photo-section">
                        <?php if (!empty($reader['photo'])): ?>
                            <img src="<?php echo h($reader['photo']); ?>" alt="<?php echo h($reader['full_name']); ?>" class="reader-photo-large">
                        <?php else: ?>
                            <div class="default-photo-large">
                                <i class="icon-user"></i>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($reader['is_featured']): ?>
                            <div class="featured-badge-large">推荐塔罗师</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="reader-info-section">
                        <h1><?php echo h($reader['full_name']); ?></h1>
                        
                        <div class="reader-meta-large">
                            <div class="meta-item">
                                <strong>从业年数：</strong><?php echo h($reader['experience_years']); ?> 年
                            </div>
                            <div class="meta-item">
                                <strong>查看次数：</strong><?php echo $totalViews; ?> 次
                            </div>

                            <!-- 简化的评分信息 -->
                            <?php if (class_exists('ReviewManager') && $reviewManager->isInstalled() && $reviewStats['total_reviews'] > 0): ?>
                                <div class="meta-item rating-summary-compact">
                                    <strong>用户评价：</strong>
                                    <div class="rating-display-compact">
                                        <div class="stars-compact">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="star-compact <?php echo $i <= round($reviewStats['average_rating']) ? 'filled' : ''; ?>">★</span>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="rating-text-compact">
                                            <?php echo number_format($reviewStats['average_rating'], 1); ?>
                                            (<?php echo $reviewStats['total_reviews']; ?>条评价)
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($reader['specialties'])): ?>
                            <div class="specialties-section">
                                <h3>擅长方向</h3>
                                <div class="specialty-tags-detail">
                                    <?php
                                    $systemSpecialties = ['感情', '学业', '桃花', '财运', '事业', '运势', '寻物'];
                                    $specialties = explode('、', $reader['specialties']);
                                    foreach ($specialties as $specialtyItem):
                                        $specialtyItem = trim($specialtyItem);
                                        if (!empty($specialtyItem)):
                                            $isSystemTag = in_array($specialtyItem, $systemSpecialties);
                                    ?>
                                        <a href="tag_readers.php?tag=<?php echo urlencode($specialtyItem); ?>"
                                           class="specialty-tag-detail <?php echo $isSystemTag ? 'system-tag' : 'custom-tag'; ?>"
                                           <?php if ($isSystemTag): ?>data-specialty="<?php echo h($specialtyItem); ?>"<?php endif; ?>
                                           style="text-decoration: none; color: inherit;">
                                            <?php echo h($specialtyItem); ?>
                                        </a>
                                    <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($reader['description'])): ?>
                            <div class="description-section">
                                <h3>个人简介</h3>
                                <p><?php echo nl2br(h($reader['description'])); ?></p>
                            </div>
                        <?php endif; ?>


                    </div>
                </div>
                
                <!-- 价格列表 -->
                <?php if (!empty($reader['price_list_image'])): ?>
                    <div class="price-list-section">
                        <h2>服务价格</h2>
                        <div class="price-list-image">
                            <img src="<?php echo h($reader['price_list_image']); ?>" alt="价格列表">
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- 联系方式 -->
                <div class="contact-section">
                    <h2>联系方式</h2>

                    <?php if (!$canViewContact): ?>
                        <div class="login-required">
                            <p>查看塔罗师联系方式需要先登录</p>
                            <a href="<?php echo SITE_URL; ?>/auth/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary">立即登录</a>
                            <a href="<?php echo SITE_URL; ?>/auth/register.php" class="btn btn-secondary">注册账户</a>
                        </div>
                    <?php elseif ($isAdmin): ?>
                        <!-- 管理员直接显示联系方式 -->
                        <div class="admin-contact-notice">
                            <p style="color: #d4af37; font-weight: 500; margin-bottom: 15px;">
                                <i class="icon-admin"></i> 管理员模式：可直接查看所有联系方式
                            </p>
                        </div>
                    <?php elseif (!$showContact): ?>
                        <!-- 显示付费提示和用户余额 -->
                        <div class="contact-payment-section">
                            <?php if ($paymentError): ?>
                                <div class="payment-error">
                                    <p style="color: #ef4444; font-weight: 500; margin-bottom: 15px;">
                                        ❌ <?php echo h($paymentError); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <div class="user-balance-info">
                                <div class="balance-display">
                                    <span class="balance-label">💰 我的Tata Coin：</span>
                                    <span class="balance-amount"><?php echo number_format($userTataCoinBalance); ?> 枚</span>
                                </div>
                                <?php if ($userTataCoinBalance < $contactCost): ?>
                                    <div class="insufficient-balance">
                                        <p style="color: #ef4444;">余额不足，需要 <?php echo $contactCost; ?> 个Tata Coin</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="contact-preview">
                                <div class="payment-info">
                                    <h3>💳 查看联系方式</h3>
                                    <p>查看 <?php echo h($reader['full_name']); ?> 的联系方式需要消耗：</p>
                                    <div class="cost-display">
                                        <span class="cost-amount"><?php echo $contactCost; ?> 个Tata Coin</span>
                                        <span class="cost-type"><?php echo $reader['is_featured'] ? '(推荐塔罗师)' : '(普通塔罗师)'; ?></span>
                                    </div>

                                </div>

                                <?php if ($hasViewedContact): ?>
                                    <form method="POST">
                                        <button type="submit" name="view_contact" class="btn btn-success">
                                            ✅ 已付费，查看联系方式
                                        </button>
                                    </form>
                                <?php elseif ($userTataCoinBalance >= $contactCost): ?>
                                    <form method="POST" onsubmit="return confirmPayment()">
                                        <button type="submit" name="view_contact" class="btn btn-primary">
                                            💳 支付 <?php echo $contactCost; ?> Tata Coin 查看
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="insufficient-funds">
                                        <p>Tata Coin余额不足</p>
                                        <a href="<?php echo SITE_URL; ?>/user/index.php" class="btn btn-secondary">前往用户中心</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($paymentSuccess): ?>
                        <div class="payment-success">
                            <p style="color: #10b981; font-weight: 500; margin-bottom: 15px;">
                                ✅ <?php echo h($paymentSuccess); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if ($showContact || $isAdmin): ?>
                        <div class="contact-info">
                            <?php if (!empty($reader['contact_info'])): ?>
                                <div class="contact-details">
                                    <h3>📝 联系信息</h3>
                                    <div class="contact-text">
                                        <?php echo nl2br(h($reader['contact_info'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="contact-methods">
                                <?php if (!empty($reader['phone'])): ?>
                                    <div class="contact-item">
                                        <span class="contact-icon">📞</span>
                                        <strong>电话：</strong>
                                        <span class="contact-value"><?php echo h($reader['phone']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($reader['wechat'])): ?>
                                    <div class="contact-item">
                                        <span class="contact-icon">💬</span>
                                        <strong>微信：</strong>
                                        <span class="contact-value"><?php echo h($reader['wechat']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($reader['qq'])): ?>
                                    <div class="contact-item">
                                        <span class="contact-icon">🐧</span>
                                        <strong>QQ：</strong>
                                        <span class="contact-value"><?php echo h($reader['qq']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($reader['xiaohongshu'])): ?>
                                    <div class="contact-item">
                                        <span class="contact-icon">📖</span>
                                        <strong>小红书：</strong>
                                        <span class="contact-value"><?php echo h($reader['xiaohongshu']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($reader['douyin'])): ?>
                                    <div class="contact-item">
                                        <span class="contact-icon">🎵</span>
                                        <strong>抖音：</strong>
                                        <span class="contact-value"><?php echo h($reader['douyin']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($reader['other_contact'])): ?>
                                    <div class="contact-item">
                                        <span class="contact-icon">🔗</span>
                                        <strong>其他：</strong>
                                        <span class="contact-value"><?php echo h($reader['other_contact']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="contact-item">
                                    <span class="contact-icon">📧</span>
                                    <strong>邮箱：</strong>
                                    <span class="contact-value"><?php echo h($reader['email']); ?></span>
                                </div>
                            </div>

                            <div class="contact-note">
                                <p><strong>💡 温馨提示：</strong>联系占卜师的时候请务必说明是通过“玄”网站来的，才能获得网站专属最低优惠价。建议先了解服务内容和价格再进行预约。</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 评价系统 -->
                <?php if (class_exists('ReviewManager') && $reviewManager->isInstalled()): ?>


                    <!-- 评价表单 -->
                    <?php if ($isAdmin || isset($_SESSION['user_id'])): ?>
                        <?php if ($canReview): ?>
                            <div class="review-form-section">
                                <h3>📝 写评价</h3>
                                <?php if ($reviewError): ?>
                                    <div class="alert alert-error"><?php echo h($reviewError); ?></div>
                                <?php endif; ?>
                                <?php if ($reviewSuccess): ?>
                                    <div class="alert alert-success"><?php echo h($reviewSuccess); ?></div>
                                <?php endif; ?>

                                <form method="POST" class="review-form" id="reviewForm">
                                    <div class="form-row">
                                        <div class="rating-input">
                                            <label>评分</label>
                                            <div class="star-rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" <?php echo $i == 5 ? 'checked' : ''; ?> required>
                                                    <label for="star<?php echo $i; ?>" class="star-label">★</label>
                                                <?php endfor; ?>
                                            </div>
                                            <div id="ratingError" class="rating-error" style="display: none;">
                                                <span>请先评分</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="review_text">评价内容</label>
                                        <textarea name="review_text" id="review_text" placeholder="分享您的体验..."></textarea>
                                    </div>

                                    <div class="form-bottom">
                                        <div class="checkbox-group">
                                            <input type="checkbox" name="is_anonymous" id="is_anonymous">
                                            <label for="is_anonymous">匿名评价</label>
                                        </div>
                                        <button type="submit" name="submit_review" class="btn btn-primary">提交评价</button>
                                    </div>
                                </form>
                            </div>
                        <?php elseif ($hasReviewed): ?>
                            <div class="review-notice">
                                <p>✅ 您已经评价过该塔罗师了</p>
                            </div>
                        <?php elseif (!$hasPurchased): ?>
                            <div class="review-notice">
                                <p>💡 购买服务后可以评价该塔罗师</p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="review-notice">
                            <p>💡 <a href="auth/login.php">登录</a> 后可以查看和发表评价</p>
                        </div>
                    <?php endif; ?>

                    <!-- 评价列表 -->
                    <div class="reviews-list">
                        <h3>💬 用户评价</h3>
                        <?php if (empty($reviews)): ?>
                            <div class="empty-state">
                                <p>暂无评价，成为第一个评价的用户吧！</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-item">
                                    <div class="review-header">
                                        <div class="reviewer-info">
                                            <img src="<?php echo h($review['user_avatar'] ?: ($review['is_anonymous'] ? '../img/anonymous.jpg' : '../img/nm.jpg')); ?>"
                                                 alt="用户头像" class="reviewer-avatar">
                                            <div class="reviewer-details">
                                                <div class="reviewer-name">
                                                    <?php echo h($review['user_name']); ?>
                                                    <?php
                                                    // 显示用户等级标签
                                                    if (!$review['is_anonymous'] && !empty($review['user_id'])) {
                                                        require_once 'includes/level_badge.php';
                                                        echo getUserLevelBadge($review['user_id'], 'user', 'small');
                                                    }
                                                    ?>
                                                </div>
                                                <div class="reviewer-meta">
                                                    <div class="review-rating">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <span class="star <?php echo $i <= $review['rating'] ? 'filled' : ''; ?>">★</span>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="review-date"><?php echo date('m-d', strtotime($review['created_at'])); ?></div>
                                    </div>

                                    <?php if (!empty($review['review_text'])): ?>
                                        <div class="review-content">
                                            <?php echo nl2br(h($review['review_text'])); ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="review-actions">
                                        <?php if ($isAdmin || isset($_SESSION['user_id'])): ?>
                                            <button class="like-btn <?php echo isset($userLikes[$review['id']]) ? 'liked' : ''; ?>"
                                                    data-review-id="<?php echo $review['id']; ?>">
                                                👍 <span class="like-count"><?php echo $review['like_count']; ?></span>
                                            </button>
                                        <?php else: ?>
                                            <span class="like-display">👍 <?php echo $review['like_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- 问大家功能 -->
                    <div class="questions-section">
                        <h3>❓ 问大家</h3>

                        <!-- 提问表单 -->
                        <?php if ($isAdmin || isset($_SESSION['user_id'])): ?>
                            <div class="question-form-section">
                                <?php if ($questionError): ?>
                                    <div class="alert alert-error"><?php echo h($questionError); ?></div>
                                <?php endif; ?>
                                <?php if ($questionSuccess): ?>
                                    <div class="alert alert-success"><?php echo h($questionSuccess); ?></div>
                                <?php endif; ?>

                                <form method="POST" class="question-form">
                                    <div class="form-group">
                                        <textarea name="question" placeholder="想了解什么？向大家提问..." required></textarea>
                                    </div>
                                    <div class="form-actions">
                                        <div class="checkbox-group">
                                            <input type="checkbox" name="question_anonymous" id="question_anonymous">
                                            <label for="question_anonymous">匿名提问</label>
                                        </div>
                                        <button type="submit" name="submit_question" class="btn btn-secondary">提问</button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="review-notice">
                                <p>💡 <a href="auth/login.php">登录</a> 后可以提问</p>
                            </div>
                        <?php endif; ?>

                        <!-- 问题列表 -->
                        <div class="questions-list">
                            <?php if (empty($questions)): ?>
                                <div class="empty-state">
                                    <p>暂无问题，快来提问吧！</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($questions as $question): ?>
                                    <div class="question-item">
                                        <div class="question-header">
                                            <span class="questioner-name"><?php echo h($question['user_name']); ?></span>
                                            <span class="question-date"><?php echo date('Y-m-d', strtotime($question['created_at'])); ?></span>
                                        </div>
                                        <div class="question-content">
                                            <?php echo nl2br(h($question['question'])); ?>
                                        </div>

                                        <!-- 回答列表 -->
                                        <?php $answers = $reviewManager->getAnswers($question['id']); ?>
                                        <?php if (!empty($answers)): ?>
                                            <div class="answers-list">
                                                <?php foreach ($answers as $answer): ?>
                                                    <div class="answer-item">
                                                        <div class="answer-header">
                                                            <span class="answerer-name"><?php echo h($answer['user_name']); ?></span>
                                                            <span class="answer-date"><?php echo date('m-d H:i', strtotime($answer['created_at'])); ?></span>
                                                        </div>
                                                        <div class="answer-content">
                                                            <?php echo nl2br(h($answer['answer'])); ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- 回答表单 -->
                                        <?php if ($isAdmin || isset($_SESSION['user_id'])): ?>
                                            <div class="answer-form">
                                                <form method="POST" class="inline-form">
                                                    <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                                    <div class="form-group">
                                                        <textarea name="answer" placeholder="回答这个问题..." required></textarea>
                                                    </div>
                                                    <div class="form-actions">
                                                        <div class="checkbox-group">
                                                            <input type="checkbox" name="answer_anonymous" id="answer_anonymous_<?php echo $question['id']; ?>">
                                                            <label for="answer_anonymous_<?php echo $question['id']; ?>">匿名回答</label>
                                                        </div>
                                                        <button type="submit" name="submit_answer" class="btn btn-small btn-secondary">回答</button>
                                                    </div>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <div class="review-notice" style="margin-top: 10px; padding: 8px 12px; font-size: 0.85rem;">
                                                <p><a href="auth/login.php">登录</a> 后可以回答</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 相关推荐 -->
                <?php
                $relatedReaders = $db->fetchAll(
                    "SELECT * FROM readers 
                     WHERE id != ? AND is_active = 1 
                     ORDER BY is_featured DESC, RAND() 
                     LIMIT 3",
                    [$readerId]
                );
                ?>
                
                <?php if (!empty($relatedReaders)): ?>
                    <div class="related-readers">
                        <div class="related-readers-header">
                            <h2>其他推荐塔罗师</h2>
                            <button id="refreshReaders" class="btn-refresh" onclick="refreshRelatedReaders()">
                                <span class="refresh-icon">🔄</span>
                                换一批
                            </button>
                        </div>
                        <div class="readers-grid" id="relatedReadersGrid">
                            <?php foreach ($relatedReaders as $relatedReader): ?>
                                <div class="reader-card">
                                    <div class="reader-photo">
                                        <a href="<?php echo SITE_URL; ?>/reader.php?id=<?php echo $relatedReader['id']; ?>" class="reader-photo-link">
                                            <?php if (!empty($relatedReader['photo'])): ?>
                                                <img src="<?php echo h($relatedReader['photo']); ?>" alt="<?php echo h($relatedReader['full_name']); ?>">
                                            <?php else: ?>
                                                <div class="default-photo">
                                                    <i class="icon-user"></i>
                                                </div>
                                            <?php endif; ?>
                                        </a>
                                    </div>

                                    <div class="reader-info">
                                        <h3><?php echo h($relatedReader['full_name']); ?></h3>
                                        <p>从业 <?php echo h($relatedReader['experience_years']); ?> 年</p>
                                        <?php if (!empty($relatedReader['specialties'])): ?>
                                            <div class="specialties">
                                                <strong>擅长：</strong>
                                                <?php
                                                // 系统提供的标准擅长方向
                                                $systemSpecialties = ['感情', '学业', '桃花', '财运', '事业', '运势', '寻物'];
                                                $specialties = explode('、', $relatedReader['specialties']);
                                                foreach ($specialties as $specialtyItem):
                                                    $specialtyItem = trim($specialtyItem);
                                                    // 只显示系统提供的标准标签
                                                    if (!empty($specialtyItem) && in_array($specialtyItem, $systemSpecialties)):
                                                ?>
                                                    <a href="readers.php?specialty=<?php echo urlencode($specialtyItem); ?>"
                                                       class="specialty-tag specialty-<?php echo h($specialtyItem); ?>"><?php echo h($specialtyItem); ?></a>
                                                <?php
                                                    endif;
                                                endforeach;
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                        <a href="<?php echo SITE_URL; ?>/reader.php?id=<?php echo $relatedReader['id']; ?>" class="btn btn-primary">查看详情</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>

    <script>
        function confirmPayment() {
            const cost = <?php echo $contactCost; ?>;
            const readerName = "<?php echo addslashes($reader['full_name']); ?>";
            const balance = <?php echo $userTataCoinBalance; ?>;

            const message = `确认支付 ${cost} 个Tata Coin 查看 ${readerName} 的联系方式吗？\n\n当前余额：${balance} 个Tata Coin\n支付后余额：${balance - cost} 个Tata Coin`;

            return confirm(message);
        }

        // 页面加载完成后的处理
        document.addEventListener('DOMContentLoaded', function() {
            // 如果有支付成功消息，3秒后自动隐藏
            const successMessage = document.querySelector('.payment-success');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.transition = 'opacity 0.5s ease';
                    successMessage.style.opacity = '0';
                    setTimeout(() => {
                        successMessage.style.display = 'none';
                    }, 500);
                }, 3000);
            }

            // 星级评分交互
            const starInputs = document.querySelectorAll('.star-rating input[type="radio"]');
            const starLabels = document.querySelectorAll('.star-rating .star-label');

            // 初始化显示（默认5星选中）
            function updateStarDisplay() {
                const checkedInput = document.querySelector('.star-rating input[type="radio"]:checked');
                if (checkedInput) {
                    const checkedIndex = Array.from(starInputs).indexOf(checkedInput);
                    for (let i = 0; i <= checkedIndex; i++) {
                        starLabels[i].style.color = '#f59e0b';
                    }
                    for (let i = checkedIndex + 1; i < starLabels.length; i++) {
                        starLabels[i].style.color = '#e5e7eb';
                    }
                }
            }

            // 页面加载时初始化
            updateStarDisplay();

            // 表单验证
            const reviewForm = document.getElementById('reviewForm');
            if (reviewForm) {
                reviewForm.addEventListener('submit', function(e) {
                    const checkedRating = document.querySelector('.star-rating input[type="radio"]:checked');
                    const ratingError = document.getElementById('ratingError');

                    if (!checkedRating) {
                        e.preventDefault();
                        ratingError.style.display = 'block';

                        // 3秒后自动隐藏错误提示
                        setTimeout(() => {
                            ratingError.style.display = 'none';
                        }, 3000);

                        // 滚动到评分区域
                        document.querySelector('.star-rating').scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });

                        return false;
                    }

                    // 隐藏错误提示
                    ratingError.style.display = 'none';
                });
            }

            starLabels.forEach((label, index) => {
                label.addEventListener('mouseenter', function() {
                    // 高亮当前星级及之前的星级
                    for (let i = 0; i <= index; i++) {
                        starLabels[i].style.color = '#f59e0b';
                    }
                    for (let i = index + 1; i < starLabels.length; i++) {
                        starLabels[i].style.color = '#e5e7eb';
                    }
                });

                label.addEventListener('mouseleave', function() {
                    // 恢复到选中状态
                    updateStarDisplay();
                });

                label.addEventListener('click', function() {
                    // 选中对应的radio
                    starInputs[index].checked = true;
                    // 更新显示
                    updateStarDisplay();
                });
            });

            // 点赞功能
            const likeButtons = document.querySelectorAll('.like-btn');
            likeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const reviewId = this.dataset.reviewId;
                    const likeCountSpan = this.querySelector('.like-count');
                    const currentCount = parseInt(likeCountSpan.textContent);

                    // 发送AJAX请求
                    fetch('ajax/like_review.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            review_id: reviewId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (data.liked) {
                                this.classList.add('liked');
                                likeCountSpan.textContent = currentCount + 1;
                            } else {
                                this.classList.remove('liked');
                                likeCountSpan.textContent = Math.max(0, currentCount - 1);
                            }
                        } else {
                            alert(data.message || '操作失败');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('网络错误，请稍后重试');
                    });
                });
            });
        });

        // 换一批推荐塔罗师功能
        function refreshRelatedReaders() {
            const refreshBtn = document.getElementById('refreshReaders');
            const readersGrid = document.getElementById('relatedReadersGrid');
            const currentReaderId = <?php echo $readerId; ?>;

            console.log('开始刷新塔罗师，当前ID:', currentReaderId);

            // 设置加载状态
            refreshBtn.classList.add('loading');
            refreshBtn.disabled = true;
            readersGrid.classList.add('loading');

            // 发送AJAX请求
            fetch('<?php echo SITE_URL; ?>/api/get_related_readers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'reader_id=' + currentReaderId
            })
            .then(response => {
                console.log('响应状态:', response.status);
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.text();
            })
            .then(text => {
                console.log('原始响应:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('解析后的数据:', data);

                    if (data.success && data.html) {
                        // 淡出效果
                        readersGrid.style.opacity = '0';
                        setTimeout(() => {
                            readersGrid.innerHTML = data.html;
                            readersGrid.style.opacity = '1';
                        }, 300);
                    } else {
                        console.error('API返回错误:', data);
                        alert('获取塔罗师失败：' + (data.message || '未知错误'));
                    }
                } catch (parseError) {
                    console.error('JSON解析错误:', parseError);
                    console.error('原始响应:', text);
                    alert('服务器响应格式错误，请稍后重试');
                }
            })
            .catch(error => {
                console.error('请求错误:', error);
                alert('网络错误：' + error.message);
            })
            .finally(() => {
                // 恢复按钮状态
                setTimeout(() => {
                    refreshBtn.classList.remove('loading');
                    refreshBtn.disabled = false;
                    readersGrid.classList.remove('loading');
                }, 500);
            });
        }
    </script>
</body>
</html>
