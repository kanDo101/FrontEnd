<?php
// Database connection parameters
$servername = "localhost";
$username = "root"; // Change to your database username
$password = ""; // Change to your database password
$dbname = "todo_kanban_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];

    $password = $_POST["password"];
    echo "<script>console.log('$email, $password');</script>";

    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare(query: "SELECT id, password, username FROM user WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $hashed_password, $username);
    $stmt->fetch();

    if ($stmt->num_rows > 0 && password_verify($password, $hashed_password)) {
        // Successful login: Start session
        $_SESSION["user_id"] = $id;
        $_SESSION["username"] = $username;
        
        // Redirect to dashboard or home page
        header("Location: ../../dashboard.php");
        exit();
    } else {
        echo "<script>alert('Invalid email or password!'); window.location.href='signup.html';</script>";
    }

    $stmt->close();
}
?>
