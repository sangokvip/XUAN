/**
 * 占卜师后台移动端菜单控制
 * 适用于所有占卜师后台页面
 */

document.addEventListener('DOMContentLoaded', function() {
    // 创建移动端菜单按钮和覆盖层（如果不存在）
    createMobileMenuElements();
    
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const readerSidebar = document.getElementById('readerSidebar');
    const mobileSidebarOverlay = document.getElementById('mobileSidebarOverlay');
    const menuIcon = document.getElementById('menuIcon');

    function createMobileMenuElements() {
        // 检查是否已存在移动端菜单按钮
        if (!document.getElementById('mobileMenuToggle')) {
            // 创建菜单按钮
            const menuButton = document.createElement('button');
            menuButton.className = 'mobile-menu-toggle';
            menuButton.id = 'mobileMenuToggle';
            menuButton.innerHTML = '<span id="menuIcon">☰</span>';
            document.body.appendChild(menuButton);
        }

        // 检查是否已存在覆盖层
        if (!document.getElementById('mobileSidebarOverlay')) {
            // 创建覆盖层
            const overlay = document.createElement('div');
            overlay.className = 'mobile-sidebar-overlay';
            overlay.id = 'mobileSidebarOverlay';
            document.body.appendChild(overlay);
        }

        // 为侧栏添加ID（如果没有）
        const sidebar = document.querySelector('.reader-sidebar');
        if (sidebar && !sidebar.id) {
            sidebar.id = 'readerSidebar';
        }
    }

    function toggleMobileMenu() {
        const isActive = readerSidebar && readerSidebar.classList.contains('active');
        
        if (isActive) {
            // 关闭菜单
            if (readerSidebar) readerSidebar.classList.remove('active');
            if (mobileSidebarOverlay) mobileSidebarOverlay.classList.remove('active');
            if (mobileMenuToggle) mobileMenuToggle.classList.remove('active');
            if (menuIcon) menuIcon.textContent = '☰';
            document.body.style.overflow = '';
        } else {
            // 打开菜单
            if (readerSidebar) readerSidebar.classList.add('active');
            if (mobileSidebarOverlay) mobileSidebarOverlay.classList.add('active');
            if (mobileMenuToggle) mobileMenuToggle.classList.add('active');
            if (menuIcon) menuIcon.textContent = '✕';
            document.body.style.overflow = 'hidden';
        }
    }

    // 点击菜单按钮
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', toggleMobileMenu);
    }

    // 点击覆盖层关闭菜单
    if (mobileSidebarOverlay) {
        mobileSidebarOverlay.addEventListener('click', toggleMobileMenu);
    }

    // 点击侧栏链接后关闭菜单
    if (readerSidebar) {
        const sidebarLinks = readerSidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    setTimeout(toggleMobileMenu, 100); // 稍微延迟以确保页面跳转
                }
            });
        });
    }

    // 窗口大小改变时处理
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            if (readerSidebar) readerSidebar.classList.remove('active');
            if (mobileSidebarOverlay) mobileSidebarOverlay.classList.remove('active');
            if (mobileMenuToggle) mobileMenuToggle.classList.remove('active');
            if (menuIcon) menuIcon.textContent = '☰';
            document.body.style.overflow = '';
        }
    });

    // ESC键关闭菜单
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && readerSidebar && readerSidebar.classList.contains('active')) {
            toggleMobileMenu();
        }
    });

    // 触摸滑动关闭菜单（简单实现）
    let touchStartX = 0;
    let touchEndX = 0;

    if (readerSidebar) {
        readerSidebar.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        });

        readerSidebar.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });

        function handleSwipe() {
            const swipeDistance = touchEndX - touchStartX;
            // 向左滑动超过100px关闭菜单
            if (swipeDistance < -100 && readerSidebar.classList.contains('active')) {
                toggleMobileMenu();
            }
        }
    }
});

// 导出函数供其他脚本使用
window.ReaderMobileMenu = {
    toggle: function() {
        const event = new Event('click');
        const button = document.getElementById('mobileMenuToggle');
        if (button) {
            button.dispatchEvent(event);
        }
    },
    
    close: function() {
        const sidebar = document.getElementById('readerSidebar');
        if (sidebar && sidebar.classList.contains('active')) {
            this.toggle();
        }
    },
    
    open: function() {
        const sidebar = document.getElementById('readerSidebar');
        if (sidebar && !sidebar.classList.contains('active')) {
            this.toggle();
        }
    }
};
