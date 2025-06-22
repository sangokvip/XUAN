<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="sidebar-nav">
    <ul>
        <li>
            <a href="<?php echo SITE_URL; ?>/reader/dashboard.php" 
               class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <span class="icon">📊</span>
                后台首页
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/reader/profile.php" 
               class="<?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                <span class="icon">👤</span>
                个人资料
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/reader/view_records.php"
               class="<?php echo $current_page === 'view_records.php' ? 'active' : ''; ?>">
                <span class="icon">👁️</span>
                查看记录
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/reader/settings.php" 
               class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                <span class="icon">⚙️</span>
                账户设置
            </a>
        </li>
    </ul>
</nav>
