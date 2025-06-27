<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="admin-nav">
    <ul>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" 
               class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <span class="icon">📊</span>
                后台首页
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/readers.php" 
               class="<?php echo $current_page === 'readers.php' ? 'active' : ''; ?>">
                <span class="icon">🔮</span>
                占卜师管理
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/users.php"
               class="<?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                <span class="icon">👥</span>
                用户管理
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/tata_coin.php"
               class="<?php echo in_array($current_page, ['tata_coin.php', 'tata_coin_transactions.php']) ? 'active' : ''; ?>">
                <span class="icon">💰</span>
                Tata Coin管理
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/checkin_records.php"
               class="<?php echo $current_page === 'checkin_records.php' ? 'active' : ''; ?>">
                <span class="icon">📅</span>
                签到记录管理
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/browse_records.php"
               class="<?php echo $current_page === 'browse_records.php' ? 'active' : ''; ?>">
                <span class="icon">👀</span>
                浏览记录统计
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/view_count_management.php"
               class="<?php echo $current_page === 'view_count_management.php' ? 'active' : ''; ?>">
                <span class="icon">📊</span>
                查看次数管理
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/messages.php"
               class="<?php echo $current_page === 'messages.php' ? 'active' : ''; ?>">
                <span class="icon">📢</span>
                消息管理
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/reviews.php"
               class="<?php echo $current_page === 'reviews.php' ? 'active' : ''; ?>">
                <span class="icon">⭐</span>
                评价管理
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/generate_reader_link.php"
               class="<?php echo $current_page === 'generate_reader_link.php' ? 'active' : ''; ?>">
                <span class="icon">🔗</span>
                注册链接
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/login_security.php"
               class="<?php echo $current_page === 'login_security.php' ? 'active' : ''; ?>">
                <span class="icon">🔐</span>
                登录安全
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/image_optimizer.php"
               class="<?php echo $current_page === 'image_optimizer.php' ? 'active' : ''; ?>">
                <span class="icon">🖼️</span>
                图片优化管理
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/database_update.php"
               class="<?php echo $current_page === 'database_update.php' ? 'active' : ''; ?>">
                <span class="icon">🔧</span>
                数据库更新
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/email_test.php"
               class="<?php echo $current_page === 'email_test.php' ? 'active' : ''; ?>">
                <span class="icon">📧</span>
                邮件服务测试
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/settings.php"
               class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                <span class="icon">⚙️</span>
                系统设置
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/statistics.php"
               class="<?php echo $current_page === 'statistics.php' ? 'active' : ''; ?>">
                <span class="icon">📈</span>
                数据统计
            </a>
        </li>
    </ul>
</nav>
