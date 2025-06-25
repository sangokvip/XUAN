<?php
session_start();
require_once '../config/config.php';
require_once '../includes/TataCoinManager.php';

// 检查用户登录
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'user') {
    header('Location: ../auth/login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$tataCoinManager = new TataCoinManager();

// 获取用户信息
$db = Database::getInstance();
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

// 获取余额和等级信息
$balance = $tataCoinManager->getBalance($userId, 'user');
$levelInfo = $tataCoinManager->getUserLevel($userId, 'user');
$dailyLimit = $tataCoinManager->getDailyEarningsLimit($userId, 'user');

// 获取今日统计
$today = date('Y-m-d');
$todayCheckIn = $db->fetchOne(
    "SELECT * FROM daily_check_ins WHERE user_id = ? AND check_in_date = ?",
    [$userId, $today]
);

$todayBrowseCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM page_browse_rewards 
     WHERE user_id = ? AND DATE(created_at) = ?",
    [$userId, $today]
)['count'];

// 获取最近交易记录
$recentTransactions = $tataCoinManager->getTransactionHistory($userId, 'user', 10);

// 获取连续签到天数
$lastCheckIn = $db->fetchOne(
    "SELECT * FROM daily_check_ins WHERE user_id = ? ORDER BY check_in_date DESC LIMIT 1",
    [$userId]
);

$consecutiveDays = 0;
if ($lastCheckIn) {
    if ($todayCheckIn) {
        $consecutiveDays = $todayCheckIn['consecutive_days'];
    } else {
        $lastDate = new DateTime($lastCheckIn['check_in_date']);
        $todayDate = new DateTime($today);
        $daysDiff = $todayDate->diff($lastDate)->days;
        
        if ($daysDiff == 1) {
            $consecutiveDays = $lastCheckIn['consecutive_days'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tata Coin 中心 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/common.css">
    <style>
        .coin-center {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .coin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .coin-balance {
            font-size: 48px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .level-info {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
        }
        
        .level-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .coin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .coin-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .checkin-section {
            text-align: center;
        }
        
        .checkin-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .checkin-btn:hover:not(:disabled) {
            background: #45a049;
            transform: translateY(-2px);
        }
        
        .checkin-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .checkin-btn.checked-in {
            background: #2196F3;
        }
        
        .streak-info {
            margin-top: 15px;
            font-size: 14px;
            color: #666;
        }
        
        .daily-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .progress-bar {
            background: #e0e0e0;
            border-radius: 10px;
            height: 8px;
            margin: 10px 0;
            overflow: hidden;
        }
        
        .progress-fill {
            background: #4CAF50;
            height: 100%;
            transition: width 0.3s;
        }
        
        .transaction-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .transaction-desc {
            flex: 1;
        }
        
        .transaction-amount {
            font-weight: bold;
        }
        
        .transaction-amount.positive {
            color: #4CAF50;
        }
        
        .transaction-amount.negative {
            color: #f44336;
        }
        
        .transaction-time {
            font-size: 12px;
            color: #999;
        }
        
        .earning-tips {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .earning-tips h4 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .earning-tips ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .earning-tips li {
            color: #856404;
            margin-bottom: 5px;
        }
        
        @media (max-width: 768px) {
            .coin-center {
                padding: 10px;
            }
            
            .coin-header {
                padding: 20px;
            }
            
            .coin-balance {
                font-size: 36px;
            }
            
            .level-info {
                flex-direction: column;
                gap: 10px;
            }
            
            .daily-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="coin-center">
        <!-- Tata Coin 余额展示 -->
        <div class="coin-header">
            <h1>💰 Tata Coin 中心</h1>
            <div class="coin-balance tata-coin-balance"><?php echo $balance; ?></div>
            <p>当前余额</p>
            
            <div class="level-info">
                <div class="level-badge">
                    <span id="user-level">Lv.<?php echo $levelInfo['level']; ?></span>
                    <span id="user-level-name"><?php echo $levelInfo['level_name']; ?></span>
                </div>
                <?php if ($levelInfo['discount_rate'] > 0): ?>
                    <div class="level-badge" id="user-discount">
                        享受<?php echo $levelInfo['discount_rate']; ?>%折扣
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="coin-grid">
            <!-- 每日签到 -->
            <div class="coin-card">
                <div class="card-title">📅 每日签到</div>
                <div class="checkin-section">
                    <button id="daily-checkin-btn" class="checkin-btn">
                        <?php echo $todayCheckIn ? '今日已签到' : '每日签到'; ?>
                    </button>
                    
                    <div class="streak-info">
                        <div id="checkin-streak">
                            <?php if ($consecutiveDays > 0): ?>
                                连续签到 <?php echo $consecutiveDays; ?> 天
                            <?php else: ?>
                                开始您的签到之旅
                            <?php endif; ?>
                        </div>
                        <div style="margin-top: 10px; font-size: 12px;">
                            连续签到7天可获得57个Tata Coin
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 今日统计 -->
            <div class="coin-card">
                <div class="card-title">📊 今日统计</div>
                <div class="daily-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $todayCheckIn ? $todayCheckIn['reward_coins'] : 0; ?></div>
                        <div class="stat-label">签到奖励</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $todayBrowseCount; ?></div>
                        <div class="stat-label">浏览奖励</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $dailyLimit['today_earned']; ?></div>
                        <div class="stat-label">今日收益</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $dailyLimit['remaining']; ?></div>
                        <div class="stat-label">剩余额度</div>
                    </div>
                </div>
                
                <div style="margin-top: 15px;">
                    <div style="display: flex; justify-content: space-between; font-size: 12px; color: #666;">
                        <span>今日收益进度</span>
                        <span><?php echo $dailyLimit['today_earned']; ?>/<?php echo $dailyLimit['max_daily']; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo min(100, ($dailyLimit['today_earned'] / $dailyLimit['max_daily']) * 100); ?>%"></div>
                    </div>
                </div>
            </div>
            
            <!-- 等级进度 -->
            <div class="coin-card">
                <div class="card-title">🏆 等级进度</div>
                <div style="text-align: center;">
                    <div style="font-size: 24px; margin-bottom: 10px;">
                        <?php echo $levelInfo['level_name']; ?>
                        <?php
                        require_once '../includes/level_badge.php';
                        echo getUserLevelBadgeHTML($levelInfo['level'], $levelInfo['level_name'], 'large');
                        ?>
                    </div>
                    <div style="font-size: 14px; color: #666; margin-bottom: 15px;">
                        累计消费：<?php echo $levelInfo['total_spent']; ?> Tata Coin
                    </div>
                    
                    <?php if ($levelInfo['next_level_requirement']): ?>
                        <div style="font-size: 12px; color: #666;">
                            距离下一等级还需消费：<?php echo $levelInfo['next_level_requirement'] - $levelInfo['total_spent']; ?> Tata Coin
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, ($levelInfo['total_spent'] / $levelInfo['next_level_requirement']) * 100); ?>%"></div>
                        </div>
                    <?php else: ?>
                        <div style="font-size: 12px; color: #4CAF50;">🎉 已达到最高等级</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- 最近交易记录 -->
        <div class="coin-card">
            <div class="card-title">📋 最近交易记录</div>
            <div class="transaction-list">
                <?php if (empty($recentTransactions)): ?>
                    <div style="text-align: center; color: #999; padding: 20px;">
                        暂无交易记录
                    </div>
                <?php else: ?>
                    <?php foreach ($recentTransactions as $transaction): ?>
                        <div class="transaction-item">
                            <div class="transaction-desc">
                                <div><?php echo htmlspecialchars($transaction['description']); ?></div>
                                <div class="transaction-time"><?php echo date('m-d H:i', strtotime($transaction['created_at'])); ?></div>
                            </div>
                            <div class="transaction-amount <?php echo $transaction['amount'] > 0 ? 'positive' : 'negative'; ?>">
                                <?php echo $transaction['amount'] > 0 ? '+' : ''; ?><?php echo $transaction['amount']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center; margin-top: 15px;">
                <a href="transaction_history.php" style="color: #667eea; text-decoration: none;">查看完整记录 →</a>
            </div>
        </div>
        
        <!-- 获取Tata Coin的方法 -->
        <div class="earning-tips">
            <h4>💡 如何获得更多 Tata Coin？</h4>
            <ul>
                <li><strong>每日签到：</strong>连续签到7天可获得57个Tata Coin</li>
                <li><strong>浏览页面：</strong>每个页面停留5秒可获得1个Tata Coin（每日最多10个）</li>
                <li><strong>完善资料：</strong>完善头像、性别等个人信息可获得20个Tata Coin</li>
                <li><strong>邀请朋友：</strong>邀请朋友注册并首次消费可获得20个Tata Coin</li>
            </ul>
        </div>

        <!-- 等级说明 -->
        <?php
        outputLevelBadgeCSS();
        echo getLevelDescription('user');
        ?>

        <style>
        .level-description {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .level-description h4 {
            color: #333;
            margin-bottom: 15px;
        }

        .level-description ul {
            list-style: none;
            padding: 0;
        }

        .level-description li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }

        .level-description li:last-child {
            border-bottom: none;
        }

        .level-description small {
            color: #666;
            font-style: italic;
        }
        </style>
    </div>
    
    <script>
        // 设置全局变量供JavaScript使用
        window.SITE_URL = '<?php echo SITE_URL; ?>';
        window.USER_ID = <?php echo $userId; ?>;
        window.USER_TYPE = 'user';
    </script>
    <script src="../assets/js/tata-coin-system.js"></script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
