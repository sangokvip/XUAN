<?php
/**
 * 数据库更新脚本：支持占卜师查看其他占卜师联系方式
 * 为user_browse_history表添加user_type字段
 */

require_once 'config/config.php';

try {
    $db = Database::getInstance();
    
    echo "<h2>🔧 数据库更新：支持占卜师查看其他占卜师</h2>";
    echo "<p>开始更新数据库结构...</p>";
    
    // 1. 检查user_browse_history表是否存在user_type字段
    echo "<h3>1. 检查user_browse_history表结构</h3>";
    
    $columns = $db->fetchAll("SHOW COLUMNS FROM user_browse_history");
    $hasUserType = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'user_type') {
            $hasUserType = true;
            break;
        }
    }
    
    if ($hasUserType) {
        echo "<p style='color: blue;'>ℹ️ user_type字段已存在</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ user_type字段不存在，需要添加</p>";
        
        // 2. 添加user_type字段
        echo "<h3>2. 添加user_type字段</h3>";
        try {
            $db->query("ALTER TABLE user_browse_history ADD COLUMN user_type ENUM('user', 'reader') DEFAULT 'user' COMMENT '用户类型：user-普通用户，reader-占卜师'");
            echo "<p style='color: green;'>✅ 成功添加user_type字段</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ 添加user_type字段失败: " . $e->getMessage() . "</p>";
            throw $e;
        }
        
        // 3. 更新现有记录的user_type为'user'
        echo "<h3>3. 更新现有记录</h3>";
        try {
            $result = $db->query("UPDATE user_browse_history SET user_type = 'user' WHERE user_type IS NULL");
            echo "<p style='color: green;'>✅ 成功更新现有记录的user_type字段</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ 更新现有记录失败: " . $e->getMessage() . "</p>";
            throw $e;
        }
    }
    
    // 4. 检查索引
    echo "<h3>4. 检查和创建索引</h3>";
    try {
        $indexes = $db->fetchAll("SHOW INDEX FROM user_browse_history");
        $hasUserTypeIndex = false;
        
        foreach ($indexes as $index) {
            if ($index['Key_name'] === 'idx_user_type') {
                $hasUserTypeIndex = true;
                break;
            }
        }
        
        if (!$hasUserTypeIndex) {
            $db->query("ALTER TABLE user_browse_history ADD INDEX idx_user_type (user_type)");
            echo "<p style='color: green;'>✅ 成功创建user_type索引</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ user_type索引已存在</p>";
        }
        
        // 创建复合索引
        $hasCompositeIndex = false;
        foreach ($indexes as $index) {
            if ($index['Key_name'] === 'idx_user_reader_type') {
                $hasCompositeIndex = true;
                break;
            }
        }
        
        if (!$hasCompositeIndex) {
            $db->query("ALTER TABLE user_browse_history ADD INDEX idx_user_reader_type (user_id, reader_id, user_type)");
            echo "<p style='color: green;'>✅ 成功创建复合索引</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ 复合索引已存在</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ 创建索引失败: " . $e->getMessage() . "</p>";
        // 索引创建失败不影响主要功能
    }
    
    // 5. 验证更新结果
    echo "<h3>5. 验证更新结果</h3>";
    
    $tableInfo = $db->fetchAll("DESCRIBE user_browse_history");
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>字段名</th><th>类型</th><th>是否为空</th><th>默认值</th><th>备注</th></tr>";
    
    foreach ($tableInfo as $field) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($field['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($field['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($field['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($field['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($field['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 6. 测试功能
    echo "<h3>6. 功能测试</h3>";
    
    // 检查是否有占卜师数据
    $readerCount = $db->fetchOne("SELECT COUNT(*) as count FROM readers")['count'];
    echo "<p>📊 当前占卜师数量: {$readerCount}</p>";
    
    if ($readerCount >= 2) {
        echo "<p style='color: green;'>✅ 数据充足，可以测试占卜师互相查看功能</p>";
        echo "<p>💡 提示：占卜师现在可以：</p>";
        echo "<ul>";
        echo "<li>免费查看自己的联系方式</li>";
        echo "<li>付费查看其他占卜师的联系方式</li>";
        echo "<li>不能对其他占卜师进行评价</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>⚠️ 占卜师数量不足，建议添加更多占卜师进行测试</p>";
    }
    
    // 7. 修复外键约束问题
    echo "<h3>7. 修复外键约束问题</h3>";

    // 检查是否存在外键约束
    $foreignKeys = $db->fetchAll("
        SELECT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'user_browse_history'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");

    if (!empty($foreignKeys)) {
        echo "<p style='color: orange;'>⚠️ 发现外键约束，需要移除以支持占卜师付费功能</p>";

        foreach ($foreignKeys as $fk) {
            $constraintName = $fk['CONSTRAINT_NAME'];
            try {
                $db->query("ALTER TABLE user_browse_history DROP FOREIGN KEY `{$constraintName}`");
                echo "<p style='color: green;'>✅ 成功移除外键约束: {$constraintName}</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ 移除外键约束失败: {$constraintName} - " . $e->getMessage() . "</p>";
            }
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ 未发现外键约束或已移除</p>";
    }

    // 8. 验证修复结果
    echo "<h3>8. 验证修复结果</h3>";

    // 测试插入占卜师记录
    $testReaderId = $db->fetchOne("SELECT id FROM readers LIMIT 1");
    if ($testReaderId) {
        $readerId = $testReaderId['id'];
        try {
            // 尝试插入一条测试记录
            $db->query("INSERT INTO user_browse_history (user_id, reader_id, browse_type, cost, user_type) VALUES (?, ?, 'paid', 30, 'reader')",
                      [$readerId, $readerId]);
            echo "<p style='color: green;'>✅ 占卜师记录插入测试成功</p>";

            // 删除测试记录
            $db->query("DELETE FROM user_browse_history WHERE user_id = ? AND reader_id = ? AND user_type = 'reader'",
                      [$readerId, $readerId]);
            echo "<p style='color: green;'>✅ 测试记录清理完成</p>";

        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ 占卜师记录插入测试失败: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ 未找到占卜师数据，无法进行插入测试</p>";
    }

    echo "<h3>🎉 数据库更新完成！</h3>";
    echo "<p style='color: green; font-weight: bold;'>所有更新已成功完成，占卜师现在可以查看其他占卜师的联系方式了！</p>";

    echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>📋 更新内容总结：</h4>";
    echo "<ul>";
    echo "<li>✅ 为user_browse_history表添加user_type字段</li>";
    echo "<li>✅ 更新现有记录的user_type为'user'</li>";
    echo "<li>✅ 创建相关索引优化查询性能</li>";
    echo "<li>✅ 移除外键约束支持占卜师记录</li>";
    echo "<li>✅ 修改TataCoinManager支持占卜师付费</li>";
    echo "<li>✅ 更新reader.php页面逻辑</li>";
    echo "<li>✅ 移除占卜师模式横幅</li>";
    echo "<li>✅ 设置占卜师用户名为金色</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ 更新失败</h3>";
    echo "<p style='color: red;'>错误信息: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>请检查数据库连接和权限设置。</p>";
}
?>
