<?php
/**
 * 头像助手类
 * 处理新的多头像系统
 */
class AvatarHelper {
    
    /**
     * 根据性别和ID获取默认头像
     * @param string $gender 性别 ('male' 或 'female')
     * @param int $id 用户或占卜师ID
     * @return string 头像文件路径
     */
    public static function getDefaultAvatar($gender, $id) {
        // 根据ID循环选择头像编号 (1-4)
        $avatarNum = (($id - 1) % 4) + 1;
        
        if ($gender === 'female') {
            return "img/f{$avatarNum}.jpg";
        } else {
            return "img/m{$avatarNum}.jpg";
        }
    }
    
    /**
     * 获取占卜师头像（包含fallback逻辑）
     * @param array $reader 占卜师数据
     * @return string 头像文件路径
     */
    public static function getReaderAvatar($reader) {
        if (!empty($reader['photo'])) {
            // 清理路径格式
            $photoSrc = $reader['photo'];
            $photoSrc = str_replace('../', '', $photoSrc);
            $photoSrc = ltrim($photoSrc, '/');
            
            // 检查是否为旧的默认头像，如果是则更新为新头像
            if ($photoSrc === 'img/tm.jpg' || $photoSrc === 'img/tf.jpg') {
                return self::getDefaultAvatar($reader['gender'], $reader['id']);
            }
            
            return $photoSrc;
        } else {
            // 使用新的默认头像系统
            return self::getDefaultAvatar($reader['gender'], $reader['id']);
        }
    }
    
    /**
     * 获取用户头像（用于评论等）
     * @param array $user 用户数据
     * @return string 头像文件路径
     */
    public static function getUserAvatar($user) {
        if (!empty($user['avatar'])) {
            // 清理路径格式
            $avatarSrc = $user['avatar'];
            $avatarSrc = str_replace('../', '', $avatarSrc);
            $avatarSrc = ltrim($avatarSrc, '/');
            
            // 检查是否为旧的默认头像
            if ($avatarSrc === 'img/nm.jpg' || $avatarSrc === 'img/nf.jpg') {
                return $avatarSrc; // 普通用户保持原有头像
            }
            
            return $avatarSrc;
        } else {
            // 普通用户使用原有的默认头像
            return ($user['gender'] === 'female') ? 'img/nf.jpg' : 'img/nm.jpg';
        }
    }
    
    /**
     * 获取所有可用的默认头像列表
     * @param string $gender 性别 ('male' 或 'female')
     * @return array 头像文件路径数组
     */
    public static function getAvailableAvatars($gender) {
        $avatars = [];
        for ($i = 1; $i <= 4; $i++) {
            if ($gender === 'female') {
                $avatars[] = "img/f{$i}.jpg";
            } else {
                $avatars[] = "img/m{$i}.jpg";
            }
        }
        return $avatars;
    }
    
    /**
     * 检查头像文件是否存在
     * @param string $avatarPath 头像文件路径
     * @return bool 文件是否存在
     */
    public static function avatarExists($avatarPath) {
        return file_exists($avatarPath);
    }
    
    /**
     * 获取头像的完整URL（用于前端显示）
     * @param string $avatarPath 头像文件路径
     * @param string $baseUrl 网站基础URL（可选）
     * @return string 完整的头像URL
     */
    public static function getAvatarUrl($avatarPath, $baseUrl = '') {
        // 确保路径格式正确
        $avatarPath = str_replace('../', '', $avatarPath);
        $avatarPath = ltrim($avatarPath, '/');
        
        if ($baseUrl) {
            return rtrim($baseUrl, '/') . '/' . $avatarPath;
        }
        
        return $avatarPath;
    }
    
    /**
     * 为注册表单生成默认头像选择HTML
     * @param string $gender 性别
     * @param string $selectedAvatar 当前选中的头像（可选）
     * @return string HTML代码
     */
    public static function generateAvatarSelectionHtml($gender, $selectedAvatar = '') {
        $avatars = self::getAvailableAvatars($gender);
        $html = '<div class="default-avatar-grid">';
        
        foreach ($avatars as $index => $avatar) {
            $avatarNum = $index + 1;
            $isSelected = ($selectedAvatar === $avatar) ? 'selected' : '';
            $genderText = ($gender === 'female') ? '女性' : '男性';
            
            $html .= '<div class="avatar-option ' . $isSelected . '" data-avatar="' . htmlspecialchars($avatar) . '">';
            $html .= '<img src="' . htmlspecialchars($avatar) . '" alt="' . $genderText . '头像' . $avatarNum . '" class="avatar-preview">';
            $html .= '<div class="avatar-label">头像' . $avatarNum . '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * 批量更新旧默认头像到新系统
     * @param Database $db 数据库实例
     * @return array 更新结果
     */
    public static function migrateOldAvatars($db) {
        $result = [
            'updated' => 0,
            'errors' => []
        ];
        
        try {
            // 查找使用旧默认头像的占卜师
            $oldAvatarReaders = $db->fetchAll("
                SELECT id, gender, photo 
                FROM readers 
                WHERE photo IN ('img/tm.jpg', 'img/tf.jpg', '../img/tm.jpg', '../img/tf.jpg')
            ");
            
            foreach ($oldAvatarReaders as $reader) {
                $newAvatar = self::getDefaultAvatar($reader['gender'], $reader['id']);
                
                try {
                    $db->query("UPDATE readers SET photo = ? WHERE id = ?", [$newAvatar, $reader['id']]);
                    $result['updated']++;
                } catch (Exception $e) {
                    $result['errors'][] = "更新占卜师ID {$reader['id']} 失败: " . $e->getMessage();
                }
            }
            
        } catch (Exception $e) {
            $result['errors'][] = "查询失败: " . $e->getMessage();
        }
        
        return $result;
    }
}
?>
