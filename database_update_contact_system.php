<?php
/**
 * 联系系统数据库更新脚本
 * 添加联系方式设置和在线留言功能
 */

session_start();
require_once 'config/config.php';

// 检查管理员权限
if (!isAdminLoggedIn()) {
    die('需要管理员权限才能执行此操作');
}

try {
    $db = Database::getInstance();
    
    echo "<h2>🔧 联系系统数据库更新</h2>";
    echo "<p>正在为联系页面添加后台管理功能...</p>";
    
    // 1. 创建在线留言表
    echo "<h3>1. 创建在线留言表</h3>";
    
    $createMessagesTable = "CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL COMMENT '留言者姓名',
        email VARCHAR(255) NOT NULL COMMENT '留言者邮箱',
        subject VARCHAR(255) NOT NULL COMMENT '留言主题',
        message TEXT NOT NULL COMMENT '留言内容',
        ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
        user_agent TEXT DEFAULT NULL COMMENT '用户代理',
        status ENUM('unread', 'read', 'replied') DEFAULT 'unread' COMMENT '状态：未读、已读、已回复',
        admin_reply TEXT DEFAULT NULL COMMENT '管理员回复',
        replied_by INT DEFAULT NULL COMMENT '回复管理员ID',
        replied_at TIMESTAMP NULL DEFAULT NULL COMMENT '回复时间',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_created_at (created_at),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='联系我们留言表'";
    
    try {
        $db->query($createMessagesTable);
        echo "<p style='color: green;'>✅ 成功创建contact_messages表</p>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠️ contact_messages表可能已存在: " . $e->getMessage() . "</p>";
    }
    
    // 2. 添加联系方式设置项
    echo "<h3>2. 添加联系方式设置项</h3>";
    
    $contactSettings = [
        'contact_email_primary' => [
            'value' => 'info@example.com',
            'description' => '主要联系邮箱'
        ],
        'contact_email_support' => [
            'value' => 'support@example.com',
            'description' => '客服支持邮箱'
        ],
        'contact_wechat_id' => [
            'value' => 'mystical_service',
            'description' => '微信客服号'
        ],
        'contact_wechat_hours' => [
            'value' => '9:00-21:00',
            'description' => '微信客服工作时间'
        ],
        'contact_qq_main' => [
            'value' => '123456789',
            'description' => '官方QQ交流群'
        ],
        'contact_qq_newbie' => [
            'value' => '987654321',
            'description' => '新手学习QQ群'
        ],
        'contact_xiaohongshu' => [
            'value' => '@神秘学园',
            'description' => '小红书账号'
        ],
        'contact_xiaohongshu_desc' => [
            'value' => '每日分享占卜知识',
            'description' => '小红书账号描述'
        ]
    ];
    
    foreach ($contactSettings as $key => $setting) {
        try {
            // 检查设置是否已存在
            $existing = $db->fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
            
            if (!$existing) {
                $db->query(
                    "INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)",
                    [$key, $setting['value'], $setting['description']]
                );
                echo "<p style='color: green;'>✅ 添加设置项: {$key}</p>";
            } else {
                echo "<p style='color: blue;'>ℹ️ 设置项已存在: {$key}</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ 添加设置项失败 {$key}: " . $e->getMessage() . "</p>";
        }
    }
    
    // 3. 检查并创建必要的索引
    echo "<h3>3. 优化数据库索引</h3>";
    
    try {
        // 为settings表添加索引（如果不存在）
        $db->query("CREATE INDEX IF NOT EXISTS idx_setting_key ON settings(setting_key)");
        echo "<p style='color: green;'>✅ 优化settings表索引</p>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠️ 索引可能已存在: " . $e->getMessage() . "</p>";
    }
    
    // 4. 插入测试数据（可选）
    echo "<h3>4. 插入测试数据</h3>";
    
    $testMessage = [
        'name' => '测试用户',
        'email' => 'test@example.com',
        'subject' => '测试留言',
        'message' => '这是一条测试留言，用于验证系统功能是否正常。',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test Browser'
    ];
    
    try {
        // 检查是否已有测试数据
        $existingTest = $db->fetchOne("SELECT id FROM contact_messages WHERE email = ?", [$testMessage['email']]);
        
        if (!$existingTest) {
            $db->query(
                "INSERT INTO contact_messages (name, email, subject, message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)",
                array_values($testMessage)
            );
            echo "<p style='color: green;'>✅ 插入测试留言数据</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ 测试数据已存在</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ 插入测试数据失败: " . $e->getMessage() . "</p>";
    }
    
    // 5. 验证表结构
    echo "<h3>5. 验证表结构</h3>";
    
    try {
        $messageCount = $db->fetchOne("SELECT COUNT(*) as count FROM contact_messages");
        echo "<p style='color: green;'>✅ contact_messages表正常，当前有 {$messageCount['count']} 条留言</p>";
        
        $settingsCount = $db->fetchOne("SELECT COUNT(*) as count FROM settings WHERE setting_key LIKE 'contact_%'");
        echo "<p style='color: green;'>✅ 联系方式设置正常，当前有 {$settingsCount['count']} 个设置项</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ 验证失败: " . $e->getMessage() . "</p>";
    }
    
    // 6. 显示当前联系方式设置
    echo "<h3>6. 当前联系方式设置</h3>";
    
    try {
        $contactSettings = $db->fetchAll("SELECT setting_key, setting_value, description FROM settings WHERE setting_key LIKE 'contact_%' ORDER BY setting_key");
        
        if (!empty($contactSettings)) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
            echo "<tr style='background: #f0f0f0;'><th>设置键</th><th>当前值</th><th>说明</th></tr>";
            
            foreach ($contactSettings as $setting) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($setting['setting_key']) . "</td>";
                echo "<td>" . htmlspecialchars($setting['setting_value']) . "</td>";
                echo "<td>" . htmlspecialchars($setting['description']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>⚠️ 未找到联系方式设置</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ 获取设置失败: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3>🎉 数据库更新完成！</h3>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #c3e6cb;'>";
    echo "<h4 style='color: #155724; margin: 0 0 10px 0;'>✅ 更新内容总结：</h4>";
    echo "<ul style='color: #155724; margin: 0;'>";
    echo "<li>创建了contact_messages表用于存储在线留言</li>";
    echo "<li>添加了8个联系方式设置项到settings表</li>";
    echo "<li>优化了数据库索引提升查询性能</li>";
    echo "<li>插入了测试数据用于功能验证</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #ffeaa7;'>";
    echo "<h4 style='color: #856404; margin: 0 0 10px 0;'>📋 下一步操作：</h4>";
    echo "<ul style='color: #856404; margin: 0;'>";
    echo "<li>访问管理员后台的系统设置页面编辑联系方式</li>";
    echo "<li>访问管理员后台的消息管理页面查看留言</li>";
    echo "<li>测试联系页面的留言提交功能</li>";
    echo "<li>根据需要调整联系方式的默认值</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ 更新失败</h3>";
    echo "<p style='color: red;'>错误信息: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>请检查数据库连接和权限设置。</p>";
}
?>
