<?php
session_start();
require_once '../config/config.php';
require_once '../includes/TataCoinManager.php';
require_once '../includes/MessageManager.php';
// CheckinManager 已简化，直接使用数据库查询

// 检查用户登录
requireLogin('../auth/login.php');

$db = Database::getInstance();
$tataCoinManager = new TataCoinManager();
$messageManager = new MessageManager();
// 直接查询签到统计

$userId = $_SESSION['user_id'];
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

if (!$user) {
    logout();
    redirect('../auth/login.php');
}

// 获取用户的Tata Coin余额
$tataCoinBalance = $tataCoinManager->getBalance($userId, 'user');

// 获取最近的交易记录
$recentTransactions = $tataCoinManager->getTransactionHistory($userId, 'user', 3);

// 获取最近的浏览记录
$recentBrowseHistory = $tataCoinManager->getBrowseHistory($userId, 3);

// 获取未读消息数量
$unreadMessageCount = 0;
try {
    if ($messageManager->isInstalled()) {
        $unreadMessageCount = $messageManager->getUnreadCount($userId, 'user');
    }
} catch (Exception $e) {
    // 忽略错误
}

// 获取签到统计
$today = date('Y-m-d');
$todayCheckin = $db->fetchOne(
    "SELECT consecutive_days FROM daily_checkins WHERE user_id = ? AND user_type = 'user' AND checkin_date = ?",
    [$userId, $today]
);

$checkinStats = [
    'checked_in_today' => !empty($todayCheckin),
    'consecutive_days' => $todayCheckin['consecutive_days'] ?? 0
];

