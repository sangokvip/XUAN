<header class="reader-header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="<?php echo SITE_URL; ?>/reader/dashboard.php">
                    <h1><?php echo getSiteName(); ?> - 占卜师后台</h1>
                </a>
            </div>
            
            <div class="user-info">
                <span>欢迎，<span class="user-name"><?php echo h($_SESSION['user_name']); ?></span></span>
                <a href="<?php echo SITE_URL; ?>/reader.php?id=<?php echo $_SESSION['reader_id']; ?>"
                   class="btn btn-primary" target="_blank">查看我的页面</a>
                <a href="<?php echo SITE_URL; ?>/index.php" class="btn btn-outline">查看网站</a>
                <a href="<?php echo SITE_URL; ?>/auth/logout.php" class="btn btn-secondary">退出登录</a>
            </div>
        </div>
    </div>
</header>
