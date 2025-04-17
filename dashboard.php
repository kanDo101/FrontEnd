<?php

session_start();

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    // Redirect to login page if not logged in
    header("Location: http://localhost/KANDO/FrontEnd/auth/signUp&signin/signup.html");
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
$username = $_SESSION["username"];

// Get user's created projects
$created_projects_query = "SELECT p.*, 
    (SELECT COUNT(*) FROM Appartenir WHERE projectId = p.id) as member_count 
    FROM Project p 
    WHERE p.userId = ?";
$stmt_created = $conn->prepare($created_projects_query);
$stmt_created->bind_param("i", $user_id);
$stmt_created->execute();
$created_projects = $stmt_created->get_result();

// Get projects the user is collaborating on (but didn't create)
$collab_projects_query = "SELECT p.*, 
    (SELECT COUNT(*) FROM Appartenir WHERE projectId = p.id) as member_count 
    FROM project p 
    JOIN Appartenir pm ON p.id = pm.projectId 
    WHERE pm.userId = ? AND p.userId != ?";
$stmt_collab = $conn->prepare($collab_projects_query);
$stmt_collab->bind_param("ii", $user_id, $user_id);
$stmt_collab->execute();
$collab_projects = $stmt_collab->get_result();

// Get user profile picture 
$photo_query = "SELECT photo FROM user WHERE id = ?";
$stmt_profile = $conn->prepare($photo_query);
$stmt_profile->bind_param("i", $user_id);
$stmt_profile->execute();
$stmt_profile->bind_result($photo);
$stmt_profile->fetch();
$stmt_profile->close();

// If no profile pic is set, use a default
if (!$photo) {
    $photo = "https://upload.wikimedia.org/wikipedia/commons/a/ac/Default_pfp.jpg";
}

// Function to get project members avatars
function getProjectMembers($conn, $project_id)
{
    $members_query = "SELECT u.photo FROM user u 
                      JOIN Appartenir pm ON u.id = pm.userId 
                      WHERE pm.projectId = ? LIMIT 4";
    $stmt = $conn->prepare($members_query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row['photo'] ? $row['photo'] : "https://upload.wikimedia.org/wikipedia/commons/a/ac/Default_pfp.jpg";
    }

    $stmt->close();
    return $members;
}

