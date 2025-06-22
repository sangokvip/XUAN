<?php
session_start();
require_once '../config/config.php';

// 检查塔罗师登录
requireReaderLogin();

$db = Database::getInstance();
$reader = getReaderById($_SESSION['reader_id']);

$errors = [];
$success = '';

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

                    // 删除旧照片
                    if (!empty($reader['photo']) && file_exists($reader['photo'])) {
                        unlink($reader['photo']);
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
    <title>个人资料管理 - 塔罗师后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/reader.css">
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
    
    <div class="reader-container">
        <div class="reader-sidebar">
            <?php include '../includes/reader_sidebar.php'; ?>
        </div>
        
        <div class="reader-content">
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
            
            <!-- 个人照片 -->
            <div class="card">
                <div class="card-header">
                    <h2>个人照片</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($reader['photo'])): ?>
                        <div class="current-photo">
                            <h3>当前照片</h3>
                            <?php
                            // 检查文件路径，如果是相对路径则直接使用，如果是绝对路径则需要调整
                            $photoPath = $reader['photo'];
                            $displayPath = $photoPath;

                            // 如果路径不是以 ../ 开头，则添加 ../
                            if (!str_starts_with($photoPath, '../') && !str_starts_with($photoPath, '/')) {
                                $displayPath = '../' . $photoPath;
                            }
                            ?>
                            <p>照片路径: <?php echo h($photoPath); ?></p>
                            <p>文件存在: <?php echo file_exists($displayPath) ? '是' : '否'; ?></p>
                            <?php if (file_exists($displayPath)): ?>
                                <img src="<?php echo h($displayPath); ?>" alt="当前照片" class="profile-photo"
                                     style="max-width: 250px; max-height: 300px; width: auto; height: auto; object-fit: cover; border-radius: 10px; border: 3px solid #d4af37; box-shadow: 0 4px 15px rgba(0,0,0,0.1); display: block; margin: 15px auto;">
                            <?php else: ?>
                                <p style="color: #999;">照片文件不存在，请重新上传</p>
                                <p style="color: #666; font-size: 12px;">检查路径: <?php echo h($displayPath); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-photo">
                            <p style="color: #666; text-align: center; padding: 20px;">
                                暂未上传照片，请上传您的个人照片
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_photo">
                        
                        <div class="form-group">
                            <label for="photo">选择新照片</label>
                            <input type="file" id="photo" name="photo" accept="image/*" required>
                            <small>支持格式：JPG、PNG、GIF，最大5MB</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">上传照片</button>
                    </form>
                </div>
            </div>
            
            <!-- 价格列表 -->
            <div class="card">
                <div class="card-header">
                    <h2>价格列表</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($reader['price_list_image'])): ?>
                        <div class="current-price-list">
                            <h3>当前价格列表</h3>
                            <?php
                            // 检查文件路径
                            $priceListPath = $reader['price_list_image'];
                            $displayPricePath = $priceListPath;

                            // 如果路径不是以 ../ 开头，则添加 ../
                            if (!str_starts_with($priceListPath, '../') && !str_starts_with($priceListPath, '/')) {
                                $displayPricePath = '../' . $priceListPath;
                            }
                            ?>
                            <p>价格列表路径: <?php echo h($priceListPath); ?></p>
                            <p>文件存在: <?php echo file_exists($displayPricePath) ? '是' : '否'; ?></p>
                            <?php if (file_exists($displayPricePath)): ?>
                                <img src="<?php echo h($displayPricePath); ?>" alt="当前价格列表" class="price-list-image"
                                     style="max-width: 400px; max-height: 600px; width: auto; height: auto; object-fit: contain; border-radius: 10px; border: 2px solid #d4af37; box-shadow: 0 4px 15px rgba(0,0,0,0.1); display: block; margin: 15px auto;">
                            <?php else: ?>
                                <p style="color: #999;">价格列表文件不存在，请重新上传</p>
                                <p style="color: #666; font-size: 12px;">检查路径: <?php echo h($displayPricePath); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-price-list">
                            <p style="color: #666; text-align: center; padding: 20px;">
                                暂未上传价格列表，请上传您的服务价格列表图片
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_price_list">
                        
                        <div class="form-group">
                            <label for="price_list">选择价格列表图片</label>
                            <input type="file" id="price_list" name="price_list" accept="image/*" required>
                            <small>支持格式：JPG、PNG、GIF，最大5MB</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">上传价格列表</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="reader-footer">
        <div class="container">
            <p>&copy; 2024 塔罗师展示平台. 保留所有权利.</p>
        </div>
    </footer>
</body>
</html>
