<?php

header("Content-Type: application/json");

require "../config/session_check.php";
require "../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "DELETE") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$data   = json_decode(file_get_contents("php://input"), true);
$taskId = isset($data["id"]) && is_numeric($data["id"]) ? (int) $data["id"] : null;
$userId = $_SESSION["user_id"];

if (!$taskId) {
    http_response_code(400);
    echo json_encode(["error" => "Task ID is required"]);
    exit;
}

try {
    // Verify task exists and user is a project member
    $check = $pdo->prepare("
        SELECT t.id, p.owner_id
        FROM tasks t
        JOIN projects p ON p.id = t.project_id
        JOIN project_members pm ON pm.project_id = t.project_id
                                AND pm.user_id   = :user_id
        WHERE t.id = :task_id
    ");
    $check->execute([":task_id" => $taskId, ":user_id" => $userId]);
    $row = $check->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(["error" => "Task not found or access denied"]);
        exit;
    }

    // Only project owner can delete tasks
    if ((int) $row["owner_id"] !== $userId) {
        http_response_code(403);
        echo json_encode(["error" => "Only the project owner can delete tasks"]);
        exit;
    }

    // DELETE — CASCADE removes comments automatically
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = :id");
    $stmt->execute([":id" => $taskId]);

    echo json_encode(["message" => "Task deleted successfully"]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to delete task"]);
}