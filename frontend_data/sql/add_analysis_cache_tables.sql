CREATE TABLE IF NOT EXISTS question_validation_cache (
  question_id BIGINT NOT NULL,
  import_id INT NOT NULL,
  syllabus_title VARCHAR(255) NULL,
  topic_code VARCHAR(50) NULL,
  keyword_coverage DECIMAL(5,2) DEFAULT 0,
  avg_score DECIMAL(10,2) DEFAULT 0,
  attempts INT DEFAULT 0,
  issues JSON NULL,
  status VARCHAR(20) DEFAULT 'ok',
  analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (question_id, import_id),
  CONSTRAINT fk_qvc_question FOREIGN KEY (question_id) REFERENCES hier_questions(question_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS question_semantic_cache (
  question_id BIGINT NOT NULL,
  import_id INT NOT NULL,
  syllabus_topic_id INT NULL,
  syllabus_title VARCHAR(255) NULL,
  discipline_name VARCHAR(255) NULL,
  text_similarity DECIMAL(10,6) DEFAULT 0,
  semantic_similarity DECIMAL(10,6) DEFAULT 0,
  combined_score DECIMAL(10,6) DEFAULT 0,
  alignment_level VARCHAR(20) DEFAULT 'low',
  avg_score DECIMAL(10,2) DEFAULT 0,
  student_attempts INT DEFAULT 0,
  analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (question_id, import_id),
  CONSTRAINT fk_qsc_question FOREIGN KEY (question_id) REFERENCES hier_questions(question_id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_qsc_syllabus FOREIGN KEY (syllabus_topic_id) REFERENCES ai_syllabus_topics(syllabus_topic_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_qvc_import ON question_validation_cache(import_id);
CREATE INDEX idx_qvc_status ON question_validation_cache(status);
CREATE INDEX idx_qsc_import ON question_semantic_cache(import_id);
CREATE INDEX idx_qsc_alignment ON question_semantic_cache(alignment_level);
