<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin();

$db = Database::getInstance();
$success = '';
$error = '';

// 处理设置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        $settings = [
            'site_name' => trim($_POST['site_name'] ?? ''),
            'site_description' => trim($_POST['site_description'] ?? ''),
            'site_url' => trim($_POST['site_url'] ?? ''),
            'max_featured_readers' => (int)($_POST['max_featured_readers'] ?? 6),
            'registration_link_hours' => (int)($_POST['registration_link_hours'] ?? 24),
            'readers_per_page' => (int)($_POST['readers_per_page'] ?? 12),
            'admin_items_per_page' => (int)($_POST['admin_items_per_page'] ?? 20)
        ];

        $updated = 0;
        foreach ($settings as $key => $value) {
            if (setSetting($key, $value)) {
                $updated++;
            }
        }

        if ($updated > 0) {
            $success = "已更新 {$updated} 项设置";
        } else {
            $error = '设置更新失败';
        }
    }

    elseif ($action === 'update_contact_settings') {
        $contactSettings = [
            'contact_email_1' => trim($_POST['contact_email_1'] ?? ''),
            'contact_email_2' => trim($_POST['contact_email_2'] ?? ''),
            'contact_wechat' => trim($_POST['contact_wechat'] ?? ''),
            'contact_wechat_hours' => trim($_POST['contact_wechat_hours'] ?? ''),
            'contact_qq_group_1' => trim($_POST['contact_qq_group_1'] ?? ''),
            'contact_qq_group_2' => trim($_POST['contact_qq_group_2'] ?? ''),
            'contact_xiaohongshu' => trim($_POST['contact_xiaohongshu'] ?? ''),
            'contact_xiaohongshu_desc' => trim($_POST['contact_xiaohongshu_desc'] ?? ''),
            'contact_phone' => trim($_POST['contact_phone'] ?? ''),
            'contact_address' => trim($_POST['contact_address'] ?? ''),
            'contact_business_hours' => trim($_POST['contact_business_hours'] ?? ''),
            'contact_notice' => trim($_POST['contact_notice'] ?? '')
        ];

        $updated = 0;
        foreach ($contactSettings as $key => $value) {
            if (setSetting($key, $value)) {
                $updated++;
            }
        }

        if ($updated > 0) {
            $success = "已更新 {$updated} 项联系方式设置";
        } else {
            $error = '联系方式设置更新失败';
        }
    }
    
    elseif ($action === 'clear_cache') {
        // 清理缓存
        if (class_exists('SimpleCache')) {
            $cache = new SimpleCache();
            $cache->clear();
            $success = '缓存已清理';
        } else {
            $error = '缓存系统不可用';
        }
    }
    
    elseif ($action === 'cleanup_logs') {
        // 清理日志
        $logDir = '../logs';
        $cleaned = 0;
        
        if (is_dir($logDir)) {
            $files = glob($logDir . '/*.log');
            $cutoffTime = time() - (30 * 24 * 3600); // 30天前
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoffTime) {
                    if (unlink($file)) {
                        $cleaned++;
                    }
                }
            }
        }
        
        $success = "已清理 {$cleaned} 个旧日志文件";
    }
    
    elseif ($action === 'cleanup_login_attempts') {
        // 清理登录尝试记录
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute();
        $cleaned = $stmt->rowCount();

        $success = "已清理 {$cleaned} 条旧登录记录";
    }

    elseif ($action === 'change_password') {
        // 修改管理员密码
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // 获取当前管理员信息
        $adminId = $_SESSION['admin_id'];
        $admin = $db->fetchOne("SELECT * FROM admins WHERE id = ?", [$adminId]);

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = '请填写所有密码字段';
        } elseif (!verifyPassword($currentPassword, $admin['password_hash'])) {
            $error = '当前密码不正确';
        } elseif ($newPassword !== $confirmPassword) {
            $error = '新密码和确认密码不一致';
        } elseif (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            $error = '新密码至少需要' . PASSWORD_MIN_LENGTH . '个字符';
        } else {
            $hashedPassword = hashPassword($newPassword);
            $result = $db->update('admins', ['password_hash' => $hashedPassword], 'id = ?', [$adminId]);
            if ($result) {
                // 密码修改成功，为了安全起见，清除会话并重定向到登录页面
                session_destroy();
                header('Location: ../auth/admin_login.php?message=' . urlencode('密码修改成功，请重新登录'));
                exit;
            } else {
                $error = '密码修改失败，请重试';
            }
        }
    }
}

