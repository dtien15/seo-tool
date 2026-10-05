CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','seoer') NOT NULL DEFAULT 'seoer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  monthly_budget_usd DECIMAL(10,2) NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  domain VARCHAR(190) NULL,
  niche VARCHAR(255) NULL,
  brand_voice TEXT NULL,
  target_audience TEXT NULL,
  main_keywords TEXT NULL,
  content_rules TEXT NULL,
  word_count INT NOT NULL DEFAULT 1500,
  images_per_article TINYINT NOT NULL DEFAULT 2,
  wp_url VARCHAR(255) NULL,
  wp_username VARCHAR(190) NULL,
  wp_app_password_enc TEXT NULL,
  wp_default_status VARCHAR(20) NOT NULL DEFAULT 'draft',
  wp_default_category_id INT NULL,
  auto_publish TINYINT(1) NOT NULL DEFAULT 0,
  sitemap_url VARCHAR(500) NULL,
  gsheet_id VARCHAR(190) NULL,
  gsheet_tab VARCHAR(100) NOT NULL DEFAULT 'Bai viet',
  sheet_token VARCHAR(64) NOT NULL,
  sheet_pulled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_owner (owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_members (
  project_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (project_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS internal_links (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  url VARCHAR(1000) NOT NULL,
  url_hash CHAR(40) NOT NULL,
  title VARCHAR(500) NULL,
  keywords VARCHAR(500) NULL,
  source VARCHAR(20) NOT NULL DEFAULT 'manual',
  title_fetched TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_project_url (project_id, url_hash),
  KEY idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS articles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  assigned_to INT UNSIGNED NULL,
  keyword VARCHAR(255) NOT NULL,
  secondary_keywords TEXT NULL,
  notes TEXT NULL,
  word_count INT NULL,
  title VARCHAR(500) NULL,
  slug VARCHAR(255) NULL,
  meta_title VARCHAR(255) NULL,
  meta_description VARCHAR(500) NULL,
  excerpt TEXT NULL,
  outline MEDIUMTEXT NULL,
  content LONGTEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'idea',
  ai_state VARCHAR(20) NOT NULL DEFAULT 'idle',
  ai_task VARCHAR(30) NULL,
  ai_error TEXT NULL,
  wp_post_id INT NULL,
  wp_url VARCHAR(500) NULL,
  wp_category_id INT NULL,
  published_at DATETIME NULL,
  sheet_row INT NULL,
  status_changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_project_status (project_id, status),
  KEY idx_assigned (assigned_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS article_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  article_id INT UNSIGNED NOT NULL,
  position INT NOT NULL DEFAULT 0,
  prompt TEXT NULL,
  alt_text VARCHAR(255) NULL,
  file_path VARCHAR(500) NULL,
  wp_media_id INT NULL,
  wp_url VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_article (article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(50) NOT NULL,
  payload TEXT NULL,
  user_id INT UNSIGNED NULL,
  project_id INT UNSIGNED NULL,
  article_id INT UNSIGNED NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  attempts INT NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  locked_by VARCHAR(40) NULL,
  locked_at DATETIME NULL,
  available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_status (status, available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usage_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  project_id INT UNSIGNED NULL,
  article_id INT UNSIGNED NULL,
  provider VARCHAR(20) NOT NULL,
  model VARCHAR(80) NOT NULL,
  task VARCHAR(30) NULL,
  input_tokens INT NOT NULL DEFAULT 0,
  output_tokens INT NOT NULL DEFAULT 0,
  images INT NOT NULL DEFAULT 0,
  cost_usd DECIMAL(10,5) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_time (user_id, created_at),
  KEY idx_project_time (project_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(100) PRIMARY KEY,
  v MEDIUMTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
