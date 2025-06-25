<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$success = [];
$errors = [];
$fixCompleted = false;

// 处理修复请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_fix'])) {
    try {
        $db = Database::getInstance();
        
        // 1. 检查settings表是否存在
        $settingsTableExists = false;
        try {
            $db->fetchOne("SELECT 1 FROM settings LIMIT 1");
            $settingsTableExists = true;
            $success[] = "✓ settings表已存在";
        } catch (Exception $e) {
            $success[] = "⚠ settings表不存在，需要创建";
        }
        
        // 2. 检查site_settings表是否存在
        $siteSettingsTableExists = false;
        $siteSettingsData = [];
        try {
            $siteSettingsData = $db->fetchAll("SELECT * FROM site_settings");
            $siteSettingsTableExists = true;
            $success[] = "✓ 发现site_settings表，包含 " . count($siteSettingsData) . " 条记录";
        } catch (Exception $e) {
            $success[] = "⚠ site_settings表不存在";
        }
        
        // 3. 如果settings表不存在，创建它
        if (!$settingsTableExists) {
            $sql = "CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE COMMENT '设置键名',
                setting_value TEXT NOT NULL COMMENT '设置值',
                description VARCHAR(255) DEFAULT NULL COMMENT '设置描述',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_setting_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统设置表'";
            
            $db->query($sql);
            $success[] = "✓ 创建settings表成功";
        }
        
        // 4. 如果site_settings表存在且有数据，迁移到settings表
        if ($siteSettingsTableExists && !empty($siteSettingsData)) {
            foreach ($siteSettingsData as $setting) {
                // 检查settings表中是否已存在该设置
                $existing = $db->fetchOne(
                    "SELECT id FROM settings WHERE setting_key = ?", 
                    [$setting['setting_key']]
                );
                
                if (!$existing) {
                    // 插入到settings表
                    $db->insert('settings', [
                        'setting_key' => $setting['setting_key'],
                        'setting_value' => $setting['setting_value'],
                        'description' => $setting['description'] ?? null
                    ]);
                    $success[] = "✓ 迁移设置: {$setting['setting_key']} = {$setting['setting_value']}";
                } else {
                    $success[] = "⚠ 设置已存在，跳过: {$setting['setting_key']}";
                }
            }
        }
        
        // 5. 确保必要的Tata Coin设置存在
        $requiredSettings = [
            'new_user_tata_coin' => ['100', '新用户注册赠送金额'],
            'featured_reader_cost' => ['30', '查看推荐塔罗师费用'],
            'normal_reader_cost' => ['10', '查看普通塔罗师费用'],
            'reader_commission_rate' => ['50', '塔罗师分成比例（%）'],
            'daily_browse_limit' => ['10', '每日浏览奖励上限'],
            'profile_completion_reward' => ['20', '完善资料奖励金额'],
            'invitation_user_reward' => ['20', '邀请用户奖励'],
            'invitation_reader_reward' => ['50', '邀请塔罗师奖励'],
            'daily_earning_limit' => ['30', '每日非付费获取上限'],
            'invitation_commission_rate' => ['5', '邀请返点比例（百分比）'],
            'reader_invitation_commission_rate' => ['20', '塔罗师邀请塔罗师返点比例（百分比）']
        ];
        
        foreach ($requiredSettings as $key => $value) {
            $existing = $db->fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
            if (!$existing) {
                $db->insert('settings', [
                    'setting_key' => $key,
                    'setting_value' => $value[0],
                    'description' => $value[1]
                ]);
                $success[] = "✓ 添加缺失设置: {$key} = {$value[0]}";
            }
        }
        
        // 6. 验证设置是否正确
        $currentSettings = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE '%tata%' OR setting_key LIKE '%invitation%'");
        $success[] = "✓ 当前Tata Coin相关设置：";
        foreach ($currentSettings as $setting) {
            $success[] = "  - {$setting['setting_key']}: {$setting['setting_value']}";
        }
        
        $fixCompleted = true;
        
    } catch (Exception $e) {
        $errors[] = "修复失败：" . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修复设置表问题 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .fix-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .fix-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin: 20px 0;
        }
        
        .fix-btn:hover {
            background: #218838;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .btn-back:hover {
            background: #5a6268;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="fix-container">
        <h1>🔧 修复设置表问题</h1>
        
        <a href="tata_coin.php" class="btn-back">← 返回Tata Coin管理</a>
        
        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <h4>❌ 修复过程中出现错误：</h4>
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-box">
                <h4>✅ 修复进度：</h4>
                <?php foreach ($success as $msg): ?>
                    <p><?php echo htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($fixCompleted): ?>
            <div class="success-box">
                <h3>🎉 修复完成！</h3>
                <p><strong>已完成的修复：</strong></p>
                <ul>
                    <li>✅ 检查并创建settings表</li>
                    <li>✅ 从site_settings表迁移数据（如果存在）</li>
                    <li>✅ 确保所有必要的Tata Coin设置存在</li>
                    <li>✅ 验证设置完整性</li>
                </ul>
                
                <p><strong>现在可以正常访问Tata Coin管理页面了！</strong></p>
                <p><a href="tata_coin.php" class="btn-back" style="background: #28a745;">访问Tata Coin管理</a></p>
            </div>
        <?php else: ?>
            <div class="warning-box">
                <h4>⚠️ 发现的问题：</h4>
                <p>系统尝试访问 <code>site_settings</code> 表，但该表可能不存在或需要迁移到 <code>settings</code> 表。</p>
                <p>这个问题导致Tata Coin管理页面无法正常访问。</p>
            </div>
            
            <div class="warning-box">
                <h4>🔧 本次修复将执行：</h4>
                <ul>
                    <li>检查settings表是否存在，如不存在则创建</li>
                    <li>检查site_settings表是否存在数据，如有则迁移到settings表</li>
                    <li>确保所有必要的Tata Coin设置都存在</li>
                    <li>验证设置的完整性和正确性</li>
                </ul>
            </div>
            
            <form method="POST">
                <button type="submit" name="confirm_fix" class="fix-btn" 
                        onclick="return confirm('确定要修复设置表问题吗？这将检查并迁移数据库设置。')">
                    🔧 开始修复
                </button>
            </form>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/admin_footer.php'; ?>
</body>
</html>
