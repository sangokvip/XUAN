<?php
session_start();
require_once '../config/config.php';
require_once '../includes/TataCoinManager.php';
require_once '../includes/MessageManager.php';
require_once '../includes/CheckinManager.php';
require_once '../includes/BrowseRewardManager.php';

// 检查占卜师登录
requireReaderLogin();

$db = Database::getInstance();
$tataCoinManager = new TataCoinManager();
$messageManager = new MessageManager();
$checkinManager = new CheckinManager();
$browseRewardManager = new BrowseRewardManager();
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

// 获取签到统计
$checkinStats = $checkinManager->getCheckinStats($_SESSION['reader_id'], 'reader');

// 获取浏览奖励统计
$browseStats = $browseRewardManager->getBrowseStats($_SESSION['reader_id'], 'reader');

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
    <title>占卜师后台 - <?php echo h($reader['full_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/reader-new.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php include '../includes/reader_header.php'; ?>
    
    <!-- 移动端导航 -->
    <div class="mobile-nav">
        <?php include '../includes/reader_mobile_nav.php'; ?>
    </div>

    <div class="reader-container">
        <div class="reader-sidebar">
            <div class="reader-sidebar-header">
                <h3>占卜师后台</h3>
            </div>
            <div class="reader-sidebar-nav">
                <?php include '../includes/reader_sidebar.php'; ?>
            </div>
        </div>

        <div class="reader-content">
            <div class="reader-content-inner">
                <div class="page-title">
                    <h1>欢迎回来，<?php echo h($reader['full_name']); ?>！</h1>
                    <p>管理您的占卜师资料和服务</p>
                </div>
            
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

            <!-- 每日签到和浏览奖励 -->
            <div class="dashboard-grid">
                <!-- 每日签到 -->
                <div class="card">
                    <div class="card-header">
                        <h3>📅 每日签到</h3>
                    </div>
                    <div class="card-body" style="text-align: center;">
                        <button id="daily-checkin-btn" class="btn btn-primary <?php echo $checkinStats['checked_in_today'] ? 'checked-in' : ''; ?>"
                                <?php echo $checkinStats['checked_in_today'] ? 'disabled' : ''; ?>>
                            <?php echo $checkinStats['checked_in_today'] ? '今日已签到' : '每日签到'; ?>
                        </button>

                        <div style="margin-top: 15px;">
                            <div id="checkin-streak" style="font-size: 16px; color: #333;">
                                <?php if ($checkinStats['consecutive_days'] > 0): ?>
                                    连续签到 <?php echo $checkinStats['consecutive_days']; ?> 天
                                <?php else: ?>
                                    开始您的签到之旅
                                <?php endif; ?>
                            </div>
                            <div style="margin-top: 10px; font-size: 12px; color: #666;">
                                连续签到7天可获得57个Tata Coin
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 浏览奖励统计 -->
                <div class="card">
                    <div class="card-header">
                        <h3>🌐 浏览奖励</h3>
                    </div>
                    <div class="card-body">
                        <div class="browse-stats">
                            <div class="stat-row">
                                <span>今日获得：</span>
                                <span id="browse-reward-today"><?php echo $browseStats['today_rewards']; ?> 个</span>
                            </div>
                            <div class="stat-row">
                                <span>剩余奖励：</span>
                                <span id="browse-remaining"><?php echo $browseStats['today_remaining']; ?> 个</span>
                            </div>
                            <div class="stat-row">
                                <span>浏览页面：</span>
                                <span><?php echo $browseStats['today_pages']; ?> 个</span>
                            </div>
                        </div>

                        <div style="margin-top: 15px;">
                            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #666;">
                                <span>今日进度</span>
                                <span><?php echo $browseStats['today_rewards']; ?>/<?php echo $browseStats['max_daily_rewards']; ?></span>
                            </div>
                            <div style="background: #e0e0e0; border-radius: 10px; height: 8px; margin: 5px 0; overflow: hidden;">
                                <div style="background: #4CAF50; height: 100%; width: <?php echo min(100, ($browseStats['today_rewards'] / $browseStats['max_daily_rewards']) * 100); ?>%; transition: width 0.3s;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 个人信息概览 -->
            <div class="card">
                <div class="card-header">
                    <h2>个人信息概览</h2>
                    <div class="card-header-actions">
                        <a href="../reader.php?id=<?php echo $_SESSION['reader_id']; ?>"
                           class="btn btn-primary" target="_blank">
                            <span class="btn-icon">🔍</span>
                            查看我的页面
                        </a>
                        <a href="profile.php" class="btn btn-secondary">编辑资料</a>
                    </div>
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
    </div>

    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .btn.checked-in {
            background: #2196F3 !important;
            cursor: not-allowed;
        }

        .browse-stats {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        // 设置全局变量供JavaScript使用
        window.SITE_URL = '<?php echo SITE_URL; ?>';
        window.USER_ID = <?php echo $_SESSION['reader_id']; ?>;
        window.USER_TYPE = 'reader';

        // 签到功能
        document.addEventListener('DOMContentLoaded', function() {
            const checkinBtn = document.getElementById('daily-checkin-btn');

            if (checkinBtn && !checkinBtn.disabled) {
                checkinBtn.addEventListener('click', function() {
                    performCheckin();
                });
            }
        });

        async function performCheckin() {
            const checkinBtn = document.getElementById('daily-checkin-btn');
            const originalText = checkinBtn.textContent;

            checkinBtn.disabled = true;
            checkinBtn.textContent = '签到中...';

            try {
                console.log('开始签到请求...');
                const response = await fetch('../api/checkin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });

                console.log('签到响应状态:', response.status);
                console.log('签到响应头:', response.headers);

                const responseText = await response.text();
                console.log('签到响应原始内容:', responseText);

                let result;
                try {
                    result = JSON.parse(responseText);
                    console.log('签到解析结果:', result);
                } catch (parseError) {
                    console.error('JSON解析失败:', parseError);
                    throw new Error('服务器返回了无效的JSON: ' + responseText.substring(0, 100));
                }

                if (result.success) {
                    // 更新界面
                    checkinBtn.textContent = '今日已签到';
                    checkinBtn.classList.add('checked-in');

                    // 更新连续签到天数
                    const streakElement = document.getElementById('checkin-streak');
                    if (streakElement) {
                        streakElement.textContent = `连续签到 ${result.consecutive_days} 天`;
                    }

                    // 显示成功消息
                    showNotification(`签到成功！获得 ${result.reward} 个 Tata Coin`, 'success');

                    // 刷新页面以更新余额
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    checkinBtn.textContent = originalText;
                    checkinBtn.disabled = false;
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                console.error('签到请求失败:', error);
                checkinBtn.textContent = originalText;
                checkinBtn.disabled = false;
                showNotification('签到失败：' + error.message, 'error');
            }
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;

            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 5px;
                color: white;
                font-weight: 500;
                z-index: 10000;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease;
                background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.opacity = '1';
                notification.style.transform = 'translateX(0)';
            }, 100);

            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

    </script>
    <script src="../assets/js/browse-reward.js"></script>

    <footer class="reader-footer">
        <div class="container">
            <p>&copy; 2024 占卜师展示平台. 保留所有权利.</p>
        </div>
    </footer>
</body>
</html>
