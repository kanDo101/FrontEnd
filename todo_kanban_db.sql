-- Create Database
CREATE DATABASE todo_kanban_db;

-- Use the created database
USE todo_kanban_db;

-- Create User table
CREATE TABLE User (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullName VARCHAR(255) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    profession VARCHAR(255),
    email VARCHAR(100),
    photo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Project table
CREATE TABLE Project (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    userId INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (userId) REFERENCES User(id) ON DELETE SET NULL
);

-- Create Task table
CREATE TABLE Task (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    state ENUM('Pending', 'In Progress', 'Completed') DEFAULT 'Pending',
    userId INT,
    projectId INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (userId) REFERENCES User(id) ON DELETE SET NULL,
    FOREIGN KEY (projectId) REFERENCES Project(id) ON DELETE CASCADE
);

-- Create Appartenir table (Many-to-many relationship between User and Project)
CREATE TABLE Appartenir (
    userId INT,
    projectId INT,
    PRIMARY KEY (userId, projectId),
    FOREIGN KEY (userId) REFERENCES User(id) ON DELETE CASCADE,
    FOREIGN KEY (projectId) REFERENCES Project(id) ON DELETE CASCADE
);

-- Add indexes to improve performance
CREATE INDEX idx_userId ON Project(userId);
CREATE INDEX idx_projectId ON Task(projectId);
