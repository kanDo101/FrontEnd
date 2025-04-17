<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "User not logged in"]);
    exit;
}

// Get form data directly
$project_id = isset($_POST['projectId']) ? intval($_POST['projectId']) : 0;
$name = isset($_POST['projectName']) ? trim($_POST['projectName']) : '';
$description = isset($_POST['projectDescription']) ? trim($_POST['projectDescription']) : '';
$dueDate = isset($_POST['dueDate']) ? trim($_POST['dueDate']) : '';

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "todo_kanban_db";
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// Update the project without any complex checks for now
$update_query = "UPDATE project SET name = ?, description = ?, dueDate = ? WHERE id = ?";
$update_stmt = $conn->prepare($update_query);
$update_stmt->bind_param("sssi", $name, $description, $dueDate, $project_id);

if ($update_stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Project updated successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Database error: " . $conn->error]);
}

$update_stmt->close();
$conn->close();
?>
