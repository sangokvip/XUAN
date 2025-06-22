<?php
// 网站安装脚本
session_start();

// 检查是否已安装
if (file_exists('config/installed.lock')) {
    die('网站已经安装完成。如需重新安装，请删除 config/installed.lock 文件。');
}

$step = (int)($_GET['step'] ?? 1);
$errors = [];
$success = '';

// 步骤1：环境检查
if ($step === 1) {
    $requirements = [
        'PHP版本 >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO扩展' => extension_loaded('pdo'),
        'PDO MySQL扩展' => extension_loaded('pdo_mysql'),
        'GD扩展' => extension_loaded('gd'),
        'Fileinfo扩展' => extension_loaded('fileinfo'),
        'uploads目录可写' => is_writable('uploads') || mkdir('uploads', 0755, true),
        'config目录可写' => is_writable('config'),
        'logs目录可写' => is_writable('logs') || mkdir('logs', 0755, true)
    ];
}

// 步骤2：数据库配置
elseif ($step === 2) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $dbHost = trim($_POST['db_host'] ?? '');
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = $_POST['db_pass'] ?? '';
        
        if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
            $errors[] = '请填写所有必填字段';
        } else {
            try {
                // 首先尝试直接连接到指定数据库
                try {
                    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                } catch (PDOException $e) {
                    // 如果数据库不存在，尝试创建
                    if (strpos($e->getMessage(), 'Unknown database') !== false) {
                        $pdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        // 尝试创建数据库
                        $pdo->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $pdo->exec("USE `{$dbName}`");
                    } else {
                        throw $e;
                    }
                }
                
                // 执行数据库结构
                $sql = file_get_contents('database/schema.sql');
                $statements = explode(';', $sql);
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        $pdo->exec($statement);
                    }
                }
                
                // 保存数据库配置
                $configContent = "<?php\n";
                $configContent .= "define('DB_HOST', '{$dbHost}');\n";
                $configContent .= "define('DB_NAME', '{$dbName}');\n";
                $configContent .= "define('DB_USER', '{$dbUser}');\n";
                $configContent .= "define('DB_PASS', '{$dbPass}');\n";
                $configContent .= "define('DB_CHARSET', 'utf8mb4');\n";
                
                file_put_contents('config/database_config.php', $configContent);
                
                $_SESSION['install_step2'] = true;
                header('Location: install.php?step=3');
                exit;
                
            } catch (Exception $e) {
                $errors[] = '数据库连接失败：' . $e->getMessage();
            }
        }
    }
}

// 步骤3：管理员账户设置
elseif ($step === 3) {
    if (!isset($_SESSION['install_step2'])) {
        header('Location: install.php?step=2');
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        $adminName = trim($_POST['admin_name'] ?? '');
        $siteUrl = trim($_POST['site_url'] ?? '');
        
        if (empty($adminEmail) || empty($adminPassword) || empty($adminName) || empty($siteUrl)) {
            $errors[] = '请填写所有字段';
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '请输入有效的邮箱地址';
        } elseif (strlen($adminPassword) < 6) {
            $errors[] = '密码至少6个字符';
        } else {
            try {
                require_once 'config/database_config.php';
                
                $pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                    DB_USER,
                    DB_PASS
                );
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // 更新管理员账户
                $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admins SET email = ?, password_hash = ?, full_name = ? WHERE id = 1");
                $stmt->execute([$adminEmail, $passwordHash, $adminName]);
                
                // 更新网站设置
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'site_url'");
                $stmt->execute([$siteUrl]);
                
                // 创建安装锁定文件
                $lockContent = "安装完成时间：" . date('Y-m-d H:i:s') . "\n";
                $lockContent .= "管理员邮箱：{$adminEmail}\n";
                file_put_contents('config/installed.lock', $lockContent);
                
                // 创建最终配置文件
                $finalConfig = "<?php\n";
                $finalConfig .= "// 网站配置\n";
                $finalConfig .= "define('SITE_URL', '{$siteUrl}');\n";
                $finalConfig .= "define('INSTALLED', true);\n";
                $finalConfig .= "\n// 包含数据库配置\n";
                $finalConfig .= "require_once __DIR__ . '/database_config.php';\n";
                
                file_put_contents('config/site_config.php', $finalConfig);
                
                $_SESSION['install_complete'] = true;
                header('Location: install.php?step=4');
                exit;
                
            } catch (Exception $e) {
                $errors[] = '安装失败：' . $e->getMessage();
            }
        }
    }
}

