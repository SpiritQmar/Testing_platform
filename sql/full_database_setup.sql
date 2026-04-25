

DROP DATABASE IF EXISTS exam_analyzer_2;
CREATE DATABASE exam_analyzer_2 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE exam_analyzer_2;


CREATE TABLE roles (
  role_id INT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(50) NOT NULL UNIQUE,
  role_description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  login VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(role_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE languages (
  language_code VARCHAR(10) PRIMARY KEY,
  name_ru VARCHAR(100) NOT NULL,
  name_kz VARCHAR(100) NOT NULL,
  name_en VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE disciplines (
  discipline_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  discipline_name VARCHAR(255) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE students (
  student_id BIGINT PRIMARY KEY,
  course_number INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE questions (
  question_id BIGINT PRIMARY KEY,
  question_text TEXT NOT NULL,
  max_score DECIMAL(8,2) NOT NULL DEFAULT 100,
  question_type VARCHAR(50) NOT NULL DEFAULT 'single',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE raw_exam_results (
  raw_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  import_id INT NOT NULL,
  student_id BIGINT NULL,
  question_id BIGINT NULL,
  question_text TEXT NULL,
  discipline_name VARCHAR(255) NULL,
  course_number INT NULL,
  max_score DECIMAL(8,2) NULL,
  received_score DECIMAL(8,2) NULL,
  score_before_appeal DECIMAL(8,2) NULL,
  score_after_appeal DECIMAL(8,2) NULL,
  discipline_score DECIMAL(8,2) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_import (import_id),
  INDEX idx_question (question_id),
  INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE imports_log (
  import_id INT AUTO_INCREMENT PRIMARY KEY,
  source_filename VARCHAR(255) NOT NULL,
  source_format VARCHAR(20) NOT NULL,
  rows_total INT NOT NULL DEFAULT 0,
  rows_imported INT NOT NULL DEFAULT 0,
  import_type VARCHAR(50) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE hier_questions (
  question_id BIGINT PRIMARY KEY,
  syllabus_topic_id INT NULL,
  question_text TEXT NOT NULL,
  question_type VARCHAR(50) NOT NULL DEFAULT 'single_choice',
  max_score DECIMAL(8,2) NOT NULL DEFAULT 100,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE hier_student_answers (
  answer_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  question_id BIGINT NOT NULL,
  student_id BIGINT NOT NULL,
  score_received DECIMAL(8,2) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_syllabus_topics (
  syllabus_topic_id INT AUTO_INCREMENT PRIMARY KEY,
  discipline_name VARCHAR(255) NOT NULL,
  course_number INT NOT NULL DEFAULT 3,
  topic_code VARCHAR(64) NOT NULL,
  title VARCHAR(500) NOT NULL,
  keywords TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_syllabus_code (discipline_name, course_number, topic_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_rubrics (
  rubric_id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  criteria_text TEXT NOT NULL,
  dose_theme_note TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_question_ai (
  question_id BIGINT PRIMARY KEY,
  question_text TEXT NOT NULL,
  max_score DECIMAL(8,2) NOT NULL DEFAULT 100,
  syllabus_topic_id INT NULL,
  rubric_id INT NULL,
  exam_year INT NOT NULL DEFAULT 2025,
  expected_keywords TEXT NULL,
  is_open_ended TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_aiq_syllabus FOREIGN KEY (syllabus_topic_id) REFERENCES ai_syllabus_topics(syllabus_topic_id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_aiq_rubric FOREIGN KEY (rubric_id) REFERENCES ai_rubrics(rubric_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_student_profile (
  student_id BIGINT PRIMARY KEY,
  nationality_group VARCHAR(64) NOT NULL DEFAULT 'unknown',
  cohort_label VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_open_answers (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  question_id BIGINT NOT NULL,
  student_id BIGINT NOT NULL,
  answer_text TEXT NOT NULL,
  keyword_matches INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_aoa_question FOREIGN KEY (question_id) REFERENCES ai_question_ai(question_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_rejected_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_text_snapshot TEXT NOT NULL,
  exam_year INT NOT NULL,
  rejection_reason TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE semantic_analysis (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id BIGINT NOT NULL,
  syllabus_topic_id INT NULL,
  tfidf_score DECIMAL(10,6) DEFAULT 0,
  match_status VARCHAR(20) DEFAULT 'none',
  analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_question (question_id),
  CONSTRAINT fk_sa_question FOREIGN KEY (question_id) REFERENCES hier_questions(question_id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_sa_syllabus FOREIGN KEY (syllabus_topic_id) REFERENCES ai_syllabus_topics(syllabus_topic_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE question_classifications (
  question_id BIGINT PRIMARY KEY,
  categories JSON NULL,
  flags JSON NULL,
  messages JSON NULL,
  classified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_qc_question FOREIGN KEY (question_id) REFERENCES hier_questions(question_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rule_classifiers (
  rule_id INT AUTO_INCREMENT PRIMARY KEY,
  rule_name VARCHAR(255) NOT NULL,
  rule_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rule_conditions (
  condition_id INT AUTO_INCREMENT PRIMARY KEY,
  rule_id INT NOT NULL,
  metric_name VARCHAR(100) NOT NULL,
  operator VARCHAR(10) NOT NULL,
  value_from DECIMAL(10,4) NOT NULL,
  value_to DECIMAL(10,4) NULL,
  condition_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (rule_id) REFERENCES rule_classifiers(rule_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rule_actions (
  action_id INT AUTO_INCREMENT PRIMARY KEY,
  rule_id INT NOT NULL,
  action_type VARCHAR(50) NOT NULL,
  action_value VARCHAR(255) NOT NULL,
  action_message TEXT NULL,
  FOREIGN KEY (rule_id) REFERENCES rule_classifiers(rule_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ai_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  setting_category VARCHAR(50) NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by INT NULL,
  CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_syllabuses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  discipline_name VARCHAR(255) NOT NULL,
  course_number INT NOT NULL DEFAULT 3,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_type VARCHAR(20) NOT NULL,
  text_content TEXT NULL,
  uploaded_by INT NOT NULL,
  status VARCHAR(50) DEFAULT 'pending',
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processed_at TIMESTAMP NULL,
  CONSTRAINT fk_syl_user FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_discipline (discipline_name),
  INDEX idx_course (course_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ui_texts (
  ui_key VARCHAR(100) PRIMARY KEY,
  text_ru TEXT,
  text_kz TEXT,
  text_en TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ui_help (
  help_key VARCHAR(100) PRIMARY KEY,
  title_ru VARCHAR(255),
  body_ru TEXT,
  title_kz VARCHAR(255),
  body_kz TEXT,
  title_en VARCHAR(255),
  body_en TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ui_menu (
  menu_id INT AUTO_INCREMENT PRIMARY KEY,
  route VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  title_ru VARCHAR(100) NOT NULL,
  title_kz VARCHAR(100),
  title_en VARCHAR(100),
  roles_csv VARCHAR(255) NOT NULL DEFAULT 'superadmin',
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS evaluation_criteria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    criteria_id INT NOT NULL UNIQUE,
    name_kazakh VARCHAR(255) NOT NULL,
    name_russian VARCHAR(255) NOT NULL,
    name_english VARCHAR(255) NOT NULL,
    weight_percent DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_criteria_id (criteria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_id INT NOT NULL,
    criteria_id INT NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_work_id (work_id),
    INDEX idx_criteria_id (criteria_id),
    INDEX idx_work_criteria (work_id, criteria_id),
    FOREIGN KEY (criteria_id) REFERENCES evaluation_criteria(criteria_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_id INT NOT NULL,
    language VARCHAR(10) NOT NULL,
    question_text TEXT NOT NULL,
    answer_text TEXT NOT NULL,
    final_score DECIMAL(5,2),
    teacher_id INT,
    teacher_comment TEXT,
    plagiarism_penalty DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_work_id (work_id),
    INDEX idx_language (language),
    INDEX idx_teacher_id (teacher_id),
    FULLTEXT idx_question_text (question_text),
    FULLTEXT idx_answer_text (answer_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_analysis_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_id INT NOT NULL,
    criteria_id INT,
    analysis_type VARCHAR(50) NOT NULL,
    analysis_result TEXT NOT NULL,
    confidence_score DECIMAL(5,2),
    model_version VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_work_id (work_id),
    INDEX idx_criteria_id (criteria_id),
    INDEX idx_analysis_type (analysis_type),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_mapping (
    work_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    question_id INT NOT NULL,
    discipline_name VARCHAR(255),
    course_number INT,
    import_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_student_question (student_id, question_id),
    INDEX idx_student_id (student_id),
    INDEX idx_question_id (question_id),
    FOREIGN KEY (import_id) REFERENCES imports_log(import_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (role_name, role_description) VALUES
('superadmin', 'Супер-администратор'),
('admin', 'Администратор'),
('teacher', 'Преподаватель'),
('student', 'Студент');

INSERT INTO users (role_id, login, password_hash, full_name, is_active) VALUES
(1, 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Супер Администратор', 1);

INSERT INTO ui_menu (route, sort_order, title_ru, title_kz, title_en, roles_csv, is_active) VALUES
('dashboard.php', 10, 'Панель управления', 'Басқару тақтасы', 'Dashboard', 'superadmin,admin,teacher', 1),
('questions.php', 20, 'Вопросы', 'Сұрақтар', 'Questions', 'superadmin,admin,teacher', 1),
('data_import.php', 30, 'Импорт данных', 'Деректер импорттау', 'Import', 'superadmin,admin', 1),
('ai_analytics.php', 40, 'ИИ-аналитика', 'ИИ талдау', 'AI Analytics', 'superadmin,admin,teacher', 1),
('users.php', 50, 'Пользователи', 'Пайдаланушылар', 'Users', 'superadmin', 1);

INSERT INTO ai_settings (setting_key, setting_value, setting_category) VALUES
('quality.easy_threshold', '0.92', 'quality'),
('quality.hard_threshold', '0.35', 'quality'),
('quality.discrimination_strong', '12', 'quality'),
('quality.discrimination_medium', '6', 'quality'),
('risk.systemic_avg', '45', 'risk'),
('risk.spot_min', '35', 'risk'),
('weights.score_weight', '40', 'weights'),
('weights.discrimination_weight', '30', 'weights'),
('weights.difficulty_weight', '0.15', 'weights'),
('weights.penalty_easy_hard', '15', 'weights')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);


INSERT INTO evaluation_criteria (criteria_id, name_kazakh, name_russian, name_english, weight_percent) VALUES
(170441, 'Дәлелдемелер негізінде шешім қабылдау', 'Принятие решений на основе доказательств', 'Evidence-based decision making', 20.00)
ON DUPLICATE KEY UPDATE name_kazakh = VALUES(name_kazakh), name_russian = VALUES(name_russian), name_english = VALUES(name_english);


