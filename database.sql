CREATE DATABASE IF NOT EXISTS metroasia
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE metroasia;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin','admin','staff') NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    nickname VARCHAR(80) NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(60) NOT NULL,
    birth_month TINYINT UNSIGNED NULL,
    birth_year SMALLINT UNSIGNED NULL,
    skill_level ENUM('2.0','2.5','3.0','3.5','4.0','4.5','5.0') NULL,
    data_privacy_act_agree TINYINT(1) NOT NULL DEFAULT 0,
    data_privacy_policy_version VARCHAR(40) NULL,
    data_privacy_agreed_at DATETIME NULL,
    member_lookup_token VARCHAR(64) NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    display_number INT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    court_type VARCHAR(80) NOT NULL DEFAULT 'Indoor',
    surface_label VARCHAR(80) NULL,
    supported_sports VARCHAR(160) NOT NULL DEFAULT 'Pickleball',
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS time_slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period VARCHAR(80) NOT NULL,
    label VARCHAR(80) NOT NULL UNIQUE,
    starts_at TIME NOT NULL,
    ends_at TIME NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    court_id INT UNSIGNED NOT NULL,
    sport ENUM('Pickleball','Basketball','Volleyball') NOT NULL,
    day_of_week ENUM('Any','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL DEFAULT 'Any',
    time_slot_id INT UNSIGNED NOT NULL,
    rate_per_hour DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_rate_lookup (court_id, sport, day_of_week, time_slot_id),
    INDEX idx_rates_lookup (court_id, sport, day_of_week, time_slot_id),
    CONSTRAINT fk_rate_court FOREIGN KEY (court_id) REFERENCES courts(id),
    CONSTRAINT fk_rate_time_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rate_id INT UNSIGNED NULL,
    admin_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    previous_payload JSON NULL,
    new_payload JSON NULL,
    reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rate_audit_rate (rate_id, created_at),
    CONSTRAINT fk_rate_audit_rate FOREIGN KEY (rate_id) REFERENCES rates(id) ON DELETE SET NULL,
    CONSTRAINT fk_rate_audit_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_channels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    channel_type ENUM('qr','bank') NOT NULL DEFAULT 'bank',
    account_name VARCHAR(160) NULL,
    account_number VARCHAR(120) NULL,
    bank_name VARCHAR(160) NULL,
    instructions TEXT NULL,
    qr_path VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_config (
    config_key VARCHAR(80) PRIMARY KEY,
    config_value TEXT NULL,
    label VARCHAR(120) NOT NULL,
    field_type ENUM('text','textarea','url','image') NOT NULL DEFAULT 'text',
    sort_order INT NOT NULL DEFAULT 0,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_site_config_updated_by FOREIGN KEY (updated_by) REFERENCES admin_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category ENUM('Pickleball','Miami','Lakers') NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    uploaded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gallery_public (category, is_active, sort_order, id),
    CONSTRAINT fk_gallery_images_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS court_blocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    block_date DATE NOT NULL,
    time_slot_id INT UNSIGNED NOT NULL,
    court_id INT UNSIGNED NULL,
    sport ENUM('Pickleball','Basketball','Volleyball') NULL,
    reason VARCHAR(80) NOT NULL,
    notes VARCHAR(255) NULL,
    status ENUM('Active','Cancelled') NOT NULL DEFAULT 'Active',
    created_by INT UNSIGNED NULL,
    cancelled_by INT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_court_blocks_lookup (block_date, time_slot_id, court_id, sport, status),
    CONSTRAINT fk_court_block_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots(id),
    CONSTRAINT fk_court_block_court FOREIGN KEY (court_id) REFERENCES courts(id),
    CONSTRAINT fk_court_block_created_by FOREIGN KEY (created_by) REFERENCES admin_users(id),
    CONSTRAINT fk_court_block_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES admin_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS override_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(40) NOT NULL,
    target_id VARCHAR(80) NULL,
    conflict_summary TEXT NULL,
    payload JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_override_logs_created (created_at),
    CONSTRAINT fk_override_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS court_bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_reference VARCHAR(40) NULL,
    member_id INT UNSIGNED NULL,
    booking_date DATE NOT NULL,
    time_slot_id INT UNSIGNED NOT NULL,
    court_id INT UNSIGNED NOT NULL,
    sport ENUM('Pickleball','Basketball','Volleyball') NOT NULL DEFAULT 'Pickleball',
    status ENUM('Held','Booked','Cancelled') NOT NULL DEFAULT 'Held',
    customer_name VARCHAR(160) NOT NULL,
    player_nickname VARCHAR(80) NULL,
    customer_email VARCHAR(190) NULL,
    customer_phone VARCHAR(60) NOT NULL,
    customer_notes TEXT NULL,
    payment_method VARCHAR(80) NOT NULL,
    receipt_path VARCHAR(255) NULL,
    base_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
    final_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    rate_snapshot JSON NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    cancelled_by INT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    cancel_reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_booking_reference (booking_reference),
    INDEX idx_court_lookup (booking_date, time_slot_id, court_id, status),
    INDEX idx_court_member (member_id, booking_date),
    CONSTRAINT fk_court_booking_member FOREIGN KEY (member_id) REFERENCES members(id),
    CONSTRAINT fk_court_booking_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots(id),
    CONSTRAINT fk_court_booking_court FOREIGN KEY (court_id) REFERENCES courts(id),
    CONSTRAINT fk_court_booking_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES admin_users(id),
    CONSTRAINT fk_court_booking_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES admin_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_entrance_fee_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 50.00,
    payment_date DATE NOT NULL,
    payment_time TIME NOT NULL,
    booking_id INT UNSIGNED NULL,
    reference_number VARCHAR(80) NULL,
    payment_method VARCHAR(80) NULL,
    receipt_path VARCHAR(255) NULL,
    recorded_by INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_member_entrance_member (member_id, payment_date, payment_time),
    CONSTRAINT fk_entrance_fee_member FOREIGN KEY (member_id) REFERENCES members(id),
    CONSTRAINT fk_entrance_fee_booking FOREIGN KEY (booking_id) REFERENCES court_bookings(id) ON DELETE SET NULL,
    CONSTRAINT fk_entrance_fee_admin FOREIGN KEY (recorded_by) REFERENCES admin_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS open_play_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    session_date DATE NOT NULL,
    session_time VARCHAR(80) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    capacity INT UNSIGNED NOT NULL,
    level_label VARCHAR(80) NOT NULL,
    description TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS open_play_reservations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NULL,
    session_id INT UNSIGNED NOT NULL,
    status ENUM('Held','Booked','Cancelled') NOT NULL DEFAULT 'Held',
    customer_name VARCHAR(160) NOT NULL,
    customer_email VARCHAR(190) NULL,
    customer_phone VARCHAR(60) NOT NULL,
    payment_method VARCHAR(80) NOT NULL,
    receipt_path VARCHAR(255) NULL,
    final_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    rate_snapshot JSON NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    cancelled_by INT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    cancel_reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_open_play_lookup (session_id, status),
    INDEX idx_open_play_member (member_id),
    CONSTRAINT fk_open_play_reservation_member FOREIGN KEY (member_id) REFERENCES members(id),
    CONSTRAINT fk_open_play_reservation_session FOREIGN KEY (session_id) REFERENCES open_play_sessions(id),
    CONSTRAINT fk_open_play_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES admin_users(id),
    CONSTRAINT fk_open_play_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES admin_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
