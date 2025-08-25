<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../includes/functions.php';

// Check admin access
startSession();
if (empty($_SESSION['is_admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$pdo = getDBConnection();
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $action = $_GET['action'] ?? 'dashboard';
    
    switch ($action) {
        case 'dashboard':
            // Get basic statistics
            $stats = [];
            
            // User statistics
            $stats['total_users'] = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_verified = 1')->fetchColumn();
            $stats['active_users'] = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE last_login IS NOT NULL AND is_verified = 1')->fetchColumn();
            $stats['pending_applications'] = (int)$pdo->query('SELECT COUNT(*) FROM user_applications WHERE status = "pending"')->fetchColumn();
            $stats['approved_applications'] = (int)$pdo->query('SELECT COUNT(*) FROM user_applications WHERE status = "approved"')->fetchColumn();
            $stats['rejected_applications'] = (int)$pdo->query('SELECT COUNT(*) FROM user_applications WHERE status = "rejected"')->fetchColumn();
            
            // Issue statistics
            $stats['total_issues'] = (int)$pdo->query('SELECT COUNT(*) FROM issues')->fetchColumn();
            $stats['open_issues'] = (int)$pdo->query('SELECT COUNT(*) FROM issues WHERE status = "open"')->fetchColumn();
            $stats['in_progress_issues'] = (int)$pdo->query('SELECT COUNT(*) FROM issues WHERE status = "in_progress"')->fetchColumn();
            $stats['resolved_issues'] = (int)$pdo->query('SELECT COUNT(*) FROM issues WHERE status = "resolved"')->fetchColumn();
            $stats['closed_issues'] = (int)$pdo->query('SELECT COUNT(*) FROM issues WHERE status = "closed"')->fetchColumn();
            
            // Voting and commenting statistics
            $stats['total_votes'] = (int)$pdo->query('SELECT COUNT(*) FROM issue_votes')->fetchColumn();
            $stats['total_comments'] = (int)$pdo->query('SELECT COUNT(*) FROM issue_comments')->fetchColumn();
            $stats['flagged_content'] = (int)$pdo->query('SELECT COUNT(*) FROM issue_comments WHERE is_flagged = 1')->fetchColumn();
            
            // Growth statistics (last 7 days)
            $stats['new_users_week'] = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();
            $stats['new_issues_week'] = (int)$pdo->query('SELECT COUNT(*) FROM issues WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();
            $stats['new_votes_week'] = (int)$pdo->query('SELECT COUNT(*) FROM issue_votes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();
            $stats['new_comments_week'] = (int)$pdo->query('SELECT COUNT(*) FROM issue_comments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();
            
            // Growth statistics (last 30 days)
            $stats['new_users_month'] = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
            $stats['new_issues_month'] = (int)$pdo->query('SELECT COUNT(*) FROM issues WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
            
            $response['success'] = true;
            $response['data'] = $stats;
            break;
            
        case 'trending_issues':
            // Get trending issues (most voted in last 7 and 30 days)
            $trending = [];
            
            // Last 7 days
            $stmt = $pdo->prepare("
                SELECT i.id, i.title, i.category, i.priority, i.status,
                       u.full_name as author_name,
                       COUNT(iv.id) as vote_count,
                       COUNT(ic.id) as comment_count
                FROM issues i
                JOIN users u ON i.user_id = u.id
                LEFT JOIN issue_votes iv ON i.id = iv.issue_id AND iv.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                LEFT JOIN issue_comments ic ON i.id = ic.issue_id AND ic.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                WHERE i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY i.id, i.title, i.category, i.priority, i.status, u.full_name
                ORDER BY vote_count DESC, comment_count DESC
                LIMIT 10
            ");
            $stmt->execute();
            $trending['last_7_days'] = $stmt->fetchAll();
            
            // Last 30 days
            $stmt = $pdo->prepare("
                SELECT i.id, i.title, i.category, i.priority, i.status,
                       u.full_name as author_name,
                       COUNT(iv.id) as vote_count,
                       COUNT(ic.id) as comment_count
                FROM issues i
                JOIN users u ON i.user_id = u.id
                LEFT JOIN issue_votes iv ON i.id = iv.issue_id AND iv.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                LEFT JOIN issue_comments ic ON i.id = ic.issue_id AND ic.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                WHERE i.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY i.id, i.title, i.category, i.priority, i.status, u.full_name
                ORDER BY vote_count DESC, comment_count DESC
                LIMIT 10
            ");
            $stmt->execute();
            $trending['last_30_days'] = $stmt->fetchAll();
            
            $response['success'] = true;
            $response['data'] = $trending;
            break;
            
        case 'user_activity':
            // Get most active users
            $stmt = $pdo->prepare("
                SELECT u.id, u.full_name, u.special_login_id, u.email,
                       COUNT(DISTINCT i.id) as issues_created,
                       COUNT(DISTINCT iv.id) as votes_cast,
                       COUNT(DISTINCT ic.id) as comments_made,
                       (COUNT(DISTINCT i.id) + COUNT(DISTINCT iv.id) + COUNT(DISTINCT ic.id)) as total_activity,
                       u.last_login
                FROM users u
                LEFT JOIN issues i ON u.id = i.user_id
                LEFT JOIN issue_votes iv ON u.id = iv.user_id
                LEFT JOIN issue_comments ic ON u.id = ic.user_id
                WHERE u.is_verified = 1
                GROUP BY u.id, u.full_name, u.special_login_id, u.email, u.last_login
                ORDER BY total_activity DESC
                LIMIT 20
            ");
            $stmt->execute();
            $user_activity = $stmt->fetchAll();
            
            $response['success'] = true;
            $response['data'] = $user_activity;
            break;
            
        case 'community_analytics':
            // Get issues by community/city
            $stmt = $pdo->prepare("
                SELECT c.id, c.name as city_name, c.state,
                       COUNT(DISTINCT u.id) as total_users,
                       COUNT(DISTINCT i.id) as total_issues,
                       COUNT(DISTINCT CASE WHEN i.status = 'resolved' THEN i.id END) as resolved_issues,
                       COUNT(DISTINCT CASE WHEN i.status = 'open' THEN i.id END) as open_issues,
                       COUNT(DISTINCT iv.id) as total_votes,
                       COUNT(DISTINCT ic.id) as total_comments
                FROM cities c
                LEFT JOIN users u ON c.id = u.city_id AND u.is_verified = 1
                LEFT JOIN issues i ON c.id = i.city_id
                LEFT JOIN issue_votes iv ON i.id = iv.issue_id
                LEFT JOIN issue_comments ic ON i.id = ic.issue_id
                GROUP BY c.id, c.name, c.state
                ORDER BY total_issues DESC, total_users DESC
            ");
            $stmt->execute();
            $community_analytics = $stmt->fetchAll();
            
            $response['success'] = true;
            $response['data'] = $community_analytics;
            break;
            
        case 'category_breakdown':
            // Get issues by category
            $stmt = $pdo->prepare("
                SELECT i.category,
                       COUNT(*) as total_issues,
                       COUNT(CASE WHEN i.status = 'open' THEN 1 END) as open_issues,
                       COUNT(CASE WHEN i.status = 'in_progress' THEN 1 END) as in_progress_issues,
                       COUNT(CASE WHEN i.status = 'resolved' THEN 1 END) as resolved_issues,
                       COUNT(CASE WHEN i.status = 'closed' THEN 1 END) as closed_issues,
                       AVG(i.votes_count) as avg_votes,
                       AVG(i.comments_count) as avg_comments
                FROM issues i
                GROUP BY i.category
                ORDER BY total_issues DESC
            ");
            $stmt->execute();
            $category_breakdown = $stmt->fetchAll();
            
            $response['success'] = true;
            $response['data'] = $category_breakdown;
            break;
            
        case 'growth_chart':
            // Get growth data for charts (last 30 days)
            $growth_data = [];
            
            for ($i = 29; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(DISTINCT u.id) as new_users,
                        COUNT(DISTINCT i.id) as new_issues,
                        COUNT(DISTINCT iv.id) as new_votes,
                        COUNT(DISTINCT ic.id) as new_comments
                    FROM (
                        SELECT DATE(created_at) as date, id FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        UNION ALL
                        SELECT DATE(created_at) as date, id FROM issues WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        UNION ALL
                        SELECT DATE(created_at) as date, id FROM issue_votes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        UNION ALL
                        SELECT DATE(created_at) as date, id FROM issue_comments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ) as activity
                    WHERE date = ?
                ");
                $stmt->execute([$date]);
                $day_data = $stmt->fetch();
                
                $growth_data[] = [
                    'date' => $date,
                    'new_users' => (int)$day_data['new_users'],
                    'new_issues' => (int)$day_data['new_issues'],
                    'new_votes' => (int)$day_data['new_votes'],
                    'new_comments' => (int)$day_data['new_comments']
                ];
            }
            
            $response['success'] = true;
            $response['data'] = $growth_data;
            break;
            
        default:
            $response['message'] = 'Invalid action';
    }
    
} catch (Exception $e) {
    $response['message'] = 'An error occurred';
    error_log("Admin stats API error: " . $e->getMessage());
}

echo json_encode($response);
?>
