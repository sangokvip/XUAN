<?php
session_start();
require_once 'config/config.php';
require_once 'includes/TataCoinManager.php';

// 获取塔罗师ID
$readerId = (int)($_GET['id'] ?? 0);

if (!$readerId) {
    redirect('readers.php');
}

// 获取塔罗师信息
$reader = getReaderById($readerId);

if (!$reader) {
    redirect('readers.php');
}

$tataCoinManager = new TataCoinManager();

// 检查用户是否已登录
$user = null;
$canViewContact = false;
$hasViewedContact = false;
$isAdmin = false;
$userTataCoinBalance = 0;
$contactCost = 0;
$paymentError = '';
$paymentSuccess = '';

// 检查管理员登录状态
if (isset($_SESSION['admin_id'])) {
    $isAdmin = true;
    $canViewContact = true;
    $hasViewedContact = true; // 管理员默认已查看
} elseif (isset($_SESSION['user_id'])) {
    $user = getUserById($_SESSION['user_id']);
    $userTataCoinBalance = $tataCoinManager->getBalance($_SESSION['user_id'], 'user');
    $canViewContact = true;

    // 检查是否已经付费查看过
    $db = Database::getInstance();
    $existingRecord = $db->fetchOne(
        "SELECT * FROM user_browse_history WHERE user_id = ? AND reader_id = ? AND browse_type = 'paid'",
        [$_SESSION['user_id'], $readerId]
    );
    $hasViewedContact = (bool)$existingRecord;

    // 确定查看联系方式的费用
    $contactCost = $reader['is_featured'] ? 30 : 10; // 推荐塔罗师30，普通塔罗师10
}

// 处理查看联系方式请求
$showContact = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['view_contact']) && $canViewContact) {
    if ($isAdmin) {
        // 管理员直接显示联系方式，不记录查看记录
        $showContact = true;
    } elseif ($hasViewedContact) {
        // 已经付费查看过，直接显示
        $showContact = true;
    } elseif (isset($_SESSION['user_id'])) {
        // 需要付费查看
        try {
            $result = $tataCoinManager->viewReaderContact($_SESSION['user_id'], $readerId);
            if ($result['success']) {
                $hasViewedContact = true;
                $showContact = true;
                if (!$result['already_paid']) {
                    $paymentSuccess = "成功支付 {$result['cost']} 个Tata Coin，塔罗师获得 {$result['reader_earning']} 个Tata Coin分成";
                    $userTataCoinBalance = $tataCoinManager->getBalance($_SESSION['user_id'], 'user');
                }
            }
        } catch (Exception $e) {
            $paymentError = $e->getMessage();
        }
    }
}

// 记录免费浏览（如果用户已登录但没有查看联系方式）
if (isset($_SESSION['user_id']) && !$showContact && !$hasViewedContact) {
    try {
        $tataCoinManager->recordFreeBrowse($_SESSION['user_id'], $readerId);
    } catch (Exception $e) {
        // 忽略记录失败
    }
}

// 更新页面查看次数（每次访问都增加）
$db = Database::getInstance();

// 更新readers表中的view_count字段
$db->query("UPDATE readers SET view_count = COALESCE(view_count, 0) + 1 WHERE id = ?", [$readerId]);

