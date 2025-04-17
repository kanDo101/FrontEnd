<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "todo_kanban_db";
if (!isset($_GET['projectId'])) {
    echo "Project ID is missing!";
    exit;
}

$projectId = intval($_GET['projectId']);
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch project details
$projectQuery = $conn->prepare("SELECT name FROM project WHERE id = ?");
$projectQuery->bind_param("i", $projectId);
$projectQuery->execute();
$projectResult = $projectQuery->get_result();
$project = $projectResult->fetch_assoc();

if (!$project) {
    echo "Project not found!";
    exit;
}
$user_id = $_SESSION["user_id"];

// Get user photo
$photo_query = "SELECT photo, username FROM user WHERE id = ?";
$stmt_profile = $conn->prepare($photo_query);
$stmt_profile->bind_param("i", $user_id);
$stmt_profile->execute();
$stmt_profile->bind_result($photo, $username);
$stmt_profile->fetch();
$stmt_profile->close();

// If no profile pic is set, use a default
if (!$photo) {
    $photo = "https://upload.wikimedia.org/wikipedia/commons/a/ac/Default_pfp.jpg";
}

// Fetch tasks
$taskQuery = $conn->prepare("SELECT t.id, t.name, t.state, t.description, u.id as assigned_user_id, u.username as assigned_username, u.photo as assigned_photo 
                            FROM task t 
                            LEFT JOIN user u ON t.userId  = u.id 
                            WHERE t.projectId = ?
                            ORDER BY t.id DESC");
$taskQuery->bind_param("i", $projectId);
$taskQuery->execute();
$taskResult = $taskQuery->get_result();


$tasks = [
    'Pending' => [],
    'inprogress' => [],
    'Completed' => []
];

while ($task = $taskResult->fetch_assoc()) {
    // Set default user photo if not available
    if (!$task['assigned_photo']) {
        $task['assigned_photo'] = "https://upload.wikimedia.org/wikipedia/commons/a/ac/Default_pfp.jpg";
    }

    // Use the state directly from database
    $state = $task['state'];

    // Default to 'Pending' if state doesn't exist in our tasks array
    if (!isset($tasks[$state])) {
        $state = 'Pending';
    }

    $tasks[$state][] = $task;
}
// Get all users for the add task form
$usersQuery = $conn->prepare("SELECT id, username FROM user");
$usersQuery->execute();
$usersResult = $usersQuery->get_result();
$users = [];
while ($user = $usersResult->fetch_assoc()) {
    $users[] = $user;
}



?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kando - Kanban Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">

    <style>
        :root {
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --text-color: #333;
            --text-muted: #6c757d;
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --accent-color: #e74c3c;
            --button-bg: #3498db;
            --button-hover: #2980b9;
            --button-text: #fff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --transition: all 0.3s ease;
            --navbar-bg: #ffffff;
            --dropdown-bg: #ffffff;
            --card-border: #e9ecef;
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --border-color: #dee2e6;
        }

        [data-theme="dark"] {
            --bg-color: #121212;
            --card-bg: #1e1e1e;
            --text-color: #f0f0f0;
            --text-muted: #a0a0a0;
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --accent-color: #e74c3c;
            --button-bg: #3498db;
            --button-hover: #2980b9;
            --shadow: 0 4px 6px rgba(66, 20, 20, 0.05);
            --navbar-bg: #1a1a1a;
            --dropdown-bg: #2a2a2a;
            --card-border: #333333;
            --bg-primary: #1e1e1e;
            --bg-secondary: #2a2a2a;
            --border-color: #444444;
        }

        .kanban-board {
            display: flex;
            gap: 20px;
            padding: 20px 0;
            overflow-x: auto;
        }

        .kanban-column {
            flex: 1;
            min-width: 300px;
            background-color: var(--bg-secondary);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .kanban-column h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .column-todo h3 {
            color: var(--primary-color);
        }

        .column-in_progress h3 {
            color: var(--secondary-color);
        }

        .column-completed h3 {
            color: var(--accent-color);
        }

        .task-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: var(--bg-primary);
            color: var(--text-color);
            font-size: 0.8rem;
            font-weight: bold;
        }

        .task-list {
            min-height: 200px;
            margin-top: 10px;
            background: linear-gradient(135deg, rgba(46, 204, 113, 0.1), var(--card-bg));
            border-radius: 6px;
        }

        .task-card {
            background-color: var(--bg-primary);
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
            cursor: grab;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }

        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.12);
        }

        .task-card.dragging {
            opacity: 0.5;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .task-title {
            font-weight: bold;
            margin: 0;
            font-size: 1rem;
            word-break: break-word;
        }

        .task-actions {
            display: flex;
            gap: 5px;
        }

        .task-actions button {
            background: none;
            border: none;
            font-size: 0.8rem;
            cursor: pointer;
            color: var(--text-muted);
            padding: 2px;
        }

        .task-actions button:hover {
            color: var(--text-color);
        }

        .task-description {
            margin: 10px 0;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .task-user {
            display: flex;
            align-items: center;
            margin-top: 10px;
        }

        .task-user img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }

        .task-user span {
            font-size: 0.85rem;
            color: var(--text-color);
        }

        /* Modal styles */
        .modal {
            z-index: 9999;
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: var(--bg-color);
        }

        .modal-content {
            background-color: var(--bg-primary);
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .close-modal {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {

            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            background-color: var(--bg-color);
            color: var(--text-color);
            box-sizing: border-box;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            font-weight: bold;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }

        .add-task-btn {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .add-task-btn i {
            margin-right: 5px;
        }

        /* Toast notification */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 20px;
            background-color: var(--text-color);
            color: var(--bg-primary);
            border-radius: 4px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transform: translateY(100px);
            opacity: 0;
            transition: transform 0.3s, opacity 0.3s;
            z-index: 1001;
        }

        .project-header {
            display: flex;
            justify-content: space-evenly;
            align-items: center;
            margin-bottom: 20px;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
    </style>
    <script src="./helper/toggle.js"></script>

</head>

<body>
    <div class="moving-circles" id="movingCircles"></div>

    <nav class="navbar" id="navbar">
        <a href="./auth/landing.html">
            <div class="logo"><i class="fas fa-tasks"></i> Kando</div>
        </a>
        <div class="nav-links">
            <a href="#features">Features</a>
            <a href="#workflow">How It Works</a>
            <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()">
                <i class="fas fa-<?php echo $theme === 'dark' ? 'sun' : 'moon'; ?>"></i>
            </button>
            <div class="user-profile">
                <img src="<?php echo htmlspecialchars($photo); ?>" alt="<?php echo htmlspecialchars($username); ?>"
                    class="avatar" id="avatarImg">
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

    <div class="container">
        <div class="project-header">
            <h2>Project: <?php echo htmlspecialchars($project['name']); ?></h2>
            <button class="add-task-btn" id="addTaskBtn">
                <i class="fas fa-plus"></i> Add New Task
            </button>
        </div>

        <div class="kanban-board">
            <div class="kanban-column column-todo" data-state="Pending">
                <h3>
                    To Do
                    <span class="task-count"><?php echo count($tasks['Pending']); ?></span>
                </h3>
                <div class="task-list" data-state="Pending">
                    <?php foreach ($tasks['Pending'] as $task): ?>
                        <div class="task-card" draggable="true" data-task-id="<?php echo $task['id']; ?>">
                            <div class="task-header">
                                <h4 class="task-title"><?php echo htmlspecialchars($task['name']); ?></h4>
                                <div class="task-actions">
                                    <button class="edit-task" data-task-id="<?php echo $task['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="delete-task" data-task-id="<?php echo $task['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php if (!empty($task['description'])): ?>
                                <div class="task-description"><?php echo htmlspecialchars($task['description']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($task['assigned_username'])): ?>
                                <div class="task-user">
                                    <img src="<?php echo htmlspecialchars($task['assigned_photo']); ?>"
                                        alt="<?php echo htmlspecialchars($task['assigned_username']); ?>">
                                    <span><?php echo htmlspecialchars($task['assigned_username']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- inprogress Column -->
            <div class="kanban-column column-in_progress" data-state="inprogress">
                <h3>
                    In Progress
                    <span class="task-count"><?php echo count($tasks['inprogress']); ?></span>
                </h3>
                <div class="task-list" data-state="inprogress">
                    <?php foreach ($tasks['inprogress'] as $task): ?>
                        <div class="task-card" draggable="true" data-task-id="<?php echo $task['id']; ?>">
                            <div class="task-header">
                                <h4 class="task-title"><?php echo htmlspecialchars($task['name']); ?></h4>
                                <div class="task-actions">
                                    <button class="edit-task" data-task-id="<?php echo $task['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="delete-task" data-task-id="<?php echo $task['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php if (!empty($task['description'])): ?>
                                <div class="task-description"><?php echo htmlspecialchars($task['description']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($task['assigned_username'])): ?>
                                <div class="task-user">
                                    <img src="<?php echo htmlspecialchars($task['assigned_photo']); ?>"
                                        alt="<?php echo htmlspecialchars($task['assigned_username']); ?>">
                                    <span><?php echo htmlspecialchars($task['assigned_username']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- COMPLETED Column -->
            <div class="kanban-column column-completed" data-state="Completed">
                <h3>
                    Completed
                    <span class="task-count"><?php echo count($tasks['Completed']); ?></span>
                </h3>
                <div class="task-list" data-state="Completed">
                    <?php foreach ($tasks['Completed'] as $task): ?>
                        <div class="task-card" draggable="true" data-task-id="<?php echo $task['id']; ?>">
                            <div class="task-header">
                                <h4 class="task-title"><?php echo htmlspecialchars($task['name']); ?></h4>
                                <div class="task-actions">
                                    <button class="edit-task" data-task-id="<?php echo $task['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="delete-task" data-task-id="<?php echo $task['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php if (!empty($task['description'])): ?>
                                <div class="task-description"><?php echo htmlspecialchars($task['description']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($task['assigned_username'])): ?>
                                <div class="task-user">
                                    <img src="<?php echo htmlspecialchars($task['assigned_photo']); ?>"
                                        alt="<?php echo htmlspecialchars($task['assigned_username']); ?>">
                                    <span><?php echo htmlspecialchars($task['assigned_username']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- Add/Edit Task Modal -->
    <div id="taskModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" id="closeModal">&times;</span>
            <h3 id="modalTitle">Add New Task</h3>
            <form id="taskForm">
                <input type="hidden" id="taskId" name="taskId" value="">
                <input type="hidden" id="projectId" name="projectId" value="<?php echo $projectId; ?>">

                <div class="form-group">
                    <label for="taskName">Task Name</label>
                    <input type="text" id="taskName" name="taskName" required>
                </div>

                <div class="form-group">
                    <label for="taskDescription">Description</label>
                    <textarea id="taskDescription" name="taskDescription" rows="4"></textarea>
                </div>

                <div class="form-group">
                    <label for="taskState">Status</label>
                    <select id="taskState" name="taskState">
                        <option value="Pending">To Do</option>
                        <option value="inprogress">inprogress</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="assignedTo">Assign To</label>
                    <select id="assignedTo" name="assignedTo">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn" id="cancelTaskBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveTaskBtn">Save Task</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" id="closeDeleteModal">&times;</span>
            <h3>Delete Task</h3>
            <p>Are you sure you want to delete this task? This action cannot be undone.</p>
            <input type="hidden" id="deleteTaskId">
            <div class="form-actions">
                <button type="button" class="btn" id="cancelDeleteBtn">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Task</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        let draggedTask = null;

        const avatarImg = document.getElementById('avatarImg');
        const userDropdown = document.getElementById('userDropdown');

        avatarImg.addEventListener('click', () => {
            userDropdown.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!avatarImg.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('active');
            }
        });

        // Create animated background circles
        function createCircles() {
            const movingCircles = document.getElementById('movingCircles');
            if (!movingCircles) return;

            const circleCount = 1;
            const colors = [
                'var(--primary-color)',
                'var(--secondary-color)',
                'var(--accent-color)'
            ];

            for (let i = 0; i < circleCount; i++) {
                const circle = document.createElement('div');
                circle.classList.add('circle');

                // Random position, size, and animation
                const size = Math.random() * 200 + 100;
                circle.style.width = `${size}px`;
                circle.style.height = `${size}px`;
                circle.style.left = `${Math.random() * 100}%`;
                circle.style.top = `${Math.random() * 100}%`;
                circle.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                circle.style.animationDelay = `${Math.random() * 5}s`;
                circle.style.animationDuration = `${Math.random() * 10 + 15}s`;

                movingCircles.appendChild(circle);
            }
        }

        function updateCircleColors() {
            const circles = document.querySelectorAll('.circle');
            circles.forEach(circle => {
                const colors = [
                    'var(--primary-color)',
                    'var(--secondary-color)',
                    'var(--accent-color)'
                ];
                circle.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            });
        }

        // Toast notification function
        function showToast(message, duration = 3000) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');

            setTimeout(() => {
                toast.classList.remove('show');
            }, duration);
        }
        // Drag and Drop functionality
        function setupDragAndDrop() {
            const taskCards = document.querySelectorAll('.task-card');
            const taskLists = document.querySelectorAll('.task-list');

            // Add event listeners to draggable items
            taskCards.forEach(taskCard => {
                taskCard.addEventListener('dragstart', function (e) {
                    draggedTask = this;
                    setTimeout(() => {
                        this.classList.add('dragging');
                    }, 0);
                });

                taskCard.addEventListener('dragend', function () {
                    this.classList.remove('dragging');
                    draggedTask = null;
                });
            });

            // Add event listeners to drop zones
            taskLists.forEach(taskList => {
                taskList.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    this.classList.add('drag-over');
                });

                taskList.addEventListener('dragleave', function () {
                    this.classList.remove('drag-over');
                });

                taskList.addEventListener('drop', function (e) {
                    e.preventDefault();
                    this.classList.remove('drag-over');

                    if (draggedTask) {
                        const taskId = draggedTask.getAttribute('data-task-id');
                        const newState = this.getAttribute('data-state');
                        const oldState = draggedTask.parentElement.getAttribute('data-state');

                        // Only update if the state actually changed
                        if (newState !== oldState) {
                            this.appendChild(draggedTask);

                            // Log the states for debugging
                            console.log('TaskID:', taskId);
                            console.log('Old State:', oldState);
                            console.log('New State:', newState);

                            updateTaskState(taskId, newState);
                        }
                    }
                });
            });
        }

        function updateTaskState(taskId, newState) {
            // Update task state via AJAX
            const formData = new FormData();
            formData.append('taskId', taskId);
            formData.append('state', newState);
            formData.append('action', 'updateState');

            // Log the data being sent
            console.log('Sending data:', {
                taskId: taskId,
                state: newState,
                action: 'updateState'
            });

            fetch('task_ajax.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Server response:', data);
                    if (data.success) {
                        showToast('Task updated successfully');
                        updateTaskCounts();
                    } else {
                        showToast('Error updating task: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error connecting to server');
                });
        }

        // Task Modal Functionality
        function setupTaskModals() {
            const addTaskBtn = document.getElementById('addTaskBtn');
            const taskModal = document.getElementById('taskModal');
            const closeModal = document.getElementById('closeModal');
            const cancelTaskBtn = document.getElementById('cancelTaskBtn');
            const taskForm = document.getElementById('taskForm');
            const modalTitle = document.getElementById('modalTitle');
            const deleteButtons = document.querySelectorAll('.delete-task');
            const editButtons = document.querySelectorAll('.edit-task');
            const deleteModal = document.getElementById('deleteModal');
            const closeDeleteModal = document.getElementById('closeDeleteModal');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

            // Open Add Task Modal
            addTaskBtn.addEventListener('click', function () {
                taskForm.reset();
                document.getElementById('taskId').value = '';
                modalTitle.textContent = 'Add New Task';
                taskModal.style.display = 'block';
            });

            // Close Task Modal
            closeModal.addEventListener('click', function () {
                taskModal.style.display = 'none';
            });

            cancelTaskBtn.addEventListener('click', function () {
                taskModal.style.display = 'none';
            });

            // Handle Task Form Submit - fix the double submission issue
            let isSubmitting = false;

            taskForm.addEventListener('submit', function (e) {
                e.preventDefault();

                // Prevent double submission
                if (isSubmitting) return;
                isSubmitting = true;

                // Collect form values
                const taskId = document.getElementById('taskId').value;
                const projectId = document.getElementById('projectId').value;
                const taskName = document.getElementById('taskName').value;
                const taskDescription = document.getElementById('taskDescription').value;
                const taskState = document.getElementById('taskState').value;
                const assignedTo = document.getElementById('assignedTo').value;

                // FormData to send to server
                const formData = new FormData();
                formData.append('projectId', projectId);
                formData.append('name', taskName);
                formData.append('description', taskDescription);
                formData.append('state', taskState);
                formData.append('assignedTo', assignedTo);

                if (taskId) {
                    formData.append('taskId', taskId);
                    formData.append('action', 'updateTask');
                } else {
                    formData.append('action', 'addTask');
                }

                // Fetch to send data
                fetch('task_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        isSubmitting = false; // Reset submission flag
                        if (data.success) {
                            showToast(taskId ? 'Task updated successfully' : 'Task added successfully');

                            // Map backend state to frontend state
                            const stateMap = {
                                'Pending': 'todo',
                                'inprogress': 'in_progress',
                                'Completed': 'completed'
                            };

                            const frontendState = stateMap[data.task.state] || 'todo';

                            if (taskId) {
                                // Update existing task card
                                const taskCard = document.querySelector(`.task-card[data-task-id="${taskId}"]`);
                                if (taskCard) {
                                    // Get current column
                                    const currentColumn = taskCard.closest('.task-list');
                                    const currentState = currentColumn.getAttribute('data-state');

                                    // Update card content
                                    updateTaskCard(taskCard, {
                                        ...data.task,
                                        state: frontendState
                                    });

                                    // If state changed, move to new column
                                    if (currentState !== frontendState) {
                                        const newColumn = document.querySelector(`.task-list[data-state="${frontendState}"]`);
                                        if (newColumn) {
                                            newColumn.appendChild(taskCard);
                                        }
                                    }
                                }
                            } else {
                                // Create new task card with mapped state
                                createTaskCard({
                                    ...data.task,
                                    state: frontendState
                                });
                            }

                            updateTaskCounts();
                            taskModal.style.display = 'none'; // Close the modal on success
                        } else {
                            showToast(data.message || 'Error saving task');
                        }
                    })
                    .catch(error => {
                        isSubmitting = false; // Reset submission flag on error
                        console.error('Error:', error);
                        showToast('Error connecting to server');
                    });
            });

            // Edit Task Button Handlers
            editButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const taskId = this.getAttribute('data-task-id');
                    loadTaskDetails(taskId);
                });
            });

            // Delete Task Button Handlers
            deleteButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const taskId = this.getAttribute('data-task-id');
                    document.getElementById('deleteTaskId').value = taskId;
                    deleteModal.style.display = 'block';
                });
            });

            // Close Delete Modal
            closeDeleteModal.addEventListener('click', function () {
                deleteModal.style.display = 'none';
            });

            cancelDeleteBtn.addEventListener('click', function () {
                deleteModal.style.display = 'none';
            });

            // Confirm Delete
            confirmDeleteBtn.addEventListener('click', function () {
                const taskId = document.getElementById('deleteTaskId').value;

                const formData = new FormData();
                formData.append('taskId', taskId);
                formData.append('action', 'deleteTask');

                fetch('task_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Task deleted successfully');
                            // Remove the task card from the DOM
                            const taskCard = document.querySelector(`.task-card[data-task-id="${taskId}"]`);
                            if (taskCard) {
                                taskCard.remove();
                                updateTaskCounts();
                            }
                        } else {
                            showToast(data.message || 'Error deleting task');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('Error connecting to server');
                    });

                deleteModal.style.display = 'none';
            });

            // Close modals when clicking outside
            window.addEventListener('click', function (e) {
                if (e.target === taskModal) {
                    taskModal.style.display = 'none';
                }
                if (e.target === deleteModal) {
                    deleteModal.style.display = 'none';
                }
            });
        }

        // Load task details for editing
        function loadTaskDetails(taskId) {
            const formData = new FormData();
            formData.append('taskId', taskId);
            formData.append('action', 'getTaskDetails');

            fetch('task_ajax.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const task = data.task;

                        document.getElementById('taskId').value = task.id;
                        document.getElementById('taskName').value = task.name;
                        document.getElementById('taskDescription').value = task.description || '';
                        document.getElementById('taskState').value = task.state.toLowerCase();
                        document.getElementById('assignedTo').value = task.assigned_user_id || '';

                        document.getElementById('modalTitle').textContent = 'Edit Task';
                        document.getElementById('taskModal').style.display = 'block';
                    } else {
                        showToast(data.message || 'Error loading task details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error connecting to server');
                });
        }

        function updateTaskCounts() {
            // Update task counts in each column
            const columns = document.querySelectorAll('.kanban-column');

            columns.forEach(column => {
                const state = column.getAttribute('data-state');
                const taskList = column.querySelector('.task-list');
                const taskCount = taskList.querySelectorAll('.task-card').length;

                const countElement = column.querySelector('.task-count');
                if (countElement) {
                    countElement.textContent = taskCount;
                }
            });
        }

        function updateTaskCard(card, task) {
            card.querySelector('.task-title').textContent = task.name;

            // Handle description
            let descElement = card.querySelector('.task-description');
            if (task.description) {
                if (!descElement) {
                    descElement = document.createElement('div');
                    descElement.className = 'task-description';
                    const insertAfter = card.querySelector('.task-header');
                    insertAfter.parentNode.insertBefore(descElement, insertAfter.nextSibling);
                }
                descElement.textContent = task.description;
            } else if (descElement) {
                descElement.remove();
            }

            // Update assigned user
            let userElement = card.querySelector('.task-user');
            if (task.assigned_username) {
                if (!userElement) {
                    userElement = document.createElement('div');
                    userElement.className = 'task-user';
                    card.appendChild(userElement);
                }
                userElement.innerHTML = `
            <img src="${task.assigned_photo || 'https://upload.wikimedia.org/wikipedia/commons/a/ac/Default_pfp.jpg'}" 
                 alt="${task.assigned_username}">
            <span>${task.assigned_username}</span>
        `;
            } else if (userElement) {
                userElement.remove();
            }
        }

        function createTaskCard(task) {
            const state = task.state ? task.state.toLowerCase() : 'todo';
            const taskList = document.querySelector(`.task-list[data-state="${state}"]`);
            if (!taskList) return;

            const taskCard = document.createElement('div');
            taskCard.className = 'task-card';
            taskCard.draggable = true;
            taskCard.dataset.taskId = task.id;

            let userHtml = '';
            if (task.assigned_username) {
                userHtml = `
            <div class="task-user">
                <img src="${task.assigned_photo || 'https://upload.wikimedia.org/wikipedia/commons/a/ac/Default_pfp.jpg'}" 
                     alt="${task.assigned_username}">
                <span>${task.assigned_username}</span>
            </div>
        `;
            }

            let descHtml = '';
            if (task.description) {
                descHtml = `<div class="task-description">${task.description}</div>`;
            }

            taskCard.innerHTML = `
        <div class="task-header">
            <h4 class="task-title">${task.name}</h4>
            <div class="task-actions">
                <button class="edit-task" data-task-id="${task.id}">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="delete-task" data-task-id="${task.id}">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        ${descHtml}
        ${userHtml}
    `;

            taskList.prepend(taskCard);

            // Add event listeners to new buttons
            taskCard.querySelector('.edit-task').addEventListener('click', function () {
                loadTaskDetails(task.id);
            });

            taskCard.querySelector('.delete-task').addEventListener('click', function () {
                document.getElementById('deleteTaskId').value = task.id;
                document.getElementById('deleteModal').style.display = 'block';
            });

            // Add drag events
            taskCard.addEventListener('dragstart', function (e) {
                draggedTask = this;
                setTimeout(() => {
                    this.classList.add('dragging');
                }, 0);
            });

            taskCard.addEventListener('dragend', function () {
                this.classList.remove('dragging');
                draggedTask = null;
            });
        }

        // Initialize all required functionality
        document.addEventListener('DOMContentLoaded', () => {
            createCircles();
            setupDragAndDrop();
            setupTaskModals();
            updateTaskCounts();
        });
    </script>
</body>

</html>
<?php
$projectQuery->close();
$taskQuery->close();
$usersQuery->close();
$conn->close();
?>