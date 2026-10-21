<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

if (($_GET['key'] ?? '') !== 'powertech2026') {
    http_response_code(403);
    die('403 Forbidden - add ?key=powertech2026');
}

$conn = new mysqli('127.0.0.1', 'root', 'Pow1216!', 'powertech_system');
if ($conn->connect_error) {
    die('DB Connection Failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

$ok  = [];
$err = [];

function runSQL($conn, $sql, $label) {
    global $ok, $err;
    if ($conn->query($sql)) {
        $ok[] = $label;
    } else {
        $err[] = $label . ' => ' . $conn->error;
    }
}

function hasCol($conn, $table, $col) {
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    return $r && $r->num_rows > 0;
}

function hasTable($conn, $table) {
    $r = $conn->query("SHOW TABLES LIKE '$table'");
    return $r && $r->num_rows > 0;
}

// ---- ADD MISSING COLUMNS ----

if (!hasCol($conn, 'employees', 'can_manage_stock')) {
    runSQL($conn, "ALTER TABLE `employees` ADD COLUMN `can_manage_stock` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_technician`", "employees.can_manage_stock");
} else { $ok[] = "SKIP employees.can_manage_stock (exists)"; }

if (!hasCol($conn, 'employees', 'name_th')) {
    runSQL($conn, "ALTER TABLE `employees` ADD COLUMN `name_th` VARCHAR(255) DEFAULT NULL AFTER `name`", "employees.name_th");
} else { $ok[] = "SKIP employees.name_th (exists)"; }

if (!hasCol($conn, 'employees', 'remember_token')) {
    runSQL($conn, "ALTER TABLE `employees` ADD COLUMN `remember_token` VARCHAR(64) DEFAULT NULL AFTER `responsible_department`", "employees.remember_token");
} else { $ok[] = "SKIP employees.remember_token (exists)"; }

if (!hasCol($conn, 'employees', 'birthdate')) {
    runSQL($conn, "ALTER TABLE `employees` ADD COLUMN `birthdate` DATE DEFAULT NULL AFTER `remember_token`", "employees.birthdate");
} else { $ok[] = "SKIP employees.birthdate (exists)"; }

if (!hasCol($conn, 'employees', 'start_date')) {
    runSQL($conn, "ALTER TABLE `employees` ADD COLUMN `start_date` DATE DEFAULT NULL AFTER `birthdate`", "employees.start_date");
} else { $ok[] = "SKIP employees.start_date (exists)"; }

if (!hasCol($conn, 'employees', 'employment_status')) {
    runSQL($conn, "ALTER TABLE `employees` ADD COLUMN `employment_status` ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER `start_date`", "employees.employment_status");
} else { $ok[] = "SKIP employees.employment_status (exists)"; }

if (!hasCol($conn, 'employees', 'updated_at')) {
    runSQL($conn, "ALTER TABLE `employees` ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`", "employees.updated_at");
} else { $ok[] = "SKIP employees.updated_at (exists)"; }

if (!hasCol($conn, 'employee_assignments', 'email')) {
    runSQL($conn, "ALTER TABLE `employee_assignments` ADD COLUMN `email` VARCHAR(100) DEFAULT NULL AFTER `department`", "employee_assignments.email");
} else { $ok[] = "SKIP employee_assignments.email (exists)"; }

if (!hasCol($conn, 'employee_assignments', 'is_primary')) {
    runSQL($conn, "ALTER TABLE `employee_assignments` ADD COLUMN `is_primary` TINYINT(1) DEFAULT 0 AFTER `email`", "employee_assignments.is_primary");
} else { $ok[] = "SKIP employee_assignments.is_primary (exists)"; }

if (!hasCol($conn, 'it_logs', 'request_uid')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `request_uid` VARCHAR(64) DEFAULT NULL AFTER `requester_id`", "it_logs.request_uid");
} else { $ok[] = "SKIP it_logs.request_uid (exists)"; }

if (!hasCol($conn, 'it_logs', 'root_cause')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `root_cause` TEXT DEFAULT NULL AFTER `solution`", "it_logs.root_cause");
} else { $ok[] = "SKIP it_logs.root_cause (exists)"; }

if (!hasCol($conn, 'it_logs', 'user_confirmed')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `user_confirmed` TINYINT(1) DEFAULT 0 AFTER `doc_number`", "it_logs.user_confirmed");
} else { $ok[] = "SKIP it_logs.user_confirmed (exists)"; }

if (!hasCol($conn, 'it_logs', 'user_confirmed_at')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `user_confirmed_at` DATETIME DEFAULT NULL AFTER `user_confirmed`", "it_logs.user_confirmed_at");
} else { $ok[] = "SKIP it_logs.user_confirmed_at (exists)"; }

if (!hasCol($conn, 'it_logs', 'user_confirmed_by')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `user_confirmed_by` VARCHAR(100) DEFAULT NULL AFTER `user_confirmed_at`", "it_logs.user_confirmed_by");
} else { $ok[] = "SKIP it_logs.user_confirmed_by (exists)"; }

if (!hasCol($conn, 'it_logs', 'repair_cost')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `repair_cost` DECIMAL(10,2) DEFAULT NULL AFTER `user_confirmed_by`", "it_logs.repair_cost");
} else { $ok[] = "SKIP it_logs.repair_cost (exists)"; }

if (!hasCol($conn, 'it_logs', 'rating')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `rating` INT(1) DEFAULT NULL AFTER `status`", "it_logs.rating");
} else { $ok[] = "SKIP it_logs.rating (exists)"; }

if (!hasCol($conn, 'it_logs', 'last_work_start_time')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `last_work_start_time` DATETIME DEFAULT NULL AFTER `completed_date`", "it_logs.last_work_start_time");
} else { $ok[] = "SKIP it_logs.last_work_start_time (exists)"; }

if (!hasCol($conn, 'it_logs', 'total_work_duration_seconds')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `total_work_duration_seconds` INT(11) NOT NULL DEFAULT 0 AFTER `last_work_start_time`", "it_logs.total_work_duration_seconds");
} else { $ok[] = "SKIP it_logs.total_work_duration_seconds (exists)"; }

if (!hasCol($conn, 'it_logs', 'urgency')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `urgency` VARCHAR(100) DEFAULT 'ปกติ' AFTER `type`", "it_logs.urgency");
} else { $ok[] = "SKIP it_logs.urgency (exists)"; }

if (!hasCol($conn, 'it_logs', 'appointment_date')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `appointment_date` DATETIME DEFAULT NULL AFTER `endDate`", "it_logs.appointment_date");
} else { $ok[] = "SKIP it_logs.appointment_date (exists)"; }

if (!hasCol($conn, 'it_logs', 'closed_date')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `closed_date` DATETIME DEFAULT NULL AFTER `appointment_date`", "it_logs.closed_date");
} else { $ok[] = "SKIP it_logs.closed_date (exists)"; }

if (!hasCol($conn, 'it_logs', 'evaluation_hardware')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `evaluation_hardware` TEXT DEFAULT NULL AFTER `closed_date`", "it_logs.evaluation_hardware");
} else { $ok[] = "SKIP it_logs.evaluation_hardware (exists)"; }

if (!hasCol($conn, 'it_logs', 'evaluation_software')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `evaluation_software` TEXT DEFAULT NULL AFTER `evaluation_hardware`", "it_logs.evaluation_software");
} else { $ok[] = "SKIP it_logs.evaluation_software (exists)"; }

if (!hasCol($conn, 'it_logs', 'evaluation_repair_cost')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `evaluation_repair_cost` DECIMAL(10,2) DEFAULT NULL AFTER `evaluation_software`", "it_logs.evaluation_repair_cost");
} else { $ok[] = "SKIP it_logs.evaluation_repair_cost (exists)"; }

if (!hasCol($conn, 'it_logs', 'evaluation_recommendation')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `evaluation_recommendation` VARCHAR(20) DEFAULT NULL AFTER `evaluation_repair_cost`", "it_logs.evaluation_recommendation");
} else { $ok[] = "SKIP it_logs.evaluation_recommendation (exists)"; }

if (!hasCol($conn, 'it_logs', 'evaluation_reason')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `evaluation_reason` TEXT DEFAULT NULL AFTER `evaluation_recommendation`", "it_logs.evaluation_reason");
} else { $ok[] = "SKIP it_logs.evaluation_reason (exists)"; }

if (!hasCol($conn, 'it_logs', 'pr_no')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `pr_no` VARCHAR(50) DEFAULT NULL AFTER `evaluation_reason`", "it_logs.pr_no");
} else { $ok[] = "SKIP it_logs.pr_no (exists)"; }

if (!hasCol($conn, 'it_logs', 'po_no')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `po_no` VARCHAR(50) DEFAULT NULL AFTER `pr_no`", "it_logs.po_no");
} else { $ok[] = "SKIP it_logs.po_no (exists)"; }

if (!hasCol($conn, 'it_logs', 'vendor_name')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `vendor_name` VARCHAR(255) DEFAULT NULL AFTER `po_no`", "it_logs.vendor_name");
} else { $ok[] = "SKIP it_logs.vendor_name (exists)"; }

if (!hasCol($conn, 'it_logs', 'eta_date')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `eta_date` DATE DEFAULT NULL AFTER `vendor_name`", "it_logs.eta_date");
} else { $ok[] = "SKIP it_logs.eta_date (exists)"; }

if (!hasCol($conn, 'it_logs', 'asset_id')) {
    runSQL($conn, "ALTER TABLE `it_logs` ADD COLUMN `asset_id` VARCHAR(100) DEFAULT NULL AFTER `department`", "it_logs.asset_id");
} else { $ok[] = "SKIP it_logs.asset_id (exists)"; }

if (!hasCol($conn, 'it_assets', 'parent_id')) {
    runSQL($conn, "ALTER TABLE `it_assets` ADD COLUMN `parent_id` INT(11) DEFAULT NULL AFTER `id`", "it_assets.parent_id");
} else { $ok[] = "SKIP it_assets.parent_id (exists)"; }

if (!hasCol($conn, 'it_assets', 'asset_pool')) {
    runSQL($conn, "ALTER TABLE `it_assets` ADD COLUMN `asset_pool` VARCHAR(50) NOT NULL DEFAULT 'general' AFTER `is_loanable`", "it_assets.asset_pool");
} else { $ok[] = "SKIP it_assets.asset_pool (exists)"; }

if (!hasCol($conn, 'it_assets', 'images')) {
    runSQL($conn, "ALTER TABLE `it_assets` ADD COLUMN `images` TEXT DEFAULT NULL AFTER `image_url`", "it_assets.images");
} else { $ok[] = "SKIP it_assets.images (exists)"; }

if (!hasCol($conn, 'it_assets', 'warranty_expires_on')) {
    runSQL($conn, "ALTER TABLE `it_assets` ADD COLUMN `warranty_expires_on` DATE DEFAULT NULL AFTER `purchase_date`", "it_assets.warranty_expires_on");
} else { $ok[] = "SKIP it_assets.warranty_expires_on (exists)"; }

if (!hasCol($conn, 'it_assets', 'checkout_time')) {
    runSQL($conn, "ALTER TABLE `it_assets` ADD COLUMN `checkout_time` DATETIME DEFAULT NULL", "it_assets.checkout_time");
} else { $ok[] = "SKIP it_assets.checkout_time (exists)"; }

if (!hasCol($conn, 'it_assets', 'custodian_employee_id')) {
    runSQL($conn, "ALTER TABLE `it_assets` ADD COLUMN `custodian_employee_id` VARCHAR(255) DEFAULT NULL AFTER `company`", "it_assets.custodian_employee_id");
} else { $ok[] = "SKIP it_assets.custodian_employee_id (exists)"; }

if (!hasCol($conn, 'it_assets', 'hostname')) {
    runSQL($conn, "ALTER TABLE `it_assets` ADD COLUMN `hostname` VARCHAR(255) DEFAULT NULL COMMENT 'PC Name / Hostname' AFTER `mac_address`", "it_assets.hostname");
} else { $ok[] = "SKIP it_assets.hostname (exists)"; }

if (!hasCol($conn, 'it_assets', 'custodian_type')) {
    runSQL($conn, "ALTER TABLE `it_assets` ADD COLUMN `custodian_type` VARCHAR(20) NOT NULL DEFAULT 'person' COMMENT 'person|department' AFTER `custodian_employee_id`", "it_assets.custodian_type");
} else { $ok[] = "SKIP it_assets.custodian_type (exists)"; }

if (!hasCol($conn, 'it_assets', 'custodian_dept_id')) {
    runSQL($conn, "ALTER TABLE `it_assets` ADD COLUMN `custodian_dept_id` INT(11) DEFAULT NULL COMMENT 'แผนกผู้ถือครอง (ใช้ร่วม)' AFTER `custodian_type`", "it_assets.custodian_dept_id");
} else { $ok[] = "SKIP it_assets.custodian_dept_id (exists)"; }

if (!hasCol($conn, 'it_loan_history', 'return_condition')) {
    runSQL($conn, "ALTER TABLE `it_loan_history` ADD COLUMN `return_condition` VARCHAR(255) DEFAULT NULL", "it_loan_history.return_condition");
} else { $ok[] = "SKIP it_loan_history.return_condition (exists)"; }

if (!hasCol($conn, 'it_loan_history', 'return_notes')) {
    runSQL($conn, "ALTER TABLE `it_loan_history` ADD COLUMN `return_notes` TEXT DEFAULT NULL", "it_loan_history.return_notes");
} else { $ok[] = "SKIP it_loan_history.return_notes (exists)"; }

if (!hasCol($conn, 'announcements', 'level')) {
    runSQL($conn, "ALTER TABLE `announcements` ADD COLUMN `level` VARCHAR(50) DEFAULT 'info' AFTER `content`", "announcements.level");
} else { $ok[] = "SKIP announcements.level (exists)"; }

if (!hasCol($conn, 'announcements', 'category')) {
    runSQL($conn, "ALTER TABLE `announcements` ADD COLUMN `category` VARCHAR(50) DEFAULT 'hr' AFTER `level`", "announcements.category");
} else { $ok[] = "SKIP announcements.category (exists)"; }

if (!hasCol($conn, 'announcements', 'expirationDate')) {
    runSQL($conn, "ALTER TABLE `announcements` ADD COLUMN `expirationDate` DATE DEFAULT NULL AFTER `category`", "announcements.expirationDate");
} else { $ok[] = "SKIP announcements.expirationDate (exists)"; }

// ---- CREATE MISSING TABLES ----

if (!hasTable($conn, 'chat_group_invites')) {
    runSQL($conn, "CREATE TABLE `chat_group_invites` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `group_id` int(11) NOT NULL,
      `inviter_id` varchar(50) NOT NULL,
      `invitee_id` varchar(50) NOT NULL,
      `status` enum('pending','accepted','declined','expired') NOT NULL DEFAULT 'pending',
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `expired_at` datetime DEFAULT NULL,
      `responded_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_chat_group_invites_group` (`group_id`),
      KEY `idx_chat_group_invites_invitee` (`invitee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE chat_group_invites");
} else { $ok[] = "SKIP TABLE chat_group_invites (exists)"; }

if (!hasTable($conn, 'chat_group_member_logs')) {
    runSQL($conn, "CREATE TABLE `chat_group_member_logs` (
      `id` bigint(20) NOT NULL AUTO_INCREMENT,
      `group_id` int(11) NOT NULL,
      `actor_id` varchar(50) DEFAULT NULL,
      `target_id` varchar(50) DEFAULT NULL,
      `action` enum('invite_sent','invite_accepted','invite_declined','member_joined','member_removed') NOT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `note` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_chat_group_member_logs_group` (`group_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE chat_group_member_logs");
} else { $ok[] = "SKIP TABLE chat_group_member_logs (exists)"; }

if (!hasTable($conn, 'job_positions')) {
    runSQL($conn, "CREATE TABLE `job_positions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `sort_order` int(11) NOT NULL DEFAULT 999,
      `is_active` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_position_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE job_positions");
} else { $ok[] = "SKIP TABLE job_positions (exists)"; }

if (!hasTable($conn, 'job_tickets')) {
    runSQL($conn, "CREATE TABLE `job_tickets` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `company` varchar(10) NOT NULL,
      `department` varchar(100) DEFAULT NULL,
      `building` varchar(255) DEFAULT NULL,
      `job_name` varchar(255) NOT NULL,
      `responsible` varchar(255) DEFAULT NULL,
      `deadline` date DEFAULT NULL,
      `is_receive` tinyint(1) DEFAULT 0,
      `is_doing` tinyint(1) DEFAULT 0,
      `is_send` tinyint(1) DEFAULT 0,
      `is_approve` tinyint(1) DEFAULT 0,
      `is_done` tinyint(1) DEFAULT 0,
      `last_update` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `created_by_id` varchar(50) DEFAULT NULL,
      `created_by_name` varchar(100) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE job_tickets");
} else { $ok[] = "SKIP TABLE job_tickets (exists)"; }

if (!hasTable($conn, 'kb_articles')) {
    runSQL($conn, "CREATE TABLE `kb_articles` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `category_id` int(11) NOT NULL,
      `title` varchar(255) NOT NULL,
      `content` longtext NOT NULL,
      `tags` varchar(255) DEFAULT NULL,
      `author_id` varchar(50) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `view_count` int(11) NOT NULL DEFAULT 0,
      `is_published` tinyint(1) NOT NULL DEFAULT 1,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE kb_articles");
} else { $ok[] = "SKIP TABLE kb_articles (exists)"; }

if (!hasTable($conn, 'kb_categories')) {
    runSQL($conn, "CREATE TABLE `kb_categories` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `description` text DEFAULT NULL,
      `icon` varchar(100) DEFAULT 'fa-book',
      `sort_order` int(11) DEFAULT 99,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE kb_categories");
} else { $ok[] = "SKIP TABLE kb_categories (exists)"; }

if (!hasTable($conn, 'locations')) {
    runSQL($conn, "CREATE TABLE `locations` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `company` varchar(10) DEFAULT NULL,
      `is_active` tinyint(1) DEFAULT 1,
      `image` varchar(255) DEFAULT NULL,
      `sort_order` int(11) DEFAULT 0,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE locations");
} else { $ok[] = "SKIP TABLE locations (exists)"; }

if (!hasTable($conn, 'password_reset_tokens')) {
    runSQL($conn, "CREATE TABLE `password_reset_tokens` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` varchar(50) NOT NULL,
      `token` varchar(255) NOT NULL,
      `expires_at` datetime NOT NULL,
      `is_used` tinyint(1) NOT NULL DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `token` (`token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE password_reset_tokens");
} else { $ok[] = "SKIP TABLE password_reset_tokens (exists)"; }

if (!hasTable($conn, 'permission_roles')) {
    runSQL($conn, "CREATE TABLE `permission_roles` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `description` varchar(255) DEFAULT NULL,
      `permissions_json` text NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE permission_roles");
} else { $ok[] = "SKIP TABLE permission_roles (exists)"; }

if (!hasTable($conn, 'personal_tasks')) {
    runSQL($conn, "CREATE TABLE `personal_tasks` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` varchar(50) NOT NULL,
      `company` varchar(10) DEFAULT NULL,
      `responsible_companies` varchar(100) DEFAULT NULL,
      `department` varchar(100) DEFAULT NULL,
      `task_type` varchar(20) NOT NULL DEFAULT 'personal',
      `title` varchar(255) NOT NULL,
      `description` text DEFAULT NULL,
      `status` varchar(20) NOT NULL DEFAULT 'todo',
      `progress_pct` tinyint(3) unsigned DEFAULT 0,
      `status_detail` varchar(255) DEFAULT NULL,
      `priority` varchar(20) NOT NULL DEFAULT 'normal',
      `due_date` date DEFAULT NULL,
      `tags` varchar(255) DEFAULT NULL,
      `share_scope` varchar(20) NOT NULL DEFAULT 'private',
      `assignee_id` varchar(50) DEFAULT NULL,
      `assignee_name` varchar(255) DEFAULT NULL,
      `building` varchar(255) DEFAULT NULL,
      `workflow_stage` varchar(20) NOT NULL DEFAULT 'todo',
      `legacy_job_ticket_id` int(11) DEFAULT NULL,
      `remind_month_start` tinyint(1) NOT NULL DEFAULT 0,
      `remind_month_end` tinyint(1) NOT NULL DEFAULT 0,
      `is_recurring` tinyint(1) NOT NULL DEFAULT 0,
      `recurring_interval_months` int(11) NOT NULL DEFAULT 12,
      `remind_days_before` int(11) NOT NULL DEFAULT 0,
      `recurring_parent_id` int(11) DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_personal_tasks_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE personal_tasks");
} else { $ok[] = "SKIP TABLE personal_tasks (exists)"; }

if (!hasTable($conn, 'personal_task_comments')) {
    runSQL($conn, "CREATE TABLE `personal_task_comments` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `task_id` int(11) NOT NULL,
      `user_id` varchar(64) DEFAULT NULL,
      `user_name` varchar(100) DEFAULT NULL,
      `comment` text DEFAULT NULL,
      `progress_pct` tinyint(3) unsigned DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_comment_task` (`task_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE personal_task_comments");
} else { $ok[] = "SKIP TABLE personal_task_comments (exists)"; }

if (!hasTable($conn, 'personal_task_history')) {
    runSQL($conn, "CREATE TABLE `personal_task_history` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `task_id` int(11) NOT NULL,
      `user_id` varchar(50) NOT NULL,
      `user_name` varchar(120) DEFAULT NULL,
      `action` varchar(50) NOT NULL,
      `old_status` varchar(20) DEFAULT NULL,
      `new_status` varchar(20) DEFAULT NULL,
      `old_priority` varchar(20) DEFAULT NULL,
      `new_priority` varchar(20) DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_history_task` (`task_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE personal_task_history");
} else { $ok[] = "SKIP TABLE personal_task_history (exists)"; }

if (!hasTable($conn, 'print_logs')) {
    runSQL($conn, "CREATE TABLE `print_logs` (
      `id` bigint(20) NOT NULL AUTO_INCREMENT,
      `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `user_id` varchar(50) NOT NULL,
      `user_name` varchar(120) DEFAULT NULL,
      `role` varchar(20) DEFAULT NULL,
      `document_type` varchar(40) NOT NULL,
      `document_id` varchar(60) NOT NULL,
      `action` varchar(40) NOT NULL,
      `client_ip` varchar(45) DEFAULT NULL,
      `user_agent` text DEFAULT NULL,
      `client_meta` text DEFAULT NULL,
      `prev_hash` char(64) NOT NULL,
      `entry_hash` char(64) NOT NULL,
      `signature` char(64) NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE print_logs");
} else { $ok[] = "SKIP TABLE print_logs (exists)"; }

if (!hasTable($conn, 'services')) {
    runSQL($conn, "CREATE TABLE `services` (
      `id` varchar(50) NOT NULL,
      `title` varchar(255) NOT NULL,
      `description` text DEFAULT NULL,
      `icon` varchar(100) DEFAULT NULL,
      `color` varchar(20) DEFAULT NULL,
      `sort_order` int(11) NOT NULL DEFAULT 99,
      `button_text` varchar(100) DEFAULT 'เข้าใช้งาน',
      `url_primary` varchar(255) DEFAULT NULL,
      `url_fallback` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE services");
} else { $ok[] = "SKIP TABLE services (exists)"; }

if (!hasTable($conn, 'setup_history')) {
    runSQL($conn, "CREATE TABLE `setup_history` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `log_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `asset_code` varchar(100) NOT NULL,
      `serial_number` varchar(100) DEFAULT NULL,
      `pc_name` varchar(100) DEFAULT NULL,
      `user_company` varchar(50) DEFAULT NULL,
      `user_department` varchar(100) DEFAULT NULL,
      `user_name` varchar(255) NOT NULL,
      `setup_date` date NOT NULL,
      `tech_name` varchar(255) NOT NULL,
      `notes` text DEFAULT NULL,
      `specific_software` text DEFAULT NULL,
      `checklist_data` longtext NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE setup_history");
} else { $ok[] = "SKIP TABLE setup_history (exists)"; }

if (hasTable($conn, 'setup_records') && !hasCol($conn, 'setup_records', 'it_asset_id')) {
    runSQL($conn, "ALTER TABLE `setup_records` ADD COLUMN `it_asset_id` INT(11) DEFAULT NULL COMMENT 'FK it_assets.id' AFTER `asset_code`", "setup_records.it_asset_id");
} elseif (hasTable($conn, 'setup_records')) { $ok[] = "SKIP setup_records.it_asset_id (exists)"; }

if (!hasTable($conn, 'stock_items')) {
    runSQL($conn, "CREATE TABLE `stock_items` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `item_code` varchar(50) NOT NULL,
      `name` varchar(255) NOT NULL,
      `unit` varchar(50) DEFAULT 'ชิ้น',
      `price_per_unit` decimal(10,2) DEFAULT 0.00,
      `min_stock` int(11) DEFAULT 0,
      `max_stock` int(11) DEFAULT 0,
      `type` enum('stationery','maid') NOT NULL DEFAULT 'stationery',
      `company` enum('PTA','PT4','PTE') NOT NULL DEFAULT 'PTA',
      `is_active` tinyint(1) DEFAULT 1,
      `category` varchar(50) NOT NULL DEFAULT 'other',
      `description` varchar(500) DEFAULT NULL,
      `image_url` varchar(255) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_item_code_company` (`item_code`,`company`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", "CREATE TABLE stock_items");
} else { $ok[] = "SKIP TABLE stock_items (exists)"; }

if (!hasTable($conn, 'stock_request_items')) {
    runSQL($conn, "CREATE TABLE `stock_request_items` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `request_id` int(11) NOT NULL,
      `item_id` int(11) NOT NULL,
      `qty_requested` int(11) NOT NULL DEFAULT 1,
      `qty_approved` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", "CREATE TABLE stock_request_items");
} else { $ok[] = "SKIP TABLE stock_request_items (exists)"; }

if (!hasTable($conn, 'stock_requests')) {
    runSQL($conn, "CREATE TABLE `stock_requests` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `company` enum('PTA','PT4','PTE') NOT NULL,
      `stock_type` enum('stationery','maid') NOT NULL,
      `branch` varchar(20) DEFAULT NULL,
      `requester_id` varchar(50) NOT NULL,
      `requester_name` varchar(255) NOT NULL,
      `department` varchar(255) DEFAULT NULL,
      `request_date` datetime DEFAULT CURRENT_TIMESTAMP,
      `status` enum('pending','approved','rejected') DEFAULT 'pending',
      `deny_reason` text DEFAULT NULL,
      `approved_by` varchar(255) DEFAULT NULL,
      `approved_at` datetime DEFAULT NULL,
      `notes` text DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", "CREATE TABLE stock_requests");
} else { $ok[] = "SKIP TABLE stock_requests (exists)"; }

if (!hasTable($conn, 'stock_transactions')) {
    runSQL($conn, "CREATE TABLE `stock_transactions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `item_id` int(11) NOT NULL,
      `company` enum('PTA','PT4','PTE') NOT NULL,
      `type` enum('received','issued','adjusted') NOT NULL,
      `quantity` int(11) NOT NULL,
      `unit_cost` decimal(10,2) DEFAULT NULL,
      `transaction_date` datetime DEFAULT CURRENT_TIMESTAMP,
      `branch` varchar(20) DEFAULT NULL,
      `requester_name` varchar(255) DEFAULT NULL,
      `requester_id` varchar(50) DEFAULT NULL,
      `request_id` int(11) DEFAULT NULL,
      `notes` text DEFAULT NULL,
      `created_by` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci", "CREATE TABLE stock_transactions");
} else { $ok[] = "SKIP TABLE stock_transactions (exists)"; }

if (!hasTable($conn, 'suppliers')) {
    runSQL($conn, "CREATE TABLE `suppliers` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `company` varchar(10) NOT NULL DEFAULT '',
      `name` varchar(255) NOT NULL,
      `tax_id` varchar(50) DEFAULT NULL,
      `phone` varchar(50) DEFAULT NULL,
      `email` varchar(255) DEFAULT NULL,
      `address` text DEFAULT NULL,
      `contact_person` varchar(255) DEFAULT NULL,
      `notes` text DEFAULT NULL,
      `is_active` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE suppliers");
} else { $ok[] = "SKIP TABLE suppliers (exists)"; }

if (!hasTable($conn, 'system_settings')) {
    runSQL($conn, "CREATE TABLE `system_settings` (
      `setting_key` varchar(50) NOT NULL,
      `setting_value` text DEFAULT NULL,
      PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE system_settings");
} else { $ok[] = "SKIP TABLE system_settings (exists)"; }

if (!hasTable($conn, 'user_permission_roles')) {
    runSQL($conn, "CREATE TABLE `user_permission_roles` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` varchar(50) NOT NULL,
      `role_id` int(11) NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_user_role` (`user_id`,`role_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE user_permission_roles");
} else { $ok[] = "SKIP TABLE user_permission_roles (exists)"; }

if (!hasTable($conn, 'user_permissions')) {
    runSQL($conn, "CREATE TABLE `user_permissions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` varchar(50) NOT NULL,
      `permission_key` varchar(100) NOT NULL,
      `is_granted` tinyint(1) NOT NULL DEFAULT 1,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_user_perm` (`user_id`,`permission_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE user_permissions");
} else { $ok[] = "SKIP TABLE user_permissions (exists)"; }

if (!hasTable($conn, 'daily_checklist_items')) {
    runSQL($conn, "CREATE TABLE `daily_checklist_items` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `company` varchar(10) NOT NULL DEFAULT '',
      `title` varchar(255) NOT NULL,
      `description` text DEFAULT NULL,
      `category` varchar(100) DEFAULT NULL,
      `sort_order` int(11) NOT NULL DEFAULT 0,
      `is_active` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE daily_checklist_items");
} else { $ok[] = "SKIP TABLE daily_checklist_items (exists)"; }

if (!hasTable($conn, 'daily_checklist_logs')) {
    runSQL($conn, "CREATE TABLE `daily_checklist_logs` (
      `id` bigint(20) NOT NULL AUTO_INCREMENT,
      `checklist_item_id` int(11) NOT NULL,
      `checklist_date` date NOT NULL,
      `company` varchar(10) NOT NULL DEFAULT '',
      `user_id` varchar(50) NOT NULL,
      `user_name` varchar(120) DEFAULT NULL,
      `checked_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `notes` text DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_item_date` (`checklist_item_id`,`checklist_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", "CREATE TABLE daily_checklist_logs");
} else { $ok[] = "SKIP TABLE daily_checklist_logs (exists)"; }

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>DB Migration</title>
<style>
  body { font-family: monospace; background:#1a1a2e; color:#eee; padding:2rem; }
  h1   { color:#4fc3f7; }
  .ok   { color:#81c784; }
  .skip { color:#777; }
  .err  { color:#e57373; background:#2d1a1a; padding:3px 8px; border-radius:4px; margin:3px 0; display:block; }
  .box  { background:#0d0d1a; border:1px solid #333; border-radius:8px; padding:1rem; margin:1rem 0; }
  .warn { background:#b71c1c; color:#fff; padding:1rem; border-radius:8px; margin-top:2rem; }
</style>
</head>
<body>
<h1>DB Migration Results</h1>
<div class="box">
<?php foreach ($ok as $r): ?>
  <div class="<?= (strpos($r,'SKIP') === 0) ? 'skip' : 'ok' ?>">
    <?= htmlspecialchars($r) ?>
  </div>
<?php endforeach; ?>
<?php foreach ($err as $e): ?>
  <span class="err"><?= htmlspecialchars($e) ?></span>
<?php endforeach; ?>
</div>
<p class="ok">✅ OK: <?= count($ok) ?></p>
<p style="color:#e57373">❌ Errors: <?= count($err) ?></p>
<div class="warn">⚠️ ลบไฟล์ migrate.php ออกจาก server ทันทีหลังรันเสร็จ!</div>
</body>
</html>
