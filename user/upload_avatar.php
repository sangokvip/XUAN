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
    // 检查是否使用默认头像
    $useDefaultAvatar = isset($_POST['use_default_avatar']) && $_POST['use_default_avatar'] === '1';
    
    if ($useDefaultAvatar) {
        // 使用默认头像
        $defaultAvatar = $user['gender'] === 'male' ? 'img/nm.jpg' : 'img/nf.jpg';
        
        try {
            $db->update('users', [
                'avatar' => $defaultAvatar
            ], 'id = ?', [$userId]);
            
            $success = '头像已更新为默认头像！';
            
            // 重新获取用户信息
            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            
        } catch (Exception $e) {
            $errors[] = '头像更新失败，请稍后重试';
        }
    } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        // 处理头像上传
        $file = $_FILES['avatar'];
        
        // 验证文件类型
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($extension, $allowedTypes)) {
            $errors[] = '只支持JPG、PNG、GIF格式的图片';
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB
            $errors[] = '图片文件大小不能超过5MB';
        } else {
            // 使用优化的图片上传
            $uploadOptions = [
                'max_width' => AVATAR_MAX_WIDTH,
                'max_height' => AVATAR_MAX_HEIGHT,
                'quality' => AVATAR_QUALITY,
                'generate_thumbnails' => true,
                'thumbnail_sizes' => [
                    'small' => [80, 80],    // 小头像（列表显示）
                    'medium' => [150, 150], // 中等头像（卡片显示）
                    'large' => [300, 300]   // 大头像（详情页）
                ],
                'webp_support' => WEBP_ENABLED
            ];

            // 创建上传目录
            $uploadDir = '../' . PHOTO_PATH;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // 临时保存文件以便优化处理
            $tempFileName = 'temp_user_' . $userId . '_' . md5(uniqid() . time()) . '.' . $extension;
            $tempPath = $uploadDir . $tempFileName;

            if (move_uploaded_file($file['tmp_name'], $tempPath)) {
                // 使用优化上传函数
                $tempFile = [
                    'tmp_name' => $tempPath,
                    'name' => 'user_' . $userId . '_' . md5(uniqid() . time()) . '.' . $extension,
                    'size' => filesize($tempPath),
                    'error' => UPLOAD_ERR_OK
                ];

                $uploadResult = uploadOptimizedImage($tempFile, PHOTO_PATH, $uploadOptions);

                // 删除临时文件
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }

                if ($uploadResult['success']) {
                    // 删除旧头像（如果不是默认头像）
                    if ($user['avatar'] && !str_contains($user['avatar'], 'img/n')) {
                        $oldAvatarPath = '../' . $user['avatar'];
                        if (file_exists($oldAvatarPath)) {
                            unlink($oldAvatarPath);
                        }

                        // 删除旧头像的缩略图和WebP版本
                        $oldBaseName = pathinfo($user['avatar'], PATHINFO_FILENAME);
                        $oldDir = '../' . dirname($user['avatar']) . '/';
                        foreach (['small', 'medium', 'large'] as $size) {
                            $oldThumb = $oldDir . $oldBaseName . '_' . $size . '.jpg';
                            $oldWebp = $oldDir . $oldBaseName . '_' . $size . '.webp';
                            if (file_exists($oldThumb)) unlink($oldThumb);
                            if (file_exists($oldWebp)) unlink($oldWebp);
                        }
                    }

                    // 更新数据库
                    try {
                        $db->update('users', [
                            'avatar' => PHOTO_PATH . $uploadResult['filename']
                        ], 'id = ?', [$userId]);

                        $success = '头像上传成功！图片已自动优化以提升加载速度。';

                        // 重新获取用户信息
                        $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

                    } catch (Exception $e) {
                        $errors[] = '头像更新失败，请稍后重试';
                        // 删除已上传的文件
                        if (file_exists($uploadResult['path'])) {
                            unlink($uploadResult['path']);
                        }
                    }
                } else {
                    $errors[] = '头像优化失败：' . $uploadResult['message'];
                }
            } else {
                $errors[] = '头像上传失败，请检查目录权限';
            }
        }
    } else {
        $errors[] = '请选择要上传的头像文件';
    }
}