// 获取当前设置
$currentSettings = [];
$settings = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
foreach ($settings as $setting) {
    $currentSettings[$setting['setting_key']] = $setting['setting_value'];
}

// 默认值
$defaults = [
    'site_name' => '塔罗师展示平台',
    'site_description' => '专业塔罗师展示平台',
    'site_url' => SITE_URL,
    'max_featured_readers' => '6',
    'registration_link_hours' => '24',
    'readers_per_page' => '12',
    'admin_items_per_page' => '20',
    // 联系方式设置默认值
    'contact_email_1' => 'info@example.com',
    'contact_email_2' => 'support@example.com',
    'contact_wechat' => 'mystical_service',
    'contact_wechat_hours' => '9:00-21:00',
    'contact_qq_group_1' => '123456789',
    'contact_qq_group_2' => '987654321',
    'contact_xiaohongshu' => '@神秘学园',
    'contact_xiaohongshu_desc' => '每日分享占卜知识',
    'contact_phone' => '',
    'contact_address' => '',
    'contact_business_hours' => '周一至周日 9:00-21:00',
    'contact_notice' => '我们会在24小时内回复您的留言'
];

// 合并设置
foreach ($defaults as $key => $value) {
    if (!isset($currentSettings[$key])) {
        $currentSettings[$key] = $value;
    }
}

// 获取系统信息
$systemInfo = [
    'php_version' => PHP_VERSION,
    'mysql_version' => $db->fetchOne("SELECT VERSION() as version")['version'] ?? 'Unknown',
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time')
];

