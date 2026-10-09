-- Additive upgrade from the starter CI3 schema; do not run as a substitute
-- for mapping a real legacy DTS database. Safe to repeat on the starter schema.
CREATE TABLE IF NOT EXISTS request_details (
  request_id INT UNSIGNED PRIMARY KEY,
  operation ENUM('create','revise','dispose','transfer','grant','assign') NOT NULL,
  target_place_id INT UNSIGNED NULL,
  target_user_id INT UNSIGNED NULL,
  access_expires_at DATETIME NULL,
  proposed_code VARCHAR(80) NULL,
  proposed_title VARCHAR(255) NULL,
  proposed_version VARCHAR(30) NULL,
  proposed_description TEXT NULL,
  CONSTRAINT fk_request_details_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_request_details_place FOREIGN KEY (target_place_id) REFERENCES places(id) ON DELETE SET NULL,
  CONSTRAINT fk_request_details_user FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS document_files (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id INT UNSIGNED NOT NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_name CHAR(64) NOT NULL UNIQUE,
  mime_type VARCHAR(120) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_files_document (document_id, id),
  FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT,
  FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS document_access_grants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  granted_by INT UNSIGNED NOT NULL,
  request_id INT UNSIGNED NOT NULL,
  expires_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_access_request (request_id),
  INDEX idx_grantee (document_id, user_id, revoked_at, expires_at),
  FOREIGN KEY (document_id) REFERENCES documents(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (granted_by) REFERENCES users(id),
  FOREIGN KEY (request_id) REFERENCES requests(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS document_assignments (
  document_id INT UNSIGNED PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  assigned_by INT UNSIGNED NOT NULL,
  request_id INT UNSIGNED NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (document_id) REFERENCES documents(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (assigned_by) REFERENCES users(id),
  FOREIGN KEY (request_id) REFERENCES requests(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS document_transfers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id INT UNSIGNED NOT NULL,
  from_place_id INT UNSIGNED NULL,
  to_place_id INT UNSIGNED NOT NULL,
  transferred_by INT UNSIGNED NOT NULL,
  request_id INT UNSIGNED NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (document_id) REFERENCES documents(id),
  FOREIGN KEY (from_place_id) REFERENCES places(id) ON DELETE SET NULL,
  FOREIGN KEY (to_place_id) REFERENCES places(id),
  FOREIGN KEY (transferred_by) REFERENCES users(id),
  FOREIGN KEY (request_id) REFERENCES requests(id)
) ENGINE=InnoDB;
