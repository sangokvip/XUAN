<?php
/**
 * 占卜师配置类
 * 包含国籍、占卜类型等配置数据
 */
class DivinationConfig {
    
    /**
     * 获取国籍列表（中英文对照）
     * @return array
     */
    public static function getNationalities() {
        return [
            'CN' => '中国 China',
            'US' => '美国 United States',
            'GB' => '英国 United Kingdom',
            'CA' => '加拿大 Canada',
            'AU' => '澳大利亚 Australia',
            'JP' => '日本 Japan',
            'KR' => '韩国 South Korea',
            'SG' => '新加坡 Singapore',
            'MY' => '马来西亚 Malaysia',
            'TH' => '泰国 Thailand',
            'VN' => '越南 Vietnam',
            'PH' => '菲律宾 Philippines',
            'ID' => '印度尼西亚 Indonesia',
            'IN' => '印度 India',
            'FR' => '法国 France',
            'DE' => '德国 Germany',
            'IT' => '意大利 Italy',
            'ES' => '西班牙 Spain',
            'NL' => '荷兰 Netherlands',
            'BE' => '比利时 Belgium',
            'CH' => '瑞士 Switzerland',
            'AT' => '奥地利 Austria',
            'SE' => '瑞典 Sweden',
            'NO' => '挪威 Norway',
            'DK' => '丹麦 Denmark',
            'FI' => '芬兰 Finland',
            'RU' => '俄罗斯 Russia',
            'BR' => '巴西 Brazil',
            'AR' => '阿根廷 Argentina',
            'MX' => '墨西哥 Mexico',
            'CL' => '智利 Chile',
            'PE' => '秘鲁 Peru',
            'CO' => '哥伦比亚 Colombia',
            'ZA' => '南非 South Africa',
            'EG' => '埃及 Egypt',
            'MA' => '摩洛哥 Morocco',
            'NG' => '尼日利亚 Nigeria',
            'KE' => '肯尼亚 Kenya',
            'GH' => '加纳 Ghana',
            'TW' => '台湾 Taiwan',
            'HK' => '香港 Hong Kong',
            'MO' => '澳门 Macau',
            'NZ' => '新西兰 New Zealand',
            'IE' => '爱尔兰 Ireland',
            'PT' => '葡萄牙 Portugal',
            'GR' => '希腊 Greece',
            'TR' => '土耳其 Turkey',
            'IL' => '以色列 Israel',
            'AE' => '阿联酋 United Arab Emirates',
            'SA' => '沙特阿拉伯 Saudi Arabia',
            'OTHER' => '其他 Other'
        ];
    }
    
    /**
     * 获取西玄占卜类型
     * @return array
     */
    public static function getWesternDivinationTypes() {
        return [
            'tarot' => '塔罗',
            'lenormand' => '雷诺曼',
            'astrology' => '占星',
            'numerology' => '数字/姓名学',
            'crystal_healing' => '水晶疗愈',
            'runes' => '卢恩符文',
            'pendulum' => '灵摆',
            'magic_ritual' => '魔法仪式',
            'channeling' => '通灵'
        ];
    }
    
    /**
     * 获取东玄占卜类型
     * @return array
     */
    public static function getEasternDivinationTypes() {
        return [
            'bazi' => '四柱八字',
            'ziwei' => '紫微斗数',
            'qimen' => '奇门遁甲',
            'yijing' => '周易',
            'date_selection' => '择日学',
            'fengshui' => '风水堪舆',
            'spirit_medium' => '过阴'
        ];
    }
    
    /**
     * 获取所有占卜类型（按分类）
     * @return array
     */
    public static function getAllDivinationTypes() {
        return [
            'western' => [
                'name' => '西玄',
                'color' => 'purple',
                'types' => self::getWesternDivinationTypes()
            ],
            'eastern' => [
                'name' => '东玄',
                'color' => 'black',
                'types' => self::getEasternDivinationTypes()
            ]
        ];
    }
    
    /**
     * 根据占卜类型获取分类
     * @param string $type 占卜类型
     * @return string western|eastern|unknown
     */
    public static function getDivinationCategory($type) {
        $westernTypes = self::getWesternDivinationTypes();
        $easternTypes = self::getEasternDivinationTypes();
        
        if (array_key_exists($type, $westernTypes)) {
            return 'western';
        } elseif (array_key_exists($type, $easternTypes)) {
            return 'eastern';
        }
        
        return 'unknown';
    }
    
    /**
     * 获取占卜类型的中文名称
     * @param string $type 占卜类型
     * @return string
     */
    public static function getDivinationTypeName($type) {
        $allTypes = array_merge(
            self::getWesternDivinationTypes(),
            self::getEasternDivinationTypes()
        );
        
        return $allTypes[$type] ?? $type;
    }
    
    /**
     * 验证占卜师类型选择
     * @param array $selectedTypes 选择的类型
     * @param string $primaryType 主要类型
     * @return array 验证结果
     */
    public static function validateDivinationSelection($selectedTypes, $primaryType) {
        $errors = [];
        
        // 检查选择数量
        if (count($selectedTypes) > 3) {
            $errors[] = '最多只能选择3种占卜类型';
        }
        
        if (empty($selectedTypes)) {
            $errors[] = '至少需要选择1种占卜类型';
        }
        
        // 检查主要类型是否在选择列表中
        if (!empty($primaryType) && !in_array($primaryType, $selectedTypes)) {
            $errors[] = '主要身份标签必须在选择的占卜类型中';
        }
        
        // 检查类型是否有效
        $allTypes = array_merge(
            array_keys(self::getWesternDivinationTypes()),
            array_keys(self::getEasternDivinationTypes())
        );
        
        foreach ($selectedTypes as $type) {
            if (!in_array($type, $allTypes)) {
                $errors[] = "无效的占卜类型：{$type}";
            }
        }
        
        if (!empty($primaryType) && !in_array($primaryType, $allTypes)) {
            $errors[] = "无效的主要身份标签：{$primaryType}";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * 获取占卜师标签样式类
     * @param string $type 占卜类型
     * @return string CSS类名
     */
    public static function getDivinationTagClass($type) {
        $category = self::getDivinationCategory($type);
        
        switch ($category) {
            case 'western':
                return 'divination-tag-western';
            case 'eastern':
                return 'divination-tag-eastern';
            default:
                return 'divination-tag-default';
        }
    }
    
    /**
     * 生成占卜师标签HTML
     * @param string $type 占卜类型
     * @param bool $isPrimary 是否为主要标签
     * @return string HTML
     */
    public static function generateDivinationTag($type, $isPrimary = false) {
        $name = self::getDivinationTypeName($type);
        $class = self::getDivinationTagClass($type);
        $primaryClass = $isPrimary ? ' primary-tag' : '';
        
        return "<span class=\"{$class}{$primaryClass}\">{$name}</span>";
    }
}
?>
