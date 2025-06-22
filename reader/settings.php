<?php
session_start();
require_once '../config/config.php';

// 检查塔罗师权限
requireReaderLogin();

$db = Database::getInstance();
$readerId = $_SESSION['reader_id'];
$success = '';
$error = '';

// 获取当前塔罗师信息
$reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'experience_years' => (int)($_POST['experience_years'] ?? 0),
            'description' => trim($_POST['description'] ?? '')
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
            $specialties[] = '其他：' . $customSpecialty;
        }
        
        $data['specialties'] = implode('、', $specialties);
        
        // 验证数据
        if (empty($data['full_name']) || empty($data['email']) || empty($specialties)) {
            $error = '请填写所有必填字段';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $error = '请输入有效的邮箱地址';
        } elseif ($data['experience_years'] < 1) {
            $error = '从业年数至少为1年';
        } else {
            // 检查邮箱是否被其他塔罗师使用
            $existingReader = $db->fetchOne("SELECT id FROM readers WHERE email = ? AND id != ?", [$data['email'], $readerId]);
            if ($existingReader) {
                $error = '该邮箱已被其他塔罗师使用';
            } else {
                $result = $db->update('readers', $data, 'id = ?', [$readerId]);
                if ($result) {
                    $success = '个人资料更新成功';
                    $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);
                } else {
                    $error = '更新失败，请重试';
                }
            }
        }
    }
    
    elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = '请填写所有密码字段';
        } elseif (!verifyPassword($currentPassword, $reader['password_hash'])) {
            $error = '当前密码不正确';
        } elseif ($newPassword !== $confirmPassword) {
            $error = '新密码和确认密码不一致';
        } elseif (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            $error = '新密码至少需要' . PASSWORD_MIN_LENGTH . '个字符';
        } else {
            $hashedPassword = hashPassword($newPassword);
            $result = $db->update('readers', ['password_hash' => $hashedPassword], 'id = ?', [$readerId]);
            if ($result) {
                $success = '密码修改成功';
            } else {
                $error = '密码修改失败，请重试';
            }
        }
    }
    
    elseif ($action === 'update_photo') {
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = '请选择要上传的头像';
        } else {
            $uploadResult = uploadFile($_FILES['photo'], PHOTO_PATH);
            if (!$uploadResult['success']) {
                $error = '头像上传失败：' . $uploadResult['message'];
            } else {
                // 删除旧头像
                if (!empty($reader['photo']) && file_exists($reader['photo'])) {
                    unlink($reader['photo']);
                }

                $newPhotoPath = PHOTO_PATH . $uploadResult['filename'];
                $result = $db->update('readers', ['photo' => $newPhotoPath], 'id = ?', [$readerId]);

                if ($result) {
                    $success = '头像更新成功';
                    $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);
                } else {
                    $error = '头像更新失败，请重试';
                    // 删除已上传的文件
                    if (file_exists($newPhotoPath)) {
                        unlink($newPhotoPath);
                    }
                }
            }
        }
    }

    elseif ($action === 'update_price_list') {
        if (!isset($_FILES['price_list']) || $_FILES['price_list']['error'] !== UPLOAD_ERR_OK) {
            $error = '请选择要上传的价格列表';
        } else {
            $uploadResult = uploadFile($_FILES['price_list'], PRICE_LIST_PATH);
            if (!$uploadResult['success']) {
                $error = '价格列表上传失败：' . $uploadResult['message'];
            } else {
                // 删除旧价格列表
                if (!empty($reader['price_list_image']) && file_exists($reader['price_list_image'])) {
                    unlink($reader['price_list_image']);
                }

                $newPriceListPath = PRICE_LIST_PATH . $uploadResult['filename'];
                $result = $db->update('readers', ['price_list_image' => $newPriceListPath], 'id = ?', [$readerId]);

                if ($result) {
                    $success = '价格列表更新成功';
                    $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);
                } else {
                    $error = '价格列表更新失败，请重试';
                    // 删除已上传的文件
                    if (file_exists($newPriceListPath)) {
                        unlink($newPriceListPath);
                    }
                }
            }
        }
    }

    elseif ($action === 'update_contact') {
        $contactData = [
            'contact_info' => trim($_POST['contact_info'] ?? ''),
            'phone' => trim($_POST['phone_contact'] ?? ''),
            'wechat' => trim($_POST['wechat'] ?? ''),
            'qq' => trim($_POST['qq'] ?? ''),
            'xiaohongshu' => trim($_POST['xiaohongshu'] ?? ''),
            'douyin' => trim($_POST['douyin'] ?? ''),
            'other_contact' => trim($_POST['other_contact'] ?? '')
        ];

        // 验证至少填写一种联系方式
        $hasContact = false;
        foreach ($contactData as $field => $value) {
            if (!empty($value)) {
                $hasContact = true;
                break;
            }
        }

        if (!$hasContact) {
            $error = '请至少填写一种联系方式';
        } else {
            $result = $db->update('readers', $contactData, 'id = ?', [$readerId]);
            if ($result) {
                $success = '联系方式更新成功';
                $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);
            } else {
                $error = '联系方式更新失败，请重试';
            }
        }
    }

    elseif ($action === 'upload_certificates') {
        if (!isset($_FILES['certificates']) || empty($_FILES['certificates']['name'][0])) {
            $error = '请选择要上传的证书图片';
        } else {
            // 确保证书目录存在
            if (!is_dir(CERTIFICATES_PATH)) {
                mkdir(CERTIFICATES_PATH, 0777, true);
                chmod(CERTIFICATES_PATH, 0777);
            }

            $uploadedFiles = [];
            $files = $_FILES['certificates'];
            $fileCount = count($files['name']);

            // 获取现有证书
            $existingCertificates = [];
            if (!empty($reader['certificates'])) {
                $existingCertificates = json_decode($reader['certificates'], true) ?: [];
            }

            // 检查总数量限制
            if (count($existingCertificates) + $fileCount > MAX_CERTIFICATES) {
                $error = '证书总数不能超过' . MAX_CERTIFICATES . '个，当前已有' . count($existingCertificates) . '个';
            } else {
                $hasError = false;

                for ($i = 0; $i < $fileCount; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $extension = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));

                        if (!in_array($extension, ALLOWED_IMAGE_TYPES)) {
                            $error = '只允许上传 ' . implode('、', ALLOWED_IMAGE_TYPES) . ' 格式的图片';
                            $hasError = true;
                            break;
                        }

                        if ($files['size'][$i] > MAX_FILE_SIZE) {
                            $error = '文件大小不能超过 ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
                            $hasError = true;
                            break;
                        }

                        $fileName = time() . '_' . $i . '_' . md5(uniqid()) . '.' . $extension;
                        $targetPath = CERTIFICATES_PATH . $fileName;

                        if (move_uploaded_file($files['tmp_name'][$i], $targetPath)) {
                            chmod($targetPath, 0644);
                            $uploadedFiles[] = $targetPath;
                        } else {
                            $error = '证书上传失败，请检查目录权限';
                            $hasError = true;
                            break;
                        }
                    }
                }

                if (!$hasError && !empty($uploadedFiles)) {
                    $allCertificates = array_merge($existingCertificates, $uploadedFiles);
                    $certificatesJson = json_encode($allCertificates, JSON_UNESCAPED_UNICODE);

                    $result = $db->update('readers', ['certificates' => $certificatesJson], 'id = ?', [$readerId]);
                    if ($result) {
                        $success = '证书上传成功，共上传' . count($uploadedFiles) . '个文件';
                        $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);
                    } else {
                        $error = '证书保存失败，请重试';
                        // 删除已上传的文件
                        foreach ($uploadedFiles as $file) {
                            if (file_exists($file)) {
                                unlink($file);
                            }
                        }
                    }
                }
            }
        }
    }

    elseif ($action === 'delete_certificate') {
        $certificateIndex = (int)($_POST['certificate_index'] ?? -1);
        $existingCertificates = [];

        if (!empty($reader['certificates'])) {
            $existingCertificates = json_decode($reader['certificates'], true) ?: [];
        }

        if ($certificateIndex >= 0 && $certificateIndex < count($existingCertificates)) {
            $fileToDelete = $existingCertificates[$certificateIndex];

            // 从数组中移除
            array_splice($existingCertificates, $certificateIndex, 1);

            $certificatesJson = json_encode($existingCertificates, JSON_UNESCAPED_UNICODE);
            $result = $db->update('readers', ['certificates' => $certificatesJson], 'id = ?', [$readerId]);

            if ($result) {
                // 删除文件
                if (file_exists($fileToDelete)) {
                    unlink($fileToDelete);
                }
                $success = '证书删除成功';
                $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);
            } else {
                $error = '证书删除失败，请重试';
            }
        } else {
            $error = '无效的证书索引';
        }
    }
}

