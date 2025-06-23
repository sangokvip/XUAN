<?php
session_start();
require_once '../config/config.php';
require_once '../includes/ReviewManager.php';

// 检查管理员登录
requireAdminLogin();

$reviewManager = new ReviewManager();
$db = Database::getInstance();

$errors = [];
$success = '';

// 处理删除评价
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
    $reviewId = (int)($_POST['review_id'] ?? 0);

    try {
        // 获取评价信息
        $review = $db->fetchOne("SELECT reader_id FROM reader_reviews WHERE id = ?", [$reviewId]);
        if ($review) {
            // 删除评价
            $db->query("DELETE FROM reader_reviews WHERE id = ?", [$reviewId]);

            // 更新评分统计
            $reviewManager->updateReaderRatingStats($review['reader_id']);

            $success = '评价删除成功！';
        } else {
            $errors[] = '评价不存在';
        }
    } catch (Exception $e) {
        $errors[] = '删除失败：' . $e->getMessage();
    }
}

// 处理删除问题
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question'])) {
    $questionId = (int)($_POST['question_id'] ?? 0);
    
    try {
        $db->query("DELETE FROM reader_questions WHERE id = ?", [$questionId]);
        $success = '问题删除成功！';
    } catch (Exception $e) {
        $errors[] = '删除失败：' . $e->getMessage();
    }
}

// 获取统计数据
$stats = $db->fetchOne("
    SELECT 
        (SELECT COUNT(*) FROM reader_reviews) as total_reviews,
        (SELECT COUNT(*) FROM reader_questions) as total_questions,
        (SELECT COUNT(*) FROM reader_question_answers) as total_answers,
        (SELECT AVG(rating) FROM reader_reviews) as average_rating
");

// 获取最新评价
$recentReviews = $db->fetchAll("
    SELECT r.*, 
           u.full_name as user_name,
           rd.full_name as reader_name
    FROM reader_reviews r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN readers rd ON r.reader_id = rd.id
    ORDER BY r.created_at DESC
    LIMIT 20
");

// 获取最新问题
$recentQuestions = $db->fetchAll("
    SELECT q.*, 
           u.full_name as user_name,
           rd.full_name as reader_name,
           (SELECT COUNT(*) FROM reader_question_answers WHERE question_id = q.id) as answer_count
    FROM reader_questions q
    LEFT JOIN users u ON q.user_id = u.id
    LEFT JOIN readers rd ON q.reader_id = rd.id
    ORDER BY q.created_at DESC
    LIMIT 20
");

$pageTitle = '评价管理';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .reviews-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .management-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .management-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .card-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .review-item,
        .question-item {
            border-bottom: 1px solid #f3f4f6;
            padding: 15px 0;
        }
        
        .review-item:last-child,
        .question-item:last-child {
            border-bottom: none;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .item-info {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            color: #1f2937;
        }
        
        .reader-name {
            color: #667eea;
            font-weight: 500;
        }
        
        .rating-display {
            margin: 5px 0;
        }
        
        .star {
            color: #f59e0b;
            font-size: 0.9rem;
        }
        
        .star.empty {
            color: #e5e7eb;
        }
        
        .item-content {
            margin: 10px 0;
            color: #374151;
            line-height: 1.6;
        }
        
        .item-meta {
            display: flex;
            gap: 15px;
            font-size: 0.8rem;
            color: #6b7280;
        }
        
        .item-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background 0.3s ease;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .empty-state {
            text-align: center;
            color: #6b7280;
            padding: 40px 20px;
        }
        
        @media (max-width: 768px) {
            .management-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="reviews-container">
        <div class="page-header">
            <h1>⭐ 评价管理</h1>
            <p>管理用户评价和问答系统</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo h($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo h($success); ?>
            </div>
        <?php endif; ?>
        
        <!-- 统计数据 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_reviews']); ?></div>
                <div class="stat-label">总评价数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_questions']); ?></div>
                <div class="stat-label">总问题数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_answers']); ?></div>
                <div class="stat-label">总回答数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['average_rating'], 1); ?></div>
                <div class="stat-label">平均评分</div>
            </div>
        </div>
        
        <!-- 管理功能 -->
        <div class="management-grid">
            <!-- 最新评价 -->
            <div class="management-card">
                <h3 class="card-title">💬 最新评价</h3>
                <?php if (empty($recentReviews)): ?>
                    <div class="empty-state">
                        <p>暂无评价</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentReviews as $review): ?>
                        <div class="review-item">
                            <div class="item-header">
                                <div class="item-info">
                                    <div class="user-name">
                                        <?php echo $review['is_anonymous'] ? '匿名用户' : h($review['user_name']); ?>
                                    </div>
                                    <div class="reader-name">评价：<?php echo h($review['reader_name']); ?></div>
                                    <div class="rating-display">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="star <?php echo $i <= $review['rating'] ? '' : 'empty'; ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="item-actions">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('确认删除这条评价吗？')">
                                        <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                        <button type="submit" name="delete_review" class="btn-danger">删除</button>
                                    </form>
                                </div>
                            </div>
                            
                            <?php if (!empty($review['review_text'])): ?>
                                <div class="item-content">
                                    <?php echo nl2br(h($review['review_text'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="item-meta">
                                <span>时间：<?php echo date('Y-m-d H:i', strtotime($review['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- 最新问题 -->
            <div class="management-card">
                <h3 class="card-title">❓ 最新问题</h3>
                <?php if (empty($recentQuestions)): ?>
                    <div class="empty-state">
                        <p>暂无问题</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentQuestions as $question): ?>
                        <div class="question-item">
                            <div class="item-header">
                                <div class="item-info">
                                    <div class="user-name">
                                        <?php echo $question['is_anonymous'] ? '匿名用户' : h($question['user_name']); ?>
                                    </div>
                                    <div class="reader-name">询问：<?php echo h($question['reader_name']); ?></div>
                                </div>
                                <div class="item-actions">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('确认删除这个问题吗？')">
                                        <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                        <button type="submit" name="delete_question" class="btn-danger">删除</button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="item-content">
                                <?php echo nl2br(h($question['question'])); ?>
                            </div>
                            
                            <div class="item-meta">
                                <span>时间：<?php echo date('Y-m-d H:i', strtotime($question['created_at'])); ?></span>
                                <span>回答数：<?php echo $question['answer_count']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../includes/admin_footer.php'; ?>
</body>
</html>
