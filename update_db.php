<?php
require_once 'config.php';

// ตรวจสอบสิทธิ์ Admin (อนุญาตให้รันถ้า Login เป็น Admin แล้ว)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("<h3>⛔ Access Denied</h3><p>คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (สำหรับ Admin เท่านั้น)</p><p>Current Role: " . ($_SESSION['role'] ?? 'Guest') . "</p><a href='login.html'>เข้าสู่ระบบ</a>");
}

header('Content-Type: text/html; charset=utf-8');

// ตั้งค่าการเชื่อมต่อ (ต้องตรงกับ api.php)
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$db_name = DB_NAME;

$conn = new mysqli($host, $user, $pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

echo "<h2>กำลังตรวจสอบฐานข้อมูล...</h2>";

// Helper Function: ตรวจสอบและเพิ่มคอลัมน์ถ้ายังไม่มี (ป้องกัน Duplicate Column Error)
function addColumnIfNotExists($conn, $table, $column, $definition) {
    $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($check->num_rows == 0) {
        if ($conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition") === TRUE) {
            echo "<p style='color: green;'>✅ เพิ่มคอลัมน์ '$column' ในตาราง '$table' เรียบร้อยแล้ว</p>";
        } else {
            echo "<p style='color: red;'>❌ Error adding column '$column' to '$table': " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ คอลัมน์ '$column' ในตาราง '$table' มีอยู่แล้ว</p>";
    }
}

function ensurePermissionRole($conn, $name, $description, $permissions) {
    $stmt = $conn->prepare("SELECT id FROM permission_roles WHERE name = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->fetch_assoc()) {
            echo "<p style='color: blue;'>ℹ️ Permission Role '$name' มีอยู่แล้ว</p>";
            return;
        }
    }
    $json = json_encode(array_values(array_unique($permissions)));
    $stmtIns = $conn->prepare("INSERT INTO permission_roles (name, description, permissions_json) VALUES (?, ?, ?)");
    if ($stmtIns) {
        $stmtIns->bind_param("sss", $name, $description, $json);
        if ($stmtIns->execute()) {
            echo "<p style='color: green;'>✅ เพิ่ม Permission Role '$name' เรียบร้อยแล้ว</p>";
        } else {
            echo "<p style='color: red;'>❌ Error inserting Permission Role '$name': " . $conn->error . "</p>";
        }
    }
}

// 1. ตรวจสอบและเพิ่มคอลัมน์ responsible_department ในตาราง employees
addColumnIfNotExists($conn, 'employees', 'responsible_department', 'VARCHAR(255) NULL AFTER role');
addColumnIfNotExists($conn, 'employees', 'remember_token', 'VARCHAR(64) NULL AFTER responsible_department');

// 2. ตรวจสอบและเพิ่มคอลัมน์ images ในตาราง it_assets (สำหรับเก็บรูปหลายรูป)
addColumnIfNotExists($conn, 'it_assets', 'images', 'TEXT NULL AFTER image_url');
addColumnIfNotExists($conn, 'it_assets', 'is_loanable', 'TINYINT(1) DEFAULT 0 AFTER status');
addColumnIfNotExists($conn, 'it_assets', 'parent_id', 'INT NULL AFTER id'); // สำหรับเชื่อมโยงทรัพย์สินแม่-ลูก (Set)

// 3. อัปเดตสถานะ 'ว่าง' เป็น 'ใช้งานปกติ'
$conn->query("UPDATE it_assets SET status = 'ใช้งานปกติ' WHERE status = 'ว่าง'");
echo "<p style='color: green;'>✅ อัปเดตสถานะพัสดุจาก 'ว่าง' เป็น 'ใช้งานปกติ' เรียบร้อยแล้ว</p>";

// 4. ตรวจสอบและสร้างตาราง job_tickets (สำหรับระบบ Job Ticket)
$table_check = $conn->query("SHOW TABLES LIKE 'job_tickets'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE job_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company VARCHAR(10) NOT NULL COMMENT 'PT4, PTA, PTE',
        department VARCHAR(100) NULL,
        building VARCHAR(255) COMMENT 'รายชื่อตึก',
        job_name VARCHAR(255) NOT NULL,
        responsible VARCHAR(255),
        deadline DATE,
        is_receive TINYINT(1) DEFAULT 0,
        is_doing TINYINT(1) DEFAULT 0,
        is_send TINYINT(1) DEFAULT 0,
        is_approve TINYINT(1) DEFAULT 0,
        is_done TINYINT(1) DEFAULT 0,
        last_update DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by_id VARCHAR(50) NULL,
        created_by_name VARCHAR(100) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ สร้างตาราง 'job_tickets' เรียบร้อยแล้ว</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating job_tickets: " . $conn->error . "</p>";
    }
} else {
    // ถ้ามีตารางแล้ว ให้เช็คคอลัมน์ที่อาจจะขาด
    addColumnIfNotExists($conn, 'job_tickets', 'department', 'VARCHAR(100) NULL AFTER company');
    addColumnIfNotExists($conn, 'job_tickets', 'created_by_id', 'VARCHAR(50) NULL AFTER deadline');
    addColumnIfNotExists($conn, 'job_tickets', 'created_by_name', 'VARCHAR(100) NULL AFTER created_by_id');
    addColumnIfNotExists($conn, 'job_tickets', 'last_update', 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    echo "<p style='color: blue;'>ℹ️ ตรวจสอบตาราง 'job_tickets' เรียบร้อยแล้ว</p>";
}

// 5. ตรวจสอบและสร้างตาราง chat_typing (สำหรับสถานะการพิมพ์ แทนการใช้ไฟล์ .json)
$table_check = $conn->query("SHOW TABLES LIKE 'chat_typing'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE chat_typing (
        user_id VARCHAR(50) PRIMARY KEY,
        typing_to VARCHAR(50) NOT NULL,
        timestamp INT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ สร้างตาราง 'chat_typing' เรียบร้อยแล้ว</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating chat_typing: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ ตาราง 'chat_typing' มีอยู่แล้ว</p>";
}

// 6. ตรวจสอบและสร้างตาราง chat_group_invites (สำหรับคำเชิญเข้ากลุ่มแชท)
$table_check = $conn->query("SHOW TABLES LIKE 'chat_group_invites'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE chat_group_invites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        inviter_id VARCHAR(50) NOT NULL,
        invitee_id VARCHAR(50) NOT NULL,
        status ENUM('pending','accepted','declined','expired') NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expired_at DATETIME NULL,
        responded_at DATETIME NULL,
        INDEX idx_chat_group_invites_group (group_id),
        INDEX idx_chat_group_invites_invitee (invitee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ สร้างตาราง 'chat_group_invites' เรียบร้อยแล้ว</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating chat_group_invites: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ ตาราง 'chat_group_invites' มีอยู่แล้ว</p>";
}

// 7. ตรวจสอบและสร้างตาราง chat_group_member_logs (สำหรับประวัติสมาชิกกลุ่มแชท)
$table_check = $conn->query("SHOW TABLES LIKE 'chat_group_member_logs'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE chat_group_member_logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        actor_id VARCHAR(50) NULL,
        target_id VARCHAR(50) NULL,
        action ENUM('invite_sent','invite_accepted','invite_declined','member_joined','member_removed') NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        note VARCHAR(255) NULL,
        INDEX idx_chat_group_member_logs_group (group_id),
        INDEX idx_chat_group_member_logs_actor (actor_id),
        INDEX idx_chat_group_member_logs_target (target_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ สร้างตาราง 'chat_group_member_logs' เรียบร้อยแล้ว</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating chat_group_member_logs: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ ตาราง 'chat_group_member_logs' มีอยู่แล้ว</p>";
}

// 8. ตรวจสอบและสร้างตาราง locations (สำหรับ Dropdown สถานที่)
$table_check = $conn->query("SHOW TABLES LIKE 'locations'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        company VARCHAR(10) NULL COMMENT 'PTA, PT4, PTE or NULL for all',
        is_active TINYINT(1) DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ สร้างตาราง 'locations' เรียบร้อยแล้ว</p>";
        // Insert default data
        $conn->query("INSERT INTO locations (name, company) VALUES ('Office F4', 'PTA'), ('Office F5', 'PT4'), ('Office F16', 'PTA'), ('Production Line 1', 'PTA'), ('Warehouse', 'PT4'), ('Meeting Room', NULL), ('Canteen', NULL)");
    } else {
        echo "<p style='color: red;'>❌ Error creating locations: " . $conn->error . "</p>";
    }
} else {
    addColumnIfNotExists($conn, 'locations', 'image', "VARCHAR(255) DEFAULT NULL COMMENT 'Path to map image'");
    addColumnIfNotExists($conn, 'locations', 'sort_order', "INT(11) DEFAULT 0");
}

// 9. ตรวจสอบและสร้างตาราง personal_tasks (สำหรับบันทึกงานส่วนตัวของผู้ใช้)
$table_check = $conn->query("SHOW TABLES LIKE 'personal_tasks'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE personal_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        company VARCHAR(10) NULL,
        responsible_companies VARCHAR(100) NULL,
        department VARCHAR(100) NULL,
        task_type VARCHAR(20) NOT NULL DEFAULT 'personal',
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'todo',
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
        recurring_interval_months INT(11) NOT NULL DEFAULT 12,
        remind_days_before INT(11) NOT NULL DEFAULT 0,
        recurring_parent_id INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_personal_tasks_user (user_id),
        INDEX idx_personal_tasks_due (due_date),
        INDEX idx_personal_tasks_status (status),
        INDEX idx_personal_tasks_company (company),
        INDEX idx_personal_tasks_department (department),
        INDEX idx_personal_tasks_share_scope (share_scope),
        INDEX idx_personal_tasks_type (task_type),
        INDEX idx_personal_tasks_workflow (workflow_stage),
        INDEX idx_personal_tasks_legacy_job (legacy_job_ticket_id),
        INDEX idx_personal_tasks_recurring_parent (recurring_parent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ สร้างตาราง 'personal_tasks' เรียบร้อยแล้ว</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating personal_tasks: " . $conn->error . "</p>";
    }
} else {
    addColumnIfNotExists($conn, 'personal_tasks', 'company', "VARCHAR(10) NULL");
    addColumnIfNotExists($conn, 'personal_tasks', 'department', "VARCHAR(100) NULL");
    addColumnIfNotExists($conn, 'personal_tasks', 'task_type', "VARCHAR(20) NOT NULL DEFAULT 'personal' AFTER department");
    addColumnIfNotExists($conn, 'personal_tasks', 'status_detail', "VARCHAR(255) NULL AFTER status");
    addColumnIfNotExists($conn, 'personal_tasks', 'share_scope', "VARCHAR(20) NOT NULL DEFAULT 'private'");
    addColumnIfNotExists($conn, 'personal_tasks', 'assignee_id', "VARCHAR(50) NULL");
    addColumnIfNotExists($conn, 'personal_tasks', 'assignee_name', "VARCHAR(255) NULL");
    addColumnIfNotExists($conn, 'personal_tasks', 'building', "VARCHAR(255) NULL AFTER assignee_name");
    addColumnIfNotExists($conn, 'personal_tasks', 'workflow_stage', "VARCHAR(20) NOT NULL DEFAULT 'todo' AFTER building");
    addColumnIfNotExists($conn, 'personal_tasks', 'legacy_job_ticket_id', "INT NULL AFTER workflow_stage");
    addColumnIfNotExists($conn, 'personal_tasks', 'responsible_companies', "VARCHAR(100) NULL AFTER company");
    addColumnIfNotExists($conn, 'personal_tasks', 'remind_month_start', "TINYINT(1) NOT NULL DEFAULT 0 AFTER progress_pct");
    addColumnIfNotExists($conn, 'personal_tasks', 'remind_month_end', "TINYINT(1) NOT NULL DEFAULT 0 AFTER remind_month_start");
    addColumnIfNotExists($conn, 'personal_tasks', 'is_recurring', "TINYINT(1) NOT NULL DEFAULT 0 AFTER remind_month_end");
    addColumnIfNotExists($conn, 'personal_tasks', 'recurring_interval_months', "INT(11) NOT NULL DEFAULT 12 AFTER is_recurring");
    addColumnIfNotExists($conn, 'personal_tasks', 'remind_days_before', "INT(11) NOT NULL DEFAULT 0 AFTER recurring_interval_months");
    addColumnIfNotExists($conn, 'personal_tasks', 'recurring_parent_id', "INT NULL AFTER remind_days_before");
    echo "<p style='color: blue;'>ℹ️ ตาราง 'personal_tasks' มีอยู่แล้ว</p>";
}

// 10. ตรวจสอบและสร้างตาราง personal_task_history (ประวัติการเปลี่ยนแปลงงานส่วนตัว)
$table_check = $conn->query("SHOW TABLES LIKE 'personal_task_history'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE personal_task_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        user_id VARCHAR(50) NOT NULL,
        user_name VARCHAR(120) NULL,
        action VARCHAR(50) NOT NULL,
        old_status VARCHAR(20) NULL,
        new_status VARCHAR(20) NULL,
        old_priority VARCHAR(20) NULL,
        new_priority VARCHAR(20) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_history_task (task_id),
        INDEX idx_history_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ สร้างตาราง 'personal_task_history' เรียบร้อยแล้ว</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating personal_task_history: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ ตาราง 'personal_task_history' มีอยู่แล้ว</p>";
}

// 11. ตรวจสอบและสร้างตาราง print_logs (สำหรับ Print Logging แบบตรวจสอบย้อนหลัง)
$table_check = $conn->query("SHOW TABLES LIKE 'print_logs'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE print_logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        user_id VARCHAR(50) NOT NULL,
        user_name VARCHAR(120) NULL,
        role VARCHAR(20) NULL,
        document_type VARCHAR(40) NOT NULL,
        document_id VARCHAR(60) NOT NULL,
        action VARCHAR(40) NOT NULL,
        client_ip VARCHAR(45) NULL,
        user_agent TEXT NULL,
        client_meta TEXT NULL,
        prev_hash CHAR(64) NOT NULL,
        entry_hash CHAR(64) NOT NULL,
        signature CHAR(64) NOT NULL,
        INDEX idx_print_logs_ts (timestamp),
        INDEX idx_print_logs_user (user_id),
        INDEX idx_print_logs_doc (document_type, document_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ สร้างตาราง 'print_logs' เรียบร้อยแล้ว</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating print_logs: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ ตาราง 'print_logs' มีอยู่แล้ว</p>";
}

// 12. ตรวจสอบและสร้างตาราง permission_roles
$table_check = $conn->query("SHOW TABLES LIKE 'permission_roles'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE permission_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description VARCHAR(255) NULL,
        permissions_json TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ สร้างตาราง 'permission_roles' เรียบร้อยแล้ว</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating permission_roles: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ ตาราง 'permission_roles' มีอยู่แล้ว</p>";
}

$checkRolesSeed = $conn->query("SHOW TABLES LIKE 'permission_roles'");
if ($checkRolesSeed && $checkRolesSeed->num_rows > 0) {
    ensurePermissionRole($conn, 'PTA-IT-Admin', 'Full IT administration permissions', [
        'admin.access_admin',
        'admin.manage_permissions',
        'asset.manage',
        'employee.manage',
        'loan.manage',
        'kb.manage',
        'settings.notifications',
        'audit.view'
    ]);

    ensurePermissionRole($conn, 'PTA-IT-Support', 'IT support staff permissions', [
        'admin.access_admin',
        'asset.manage',
        'loan.manage',
        'kb.manage',
        'settings.notifications'
    ]);

    ensurePermissionRole($conn, 'PTA-HR', 'HR management permissions', [
        'admin.access_admin',
        'employee.manage'
    ]);

    ensurePermissionRole($conn, 'PTA-User', 'Standard user permissions', []);
}

// 13. ตรวจสอบและสร้างตาราง user_permission_roles
$table_check = $conn->query("SHOW TABLES LIKE 'user_permission_roles'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE user_permission_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        role_id INT NOT NULL,
        UNIQUE KEY uniq_user_role (user_id, role_id),
        INDEX idx_upr_user (user_id),
        CONSTRAINT fk_upr_role FOREIGN KEY (role_id) REFERENCES permission_roles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ สร้างตาราง 'user_permission_roles' เรียบร้อยแล้ว</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating user_permission_roles: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ ตาราง 'user_permission_roles' มีอยู่แล้ว</p>";
}

// 14. ตรวจสอบและสร้างตาราง user_permissions
$table_check = $conn->query("SHOW TABLES LIKE 'user_permissions'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE user_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        permission_key VARCHAR(100) NOT NULL,
        is_granted TINYINT(1) NOT NULL DEFAULT 1,
        UNIQUE KEY uniq_user_perm (user_id, permission_key),
        INDEX idx_up_user (user_id),
        INDEX idx_up_perm (permission_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ สร้างตาราง 'user_permissions' เรียบร้อยแล้ว</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating user_permissions: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ ตาราง 'user_permissions' มีอยู่แล้ว</p>";
}

$table_check = $conn->query("SHOW TABLES LIKE 'daily_checklist_items'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE daily_checklist_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company VARCHAR(10) NOT NULL DEFAULT '' COMMENT 'PTA, PT4, PTE หรือว่างสำหรับใช้ร่วมกัน',
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        category VARCHAR(100) NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_daily_items_company (company),
        INDEX idx_daily_items_active (is_active),
        INDEX idx_daily_items_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ สร้างตาราง 'daily_checklist_items' เรียบร้อยแล้ว</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating daily_checklist_items: " . $conn->error . "</p>";
    }
} else {
    addColumnIfNotExists($conn, 'daily_checklist_items', 'company', "VARCHAR(10) NOT NULL DEFAULT '' COMMENT 'PTA, PT4, PTE หรือว่างสำหรับใช้ร่วมกัน'");
    addColumnIfNotExists($conn, 'daily_checklist_items', 'category', "VARCHAR(100) NULL");
    addColumnIfNotExists($conn, 'daily_checklist_items', 'sort_order', "INT NOT NULL DEFAULT 0");
    addColumnIfNotExists($conn, 'daily_checklist_items', 'is_active', "TINYINT(1) NOT NULL DEFAULT 1");
    echo "<p style='color: blue;'>ℹ️ ตาราง 'daily_checklist_items' มีอยู่แล้ว</p>";
}

$table_check = $conn->query("SHOW TABLES LIKE 'daily_checklist_logs'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE daily_checklist_logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        checklist_item_id INT NOT NULL,
        checklist_date DATE NOT NULL,
        company VARCHAR(10) NOT NULL DEFAULT '',
        user_id VARCHAR(50) NOT NULL,
        user_name VARCHAR(120) NULL,
        checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        notes TEXT NULL,
        UNIQUE KEY uniq_item_date (checklist_item_id, checklist_date),
        INDEX idx_daily_logs_date (checklist_date),
        INDEX idx_daily_logs_company (company),
        INDEX idx_daily_logs_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ สร้างตาราง 'daily_checklist_logs' เรียบร้อยแล้ว</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating daily_checklist_logs: " . $conn->error . "</p>";
    }
} else {
    addColumnIfNotExists($conn, 'daily_checklist_logs', 'company', "VARCHAR(10) NOT NULL DEFAULT ''");
    addColumnIfNotExists($conn, 'daily_checklist_logs', 'notes', "TEXT NULL");
    echo "<p style='color: blue;'>ℹ️ ตาราง 'daily_checklist_logs' มีอยู่แล้ว</p>";
}
// 15. ตรวจสอบและสร้างตาราง personal_task_comments (Comments & Progress)
$table_check = $conn->query("SHOW TABLES LIKE 'personal_task_comments'");
if ($table_check->num_rows == 0) {
    $sql = "CREATE TABLE personal_task_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        user_id VARCHAR(64) NULL,
        user_name VARCHAR(100) NULL,
        comment TEXT NULL,
        progress_pct TINYINT UNSIGNED DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_comment_task (task_id),
        INDEX idx_comment_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ สร้างตาราง 'personal_task_comments' เรียบร้อยแล้ว</p>";
    } else {
        echo "<p style='color: red;'>❌ Error: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ ตาราง 'personal_task_comments' มีอยู่แล้ว</p>";
}

// 16. เพิ่ม column progress_pct และ deadline ใน personal_tasks และ job_tickets
addColumnIfNotExists($conn, 'personal_tasks', 'progress_pct', 'TINYINT UNSIGNED DEFAULT 0 AFTER status');
addColumnIfNotExists($conn, 'job_tickets', 'deadline', 'DATE NULL AFTER responsible');

$conn->query("UPDATE personal_tasks SET task_type = 'personal' WHERE task_type IS NULL OR task_type = ''");
$conn->query("UPDATE personal_tasks SET workflow_stage = CASE WHEN status = 'done' THEN 'done' WHEN status = 'doing' THEN 'doing' ELSE 'todo' END WHERE workflow_stage IS NULL OR workflow_stage = ''");

$migrateJobTicketsSql = "INSERT INTO personal_tasks (
    user_id, company, department, task_type, title, description, status, priority, due_date, tags,
    share_scope, assignee_name, building, workflow_stage, legacy_job_ticket_id, progress_pct, created_at, updated_at
)
SELECT
    COALESCE(NULLIF(jt.created_by_id, ''), 'job-ticket'),
    jt.company,
    jt.department,
    'job_ticket',
    jt.job_name,
    NULL,
    CASE
        WHEN jt.is_done = 1 THEN 'done'
        WHEN jt.is_receive = 1 OR jt.is_doing = 1 OR jt.is_send = 1 OR jt.is_approve = 1 THEN 'doing'
        ELSE 'todo'
    END,
    'normal',
    jt.deadline,
    '',
    'company',
    jt.responsible,
    jt.building,
    CASE
        WHEN jt.is_done = 1 THEN 'done'
        WHEN jt.is_approve = 1 THEN 'approve'
        WHEN jt.is_send = 1 THEN 'send'
        WHEN jt.is_doing = 1 THEN 'doing'
        WHEN jt.is_receive = 1 THEN 'receive'
        ELSE 'todo'
    END,
    jt.id,
    CASE
        WHEN jt.is_done = 1 THEN 100
        WHEN jt.is_approve = 1 THEN 90
        WHEN jt.is_send = 1 THEN 75
        WHEN jt.is_doing = 1 THEN 50
        WHEN jt.is_receive = 1 THEN 25
        ELSE 0
    END,
    COALESCE(jt.last_update, NOW()),
    COALESCE(jt.last_update, NOW())
FROM job_tickets jt
LEFT JOIN personal_tasks pt ON pt.task_type = 'job_ticket' AND pt.legacy_job_ticket_id = jt.id
WHERE pt.id IS NULL";

if ($conn->query($migrateJobTicketsSql) === TRUE) {
    $migrated = $conn->affected_rows;
    echo "<p style='color: green;'>✅ ย้ายข้อมูล job_tickets เข้า personal_tasks แล้ว {$migrated} รายการ</p>";
} else {
    echo "<p style='color: red;'>❌ Error migrating job_tickets to personal_tasks: " . $conn->error . "</p>";
}

echo "<hr>";
echo "<a href='index.html' style='font-size: 20px; font-weight: bold;'>กลับหน้า Dashboard</a>";

$conn->close();
?>
