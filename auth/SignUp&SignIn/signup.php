<?php
// Database connection parameters
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

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and sanitize inputs
    $fullName = htmlspecialchars(trim($_POST["fullName"]));
    $username = htmlspecialchars(trim($_POST["username"]));
    $profession = htmlspecialchars(trim($_POST["profession"]));
    $email = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);
    $password = $_POST["password"];
    $photo = htmlspecialchars(trim($_POST["photo"]));
    
    
    // Check if email already exists
    $checkEmail = "SELECT * FROM user WHERE email = ?";
    $stmt = $conn->prepare($checkEmail);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "Email already exists. Please use a different email or <a href='#' onclick='window.history.back();'>go back</a> to try again.";
        exit();
    }
    
    // Check if username already exists
    $checkUsername = "SELECT * FROM user WHERE username = ?";
    $stmt = $conn->prepare($checkUsername);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "Username already exists. Please choose a different username or <a href='#' onclick='window.history.back();'>go back</a> to try again.";
        exit();
    }
    
    // Hash password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Create the SQL query to insert user
    $sql = "INSERT INTO user (fullname, username, profession, email, password, photo, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    // Prepare and bind parameters
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $fullName, $username, $profession, $email, $hashed_password, $photo);
    
    // Execute the query and check if successful
    if ($stmt->execute()) {
        // Get the user ID of the newly created user
        $user_id = $conn->insert_id;
        
        // Start a session and store user information
        session_start();
        $_SESSION["user_id"] = $user_id;
        $_SESSION["username"] = $username;
        $_SESSION["fullname"] = $fullName;
        $_SESSION["email"] = $email;
        $_SESSION["photo"] = $photo;
        
        // Redirect to the dashboard or home page
        header("Location: ../landing.html");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    
    // Close statement
    $stmt->close();
}

// Close connection
$conn->close();
?>