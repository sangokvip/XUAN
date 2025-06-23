<?php
session_start();
require_once '../config/config.php';
require_once '../includes/TataCoinManager.php';
require_once '../includes/MessageManager.php';

// 检查用户登录
requireLogin('../auth/login.php');

$db = Database::getInstance();
$tataCoinManager = new TataCoinManager();
$messageManager = new MessageManager();

$userId = $_SESSION['user_id'];
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

if (!$user) {
    logout();
    redirect('../auth/login.php');
}

// 获取用户的Tata Coin余额
$tataCoinBalance = $tataCoinManager->getBalance($userId, 'user');

// 获取最近的交易记录
$recentTransactions = $tataCoinManager->getTransactionHistory($userId, 'user', 5);

// 获取最近的浏览记录
$recentBrowseHistory = $tataCoinManager->getBrowseHistory($userId, 5);

// 获取未读消息数量
$unreadMessageCount = 0;
try {
    if ($messageManager->isInstalled()) {
        $unreadMessageCount = $messageManager->getUnreadCount($userId, 'user');
    }
} catch (Exception $e) {
    // 忽略错误
}

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
        .user-center {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        
        .user-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .user-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 30px;
            position: relative;
            z-index: 1;
        }
        
        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
        }
        
        .user-details h1 {
            margin: 0 0 10px 0;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .user-details p {
            margin: 5px 0;
            opacity: 0.9;
        }
        
        .tata-coin-display {
            background: rgba(255, 255, 255, 0.2);
            padding: 15px 20px;
            border-radius: 15px;
            margin-top: 20px;
            backdrop-filter: blur(10px);
        }
        
        .tata-coin-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffd700;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .card-icon.profile {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        }

        .card-icon.transactions {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        }

        .card-icon.history {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);
        }

        .card-icon.messages {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            color: #374151;
        }
        
        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: white;
            margin: 0;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        
        .quick-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .transaction-item, .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .transaction-item:last-child, .history-item:last-child {
            border-bottom: none;
        }
        
        .transaction-info, .history-info {
            flex: 1;
        }
        
        .transaction-desc, .history-desc {
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .transaction-time, .history-time {
            font-size: 0.85rem;
            color: #64748b;
        }
        
        .transaction-amount {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .amount-positive {
            color: #10b981;
        }
        
        .amount-negative {
            color: #ef4444;
        }
        
        .empty-state {
            text-align: center;
            color: #64748b;
            padding: 40px 20px;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .unread-badge {
            background: #ef4444;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 8px;
            font-weight: 600;
        }

        .unread-count {
            color: #ef4444;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* 交易记录网格布局 */
        .transactions-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin: 20px 0;
        }

        .transaction-card {
            display: flex;
            align-items: center;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .transaction-icon {
            font-size: 1.5rem;
            margin-right: 15px;
        }

        .transaction-details {
            flex: 1;
        }

        .transaction-desc {
            color: white;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .transaction-time {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.8rem;
        }

        /* 浏览记录网格布局 */
        .history-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin: 20px 0;
        }

        .history-card {
            display: flex;
            align-items: center;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .history-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .history-details {
            flex: 1;
        }

        .history-name {
            color: white;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .history-time {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.8rem;
        }

        .history-link {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .history-link:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        
        @media (max-width: 768px) {
            .user-center {
                padding: 15px;
            }
            
            .user-header {
                padding: 25px 20px;
            }
            
            .user-info {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .dashboard-card {
                padding: 20px;
            }
            
            .quick-actions {
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
    
    <div class="user-center">
        <!-- 用户信息头部 -->
        <div class="user-header">
            <div class="user-info">
                <img src="../<?php echo h($user['avatar'] ?: 'img/nm.jpg'); ?>" alt="头像" class="user-avatar">
                <div class="user-details">
                    <h1>欢迎回来，<?php echo h($user['full_name']); ?>！</h1>
                    <p>📧 <?php echo h($user['email']); ?></p>
                    <p>📱 <?php echo h($user['phone'] ?: '未设置'); ?></p>
                    <p>🎂 注册时间：<?php echo date('Y年m月d日', strtotime($user['created_at'])); ?></p>
                </div>
            </div>
            
            <div class="tata-coin-display">
                <div>💰 我的Tata Coin</div>
                <div class="tata-coin-amount"><?php echo number_format($tataCoinBalance); ?> 枚</div>
            </div>
        </div>
        
        <!-- 功能面板 -->
        <div class="dashboard-grid">
            <!-- 个人资料管理 -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon profile">👤</div>
                    <h3 class="card-title">个人资料</h3>
                </div>
                <div class="quick-actions">
                    <a href="profile.php" class="action-btn">
                        ✏️ 编辑资料
                    </a>
                    <a href="change_password.php" class="action-btn">
                        🔐 修改密码
                    </a>
                    <a href="upload_avatar.php" class="action-btn">
                        📷 更换头像
                    </a>
                </div>
            </div>

            <!-- Tata Coin管理 -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon coin">💰</div>
                    <h3 class="card-title">Tata Coin管理</h3>
                </div>
                <div class="balance-info">
                    <div class="balance-amount"><?php echo number_format($tataCoinBalance); ?></div>
                    <div class="balance-label">枚</div>
                </div>
                <div class="quick-actions">
                    <a href="transactions.php" class="action-btn">
                        📊 交易记录
                    </a>
                    <a href="tata_coin_guide.php" class="action-btn">
                        💡 使用说明
                    </a>
                    <a href="purchases.php" class="action-btn">
                        🛒 我的购买
                    </a>
                </div>
            </div>
            
            <!-- 最近交易 -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon transactions">💳</div>
                    <h3 class="card-title">最近交易</h3>
                </div>
                <?php if (empty($recentTransactions)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">💸</div>
                        <p>暂无交易记录</p>
                    </div>
                <?php else: ?>
                    <div class="transactions-grid">
                        <?php foreach (array_slice($recentTransactions, 0, 3) as $transaction): ?>
                            <div class="transaction-card">
                                <div class="transaction-icon">
                                    <?php echo $transaction['amount'] > 0 ? '💰' : '💸'; ?>
                                </div>
                                <div class="transaction-details">
                                    <div class="transaction-desc"><?php echo h(mb_substr($transaction['description'], 0, 20)); ?>...</div>
                                    <div class="transaction-time"><?php echo date('m-d H:i', strtotime($transaction['created_at'])); ?></div>
                                </div>
                                <div class="transaction-amount <?php echo $transaction['amount'] > 0 ? 'amount-positive' : 'amount-negative'; ?>">
                                    <?php echo $transaction['amount'] > 0 ? '+' : ''; ?><?php echo $transaction['amount']; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="transactions.php" class="action-btn">查看全部交易</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- 浏览记录 -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon history">📖</div>
                    <h3 class="card-title">浏览记录</h3>
                </div>
                <?php if (empty($recentBrowseHistory)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">👁️</div>
                        <p>暂无浏览记录</p>
                        <a href="../readers.php" class="action-btn" style="margin-top: 15px;">去看看塔罗师</a>
                    </div>
                <?php else: ?>
                    <div class="history-grid">
                        <?php foreach (array_slice($recentBrowseHistory, 0, 3) as $history): ?>
                            <div class="history-card">
                                <img src="../<?php echo h($history['photo_circle'] ?: ($history['photo'] ?: 'img/tm.jpg')); ?>"
                                     alt="<?php echo h($history['full_name']); ?>"
                                     class="history-avatar">
                                <div class="history-details">
                                    <div class="history-name">
                                        <?php echo h($history['full_name']); ?>
                                        <?php if ($history['browse_type'] === 'paid'): ?>
                                            <span style="color: #f59e0b;">💰</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="history-time"><?php echo date('m-d H:i', strtotime($history['created_at'])); ?></div>
                                </div>
                                <a href="../reader.php?id=<?php echo $history['reader_id']; ?>" class="history-link">查看</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="browse_history.php" class="action-btn">查看全部记录</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 消息通知 -->
            <?php if ($messageManager->isInstalled()): ?>
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon messages">📢</div>
                    <h3 class="card-title">
                        消息通知
                        <?php if ($unreadMessageCount > 0): ?>
                            <span class="unread-badge"><?php echo $unreadMessageCount; ?></span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="quick-actions">
                    <a href="messages.php" class="action-btn">
                        📬 查看消息
                        <?php if ($unreadMessageCount > 0): ?>
                            <span class="unread-count">(<?php echo $unreadMessageCount; ?>条未读)</span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- 快速导航 -->
        <div style="text-align: center; margin-top: 40px;">
            <a href="../index.php" class="action-btn">🏠 返回首页</a>
            <a href="../readers.php" class="action-btn">🔮 浏览塔罗师</a>
            <a href="../auth/logout.php" class="action-btn" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">🚪 退出登录</a>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
