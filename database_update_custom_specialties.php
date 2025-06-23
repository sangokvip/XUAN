<?php
require_once 'config/config.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>数据库更新：添加 custom_specialties 字段</h2>";
    
    // 检查字段是否已存在
    $checkField = $db->fetchOne("SHOW COLUMNS FROM readers LIKE 'custom_specialties'");
    
    if (!$checkField) {
        // 添加 custom_specialties 字段
        $db->query("ALTER TABLE readers ADD COLUMN custom_specialties VARCHAR(500) DEFAULT NULL COMMENT '自定义专长标签（最多3个，每个最多4字符）'");
        echo "<p style='color: green;'>✅ 成功添加 custom_specialties 字段</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ custom_specialties 字段已存在</p>";
    }
    
    // 显示更新后的表结构
    echo "<h3>readers 表结构：</h3>";
    $columns = $db->fetchAll("SHOW COLUMNS FROM readers");
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
    
    echo "<p style='color: green; font-weight: bold;'>数据库更新完成！</p>";
    echo "<p><a href='tag_readers.php?tag=感情'>测试标签页面</a> | <a href='search.php?q=塔罗'>测试搜索页面</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>详细信息: " . htmlspecialchars($e->getTraceAsString()) . "</p>";
}
?>
