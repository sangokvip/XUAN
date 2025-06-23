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
                            <a href="<?php echo SITE_URL; ?>/user/index.php" class="mobile-user-name"><?php echo h($currentUser['full_name'] ?? $_SESSION['user_name']); ?></a>
                        </li>
                        <li class="mobile-only"><a href="<?php echo SITE_URL; ?>/user/index.php">用户中心</a></li>
                        <li class="mobile-only"><a href="<?php echo SITE_URL; ?>/user/profile.php">个人资料</a></li>
                        <li class="mobile-only"><a href="<?php echo SITE_URL; ?>/auth/logout.php">退出登录</a></li>
                    <?php elseif (isReaderLoggedIn()): ?>
                        <?php $currentReader = getReaderById($_SESSION['reader_id']); ?>
                        <li class="mobile-only mobile-user">
                            <a href="<?php echo SITE_URL; ?>/reader/dashboard.php" class="mobile-user-name"><?php echo h($currentReader['full_name']); ?></a>
                        </li>
                        <li class="mobile-only"><a href="<?php echo SITE_URL; ?>/reader/dashboard.php">占卜师后台</a></li>
                        <li class="mobile-only"><a href="<?php echo SITE_URL; ?>/auth/logout.php">退出登录</a></li>
                    <?php elseif (isAdminLoggedIn()): ?>
                        <li class="mobile-only mobile-user">
                            <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="mobile-user-name">管理员</a>
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
                    <div class="user-dropdown" id="userDropdown">
                        <button class="user-toggle" data-user-center="<?php echo SITE_URL; ?>/user/index.php">
                            <span class="user-name"><?php echo h($currentUser['full_name'] ?? $_SESSION['user_name']); ?></span>
                            <?php
                            // 显示未读消息数量
                            try {
                                require_once __DIR__ . '/MessageManager.php';
                                $messageManager = new MessageManager();
                                if ($messageManager->isInstalled()) {
                                    $unreadCount = $messageManager->getUnreadCount($_SESSION['user_id'], 'user');
                                    if ($unreadCount > 0) {
                                        echo '<a href="' . SITE_URL . '/user/messages.php" class="unread-messages-badge" title="' . $unreadCount . '条未读消息">' . $unreadCount . '</a>';
                                    }
                                }
                            } catch (Exception $e) {
                                // 忽略错误
                            }
                            ?>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu" id="userDropdownMenu">
                            <a href="<?php echo SITE_URL; ?>/user/index.php">用户中心</a>
                            <a href="<?php echo SITE_URL; ?>/user/profile.php">个人资料</a>
                            <a href="<?php echo SITE_URL; ?>/auth/logout.php">退出登录</a>
                        </div>
                    </div>
                <?php elseif (isReaderLoggedIn()): ?>
                    <?php
                    $currentReader = getReaderById($_SESSION['reader_id']);
                    ?>
                    <div class="user-dropdown" id="readerDropdown">
                        <button class="user-toggle" data-user-center="<?php echo SITE_URL; ?>/reader/dashboard.php">
                            <span class="user-name"><?php echo h($currentReader['full_name']); ?></span>
                            <?php
                            // 显示未读消息数量
                            try {
                                require_once __DIR__ . '/MessageManager.php';
                                $messageManager = new MessageManager();
                                if ($messageManager->isInstalled()) {
                                    $unreadCount = $messageManager->getUnreadCount($_SESSION['reader_id'], 'reader');
                                    if ($unreadCount > 0) {
                                        echo '<a href="' . SITE_URL . '/reader/messages.php" class="unread-messages-badge" title="' . $unreadCount . '条未读消息">' . $unreadCount . '</a>';
                                    }
                                }
                            } catch (Exception $e) {
                                // 忽略错误
                            }
                            ?>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu" id="readerDropdownMenu">
                            <a href="<?php echo SITE_URL; ?>/reader/dashboard.php">占卜师后台</a>
                            <a href="<?php echo SITE_URL; ?>/auth/logout.php">退出登录</a>
                        </div>
                    </div>
                <?php elseif (isAdminLoggedIn()): ?>
                    <div class="user-dropdown" id="adminDropdown">
                        <button class="user-toggle" data-user-center="<?php echo SITE_URL; ?>/admin/dashboard.php">
                            <span class="user-name">管理员</span>
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu" id="adminDropdownMenu">
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

// 用户下拉菜单控制 - 悬停显示，3秒延迟消失
let dropdownHideTimer = null;

