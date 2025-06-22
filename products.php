<?php
session_start();
require_once 'config/config.php';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>魔法产品 - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="page-header">
                <h1>魔法产品</h1>
                <p>精选神秘学用品，助力您的灵性修行</p>
            </div>
            
            <div class="coming-soon">
                <div class="coming-soon-content">
                    <h2>✨ 产品商城即将开放</h2>
                    <p>我们正在筹备精美的神秘学产品，为您的修行之路提供支持：</p>
                    
                    <div class="product-preview">
                        <div class="product-category">
                            <h3>🃏 塔罗牌系列</h3>
                            <ul>
                                <li>经典韦特塔罗牌</li>
                                <li>现代艺术塔罗牌</li>
                                <li>限量版收藏塔罗牌</li>
                                <li>塔罗牌收纳盒</li>
                            </ul>
                        </div>
                        
                        <div class="product-category">
                            <h3>🔮 占卜工具</h3>
                            <ul>
                                <li>水晶球</li>
                                <li>占卜石</li>
                                <li>符文石</li>
                                <li>占卜布</li>
                            </ul>
                        </div>
                        
                        <div class="product-category">
                            <h3>💎 能量水晶</h3>
                            <ul>
                                <li>紫水晶</li>
                                <li>白水晶</li>
                                <li>黑曜石</li>
                                <li>月光石</li>
                            </ul>
                        </div>
                        
                        <div class="product-category">
                            <h3>📖 神秘学书籍</h3>
                            <ul>
                                <li>塔罗学习指南</li>
                                <li>占星学入门</li>
                                <li>数字学解析</li>
                                <li>冥想修行手册</li>
                            </ul>
                        </div>
                        
                        <div class="product-category">
                            <h3>🕯️ 仪式用品</h3>
                            <ul>
                                <li>香薰蜡烛</li>
                                <li>净化香料</li>
                                <li>仪式道具</li>
                                <li>护身符</li>
                            </ul>
                        </div>
                        
                        <div class="product-category">
                            <h3>🎁 精美礼品</h3>
                            <ul>
                                <li>神秘学礼品套装</li>
                                <li>定制塔罗牌</li>
                                <li>能量首饰</li>
                                <li>装饰摆件</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="contact-info">
                        <h3>📞 提前预订</h3>
                        <p>如果您对某些产品特别感兴趣，可以提前联系我们进行预订</p>
                        <div class="contact-methods">
                            <div class="contact-item">
                                <strong>微信：</strong> mystical_shop
                            </div>
                            <div class="contact-item">
                                <strong>邮箱：</strong> shop@example.com
                            </div>
                            <div class="contact-item">
                                <strong>QQ群：</strong> 123456789
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <style>
        .coming-soon {
            padding: 60px 20px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            margin: 40px 0;
        }
        
        .coming-soon-content {
            text-align: center;
        }
        
        .coming-soon-content h2 {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .product-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 40px 0;
            text-align: left;
        }
        
        .product-category {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .product-category:hover {
            transform: translateY(-5px);
        }
        
        .product-category h3 {
            color: #d4af37;
            margin-bottom: 20px;
            font-size: 1.3rem;
            text-align: center;
        }
        
        .product-category ul {
            list-style: none;
            padding: 0;
        }
        
        .product-category li {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            position: relative;
            padding-left: 20px;
        }
        
        .product-category li:before {
            content: "✨";
            position: absolute;
            left: 0;
        }
        
        .contact-info {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-top: 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .contact-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .contact-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .product-preview {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .contact-methods {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