// Function to calculate project progress
function getProjectProgress($conn, $project_id)
{
    $progress_query = "SELECT 
                      COUNT(CASE WHEN state = 'completed' THEN 1 END) as completed,
                      COUNT(*) as total
                      FROM task WHERE projectId = ?";
    $stmt = $conn->prepare($progress_query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $progress = 0;
    if ($row['total'] > 0) {
        $progress = ($row['completed'] / $row['total']) * 100;
    }

    $stmt->close();
    return round($progress);
}

// Get the current theme preference from cookie
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
        .project-header {
            display: flex;
            justify-content: space-evenly;
            align-items: center;
            margin-bottom: 20px;
        }

        .add-project-btn {
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

        /* Section header with add button */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .add-project-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
        }

        .add-project-btn:hover {
            background-color: var(--secondary-color);
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: var(--bg-color);
            border-radius: 8px;
            padding: 25px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-color);
        }

        /* Form styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background-color: var(--input-bg);
            color: var(--text-color);
            border: 1px solid var(--border-color);

        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .cancel-btn {
            background-color: var(--bg-secondary);
            color: var(--text-color);
            border: 1px solid var(--border-color);
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }

        .submit-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }

        /* Team member search and selection */
        .search-results {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            margin-top: 5px;
            display: none;
        }

        .search-result-item {
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .search-result-item:hover {
            background-color: var(--bg-secondary);
        }

        .selected-members {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }

        .selected-member {
            display: flex;
            align-items: center;
            background-color: var(--bg-secondary);
            border-radius: 20px;
            padding: 5px 10px;
            gap: 8px;
        }

        .selected-member img {
            width: 25px;
            height: 25px;
            border-radius: 50%;
        }

        .remove-member {
            cursor: pointer;
            font-size: 12px;
        }

        .search-result-item img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Project actions buttons */
        .project-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .project-card {
            position: relative;
        }

        .project-card:hover .project-actions {
            opacity: 1;
        }

        .project-actions button {
            background: none;
            border: none;
            color: var(--text-color);
            cursor: pointer;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--bg-secondary);
        }

        .project-actions button:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .project-actions .edit-project:hover {
            background-color: var(--primary-color);
        }

        .project-actions .delete-project:hover {
            background-color: var(--accent-color);
        }

        /* Delete confirmation dialog */
        .delete-confirmation {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .delete-confirmation-content {
            background-color: var(--bg-color);
            border-radius: 8px;
            padding: 25px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .delete-confirmation h3 {
            margin-top: 0;
            margin-bottom: 20px;
        }

        .delete-confirmation p {
            margin-bottom: 25px;
        }

        .delete-confirmation-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .delete-confirmation-buttons button {
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            border: none;
        }

        .cancel-delete {
            background-color: var(--bg-secondary);
            color: var(--text-color);
        }

        .confirm-delete {
            background-color: var(--accent-color);
            color: white;
        }


        #memberSearch {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background-color: var(--input-bg);
            color: var(--text-color);
            margin-bottom: 5px;
        }

        .search-result-item {
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
        }

        .search-result-item:last-child {
            border-bottom: none;
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
        <div class="projects-container">
            <div class="project-header">
                <h2 class="section-title">My Projects</h2>
                <button class="add-project-btn" id="addProjectBtn">
                    <i class="fas fa-plus"></i> Add New Project
                </button>
            </div>

            <div class="project-grid">
                <?php if ($created_projects->num_rows > 0): ?>
                    <?php while ($project = $created_projects->fetch_assoc()): ?>
                        <?php
                        $Appartenir = getProjectMembers($conn, $project['id']);
                        $progress = getProjectProgress($conn, $project['id']);
                        $color = '';
                        if ($progress < 30) {
                            $color = 'var(--accent-color)';
                        } else if ($progress < 70) {
                            $color = 'var(--primary-color)';
                        } else {
                            $color = 'var(--secondary-color)';
                        }
                        ?>
                        <div class="project-card">
                            <div class="project-actions">
                                <button class="edit-project" data-project-id="<?php echo $project['id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="delete-project" data-project-id="<?php echo $project['id']; ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <div class="project-content"
                                onclick="window.location='project.php?projectId=<?php echo $project['id']; ?>'">
                                <h3 class="project-title"><?php echo htmlspecialchars($project['name']); ?></h3>
                                <p class="project-description"><?php echo htmlspecialchars($project['description']); ?></p>
                                <div class="project-meta">
                                    <div class="project-members">
                                        <?php foreach ($Appartenir as $member): ?>
                                            <img src="<?php echo htmlspecialchars($member); ?>" alt="Team Member"
                                                class="project-member">
                                        <?php endforeach; ?>
                                        <?php if ($project['member_count'] > 4): ?>
                                            <span style="margin-left: 5px;">+<?php echo $project['member_count'] - 4; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span>Due: <?php echo date('M d', strtotime($project['dueDate'])); ?></span>
                                </div>
                                <div class="project-progress">
                                    <div class="progress-bar"
                                        style="width: <?php echo $progress; ?>%; background-color: <?php echo $color; ?>;">
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-plus"></i>
                        <h3>No Projects Yet</h3>
                        <p>Create your first project to get started</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="projects-container">
            <h2 class="section-title">Collaborations</h2>
            <div class="project-grid">
                <?php if ($collab_projects->num_rows > 0): ?>
                    <?php while ($project = $collab_projects->fetch_assoc()): ?>
                        <?php
                        $Appartenir = getProjectMembers($conn, $project['id']);
                        $progress = getProjectProgress($conn, $project['id']);
                        $color = '';
                        if ($progress < 30) {
                            $color = 'var(--accent-color)';
                        } else if ($progress < 70) {
                            $color = 'var(--primary-color)';
                        } else {
                            $color = 'var(--secondary-color)';
                        }
                        ?>
                        <div class="project-card"
                            onclick="window.location='project.php?projectId=<?php echo $project['id']; ?>'">
                            <h3 class="project-title"><?php echo htmlspecialchars($project['name']); ?></h3>
                            <p class="project-description"><?php echo htmlspecialchars($project['description']); ?></p>
                            <div class="project-meta">
                                <div class="project-members">
                                    <?php foreach ($Appartenir as $member): ?>
                                        <img src="<?php echo htmlspecialchars($member); ?>" alt="Team Member"
                                            class="project-member">
                                    <?php endforeach; ?>
                                    <?php if ($project['member_count'] > 4): ?>
                                        <span style="margin-left: 5px;">+<?php echo $project['member_count'] - 4; ?></span>
                                    <?php endif; ?>
                                </div>
                                <span>Due: <?php echo date('M d', strtotime($project['dueDate'])); ?></span>
                            </div>
                            <div class="project-progress">
                                <div class="progress-bar"
                                    style="width: <?php echo $progress; ?>%; background-color: <?php echo $color; ?>;"></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>No Collaborations Yet</h3>
                        <p>You haven't been added to any projects</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Add Project Modal -->
    <div id="projectModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2>Create New Project</h2>

            <form id="addProjectForm" method="POST" action="add_project.php">
                <div class="form-group">
                    <label for="projectName">Project Name</label>
                    <input type="text" id="projectName" name="projectName" required>
                </div>

                <div class="form-group">
                    <label for="projectDescription">Description</label>
                    <textarea id="projectDescription" name="projectDescription" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label for="dueDate">Due Date</label>
                    <input type="date" id="dueDate" name="dueDate" required>
                </div>

                <div class="form-group">
                    <label for="teamMembers">Team Members</label>
                    <div class="selected-members" id="selectedMembers"></div>
                    <input type="text" id="memberSearch" placeholder="Search users...">
                    <div id="searchResults" class="search-results"></div>
                    <input type="hidden" id="memberIds" name="memberIds">
                </div>

                <div class="form-actions">
                    <button type="button" class="cancel-btn" id="cancelBtn">Cancel</button>
                    <button type="submit" class="submit-btn">Create Project</button>
                </div>
            </form>
        </div>
    </div>
<!-- Edit Project Modal -->
<div id="editProjectModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" id="closeEditModal">&times;</span>
        <h2>Edit Project</h2>

        <form id="editProjectForm" method="POST" action="update_project.php">
            <input type="hidden" id="editProjectId" name="projectId">
            <div class="form-group">
                <label for="editProjectName">Project Name</label>
                <input type="text" id="editProjectName" name="projectName" required>
            </div>

            <div class="form-group">
                <label for="editProjectDescription">Description</label>
                <textarea id="editProjectDescription" name="projectDescription" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label for="editDueDate">Due Date</label>
                <input type="date" id="editDueDate" name="dueDate" required>
            </div>

            <div class="form-group">
                <label for="editTeamMembers">Team Members</label>
                <div class="selected-members" id="editSelectedMembers"></div>
                <input type="text" id="editMemberSearch" placeholder="Search users...">
                <div id="editSearchResults" class="search-results"></div>
                <input type="hidden" id="editMemberIds" name="memberIds">
            </div>

            <div class="form-actions">
                <button type="button" class="cancel-btn" id="cancelEditBtn">Cancel</button>
                <button type="submit" class="submit-btn">Update Project</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Dialog -->
<div id="deleteConfirmation" class="delete-confirmation">
    <div class="delete-confirmation-content">
        <h3>Delete Project</h3>
        <p>Are you sure you want to delete this project? This action cannot be undone.</p>
        <input type="hidden" id="deleteProjectId" value="">
        <div class="delete-confirmation-buttons">
            <button class="cancel-delete" id="cancelDeleteBtn">Cancel</button>
            <button class="confirm-delete" id="confirmDeleteBtn">Delete</button>
        </div>
    </div>
</div>

    <script>
        // Project Edit and Delete functionality
const editProjectModal = document.getElementById('editProjectModal');
const closeEditModal = document.getElementById('closeEditModal');
const cancelEditBtn = document.getElementById('cancelEditBtn');
const editProjectForm = document.getElementById('editProjectForm');
const deleteConfirmation = document.getElementById('deleteConfirmation');
const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
// Add this to your existing script section
document.getElementById('editProjectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const projectId = document.getElementById('editProjectId').value;
    const projectName = document.getElementById('editProjectName').value;
    const projectDescription = document.getElementById('editProjectDescription').value;
    const dueDate = document.getElementById('editDueDate').value;
    
    // Prepare data in the format expected by update_project.php
    const data = {
        id: parseInt(projectId),
        name: projectName,
        description: projectDescription,
        dueDate: dueDate
    };
    
    // Send as JSON
    fetch('update_project.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Close modal and reload page to see changes
            closeEditProjectModal();
            window.location.reload();
        } else {
            alert(result.message || 'Failed to update project');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the project');
    });
});

