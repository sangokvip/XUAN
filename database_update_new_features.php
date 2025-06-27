<?php
// 新功能数据库更新脚本
require_once 'config/config.php';

try {
    $db = Database::getInstance();
    echo "<h2>新功能数据库更新</h2>";
    
    // 1. 为占卜师表添加新字段
    echo "<h3>1. 更新占卜师表结构</h3>";
    
    // 添加国籍字段
    try {
        $db->query("ALTER TABLE readers ADD COLUMN nationality VARCHAR(100) DEFAULT NULL COMMENT '国籍（中英文格式）'");
        echo "<p style='color: green;'>✅ 添加国籍字段成功</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p style='color: blue;'>ℹ️ 国籍字段已存在</p>";
        } else {
            echo "<p style='color: red;'>❌ 添加国籍字段失败: " . $e->getMessage() . "</p>";
        }
    }
    
    // 添加占卜类型字段
    try {
        $db->query("ALTER TABLE readers ADD COLUMN divination_types TEXT DEFAULT NULL COMMENT '占卜类型（JSON格式存储）'");
        echo "<p style='color: green;'>✅ 添加占卜类型字段成功</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p style='color: blue;'>ℹ️ 占卜类型字段已存在</p>";
        } else {
            echo "<p style='color: red;'>❌ 添加占卜类型字段失败: " . $e->getMessage() . "</p>";
        }
    }
    
    // 添加主要身份标签字段
    try {
        $db->query("ALTER TABLE readers ADD COLUMN primary_identity VARCHAR(50) DEFAULT NULL COMMENT '主要身份标签'");
        echo "<p style='color: green;'>✅ 添加主要身份标签字段成功</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p style='color: blue;'>ℹ️ 主要身份标签字段已存在</p>";
        } else {
            echo "<p style='color: red;'>❌ 添加主要身份标签字段失败: " . $e->getMessage() . "</p>";
        }
    }
    
    // 添加身份类别字段（西玄/东玄）
    try {
        $db->query("ALTER TABLE readers ADD COLUMN identity_category ENUM('western', 'eastern') DEFAULT NULL COMMENT '身份类别：western-西玄，eastern-东玄'");
        echo "<p style='color: green;'>✅ 添加身份类别字段成功</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p style='color: blue;'>ℹ️ 身份类别字段已存在</p>";
        } else {
            echo "<p style='color: red;'>❌ 添加身份类别字段失败: " . $e->getMessage() . "</p>";
        }
    }
    
    // 2. 创建签到记录表
    echo "<h3>2. 创建签到记录表</h3>";
    try {
        $db->query("CREATE TABLE IF NOT EXISTS daily_checkins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL COMMENT '用户ID',
            reader_id INT DEFAULT NULL COMMENT '占卜师ID',
            user_type ENUM('user', 'reader') NOT NULL COMMENT '用户类型',
            checkin_date DATE NOT NULL COMMENT '签到日期',
            consecutive_days INT DEFAULT 1 COMMENT '连续签到天数',
            reward_amount INT DEFAULT 0 COMMENT '奖励金额',
            ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_checkin (user_id, reader_id, user_type, checkin_date),
            INDEX idx_user_type_date (user_type, checkin_date),
            INDEX idx_user_id (user_id),
            INDEX idx_reader_id (reader_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='每日签到记录表'");
        echo "<p style='color: green;'>✅ 创建签到记录表成功</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ 创建签到记录表失败: " . $e->getMessage() . "</p>";
    }
    
    // 3. 创建页面浏览记录表
    echo "<h3>3. 创建页面浏览记录表</h3>";
    try {
        $db->query("CREATE TABLE IF NOT EXISTS page_browse_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL COMMENT '用户ID',
            reader_id INT DEFAULT NULL COMMENT '占卜师ID',
            user_type ENUM('user', 'reader') NOT NULL COMMENT '用户类型',
            page_url VARCHAR(500) NOT NULL COMMENT '页面URL',
            page_title VARCHAR(200) DEFAULT NULL COMMENT '页面标题',
            browse_date DATE NOT NULL COMMENT '浏览日期',
            browse_time INT DEFAULT 0 COMMENT '浏览时长（秒）',
            reward_given TINYINT(1) DEFAULT 0 COMMENT '是否已给奖励',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_browse (user_id, reader_id, user_type, page_url, browse_date),
            INDEX idx_user_type_date (user_type, browse_date),
            INDEX idx_user_id (user_id),
            INDEX idx_reader_id (reader_id),
            INDEX idx_reward_given (reward_given)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='页面浏览记录表'");
        echo "<p style='color: green;'>✅ 创建页面浏览记录表成功</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ 创建页面浏览记录表失败: " . $e->getMessage() . "</p>";
    }
    
    // 4. 创建每日浏览统计表
    echo "<h3>4. 创建每日浏览统计表</h3>";
    try {
        $db->query("CREATE TABLE IF NOT EXISTS daily_browse_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL COMMENT '用户ID',
            reader_id INT DEFAULT NULL COMMENT '占卜师ID',
            user_type ENUM('user', 'reader') NOT NULL COMMENT '用户类型',
            browse_date DATE NOT NULL COMMENT '浏览日期',
            total_pages INT DEFAULT 0 COMMENT '浏览页面总数',
            total_rewards INT DEFAULT 0 COMMENT '获得奖励总数',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_daily_stats (user_id, reader_id, user_type, browse_date),
            INDEX idx_user_type_date (user_type, browse_date),
            INDEX idx_user_id (user_id),
            INDEX idx_reader_id (reader_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='每日浏览统计表'");
        echo "<p style='color: green;'>✅ 创建每日浏览统计表成功</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ 创建每日浏览统计表失败: " . $e->getMessage() . "</p>";
    }
    
    // 5. 为用户表添加邮箱验证字段
    echo "<h3>5. 更新用户表结构</h3>";
    try {
        $db->query("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) DEFAULT 0 COMMENT '邮箱是否已验证'");
        echo "<p style='color: green;'>✅ 添加用户邮箱验证字段成功</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p style='color: blue;'>ℹ️ 用户邮箱验证字段已存在</p>";
        } else {
            echo "<p style='color: red;'>❌ 添加用户邮箱验证字段失败: " . $e->getMessage() . "</p>";
        }
    }
    
    try {
        $db->query("ALTER TABLE readers ADD COLUMN email_verified TINYINT(1) DEFAULT 0 COMMENT '邮箱是否已验证'");
        echo "<p style='color: green;'>✅ 添加占卜师邮箱验证字段成功</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p style='color: blue;'>ℹ️ 占卜师邮箱验证字段已存在</p>";
        } else {
            echo "<p style='color: red;'>❌ 添加占卜师邮箱验证字段失败: " . $e->getMessage() . "</p>";
        }
    }
    
    // 6. 创建密码重置令牌表
    echo "<h3>6. 创建密码重置令牌表</h3>";
    try {
        $db->query("CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL COMMENT '用户ID',
            reader_id INT DEFAULT NULL COMMENT '占卜师ID',
            user_type ENUM('user', 'reader') NOT NULL COMMENT '用户类型',
            email VARCHAR(255) NOT NULL COMMENT '邮箱地址',
            token VARCHAR(255) NOT NULL COMMENT '重置令牌',
            expires_at TIMESTAMP NOT NULL COMMENT '过期时间',
            used TINYINT(1) DEFAULT 0 COMMENT '是否已使用',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_email (email),
            INDEX idx_expires_at (expires_at),
            INDEX idx_user_id (user_id),
            INDEX idx_reader_id (reader_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='密码重置令牌表'");
        echo "<p style='color: green;'>✅ 创建密码重置令牌表成功</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ 创建密码重置令牌表失败: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3>✅ 数据库更新完成！</h3>";
    echo "<p><a href='admin/dashboard.php'>返回管理后台</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 数据库连接失败: " . $e->getMessage() . "</p>";
}
?>
