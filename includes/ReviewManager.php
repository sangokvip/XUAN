<?php

class ReviewManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 检查用户是否已购买过该塔罗师的服务
     * @param int $userId 用户ID
     * @param int $readerId 塔罗师ID
     * @return bool
     */
    public function hasUserPurchased($userId, $readerId) {
        $purchase = $this->db->fetchOne(
            "SELECT 1 FROM user_browse_history
             WHERE user_id = ? AND reader_id = ? AND browse_type = 'paid'
             LIMIT 1",
            [$userId, $readerId]
        );

        return !empty($purchase);
    }
    
    /**
     * 检查用户是否已经评价过该塔罗师
     * @param int $userId 用户ID
     * @param int $readerId 塔罗师ID
     * @return bool
     */
    public function hasUserReviewed($userId, $readerId) {
        $review = $this->db->fetchOne(
            "SELECT 1 FROM reader_reviews 
             WHERE user_id = ? AND reader_id = ? 
             LIMIT 1",
            [$userId, $readerId]
        );
        
        return !empty($review);
    }
    
    /**
     * 添加评价
     * @param int $readerId 塔罗师ID
     * @param int $userId 用户ID
     * @param int $rating 评分 (1-5)
     * @param string $reviewText 评价内容
     * @param bool $isAnonymous 是否匿名
     * @return bool
     */
    public function addReview($readerId, $userId, $rating, $reviewText, $isAnonymous = false) {
        // 检查是否已经评价过
        if ($this->hasUserReviewed($userId, $readerId)) {
            throw new Exception('您已经评价过该塔罗师了');
        }
        
        // 检查是否已购买
        $isPurchased = $this->hasUserPurchased($userId, $readerId);
        if (!$isPurchased) {
            throw new Exception('只有购买过服务的用户才能评价');
        }
        
        // 验证评分
        if ($rating < 1 || $rating > 5) {
            throw new Exception('评分必须在1-5之间');
        }
        
        try {
            $this->db->insert('reader_reviews', [
                'reader_id' => $readerId,
                'user_id' => $userId,
                'rating' => $rating,
                'review_text' => trim($reviewText),
                'is_anonymous' => $isAnonymous ? 1 : 0,
                'is_purchased' => 1
            ]);

            // 手动更新评分统计
            $this->updateReaderRatingStats($readerId);

            return true;
        } catch (Exception $e) {
            throw new Exception('评价提交失败：' . $e->getMessage());
        }
    }
    
    /**
     * 获取塔罗师的评价列表
     * @param int $readerId 塔罗师ID
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @param string $orderBy 排序方式
     * @return array
     */
    public function getReviews($readerId, $limit = 10, $offset = 0, $orderBy = 'created_at DESC') {
        return $this->db->fetchAll(
            "SELECT r.*, 
                    CASE 
                        WHEN r.is_anonymous = 1 THEN '匿名用户'
                        ELSE u.full_name 
                    END as user_name,
                    u.avatar as user_avatar,
                    (SELECT COUNT(*) FROM reader_review_likes WHERE review_id = r.id) as like_count
             FROM reader_reviews r
             LEFT JOIN users u ON r.user_id = u.id
             WHERE r.reader_id = ?
             ORDER BY {$orderBy}
             LIMIT ? OFFSET ?",
            [$readerId, $limit, $offset]
        );
    }
    
    /**
     * 获取评价统计
     * @param int $readerId 塔罗师ID
     * @return array
     */
    public function getReviewStats($readerId) {
        $stats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_reviews,
                AVG(rating) as average_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
             FROM reader_reviews 
             WHERE reader_id = ?",
            [$readerId]
        );
        
        return $stats ?: [
            'total_reviews' => 0,
            'average_rating' => 0,
            'rating_5' => 0,
            'rating_4' => 0,
            'rating_3' => 0,
            'rating_2' => 0,
            'rating_1' => 0
        ];
    }
    
    /**
     * 添加问题
     * @param int $readerId 塔罗师ID
     * @param int $userId 用户ID
     * @param string $question 问题内容
     * @param bool $isAnonymous 是否匿名
     * @return int 问题ID
     */
    public function addQuestion($readerId, $userId, $question, $isAnonymous = false) {
        if (empty(trim($question))) {
            throw new Exception('问题内容不能为空');
        }
        
        return $this->db->insert('reader_questions', [
            'reader_id' => $readerId,
            'user_id' => $userId,
            'question' => trim($question),
            'is_anonymous' => $isAnonymous ? 1 : 0
        ]);
    }
    
    /**
     * 添加回答
     * @param int $questionId 问题ID
     * @param int $userId 用户ID
     * @param string $answer 回答内容
     * @param bool $isAnonymous 是否匿名
     * @return bool
     */
    public function addAnswer($questionId, $userId, $answer, $isAnonymous = false) {
        if (empty(trim($answer))) {
            throw new Exception('回答内容不能为空');
        }
        
        // 获取问题信息
        $question = $this->db->fetchOne(
            "SELECT reader_id FROM reader_questions WHERE id = ?",
            [$questionId]
        );
        
        if (!$question) {
            throw new Exception('问题不存在');
        }
        
        // 检查是否已购买过该塔罗师的服务
        $isPurchased = $this->hasUserPurchased($userId, $question['reader_id']);
        
        $this->db->insert('reader_question_answers', [
            'question_id' => $questionId,
            'user_id' => $userId,
            'answer' => trim($answer),
            'is_anonymous' => $isAnonymous ? 1 : 0,
            'is_purchased' => $isPurchased ? 1 : 0
        ]);
        
        return true;
    }
    
    /**
     * 获取问题列表
     * @param int $readerId 塔罗师ID
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array
     */
    public function getQuestions($readerId, $limit = 10, $offset = 0) {
        return $this->db->fetchAll(
            "SELECT q.*, 
                    CASE 
                        WHEN q.is_anonymous = 1 THEN '匿名用户'
                        ELSE u.full_name 
                    END as user_name,
                    (SELECT COUNT(*) FROM reader_question_answers WHERE question_id = q.id) as answer_count
             FROM reader_questions q
             LEFT JOIN users u ON q.user_id = u.id
             WHERE q.reader_id = ?
             ORDER BY q.created_at DESC
             LIMIT ? OFFSET ?",
            [$readerId, $limit, $offset]
        );
    }
    
    /**
     * 获取问题的回答列表
     * @param int $questionId 问题ID
     * @return array
     */
    public function getAnswers($questionId) {
        return $this->db->fetchAll(
            "SELECT a.*, 
                    CASE 
                        WHEN a.is_anonymous = 1 THEN '匿名用户'
                        ELSE u.full_name 
                    END as user_name,
                    u.avatar as user_avatar
             FROM reader_question_answers a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.question_id = ?
             ORDER BY a.is_purchased DESC, a.created_at ASC",
            [$questionId]
        );
    }
    
    /**
     * 点赞评价
     * @param int $reviewId 评价ID
     * @param int $userId 用户ID
     * @return bool
     */
    public function likeReview($reviewId, $userId) {
        // 检查是否已经点赞
        $existing = $this->db->fetchOne(
            "SELECT 1 FROM reader_review_likes WHERE review_id = ? AND user_id = ?",
            [$reviewId, $userId]
        );
        
        if ($existing) {
            // 取消点赞
            $this->db->query(
                "DELETE FROM reader_review_likes WHERE review_id = ? AND user_id = ?",
                [$reviewId, $userId]
            );
            return false;
        } else {
            // 添加点赞
            $this->db->insert('reader_review_likes', [
                'review_id' => $reviewId,
                'user_id' => $userId
            ]);
            return true;
        }
    }
    
    /**
     * 检查评价系统是否已安装
     * @return bool
     */
    public function isInstalled() {
        try {
            $this->db->fetchOne("SELECT 1 FROM reader_reviews LIMIT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 获取用户对评价的点赞状态
     * @param int $userId 用户ID
     * @param array $reviewIds 评价ID数组
     * @return array
     */
    public function getUserLikeStatus($userId, $reviewIds) {
        if (empty($reviewIds)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($reviewIds) - 1) . '?';
        $params = array_merge([$userId], $reviewIds);
        
        $likes = $this->db->fetchAll(
            "SELECT review_id FROM reader_review_likes 
             WHERE user_id = ? AND review_id IN ({$placeholders})",
            $params
        );
        
        $likedReviews = [];
        foreach ($likes as $like) {
            $likedReviews[$like['review_id']] = true;
        }
        
        return $likedReviews;
    }

    /**
     * 更新塔罗师的评分统计
     * @param int $readerId 塔罗师ID
     * @return bool
     */
    public function updateReaderRatingStats($readerId) {
        try {
            $stats = $this->db->fetchOne("
                SELECT
                    AVG(rating) as avg_rating,
                    COUNT(*) as total_reviews
                FROM reader_reviews
                WHERE reader_id = ?
            ", [$readerId]);

            $avgRating = $stats['avg_rating'] ? round($stats['avg_rating'], 2) : 0;
            $totalReviews = $stats['total_reviews'] ?: 0;

            $this->db->query("
                UPDATE readers
                SET average_rating = ?, total_reviews = ?
                WHERE id = ?
            ", [$avgRating, $totalReviews, $readerId]);

            return true;
        } catch (Exception $e) {
            error_log("Update reader rating stats error: " . $e->getMessage());
            return false;
        }
    }
}
?>
