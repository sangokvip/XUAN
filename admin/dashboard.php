<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$db = Database::getInstance();

// 获取统计数据
$stats = [];

// 用户统计
$userStats = $db->fetchOne("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
$stats['total_users'] = $userStats['total'] ?? 0;

// 塔罗师统计
$readerStats = $db->fetchOne("SELECT COUNT(*) as total FROM readers WHERE is_active = 1");
$stats['total_readers'] = $readerStats['total'] ?? 0;

// 推荐塔罗师统计
$featuredStats = $db->fetchOne("SELECT COUNT(*) as total FROM readers WHERE is_featured = 1 AND is_active = 1");
$stats['featured_readers'] = $featuredStats['total'] ?? 0;

// 查看次数统计
$viewStats = $db->fetchOne("SELECT COUNT(*) as total FROM contact_views");
$stats['total_views'] = $viewStats['total'] ?? 0;

// 本月新注册用户
$monthlyUsers = $db->fetchOne(
    "SELECT COUNT(*) as monthly FROM users 
     WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())"
);
$stats['monthly_users'] = $monthlyUsers['monthly'] ?? 0;

// 本月新注册塔罗师
$monthlyReaders = $db->fetchOne(
    "SELECT COUNT(*) as monthly FROM readers 
     WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())"
);
$stats['monthly_readers'] = $monthlyReaders['monthly'] ?? 0;

// 最近注册的用户
$recentUsers = $db->fetchAll(
    "SELECT full_name, email, created_at FROM users 
     WHERE is_active = 1 
     ORDER BY created_at DESC 
     LIMIT 5"
);

// 最近注册的塔罗师
$recentReaders = $db->fetchAll(
    "SELECT full_name, email, experience_years, created_at FROM readers 
     WHERE is_active = 1 
     ORDER BY created_at DESC 
     LIMIT 5"
);

// 最活跃的塔罗师（按查看次数）
$popularReaders = $db->fetchAll(
    "SELECT r.full_name, r.email,
            COALESCE(r.view_count, (SELECT COUNT(*) FROM contact_views cv WHERE cv.reader_id = r.id)) as view_count
     FROM readers r
     WHERE r.is_active = 1
     ORDER BY view_count DESC
     LIMIT 5"
);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - <?php echo getSiteName(); ?></title>
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
            <h1>管理后台概览</h1>
            
            <!-- 统计数据 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">总用户数</div>
                    <div class="stat-change">本月新增: <?php echo $stats['monthly_users']; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_readers']; ?></div>
                    <div class="stat-label">总塔罗师数</div>
                    <div class="stat-change">本月新增: <?php echo $stats['monthly_readers']; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['featured_readers']; ?></div>
                    <div class="stat-label">推荐塔罗师</div>
                    <div class="stat-change">首页展示</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_views']; ?></div>
                    <div class="stat-label">总查看次数</div>
                    <div class="stat-change">联系方式查看</div>
                </div>
            </div>
            
            <!-- 快速操作 -->
            <div class="quick-actions">
                <h2>快速操作</h2>
                <div class="action-buttons">
                    <a href="generate_reader_link.php" class="btn btn-primary">生成塔罗师注册链接</a>
                    <a href="login_security.php" class="btn btn-warning">🔐 登录安全管理</a>
                    <a href="view_count_management.php" class="btn btn-info">📊 查看次数管理</a>
                    <a href="readers.php" class="btn btn-secondary">管理塔罗师</a>
                    <a href="users.php" class="btn btn-secondary">管理用户</a>
                    <a href="settings.php" class="btn btn-secondary">系统设置</a>
                </div>
            </div>
            
            <div class="dashboard-grid">
                <!-- 最近注册的用户 -->
                <div class="card">
                    <div class="card-header">
                        <h2>最近注册的用户</h2>
                        <a href="users.php" class="btn btn-secondary">查看全部</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentUsers)): ?>
                            <p class="no-data">暂无用户</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>姓名</th>
                                            <th>邮箱</th>
                                            <th>注册时间</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentUsers as $user): ?>
                                            <tr>
                                                <td><?php echo h($user['full_name']); ?></td>
                                                <td><?php echo h($user['email']); ?></td>
                                                <td><?php echo date('m-d H:i', strtotime($user['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 最近注册的塔罗师 -->
                <div class="card">
                    <div class="card-header">
                        <h2>最近注册的塔罗师</h2>
                        <a href="readers.php" class="btn btn-secondary">查看全部</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentReaders)): ?>
                            <p class="no-data">暂无塔罗师</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>姓名</th>
                                            <th>邮箱</th>
                                            <th>从业年数</th>
                                            <th>注册时间</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentReaders as $reader): ?>
                                            <tr>
                                                <td><?php echo h($reader['full_name']); ?></td>
                                                <td><?php echo h($reader['email']); ?></td>
                                                <td><?php echo h($reader['experience_years']); ?>年</td>
                                                <td><?php echo date('m-d H:i', strtotime($reader['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
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
                                            <th>邮箱</th>
                                            <th>查看次数</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($popularReaders as $reader): ?>
                                            <tr>
                                                <td><?php echo h($reader['full_name']); ?></td>
                                                <td><?php echo h($reader['email']); ?></td>
                                                <td><?php echo h($reader['view_count']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
