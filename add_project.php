<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/SignUp/login.php");
    exit();
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: dashboard.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "todo_kanban_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data
$projectName = trim($_POST["projectName"]);
$projectDescription = trim($_POST["projectDescription"]);
$dueDate = $_POST["dueDate"];
$creatorId = $_SESSION["user_id"];

// Validate project name
if (empty($projectName)) {
    $_SESSION["error"] = "Project name is required";
    header("Location: dashboard.php");
    exit();
}

// Insert new project
$insert_project = "INSERT INTO Project (name, description, userId, dueDate) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($insert_project);
$stmt->bind_param("ssis", $projectName, $projectDescription, $creatorId, $dueDate);

if ($stmt->execute()) {
    $projectId = $stmt->insert_id;
    
    // Add creator to project members
    $insert_member = "INSERT INTO Appartenir (userId, projectId) VALUES (?, ?)";
    $stmt_member = $conn->prepare($insert_member);
    $stmt_member->bind_param("ii", $creatorId, $projectId);
    $stmt_member->execute();
    $stmt_member->close();
    
    // Add team members if any
    if (!empty($_POST["memberIds"])) {
        $memberIds = json_decode($_POST["memberIds"], true);
        if (is_array($memberIds)) {
            $stmt_team = $conn->prepare($insert_member);
            
            foreach ($memberIds as $memberId) {
                $stmt_team->bind_param("ii", $memberId, $projectId);
                $stmt_team->execute();
            }
            
            $stmt_team->close();
        }
    }
    
    $_SESSION["success"] = "Project created successfully";
} else {
    $_SESSION["error"] = "Failed to create project: " . $conn->error;
}

$stmt->close();
$conn->close();

header("Location: dashboard.php");
exit();