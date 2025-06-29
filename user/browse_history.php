<?php
session_start();
require_once '../config/config.php';
require_once '../includes/TataCoinManager.php';

// 检查用户登录
requireLogin('../auth/login.php');

$db = Database::getInstance();
$tataCoinManager = new TataCoinManager();

$userId = $_SESSION['user_id'];
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

if (!$user) {
    logout();
    redirect('../auth/login.php');
}

// 分页参数
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// 获取浏览记录
$browseHistory = $tataCoinManager->getBrowseHistory($userId, $limit, $offset);

// 获取总记录数
$totalCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM user_browse_history WHERE user_id = ?",
    [$userId]
)['count'];

$totalPages = ceil($totalCount / $limit);

// 统计数据
$stats = $db->fetchOne(
    "SELECT 
        COUNT(*) as total_views,
        COUNT(CASE WHEN browse_type = 'paid' THEN 1 END) as paid_views,
        COUNT(CASE WHEN browse_type = 'free' THEN 1 END) as free_views,
        SUM(CASE WHEN browse_type = 'paid' THEN cost ELSE 0 END) as total_spent
     FROM user_browse_history 
     WHERE user_id = ?",
    [$userId]
);

$pageTitle = '浏览记录';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .history-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        
        .history-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .history-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .stat-card.total {
            border-left: 4px solid #4facfe;
        }
        
        .stat-card.paid {
            border-left: 4px solid #f59e0b;
        }
        
        .stat-card.free {
            border-left: 4px solid #10b981;
        }
        
        .stat-card.spent {
            border-left: 4px solid #ef4444;
        }
        
        .history-list {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .history-item {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #f1f5f9;
            transition: background-color 0.3s ease;
        }
        
        .history-item:last-child {
            border-bottom: none;
        }
        
        .history-item:hover {
            background: #f8fafc;
        }
        
        .reader-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 20px;
            border: 2px solid #e5e7eb;
        }
        
        .reader-info {
            flex: 1;
        }
        
        .reader-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .reader-specialties {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .browse-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .browse-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .type-free {
            background: #d1fae5;
            color: #065f46;
        }
        
        .type-paid {
            background: #fef3c7;
            color: #92400e;
        }
        
        .browse-cost {
            font-weight: 600;
            color: #ef4444;
        }
        
        .browse-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
        }
        
        .browse-time {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-view:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .btn-back {
            background: #6b7280;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .btn-back:hover {
            background: #4b5563;
            transform: translateY(-1px);
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .pagination a,
        .pagination span {
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .pagination a {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        
        .pagination a:hover {
            background: #4facfe;
            color: white;
            border-color: #4facfe;
        }
        
        .pagination .current {
            background: #4facfe;
            color: white;
            border: 1px solid #4facfe;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            margin: 0 0 10px 0;
            color: #374151;
        }
        
        .featured-badge {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
            margin-left: 8px;
        }
        
        @media (max-width: 768px) {
            .history-container {
                margin: 20px auto;
                padding: 15px;
            }
            
            .history-header {
                padding: 20px 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .stat-card {
                padding: 20px 15px;
            }
            
            .history-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
                padding: 15px;
            }
            
            .reader-avatar {
                width: 50px;
                height: 50px;
                margin-right: 15px;
            }
            
            .browse-actions {
                align-items: flex-start;
                width: 100%;
            }
            
            .browse-meta {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="history-container">
        <div class="history-header">
            <h1>📖 浏览记录</h1>
            <p>查看您访问过的塔罗师</p>
        </div>
        
        <a href="index.php" class="btn-back">← 返回用户中心</a>
        
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-number"><?php echo number_format($stats['total_views']); ?></div>
                <div class="stat-label">总浏览次数</div>
            </div>
            <div class="stat-card paid">
                <div class="stat-number"><?php echo number_format($stats['paid_views']); ?></div>
                <div class="stat-label">付费查看次数</div>
            </div>
            <div class="stat-card free">
                <div class="stat-number"><?php echo number_format($stats['free_views']); ?></div>
                <div class="stat-label">免费浏览次数</div>
            </div>
            <div class="stat-card spent">
                <div class="stat-number"><?php echo number_format($stats['total_spent']); ?></div>
                <div class="stat-label">累计消费 Tata Coin</div>
            </div>
        </div>
        
        <div class="history-list">
            <?php if (empty($browseHistory)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">👁️</div>
                    <h3>暂无浏览记录</h3>
                    <p>您还没有浏览过任何塔罗师</p>
                    <a href="../readers.php" class="btn-view" style="margin-top: 15px;">去看看塔罗师</a>
                </div>
            <?php else: ?>
                <?php foreach ($browseHistory as $history): ?>
                    <div class="history-item">
                        <?php
                        $photoSrc = '';
                        if (!empty($history['photo_circle'])) {
                            $photoSrc = '../' . $history['photo_circle'];
                        } elseif (!empty($history['photo'])) {
                            $photoSrc = '../' . $history['photo'];
                        } else {
                            // 根据性别使用默认头像
                            $photoSrc = ($history['gender'] === 'female') ? '../img/tf.jpg' : '../img/tm.jpg';
                        }
                        ?>
                        <img src="<?php echo h($photoSrc); ?>"
                             alt="<?php echo h($history['full_name']); ?>"
                             class="reader-avatar">
                        
                        <div class="reader-info">
                            <div class="reader-name">
                                <?php echo h($history['full_name']); ?>
                                <?php if ($history['is_featured']): ?>
                                    <span class="featured-badge">推荐</span>
                                <?php endif; ?>
                            </div>
                            <div class="reader-specialties">
                                擅长：<?php echo h($history['specialties'] ?: '暂无'); ?>
                            </div>
                            <div class="browse-meta">
                                <span class="browse-type type-<?php echo $history['browse_type']; ?>">
                                    <?php echo $history['browse_type'] === 'paid' ? '付费查看' : '免费浏览'; ?>
                                </span>
                                <?php if ($history['cost'] > 0): ?>
                                    <span class="browse-cost">-<?php echo $history['cost']; ?> Tata Coin</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="browse-actions">
                            <div class="browse-time">
                                <?php echo date('Y-m-d H:i', strtotime($history['created_at'])); ?>
                            </div>
                            <a href="../reader.php?id=<?php echo $history['reader_id']; ?>" class="btn-view">
                                再次查看
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>">← 上一页</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>">下一页 →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
