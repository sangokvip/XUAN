<?php
session_start();
require_once '../config/config.php';
require_once '../includes/TataCoinManager.php';

// 检查用户登录
requireLogin('../auth/login.php');

$db = Database::getInstance();
$tataCoinManager = new TataCoinManager();

$userId = $_SESSION['user_id'];
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

if (!$user) {
    logout();
    redirect('../auth/login.php');
}

// 获取当前余额
$currentBalance = $tataCoinManager->getBalance($userId, 'user');

// 获取系统设置
$featuredCost = $tataCoinManager->getSetting('featured_reader_cost', 30);
$normalCost = $tataCoinManager->getSetting('normal_reader_cost', 10);

$pageTitle = 'Tata Coin使用说明';
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
        .guide-container {
            max-width: 900px;
            margin: 40px auto;
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
        }
        
        .balance-amount {
            font-size: 2rem;
            font-weight: 700;
            color: #fbbf24;
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
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }
        
        .feature-card {
            background: #f8fafc;
            border-radius: 15px;
            padding: 25px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            border-color: #f59e0b;
            transform: translateY(-2px);
        }
        
        .feature-icon {
            font-size: 2rem;
            margin-bottom: 15px;
        }
        
        .feature-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 10px;
        }
        
        .feature-desc {
            color: #6b7280;
            line-height: 1.5;
        }
        
        .price-table {
            background: #f8fafc;
            border-radius: 15px;
            padding: 25px;
            margin-top: 25px;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .price-row:last-child {
            border-bottom: none;
        }
        
        .price-item {
            font-weight: 500;
            color: #374151;
        }
        
        .price-cost {
            font-weight: 700;
            color: #f59e0b;
            font-size: 1.1rem;
        }
        
        .tips-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .tips-list li {
            padding: 15px 0;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .tips-list li:last-child {
            border-bottom: none;
        }
        
        .tip-icon {
            background: #fef3c7;
            color: #92400e;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .tip-content {
            color: #4b5563;
            line-height: 1.6;
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
                margin: 20px auto;
                padding: 15px;
            }
            
            .guide-header {
                padding: 25px 20px;
            }
            
            .guide-section {
                padding: 25px 20px;
            }
            
            .feature-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .price-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="guide-container">
        <div class="guide-header">
            <h1>💰 Tata Coin使用说明</h1>
            <p>了解如何使用Tata Coin获得更好的服务体验</p>
            
            <div class="balance-display">
                <div>您当前的余额</div>
                <div class="balance-amount"><?php echo number_format($currentBalance); ?> 枚</div>
            </div>
        </div>
        
        <a href="index.php" class="btn-back">← 返回用户中心</a>
        
        <!-- 什么是Tata Coin -->
        <div class="guide-section">
            <h2 class="section-title">
                <div class="section-icon">🪙</div>
                什么是Tata Coin？
            </h2>
            <p style="color: #4b5563; line-height: 1.6; font-size: 1.1rem;">
                Tata Coin是我们网站的虚拟货币，用于购买各种服务和内容。通过Tata Coin，您可以获得更深入的塔罗师服务，包括查看联系方式、购买课程和神秘产品等。
            </p>
        </div>
        
        <!-- 如何获得Tata Coin -->
        <div class="guide-section">
            <h2 class="section-title">
                <div class="section-icon">🎁</div>
                如何获得Tata Coin？
            </h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon">🎉</div>
                    <div class="feature-title">新用户赠送</div>
                    <div class="feature-desc">注册成功后立即获得100枚Tata Coin，让您开始探索我们的服务。</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📅</div>
                    <div class="feature-title">每日签到</div>
                    <div class="feature-desc">连续签到7天可获得57枚Tata Coin，每天5-12枚不等。</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👀</div>
                    <div class="feature-title">浏览页面</div>
                    <div class="feature-desc">每个页面停留5秒可获得1枚Tata Coin，每日最多10枚。</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📝</div>
                    <div class="feature-title">完善资料</div>
                    <div class="feature-desc">完善头像、性别等个人信息可获得20枚Tata Coin。</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <div class="feature-title">邀请朋友</div>
                    <div class="feature-desc">邀请朋友注册并首次消费可获得20枚Tata Coin。</div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">💝</div>
                    <div class="feature-title">管理员赠送</div>
                    <div class="feature-desc">在特殊情况下，管理员可能会向用户赠送Tata Coin。</div>
                </div>
            </div>

            <div style="background: #fef3c7; border: 2px solid #f59e0b; border-radius: 15px; padding: 20px; margin-top: 20px;">
                <h4 style="color: #92400e; margin-bottom: 10px;">💡 每日获取上限</h4>
                <p style="color: #92400e; margin: 0;">
                    为了保持平台经济平衡，每日通过非付费方式最多可获得<strong>30枚Tata Coin</strong>。
                    包括签到奖励、浏览奖励等，但不包括邀请奖励和管理员赠送。
                </p>
            </div>
        </div>
        
        <!-- 如何使用Tata Coin -->
        <div class="guide-section">
            <h2 class="section-title">
                <div class="section-icon">🛒</div>
                如何使用Tata Coin？
            </h2>
            <div class="price-table">
                <div class="price-row">
                    <div class="price-item">🌟 查看推荐塔罗师联系方式</div>
                    <div class="price-cost"><?php echo $featuredCost; ?> 枚</div>
                </div>
                <div class="price-row">
                    <div class="price-item">👤 查看普通塔罗师联系方式</div>
                    <div class="price-cost"><?php echo $normalCost; ?> 枚</div>
                </div>
                <div class="price-row">
                    <div class="price-item">📚 购买塔罗课程</div>
                    <div class="price-cost">根据课程定价</div>
                </div>
                <div class="price-row">
                    <div class="price-item">🔮 购买神秘产品</div>
                    <div class="price-cost">根据产品定价</div>
                </div>
            </div>
        </div>
        
        <!-- 使用技巧 -->
        <div class="guide-section">
            <h2 class="section-title">
                <div class="section-icon">💡</div>
                使用技巧与建议
            </h2>
            <ul class="tips-list">
                <li>
                    <div class="tip-icon">1</div>
                    <div class="tip-content">
                        <strong>合理规划使用：</strong>建议先浏览塔罗师的基本信息，确定合适后再使用Tata Coin查看联系方式。
                    </div>
                </li>
                <li>
                    <div class="tip-icon">2</div>
                    <div class="tip-content">
                        <strong>关注推荐塔罗师：</strong>推荐塔罗师通常经验更丰富，服务质量更高，值得优先考虑。
                    </div>
                </li>
                <li>
                    <div class="tip-icon">3</div>
                    <div class="tip-content">
                        <strong>查看用户评价：</strong>在使用Tata Coin前，可以查看其他用户的评价和反馈。
                    </div>
                </li>
                <li>
                    <div class="tip-icon">4</div>
                    <div class="tip-content">
                        <strong>保持余额充足：</strong>建议保持一定的Tata Coin余额，以便随时获得需要的服务。
                    </div>
                </li>
                <li>
                    <div class="tip-icon">5</div>
                    <div class="tip-content">
                        <strong>关注活动信息：</strong>定期关注网站活动，获得更多Tata Coin奖励机会。
                    </div>
                </li>
            </ul>
        </div>
        
        <!-- 常见问题 -->
        <div class="guide-section">
            <h2 class="section-title">
                <div class="section-icon">❓</div>
                常见问题
            </h2>
            <div style="color: #4b5563; line-height: 1.6;">
                <p><strong>Q: Tata Coin会过期吗？</strong><br>
                A: 不会，您的Tata Coin余额永久有效，不会过期。</p>
                
                <p><strong>Q: 可以转让Tata Coin给其他用户吗？</strong><br>
                A: 目前不支持用户间的Tata Coin转让功能。</p>
                
                <p><strong>Q: 如果余额不足怎么办？</strong><br>
                A: 请关注网站活动获得更多Tata Coin，或联系客服了解其他获得方式。</p>
                
                <p><strong>Q: 查看过的塔罗师联系方式还需要再次付费吗？</strong><br>
                A: 不需要，一次付费后可以永久查看该塔罗师的联系方式。</p>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="transactions.php" class="btn-back" style="background: #f59e0b;">📊 查看交易记录</a>
            <a href="../readers.php" class="btn-back" style="background: #10b981;">🔮 浏览塔罗师</a>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
