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

// 1. Check required fields
if (!isset($data["project_id"]) || !is_numeric($data["project_id"])) {
    http_response_code(400);
    echo json_encode(["error" => "project_id is required"]);
    exit;
}

if (!isset($data["title"]) || trim($data["title"]) === "") {
    http_response_code(400);
    echo json_encode(["error" => "Title is required"]);
    exit;
}

$projectId = (int) $data["project_id"];
$title     = trim($data["title"]);
$priority  = $data["priority"]  ?? "medium";
$dueDate   = $data["due_date"]  ?? null;
$userId    = $_SESSION["user_id"];

// 2. Validate
if (strlen($title) > 150) {
    http_response_code(400);
    echo json_encode(["error" => "Title must be 150 characters or less"]);
    exit;
}

$validPriorities = ["low", "medium", "high"];
if (!in_array($priority, $validPriorities)) {
    http_response_code(400);
    echo json_encode(["error" => "Priority must be low, medium, or high"]);
    exit;
}

if ($dueDate !== null && !strtotime($dueDate)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid due_date format"]);
    exit;
}

try {
    // 3. Verify the user is a member of this project
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

    // 4. INSERT
    $stmt = $pdo->prepare("
        INSERT INTO tasks (title, status, priority, project_id, due_date)
        VALUES (:title, 'todo', :priority, :project_id, :due_date)
    ");
    $stmt->execute([
        ":title"      => $title,
        ":priority"   => $priority,
        ":project_id" => $projectId,
        ":due_date"   => $dueDate,
    ]);

    $newId = (int) $pdo->lastInsertId();

    // 5. Fetch the new task to return it
    $fetch = $pdo->prepare("
        SELECT id, title, status, priority, due_date,
               assigned_to, created_at, updated_at
        FROM tasks WHERE id = :id
    ");
    $fetch->execute([":id" => $newId]);
    $task = $fetch->fetch(PDO::FETCH_ASSOC);

    $task["id"]          = (int) $task["id"];
    $task["assigned_to"] = null;
    $task["assigned_username"] = null;

    http_response_code(201);
    echo json_encode([
        "message" => "Task created successfully",
        "task"    => $task,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to create task"]);
}