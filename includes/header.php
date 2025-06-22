<?php
// 确保SITE_URL已定义
if (!defined('SITE_URL')) {
    if (file_exists(__DIR__ . '/../config/site_config.php')) {
        require_once __DIR__ . '/../config/site_config.php';
    } else {
        define('SITE_URL', 'http://localhost');
    }
}
?>
<header class="site-header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="<?php echo SITE_URL; ?>/index.php">
                    <h1><?php echo getSiteName(); ?></h1>
                </a>
            </div>

            <!-- 移动端菜单按钮 -->
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>

            <nav class="main-nav" id="mainNav">
                <ul>
                    <li><a href="<?php echo SITE_URL; ?>/index.php">首页</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/readers.php">占卜师</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/courses.php">西玄课程</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/products.php">魔法产品</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/contact.php">联系我们</a></li>

                    <!-- 移动端专用菜单项 -->
                    <li class="mobile-only mobile-divider"></li>

                    <!-- 搜索功能 -->
                    <li class="mobile-only mobile-search">
                        <form action="<?php echo SITE_URL; ?>/search.php" method="GET" class="mobile-search-form">
                            <input type="text" name="q" placeholder="搜索占卜师..." class="mobile-search-input">
                            <button type="submit" class="mobile-search-btn">搜索</button>
                        </form>
                    </li>

                    <!-- 用户菜单 -->
                    <?php if (isLoggedIn()): ?>
                        <?php $currentUser = getUserById($_SESSION['user_id']); ?>
                        <li class="mobile-only mobile-user">
                            <span class="mobile-user-name"><?php echo h($currentUser['full_name'] ?? $_SESSION['user_name']); ?></span>
                        </li>
                        <li class="mobile-only"><a href="<?php echo SITE_URL; ?>/user/profile.php">个人资料</a></li>
                        <li class="mobile-only"><a href="<?php echo SITE_URL; ?>/auth/logout.php">退出登录</a></li>
                    <?php elseif (isReaderLoggedIn()): ?>
                        <?php $currentReader = getReaderById($_SESSION['reader_id']); ?>
                        <li class="mobile-only mobile-user">
                            <span class="mobile-user-name"><?php echo h($currentReader['full_name']); ?></span>
                        </li>
                        <li class="mobile-only"><a href="<?php echo SITE_URL; ?>/reader/dashboard.php">占卜师后台</a></li>
                        <li class="mobile-only"><a href="<?php echo SITE_URL; ?>/auth/logout.php">退出登录</a></li>
                    <?php elseif (isAdminLoggedIn()): ?>
                        <li class="mobile-only mobile-user">
                            <span class="mobile-user-name">管理员</span>
                        </li>
                        <li class="mobile-only"><a href="<?php echo SITE_URL; ?>/admin/dashboard.php">管理后台</a></li>
                        <li class="mobile-only"><a href="<?php echo SITE_URL; ?>/auth/logout.php">退出登录</a></li>
                    <?php else: ?>
                        <li class="mobile-only"><a href="<?php echo SITE_URL; ?>/auth/login.php" class="mobile-login-btn">登录</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="header-actions">
                <!-- 用户菜单 -->
                <div class="user-menu">
                <?php if (isLoggedIn()): ?>
                    <?php
                    $currentUser = getUserById($_SESSION['user_id']);
                    ?>
                    <div class="user-dropdown">
                        <button class="user-toggle">
                            <span class="user-name"><?php echo h($currentUser['full_name'] ?? $_SESSION['user_name']); ?></span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu">
                            <a href="<?php echo SITE_URL; ?>/user/profile.php">个人资料</a>
                            <a href="<?php echo SITE_URL; ?>/auth/logout.php">退出登录</a>
                        </div>
                    </div>
                <?php elseif (isReaderLoggedIn()): ?>
                    <?php
                    $currentReader = getReaderById($_SESSION['reader_id']);
                    ?>
                    <div class="user-dropdown">
                        <button class="user-toggle">
                            <span class="user-name"><?php echo h($currentReader['full_name']); ?></span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu">
                            <a href="<?php echo SITE_URL; ?>/reader/dashboard.php">占卜师后台</a>
                            <a href="<?php echo SITE_URL; ?>/auth/logout.php">退出登录</a>
                        </div>
                    </div>
                <?php elseif (isAdminLoggedIn()): ?>
                    <div class="user-dropdown">
                        <button class="user-toggle">
                            <span class="user-name">管理员</span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu">
                            <a href="<?php echo SITE_URL; ?>/admin/dashboard.php">管理后台</a>
                            <a href="<?php echo SITE_URL; ?>/auth/logout.php">退出登录</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="auth-links">
                        <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-login">登录</a>
                    </div>
                <?php endif; ?>
                </div>

                <!-- 搜索图标 -->
                <div class="search-icon-container">
                    <button type="button" class="search-icon-btn" onclick="toggleSearchBox()">🔍</button>
                    <div class="search-dropdown" id="searchDropdown" style="display: none;">
                        <form action="<?php echo SITE_URL; ?>/search.php" method="GET" class="search-form">
                            <input type="text" name="q" placeholder="搜索占卜师..." class="search-input" id="searchInput">
                            <button type="submit" class="search-submit-btn">搜索</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 移动端菜单遮罩 -->
    <div class="mobile-nav-overlay" id="mobileNavOverlay" onclick="closeMobileMenu()"></div>
</header>

<script>
// 移动端菜单切换
function toggleMobileMenu() {
    const nav = document.getElementById('mainNav');
    const toggle = document.querySelector('.mobile-menu-toggle');
    const overlay = document.getElementById('mobileNavOverlay');

    nav.classList.toggle('mobile-nav-open');
    toggle.classList.toggle('active');
    overlay.classList.toggle('active');

    // 防止背景滚动
    if (nav.classList.contains('mobile-nav-open')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

// 关闭移动端菜单
function closeMobileMenu() {
    const nav = document.getElementById('mainNav');
    const toggle = document.querySelector('.mobile-menu-toggle');
    const overlay = document.getElementById('mobileNavOverlay');

    nav.classList.remove('mobile-nav-open');
    toggle.classList.remove('active');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

// 搜索框切换
function toggleSearchBox() {
    const dropdown = document.getElementById('searchDropdown');
    const input = document.getElementById('searchInput');

    if (dropdown.style.display === 'none') {
        dropdown.style.display = 'block';
        input.focus();
    } else {
        dropdown.style.display = 'none';
    }
}

// 点击外部关闭菜单和搜索框
document.addEventListener('click', function(event) {
    const nav = document.getElementById('mainNav');
    const toggle = document.querySelector('.mobile-menu-toggle');
    const searchContainer = document.querySelector('.search-icon-container');
    const dropdown = document.getElementById('searchDropdown');

    // 关闭移动端菜单
    if (!nav.contains(event.target) && !toggle.contains(event.target)) {
        closeMobileMenu();
    }

    // 关闭搜索框
    if (searchContainer && !searchContainer.contains(event.target)) {
        dropdown.style.display = 'none';
    }
});

// ESC键关闭菜单和搜索框
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeMobileMenu();
        document.getElementById('searchDropdown').style.display = 'none';
    }
});

// 窗口大小改变时关闭移动端菜单
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        closeMobileMenu();
    }
});

// 点击导航链接时关闭移动端菜单
document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.main-nav a');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            closeMobileMenu();
        });
    });
});
</script>
