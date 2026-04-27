<?php

header("Content-Type: application/json");

require "../config/session_check.php";
require "../config/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

// 1. Check required field
if (!isset($data["id"]) || !is_numeric($data["id"])) {
    http_response_code(400);
    echo json_encode(["error" => "Task ID is required"]);
    exit;
}

$taskId = (int) $data["id"];
$userId = $_SESSION["user_id"];

$validStatuses   = ["todo", "in_progress", "done"];
$validPriorities = ["low", "medium", "high"];

try {
    // 2. Fetch task + verify access via project membership
    $check = $pdo->prepare("
        SELECT t.id, t.project_id, t.status, t.priority,
               t.assigned_to, t.title
        FROM tasks t
        JOIN project_members pm ON pm.project_id = t.project_id
                                AND pm.user_id   = :user_id
        WHERE t.id = :task_id
    ");
    $check->execute([":task_id" => $taskId, ":user_id" => $userId]);
    $task = $check->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        http_response_code(404);
        echo json_encode(["error" => "Task not found or access denied"]);
        exit;
    }

    // 3. Build dynamic UPDATE — only fields sent in the request
    $fields = [];
    $params = [":task_id" => $taskId];

    if (isset($data["status"])) {
        if (!in_array($data["status"], $validStatuses)) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid status"]);
            exit;
        }
        $fields[] = "status = :status";
        $params[":status"] = $data["status"];
    }

    if (isset($data["priority"])) {
        if (!in_array($data["priority"], $validPriorities)) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid priority"]);
            exit;
        }
        $fields[] = "priority = :priority";
        $params[":priority"] = $data["priority"];
    }

    if (array_key_exists("assigned_to", $data)) {
        // null = unassign
        $fields[] = "assigned_to = :assigned_to";
        $params[":assigned_to"] = $data["assigned_to"] ? (int) $data["assigned_to"] : null;
    }

    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(["error" => "No fields to update"]);
        exit;
    }

    // 4. Execute dynamic UPDATE
    $sql = "UPDATE tasks SET " . implode(", ", $fields) . " WHERE id = :task_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(["message" => "Task updated successfully"]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to update task"]);
}