// 获取存储信息
$storageInfo = [];
$uploadDir = '../uploads';
if (is_dir($uploadDir)) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir));
    $totalSize = 0;
    $fileCount = 0;
    
    foreach ($files as $file) {
        if ($file->isFile()) {
            $totalSize += $file->getSize();
            $fileCount++;
        }
    }
    
    $storageInfo['upload_files'] = $fileCount;
    $storageInfo['upload_size'] = round($totalSize / 1024 / 1024, 2) . ' MB';
} else {
    $storageInfo['upload_files'] = 0;
    $storageInfo['upload_size'] = '0 MB';
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - 管理后台</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none';">
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
            <h1>系统设置</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo h($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo h($success); ?></div>
            <?php endif; ?>
            
            <!-- 基本设置 -->
            <div class="settings-section">
                <h2>基本设置</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="setting-item">
                        <div class="setting-label">网站名称</div>
                        <div class="setting-control">
                            <input type="text" name="site_name" value="<?php echo h($currentSettings['site_name']); ?>" required>
                        </div>
                        <div class="setting-description">显示在网站标题和头部的名称</div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-label">网站描述</div>
                        <div class="setting-control">
                            <input type="text" name="site_description" value="<?php echo h($currentSettings['site_description']); ?>">
                        </div>
                        <div class="setting-description">网站的简短描述</div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-label">网站URL</div>
                        <div class="setting-control">
                            <input type="url" name="site_url" value="<?php echo h($currentSettings['site_url']); ?>" required>
                        </div>
                        <div class="setting-description">网站的完整URL地址</div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-label">推荐塔罗师数量</div>
                        <div class="setting-control">
                            <input type="number" name="max_featured_readers" value="<?php echo h($currentSettings['max_featured_readers']); ?>" min="1" max="20">
                        </div>
                        <div class="setting-description">首页显示的推荐塔罗师数量</div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-label">注册链接有效期</div>
                        <div class="setting-control">
                            <input type="number" name="registration_link_hours" value="<?php echo h($currentSettings['registration_link_hours']); ?>" min="1" max="168">
                        </div>
                        <div class="setting-description">塔罗师注册链接的有效期（小时）</div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-label">每页塔罗师数量</div>
                        <div class="setting-control">
                            <input type="number" name="readers_per_page" value="<?php echo h($currentSettings['readers_per_page']); ?>" min="6" max="50">
                        </div>
                        <div class="setting-description">塔罗师列表页每页显示的数量</div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-label">后台每页条目数</div>
                        <div class="setting-control">
                            <input type="number" name="admin_items_per_page" value="<?php echo h($currentSettings['admin_items_per_page']); ?>" min="10" max="100">
                        </div>
                        <div class="setting-description">管理后台列表页每页显示的条目数</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">保存设置</button>
                </form>
            </div>

            <!-- 联系方式设置 -->
            <div class="settings-section">
                <h2>联系方式设置</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_contact_settings">

                    <div class="setting-item">
                        <div class="setting-label">主要联系邮箱</div>
                        <div class="setting-control">
                            <input type="email" name="contact_email_1" value="<?php echo h($currentSettings['contact_email_1']); ?>" required>
                        </div>
                        <div class="setting-description">显示在联系页面的主要邮箱地址</div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">客服邮箱</div>
                        <div class="setting-control">
                            <input type="email" name="contact_email_2" value="<?php echo h($currentSettings['contact_email_2']); ?>">
                        </div>
                        <div class="setting-description">客服支持邮箱地址</div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">微信客服号</div>
                        <div class="setting-control">
                            <input type="text" name="contact_wechat" value="<?php echo h($currentSettings['contact_wechat']); ?>">
                        </div>
                        <div class="setting-description">微信客服账号</div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">微信客服时间</div>
                        <div class="setting-control">
                            <input type="text" name="contact_wechat_hours" value="<?php echo h($currentSettings['contact_wechat_hours']); ?>">
                        </div>
                        <div class="setting-description">微信客服的工作时间</div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">官方QQ群</div>
                        <div class="setting-control">
                            <input type="text" name="contact_qq_group_1" value="<?php echo h($currentSettings['contact_qq_group_1']); ?>">
                        </div>
                        <div class="setting-description">官方交流QQ群号</div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">新手学习群</div>
                        <div class="setting-control">
                            <input type="text" name="contact_qq_group_2" value="<?php echo h($currentSettings['contact_qq_group_2']); ?>">
                        </div>
                        <div class="setting-description">新手学习QQ群号</div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">小红书账号</div>
                        <div class="setting-control">
                            <input type="text" name="contact_xiaohongshu" value="<?php echo h($currentSettings['contact_xiaohongshu']); ?>">
                        </div>
                        <div class="setting-description">小红书官方账号</div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">小红书描述</div>
                        <div class="setting-control">
                            <input type="text" name="contact_xiaohongshu_desc" value="<?php echo h($currentSettings['contact_xiaohongshu_desc']); ?>">
                        </div>
                        <div class="setting-description">小红书账号的描述信息</div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">联系电话</div>
                        <div class="setting-control">
                            <input type="text" name="contact_phone" value="<?php echo h($currentSettings['contact_phone']); ?>">
                        </div>
                        <div class="setting-description">联系电话（可选，留空则不显示）</div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">联系地址</div>
                        <div class="setting-control">
                            <input type="text" name="contact_address" value="<?php echo h($currentSettings['contact_address']); ?>">
                        </div>
                        <div class="setting-description">联系地址（可选，留空则不显示）</div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">营业时间</div>
                        <div class="setting-control">
                            <input type="text" name="contact_business_hours" value="<?php echo h($currentSettings['contact_business_hours']); ?>">
                        </div>
                        <div class="setting-description">营业或服务时间</div>
                    </div>

                    <div class="setting-item">
                        <div class="setting-label">联系页面提示</div>
                        <div class="setting-control">
                            <input type="text" name="contact_notice" value="<?php echo h($currentSettings['contact_notice']); ?>">
                        </div>
                        <div class="setting-description">在联系页面显示的提示信息</div>
                    </div>

                    <button type="submit" class="btn btn-primary">保存联系方式设置</button>
                </form>
            </div>

            <!-- 账户安全 -->
            <div class="settings-section">
                <h2>账户安全</h2>

                <div class="setting-item">
                    <div class="setting-label">修改密码</div>
                    <div class="setting-description">为了账户安全，建议定期修改密码</div>
                    <div class="setting-control">
                        <form method="POST" class="password-form">
                            <input type="hidden" name="action" value="change_password">

                            <div class="form-group">
                                <label for="current_password">当前密码</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>

                            <div class="form-group">
                                <label for="new_password">新密码</label>
                                <input type="password" id="new_password" name="new_password" required
                                       minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                <small>密码至少需要 <?php echo PASSWORD_MIN_LENGTH; ?> 个字符</small>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">确认新密码</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>

                            <button type="submit" class="btn btn-primary">修改密码</button>
                            <a href="test_password_change.php" class="btn btn-secondary" style="margin-left: 10px;">测试密码功能</a>
                        </form>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-label">当前管理员信息</div>
                    <div class="setting-description">
                        <?php
                        $adminId = $_SESSION['admin_id'];
                        $currentAdmin = $db->fetchOne("SELECT username, email, full_name, created_at FROM admins WHERE id = ?", [$adminId]);
                        ?>
                        <strong>用户名：</strong><?php echo h($currentAdmin['username']); ?><br>
                        <strong>邮箱：</strong><?php echo h($currentAdmin['email']); ?><br>
                        <strong>姓名：</strong><?php echo h($currentAdmin['full_name']); ?><br>
                        <strong>创建时间：</strong><?php echo date('Y-m-d H:i:s', strtotime($currentAdmin['created_at'])); ?>
                    </div>
                </div>
            </div>

            <!-- 系统维护 -->
            <div class="settings-section">
                <h2>系统维护</h2>
                
                <div class="setting-item">
                    <div class="setting-label">清理缓存</div>
                    <div class="setting-description">清理所有缓存文件，提高系统性能</div>
                    <div class="setting-control">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="clear_cache">
                            <button type="submit" class="btn btn-secondary">清理缓存</button>
                        </form>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">清理日志</div>
                    <div class="setting-description">删除30天前的日志文件</div>
                    <div class="setting-control">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="cleanup_logs">
                            <button type="submit" class="btn btn-secondary">清理日志</button>
                        </form>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">清理登录记录</div>
                    <div class="setting-description">删除7天前的登录尝试记录</div>
                    <div class="setting-control">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="cleanup_login_attempts">
                            <button type="submit" class="btn btn-secondary">清理记录</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- 系统信息 -->
            <div class="settings-section">
                <h2>系统信息</h2>
                
                <div class="setting-item">
                    <div class="setting-label">PHP版本</div>
                    <div class="setting-description"><?php echo $systemInfo['php_version']; ?></div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">MySQL版本</div>
                    <div class="setting-description"><?php echo $systemInfo['mysql_version']; ?></div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">上传限制</div>
                    <div class="setting-description">
                        文件大小: <?php echo $systemInfo['upload_max_filesize']; ?> | 
                        POST大小: <?php echo $systemInfo['post_max_size']; ?>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">内存限制</div>
                    <div class="setting-description"><?php echo $systemInfo['memory_limit']; ?></div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">执行时间限制</div>
                    <div class="setting-description"><?php echo $systemInfo['max_execution_time']; ?> 秒</div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">上传文件</div>
                    <div class="setting-description">
                        文件数量: <?php echo $storageInfo['upload_files']; ?> | 
                        占用空间: <?php echo $storageInfo['upload_size']; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .settings-section {
            background: #fff;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .settings-section h2 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 1.5rem;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }

        .setting-item {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .setting-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .setting-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .setting-description {
            color: #666;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .setting-control {
            margin-top: 12px;
        }

        .password-form {
            max-width: 400px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }

        .password-form .form-group {
            margin-bottom: 16px;
        }

        .password-form label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #333;
        }

        .password-form input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .password-form input[type="password"]:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }

        .password-form small {
            display: block;
            margin-top: 4px;
            color: #6c757d;
            font-size: 12px;
        }

        .password-form .btn {
            margin-top: 8px;
        }

        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #545b62;
        }
    </style>

    <script>
        // 密码确认验证
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');

            function validatePasswords() {
                if (newPassword.value && confirmPassword.value) {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('密码不一致');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                }
            }

            newPassword.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);

            // 表单提交前最终验证
            document.querySelector('.password-form').addEventListener('submit', function(e) {
                if (newPassword.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('新密码和确认密码不一致');
                    return false;
                }

                if (newPassword.value.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
                    e.preventDefault();
                    alert('密码长度至少需要 <?php echo PASSWORD_MIN_LENGTH; ?> 个字符');
                    return false;
                }

                return confirm('确定要修改密码吗？修改后需要重新登录。');
            });
        });
    </script>
</body>
</html>
