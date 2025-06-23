<?php
session_start();
require_once '../config/config.php';
require_once '../includes/TataCoinManager.php';
require_once '../includes/MessageManager.php';

// 检查塔罗师登录
requireReaderLogin();

$db = Database::getInstance();
$tataCoinManager = new TataCoinManager();
$messageManager = new MessageManager();
$reader = getReaderById($_SESSION['reader_id']);

// 获取统计数据
$stats = [];

// 查看次数统计
$viewStats = $db->fetchOne(
    "SELECT COUNT(*) as total_views FROM contact_views WHERE reader_id = ?",
    [$_SESSION['reader_id']]
);
$stats['total_views'] = $viewStats['total_views'] ?? 0;

// 本月查看次数
$monthlyViews = $db->fetchOne(
    "SELECT COUNT(*) as monthly_views FROM contact_views 
     WHERE reader_id = ? AND MONTH(viewed_at) = MONTH(CURRENT_DATE()) AND YEAR(viewed_at) = YEAR(CURRENT_DATE())",
    [$_SESSION['reader_id']]
);
$stats['monthly_views'] = $monthlyViews['monthly_views'] ?? 0;

// 获取Tata Coin余额和收益
$tataCoinBalance = 0;
$totalEarnings = 0;
try {
    if ($tataCoinManager->isInstalled()) {
        $tataCoinBalance = $tataCoinManager->getBalance($_SESSION['reader_id'], 'reader');
        $earningsData = $tataCoinManager->getReaderEarnings($_SESSION['reader_id']);
        $totalEarnings = $earningsData['total_earnings'] ?? 0;
    }
} catch (Exception $e) {
    // 忽略错误
}

// 获取未读消息数量
$unreadMessageCount = 0;
try {
    if ($messageManager->isInstalled()) {
        $unreadMessageCount = $messageManager->getUnreadCount($_SESSION['reader_id'], 'reader');
    }
} catch (Exception $e) {
    // 忽略错误
}

// 最近的查看记录
$recentViews = $db->fetchAll(
    "SELECT cv.viewed_at, u.full_name as user_name, u.email as user_email 
     FROM contact_views cv 
     JOIN users u ON cv.user_id = u.id 
     WHERE cv.reader_id = ? 
     ORDER BY cv.viewed_at DESC 
     LIMIT 10",
    [$_SESSION['reader_id']]
);

// 检查资料完整性
$profileCompleteness = [];
$profileCompleteness['basic_info'] = !empty($reader['full_name']) && !empty($reader['specialties']) && !empty($reader['description']);
$profileCompleteness['photo'] = !empty($reader['photo']);
$profileCompleteness['price_list'] = !empty($reader['price_list_image']);
$profileCompleteness['contact_info'] = !empty($reader['contact_info']);

$completenessScore = array_sum($profileCompleteness) / count($profileCompleteness) * 100;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>塔罗师后台 - <?php echo h($reader['full_name']); ?></title>
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
            <h1>欢迎回来，<?php echo h($reader['full_name']); ?>！</h1>
            
            <!-- 资料完整性提醒 -->
            <?php if ($completenessScore < 100): ?>
                <div class="alert alert-warning">
                    <h3>完善您的资料</h3>
                    <p>您的资料完整度为 <strong><?php echo round($completenessScore); ?>%</strong>，完善资料可以获得更多用户关注。</p>
                    <ul>
                        <?php if (!$profileCompleteness['basic_info']): ?>
                            <li><a href="profile.php">完善基本信息和个人简介</a></li>
                        <?php endif; ?>
                        <?php if (!$profileCompleteness['photo']): ?>
                            <li><a href="profile.php">上传个人照片</a></li>
                        <?php endif; ?>
                        <?php if (!$profileCompleteness['price_list']): ?>
                            <li><a href="profile.php">上传价格列表</a></li>
                        <?php endif; ?>
                        <?php if (!$profileCompleteness['contact_info']): ?>
                            <li><a href="profile.php">填写联系方式</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- 统计数据 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_views']; ?></div>
                    <div class="stat-label">总查看次数</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['monthly_views']; ?></div>
                    <div class="stat-label">本月查看次数</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($tataCoinBalance); ?></div>
                    <div class="stat-label">Tata Coin余额</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($totalEarnings); ?></div>
                    <div class="stat-label">累计收益</div>
                </div>

                <div class="stat-card">
                    <div class="stat-number"><?php echo round($completenessScore); ?>%</div>
                    <div class="stat-label">资料完整度</div>
                </div>

                <?php if ($messageManager->isInstalled()): ?>
                <div class="stat-card">
                    <div class="stat-number">
                        <?php echo $unreadMessageCount; ?>
                        <?php if ($unreadMessageCount > 0): ?>
                            <span class="unread-indicator">!</span>
                        <?php endif; ?>
                    </div>
                    <div class="stat-label">未读消息</div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 个人信息概览 -->
            <div class="card">
                <div class="card-header">
                    <h2>个人信息概览</h2>
                    <a href="profile.php" class="btn btn-secondary">编辑资料</a>
                </div>
                <div class="card-body">
                    <div class="profile-overview">
                        <?php if (!empty($reader['photo'])): ?>
                            <div class="profile-photo">
                                <?php
                                // 确保路径正确：如果路径不以../开头，则添加../
                                $photoPath = $reader['photo'];
                                if (!str_starts_with($photoPath, '../')) {
                                    $photoPath = '../' . $photoPath;
                                }
                                ?>
                                <img src="<?php echo h($photoPath); ?>" alt="个人照片">
                            </div>
                        <?php endif; ?>
                        
                        <div class="profile-info">
                            <h3><?php echo h($reader['full_name']); ?></h3>
                            <p><strong>从业年数：</strong><?php echo h($reader['experience_years']); ?>年</p>
                            <p><strong>擅长方向：</strong><?php echo h($reader['specialties'] ?: '未填写'); ?></p>
                            <p><strong>个人简介：</strong><?php echo h($reader['description'] ?: '未填写'); ?></p>
                            <p><strong>注册时间：</strong><?php echo date('Y-m-d', strtotime($reader['created_at'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 最近的查看记录 -->
            <div class="card">
                <div class="card-header">
                    <h2>最近的查看记录</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($recentViews)): ?>
                        <p class="no-data">暂无查看记录</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>用户姓名</th>
                                        <th>用户邮箱</th>
                                        <th>查看时间</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentViews as $view): ?>
                                        <tr>
                                            <td><?php echo h($view['user_name']); ?></td>
                                            <td><?php echo h($view['user_email']); ?></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($view['viewed_at'])); ?></td>
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

    <footer class="reader-footer">
        <div class="container">
            <p>&copy; 2024 塔罗师展示平台. 保留所有权利.</p>
        </div>
    </footer>
</body>
</html>
