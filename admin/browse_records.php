<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin();

$db = Database::getInstance();

// 获取分页参数
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// 获取筛选参数
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$userId = $_GET['user_id'] ?? '';

// 构建查询条件
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($dateFrom)) {
    $whereClause .= " AND DATE(br.created_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereClause .= " AND DATE(br.created_at) <= ?";
    $params[] = $dateTo;
}

if (!empty($userId)) {
    $whereClause .= " AND br.user_id = ?";
    $params[] = $userId;
}

// 获取浏览记录
$sql = "SELECT br.*, u.username,
        CASE
            WHEN br.browse_type = 'paid' THEN CONCAT('付费查看占卜师: ', r.full_name)
            WHEN br.browse_type = 'free' THEN CONCAT('免费浏览占卜师: ', r.full_name)
            ELSE br.browse_type
        END as browse_description,
        r.full_name as reader_name
        FROM user_browse_history br
        LEFT JOIN users u ON br.user_id = u.id
        LEFT JOIN readers r ON br.reader_id = r.id
        {$whereClause}
        ORDER BY br.created_at DESC
        LIMIT {$limit} OFFSET {$offset}";

$records = $db->fetchAll($sql, $params);

// 获取总记录数
$countSql = "SELECT COUNT(*) as total FROM user_browse_history br {$whereClause}";
$totalResult = $db->fetchOne($countSql, $params);
$totalRecords = $totalResult['total'];
$totalPages = ceil($totalRecords / $limit);

// 获取统计数据
$today = date('Y-m-d');

