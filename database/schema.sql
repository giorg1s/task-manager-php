CREATE DATABASE IF NOT EXISTS task_manager;
USE task_manager;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    owner_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (owner_id) REFERENCES users(id)
        ON DELETE CASCADE
);

CREATE TABLE project_members (
    project_id INT,
    user_id INT,
    role ENUM('owner','member') DEFAULT 'member',

    PRIMARY KEY (project_id, user_id),

    FOREIGN KEY (project_id) REFERENCES projects(id)
        ON DELETE CASCADE,

    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
);

CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    status ENUM('todo','in_progress','done') DEFAULT 'todo',
    priority ENUM('low','medium','high') DEFAULT 'medium',

    project_id INT NOT NULL,
    assigned_to INT NULL,

    due_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (project_id) REFERENCES projects(id)
        ON DELETE CASCADE,

    FOREIGN KEY (assigned_to) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (task_id) REFERENCES tasks(id)
        ON DELETE CASCADE,

    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
);