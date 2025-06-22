<?php
session_start();
require_once 'config/config.php';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>西玄课程 - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="page-header">
                <h1>西玄课程</h1>
                <p>探索神秘学的奥秘，开启心灵成长之旅</p>
            </div>
            
            <div class="coming-soon">
                <div class="coming-soon-content">
                    <h2>🔮 课程即将上线</h2>
                    <p>我们正在精心准备丰富的西玄课程内容，包括：</p>
                    
                    <div class="course-preview">
                        <div class="course-item">
                            <h3>📚 塔罗基础课程</h3>
                            <p>从零开始学习塔罗牌的基本知识和解读技巧</p>
                        </div>
                        
                        <div class="course-item">
                            <h3>🌟 进阶占卜技法</h3>
                            <p>深入学习各种占卜方法和高级解读技巧</p>
                        </div>
                        
                        <div class="course-item">
                            <h3>🧘 冥想与直觉开发</h3>
                            <p>培养内在直觉，提升灵性感知能力</p>
                        </div>
                        
                        <div class="course-item">
                            <h3>🔯 神秘学理论</h3>
                            <p>学习占星学、数字学等神秘学知识体系</p>
                        </div>
                    </div>
                    
                    <div class="notify-section">
                        <h3>📧 课程上线通知</h3>
                        <p>留下您的邮箱，我们会在课程上线时第一时间通知您</p>
                        <form class="notify-form">
                            <input type="email" placeholder="请输入您的邮箱地址" required>
                            <button type="submit" class="btn btn-primary">订阅通知</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <style>
        .coming-soon {
            text-align: center;
            padding: 60px 20px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            margin: 40px 0;
        }
        
        .coming-soon-content h2 {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .course-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin: 40px 0;
        }
        
        .course-item {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .course-item:hover {
            transform: translateY(-5px);
        }
        
        .course-item h3 {
            color: #d4af37;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        .notify-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-top: 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .notify-form {
            display: flex;
            gap: 15px;
            max-width: 400px;
            margin: 20px auto 0;
        }
        
        .notify-form input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 25px;
            font-size: 14px;
        }
        
        .notify-form input:focus {
            outline: none;
            border-color: #d4af37;
        }
        
        @media (max-width: 768px) {
            .notify-form {
                flex-direction: column;
            }
            
            .course-preview {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
    </style>
</body>
</html>
