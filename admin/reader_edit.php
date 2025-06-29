<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/DivinationConfig.php';

// 检查管理员权限
requireAdminLogin();



$db = Database::getInstance();
$success = '';
$errors = [];

// 获取占卜师ID
$readerId = (int)($_GET['id'] ?? 0);
if (!$readerId) {
    header('Location: readers.php');
    exit;
}

// 获取占卜师信息
$reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);
if (!$reader) {
    header('Location: readers.php');
    exit;
}

// 获取当前查看次数（优先从readers表的view_count字段获取，如果为空则从contact_views计算）
$currentViewCount = $reader['view_count'] ?? 0;
if ($currentViewCount == 0) {
    // 如果view_count字段为0，则从contact_views表计算实际查看次数
    $viewCountResult = $db->fetchOne("SELECT COUNT(*) as count FROM contact_views WHERE reader_id = ?", [$readerId]);
    $currentViewCount = $viewCountResult['count'] ?? 0;
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'experience_years' => (int)($_POST['experience_years'] ?? 0),
        'description' => trim($_POST['description'] ?? ''),
        'custom_specialty' => trim($_POST['custom_specialty'] ?? ''),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'view_count' => max(0, (int)($_POST['view_count'] ?? $currentViewCount)),
        'nationality' => $_POST['nationality'] ?? '',
        'divination_types' => $_POST['divination_types'] ?? [],
        'primary_identity' => $_POST['primary_identity'] ?? ''
    ];
    
    // 处理擅长方向
    $specialties = $_POST['specialties'] ?? [];
    if (!empty($data['custom_specialty'])) {
        // 分割多个自定义标签（用逗号或顿号分隔）
        $customTags = preg_split('/[,，、]/', $data['custom_specialty']);
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
    $specialtiesStr = implode('、', $specialties);
    
    // 验证必填字段
    if (empty($data['username']) || empty($data['email']) || 
        empty($data['full_name']) || empty($specialties)) {
        $errors[] = '请填写所有必填字段';
    }
    
    if ($data['experience_years'] < 1) {
        $errors[] = '从业年数至少为1年';
    }
    
    // 检查用户名和邮箱是否已存在（排除当前用户）
    if (empty($errors)) {
        $existingUser = $db->fetchOne("SELECT id FROM readers WHERE (username = ? OR email = ?) AND id != ?", 
                                     [$data['username'], $data['email'], $readerId]);
        if ($existingUser) {
            $errors[] = '用户名或邮箱已被其他用户使用';
        }
    }
    
    // 验证占卜类型选择
    if (!empty($data['divination_types'])) {
        $divinationValidation = DivinationConfig::validateDivinationSelection(
            $data['divination_types'],
            $data['primary_identity']
        );

        if (!$divinationValidation['valid']) {
            $errors = array_merge($errors, $divinationValidation['errors']);
        }
    }

    // 处理占卜类型数据
    $divinationTypesJson = null;
    $identityCategory = null;
    if (!empty($data['divination_types'])) {
        $divinationTypesJson = json_encode($data['divination_types']);

        // 根据主要身份标签确定类别
        if (!empty($data['primary_identity'])) {
            $identityCategory = DivinationConfig::getDivinationCategory($data['primary_identity']);
        }
    }

    // 处理密码更新
    $updateData = [
        'username' => $data['username'],
        'email' => $data['email'],
        'full_name' => $data['full_name'],
        'phone' => $data['phone'],
        'experience_years' => $data['experience_years'],
        'specialties' => $specialtiesStr,
        'description' => $data['description'],
        'is_active' => $data['is_active'],
        'is_featured' => $data['is_featured'],
        'nationality' => $data['nationality'],
        'divination_types' => $divinationTypesJson,
        'primary_identity' => $data['primary_identity'],
        'identity_category' => $identityCategory
    ];
    
    // 如果提供了新密码，则更新密码
    if (!empty($_POST['password'])) {
        if ($_POST['password'] !== $_POST['confirm_password']) {
            $errors[] = '两次输入的密码不一致';
        } elseif (strlen($_POST['password']) < PASSWORD_MIN_LENGTH) {
            $errors[] = '密码长度至少为' . PASSWORD_MIN_LENGTH . '个字符';
        } else {
            $updateData['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
    }
    
    // 处理头像上传
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
                
                // 删除旧头像
                if (!empty($reader['photo'])) {
                    $oldPhotoPath = '../' . $reader['photo'];
                    $realPath = realpath($oldPhotoPath);
                    $realBaseDir = realpath('../');
                    if ($realPath && $realBaseDir && strpos($realPath, $realBaseDir) === 0 && file_exists($oldPhotoPath)) {
                        unlink($oldPhotoPath);
                    }
                }
                
                // 保存相对路径到数据库（相对于网站根目录）
                $updateData['photo'] = PHOTO_PATH . $fileName;
            } else {
                $errors[] = '头像上传失败，请检查目录权限';
            }
        }
    }
    
    // 如果没有错误，更新数据库
    if (empty($errors)) {
        // 处理查看次数修改 - 直接更新readers表的view_count字段
        $newViewCount = $data['view_count'];
        if ($newViewCount != $currentViewCount) {
            // 直接更新readers表的view_count字段
            $updateData['view_count'] = $newViewCount;
        } else {
            // 如果没有修改，从updateData中移除view_count
            unset($updateData['view_count']);
        }

        $result = $db->update('readers', $updateData, 'id = ?', [$readerId]);

        if ($result) {
            $success = '占卜师信息更新成功！';
            // 重新获取更新后的数据
            $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);
            // 重新获取查看次数
            $currentViewCount = $reader['view_count'] ?? 0;
        } else {
            $errors[] = '数据库更新失败';
        }
    }
}

