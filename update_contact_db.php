<?php
require_once 'config/config.php';

echo "<h1>更新塔罗师联系方式字段</h1>";

try {
    $db = Database::getInstance();
    
    echo "<h2>1. 添加新的联系方式字段</h2>";
    
    // 检查字段是否已存在
    $columns = $db->fetchAll("SHOW COLUMNS FROM readers");
    $existingColumns = array_column($columns, 'Field');
    
    $newFields = [
        'wechat' => "ADD COLUMN wechat VARCHAR(100) DEFAULT NULL COMMENT '微信号'",
        'qq' => "ADD COLUMN qq VARCHAR(50) DEFAULT NULL COMMENT 'QQ号'",
        'xiaohongshu' => "ADD COLUMN xiaohongshu VARCHAR(100) DEFAULT NULL COMMENT '小红书账号'",
        'douyin' => "ADD COLUMN douyin VARCHAR(100) DEFAULT NULL COMMENT '抖音账号'",
        'other_contact' => "ADD COLUMN other_contact TEXT DEFAULT NULL COMMENT '其他联系方式'"
    ];
    
    foreach ($newFields as $fieldName => $sql) {
        if (!in_array($fieldName, $existingColumns)) {
            try {
                $db->query("ALTER TABLE readers " . $sql);
                echo "<p>✅ 成功添加字段: {$fieldName}</p>";
            } catch (Exception $e) {
                echo "<p>❌ 添加字段 {$fieldName} 失败: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p>ℹ️ 字段 {$fieldName} 已存在，跳过</p>";
        }
    }
    
    echo "<h2>2. 更新字段注释</h2>";
    
    try {
        $db->query("ALTER TABLE readers MODIFY COLUMN contact_info TEXT COMMENT '联系信息描述'");
        echo "<p>✅ 更新 contact_info 字段注释成功</p>";
    } catch (Exception $e) {
        echo "<p>❌ 更新 contact_info 字段注释失败: " . $e->getMessage() . "</p>";
    }
    
    try {
        $db->query("ALTER TABLE readers MODIFY COLUMN phone VARCHAR(20) COMMENT '电话号码'");
        echo "<p>✅ 更新 phone 字段注释成功</p>";
    } catch (Exception $e) {
        echo "<p>❌ 更新 phone 字段注释失败: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>3. 查看更新后的表结构</h2>";
    
    $columns = $db->fetchAll("SHOW COLUMNS FROM readers");
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
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
    
    echo "<h2>4. 测试数据插入</h2>";
    
    // 测试插入一条包含新字段的数据
    try {
        $testData = [
            'wechat' => 'test_wechat_123',
            'qq' => '123456789',
            'xiaohongshu' => 'test_xiaohongshu',
            'douyin' => 'test_douyin',
            'other_contact' => '其他联系方式测试'
        ];
        
        // 查找一个现有的塔罗师进行更新测试
        $reader = $db->fetchOne("SELECT id FROM readers LIMIT 1");
        if ($reader) {
            $db->update('readers', $testData, 'id = ?', [$reader['id']]);
            echo "<p>✅ 测试数据更新成功</p>";
            
            // 验证数据
            $updatedReader = $db->fetchOne("SELECT wechat, qq, xiaohongshu, douyin, other_contact FROM readers WHERE id = ?", [$reader['id']]);
            echo "<p>验证数据:</p>";
            echo "<ul>";
            foreach ($testData as $field => $value) {
                echo "<li>{$field}: " . htmlspecialchars($updatedReader[$field] ?? 'NULL') . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>⚠️ 没有找到现有塔罗师数据进行测试</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ 测试数据插入失败: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>✅ 数据库更新完成！</h2>";
    echo "<p>现在可以在塔罗师资料中使用新的联系方式字段了。</p>";
    
} catch (Exception $e) {
    echo "<h2>❌ 数据库连接失败</h2>";
    echo "<p>错误信息: " . $e->getMessage() . "</p>";
}

echo "<p><a href='reader/settings.php'>前往塔罗师设置页面测试</a></p>";
echo "<p><small>更新完成后请删除此文件 (update_contact_db.php)</small></p>";
?>
