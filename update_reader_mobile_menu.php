<?php
/**
 * 为所有占卜师后台页面添加移动端菜单支持
 */

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>更新占卜师后台移动端菜单</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #d4af37; padding-bottom: 10px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #d4af37; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 0 0; }
        .btn:hover { background: #b8941f; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 更新占卜师后台移动端菜单</h1>";

// 需要更新的占卜师后台页面
$readerPages = [
    'reader/settings.php',
    'reader/messages.php', 
    'reader/view_records.php',
    'reader/tata_coin_guide.php',
    'reader/invitation.php'
];

$updatedFiles = [];
$errors = [];

foreach ($readerPages as $filePath) {
    if (!file_exists($filePath)) {
        $errors[] = "文件不存在: $filePath";
        continue;
    }
    
    echo "<h3>处理文件: $filePath</h3>";
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $updated = false;
    
    // 1. 检查是否已有移动端菜单按钮
    if (strpos($content, 'mobile-menu-toggle') === false) {
        // 查找 reader-container 并添加移动端菜单
        $pattern = '/(<div class="reader-container">\s*<div class="reader-sidebar">)/';
        $replacement = '<!-- 移动端菜单按钮 -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <span id="menuIcon">☰</span>
    </button>
    
    <!-- 移动端侧栏覆盖层 -->
    <div class="mobile-sidebar-overlay" id="mobileSidebarOverlay"></div>
    
    <div class="reader-container">
        <div class="reader-sidebar" id="readerSidebar">';
        
        $newContent = preg_replace($pattern, $replacement, $content);
        if ($newContent !== $content) {
            $content = $newContent;
            $updated = true;
            echo "<div class='success'>✅ 添加了移动端菜单按钮和覆盖层</div>";
        }
    } else {
        echo "<div class='info'>ℹ️ 移动端菜单按钮已存在</div>";
    }
    
    // 2. 检查是否已有JavaScript支持
    if (strpos($content, 'reader-mobile-menu.js') === false) {
        // 在 </body> 前添加JavaScript
        $pattern = '/(<\/body>)/';
        $replacement = '    <script src="../assets/js/reader-mobile-menu.js"></script>
</body>';
        
        $newContent = preg_replace($pattern, $replacement, $content);
        if ($newContent !== $content) {
            $content = $newContent;
            $updated = true;
            echo "<div class='success'>✅ 添加了移动端菜单JavaScript支持</div>";
        }
    } else {
        echo "<div class='info'>ℹ️ JavaScript支持已存在</div>";
    }
    
    // 3. 确保侧栏有正确的ID
    if (strpos($content, 'id="readerSidebar"') === false) {
        $pattern = '/(<div class="reader-sidebar"(?![^>]*id=))/';
        $replacement = '<div class="reader-sidebar" id="readerSidebar"';
        
        $newContent = preg_replace($pattern, $replacement, $content);
        if ($newContent !== $content) {
            $content = $newContent;
            $updated = true;
            echo "<div class='success'>✅ 为侧栏添加了ID</div>";
        }
    } else {
        echo "<div class='info'>ℹ️ 侧栏ID已存在</div>";
    }
    
    // 保存文件
    if ($updated) {
        if (file_put_contents($filePath, $content)) {
            $updatedFiles[] = $filePath;
            echo "<div class='success'><strong>✅ 文件更新成功: $filePath</strong></div>";
        } else {
            $errors[] = "无法写入文件: $filePath";
        }
    } else {
        echo "<div class='info'>📝 文件无需更新: $filePath</div>";
    }
    
    echo "<hr>";
}

// 显示总结
echo "<h2>📊 更新总结</h2>";

if (!empty($updatedFiles)) {
    echo "<div class='success'>";
    echo "<h3>✅ 成功更新的文件 (" . count($updatedFiles) . "个):</h3>";
    echo "<ul>";
    foreach ($updatedFiles as $file) {
        echo "<li>$file</li>";
    }
    echo "</ul>";
    echo "</div>";
}

if (!empty($errors)) {
    echo "<div class='error'>";
    echo "<h3>❌ 错误信息:</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
    echo "</div>";
}

echo "<div class='info'>";
echo "<h3>📱 移动端菜单功能说明:</h3>";
echo "<ul>";
echo "<li><strong>汉堡菜单按钮</strong>: 固定在左上角，点击打开/关闭侧栏</li>";
echo "<li><strong>侧栏滑出</strong>: 从左侧滑出，覆盖在内容上方</li>";
echo "<li><strong>覆盖层</strong>: 点击空白区域关闭菜单</li>";
echo "<li><strong>自动关闭</strong>: 点击菜单项后自动关闭</li>";
echo "<li><strong>响应式</strong>: 桌面端自动隐藏移动端菜单</li>";
echo "<li><strong>键盘支持</strong>: ESC键关闭菜单</li>";
echo "<li><strong>触摸支持</strong>: 向左滑动关闭菜单</li>";
echo "</ul>";
echo "</div>";

echo "<div class='info'>";
echo "<h3>🎨 样式特点:</h3>";
echo "<ul>";
echo "<li><strong>金色主题</strong>: 与占卜师后台风格一致</li>";
echo "<li><strong>平滑动画</strong>: 菜单打开/关闭有过渡效果</li>";
echo "<li><strong>阴影效果</strong>: 侧栏有立体阴影</li>";
echo "<li><strong>状态指示</strong>: 按钮图标变化(☰ ↔ ✕)</li>";
echo "</ul>";
echo "</div>";

echo "
        <hr>
        <p><a href='reader/dashboard.php' class='btn'>测试占卜师后台</a></p>
        <p><a href='index.php' class='btn'>返回首页</a></p>
    </div>
</body>
</html>";
?>
