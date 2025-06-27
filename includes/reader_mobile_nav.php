<?php
/**
 * 占卜师后台移动端网格导航
 * 每行显示3个功能图标
 */

// 获取当前页面路径，用于高亮当前页面
$currentPage = basename($_SERVER['PHP_SELF']);

// 导航菜单项配置
$navItems = [
    [
        'url' => 'dashboard.php',
        'icon' => '📊',
        'text' => '首页',
        'active' => $currentPage === 'dashboard.php'
    ],
    [
        'url' => 'profile.php',
        'icon' => '👤',
        'text' => '资料',
        'active' => $currentPage === 'profile.php'
    ],
    [
        'url' => '../reader.php?id=' . $_SESSION['reader_id'],
        'icon' => '🔍',
        'text' => '我的页面',
        'active' => false,
        'target' => '_blank'
    ],
    [
        'url' => 'view_records.php',
        'icon' => '👁️',
        'text' => '记录',
        'active' => $currentPage === 'view_records.php'
    ],
    [
        'url' => 'messages.php',
        'icon' => '📬',
        'text' => '消息',
        'active' => $currentPage === 'messages.php'
    ],
    [
        'url' => 'tata_coin_guide.php',
        'icon' => '💰',
        'text' => 'Tata币',
        'active' => $currentPage === 'tata_coin_guide.php'
    ],
    [
        'url' => 'invitation.php',
        'icon' => '🎯',
        'text' => '邀请',
        'active' => $currentPage === 'invitation.php'
    ],
    [
        'url' => 'settings.php',
        'icon' => '⚙️',
        'text' => '设置',
        'active' => $currentPage === 'settings.php'
    ],
    [
        'url' => '../index.php',
        'icon' => '🏠',
        'text' => '首页',
        'active' => false
    ],
    [
        'url' => '../auth/logout.php',
        'icon' => '🚪',
        'text' => '退出',
        'active' => false
    ]
];
?>

<div class="mobile-nav-grid">
    <?php foreach ($navItems as $item): ?>
        <a href="<?php echo h($item['url']); ?>"
           class="mobile-nav-item <?php echo $item['active'] ? 'active' : ''; ?>"
           <?php echo isset($item['target']) ? 'target="' . h($item['target']) . '"' : ''; ?>>
            <span class="icon"><?php echo $item['icon']; ?></span>
            <span class="text"><?php echo h($item['text']); ?></span>
        </a>
    <?php endforeach; ?>
</div>
