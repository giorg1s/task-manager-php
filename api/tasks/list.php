<?php

header("Content-Type: application/json");

require "../config/session_check.php";
require "../config/db.php";

// project_id is required as query parameter
if (!isset($_GET["project_id"]) || !is_numeric($_GET["project_id"])) {
    http_response_code(400);
    echo json_encode(["error" => "project_id is required"]);
    exit;
}

$projectId = (int) $_GET["project_id"];
$userId    = $_SESSION["user_id"];

try {
    // Verify the user is a member of this project
    $access = $pdo->prepare("
        SELECT 1 FROM project_members
        WHERE project_id = :project_id AND user_id = :user_id
    ");
    $access->execute([":project_id" => $projectId, ":user_id" => $userId]);

    if (!$access->fetch()) {
        http_response_code(403);
        echo json_encode(["error" => "Access denied"]);
        exit;
    }

    // Fetch all tasks for this project, including assignee username
    $stmt = $pdo->prepare("
        SELECT
            t.id,
            t.title,
            t.status,
            t.priority,
            t.due_date,
            t.assigned_to,
            t.created_at,
            t.updated_at,
            u.username AS assigned_username
        FROM tasks t
        LEFT JOIN users u ON u.id = t.assigned_to
        WHERE t.project_id = :project_id
        ORDER BY
            FIELD(t.status, 'todo', 'in_progress', 'done'),
            FIELD(t.priority, 'high', 'medium', 'low'),
            t.created_at ASC
    ");
    $stmt->execute([":project_id" => $projectId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast numeric fields
    $tasks = array_map(function ($row) {
        $row["id"]          = (int) $row["id"];
        $row["assigned_to"] = $row["assigned_to"] ? (int) $row["assigned_to"] : null;
        return $row;
    }, $rows);

    echo json_encode(["tasks" => $tasks]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to fetch tasks"]);
}