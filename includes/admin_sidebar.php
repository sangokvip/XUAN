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
                塔罗师管理
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
            <a href="<?php echo SITE_URL; ?>/admin/messages.php"
               class="<?php echo $current_page === 'messages.php' ? 'active' : ''; ?>">
                <span class="icon">📢</span>
                消息管理
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
            <a href="<?php echo SITE_URL; ?>/admin/database_update.php"
               class="<?php echo $current_page === 'database_update.php' ? 'active' : ''; ?>">
                <span class="icon">🔧</span>
                数据库更新
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
