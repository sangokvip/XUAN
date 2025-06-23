<!-- 管理后台页脚 -->
<footer class="admin-footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-left">
                <p>&copy; <?php echo date('Y'); ?> <?php echo getSiteName(); ?> 管理后台. 保留所有权利.</p>
            </div>
            <div class="footer-right">
                <span>管理员：<?php echo h($_SESSION['user_name'] ?? ''); ?></span>
                <span class="separator">|</span>
                <span>登录时间：<?php echo date('Y-m-d H:i'); ?></span>
            </div>
        </div>
    </div>
</footer>

<style>
.admin-footer {
    background: #1f2937;
    color: #9ca3af;
    padding: 20px 0;
    margin-top: 40px;
    border-top: 1px solid #374151;
}

.admin-footer .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.admin-footer .footer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.admin-footer .footer-left p {
    margin: 0;
    font-size: 0.9rem;
}

.admin-footer .footer-right {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.85rem;
}

.admin-footer .separator {
    color: #6b7280;
}

@media (max-width: 768px) {
    .admin-footer .footer-content {
        flex-direction: column;
        text-align: center;
    }
    
    .admin-footer .footer-right {
        flex-direction: column;
        gap: 5px;
    }
    
    .admin-footer .separator {
        display: none;
    }
}
</style>
