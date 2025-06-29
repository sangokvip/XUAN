<?php
session_start();
require_once '../config/config.php';
require_once '../includes/DivinationConfig.php';

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
            'description' => trim($_POST['description'] ?? ''),
            // specialties 将在下面单独处理
            // 联系方式字段
            'contact_info' => trim($_POST['contact_info'] ?? ''),
            'wechat' => trim($_POST['wechat'] ?? ''),
            'qq' => trim($_POST['qq'] ?? ''),
            'weibo' => trim($_POST['weibo'] ?? ''),
            'other_contact' => trim($_POST['other_contact'] ?? ''),
            // 身份标签字段
            'divination_types' => json_encode($_POST['divination_types'] ?? []),
            'primary_identity' => trim($_POST['primary_identity'] ?? ''),
            'identity_category' => trim($_POST['identity_category'] ?? '')
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
        
        // 验证身份标签
        $divinationTypes = $_POST['divination_types'] ?? [];
        $primaryIdentity = trim($_POST['primary_identity'] ?? '');
        $identityCategory = trim($_POST['identity_category'] ?? '');

        if (!empty($divinationTypes) && count($divinationTypes) > 3) {
            $error = '最多只能选择3个身份标签';
        } elseif (!empty($primaryIdentity) && !in_array($primaryIdentity, $divinationTypes)) {
            $error = '主要标签必须在选择的标签中';
        } elseif (!empty($divinationTypes) && empty($primaryIdentity)) {
            $error = '选择了身份标签后必须指定一个主要标签';
        }

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
                // 处理密码修改（如果提供了密码字段）
                $passwordChanged = false;
                $currentPassword = trim($_POST['current_password'] ?? '');
                $newPassword = trim($_POST['new_password'] ?? '');
                $confirmPassword = trim($_POST['confirm_password'] ?? '');

                if (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword)) {
                    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                        $error = '要修改密码，请填写所有密码字段';
                    } elseif (!verifyPassword($currentPassword, $reader['password_hash'])) {
                        $error = '当前密码不正确';
                    } elseif ($newPassword !== $confirmPassword) {
                        $error = '新密码和确认密码不一致';
                    } elseif (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                        $error = '新密码至少需要' . PASSWORD_MIN_LENGTH . '个字符';
                    } else {
                        $data['password_hash'] = hashPassword($newPassword);
                        $passwordChanged = true;
                    }
                }

                if (empty($error)) {
                    $result = $db->update('readers', $data, 'id = ?', [$readerId]);
                    if ($result) {
                        $successMsg = '个人资料更新成功';
                        if ($passwordChanged) {
                            $successMsg .= '，密码已修改';
                        }
                        $success = $successMsg;
                        $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);
                    } else {
                        $error = '更新失败，请重试';
                    }
                }
            }
        }
    }
    

    
    elseif ($action === 'update_photo') {
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = '请选择要上传的头像';
        } else {
            // 使用绝对路径进行上传
            $absolutePhotoPath = '../' . PHOTO_PATH;
            $uploadResult = uploadOptimizedImage($_FILES['photo'], $absolutePhotoPath);
            if (!$uploadResult['success']) {
                $error = '头像上传失败：' . $uploadResult['message'];
            } else {
                // 删除旧头像及其优化版本
                if (!empty($reader['photo'])) {
                    $oldFilename = basename($reader['photo']);

                    // 删除原图 - 处理各种可能的路径格式
                    $oldPaths = [
                        $reader['photo'],
                        '../' . $reader['photo'],
                        '../' . str_replace('../', '', $reader['photo'])
                    ];

                    foreach ($oldPaths as $oldPath) {
                        if (file_exists($oldPath)) {
                            unlink($oldPath);
                            break;
                        }
                    }

                    // 删除优化版本
                    require_once '../includes/ImageOptimizer.php';
                    $optimizer = new ImageOptimizer($absolutePhotoPath);
                    $optimizer->cleanupOptimizedImages($oldFilename);
                }

                // 保存相对路径到数据库（相对于网站根目录）
                $newPhotoPath = PHOTO_PATH . $uploadResult['filename'];
                $result = $db->update('readers', ['photo' => $newPhotoPath], 'id = ?', [$readerId]);

                if ($result) {
                    $compressionInfo = '';
                    if ($uploadResult['original_size'] > 0 && $uploadResult['optimized_size'] > 0) {
                        $compressionRatio = round((1 - $uploadResult['optimized_size'] / $uploadResult['original_size']) * 100, 1);
                        $compressionInfo = "（已优化，节省 {$compressionRatio}% 空间）";
                    }
                    $success = '头像更新成功' . $compressionInfo;
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
            // 使用绝对路径进行上传，并启用图片优化
            $absolutePriceListPath = '../' . PRICE_LIST_PATH;
            $uploadResult = uploadOptimizedImage($_FILES['price_list'], $absolutePriceListPath);
            if (!$uploadResult['success']) {
                $error = '价格列表上传失败：' . $uploadResult['message'];
            } else {
                // 删除旧价格列表 - 处理各种可能的路径格式
                if (!empty($reader['price_list_image'])) {
                    $oldPaths = [
                        $reader['price_list_image'],
                        '../' . $reader['price_list_image'],
                        '../' . str_replace('../', '', $reader['price_list_image'])
                    ];

                    foreach ($oldPaths as $oldPath) {
                        if (file_exists($oldPath)) {
                            unlink($oldPath);
                            break;
                        }
                    }
                }

                // 保存相对路径到数据库（相对于网站根目录）
                $newPriceListPath = PRICE_LIST_PATH . $uploadResult['filename'];
                $result = $db->update('readers', ['price_list_image' => $newPriceListPath], 'id = ?', [$readerId]);

                if ($result) {
                    $compressionInfo = '';
                    if ($uploadResult['original_size'] > 0 && $uploadResult['optimized_size'] > 0) {
                        $compressionRatio = round((1 - $uploadResult['optimized_size'] / $uploadResult['original_size']) * 100, 1);
                        $compressionInfo = "（已优化，节省 {$compressionRatio}% 空间）";
                    }
                    $success = '价格列表更新成功' . $compressionInfo;
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



    elseif ($action === 'upload_certificates') {
        if (!isset($_FILES['certificates']) || empty($_FILES['certificates']['name'][0])) {
            $error = '请选择要上传的证书图片';
        } else {
            // 确保证书目录存在 - 使用绝对路径
            $absoluteCertificatesPath = '../' . CERTIFICATES_PATH;
            if (!is_dir($absoluteCertificatesPath)) {
                mkdir($absoluteCertificatesPath, 0755, true);
                chmod($absoluteCertificatesPath, 0755);
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

                        // 创建临时文件数组用于图片优化
                        $tempFile = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                            'size' => $files['size'][$i]
                        ];

                        // 使用图片优化上传
                        $uploadResult = uploadOptimizedImage($tempFile, $absoluteCertificatesPath);
                        if ($uploadResult['success']) {
                            // 保存相对路径到数组中
                            $uploadedFiles[] = CERTIFICATES_PATH . $uploadResult['filename'];
                        } else {
                            $error = '证书上传失败：' . $uploadResult['message'];
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
                        $success = '证书上传成功，共上传' . count($uploadedFiles) . '个文件（已自动优化压缩）';
                        $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);
                    } else {
                        $error = '证书保存失败，请重试';
                        // 删除已上传的文件
                        foreach ($uploadedFiles as $file) {
                            // 转换为绝对路径进行删除
                            $absoluteFile = '../' . str_replace('../', '', $file);
                            if (file_exists($absoluteFile)) {
                                unlink($absoluteFile);
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
            $certificateToDelete = $existingCertificates[$certificateIndex];

            // 提取文件路径
            $fileToDelete = '';
            if (is_string($certificateToDelete)) {
                $fileToDelete = $certificateToDelete;
            } elseif (is_array($certificateToDelete) && isset($certificateToDelete['file'])) {
                $fileToDelete = $certificateToDelete['file'];
            }

            // 从数组中移除
            array_splice($existingCertificates, $certificateIndex, 1);

            $certificatesJson = json_encode($existingCertificates, JSON_UNESCAPED_UNICODE);
            $result = $db->update('readers', ['certificates' => $certificatesJson], 'id = ?', [$readerId]);

            if ($result) {
                // 删除文件 - 只尝试直接路径（避免 open_basedir 限制）
                if (!empty($fileToDelete) && file_exists($fileToDelete)) {
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

    elseif ($action === 'delete_multiple_certificates') {
        $certificateIndexes = $_POST['certificate_indexes'] ?? [];

        if (empty($certificateIndexes)) {
            $error = '请选择要删除的证书';
        } else {
            $existingCertificates = [];
            if (!empty($reader['certificates'])) {
                $existingCertificates = json_decode($reader['certificates'], true) ?: [];
            }

            // 验证索引有效性并收集要删除的文件
            $filesToDelete = [];
            $validIndexes = [];

            foreach ($certificateIndexes as $index) {
                $index = (int)$index;
                if ($index >= 0 && $index < count($existingCertificates)) {
                    $validIndexes[] = $index;

                    // 提取文件路径
                    $certificateToDelete = $existingCertificates[$index];
                    if (is_string($certificateToDelete)) {
                        $filesToDelete[] = $certificateToDelete;
                    } elseif (is_array($certificateToDelete) && isset($certificateToDelete['file'])) {
                        $filesToDelete[] = $certificateToDelete['file'];
                    }
                }
            }

            if (empty($validIndexes)) {
                $error = '选择的证书索引无效';
            } else {
                // 按索引倒序排列，避免删除时索引变化
                rsort($validIndexes);

                // 从数组中移除选中的证书
                foreach ($validIndexes as $index) {
                    array_splice($existingCertificates, $index, 1);
                }

                $certificatesJson = json_encode($existingCertificates, JSON_UNESCAPED_UNICODE);
                $result = $db->update('readers', ['certificates' => $certificatesJson], 'id = ?', [$readerId]);

                if ($result) {
                    // 删除文件 - 只尝试直接路径（避免 open_basedir 限制）
                    $deletedCount = 0;
                    foreach ($filesToDelete as $fileToDelete) {
                        if (!empty($fileToDelete) && file_exists($fileToDelete)) {
                            unlink($fileToDelete);
                            $deletedCount++;
                        }
                    }

                    $success = "成功删除 {$deletedCount} 个证书";
                    $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);
                } else {
                    $error = '证书删除失败，请重试';
                }
            }
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
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/reader-new.css?v=<?php echo time(); ?>">
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

        /* 占卜方向选择美化 - 与身份标签样式一致 */
        .specialty-options {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 10px !important;
            margin-bottom: 15px !important;
            padding: 20px !important;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef) !important;
            border-radius: 12px !important;
            border: 2px solid #d4af37 !important;
        }

        .checkbox-label {
            position: relative !important;
            cursor: pointer !important;
            margin: 5px !important;
        }

        .checkbox-label input[type="checkbox"] {
            position: absolute !important;
            opacity: 0 !important;
            width: 0 !important;
            height: 0 !important;
        }

        .specialty-tag {
            display: inline-block !important;
            padding: 10px 20px !important;
            padding-left: 35px !important;
            border-radius: 25px !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
            border: 2px solid transparent !important;
            user-select: none !important;
            position: relative !important;
            min-width: 80px !important;
            text-align: center !important;
            background: #fff !important;
            color: #d4af37 !important;
            border-color: #f0c674 !important;
        }

        /* 自定义复选框样式 */
        .specialty-tag::before {
            content: '' !important;
            position: absolute !important;
            left: 12px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            width: 16px !important;
            height: 16px !important;
            border: 2px solid #d4af37 !important;
            border-radius: 50% !important;
            background: white !important;
            transition: all 0.3s ease !important;
        }

        /* 选中状态的圆点 */
        .checkbox-label input[type="checkbox"]:checked + .specialty-tag::before {
            background: #d4af37 !important;
            border-color: #d4af37 !important;
            box-shadow: inset 0 0 0 3px white !important;
        }

        .checkbox-label:hover .specialty-tag {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3) !important;
        }

        .checkbox-label input[type="checkbox"]:checked + .specialty-tag {
            background: #d4af37 !important;
            color: white !important;
            border-color: #b8941f !important;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3) !important;
        }

        /* 选中状态下的圆点样式 */
        .checkbox-label input[type="checkbox"]:checked + .specialty-tag::before {
            background: #d4af37 !important;
            border-color: white !important;
            box-shadow: inset 0 0 0 3px white !important;
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

        /* 表单分节样式 */
        .form-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #f0f0f0;
        }

        .form-section h3 {
            color: #000;
            margin-bottom: 10px;
            font-size: 1.2rem;
            font-weight: 600;
        }

        /* 卡片标题颜色修改为黑色 */
        .card-header h2 {
            color: #000 !important;
        }

        .media-header h3 {
            color: #000 !important;
        }

        .form-section-desc {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        /* 快速操作按钮 */
        .quick-actions {
            margin-top: 15px;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #d4af37;
            color: #d4af37;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline:hover {
            background: #d4af37;
            color: white;
        }

        /* 身份标签样式 */
        .identity-category {
            margin-bottom: 25px;
        }

        .identity-category h4 {
            color: #000;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }

        .identity-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .identity-tag-label {
            position: relative;
            cursor: pointer;
            margin: 5px;
        }

        .identity-tag-label input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .identity-tag {
            display: inline-block;
            padding: 10px 20px;
            padding-left: 35px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            user-select: none;
            position: relative;
            min-width: 80px;
            text-align: center;
        }

        /* 自定义复选框样式 */
        .identity-tag::before {
            content: '';
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            border: 2px solid #ccc;
            border-radius: 50%;
            background: white;
            transition: all 0.3s ease;
        }

        /* 选中状态的圆点 */
        .identity-tag-label input[type="checkbox"]:checked + .identity-tag::before {
            background: #fff;
            border-color: currentColor;
            box-shadow: inset 0 0 0 3px currentColor;
        }

        .identity-tag.western {
            background: #f3f0ff;
            color: #8b5cf6;
            border-color: #e0d9ff;
        }

        .identity-tag.eastern {
            background: #f9fafb;
            color: #374151;
            border-color: #e5e7eb;
        }

        .identity-tag-label:hover .identity-tag {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .identity-tag-label.selected .identity-tag.western {
            background: #8b5cf6;
            color: white;
            border-color: #7c3aed;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        .identity-tag-label.selected .identity-tag.eastern {
            background: #374151;
            color: white;
            border-color: #1f2937;
            box-shadow: 0 4px 12px rgba(55, 65, 81, 0.3);
        }

        /* 选中状态下的圆点样式 */
        .identity-tag-label.selected .identity-tag.western::before {
            background: #8b5cf6;
            border-color: white;
            box-shadow: inset 0 0 0 3px white;
        }

        .identity-tag-label.selected .identity-tag.eastern::before {
            background: #374151;
            border-color: white;
            box-shadow: inset 0 0 0 3px white;
        }

        .primary-identity-selection {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #d4af37;
        }

        .primary-identity-selection label {
            color: #000;
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
        }

        .primary-identity-selection select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }

        .primary-identity-selection select:focus {
            border-color: #d4af37;
            outline: none;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .primary-identity-selection small {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }

        /* 侧边栏查看页面链接样式 */
        .view-page-link {
            background: linear-gradient(135deg, #d4af37, #f4d03f) !important;
            color: white !important;
            border-left-color: #d4af37 !important;
        }

        .view-page-link:hover {
            background: linear-gradient(135deg, #b8941f, #d4af37) !important;
            transform: translateX(5px);
        }

        /* 证书多选样式 */
        .certificate-item {
            position: relative;
        }

        .certificate-checkbox {
            position: absolute !important;
            top: 5px !important;
            left: 5px !important;
            z-index: 10 !important;
            width: 18px !important;
            height: 18px !important;
            cursor: pointer !important;
            opacity: 0.8 !important;
            background: white !important;
            border: 2px solid #d4af37 !important;
            border-radius: 3px !important;
        }

        .certificate-checkbox:checked {
            background: #d4af37 !important;
            opacity: 1 !important;
        }

        .certificate-item:hover .certificate-checkbox {
            opacity: 1 !important;
        }

        .btn-danger {
            background: #dc3545 !important;
            border-color: #dc3545 !important;
            color: white !important;
        }

        .btn-danger:hover {
            background: #c82333 !important;
            border-color: #bd2130 !important;
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

            .form-section {
                margin-top: 20px;
                padding-top: 20px;
            }

            .media-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .media-header > div {
                flex-wrap: wrap;
            }
        }
    </style>

    <!-- 图片优化CSS -->
    <link rel="stylesheet" href="../assets/css/image-optimization.css">
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
                <div class="page-title">
                    <h1>个人设置</h1>
                    <p>管理您的个人信息、身份标签、联系方式和账户设置</p>
                </div>

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
                                    // 安全的图片路径处理
                                    $photoPath = trim($reader['photo']);

                                    // 如果路径不以uploads/开头，构建标准路径
                                    if (!str_starts_with($photoPath, 'uploads/')) {
                                        $photoPath = 'uploads/photos/' . basename($photoPath);
                                    }

                                    // 尝试使用优化后的缩略图
                                    require_once '../includes/ImageOptimizer.php';
                                    $optimizer = new ImageOptimizer('uploads/photos/');
                                    $optimizedUrl = $optimizer->getOptimizedImageUrl(basename($photoPath), 'thumb', true);
                                    $displayPath = '/' . $optimizedUrl;

                                    // 如果优化版本不存在，回退到原图
                                    if (!file_exists($optimizedUrl)) {
                                        $displayPath = '/' . $photoPath;
                                    }
                                    ?>
                                    <img src="<?php echo h($displayPath); ?>" alt="个人照片"
                                         style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #d4af37; border-radius: 10px;"
                                         onerror="this.src='/img/default-avatar.jpg'; this.onerror=null;">
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
                                    <?php
                                    // 安全的价格列表图片路径处理
                                    $priceListPath = trim($reader['price_list_image']);

                                    // 如果路径不以uploads/开头，构建标准路径
                                    if (!str_starts_with($priceListPath, 'uploads/')) {
                                        $priceListPath = 'uploads/price_lists/' . basename($priceListPath);
                                    }

                                    // 尝试使用优化后的缩略图
                                    require_once '../includes/ImageOptimizer.php';
                                    $optimizer = new ImageOptimizer('uploads/price_lists/');
                                    $optimizedUrl = $optimizer->getOptimizedImageUrl(basename($priceListPath), 'thumb', true);
                                    $displayPath = '/' . $optimizedUrl;

                                    // 如果优化版本不存在，回退到原图
                                    if (!file_exists($optimizedUrl)) {
                                        $displayPath = '/' . $priceListPath;
                                    }
                                    ?>
                                    <img src="<?php echo h($displayPath); ?>" alt="价格列表"
                                         style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #d4af37; border-radius: 10px;"
                                         onerror="this.src='/img/placeholder-price.jpg'; this.onerror=null;">
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
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span class="certificate-count">
                                    <?php
                                    $certificates = [];
                                    if (!empty($reader['certificates'])) {
                                        $certificates = json_decode($reader['certificates'], true) ?: [];
                                    }
                                    echo count($certificates) . '/' . MAX_CERTIFICATES;
                                    ?>
                                </span>
                                <?php if (!empty($certificates)): ?>
                                    <button type="button" class="btn btn-secondary btn-small" onclick="toggleSelectAll()">全选/取消</button>
                                    <button type="button" class="btn btn-danger btn-small" onclick="deleteSelected()" id="delete-selected-btn" style="display: none;">删除选中</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="media-content">
                            <div class="certificates-grid">
                                <?php if (!empty($certificates)): ?>
                                    <?php foreach ($certificates as $index => $certificate): ?>
                                        <div class="certificate-item">
                                            <?php
                                            // 处理证书数据 - 确保我们得到字符串路径
                                            $certificatePath = '';

                                            if (is_string($certificate)) {
                                                // 如果是字符串，直接使用
                                                $certificatePath = $certificate;
                                            } elseif (is_array($certificate)) {
                                                // 如果是数组，尝试获取路径字段（按优先级）
                                                if (isset($certificate['file'])) {
                                                    // 新格式：包含 file 字段
                                                    $certificatePath = $certificate['file'];
                                                } elseif (isset($certificate['path'])) {
                                                    $certificatePath = $certificate['path'];
                                                } elseif (isset($certificate['url'])) {
                                                    $certificatePath = $certificate['url'];
                                                } elseif (isset($certificate[0])) {
                                                    $certificatePath = $certificate[0];
                                                } else {
                                                    // 如果都没有，跳过这个证书
                                                    continue;
                                                }
                                            } else {
                                                // 其他类型，跳过
                                                continue;
                                            }

                                            // 确保路径是字符串且不为空
                                            if (empty($certificatePath) || !is_string($certificatePath)) {
                                                continue;
                                            }

                                            // 确保路径以uploads/开头
                                            if (!str_starts_with($certificatePath, 'uploads/')) {
                                                $certificatePath = 'uploads/certificates/' . basename($certificatePath);
                                            }

                                            // 尝试使用优化后的缩略图
                                            require_once '../includes/ImageOptimizer.php';
                                            $optimizer = new ImageOptimizer('uploads/certificates/');
                                            $optimizedUrl = $optimizer->getOptimizedImageUrl(basename($certificatePath), 'thumb', true);
                                            $displayPath = '/' . $optimizedUrl;

                                            // 如果优化版本不存在，回退到原图
                                            if (!file_exists($optimizedUrl)) {
                                                $displayPath = '/' . $certificatePath;
                                            }
                                            ?>
                                            <img src="<?php echo h($displayPath); ?>" alt="证书<?php echo $index + 1; ?>"
                                                 style="width: 80px; height: 80px; object-fit: cover; border: 2px solid #d4af37; border-radius: 5px;"
                                                 onerror="this.src='/img/placeholder-cert.jpg'; this.onerror=null;">

                                            <!-- 多选复选框 -->
                                            <input type="checkbox" class="certificate-checkbox" value="<?php echo $index; ?>"
                                                   style="position: absolute; top: 5px; left: 5px; z-index: 10;">

                                            <!-- 单个删除按钮 -->
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
                    <h2>📝 基本信息</h2>
                    <p>请完善您的基本信息，这些信息将显示在您的个人页面上</p>
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
                                        <span class="specialty-tag"><?php echo h($specialty); ?></span>
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

                        <!-- 身份标签设置 -->
                        <div class="form-section">
                            <h3>🏷️ 身份标签设置</h3>
                            <p class="form-section-desc">选择您的身份标签（最多3个），并指定一个主要标签</p>

                            <?php
                            $currentDivinationTypes = [];
                            if (!empty($reader['divination_types'])) {
                                $currentDivinationTypes = json_decode($reader['divination_types'], true) ?: [];
                            }
                            $currentPrimaryIdentity = $reader['primary_identity'] ?? '';
                            $currentIdentityCategory = $reader['identity_category'] ?? '';
                            ?>

                            <!-- 西玄标签 -->
                            <div class="identity-category">
                                <h4>🔮 西玄标签</h4>
                                <div class="identity-tags">
                                    <?php foreach (DivinationConfig::getWesternDivinationTypes() as $key => $name): ?>
                                        <label class="identity-tag-label <?php echo in_array($key, $currentDivinationTypes) ? 'selected' : ''; ?>">
                                            <input type="checkbox" name="divination_types[]" value="<?php echo h($key); ?>"
                                                   <?php echo in_array($key, $currentDivinationTypes) ? 'checked' : ''; ?>>
                                            <span class="identity-tag western"><?php echo h($name); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- 东玄标签 -->
                            <div class="identity-category">
                                <h4>🏮 东玄标签</h4>
                                <div class="identity-tags">
                                    <?php foreach (DivinationConfig::getEasternDivinationTypes() as $key => $name): ?>
                                        <label class="identity-tag-label <?php echo in_array($key, $currentDivinationTypes) ? 'selected' : ''; ?>">
                                            <input type="checkbox" name="divination_types[]" value="<?php echo h($key); ?>"
                                                   <?php echo in_array($key, $currentDivinationTypes) ? 'checked' : ''; ?>>
                                            <span class="identity-tag eastern"><?php echo h($name); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- 主要标签选择 -->
                            <div class="primary-identity-selection">
                                <label for="primary_identity">主要身份标签 *</label>
                                <select id="primary_identity" name="primary_identity" required>
                                    <option value="">请选择主要标签</option>
                                    <?php foreach (array_merge(DivinationConfig::getWesternDivinationTypes(), DivinationConfig::getEasternDivinationTypes()) as $key => $name): ?>
                                        <option value="<?php echo h($key); ?>"
                                                <?php echo $currentPrimaryIdentity === $key ? 'selected' : ''; ?>
                                                data-category="<?php echo in_array($key, array_keys(DivinationConfig::getWesternDivinationTypes())) ? 'western' : 'eastern'; ?>">
                                            <?php echo h($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small>主要标签将在您的个人页面中突出显示</small>
                            </div>

                            <input type="hidden" id="identity_category" name="identity_category" value="<?php echo h($currentIdentityCategory); ?>">
                        </div>

                        <div class="form-group">
                            <label for="description">个人简介</label>
                            <textarea id="description" name="description" rows="4"
                                      placeholder="请简单介绍您的塔罗经历和服务特色"><?php echo h($reader['description'] ?? ''); ?></textarea>
                        </div>

                        <!-- 联系方式设置 - 合并到基本信息中 -->
                        <div class="form-section">
                            <h3>📞 联系方式设置</h3>
                            <p class="form-section-desc">设置您的联系方式，用户查看后可以通过这些方式联系您</p>

                            <div class="form-group">
                                <label for="contact_info">联系信息描述</label>
                                <textarea id="contact_info" name="contact_info" rows="3"
                                          placeholder="请简单介绍您的服务时间、预约方式等信息"><?php echo h($reader['contact_info'] ?? ''); ?></textarea>
                                <small>例如：工作时间9:00-21:00，请提前预约</small>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="wechat">💬 微信号</label>
                                    <input type="text" id="wechat" name="wechat"
                                           placeholder="请输入微信号"
                                           value="<?php echo h($reader['wechat'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="qq">🐧 QQ号码</label>
                                    <input type="text" id="qq" name="qq"
                                           placeholder="请输入QQ号码"
                                           value="<?php echo h($reader['qq'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="weibo">📱 微博账号</label>
                                    <input type="text" id="weibo" name="weibo"
                                           placeholder="请输入微博账号"
                                           value="<?php echo h($reader['weibo'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="other_contact">🔗 其他联系方式</label>
                                    <input type="text" id="other_contact" name="other_contact"
                                           placeholder="如小红书、抖音等"
                                           value="<?php echo h($reader['other_contact'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- 密码修改 - 合并到基本信息中 -->
                        <div class="form-section">
                            <h3>🔒 密码修改</h3>
                            <p class="form-section-desc">如需修改密码，请填写以下信息</p>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="current_password">当前密码</label>
                                    <input type="password" id="current_password" name="current_password"
                                           placeholder="请输入当前密码">
                                </div>

                                <div class="form-group">
                                    <label for="new_password">新密码</label>
                                    <input type="password" id="new_password" name="new_password"
                                           placeholder="请输入新密码">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">确认新密码</label>
                                <input type="password" id="confirm_password" name="confirm_password"
                                       placeholder="请再次输入新密码">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">保存所有设置</button>
                    </form>
                </div>
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

        // 身份标签交互逻辑
        document.addEventListener('DOMContentLoaded', function() {
            const identityLabels = document.querySelectorAll('.identity-tag-label');
            const primarySelect = document.getElementById('primary_identity');
            const identityCategoryInput = document.getElementById('identity_category');

            // 标签选择限制（最多3个）
            function updateTagSelection() {
                const checkedBoxes = document.querySelectorAll('.identity-tag-label input[type="checkbox"]:checked');
                const uncheckedBoxes = document.querySelectorAll('.identity-tag-label input[type="checkbox"]:not(:checked)');

                // 如果已选择3个，禁用其他选项
                if (checkedBoxes.length >= 3) {
                    uncheckedBoxes.forEach(box => {
                        box.disabled = true;
                        box.parentElement.style.opacity = '0.5';
                    });
                } else {
                    uncheckedBoxes.forEach(box => {
                        box.disabled = false;
                        box.parentElement.style.opacity = '1';
                    });
                }

                // 更新主要标签选项
                updatePrimaryOptions();
            }

            // 更新主要标签下拉选项
            function updatePrimaryOptions() {
                const checkedBoxes = document.querySelectorAll('.identity-tag-label input[type="checkbox"]:checked');
                const selectedValues = Array.from(checkedBoxes).map(box => box.value);

                // 清空并重新填充主要标签选项
                primarySelect.innerHTML = '<option value="">请选择主要标签</option>';

                selectedValues.forEach(value => {
                    const option = document.createElement('option');
                    option.value = value;

                    // 获取标签名称
                    const label = document.querySelector(`input[value="${value}"]`).parentElement.querySelector('.identity-tag').textContent;
                    option.textContent = label;

                    // 设置类别
                    const isWestern = document.querySelector(`input[value="${value}"]`).parentElement.querySelector('.identity-tag').classList.contains('western');
                    option.dataset.category = isWestern ? 'western' : 'eastern';

                    primarySelect.appendChild(option);
                });

                // 如果当前选择的主要标签不在已选择的标签中，清空选择
                if (!selectedValues.includes(primarySelect.value)) {
                    primarySelect.value = '';
                    identityCategoryInput.value = '';
                }
            }

            // 监听标签选择变化
            identityLabels.forEach(label => {
                const checkbox = label.querySelector('input[type="checkbox"]');

                checkbox.addEventListener('change', function() {
                    // 更新视觉状态
                    if (this.checked) {
                        label.classList.add('selected');
                    } else {
                        label.classList.remove('selected');
                    }

                    updateTagSelection();
                });
            });

            // 监听主要标签选择变化
            primarySelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption && selectedOption.dataset.category) {
                    identityCategoryInput.value = selectedOption.dataset.category;
                } else {
                    identityCategoryInput.value = '';
                }
            });

            // 初始化状态
            updateTagSelection();
        });

        // 证书多选删除功能
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.certificate-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);

            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });

            updateDeleteButton();
        }

        function updateDeleteButton() {
            const checkboxes = document.querySelectorAll('.certificate-checkbox');
            const checkedBoxes = Array.from(checkboxes).filter(cb => cb.checked);
            const deleteBtn = document.getElementById('delete-selected-btn');

            if (deleteBtn) {
                deleteBtn.style.display = checkedBoxes.length > 0 ? 'inline-block' : 'none';
                deleteBtn.textContent = `删除选中 (${checkedBoxes.length})`;
            }
        }

        function deleteSelected() {
            const checkboxes = document.querySelectorAll('.certificate-checkbox');
            const checkedIndexes = Array.from(checkboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.value);

            if (checkedIndexes.length === 0) {
                alert('请选择要删除的证书');
                return;
            }

            if (!confirm(`确定删除选中的 ${checkedIndexes.length} 个证书吗？此操作不可恢复。`)) {
                return;
            }

            // 创建表单并提交
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_multiple_certificates';
            form.appendChild(actionInput);

            checkedIndexes.forEach(index => {
                const indexInput = document.createElement('input');
                indexInput.type = 'hidden';
                indexInput.name = 'certificate_indexes[]';
                indexInput.value = index;
                form.appendChild(indexInput);
            });

            document.body.appendChild(form);
            form.submit();
        }

        // 监听证书复选框变化
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.certificate-checkbox');
            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateDeleteButton);
            });
            updateDeleteButton();
        });
    </script>

    <!-- 图片懒加载JavaScript -->
    <script src="../assets/js/lazy-loading.js"></script>

    <footer class="reader-footer">
        <div class="container">
            <p>&copy; 2024 塔罗师展示平台. 保留所有权利.</p>
        </div>
    </footer>
</body>
</html>
