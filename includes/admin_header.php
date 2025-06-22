<header class="admin-header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="<?php echo SITE_URL; ?>/admin/dashboard.php">
                    <h1><?php echo getSiteName(); ?> - 管理后台</h1>
                </a>
            </div>
            
            <div class="user-info">
                <span>管理员：<span class="user-name"><?php echo h($_SESSION['user_name']); ?></span></span>
                <a href="<?php echo SITE_URL; ?>/index.php" class="btn btn-outline">查看网站</a>
                <a href="<?php echo SITE_URL; ?>/auth/logout.php" class="btn btn-secondary">退出登录</a>
            </div>
        </div>
    </div>
</header>