// Setup edit buttons
document.querySelectorAll('.edit-project').forEach(button => {
    button.addEventListener('click', (e) => {
        e.stopPropagation(); // Prevent the click from propagating to the project card
        const projectId = button.getAttribute('data-project-id');
        loadProjectDetails(projectId);
    });
});

// Setup delete buttons
document.querySelectorAll('.delete-project').forEach(button => {
    button.addEventListener('click', (e) => {
        e.stopPropagation(); // Prevent the click from propagating to the project card
        const projectId = button.getAttribute('data-project-id');
        document.getElementById('deleteProjectId').value = projectId;
        deleteConfirmation.style.display = 'flex';
    });
});

// Close edit modal
function closeEditProjectModal() {
    editProjectModal.style.display = 'none';
    // Reset form
    document.getElementById('editProjectForm').reset();
    document.getElementById('editSelectedMembers').innerHTML = '';
    document.getElementById('editMemberIds').value = '';
}

closeEditModal.addEventListener('click', closeEditProjectModal);
cancelEditBtn.addEventListener('click', closeEditProjectModal);

// Close edit modal when clicking outside
window.addEventListener('click', (e) => {
    if (e.target === editProjectModal) {
        closeEditProjectModal();
    }
    if (e.target === deleteConfirmation) {
        deleteConfirmation.style.display = 'none';
    }
});

