<?php
require_once 'config/config.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>数据库更新：创建邀请系统</h2>";
    
    // 1. 创建邀请链接表
    $createInvitationTable = "
    CREATE TABLE IF NOT EXISTS invitation_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        inviter_id INT NOT NULL,
        inviter_type ENUM('reader', 'user') NOT NULL,
        token VARCHAR(255) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL,
        is_active BOOLEAN DEFAULT TRUE,
        INDEX idx_token (token),
        INDEX idx_inviter (inviter_id, inviter_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请链接表'";
    
    $db->query($createInvitationTable);
    echo "<p style='color: green;'>✅ 创建 invitation_links 表</p>";
    
    // 2. 创建邀请关系表
    $createInvitationRelationTable = "
    CREATE TABLE IF NOT EXISTS invitation_relations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        inviter_id INT NOT NULL,
        inviter_type ENUM('reader', 'user') NOT NULL,
        invitee_id INT NOT NULL,
        invitee_type ENUM('reader', 'user') NOT NULL,
        invitation_token VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_inviter (inviter_id, inviter_type),
        INDEX idx_invitee (invitee_id, invitee_type),
        INDEX idx_token (invitation_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请关系表'";
    
    $db->query($createInvitationRelationTable);
    echo "<p style='color: green;'>✅ 创建 invitation_relations 表</p>";
    
    // 3. 创建返点记录表
    $createCommissionTable = "
    CREATE TABLE IF NOT EXISTS invitation_commissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        inviter_id INT NOT NULL,
        inviter_type ENUM('reader', 'user') NOT NULL,
        invitee_id INT NOT NULL,
        invitee_type ENUM('reader', 'user') NOT NULL,
        transaction_id INT NOT NULL,
        commission_amount DECIMAL(10,2) NOT NULL,
        commission_rate DECIMAL(5,2) NOT NULL DEFAULT 5.00,
        original_amount DECIMAL(10,2) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_inviter (inviter_id, inviter_type),
        INDEX idx_transaction (transaction_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请返点记录表'";
    
    $db->query($createCommissionTable);
    echo "<p style='color: green;'>✅ 创建 invitation_commissions 表</p>";
    
    // 4. 为用户表和塔罗师表添加邀请人字段
    $checkUserInviterField = $db->fetchOne("SHOW COLUMNS FROM users LIKE 'invited_by'");
    if (!$checkUserInviterField) {
        $db->query("ALTER TABLE users ADD COLUMN invited_by INT DEFAULT NULL COMMENT '邀请人ID'");
        $db->query("ALTER TABLE users ADD COLUMN invited_by_type ENUM('reader', 'user') DEFAULT NULL COMMENT '邀请人类型'");
        echo "<p style='color: green;'>✅ 为 users 表添加邀请人字段</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ users 表邀请人字段已存在</p>";
    }
    
    $checkReaderInviterField = $db->fetchOne("SHOW COLUMNS FROM readers LIKE 'invited_by'");
    if (!$checkReaderInviterField) {
        $db->query("ALTER TABLE readers ADD COLUMN invited_by INT DEFAULT NULL COMMENT '邀请人ID'");
        $db->query("ALTER TABLE readers ADD COLUMN invited_by_type ENUM('reader', 'user') DEFAULT NULL COMMENT '邀请人类型'");
        echo "<p style='color: green;'>✅ 为 readers 表添加邀请人字段</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ readers 表邀请人字段已存在</p>";
    }
    
    // 5. 添加邀请返点设置
    $checkInvitationSettings = $db->fetchOne("SELECT * FROM site_settings WHERE setting_key = 'invitation_commission_rate'");
    if (!$checkInvitationSettings) {
        $db->query("INSERT INTO site_settings (setting_key, setting_value, description) VALUES ('invitation_commission_rate', '5', '邀请返点比例（百分比）')");
        echo "<p style='color: green;'>✅ 添加邀请返点设置</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ 邀请返点设置已存在</p>";
    }

    // 6. 添加塔罗师邀请塔罗师的返点设置
    $checkReaderInvitationSettings = $db->fetchOne("SELECT * FROM site_settings WHERE setting_key = 'reader_invitation_commission_rate'");
    if (!$checkReaderInvitationSettings) {
        $db->query("INSERT INTO site_settings (setting_key, setting_value, description) VALUES ('reader_invitation_commission_rate', '20', '塔罗师邀请塔罗师返点比例（百分比）')");
        echo "<p style='color: green;'>✅ 添加塔罗师邀请返点设置</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ 塔罗师邀请返点设置已存在</p>";
    }
    
    // 6. 为 tata_coin_transactions 表添加邀请相关字段
    $checkCommissionField = $db->fetchOne("SHOW COLUMNS FROM tata_coin_transactions LIKE 'is_commission'");
    if (!$checkCommissionField) {
        $db->query("ALTER TABLE tata_coin_transactions ADD COLUMN is_commission BOOLEAN DEFAULT FALSE COMMENT '是否为邀请返点'");
        $db->query("ALTER TABLE tata_coin_transactions ADD COLUMN commission_from_user_id INT DEFAULT NULL COMMENT '返点来源用户ID'");
        $db->query("ALTER TABLE tata_coin_transactions ADD COLUMN commission_from_user_type ENUM('reader', 'user') DEFAULT NULL COMMENT '返点来源用户类型'");
        echo "<p style='color: green;'>✅ 为 tata_coin_transactions 表添加邀请返点字段</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ tata_coin_transactions 表邀请返点字段已存在</p>";
    }
    
    echo "<h3>数据库表结构：</h3>";
    
    // 显示新创建的表结构
    $tables = ['invitation_links', 'invitation_relations', 'invitation_commissions'];
    foreach ($tables as $table) {
        echo "<h4>{$table} 表结构：</h4>";
        $columns = $db->fetchAll("SHOW COLUMNS FROM {$table}");
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>字段名</th><th>类型</th><th>允许NULL</th><th>默认值</th><th>注释</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($column['Comment'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<p style='color: green; font-weight: bold;'>邀请系统数据库更新完成！</p>";
    echo "<p><a href='admin/tata_coin.php'>前往管理后台配置邀请返点</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>详细信息: " . htmlspecialchars($e->getTraceAsString()) . "</p>";
}
?>
