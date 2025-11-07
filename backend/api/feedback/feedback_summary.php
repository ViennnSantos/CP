<!-- admin/partials/feedback_summary.php -->
<?php
// Admin Feedback Summary Dashboard
// This partial should be included in admin/index.php in the feedback section

if (!isset($pdo) || !$pdo instanceof PDO) {
    // Try to get database connection
    if (class_exists('Database')) {
        try {
            $pdo = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            $pdo = null;
        }
    }
}

$summaryStats = [
    'total_reviews' => 0,
    'avg_rating' => 0.0,
    'pending_approvals' => 0,
    'reviews_with_images' => 0,
    'released_reviews' => 0,
    'total_customers' => 0
];

if ($pdo instanceof PDO) {
    try {
        // Check if deleted column exists
        $colCheck = $pdo->query("SHOW COLUMNS FROM feedback LIKE 'deleted'")->fetch();
        $deletedFilter = $colCheck ? " AND deleted = 0" : "";

        // Get summary statistics
        $statsQuery = $pdo->query("
            SELECT 
                COUNT(*) as total_reviews,
                COALESCE(ROUND(AVG(rating), 1), 0) as avg_rating,
                SUM(CASE WHEN status = 'pending' OR is_released = 0 THEN 1 ELSE 0 END) as pending_approvals,
                SUM(CASE WHEN image_path IS NOT NULL AND image_path != '' THEN 1 ELSE 0 END) as reviews_with_images,
                SUM(CASE WHEN is_released = 1 THEN 1 ELSE 0 END) as released_reviews,
                COUNT(DISTINCT customer_id) as total_customers
            FROM feedback
            WHERE 1=1 {$deletedFilter}
        ");
        
        $stats = $statsQuery->fetch(PDO::FETCH_ASSOC);
        if ($stats) {
            $summaryStats = [
                'total_reviews' => (int)$stats['total_reviews'],
                'avg_rating' => (float)$stats['avg_rating'],
                'pending_approvals' => (int)$stats['pending_approvals'],
                'reviews_with_images' => (int)$stats['reviews_with_images'],
                'released_reviews' => (int)$stats['released_reviews'],
                'total_customers' => (int)$stats['total_customers']
            ];
        }
    } catch (Exception $e) {
        error_log("Feedback summary error: " . $e->getMessage());
    }
}

// Helper function for rating stars
function renderStars(float $rating): string {
    $fullStars = floor($rating);
    $hasHalfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);
    
    $html = '';
    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<span class="summary-star filled">★</span>';
    }
    if ($hasHalfStar) {
        $html .= '<span class="summary-star half">★</span>';
    }
    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<span class="summary-star empty">★</span>';
    }
    return $html;
}
?>

<style>
/* Feedback Summary Dashboard Styles */
.feedback-summary-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 32px;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
}

.feedback-summary-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}

.feedback-summary-icon {
    font-size: 32px;
}

.feedback-summary-title {
    font-size: 24px;
    font-weight: 700;
    color: white;
    margin: 0;
}

.feedback-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.summary-stat-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 20px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: transform 0.2s, box-shadow 0.2s;
}

.summary-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

.summary-stat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.summary-stat-icon {
    font-size: 24px;
    opacity: 0.9;
}

.summary-stat-badge {
    background: rgba(255, 255, 255, 0.25);
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.summary-stat-badge.pending {
    background: #fbbf24;
    color: #78350f;
}

.summary-stat-value {
    font-size: 36px;
    font-weight: 700;
    color: white;
    margin-bottom: 4px;
    line-height: 1;
}

.summary-stat-label {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.85);
    font-weight: 500;
}

.summary-rating-container {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
}

.summary-stars {
    display: flex;
    gap: 2px;
}

.summary-star {
    font-size: 18px;
    color: rgba(255, 255, 255, 0.3);
}

.summary-star.filled {
    color: #fbbf24;
}

.summary-star.half {
    color: #fbbf24;
    opacity: 0.5;
}

.summary-rating-text {
    font-size: 14px;
    color: white;
    font-weight: 600;
}

/* Pending badge pulse animation */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.summary-stat-badge.pending {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Responsive */
@media (max-width: 768px) {
    .feedback-summary-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-stat-value {
        font-size: 28px;
    }
}
</style>

<!-- Feedback Summary Dashboard HTML -->
<div class="feedback-summary-container">
    <div class="feedback-summary-header">
        <span class="feedback-summary-icon">📊</span>
        <h2 class="feedback-summary-title">Feedback Dashboard</h2>
    </div>

    <div class="feedback-summary-grid">
        <!-- Average Rating Card -->
        <div class="summary-stat-card">
            <div class="summary-stat-header">
                <span class="summary-stat-icon">⭐</span>
            </div>
            <div class="summary-stat-value"><?php echo number_format($summaryStats['avg_rating'], 1); ?></div>
            <div class="summary-stat-label">Average Rating</div>
            <div class="summary-rating-container">
                <div class="summary-stars">
                    <?php echo renderStars($summaryStats['avg_rating']); ?>
                </div>
                <span class="summary-rating-text"><?php echo number_format($summaryStats['avg_rating'], 1); ?>/5</span>
            </div>
        </div>

        <!-- Total Reviews Card -->
        <div class="summary-stat-card">
            <div class="summary-stat-header">
                <span class="summary-stat-icon">💬</span>
            </div>
            <div class="summary-stat-value"><?php echo number_format($summaryStats['total_reviews']); ?></div>
            <div class="summary-stat-label">Total Reviews</div>
        </div>

        <!-- Pending Approvals Card -->
        <div class="summary-stat-card">
            <div class="summary-stat-header">
                <span class="summary-stat-icon">⏳</span>
                <?php if ($summaryStats['pending_approvals'] > 0): ?>
                    <span class="summary-stat-badge pending">Action Needed</span>
                <?php endif; ?>
            </div>
            <div class="summary-stat-value"><?php echo number_format($summaryStats['pending_approvals']); ?></div>
            <div class="summary-stat-label">Pending Approvals</div>
        </div>

        <!-- Reviews with Images Card -->
        <div class="summary-stat-card">
            <div class="summary-stat-header">
                <span class="summary-stat-icon">📸</span>
            </div>
            <div class="summary-stat-value"><?php echo number_format($summaryStats['reviews_with_images']); ?></div>
            <div class="summary-stat-label">Reviews with Photos</div>
        </div>

        <!-- Released Reviews Card -->
        <div class="summary-stat-card">
            <div class="summary-stat-header">
                <span class="summary-stat-icon">✅</span>
            </div>
            <div class="summary-stat-value"><?php echo number_format($summaryStats['released_reviews']); ?></div>
            <div class="summary-stat-label">Published Reviews</div>
        </div>

        <!-- Unique Customers Card -->
        <div class="summary-stat-card">
            <div class="summary-stat-header">
                <span class="summary-stat-icon">👥</span>
            </div>
            <div class="summary-stat-value"><?php echo number_format($summaryStats['total_customers']); ?></div>
            <div class="summary-stat-label">Customers Reviewed</div>
        </div>
    </div>
</div>