// 解析擅长方向
$currentSpecialties = !empty($reader['specialties']) ? explode('、', $reader['specialties']) : [];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑占卜师 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/divination-tags.css">
    <style>
        /* 一键选择按钮样式 */
        .specialty-quick-actions {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 6px 12px !important;
            font-size: 12px !important;
            border-radius: 15px !important;
        }

        .btn-secondary {
            background: #6c757d !important;
            color: white !important;
            border: none !important;
        }

        .btn-secondary:hover {
            background: #5a6268 !important;
            transform: translateY(-1px) !important;
        }

        /* 美化占卜方向选择 */
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
            justify-content: center !important;
            cursor: pointer !important;
            font-size: 14px !important;
            position: relative !important;
            padding: 12px 8px !important;
            margin-bottom: 0 !important;
            background: white !important;
            border-radius: 8px !important;
            transition: all 0.3s ease !important;
            border: 2px solid transparent !important;
            text-align: center !important;
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

        .checkbox-label input:checked {
            background: #d4af37 !important;
        }

        .checkmark {
            position: absolute !important;
            top: 8px !important;
            right: 8px !important;
            height: 16px !important;
            width: 16px !important;
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

        .specialty-text {
            padding-right: 25px !important;
            display: block !important;
            width: 100% !important;
        }

        .current-photo {
            margin-bottom: 15px;
            text-align: center;
        }

        .current-photo img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 10px;
            border: 2px solid #d4af37;
        }

        /* 占卜类型选择区域样式修复 */
        .divination-category {
            margin-bottom: 25px;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e9ecef;
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

        /* 状态设置区域样式修复 */
        .form-section {
            margin-bottom: 30px;
            background: white;
            border-radius: 12px;
            padding: 25px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .form-section h3 {
            margin: 0 0 20px 0;
            color: #374151;
            font-size: 1.2rem;
            font-weight: 600;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }

        /* 状态复选框样式 */
        .status-checkboxes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .checkbox-group {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .checkbox-group:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            font-weight: 500;
            color: #374151;
            margin: 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #667eea;
            cursor: pointer;
        }

        /* 密码更新区域样式 */
        .password-section {
            background: #fff8f0;
            border: 2px solid #fbbf24;
            border-radius: 12px;
            padding: 25px;
            margin: 20px 0;
        }

        .password-section h4 {
            margin: 0 0 15px 0;
            color: #92400e;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .password-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .password-fields .form-group {
            margin-bottom: 0;
        }

        .password-fields input {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .password-fields input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* 表单按钮样式 */
        .form-actions {
            margin-top: 30px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 15px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            text-decoration: none;
            color: white;
        }

        /* 表单整体布局 */
        .form-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .divination-grid {
                grid-template-columns: 1fr;
            }

            .status-checkboxes {
                grid-template-columns: 1fr;
            }

            .password-fields {
                grid-template-columns: 1fr;
            }

            .form-section {
                padding: 20px;
            }

            .btn-primary, .btn-secondary {
                width: 100%;
                margin: 5px 0;
            }
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .specialty-options {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)) !important;
                gap: 10px !important;
                padding: 15px !important;
            }

            .specialty-quick-actions {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .specialty-options {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-sidebar">
            <?php include '../includes/admin_sidebar.php'; ?>
        </div>
        
        <div class="admin-content">
            <div class="page-header">
                <h1>编辑占卜师 - <?php echo h($reader['full_name']); ?></h1>
                <div class="page-actions">
                    <a href="readers.php" class="btn btn-secondary">返回列表</a>
                </div>
            </div>
            
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

            <div class="card">
                <div class="card-header">
                    <h2>占卜师信息</h2>
                </div>
                <div class="card-body">
                    <div class="form-container">
                        <form method="POST" enctype="multipart/form-data">
                        <!-- 当前头像显示 -->
                        <?php if (!empty($reader['photo'])): ?>
                            <div class="current-photo">
                                <h3>当前头像</h3>
                                <img src="../<?php echo h($reader['photo']); ?>" alt="当前头像">
                            </div>
                        <?php endif; ?>
                        
                        <!-- 头像上传 -->
                        <div class="form-group">
                            <label for="photo">更换头像照片（可选）</label>
                            <input type="file" id="photo" name="photo" accept="image/*">
                            <small>请上传清晰的个人照片，支持JPG、PNG格式，文件大小不超过5MB</small>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">用户名 *</label>
                                <input type="text" id="username" name="username" required
                                       value="<?php echo h($reader['username']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="email">邮箱地址 *</label>
                                <input type="email" id="email" name="email" required
                                       value="<?php echo h($reader['email']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">昵称 *</label>
                                <input type="text" id="full_name" name="full_name" required
                                       placeholder="请输入占卜师昵称"
                                       value="<?php echo h($reader['full_name']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">手机号码</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo h($reader['phone']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="experience_years">从业年数 *</label>
                                <input type="number" id="experience_years" name="experience_years" required min="1" max="50"
                                       value="<?php echo h($reader['experience_years']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="nationality">国籍 *</label>
                                <select id="nationality" name="nationality" required>
                                    <option value="">请选择国籍</option>
                                    <?php
                                    $nationalities = DivinationConfig::getNationalities();
                                    foreach ($nationalities as $code => $name):
                                    ?>
                                        <option value="<?php echo h($code); ?>" <?php echo $reader['nationality'] === $code ? 'selected' : ''; ?>>
                                            <?php echo h($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="view_count">查看次数</label>
                                <input type="number" id="view_count" name="view_count" min="0"
                                       value="<?php echo $currentViewCount; ?>">
                                <small>当前查看次数：<?php echo $currentViewCount; ?>，可手动调整</small>
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
                                    $isChecked = in_array($specialty, $currentSpecialties);
                                ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="specialties[]" value="<?php echo h($specialty); ?>"
                                               <?php echo $isChecked ? 'checked' : ''; ?>>
                                        <span class="checkmark"></span>
                                        <span class="specialty-text"><?php echo h($specialty); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="custom-specialty">
                                <label for="custom_specialty">其他占卜方向（可选）</label>
                                <input type="text" id="custom_specialty" name="custom_specialty"
                                       placeholder="请填写其他擅长方向，用逗号分隔，每个不超过4字，最多3个">
                                <small>注意：自定义标签只在个人页面显示，列表页面只显示系统标准标签</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">个人简介</label>
                            <textarea id="description" name="description" rows="4"
                                      placeholder="请简单介绍占卜师的经历和服务特色"><?php echo h($reader['description']); ?></textarea>
                        </div>

                        <!-- 占卜类型选择 -->
                        <div class="form-section">
                            <h3>🔮 占卜类型</h3>
                            <p style="color: #6b7280; margin-bottom: 20px;">
                                最多选择3项，其中1项作为主要身份标签
                            </p>

                            <?php
                            $allDivinationTypes = DivinationConfig::getAllDivinationTypes();
                            $selectedTypes = [];
                            if (!empty($reader['divination_types'])) {
                                $selectedTypes = json_decode($reader['divination_types'], true) ?: [];
                            }
                            $primaryIdentity = $reader['primary_identity'] ?? '';
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
                                    • 主要身份标签将在占卜师的个人页面和列表中显示<br>
                                    • 其他选择的类型将作为技能项展示
                                </small>
                            </div>
                        </div>

                        <!-- 状态设置 -->
                        <div class="form-section">
                            <h3>⚙️ 状态设置</h3>
                            <div class="status-checkboxes">
                                <div class="checkbox-group">
                                    <label>
                                        <input type="checkbox" name="is_active" value="1" <?php echo $reader['is_active'] ? 'checked' : ''; ?>>
                                        <span>激活状态</span>
                                    </label>
                                    <small style="color: #6b7280; margin-top: 5px; display: block;">
                                        激活后占卜师可以正常使用平台功能
                                    </small>
                                </div>

                                <div class="checkbox-group">
                                    <label>
                                        <input type="checkbox" name="is_featured" value="1" <?php echo $reader['is_featured'] ? 'checked' : ''; ?>>
                                        <span>推荐占卜师</span>
                                    </label>
                                    <small style="color: #6b7280; margin-top: 5px; display: block;">
                                        推荐占卜师将在首页显示
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 密码更新 -->
                        <div class="password-section">
                            <h4>🔐 密码更新</h4>
                            <p style="color: #6b7280; margin-bottom: 20px;">
                                如需修改密码请填写以下字段，留空则不修改密码
                            </p>
                            <div class="password-fields">
                                <div class="form-group">
                                    <label for="password">新密码</label>
                                    <input type="password" id="password" name="password"
                                           placeholder="请输入新密码">
                                    <small style="color: #6b7280;">至少<?php echo PASSWORD_MIN_LENGTH; ?>个字符</small>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password">确认新密码</label>
                                    <input type="password" id="confirm_password" name="confirm_password"
                                           placeholder="请再次输入新密码">
                                    <small style="color: #6b7280;">必须与新密码一致</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">更新占卜师信息</button>
                            <a href="readers.php" class="btn btn-secondary">取消</a>
                        </div>
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
            // 清空所有选择
            clearAllSpecialties();

            // 选择热门方向：感情、事业、财运
            const popularSpecialties = ['感情', '事业', '财运'];
            const checkboxes = document.querySelectorAll('input[name="specialties[]"]');

            checkboxes.forEach(checkbox => {
                if (popularSpecialties.includes(checkbox.value)) {
                    checkbox.checked = true;
                }
            });
        }

        // 添加动画效果
        document.addEventListener('DOMContentLoaded', function() {
            const labels = document.querySelectorAll('.checkbox-label');
            labels.forEach((label, index) => {
                label.style.animationDelay = (index * 0.1) + 's';
                label.style.animation = 'fadeInUp 0.5s ease forwards';
            });
        });

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

        // 密码确认验证
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');

        function validatePasswords() {
            if (password.value && confirmPassword.value) {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('密码不一致');
                    confirmPassword.style.borderColor = '#dc3545';
                } else {
                    confirmPassword.setCustomValidity('');
                    confirmPassword.style.borderColor = '#28a745';
                }
            } else {
                confirmPassword.setCustomValidity('');
                confirmPassword.style.borderColor = '#e5e7eb';
            }
        }

        if (password && confirmPassword) {
            password.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);
        }

        // 表单提交验证
        document.querySelector('form').addEventListener('submit', function(e) {
            // 检查占卜类型选择
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
            if (password.value && password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('两次输入的密码不一致');
                return false;
            }

            return true;
        });

        // CSS动画
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .checkbox-label {
                opacity: 0;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
