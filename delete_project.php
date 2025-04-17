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
$project_id = isset($_POST['projectId']) ? intval($_POST['projectId']) : 0;

if ($project_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid project ID"]);
    exit;
}

// Check if user owns this project
$check_query = "SELECT 1 FROM project WHERE id = ? AND userId = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("ii", $project_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$is_owner = $check_result->num_rows > 0;
$check_stmt->close();

if (!$is_owner) {
    echo json_encode(["success" => false, "message" => "You don't have permission to delete this project"]);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    $delete_members_query = "DELETE FROM appartenir WHERE projectId = ?";
    $delete_members_stmt = $conn->prepare($delete_members_query);
    $delete_members_stmt->bind_param("i", $project_id);
    $delete_members_stmt->execute();
    $delete_members_stmt->close();
    
    // Delete associated tasks
    $delete_tasks_query = "DELETE FROM task WHERE projectId = ?";
    $delete_tasks_stmt = $conn->prepare($delete_tasks_query);
    $delete_tasks_stmt->bind_param("i", $project_id);
    $delete_tasks_stmt->execute();
    $delete_tasks_stmt->close();
    
    // Delete the project
    $delete_project_query = "DELETE FROM project WHERE id = ?";
    $delete_project_stmt = $conn->prepare($delete_project_query);
    $delete_project_stmt->bind_param("i", $project_id);
    $delete_project_stmt->execute();
    $delete_project_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(["success" => true, "message" => "Project deleted successfully"]);
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Error deleting project: " . $e->getMessage()]);
}

$conn->close();
?>
