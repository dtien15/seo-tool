ALTER TABLE users MODIFY role VARCHAR(20) NOT NULL DEFAULT 'seo';

UPDATE users SET role = 'seo' WHERE role = 'seoer';

ALTER TABLE projects
  ADD has_website TINYINT(1) NOT NULL DEFAULT 1,
  ADD website_verdict VARCHAR(20) NULL,
  ADD website_proposal TEXT NULL,
  ADD reoptimize_days INT NOT NULL DEFAULT 30;

ALTER TABLE articles
  ADD type VARCHAR(20) NOT NULL DEFAULT 'new',
  ADD writer_id INT UNSIGNED NULL,
  ADD designer_id INT UNSIGNED NULL,
  ADD cluster VARCHAR(190) NULL,
  ADD planned_date DATE NULL,
  ADD submitted_at DATETIME NULL,
  ADD reject_note TEXT NULL,
  ADD last_reviewed_at DATETIME NULL,
  ADD KEY idx_writer (writer_id),
  ADD KEY idx_designer (designer_id);

UPDATE articles SET status = 'plan' WHERE status = 'idea';

UPDATE articles SET status = 'content_review' WHERE status = 'review';

UPDATE articles SET status = 'ready' WHERE status = 'approved';

ALTER TABLE article_images
  ADD source VARCHAR(10) NOT NULL DEFAULT 'ai',
  ADD uploaded_by INT UNSIGNED NULL;

ALTER TABLE internal_links
  ADD page_type VARCHAR(20) NULL,
  ADD cluster VARCHAR(190) NULL,
  ADD audit_action VARCHAR(20) NULL,
  ADD audit_note TEXT NULL;

CREATE TABLE IF NOT EXISTS article_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  article_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_article (article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS article_approvals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  article_id INT UNSIGNED NOT NULL,
  stage VARCHAR(20) NOT NULL,
  side VARCHAR(10) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_article_stage (article_id, stage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_research (
  project_id INT UNSIGNED NOT NULL,
  section VARCHAR(40) NOT NULL,
  content MEDIUMTEXT NULL,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (project_id, section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS competitors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  domain VARCHAR(190) NOT NULL,
  started_at VARCHAR(20) NULL,
  backlink_notes TEXT NULL,
  content_notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS competitor_metrics (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  competitor_id INT UNSIGNED NOT NULL,
  period VARCHAR(7) NOT NULL,
  traffic INT NULL,
  keywords INT NULL,
  top10 INT NULL,
  referring_domains INT NULL,
  backlinks INT NULL,
  UNIQUE KEY uniq_period (competitor_id, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS competitor_keywords (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  competitor_id INT UNSIGNED NOT NULL,
  keyword VARCHAR(255) NOT NULL,
  position INT NULL,
  volume INT NULL,
  kd INT NULL,
  traffic INT NULL,
  url VARCHAR(1000) NULL,
  period VARCHAR(7) NULL,
  KEY idx_competitor (competitor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS keywords (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  keyword VARCHAR(255) NOT NULL,
  volume INT NULL,
  kd INT NULL,
  intent VARCHAR(30) NULL,
  cluster VARCHAR(190) NULL,
  target_url VARCHAR(1000) NULL,
  priority TINYINT NOT NULL DEFAULT 2,
  current_rank INT NULL,
  article_id INT UNSIGNED NULL,
  source VARCHAR(30) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_project_keyword (project_id, keyword),
  KEY idx_cluster (project_id, cluster)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kpis (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  period VARCHAR(7) NOT NULL,
  metric VARCHAR(40) NOT NULL,
  target DECIMAL(14,2) NULL,
  actual DECIMAL(14,2) NULL,
  note VARCHAR(500) NULL,
  UNIQUE KEY uniq_kpi (project_id, period, metric)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  category VARCHAR(20) NOT NULL,
  item VARCHAR(255) NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'todo',
  note TEXT NULL,
  sort INT NOT NULL DEFAULT 0,
  KEY idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
