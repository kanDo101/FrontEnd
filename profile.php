<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    // Redirect to login page if not logged in
    header("Location: ../auth/SignUp/login.php");
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

// Get user information
$user_id = $_SESSION["user_id"];
$user_query = "SELECT * FROM User WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Initialize alert messages
$profile_message = "";
$password_message = "";
$message_type = "";

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $fullName = $_POST['fullName'];
        $username = $_POST['username'];
        $profession = $_POST['profession'];
        $email = $_POST['email'];

        // Handle file upload for photo
        $photo = $user['photo']; 
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $upload_dir = 'uploads/';
            $photo = $upload_dir . basename($_FILES['photo']['name']);
            move_uploaded_file($_FILES['photo']['tmp_name'], $photo);
        }

        $update_query = "UPDATE User SET fullName = ?, username = ?, profession = ?, email = ?, photo = ? WHERE id = ?";
        $stmt_update = $conn->prepare($update_query);
        $stmt_update->bind_param("sssssi", $fullName, $username, $profession, $email, $photo, $user_id);
        $stmt_update->execute();
        $stmt_update->close();

        // Refresh user data
        $user['fullName'] = $fullName;
        $user['username'] = $username;
        $user['profession'] = $profession;
        $user['email'] = $email;
        $user['photo'] = $photo;
        
        // Set success message
        $profile_message = "Profile updated successfully!";
        $message_type = "success";
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $password_query = "SELECT password FROM User WHERE id = ?";
        $stmt_password = $conn->prepare($password_query);
        $stmt_password->bind_param("i", $user_id);
        $stmt_password->execute();
        $result = $stmt_password->get_result()->fetch_assoc();
        $stmt_password->close();
        
        if (password_verify($current_password, $result['password'])) {
            // Check if new passwords match
            if ($new_password === $confirm_password) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password in database
                $update_password_query = "UPDATE User SET password = ? WHERE id = ?";
                $stmt_update_password = $conn->prepare($update_password_query);
                $stmt_update_password->bind_param("si", $hashed_password, $user_id);
                $stmt_update_password->execute();
                $stmt_update_password->close();
                
                // Set success message
                $password_message = "Password updated successfully!";
                $message_type = "success";
            } else {
                $password_message = "New passwords do not match.";
                $message_type = "error";
            }
        } else {
            $password_message = "Current password is incorrect.";
            $message_type = "error";
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Kando</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --text-color: #333;
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --accent-color: #e74c3c;
            --button-bg: #3498db;
            --button-hover: #2980b9;
            --button-text: #fff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --transition: all 0.3s ease;
            --input-bg: #f9f9f9;
            --border-color: #e0e0e0;
        }

        [data-theme="dark"] {
            --bg-color: #121212;
            --card-bg: #1e1e1e;
            --text-color: #f0f0f0;
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --accent-color: #e74c3c;
            --button-bg: #3498db;
            --button-hover: #2980b9;
            --shadow: 0 4px 6px rgba(255, 255, 255, 0.05);
            --input-bg: #2c2c2c;
            --border-color: #333;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: var(--transition);
            position: relative;
            overflow-x: hidden;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background-color: var(--card-bg);
            box-shadow: var(--shadow);
            position: relative;
            z-index: 100;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 0.5rem;
        }
        
        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .nav-links a {
            color: var(--text-color);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .nav-links a:hover {
            color: var(--primary-color);
        }
        
        .theme-toggle {
            background: none;
            border: none;
            color: var(--text-color);
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .user-profile {
            position: relative;
            cursor: pointer;
        }
        
        .user-profile .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: var(--card-bg);
            box-shadow: var(--shadow);
            border-radius: var(--border-radius);
            min-width: 200px;
            padding: 0.5rem 0;
            display: none;
            z-index: 10;
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--text-color);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .dropdown-item:hover {
            background-color: rgba(0, 0, 0, 0.05);
            color: var(--primary-color);
        }
        
        .dropdown-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .user-profile:hover .dropdown {
            display: block;
        }
        
        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            position: relative;
            z-index: 10;
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .profile-header h2 {
            margin: 0;
            font-size: 2rem;
            color: var(--primary-color);
        }
        
        .tabs {
            display: flex;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border-bottom: 3px solid transparent;
        }
        
        .tab.active {
            border-bottom: 3px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .profile-photo {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 1rem;
            border: 3px solid var(--primary-color);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            background-color: var(--input-bg);
            color: var(--text-color);
            transition: var(--transition);
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.25);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .cancel-btn,
        .submit-btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .cancel-btn {
            background-color: transparent;
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }
        
        .cancel-btn:hover {
            background-color: var(--border-color);
        }
        
        .submit-btn {
            background-color: var(--button-bg);
            color: var(--button-text);
            border: none;
        }
        
        .submit-btn:hover {
            background-color: var(--button-hover);
        }
        
        .message {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
        }
        
        .message.success {
            background-color: rgba(46, 204, 113, 0.2);
            color: #27ae60;
            border: 1px solid #27ae60;
        }
        
        .message.error {
            background-color: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }
        
        .hero-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }
        
        .shape {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            opacity: 0.1;
            animation: float 15s infinite ease-in-out;
        }
        
        .shape:nth-child(1) {
            width: 150px;
            height: 150px;
            top: 10%;
            left: 5%;
            animation-delay: 0s;
        }
        
        .shape:nth-child(2) {
            width: 100px;
            height: 100px;
            top: 20%;
            right: 10%;
            animation-delay: 2s;
        }
        
        .shape:nth-child(3) {
            width: 80px;
            height: 80px;
            bottom: 20%;
            left: 15%;
            animation-delay: 4s;
        }
        
        .shape:nth-child(4) {
            width: 120px;
            height: 120px;
            bottom: 10%;
            right: 20%;
            animation-delay: 6s;
        }
        
        .shape:nth-child(5) {
            width: 60px;
            height: 60px;
            top: 40%;
            left: 30%;
            animation-delay: 8s;
        }
        
        .shape:nth-child(6) {
            width: 90px;
            height: 90px;
            top: 60%;
            right: 30%;
            animation-delay: 10s;
        }
        
        .shape:nth-child(7) {
            width: 70px;
            height: 70px;
            bottom: 40%;
            left: 40%;
            animation-delay: 12s;
        }
        
        .shape:nth-child(8) {
            width: 110px;
            height: 110px;
            bottom: 50%;
            right: 40%;
            animation-delay: 14s;
        }
        
        @keyframes float {
            0% {
                transform: translate(0, 0) rotate(0deg);
            }
            50% {
                transform: translate(20px, 20px) rotate(180deg);
            }
            100% {
                transform: translate(0, 0) rotate(360deg);
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
                margin: 1rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .cancel-btn, .submit-btn {
                width: 100%;
                margin-top: 0.5rem;
            }
            
            .nav-links {
                gap: 1rem;
            }
        }
    </style>
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const themeToggle = document.getElementById('themeToggle');
            const currentTheme = html.getAttribute('data-theme');
            
            if (currentTheme === 'light') {
                html.setAttribute('data-theme', 'dark');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            } else {
                html.setAttribute('data-theme', 'light');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Handle tab switching
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding content
                    tab.classList.add('active');
                    const target = tab.getAttribute('data-tab');
                    document.getElementById(target).classList.add('active');
                });
            });
        });
    </script>
