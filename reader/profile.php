<?php
session_start();
require_once '../config/config.php';

// 检查占卜师登录
requireReaderLogin();

$db = Database::getInstance();
$reader = getReaderById($_SESSION['reader_id']);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        // 更新基本信息（不包括擅长方向）
        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'experience_years' => (int)($_POST['experience_years'] ?? 0),
            'description' => trim($_POST['description'] ?? '')
        ];

        // 验证数据
        if (empty($data['full_name'])) {
            $errors[] = '姓名不能为空';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = '请输入有效的邮箱地址';
        }

        if ($data['experience_years'] < 0) {
            $errors[] = '从业年数不能为负数';
        }

        if (empty($errors)) {
            try {
                $db->update('readers', $data, 'id = ?', [$_SESSION['reader_id']]);
                $success = '基本信息更新成功！';
                $reader = getReaderById($_SESSION['reader_id']); // 重新获取数据
            } catch (Exception $e) {
                $errors[] = '更新失败，请稍后重试';
            }
        }
    }
    elseif ($action === 'update_specialties') {
        // 更新擅长的占卜方向
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

        $data = ['specialties' => implode('、', $specialties)];

        // 验证数据
        if (empty($specialties)) {
            $errors[] = '请至少选择一个擅长的占卜方向';
        }

        if (empty($errors)) {
            try {
                $db->update('readers', $data, 'id = ?', [$_SESSION['reader_id']]);
                $success = '擅长方向更新成功！';
                $reader = getReaderById($_SESSION['reader_id']); // 重新获取数据
            } catch (Exception $e) {
                $errors[] = '更新失败，请稍后重试';
            }
        }
    }

    elseif ($action === 'update_photo') {
        // 头像上传功能（从settings.php合并）
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = '请选择要上传的头像';
        } else {
            // 使用优化的图片上传
            $uploadOptions = [
                'max_width' => AVATAR_MAX_WIDTH,
                'max_height' => AVATAR_MAX_HEIGHT,
                'quality' => AVATAR_QUALITY,
                'generate_thumbnails' => true,
                'thumbnail_sizes' => [
                    'small' => [80, 80],
                    'medium' => [150, 150],
                    'large' => [300, 300]
                ],
                'webp_support' => WEBP_ENABLED
            ];

            $uploadResult = uploadOptimizedImage($_FILES['photo'], PHOTO_PATH, $uploadOptions);
            if (!$uploadResult['success']) {
                $errors[] = '头像上传失败：' . $uploadResult['message'];
            } else {
                // 删除旧头像
                if (!empty($reader['photo'])) {
                    $oldPhotoPath = $reader['photo'];
                    if (!str_starts_with($oldPhotoPath, '../')) {
                        $oldPhotoPath = '../' . $oldPhotoPath;
                    }
                    if (file_exists($oldPhotoPath)) {
                        unlink($oldPhotoPath);
                    }

                    // 删除旧头像的缩略图和WebP版本
                    $oldBaseName = pathinfo($reader['photo'], PATHINFO_FILENAME);
                    $oldDir = '../' . dirname($reader['photo']) . '/';
                    foreach (['small', 'medium', 'large'] as $size) {
                        $oldThumb = $oldDir . $oldBaseName . '_' . $size . '.jpg';
                        $oldWebp = $oldDir . $oldBaseName . '_' . $size . '.webp';
                        if (file_exists($oldThumb)) unlink($oldThumb);
                        if (file_exists($oldWebp)) unlink($oldWebp);
                    }
                }

                $newPhotoPath = PHOTO_PATH . $uploadResult['filename'];
                $result = $db->update('readers', ['photo' => $newPhotoPath], 'id = ?', [$_SESSION['reader_id']]);

                if ($result) {
                    $success = '头像更新成功！图片已自动优化以提升加载速度。';
                    $reader = getReaderById($_SESSION['reader_id']);
                } else {
                    $errors[] = '头像更新失败，请重试';
                }
            }
        }
    }

    elseif ($action === 'change_password') {
        // 密码修改功能（从settings.php合并）
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errors[] = '请填写所有密码字段';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = '新密码和确认密码不匹配';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = '新密码长度至少6位';
        } elseif (!password_verify($currentPassword, $reader['password'])) {
            $errors[] = '当前密码不正确';
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $result = $db->update('readers', ['password' => $hashedPassword], 'id = ?', [$_SESSION['reader_id']]);

            if ($result) {
                $success = '密码修改成功！';
            } else {
                $errors[] = '密码修改失败，请重试';
            }
        }
    }

    elseif ($action === 'update_divination_types') {
        // 占卜类型更新功能
        require_once '../includes/DivinationConfig.php';

        $selectedTypes = $_POST['divination_types'] ?? [];
        $primaryType = trim($_POST['primary_identity'] ?? '');

        // 验证选择
        $validation = DivinationConfig::validateDivinationSelection($selectedTypes, $primaryType);

        if (!$validation['valid']) {
            $errors = array_merge($errors, $validation['errors']);
        } else {
            // 确定身份类别
            $identityCategory = null;
            if (!empty($primaryType)) {
                $identityCategory = DivinationConfig::getDivinationCategory($primaryType);
            }

            $updateData = [
                'divination_types' => json_encode($selectedTypes, JSON_UNESCAPED_UNICODE),
                'primary_identity' => $primaryType,
                'identity_category' => $identityCategory
            ];

            try {
                $result = $db->update('readers', $updateData, 'id = ?', [$_SESSION['reader_id']]);
                if ($result) {
                    $success = '占卜类型更新成功！';
                    $reader = getReaderById($_SESSION['reader_id']);
                } else {
                    $errors[] = '占卜类型更新失败，请重试';
                }
            } catch (Exception $e) {
                $errors[] = '占卜类型更新失败：' . $e->getMessage();
            }
        }
    }

    elseif ($action === 'update_contact') {
        // 联系方式更新功能（从settings.php合并）
        $contactData = [
            'contact_info' => trim($_POST['contact_info'] ?? ''),
            'phone' => trim($_POST['phone_contact'] ?? ''),
            'wechat' => trim($_POST['wechat'] ?? ''),
            'qq' => trim($_POST['qq'] ?? ''),
            'xiaohongshu' => trim($_POST['xiaohongshu'] ?? ''),
            'weibo' => trim($_POST['weibo'] ?? ''),
            'email_contact' => trim($_POST['email_contact'] ?? ''),
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
            $errors[] = '请至少填写一种联系方式';
        } else {
            try {
                $result = $db->update('readers', $contactData, 'id = ?', [$_SESSION['reader_id']]);
                if ($result) {
                    $success = '联系方式更新成功';
                    $reader = getReaderById($_SESSION['reader_id']);
                } else {
                    $errors[] = '联系方式更新失败，请重试';
                }
            } catch (Exception $e) {
                $errors[] = '联系方式更新失败，请重试';
            }
        }
    }

    elseif ($action === 'upload_certificates') {
        // 证书上传功能
        if (!isset($_FILES['certificates']) || empty($_FILES['certificates']['name'][0])) {
            $errors[] = '请选择要上传的证书图片';
        } else {
            // 确保证书目录存在
            if (!is_dir('../' . CERTIFICATES_PATH)) {
                mkdir('../' . CERTIFICATES_PATH, 0777, true);
                chmod('../' . CERTIFICATES_PATH, 0777);
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
                $errors[] = '证书总数不能超过' . MAX_CERTIFICATES . '个，当前已有' . count($existingCertificates) . '个';
            } else {
                $hasError = false;

                for ($i = 0; $i < $fileCount; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $extension = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));

                        if (!in_array($extension, ALLOWED_IMAGE_TYPES)) {
                            $errors[] = '只允许上传 ' . implode('、', ALLOWED_IMAGE_TYPES) . ' 格式的图片';
                            $hasError = true;
                            break;
                        }

                        if ($files['size'][$i] > MAX_FILE_SIZE) {
                            $errors[] = '文件大小不能超过 ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
                            $hasError = true;
                            break;
                        }

                        $fileName = time() . '_' . $i . '_' . md5(uniqid()) . '.' . $extension;
                        $targetPath = '../' . CERTIFICATES_PATH . $fileName;

                        if (move_uploaded_file($files['tmp_name'][$i], $targetPath)) {
                            chmod($targetPath, 0644);
                            $uploadedFiles[] = CERTIFICATES_PATH . $fileName;
                        } else {
                            $errors[] = '证书上传失败，请检查目录权限';
                            $hasError = true;
                            break;
                        }
                    }
                }

                if (!$hasError && !empty($uploadedFiles)) {
                    $allCertificates = array_merge($existingCertificates, $uploadedFiles);
                    $certificatesJson = json_encode($allCertificates, JSON_UNESCAPED_UNICODE);

                    $result = $db->update('readers', ['certificates' => $certificatesJson], 'id = ?', [$_SESSION['reader_id']]);
                    if ($result) {
                        $success = '证书上传成功，共上传' . count($uploadedFiles) . '个文件';
                        $reader = getReaderById($_SESSION['reader_id']);
                    } else {
                        $errors[] = '证书保存失败，请重试';
                        // 删除已上传的文件
                        foreach ($uploadedFiles as $file) {
                            if (file_exists('../' . $file)) {
                                unlink('../' . $file);
                            }
                        }
                    }
                }
            }
        }
    }

    elseif ($action === 'delete_certificate') {
        // 删除证书功能
        $certificateIndex = isset($_POST['certificate_index']) ? (int)$_POST['certificate_index'] : -1;
        $existingCertificates = [];

        if (!empty($reader['certificates'])) {
            $existingCertificates = json_decode($reader['certificates'], true) ?: [];
        }

        // 验证索引是否有效
        if ($certificateIndex >= 0 && $certificateIndex < count($existingCertificates) && isset($existingCertificates[$certificateIndex])) {
            $fileToDelete = $existingCertificates[$certificateIndex];

            // 从数组中移除
            array_splice($existingCertificates, $certificateIndex, 1);

            $certificatesJson = json_encode($existingCertificates, JSON_UNESCAPED_UNICODE);
            $result = $db->update('readers', ['certificates' => $certificatesJson], 'id = ?', [$_SESSION['reader_id']]);

            if ($result) {
                // 删除文件
                if (file_exists('../' . $fileToDelete)) {
                    unlink('../' . $fileToDelete);
                }
                $success = '证书删除成功';
                $reader = getReaderById($_SESSION['reader_id']); // 重新获取更新后的数据
            } else {
                $errors[] = '证书删除失败，请重试';
            }
        } else {
            $errors[] = '无效的证书索引，请刷新页面后重试';
        }
    }

    elseif ($action === 'update_divination_types') {
        // 占卜类型管理功能
        require_once '../includes/DivinationConfig.php';

        $selectedTypes = $_POST['divination_types'] ?? [];
        $primaryType = trim($_POST['primary_identity'] ?? '');

        // 验证选择
        $validation = DivinationConfig::validateDivinationSelection($selectedTypes, $primaryType);

        if (!$validation['valid']) {
            $errors = array_merge($errors, $validation['errors']);
        } else {
            // 确定身份类别
            $identityCategory = null;
            if (!empty($primaryType)) {
                $identityCategory = DivinationConfig::getDivinationCategory($primaryType);
            }

            $updateData = [
                'divination_types' => json_encode($selectedTypes, JSON_UNESCAPED_UNICODE),
                'primary_identity' => $primaryType,
                'identity_category' => $identityCategory
            ];

            $result = $db->update('readers', $updateData, 'id = ?', [$_SESSION['reader_id']]);

            if ($result) {
                $success = '占卜类型更新成功';
                $reader = getReaderById($_SESSION['reader_id']);
            } else {
                $errors[] = '占卜类型更新失败，请重试';
            }
        }
    }

    elseif ($action === 'upload_photo') {
        // 上传个人照片
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['photo'];

            // 使用绝对路径确保目录存在
            $absolutePhotoPath = '../' . PHOTO_PATH;
            if (!is_dir($absolutePhotoPath)) {
                mkdir($absolutePhotoPath, 0777, true);
            }
            chmod($absolutePhotoPath, 0777);

            // 验证文件类型
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($extension, $allowedTypes)) {
                $errors[] = '只允许上传 JPG、PNG、GIF 格式的图片';
            } elseif ($file['size'] > MAX_FILE_SIZE) {
                $errors[] = '文件大小不能超过 ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
            } else {
                // 生成新文件名
                $fileName = md5(uniqid() . time()) . '.' . $extension;
                $targetPath = $absolutePhotoPath . $fileName;
                $dbPath = PHOTO_PATH . $fileName; // 数据库中保存的相对路径

                // 直接上传文件
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // 设置文件权限
                    chmod($targetPath, 0644);

                    // 删除旧照片（需要考虑路径格式）
                    if (!empty($reader['photo'])) {
                        $oldPhotoPath = $reader['photo'];
                        // 如果路径不以../开头，添加../（因为我们在reader/子目录中）
                        if (!str_starts_with($oldPhotoPath, '../')) {
                            $oldPhotoPath = '../' . $oldPhotoPath;
                        }
                        if (file_exists($oldPhotoPath)) {
                            unlink($oldPhotoPath);
                        }
                    }

                    // 更新数据库（保存相对路径）
                    $updateResult = $db->update('readers', ['photo' => $dbPath], 'id = ?', [$_SESSION['reader_id']]);

                    if ($updateResult) {
                        $success = '个人照片上传成功！';
                        $reader = getReaderById($_SESSION['reader_id']);
                    } else {
                        $errors[] = '数据库更新失败';
                        // 删除已上传的文件
                        if (file_exists($targetPath)) {
                            unlink($targetPath);
                        }
                    }
                } else {
                    $errors[] = '文件上传失败，请检查目录权限';
                }
            }
        } else {
            if (isset($_FILES['photo'])) {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => '文件大小超过 upload_max_filesize',
                    UPLOAD_ERR_FORM_SIZE => '文件大小超过 MAX_FILE_SIZE',
                    UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
                    UPLOAD_ERR_NO_FILE => '没有文件被上传',
                    UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
                    UPLOAD_ERR_CANT_WRITE => '文件写入失败',
                    UPLOAD_ERR_EXTENSION => 'PHP扩展停止了文件上传'
                ];
                $errorCode = $_FILES['photo']['error'];
                $errors[] = '文件上传错误: ' . ($uploadErrors[$errorCode] ?? "未知错误 ({$errorCode})");
            } else {
                $errors[] = '请选择要上传的照片';
            }
        }
    }
    
    elseif ($action === 'upload_price_list') {
        // 上传价格列表图片
        if (isset($_FILES['price_list']) && $_FILES['price_list']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['price_list'];

            // 使用绝对路径确保目录存在
            $absolutePriceListPath = '../' . PRICE_LIST_PATH;
            if (!is_dir($absolutePriceListPath)) {
                mkdir($absolutePriceListPath, 0777, true);
            }
            chmod($absolutePriceListPath, 0777);

            // 验证文件类型
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($extension, $allowedTypes)) {
                $errors[] = '只允许上传 JPG、PNG、GIF 格式的图片';
            } elseif ($file['size'] > MAX_FILE_SIZE) {
                $errors[] = '文件大小不能超过 ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
            } else {
                // 生成新文件名
                $fileName = md5(uniqid() . time()) . '.' . $extension;
                $targetPath = $absolutePriceListPath . $fileName;
                $dbPricePath = PRICE_LIST_PATH . $fileName; // 数据库中保存的相对路径

                // 直接上传文件
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // 设置文件权限
                    chmod($targetPath, 0644);

                    // 删除旧价格列表
                    if (!empty($reader['price_list_image']) && file_exists($reader['price_list_image'])) {
                        unlink($reader['price_list_image']);
                    }

                    // 更新数据库（保存相对路径）
                    $updateResult = $db->update('readers', ['price_list_image' => $dbPricePath], 'id = ?', [$_SESSION['reader_id']]);

                    if ($updateResult) {
                        $success = '价格列表上传成功！';
                        $reader = getReaderById($_SESSION['reader_id']);
                    } else {
                        $errors[] = '数据库更新失败';
                        // 删除已上传的文件
                        if (file_exists($targetPath)) {
                            unlink($targetPath);
                        }
                    }
                } else {
                    $errors[] = '文件上传失败，请检查目录权限';
                }
            }
        } else {
            if (isset($_FILES['price_list'])) {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => '文件大小超过 upload_max_filesize',
                    UPLOAD_ERR_FORM_SIZE => '文件大小超过 MAX_FILE_SIZE',
                    UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
                    UPLOAD_ERR_NO_FILE => '没有文件被上传',
                    UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
                    UPLOAD_ERR_CANT_WRITE => '文件写入失败',
                    UPLOAD_ERR_EXTENSION => 'PHP扩展停止了文件上传'
                ];
                $errorCode = $_FILES['price_list']['error'];
                $errors[] = '文件上传错误: ' . ($uploadErrors[$errorCode] ?? "未知错误 ({$errorCode})");
            } else {
                $errors[] = '请选择要上传的价格列表图片';
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
    <title>个人资料管理 - 占卜师后台</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/reader-new.css?v=<?php echo time(); ?>">
    <style>
        /* 强制控制图片尺寸 */
        .current-photo img,
        .current-photo .profile-photo,
        img.profile-photo {
            max-width: 250px !important;
            max-height: 300px !important;
            width: auto !important;
            height: auto !important;
            object-fit: cover !important;
            border-radius: 10px !important;
            border: 3px solid #d4af37 !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
            display: block !important;
            margin: 15px auto !important;
        }

        .current-price-list img,
        .current-price-list .price-list-image,
        img.price-list-image {
            max-width: 400px !important;
            max-height: 600px !important;
            width: auto !important;
            height: auto !important;
            object-fit: contain !important;
            border-radius: 10px !important;
            border: 2px solid #d4af37 !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
            display: block !important;
            margin: 15px auto !important;
        }

        .current-photo,
        .current-price-list {
            text-align: center !important;
            padding: 20px !important;
            background: #f8f9fa !important;
            border-radius: 10px !important;
            margin: 15px 0 !important;
        }

        /* 响应式调整 */
        @media (max-width: 768px) {
            .current-photo img,
            .current-photo .profile-photo,
            img.profile-photo {
                max-width: 200px !important;
                max-height: 250px !important;
            }

            .current-price-list img,
            .current-price-list .price-list-image,
            img.price-list-image {
                max-width: 300px !important;
                max-height: 450px !important;
            }
        }

        @media (max-width: 480px) {
            .current-photo img,
            .current-photo .profile-photo,
            img.profile-photo {
                max-width: 150px !important;
                max-height: 200px !important;
            }

            .current-price-list img,
            .current-price-list .price-list-image,
            img.price-list-image {
                max-width: 250px !important;
                max-height: 350px !important;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/reader_header.php'; ?>
    
    <!-- 移动端导航 -->
    <div class="mobile-nav">
        <?php include '../includes/reader_mobile_nav.php'; ?>
    </div>

    <div class="reader-container">
        <div class="reader-sidebar">
            <div class="reader-sidebar-header">
                <h3>占卜师后台</h3>
            </div>
            <div class="reader-sidebar-nav">
                <?php include '../includes/reader_sidebar.php'; ?>
            </div>
        </div>

        <div class="reader-content">
            <div class="reader-content-inner">
            <h1>个人资料与账户设置</h1>

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
                <div class="alert alert-success"><?php echo h($success); ?></div>
            <?php endif; ?>

            <!-- 个人照片管理 -->
            <div class="card">
                <div class="card-header">
                    <h2>📸 个人照片</h2>
                </div>
                <div class="card-body">
                    <div class="photo-management">
                        <div class="current-photo-display">
                            <?php
                            $photoSrc = '';
                            if (!empty($reader['photo'])) {
                                $photoSrc = $reader['photo'];
                                if (!str_starts_with($photoSrc, '../')) {
                                    $photoSrc = '../' . $photoSrc;
                                }
                            } else {
                                // 根据性别使用默认头像
                                $readerId = $reader['id'];
                                if ($reader['gender'] === 'female') {
                                    $avatarNum = (($readerId - 1) % 4) + 1;
                                    $photoSrc = "../img/f{$avatarNum}.jpg";
                                } else {
                                    $avatarNum = (($readerId - 1) % 4) + 1;
                                    $photoSrc = "../img/m{$avatarNum}.jpg";
                                }
                            }
                            ?>
                            <div class="photo-preview-container">
                                <img src="<?php echo h($photoSrc); ?>" alt="个人照片" class="profile-photo-preview">
                                <div class="photo-status">
                                    <?php if (!empty($reader['photo'])): ?>
                                        <span class="status-badge status-uploaded">✅ 已上传</span>
                                    <?php else: ?>
                                        <span class="status-badge status-default">📷 默认头像</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="photo-upload-form">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload_photo">

                                <div class="form-group">
                                    <label for="photo_upload">选择新照片</label>
                                    <input type="file" id="photo_upload" name="photo" accept="image/*" required>
                                    <small>支持格式：JPG、PNG、GIF，最大5MB。图片将自动优化以提升加载速度。</small>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <span class="btn-icon">📤</span>
                                    上传照片
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 基本信息 -->
            <div class="card">
                <div class="card-header">
                    <h2>👤 基本信息</h2>
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
                                <label for="email">邮箱地址</label>
                                <input type="email" id="email" name="email"
                                       placeholder="请输入邮箱地址"
                                       value="<?php echo h($reader['email']); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">手机号码</label>
                                <input type="tel" id="phone" name="phone"
                                       placeholder="请输入手机号码"
                                       value="<?php echo h($reader['phone']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="experience_years">从业年数</label>
                                <input type="number" id="experience_years" name="experience_years" min="0" max="50"
                                       placeholder="请输入从业年数"
                                       value="<?php echo h($reader['experience_years']); ?>">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">保存资料</button>
                    </form>
                </div>
            </div>

            <!-- 占卜类型管理 -->
            <div class="card">
                <div class="card-header">
                    <h2>🔮 占卜类型</h2>
                    <p>最多选择3项，其中1项作为主要身份标签</p>
                </div>
                <div class="card-body">
                    <?php
                    require_once '../includes/DivinationConfig.php';

                    // 获取现有的占卜类型
                    $selectedTypes = [];
                    if (!empty($reader['divination_types'])) {
                        $selectedTypes = json_decode($reader['divination_types'], true) ?: [];
                    }

                    $primaryIdentity = $reader['primary_identity'] ?? '';
                    $allDivinationTypes = DivinationConfig::getAllDivinationTypes();
                    ?>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_divination_types">

                        <div class="divination-types-management">
                            <?php foreach ($allDivinationTypes as $categoryKey => $category): ?>
                                <div class="divination-category">
                                    <div class="category-header">
                                        <h3 class="category-title <?php echo $category['color']; ?>">
                                            <?php echo h($category['name']); ?>
                                        </h3>
                                    </div>

                                    <div class="divination-types-grid">
                                        <?php foreach ($category['types'] as $typeKey => $typeName): ?>
                                            <div class="divination-type-item">
                                                <label class="divination-type-label">
                                                    <input type="checkbox"
                                                           name="divination_types[]"
                                                           value="<?php echo h($typeKey); ?>"
                                                           <?php echo in_array($typeKey, $selectedTypes) ? 'checked' : ''; ?>>
                                                    <span class="type-name"><?php echo h($typeName); ?></span>
                                                    <span class="primary-radio">
                                                        <input type="radio"
                                                               name="primary_identity"
                                                               value="<?php echo h($typeKey); ?>"
                                                               <?php echo $primaryIdentity === $typeKey ? 'checked' : ''; ?>>
                                                        <span class="radio-label">主要</span>
                                                    </span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="selection-summary">
                                <h4>当前选择：</h4>
                                <div class="selected-types-preview">
                                    <?php if (!empty($selectedTypes)): ?>
                                        <?php foreach ($selectedTypes as $type): ?>
                                            <span class="selected-type-tag <?php echo DivinationConfig::getDivinationTagClass($type); ?>">
                                                <?php echo h(DivinationConfig::getDivinationTypeName($type)); ?>
                                                <?php if ($type === $primaryIdentity): ?>
                                                    <span class="primary-badge">主要</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="no-selection">暂未选择占卜类型</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <span class="btn-icon">🔮</span>
                            保存占卜类型
                        </button>
                    </form>
                </div>
            </div>

            <!-- 擅长的占卜方向 -->
            <div class="card">
                <div class="card-header">
                    <h2>⭐ 擅长的占卜方向</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_specialties">

                        <div class="form-group">
                            <label>擅长的占卜方向 *</label>
                            <?php
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
                                      placeholder="请简单介绍您的塔罗经历和服务特色"><?php echo h($reader['description']); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">保存擅长方向</button>
                    </form>
                </div>
            </div>

            <!-- 证书管理 -->
            <div class="card">
                <div class="card-header">
                    <h2>🏆 证书管理</h2>
                    <p>上传您的专业证书，最多<?php echo MAX_CERTIFICATES; ?>个</p>
                </div>
                <div class="card-body">
                    <?php
                    $certificates = [];
                    if (!empty($reader['certificates'])) {
                        $certificates = json_decode($reader['certificates'], true) ?: [];
                    }
                    ?>

                    <div class="certificates-management">
                        <?php if (!empty($certificates)): ?>
                            <div class="current-certificates">
                                <h4>当前证书 (<?php echo count($certificates); ?>/<?php echo MAX_CERTIFICATES; ?>)</h4>
                                <div class="certificates-grid">
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
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (count($certificates) < MAX_CERTIFICATES): ?>
                            <div class="upload-certificates">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_certificates">

                                    <div class="form-group">
                                        <label for="certificates">选择证书图片</label>
                                        <input type="file" id="certificates" name="certificates[]" accept="image/*" multiple>
                                        <small>可同时选择多个文件，支持JPG、PNG、GIF格式，每个文件最大5MB</small>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <span class="btn-icon">📤</span>
                                        上传证书
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="certificates-full">
                                <p>已达到证书上传上限，如需添加新证书请先删除现有证书。</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 密码修改 -->
            <div class="card">
                <div class="card-header">
                    <h2>🔒 密码修改</h2>
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
                                <input type="password" id="new_password" name="new_password" required minlength="6">
                                <small>密码长度至少6位</small>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">确认新密码 *</label>
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">修改密码</button>
                    </form>
                </div>
            </div>

            <!-- 联系方式设置 -->
            <div class="card">
                <div class="card-header">
                    <h2>📞 联系方式设置</h2>
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
                                    <label for="weibo">🌐 微博</label>
                                    <input type="text" id="weibo" name="weibo"
                                           placeholder="请输入微博账号"
                                           value="<?php echo h($reader['weibo'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="email_contact">📧 联系邮箱</label>
                                    <input type="email" id="email_contact" name="email_contact"
                                           placeholder="请输入联系邮箱（可与注册邮箱不同）"
                                           value="<?php echo h($reader['email_contact'] ?? ''); ?>">
                                    <small>如果与注册邮箱不同，可以单独设置用于联系的邮箱</small>
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
                                           placeholder="其他联系方式（如网站等）"
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

            <!-- 价格列表 -->
            <div class="card">
                <div class="card-header">
                    <h2>💰 价格列表</h2>
                </div>
                <div class="card-body">
                    <div class="price-list-management">
                        <?php if (!empty($reader['price_list_image'])): ?>
                            <div class="current-price-list">
                                <h4>当前价格列表</h4>
                                <?php
                                $priceListPath = $reader['price_list_image'];
                                $displayPricePath = $priceListPath;
                                if (!str_starts_with($priceListPath, '../') && !str_starts_with($priceListPath, '/')) {
                                    $displayPricePath = '../' . $priceListPath;
                                }
                                ?>
                                <div class="price-list-preview">
                                    <?php if (file_exists($displayPricePath)): ?>
                                        <img src="<?php echo h($displayPricePath); ?>" alt="价格列表" class="price-list-image">
                                        <div class="price-status">
                                            <span class="status-badge status-uploaded">✅ 已上传</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="price-error">
                                            <span class="status-badge status-error">❌ 文件不存在</span>
                                            <p>请重新上传价格列表</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="no-price-list">
                                <div class="empty-state">
                                    <span class="empty-icon">💰</span>
                                    <p>暂未上传价格列表</p>
                                    <small>请上传您的服务价格列表图片</small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 上传新价格列表 -->
                    <div class="upload-section">
                        <h4>上传价格列表</h4>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_price_list">

                            <div class="form-group">
                                <label for="price_list">选择图片文件</label>
                                <input type="file" id="price_list" name="price_list" accept="image/*" required>
                                <small>支持格式：JPG、PNG、GIF，最大5MB</small>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <span class="btn-icon">📤</span>
                                上传价格列表
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <style>
    /* 页面整体布局优化 */
    .card {
        margin-bottom: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        border: 1px solid #e9ecef;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .card:hover {
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        transform: translateY(-2px);
    }

    .card-header {
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        padding: 20px;
    }

    .card-header h2 {
        margin: 0;
        font-size: 20px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #212529;
    }

    .card-header p {
        margin: 8px 0 0 0;
        color: #6c757d;
        font-size: 14px;
    }

    .card-body {
        padding: 25px;
    }

    /* 个人照片管理样式 */
    .photo-management {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        align-items: start;
    }

    .photo-preview-container {
        text-align: center;
        position: relative;
    }

    .profile-photo-preview {
        width: 200px;
        height: 200px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #d4af37;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
    }

    .profile-photo-preview:hover {
        transform: scale(1.05);
        box-shadow: 0 12px 35px rgba(0,0,0,0.2);
    }

    .photo-status {
        margin-top: 15px;
    }

    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-uploaded {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }

    .status-default {
        background: linear-gradient(135deg, #6c757d, #adb5bd);
        color: white;
    }

    .status-error {
        background: linear-gradient(135deg, #dc3545, #fd7e14);
        color: white;
    }

    .photo-upload-form {
        background: #f8f9fa;
        padding: 25px;
        border-radius: 10px;
        border: 2px dashed #dee2e6;
        transition: all 0.3s ease;
    }

    .photo-upload-form:hover {
        border-color: #d4af37;
        background: #fffbf0;
    }

    /* 按钮样式优化 */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 14px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #d4af37, #f4e4a6);
        color: white;
        box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #b8941f, #d4af37);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
    }

    .btn-icon {
        font-size: 16px;
    }

    /* 占卜方向选择样式 */
    .specialty-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 15px;
        margin: 15px 0;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        cursor: pointer;
        padding: 10px 15px;
        background: white;
        border-radius: 8px;
        border: 2px solid #dee2e6;
        transition: all 0.3s ease;
        font-size: 14px;
        font-weight: 500;
        position: relative;
        overflow: hidden;
    }

    .checkbox-label:hover {
        border-color: #d4af37;
        background: #fffbf0;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
    }

    /* 选中状态的高亮效果 */
    .checkbox-label:has(input[type="checkbox"]:checked),
    .checkbox-label.checked {
        background: linear-gradient(135deg, #d4af37, #f4e4a6);
        border-color: #d4af37;
        color: white;
        font-weight: 600;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
    }

    .checkbox-label:has(input[type="checkbox"]:checked):hover,
    .checkbox-label.checked:hover {
        background: linear-gradient(135deg, #b8941f, #d4af37);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(212, 175, 55, 0.5);
    }

    .checkbox-label input[type="checkbox"] {
        display: none;
    }

    .checkmark {
        width: 18px;
        height: 18px;
        border: 2px solid #dee2e6;
        border-radius: 4px;
        margin-right: 8px;
        position: relative;
        transition: all 0.3s ease;
        flex-shrink: 0;
    }

    .checkbox-label input[type="checkbox"]:checked + .checkmark {
        background: white;
        border-color: white;
        box-shadow: 0 0 0 2px rgba(255,255,255,0.3);
    }

    .checkbox-label input[type="checkbox"]:checked + .checkmark::after {
        content: '✓';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #d4af37;
        font-size: 14px;
        font-weight: bold;
    }

    /* 选中状态下的文字颜色保持白色 */
    .checkbox-label:has(input[type="checkbox"]:checked) .checkmark,
    .checkbox-label.checked .checkmark {
        background: white;
        border-color: white;
    }

    .checkbox-label:has(input[type="checkbox"]:checked) .checkmark::after,
    .checkbox-label.checked .checkmark::after {
        color: #d4af37;
    }

    .custom-specialty {
        margin-top: 20px;
    }

    .custom-specialty label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #495057;
    }

    .custom-specialty input {
        width: 100%;
        padding: 10px 15px;
        border: 2px solid #dee2e6;
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.3s ease;
    }

    .custom-specialty input:focus {
        outline: none;
        border-color: #d4af37;
        box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
    }

    /* 头像预览样式 */
    .photo-preview {
        text-align: center;
        margin: 20px 0;
    }

    .current-avatar {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #d4af37;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    /* 联系方式设置样式 */
    .contact-fields {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        margin: 15px 0;
        border: 1px solid #e9ecef;
    }

    .contact-fields label {
        font-weight: 600;
        color: #333;
        display: flex;
        align-items: center;
        gap: 5px;
        margin-bottom: 8px;
    }

    .contact-fields input {
        border: 2px solid #e9ecef;
        transition: border-color 0.3s ease;
        padding: 10px 15px;
        border-radius: 6px;
        width: 100%;
    }

    .contact-fields input:focus {
        border-color: #d4af37;
        box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        outline: none;
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

    /* 占卜类型管理样式 */
    .divination-types-management {
        background: #f8f9fa;
        padding: 25px;
        border-radius: 12px;
        margin: 15px 0;
    }

    .divination-category {
        margin-bottom: 30px;
    }

    .category-header {
        margin-bottom: 20px;
        text-align: center;
    }

    .category-title {
        margin: 0;
        padding: 12px 24px;
        border-radius: 25px;
        color: white;
        font-size: 18px;
        font-weight: 600;
        display: inline-block;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }

    .category-title.purple {
        background: linear-gradient(135deg, #8b5cf6, #a855f7);
    }

    .category-title.black {
        background: linear-gradient(135deg, #374151, #1f2937);
    }

    .divination-types-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .divination-type-item {
        background: white;
        border-radius: 10px;
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
        overflow: hidden;
    }

    .divination-type-item:hover {
        border-color: #d4af37;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }

    .divination-type-label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px 20px;
        cursor: pointer;
        margin: 0;
    }

    .divination-type-label input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin-right: 12px;
        accent-color: #d4af37;
    }

    .type-name {
        flex: 1;
        font-weight: 500;
        color: #374151;
        font-size: 15px;
    }

    .primary-radio {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-left: 10px;
    }

    .primary-radio input[type="radio"] {
        width: 16px;
        height: 16px;
        accent-color: #d4af37;
    }

    .radio-label {
        font-size: 12px;
        color: #6b7280;
        font-weight: 600;
    }

    .divination-type-item:has(input[type="checkbox"]:checked) {
        border-color: #d4af37;
        background: linear-gradient(135deg, #fffbf0, #fff);
    }

    .divination-type-item:has(input[type="radio"]:checked) {
        background: linear-gradient(135deg, #d4af37, #f4e4a6);
        color: white;
    }

    .divination-type-item:has(input[type="radio"]:checked) .type-name,
    .divination-type-item:has(input[type="radio"]:checked) .radio-label {
        color: white;
    }

    .selection-summary {
        margin-top: 25px;
        padding: 20px;
        background: white;
        border-radius: 10px;
        border: 1px solid #e9ecef;
    }

    .selection-summary h4 {
        margin: 0 0 15px 0;
        color: #374151;
        font-size: 16px;
    }

    .selected-types-preview {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .selected-type-tag {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        color: white;
        position: relative;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }

    .divination-tag-western {
        background: linear-gradient(135deg, #8b5cf6, #a855f7);
    }

    .divination-tag-eastern {
        background: linear-gradient(135deg, #374151, #1f2937);
    }

    .primary-badge {
        background: rgba(255,255,255,0.3);
        padding: 2px 6px;
        border-radius: 8px;
        font-size: 10px;
        margin-left: 6px;
        font-weight: 700;
    }

    .no-selection {
        color: #6b7280;
        font-style: italic;
        padding: 10px 0;
    }

    /* 证书管理样式 */
    .certificates-management {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        margin: 15px 0;
    }

    .current-certificates h4 {
        margin: 0 0 15px 0;
        color: #495057;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .certificates-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .certificate-item {
        position: relative;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .certificate-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .certificate-thumb {
        width: 100%;
        height: 120px;
        object-fit: cover;
        display: block;
    }

    .btn-delete {
        position: absolute;
        top: 5px;
        right: 5px;
        width: 25px;
        height: 25px;
        border-radius: 50%;
        background: rgba(220, 53, 69, 0.9);
        color: white;
        border: none;
        cursor: pointer;
        font-size: 14px;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .btn-delete:hover {
        background: #dc3545;
        transform: scale(1.1);
    }

    .upload-certificates {
        background: white;
        padding: 20px;
        border-radius: 8px;
        border: 2px dashed #dee2e6;
        text-align: center;
        transition: all 0.3s ease;
    }

    .upload-certificates:hover {
        border-color: #d4af37;
        background: #fffbf0;
    }

    .certificates-full {
        background: #fff3cd;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #ffeaa7;
        color: #856404;
        text-align: center;
    }

    /* 价格列表管理样式 */
    .price-list-management {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .upload-section {
        background: white;
        padding: 20px;
        border-radius: 10px;
        border: 1px solid #e9ecef;
    }

    .upload-section h4 {
        margin: 0 0 15px 0;
        color: #212529;
        font-size: 16px;
        font-weight: 600;
    }

    .price-list-preview {
        text-align: center;
        background: white;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }

    .price-list-image {
        max-width: 100%;
        max-height: 400px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        margin-bottom: 15px;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
    }

    .empty-icon {
        font-size: 48px;
        display: block;
        margin-bottom: 15px;
    }

    .empty-state p {
        margin: 0 0 5px 0;
        font-size: 16px;
        font-weight: 500;
    }

    .empty-state small {
        font-size: 14px;
        opacity: 0.8;
    }

    /* 响应式设计 */
    @media (max-width: 768px) {
        .photo-management {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .profile-photo-preview {
            width: 150px;
            height: 150px;
        }

        .specialty-options {
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 10px;
            padding: 15px;
        }

        .checkbox-label {
            padding: 6px 10px;
            font-size: 13px;
        }

        .contact-fields {
            padding: 15px;
        }

        .form-row {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .certificates-grid {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
        }

        .certificate-thumb {
            height: 100px;
        }

        .divination-types-grid {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .divination-type-label {
            padding: 12px 15px;
        }

        .card-header {
            padding: 15px;
        }

        .card-body {
            padding: 20px;
        }

        .btn {
            padding: 10px 20px;
            font-size: 13px;
        }
    }

    @media (max-width: 480px) {
        .profile-photo-preview {
            width: 120px;
            height: 120px;
        }

        .certificates-grid {
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        }

        .card-header h2 {
            font-size: 18px;
        }

        .tags-management .form-row {
            grid-template-columns: 1fr;
        }
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 占卜类型选择交互
        const checkboxes = document.querySelectorAll('input[name="divination_types[]"]');
        const radios = document.querySelectorAll('input[name="primary_identity"]');
        const maxSelections = 3;

        // 复选框变化处理
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const checkedBoxes = document.querySelectorAll('input[name="divination_types[]"]:checked');
                const correspondingRadio = document.querySelector(`input[name="primary_identity"][value="${this.value}"]`);

                if (this.checked) {
                    // 检查是否超过最大选择数量
                    if (checkedBoxes.length > maxSelections) {
                        this.checked = false;
                        alert(`最多只能选择${maxSelections}种占卜类型`);
                        return;
                    }

                    // 启用对应的单选按钮
                    if (correspondingRadio) {
                        correspondingRadio.disabled = false;
                    }
                } else {
                    // 禁用并取消选择对应的单选按钮
                    if (correspondingRadio) {
                        correspondingRadio.disabled = true;
                        correspondingRadio.checked = false;
                    }
                }

                updatePreview();
            });
        });

        // 单选按钮变化处理
        radios.forEach(radio => {
            radio.addEventListener('change', function() {
                updatePreview();
            });
        });

        // 更新预览
        function updatePreview() {
            const checkedBoxes = document.querySelectorAll('input[name="divination_types[]"]:checked');
            const selectedPrimary = document.querySelector('input[name="primary_identity"]:checked');
            const previewContainer = document.querySelector('.selected-types-preview');

            if (!previewContainer) return;

            previewContainer.innerHTML = '';

            if (checkedBoxes.length === 0) {
                previewContainer.innerHTML = '<span class="no-selection">暂未选择占卜类型</span>';
                return;
            }

            checkedBoxes.forEach(checkbox => {
                const typeKey = checkbox.value;
                const typeName = checkbox.closest('.divination-type-label').querySelector('.type-name').textContent;
                const category = checkbox.closest('.divination-category').querySelector('.category-title').classList.contains('purple') ? 'western' : 'eastern';
                const isPrimary = selectedPrimary && selectedPrimary.value === typeKey;

                const tag = document.createElement('span');
                tag.className = `selected-type-tag divination-tag-${category}`;
                tag.innerHTML = typeName + (isPrimary ? '<span class="primary-badge">主要</span>' : '');

                previewContainer.appendChild(tag);
            });
        }

        // 初始化时禁用未选择类型的单选按钮
        radios.forEach(radio => {
            const correspondingCheckbox = document.querySelector(`input[name="divination_types[]"][value="${radio.value}"]`);
            if (correspondingCheckbox && !correspondingCheckbox.checked) {
                radio.disabled = true;
            }
        });

        // 初始化预览
        updatePreview();

        // 处理擅长的占卜方向选择高亮
        const specialtyCheckboxes = document.querySelectorAll('.specialty-options input[type="checkbox"]');

        function updateSpecialtyHighlight() {
            specialtyCheckboxes.forEach(checkbox => {
                const label = checkbox.closest('.checkbox-label');
                if (checkbox.checked) {
                    label.classList.add('checked');
                } else {
                    label.classList.remove('checked');
                }
            });
        }

        // 监听复选框变化
        specialtyCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSpecialtyHighlight);
        });

        // 初始化高亮状态
        updateSpecialtyHighlight();
    });
    </script>

</body>
</html>
