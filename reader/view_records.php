<?php
session_start();
require_once '../config/config.php';

// 检查塔罗师权限
requireReaderLogin();

$db = Database::getInstance();
$readerId = $_SESSION['reader_id'];

// 获取筛选参数
$page = max(1, (int)($_GET['page'] ?? 1));
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// 构建查询条件
$whereClause = "WHERE cv.reader_id = ?";
$params = [$readerId];

if (!empty($dateFrom)) {
    $whereClause .= " AND DATE(cv.viewed_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereClause .= " AND DATE(cv.viewed_at) <= ?";
    $params[] = $dateTo;
}

// 获取查看记录
$offset = ($page - 1) * ADMIN_ITEMS_PER_PAGE;

$viewRecords = $db->fetchAll(
    "SELECT cv.*, u.full_name as user_name, u.email as user_email
     FROM contact_views cv 
     LEFT JOIN users u ON cv.user_id = u.id 
     {$whereClause} 
     ORDER BY cv.viewed_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [ADMIN_ITEMS_PER_PAGE, $offset])
);

// 获取总数
$totalResult = $db->fetchOne(
    "SELECT COUNT(*) as total FROM contact_views cv {$whereClause}",
    $params
);
$total = $totalResult['total'];
$totalPages = ceil($total / ADMIN_ITEMS_PER_PAGE);

// 获取统计数据
$stats = $db->fetchOne(
    "SELECT 
        COUNT(*) as total_views,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(CASE WHEN DATE(viewed_at) = CURDATE() THEN 1 END) as today_views,
        COUNT(CASE WHEN WEEK(viewed_at) = WEEK(CURDATE()) AND YEAR(viewed_at) = YEAR(CURDATE()) THEN 1 END) as week_views,
        COUNT(CASE WHEN MONTH(viewed_at) = MONTH(CURDATE()) AND YEAR(viewed_at) = YEAR(CURDATE()) THEN 1 END) as month_views
     FROM contact_views 
     WHERE reader_id = ?",
    [$readerId]
);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>查看记录 - 塔罗师后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/reader.css">
</head>
<body>
    <?php include '../includes/reader_header.php'; ?>
    
    <div class="reader-container">
        <div class="reader-sidebar">
            <?php include '../includes/reader_sidebar.php'; ?>
        </div>
        
        <div class="reader-content">
            <h1>查看记录</h1>
            
            <!-- 统计卡片 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_views']; ?></div>
                    <div class="stat-label">总查看次数</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['unique_users']; ?></div>
                    <div class="stat-label">独立访客</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['today_views']; ?></div>
                    <div class="stat-label">今日查看</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['week_views']; ?></div>
                    <div class="stat-label">本周查看</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['month_views']; ?></div>
                    <div class="stat-label">本月查看</div>
                </div>
            </div>
            
            <!-- 筛选器 -->
            <div class="card">
                <div class="card-header">
                    <h2>筛选条件</h2>
                </div>
                <div class="card-body">
                    <form method="GET" class="filter-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_from">开始日期</label>
                                <input type="date" id="date_from" name="date_from" 
                                       value="<?php echo h($dateFrom); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="date_to">结束日期</label>
                                <input type="date" id="date_to" name="date_to" 
                                       value="<?php echo h($dateTo); ?>">
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">筛选</button>
                                <a href="view_records.php" class="btn btn-secondary">重置</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 查看记录列表 -->
            <div class="card">
                <div class="card-header">
                    <h2>查看记录 (共 <?php echo $total; ?> 条)</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($viewRecords)): ?>
                        <p class="no-data">暂无查看记录</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>查看时间</th>
                                        <th>用户姓名</th>
                                        <th>用户邮箱</th>
                                        <th>查看日期</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($viewRecords as $record): ?>
                                        <tr>
                                            <td><?php echo date('H:i:s', strtotime($record['viewed_at'])); ?></td>
                                            <td><?php echo h($record['user_name'] ?? '未知用户'); ?></td>
                                            <td><?php echo h($record['user_email'] ?? '-'); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($record['viewed_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- 分页 -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" 
                                       class="btn btn-secondary">上一页</a>
                                <?php endif; ?>
                                
                                <div class="page-numbers">
                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($totalPages, $page + 2);
                                    
                                    for ($i = $start; $i <= $end; $i++):
                                    ?>
                                        <a href="?page=<?php echo $i; ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" 
                                           class="page-number <?php echo $i === $page ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                </div>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" 
                                       class="btn btn-secondary">下一页</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="reader-footer">
        <div class="container">
            <p>&copy; 2024 塔罗师展示平台. 保留所有权利.</p>
        </div>
    </footer>
</body>
</html>
