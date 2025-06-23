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

// 获取已购买的塔罗师联系方式
$purchasedReaders = $db->fetchAll("
    SELECT ubh.*, r.full_name, r.photo, r.photo_circle, r.specialties, r.is_featured, r.experience_years
    FROM user_browse_history ubh
    JOIN readers r ON ubh.reader_id = r.id
    WHERE ubh.user_id = ? AND ubh.browse_type = 'paid'
    ORDER BY ubh.created_at DESC
    LIMIT ? OFFSET ?
", [$userId, $limit, $offset]);

// 获取总数
$totalCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM user_browse_history WHERE user_id = ? AND browse_type = 'paid'",
    [$userId]
)['count'];

$totalPages = ceil($totalCount / $limit);

// 统计信息
$stats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_purchases,
        SUM(cost) as total_spent,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_purchases
    FROM user_browse_history 
    WHERE user_id = ? AND browse_type = 'paid'
", [$userId]);

$pageTitle = '我的购买';
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
        .purchases-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        
        .purchases-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .purchases-header h1 {
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
        
        .purchases-list {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .purchase-item {
            display: flex;
            align-items: center;
            padding: 25px;
            border-bottom: 1px solid #f1f5f9;
            transition: background-color 0.3s ease;
        }
        
        .purchase-item:last-child {
            border-bottom: none;
        }
        
        .purchase-item:hover {
            background: #f8fafc;
        }
        
        .reader-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 20px;
            border: 3px solid #e5e7eb;
        }
        
        .reader-info {
            flex: 1;
        }
        
        .reader-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .featured-badge {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
        }
        
        .reader-meta {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        
        .reader-specialties {
            color: #4b5563;
            font-size: 0.85rem;
        }
        
        .purchase-meta {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
        }
        
        .purchase-cost {
            background: #fee2e2;
            color: #991b1b;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .purchase-time {
            font-size: 0.8rem;
            color: #6b7280;
        }
        
        .purchase-actions {
            display: flex;
            gap: 10px;
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
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .pagination .current {
            background: #667eea;
            color: white;
            border: 1px solid #667eea;
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
        
        @media (max-width: 768px) {
            .purchases-container {
                margin: 20px auto;
                padding: 15px;
            }
            
            .purchases-header {
                padding: 20px 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .stat-card {
                padding: 20px 15px;
            }
            
            .purchase-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
                padding: 20px 15px;
            }
            
            .reader-avatar {
                width: 60px;
                height: 60px;
                margin-right: 15px;
            }
            
            .purchase-meta {
                align-items: flex-start;
                width: 100%;
            }
            
            .purchase-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="purchases-container">
        <div class="purchases-header">
            <h1>🛒 我的购买</h1>
            <p>查看您已购买的塔罗师联系方式</p>
        </div>
        
        <a href="index.php" class="btn-back">← 返回用户中心</a>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_purchases']); ?></div>
                <div class="stat-label">总购买次数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_spent']); ?></div>
                <div class="stat-label">累计消费 Tata Coin</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['recent_purchases']); ?></div>
                <div class="stat-label">近30天购买</div>
            </div>
        </div>
        
        <div class="purchases-list">
            <?php if (empty($purchasedReaders)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🛍️</div>
                    <h3>暂无购买记录</h3>
                    <p>您还没有购买过任何塔罗师的联系方式</p>
                    <a href="../readers.php" class="btn-view" style="margin-top: 15px;">去看看塔罗师</a>
                </div>
            <?php else: ?>
                <?php foreach ($purchasedReaders as $purchase): ?>
                    <div class="purchase-item">
                        <img src="../<?php echo h($purchase['photo_circle'] ?: ($purchase['photo'] ?: 'img/tm.jpg')); ?>" 
                             alt="<?php echo h($purchase['full_name']); ?>" 
                             class="reader-avatar">
                        
                        <div class="reader-info">
                            <div class="reader-name">
                                <?php echo h($purchase['full_name']); ?>
                                <?php if ($purchase['is_featured']): ?>
                                    <span class="featured-badge">推荐</span>
                                <?php endif; ?>
                            </div>
                            <div class="reader-meta">
                                从业 <?php echo h($purchase['experience_years']); ?> 年
                            </div>
                            <div class="reader-specialties">
                                擅长：<?php echo h($purchase['specialties'] ?: '暂无'); ?>
                            </div>
                        </div>
                        
                        <div class="purchase-meta">
                            <div class="purchase-cost">
                                -<?php echo $purchase['cost']; ?> Tata Coin
                            </div>
                            <div class="purchase-time">
                                <?php echo date('Y-m-d H:i', strtotime($purchase['created_at'])); ?>
                            </div>
                            <div class="purchase-actions">
                                <a href="../reader.php?id=<?php echo $purchase['reader_id']; ?>" 
                                   class="btn-view" target="_blank">
                                    再次查看
                                </a>
                            </div>
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
