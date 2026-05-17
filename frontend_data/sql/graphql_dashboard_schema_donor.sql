
CREATE DATABASE IF NOT EXISTS uams_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uams_db;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'reviewer') NOT NULL,
    department VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    avatar_url VARCHAR(500) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login DATETIME NULL,

    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE disciplines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    name_ru VARCHAR(255) NOT NULL,
    name_kk VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,

    INDEX idx_code (code),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE questions (
    id VARCHAR(50) PRIMARY KEY,
    discipline VARCHAR(100) NOT NULL,
    course INT NOT NULL,
    text_ru TEXT NOT NULL,
    text_kk TEXT NOT NULL,
    text_en TEXT NOT NULL,
    difficulty DECIMAL(3,2) NOT NULL CHECK (difficulty >= 0 AND difficulty <= 1),
    correlation DECIMAL(3,2) NOT NULL CHECK (correlation >= 0 AND correlation <= 1),
    discrimination DECIMAL(3,2) NOT NULL DEFAULT 0,
    status ENUM('approved', 'review', 'rejected') DEFAULT 'review',
    translation_issues INT DEFAULT 0,
    created_by INT NOT NULL,
    reviewed_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_discipline (discipline),
    INDEX idx_course (course),
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at),

    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,

    FULLTEXT idx_text_search (text_ru, text_kk, text_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE question_options (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id VARCHAR(50) NOT NULL,
    text_ru TEXT NOT NULL,
    text_kk TEXT NOT NULL,
    text_en TEXT NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    position INT NOT NULL,

    INDEX idx_question_id (question_id),
    INDEX idx_is_correct (is_correct),

    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE question_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id VARCHAR(50) NOT NULL UNIQUE,
    total_attempts INT DEFAULT 0,
    correct_attempts INT DEFAULT 0,
    average_time INT DEFAULT 0 COMMENT 'Average time in seconds',
    last_analyzed DATETIME NULL,

    INDEX idx_question_id (question_id),

    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id VARCHAR(50) NOT NULL,
    details TEXT NULL COMMENT 'JSON data',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created_at (created_at),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    total_records INT NOT NULL,
    successful_records INT NOT NULL,
    failed_records INT NOT NULL,
    status ENUM('processing', 'completed', 'failed') DEFAULT 'processing',
    error_log TEXT NULL COMMENT 'JSON array of errors',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,

    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(500) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_id (user_id),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO users (email, password_hash, name, role, department, is_active) VALUES
('admin@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'admin', 'IT Department', 1);

INSERT INTO disciplines (code, name_ru, name_kk, name_en, is_active) VALUES
('MATH', 'Математика', 'Математика', 'Mathematics', 1),
('PHYS', 'Физика', 'Физика', 'Physics', 1),
('CHEM', 'Химия', 'Химия', 'Chemistry', 1),
('BIOL', 'Биология', 'Биология', 'Biology', 1),
('HIST', 'История', 'Тарих', 'History', 1),
('LIT', 'Литература', 'Әдебиет', 'Literature', 1),
('CS', 'Информатика', 'Информатика', 'Computer Science', 1);


DELIMITER //

CREATE PROCEDURE GetQuestions(
    IN p_search VARCHAR(255),
    IN p_discipline VARCHAR(100),
    IN p_course INT,
    IN p_status VARCHAR(20),
    IN p_page INT,
    IN p_per_page INT,
    IN p_sort_by VARCHAR(50),
    IN p_sort_direction VARCHAR(4)
)
BEGIN
    DECLARE v_offset INT;
    SET v_offset = (p_page - 1) * p_per_page;

    SELECT
        q.*,
        u1.name as created_by_name,
        u2.name as reviewed_by_name
    FROM questions q
    LEFT JOIN users u1 ON q.created_by = u1.id
    LEFT JOIN users u2 ON q.reviewed_by = u2.id
    WHERE
        (p_search IS NULL OR
         q.text_ru LIKE CONCAT('%', p_search, '%') OR
         q.text_kk LIKE CONCAT('%', p_search, '%') OR
         q.text_en LIKE CONCAT('%', p_search, '%') OR
         q.id LIKE CONCAT('%', p_search, '%'))
    AND (p_discipline IS NULL OR q.discipline = p_discipline)
    AND (p_course IS NULL OR q.course = p_course)
    AND (p_status IS NULL OR q.status = p_status)
    ORDER BY
        CASE WHEN p_sort_by = 'id' AND p_sort_direction = 'ASC' THEN q.id END ASC,
        CASE WHEN p_sort_by = 'id' AND p_sort_direction = 'DESC' THEN q.id END DESC,
        CASE WHEN p_sort_by = 'difficulty' AND p_sort_direction = 'ASC' THEN q.difficulty END ASC,
        CASE WHEN p_sort_by = 'difficulty' AND p_sort_direction = 'DESC' THEN q.difficulty END DESC,
        CASE WHEN p_sort_by = 'created_at' AND p_sort_direction = 'ASC' THEN q.created_at END ASC,
        CASE WHEN p_sort_by = 'created_at' AND p_sort_direction = 'DESC' THEN q.created_at END DESC,
        q.created_at DESC
    LIMIT p_per_page OFFSET v_offset;
END //

CREATE PROCEDURE GetDashboardStats()
BEGIN
    SELECT
        COUNT(*) as total_questions,
        AVG(difficulty) as avg_difficulty,
        AVG(correlation) as avg_correlation,
        SUM(CASE WHEN status = 'rejected' OR translation_issues > 0 THEN 1 ELSE 0 END) as issues_found
    FROM questions;

    SELECT
        status,
        COUNT(*) as count
    FROM questions
    GROUP BY status;

    SELECT
        discipline,
        COUNT(*) as count
    FROM questions
    GROUP BY discipline
    ORDER BY count DESC
    LIMIT 10;
END //

CREATE PROCEDURE CreateAuditLog(
    IN p_user_id INT,
    IN p_action VARCHAR(100),
    IN p_entity_type VARCHAR(50),
    IN p_entity_id VARCHAR(50),
    IN p_details TEXT,
    IN p_ip_address VARCHAR(45),
    IN p_user_agent VARCHAR(500)
)
BEGIN
    INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
    VALUES (p_user_id, p_action, p_entity_type, p_entity_id, p_details, p_ip_address, p_user_agent);
END //

DELIMITER ;


CREATE VIEW v_questions_summary AS
SELECT
    q.id,
    q.discipline,
    q.course,
    q.text_ru,
    q.text_kk,
    q.text_en,
    q.difficulty,
    q.correlation,
    q.discrimination,
    q.status,
    q.translation_issues,
    q.created_at,
    u1.name as created_by_name,
    u2.name as reviewed_by_name,
    COALESCE(m.total_attempts, 0) as total_attempts,
    COALESCE(m.correct_attempts, 0) as correct_attempts
FROM questions q
LEFT JOIN users u1 ON q.created_by = u1.id
LEFT JOIN users u2 ON q.reviewed_by = u2.id
LEFT JOIN question_metrics m ON q.id = m.question_id;

CREATE VIEW v_active_users AS
SELECT
    id,
    email,
    name,
    role,
    department,
    phone,
    last_login,
    created_at
FROM users
WHERE is_active = 1;


DELIMITER //

CREATE TRIGGER after_question_update
AFTER UPDATE ON questions
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
        VALUES (
            NEW.reviewed_by,
            'UPDATE_QUESTION_STATUS',
            'question',
            NEW.id,
            JSON_OBJECT('old_status', OLD.status, 'new_status', NEW.status)
        );
    END IF;
END //

CREATE TRIGGER after_question_insert
AFTER INSERT ON questions
FOR EACH ROW
BEGIN
    INSERT INTO question_metrics (question_id, total_attempts, correct_attempts, last_analyzed)
    VALUES (NEW.id, 0, 0, NOW());
END //

DELIMITER ;


CREATE INDEX idx_question_discipline_status ON questions(discipline, status);
CREATE INDEX idx_question_course_status ON questions(course, status);
CREATE INDEX idx_audit_user_date ON audit_logs(user_id, created_at);


CREATE USER IF NOT EXISTS 'uams_app'@'localhost' IDENTIFIED BY 'your_secure_password_here';

GRANT SELECT, INSERT, UPDATE, DELETE ON uams_db.* TO 'uams_app'@'localhost';
GRANT EXECUTE ON PROCEDURE uams_db.GetQuestions TO 'uams_app'@'localhost';
GRANT EXECUTE ON PROCEDURE uams_db.GetDashboardStats TO 'uams_app'@'localhost';
GRANT EXECUTE ON PROCEDURE uams_db.CreateAuditLog TO 'uams_app'@'localhost';

FLUSH PRIVILEGES;
