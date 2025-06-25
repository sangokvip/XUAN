<?php
// 测试数据库结构脚本
// 这个文件用于验证install.php中的数据库结构是否正确

// 包含install.php中的数据库结构函数
function getCompleteDbStructure() {
    return [
        // 用户表
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            gender ENUM('male', 'female') DEFAULT NULL COMMENT '性别：male-男，female-女',
            avatar VARCHAR(255) DEFAULT NULL COMMENT '头像路径',
            tata_coin INT DEFAULT 0 COMMENT 'Tata Coin余额，通过系统发放',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // 管理员表
        "CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // 系统设置表
        "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 插入默认设置
        "INSERT INTO settings (setting_key, setting_value, description) VALUES
        ('site_name', '塔罗师展示平台', '网站名称'),
        ('site_description', '专业塔罗师展示平台', '网站描述'),
        ('max_featured_readers', '6', '首页最大推荐塔罗师数量'),
        ('registration_link_hours', '24', '注册链接有效期（小时）')
        ON DUPLICATE KEY UPDATE setting_key = setting_key"
    ];
}

// 分析SQL语句
$sqlStatements = getCompleteDbStructure();
echo "<h1>数据库结构分析</h1>";
echo "<p>总共 " . count($sqlStatements) . " 个SQL语句</p>";

echo "<h2>表结构：</h2>";
$tableCount = 0;
$insertCount = 0;

foreach ($sqlStatements as $index => $sql) {
    $sql = trim($sql);
    if (!empty($sql)) {
        if (preg_match('/CREATE TABLE.*?`?(\w+)`?\s*\(/i', $sql, $matches)) {
            $tableCount++;
            echo "<div style='margin: 10px 0; padding: 10px; background: #f0f8ff; border-left: 4px solid #007bff;'>";
            echo "<strong>表 {$tableCount}: {$matches[1]}</strong><br>";
            echo "<small>SQL语句 " . ($index + 1) . "</small>";
            echo "</div>";
        } elseif (preg_match('/INSERT INTO\s+(\w+)/i', $sql, $matches)) {
            $insertCount++;
            echo "<div style='margin: 10px 0; padding: 10px; background: #f0fff0; border-left: 4px solid #28a745;'>";
            echo "<strong>数据插入 {$insertCount}: {$matches[1]}</strong><br>";
            echo "<small>SQL语句 " . ($index + 1) . "</small>";
            echo "</div>";
        }
    }
}

echo "<h2>统计：</h2>";
echo "<ul>";
echo "<li>创建表数量: {$tableCount}</li>";
echo "<li>数据插入数量: {$insertCount}</li>";
echo "<li>总SQL语句数量: " . count($sqlStatements) . "</li>";
echo "</ul>";

// 检查关键表是否存在
$requiredTables = ['users', 'admins', 'settings', 'readers'];
$foundTables = [];

foreach ($sqlStatements as $sql) {
    foreach ($requiredTables as $table) {
        if (preg_match("/CREATE TABLE.*?{$table}/i", $sql)) {
            $foundTables[] = $table;
        }
    }
}

echo "<h2>关键表检查：</h2>";
foreach ($requiredTables as $table) {
    $status = in_array($table, $foundTables) ? "✓" : "✗";
    $color = in_array($table, $foundTables) ? "green" : "red";
    echo "<div style='color: {$color};'>{$status} {$table}</div>";
}

if (count($foundTables) === count($requiredTables)) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    echo "✓ 所有关键表都已包含在数据库结构中！";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    echo "✗ 缺少关键表，请检查数据库结构！";
    echo "</div>";
}
?>
