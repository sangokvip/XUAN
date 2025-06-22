<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$db = Database::getInstance();

// 获取基础统计数据
$stats = [];

// 用户统计
$userStats = $db->fetchAll("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
        SUM(CASE WHEN WEEK(created_at) = WEEK(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as this_week,
        SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as this_month
    FROM users
");
$stats['users'] = $userStats[0];

// 塔罗师统计
$readerStats = $db->fetchAll("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
        SUM(CASE WHEN WEEK(created_at) = WEEK(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as this_week,
        SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as this_month
    FROM readers
");
$stats['readers'] = $readerStats[0];

// 查看统计
$viewStats = $db->fetchAll("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN DATE(viewed_at) = CURDATE() THEN 1 ELSE 0 END) as today,
        SUM(CASE WHEN WEEK(viewed_at) = WEEK(CURDATE()) AND YEAR(viewed_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as this_week,
        SUM(CASE WHEN MONTH(viewed_at) = MONTH(CURDATE()) AND YEAR(viewed_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as this_month
    FROM contact_views
");
$stats['views'] = $viewStats[0];

// 注册链接统计
$linkStats = $db->fetchAll("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_used = 1 THEN 1 ELSE 0 END) as used,
        SUM(CASE WHEN expires_at > NOW() AND is_used = 0 THEN 1 ELSE 0 END) as active
    FROM reader_registration_links
");
$stats['links'] = $linkStats[0];

// 最近7天的注册趋势
$dailyRegistrations = $db->fetchAll("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as user_count,
        (SELECT COUNT(*) FROM readers WHERE DATE(created_at) = DATE(u.created_at)) as reader_count
    FROM users u
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC
");

// 最受欢迎的塔罗师
$popularReaders = $db->fetchAll("
    SELECT 
        r.full_name,
        r.email,
        r.specialties,
        COUNT(cv.id) as view_count,
        r.is_featured
    FROM readers r
    LEFT JOIN contact_views cv ON r.id = cv.reader_id
    WHERE r.is_active = 1
    GROUP BY r.id
    ORDER BY view_count DESC
    LIMIT 10
");

// 最活跃的用户
$activeUsers = $db->fetchAll("
    SELECT 
        u.full_name,
        u.email,
        COUNT(cv.id) as view_count,
        MAX(cv.viewed_at) as last_view
    FROM users u
    LEFT JOIN contact_views cv ON u.id = cv.user_id
    WHERE u.is_active = 1
    GROUP BY u.id
    HAVING view_count > 0
    ORDER BY view_count DESC
    LIMIT 10
");

// 登录尝试统计
$loginStats = $db->fetchAll("
    SELECT 
        COUNT(*) as total_attempts,
        SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN DATE(attempted_at) = CURDATE() THEN 1 ELSE 0 END) as today_attempts
    FROM login_attempts
    WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stats['logins'] = $loginStats[0] ?? ['total_attempts' => 0, 'successful' => 0, 'failed' => 0, 'today_attempts' => 0];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据统计 - 管理后台</title>
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
            <h1>数据统计</h1>
            
            <!-- 总体统计 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['users']['total']; ?></div>
                    <div class="stat-label">总用户数</div>
                    <div class="stat-change">活跃: <?php echo $stats['users']['active']; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['readers']['total']; ?></div>
                    <div class="stat-label">总塔罗师数</div>
                    <div class="stat-change">推荐: <?php echo $stats['readers']['featured']; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['views']['total']; ?></div>
                    <div class="stat-label">总查看次数</div>
                    <div class="stat-change">本月: <?php echo $stats['views']['this_month']; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['links']['used']; ?>/<?php echo $stats['links']['total']; ?></div>
                    <div class="stat-label">注册链接使用</div>
                    <div class="stat-change">活跃: <?php echo $stats['links']['active']; ?></div>
                </div>
            </div>
            
            <!-- 时间趋势 -->
            <div class="dashboard-grid">
                <!-- 注册趋势 -->
                <div class="card">
                    <div class="card-header">
                        <h2>最近7天注册趋势</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($dailyRegistrations)): ?>
                            <p class="no-data">暂无数据</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>日期</th>
                                            <th>用户注册</th>
                                            <th>塔罗师注册</th>
                                            <th>总计</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dailyRegistrations as $day): ?>
                                            <tr>
                                                <td><?php echo $day['date']; ?></td>
                                                <td><?php echo $day['user_count']; ?></td>
                                                <td><?php echo $day['reader_count']; ?></td>
                                                <td><?php echo $day['user_count'] + $day['reader_count']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 登录统计 -->
                <div class="card">
                    <div class="card-header">
                        <h2>登录统计（最近7天）</h2>
                    </div>
                    <div class="card-body">
                        <div class="stat-item">
                            <div class="setting-label">总登录尝试</div>
                            <div class="setting-description"><?php echo $stats['logins']['total_attempts']; ?> 次</div>
                        </div>
                        <div class="stat-item">
                            <div class="setting-label">成功登录</div>
                            <div class="setting-description"><?php echo $stats['logins']['successful']; ?> 次</div>
                        </div>
                        <div class="stat-item">
                            <div class="setting-label">失败登录</div>
                            <div class="setting-description"><?php echo $stats['logins']['failed']; ?> 次</div>
                        </div>
                        <div class="stat-item">
                            <div class="setting-label">今日登录</div>
                            <div class="setting-description"><?php echo $stats['logins']['today_attempts']; ?> 次</div>
                        </div>
                    </div>
                </div>
                
                <!-- 最受欢迎的塔罗师 -->
                <div class="card">
                    <div class="card-header">
                        <h2>最受欢迎的塔罗师</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($popularReaders)): ?>
                            <p class="no-data">暂无数据</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>姓名</th>
                                            <th>擅长方向</th>
                                            <th>查看次数</th>
                                            <th>状态</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($popularReaders as $reader): ?>
                                            <tr>
                                                <td><?php echo h($reader['full_name']); ?></td>
                                                <td><?php echo h(mb_substr($reader['specialties'] ?? '', 0, 20)); ?></td>
                                                <td><?php echo $reader['view_count']; ?></td>
                                                <td>
                                                    <?php if ($reader['is_featured']): ?>
                                                        <span class="status-active">推荐</span>
                                                    <?php else: ?>
                                                        <span class="status-used">普通</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 最活跃的用户 -->
                <div class="card">
                    <div class="card-header">
                        <h2>最活跃的用户</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activeUsers)): ?>
                            <p class="no-data">暂无数据</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>姓名</th>
                                            <th>邮箱</th>
                                            <th>查看次数</th>
                                            <th>最后查看</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activeUsers as $user): ?>
                                            <tr>
                                                <td><?php echo h($user['full_name']); ?></td>
                                                <td><?php echo h($user['email']); ?></td>
                                                <td><?php echo $user['view_count']; ?></td>
                                                <td><?php echo $user['last_view'] ? date('m-d H:i', strtotime($user['last_view'])) : '-'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- 详细统计 -->
            <div class="settings-section">
                <h2>详细统计信息</h2>
                
                <div class="setting-item">
                    <div class="setting-label">今日新增</div>
                    <div class="setting-description">
                        用户: <?php echo $stats['users']['today']; ?> | 
                        塔罗师: <?php echo $stats['readers']['today']; ?> | 
                        查看: <?php echo $stats['views']['today']; ?>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">本周新增</div>
                    <div class="setting-description">
                        用户: <?php echo $stats['users']['this_week']; ?> | 
                        塔罗师: <?php echo $stats['readers']['this_week']; ?> | 
                        查看: <?php echo $stats['views']['this_week']; ?>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">本月新增</div>
                    <div class="setting-description">
                        用户: <?php echo $stats['users']['this_month']; ?> | 
                        塔罗师: <?php echo $stats['readers']['this_month']; ?> | 
                        查看: <?php echo $stats['views']['this_month']; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
