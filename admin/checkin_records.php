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
$userType = $_GET['user_type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// 构建查询条件
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($userType)) {
    $whereClause .= " AND user_type = ?";
    $params[] = $userType;
}

if (!empty($dateFrom)) {
    $whereClause .= " AND DATE(checkin_date) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereClause .= " AND DATE(checkin_date) <= ?";
    $params[] = $dateTo;
}

// 获取签到记录
$sql = "SELECT cr.*,
        CASE
            WHEN cr.user_type = 'user' THEN u.username
            WHEN cr.user_type = 'reader' THEN r.full_name
        END as user_name
        FROM daily_checkins cr
        LEFT JOIN users u ON cr.user_id = u.id AND cr.user_type = 'user'
        LEFT JOIN readers r ON cr.reader_id = r.id AND cr.user_type = 'reader'
        {$whereClause}
        ORDER BY cr.checkin_date DESC
        LIMIT {$limit} OFFSET {$offset}";

$records = $db->fetchAll($sql, $params);

// 获取总记录数
$countSql = "SELECT COUNT(*) as total FROM daily_checkins cr {$whereClause}";
$totalResult = $db->fetchOne($countSql, $params);
$totalRecords = $totalResult['total'];
$totalPages = ceil($totalRecords / $limit);

// 获取统计数据
$stats = [
    'today_total' => 0,
    'today_users' => 0,
    'today_readers' => 0,
    'total_coins_today' => 0
];

$today = date('Y-m-d');
$todayStats = $db->fetchAll("
    SELECT user_type, COUNT(*) as count, SUM(reward_amount) as total_coins
    FROM daily_checkins
    WHERE DATE(checkin_date) = ?
    GROUP BY user_type
", [$today]);

foreach ($todayStats as $stat) {
    if ($stat['user_type'] === 'user') {
        $stats['today_users'] = $stat['count'];
    } elseif ($stat['user_type'] === 'reader') {
        $stats['today_readers'] = $stat['count'];
    }
    $stats['total_coins_today'] += $stat['total_coins'];
}
$stats['today_total'] = $stats['today_users'] + $stats['today_readers'];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>签到记录管理 - 管理后台</title>
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
                <h1>签到记录管理</h1>
            </div>
            
            <!-- 统计卡片 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['today_total']; ?></div>
                        <div class="stat-label">今日签到总数</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['today_users']; ?></div>
                        <div class="stat-label">今日用户签到</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🔮</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['today_readers']; ?></div>
                        <div class="stat-label">今日占卜师签到</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🪙</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['total_coins_today']; ?></div>
                        <div class="stat-label">今日发放金币</div>
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
                                <label for="user_type">用户类型</label>
                                <select id="user_type" name="user_type">
                                    <option value="">全部</option>
                                    <option value="user" <?php echo $userType === 'user' ? 'selected' : ''; ?>>普通用户</option>
                                    <option value="reader" <?php echo $userType === 'reader' ? 'selected' : ''; ?>>占卜师</option>
                                </select>
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
                                <a href="checkin_records.php" class="btn btn-secondary">重置</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 签到记录表格 -->
            <div class="card">
                <div class="card-header">
                    <h2>签到记录</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($records)): ?>
                        <p class="no-data">没有找到签到记录</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>用户</th>
                                        <th>用户类型</th>
                                        <th>签到时间</th>
                                        <th>连续签到天数</th>
                                        <th>获得金币</th>
                                        <th>IP地址</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($records as $record): ?>
                                        <tr>
                                            <td><?php echo h($record['user_name'] ?? '未知用户'); ?></td>
                                            <td>
                                                <span class="user-type-badge <?php echo $record['user_type']; ?>">
                                                    <?php echo $record['user_type'] === 'user' ? '普通用户' : '占卜师'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($record['checkin_date'])); ?></td>
                                            <td>
                                                <span class="consecutive-days"><?php echo $record['consecutive_days']; ?>天</span>
                                            </td>
                                            <td>
                                                <span class="coins-earned">+<?php echo $record['reward_amount']; ?></span>
                                            </td>
                                            <td><?php echo h($record['ip_address'] ?? '-'); ?></td>
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

        .user-type-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .user-type-badge.user {
            background: #e3f2fd;
            color: #1976d2;
        }

        .user-type-badge.reader {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .consecutive-days {
            font-weight: 600;
            color: #4caf50;
        }

        .coins-earned {
            font-weight: 600;
            color: #ff9800;
        }

        .filter-form .form-row {
            display: flex;
            gap: 20px;
            align-items: end;
        }

        .filter-form .form-group {
            flex: 1;
        }
    </style>
</body>
</html>
