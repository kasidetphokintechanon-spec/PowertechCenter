<?php
/**
 * Ensure personal_tasks table/columns exist (silent migration for API use).
 */
if (!function_exists('ensurePersonalTasksSchema')) {
    function ensurePersonalTasksColumn($conn, $table, $column, $definition) {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $check = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeCol}'");
        if ($check && $check->num_rows === 0) {
            @$conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeCol}` {$definition}");
        }
    }

    function ensurePersonalTasksSchema($conn) {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $exists = $conn->query("SHOW TABLES LIKE 'personal_tasks'");
        if (!$exists || $exists->num_rows === 0) {
            $sql = "CREATE TABLE IF NOT EXISTS personal_tasks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(50) NOT NULL,
                company VARCHAR(10) NULL,
                responsible_companies VARCHAR(100) NULL,
                department VARCHAR(100) NULL,
                task_type VARCHAR(20) NOT NULL DEFAULT 'personal',
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'todo',
                progress_pct TINYINT UNSIGNED DEFAULT 0,
                status_detail VARCHAR(255) NULL,
                priority VARCHAR(20) NOT NULL DEFAULT 'normal',
                due_date DATE NULL,
                tags VARCHAR(255) NULL,
                share_scope VARCHAR(20) NOT NULL DEFAULT 'private',
                assignee_id VARCHAR(50) NULL,
                assignee_name VARCHAR(255) NULL,
                building VARCHAR(255) NULL,
                workflow_stage VARCHAR(20) NOT NULL DEFAULT 'todo',
                legacy_job_ticket_id INT NULL,
                remind_month_start TINYINT(1) NOT NULL DEFAULT 0,
                remind_month_end TINYINT(1) NOT NULL DEFAULT 0,
                is_recurring TINYINT(1) NOT NULL DEFAULT 0,
                recurring_interval_months INT NOT NULL DEFAULT 12,
                remind_days_before INT NOT NULL DEFAULT 0,
                recurring_parent_id INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_personal_tasks_user (user_id),
                INDEX idx_personal_tasks_due (due_date),
                INDEX idx_personal_tasks_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            @$conn->query($sql);
        }

        ensurePersonalTasksColumn($conn, 'personal_tasks', 'company', "VARCHAR(10) NULL");
        ensurePersonalTasksColumn($conn, 'personal_tasks', 'responsible_companies', "VARCHAR(100) NULL AFTER company");
        ensurePersonalTasksColumn($conn, 'personal_tasks', 'department', "VARCHAR(100) NULL");
        ensurePersonalTasksColumn($conn, 'personal_tasks', 'task_type', "VARCHAR(20) NOT NULL DEFAULT 'personal'");
        ensurePersonalTasksColumn($conn, 'personal_tasks', 'status_detail', "VARCHAR(255) NULL");
        ensurePersonalTasksColumn($conn, 'personal_tasks', 'share_scope', "VARCHAR(20) NOT NULL DEFAULT 'private'");
        ensurePersonalTasksColumn($conn, 'personal_tasks', 'assignee_name', "VARCHAR(255) NULL");
        ensurePersonalTasksColumn($conn, 'personal_tasks', 'building', "VARCHAR(255) NULL");
        ensurePersonalTasksColumn($conn, 'personal_tasks', 'workflow_stage', "VARCHAR(20) NOT NULL DEFAULT 'todo'");
        ensurePersonalTasksColumn($conn, 'personal_tasks', 'legacy_job_ticket_id', "INT NULL");
        ensurePersonalTasksColumn($conn, 'personal_tasks', 'progress_pct', "TINYINT UNSIGNED DEFAULT 0");
        ensurePersonalTasksColumn($conn, 'personal_tasks', 'remind_month_start', "TINYINT(1) NOT NULL DEFAULT 0");
        ensurePersonalTasksColumn($conn, 'personal_tasks', 'remind_month_end', "TINYINT(1) NOT NULL DEFAULT 0");
        ensurePersonalTasksColumn($conn, 'personal_tasks', 'is_recurring', "TINYINT(1) NOT NULL DEFAULT 0");
        ensurePersonalTasksColumn($conn, 'personal_tasks', 'recurring_interval_months', "INT NOT NULL DEFAULT 12");
        ensurePersonalTasksColumn($conn, 'personal_tasks', 'remind_days_before', "INT NOT NULL DEFAULT 0");
        ensurePersonalTasksColumn($conn, 'personal_tasks', 'recurring_parent_id', "INT NULL");

        $hist = $conn->query("SHOW TABLES LIKE 'personal_task_history'");
        if (!$hist || $hist->num_rows === 0) {
            @$conn->query("CREATE TABLE IF NOT EXISTS personal_task_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                task_id INT NOT NULL,
                user_id VARCHAR(50) NOT NULL,
                user_name VARCHAR(255) NULL,
                action VARCHAR(50) NOT NULL DEFAULT 'update',
                old_status VARCHAR(20) NULL,
                new_status VARCHAR(20) NULL,
                old_priority VARCHAR(20) NULL,
                new_priority VARCHAR(20) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pt_history_task (task_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    }
}
