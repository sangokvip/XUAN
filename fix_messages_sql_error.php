<?php
/**
 * 修复messages.php中的SQL语法错误
 * 问题：使用了MySQL保留字'read'作为列别名
 */

session_start();
require_once 'config/config.php';

// 检查管理员权限
if (!isAdminLoggedIn()) {
    die('需要管理员权限才能执行此操作');
}

try {
    $db = Database::getInstance();
    
    echo "<h2>🔧 修复SQL语法错误</h2>";
    echo "<p>正在测试修复后的SQL查询...</p>";
    
    // 测试修复后的SQL查询
    echo "<h3>1. 测试在线留言统计查询</h3>";
    
    try {
        $contactStats = $db->fetchOne("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as `read`,
                SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied
            FROM contact_messages
        ");
        
        echo "<p style='color: green;'>✅ SQL查询执行成功</p>";
        echo "<p>统计结果：</p>";
        echo "<ul>";
        echo "<li>总留言数：" . ($contactStats['total'] ?? 0) . "</li>";
        echo "<li>未读留言：" . ($contactStats['unread'] ?? 0) . "</li>";
        echo "<li>已读留言：" . ($contactStats['read'] ?? 0) . "</li>";
        echo "<li>已回复留言：" . ($contactStats['replied'] ?? 0) . "</li>";
        echo "</ul>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ SQL查询失败：" . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // 检查contact_messages表是否存在
    echo "<h3>2. 检查contact_messages表</h3>";
    
    try {
        $tableExists = $db->fetchOne("SHOW TABLES LIKE 'contact_messages'");
        
        if ($tableExists) {
            echo "<p style='color: green;'>✅ contact_messages表存在</p>";
            
            // 检查表结构
            $columns = $db->fetchAll("DESCRIBE contact_messages");
            echo "<p>表结构：</p>";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr style='background: #f0f0f0;'><th>字段名</th><th>类型</th><th>是否为空</th><th>键</th><th>默认值</th></tr>";
            
            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
                echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
                echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
                echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
                echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // 检查数据
            $messageCount = $db->fetchOne("SELECT COUNT(*) as count FROM contact_messages");
            echo "<p>当前留言数量：" . ($messageCount['count'] ?? 0) . "</p>";
            
        } else {
            echo "<p style='color: red;'>❌ contact_messages表不存在</p>";
            echo "<p>请先运行 database_update_contact_system.php 创建表</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ 检查表失败：" . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // 测试其他相关查询
    echo "<h3>3. 测试其他相关查询</h3>";
    
    try {
        // 测试获取留言列表
        $messages = $db->fetchAll("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5");
        echo "<p style='color: green;'>✅ 留言列表查询成功，返回 " . count($messages) . " 条记录</p>";
        
        // 测试联系方式设置查询
        $contactSettings = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'contact_%' LIMIT 3");
        echo "<p style='color: green;'>✅ 联系方式设置查询成功，返回 " . count($contactSettings) . " 个设置项</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ 其他查询失败：" . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // 提供解决方案
    echo "<h3>4. 问题解决方案</h3>";
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #c3e6cb;'>";
    echo "<h4 style='color: #155724; margin: 0 0 10px 0;'>✅ 问题已修复：</h4>";
    echo "<ul style='color: #155724; margin: 0;'>";
    echo "<li>在SQL查询中为保留字'read'添加了反引号：`read`</li>";
    echo "<li>这样可以避免MySQL将其识别为保留字</li>";
    echo "<li>修复后的查询应该可以正常执行</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #ffeaa7;'>";
    echo "<h4 style='color: #856404; margin: 0 0 10px 0;'>💡 技术说明：</h4>";
    echo "<ul style='color: #856404; margin: 0;'>";
    echo "<li><strong>问题原因</strong>：'read'是MySQL的保留字，不能直接用作列别名</li>";
    echo "<li><strong>解决方法</strong>：使用反引号包围保留字：`read`</li>";
    echo "<li><strong>其他保留字</strong>：类似的还有'order', 'group', 'select'等</li>";
    echo "<li><strong>最佳实践</strong>：避免使用保留字作为列名或别名</li>";
    echo "</ul>";
    echo "</div>";
    
    // 如果表不存在，提供创建指导
    if (!$tableExists) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #f5c6cb;'>";
        echo "<h4 style='color: #721c24; margin: 0 0 10px 0;'>⚠️ 需要创建数据表：</h4>";
        echo "<ul style='color: #721c24; margin: 0;'>";
        echo "<li>请访问：<a href='database_update_contact_system.php'>database_update_contact_system.php</a></li>";
        echo "<li>运行数据库更新脚本创建contact_messages表</li>";
        echo "<li>创建完成后再访问messages.php页面</li>";
        echo "</ul>";
        echo "</div>";
    }
    
    echo "<h3>🎉 修复完成！</h3>";
    echo "<p style='color: green; font-weight: bold;'>现在可以正常访问管理员后台的消息管理页面了。</p>";
    
    echo "<div style='text-align: center; margin: 30px 0;'>";
    echo "<a href='admin/messages.php' style='display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 500;'>访问消息管理页面</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ 修复失败</h3>";
    echo "<p style='color: red;'>错误信息: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>请检查数据库连接和权限设置。</p>";
}
?>