function showUserDropdown(dropdown) {
    // 清除之前的隐藏定时器
    if (dropdownHideTimer) {
        clearTimeout(dropdownHideTimer);
        dropdownHideTimer = null;
    }

    // 关闭其他下拉菜单
    closeOtherDropdowns(dropdown);

    // 显示当前下拉菜单
    const dropdownMenu = dropdown.querySelector('.dropdown-menu');
    if (dropdownMenu) {
        dropdownMenu.style.display = 'block';
        dropdown.classList.add('active');
    }
}

function hideUserDropdown(dropdown) {
    // 设置3秒延迟隐藏
    dropdownHideTimer = setTimeout(() => {
        const dropdownMenu = dropdown.querySelector('.dropdown-menu');
        if (dropdownMenu) {
            dropdownMenu.style.display = 'none';
            dropdown.classList.remove('active');
        }
        dropdownHideTimer = null;
    }, 3000); // 3秒延迟
}

function cancelHideDropdown() {
    // 取消隐藏定时器
    if (dropdownHideTimer) {
        clearTimeout(dropdownHideTimer);
        dropdownHideTimer = null;
    }
}

function closeOtherDropdowns(currentDropdown) {
    const allDropdowns = document.querySelectorAll('.user-dropdown');
    allDropdowns.forEach(dropdown => {
        if (dropdown !== currentDropdown) {
            const dropdownMenu = dropdown.querySelector('.dropdown-menu');
            if (dropdownMenu) {
                dropdownMenu.style.display = 'none';
                dropdown.classList.remove('active');
            }
        }
    });

    // 关闭搜索框
    const searchDropdown = document.getElementById('searchDropdown');
    if (searchDropdown) {
        searchDropdown.style.display = 'none';
    }
}

function closeAllDropdowns() {
    // 清除定时器
    if (dropdownHideTimer) {
        clearTimeout(dropdownHideTimer);
        dropdownHideTimer = null;
    }

    const dropdowns = document.querySelectorAll('.dropdown-menu');
    const userDropdowns = document.querySelectorAll('.user-dropdown');

    dropdowns.forEach(dropdown => {
        dropdown.style.display = 'none';
    });

    userDropdowns.forEach(dropdown => {
        dropdown.classList.remove('active');
    });

    // 关闭搜索框
    const searchDropdown = document.getElementById('searchDropdown');
    if (searchDropdown) {
        searchDropdown.style.display = 'none';
    }
}

// 点击外部关闭菜单和搜索框
document.addEventListener('click', function(event) {
    const nav = document.getElementById('mainNav');
    const toggle = document.querySelector('.mobile-menu-toggle');
    const searchContainer = document.querySelector('.search-icon-container');
    const userDropdowns = document.querySelectorAll('.user-dropdown');

    // 关闭移动端菜单
    if (!nav.contains(event.target) && !toggle.contains(event.target)) {
        closeMobileMenu();
    }

    // 关闭搜索框
    if (searchContainer && !searchContainer.contains(event.target)) {
        document.getElementById('searchDropdown').style.display = 'none';
    }

    // 检查是否点击在任何用户下拉菜单外部
    let clickedOutside = true;
    userDropdowns.forEach(dropdown => {
        if (dropdown.contains(event.target)) {
            clickedOutside = false;
        }
    });

    if (clickedOutside) {
        closeAllDropdowns();
    }
});

// ESC键关闭菜单和搜索框
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeMobileMenu();
        closeAllDropdowns();
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

    // 初始化用户下拉菜单
    const userDropdowns = document.querySelectorAll('.user-dropdown');
    userDropdowns.forEach(dropdown => {
        const userToggle = dropdown.querySelector('.user-toggle');
        const dropdownMenu = dropdown.querySelector('.dropdown-menu');
        const userName = userToggle.querySelector('.user-name');

        if (userToggle && dropdownMenu) {
            // 鼠标进入用户下拉区域时显示菜单
            dropdown.addEventListener('mouseenter', function() {
                showUserDropdown(dropdown);
            });

            // 鼠标离开用户下拉区域时开始3秒倒计时
            dropdown.addEventListener('mouseleave', function() {
                hideUserDropdown(dropdown);
            });

            // 鼠标重新进入时取消隐藏倒计时
            dropdown.addEventListener('mouseenter', function() {
                cancelHideDropdown();
            });
        }

        // 添加用户名点击事件
        if (userName) {
            userName.style.cursor = 'pointer';
            userName.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                const userCenter = userToggle.getAttribute('data-user-center');
                if (userCenter) {
                    window.location.href = userCenter;
                }
            });
        }
    });
});
</script>
