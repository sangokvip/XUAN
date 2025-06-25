<?php
/**
 * 等级标签测试页面
 * 用于预览所有等级标签的显示效果
 */
require_once 'includes/level_badge.php';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>等级标签预览</title>
    <?php outputLevelBadgeCSS(); ?>
    <style>
        body {
            font-family: 'Microsoft YaHei', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section h2 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .badge-demo {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .badge-demo .label {
            width: 200px;
            font-weight: bold;
            color: #555;
        }
        
        .size-demo {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .demo-text {
            color: #666;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏆 等级标签系统预览</h1>
        
        <!-- 用户等级标签 -->
        <div class="section">
            <h2>👤 用户等级标签</h2>
            
            <div class="badge-demo">
                <div class="label">L1 (0-100 coin):</div>
                <div class="size-demo">
                    <?php echo getUserLevelBadgeHTML(1, 'L1', 'small'); ?>
                    <?php echo getUserLevelBadgeHTML(1, 'L1', 'medium'); ?>
                    <?php echo getUserLevelBadgeHTML(1, 'L1', 'large'); ?>
                </div>
                <div class="demo-text">无折扣</div>
            </div>
            
            <div class="badge-demo">
                <div class="label">L2 (101-200 coin):</div>
                <div class="size-demo">
                    <?php echo getUserLevelBadgeHTML(2, 'L2', 'small'); ?>
                    <?php echo getUserLevelBadgeHTML(2, 'L2', 'medium'); ?>
                    <?php echo getUserLevelBadgeHTML(2, 'L2', 'large'); ?>
                </div>
                <div class="demo-text">享受5%折扣</div>
            </div>
            
            <div class="badge-demo">
                <div class="label">L3 (201-500 coin):</div>
                <div class="size-demo">
                    <?php echo getUserLevelBadgeHTML(3, 'L3', 'small'); ?>
                    <?php echo getUserLevelBadgeHTML(3, 'L3', 'medium'); ?>
                    <?php echo getUserLevelBadgeHTML(3, 'L3', 'large'); ?>
                </div>
                <div class="demo-text">享受10%折扣</div>
            </div>
            
            <div class="badge-demo">
                <div class="label">L4 (501-999 coin):</div>
                <div class="size-demo">
                    <?php echo getUserLevelBadgeHTML(4, 'L4', 'small'); ?>
                    <?php echo getUserLevelBadgeHTML(4, 'L4', 'medium'); ?>
                    <?php echo getUserLevelBadgeHTML(4, 'L4', 'large'); ?>
                </div>
                <div class="demo-text">享受15%折扣</div>
            </div>
            
            <div class="badge-demo">
                <div class="label">L5 (1000+ coin):</div>
                <div class="size-demo">
                    <?php echo getUserLevelBadgeHTML(5, 'L5', 'small'); ?>
                    <?php echo getUserLevelBadgeHTML(5, 'L5', 'medium'); ?>
                    <?php echo getUserLevelBadgeHTML(5, 'L5', 'large'); ?>
                </div>
                <div class="demo-text">享受20%折扣</div>
            </div>
        </div>
        
        <!-- 塔罗师等级标签 -->
        <div class="section">
            <h2>🔮 塔罗师等级标签</h2>
            
            <div class="badge-demo">
                <div class="label">普通塔罗师:</div>
                <div class="size-demo">
                    <?php echo getReaderLevelBadgeHTML('塔罗师', 'small'); ?>
                    <?php echo getReaderLevelBadgeHTML('塔罗师', 'medium'); ?>
                    <?php echo getReaderLevelBadgeHTML('塔罗师', 'large'); ?>
                </div>
                <div class="demo-text">查看费用: 10 Tata Coin</div>
            </div>
            
            <div class="badge-demo">
                <div class="label">推荐塔罗师:</div>
                <div class="size-demo">
                    <?php echo getReaderLevelBadgeHTML('推荐塔罗师', 'small'); ?>
                    <?php echo getReaderLevelBadgeHTML('推荐塔罗师', 'medium'); ?>
                    <?php echo getReaderLevelBadgeHTML('推荐塔罗师', 'large'); ?>
                </div>
                <div class="demo-text">查看费用: 30 Tata Coin</div>
            </div>
        </div>
        
        <!-- 使用示例 -->
        <div class="section">
            <h2>📝 使用示例</h2>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 15px;">
                <h4>评论区用户名显示：</h4>
                <div style="padding: 10px; background: white; border-radius: 5px;">
                    <strong>张三</strong> <?php echo getUserLevelBadgeHTML(3, 'L3', 'small'); ?>
                    <div style="margin-top: 5px; color: #666; font-size: 14px;">
                        这个塔罗师很专业，推荐！
                    </div>
                </div>
            </div>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 15px;">
                <h4>塔罗师卡片显示：</h4>
                <div style="padding: 15px; background: white; border-radius: 5px;">
                    <h3 style="margin: 0 0 5px 0;">
                        李老师 <?php echo getReaderLevelBadgeHTML('推荐塔罗师', 'small'); ?>
                    </h3>
                    <div style="color: #666; font-size: 14px;">从业 5 年</div>
                </div>
            </div>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                <h4>用户中心等级显示：</h4>
                <div style="padding: 15px; background: white; border-radius: 5px; text-align: center;">
                    <div style="font-size: 24px; margin-bottom: 10px;">
                        L4 <?php echo getUserLevelBadgeHTML(4, 'L4', 'large'); ?>
                    </div>
                    <div style="color: #666;">享受15%折扣优惠</div>
                </div>
            </div>
        </div>
        
        <!-- 等级说明 -->
        <div class="section">
            <?php echo getLevelDescription('user'); ?>
        </div>
        
        <div class="section">
            <?php echo getLevelDescription('reader'); ?>
        </div>
        
        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <p style="color: #666;">
                <strong>注意：</strong>这是等级标签预览页面，完成测试后请删除此文件。
            </p>
        </div>
    </div>
</body>
</html>
