<?php
// employee_access.php

// 1. การเชื่อมต่อฐานข้อมูล (ปรับตามการตั้งค่าของคุณ)
require_once 'config.php';

// ตรวจสอบสิทธิ์ Admin (ป้องกัน User ทั่วไปเข้าถึง)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('<div style="font-family: sans-serif; text-align: center; margin-top: 50px; color: red;"><h3>⛔ Access Denied</h3><p>คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (สำหรับ Admin เท่านั้น)</p><a href="index.html">กลับหน้าหลัก</a></div>');
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// --- Search Logic ---
$search_results = [];
$search_error = '';
if (isset($_GET['search_term']) && !empty($_GET['search_term'])) {
    $term = $_GET['search_term'];
    $like_term = "%" . $term . "%";
    // Search by ID, Name (EN), Name (TH)
    $stmt_search = $conn->prepare("SELECT id, name, position, image FROM employees WHERE id LIKE ? OR name LIKE ? OR name_th LIKE ? LIMIT 20");
    $stmt_search->bind_param("sss", $like_term, $like_term, $like_term);
    $stmt_search->execute();
    $res_search = $stmt_search->get_result();
    
    if ($res_search->num_rows === 1) {
        $found = $res_search->fetch_assoc();
        header("Location: ?id=" . $found['id']);
        exit;
    } elseif ($res_search->num_rows > 1) {
        while ($row = $res_search->fetch_assoc()) {
            $search_results[] = $row;
        }
    } else {
        $search_error = "ไม่พบข้อมูลพนักงาน: " . htmlspecialchars($term);
    }
}

// 2. รับค่า Employee ID (ถ้าไม่มีให้ใช้ค่า Default เพื่อทดสอบ)
$emp_id = isset($_GET['id']) ? $_GET['id'] : '5808200';

// 3. จัดการ Action (Add, Delete, Set Primary)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_access') {
        $company = $_POST['company'];
        $department = $_POST['department'];
        $email = $_POST['email'];
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;

        // ถ้าตั้งเป็น Primary ให้เคลียร์ของเก่าก่อน
        if ($is_primary) {
            $stmt = $conn->prepare("UPDATE employee_assignments SET is_primary = 0 WHERE employee_id = ?");
            $stmt->bind_param("s", $emp_id);
            $stmt->execute();
        }

        $stmt = $conn->prepare("INSERT INTO employee_assignments (employee_id, company, department, email, is_primary) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $emp_id, $company, $department, $email, $is_primary);
        $stmt->execute();
    } 
    elseif ($action === 'delete_access') {
        $assignment_id = $_POST['assignment_id'];
        // ลบเฉพาะแถวที่ระบุ (และไม่ควรลบ Primary ถ้าไม่มีการเช็ค แต่ใน UI เราจะ disable ปุ่มลบ Primary ไว้)
        $stmt = $conn->prepare("DELETE FROM employee_assignments WHERE assignment_id = ?");
        $stmt->bind_param("i", $assignment_id);
        $stmt->execute();
    }
    elseif ($action === 'set_primary') {
        $assignment_id = $_POST['assignment_id'];
        
        // 1. ปรับทั้งหมดเป็น 0
        $stmt = $conn->prepare("UPDATE employee_assignments SET is_primary = 0 WHERE employee_id = ?");
        $stmt->bind_param("s", $emp_id);
        $stmt->execute();

        // 2. ปรับอันที่เลือกเป็น 1
        $stmt = $conn->prepare("UPDATE employee_assignments SET is_primary = 1 WHERE assignment_id = ?");
        $stmt->bind_param("i", $assignment_id);
        $stmt->execute();
    }
    
    // Redirect กลับมาหน้าเดิมเพื่อป้องกันการ Submit ซ้ำ
    header("Location: ?id=" . $emp_id);
    exit;
}

// 4. ดึงข้อมูลพนักงาน
$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

if (!$employee) {
    die("ไม่พบข้อมูลพนักงานรหัส: " . htmlspecialchars($emp_id));
}

// 5. ดึงข้อมูลสิทธิ์ (Assignments)
$stmt = $conn->prepare("SELECT * FROM employee_assignments WHERE employee_id = ? ORDER BY is_primary DESC, company ASC");
$stmt->bind_param("s", $emp_id);
$stmt->execute();
$assignments = $stmt->get_result();

