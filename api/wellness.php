<?php

/**
 * Wellness and Sustainability API
 * Handles eco points, wellness logs, badges, and challenges
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $user_id = $_SESSION['user_id'] ?? null;

    if (!$user_id) {
        throw new Exception('Authentication required');
    }

    switch ($action) {
        case 'log_wellness':
            $log_date = $_POST['log_date'] ?? date('Y-m-d');
            $mood = $_POST['mood'] ?? null;
            $stress_level = intval($_POST['stress_level'] ?? 0);
            $energy_level = intval($_POST['energy_level'] ?? 0);
            $sleep_hours = floatval($_POST['sleep_hours'] ?? 0);
            $notes = sanitize($_POST['notes'] ?? '');

            // Insert or update wellness log
            $existing = db()->fetchOne(
                "SELECT id FROM wellness_logs WHERE user_id = ? AND log_date = ?",
                [$user_id, $log_date]
            );

            if ($existing) {
                db()->update('wellness_logs', [
                    'mood' => $mood,
                    'stress_level' => $stress_level,
                    'energy_level' => $energy_level,
                    'sleep_hours' => $sleep_hours,
                    'notes' => $notes
                ], 'id = ?', [$existing['id']]);
            } else {
                db()->insert('wellness_logs', [
                    'user_id' => $user_id,
                    'log_date' => $log_date,
                    'mood' => $mood,
                    'stress_level' => $stress_level,
                    'energy_level' => $energy_level,
                    'sleep_hours' => $sleep_hours,
                    'notes' => $notes
                ]);
            }

            // Update wellness score
            update_wellness_score($user_id);

            $response = [
                'success' => true,
                'message' => 'Wellness log saved successfully'
            ];
            break;

        case 'get_wellness_logs':
            $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $end_date = $_GET['end_date'] ?? date('Y-m-d');

            $logs = db()->fetchAll(
                "SELECT * FROM wellness_logs
                 WHERE user_id = ? AND log_date BETWEEN ? AND ?
                 ORDER BY log_date DESC",
                [$user_id, $start_date, $end_date]
            );

            $response = [
                'success' => true,
                'message' => 'Wellness logs retrieved',
                'data' => $logs
            ];
            break;

        case 'add_eco_points':
            $metric_type = $_POST['metric_type'] ?? '';
            $points = intval($_POST['points'] ?? 0);
            $description = sanitize($_POST['description'] ?? '');

            db()->insert('sustainability_metrics', [
                'user_id' => $user_id,
                'metric_type' => $metric_type,
                'points_earned' => $points,
                'description' => $description
            ]);

            // Update user's total eco points
            update_eco_points($user_id);

            // Check for badge achievements
            check_badge_achievements($user_id, 'eco');

            $response = [
                'success' => true,
                'message' => "$points eco points added",
                'data' => ['points_added' => $points]
            ];
            break;

        case 'get_user_scores':
            $scores = db()->fetchOne(
                "SELECT * FROM user_wellness_scores WHERE user_id = ?",
                [$user_id]
            );

            if (!$scores) {
                // Initialize scores
                db()->insert('user_wellness_scores', [
                    'user_id' => $user_id,
                    'eco_points' => 0,
                    'wellness_score' => 0,
                    'badges_earned' => json_encode([])
                ]);

                $scores = db()->fetchOne(
                    "SELECT * FROM user_wellness_scores WHERE user_id = ?",
                    [$user_id]
                );
            }

            // Get badge details
            $badge_ids = json_decode($scores['badges_earned'] ?? '[]', true);
            $badges = [];
            if (!empty($badge_ids)) {
                $placeholders = implode(',', array_fill(0, count($badge_ids), '?'));
                $badges = db()->fetchAll(
                    "SELECT * FROM gamification_badges WHERE id IN ($placeholders)",
                    $badge_ids
                );
            }

            $response = [
                'success' => true,
                'message' => 'User scores retrieved',
                'data' => [
                    'scores' => $scores,
                    'badges' => $badges
                ]
            ];
            break;

        case 'get_leaderboard':
            $type = $_GET['type'] ?? 'eco'; // eco or wellness
            $limit = intval($_GET['limit'] ?? 10);

            $field = $type === 'eco' ? 'eco_points' : 'wellness_score';

            $leaderboard = db()->fetchAll(
                "SELECT uws.*, u.full_name, u.role
                 FROM user_wellness_scores uws
                 JOIN users u ON uws.user_id = u.id
                 ORDER BY uws.$field DESC
                 LIMIT ?",
                [$limit]
            );

            $response = [
                'success' => true,
                'message' => 'Leaderboard retrieved',
                'data' => $leaderboard
            ];
            break;

        case 'create_challenge':
            if ($_SESSION['role'] !== 'admin') {
                throw new Exception('Admin access required');
            }

            $name = sanitize($_POST['name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $goal_type = $_POST['goal_type'] ?? '';
            $goal_target = intval($_POST['goal_target'] ?? 0);
            $reward_points = intval($_POST['reward_points'] ?? 0);

            $challenge_id = db()->insert('sustainability_challenges', [
                'name' => $name,
                'description' => $description,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'goal_type' => $goal_type,
                'goal_target' => $goal_target,
                'reward_points' => $reward_points,
                'created_by' => $user_id
            ]);

            log_activity($user_id, 'create', 'sustainability_challenges', $challenge_id);

            $response = [
                'success' => true,
                'message' => 'Challenge created successfully',
                'data' => ['challenge_id' => $challenge_id]
            ];
            break;

        case 'join_challenge':
            $challenge_id = intval($_POST['challenge_id'] ?? 0);

            // Check if already joined
            $existing = db()->fetchOne(
                "SELECT id FROM challenge_participants WHERE challenge_id = ? AND user_id = ?",
                [$challenge_id, $user_id]
            );

            if ($existing) {
                throw new Exception('Already joined this challenge');
            }

            db()->insert('challenge_participants', [
                'challenge_id' => $challenge_id,
                'user_id' => $user_id,
                'progress' => 0
            ]);

            $response = [
                'success' => true,
                'message' => 'Joined challenge successfully'
            ];
            break;

        case 'update_challenge_progress':
            $challenge_id = intval($_POST['challenge_id'] ?? 0);
            $progress = intval($_POST['progress'] ?? 0);

            $participant = db()->fetchOne(
                "SELECT * FROM challenge_participants
                 WHERE challenge_id = ? AND user_id = ?",
                [$challenge_id, $user_id]
            );

            if (!$participant) {
                throw new Exception('Not participating in this challenge');
            }

            $challenge = db()->fetchOne(
                "SELECT * FROM sustainability_challenges WHERE id = ?",
                [$challenge_id]
            );

            $completed = $progress >= $challenge['goal_target'];

            db()->update('challenge_participants', [
                'progress' => $progress,
                'completed' => $completed,
                'completed_at' => $completed ? date('Y-m-d H:i:s') : null
            ], 'id = ?', [$participant['id']]);

            if ($completed && !$participant['completed']) {
                // Award points
                add_eco_points(
                    $user_id,
                    'challenge_completion',
                    $challenge['reward_points'],
                    "Completed challenge: {$challenge['name']}"
                );
            }

            $response = [
                'success' => true,
                'message' => $completed ? 'Challenge completed!' : 'Progress updated',
                'data' => [
                    'progress' => $progress,
                    'completed' => $completed
                ]
            ];
            break;

        case 'get_active_challenges':
            $today = date('Y-m-d');
            $challenges = db()->fetchAll(
                "SELECT c.*,
                        (SELECT COUNT(*) FROM challenge_participants WHERE challenge_id = c.id) as participant_count,
                        cp.progress, cp.completed
                 FROM sustainability_challenges c
                 LEFT JOIN challenge_participants cp ON c.id = cp.challenge_id AND cp.user_id = ?
                 WHERE c.is_active = 1 AND c.start_date <= ? AND c.end_date >= ?
                 ORDER BY c.start_date DESC",
                [$user_id, $today, $today]
            );

            $response = [
                'success' => true,
                'message' => 'Active challenges retrieved',
                'data' => $challenges
            ];
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);

// Helper functions
function update_wellness_score($user_id)
{
    // Calculate wellness score from recent logs
    $logs = db()->fetchAll(
        "SELECT * FROM wellness_logs
         WHERE user_id = ? AND log_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        [$user_id]
    );

    if (empty($logs)) return;

    $total_score = 0;
    $count = count($logs);

    foreach ($logs as $log) {
        $mood_score = match ($log['mood']) {
            'excellent' => 100,
            'good' => 80,
            'neutral' => 60,
            'stressed' => 40,
            'poor' => 20,
            default => 50
        };

        $stress_score = (10 - $log['stress_level']) * 10;
        $energy_score = $log['energy_level'] * 10;
        $sleep_score = min($log['sleep_hours'] / 8 * 100, 100);

        $daily_score = ($mood_score + $stress_score + $energy_score + $sleep_score) / 4;
        $total_score += $daily_score;
    }

    $wellness_score = $total_score / $count;

    // Update or insert score
    $existing = db()->fetchOne(
        "SELECT id FROM user_wellness_scores WHERE user_id = ?",
        [$user_id]
    );

    if ($existing) {
        db()->update('user_wellness_scores', [
            'wellness_score' => $wellness_score
        ], 'id = ?', [$existing['id']]);
    } else {
        db()->insert('user_wellness_scores', [
            'user_id' => $user_id,
            'wellness_score' => $wellness_score
        ]);
    }
}

function update_eco_points($user_id)
{
    $total = db()->fetchOne(
        "SELECT SUM(points_earned) as total FROM sustainability_metrics WHERE user_id = ?",
        [$user_id]
    );

    $eco_points = $total['total'] ?? 0;

    $existing = db()->fetchOne(
        "SELECT id FROM user_wellness_scores WHERE user_id = ?",
        [$user_id]
    );

    if ($existing) {
        db()->update('user_wellness_scores', [
            'eco_points' => $eco_points
        ], 'id = ?', [$existing['id']]);
    } else {
        db()->insert('user_wellness_scores', [
            'user_id' => $user_id,
            'eco_points' => $eco_points
        ]);
    }
}

function check_badge_achievements($user_id, $type)
{
    // Check if user qualifies for any badges
    $scores = db()->fetchOne(
        "SELECT * FROM user_wellness_scores WHERE user_id = ?",
        [$user_id]
    );

    if (!$scores) return;

    $badges = db()->fetchAll(
        "SELECT * FROM gamification_badges WHERE badge_type = ? AND is_active = 1",
        [$type]
    );

    foreach ($badges as $badge) {
        $criteria = json_decode($badge['criteria'], true);
        $qualified = false;

        if ($type === 'eco' && $scores['eco_points'] >= $criteria['min_points']) {
            $qualified = true;
        } elseif ($type === 'wellness' && $scores['wellness_score'] >= $criteria['min_score']) {
            $qualified = true;
        }

        if ($qualified) {
            // Check if already earned
            $existing = db()->fetchOne(
                "SELECT id FROM user_badges WHERE user_id = ? AND badge_id = ?",
                [$user_id, $badge['id']]
            );

            if (!$existing) {
                db()->insert('user_badges', [
                    'user_id' => $user_id,
                    'badge_id' => $badge['id']
                ]);

                // Update badges_earned JSON
                $badge_ids = json_decode($scores['badges_earned'] ?? '[]', true);
                $badge_ids[] = $badge['id'];

                db()->update('user_wellness_scores', [
                    'badges_earned' => json_encode(array_unique($badge_ids))
                ], 'user_id = ?', [$user_id]);
            }
        }
    }
}

function add_eco_points($user_id, $metric_type, $points, $description)
{
    db()->insert('sustainability_metrics', [
        'user_id' => $user_id,
        'metric_type' => $metric_type,
        'points_earned' => $points,
        'description' => $description
    ]);

    update_eco_points($user_id);
    check_badge_achievements($user_id, 'eco');
}
