<footer class="site-footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3><?php echo getSiteName(); ?></h3>
                <p>专业的塔罗师展示平台，连接塔罗师与寻求指导的用户。</p>
            </div>
            
            <div class="footer-section">
                <h4>快速链接</h4>
                <ul>
                    <li><a href="<?php echo SITE_URL; ?>/index.php">首页</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/readers.php">塔罗师列表</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/auth/register.php">用户注册</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h4>塔罗师</h4>
                <ul>
                    <li><a href="<?php echo SITE_URL; ?>/auth/reader_login.php">塔罗师登录</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/reader/dashboard.php">塔罗师后台</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h4>联系我们</h4>
                <p>邮箱：<?php echo SITE_EMAIL; ?></p>
                <p>如有问题，请随时联系我们。</p>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo getSiteName(); ?>. 保留所有权利。</p>
        </div>
    </div>
</footer>
