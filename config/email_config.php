<?php
/**
 * 邮件配置文件
 * 请根据您的邮件服务器设置修改以下配置
 */

// 邮件服务器配置
define('SMTP_HOST', 'smtp.qq.com');        // SMTP服务器地址
define('SMTP_PORT', 587);                  // SMTP端口
define('SMTP_USERNAME', '');               // 发送邮箱账号（请填写您的邮箱）
define('SMTP_PASSWORD', '');               // 邮箱密码或授权码（请填写您的密码）
define('SMTP_SECURE', 'tls');              // 加密方式 tls/ssl
define('FROM_EMAIL', '');                  // 发件人邮箱（请填写您的邮箱）
define('FROM_NAME', '占卜师平台');           // 发件人名称

// 邮件模板配置
define('EMAIL_TEMPLATES_PATH', __DIR__ . '/../templates/email/');

/**
 * 常用SMTP服务器配置参考：
 * 
 * QQ邮箱：
 * SMTP_HOST: smtp.qq.com
 * SMTP_PORT: 587 (TLS) 或 465 (SSL)
 * SMTP_SECURE: tls 或 ssl
 * 
 * 163邮箱：
 * SMTP_HOST: smtp.163.com
 * SMTP_PORT: 25 或 994 (SSL)
 * SMTP_SECURE: ssl
 * 
 * Gmail：
 * SMTP_HOST: smtp.gmail.com
 * SMTP_PORT: 587 (TLS) 或 465 (SSL)
 * SMTP_SECURE: tls 或 ssl
 * 
 * 阿里云邮件推送：
 * SMTP_HOST: smtpdm.aliyun.com
 * SMTP_PORT: 25 或 80
 * SMTP_SECURE: 无
 */

// 检查配置是否完整
function isEmailConfigured() {
    return !empty(SMTP_USERNAME) && !empty(SMTP_PASSWORD) && !empty(FROM_EMAIL);
}

// 获取邮件配置状态
function getEmailConfigStatus() {
    if (!isEmailConfigured()) {
        return [
            'configured' => false,
            'message' => '邮件服务未配置，请在 config/email_config.php 中设置邮件服务器信息'
        ];
    }
    
    return [
        'configured' => true,
        'message' => '邮件服务已配置'
    ];
}
?>
