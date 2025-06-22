<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$db = Database::getInstance();
$success = '';
$errors = [];

// 获取塔罗师ID
$readerId = (int)($_GET['id'] ?? 0);
if (!$readerId) {
    header('Location: readers.php');
    exit;
}

// 获取塔罗师信息
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
        'view_count' => max(0, (int)($_POST['view_count'] ?? $currentViewCount))
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
        'is_featured' => $data['is_featured']
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
                if (!empty($reader['photo']) && file_exists('../' . $reader['photo'])) {
                    unlink('../' . $reader['photo']);
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
            $success = '塔罗师信息更新成功！';
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
    <title>编辑塔罗师 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
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
                <h1>编辑塔罗师 - <?php echo h($reader['full_name']); ?></h1>
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
                    <h2>塔罗师信息</h2>
                </div>
                <div class="card-body">
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
                                       placeholder="请输入塔罗师昵称"
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
                                      placeholder="请简单介绍塔罗师的经历和服务特色"><?php echo h($reader['description']); ?></textarea>
                        </div>
                        
                        <!-- 状态设置 -->
                        <div class="form-row">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="is_active" value="1" <?php echo $reader['is_active'] ? 'checked' : ''; ?>>
                                    激活状态
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="is_featured" value="1" <?php echo $reader['is_featured'] ? 'checked' : ''; ?>>
                                    推荐塔罗师
                                </label>
                            </div>
                        </div>
                        
                        <!-- 密码更新 -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">新密码（留空则不修改）</label>
                                <input type="password" id="password" name="password">
                                <small>至少<?php echo PASSWORD_MIN_LENGTH; ?>个字符</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">确认新密码</label>
                                <input type="password" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">更新塔罗师信息</button>
                            <a href="readers.php" class="btn btn-secondary">取消</a>
                        </div>
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
