<?php
session_start();
require_once 'config/config.php';

// 检查是否已登录
$user = null;
if (isset($_SESSION['user_id'])) {
    $user = getUserById($_SESSION['user_id']);
}

// 获取推荐的塔罗师
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
</head>
<body class="home-page">
    <?php include 'includes/header.php'; ?>

    <main>
        <!-- 英雄区域 -->
        <section class="hero-section">
            <div class="hero-background">
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <div class="container">
                        <h1 class="hero-title">
                            To Believe or Not To Believe<br>
                            This Is A Question
                        </h1>
                        <p class="hero-description">
                            联系经验丰富的占卜师，获得个性化准确解读，<br>
                            更有详细的玄学课程和魔法产品，提升您的灵性之旅。
                        </p>
                        <div class="hero-actions">
                            <a href="<?php echo SITE_URL; ?>/readers.php" class="btn btn-explore">Explore</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 推荐塔罗师 -->
        <section class="featured-readers-section">
            <div class="container">
                <h2 class="section-title">推荐塔罗师</h2>
                <div class="readers-circle-grid">
                    <?php foreach ($featured_readers as $reader): ?>
                        <div class="reader-circle-card">
                            <a href="<?php echo SITE_URL; ?>/reader.php?id=<?php echo $reader['id']; ?>" class="reader-circle-link">
                                <div class="reader-circle-photo">
                                    <?php if (!empty($reader['photo_circle'])): ?>
                                        <img src="<?php echo htmlspecialchars($reader['photo_circle']); ?>"
                                             alt="<?php echo htmlspecialchars($reader['full_name']); ?>"
                                             onerror="this.style.display='none'; this.parentNode.querySelector('.default-circle-photo').style.display='flex';">
                                    <?php elseif (!empty($reader['photo'])): ?>
                                        <img src="<?php echo htmlspecialchars($reader['photo']); ?>"
                                             alt="<?php echo htmlspecialchars($reader['full_name']); ?>"
                                             onerror="this.style.display='none'; this.parentNode.querySelector('.default-circle-photo').style.display='flex';">
                                    <?php endif; ?>
                                    <div class="default-circle-photo" style="display: <?php echo (!empty($reader['photo_circle']) || !empty($reader['photo'])) ? 'none' : 'flex'; ?>;">
                                        <i class="icon-user">👤</i>
                                    </div>
                                </div>

                                <div class="reader-circle-info">
                                    <h3 class="reader-name"><?php echo htmlspecialchars($reader['full_name']); ?></h3>
                                    <p class="reader-experience"><?php echo htmlspecialchars($reader['experience_years']); ?> years of experience</p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="section-footer">
                    <a href="<?php echo SITE_URL; ?>/readers.php" class="btn btn-outline">查看更多塔罗师</a>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
