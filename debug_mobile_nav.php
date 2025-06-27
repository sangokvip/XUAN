<?php
// 简单的调试页面，检查移动端导航
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>移动端导航调试</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/reader.css">
    <style>
        /* 调试样式 */
        .debug-info {
            background: #f0f0f0;
            padding: 10px;
            margin: 10px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
        }
        
        /* 强制显示移动端导航用于调试 */
        .mobile-nav-grid {
            display: grid !important;
            border: 2px solid red !important;
        }
        
        .mobile-nav-item {
            border: 1px solid blue !important;
        }
        
        /* 隐藏侧栏 */
        .reader-sidebar {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="debug-info">
        <strong>调试信息:</strong><br>
        - 当前时间: <?php echo date('Y-m-d H:i:s'); ?><br>
        - 用户代理: <?php echo h($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'); ?><br>
        - 屏幕宽度: <span id="screenWidth"></span>px<br>
        - 移动端导航应该显示红色边框<br>
        - 导航项应该显示蓝色边框
    </div>

    <div class="reader-container">
        <!-- 移动端网格导航 -->
        <?php include 'includes/reader_mobile_nav.php'; ?>

        <div class="reader-sidebar">
            <p>这是侧栏，在移动端应该被隐藏</p>
        </div>
        
        <div class="reader-content">
            <h1>移动端导航调试页面</h1>
            <p>这个页面用于调试移动端网格导航的显示问题。</p>
            
            <div class="debug-info">
                <strong>检查项目:</strong><br>
                ✓ CSS文件是否正确加载<br>
                ✓ 移动端导航是否显示<br>
                ✓ 侧栏是否被隐藏<br>
                ✓ 网格布局是否正确
            </div>
        </div>
    </div>

    <script>
        // 显示屏幕宽度
        document.getElementById('screenWidth').textContent = window.innerWidth;
        
        // 监听窗口大小变化
        window.addEventListener('resize', function() {
            document.getElementById('screenWidth').textContent = window.innerWidth;
        });
        
        // 检查CSS规则
        console.log('=== 移动端导航调试信息 ===');
        
        const mobileNav = document.querySelector('.mobile-nav-grid');
        if (mobileNav) {
            console.log('✓ 找到移动端导航元素');
            console.log('显示状态:', window.getComputedStyle(mobileNav).display);
            console.log('网格列:', window.getComputedStyle(mobileNav).gridTemplateColumns);
        } else {
            console.log('✗ 未找到移动端导航元素');
        }
        
        const sidebar = document.querySelector('.reader-sidebar');
        if (sidebar) {
            console.log('侧栏显示状态:', window.getComputedStyle(sidebar).display);
        }
        
        // 检查媒体查询
        if (window.matchMedia('(max-width: 768px)').matches) {
            console.log('✓ 当前符合移动端媒体查询条件');
        } else {
            console.log('✗ 当前不符合移动端媒体查询条件');
        }
    </script>
</body>
</html>
