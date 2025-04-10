<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("HTTP/1.1 401 Unauthorized");
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
    header("HTTP/1.1 500 Internal Server Error");
    exit;
}

$term = isset($_GET['term']) ? $_GET['term'] : '';
$currentUserId = $_SESSION["user_id"];

// Search for users by username
$search_query = "SELECT id, username, photo FROM user 
                WHERE username LIKE ? AND id != ? 
                LIMIT 10";
                
$stmt = $conn->prepare($search_query);
$searchParam = "%$term%";
$stmt->bind_param("si", $searchParam, $currentUserId);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = [
        'id' => $row['id'],
        'username' => $row['username'],
        'photo' => $row['photo'] ?: null
    ];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($users);