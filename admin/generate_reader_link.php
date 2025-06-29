<?php
session_start();
require_once '../config/config.php';

// 确保从数据库获取最新的网站URL
if (function_exists('getSetting')) {
    $currentSiteUrl = getSetting('site_url', SITE_URL);
} else {
    $currentSiteUrl = SITE_URL;
}

// 检查管理员权限
requireAdminLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        
        // 生成唯一token
        $token = generateRandomString(64);
        
        // 计算过期时间
        $expiresAt = date('Y-m-d H:i:s', time() + (REGISTRATION_LINK_HOURS * 3600));
        
        // 插入注册链接记录
        $linkId = $db->insert('reader_registration_links', [
            'token' => $token,
            'created_by' => $_SESSION['admin_id'],
            'expires_at' => $expiresAt
        ]);
        
        if ($linkId) {
            $registrationUrl = $currentSiteUrl . '/auth/reader_register.php?token=' . $token;
            $success = '注册链接生成成功！';
        } else {
            $error = '生成注册链接失败';
        }
    } catch (Exception $e) {
        $error = '生成注册链接时发生错误';
    }
}

// 获取最近的注册链接
$db = Database::getInstance();
$recentLinks = $db->fetchAll(
    "SELECT rl.*, a.full_name as admin_name, r.full_name as reader_name 
     FROM reader_registration_links rl 
     LEFT JOIN admins a ON rl.created_by = a.id 
     LEFT JOIN readers r ON rl.used_by = r.id 
     ORDER BY rl.created_at DESC 
     LIMIT 20"
);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>生成占卜师注册链接 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-sidebar">
            <?php include '../includes/admin_sidebar.php'; ?>
        </div>
        
        <div class="admin-content">
            <h1>生成占卜师注册链接</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo h($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo h($success); ?>
                    <?php if (isset($registrationUrl)): ?>
                        <div class="registration-link">
                            <h3>注册链接：</h3>
                            <div class="link-container">
                                <input type="text" value="<?php echo h($registrationUrl); ?>" readonly id="registration-url">
                                <button onclick="copyToClipboard()" class="btn btn-secondary">复制链接</button>
                            </div>
                            <p><strong>有效期：</strong><?php echo REGISTRATION_LINK_HOURS; ?>小时</p>
                            <p><strong>过期时间：</strong><?php echo date('Y-m-d H:i:s', strtotime($expiresAt)); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2>生成新的注册链接</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <p>点击下方按钮生成一个新的占卜师注册链接，链接有效期为<?php echo REGISTRATION_LINK_HOURS; ?>小时。</p>
                        <button type="submit" class="btn btn-primary">生成注册链接</button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>最近的注册链接</h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>创建时间</th>
                                    <th>创建者</th>
                                    <th>过期时间</th>
                                    <th>状态</th>
                                    <th>使用者</th>
                                    <th>使用时间</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentLinks as $link): ?>
                                    <tr>
                                        <td><?php echo h($link['created_at']); ?></td>
                                        <td><?php echo h($link['admin_name']); ?></td>
                                        <td><?php echo h($link['expires_at']); ?></td>
                                        <td>
                                            <?php if ($link['is_used']): ?>
                                                <span class="status-used">已使用</span>
                                            <?php elseif (strtotime($link['expires_at']) < time()): ?>
                                                <span class="status-expired">已过期</span>
                                            <?php else: ?>
                                                <span class="status-active">有效</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo h($link['reader_name'] ?? '-'); ?></td>
                                        <td><?php echo h($link['used_at'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function copyToClipboard() {
            const urlInput = document.getElementById('registration-url');
            urlInput.select();
            urlInput.setSelectionRange(0, 99999);
            document.execCommand('copy');
            alert('链接已复制到剪贴板！');
        }
    </script>
</body>
</html>