// Close delete confirmation
cancelDeleteBtn.addEventListener('click', () => {
    deleteConfirmation.style.display = 'none';
});

// Confirm delete
confirmDeleteBtn.addEventListener('click', () => {
    const projectId = document.getElementById('deleteProjectId').value;
    if (projectId) {
        // Send delete request
        fetch('delete_project.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `projectId=${projectId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload the page to show the updated project list
                window.location.reload();
            } else {
                alert(data.message || 'Error deleting project');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while trying to delete the project');
        });
    }
    deleteConfirmation.style.display = 'none';
});

// Load project details for editing
function loadProjectDetails(projectId) {
    fetch(`get_project_details.php?projectId=${projectId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate form fields
                document.getElementById('editProjectId').value = data.project.id;
                document.getElementById('editProjectName').value = data.project.name;
                document.getElementById('editProjectDescription').value = data.project.description || '';
                document.getElementById('editDueDate').value = data.project.dueDate;
                
                // Clear existing members
                document.getElementById('editSelectedMembers').innerHTML = '';
                const selectedMembersContainer = document.getElementById('editSelectedMembers');
                let editSelectedMemberIds = [];
                
                // Add team members
                if (data.members && data.members.length > 0) {
                    data.members.forEach(member => {
                        editSelectedMemberIds.push(member.id);
                        
                        const memberElement = document.createElement('div');
                        memberElement.className = 'selected-member';
                        memberElement.innerHTML = `
                            <img src="${member.photo || 'https://upload.wikimedia.org/wikipedia/commons/a/ac/Default_pfp.jpg'}" alt="${member.username}">
                            <span>${member.username}</span>
                            <span class="remove-member" data-id="${member.id}">×</span>
                        `;
                        
                        memberElement.querySelector('.remove-member').addEventListener('click', function() {
                            const userId = parseInt(this.getAttribute('data-id'));
                            editSelectedMemberIds = editSelectedMemberIds.filter(id => id !== userId);
                            document.getElementById('editMemberIds').value = JSON.stringify(editSelectedMemberIds);
                            memberElement.remove();
                        });
                        
                        selectedMembersContainer.appendChild(memberElement);
                    });
                    
                    document.getElementById('editMemberIds').value = JSON.stringify(editSelectedMemberIds);
                }
                
                // Show the modal
                editProjectModal.style.display = 'flex';
                
                // Setup member search for edit modal
                setupEditMemberSearch(editSelectedMemberIds);
            } else {
                alert(data.message || 'Error loading project details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while trying to load project details');
        });
}

// Setup member search for edit modal
function setupEditMemberSearch(selectedIds) {
    const memberSearch = document.getElementById('editMemberSearch');
    const searchResults = document.getElementById('editSearchResults');
    const selectedMembers = document.getElementById('editSelectedMembers');
    const memberIdsInput = document.getElementById('editMemberIds');
    let editSelectedMemberIds = selectedIds || [];
    
    memberSearch.addEventListener('input', () => {
        const searchTerm = memberSearch.value.trim();
        
        if (searchTerm.length < 2) {
            searchResults.style.display = 'none';
            return;
        }
        
        // Fetch users that match search term
        fetch(`search_users.php?term=${encodeURIComponent(searchTerm)}`)
            .then(response => response.json())
            .then(users => {
                if (users.length > 0) {
                    searchResults.innerHTML = '';
                    users.forEach(user => {
                        // Skip if already selected
                        if (editSelectedMemberIds.includes(user.id)) return;
                        
                        const item = document.createElement('div');
                        item.className = 'search-result-item';
                        item.innerHTML = `
                            <img src="${user.photo || 'https://upload.wikimedia.org/wikipedia/commons/a/ac/Default_pfp.jpg'}" alt="${user.username}">
                            <span>${user.username}</span>
                        `;
                        
                        item.addEventListener('click', () => {
                            addEditTeamMember(user, editSelectedMemberIds, memberIdsInput, selectedMembers);
                            searchResults.style.display = 'none';
                            memberSearch.value = '';
                        });
                        
                        searchResults.appendChild(item);
                    });
                    searchResults.style.display = 'block';
                } else {
                    searchResults.innerHTML = '<div class="search-result-item">No users found</div>';
                    searchResults.style.display = 'block';
                }
            });
    });
}

function addEditTeamMember(user, selectedMemberIds, memberIdsInput, selectedMembers) {
    if (selectedMemberIds.includes(user.id)) return;
    
    selectedMemberIds.push(user.id);
    memberIdsInput.value = JSON.stringify(selectedMemberIds);
    
    const memberElement = document.createElement('div');
    memberElement.className = 'selected-member';
    memberElement.innerHTML = `
        <img src="${user.photo || 'https://upload.wikimedia.org/wikipedia/commons/a/ac/Default_pfp.jpg'}" alt="${user.username}">
        <span>${user.username}</span>
        <span class="remove-member" data-id="${user.id}">×</span>
    `;
    
    memberElement.querySelector('.remove-member').addEventListener('click', function() {
        const userId = parseInt(this.getAttribute('data-id'));
        const newSelectedIds = selectedMemberIds.filter(id => id !== userId);
        // Update the original array by reference
        selectedMemberIds.length = 0;
        newSelectedIds.forEach(id => selectedMemberIds.push(id));
        memberIdsInput.value = JSON.stringify(selectedMemberIds);
        memberElement.remove();
    });
    
    selectedMembers.appendChild(memberElement);
}

        // Modal functionality
        const modal = document.getElementById('projectModal');
        const addProjectBtn = document.getElementById('addProjectBtn');
        const closeModal = document.querySelector('.close-modal');
        const cancelBtn = document.getElementById('cancelBtn');

        addProjectBtn.addEventListener('click', () => {
            modal.style.display = 'flex';
        });

        function closeProjectModal() {
            modal.style.display = 'none';
            // Reset form
            document.getElementById('addProjectForm').reset();
            document.getElementById('selectedMembers').innerHTML = '';
            document.getElementById('memberIds').value = '';
        }

        closeModal.addEventListener('click', closeProjectModal);
        cancelBtn.addEventListener('click', closeProjectModal);

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeProjectModal();
            }
        });

        // Team member search functionality
        const memberSearch = document.getElementById('memberSearch');
        const searchResults = document.getElementById('searchResults');
        const selectedMembers = document.getElementById('selectedMembers');
        const memberIdsInput = document.getElementById('memberIds');
        let selectedMemberIds = [];

        memberSearch.addEventListener('input', () => {
            const searchTerm = memberSearch.value.trim();

            if (searchTerm.length < 2) {
                searchResults.style.display = 'none';
                return;
            }

            // Fetch users that match search term
            fetch(`search_users.php?term=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(users => {
                    if (users.length > 0) {
                        searchResults.innerHTML = '';
                        users.forEach(user => {
                            // Skip if already selected
                            if (selectedMemberIds.includes(user.id)) return;

                            const item = document.createElement('div');
                            item.className = 'search-result-item';
                            item.innerHTML = `
                        <img src="${user.photo || 'https://upload.wikimedia.org/wikipedia/commons/a/ac/Default_pfp.jpg'}" alt="${user.username}">
                        <span>${user.username}</span>
                    `;

                            item.addEventListener('click', () => {
                                addTeamMember(user);
                                searchResults.style.display = 'none';
                                memberSearch.value = '';
                            });

                            searchResults.appendChild(item);
                        });
                        searchResults.style.display = 'block';
                    } else {
                        searchResults.innerHTML = '<div class="search-result-item">No users found</div>';
                        searchResults.style.display = 'block';
                    }
                });
        });

        function addTeamMember(user) {
            if (selectedMemberIds.includes(user.id)) return;

            selectedMemberIds.push(user.id);
            memberIdsInput.value = JSON.stringify(selectedMemberIds);

            const memberElement = document.createElement('div');
            memberElement.className = 'selected-member';
            memberElement.innerHTML = `
        <img src="${user.photo || 'https://upload.wikimedia.org/wikipedia/commons/a/ac/Default_pfp.jpg'}" alt="${user.username}">
        <span>${user.username}</span>
        <span class="remove-member" data-id="${user.id}">×</span>
    `;

            memberElement.querySelector('.remove-member').addEventListener('click', function () {
                const userId = parseInt(this.getAttribute('data-id'));
                selectedMemberIds = selectedMemberIds.filter(id => id !== userId);
                memberIdsInput.value = JSON.stringify(selectedMemberIds);
                memberElement.remove();
            });

            selectedMembers.appendChild(memberElement);
        }

        // Avatar dropdown functionality
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
            const circleCount = 5;
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

        // Initialize the dashboard
        document.addEventListener('DOMContentLoaded', () => {
            createCircles();
        });
    </script>
</body>

</html>

<?php
// Close the database connection
$stmt_created->close();
$stmt_collab->close();
$conn->close();
?>