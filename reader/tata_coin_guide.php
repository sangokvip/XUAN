<?php
session_start();
require_once '../config/config.php';
require_once '../includes/TataCoinManager.php';

// 检查塔罗师登录
requireReaderLogin();

$db = Database::getInstance();
$tataCoinManager = new TataCoinManager();

$readerId = $_SESSION['reader_id'];
$reader = getReaderById($readerId);

// 获取当前余额和收益统计
$currentBalance = 0;
$earningsData = [];
$recentTransactions = [];

try {
    if ($tataCoinManager->isInstalled()) {
        $currentBalance = $tataCoinManager->getBalance($readerId, 'reader');
        $earningsData = $tataCoinManager->getReaderEarnings($readerId);
        $recentTransactions = $tataCoinManager->getTransactionHistory($readerId, 'reader', 10);
    }
} catch (Exception $e) {
    // 忽略错误
}

// 获取系统设置
$featuredCost = $tataCoinManager->getSetting('featured_reader_cost', 30);
$normalCost = $tataCoinManager->getSetting('normal_reader_cost', 10);
$readerShareRate = $tataCoinManager->getSetting('reader_commission_rate', 50);

$pageTitle = 'Tata Coin详细说明';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - 塔罗师后台</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/reader-new.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .guide-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        
        .guide-header {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .guide-header h1 {
            margin: 0 0 15px 0;
            font-size: 2.5rem;
            font-weight: 700;
        }
        
        .balance-display {
            background: rgba(255, 255, 255, 0.2);
            padding: 20px;
            border-radius: 15px;
            margin-top: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }
        
        .balance-item {
            text-align: center;
        }
        
        .balance-amount {
            font-size: 1.8rem;
            font-weight: 700;
            color: #fbbf24;
            display: block;
        }
        
        .balance-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .guide-section {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0 0 25px 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
        
        .earnings-table {
            background: #f8fafc;
            border-radius: 15px;
            padding: 25px;
            margin-top: 25px;
        }
        
        .earnings-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .earnings-row:last-child {
            border-bottom: none;
        }
        
        .earnings-item {
            font-weight: 500;
            color: #374151;
        }
        
        .earnings-amount {
            font-weight: 700;
            color: #f59e0b;
            font-size: 1.1rem;
        }
        
        .share-explanation {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
        }
        
        .share-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .share-details {
            color: #92400e;
            line-height: 1.6;
        }
        
        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .transaction-info {
            flex: 1;
        }
        
        .transaction-desc {
            font-weight: 500;
            color: #374151;
            margin-bottom: 5px;
        }
        
        .transaction-time {
            font-size: 0.8rem;
            color: #6b7280;
        }
        
        .transaction-amount {
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .amount-positive {
            color: #10b981;
        }
        
        .amount-negative {
            color: #ef4444;
        }
        
        .btn-back {
            background: #6b7280;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 30px;
        }
        
        .btn-back:hover {
            background: #4b5563;
            transform: translateY(-1px);
            color: white;
        }
        
        @media (max-width: 768px) {
            .guide-container {
                padding: 15px;
            }
            
            .guide-header {
                padding: 25px 20px;
            }
            
            .guide-section {
                padding: 25px 20px;
            }
            
            .balance-display {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .earnings-row,
            .transaction-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
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
            <div class="guide-container">
                <div class="guide-header">
                    <h1>💰 Tata Coin详细说明</h1>
                    <p>了解Tata Coin系统和您的收益分成机制</p>
                    
                    <div class="balance-display">
                        <div class="balance-item">
                            <span class="balance-amount"><?php echo number_format($currentBalance); ?></span>
                            <span class="balance-label">当前余额</span>
                        </div>
                        <div class="balance-item">
                            <span class="balance-amount"><?php echo number_format($earningsData['total_earnings'] ?? 0); ?></span>
                            <span class="balance-label">累计收益</span>
                        </div>
                        <div class="balance-item">
                            <span class="balance-amount"><?php echo number_format($earningsData['monthly_earnings'] ?? 0); ?></span>
                            <span class="balance-label">本月收益</span>
                        </div>
                    </div>
                </div>
                
                <a href="dashboard.php" class="btn-back">← 返回后台首页</a>
                
                <!-- 收益分成机制 -->
                <div class="guide-section">
                    <h2 class="section-title">
                        <div class="section-icon">💎</div>
                        收益分成机制
                    </h2>
                    
                    <div class="share-explanation">
                        <div class="share-title">
                            <span>🎯</span>
                            您的收益分成比例：<?php echo $readerShareRate; ?>%
                        </div>
                        <div class="share-details">
                            <p>当用户支付Tata Coin查看您的联系方式时，您将获得 <strong><?php echo $readerShareRate; ?>%</strong> 的分成收益。</p>
                            <p>这些收益会自动添加到您的Tata Coin余额中，您可以随时查看收益记录。</p>
                        </div>
                    </div>
                    
                    <div class="earnings-table">
                        <div class="earnings-row">
                            <div class="earnings-item">🌟 推荐塔罗师联系方式查看</div>
                            <div class="earnings-amount">+<?php echo round($featuredCost * $readerShareRate / 100); ?> Tata Coin</div>
                        </div>
                        <div class="earnings-row">
                            <div class="earnings-item">👤 普通塔罗师联系方式查看</div>
                            <div class="earnings-amount">+<?php echo round($normalCost * $readerShareRate / 100); ?> Tata Coin</div>
                        </div>
                    </div>
                </div>
                
                <!-- 塔罗师等级系统 -->
                <div class="guide-section">
                    <h2 class="section-title">
                        <div class="section-icon">🏆</div>
                        塔罗师等级系统
                    </h2>

                    <?php
                    require_once '../includes/level_badge.php';
                    outputLevelBadgeCSS();

                    $readerLevel = $tataCoinManager->getUserLevel($readerId, 'reader');
                    ?>

                    <div class="share-explanation">
                        <div class="share-title">
                            <span>🎖️</span>
                            您的当前等级：<?php echo getReaderLevelBadgeHTML($readerLevel['level_name'], 'medium'); ?>
                        </div>
                        <div class="share-details">
                            <p>平台设有两种塔罗师等级：</p>
                            <ul style="margin: 15px 0; padding-left: 20px;">
                                <li><?php echo getReaderLevelBadgeHTML('塔罗师', 'medium'); ?> <strong>塔罗师</strong> - 平台认证的专业塔罗师</li>
                                <li><?php echo getReaderLevelBadgeHTML('推荐塔罗师', 'medium'); ?> <strong>推荐塔罗师</strong> - 平台重点推荐的优质塔罗师</li>
                            </ul>
                            <p><strong>推荐塔罗师特权：</strong></p>
                            <ul style="margin: 10px 0; padding-left: 20px;">
                                <li>首页优先展示，获得更多曝光机会</li>
                                <li>更高的查看费用（<?php echo $featuredCost; ?> vs <?php echo $normalCost; ?> Tata Coin）</li>
                                <li>专属的推荐标识和徽章</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- 如何增加收益 -->
                <div class="guide-section">
                    <h2 class="section-title">
                        <div class="section-icon">📈</div>
                        如何增加收益？
                    </h2>
                    <div style="color: #4b5563; line-height: 1.6;">
                        <ol style="padding-left: 20px;">
                            <li><strong>完善个人资料：</strong>上传清晰的个人照片、详细的个人简介和专业的价格列表</li>
                            <li><strong>提升专业度：</strong>在擅长方向中明确标注您的专业领域</li>
                            <li><strong>积极互动：</strong>及时回复用户咨询，提供优质的服务体验</li>
                            <li><strong>争取推荐：</strong>优秀的塔罗师有机会成为推荐塔罗师，获得更高的查看费用和曝光</li>
                            <li><strong>保持活跃：</strong>定期更新资料，保持账户活跃状态</li>
                            <li><strong>邀请新用户：</strong>通过邀请链接推广，获得邀请奖励</li>
                        </ol>
                    </div>
                </div>
                
                <!-- 最近收益记录 -->
                <?php if (!empty($recentTransactions)): ?>
                <div class="guide-section">
                    <h2 class="section-title">
                        <div class="section-icon">📊</div>
                        最近收益记录
                    </h2>
                    
                    <?php foreach (array_slice($recentTransactions, 0, 10) as $transaction): ?>
                        <?php if ($transaction['amount'] > 0): ?>
                        <div class="transaction-item">
                            <div class="transaction-info">
                                <div class="transaction-desc"><?php echo h($transaction['description']); ?></div>
                                <div class="transaction-time"><?php echo date('Y-m-d H:i', strtotime($transaction['created_at'])); ?></div>
                            </div>
                            <div class="transaction-amount amount-positive">
                                +<?php echo $transaction['amount']; ?> Tata Coin
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="view_records.php" class="btn-back" style="background: #f59e0b;">查看完整记录</a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- 常见问题 -->
                <div class="guide-section">
                    <h2 class="section-title">
                        <div class="section-icon">❓</div>
                        常见问题
                    </h2>
                    <div style="color: #4b5563; line-height: 1.6;">
                        <p><strong>Q: 收益什么时候到账？</strong><br>
                        A: 用户支付查看费用后，您的分成收益会立即到账。</p>
                        
                        <p><strong>Q: 可以提现Tata Coin吗？</strong><br>
                        A: 目前Tata Coin主要用于平台内的服务交易，具体提现政策请联系管理员。</p>
                        
                        <p><strong>Q: 如何成为推荐塔罗师？</strong><br>
                        A: 推荐塔罗师由管理员根据服务质量、用户反馈等因素综合评定。</p>
                        
                        <p><strong>Q: 收益分成比例会变化吗？</strong><br>
                        A: 分成比例由平台统一设定，如有调整会提前通知所有塔罗师。</p>
                    </div>
                </div>
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
