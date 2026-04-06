<?php

header("Content-Type: application/json");

require "../config/session_check.php";
require "../config/db.php";

$userId = $_SESSION["user_id"];

// Fetch all projects where the logged-in user is a member (or owner)
// For each project, also return:
//   - task_count  : total tasks
//   - done_count  : completed tasks
//   - members     : up to 5 members (id + username)

$sql = "
    SELECT
        p.id,
        p.title,
        p.description,
        p.owner_id,
        p.created_at,

        -- Task counts
        COUNT(DISTINCT t.id)                                    AS task_count,
        COUNT(DISTINCT CASE WHEN t.status = 'done' THEN t.id END) AS done_count,

        -- Members as JSON array (max 5 for the dashboard cards)
        (
            SELECT JSON_ARRAYAGG(
                JSON_OBJECT('id', u2.id, 'username', u2.username)
            )
            FROM (
                SELECT u.id, u.username
                FROM project_members pm2
                JOIN users u ON u.id = pm2.user_id
                WHERE pm2.project_id = p.id
                ORDER BY pm2.role DESC, u.id ASC
                LIMIT 5
            ) u2
        ) AS members

    FROM projects p
    JOIN project_members pm ON pm.project_id = p.id
                            AND pm.user_id   = :user_id
    LEFT JOIN tasks t        ON t.project_id = p.id

    GROUP BY p.id
    ORDER BY p.created_at DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // JSON_ARRAYAGG returns a string — decode it
    $projects = array_map(function ($row) {
        $row['task_count'] = (int) $row['task_count'];
        $row['done_count'] = (int) $row['done_count'];
        $row['members']    = $row['members']
            ? json_decode($row['members'], true)
            : [];
        return $row;
    }, $rows);

    echo json_encode(['projects' => $projects]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch projects']);
}