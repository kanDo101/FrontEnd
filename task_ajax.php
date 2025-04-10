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

function addTask($conn) {
    $projectId = isset($_POST['projectId']) ? intval($_POST['projectId']) : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $state = isset($_POST['state']) ? trim($_POST['state']) : 'todo';
    $assignedTo = isset($_POST['assignedTo']) && !empty($_POST['assignedTo']) ? intval($_POST['assignedTo']) : null;
    
    if (empty($name) || $projectId <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid task data"]);
        return;
    }
    
    // Map frontend states to backend states
    $stateMap = [
        'todo' => 'Pending',
        'in_progress' => 'In Progress',
        'completed' => 'Completed'
    ];
    
    if (!isset($stateMap[$state])) {
        echo json_encode(["success" => false, "message" => "Invalid state"]);
        return;
    }
    
    $backendState = $stateMap[$state];
    
    $stmt = $conn->prepare("INSERT INTO task (projectId, name, description, state, assigned_to) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $projectId, $name, $description, $backendState, $assignedTo);
    
    if ($stmt->execute()) {
        $taskId = $conn->insert_id;
        $task = getTaskWithUserInfo($conn, $taskId);
        echo json_encode(["success" => true, "task" => $task]);
    } else {
        echo json_encode(["success" => false, "message" => "Error creating task"]);
    }
    
    $stmt->close();
}

function updateTask($conn) {
    $taskId = isset($_POST['taskId']) ? intval($_POST['taskId']) : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $state = isset($_POST['state']) ? trim($_POST['state']) : '';
    $assignedTo = isset($_POST['assignedTo']) && !empty($_POST['assignedTo']) ? intval($_POST['assignedTo']) : null;
    
    if (empty($name) || $taskId <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid task data"]);
        return;
    }
    
    $stateMap = [
        'todo' => 'Pending',
        'in_progress' => 'In Progress',
        'completed' => 'Completed'
    ];
    
    if (!isset($stateMap[$state])) {
        echo json_encode(["success" => false, "message" => "Invalid state"]);
        return;
    }
    
    $backendState = $stateMap[$state];
    
    $stmt = $conn->prepare("UPDATE task SET name = ?, description = ?, state = ?, assigned_to = ? WHERE id = ?");
    $stmt->bind_param("sssii", $name, $description, $backendState, $assignedTo, $taskId);
    
    if ($stmt->execute()) {
        $task = getTaskWithUserInfo($conn, $taskId);
        echo json_encode(["success" => true, "task" => $task]);
    } else {
        echo json_encode(["success" => false, "message" => "Error updating task"]);
    }
    
    $stmt->close();
}

function deleteTask($conn) {
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
    
    // Map frontend states to backend states
    $stateMap = [
        'todo' => 'Pending',
        'in_progress' => 'In Progress',
        'completed' => 'Completed'
    ];
    
    if (!isset($stateMap[$state])) {
        echo json_encode(["success" => false, "message" => "Invalid state"]);
        return;
    }
    
    $backendState = $stateMap[$state];
    
    $stmt = $conn->prepare("UPDATE task SET state = ? WHERE id = ?");
    $stmt->bind_param("si", $backendState, $taskId);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "Error updating task state"]);
    }
    
    $stmt->close();
}

function getTaskDetails($conn) {
    $taskId = isset($_POST['taskId']) ? intval($_POST['taskId']) : 0;
    
    if ($taskId <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid task ID"]);
        return;
    }
    
    $task = getTaskWithUserInfo($conn, $taskId);
    
    if ($task) {
        // Convert backend state to frontend format
        $stateMap = [
            'Pending' => 'todo',
            'In Progress' => 'in_progress',
            'Completed' => 'completed'
        ];
        
        $task['state'] = $stateMap[$task['state']] ?? 'todo';
        echo json_encode(["success" => true, "task" => $task]);
    } else {
        echo json_encode(["success" => false, "message" => "Task not found"]);
    }
}

function getTaskWithUserInfo($conn, $taskId) {
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
    
    // Set default photo if none exists
    if ($task && !empty($task['assigned_photo'])) {
        $task['assigned_photo'] = "https://upload.wikimedia.org/wikipedia/commons/a/ac/Default_pfp.jpg";
    }
    
    return $task;
}

$conn->close();
?>