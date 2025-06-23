<?php
session_start();
require_once '../config/config.php';

// 检查用户登录
requireLogin('../auth/login.php');

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

if (!$user) {
    logout();
    redirect('../auth/login.php');
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'gender' => $_POST['gender'] ?? ''
    ];
    
    // 验证数据
    if (empty($data['full_name'])) {
        $errors[] = '姓名不能为空';
    }
    
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = '请输入有效的邮箱地址';
    }
    
    if (!empty($data['gender']) && !in_array($data['gender'], ['male', 'female'])) {
        $errors[] = '性别选择无效';
    }
    
    // 检查邮箱是否被其他用户使用
    if ($data['email'] !== $user['email']) {
        $existingUser = $db->fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$data['email'], $userId]);
        if ($existingUser) {
            $errors[] = '该邮箱已被其他用户使用';
        }
    }
    
    if (empty($errors)) {
        try {
            $db->update('users', [
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?: null,
                'gender' => $data['gender'] ?: null
            ], 'id = ?', [$userId]);
            
            // 更新session中的用户名
            $_SESSION['user_name'] = $data['full_name'];
            
            $success = '个人资料更新成功！';
            
            // 重新获取用户信息
            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            
        } catch (Exception $e) {
            $errors[] = '更新失败，请稍后重试';
        }
    }
}

$pageTitle = '编辑个人资料';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .profile-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .profile-form {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fff;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }
        
        .form-group small {
            display: block;
            margin-top: 6px;
            color: #6b7280;
            font-size: 0.85rem;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 16px 40px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-right: 15px;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-1px);
            color: white;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .alert ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .alert li {
            margin: 5px 0;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        @media (max-width: 768px) {
            .profile-container {
                margin: 20px auto;
                padding: 15px;
            }
            
            .profile-form {
                padding: 25px 20px;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 15px;
            }
            
            .btn-secondary {
                margin-right: 0;
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="profile-container">
        <div class="profile-header">
            <h1>✏️ 编辑个人资料</h1>
            <p>更新您的个人信息</p>
        </div>
        
        <div class="profile-form">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo h($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo h($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="full_name">姓名 *</label>
                    <input type="text" id="full_name" name="full_name" required
                           value="<?php echo h($user['full_name']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">邮箱地址 *</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo h($user['email']); ?>">
                    <small>邮箱地址用于登录和接收重要通知</small>
                </div>
                
                <div class="form-group">
                    <label for="phone">手机号码</label>
                    <input type="tel" id="phone" name="phone"
                           value="<?php echo h($user['phone'] ?? ''); ?>"
                           placeholder="请输入手机号码">
                    <small>手机号码用于账户安全验证（可选）</small>
                </div>
                
                <div class="form-group">
                    <label for="gender">性别</label>
                    <select id="gender" name="gender">
                        <option value="">请选择性别</option>
                        <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>男</option>
                        <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>女</option>
                    </select>
                    <small>性别信息用于个性化推荐</small>
                </div>
                
                <button type="submit" class="btn-submit">💾 保存更改</button>
            </form>
            
            <div class="form-actions">
                <a href="index.php" class="btn-secondary">← 返回用户中心</a>
                <div>
                    <a href="change_password.php" class="btn-secondary">🔐 修改密码</a>
                    <a href="upload_avatar.php" class="btn-secondary">📷 更换头像</a>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
