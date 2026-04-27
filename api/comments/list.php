<?php

header("Content-Type: application/json");

require "../../config/session_check.php";
require "../../config/db.php";

if (!isset($_GET["task_id"]) || !is_numeric($_GET["task_id"])) {
    http_response_code(400);
    echo json_encode(["error" => "task_id is required"]);
    exit;
}

$taskId = (int) $_GET["task_id"];
$userId = $_SESSION["user_id"];

try {
    // Verify user is a member of the project that owns this task
    $access = $pdo->prepare("
        SELECT 1
        FROM tasks t
        JOIN project_members pm ON pm.project_id = t.project_id
                                AND pm.user_id   = :user_id
        WHERE t.id = :task_id
    ");
    $access->execute([":task_id" => $taskId, ":user_id" => $userId]);

    if (!$access->fetch()) {
        http_response_code(403);
        echo json_encode(["error" => "Access denied"]);
        exit;
    }

    // Fetch comments with username, oldest first
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.task_id,
            c.content,
            c.created_at,
            u.id       AS user_id,
            u.username AS username
        FROM comments c
        JOIN users u ON u.id = c.user_id
        WHERE c.task_id = :task_id
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([":task_id" => $taskId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $comments = array_map(function ($row) {
        $row["id"]      = (int) $row["id"];
        $row["task_id"] = (int) $row["task_id"];
        $row["user_id"] = (int) $row["user_id"];
        return $row;
    }, $rows);

    echo json_encode(["comments" => $comments]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to fetch comments"]);
}