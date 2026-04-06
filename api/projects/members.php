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
if (!isset($data["project_id"]) || !is_numeric($data["project_id"])) {
    http_response_code(400);
    echo json_encode(["error" => "Project ID is required"]);
    exit;
}

if (!isset($data["username"]) || trim($data["username"]) === "") {
    http_response_code(400);
    echo json_encode(["error" => "Username is required"]);
    exit;
}

$projectId = (int) $data["project_id"];
$username  = trim($data["username"]);
$userId    = $_SESSION["user_id"];

try {
    // 2. Check the project exists
    $checkProject = $pdo->prepare("
        SELECT id, owner_id FROM projects WHERE id = :id
    ");
    $checkProject->execute([":id" => $projectId]);
    $project = $checkProject->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        http_response_code(404);
        echo json_encode(["error" => "Project not found"]);
        exit;
    }

    // 3. Authorization — only the owner can add members
    if ((int) $project["owner_id"] !== $userId) {
        http_response_code(403);
        echo json_encode(["error" => "Only the project owner can add members"]);
        exit;
    }

    // 4. Find the user by username
    $findUser = $pdo->prepare("
        SELECT id, username FROM users WHERE username = :username
    ");
    $findUser->execute([":username" => $username]);
    $newMember = $findUser->fetch(PDO::FETCH_ASSOC);

    if (!$newMember) {
        http_response_code(404);
        echo json_encode(["error" => "User not found"]);
        exit;
    }

    // 5. Check if already a member
    $checkMember = $pdo->prepare("
        SELECT 1 FROM project_members
        WHERE project_id = :project_id AND user_id = :user_id
    ");
    $checkMember->execute([
        ":project_id" => $projectId,
        ":user_id"    => $newMember["id"],
    ]);

    if ($checkMember->fetch()) {
        http_response_code(409);
        echo json_encode(["error" => "User is already a member of this project"]);
        exit;
    }

    // 6. INSERT into project_members
    $insert = $pdo->prepare("
        INSERT INTO project_members (project_id, user_id, role)
        VALUES (:project_id, :user_id, 'member')
    ");
    $insert->execute([
        ":project_id" => $projectId,
        ":user_id"    => $newMember["id"],
    ]);

    http_response_code(201);
    echo json_encode([
        "message" => "Member added successfully",
        "member"  => [
            "id"       => (int) $newMember["id"],
            "username" => $newMember["username"],
            "role"     => "member",
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Failed to add member"]);
}