$pageTitle = '用户中心';
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
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        /* 用户信息头部 */
        .user-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
        }
        
        .user-details h1 {
            margin: 0 0 5px 0;
            font-size: 1.6rem;
            font-weight: 700;
        }
        
        .user-meta {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
        }
        
        .tata-coin-display {
            background: rgba(255, 255, 255, 0.15);
            padding: 10px 15px;
            border-radius: 10px;
            margin-left: auto;
            backdrop-filter: blur(10px);
            text-align: center;
        }
        
        .tata-coin-amount {
            font-size: 1.4rem;
            font-weight: 700;
            color: #ffd700;
            margin: 0;
        }
        
        .tata-coin-label {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
        }

        /* 每日签到样式 */
        .daily-checkin-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .checkin-card {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .checkin-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .checkin-icon {
            font-size: 1.5rem;
        }

        .checkin-header h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .checkin-content {
            text-align: center;
        }

        .checkin-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            margin-bottom: 15px;
        }

        .checkin-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .checkin-btn:active:not(:disabled) {
            transform: translateY(0);
        }

        .checkin-btn.checked-in {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            box-shadow: 0 4px 15px rgba(107, 114, 128, 0.3);
            cursor: not-allowed;
        }

        .checkin-info {
            color: rgba(255, 255, 255, 0.9);
        }

        .streak-text {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .reward-hint {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        /* 移动端签到样式 */
        @media (max-width: 768px) {
            .daily-checkin-section {
                margin-top: 15px;
                padding-top: 15px;
            }

            .checkin-card {
                padding: 15px;
            }

            .checkin-header h3 {
                font-size: 1.1rem;
            }

            .checkin-btn {
                padding: 10px 25px;
                font-size: 0.9rem;
            }

            .streak-text {
                font-size: 1rem;
            }

            .reward-hint {
                font-size: 0.8rem;
            }
        }

        /* 横向卡片布局 */
        .dashboard-section {
            background: white;
            border-radius: 16px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%);
            color: white;
            padding: 15px 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-icon {
            font-size: 1.3rem;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }
        
        .section-content {
            padding: 20px 25px;
        }
        
        /* 按钮组 */
        .button-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .action-btn.secondary {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
        }
        
        .action-btn.secondary:hover {
            box-shadow: 0 6px 20px rgba(107, 114, 128, 0.3);
        }
        
        /* 列表项 */
        .list-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .list-item:last-child {
            border-bottom: none;
        }
        
        .list-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 12px;
            border: 2px solid #e5e7eb;
        }
        
        .list-content {
            flex: 1;
        }
        
        .list-title {
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 4px 0;
            font-size: 0.9rem;
        }
        
        .list-subtitle {
            color: #6b7280;
            font-size: 0.8rem;
            margin: 0;
        }
        
        .list-action {
            color: #667eea;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 6px;
            background: #f8fafc;
            transition: all 0.3s ease;
        }
        
        .list-action:hover {
            background: #667eea;
            color: white;
        }
        
        .amount-positive {
            color: #10b981;
            font-weight: 600;
        }
        
        .amount-negative {
            color: #ef4444;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            color: #6b7280;
            padding: 30px 20px;
        }
        
        .empty-state-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        /* 响应式 */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 20px 15px;
            }
            
            .user-info {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .tata-coin-display {
                margin-left: 0;
            }
            
            .section-content {
                padding: 15px 20px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .action-btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="dashboard-container">
        <!-- 用户信息头部 -->
        <div class="user-header">
            <div class="user-info">
                <?php
                $avatarPath = getUserAvatarUrl($user, '../');
                ?>
                <img src="<?php echo h($avatarPath); ?>"
                     alt="用户头像" class="user-avatar"
                     onerror="this.src='<?php echo ($user['gender'] === 'female') ? '../img/nf.jpg' : '../img/nm.jpg'; ?>'">
                <div class="user-details">
                    <h1><?php echo h($user['full_name']); ?></h1>
                    <div class="user-meta">
                        <?php echo h($user['email']); ?> | 
                        注册于 <?php echo date('Y年m月', strtotime($user['created_at'])); ?>
                    </div>
                </div>
                <div class="tata-coin-display">
                    <div class="tata-coin-amount"><?php echo number_format($tataCoinBalance); ?></div>
                    <div class="tata-coin-label">Tata Coin</div>
                </div>
            </div>

            <!-- 每日签到区域 -->
            <div class="daily-checkin-section">
                <div class="checkin-card">
                    <div class="checkin-header">
                        <span class="checkin-icon">📅</span>
                        <h3>每日签到</h3>
                    </div>
                    <div class="checkin-content">
                        <button id="daily-checkin-btn" class="checkin-btn <?php echo $checkinStats['checked_in_today'] ? 'checked-in' : ''; ?>"
                                <?php echo $checkinStats['checked_in_today'] ? 'disabled' : ''; ?>>
                            <?php echo $checkinStats['checked_in_today'] ? '今日已签到' : '每日签到'; ?>
                        </button>
                        <div class="checkin-info">
                            <div id="checkin-streak" class="streak-text">
                                <?php if ($checkinStats['consecutive_days'] > 0): ?>
                                    连续签到 <?php echo $checkinStats['consecutive_days']; ?> 天
                                <?php else: ?>
                                    开始您的签到之旅
                                <?php endif; ?>
                            </div>
                            <div class="reward-hint">连续签到7天可获得57个Tata Coin</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 个人资料管理 -->
        <div class="dashboard-section">
            <div class="section-header">
                <span class="section-icon">👤</span>
                <h3 class="section-title">个人资料</h3>
            </div>
            <div class="section-content">
                <div class="button-group">
                    <a href="profile.php" class="action-btn">
                        ✏️ 编辑资料
                    </a>
                    <a href="change_password.php" class="action-btn secondary">
                        🔒 修改密码
                    </a>
                    <a href="upload_avatar.php" class="action-btn secondary">
                        📷 更换头像
                    </a>
                    <?php if ($messageManager->isInstalled()): ?>
                    <a href="messages.php" class="action-btn secondary">
                        📬 查看消息
                        <?php if ($unreadMessageCount > 0): ?>
                            <span style="background: #ef4444; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px; margin-left: 5px;">
                                <?php echo $unreadMessageCount; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Tata Coin管理 -->
        <div class="dashboard-section">
            <div class="section-header">
                <span class="section-icon">💰</span>
                <h3 class="section-title">Tata Coin管理</h3>
            </div>
            <div class="section-content">
                <div class="button-group">
                    <a href="transactions.php" class="action-btn">
                        📊 交易记录
                    </a>
                    <a href="tata_coin_guide.php" class="action-btn secondary">
                        💡 使用说明
                    </a>
                    <a href="purchase.php" class="action-btn secondary">
                        🛒 我的购买
                    </a>
                </div>
            </div>
        </div>

        <!-- 最近交易 -->
        <div class="dashboard-section">
            <div class="section-header">
                <span class="section-icon">💳</span>
                <h3 class="section-title">最近交易</h3>
            </div>
            <div class="section-content">
                <?php if (empty($recentTransactions)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">💸</div>
                        <p>暂无交易记录</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentTransactions as $transaction): ?>
                        <div class="list-item">
                            <div class="list-content">
                                <div class="list-title"><?php echo h($transaction['description']); ?></div>
                                <div class="list-subtitle"><?php echo date('m-d H:i', strtotime($transaction['created_at'])); ?></div>
                            </div>
                            <div class="<?php echo $transaction['amount'] > 0 ? 'amount-positive' : 'amount-negative'; ?>">
                                <?php echo $transaction['amount'] > 0 ? '+' : ''; ?><?php echo $transaction['amount']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="transactions.php" class="action-btn secondary">查看全部交易</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 浏览记录 -->
        <div class="dashboard-section">
            <div class="section-header">
                <span class="section-icon">📖</span>
                <h3 class="section-title">浏览记录</h3>
            </div>
            <div class="section-content">
                <?php if (empty($recentBrowseHistory)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">👁️</div>
                        <p>暂无浏览记录</p>
                        <a href="../readers.php" class="action-btn" style="margin-top: 15px;">去看看塔罗师</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentBrowseHistory as $history): ?>
                        <div class="list-item">
                            <?php
                            $readerAvatar = getReaderPhotoUrl($history, true, '../');
                            ?>
                            <img src="<?php echo h($readerAvatar); ?>"
                                 alt="<?php echo h($history['full_name']); ?>"
                                 class="list-avatar"
                                 onerror="this.src='<?php echo ($history['gender'] === 'female') ? '../img/f1.jpg' : '../img/m1.jpg'; ?>'">
                            <div class="list-content">
                                <div class="list-title">
                                    <?php echo h($history['full_name']); ?>
                                    <?php if ($history['browse_type'] === 'paid'): ?>
                                        <span style="color: #f59e0b;">💰</span>
                                    <?php endif; ?>
                                </div>
                                <div class="list-subtitle"><?php echo date('m-d H:i', strtotime($history['created_at'])); ?></div>
                            </div>
                            <a href="../reader.php?id=<?php echo $history['reader_id']; ?>" class="list-action">查看</a>
                        </div>
                    <?php endforeach; ?>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="browse_history.php" class="action-btn secondary">查看全部记录</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>


        <!-- 快速导航 -->
        <div class="dashboard-section">
            <div class="section-header">
                <span class="section-icon">🚀</span>
                <h3 class="section-title">快速导航</h3>
            </div>
            <div class="section-content">
                <div class="button-group">
                    <a href="../index.php" class="action-btn">🏠 返回首页</a>
                    <a href="../readers.php" class="action-btn">🔮 浏览塔罗师</a>
                    <a href="../search.php" class="action-btn secondary">🔍 搜索塔罗师</a>
                    <a href="../auth/logout.php" class="action-btn" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">🚪 退出登录</a>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        // 每日签到功能
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
                    // 签到成功
                    checkinBtn.textContent = '今日已签到';
                    checkinBtn.classList.add('checked-in');
                    checkinBtn.disabled = true;

                    // 更新连续签到天数
                    const streakText = document.getElementById('checkin-streak');
                    if (streakText) {
                        streakText.textContent = `连续签到 ${result.consecutive_days} 天`;
                    }

                    // 显示成功消息
                    showNotification(result.message, 'success');

                    // 刷新页面以更新Tata币余额
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    // 签到失败
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

        // 通知函数
        function showNotification(message, type) {
            // 创建通知元素
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 600;
                z-index: 10000;
                max-width: 300px;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
                transform: translateX(100%);
                transition: transform 0.3s ease;
            `;

            if (type === 'success') {
                notification.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            } else {
                notification.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
            }

            notification.textContent = message;
            document.body.appendChild(notification);

            // 显示动画
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);

            // 自动隐藏
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // 绑定签到按钮事件
        document.addEventListener('DOMContentLoaded', function() {
            const checkinBtn = document.getElementById('daily-checkin-btn');
            if (checkinBtn && !checkinBtn.disabled) {
                checkinBtn.addEventListener('click', performCheckin);
            }
        });
    </script>
</body>
</html>
