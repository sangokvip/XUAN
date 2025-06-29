<?php
session_start();
require_once '../config/config.php';
require_once '../includes/DivinationConfig.php';

$errors = [];
$success = '';
$token = $_GET['token'] ?? '';
$inviteToken = $_GET['invite'] ?? '';

// 验证token（管理员生成的注册链接）
$adminLink = null;
if (!empty($token)) {
    $db = Database::getInstance();
    $adminLink = $db->fetchOne(
        "SELECT * FROM reader_registration_links WHERE token = ? AND is_used = 0 AND expires_at > NOW()",
        [$token]
    );

    if (!$adminLink) {
        $errors[] = '管理员注册链接无效或已过期';
    }
}

// 验证邀请码（塔罗师邀请链接）
$invitation = null;
if (!empty($inviteToken)) {
    require_once '../includes/InvitationManager.php';
    $invitationManager = new InvitationManager();
    $invitation = $invitationManager->getInvitationByToken($inviteToken);
    if (!$invitation) {
        $errors[] = '邀请链接无效或已过期';
    }
}

// 必须有管理员链接或邀请码之一
if (empty($token) && empty($inviteToken)) {
    $errors[] = '无效的注册链接';
} elseif (!$adminLink && !$invitation) {
    $errors[] = '注册链接无效';
}

