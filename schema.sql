CREATE TABLE IF NOT EXISTS roles (
  role_id INT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(50) NOT NULL UNIQUE,
  role_description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  login VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS imports_log (
  import_id INT AUTO_INCREMENT PRIMARY KEY,
  source_filename VARCHAR(255) NOT NULL,
  source_format VARCHAR(30) NOT NULL,
  rows_total INT NOT NULL DEFAULT 0,
  rows_imported INT NOT NULL DEFAULT 0,
  rows_rejected INT NOT NULL DEFAULT 0,
  import_type VARCHAR(80) NOT NULL DEFAULT 'exam_results_upload',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS disciplines (
  discipline_id INT AUTO_INCREMENT PRIMARY KEY,
  discipline_name VARCHAR(255) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS students (
  student_id BIGINT PRIMARY KEY,
  educational_program VARCHAR(255) NULL,
  course_number INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS topics (
  topic_id BIGINT PRIMARY KEY,
  discipline_id INT NULL,
  topic_name VARCHAR(255) NULL,
  topic_weight_pct DECIMAL(8,2) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_topics_disc FOREIGN KEY (discipline_id) REFERENCES disciplines(discipline_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS questions (
  question_id BIGINT PRIMARY KEY,
  discipline_id INT NULL,
  topic_id BIGINT NULL,
  question_text TEXT NULL,
  question_type VARCHAR(50) DEFAULT 'exam',
  course_number INT NULL,
  max_score DECIMAL(8,2) DEFAULT 100,
  language_code VARCHAR(20) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_questions_disc FOREIGN KEY (discipline_id) REFERENCES disciplines(discipline_id),
  CONSTRAINT fk_questions_topic FOREIGN KEY (topic_id) REFERENCES topics(topic_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS raw_exam_results (
  raw_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  import_id INT NOT NULL,
  discipline_name VARCHAR(255) NULL,
  topic_id BIGINT NULL,
  question_id BIGINT NULL,
  student_id BIGINT NULL,
  educational_program VARCHAR(255) NULL,
  course_number INT NULL,
  reviewer_teacher_id BIGINT NULL,
  department_name VARCHAR(255) NULL,
  exam_language VARCHAR(50) NULL,
  topic_weight_pct DECIMAL(8,2) NULL,
  score_before_appeal DECIMAL(8,2) NULL,
  score_after_appeal DECIMAL(8,2) NULL,
  discipline_score DECIMAL(8,2) NULL,
  received_score DECIMAL(8,2) NULL,
  question_text TEXT NULL,
  max_score DECIMAL(8,2) DEFAULT 100,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_import (import_id),
  INDEX idx_question (question_id),
  INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS question_metrics (
  metric_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  question_id BIGINT NOT NULL UNIQUE,
  attempts_count INT NOT NULL DEFAULT 0,
  avg_score_before_appeal DECIMAL(8,2) NULL,
  avg_score_after_appeal DECIMAL(8,2) NULL,
  avg_discipline_score DECIMAL(8,2) NULL,
  difficulty_pct DECIMAL(8,2) NULL,
  discrimination_index DECIMAL(10,4) NULL,
  flag VARCHAR(30) NOT NULL DEFAULT 'medium',
  recommendation TEXT NULL,
  calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_qm_question FOREIGN KEY (question_id) REFERENCES questions(question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_syllabus_topics (
  syllabus_topic_id INT AUTO_INCREMENT PRIMARY KEY,
  discipline_name VARCHAR(255) NOT NULL,
  course_number INT NOT NULL DEFAULT 3,
  topic_code VARCHAR(64) NOT NULL,
  title VARCHAR(500) NOT NULL,
  keywords TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_syllabus_code (discipline_name, course_number, topic_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hier_questions (
  question_id BIGINT PRIMARY KEY,
  syllabus_topic_id INT NULL,
  question_text TEXT NOT NULL,
  question_type VARCHAR(50) NOT NULL DEFAULT 'single_choice',
  max_score DECIMAL(8,2) NOT NULL DEFAULT 100,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hq_syllabus FOREIGN KEY (syllabus_topic_id) REFERENCES ai_syllabus_topics(syllabus_topic_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS semantic_analysis (
  id INT AUTO_INCREMENT PRIMARY KEY,
  question_id BIGINT NOT NULL,
  syllabus_topic_id INT NULL,
  tfidf_score DECIMAL(10,6) DEFAULT 0,
  match_status VARCHAR(20) DEFAULT 'none',
  analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sem_question (question_id),
  CONSTRAINT fk_sem_question FOREIGN KEY (question_id) REFERENCES hier_questions(question_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rule_classifiers (
  rule_id INT AUTO_INCREMENT PRIMARY KEY,
  rule_name VARCHAR(255) NOT NULL,
  rule_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS question_classifications (
  question_id BIGINT PRIMARY KEY,
  categories JSON NULL,
  flags JSON NULL,
  messages JSON NULL,
  classified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_qc_question FOREIGN KEY (question_id) REFERENCES hier_questions(question_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NOT NULL,
  setting_category VARCHAR(50) NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE OR REPLACE VIEW v_question_list AS
SELECT
  q.question_id,
  d.discipline_name,
  q.course_number,
  qm.attempts_count,
  qm.avg_score_before_appeal,
  qm.avg_score_after_appeal,
  qm.avg_discipline_score,
  qm.difficulty_pct,
  qm.discrimination_index,
  qm.flag,
  qm.recommendation
FROM questions q
LEFT JOIN disciplines d ON d.discipline_id = q.discipline_id
LEFT JOIN question_metrics qm ON qm.question_id = q.question_id;
