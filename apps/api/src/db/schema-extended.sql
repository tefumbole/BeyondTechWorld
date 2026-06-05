-- Extended tables: tasks, messaging (import also auto-creates missing columns from export JSON)

CREATE TABLE IF NOT EXISTS tasks (
  id CHAR(36) NOT NULL PRIMARY KEY DEFAULT (UUID()),
  title VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  priority VARCHAR(50) DEFAULT 'Medium',
  start_date DATE DEFAULT NULL,
  deadline DATE DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'Pending',
  created_by CHAR(36) DEFAULT NULL,
  category_id CHAR(36) DEFAULT NULL,
  notification_template LONGTEXT DEFAULT NULL,
  schedules_json JSON DEFAULT NULL,
  is_scheduled TINYINT(1) DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tasks_status (status),
  INDEX idx_tasks_deadline (deadline)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_assignments (
  id CHAR(36) NOT NULL PRIMARY KEY DEFAULT (UUID()),
  task_id CHAR(36) NOT NULL,
  user_id CHAR(36) NOT NULL,
  status VARCHAR(50) DEFAULT 'Pending',
  progress INT DEFAULT 0,
  accepted_at DATETIME DEFAULT NULL,
  declined_at DATETIME DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  last_update_at DATETIME DEFAULT NULL,
  invite_token CHAR(36) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_task_user (task_id, user_id),
  INDEX idx_task_assign_user (user_id),
  INDEX idx_task_assign_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_updates (
  id CHAR(36) NOT NULL PRIMARY KEY DEFAULT (UUID()),
  assignment_id CHAR(36) NOT NULL,
  progress INT NOT NULL DEFAULT 0,
  comment TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_task_updates_assignment (assignment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_attachments (
  id CHAR(36) NOT NULL PRIMARY KEY DEFAULT (UUID()),
  task_id CHAR(36) NOT NULL,
  update_id CHAR(36) DEFAULT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_url TEXT NOT NULL,
  attachment_type VARCHAR(50) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_task_attach_task (task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_categories (
  id CHAR(36) NOT NULL PRIMARY KEY DEFAULT (UUID()),
  name VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_message_templates (
  id CHAR(36) NOT NULL PRIMARY KEY DEFAULT (UUID()),
  name VARCHAR(255) NOT NULL,
  subject VARCHAR(255) DEFAULT NULL,
  body TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_reminders (
  id CHAR(36) NOT NULL PRIMARY KEY DEFAULT (UUID()),
  task_id CHAR(36) NOT NULL,
  reminder_time DATETIME NOT NULL,
  is_sent TINYINT(1) DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_task_reminders_time (reminder_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
  id CHAR(36) NOT NULL PRIMARY KEY DEFAULT (UUID()),
  reference VARCHAR(100) DEFAULT NULL,
  category VARCHAR(100) DEFAULT NULL,
  subject VARCHAR(255) DEFAULT NULL,
  body LONGTEXT DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'draft',
  send_email TINYINT(1) DEFAULT 0,
  send_whatsapp TINYINT(1) DEFAULT 0,
  generate_pdf TINYINT(1) DEFAULT 0,
  is_scheduled TINYINT(1) DEFAULT 0,
  scheduled_for DATETIME DEFAULT NULL,
  created_by CHAR(36) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_messages_status (status),
  INDEX idx_messages_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_templates (
  id CHAR(36) NOT NULL PRIMARY KEY DEFAULT (UUID()),
  name VARCHAR(255) NOT NULL,
  category VARCHAR(100) DEFAULT NULL,
  subject VARCHAR(255) DEFAULT NULL,
  body LONGTEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_settings (
  id CHAR(36) NOT NULL PRIMARY KEY DEFAULT (UUID()),
  company_name VARCHAR(255) DEFAULT NULL,
  logo_url TEXT DEFAULT NULL,
  footer_text TEXT DEFAULT NULL,
  settings_json JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_attachments (
  id CHAR(36) NOT NULL PRIMARY KEY DEFAULT (UUID()),
  message_id CHAR(36) NOT NULL,
  file_name VARCHAR(255) DEFAULT NULL,
  file_url TEXT DEFAULT NULL,
  file_size BIGINT DEFAULT NULL,
  mime_type VARCHAR(100) DEFAULT NULL,
  type VARCHAR(50) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_message_attach_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_recipients (
  id CHAR(36) NOT NULL PRIMARY KEY DEFAULT (UUID()),
  message_id CHAR(36) NOT NULL,
  recipient_type VARCHAR(50) DEFAULT NULL,
  recipient_id CHAR(36) DEFAULT NULL,
  recipient_name VARCHAR(255) DEFAULT NULL,
  recipient_email VARCHAR(255) DEFAULT NULL,
  recipient_phone VARCHAR(50) DEFAULT NULL,
  reference_code VARCHAR(100) DEFAULT NULL,
  verification_url TEXT DEFAULT NULL,
  barcode_value VARCHAR(100) DEFAULT NULL,
  pdf_url TEXT DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'pending',
  last_error TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_message_recipients_message (message_id),
  INDEX idx_message_recipients_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_queue (
  id CHAR(36) NOT NULL PRIMARY KEY DEFAULT (UUID()),
  message_id CHAR(36) NOT NULL,
  message_recipient_id CHAR(36) NOT NULL,
  channel VARCHAR(50) NOT NULL,
  status VARCHAR(50) DEFAULT 'pending',
  attempts INT DEFAULT 0,
  max_attempts INT DEFAULT 3,
  locked_at DATETIME DEFAULT NULL,
  locked_by VARCHAR(100) DEFAULT NULL,
  run_after DATETIME DEFAULT NULL,
  last_error TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_message_queue_status (status),
  INDEX idx_message_queue_run_after (run_after)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_logs (
  id CHAR(36) NOT NULL PRIMARY KEY DEFAULT (UUID()),
  message_id CHAR(36) DEFAULT NULL,
  message_recipient_id CHAR(36) DEFAULT NULL,
  queue_id CHAR(36) DEFAULT NULL,
  channel VARCHAR(50) DEFAULT NULL,
  event_type VARCHAR(100) DEFAULT NULL,
  details JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_message_logs_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pending_registrations (
  id CHAR(36) NOT NULL PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(255) NOT NULL,
  phone VARCHAR(50) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'user',
  otp VARCHAR(10) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pending_reg_email (email),
  INDEX idx_pending_reg_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
