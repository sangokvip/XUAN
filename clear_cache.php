<?php
/**
 * 清除缓存并强制重新加载CSS
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>清除缓存</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success {
            color: #28a745;
            background: #d4edda;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #d4af37;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #b8941f;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 清除缓存</h1>
        
        <div class="success">
            ✅ CSS文件已添加版本号，强制重新加载
        </div>
        
        <div class="info">
            <strong>已更新的文件:</strong><br>
            • assets/css/reader.css - 移动端网格导航样式<br>
            • includes/reader_mobile_nav.php - 导航文字优化<br>
            • reader/dashboard.php - 添加CSS版本号<br>
            • reader/settings.php - 添加CSS版本号<br>
            • reader/messages.php - 添加CSS版本号<br>
            • reader/view_records.php - 添加CSS版本号<br>
            • reader/invitation.php - 添加CSS版本号<br>
            • reader/tata_coin_guide.php - 添加CSS版本号<br>
            • reader/profile.php - 添加CSS版本号<br>
        </div>
        
        <div class="info">
            <strong>移动端网格导航特性:</strong><br>
            • 3列网格布局，整齐美观<br>
            • 金色主题，与网站风格一致<br>
            • 简洁的图标+文字组合<br>
            • 完全隐藏传统侧栏<br>
            • 响应式设计，适配各种屏幕<br>
        </div>
        
        <p>
            <a href="reader/dashboard.php" class="btn">测试占卜师后台</a>
            <a href="debug_mobile_nav.php" class="btn">调试页面</a>
        </p>
        
        <div class="info">
            <strong>如果移动端导航仍未显示，请尝试:</strong><br>
            1. 强制刷新页面 (Ctrl+F5 或 Cmd+Shift+R)<br>
            2. 清除浏览器缓存<br>
            3. 在移动设备或开发者工具的移动端模式下查看<br>
            4. 检查浏览器控制台是否有错误信息
        </div>
        
        <script>
            // 自动检测移动端
            if (window.innerWidth <= 768) {
                document.body.style.background = '#e8f5e8';
                const container = document.querySelector('.container');
                container.innerHTML = '<h2>✅ 当前是移动端视图</h2>' + container.innerHTML;
            }
        </script>
    </div>
</body>
</html>
