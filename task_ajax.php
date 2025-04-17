<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "todo_kanban_db";

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "User not logged in"]);
    exit;
}

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

$user_id = $_SESSION["user_id"];
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'addTask':
        addTask($conn);
        break;
    case 'updateTask':
        updateTask($conn);
        break;
    case 'deleteTask':
        deleteTask($conn);
        break;
    case 'updateState':
        updateTaskState($conn);
        break;
    case 'getTaskDetails':
        getTaskDetails($conn);
        break;
    default:
        echo json_encode(["success" => false, "message" => "Invalid action"]);
        break;
}

function addTask($conn)
{
    $projectId = isset($_POST['projectId']) ? intval($_POST['projectId']) : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $state = isset($_POST['state']) ? trim($_POST['state']) : 'Pending';
    $assignedTo = isset($_POST['assignedTo']) && !empty($_POST['assignedTo']) ? intval($_POST['assignedTo']) : null;

    if (empty($name) || $projectId <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid task data"]);
        return;
    }

    // Validate state values directly
    $validStates = ['Pending', 'inprogress', 'Completed'];
    if (!in_array($state, $validStates)) {
        echo json_encode(["success" => false, "message" => "Invalid state"]);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO task (projectId, name, description, state, assigned_to) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $projectId, $name, $description, $state, $assignedTo);

    if ($stmt->execute()) {
        $taskId = $conn->insert_id;
        $task = getTaskWithUserInfo($conn, $taskId);
        echo json_encode(["success" => true, "task" => $task]);
    } else {
        echo json_encode(["success" => false, "message" => "Error creating task"]);
    }

    $stmt->close();
}

function updateTask($conn)
{
    $taskId = isset($_POST['taskId']) ? intval($_POST['taskId']) : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $state = isset($_POST['state']) ? trim($_POST['state']) : '';
    $assignedTo = isset($_POST['assignedTo']) && !empty($_POST['assignedTo']) ? intval($_POST['assignedTo']) : null;

    if (empty($name) || $taskId <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid task data"]);
        return;
    }

    // Validate state values directly
    $validStates = ['Pending', 'inprogress', 'Completed'];
    if (!in_array($state, $validStates)) {
        echo json_encode(["success" => false, "message" => "Invalid state"]);
        return;
    }

    $stmt = $conn->prepare("UPDATE task SET name = ?, description = ?, state = ?, assigned_to = ? WHERE id = ?");
    $stmt->bind_param("sssii", $name, $description, $state, $assignedTo, $taskId);

    if ($stmt->execute()) {
        $task = getTaskWithUserInfo($conn, $taskId);
        echo json_encode(["success" => true, "task" => $task]);
    } else {
        echo json_encode(["success" => false, "message" => "Error updating task"]);
    }

    $stmt->close();
}

function deleteTask($conn)
{
    $taskId = isset($_POST['taskId']) ? intval($_POST['taskId']) : 0;

    if ($taskId <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid task ID"]);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM task WHERE id = ?");
    $stmt->bind_param("i", $taskId);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "Error deleting task"]);
    }

    $stmt->close();
}

function updateTaskState($conn) {
    $taskId = isset($_POST['taskId']) ? intval($_POST['taskId']) : 0;
    $state = isset($_POST['state']) ? trim($_POST['state']) : '';
    
    if ($taskId <= 0 || empty($state)) {
        echo json_encode(["success" => false, "message" => "Invalid task data"]);
        return;
    }
    
    // Debug information
    error_log("Updating task $taskId to state: '$state'");
    
    // Validate state values directly
    $validStates = ['Pending', 'inprogress', 'Completed'];
    if (!in_array($state, $validStates)) {
        echo json_encode(["success" => false, "message" => "Invalid state value: $state"]);
        return;
    }
    
    // Use prepared statement to update the state
    $stmt = $conn->prepare("UPDATE task SET state = ? WHERE id = ?");
    $stmt->bind_param("si", $state, $taskId);
    
    if ($stmt->execute()) {
        // Get the updated task from the database to confirm
        $checkQuery = "SELECT state FROM task WHERE id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("i", $taskId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $updatedTask = $checkResult->fetch_assoc();
        $checkStmt->close();
        
        $updatedState = $updatedTask ? $updatedTask['state'] : 'unknown';
        
        echo json_encode([
            "success" => true, 
            "message" => "Task updated to $state", 
            "debug" => ["requested_state" => $state, "updated_state" => $updatedState]
        ]);
    } else {
        echo json_encode([
            "success" => false, 
            "message" => "Error updating task state", 
            "error" => $conn->error
        ]);
    }
    
    $stmt->close();
}


function getTaskDetails($conn)
{
    $taskId = isset($_POST['taskId']) ? intval($_POST['taskId']) : 0;

    if ($taskId <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid task ID"]);
        return;
    }

    $task = getTaskWithUserInfo($conn, $taskId);

    if ($task) {
        // No conversion needed - use the state directly from the database
        echo json_encode(["success" => true, "task" => $task]);
    } else {
        echo json_encode(["success" => false, "message" => "Task not found"]);
    }
}

function getTaskWithUserInfo($conn, $taskId)
{
    $query = "SELECT t.id, t.name, t.description, t.state, 
                     u.id as assigned_user_id, u.username as assigned_username, u.photo as assigned_photo 
              FROM task t 
              LEFT JOIN user u ON t.assigned_to = u.id 
              WHERE t.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $taskId);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    $stmt->close();

    // Fixed the condition for the default photo - it should be when photo is null or empty
    if ($task && (!isset($task['assigned_photo']) || empty($task['assigned_photo']))) {
        $task['assigned_photo'] = "https://upload.wikimedia.org/wikipedia/commons/a/ac/Default_pfp.jpg";
    }

    return $task;
}

$conn->close();
?>