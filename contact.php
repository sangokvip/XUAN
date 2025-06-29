<?php
session_start();
require_once 'config/config.php';

$success = '';
$errors = [];
$db = Database::getInstance();

// 处理联系表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // 验证表单
    if (empty($name)) {
        $errors[] = '请输入您的姓名';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '请输入有效的邮箱地址';
    }

    if (empty($subject)) {
        $errors[] = '请输入主题';
    }

    if (empty($message)) {
        $errors[] = '请输入留言内容';
    }

    if (empty($errors)) {
        try {
            // 保存留言到数据库
            $stmt = $db->prepare("
                INSERT INTO contact_messages (name, email, subject, message, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $result = $stmt->execute([$name, $email, $subject, $message, $ipAddress, $userAgent]);

            if ($result) {
                $success = '感谢您的留言！我们会在24小时内回复您。';
                // 清空表单
                $name = $email = $subject = $message = '';
            } else {
                $errors[] = '留言发送失败，请稍后重试';
            }
        } catch (Exception $e) {
            $errors[] = '系统错误，请稍后重试';
            error_log('Contact form error: ' . $e->getMessage());
        }
    }
}

// 获取联系方式设置
$contactSettings = [
    'email_1' => getSetting('contact_email_1', 'info@example.com'),
    'email_2' => getSetting('contact_email_2', 'support@example.com'),
    'wechat' => getSetting('contact_wechat', 'mystical_service'),
    'wechat_hours' => getSetting('contact_wechat_hours', '9:00-21:00'),
    'qq_group_1' => getSetting('contact_qq_group_1', '123456789'),
    'qq_group_2' => getSetting('contact_qq_group_2', '987654321'),
    'xiaohongshu' => getSetting('contact_xiaohongshu', '@神秘学园'),
    'xiaohongshu_desc' => getSetting('contact_xiaohongshu_desc', '每日分享占卜知识'),
    'phone' => getSetting('contact_phone', ''),
    'address' => getSetting('contact_address', ''),
    'business_hours' => getSetting('contact_business_hours', '周一至周日 9:00-21:00'),
    'notice' => getSetting('contact_notice', '我们会在24小时内回复您的留言')
];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>联系我们 - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="page-header">
                <h1>联系我们</h1>
                <p>有任何问题或建议，欢迎与我们联系</p>
            </div>
            
            <div class="contact-content">
                <div class="contact-info-section">
                    <h2>📞 联系方式</h2>
                    
                    <div class="contact-cards">
                        <div class="contact-card">
                            <div class="contact-icon">📧</div>
                            <h3>邮箱联系</h3>
                            <p><?php echo h($contactSettings['email_1']); ?></p>
                            <p><?php echo h($contactSettings['email_2']); ?></p>
                        </div>

                        <div class="contact-card">
                            <div class="contact-icon">💬</div>
                            <h3>微信客服</h3>
                            <p>微信号：<?php echo h($contactSettings['wechat']); ?></p>
                            <p>工作时间：<?php echo h($contactSettings['wechat_hours']); ?></p>
                        </div>

                        <div class="contact-card">
                            <div class="contact-icon">📱</div>
                            <h3>QQ群</h3>
                            <p>官方交流群：<?php echo h($contactSettings['qq_group_1']); ?></p>
                            <p>新手学习群：<?php echo h($contactSettings['qq_group_2']); ?></p>
                        </div>

                        <div class="contact-card">
                            <div class="contact-icon">📍</div>
                            <h3>小红书</h3>
                            <p><?php echo h($contactSettings['xiaohongshu']); ?></p>
                            <p><?php echo h($contactSettings['xiaohongshu_desc']); ?></p>
                        </div>

                        <?php if (!empty($contactSettings['phone'])): ?>
                        <div class="contact-card">
                            <div class="contact-icon">📞</div>
                            <h3>电话联系</h3>
                            <p><?php echo h($contactSettings['phone']); ?></p>
                            <p><?php echo h($contactSettings['business_hours']); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($contactSettings['address'])): ?>
                        <div class="contact-card">
                            <div class="contact-icon">🏢</div>
                            <h3>联系地址</h3>
                            <p><?php echo h($contactSettings['address']); ?></p>
                            <p><?php echo h($contactSettings['business_hours']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="contact-form-section">
                    <h2>💌 在线留言</h2>
                    
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
                            <br><small><?php echo h($contactSettings['notice']); ?></small>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="contact-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">姓名 *</label>
                                <input type="text" id="name" name="name" required 
                                       value="<?php echo h($name ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">邮箱 *</label>
                                <input type="email" id="email" name="email" required 
                                       value="<?php echo h($email ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">主题 *</label>
                            <select id="subject" name="subject" required>
                                <option value="">请选择主题</option>
                                <option value="占卜咨询" <?php echo ($subject ?? '') === '占卜咨询' ? 'selected' : ''; ?>>占卜咨询</option>
                                <option value="课程咨询" <?php echo ($subject ?? '') === '课程咨询' ? 'selected' : ''; ?>>课程咨询</option>
                                <option value="产品咨询" <?php echo ($subject ?? '') === '产品咨询' ? 'selected' : ''; ?>>产品咨询</option>
                                <option value="技术支持" <?php echo ($subject ?? '') === '技术支持' ? 'selected' : ''; ?>>技术支持</option>
                                <option value="合作洽谈" <?php echo ($subject ?? '') === '合作洽谈' ? 'selected' : ''; ?>>合作洽谈</option>
                                <option value="其他" <?php echo ($subject ?? '') === '其他' ? 'selected' : ''; ?>>其他</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">留言内容 *</label>
                            <textarea id="message" name="message" rows="6" required 
                                      placeholder="请详细描述您的问题或建议..."><?php echo h($message ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">发送留言</button>
                        </div>
                    </form>
                </div>
                
                <div class="faq-section">
                    <h2>❓ 常见问题</h2>
                    
                    <div class="faq-list">
                        <div class="faq-item">
                            <h3>如何选择合适的占卜师？</h3>
                            <p>您可以根据占卜师的专长、从业年数、用户评价等信息来选择。建议先查看占卜师的详细介绍，了解其擅长的占卜方向是否符合您的需求。</p>
                        </div>
                        
                        <div class="faq-item">
                            <h3>占卜咨询的费用如何？</h3>
                            <p>每位占卜师的收费标准不同，您可以在占卜师的个人页面查看具体的价格信息。我们建议在咨询前先了解清楚费用和服务内容。</p>
                        </div>
                        
                        <div class="faq-item">
                            <h3>如何成为平台的占卜师？</h3>
                            <p>如果您是专业的占卜师，可以通过我们的注册页面申请加入。我们会对申请者的资质进行审核，确保为用户提供优质的服务。</p>
                        </div>
                        
                        <div class="faq-item">
                            <h3>平台是否提供学习课程？</h3>
                            <p>我们正在筹备丰富的神秘学课程，包括塔罗、占星、数字学等内容。课程将很快上线，敬请期待！</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <style>
        .contact-content {
            margin: 40px 0;
        }
        
        .contact-info-section {
            margin-bottom: 60px;
        }
        
        .contact-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .contact-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .contact-card:hover {
            transform: translateY(-5px);
        }
        
        .contact-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .contact-card h3 {
            color: #d4af37;
            margin-bottom: 15px;
        }
        
        .contact-form-section {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 60px;
        }
        
        .contact-form-section h2 {
            margin-bottom: 30px;
            color: #2c3e50;
        }
        
        .faq-section {
            background: #f8f9fa;
            padding: 40px;
            border-radius: 10px;
        }
        
        .faq-section h2 {
            margin-bottom: 30px;
            color: #2c3e50;
        }
        
        .faq-list {
            display: grid;
            gap: 20px;
        }
        
        .faq-item {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .faq-item h3 {
            color: #d4af37;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        
        .faq-item p {
            line-height: 1.6;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .contact-cards {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .contact-form-section,
            .faq-section {
                padding: 25px;
            }
        }
    </style>
</body>
</html>
