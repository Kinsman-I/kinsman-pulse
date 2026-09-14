SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS tenants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  logo_path VARCHAR(255) NULL,
  primary_color VARCHAR(20) NOT NULL DEFAULT '#16765f',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  role ENUM('admin','professional','student') NOT NULL,
  name VARCHAR(180) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(30) NULL,
  avatar_path VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_users_tenant_role (tenant_id, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_reset_expiration (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS professionals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  service_type ENUM('nutrition','personal','complete') NOT NULL DEFAULT 'complete',
  registration_number VARCHAR(80) NULL,
  specialty VARCHAR(160) NULL,
  brand_name VARCHAR(160) NULL,
  logo_path VARCHAR(255) NULL,
  primary_color VARCHAR(20) NULL,
  whatsapp VARCHAR(30) NULL,
  instagram VARCHAR(120) NULL,
  welcome_message VARCHAR(255) NULL,
  footer_text VARCHAR(180) NULL,
  subscription_plan ENUM('basic','plus','premium') NOT NULL DEFAULT 'basic',
  subscription_status ENUM('trial','active','past_due','cancelled') NOT NULL DEFAULT 'cancelled',
  trial_ends_at DATETIME NULL,
  access_until DATETIME NULL,
  cancellation_requested_at DATETIME NULL,
  referral_code VARCHAR(24) NULL UNIQUE,
  referred_by_professional_id BIGINT UNSIGNED NULL,
  asaas_customer_id VARCHAR(80) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prof_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_prof_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referral_rewards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referrer_professional_id BIGINT UNSIGNED NOT NULL,
  referred_professional_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','available','applied','cancelled') NOT NULL DEFAULT 'pending',
  qualified_at DATETIME NULL,
  benefit_due_date DATE NULL,
  applied_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_referral_referrer FOREIGN KEY (referrer_professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
  CONSTRAINT fk_referral_referred FOREIGN KEY (referred_professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
  UNIQUE KEY uq_referral_referred (referred_professional_id),
  INDEX idx_referral_referrer_status (referrer_professional_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS students (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  professional_id BIGINT UNSIGNED NOT NULL,
  service_type ENUM('nutrition','personal','complete') NOT NULL DEFAULT 'complete',
  objective TEXT NULL,
  birth_date DATE NULL,
  height_cm DECIMAL(6,2) NULL,
  current_weight_kg DECIMAL(6,2) NULL,
  target_weight_kg DECIMAL(6,2) NULL,
  clinical_notes TEXT NULL,
  started_at DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_student_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_student_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_student_prof FOREIGN KEY (professional_id) REFERENCES professionals(id),
  INDEX idx_students_prof (professional_id),
  INDEX idx_students_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS professional_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  professional_id BIGINT UNSIGNED NOT NULL,
  plan_code ENUM('basic','plus','premium') NOT NULL,
  price_cents INT UNSIGNED NOT NULL,
  status ENUM('trial','active','past_due','cancelled') NOT NULL DEFAULT 'cancelled',
  asaas_checkout_id VARCHAR(100) NULL UNIQUE,
  asaas_subscription_id VARCHAR(100) NULL,
  asaas_customer_id VARCHAR(80) NULL,
  external_reference VARCHAR(200) NULL,
  starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ends_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_subscription_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_subscription_professional FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
  INDEX idx_subscription_prof_status (professional_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asaas_webhook_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id VARCHAR(180) NOT NULL UNIQUE,
  event_type VARCHAR(100) NOT NULL,
  payload JSON NOT NULL,
  processed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assessments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  weight_kg DECIMAL(6,2) NULL,
  waist_cm DECIMAL(6,2) NULL,
  hip_cm DECIMAL(6,2) NULL,
  body_fat_percent DECIMAL(5,2) NULL,
  notes TEXT NULL,
  assessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_assessment_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_assessment_student_date (student_id, assessed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_anamneses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  daily_routine TEXT NULL,
  training_days_per_week TINYINT UNSIGNED NULL,
  training_minutes_per_session SMALLINT UNSIGNED NULL,
  meals_at_work TINYINT(1) NULL,
  diseases TEXT NULL,
  controlled_medications TEXT NULL,
  injuries_surgeries TEXT NULL,
  has_allergies TINYINT(1) NOT NULL DEFAULT 0,
  allergies TEXT NULL,
  used_ergogenics TINYINT(1) NOT NULL DEFAULT 0,
  ergogenics TEXT NULL,
  objective TEXT NULL,
  submitted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_anamnesis_student (student_id),
  CONSTRAINT fk_anamnesis_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS progress_photos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  assessment_id BIGINT UNSIGNED NULL,
  image_path VARCHAR(255) NOT NULL,
  photo_type ENUM('front','side','back','other') NOT NULL DEFAULT 'other',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_photo_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_photo_assessment FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS food_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  professional_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  objective TEXT NULL,
  general_guidance TEXT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_food_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_food_prof FOREIGN KEY (professional_id) REFERENCES professionals(id),
  INDEX idx_food_student_status (student_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  food_plan_id BIGINT UNSIGNED NOT NULL,
  meal_time TIME NULL,
  name VARCHAR(160) NOT NULL,
  foods TEXT NOT NULL,
  substitutions TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_meal_plan FOREIGN KEY (food_plan_id) REFERENCES food_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exercise_catalog (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  professional_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  muscle_group VARCHAR(120) NULL,
  equipment VARCHAR(120) NULL,
  instructions TEXT NULL,
  image_path VARCHAR(255) NULL,
  video_url VARCHAR(500) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_exercise_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_exercise_prof FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
  UNIQUE KEY uq_prof_exercise (professional_id,name),
  INDEX idx_exercise_search (tenant_id,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS global_exercises (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL UNIQUE,
  muscle_group VARCHAR(120) NOT NULL,
  equipment VARCHAR(120) NULL,
  difficulty ENUM('iniciante','intermediario','avancado') NOT NULL DEFAULT 'iniciante',
  instructions TEXT NOT NULL,
  image_path VARCHAR(255) NULL,
  video_url VARCHAR(500) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_global_exercise_search (muscle_group,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workout_sheets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  professional_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  objective TEXT NULL,
  weekly_frequency INT UNSIGNED NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_workout_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_workout_prof FOREIGN KEY (professional_id) REFERENCES professionals(id),
  INDEX idx_workout_student_status (student_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workout_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  workout_sheet_id BIGINT UNSIGNED NOT NULL,
  exercise_id BIGINT UNSIGNED NOT NULL,
  sets_count INT UNSIGNED NULL,
  repetitions VARCHAR(40) NULL,
  suggested_load VARCHAR(40) NULL,
  rest_seconds INT UNSIGNED NULL,
  notes TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_item_sheet FOREIGN KEY (workout_sheet_id) REFERENCES workout_sheets(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_exercise FOREIGN KEY (exercise_id) REFERENCES exercise_catalog(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workout_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  workout_sheet_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  timer_started_at DATETIME NULL,
  paused_at DATETIME NULL,
  paused_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  finished_at DATETIME NULL,
  duration_seconds INT UNSIGNED NULL,
  notes TEXT NULL,
  CONSTRAINT fk_session_sheet FOREIGN KEY (workout_sheet_id) REFERENCES workout_sheets(id),
  CONSTRAINT fk_session_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workout_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  workout_item_id BIGINT UNSIGNED NOT NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  performed_load VARCHAR(40) NULL,
  performed_repetitions VARCHAR(40) NULL,
  notes TEXT NULL,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_log_session FOREIGN KEY (session_id) REFERENCES workout_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_log_item FOREIGN KEY (workout_item_id) REFERENCES workout_items(id),
  UNIQUE INDEX uq_workout_log_session_item (session_id,workout_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  professional_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  subject VARCHAR(160) NOT NULL DEFAULT 'Ajuda pelo Pulse',
  message TEXT NOT NULL,
  reply_message TEXT NULL,
  replied_at DATETIME NULL,
  read_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_message_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_professional FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_message_professional (professional_id,read_at,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commercial_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  professional_id BIGINT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  service_type ENUM('nutrition','personal','complete') NOT NULL,
  description TEXT NULL,
  price_cents INT UNSIGNED NOT NULL,
  billing_cycle ENUM('monthly','quarterly','semiannual','annual','single') NOT NULL DEFAULT 'monthly',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_plan_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_plan_prof FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NULL,
  amount_cents INT UNSIGNED NOT NULL,
  status ENUM('pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  provider VARCHAR(60) NULL,
  provider_reference VARCHAR(255) NULL,
  due_at DATETIME NULL,
  paid_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_plan FOREIGN KEY (plan_id) REFERENCES commercial_plans(id) ON DELETE SET NULL,
  INDEX idx_payment_tenant_status (tenant_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(100) NULL,
  entity_id BIGINT UNSIGNED NULL,
  details JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_tenant_date (tenant_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