// 获取更新后的查看次数
$readerData = $db->fetchOne("SELECT view_count FROM readers WHERE id = ?", [$readerId]);
$totalViews = $readerData['view_count'] ?? 0;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($reader['full_name']); ?> - 塔罗师详情</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* 强制修复塔罗师照片显示 - 完整显示图片 */
        .reader-photo {
            height: 250px !important;
            overflow: hidden !important;
            position: relative !important;
            background: #f8f9fa !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .reader-photo img {
            max-width: 100% !important;
            max-height: 100% !important;
            width: auto !important;
            height: auto !important;
            object-fit: contain !important;
            transition: transform 0.3s ease !important;
        }

        .reader-photo-large {
            max-width: 100% !important;
            max-height: 400px !important;
            width: auto !important;
            height: auto !important;
            object-fit: contain !important;
            border-radius: 15px !important;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important;
        }

        .default-photo {
            width: calc(100% - 20px) !important;
            height: calc(100% - 20px) !important;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 48px !important;
            color: #6c757d !important;
            border: 2px dashed #d4af37 !important;
            border-radius: 10px !important;
            margin: 10px !important;
            box-sizing: border-box !important;
        }

        .default-photo-large {
            width: 300px !important;
            height: 400px !important;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 80px !important;
            color: #6c757d !important;
            border: 2px dashed #d4af37 !important;
            border-radius: 15px !important;
            box-sizing: border-box !important;
        }

        .price-list-image img {
            max-width: 100% !important;
            height: auto !important;
            object-fit: contain !important;
            border-radius: 10px !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
        }

        /* 联系方式样式 */
        .contact-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 15px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            border-left: 4px solid #d4af37;
            transition: all 0.3s ease;
        }

        .contact-item:hover {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.2);
        }

        .contact-icon {
            font-size: 18px;
            min-width: 20px;
        }

        .contact-value {
            color: #333;
            font-weight: 500;
            word-break: break-all;
        }

        .contact-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #d4af37;
        }

        .contact-details h3 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .contact-text {
            color: #666;
            line-height: 1.6;
        }

        .contact-note {
            background: linear-gradient(135deg, #e7f3ff, #cce7ff);
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #007bff;
        }

        .contact-note p {
            margin: 0;
            color: #004085;
        }

        @media (max-width: 768px) {
            .contact-methods {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .contact-item {
                padding: 10px 12px;
            }
        }

        /* 管理员模式横幅 */
        .admin-mode-banner {
            background: linear-gradient(135deg, #d4af37, #f1c40f);
            color: #1a1a1a;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }

        .admin-banner-content {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .admin-icon {
            font-size: 1.5rem;
        }

        .admin-text {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .admin-note {
            color: #2c3e50;
            flex: 1;
            min-width: 200px;
        }

        .admin-link {
            background: rgba(26, 26, 26, 0.1);
            color: #1a1a1a;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid rgba(26, 26, 26, 0.2);
        }

        .admin-link:hover {
            background: rgba(26, 26, 26, 0.2);
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .admin-banner-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .admin-note {
                min-width: auto;
            }
        }

        /* 专长标签样式 */
        .specialties-section {
            margin-bottom: 30px;
        }

        .specialties-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .specialty-tags-detail {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .specialty-tag-detail {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .specialty-tag-detail.system-tag {
            border: 1px solid transparent;
        }

        /* 系统标签的特定颜色 */
        .specialty-tag-detail.system-tag[data-specialty="感情"] {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            color: white;
            border-color: #ff6b6b;
        }

        .specialty-tag-detail.system-tag[data-specialty="桃花"] {
            background: linear-gradient(135deg, #ff69b4, #ff91d4);
            color: white;
            border-color: #ff69b4;
        }

        .specialty-tag-detail.system-tag[data-specialty="财运"] {
            background: linear-gradient(135deg, #d4af37, #ffd700);
            color: #000;
            border-color: #d4af37;
        }

        .specialty-tag-detail.system-tag[data-specialty="事业"] {
            background: linear-gradient(135deg, #28a745, #5cb85c);
            color: white;
            border-color: #28a745;
        }

        .specialty-tag-detail.system-tag[data-specialty="运势"] {
            background: linear-gradient(135deg, #ff8c00, #ffa500);
            color: white;
            border-color: #ff8c00;
        }

        .specialty-tag-detail.system-tag[data-specialty="学业"] {
            background: linear-gradient(135deg, #007bff, #4dabf7);
            color: white;
            border-color: #007bff;
        }

        .specialty-tag-detail.system-tag[data-specialty="寻物"] {
            background: linear-gradient(135deg, #6f42c1, #8e44ad);
            color: white;
            border-color: #6f42c1;
        }

        .specialty-tag-detail.custom-tag {
            background: linear-gradient(135deg, #6c757d, #868e96);
            color: white;
            border: 1px solid #6c757d;
        }

        .specialty-tag-detail:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        /* Tata Coin付费界面样式 */
        .contact-payment-section {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 20px;
            border: 2px solid #e5e7eb;
        }

        .user-balance-info {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #d1d5db;
        }

        .balance-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .balance-label {
            font-weight: 600;
            color: #374151;
        }

        .balance-amount {
            font-size: 1.2rem;
            font-weight: 700;
            color: #f59e0b;
        }

        .insufficient-balance {
            background: #fee2e2;
            color: #991b1b;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #fecaca;
        }

        .payment-info {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid #d1d5db;
        }

        .payment-info h3 {
            margin: 0 0 15px 0;
            color: #1f2937;
            font-size: 1.3rem;
        }

        .cost-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            text-align: center;
            margin: 15px 0;
        }

        .cost-amount {
            font-size: 1.4rem;
            font-weight: 700;
            display: block;
        }

        .cost-type {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .cost-note {
            font-size: 0.9rem;
            color: #6b7280;
            text-align: center;
            margin: 10px 0;
        }

        .payment-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .payment-success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
            color: white;
        }

        .insufficient-funds {
            text-align: center;
            padding: 20px;
            background: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 12px;
            color: #92400e;
        }



        @media (max-width: 768px) {
            .specialty-tags-detail {
                gap: 6px;
            }

            .specialty-tag-detail {
                font-size: 12px;
                padding: 4px 8px;
            }

            .contact-payment-section {
                padding: 20px 15px;
            }

            .user-balance-info,
            .payment-info {
                padding: 15px;
            }

            .balance-display {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <?php if ($isAdmin): ?>
                <div class="admin-mode-banner">
                    <div class="admin-banner-content">
                        <span class="admin-icon">👑</span>
                        <span class="admin-text">管理员模式</span>
                        <span class="admin-note">您正以管理员身份浏览，可查看所有联系方式</span>
                        <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="admin-link">返回后台</a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="reader-detail">
                <!-- 返回按钮 -->
                <div class="back-link">
                    <a href="<?php echo SITE_URL; ?>/readers.php" class="btn btn-secondary">← 返回塔罗师列表</a>
                </div>
                
                <div class="reader-profile">
                    <div class="reader-photo-section">
                        <?php if (!empty($reader['photo'])): ?>
                            <img src="<?php echo h($reader['photo']); ?>" alt="<?php echo h($reader['full_name']); ?>" class="reader-photo-large">
                        <?php else: ?>
                            <div class="default-photo-large">
                                <i class="icon-user"></i>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($reader['is_featured']): ?>
                            <div class="featured-badge-large">推荐塔罗师</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="reader-info-section">
                        <h1><?php echo h($reader['full_name']); ?></h1>
                        
                        <div class="reader-meta-large">
                            <div class="meta-item">
                                <strong>从业年数：</strong><?php echo h($reader['experience_years']); ?> 年
                            </div>
                            <div class="meta-item">
                                <strong>查看次数：</strong><?php echo $totalViews; ?> 次
                            </div>
                            <div class="meta-item">
                                <strong>注册时间：</strong><?php echo date('Y年m月', strtotime($reader['created_at'])); ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($reader['specialties'])): ?>
                            <div class="specialties-section">
                                <h3>擅长方向</h3>
                                <div class="specialty-tags-detail">
                                    <?php
                                    $systemSpecialties = ['感情', '学业', '桃花', '财运', '事业', '运势', '寻物'];
                                    $specialties = explode('、', $reader['specialties']);
                                    foreach ($specialties as $specialtyItem):
                                        $specialtyItem = trim($specialtyItem);
                                        if (!empty($specialtyItem)):
                                            $isSystemTag = in_array($specialtyItem, $systemSpecialties);
                                    ?>
                                        <a href="tag_readers.php?tag=<?php echo urlencode($specialtyItem); ?>"
                                           class="specialty-tag-detail <?php echo $isSystemTag ? 'system-tag' : 'custom-tag'; ?>"
                                           <?php if ($isSystemTag): ?>data-specialty="<?php echo h($specialtyItem); ?>"<?php endif; ?>
                                           style="text-decoration: none; color: inherit;">
                                            <?php echo h($specialtyItem); ?>
                                        </a>
                                    <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($reader['description'])): ?>
                            <div class="description-section">
                                <h3>个人简介</h3>
                                <p><?php echo nl2br(h($reader['description'])); ?></p>
                            </div>
                        <?php endif; ?>


                    </div>
                </div>
                
                <!-- 价格列表 -->
                <?php if (!empty($reader['price_list_image'])): ?>
                    <div class="price-list-section">
                        <h2>服务价格</h2>
                        <div class="price-list-image">
                            <img src="<?php echo h($reader['price_list_image']); ?>" alt="价格列表">
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- 联系方式 -->
                <div class="contact-section">
                    <h2>联系方式</h2>

                    <?php if (!$canViewContact): ?>
                        <div class="login-required">
                            <p>查看塔罗师联系方式需要先登录</p>
                            <a href="<?php echo SITE_URL; ?>/auth/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary">立即登录</a>
                            <a href="<?php echo SITE_URL; ?>/auth/register.php" class="btn btn-secondary">注册账户</a>
                        </div>
                    <?php elseif ($isAdmin): ?>
                        <!-- 管理员直接显示联系方式 -->
                        <div class="admin-contact-notice">
                            <p style="color: #d4af37; font-weight: 500; margin-bottom: 15px;">
                                <i class="icon-admin"></i> 管理员模式：可直接查看所有联系方式
                            </p>
                        </div>
                    <?php elseif (!$showContact): ?>
                        <!-- 显示付费提示和用户余额 -->
                        <div class="contact-payment-section">
                            <?php if ($paymentError): ?>
                                <div class="payment-error">
                                    <p style="color: #ef4444; font-weight: 500; margin-bottom: 15px;">
                                        ❌ <?php echo h($paymentError); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <div class="user-balance-info">
                                <div class="balance-display">
                                    <span class="balance-label">💰 我的Tata Coin：</span>
                                    <span class="balance-amount"><?php echo number_format($userTataCoinBalance); ?> 枚</span>
                                </div>
                                <?php if ($userTataCoinBalance < $contactCost): ?>
                                    <div class="insufficient-balance">
                                        <p style="color: #ef4444;">余额不足，需要 <?php echo $contactCost; ?> 个Tata Coin</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="contact-preview">
                                <div class="payment-info">
                                    <h3>💳 查看联系方式</h3>
                                    <p>查看 <?php echo h($reader['full_name']); ?> 的联系方式需要消耗：</p>
                                    <div class="cost-display">
                                        <span class="cost-amount"><?php echo $contactCost; ?> 个Tata Coin</span>
                                        <span class="cost-type"><?php echo $reader['is_featured'] ? '(推荐塔罗师)' : '(普通塔罗师)'; ?></span>
                                    </div>

                                </div>

                                <?php if ($hasViewedContact): ?>
                                    <form method="POST">
                                        <button type="submit" name="view_contact" class="btn btn-success">
                                            ✅ 已付费，查看联系方式
                                        </button>
                                    </form>
                                <?php elseif ($userTataCoinBalance >= $contactCost): ?>
                                    <form method="POST" onsubmit="return confirmPayment()">
                                        <button type="submit" name="view_contact" class="btn btn-primary">
                                            💳 支付 <?php echo $contactCost; ?> Tata Coin 查看
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="insufficient-funds">
                                        <p>Tata Coin余额不足</p>
                                        <a href="<?php echo SITE_URL; ?>/user/index.php" class="btn btn-secondary">前往用户中心</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($paymentSuccess): ?>
                        <div class="payment-success">
                            <p style="color: #10b981; font-weight: 500; margin-bottom: 15px;">
                                ✅ <?php echo h($paymentSuccess); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if ($showContact || $isAdmin): ?>
                        <div class="contact-info">
                            <?php if (!empty($reader['contact_info'])): ?>
                                <div class="contact-details">
                                    <h3>📝 联系信息</h3>
                                    <div class="contact-text">
                                        <?php echo nl2br(h($reader['contact_info'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="contact-methods">
                                <?php if (!empty($reader['phone'])): ?>
                                    <div class="contact-item">
                                        <span class="contact-icon">📞</span>
                                        <strong>电话：</strong>
                                        <span class="contact-value"><?php echo h($reader['phone']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($reader['wechat'])): ?>
                                    <div class="contact-item">
                                        <span class="contact-icon">💬</span>
                                        <strong>微信：</strong>
                                        <span class="contact-value"><?php echo h($reader['wechat']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($reader['qq'])): ?>
                                    <div class="contact-item">
                                        <span class="contact-icon">🐧</span>
                                        <strong>QQ：</strong>
                                        <span class="contact-value"><?php echo h($reader['qq']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($reader['xiaohongshu'])): ?>
                                    <div class="contact-item">
                                        <span class="contact-icon">📖</span>
                                        <strong>小红书：</strong>
                                        <span class="contact-value"><?php echo h($reader['xiaohongshu']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($reader['douyin'])): ?>
                                    <div class="contact-item">
                                        <span class="contact-icon">🎵</span>
                                        <strong>抖音：</strong>
                                        <span class="contact-value"><?php echo h($reader['douyin']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($reader['other_contact'])): ?>
                                    <div class="contact-item">
                                        <span class="contact-icon">🔗</span>
                                        <strong>其他：</strong>
                                        <span class="contact-value"><?php echo h($reader['other_contact']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="contact-item">
                                    <span class="contact-icon">📧</span>
                                    <strong>邮箱：</strong>
                                    <span class="contact-value"><?php echo h($reader['email']); ?></span>
                                </div>
                            </div>

                            <div class="contact-note">
                                <p><strong>💡 温馨提示：</strong>请通过以上方式联系塔罗师预约服务。建议先了解服务内容和价格再进行预约。</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- 相关推荐 -->
                <?php
                $relatedReaders = $db->fetchAll(
                    "SELECT * FROM readers 
                     WHERE id != ? AND is_active = 1 
                     ORDER BY is_featured DESC, RAND() 
                     LIMIT 3",
                    [$readerId]
                );
                ?>
                
                <?php if (!empty($relatedReaders)): ?>
                    <div class="related-readers">
                        <h2>其他推荐塔罗师</h2>
                        <div class="readers-grid">
                            <?php foreach ($relatedReaders as $relatedReader): ?>
                                <div class="reader-card">
                                    <div class="reader-photo">
                                        <a href="<?php echo SITE_URL; ?>/reader.php?id=<?php echo $relatedReader['id']; ?>" class="reader-photo-link">
                                            <?php if (!empty($relatedReader['photo'])): ?>
                                                <img src="<?php echo h($relatedReader['photo']); ?>" alt="<?php echo h($relatedReader['full_name']); ?>">
                                            <?php else: ?>
                                                <div class="default-photo">
                                                    <i class="icon-user"></i>
                                                </div>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                    
                                    <div class="reader-info">
                                        <h3><?php echo h($relatedReader['full_name']); ?></h3>
                                        <p>从业 <?php echo h($relatedReader['experience_years']); ?> 年</p>
                                        <?php if (!empty($relatedReader['specialties'])): ?>
                                            <p><?php echo h(mb_substr($relatedReader['specialties'], 0, 30)); ?>...</p>
                                        <?php endif; ?>
                                        <a href="<?php echo SITE_URL; ?>/reader.php?id=<?php echo $relatedReader['id']; ?>" class="btn btn-primary">查看详情</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>

    <script>
        function confirmPayment() {
            const cost = <?php echo $contactCost; ?>;
            const readerName = "<?php echo addslashes($reader['full_name']); ?>";
            const balance = <?php echo $userTataCoinBalance; ?>;

            const message = `确认支付 ${cost} 个Tata Coin 查看 ${readerName} 的联系方式吗？\n\n当前余额：${balance} 个Tata Coin\n支付后余额：${balance - cost} 个Tata Coin`;

            return confirm(message);
        }

        // 页面加载完成后的处理
        document.addEventListener('DOMContentLoaded', function() {
            // 如果有支付成功消息，3秒后自动隐藏
            const successMessage = document.querySelector('.payment-success');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.transition = 'opacity 0.5s ease';
                    successMessage.style.opacity = '0';
                    setTimeout(() => {
                        successMessage.style.display = 'none';
                    }, 500);
                }, 3000);
            }
        });
    </script>
</body>
</html>