// 今日统计
$todayStats = $db->fetchOne("
    SELECT
        COUNT(*) as total_browses,
        COUNT(DISTINCT user_id) as unique_users,
        SUM(CASE WHEN browse_type = 'free' THEN 1 ELSE 0 END) as free_views,
        SUM(CASE WHEN browse_type = 'paid' THEN 1 ELSE 0 END) as paid_views,
        SUM(cost) as total_coins_earned
    FROM user_browse_history
    WHERE DATE(created_at) = ?
", [$today]);

// 热门占卜师统计
$popularReaders = $db->fetchAll("
    SELECT r.full_name, COUNT(*) as view_count
    FROM user_browse_history br
    LEFT JOIN readers r ON br.reader_id = r.id
    WHERE DATE(br.created_at) = ?
    GROUP BY br.reader_id
    ORDER BY view_count DESC
    LIMIT 10
", [$today]);

// 活跃用户统计
$activeUsers = $db->fetchAll("
    SELECT u.username, COUNT(*) as browse_count, SUM(br.cost) as coins_spent
    FROM user_browse_history br
    LEFT JOIN users u ON br.user_id = u.id
    WHERE DATE(br.created_at) = ?
    GROUP BY br.user_id
    ORDER BY browse_count DESC
    LIMIT 10
", [$today]);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>浏览记录统计 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-sidebar">
            <?php include '../includes/admin_sidebar.php'; ?>
        </div>
        
        <div class="admin-content">
            <div class="page-header">
                <h1>浏览记录统计</h1>
            </div>
            
            <!-- 统计卡片 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👀</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $todayStats['total_browses'] ?? 0; ?></div>
                        <div class="stat-label">今日总浏览量</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $todayStats['unique_users'] ?? 0; ?></div>
                        <div class="stat-label">今日活跃用户</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👁️</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $todayStats['free_views'] ?? 0; ?></div>
                        <div class="stat-label">免费浏览次数</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🪙</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $todayStats['total_coins_earned'] ?? 0; ?></div>
                        <div class="stat-label">今日消费金币</div>
                    </div>
                </div>
            </div>

            <!-- 统计图表区域 -->
            <div class="stats-row">
                <!-- 热门占卜师 -->
                <div class="card half-width">
                    <div class="card-header">
                        <h3>今日热门占卜师</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($popularReaders)): ?>
                            <p class="no-data">暂无数据</p>
                        <?php else: ?>
                            <div class="popular-readers">
                                <?php foreach ($popularReaders as $reader): ?>
                                    <div class="reader-item">
                                        <div class="reader-name"><?php echo h($reader['full_name'] ?? '未知占卜师'); ?></div>
                                        <div class="reader-count"><?php echo $reader['view_count']; ?>次浏览</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 活跃用户 -->
                <div class="card half-width">
                    <div class="card-header">
                        <h3>今日活跃用户</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activeUsers)): ?>
                            <p class="no-data">暂无数据</p>
                        <?php else: ?>
                            <div class="active-users">
                                <?php foreach ($activeUsers as $user): ?>
                                    <div class="user-item">
                                        <div class="user-name"><?php echo h($user['username'] ?? '匿名用户'); ?></div>
                                        <div class="user-stats">
                                            <span class="browse-count"><?php echo $user['browse_count']; ?>次浏览</span>
                                            <span class="coins-spent">-<?php echo $user['coins_spent']; ?>金币</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 筛选表单 -->
            <div class="card">
                <div class="card-header">
                    <h2>筛选条件</h2>
                </div>
                <div class="card-body">
                    <form method="GET" class="filter-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="user_id">用户ID</label>
                                <input type="number" id="user_id" name="user_id" value="<?php echo h($userId); ?>" placeholder="输入用户ID">
                            </div>
                            <div class="form-group">
                                <label for="date_from">开始日期</label>
                                <input type="date" id="date_from" name="date_from" value="<?php echo h($dateFrom); ?>">
                            </div>
                            <div class="form-group">
                                <label for="date_to">结束日期</label>
                                <input type="date" id="date_to" name="date_to" value="<?php echo h($dateTo); ?>">
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">筛选</button>
                                <a href="browse_records.php" class="btn btn-secondary">重置</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 浏览记录表格 -->
            <div class="card">
                <div class="card-header">
                    <h2>浏览记录详情</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($records)): ?>
                        <p class="no-data">没有找到浏览记录</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>用户</th>
                                        <th>浏览内容</th>
                                        <th>浏览时间</th>
                                        <th>停留时长</th>
                                        <th>获得金币</th>
                                        <th>IP地址</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($records as $record): ?>
                                        <tr>
                                            <td><?php echo h($record['username'] ?? '匿名用户'); ?></td>
                                            <td>
                                                <div class="browse-content">
                                                    <?php echo h($record['browse_description']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($record['created_at'])); ?></td>
                                            <td>
                                                <span class="no-duration">-</span>
                                            </td>
                                            <td>
                                                <?php if ($record['cost'] > 0): ?>
                                                    <span class="coins-spent">-<?php echo $record['cost']; ?></span>
                                                <?php else: ?>
                                                    <span class="no-coins">免费</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>-</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- 分页 -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php
                                    $params = $_GET;
                                    $params['page'] = $i;
                                    $url = '?' . http_build_query($params);
                                    ?>
                                    <a href="<?php echo $url; ?>" 
                                       class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .half-width {
            width: 100%;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            font-size: 2rem;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .popular-pages, .active-users {
            max-height: 300px;
            overflow-y: auto;
        }

        .page-item, .user-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .page-item:last-child, .user-item:last-child {
            border-bottom: none;
        }

        .page-url, .user-name {
            font-weight: 500;
            color: #333;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .page-count {
            color: #666;
            font-size: 0.9rem;
        }

        .user-stats {
            display: flex;
            gap: 10px;
            font-size: 0.8rem;
        }

        .browse-count {
            color: #2196f3;
        }

        .coins-earned {
            color: #ff9800;
            font-weight: 600;
        }

        .duration {
            color: #4caf50;
            font-weight: 500;
        }

        .no-duration, .no-coins {
            color: #999;
        }

        .browse-content {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .filter-form .form-row {
            display: flex;
            gap: 20px;
            align-items: end;
        }

        .filter-form .form-group {
            flex: 1;
        }

        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
