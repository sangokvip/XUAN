<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$db = Database::getInstance();
$success = '';
$errors = [];

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'experience_years' => (int)($_POST['experience_years'] ?? 0),
        'description' => trim($_POST['description'] ?? ''),
        'custom_specialty' => trim($_POST['custom_specialty'] ?? '')
    ];
    
    // 处理擅长方向
    $specialties = $_POST['specialties'] ?? [];
    if (!empty($data['custom_specialty'])) {
        $specialties[] = $data['custom_specialty'];
    }
    $specialtiesStr = implode('、', $specialties);
    
    // 验证必填字段
    if (empty($data['username']) || empty($data['email']) || empty($data['password']) ||
        empty($data['full_name']) || empty($specialties)) {
        $errors[] = '请填写所有必填字段';
    }
    
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = '请上传头像照片';
    }
    
    if ($data['password'] !== $data['confirm_password']) {
        $errors[] = '两次输入的密码不一致';
    }
    
    if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
        $errors[] = '密码长度至少为' . PASSWORD_MIN_LENGTH . '个字符';
    }
    
    if ($data['experience_years'] < 1) {
        $errors[] = '从业年数至少为1年';
    }
    
    // 检查用户名和邮箱是否已存在
    if (empty($errors)) {
        $existingUser = $db->fetchOne("SELECT id FROM readers WHERE username = ? OR email = ?", 
                                     [$data['username'], $data['email']]);
        if ($existingUser) {
            $errors[] = '用户名或邮箱已存在';
        }
    }
    
    // 如果没有错误，处理注册
    if (empty($errors)) {
        // 处理头像上传
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

                // 创建塔罗师账户
                $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                
                $insertData = [
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'password_hash' => $hashedPassword,
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'experience_years' => $data['experience_years'],
                    'specialties' => $specialtiesStr,
                    'description' => $data['description'],
                    'photo' => $data['photo'],
                    'photo_circle' => $data['photo_circle'] ?? null,
                    'is_active' => 1,
                    'is_featured' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $result = $db->insert('readers', $insertData);
                
                if ($result) {
                    $success = '塔罗师添加成功！';
                    // 清空表单数据
                    $data = [];
                } else {
                    $errors[] = '数据库保存失败';
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
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>添加塔罗师 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/image-cropper.css">
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
                <h1>添加塔罗师</h1>
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
                    <p><a href="readers.php">返回塔罗师列表</a></p>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h2>塔罗师信息</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <!-- 头像上传 -->
                            <div class="form-group photo-upload-enhanced">
                                <label for="photo">头像照片 *</label>
                                <input type="file" id="photo" name="photo" accept="image/*" required>
                                <input type="hidden" id="photo_circle_data" name="photo_circle_data">
                                <small>请上传清晰的个人照片，支持JPG、PNG格式，文件大小不超过5MB。上传后可以剪裁圆形头像用于首页展示。</small>

                                <div class="current-photos" id="photo-previews" style="display: none;">
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

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="username">用户名 *</label>
                                    <input type="text" id="username" name="username" required
                                           value="<?php echo h($_POST['username'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="email">邮箱地址 *</label>
                                    <input type="email" id="email" name="email" required
                                           value="<?php echo h($_POST['email'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="full_name">昵称 *</label>
                                    <input type="text" id="full_name" name="full_name" required
                                           placeholder="请输入塔罗师昵称"
                                           value="<?php echo h($_POST['full_name'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">手机号码</label>
                                    <input type="tel" id="phone" name="phone" 
                                           value="<?php echo h($_POST['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="experience_years">从业年数 *</label>
                                <input type="number" id="experience_years" name="experience_years" required min="1" max="50"
                                       value="<?php echo h($_POST['experience_years'] ?? ''); ?>">
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
                                    $selectedSpecialties = $_POST['specialties'] ?? [];
                                    foreach ($predefinedSpecialties as $specialty):
                                    ?>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="specialties[]" value="<?php echo h($specialty); ?>"
                                                   <?php echo in_array($specialty, $selectedSpecialties) ? 'checked' : ''; ?>>
                                            <span class="checkmark"></span>
                                            <span class="specialty-text"><?php echo h($specialty); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <div class="custom-specialty">
                                    <label for="custom_specialty">其他占卜方向（可选）</label>
                                    <input type="text" id="custom_specialty" name="custom_specialty"
                                           placeholder="请填写其他擅长的占卜方向"
                                           value="<?php echo h($_POST['custom_specialty'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">个人简介</label>
                                <textarea id="description" name="description" rows="4" 
                                          placeholder="请简单介绍塔罗师的经历和服务特色"><?php echo h($_POST['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="password">密码 *</label>
                                    <input type="password" id="password" name="password" required>
                                    <small>至少<?php echo PASSWORD_MIN_LENGTH; ?>个字符</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">确认密码 *</label>
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">添加塔罗师</button>
                                <a href="readers.php" class="btn btn-secondary">取消</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
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

    <script src="../assets/js/image-cropper.js"></script>
    <script>
        // 头像上传和剪裁功能
        document.addEventListener('DOMContentLoaded', function() {
            const photoInput = document.getElementById('photo');
            const photoCircleData = document.getElementById('photo_circle_data');
            const photoPreviews = document.getElementById('photo-previews');
            const originalPreview = document.getElementById('original-preview');
            const circlePreview = document.getElementById('circle-preview');
            const cropButton = document.getElementById('crop-photo-btn');

            let originalFile = null;
            let circleBlob = null;

            photoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;

                originalFile = file;

                // 显示原始图片预览
                const reader = new FileReader();
                reader.onload = function(e) {
                    originalPreview.src = e.target.result;
                    photoPreviews.style.display = 'flex';
                };
                reader.readAsDataURL(file);

                // 自动打开剪裁工具
                cropPhoto(file);
            });

            cropButton.addEventListener('click', function() {
                if (originalFile) {
                    cropPhoto(originalFile);
                }
            });

            function cropPhoto(file) {
                window.imageCropper.show(file)
                    .then(function(blob) {
                        circleBlob = blob;

                        // 显示圆形头像预览
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            circlePreview.src = e.target.result;
                            cropButton.style.display = 'inline-block';
                        };
                        reader.readAsDataURL(blob);

                        // 将圆形头像数据转换为base64存储
                        const reader2 = new FileReader();
                        reader2.onload = function(e) {
                            photoCircleData.value = e.target.result;
                        };
                        reader2.readAsDataURL(blob);
                    })
                    .catch(function(error) {
                        if (error !== 'cancelled') {
                            console.error('剪裁失败:', error);
                        }
                    });
            }
        });
    </script>
</body>
</html>