// 解析当前的占卜方向
$currentSpecialties = [];
$customSpecialtyValue = '';
if (!empty($reader['specialties'])) {
    $specialtyArray = explode('、', $reader['specialties']);
    foreach ($specialtyArray as $specialty) {
        if (strpos($specialty, '其他：') === 0) {
            $customSpecialtyValue = substr($specialty, 3);
        } else {
            $currentSpecialties[] = $specialty;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>账户设置 - 塔罗师后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/reader.css">
    <style>
        /* 联系方式设置样式 */
        .contact-fields {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 15px 0;
        }

        .contact-fields label {
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .contact-fields input {
            border: 2px solid #e9ecef;
            transition: border-color 0.3s ease;
        }

        .contact-fields input:focus {
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .contact-tips {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #d4af37;
            margin: 20px 0;
        }

        .contact-tips h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }

        .contact-tips ul {
            margin: 0;
            padding-left: 20px;
        }

        .contact-tips li {
            color: #856404;
            margin-bottom: 5px;
        }

        /* 表单行间距优化 */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .contact-fields {
                padding: 15px;
            }
        }

        /* 图片管理区域样式 */
        .media-management {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 2px solid #d4af37;
        }

        .media-management h2 {
            margin: 0 0 20px 0;
            color: #333;
            text-align: center;
        }

        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .media-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .media-card:hover {
            transform: translateY(-2px);
        }

        .certificates-card {
            grid-column: 1 / -1;
        }

        .media-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .media-header h3 {
            margin: 0;
            color: #333;
        }

        .certificate-count {
            background: #d4af37;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .media-content {
            text-align: center;
        }

        .current-media {
            margin-bottom: 15px;
        }

        .media-preview {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 10px;
            border: 3px solid #d4af37;
        }

        .media-placeholder {
            width: 120px;
            height: 120px;
            background: #f8f9fa;
            border: 2px dashed #d4af37;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        .placeholder-icon {
            font-size: 36px;
            margin-bottom: 8px;
        }

        .media-status {
            margin-top: 8px;
            font-size: 12px;
            font-weight: bold;
        }

        .media-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .file-input {
            padding: 8px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 12px;
        }

        .btn-small {
            padding: 8px 16px !important;
            font-size: 12px !important;
        }

        /* 证书管理样式 */
        .certificates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .certificate-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
        }

        .certificate-thumb {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #d4af37;
        }

        .btn-delete {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .add-certificate {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .file-input-hidden {
            display: none;
        }

        .upload-label {
            width: 100%;
            height: 120px;
            border: 2px dashed #d4af37;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .upload-label:hover {
            background: #fff3cd;
            border-color: #b8860b;
        }

        .upload-icon {
            font-size: 36px;
            color: #d4af37;
            margin-bottom: 5px;
        }

        .upload-text {
            font-size: 12px;
            color: #666;
        }

        .upload-btn {
            margin-top: 10px;
        }

        /* 一键选择按钮样式 */
        .specialty-quick-actions {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }

        /* 占卜方向选择美化 */
        .specialty-options {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)) !important;
            gap: 12px !important;
            margin-bottom: 15px !important;
            padding: 20px !important;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef) !important;
            border-radius: 12px !important;
            border: 2px solid #d4af37 !important;
        }

        .checkbox-label {
            display: flex !important;
            align-items: center !important;
            cursor: pointer !important;
            font-size: 14px !important;
            position: relative !important;
            padding: 10px 12px !important;
            margin-bottom: 0 !important;
            background: white !important;
            border-radius: 8px !important;
            transition: all 0.3s ease !important;
            border: 2px solid transparent !important;
            justify-content: space-between !important;
        }

        .checkbox-label:hover {
            background: #fff3cd !important;
            border-color: #d4af37 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.2) !important;
        }

        .checkbox-label input:checked + .checkmark + span {
            color: #d4af37 !important;
            font-weight: bold !important;
        }

        .checkmark {
            position: absolute !important;
            top: 50% !important;
            right: 8px !important;
            transform: translateY(-50%) !important;
            height: 18px !important;
            width: 18px !important;
            background-color: #fff !important;
            border: 2px solid #ddd !important;
            border-radius: 50% !important;
            transition: all 0.3s ease !important;
        }

        .checkbox-label input:checked ~ .checkmark {
            background-color: #d4af37 !important;
            border-color: #d4af37 !important;
        }

        .checkmark:after {
            content: "✓" !important;
            position: absolute !important;
            display: none !important;
            left: 50% !important;
            top: 50% !important;
            transform: translate(-50%, -50%) !important;
            color: white !important;
            font-size: 12px !important;
            font-weight: bold !important;
        }

        .checkbox-label input:checked ~ .checkmark:after {
            display: block !important;
        }

        .custom-specialty {
            margin-top: 15px !important;
            padding-top: 15px !important;
            border-top: 1px solid #eee !important;
        }

        .custom-specialty label {
            font-size: 14px !important;
            color: #666 !important;
            margin-bottom: 5px !important;
            display: block !important;
        }

        .custom-specialty input {
            width: 100% !important;
            padding: 10px 12px !important;
            border: 2px solid #ddd !important;
            border-radius: 8px !important;
            font-size: 14px !important;
            transition: border-color 0.3s ease !important;
        }

        .custom-specialty input:focus {
            border-color: #d4af37 !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1) !important;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .media-grid {
                grid-template-columns: 1fr;
            }

            .certificates-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                gap: 10px;
            }

            .media-management {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/reader_header.php'; ?>
    
    <div class="reader-container">
        <div class="reader-sidebar">
            <?php include '../includes/reader_sidebar.php'; ?>
        </div>
        
        <div class="reader-content">
            <h1>账户设置</h1>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo h($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo h($success); ?></div>
            <?php endif; ?>

            <!-- 图片管理区域 - 置顶显示 -->
            <div class="media-management">
                <h2>📸 图片管理</h2>
                <div class="media-grid">
                    <!-- 个人照片 -->
                    <div class="media-card">
                        <div class="media-header">
                            <h3>👤 个人照片</h3>
                        </div>
                        <div class="media-content">
                            <div class="current-media">
                                <?php if (!empty($reader['photo'])): ?>
                                    <?php
                                    // 确保路径正确：如果路径不以../开头，则添加../
                                    $photoPath = $reader['photo'];
                                    if (!str_starts_with($photoPath, '../')) {
                                        $photoPath = '../' . $photoPath;
                                    }
                                    ?>
                                    <img src="<?php echo h($photoPath); ?>" alt="个人照片" class="media-preview">
                                    <div class="media-status">✅ 已设置</div>
                                <?php else: ?>
                                    <div class="media-placeholder">
                                        <div class="placeholder-icon">📷</div>
                                        <div class="media-status">❌ 未设置</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <form method="POST" enctype="multipart/form-data" class="media-form">
                                <input type="hidden" name="action" value="update_photo">
                                <input type="file" name="photo" accept="image/*" required class="file-input">
                                <button type="submit" class="btn btn-primary btn-small">更新照片</button>
                            </form>
                        </div>
                    </div>

                    <!-- 价格列表 -->
                    <div class="media-card">
                        <div class="media-header">
                            <h3>💰 价格列表</h3>
                        </div>
                        <div class="media-content">
                            <div class="current-media">
                                <?php if (!empty($reader['price_list_image'])): ?>
                                    <img src="../<?php echo h($reader['price_list_image']); ?>" alt="价格列表" class="media-preview">
                                    <div class="media-status">✅ 已设置</div>
                                <?php else: ?>
                                    <div class="media-placeholder">
                                        <div class="placeholder-icon">💰</div>
                                        <div class="media-status">❌ 未设置</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <form method="POST" enctype="multipart/form-data" class="media-form">
                                <input type="hidden" name="action" value="update_price_list">
                                <input type="file" name="price_list" accept="image/*" required class="file-input">
                                <button type="submit" class="btn btn-primary btn-small">更新价格</button>
                            </form>
                        </div>
                    </div>

                    <!-- 证书管理 -->
                    <div class="media-card certificates-card">
                        <div class="media-header">
                            <h3>🏆 证书管理</h3>
                            <span class="certificate-count">
                                <?php
                                $certificates = [];
                                if (!empty($reader['certificates'])) {
                                    $certificates = json_decode($reader['certificates'], true) ?: [];
                                }
                                echo count($certificates) . '/' . MAX_CERTIFICATES;
                                ?>
                            </span>
                        </div>
                        <div class="media-content">
                            <div class="certificates-grid">
                                <?php if (!empty($certificates)): ?>
                                    <?php foreach ($certificates as $index => $certificate): ?>
                                        <div class="certificate-item">
                                            <img src="../<?php echo h($certificate); ?>" alt="证书<?php echo $index + 1; ?>" class="certificate-thumb">
                                            <form method="POST" class="delete-form">
                                                <input type="hidden" name="action" value="delete_certificate">
                                                <input type="hidden" name="certificate_index" value="<?php echo $index; ?>">
                                                <button type="submit" class="btn-delete" onclick="return confirm('确定删除这个证书吗？')">×</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (count($certificates) < MAX_CERTIFICATES): ?>
                                    <div class="add-certificate">
                                        <form method="POST" enctype="multipart/form-data" class="upload-form">
                                            <input type="hidden" name="action" value="upload_certificates">
                                            <input type="file" name="certificates[]" accept="image/*" multiple class="file-input-hidden" id="certificates-input">
                                            <label for="certificates-input" class="upload-label">
                                                <div class="upload-icon">+</div>
                                                <div class="upload-text">添加证书</div>
                                            </label>
                                            <button type="submit" class="btn btn-primary btn-small upload-btn" style="display: none;">上传</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 个人资料设置 -->
            <div class="card">
                <div class="card-header">
                    <h2>个人资料</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">昵称 *</label>
                                <input type="text" id="full_name" name="full_name" required
                                       placeholder="请输入您的塔罗师昵称"
                                       value="<?php echo h($reader['full_name']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">邮箱地址 *</label>
                                <input type="email" id="email" name="email" required 
                                       value="<?php echo h($reader['email']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">手机号码</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo h($reader['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="experience_years">从业年数 *</label>
                                <input type="number" id="experience_years" name="experience_years" required min="1" max="50"
                                       value="<?php echo h($reader['experience_years']); ?>">
                            </div>
                        </div>
                        
                        <!-- 占卜方向选择 -->
                        <div class="form-group">
                            <label>擅长的占卜方向 * (可多选)</label>

                            <!-- 一键选择按钮 -->
                            <div class="specialty-quick-actions">
                                <button type="button" class="btn btn-secondary btn-small" onclick="selectAllSpecialties()">全选</button>
                                <button type="button" class="btn btn-secondary btn-small" onclick="clearAllSpecialties()">清空</button>
                                <button type="button" class="btn btn-secondary btn-small" onclick="selectPopularSpecialties()">选择热门</button>
                            </div>

                            <div class="specialty-options">
                                <?php
                                $predefinedSpecialties = ['感情', '学业', '桃花', '财运', '事业', '运势', '寻物'];
                                foreach ($predefinedSpecialties as $specialty):
                                ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="specialties[]" value="<?php echo h($specialty); ?>"
                                               <?php echo in_array($specialty, $currentSpecialties) ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <?php echo h($specialty); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="custom-specialty">
                                <label for="custom_specialty">其他占卜方向（可选）</label>
                                <input type="text" id="custom_specialty" name="custom_specialty"
                                       placeholder="请填写其他擅长的占卜方向"
                                       value="<?php echo h($customSpecialtyValue); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">个人简介</label>
                            <textarea id="description" name="description" rows="4" 
                                      placeholder="请简单介绍您的塔罗经历和服务特色"><?php echo h($reader['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">保存资料</button>
                    </form>
                </div>
            </div>
            

            <!-- 联系方式设置 -->
            <div class="card">
                <div class="card-header">
                    <h2>联系方式设置</h2>
                    <p>设置您的联系方式，用户查看后可以通过这些方式联系您</p>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_contact">

                        <div class="form-group">
                            <label for="contact_info">联系信息描述</label>
                            <textarea id="contact_info" name="contact_info" rows="3"
                                      placeholder="请简单介绍您的服务时间、预约方式等信息"><?php echo h($reader['contact_info'] ?? ''); ?></textarea>
                            <small>例如：工作时间9:00-21:00，请提前预约</small>
                        </div>

                        <div class="contact-fields">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="phone_contact">📞 电话号码</label>
                                    <input type="tel" id="phone_contact" name="phone_contact"
                                           placeholder="请输入手机号码"
                                           value="<?php echo h($reader['phone'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="wechat">💬 微信号</label>
                                    <input type="text" id="wechat" name="wechat"
                                           placeholder="请输入微信号"
                                           value="<?php echo h($reader['wechat'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="qq">🐧 QQ号</label>
                                    <input type="text" id="qq" name="qq"
                                           placeholder="请输入QQ号"
                                           value="<?php echo h($reader['qq'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="xiaohongshu">📖 小红书</label>
                                    <input type="text" id="xiaohongshu" name="xiaohongshu"
                                           placeholder="请输入小红书账号"
                                           value="<?php echo h($reader['xiaohongshu'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="douyin">🎵 抖音号</label>
                                    <input type="text" id="douyin" name="douyin"
                                           placeholder="请输入抖音号"
                                           value="<?php echo h($reader['douyin'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="other_contact">🔗 其他联系方式</label>
                                    <input type="text" id="other_contact" name="other_contact"
                                           placeholder="其他联系方式（如邮箱、网站等）"
                                           value="<?php echo h($reader['other_contact'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="contact-tips">
                            <h4>💡 温馨提示：</h4>
                            <ul>
                                <li>请至少填写一种联系方式</li>
                                <li>建议提供多种联系方式，方便用户选择</li>
                                <li>请确保联系方式准确有效</li>
                                <li>用户需要登录后才能查看您的联系方式</li>
                            </ul>
                        </div>

                        <button type="submit" class="btn btn-primary">保存联系方式</button>
                    </form>
                </div>
            </div>

            <!-- 密码设置 -->
            <div class="card">
                <div class="card-header">
                    <h2>修改密码</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label for="current_password">当前密码 *</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">新密码 *</label>
                                <input type="password" id="new_password" name="new_password" required>
                                <small>至少<?php echo PASSWORD_MIN_LENGTH; ?>个字符</small>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">确认新密码 *</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">修改密码</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 一键选择功能
        function selectAllSpecialties() {
            const checkboxes = document.querySelectorAll('input[name="specialties[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
        }

        function clearAllSpecialties() {
            const checkboxes = document.querySelectorAll('input[name="specialties[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
        }

        function selectPopularSpecialties() {
            clearAllSpecialties();
            const popularSpecialties = ['感情', '事业', '财运'];
            const checkboxes = document.querySelectorAll('input[name="specialties[]"]');

            checkboxes.forEach(checkbox => {
                if (popularSpecialties.includes(checkbox.value)) {
                    checkbox.checked = true;
                }
            });
        }

        // 证书上传功能
        document.addEventListener('DOMContentLoaded', function() {
            const certificatesInput = document.getElementById('certificates-input');
            const uploadBtn = document.querySelector('.upload-btn');

            if (certificatesInput && uploadBtn) {
                certificatesInput.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        uploadBtn.style.display = 'block';
                        uploadBtn.textContent = `上传 ${this.files.length} 个文件`;
                    } else {
                        uploadBtn.style.display = 'none';
                    }
                });
            }

            // 删除确认
            const deleteForms = document.querySelectorAll('.delete-form');
            deleteForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!confirm('确定删除这个证书吗？')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>

    <footer class="reader-footer">
        <div class="container">
            <p>&copy; 2024 塔罗师展示平台. 保留所有权利.</p>
        </div>
    </footer>
</body>
</html>
