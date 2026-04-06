<?php

header("Content-Type: application/json");

require "../config/session_check.php";
require "../config/db.php";

// Only DELETE is allowed
if ($_SERVER["REQUEST_METHOD"] !== "DELETE") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

// 1. Check required field
if (!isset($data["id"]) || !is_numeric($data["id"])) {
    http_response_code(400);
    echo json_encode(["error" => "Project ID is required"]);
    exit;
}

$projectId = (int) $data["id"];
$userId    = $_SESSION["user_id"];

try {
    // 2. Check the project exists
    $check = $pdo->prepare("
        SELECT id, owner_id FROM projects WHERE id = :id
    ");
    $check->execute([":id" => $projectId]);
    $project = $check->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        http_response_code(404);
        echo json_encode(["error" => "Project not found"]);
        exit;
    }

    // 3. Authorization check — only the owner can delete
    if ((int) $project["owner_id"] !== $userId) {
        http_response_code(403);
        echo json_encode(["error" => "Only the project owner can delete this project"]);
        exit;
    }

    // 4. DELETE — CASCADE in schema automatically removes:
    //    project_members, tasks, and comments (via tasks)
    $delete = $pdo->prepare("DELETE FROM projects WHERE id = :id");
    $delete->execute([":id" => $projectId]);

    echo json_encode(["message" => "Project deleted successfully"]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to delete project"]);
}
