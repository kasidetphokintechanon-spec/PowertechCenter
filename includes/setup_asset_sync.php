<?php
/**
 * One-way sync: setup_records → it_assets (ทะเบียนพัสดุเป็น master, setup push ข้อมูลติดตั้งกลับ)
 */

if (!function_exists('ensureSetupAssetSyncSchema')) {
    function ensureSetupAssetSyncSchema(mysqli $conn): void {
        static $done = false;
        if ($done) return;

        $res = $conn->query("SHOW COLUMNS FROM it_assets LIKE 'hostname'");
        if ($res && $res->num_rows === 0) {
            $conn->query("ALTER TABLE it_assets ADD COLUMN hostname VARCHAR(255) DEFAULT NULL COMMENT 'PC Name / Hostname' AFTER mac_address");
        }
        $res = $conn->query("SHOW COLUMNS FROM it_assets LIKE 'custodian_type'");
        if ($res && $res->num_rows === 0) {
            $conn->query("ALTER TABLE it_assets ADD COLUMN custodian_type VARCHAR(20) NOT NULL DEFAULT 'person' AFTER custodian_employee_id");
        }
        $res = $conn->query("SHOW COLUMNS FROM it_assets LIKE 'custodian_dept_id'");
        if ($res && $res->num_rows === 0) {
            $conn->query("ALTER TABLE it_assets ADD COLUMN custodian_dept_id INT(11) DEFAULT NULL AFTER custodian_type");
        }

        $conn->query("CREATE TABLE IF NOT EXISTS setup_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_name VARCHAR(200) DEFAULT '',
            employee_id VARCHAR(50) DEFAULT '',
            department VARCHAR(100) DEFAULT '',
            company VARCHAR(20) DEFAULT '',
            asset_code VARCHAR(50) DEFAULT '',
            serial_number VARCHAR(100) DEFAULT '',
            pc_name VARCHAR(100) DEFAULT '',
            tech_name VARCHAR(100) DEFAULT '',
            job_type VARCHAR(50) DEFAULT '',
            setup_date DATE DEFAULT NULL,
            ip_address VARCHAR(50) DEFAULT '',
            gateway VARCHAR(50) DEFAULT '',
            company_access JSON DEFAULT NULL,
            printer_buildings JSON DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            checklist_states JSON DEFAULT NULL,
            custom_items JSON DEFAULT NULL,
            granted_systems JSON DEFAULT NULL,
            status ENUM('active','offboarded') DEFAULT 'active',
            offboard_date DATE DEFAULT NULL,
            offboard_notes TEXT DEFAULT NULL,
            offboard_checklist JSON DEFAULT NULL,
            offboard_by VARCHAR(100) DEFAULT NULL,
            created_by VARCHAR(100) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $conn->query("ALTER TABLE setup_records ADD COLUMN IF NOT EXISTS it_asset_id INT(11) DEFAULT NULL");
        $conn->query("ALTER TABLE setup_records ADD COLUMN IF NOT EXISTS granted_systems JSON DEFAULT NULL");

        $done = true;
    }
}

if (!function_exists('normalizeSetupIp')) {
    function normalizeSetupIp(string $ip): string {
        $ip = trim($ip);
        if ($ip === '') return '';
        if (($pos = strpos($ip, '/')) !== false) {
            $ip = substr($ip, 0, $pos);
        }
        return trim($ip);
    }
}

if (!function_exists('resolveDeptIdByName')) {
    function resolveDeptIdByName(mysqli $conn, string $name, string $company = ''): ?int {
        $name = trim($name);
        if ($name === '') return null;

        $stmt = $conn->prepare('SELECT dept_id FROM departments WHERE dept_name = ? ORDER BY dept_id ASC LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) return (int)$row['dept_id'];

        // fuzzy: dept_name contains search text
        $like = '%' . $name . '%';
        $stmt2 = $conn->prepare('SELECT dept_id FROM departments WHERE dept_name LIKE ? ORDER BY dept_id ASC LIMIT 1');
        if (!$stmt2) return null;
        $stmt2->bind_param('s', $like);
        $stmt2->execute();
        $row2 = $stmt2->get_result()->fetch_assoc();
        return $row2 ? (int)$row2['dept_id'] : null;
    }
}

if (!function_exists('linkSetupRecordToAsset')) {
    function linkSetupRecordToAsset(mysqli $conn, int $setupRecordId, int $itAssetId): void {
        if ($setupRecordId <= 0 || $itAssetId <= 0) return;
        $stmt = $conn->prepare('UPDATE setup_records SET it_asset_id = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('ii', $itAssetId, $setupRecordId);
            $stmt->execute();
        }
    }
}

if (!function_exists('syncSetupToItAsset')) {
    /**
     * @return array{status:string,message:string,it_asset_id?:int,updated_fields?:string[]}
     */
    function syncSetupToItAsset(mysqli $conn, array $setup): array {
        ensureSetupAssetSyncSchema($conn);

        $assetCode = trim($setup['asset_code'] ?? '');
        if ($assetCode === '') {
            return ['status' => 'skipped', 'message' => 'ไม่มีรหัสพัสดุ IT'];
        }

        $stmt = $conn->prepare(
            'SELECT id, equipment_id, serial_number, hostname, ip_address, borrower, borrower_id,
                    custodian_employee_id, department, company, owning_department, status, name
             FROM it_assets WHERE equipment_id = ? LIMIT 1'
        );
        if (!$stmt) {
            return ['status' => 'error', 'message' => 'Prepare lookup failed'];
        }
        $stmt->bind_param('s', $assetCode);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            return [
                'status' => 'not_found',
                'message' => "ไม่พบทะเบียน \"{$assetCode}\" — สร้างรหัสใน Admin ก่อน แล้วดึงมา Setup",
            ];
        }

        $assetId = (int)$row['id'];
        $setupRecordId = (int)($setup['setup_record_id'] ?? 0);
        if ($setupRecordId > 0) {
            linkSetupRecordToAsset($conn, $setupRecordId, $assetId);
        }

        $sets = [];
        $types = '';
        $values = [];
        $changed = [];

        $serial = trim($setup['serial_number'] ?? '');
        if ($serial !== '') {
            $sets[] = 'serial_number = ?';
            $types .= 's';
            $values[] = $serial;
            $changed[] = 'serial_number';
        }

        $hostname = trim($setup['pc_name'] ?? '');
        if ($hostname !== '') {
            $sets[] = 'hostname = ?';
            $types .= 's';
            $values[] = $hostname;
            $changed[] = 'hostname';
        }

        $ip = normalizeSetupIp((string)($setup['ip_address'] ?? ''));
        if ($ip !== '') {
            $sets[] = 'ip_address = ?';
            $types .= 's';
            $values[] = $ip;
            $changed[] = 'ip_address';
        }

        $empId = trim($setup['employee_id'] ?? '');
        $empName = trim($setup['employee_name'] ?? '');
        $deptText = trim($setup['department'] ?? '');
        $company = trim($setup['company'] ?? '');

        if ($empId !== '' || $empName !== '') {
            $sets[] = "custodian_type = 'person'";
            $sets[] = 'custodian_dept_id = NULL';
            if ($empId !== '') {
                $sets[] = 'custodian_employee_id = ?';
                $sets[] = 'borrower_id = ?';
                $types .= 'ss';
                $values[] = $empId;
                $values[] = $empId;
            }
            if ($empName !== '') {
                $sets[] = 'borrower = ?';
                $types .= 's';
                $values[] = $empName;
            }
            if ($deptText !== '') {
                $sets[] = 'department = ?';
                $types .= 's';
                $values[] = $deptText;
                $deptId = resolveDeptIdByName($conn, $deptText, $company);
                if ($deptId) {
                    $sets[] = 'owning_department = ?';
                    $types .= 'i';
                    $values[] = $deptId;
                }
            }
            $changed[] = 'custodian';
        }

        if ($company !== '' && in_array($company, ['PTA', 'PT4', 'PTE'], true)) {
            $sets[] = 'company = ?';
            $types .= 's';
            $values[] = $company;
            $changed[] = 'company';
        }

        $jobType = trim($setup['job_type'] ?? '');
        if (in_array($jobType, ['ติดตั้งใหม่', 'ส่งมอบเครื่องใหม่', 'อัปเกรดเครื่องเดิม'], true)) {
            $sets[] = "status = 'ใช้งานปกติ'";
            $changed[] = 'status';
        }

        if (empty($sets)) {
            return [
                'status' => 'linked',
                'message' => 'ผูกทะเบียนแล้ว (ไม่มีฟิลด์ใหม่ให้อัปเดต)',
                'it_asset_id' => $assetId,
                'updated_fields' => [],
            ];
        }

        $sql = 'UPDATE it_assets SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $types .= 'i';
        $values[] = $assetId;

        $upd = $conn->prepare($sql);
        if (!$upd) {
            return ['status' => 'error', 'message' => 'Prepare update failed: ' . $conn->error];
        }

        $upd->bind_param($types, ...$values);
        if (!$upd->execute()) {
            return ['status' => 'error', 'message' => 'อัปเดตทะเบียนไม่สำเร็จ: ' . $upd->error];
        }

        if (function_exists('logAssetHistory')) {
            $detail = 'Sync จาก Setup: ' . implode(', ', array_unique($changed));
            logAssetHistory($conn, $assetId, 'SETUP_SYNC', $detail);
        }

        return [
            'status' => 'success',
            'message' => 'อัปเดตทะเบียนพัสดุแล้ว',
            'it_asset_id' => $assetId,
            'updated_fields' => array_values(array_unique($changed)),
        ];
    }
}
