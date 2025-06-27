<?php
session_start();
require_once 'config/config.php';
require_once 'includes/DivinationTagHelper.php';

// 检查是否已登录
$user = null;
if (isset($_SESSION['user_id'])) {
    $user = getUserById($_SESSION['user_id']);
}

// 获取推荐的占卜师
$featured_readers = getFeaturedReaders();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getSiteName(); ?> - <?php echo getSiteDescription(); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="assets/css/divination-tags.css">

    <?php
    // 输出等级标签CSS
    require_once 'includes/level_badge.php';
    outputLevelBadgeCSS();
    ?>
</head>
<body class="home-page">
    <?php include 'includes/header.php'; ?>

    <main>
        <!-- 英雄区域 -->
        <section class="hero-section">
            <div class="hero-background"></div>
            <div class="hero-overlay"></div>
            <div class="hero-content">
                <div class="container">
                    <h1 class="hero-title">
                        Find Your Perfect Diviner
                    </h1>
                    <p class="hero-description">
                        Connect with experienced diviners for personalized readings and guidance.
                    </p>
                    <div class="hero-actions">
                        <a href="<?php echo SITE_URL; ?>/readers.php" class="btn btn-explore">Explore Diviners</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- 推荐占卜师 -->
        <section class="featured-readers-section">
            <div class="container">
                <h2 class="section-title">推荐占卜师</h2>
                <div class="readers-circle-grid">
                    <?php foreach ($featured_readers as $reader): ?>
                        <div class="reader-circle-card">
                            <a href="<?php echo SITE_URL; ?>/reader.php?id=<?php echo $reader['id']; ?>" class="reader-circle-link">
                                <div class="reader-circle-photo">
                                    <?php
                                    $photoSrc = getReaderPhotoUrl($reader, true);
                                    ?>
                                    <img src="<?php echo htmlspecialchars($photoSrc); ?>"
                                         alt="<?php echo htmlspecialchars($reader['full_name']); ?>">
                                </div>

                                <div class="reader-circle-info">
                                    <h3 class="reader-name">
                                        <?php echo htmlspecialchars($reader['full_name']); ?>
                                        <?php
                                        // 首页推荐占卜师不显示"推荐占卜师"标签，只显示普通占卜师的标签
                                        if (!$reader['is_featured']) {
                                            require_once 'includes/level_badge.php';
                                            echo getReaderLevelBadgeHTML('占卜师', 'small');
                                        }
                                        ?>
                                    </h3>
                                    <p class="reader-experience">从业 <?php echo htmlspecialchars($reader['experience_years']); ?> 年</p>

                                    <!-- 占卜师身份标签 -->
                                    <?php echo DivinationTagHelper::generateTagsContainer($reader, 'center', true, false); ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="section-footer">
                    <a href="<?php echo SITE_URL; ?>/readers.php" class="btn btn-outline">查看更多占卜师</a>
                </div>
            </div>
        </section>

        <!-- 服务说明区域 -->
        <section class="services-section">
            <div class="container">
                <h2 class="services-title">拨云见日，自此启程</h2>
                <p class="services-subtitle">严选五星占卜师，启幕专属灵性对话，在星月交辉处，与迷失的自我重逢。</p>

                <div class="services-grid">
                    <div class="service-item">
                        <div class="service-icon">
                            <span class="icon">⭐</span>
                        </div>
                        <h3 class="service-title">星选占卜圣手</h3>
                        <p class="service-description">
                            此间仅驻留历遍千万签的占卜旅人，与众生评述中淬炼的通幽者
                        </p>
                    </div>

                    <div class="service-item">
                        <div class="service-icon">
                            <span class="icon">🛡️</span>
                        </div>
                        <h3 class="service-title">星辰不言，吾辈不泄</h3>
                        <p class="service-description">
                            此间所言，皆封于星匣；天机不泄，因果不昧；凡所占验，尽归尘密     
                        </p>
                    </div>

                    <div class="service-item">
                        <div class="service-icon">
                            <span class="icon">🎯</span>
                        </div>
                        <h3 class="service-title">凡心所向，必有所得</h3>
                        <p class="service-description">
                            观星卜卦的方外之士，参透玄机的授业之师，暗藏天机的秘法之物，众生所求，皆有所应
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
