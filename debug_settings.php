<?php
require_once 'config/config.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>调试：检查 site_settings 表</h2>";

    // 检查表结构
    echo "<h3>site_settings 表结构：</h3>";
    $columns = $db->fetchAll("SHOW COLUMNS FROM site_settings");
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>字段名</th><th>类型</th><th>允许NULL</th><th>默认值</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 检查所有设置
    $allSettings = $db->fetchAll("SELECT * FROM site_settings ORDER BY setting_key");
    
    echo "<h3>所有设置记录：</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>设置键</th><th>设置值</th><th>描述</th><th>创建时间</th></tr>";
    foreach ($allSettings as $setting) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($setting['setting_key']) . "</td>";
        echo "<td>" . htmlspecialchars($setting['setting_value']) . "</td>";
        echo "<td>" . htmlspecialchars($setting['description'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($setting['created_at'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 检查特定的邀请设置
    echo "<h3>邀请相关设置：</h3>";
    $invitationSettings = $db->fetchAll("SELECT * FROM site_settings WHERE setting_key LIKE '%invitation%'");
    
    if (empty($invitationSettings)) {
        echo "<p style='color: red;'>❌ 没有找到邀请相关设置</p>";
        
        // 尝试插入默认设置
        echo "<h4>尝试插入默认邀请设置：</h4>";
        try {
            $db->query("INSERT IGNORE INTO site_settings (setting_key, setting_value, description) VALUES ('invitation_commission_rate', '5', '邀请返点比例（百分比）')");
            echo "<p style='color: green;'>✅ 插入 invitation_commission_rate 设置</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ 插入 invitation_commission_rate 失败: " . $e->getMessage() . "</p>";
        }
        
        try {
            $db->query("INSERT IGNORE INTO site_settings (setting_key, setting_value, description) VALUES ('reader_invitation_commission_rate', '20', '塔罗师邀请塔罗师返点比例（百分比）')");
            echo "<p style='color: green;'>✅ 插入 reader_invitation_commission_rate 设置</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ 插入 reader_invitation_commission_rate 失败: " . $e->getMessage() . "</p>";
        }
        
        // 重新查询
        $invitationSettings = $db->fetchAll("SELECT * FROM site_settings WHERE setting_key LIKE '%invitation%'");
    }
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>设置键</th><th>设置值</th><th>描述</th></tr>";
    foreach ($invitationSettings as $setting) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($setting['setting_key']) . "</td>";
        echo "<td>" . htmlspecialchars($setting['setting_value']) . "</td>";
        echo "<td>" . htmlspecialchars($setting['description'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 测试读取设置的代码
    echo "<h3>测试设置读取：</h3>";
    $settings = [];
    $settingsData = $db->fetchAll("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('new_user_tata_coin', 'featured_reader_cost', 'normal_reader_cost', 'reader_commission_rate', 'invitation_commission_rate', 'reader_invitation_commission_rate')");
    foreach ($settingsData as $setting) {
        if (in_array($setting['setting_key'], ['invitation_commission_rate', 'reader_invitation_commission_rate'])) {
            $settings[$setting['setting_key']] = (float)$setting['setting_value'];
        } else {
            $settings[$setting['setting_key']] = (int)$setting['setting_value'];
        }
    }
    
    echo "<pre>";
    print_r($settings);
    echo "</pre>";
    
    echo "<h3>表单显示值：</h3>";
    echo "<p>邀请返点比例: " . ($settings['invitation_commission_rate'] ?? '默认5') . "</p>";
    echo "<p>塔罗师邀请返点比例: " . ($settings['reader_invitation_commission_rate'] ?? '默认20') . "</p>";
    
    echo "<p style='color: green; font-weight: bold;'>调试完成！</p>";
    echo "<p><a href='admin/tata_coin.php'>返回 Tata Coin 管理</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>详细信息: " . htmlspecialchars($e->getTraceAsString()) . "</p>";
}
?>
