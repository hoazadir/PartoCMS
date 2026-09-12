<?php
require_once __DIR__ . '/../config.php';

jsonHeader();

if (!isLoggedIn()) {
    jsonResponse(['status' => 'error', 'message' => 'دسترسی غیرمجاز'], 401);
}

$pdo = getDB();
$action = $_GET['action'] ?? '';
$range = (int) ($_GET['range'] ?? 30); // روز

try {
    switch ($action) {
        // ============ نمودار بازدید روزانه ============
        case 'views_chart':
            $stmt = $pdo->prepare("
                SELECT DATE(viewed_at) as date, COUNT(*) as count
                FROM views
                WHERE viewed_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(viewed_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$range]);
            $data = $stmt->fetchAll();

            // پر کردن روزهای خالی
            $result = [];
            $dates = [];
            foreach ($data as $row) {
                $dates[$row['date']] = (int) $row['count'];
            }

            for ($i = $range - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $result[] = [
                    'date' => $date,
                    'label' => date('m/d', strtotime($date)),
                    'count' => $dates[$date] ?? 0,
                ];
            }

            jsonResponse(['status' => 'success', 'data' => $result]);
            break;

        // ============ پربازدیدترین مقالات ============
        case 'top_posts':
            $stmt = $pdo->prepare("
                SELECT ci.id, ci.title, ci.views,
                    (SELECT COUNT(*) FROM views WHERE content_id = ci.id) as total_views,
                    (SELECT COUNT(*) FROM comments WHERE content_id = ci.id AND status='approved') as comments_count
                FROM content_items ci
                WHERE ci.status = 'published'
                ORDER BY ci.views DESC
                LIMIT 10
            ");
            $stmt->execute();
            jsonResponse(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        // ============ آمار کاربران ============
        case 'user_stats':
            $stmt = $pdo->query("
                SELECT r.name as role_name, r.slug as role_slug, COUNT(u.id) as count
                FROM roles r
                LEFT JOIN users u ON u.role_id = r.id
                GROUP BY r.id
                ORDER BY count DESC
            ");
            $roles = $stmt->fetchAll();

            $activeUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
            $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $newThisMonth = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

            jsonResponse([
                'status' => 'success',
                'data' => [
                    'roles' => $roles,
                    'total' => (int) $totalUsers,
                    'active' => (int) $activeUsers,
                    'new_month' => (int) $newThisMonth,
                ]
            ]);
            break;

        // ============ آمار محتوا ============
        case 'content_stats':
            $stats = [
                'total_posts' => (int) $pdo->query("SELECT COUNT(*) FROM content_items")->fetchColumn(),
                'published' => (int) $pdo->query("SELECT COUNT(*) FROM content_items WHERE status='published'")->fetchColumn(),
                'drafts' => (int) $pdo->query("SELECT COUNT(*) FROM content_items WHERE status='draft'")->fetchColumn(),
                'total_views' => (int) $pdo->query("SELECT COUNT(*) FROM views")->fetchColumn(),
                'total_comments' => (int) $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
                'pending_comments' => (int) $pdo->query("SELECT COUNT(*) FROM comments WHERE status='pending'")->fetchColumn(),
            ];

            // آمار دسته‌بندی
            $categories = $pdo->query("
                SELECT c.name, c.icon, c.color, COUNT(ci.id) as count
                FROM categories c
                LEFT JOIN content_items ci ON ci.category_id = c.id AND ci.status='published'
                WHERE c.is_active = 1
                GROUP BY c.id
                HAVING count > 0
                ORDER BY count DESC
            ")->fetchAll();

            jsonResponse([
                'status' => 'success',
                'data' => array_merge($stats, ['categories' => $categories])
            ]);
            break;

        // ============ آمار بازدید ماهانه ============
        case 'monthly_views':
            $stmt = $pdo->query("
                SELECT 
                    DATE_FORMAT(viewed_at, '%Y-%m') as month,
                    COUNT(*) as count
                FROM views
                WHERE viewed_at > DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(viewed_at, '%Y-%m')
                ORDER BY month ASC
            ");
            jsonResponse(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        // ============ آمار ساعتی ============
        case 'hourly_views':
            $stmt = $pdo->query("
                SELECT HOUR(viewed_at) as hour, COUNT(*) as count
                FROM views
                WHERE viewed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY HOUR(viewed_at)
                ORDER BY hour ASC
            ");
            jsonResponse(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        // ============ آمار کلی ============
        case 'summary':
            $today = (int) $pdo->query("SELECT COUNT(*) FROM views WHERE DATE(viewed_at) = CURDATE()")->fetchColumn();
            $yesterday = (int) $pdo->query("SELECT COUNT(*) FROM views WHERE DATE(viewed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
            $week = (int) $pdo->query("SELECT COUNT(*) FROM views WHERE viewed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
            $month = (int) $pdo->query("SELECT COUNT(*) FROM views WHERE viewed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

            jsonResponse([
                'status' => 'success',
                'data' => [
                    'today' => $today,
                    'yesterday' => $yesterday,
                    'week' => $week,
                    'month' => $month,
                    'growth' => $yesterday > 0 ? round((($today - $yesterday) / $yesterday) * 100, 1) : 0,
                ]
            ]);
            break;

        // ============ بازدید یک مقاله ============
        case 'post_views':
            $postId = (int) ($_GET['post_id'] ?? 0);
            if (!$postId) jsonResponse(['status' => 'error', 'message' => 'شناسه مقاله لازم است'], 400);

            $stmt = $pdo->prepare("
                SELECT DATE(viewed_at) as date, COUNT(*) as count
                FROM views
                WHERE content_id = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(viewed_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$postId, $range]);
            jsonResponse(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        // ============ لاگ فعالیت‌ها ============
        case 'activity_log':
            $limit = min((int) ($_GET['limit'] ?? 20), 100);
            $stmt = $pdo->prepare("
                SELECT a.*, u.username
                FROM activity_log a
                LEFT JOIN users u ON u.id = a.user_id
                ORDER BY a.created_at DESC
                LIMIT {$limit}
            ");
            $stmt->execute();
            jsonResponse(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        default:
            jsonResponse(['status' => 'error', 'message' => 'اکشن نامعتبر'], 400);
    }
} catch (PDOException $e) {
    jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
}
