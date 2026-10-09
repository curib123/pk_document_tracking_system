-- New baseline schema: the repository did not contain a legacy database export.
-- Use on a NEW pk_dts database. Do not execute on an existing production database.
CREATE DATABASE IF NOT EXISTS pk_dts CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pk_dts;
CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  module VARCHAR(60) NOT NULL,
  action VARCHAR(30) NOT NULL,
  UNIQUE KEY permission_key (module, action)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  leader_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  email VARCHAR(180) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (leader_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS places (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('area','specific','asset','location','sequence','softcopy-categories') NOT NULL,
  name VARCHAR(180) NOT NULL,
  description TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY place_type_name (type, name)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('hardcopy','softcopy') NOT NULL,
  code VARCHAR(80) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  place_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NULL,
  version VARCHAR(30) NOT NULL DEFAULT '1',
  status ENUM('active','archived','disposed') NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY kind_code (kind, code),
  FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE SET NULL,
  FOREIGN KEY (category_id) REFERENCES places(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS workflows (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_type ENUM('softcopy','hardcopy','hardcopy-transfer','access-grant','document-assign') NOT NULL,
  name VARCHAR(160) NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY workflow_version (request_type, version)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS workflow_steps (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  workflow_id INT UNSIGNED NOT NULL,
  step_order INT UNSIGNED NOT NULL,
  label VARCHAR(160) NOT NULL,
  approver_type ENUM('user','role','requester_leader','requester') NOT NULL,
  approver_user_id INT UNSIGNED NULL,
  approver_role_id INT UNSIGNED NULL,
  UNIQUE KEY workflow_order (workflow_id, step_order),
  FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
  FOREIGN KEY (approver_user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (approver_role_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_type ENUM('softcopy','hardcopy','hardcopy-transfer','access-grant','document-assign') NOT NULL,
  requester_id INT UNSIGNED NOT NULL,
  document_id INT UNSIGNED NULL,
  subject VARCHAR(255) NOT NULL,
  remark TEXT NULL,
  status ENUM('draft','pending','returned','approved','rejected') NOT NULL DEFAULT 'draft',
  workflow_id INT UNSIGNED NULL,
  current_step INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (requester_id) REFERENCES users(id),
  FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
  FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE SET NULL,
  INDEX requester_status (requester_id, status),
  INDEX workflow_status (workflow_id, status)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS request_decisions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  step_order INT UNSIGNED NOT NULL,
  actor_id INT UNSIGNED NOT NULL,
  decision ENUM('approved','rejected','returned') NOT NULL,
  remark TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
  FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB;
