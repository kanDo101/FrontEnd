<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "User not logged in"]);
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "todo_kanban_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$user_id = $_SESSION["user_id"];
$project_id = isset($_GET['projectId']) ? intval($_GET['projectId']) : 0;

if ($project_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid project ID"]);
    exit;
}

// Get project details
$query = "SELECT * FROM project WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$project = $result->fetch_assoc();
$stmt->close();

if (!$project) {
    echo json_encode(["success" => false, "message" => "Project not found"]);
    exit;
}

// Check if user can edit this project (owner or collaborator)
$check_query = "SELECT 1 FROM project WHERE id = ? AND userId = ? 
                UNION 
                SELECT 1 FROM appartenir WHERE projectId = ? AND userId = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("iiii", $project_id, $user_id, $project_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$has_access = $check_result->num_rows > 0;
$check_stmt->close();

if (!$has_access) {
    echo json_encode(["success" => false, "message" => "You don't have permission to edit this project"]);
    exit;
}

// Get project members
$members_query = "SELECT u.id, u.username, u.photo FROM user u 
                  JOIN appartenir a ON u.id = a.userId 
                  WHERE a.projectId = ?";
$members_stmt = $conn->prepare($members_query);
$members_stmt->bind_param("i", $project_id);
$members_stmt->execute();
$members_result = $members_stmt->get_result();

$members = [];
while ($member = $members_result->fetch_assoc()) {
    // Set default photo if none exists
    if (!$member['photo']) {
        $member['photo'] = "https://upload.wikimedia.org/wikipedia/commons/a/ac/Default_pfp.jpg";
    }
    $members[] = $member;
}

$members_stmt->close();

// Format the project data
$formatted_project = [
    "id" => $project['id'],
    "name" => $project['name'],
    "description" => $project['description'],
    "dueDate" => date('Y-m-d', strtotime($project['dueDate']))
];

echo json_encode([
    "success" => true,
    "project" => $formatted_project,
    "members" => $members
]);

$conn->close();
?>
