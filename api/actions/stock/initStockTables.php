<?php
// api/actions/stock/initStockTables.php
// Called automatically before any stock action to ensure tables exist

function initStockTables($conn) {
    static $done = false;
    if ($done) return;
    $done = true;

    $conn->query("CREATE TABLE IF NOT EXISTS stock_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_code VARCHAR(50) NOT NULL,
        name VARCHAR(255) NOT NULL,
        unit VARCHAR(50) DEFAULT 'ชิ้น',
        price_per_unit DECIMAL(10,2) DEFAULT 0,
        min_stock INT DEFAULT 0,
        max_stock INT DEFAULT 0,
        type VARCHAR(50) NOT NULL DEFAULT 'stationery',
        company ENUM('PTA','PT4','PTE') NOT NULL DEFAULT 'PTA',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_item_code_company (item_code, company)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS stock_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company ENUM('PTA','PT4','PTE') NOT NULL,
        stock_type ENUM('stationery','maid') NOT NULL,
        branch VARCHAR(20) DEFAULT NULL,
        requester_id VARCHAR(50) NOT NULL,
        requester_name VARCHAR(255) NOT NULL,
        department VARCHAR(255) DEFAULT NULL,
        request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        deny_reason TEXT DEFAULT NULL,
        approved_by VARCHAR(255) DEFAULT NULL,
        approved_at DATETIME DEFAULT NULL,
        notes TEXT DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS stock_request_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        item_id INT NOT NULL,
        qty_requested INT NOT NULL DEFAULT 1,
        qty_approved INT DEFAULT NULL,
        KEY fk_sri_request (request_id),
        KEY fk_sri_item (item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add category column if not yet present (migration)
    $catCheck = $conn->query("SHOW COLUMNS FROM stock_items LIKE 'category'");
    if ($catCheck && $catCheck->num_rows === 0) {
        $conn->query("ALTER TABLE stock_items ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'other' AFTER is_active");
    }

    // Add description column if not yet present
    $descCheck = $conn->query("SHOW COLUMNS FROM stock_items LIKE 'description'");
    if ($descCheck && $descCheck->num_rows === 0) {
        $conn->query("ALTER TABLE stock_items ADD COLUMN description VARCHAR(500) DEFAULT NULL AFTER category");
    }

    // Add image_url column if not yet present
    $imgCheck = $conn->query("SHOW COLUMNS FROM stock_items LIKE 'image_url'");
    if ($imgCheck && $imgCheck->num_rows === 0) {
        $conn->query("ALTER TABLE stock_items ADD COLUMN image_url VARCHAR(255) DEFAULT NULL AFTER description");
    }

    // Migrate type column from ENUM to VARCHAR (for flexible stock types)
    $typeCol = $conn->query("SHOW COLUMNS FROM stock_items LIKE 'type'");
    if ($typeCol && ($row = $typeCol->fetch_assoc())) {
        if (stripos($row['Type'], 'enum') !== false) {
            $conn->query("ALTER TABLE stock_items MODIFY COLUMN type VARCHAR(50) NOT NULL DEFAULT 'stationery'");
        }
    }

    // Add supplier column if not yet present
    $supplierCheck = $conn->query("SHOW COLUMNS FROM stock_transactions LIKE 'supplier'");
    if ($supplierCheck && $supplierCheck->num_rows === 0) {
        $conn->query("ALTER TABLE stock_transactions ADD COLUMN supplier VARCHAR(255) DEFAULT NULL AFTER notes");
    }

    // Add expiry_date to stock_items if not yet present
    $expiryCheck = $conn->query("SHOW COLUMNS FROM stock_items LIKE 'has_expiry'");
    if ($expiryCheck && $expiryCheck->num_rows === 0) {
        $conn->query("ALTER TABLE stock_items ADD COLUMN has_expiry TINYINT(1) DEFAULT 0 AFTER image_url");
    }

    // Add expiry_date to stock_transactions (per lot)
    $txExpiryCheck = $conn->query("SHOW COLUMNS FROM stock_transactions LIKE 'expiry_date'");
    if ($txExpiryCheck && $txExpiryCheck->num_rows === 0) {
        $conn->query("ALTER TABLE stock_transactions ADD COLUMN expiry_date DATE DEFAULT NULL AFTER supplier");
    }

    // Migrate transactions type ENUM to include 'returned'
    $txTypeCol = $conn->query("SHOW COLUMNS FROM stock_transactions LIKE 'type'");
    if ($txTypeCol && ($txRow = $txTypeCol->fetch_assoc())) {
        if (stripos($txRow['Type'], 'returned') === false) {
            $conn->query("ALTER TABLE stock_transactions MODIFY COLUMN type ENUM('received','issued','adjusted','returned') NOT NULL");
        }
    }

    // Add pack_unit + pack_size for multi-unit support (e.g. receive by box, issue by piece)
    $packUnitCheck = $conn->query("SHOW COLUMNS FROM stock_items LIKE 'pack_unit'");
    if ($packUnitCheck && $packUnitCheck->num_rows === 0) {
        $conn->query("ALTER TABLE stock_items ADD COLUMN pack_unit VARCHAR(20) NOT NULL DEFAULT '' AFTER has_expiry");
    }
    $packSizeCheck = $conn->query("SHOW COLUMNS FROM stock_items LIKE 'pack_size'");
    if ($packSizeCheck && $packSizeCheck->num_rows === 0) {
        $conn->query("ALTER TABLE stock_items ADD COLUMN pack_size INT NOT NULL DEFAULT 1 AFTER pack_unit");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS stock_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        company ENUM('PTA','PT4','PTE') NOT NULL,
        type ENUM('received','issued','adjusted','returned') NOT NULL,
        quantity INT NOT NULL,
        unit_cost DECIMAL(10,2) DEFAULT NULL,
        transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        branch VARCHAR(20) DEFAULT NULL,
        requester_name VARCHAR(255) DEFAULT NULL,
        requester_id VARCHAR(50) DEFAULT NULL,
        request_id INT DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_by VARCHAR(255) DEFAULT NULL,
        KEY fk_st_item (item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS stock_dept_quotas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company ENUM('PTA','PT4','PTE') NOT NULL,
        department VARCHAR(255) NOT NULL,
        stock_type ENUM('stationery','maid') NOT NULL DEFAULT 'stationery',
        monthly_limit INT NOT NULL DEFAULT 0,
        monthly_value_limit DECIMAL(12,2) NOT NULL DEFAULT 0,
        updated_by VARCHAR(255) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_quota (company, department, stock_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add monthly_value_limit if upgrading from older schema
    $valLimCheck = $conn->query("SHOW COLUMNS FROM stock_dept_quotas LIKE 'monthly_value_limit'");
    if ($valLimCheck && $valLimCheck->num_rows === 0) {
        $conn->query("ALTER TABLE stock_dept_quotas ADD COLUMN monthly_value_limit DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER monthly_limit");
    }
}
?>
