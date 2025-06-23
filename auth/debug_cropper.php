<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图片剪裁调试页面</title>
    <link rel="stylesheet" href="../assets/css/image-cropper.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .debug-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            border-left: 4px solid #ccc;
        }
        .status-ok {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .status-error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .status-warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        .test-section {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .preview-area {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .preview-item {
            text-align: center;
        }
        .preview-item img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border: 2px solid #ddd;
            border-radius: 10px;
        }
        .preview-item.circle img {
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <div class="debug-container">
        <h1>图片剪裁工具调试页面</h1>
        
        <h2>系统状态检查</h2>
        <div id="status-checks">
            <div class="status-item" id="status-js">检查中...</div>
            <div class="status-item" id="status-css">检查中...</div>
            <div class="status-item" id="status-cropper">检查中...</div>
            <div class="status-item" id="status-simple">检查中...</div>
        </div>
        
        <div class="test-section">
            <h3>功能测试</h3>
            <input type="file" id="test-file" accept="image/*">
            <button class="btn" onclick="testMainCropper()">测试主剪裁工具</button>
            <button class="btn" onclick="testSimpleCropper()">测试简化剪裁工具</button>
            <button class="btn" onclick="testBothCroppers()">测试两个工具</button>
            
            <div class="preview-area" id="test-results" style="display: none;">
                <div class="preview-item">
                    <img id="original-img" src="" alt="原始图片">
                    <p>原始图片</p>
                </div>
                <div class="preview-item circle">
                    <img id="main-result" src="" alt="主工具结果">
                    <p>主工具结果</p>
                </div>
                <div class="preview-item circle">
                    <img id="simple-result" src="" alt="简化工具结果">
                    <p>简化工具结果</p>
                </div>
            </div>
        </div>
        
        <div class="test-section">
            <h3>控制台日志</h3>
            <div id="console-log" style="background: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;">
                等待操作...
            </div>
        </div>
    </div>

    <script src="../assets/js/image-cropper.js"></script>
    <script src="../assets/js/simple-cropper.js"></script>
    <script>
        const consoleLog = document.getElementById('console-log');
        
        function log(message, type = 'info') {
            const time = new Date().toLocaleTimeString();
            const color = type === 'error' ? 'red' : type === 'success' ? 'green' : 'black';
            consoleLog.innerHTML += `<div style="color: ${color}">[${time}] ${message}</div>`;
            consoleLog.scrollTop = consoleLog.scrollHeight;
        }
        
        function setStatus(id, message, type) {
            const element = document.getElementById(id);
            element.textContent = message;
            element.className = `status-item status-${type}`;
        }
        
        // 检查系统状态
        document.addEventListener('DOMContentLoaded', function() {
            log('页面加载完成');
            
            // 检查JavaScript文件
            try {
                if (typeof ImageCropper !== 'undefined') {
                    setStatus('status-js', '✅ image-cropper.js 已加载', 'ok');
                } else {
                    setStatus('status-js', '❌ image-cropper.js 未加载', 'error');
                }
            } catch (e) {
                setStatus('status-js', '❌ image-cropper.js 加载错误: ' + e.message, 'error');
            }
            
            // 检查CSS文件
            const cssLoaded = Array.from(document.styleSheets).some(sheet => 
                sheet.href && sheet.href.includes('image-cropper.css')
            );
            if (cssLoaded) {
                setStatus('status-css', '✅ image-cropper.css 已加载', 'ok');
            } else {
                setStatus('status-css', '⚠️ image-cropper.css 可能未加载', 'warning');
            }
            
            // 检查主剪裁工具
            setTimeout(() => {
                if (window.imageCropper) {
                    setStatus('status-cropper', '✅ 主图片剪裁工具已初始化', 'ok');
                } else {
                    setStatus('status-cropper', '❌ 主图片剪裁工具未初始化', 'error');
                }
                
                if (window.simpleCropper) {
                    setStatus('status-simple', '✅ 简化图片剪裁工具已初始化', 'ok');
                } else {
                    setStatus('status-simple', '❌ 简化图片剪裁工具未初始化', 'error');
                }
            }, 500);
        });
        
        let currentFile = null;
        
        document.getElementById('test-file').addEventListener('change', function(e) {
            currentFile = e.target.files[0];
            if (currentFile) {
                log(`选择文件: ${currentFile.name}`);
                
                // 显示原始图片
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('original-img').src = e.target.result;
                    document.getElementById('test-results').style.display = 'flex';
                };
                reader.readAsDataURL(currentFile);
            }
        });
        
        function testMainCropper() {
            if (!currentFile) {
                alert('请先选择一个图片文件');
                return;
            }
            
            log('测试主剪裁工具...');
            
            if (!window.imageCropper) {
                log('主剪裁工具不可用', 'error');
                return;
            }
            
            window.imageCropper.show(currentFile)
                .then(blob => {
                    log('主剪裁工具成功', 'success');
                    const reader = new FileReader();
                    reader.onload = e => {
                        document.getElementById('main-result').src = e.target.result;
                    };
                    reader.readAsDataURL(blob);
                })
                .catch(error => {
                    log(`主剪裁工具失败: ${error}`, 'error');
                });
        }
        
        function testSimpleCropper() {
            if (!currentFile) {
                alert('请先选择一个图片文件');
                return;
            }
            
            log('测试简化剪裁工具...');
            
            if (!window.simpleCropper) {
                log('简化剪裁工具不可用', 'error');
                return;
            }
            
            window.simpleCropper.cropToCircle(currentFile)
                .then(blob => {
                    log('简化剪裁工具成功', 'success');
                    const reader = new FileReader();
                    reader.onload = e => {
                        document.getElementById('simple-result').src = e.target.result;
                    };
                    reader.readAsDataURL(blob);
                })
                .catch(error => {
                    log(`简化剪裁工具失败: ${error}`, 'error');
                });
        }
        
        function testBothCroppers() {
            testMainCropper();
            setTimeout(() => testSimpleCropper(), 1000);
        }
    </script>
</body>
</html>
