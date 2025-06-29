<header class="reader-header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="<?php echo SITE_URL; ?>/reader/dashboard.php">
                    <img src="<?php echo SITE_URL; ?>/img/logo.png" alt="<?php echo getSiteName(); ?>" class="logo-image">
                </a>
            </div>
            
            <div class="user-info">
                <span>欢迎，<span class="user-name"><?php echo h($_SESSION['user_name']); ?></span></span>
                <a href="<?php echo SITE_URL; ?>/index.php" class="btn btn-outline">查看网站</a>
                <a href="<?php echo SITE_URL; ?>/auth/logout.php" class="btn btn-secondary">退出登录</a>
            </div>
        </div>
    </div>
</header>