</head>

<body>
    <div class="hero-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <nav class="navbar">
        <a href="./dashboard.php" style="text-decoration: none;">
            <div class="logo"><i class="fas fa-tasks"></i> Kando</div>
        </a>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="#features">Features</a>
            <a href="#workflow">How It Works</a>
            <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()">
                <i class="fas fa-moon"></i>
            </button>
            <div class="user-profile">
                <img src="<?php echo htmlspecialchars($user['photo'] ?? 'uploads/default-avatar.png'); ?>" alt="Profile Picture" class="avatar">
                <div class="dropdown" id="userDropdown">
                    <a href="profile.php" class="dropdown-item">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container animate__animated animate__fadeIn">
        <div class="tabs">
            <div class="tab active" data-tab="profile-tab">Profile Information</div>
            <div class="tab" data-tab="password-tab">Change Password</div>
        </div>
        
        <div id="profile-tab" class="tab-content active">
            <?php if (!empty($profile_message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $profile_message; ?>
                </div>
            <?php endif; ?>
            
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <div class="profile-photo">
                    <img src="<?php echo htmlspecialchars($user['photo'] ?? 'uploads/default-avatar.png'); ?>" alt="Profile Picture" class="avatar">
                    <input type="file" id="photo" name="photo" style="display: none;">
                    <label for="photo" style="cursor: pointer; color: var(--primary-color); margin-top: 10px;">
                        <i class="fas fa-camera"></i> Change Photo
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="fullName">Full Name</label>
                    <input type="text" id="fullName" name="fullName" value="<?php echo htmlspecialchars($user['fullName'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="profession">Profession</label>
                    <input type="text" id="profession" name="profession" value="<?php echo htmlspecialchars($user['profession'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                </div>

                <div class="form-actions">
                    <a href="dashboard.php" class="cancel-btn">Cancel</a>
                    <button type="submit" name="update_profile" class="submit-btn">Save Changes</button>
                </div>
            </form>
        </div>
        
        <div id="password-tab" class="tab-content">
            <?php if (!empty($password_message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $password_message; ?>
                </div>
            <?php endif; ?>
            
            <form action="profile.php" method="POST">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <div class="form-actions">
                    <a href="dashboard.php" class="cancel-btn">Cancel</a>
                    <button type="submit" name="change_password" class="submit-btn">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>