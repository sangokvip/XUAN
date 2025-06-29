<?php
session_start();
require_once '../config/config.php';

// 检查占卜师登录
requireReaderLogin();

$db = Database::getInstance();
$reader = getReaderById($_SESSION['reader_id']);


echo "</div>";

$errors = [];
$success = '';

// 获取当前证书列表
$certificates = [];
if (!empty($reader['certificates'])) {
    $decoded = json_decode($reader['certificates'], true);
    if (is_array($decoded)) {
        $certificates = $decoded;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // 更新基本信息
        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'experience_years' => (int)($_POST['experience_years'] ?? 0),
            'specialties' => trim($_POST['specialties'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'contact_info' => trim($_POST['contact_info'] ?? '')
        ];
        
        // 验证数据
        if (empty($data['full_name'])) {
            $errors[] = '姓名不能为空';
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
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($extension, $allowedTypes)) {
                $errors[] = '只允许上传 JPG、PNG、GIF、WebP 格式的图片';
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

                    // 删除旧照片
                    if (!empty($reader['photo'])) {
                        $oldPhotoPath = '../' . $reader['photo'];
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
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($extension, $allowedTypes)) {
                $errors[] = '只允许上传 JPG、PNG、GIF、WebP 格式的图片';
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
                    if (!empty($reader['price_list_image'])) {
                        $oldPriceListPath = '../' . $reader['price_list_image'];
                        if (file_exists($oldPriceListPath)) {
                            unlink($oldPriceListPath);
                        }
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

    elseif ($action === 'upload_certificate') {
        // 上传证书
        if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['certificate'];
            $certificateName = trim($_POST['certificate_name'] ?? '');

            if (empty($certificateName)) {
                $errors[] = '请输入证书名称';
            } else {
                // 使用绝对路径确保目录存在
                $absoluteCertPath = '../uploads/certificates/';
                if (!is_dir($absoluteCertPath)) {
                    mkdir($absoluteCertPath, 0777, true);
                }
                chmod($absoluteCertPath, 0777);

                // 验证文件类型
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

                if (!in_array($extension, $allowedTypes)) {
                    $errors[] = '只允许上传 JPG、PNG、GIF、WebP、PDF 格式的文件';
                } elseif ($file['size'] > MAX_FILE_SIZE) {
                    $errors[] = '文件大小不能超过 ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
                } else {
                    // 生成新文件名
                    $fileName = md5(uniqid() . time()) . '.' . $extension;
                    $targetPath = $absoluteCertPath . $fileName;
                    $dbPath = 'uploads/certificates/' . $fileName; // 数据库中保存的相对路径

                    // 直接上传文件
                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        // 设置文件权限
                        chmod($targetPath, 0644);

                        // 添加到证书列表
                        $certificates[] = [
                            'name' => $certificateName,
                            'file' => $dbPath,
                            'upload_time' => date('Y-m-d H:i:s')
                        ];

                        // 更新数据库
                        $updateResult = $db->update('readers', ['certificates' => json_encode($certificates)], 'id = ?', [$_SESSION['reader_id']]);

                        if ($updateResult) {
                            $success = '证书上传成功！';
                            $reader = getReaderById($_SESSION['reader_id']);
                            // 重新获取证书列表
                            $decoded = json_decode($reader['certificates'], true);
                            $certificates = is_array($decoded) ? $decoded : [];
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
            }
        } else {
            $errors[] = '请选择要上传的证书文件';
        }
    }

    elseif ($action === 'delete_certificate') {
        // 删除证书
        $index = (int)($_POST['certificate_index'] ?? -1);

        if ($index >= 0 && $index < count($certificates)) {
            $certificate = $certificates[$index];

            // 删除文件
            $filePath = '../' . $certificate['file'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // 从数组中移除
            array_splice($certificates, $index, 1);

            // 更新数据库
            $updateResult = $db->update('readers', ['certificates' => json_encode($certificates)], 'id = ?', [$_SESSION['reader_id']]);

            if ($updateResult) {
                $success = '证书删除成功！';
                $reader = getReaderById($_SESSION['reader_id']);
                // 重新获取证书列表
                $decoded = json_decode($reader['certificates'], true);
                $certificates = is_array($decoded) ? $decoded : [];
            } else {
                $errors[] = '删除失败，请稍后重试';
            }
        } else {
            $errors[] = '无效的证书索引';
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

        /* 图片管理样式 */
        .image-section {
            margin-bottom: 40px;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .image-section:hover {
            border-color: #d4af37;
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.15);
        }

        .image-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.3em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .image-display {
            text-align: center;
            margin-bottom: 20px;
            padding: 20px;
            background: #ffffff;
            border-radius: 12px;
            border: 2px dashed #dee2e6;
        }

        .uploaded-image {
            max-width: 250px;
            max-height: 300px;
            width: auto;
            height: auto;
            object-fit: cover;
            border-radius: 12px;
            border: 3px solid #d4af37;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .uploaded-image:hover {
            transform: scale(1.05);
        }

        .no-image {
            font-size: 4em;
            color: #dee2e6;
            margin-bottom: 10px;
        }

        .image-status {
            margin: 10px 0;
            font-weight: 600;
            font-size: 0.9em;
        }

        .image-status.success {
            color: #28a745;
        }

        .image-status.error {
            color: #dc3545;
        }

        .upload-form {
            display: flex;
            gap: 15px;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }

        .upload-form input[type="file"] {
            padding: 10px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            background: #ffffff;
            font-size: 0.9em;
            flex: 1;
            min-width: 200px;
        }

        .upload-form button {
            background: linear-gradient(135deg, #d4af37 0%, #f1c40f 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .upload-form button:hover {
            background: linear-gradient(135deg, #b8941f 0%, #d4af37 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }

        /* 证书网格 */
        .certificates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .certificate-item {
            background: #ffffff;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .certificate-item:hover {
            border-color: #d4af37;
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.2);
            transform: translateY(-3px);
        }

        .certificate-image {
            max-width: 150px;
            max-height: 180px;
            width: auto;
            height: auto;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #d4af37;
            margin-bottom: 10px;
        }

        .pdf-preview {
            font-size: 3em;
            color: #d4af37;
            margin-bottom: 10px;
        }

        .certificate-info h4 {
            color: #2c3e50;
            margin: 10px 0;
            font-size: 1.1em;
        }

        .certificate-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 15px;
        }

        .btn-view, .btn-delete {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85em;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-view:hover {
            background: #138496;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        .no-certificates {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
        }

        .upload-certificate-form {
            margin-top: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px dashed #dee2e6;
        }

        .form-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .form-row input[type="text"] {
            flex: 1;
            min-width: 150px;
            padding: 10px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 0.9em;
        }

        .form-row input[type="file"] {
            flex: 1;
            min-width: 200px;
            padding: 10px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 0.9em;
        }

        .form-row button {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .form-row button:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }



        @media (max-width: 768px) {
            .image-section {
                padding: 20px;
                margin-bottom: 30px;
            }

            .upload-form {
                flex-direction: column;
                gap: 10px;
            }

            .upload-form input[type="file"] {
                min-width: auto;
                width: 100%;
            }

            .certificates-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .form-row {
                flex-direction: column;
                gap: 10px;
            }

            .form-row input[type="text"],
            .form-row input[type="file"] {
                min-width: auto;
                width: 100%;
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
            <h1>个人资料管理</h1>
            
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
            
            <!-- 基本信息 -->
            <div class="card">
                <div class="card-header">
                    <h2>基本信息</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">姓名 *</label>
                                <input type="text" id="full_name" name="full_name" required 
                                       value="<?php echo h($reader['full_name']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">手机号码</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo h($reader['phone']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="experience_years">从业年数</label>
                            <input type="number" id="experience_years" name="experience_years" min="0" max="50"
                                   value="<?php echo h($reader['experience_years']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="specialties">擅长的占卜方向</label>
                            <input type="text" id="specialties" name="specialties" 
                                   placeholder="例如：爱情塔罗、事业指导、心理咨询"
                                   value="<?php echo h($reader['specialties']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">个人简介</label>
                            <textarea id="description" name="description" rows="4" 
                                      placeholder="请简单介绍您的塔罗经历和服务特色"><?php echo h($reader['description']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_info">联系方式</label>
                            <textarea id="contact_info" name="contact_info" rows="3" 
                                      placeholder="请填写您的联系方式，如微信号、QQ号等"><?php echo h($reader['contact_info']); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">更新基本信息</button>
                    </form>
                </div>
            </div>
            
            <!-- 📸 图片管理 -->
            <div class="card">
                <div class="card-header">
                    <h2>📸 图片管理</h2>
                    <p>管理您的个人照片、价格列表和证书</p>
                </div>
                <div class="card-body">

                    <!-- 个人照片 -->
                    <div class="image-section">
                        <h3>👤 个人照片</h3>
                        <div class="image-display">
                            <?php if (!empty($reader['photo'])): ?>
                                <?php
                                $photoPath = '../uploads/photos/' . basename($reader['photo']);
                                if (file_exists($photoPath)):
                                ?>
                                    <img src="<?php echo h($photoPath); ?>" alt="个人照片" class="uploaded-image">
                                    <p class="image-status success">✅ 已设置</p>
                                <?php else: ?>
                                    <div class="no-image">📷</div>
                                    <p class="image-status error">❌ 文件丢失</p>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="no-image">📷</div>
                                <p class="image-status">未上传</p>
                            <?php endif; ?>
                        </div>
                        <form method="POST" enctype="multipart/form-data" class="upload-form">
                            <input type="hidden" name="action" value="upload_photo">
                            <input type="file" name="photo" accept="image/*" required>
                            <button type="submit">更新照片</button>
                        </form>
                    </div>

                    <!-- 价格列表 -->
                    <div class="image-section">
                        <h3>💰 价格列表</h3>
                        <div class="image-display">
                            <?php if (!empty($reader['price_list_image'])): ?>
                                <?php
                                $priceListPath = '../uploads/price_lists/' . basename($reader['price_list_image']);
                                if (file_exists($priceListPath)):
                                ?>
                                    <img src="<?php echo h($priceListPath); ?>" alt="价格列表" class="uploaded-image">
                                    <p class="image-status success">✅ 已设置</p>
                                <?php else: ?>
                                    <div class="no-image">💰</div>
                                    <p class="image-status error">❌ 文件丢失</p>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="no-image">💰</div>
                                <p class="image-status">未上传</p>
                            <?php endif; ?>
                        </div>
                        <form method="POST" enctype="multipart/form-data" class="upload-form">
                            <input type="hidden" name="action" value="upload_price_list">
                            <input type="file" name="price_list" accept="image/*" required>
                            <button type="submit">更新价格列表</button>
                        </form>
                    </div>

                    <!-- 证书管理 -->
                    <div class="image-section">
                        <h3>🏆 证书管理</h3>

                        <!-- 已上传的证书 -->
                        <?php if (!empty($certificates)): ?>
                            <div class="certificates-grid">
                                <?php foreach ($certificates as $index => $cert): ?>
                                    <?php if (is_array($cert) && isset($cert['name']) && isset($cert['file'])): ?>
                                        <div class="certificate-item">
                                            <div class="certificate-preview">
                                                <?php
                                                $certPath = '../uploads/certificates/' . basename($cert['file']);
                                                $extension = strtolower(pathinfo($cert['file'], PATHINFO_EXTENSION));
                                                ?>

                                                <?php if (file_exists($certPath)): ?>
                                                    <?php if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                                        <img src="<?php echo h($certPath); ?>" alt="<?php echo h($cert['name']); ?>" class="certificate-image">
                                                    <?php else: ?>
                                                        <div class="pdf-preview">📄</div>
                                                    <?php endif; ?>
                                                    <p class="image-status success">✅ 正常</p>
                                                <?php else: ?>
                                                    <div class="pdf-preview">❌</div>
                                                    <p class="image-status error">文件丢失</p>
                                                <?php endif; ?>
                                            </div>

                                            <div class="certificate-info">
                                                <h4><?php echo h($cert['name']); ?></h4>
                                                <div class="certificate-actions">
                                                    <?php if (file_exists($certPath)): ?>
                                                        <a href="<?php echo h($certPath); ?>" target="_blank" class="btn-view">查看</a>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('确定删除？');">
                                                        <input type="hidden" name="action" value="delete_certificate">
                                                        <input type="hidden" name="certificate_index" value="<?php echo $index; ?>">
                                                        <button type="submit" class="btn-delete">删除</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-certificates">暂无证书，请上传您的资质证书</p>
                        <?php endif; ?>

                        <!-- 上传新证书 -->
                        <form method="POST" enctype="multipart/form-data" class="upload-certificate-form">
                            <input type="hidden" name="action" value="upload_certificate">
                            <div class="form-row">
                                <input type="text" name="certificate_name" placeholder="证书名称" required>
                                <input type="file" name="certificate" accept="image/*,.pdf" required>
                                <button type="submit">上传证书</button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
    </div>

    <footer class="reader-footer">
        <div class="container">
            <p>&copy; 2024 占卜师展示平台. 保留所有权利.</p>
        </div>
    </footer>

</body>
</html>
