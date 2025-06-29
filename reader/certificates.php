<?php
session_start();
require_once '../config/config.php';

// 检查占卜师登录
requireReaderLogin();

$db = Database::getInstance();
$reader = getReaderById($_SESSION['reader_id']);

$errors = [];
$success = '';

// 获取当前证书列表
$certificates = [];
if (!empty($reader['certificates'])) {
    $certificates = json_decode($reader['certificates'], true) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload_certificate') {
        // 上传证书
        if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['certificate'];

            // 使用绝对路径确保目录存在
            $absoluteCertPath = '../' . CERTIFICATES_PATH;
            if (!is_dir($absoluteCertPath)) {
                mkdir($absoluteCertPath, 0777, true);
            }
            chmod($absoluteCertPath, 0777);

            // 验证文件类型
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($extension, $allowedTypes)) {
                $errors[] = '只允许上传 JPG、PNG、GIF、WebP 格式的图片';
            } elseif ($file['size'] > MAX_FILE_SIZE) {
                $errors[] = '文件大小不能超过 ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
            } elseif (count($certificates) >= MAX_CERTIFICATES) {
                $errors[] = '最多只能上传 ' . MAX_CERTIFICATES . ' 个证书';
            } else {
                // 生成新文件名
                $fileName = $_SESSION['reader_id'] . '_' . count($certificates) . '_' . md5(uniqid() . time()) . '.' . $extension;
                $targetPath = $absoluteCertPath . $fileName;
                $dbPath = CERTIFICATES_PATH . $fileName; // 数据库中保存的相对路径

                // 上传文件并进行优化
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // 设置文件权限
                    chmod($targetPath, 0644);

                    // 使用ImageOptimizer进行图片优化
                    try {
                        require_once '../includes/ImageOptimizer.php';
                        $optimizer = new ImageOptimizer('../' . CERTIFICATES_PATH);
                        $optimizeResult = $optimizer->processUploadedImage($targetPath, $fileName);
                        
                        if ($optimizeResult['success']) {
                            $success = '证书上传并优化成功！';
                        } else {
                            $success = '证书上传成功！（优化失败：' . $optimizeResult['error'] . '）';
                        }
                    } catch (Exception $e) {
                        $success = '证书上传成功！（优化功能不可用）';
                    }

                    // 添加到证书列表
                    $certificateInfo = [
                        'path' => $dbPath,
                        'name' => trim($_POST['certificate_name'] ?? '证书'),
                        'upload_time' => date('Y-m-d H:i:s')
                    ];
                    $certificates[] = $certificateInfo;

                    // 更新数据库
                    $updateResult = $db->update('readers', ['certificates' => json_encode($certificates)], 'id = ?', [$_SESSION['reader_id']]);

                    if ($updateResult) {
                        $reader = getReaderById($_SESSION['reader_id']);
                        $certificates = json_decode($reader['certificates'], true) ?: [];
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
            if (isset($_FILES['certificate'])) {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => '文件大小超过 upload_max_filesize',
                    UPLOAD_ERR_FORM_SIZE => '文件大小超过 MAX_FILE_SIZE',
                    UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
                    UPLOAD_ERR_NO_FILE => '没有文件被上传',
                    UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
                    UPLOAD_ERR_CANT_WRITE => '文件写入失败',
                    UPLOAD_ERR_EXTENSION => 'PHP扩展停止了文件上传'
                ];
                $errorCode = $_FILES['certificate']['error'];
                $errors[] = '文件上传错误: ' . ($uploadErrors[$errorCode] ?? "未知错误 ({$errorCode})");
            } else {
                $errors[] = '请选择要上传的证书图片';
            }
        }
    }
    
    elseif ($action === 'delete_certificate') {
        // 删除单个证书
        $index = (int)($_POST['certificate_index'] ?? -1);
        if ($index >= 0 && $index < count($certificates)) {
            $certificate = $certificates[$index];
            $filePath = '../' . $certificate['path'];
            
            // 删除文件
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // 删除优化版本
            $fileName = basename($certificate['path']);
            $sizes = ['thumb', 'small', 'medium', 'large', 'circle'];
            foreach ($sizes as $size) {
                $optimizedPath = '../uploads/certificates/optimized/' . $size . '/' . $fileName;
                $webpPath = '../uploads/certificates/webp/' . $size . '/' . str_replace(['.jpg', '.jpeg', '.png', '.gif'], '.webp', $fileName);
                if (file_exists($optimizedPath)) unlink($optimizedPath);
                if (file_exists($webpPath)) unlink($webpPath);
            }
            
            // 从数组中移除
            array_splice($certificates, $index, 1);
            
            // 更新数据库
            $updateResult = $db->update('readers', ['certificates' => json_encode($certificates)], 'id = ?', [$_SESSION['reader_id']]);
            
            if ($updateResult) {
                $success = '证书删除成功！';
                $reader = getReaderById($_SESSION['reader_id']);
                $certificates = json_decode($reader['certificates'], true) ?: [];
            } else {
                $errors[] = '数据库更新失败';
            }
        } else {
            $errors[] = '无效的证书索引';
        }
    }
    
    elseif ($action === 'delete_selected') {
        // 批量删除证书
        $selectedIndexes = $_POST['selected_certificates'] ?? [];
        if (!empty($selectedIndexes)) {
            // 按索引倒序排序，避免删除时索引变化
            rsort($selectedIndexes);
            $deletedCount = 0;
            
            foreach ($selectedIndexes as $index) {
                $index = (int)$index;
                if ($index >= 0 && $index < count($certificates)) {
                    $certificate = $certificates[$index];
                    $filePath = '../' . $certificate['path'];
                    
                    // 删除文件
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    
                    // 删除优化版本
                    $fileName = basename($certificate['path']);
                    $sizes = ['thumb', 'small', 'medium', 'large', 'circle'];
                    foreach ($sizes as $size) {
                        $optimizedPath = '../uploads/certificates/optimized/' . $size . '/' . $fileName;
                        $webpPath = '../uploads/certificates/webp/' . $size . '/' . str_replace(['.jpg', '.jpeg', '.png', '.gif'], '.webp', $fileName);
                        if (file_exists($optimizedPath)) unlink($optimizedPath);
                        if (file_exists($webpPath)) unlink($webpPath);
                    }
                    
                    // 从数组中移除
                    array_splice($certificates, $index, 1);
                    $deletedCount++;
                }
            }
            
            // 更新数据库
            $updateResult = $db->update('readers', ['certificates' => json_encode($certificates)], 'id = ?', [$_SESSION['reader_id']]);
            
            if ($updateResult) {
                $success = "成功删除 {$deletedCount} 个证书！";
                $reader = getReaderById($_SESSION['reader_id']);
                $certificates = json_decode($reader['certificates'], true) ?: [];
            } else {
                $errors[] = '数据库更新失败';
            }
        } else {
            $errors[] = '请选择要删除的证书';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>证书管理 - 占卜师后台</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/reader-new.css?v=<?php echo time(); ?>">
    <style>
        .certificates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .certificate-item {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .certificate-item:hover {
            border-color: #d4af37;
            box-shadow: 0 4px 20px rgba(212, 175, 55, 0.2);
        }
        
        .certificate-item.selected {
            border-color: #d4af37;
            background: #fffbf0;
        }
        
        .certificate-image {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .certificate-info {
            text-align: center;
        }
        
        .certificate-name {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .certificate-time {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .certificate-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 4px;
        }
        
        .batch-actions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            display: none;
        }
        
        .batch-actions.show {
            display: block;
        }
        
        .select-all-container {
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .certificates-grid {
                grid-template-columns: 1fr;
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
            <h1>🏆 证书管理</h1>
            
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
            
            <!-- 上传新证书 -->
            <div class="card">
                <div class="card-header">
                    <h2>上传新证书</h2>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_certificate">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="certificate_name">证书名称</label>
                                <input type="text" id="certificate_name" name="certificate_name" 
                                       placeholder="例如：塔罗师资格证书" maxlength="50">
                            </div>
                            
                            <div class="form-group">
                                <label for="certificate">选择证书图片</label>
                                <input type="file" id="certificate" name="certificate" accept="image/*" required>
                                <small>支持格式：JPG、PNG、GIF、WebP，最大<?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB</small>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">上传证书</button>
                        <p style="color: #666; font-size: 14px; margin-top: 10px;">
                            最多可上传 <?php echo MAX_CERTIFICATES; ?> 个证书，当前已上传 <?php echo count($certificates); ?> 个
                        </p>
                    </form>
                </div>
            </div>

            <!-- 证书列表 -->
            <div class="card">
                <div class="card-header">
                    <h2>我的证书 (<?php echo count($certificates); ?>/<?php echo MAX_CERTIFICATES; ?>)</h2>
                    <?php if (!empty($certificates)): ?>
                        <div class="card-header-actions">
                            <button type="button" id="toggleSelectMode" class="btn btn-secondary btn-small">多选模式</button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($certificates)): ?>
                        <div class="no-data">
                            <p style="text-align: center; color: #666; padding: 40px;">
                                暂未上传任何证书<br>
                                <small>上传您的专业证书，提升客户信任度</small>
                            </p>
                        </div>
                    <?php else: ?>
                        <!-- 批量操作区域 -->
                        <div class="batch-actions" id="batchActions">
                            <div class="select-all-container">
                                <label>
                                    <input type="checkbox" id="selectAll"> 全选
                                </label>
                                <span id="selectedCount" style="margin-left: 15px; color: #666;">已选择 0 个证书</span>
                            </div>
                            <form method="POST" onsubmit="return confirmBatchDelete()">
                                <input type="hidden" name="action" value="delete_selected">
                                <div id="selectedCertificatesInput"></div>
                                <button type="submit" class="btn btn-danger btn-small">删除选中的证书</button>
                                <button type="button" id="cancelSelect" class="btn btn-secondary btn-small">取消</button>
                            </form>
                        </div>

                        <!-- 证书网格 -->
                        <div class="certificates-grid">
                            <?php foreach ($certificates as $index => $certificate): ?>
                                <div class="certificate-item" data-index="<?php echo $index; ?>">
                                    <div class="certificate-checkbox" style="display: none;">
                                        <input type="checkbox" class="certificate-select" value="<?php echo $index; ?>">
                                    </div>

                                    <?php
                                    // 获取优化后的图片URL
                                    $certPath = $certificate['path'];
                                    $displayPath = '../' . $certPath;

                                    // 尝试使用优化后的图片
                                    $optimizedImageUrl = null;
                                    try {
                                        require_once '../includes/ImageOptimizer.php';
                                        $optimizer = new ImageOptimizer('../' . CERTIFICATES_PATH);
                                        $fileName = basename($certPath);
                                        $optimizedImageUrl = $optimizer->getOptimizedImageUrl($fileName, 'medium', true);
                                        // 转换为相对于当前页面的路径
                                        if ($optimizedImageUrl && !str_starts_with($optimizedImageUrl, '../')) {
                                            $optimizedImageUrl = '../' . $optimizedImageUrl;
                                        }
                                    } catch (Exception $e) {
                                        // 如果优化功能不可用，使用原图
                                    }

                                    $finalImagePath = $optimizedImageUrl && file_exists($optimizedImageUrl) ? $optimizedImageUrl : $displayPath;
                                    ?>

                                    <?php if (file_exists($finalImagePath)): ?>
                                        <img src="<?php echo h($finalImagePath); ?>" alt="<?php echo h($certificate['name']); ?>"
                                             class="certificate-image" onclick="openImageModal('<?php echo h($finalImagePath); ?>', '<?php echo h($certificate['name']); ?>')">
                                    <?php else: ?>
                                        <div style="height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; margin-bottom: 10px;">
                                            <span style="color: #999;">图片不存在</span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="certificate-info">
                                        <div class="certificate-name"><?php echo h($certificate['name']); ?></div>
                                        <div class="certificate-time">上传时间：<?php echo date('Y-m-d', strtotime($certificate['upload_time'])); ?></div>
                                        <div class="certificate-actions">
                                            <form method="POST" style="display: inline;" onsubmit="return confirmDelete('<?php echo h($certificate['name']); ?>')">
                                                <input type="hidden" name="action" value="delete_certificate">
                                                <input type="hidden" name="certificate_index" value="<?php echo $index; ?>">
                                                <button type="submit" class="btn btn-danger btn-small">删除</button>
                                            </form>
                                        </div>
                                        <?php if ($optimizedImageUrl): ?>
                                            <div style="font-size: 11px; color: #666; margin-top: 5px;">已优化</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- 图片查看模态框 -->
    <div id="imageModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; cursor: pointer;" onclick="closeImageModal()">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 90%; max-height: 90%;">
            <img id="modalImage" style="max-width: 100%; max-height: 100%; border-radius: 10px;">
            <div id="modalTitle" style="color: white; text-align: center; margin-top: 10px; font-size: 18px;"></div>
        </div>
        <div style="position: absolute; top: 20px; right: 30px; color: white; font-size: 30px; cursor: pointer;" onclick="closeImageModal()">&times;</div>
    </div>

    <footer class="reader-footer">
        <div class="container">
            <p>&copy; 2024 占卜师展示平台. 保留所有权利.</p>
        </div>
    </footer>

    <script>
        // 多选模式
        let selectMode = false;
        const toggleSelectBtn = document.getElementById('toggleSelectMode');
        const batchActions = document.getElementById('batchActions');
        const selectAllCheckbox = document.getElementById('selectAll');
        const selectedCountSpan = document.getElementById('selectedCount');
        const selectedInput = document.getElementById('selectedCertificatesInput');
        const cancelSelectBtn = document.getElementById('cancelSelect');

        if (toggleSelectBtn) {
            toggleSelectBtn.addEventListener('click', function() {
                selectMode = !selectMode;
                toggleSelectMode();
            });
        }

        if (cancelSelectBtn) {
            cancelSelectBtn.addEventListener('click', function() {
                selectMode = false;
                toggleSelectMode();
            });
        }

        function toggleSelectMode() {
            const checkboxes = document.querySelectorAll('.certificate-checkbox');
            const certificateSelects = document.querySelectorAll('.certificate-select');

            if (selectMode) {
                toggleSelectBtn.textContent = '退出多选';
                batchActions.classList.add('show');
                checkboxes.forEach(cb => cb.style.display = 'block');
            } else {
                toggleSelectBtn.textContent = '多选模式';
                batchActions.classList.remove('show');
                checkboxes.forEach(cb => cb.style.display = 'none');
                certificateSelects.forEach(cs => cs.checked = false);
                selectAllCheckbox.checked = false;
                updateSelectedCount();
            }
        }

        // 全选功能
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const certificateSelects = document.querySelectorAll('.certificate-select');
                certificateSelects.forEach(cs => cs.checked = this.checked);
                updateSelectedCount();
            });
        }

        // 单选功能
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('certificate-select')) {
                updateSelectedCount();

                // 更新全选状态
                const certificateSelects = document.querySelectorAll('.certificate-select');
                const checkedCount = document.querySelectorAll('.certificate-select:checked').length;
                selectAllCheckbox.checked = checkedCount === certificateSelects.length;
            }
        });

        function updateSelectedCount() {
            const checkedBoxes = document.querySelectorAll('.certificate-select:checked');
            selectedCountSpan.textContent = `已选择 ${checkedBoxes.length} 个证书`;

            // 更新隐藏输入
            selectedInput.innerHTML = '';
            checkedBoxes.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_certificates[]';
                input.value = cb.value;
                selectedInput.appendChild(input);
            });
        }

        // 确认删除
        function confirmDelete(name) {
            return confirm(`确定要删除证书"${name}"吗？此操作不可撤销。`);
        }

        function confirmBatchDelete() {
            const checkedCount = document.querySelectorAll('.certificate-select:checked').length;
            if (checkedCount === 0) {
                alert('请选择要删除的证书');
                return false;
            }
            return confirm(`确定要删除选中的 ${checkedCount} 个证书吗？此操作不可撤销。`);
        }

        // 图片查看模态框
        function openImageModal(src, title) {
            document.getElementById('modalImage').src = src;
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('imageModal').style.display = 'block';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // ESC键关闭模态框
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });
    </script>

</body>
</html>