// 6. ดึงรายชื่อแผนกสำหรับ Dropdown
$dept_result = $conn->query("SELECT dept_name FROM departments ORDER BY dept_name ASC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสิทธิ์พนักงาน - <?php echo htmlspecialchars($employee['name']); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .primary-badge { font-size: 0.8rem; padding: 5px 10px; border-radius: 20px; }
        .company-pta { color: #0d6efd; }
        .company-pt4 { color: #0dcaf0; }
        .company-pte { color: #ffc107; }
    </style>
</head>
<body class="bg-light">

<div class="container mt-5">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-user-shield text-primary"></i> จัดการสิทธิ์การเข้าใช้งาน</h2>
        <a href="#" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> กลับหน้ารายชื่อ</a>
    </div>

    <!-- Search Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-10">
                    <input type="text" class="form-control" name="search_term" placeholder="ค้นหาชื่อ หรือ รหัสพนักงาน..." value="<?php echo isset($_GET['search_term']) ? htmlspecialchars($_GET['search_term']) : ''; ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> ค้นหา</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Search Results -->
    <?php if (!empty($search_results)): ?>
    <div class="list-group mb-4 shadow-sm">
        <div class="list-group-item list-group-item-primary fw-bold">ผลการค้นหา (<?php echo count($search_results); ?>)</div>
        <?php foreach ($search_results as $res): ?>
            <a href="?id=<?php echo $res['id']; ?>" class="list-group-item list-group-item-action d-flex align-items-center">
                <?php $s_img = !empty($res['image']) ? "uploads/employees/" . $res['image'] : "https://via.placeholder.com/40"; ?>
                <img src="<?php echo htmlspecialchars($s_img); ?>" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                <div>
                    <div class="fw-bold"><?php echo htmlspecialchars($res['name']); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($res['id']); ?> | <?php echo htmlspecialchars($res['position']); ?></small>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($search_error): ?>
        <div class="alert alert-danger mb-4"><?php echo $search_error; ?></div>
    <?php endif; ?>

    <!-- Employee Info Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 text-secondary"><i class="fas fa-user"></i> ข้อมูลพนักงาน</h5>
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <?php 
                        $img_path = !empty($employee['image']) ? "uploads/employees/" . $employee['image'] : "https://via.placeholder.com/100";
                    ?>
                    <img src="<?php echo htmlspecialchars($img_path); ?>" class="rounded-circle img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;" alt="Profile">
                </div>
                <div class="col-md-10">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-muted small">รหัสพนักงาน</label>
                            <div class="fw-bold"><?php echo htmlspecialchars($employee['id']); ?></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-muted small">ชื่อ-นามสกุล</label>
                            <div class="fw-bold"><?php echo htmlspecialchars($employee['name']); ?></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-muted small">ตำแหน่งหลัก</label>
                            <div class="fw-bold"><?php echo htmlspecialchars($employee['position']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Access Table Card -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-primary"><i class="fas fa-building"></i> สิทธิ์การเข้าถึงบริษัท (Company Access)</h5>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addAccessModal">
                <i class="fas fa-plus"></i> เพิ่มสิทธิ์บริษัท
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">บริษัท (Company)</th>
                            <th>แผนก (Department)</th>
                            <th>อีเมลประจำบริษัท (Email)</th>
                            <th class="text-center">สถานะหลัก (Primary)</th>
                            <th class="text-end pe-4">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($assignments->num_rows > 0): ?>
                            <?php while($row = $assignments->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 fw-bold <?php echo 'company-' . strtolower($row['company']); ?>">
                                        <?php echo htmlspecialchars($row['company']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                                    <td>
                                        <?php echo !empty($row['email']) ? htmlspecialchars($row['email']) : '<span class="text-muted small">- ไม่ระบุ -</span>'; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['is_primary'] == 1): ?>
                                            <span class="badge bg-success primary-badge"><i class="fas fa-check-circle"></i> บริษัทหลัก</span>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="set_primary">
                                                <input type="hidden" name="assignment_id" value="<?php echo $row['assignment_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirm('ต้องการเปลี่ยนบริษัทหลักใช่หรือไม่?')">
                                                    ตั้งเป็นหลัก
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php if ($row['is_primary'] == 0): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_access">
                                                <input type="hidden" name="assignment_id" value="<?php echo $row['assignment_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('ยืนยันการลบสิทธิ์?')">
                                                    <i class="fas fa-trash"></i> ลบ
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-danger" disabled title="ไม่สามารถลบบริษัทหลักได้">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">ยังไม่มีข้อมูลสิทธิ์การเข้าใช้งาน</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Modal: Add Access -->
<div class="modal fade" id="addAccessModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> เพิ่มสิทธิ์การเข้าใช้งาน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_access">
                    
                    <!-- Company -->
                    <div class="mb-3">
                        <label class="form-label">เลือกบริษัท <span class="text-danger">*</span></label>
                        <select class="form-select" name="company" required>
                            <option value="" selected disabled>-- กรุณาเลือก --</option>
                            <option value="PTA">PTA - Powertech Engine Assembly Co., Ltd.</option>
                            <option value="PT4">PT4 - Powertech 2004 Co., Ltd.</option>
                            <option value="PTE">PTE - Powertech Energy Solutions Co., Ltd.</option>
                        </select>
                    </div>

                    <!-- Department (Dynamic from DB) -->
                    <div class="mb-3">
                        <label class="form-label">แผนกในบริษัทนั้น <span class="text-danger">*</span></label>
                        <select class="form-select" name="department" required>
                            <option value="" selected disabled>-- กรุณาเลือก --</option>
                            <?php 
                            if ($dept_result->num_rows > 0) {
                                while($dept = $dept_result->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($dept['dept_name']) . '">' . htmlspecialchars($dept['dept_name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <label class="form-label">อีเมลบริษัท (ถ้ามี)</label>
                        <input type="email" class="form-control" name="email" placeholder="ex: name@company.com">
                    </div>

                    <!-- Primary Checkbox -->
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="checkPrimary">
                        <label class="form-check-label" for="checkPrimary">
                            กำหนดเป็นบริษัทหลัก (Primary Company)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