$pageTitle = '更换头像';
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
        .avatar-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        
        .avatar-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .avatar-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .avatar-form {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .current-avatar {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .current-avatar img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid #e5e7eb;
            object-fit: cover;
            margin-bottom: 15px;
        }
        
        .current-avatar p {
            color: #6b7280;
            margin: 0;
        }
        
        .upload-section {
            border: 2px dashed #d1d5db;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
            transition: all 0.3s ease;
        }
        
        .upload-section:hover {
            border-color: #10b981;
            background: #f0fdf4;
        }
        
        .upload-section.dragover {
            border-color: #10b981;
            background: #f0fdf4;
        }
        
        .upload-icon {
            font-size: 3rem;
            color: #10b981;
            margin-bottom: 20px;
        }
        
        .upload-text {
            font-size: 1.1rem;
            color: #374151;
            margin-bottom: 15px;
        }
        
        .upload-hint {
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 20px;
        }
        
        .file-input {
            display: none;
        }
        
        .btn-upload {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }
        
        .btn-default {
            background: #6b7280;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-left: 15px;
        }
        
        .btn-default:hover {
            background: #4b5563;
            transform: translateY(-1px);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
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
            margin-top: 20px;
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
        
        .preview-section {
            display: none;
            text-align: center;
            margin-top: 20px;
        }
        
        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
        }
        
        @media (max-width: 768px) {
            .avatar-container {
                margin: 20px auto;
                padding: 15px;
            }
            
            .avatar-form {
                padding: 25px 20px;
            }
            
            .upload-section {
                padding: 25px 15px;
            }
            
            .btn-default {
                margin-left: 0;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="avatar-container">
        <div class="avatar-header">
            <h1>📷 更换头像</h1>
            <p>上传您的个人头像</p>
        </div>
        
        <div class="avatar-form">
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
            
            <div class="current-avatar">
                <?php
                $currentAvatarSrc = getUserAvatarUrl($user, '../');
                ?>
                <img src="<?php echo h($currentAvatarSrc); ?>" alt="当前头像" id="currentAvatar">
                <p>当前头像</p>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="avatarForm">
                <div class="upload-section" id="uploadSection">
                    <div class="upload-icon">📸</div>
                    <div class="upload-text">点击选择图片或拖拽图片到此处</div>
                    <div class="upload-hint">支持JPG、PNG、GIF格式，文件大小不超过5MB</div>
                    <input type="file" id="avatar" name="avatar" accept="image/*" class="file-input">
                    <button type="button" class="btn-upload" onclick="document.getElementById('avatar').click()">
                        📁 选择图片
                    </button>
                    <button type="button" class="btn-default" onclick="useDefaultAvatar()">
                        🎭 使用默认头像
                    </button>
                </div>
                
                <div class="preview-section" id="previewSection">
                    <p>预览：</p>
                    <img id="previewImage" class="preview-image" alt="预览">
                </div>
                
                <input type="hidden" id="use_default_avatar" name="use_default_avatar" value="0">
                <button type="submit" class="btn-submit" id="submitBtn" style="display: none;">💾 保存头像</button>
            </form>
            
            <a href="index.php" class="btn-secondary">← 返回用户中心</a>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const avatarInput = document.getElementById('avatar');
            const uploadSection = document.getElementById('uploadSection');
            const previewSection = document.getElementById('previewSection');
            const previewImage = document.getElementById('previewImage');
            const submitBtn = document.getElementById('submitBtn');
            const useDefaultAvatarInput = document.getElementById('use_default_avatar');
            
            // 文件选择事件
            avatarInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    previewFile(file);
                    useDefaultAvatarInput.value = '0';
                }
            });
            
            // 拖拽上传
            uploadSection.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadSection.classList.add('dragover');
            });
            
            uploadSection.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadSection.classList.remove('dragover');
            });
            
            uploadSection.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadSection.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const file = files[0];
                    if (file.type.startsWith('image/')) {
                        avatarInput.files = files;
                        previewFile(file);
                        useDefaultAvatarInput.value = '0';
                    } else {
                        alert('请选择图片文件');
                    }
                }
            });
            
            function previewFile(file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewSection.style.display = 'block';
                    submitBtn.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
        
        function useDefaultAvatar() {
            if (confirm('确定要使用默认头像吗？')) {
                document.getElementById('use_default_avatar').value = '1';
                document.getElementById('avatarForm').submit();
            }
        }
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