// 如果已登录，重定向
if (isReaderLoggedIn()) {
    redirect('../reader/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'gender' => $_POST['gender'] ?? '',
        'experience_years' => (int)($_POST['experience_years'] ?? 0),
        'description' => trim($_POST['description'] ?? ''),
        'nationality' => $_POST['nationality'] ?? 'CN',
        'divination_types' => $_POST['divination_types'] ?? [],
        'primary_identity' => $_POST['primary_identity'] ?? '',
        // 联系方式字段
        'wechat' => trim($_POST['wechat'] ?? ''),
        'qq' => trim($_POST['qq'] ?? ''),
        'xiaohongshu' => trim($_POST['xiaohongshu'] ?? ''),
        'weibo' => trim($_POST['weibo'] ?? ''),
        'other_contact' => trim($_POST['other_contact'] ?? ''),
        'contact_info' => trim($_POST['contact_info'] ?? '')
    ];

    // 处理占卜方向
    $specialties = [];
    $predefinedSpecialties = ['感情', '学业', '桃花', '财运', '事业', '运势', '寻物'];

    foreach ($predefinedSpecialties as $specialty) {
        if (isset($_POST['specialties']) && in_array($specialty, $_POST['specialties'])) {
            $specialties[] = $specialty;
        }
    }

    // 处理自定义占卜方向
    $customSpecialty = trim($_POST['custom_specialty'] ?? '');
    if (!empty($customSpecialty)) {
        // 分割多个自定义标签（用逗号或顿号分隔）
        $customTags = preg_split('/[,，、]/', $customSpecialty);
        $validCustomTags = [];

        foreach ($customTags as $tag) {
            $tag = trim($tag);
            // 检查标签长度不超过4个字
            if (!empty($tag) && mb_strlen($tag) <= 4) {
                $validCustomTags[] = $tag;
            }
        }

        // 限制自定义标签不超过3个
        $validCustomTags = array_slice($validCustomTags, 0, 3);

        // 添加到专长列表
        foreach ($validCustomTags as $tag) {
            $specialties[] = $tag;
        }
    }

    $data['specialties'] = implode('、', $specialties);

    // 验证占卜类型选择
    $divinationValidation = DivinationConfig::validateDivinationSelection(
        $data['divination_types'],
        $data['primary_identity']
    );

    if (!$divinationValidation['valid']) {
        $errors = array_merge($errors, $divinationValidation['errors']);
    }

    // 验证数据
    if (empty($data['username']) || empty($data['email']) || empty($data['password']) ||
        empty($data['full_name']) || empty($specialties) || empty($data['nationality']) ||
        empty($data['divination_types']) || empty($data['primary_identity'])) {
        $errors[] = '请填写所有必填字段';
    }

    // 验证用户名格式
    if (!empty($data['username'])) {
        if (strlen($data['username']) < 3) {
            $errors[] = '用户名至少3个字符';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            $errors[] = '用户名只能包含英文字母、数字和下划线';
        }
    }

    // 检查是否使用默认头像
    $useDefaultAvatar = isset($_POST['use_default_avatar']) && $_POST['use_default_avatar'] === '1';

    if ($useDefaultAvatar) {
        // 如果使用默认头像，检查是否选择了具体的头像
        $selectedDefaultAvatar = $_POST['selected_default_avatar'] ?? '';
        if (empty($selectedDefaultAvatar)) {
            $errors[] = '请选择一个默认头像';
        }
    } else {
        // 如果不使用默认头像，检查是否上传了文件
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = '请上传头像照片或选择使用默认头像';
        }
    }

    if ($data['password'] !== $data['confirm_password']) {
        $errors[] = '两次输入的密码不一致';
    }

    if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
        $errors[] = '密码至少需要' . PASSWORD_MIN_LENGTH . '个字符';
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = '请输入有效的邮箱地址';
    }

    if ($data['experience_years'] < 1) {
        $errors[] = '从业年数至少为1年';
    }

    // 验证联系方式（至少填写一项）
    $contactFields = ['wechat', 'qq', 'xiaohongshu', 'weibo', 'other_contact'];
    $hasContact = false;
    foreach ($contactFields as $field) {
        if (!empty($_POST[$field])) {
            $hasContact = true;
            break;
        }
    }

    if (!$hasContact) {
        $errors[] = '请至少填写一种联系方式';
    }

    // 如果没有错误，处理注册
    if (empty($errors)) {
        // 处理头像
        if ($useDefaultAvatar) {
            // 使用选择的默认头像
            $selectedDefaultAvatar = $_POST['selected_default_avatar'] ?? '';
            if (!empty($selectedDefaultAvatar)) {
                // 验证选择的头像是否有效
                $validAvatars = [
                    '../img/m1.jpg', '../img/m2.jpg', '../img/m3.jpg', '../img/m4.jpg',
                    '../img/f1.jpg', '../img/f2.jpg', '../img/f3.jpg', '../img/f4.jpg'
                ];
                if (in_array($selectedDefaultAvatar, $validAvatars)) {
                    $data['photo'] = $selectedDefaultAvatar;
                } else {
                    // 如果选择的头像无效，使用第一个默认头像
                    $data['photo'] = $data['gender'] === 'male' ? '../img/m1.jpg' : '../img/f1.jpg';
                }
            } else {
                // 如果没有选择具体头像，使用第一个默认头像
                $data['photo'] = $data['gender'] === 'male' ? '../img/m1.jpg' : '../img/f1.jpg';
            }

            // 优先使用管理员token，如果没有则使用邀请token
            $registrationToken = !empty($token) ? $token : null;
            $result = registerReader($data, $registrationToken, $inviteToken);
            if ($result['success']) {
                $success = '注册成功！请使用您的用户名和密码登录。';
                // 清空表单数据
                $data = [];
            } else {
                $errors = $result['errors'];
            }
        } else {
            // 处理头像上传
            $file = $_FILES['photo'];

        // 使用绝对路径确保目录存在（从auth目录访问上级目录）
        $absolutePhotoPath = '../' . PHOTO_PATH;
        if (!is_dir($absolutePhotoPath)) {
            mkdir($absolutePhotoPath, 0777, true);
        }
        chmod($absolutePhotoPath, 0777);

        // 验证文件类型
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($extension, $allowedTypes)) {
            $errors[] = '只允许上传 JPG、PNG、GIF 格式的头像';
        } elseif ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = '头像文件大小不能超过 ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
        } else {
            // 生成新文件名
            $fileName = md5(uniqid() . time()) . '.' . $extension;
            $targetPath = $absolutePhotoPath . $fileName;

            // 直接上传文件
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // 设置文件权限
                chmod($targetPath, 0644);

                // 保存相对路径到数据库（相对于网站根目录）
                $data['photo'] = PHOTO_PATH . $fileName;

                // 处理圆形头像
                if (!empty($_POST['photo_circle_data'])) {
                    $circleData = $_POST['photo_circle_data'];
                    if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $circleData, $matches)) {
                        $imageType = $matches[1];
                        $imageData = base64_decode($matches[2]);

                        if ($imageData !== false) {
                            $circleFileName = 'circle_' . md5(uniqid() . time()) . '.jpg';
                            $circleTargetPath = $absolutePhotoPath . $circleFileName;

                            if (file_put_contents($circleTargetPath, $imageData)) {
                                chmod($circleTargetPath, 0644);
                                $data['photo_circle'] = PHOTO_PATH . $circleFileName;
                            }
                        }
                    }
                }

                // 优先使用管理员token，如果没有则使用邀请token
                $registrationToken = !empty($token) ? $token : null;
                $result = registerReader($data, $registrationToken, $inviteToken);
                if ($result['success']) {
                    $success = '注册成功！请使用您的用户名和密码登录。';
                    // 清空表单数据
                    $data = [];
                } else {
                    $errors = $result['errors'];
                    // 删除已上传的头像
                    if (file_exists($targetPath)) {
                        unlink($targetPath);
                    }
                }
            } else {
                $errors[] = '头像上传失败，请检查目录权限';
            }
        }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>占卜师注册 - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/image-cropper.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* 重新设计的注册页面样式 */
        .auth-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .auth-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .auth-form {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 32px 64px rgba(0, 0, 0, 0.15);
            padding: 0;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .register-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .register-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }

        .register-header p {
            margin: 10px 0 0 0;
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .register-content {
            padding: 50px;
        }

        .form-section {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            position: relative;
        }

        .section-title::before {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
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
        .form-group select:focus,
        .form-group textarea:focus {
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

        .required-mark {
            color: #ef4444;
            margin-left: 2px;
        }

        /* 头像上传区域美化 */
        .photo-upload-section {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 16px;
            padding: 30px;
            border: 2px dashed #cbd5e0;
            text-align: center;
            transition: all 0.3s ease;
        }

        .photo-upload-section:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f4ff 0%, #e0e7ff 100%);
        }

        .photo-upload-controls {
            display: flex;
            gap: 15px;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .photo-upload-controls input[type="file"] {
            flex: 1;
            min-width: 250px;
            max-width: 400px;
        }

        .default-avatar-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            white-space: nowrap;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .default-avatar-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }

        .default-avatar-btn.active {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .photo-previews {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 25px;
        }

        .photo-preview {
            text-align: center;
        }

        .photo-preview img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 12px;
            border: 3px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .photo-preview.circle img {
            border-radius: 50%;
        }

        .photo-preview-label {
            margin-top: 8px;
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 500;
        }

        .crop-button {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            margin-top: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        .crop-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.4);
        }

        /* 头像选择区域样式 */
        .avatar-choice-section {
            margin-top: 15px;
        }

        .avatar-choice-tabs {
            display: flex;
            margin-bottom: 20px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .avatar-tab {
            flex: 1;
            padding: 12px 20px;
            border: none;
            background: #f1f5f9;
            color: #64748b;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .avatar-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .avatar-tab:hover:not(.active) {
            background: #e2e8f0;
            color: #475569;
        }

        .avatar-section {
            display: none;
        }

        .avatar-section.active {
            display: block;
        }

        .default-avatars-grid {
            text-align: center;
        }

        .avatar-hint {
            color: #6b7280;
            margin-bottom: 20px;
            font-style: italic;
        }

        .gender-avatars {
            margin-bottom: 25px;
        }

        .gender-avatars h4 {
            color: #374151;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .avatars-row {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .avatar-option {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s ease;
            position: relative;
        }

        .avatar-option:hover {
            border-color: #667eea;
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .avatar-option.selected {
            border-color: #10b981;
            box-shadow: 0 0 0 2px #10b981;
        }

        .avatar-option img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .avatar-option img:not([src]),
        .avatar-option img[src=""] {
            display: none;
        }

        .avatar-option.selected::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #10b981;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        /* 专长选择区域美化 */
        .specialty-section {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 16px;
            padding: 30px;
            border: 2px solid #f59e0b;
        }

        .specialty-quick-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .quick-action-btn {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(107, 114, 128, 0.3);
        }

        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.4);
        }

        .specialty-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .specialty-card {
            background: white;
            border-radius: 12px;
            padding: 20px 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .specialty-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .specialty-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
            border-color: #667eea;
        }

        .specialty-card:hover::before {
            opacity: 0.05;
        }

        .specialty-card.selected {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .specialty-card input[type="checkbox"] {
            display: none;
        }

        .specialty-text {
            font-weight: 600;
            font-size: 1rem;
            position: relative;
            z-index: 1;
        }

        .specialty-icon {
            font-size: 1.5rem;
            margin-bottom: 8px;
            display: block;
            position: relative;
            z-index: 1;
        }

        .custom-specialty-input {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 2px solid #e5e7eb;
        }

        .custom-specialty-input input {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 12px 16px;
        }

        /* 提交按钮美化 */
        .submit-section {
            text-align: center;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #e5e7eb;
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 18px 50px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
        }

        .btn-submit:active {
            transform: translateY(-1px);
        }

        /* 链接样式 */
        .auth-links {
            text-align: center;
            margin-top: 30px;
            padding: 25px;
            background: #f8fafc;
            border-radius: 16px;
        }

        .auth-links p {
            margin: 8px 0;
            color: #6b7280;
        }

        .auth-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .auth-links a:hover {
            color: #764ba2;
        }

        /* 警告和成功消息 */
        .alert {
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border: none;
        }

        .alert-error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #dc2626;
            border-left: 4px solid #ef4444;
        }

        .alert-success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #16a34a;
            border-left: 4px solid #22c55e;
        }

        .alert ul {
            margin: 0;
            padding-left: 20px;
        }

        .alert li {
            margin: 5px 0;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .auth-container {
                padding: 0 15px;
            }

            .register-content {
                padding: 30px 25px;
            }

            .register-header {
                padding: 30px 25px;
            }

            .register-header h1 {
                font-size: 2rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .specialty-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .specialty-card {
                padding: 15px 10px;
            }

            .photo-upload-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .photo-upload-controls input[type="file"] {
                min-width: auto;
                max-width: none;
            }

            .default-avatar-btn {
                width: 100%;
                text-align: center;
            }

            .photo-previews {
                flex-direction: column;
                align-items: center;
            }

            .specialty-quick-actions {
                justify-content: center;
            }

            .btn-submit {
                width: 100%;
                padding: 16px 30px;
            }
        }

        @media (max-width: 480px) {
            .specialty-grid {
                grid-template-columns: 1fr;
            }

            .register-header h1 {
                font-size: 1.8rem;
            }

            .section-title {
                font-size: 1.2rem;
            }
        }

        /* 动画效果 */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-section {
            animation: fadeInUp 0.6s ease forwards;
        }

        .form-section:nth-child(1) { animation-delay: 0.1s; }
        .form-section:nth-child(2) { animation-delay: 0.2s; }
        .form-section:nth-child(3) { animation-delay: 0.3s; }
        .form-section:nth-child(4) { animation-delay: 0.4s; }

        .specialty-card {
            animation: slideInLeft 0.5s ease forwards;
        }

        .specialty-card:nth-child(1) { animation-delay: 0.1s; }
        .specialty-card:nth-child(2) { animation-delay: 0.15s; }
        .specialty-card:nth-child(3) { animation-delay: 0.2s; }
        .specialty-card:nth-child(4) { animation-delay: 0.25s; }
        .specialty-card:nth-child(5) { animation-delay: 0.3s; }
        .specialty-card:nth-child(6) { animation-delay: 0.35s; }
        .specialty-card:nth-child(7) { animation-delay: 0.4s; }

        /* 占卜类型选择样式 */
        .divination-section {
            margin-top: 20px;
        }

        .divination-category {
            margin-bottom: 30px;
        }

        .category-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #374151;
        }

        .category-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            color: white;
        }

        .western-badge {
            background: linear-gradient(135deg, #8b5cf6, #a855f7);
        }

        .eastern-badge {
            background: linear-gradient(135deg, #374151, #4b5563);
        }

        .divination-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .divination-card {
            position: relative;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .divination-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }

        .divination-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f4ff, #e0e7ff);
        }

        .divination-card input[type="checkbox"] {
            display: none;
        }

        .divination-text {
            font-weight: 500;
            color: #374151;
            margin-bottom: 10px;
        }

        .primary-radio {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .divination-card.selected .primary-radio {
            opacity: 1;
        }

        .primary-radio input[type="radio"] {
            margin: 0;
        }

        .primary-radio label {
            font-size: 0.8rem;
            color: #667eea;
            font-weight: 500;
            margin: 0;
        }

        .divination-help {
            margin-top: 20px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-form">
            <div class="register-header">
                <h1>✨ 占卜师注册</h1>
<parameter name="p">加入我们的专业占卜师团队，开启您的占卜之旅</p>
            </div>

            <div class="register-content">
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
                        <p><a href="reader_login.php">立即登录</a></p>
                    </div>
                <?php else: ?>
                <form method="POST" enctype="multipart/form-data">
                    <!-- 头像上传区域 -->
                    <div class="form-section">
                        <h3 class="section-title">📸 头像设置</h3>
                        <div class="photo-upload-section">
                            <div class="form-group">
                                <label for="photo">头像照片 <span class="required-mark">*</span></label>

                                <!-- 头像选择方式 -->
                                <div class="avatar-choice-section">
                                    <div class="avatar-choice-tabs">
                                        <button type="button" id="upload-tab" class="avatar-tab active">上传头像</button>
                                        <button type="button" id="default-tab" class="avatar-tab">选择默认头像</button>
                                    </div>

                                    <!-- 上传头像区域 -->
                                    <div id="upload-section" class="avatar-section active">
                                        <div class="photo-upload-controls">
                                            <input type="file" id="photo" name="photo" accept="image/*" required>
                                        </div>
                                        <small>请上传清晰的个人照片，支持JPG、PNG格式，文件大小不超过5MB。上传后可以剪裁圆形头像用于首页展示。</small>
                                    </div>

                                    <!-- 默认头像选择区域 -->
                                    <div id="default-section" class="avatar-section">
                                        <div class="default-avatars-grid">
                                            <p class="avatar-hint">请选择一个默认头像：</p>

                                            <div class="all-avatars" style="display: block;">
                                                <h4>男性头像</h4>
                                                <div class="avatars-row">
                                                    <div class="avatar-option" data-avatar="../img/m1.jpg">
                                                        <img src="../img/m1.jpg" alt="男性头像1">
                                                    </div>
                                                    <div class="avatar-option" data-avatar="../img/m2.jpg">
                                                        <img src="../img/m2.jpg" alt="男性头像2">
                                                    </div>
                                                    <div class="avatar-option" data-avatar="../img/m3.jpg">
                                                        <img src="../img/m3.jpg" alt="男性头像3">
                                                    </div>
                                                    <div class="avatar-option" data-avatar="../img/m4.jpg">
                                                        <img src="../img/m4.jpg" alt="男性头像4">
                                                    </div>
                                                </div>

                                                <h4 style="margin-top: 20px;">女性头像</h4>
                                                <div class="avatars-row">
                                                    <div class="avatar-option" data-avatar="../img/f1.jpg">
                                                        <img src="../img/f1.jpg" alt="女性头像1">
                                                    </div>
                                                    <div class="avatar-option" data-avatar="../img/f2.jpg">
                                                        <img src="../img/f2.jpg" alt="女性头像2">
                                                    </div>
                                                    <div class="avatar-option" data-avatar="../img/f3.jpg">
                                                        <img src="../img/f3.jpg" alt="女性头像3">
                                                    </div>
                                                    <div class="avatar-option" data-avatar="../img/f4.jpg">
                                                        <img src="../img/f4.jpg" alt="女性头像4">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <small>选择一个默认头像作为您的占卜师头像。</small>
                                    </div>
                                </div>

                                <input type="hidden" id="photo_circle_data" name="photo_circle_data">
                                <input type="hidden" id="use_default_avatar" name="use_default_avatar" value="0">
                                <input type="hidden" id="selected_default_avatar" name="selected_default_avatar" value="">

                                <div class="photo-previews" id="photo-previews" style="display: none;">
                                    <div class="photo-preview">
                                        <img id="original-preview" src="" alt="原始照片">
                                        <div class="photo-preview-label">完整照片</div>
                                    </div>
                                    <div class="photo-preview circle">
                                        <img id="circle-preview" src="" alt="圆形头像">
                                        <div class="photo-preview-label">圆形头像</div>
                                    </div>
                                </div>

                                <button type="button" id="crop-photo-btn" class="crop-button" style="display: none;">重新剪裁圆形头像</button>
                            </div>
                        </div>
                    </div>

                    <!-- 基本信息 -->
                    <div class="form-section">
                        <h3 class="section-title">👤 基本信息</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="username">用户名 <span class="required-mark">*</span></label>
                                <input type="text" id="username" name="username" required
                                       placeholder="请输入登录用户名"
                                       value="<?php echo h($_POST['username'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="email">邮箱地址 <span class="required-mark">*</span></label>
                                <input type="email" id="email" name="email" required
                                       placeholder="请输入邮箱地址"
                                       value="<?php echo h($_POST['email'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="full_name">占卜师昵称 <span class="required-mark">*</span></label>
                                <input type="text" id="full_name" name="full_name" required
                                       placeholder="请输入您的占卜师昵称"
                                       value="<?php echo h($_POST['full_name'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="phone">手机号码</label>
                                <input type="tel" id="phone" name="phone"
                                       placeholder="请输入手机号码"
                                       value="<?php echo h($_POST['phone'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="gender">性别 <span class="required-mark">*</span></label>
                                <select id="gender" name="gender" required>
                                    <option value="">请选择性别</option>
                                    <option value="male" <?php echo ($_POST['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>男</option>
                                    <option value="female" <?php echo ($_POST['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>女</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="experience_years">从业年数 <span class="required-mark">*</span></label>
                                <input type="number" id="experience_years" name="experience_years" required min="1" max="50"
                                       placeholder="请输入从业年数"
                                       value="<?php echo h($_POST['experience_years'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="nationality">国籍 <span class="required-mark">*</span></label>
                                <select id="nationality" name="nationality" required>
                                    <option value="">请选择国籍</option>
                                    <?php
                                    $nationalities = DivinationConfig::getNationalities();
                                    $selectedNationality = $_POST['nationality'] ?? 'CN';
                                    foreach ($nationalities as $code => $name):
                                    ?>
                                        <option value="<?php echo h($code); ?>" <?php echo $selectedNationality === $code ? 'selected' : ''; ?>>
                                            <?php echo h($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 专长选择 -->
                    <div class="form-section">
                        <h3 class="section-title">🔮 擅长领域</h3>
                        <div class="specialty-section">
                            <label>擅长的占卜方向 <span class="required-mark">*</span> (可多选)</label>

                            <!-- 一键选择按钮 -->
                            <div class="specialty-quick-actions">
                                <button type="button" class="quick-action-btn" onclick="selectAllSpecialties()">全选</button>
                                <button type="button" class="quick-action-btn" onclick="clearAllSpecialties()">清空</button>
                                <button type="button" class="quick-action-btn" onclick="selectPopularSpecialties()">选择热门</button>
                            </div>

                            <div class="specialty-grid">
                                <?php
                                $predefinedSpecialties = [
                                    '感情' => '💕',
                                    '学业' => '📚',
                                    '桃花' => '🌸',
                                    '财运' => '💰',
                                    '事业' => '💼',
                                    '运势' => '🍀',
                                    '寻物' => '🔍'
                                ];
                                $selectedSpecialties = $_POST['specialties'] ?? [];
                                foreach ($predefinedSpecialties as $specialty => $icon):
                                ?>
                                    <div class="specialty-card <?php echo in_array($specialty, $selectedSpecialties) ? 'selected' : ''; ?>"
                                         onclick="toggleSpecialty(this)">
                                        <input type="checkbox" name="specialties[]" value="<?php echo h($specialty); ?>"
                                               <?php echo in_array($specialty, $selectedSpecialties) ? 'checked' : ''; ?>>
                                        <span class="specialty-icon"><?php echo $icon; ?></span>
                                        <span class="specialty-text"><?php echo h($specialty); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="custom-specialty-input">
                                <label for="custom_specialty">其他占卜方向（可选）</label>
                                <input type="text" id="custom_specialty" name="custom_specialty"
                                       placeholder="请填写其他擅长方向，用逗号分隔，每个不超过4字，最多3个"
                                       value="<?php echo h($_POST['custom_specialty'] ?? ''); ?>">
                                <small>注意：自定义标签只在个人页面显示，列表页面只显示系统标准标签</small>
                            </div>
                        </div>
                    </div>

                    <!-- 占卜类型选择 -->
                    <div class="form-section">
                        <h3 class="section-title">🔮 占卜类型</h3>
                        <div class="divination-section">
                            <label>占卜类型 <span class="required-mark">*</span> (最多选择3项，其中1项作为主要身份标签)</label>

                            <?php
                            $allDivinationTypes = DivinationConfig::getAllDivinationTypes();
                            $selectedTypes = $_POST['divination_types'] ?? [];
                            $primaryIdentity = $_POST['primary_identity'] ?? '';
                            ?>

                            <?php foreach ($allDivinationTypes as $category => $categoryData): ?>
                                <div class="divination-category">
                                    <h4 class="category-title <?php echo $category; ?>-category">
                                        <?php echo h($categoryData['name']); ?>
                                        <span class="category-badge <?php echo $category; ?>-badge"><?php echo $categoryData['color'] === 'purple' ? '紫' : '黑'; ?></span>
                                    </h4>
                                    <div class="divination-grid">
                                        <?php foreach ($categoryData['types'] as $typeKey => $typeName): ?>
                                            <div class="divination-card <?php echo in_array($typeKey, $selectedTypes) ? 'selected' : ''; ?>"
                                                 onclick="toggleDivinationType(this, '<?php echo $typeKey; ?>')">
                                                <input type="checkbox" name="divination_types[]" value="<?php echo h($typeKey); ?>"
                                                       <?php echo in_array($typeKey, $selectedTypes) ? 'checked' : ''; ?>>
                                                <span class="divination-text"><?php echo h($typeName); ?></span>
                                                <div class="primary-radio">
                                                    <input type="radio" name="primary_identity" value="<?php echo h($typeKey); ?>"
                                                           <?php echo $primaryIdentity === $typeKey ? 'checked' : ''; ?>
                                                           onclick="event.stopPropagation();">
                                                    <label>主要</label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="divination-help">
                                <small>
                                    <strong>说明：</strong><br>
                                    • 西玄占卜师标签为<span style="color: purple; font-weight: bold;">紫色</span><br>
                                    • 东玄占卜师标签为<span style="color: black; font-weight: bold;">黑色</span><br>
                                    • 主要身份标签将在您的个人页面和列表中显示<br>
                                    • 其他选择的类型将作为技能项展示
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- 个人简介 -->
                    <div class="form-section">
                        <h3 class="section-title">📝 个人简介</h3>
                        <div class="form-group">
                            <label for="description">个人简介</label>
                            <textarea id="description" name="description" rows="4"
                                      placeholder="请简单介绍您的占卜经历和服务特色，让用户更好地了解您"><?php echo h($_POST['description'] ?? ''); ?></textarea>
                            <small>简介将显示在您的个人页面，帮助用户了解您的专业背景</small>
                        </div>
                    </div>

                    <!-- 账户安全 -->
                    <div class="form-section">
                        <h3 class="section-title">🔐 账户安全</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="password">登录密码 <span class="required-mark">*</span></label>
                                <input type="password" id="password" name="password" required
                                       placeholder="请输入登录密码">
                                <small>至少<?php echo PASSWORD_MIN_LENGTH; ?>个字符</small>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">确认密码 <span class="required-mark">*</span></label>
                                <input type="password" id="confirm_password" name="confirm_password" required
                                       placeholder="请再次输入密码">
                            </div>
                        </div>
                    </div>

                    <!-- 联系方式设置 -->
                    <div class="form-section">
                        <h3 class="section-title">📞 联系方式</h3>
                        <p class="section-description">请至少填写一种联系方式，用户查看后可以通过这些方式联系您</p>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="wechat">微信号</label>
                                <input type="text" id="wechat" name="wechat"
                                       placeholder="请输入微信号"
                                       value="<?php echo h($_POST['wechat'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="qq">QQ号</label>
                                <input type="text" id="qq" name="qq"
                                       placeholder="请输入QQ号"
                                       value="<?php echo h($_POST['qq'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="xiaohongshu">小红书</label>
                                <input type="text" id="xiaohongshu" name="xiaohongshu"
                                       placeholder="请输入小红书账号"
                                       value="<?php echo h($_POST['xiaohongshu'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="weibo">微博</label>
                                <input type="text" id="weibo" name="weibo"
                                       placeholder="请输入微博账号"
                                       value="<?php echo h($_POST['weibo'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="other_contact">其他联系方式</label>
                                <input type="text" id="other_contact" name="other_contact"
                                       placeholder="请输入其他联系方式"
                                       value="<?php echo h($_POST['other_contact'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="contact_info">联系信息描述</label>
                            <textarea id="contact_info" name="contact_info" rows="3"
                                      placeholder="请简单介绍您的服务时间、预约方式等信息"><?php echo h($_POST['contact_info'] ?? ''); ?></textarea>
                            <small>例如：工作时间9:00-21:00，请提前预约</small>
                        </div>
                    </div>

                    <!-- 提交按钮 -->
                    <div class="submit-section">
                        <button type="submit" class="btn-submit">立即注册</button>
                    </div>
                </form>
                <?php endif; ?>

                <div class="auth-links">
                    <p>已有账户？<a href="reader_login.php">立即登录</a></p>
                    <p><a href="../index.php">返回首页</a></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 专长选择功能
        function toggleSpecialty(card) {
            const checkbox = card.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;

            if (checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        }

        function selectAllSpecialties() {
            const cards = document.querySelectorAll('.specialty-card');
            cards.forEach(card => {
                const checkbox = card.querySelector('input[type="checkbox"]');
                checkbox.checked = true;
                card.classList.add('selected');
            });
        }

        function clearAllSpecialties() {
            const cards = document.querySelectorAll('.specialty-card');
            cards.forEach(card => {
                const checkbox = card.querySelector('input[type="checkbox"]');
                checkbox.checked = false;
                card.classList.remove('selected');
            });
        }

        // 占卜类型选择功能
        function toggleDivinationType(card, typeKey) {
            const checkbox = card.querySelector('input[type="checkbox"]');
            const radio = card.querySelector('input[type="radio"]');

            // 检查当前选择数量
            const selectedCards = document.querySelectorAll('.divination-card.selected');

            if (!checkbox.checked && selectedCards.length >= 3) {
                alert('最多只能选择3种占卜类型');
                return;
            }

            checkbox.checked = !checkbox.checked;

            if (checkbox.checked) {
                card.classList.add('selected');
                // 如果是第一个选择的，自动设为主要身份
                const checkedBoxes = document.querySelectorAll('.divination-card input[type="checkbox"]:checked');
                if (checkedBoxes.length === 1) {
                    radio.checked = true;
                }
            } else {
                card.classList.remove('selected');
                // 如果取消选择的是主要身份，清除主要身份选择
                if (radio.checked) {
                    radio.checked = false;
                    // 自动选择第一个剩余的作为主要身份
                    const remainingChecked = document.querySelectorAll('.divination-card input[type="checkbox"]:checked');
                    if (remainingChecked.length > 0) {
                        const firstRemaining = remainingChecked[0].closest('.divination-card').querySelector('input[type="radio"]');
                        firstRemaining.checked = true;
                    }
                }
            }
        }

        function selectPopularSpecialties() {
            // 选择热门专长：感情、事业、财运
            const popularSpecialties = ['感情', '事业', '财运'];
            const cards = document.querySelectorAll('.specialty-card');

            // 先清空所有选择
            clearAllSpecialties();

            // 选择热门专长
            cards.forEach(card => {
                const checkbox = card.querySelector('input[type="checkbox"]');
                if (popularSpecialties.includes(checkbox.value)) {
                    checkbox.checked = true;
                    card.classList.add('selected');
                }
            });
        }

        function selectPopularSpecialties() {
            // 清空所有选择
            clearAllSpecialties();

            // 选择热门方向：感情、事业、财运
            const popularSpecialties = ['感情', '事业', '财运'];
            const cards = document.querySelectorAll('.specialty-card');

            cards.forEach(card => {
                const checkbox = card.querySelector('input[type="checkbox"]');
                if (popularSpecialties.includes(checkbox.value)) {
                    checkbox.checked = true;
                    card.classList.add('selected');
                }
            });
        }

        // 检查图片剪裁工具加载状态
        function checkImageCropperLoaded() {
            console.log('检查图片剪裁工具状态...');
            console.log('window.imageCropper:', window.imageCropper);
            console.log('window.simpleCropper:', window.simpleCropper);
            console.log('window.ImageCropper:', window.ImageCropper);

            if (window.imageCropper) {
                console.log('✅ 图片剪裁工具已加载');
                return true;
            } else if (window.ImageCropper) {
                console.log('✅ ImageCropper 类已加载，正在初始化...');
                window.imageCropper = new window.ImageCropper();
                return true;
            } else {
                console.error('❌ 图片剪裁工具未加载');
                return false;
            }
        }

        // 表单验证
        document.addEventListener('DOMContentLoaded', function() {
            // 检查图片剪裁工具
            setTimeout(checkImageCropperLoaded, 2000);
            const form = document.querySelector('form');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');

            // 密码确认验证
            function validatePasswords() {
                if (password.value && confirmPassword.value) {
                    if (password.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('密码不一致');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                }
            }

            password.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);

            // 表单提交验证
            form.addEventListener('submit', function(e) {
                // 检查是否选择了专长
                const selectedSpecialties = document.querySelectorAll('input[name="specialties[]"]:checked');
                if (selectedSpecialties.length === 0) {
                    e.preventDefault();
                    alert('请至少选择一个擅长的占卜方向');
                    return false;
                }

                // 检查是否选择了占卜类型
                const selectedDivinationTypes = document.querySelectorAll('input[name="divination_types[]"]:checked');
                if (selectedDivinationTypes.length === 0) {
                    e.preventDefault();
                    alert('请至少选择一种占卜类型');
                    return false;
                }

                if (selectedDivinationTypes.length > 3) {
                    e.preventDefault();
                    alert('最多只能选择3种占卜类型');
                    return false;
                }

                // 检查是否选择了主要身份标签
                const primaryIdentity = document.querySelector('input[name="primary_identity"]:checked');
                if (!primaryIdentity) {
                    e.preventDefault();
                    alert('请选择一个主要身份标签');
                    return false;
                }

                // 检查密码
                if (password.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('两次输入的密码不一致');
                    return false;
                }

                // 检查联系方式（至少填写一项）
                const contactFields = ['wechat', 'qq', 'xiaohongshu', 'weibo', 'other_contact'];
                let hasContact = false;
                for (let field of contactFields) {
                    const input = document.getElementById(field);
                    if (input && input.value.trim() !== '') {
                        hasContact = true;
                        break;
                    }
                }

                if (!hasContact) {
                    e.preventDefault();
                    alert('请至少填写一种联系方式');
                    return false;
                }

                return true;
            });
        });

        // 头像选择功能
        document.addEventListener('DOMContentLoaded', function() {
            const uploadTab = document.getElementById('upload-tab');
            const defaultTab = document.getElementById('default-tab');
            const uploadSection = document.getElementById('upload-section');
            const defaultSection = document.getElementById('default-section');
            const genderSelect = document.getElementById('gender');
            // 不再需要分别的男女头像容器，因为现在都显示在一起
            const photoInput = document.getElementById('photo');
            const useDefaultAvatarInput = document.getElementById('use_default_avatar');
            const selectedDefaultAvatarInput = document.getElementById('selected_default_avatar');

            // 切换头像选择方式
            uploadTab.addEventListener('click', function() {
                uploadTab.classList.add('active');
                defaultTab.classList.remove('active');
                uploadSection.classList.add('active');
                defaultSection.classList.remove('active');
                photoInput.required = true;
                useDefaultAvatarInput.value = '0';
                selectedDefaultAvatarInput.value = '';
                // 清除默认头像选择
                document.querySelectorAll('.avatar-option').forEach(option => {
                    option.classList.remove('selected');
                });
            });

            defaultTab.addEventListener('click', function() {
                defaultTab.classList.add('active');
                uploadTab.classList.remove('active');
                defaultSection.classList.add('active');
                uploadSection.classList.remove('active');
                photoInput.required = false;
                photoInput.value = '';
                useDefaultAvatarInput.value = '1';
            });

            // 性别选择变化时的处理（现在不需要控制头像显示）
            genderSelect.addEventListener('change', function() {
                // 性别改变时不需要清除头像选择，用户可以自由选择任何头像
                console.log('性别已选择:', this.value);
            });

            // 默认头像选择
            document.querySelectorAll('.avatar-option').forEach(option => {
                option.addEventListener('click', function() {
                    // 清除其他选择
                    document.querySelectorAll('.avatar-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    // 选择当前头像
                    this.classList.add('selected');
                    selectedDefaultAvatarInput.value = this.dataset.avatar;
                });
            });

            // 处理图片加载错误
            document.querySelectorAll('.avatar-option img').forEach(img => {
                img.addEventListener('error', function() {
                    console.error('图片加载失败:', this.src);
                    this.style.display = 'none';
                    // 可以在这里添加一个占位符
                    const placeholder = document.createElement('div');
                    placeholder.style.cssText = 'width: 100%; height: 100%; background: #f3f4f6; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 12px;';
                    placeholder.textContent = '图片加载失败';
                    this.parentNode.appendChild(placeholder);
                });

                img.addEventListener('load', function() {
                    console.log('图片加载成功:', this.src);
                });
            });
        });
    </script>

    <script src="../assets/js/image-cropper.js?v=<?php echo time(); ?>" onload="console.log('image-cropper.js 加载成功')" onerror="console.error('image-cropper.js 加载失败')"></script>
    <script src="../assets/js/simple-cropper.js?v=<?php echo time(); ?>" onload="console.log('simple-cropper.js 加载成功')" onerror="console.error('simple-cropper.js 加载失败')"></script>
    <script>
        // 简单的Canvas圆形剪裁函数 - 提前定义
        function createSimpleCircleCrop(file) {
            return new Promise((resolve, reject) => {
                console.log('createSimpleCircleCrop: 开始处理文件');
                const reader = new FileReader();
                reader.onload = function(e) {
                    console.log('createSimpleCircleCrop: FileReader加载完成');
                    const img = new Image();
                    img.onload = function() {
                        console.log('createSimpleCircleCrop: 图片加载完成', img.width, 'x', img.height);
                        try {
                            const canvas = document.createElement('canvas');
                            const ctx = canvas.getContext('2d');
                            const size = 300;

                            canvas.width = size;
                            canvas.height = size;

                            // 计算缩放比例，确保图片完全填充圆形
                            const scale = Math.max(size / img.width, size / img.height);
                            const scaledWidth = img.width * scale;
                            const scaledHeight = img.height * scale;
                            const x = (size - scaledWidth) / 2;
                            const y = (size - scaledHeight) / 2;

                            console.log('createSimpleCircleCrop: 缩放参数', {scale, scaledWidth, scaledHeight, x, y});

                            // 创建圆形剪裁路径
                            ctx.save();
                            ctx.beginPath();
                            ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
                            ctx.clip();

                            // 绘制图片
                            ctx.drawImage(img, x, y, scaledWidth, scaledHeight);
                            ctx.restore();

                            console.log('createSimpleCircleCrop: 图片绘制完成，转换为Blob');

                            // 转换为Blob
                            canvas.toBlob(resolve, 'image/jpeg', 0.8);
                        } catch (error) {
                            console.error('createSimpleCircleCrop: Canvas操作失败', error);
                            reject(error);
                        }
                    };
                    img.onerror = function() {
                        console.error('createSimpleCircleCrop: 图片加载失败');
                        reject(new Error('图片加载失败'));
                    };
                    img.src = e.target.result;
                };
                reader.onerror = function() {
                    console.error('createSimpleCircleCrop: 文件读取失败');
                    reject(new Error('文件读取失败'));
                };
                reader.readAsDataURL(file);
            });
        }

        // 头像上传和剪裁功能
        document.addEventListener('DOMContentLoaded', function() {
            const photoInput = document.getElementById('photo');
            const photoCircleData = document.getElementById('photo_circle_data');
            const photoPreviews = document.getElementById('photo-previews');
            const originalPreview = document.getElementById('original-preview');
            const circlePreview = document.getElementById('circle-preview');
            const cropButton = document.getElementById('crop-photo-btn');
            const useDefaultAvatarInput = document.getElementById('use_default_avatar');

            let originalFile = null;
            let circleBlob = null;
            let usingDefaultAvatar = false;

            // 注意：我们已经移除了默认头像按钮，现在使用标签页切换

            photoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;

                console.log('文件选择:', file.name, file.type, file.size);

                // 验证文件类型
                if (!file.type.startsWith('image/')) {
                    alert('请选择图片文件');
                    return;
                }

                // 验证文件大小 (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('图片文件大小不能超过5MB');
                    return;
                }

                // 取消默认头像选择
                if (usingDefaultAvatar) {
                    usingDefaultAvatar = false;
                    useDefaultAvatarInput.value = '0';
                    photoInput.required = true;
                }

                originalFile = file;

                // 显示原始图片预览
                const reader = new FileReader();
                reader.onload = function(e) {
                    console.log('原始图片加载完成');
                    originalPreview.src = e.target.result;
                    originalPreview.style.display = 'block';
                    photoPreviews.style.display = 'flex';

                    // 延迟一点再打开剪裁工具，确保预览显示
                    setTimeout(() => {
                        console.log('准备开始剪裁，检查工具状态...');
                        checkImageCropperLoaded();
                        cropPhoto(file);
                    }, 500);
                };
                reader.readAsDataURL(file);
            });

            cropButton.addEventListener('click', function() {
                if (originalFile) {
                    cropPhoto(originalFile);
                }
            });

            function cropPhoto(file) {
                console.log('cropPhoto: 开始剪裁图片:', file.name);
                console.log('cropPhoto: 文件大小:', file.size, '字节');
                console.log('cropPhoto: 文件类型:', file.type);

                // 检查createSimpleCircleCrop函数是否存在
                if (typeof createSimpleCircleCrop !== 'function') {
                    console.error('cropPhoto: createSimpleCircleCrop函数未定义');
                    return;
                }

                // 直接使用内置的Canvas剪裁功能
                console.log('cropPhoto: 调用createSimpleCircleCrop函数');
                createSimpleCircleCrop(file)
                    .then(blob => {
                        console.log('cropPhoto: Canvas剪裁成功，blob大小:', blob.size);
                        handleCropSuccess(blob);
                    })
                    .catch(error => {
                        console.error('cropPhoto: Canvas剪裁失败:', error);
                        // 如果Canvas剪裁失败，则跳过圆形头像
                        circlePreview.style.display = 'none';
                        cropButton.style.display = 'none';

                        const hint = document.createElement('div');
                        hint.style.cssText = 'color: #f59e0b; font-size: 14px; margin-top: 10px; padding: 10px; background: #fef3c7; border-radius: 5px;';
                        hint.textContent = '图片剪裁功能不可用，将使用原始图片作为头像';
                        photoPreviews.appendChild(hint);
                    });
            }

            function handleCropSuccess(blob) {
                console.log('图片剪裁完成，blob大小:', blob.size);
                circleBlob = blob;

                // 显示圆形头像预览
                const reader = new FileReader();
                reader.onload = function(e) {
                    console.log('圆形头像预览加载完成');
                    circlePreview.src = e.target.result;
                    circlePreview.style.display = 'block';
                    cropButton.style.display = 'inline-block';

                    // 确保预览容器可见
                    photoPreviews.style.display = 'flex';

                    console.log('圆形头像预览已设置');
                };
                reader.readAsDataURL(blob);

                // 将圆形头像数据转换为base64存储
                const reader2 = new FileReader();
                reader2.onload = function(e) {
                    photoCircleData.value = e.target.result;
                    console.log('圆形头像数据已保存到隐藏字段');
                };
                reader2.readAsDataURL(blob);
            }

            function handleCropError(error) {
                if (error !== 'cancelled') {
                    console.error('剪裁失败:', error);

                    // 尝试使用简化版剪裁工具作为备用
                    if (window.simpleCropper && error !== 'simple-cropper-failed') {
                        console.log('尝试使用简化版剪裁工具');
                        window.simpleCropper.cropToCircle(originalFile)
                            .then(handleCropSuccess)
                            .catch(() => {
                                console.error('简化版剪裁工具也失败了');
                                alert('图片剪裁失败，将跳过圆形头像生成');
                                circlePreview.style.display = 'none';
                                cropButton.style.display = 'none';
                            });
                    } else {
                        alert('图片剪裁失败，将跳过圆形头像生成');
                        circlePreview.style.display = 'none';
                        cropButton.style.display = 'none';
                    }
                } else {
                    console.log('用户取消了剪裁');
                    // 用户取消时，隐藏圆形头像预览
                    circlePreview.style.display = 'none';
                    cropButton.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>
