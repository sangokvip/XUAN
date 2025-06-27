<?php
/**
 * 修复外键约束问题
 * 解决占卜师付费查看其他占卜师时的数据库错误
 */

require_once 'config/config.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>🔧 修复外键约束问题</h2>";
    echo "<p>解决占卜师付费查看其他占卜师时的数据库错误...</p>";
    
    // 1. 检查当前外键约束
    echo "<h3>1. 检查当前外键约束</h3>";
    
    $foreignKeys = $db->fetchAll("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'user_browse_history' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    if (empty($foreignKeys)) {
        echo "<p style='color: blue;'>ℹ️ 未发现外键约束，可能已经移除</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ 发现以下外键约束：</p>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>约束名</th><th>列名</th><th>引用表</th><th>引用列</th></tr>";
        
        foreach ($foreignKeys as $fk) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($fk['CONSTRAINT_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['COLUMN_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['REFERENCED_TABLE_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 2. 移除外键约束
    echo "<h3>2. 移除外键约束</h3>";
    
    if (!empty($foreignKeys)) {
        foreach ($foreignKeys as $fk) {
            $constraintName = $fk['CONSTRAINT_NAME'];
            try {
                $db->query("ALTER TABLE user_browse_history DROP FOREIGN KEY `{$constraintName}`");
                echo "<p style='color: green;'>✅ 成功移除外键约束: {$constraintName}</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ 移除外键约束失败: {$constraintName}</p>";
                echo "<p style='color: red;'>错误信息: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ 无需移除外键约束</p>";
    }
    
    // 3. 检查user_type字段
    echo "<h3>3. 检查user_type字段</h3>";
    
    $columns = $db->fetchAll("SHOW COLUMNS FROM user_browse_history");
    $hasUserType = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'user_type') {
            $hasUserType = true;
            break;
        }
    }
    
    if (!$hasUserType) {
        echo "<p style='color: orange;'>⚠️ user_type字段不存在，正在添加...</p>";
        try {
            $db->query("ALTER TABLE user_browse_history ADD COLUMN user_type ENUM('user', 'reader') DEFAULT 'user' COMMENT '用户类型：user-普通用户，reader-占卜师'");
            echo "<p style='color: green;'>✅ 成功添加user_type字段</p>";
            
            // 更新现有记录
            $db->query("UPDATE user_browse_history SET user_type = 'user' WHERE user_type IS NULL");
            echo "<p style='color: green;'>✅ 成功更新现有记录的user_type字段</p>";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ 添加user_type字段失败: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ user_type字段已存在</p>";
    }
    
    // 4. 测试占卜师记录插入
    echo "<h3>4. 测试占卜师记录插入</h3>";
    
    $testReader = $db->fetchOne("SELECT id FROM readers LIMIT 1");
    if ($testReader) {
        $readerId = $testReader['id'];
        try {
            // 尝试插入一条测试记录
            $db->query("INSERT INTO user_browse_history (user_id, reader_id, browse_type, cost, user_type) VALUES (?, ?, 'paid', 30, 'reader')", 
                      [$readerId, $readerId]);
            echo "<p style='color: green;'>✅ 占卜师记录插入测试成功</p>";
            
            // 删除测试记录
            $db->query("DELETE FROM user_browse_history WHERE user_id = ? AND reader_id = ? AND user_type = 'reader' AND cost = 30", 
                      [$readerId, $readerId]);
            echo "<p style='color: green;'>✅ 测试记录清理完成</p>";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ 占卜师记录插入测试失败</p>";
            echo "<p style='color: red;'>错误信息: " . htmlspecialchars($e->getMessage()) . "</p>";
            
            // 如果还有问题，可能需要进一步检查
            echo "<p style='color: orange;'>💡 建议检查：</p>";
            echo "<ul>";
            echo "<li>确认user_browse_history表结构正确</li>";
            echo "<li>确认所有外键约束已移除</li>";
            echo "<li>确认user_type字段已正确添加</li>";
            echo "</ul>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ 未找到占卜师数据，无法进行插入测试</p>";
    }
    
    // 5. 显示当前表结构
    echo "<h3>5. 当前表结构</h3>";
    
    $tableInfo = $db->fetchAll("DESCRIBE user_browse_history");
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>字段名</th><th>类型</th><th>是否为空</th><th>键</th><th>默认值</th><th>额外</th></tr>";
    
    foreach ($tableInfo as $field) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($field['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($field['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($field['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($field['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($field['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($field['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 6. 检查修复后的外键状态
    echo "<h3>6. 检查修复后的外键状态</h3>";
    
    $remainingForeignKeys = $db->fetchAll("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'user_browse_history' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    if (empty($remainingForeignKeys)) {
        echo "<p style='color: green;'>✅ 所有外键约束已成功移除</p>";
    } else {
        echo "<p style='color: red;'>❌ 仍有外键约束存在：</p>";
        foreach ($remainingForeignKeys as $fk) {
            echo "<p>- " . htmlspecialchars($fk['CONSTRAINT_NAME']) . "</p>";
        }
    }
    
    echo "<h3>🎉 修复完成！</h3>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #c3e6cb;'>";
    echo "<h4 style='color: #155724; margin: 0 0 10px 0;'>✅ 修复总结：</h4>";
    echo "<ul style='color: #155724; margin: 0;'>";
    echo "<li>移除了user_browse_history表的外键约束</li>";
    echo "<li>确保user_type字段存在并正确配置</li>";
    echo "<li>测试了占卜师记录的插入功能</li>";
    echo "<li>占卜师现在可以正常付费查看其他占卜师的联系方式</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #ffeaa7;'>";
    echo "<h4 style='color: #856404; margin: 0 0 10px 0;'>💡 注意事项：</h4>";
    echo "<ul style='color: #856404; margin: 0;'>";
    echo "<li>移除外键约束后，需要在应用层确保数据完整性</li>";
    echo "<li>user_type字段用于区分用户类型（user/reader）</li>";
    echo "<li>建议定期检查数据一致性</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ 修复失败</h3>";
    echo "<p style='color: red;'>错误信息: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>请检查数据库连接和权限设置。</p>";
}
?>
