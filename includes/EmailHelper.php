<?php
/**
 * 邮件发送助手类
 * 使用PHP内置的mail()函数或SMTP发送邮件
 */

require_once __DIR__ . '/../config/email_config.php';

class EmailHelper {
    
    /**
     * 发送邮件
     * @param string $to 收件人邮箱
     * @param string $subject 邮件主题
     * @param string $body 邮件内容（HTML格式）
     * @param string $toName 收件人姓名
     * @return array 发送结果
     */
    public static function sendEmail($to, $subject, $body, $toName = '') {
        if (!isEmailConfigured()) {
            return [
                'success' => false,
                'message' => '邮件服务未配置，请联系管理员'
            ];
        }
        
        try {
            // 使用简单的mail()函数发送邮件
            $headers = self::buildHeaders($toName);
            $success = mail($to, $subject, $body, $headers);
            
            if ($success) {
                return [
                    'success' => true,
                    'message' => '邮件发送成功'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => '邮件发送失败，请稍后重试'
                ];
            }
        } catch (Exception $e) {
            error_log('邮件发送失败: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '邮件发送失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 构建邮件头部
     * @param string $toName 收件人姓名
     * @return string
     */
    private static function buildHeaders($toName = '') {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
        $headers[] = 'Reply-To: ' . FROM_EMAIL;
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        
        return implode("\r\n", $headers);
    }
    
    /**
     * 发送密码重置邮件
     * @param string $email 用户邮箱
     * @param string $name 用户姓名
     * @param string $resetToken 重置令牌
     * @param string $userType 用户类型 user/reader
     * @return array
     */
    public static function sendPasswordResetEmail($email, $name, $resetToken, $userType = 'user') {
        $resetUrl = SITE_URL . "/auth/reset_password.php?token={$resetToken}&type={$userType}";
        
        $subject = '密码重置 - ' . getSiteName();
        
        $body = self::getPasswordResetEmailTemplate($name, $resetUrl);
        
        return self::sendEmail($email, $subject, $body, $name);
    }
    
    /**
     * 获取密码重置邮件模板
     * @param string $name 用户姓名
     * @param string $resetUrl 重置链接
     * @return string
     */
    private static function getPasswordResetEmailTemplate($name, $resetUrl) {
        $siteName = getSiteName();
        
        return "
        <!DOCTYPE html>
        <html lang='zh-CN'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>密码重置</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$siteName}</h1>
                    <p>密码重置请求</p>
                </div>
                <div class='content'>
                    <h2>您好，{$name}！</h2>
                    <p>我们收到了您的密码重置请求。如果这是您本人的操作，请点击下面的按钮重置您的密码：</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$resetUrl}' class='button'>重置密码</a>
                    </div>
                    
                    <div class='warning'>
                        <strong>安全提醒：</strong>
                        <ul>
                            <li>此链接将在24小时后失效</li>
                            <li>如果您没有请求重置密码，请忽略此邮件</li>
                            <li>请不要将此链接分享给他人</li>
                        </ul>
                    </div>
                    
                    <p>如果按钮无法点击，请复制以下链接到浏览器地址栏：</p>
                    <p style='word-break: break-all; background: #f0f0f0; padding: 10px; border-radius: 5px;'>{$resetUrl}</p>
                    
                    <p>如果您有任何问题，请联系我们的客服团队。</p>
                </div>
                <div class='footer'>
                    <p>此邮件由系统自动发送，请勿回复</p>
                    <p>&copy; " . date('Y') . " {$siteName} 版权所有</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * 发送测试邮件
     * @param string $email 测试邮箱
     * @return array
     */
    public static function sendTestEmail($email) {
        $subject = '邮件服务测试 - ' . getSiteName();
        $body = self::getTestEmailTemplate();
        
        return self::sendEmail($email, $subject, $body);
    }
    
    /**
     * 获取测试邮件模板
     * @return string
     */
    private static function getTestEmailTemplate() {
        $siteName = getSiteName();
        
        return "
        <!DOCTYPE html>
        <html lang='zh-CN'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>邮件服务测试</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0; color: #155724; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$siteName}</h1>
                    <p>邮件服务测试</p>
                </div>
                <div class='content'>
                    <div class='success'>
                        <h2>✅ 邮件服务配置成功！</h2>
                        <p>恭喜！您的邮件服务已经配置成功，可以正常发送邮件了。</p>
                    </div>
                    
                    <h3>测试信息：</h3>
                    <ul>
                        <li>发送时间：" . date('Y-m-d H:i:s') . "</li>
                        <li>服务器：" . SMTP_HOST . "</li>
                        <li>端口：" . SMTP_PORT . "</li>
                        <li>加密方式：" . SMTP_SECURE . "</li>
                    </ul>
                    
                    <p>现在您可以使用忘记密码功能了。</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * 验证邮箱格式
     * @param string $email
     * @return bool
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * 生成重置令牌
     * @return string
     */
    public static function generateResetToken() {
        return bin2hex(random_bytes(32));
    }

    /**
     * 使用SMTP发送邮件（高级版本）
     * 如果需要更稳定的邮件发送，可以使用这个方法
     * 需要安装PHPMailer库
     */
    public static function sendEmailViaSMTP($to, $subject, $body, $toName = '') {
        // 这里可以集成PHPMailer或其他SMTP库
        // 目前使用简单的mail()函数
        return self::sendEmail($to, $subject, $body, $toName);
    }
}
?>
