<?php
session_start();
require_once 'config/config.php';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>关于我们 - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/about.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <!-- 页面标题区域 -->
        <section class="page-hero">
            <div class="container">
                <div class="hero-content">
                    <h1 class="page-title">关于我们</h1>
                    <p class="page-subtitle">连接心灵，指引方向</p>
                </div>
            </div>
        </section>

        <!-- 平台介绍 -->
        <section class="platform-intro">
            <div class="container">
                <div class="intro-content">
                    <div class="intro-text">
                        <h2>专业的占卜师展示平台</h2>
                        <p>我们致力于为用户提供专业、可信赖的占卜服务平台。在这里，您可以找到经过严格筛选的优秀占卜师，获得专业的指导和建议。</p>
                        <p>平台汇聚了来自不同领域的占卜专家，包括塔罗牌、占星学、数字命理学、易经八卦等多种占卜方式，为您提供全方位的灵性指导服务。</p>
                    </div>
                    <div class="intro-image">
                        <div class="mystical-symbol">✨</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 平台特色 -->
        <section class="platform-features">
            <div class="container">
                <h2 class="section-title">平台特色</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">🔮</div>
                        <h3>专业占卜师</h3>
                        <p>严格筛选具有丰富经验的专业占卜师，确保服务质量和专业水准</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🛡️</div>
                        <h3>隐私保护</h3>
                        <p>严格保护用户隐私，所有咨询内容均采用加密传输，绝不泄露个人信息</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">💎</div>
                        <h3>多元化服务</h3>
                        <p>涵盖塔罗牌、占星学、数字命理、易经八卦等多种占卜方式，满足不同需求</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">⭐</div>
                        <h3>评价体系</h3>
                        <p>完善的用户评价和反馈系统，帮助您选择最适合的占卜师</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">💰</div>
                        <h3>Tata Coin系统</h3>
                        <p>创新的虚拟货币系统，通过签到、浏览等方式获得积分，享受更多服务</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📱</div>
                        <h3>便捷体验</h3>
                        <p>响应式设计，支持手机、平板、电脑等多种设备，随时随地获得指导</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 服务流程 -->
        <section class="service-process">
            <div class="container">
                <h2 class="section-title">服务流程</h2>
                <div class="process-steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h3>注册账户</h3>
                            <p>快速注册成为平台用户，获得100个Tata Coin新手奖励</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h3>浏览占卜师</h3>
                            <p>浏览占卜师列表，查看详细资料、专业领域和用户评价</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h3>选择服务</h3>
                            <p>使用Tata Coin查看占卜师联系方式，直接沟通预约服务</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <h3>获得指导</h3>
                            <p>享受专业的占卜服务，获得人生指导和建议</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 数据统计 -->
        <section class="platform-stats">
            <div class="container">
                <h2 class="section-title">平台数据</h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number">
                            <?php
                            $db = Database::getInstance();
                            $userCount = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
                            echo number_format($userCount['count'] ?? 0);
                            ?>+
                        </div>
                        <div class="stat-label">注册用户</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">
                            <?php
                            $readerCount = $db->fetchOne("SELECT COUNT(*) as count FROM readers WHERE is_active = 1");
                            echo number_format($readerCount['count'] ?? 0);
                            ?>+
                        </div>
                        <div class="stat-label">专业占卜师</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">
                            <?php
                            $viewCount = $db->fetchOne("SELECT COUNT(*) as count FROM contact_views");
                            echo number_format($viewCount['count'] ?? 0);
                            ?>+
                        </div>
                        <div class="stat-label">服务次数</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">
                            <?php
                            $reviewCount = $db->fetchOne("SELECT COUNT(*) as count FROM reader_reviews");
                            echo number_format($reviewCount['count'] ?? 0);
                            ?>+
                        </div>
                        <div class="stat-label">用户评价</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 占卜师入驻 -->
        <section class="join-platform">
            <div class="container">
                <div class="join-content">
                    <div class="join-text">
                        <h2>成为我们的占卜师</h2>
                        <p>如果您是专业的占卜师，欢迎加入我们的平台。我们为占卜师提供：</p>
                        <ul>
                            <li>专业的展示平台，提升个人品牌</li>
                            <li>完善的后台管理系统</li>
                            <li>收入分成机制，获得稳定收益</li>
                            <li>邀请返点系统，扩展客户网络</li>
                            <li>数据统计分析，了解服务效果</li>
                        </ul>
                        <p>我们期待与更多专业的占卜师合作，共同为用户提供优质的服务。</p>
                    </div>
                    <div class="join-actions">
                        <div class="mystical-decoration">🌟</div>
                        <p class="join-note">占卜师入驻需要通过专用邀请链接注册</p>
                        <a href="<?php echo SITE_URL; ?>/contact.php" class="btn btn-primary">联系我们</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- 联系信息 -->
        <section class="contact-info">
            <div class="container">
                <h2 class="section-title">联系我们</h2>
                <div class="contact-grid">
                    <div class="contact-item">
                        <div class="contact-icon">📧</div>
                        <h3>邮箱联系</h3>
                        <p>如有任何问题或建议，欢迎发送邮件给我们</p>
                        <a href="<?php echo SITE_URL; ?>/contact.php" class="contact-link">发送邮件</a>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">💬</div>
                        <h3>在线客服</h3>
                        <p>工作时间内提供在线客服支持</p>
                        <span class="contact-time">周一至周五 9:00-18:00</span>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">🔗</div>
                        <h3>社交媒体</h3>
                        <p>关注我们的社交媒体获取最新资讯</p>
                        <span class="contact-note">敬请期待</span>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
