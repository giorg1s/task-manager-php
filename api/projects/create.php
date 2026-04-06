<?php

header("Content-Type: application/json");

require "../config/session_check.php";
require "../config/db.php";

// Only POST is allowed
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

// 1. Check required fields
if (!isset($data["title"]) || trim($data["title"]) === "") {
    http_response_code(400);
    echo json_encode(["error" => "Title is required"]);
    exit;
}

$title       = trim($data["title"]);
$description = trim($data["description"] ?? "");
$ownerId     = $_SESSION["user_id"];

// 2. Validation
if (strlen($title) > 100) {
    http_response_code(400);
    echo json_encode(["error" => "Title must be 100 characters or less"]);
    exit;
}

// 3. INSERT — the after_project_insert trigger automatically
// adds the owner to project_members with role = 'owner'
try {
    $stmt = $pdo->prepare("
        INSERT INTO projects (title, description, owner_id)
        VALUES (:title, :description, :owner_id)
    ");

    $stmt->execute([
        ":title"       => $title,
        ":description" => $description,
        ":owner_id"    => $ownerId,
    ]);

    $newId = (int) $pdo->lastInsertId();

    // 4. Fetch the new project to return it in the response
    $fetch = $pdo->prepare("
        SELECT id, title, description, owner_id, created_at
        FROM projects
        WHERE id = :id
    ");
    $fetch->execute([":id" => $newId]);
    $project = $fetch->fetch(PDO::FETCH_ASSOC);

    // 5. Add extra fields expected by dashboard.js
    $project["task_count"] = 0;
    $project["done_count"] = 0;
    $project["members"]    = [[
        "id"       => (int) $ownerId,
        "username" => $_SESSION["username"],
    ]];

    http_response_code(201);
    echo json_encode([
        "message" => "Project created successfully",
        "project" => $project,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to create project"]);
}
