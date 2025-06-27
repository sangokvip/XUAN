<?php
/**
 * 添加微博字段到占卜师表
 */

require_once 'config/config.php';

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>添加微博字段</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 0 0; }
        .btn:hover { background: #005a8b; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 添加微博字段到占卜师表</h1>";

try {
    $db = Database::getInstance();
    
    echo "<div class='info'>开始检查和添加微博字段...</div>";
    
    // 1. 检查 weibo 字段是否存在
    echo "<h3>1. 检查 weibo 字段</h3>";
    
    $columns = $db->fetchAll("SHOW COLUMNS FROM readers");
    $hasWeibo = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'weibo') {
            $hasWeibo = true;
            break;
        }
    }
    
    if (!$hasWeibo) {
        echo "<div class='info'>weibo 字段不存在，正在添加...</div>";
        
        $addColumnSQL = "ALTER TABLE readers ADD COLUMN weibo VARCHAR(100) DEFAULT NULL COMMENT '微博账号' AFTER xiaohongshu";
        $db->query($addColumnSQL);
        
        echo "<div class='success'>✅ weibo 字段添加成功！</div>";
    } else {
        echo "<div class='success'>✅ weibo 字段已存在</div>";
    }
    
    // 2. 检查 email_contact 字段是否存在
    echo "<h3>2. 检查 email_contact 字段</h3>";
    
    $hasEmailContact = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'email_contact') {
            $hasEmailContact = true;
            break;
        }
    }
    
    if (!$hasEmailContact) {
        echo "<div class='info'>email_contact 字段不存在，正在添加...</div>";
        
        $addColumnSQL = "ALTER TABLE readers ADD COLUMN email_contact VARCHAR(100) DEFAULT NULL COMMENT '联系邮箱' AFTER weibo";
        $db->query($addColumnSQL);
        
        echo "<div class='success'>✅ email_contact 字段添加成功！</div>";
    } else {
        echo "<div class='success'>✅ email_contact 字段已存在</div>";
    }
    
    // 3. 显示当前表结构
    echo "<h3>3. 当前联系方式相关字段</h3>";
    $columns = $db->fetchAll("SHOW COLUMNS FROM readers");
    
    echo "<pre>";
    echo "联系方式相关字段：\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($columns as $column) {
        if (in_array($column['Field'], ['phone', 'wechat', 'qq', 'xiaohongshu', 'weibo', 'email_contact', 'douyin', 'other_contact', 'contact_info'])) {
            printf("%-15s %-20s %-8s %s\n", 
                $column['Field'], 
                $column['Type'], 
                $column['Null'], 
                $column['Comment'] ?? ''
            );
        }
    }
    echo "</pre>";
    
    echo "<div class='success'><strong>🎉 字段更新完成！</strong></div>";
    echo "<div class='info'>现在可以在注册页面使用微博字段，在设置页面使用微博和联系邮箱字段了。</div>";
    echo "<div class='info'><strong>注意：</strong>注册页面不包含联系邮箱字段，因为已有注册邮箱。联系邮箱仅在后台设置中可用。</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ 更新过程中出现错误：" . $e->getMessage() . "</div>";
}

echo "
        <hr>
        <p><a href='index.php' class='btn'>返回首页</a></p>
        <p><a href='auth/reader_register.php' class='btn'>测试注册页面</a></p>
        <p><a href='reader/settings.php' class='btn'>测试设置页面</a></p>
    </div>
</body>
</html>";
?>