// 步骤4：安装完成
elseif ($step === 4) {
    if (!isset($_SESSION['install_complete'])) {
        header('Location: install.php?step=1');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>塔罗师展示平台 - 安装向导</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
            color: #333;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .install-container {
            background: #fff;
            border-radius: 15px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }
        h1 {
            color: #d4af37;
            text-align: center;
            margin-bottom: 30px;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        .step.active {
            background: #d4af37;
            color: #fff;
        }
        .step.completed {
            background: #28a745;
            color: #fff;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .btn {
            background: #d4af37;
            color: #000;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        .btn:hover {
            background: #b8860b;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .requirements {
            list-style: none;
            padding: 0;
        }
        .requirements li {
            padding: 10px;
            margin-bottom: 5px;
            border-radius: 5px;
        }
        .requirements .pass {
            background: #d4edda;
            color: #155724;
        }
        .requirements .fail {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <h1>塔罗师展示平台安装向导</h1>
        
        <div class="step-indicator">
            <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
            <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
            <div class="step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">3</div>
            <div class="step <?php echo $step >= 4 ? 'active' : ''; ?>">4</div>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($step === 1): ?>
            <h2>步骤1：环境检查</h2>
            <ul class="requirements">
                <?php foreach ($requirements as $name => $passed): ?>
                    <li class="<?php echo $passed ? 'pass' : 'fail'; ?>">
                        <?php echo $passed ? '✓' : '✗'; ?> <?php echo $name; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <?php if (array_product($requirements)): ?>
                <div class="alert alert-success">环境检查通过！</div>
                <a href="install.php?step=2" class="btn">下一步</a>
            <?php else: ?>
                <div class="alert alert-error">请解决上述问题后重新检查。</div>
                <a href="install.php?step=1" class="btn">重新检查</a>
            <?php endif; ?>
            
        <?php elseif ($step === 2): ?>
            <h2>步骤2：数据库配置</h2>

            <div class="alert alert-warning">
                <strong>注意：</strong>如果您遇到数据库权限问题，请：
                <ol>
                    <li>手动创建数据库 <code>tarot_platform</code></li>
                    <li>导入 <code>database/install.sql</code> 文件</li>
                    <li>确保数据库用户有完整权限</li>
                </ol>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="db_host">数据库主机 *</label>
                    <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="db_name">数据库名称 *</label>
                    <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'tarot_platform'); ?>" required>
                    <small>请确保此数据库已存在或用户有创建权限</small>
                </div>

                <div class="form-group">
                    <label for="db_user">数据库用户名 *</label>
                    <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="db_pass">数据库密码</label>
                    <input type="password" id="db_pass" name="db_pass">
                </div>

                <button type="submit" class="btn">连接数据库并安装</button>
            </form>
            
        <?php elseif ($step === 3): ?>
            <h2>步骤3：管理员账户设置</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="admin_email">管理员邮箱 *</label>
                    <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_password">管理员密码 *</label>
                    <input type="password" id="admin_password" name="admin_password" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_name">管理员姓名 *</label>
                    <input type="text" id="admin_name" name="admin_name" value="<?php echo htmlspecialchars($_POST['admin_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="site_url">网站URL *</label>
                    <input type="url" id="site_url" name="site_url" value="<?php echo htmlspecialchars($_POST['site_url'] ?? 'http://localhost'); ?>" required>
                </div>
                
                <button type="submit" class="btn">完成安装</button>
            </form>
            
        <?php elseif ($step === 4): ?>
            <h2>安装完成！</h2>
            <div class="alert alert-success">
                <p>恭喜！塔罗师展示平台已成功安装。</p>
                <p>为了安全起见，请删除此安装文件 (install.php)。</p>
            </div>
            
            <p><strong>下一步操作：</strong></p>
            <ul>
                <li>访问 <a href="index.php">网站首页</a></li>
                <li>访问 <a href="auth/admin_login.php">管理后台</a></li>
                <li>生成塔罗师注册链接</li>
            </ul>
            
            <a href="index.php" class="btn">访问网站</a>
        <?php endif; ?>
    </div>
</body>
</html>
