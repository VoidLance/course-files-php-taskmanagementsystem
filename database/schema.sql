CREATE TABLE IF NOT EXISTS tms_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'project_manager', 'team_member') NOT NULL DEFAULT 'team_member',
    timezone VARCHAR(60) NOT NULL DEFAULT 'UTC',
    avatar_url VARCHAR(255) NULL,
    email_verified_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS tms_email_verifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_tms_email_verification_user FOREIGN KEY (user_id) REFERENCES tms_users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tms_password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_tms_password_reset_user FOREIGN KEY (user_id) REFERENCES tms_users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tms_projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    visibility ENUM('public', 'private', 'team') NOT NULL DEFAULT 'private',
    owner_id INT UNSIGNED NOT NULL,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_tms_project_owner FOREIGN KEY (owner_id) REFERENCES tms_users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tms_project_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('project_manager', 'team_member') NOT NULL DEFAULT 'team_member',
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_project_member (project_id, user_id),
    CONSTRAINT fk_tms_member_project FOREIGN KEY (project_id) REFERENCES tms_projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_tms_member_user FOREIGN KEY (user_id) REFERENCES tms_users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tms_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    creator_id INT UNSIGNED NOT NULL,
    assignee_id INT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    column_name VARCHAR(40) NOT NULL DEFAULT 'todo',
    status ENUM('not_started', 'in_progress', 'completed', 'on_hold') NOT NULL DEFAULT 'not_started',
    priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    due_date DATETIME NULL,
    labels JSON NULL,
    attachment_path VARCHAR(255) NULL,
    position INT NOT NULL DEFAULT 999,
    estimated_minutes INT NOT NULL DEFAULT 0,
    tracked_minutes INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_tms_task_project FOREIGN KEY (project_id) REFERENCES tms_projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_tms_task_creator FOREIGN KEY (creator_id) REFERENCES tms_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tms_task_assignee FOREIGN KEY (assignee_id) REFERENCES tms_users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS tms_comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_tms_comment_task FOREIGN KEY (task_id) REFERENCES tms_tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_tms_comment_user FOREIGN KEY (user_id) REFERENCES tms_users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tms_subtasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_tms_subtask_task FOREIGN KEY (task_id) REFERENCES tms_tasks(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tms_activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    task_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    action VARCHAR(80) NOT NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_tms_activity_project FOREIGN KEY (project_id) REFERENCES tms_projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_tms_activity_task FOREIGN KEY (task_id) REFERENCES tms_tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_tms_activity_user FOREIGN KEY (user_id) REFERENCES tms_users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tms_saved_filters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    filter_json JSON NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_tms_saved_filter_user FOREIGN KEY (user_id) REFERENCES tms_users(id) ON DELETE CASCADE
);

CREATE INDEX idx_tms_tasks_project ON tms_tasks(project_id);
CREATE INDEX idx_tms_tasks_status ON tms_tasks(status);
CREATE INDEX idx_tms_tasks_due_date ON tms_tasks(due_date);
CREATE INDEX idx_tms_tasks_assignee ON tms_tasks(assignee_id);
CREATE INDEX idx_tms_activity_task ON tms_activity_logs(task_id);
