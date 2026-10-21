<?php
// includes/job_position_levels.php
// ระดับในผังองค์กรของแต่ละตำแหน่ง (job_positions.org_level) — HR ตั้งเองได้ที่ admin.html?tab=job_positions
// ไม่ได้ตั้ง (NULL) = เดาจากชื่อตำแหน่ง · ไม่ใช้ sort_order เพราะ HR เรียงไว้ปนกันหลายระดับ

const ORG_LEVELS = [
    1 => 'ผู้บริหาร',
    2 => 'ผู้จัดการ',
    3 => 'หัวหน้างาน',
    4 => 'วิศวกร / ผู้ชำนาญ',
    5 => 'พนักงาน',
];

function ensureJobPositionLevelColumn(mysqli $conn): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $col = $conn->query("SHOW COLUMNS FROM job_positions LIKE 'org_level'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE job_positions ADD COLUMN org_level TINYINT NULL AFTER sort_order");
    }
}

// เดาระดับจากชื่อตำแหน่ง (ใช้เมื่อ HR ยังไม่ได้ตั้งเอง)
function guessOrgLevel(string $name): int {
    $t = mb_strtolower(trim($name), 'UTF-8');
    if (preg_match('/managing director|executive director|\bdirector\b|ผู้อำนวยการ/u', $t)) return 1;
    if (preg_match('/manager|head of|ผู้จัดการ|หัวหน้าแผนก/u', $t)) return 2;
    if (preg_match('/supervisor|leader|หัวหน้า/u', $t)) return 3;
    if (preg_match('/engineer|programmer|specialist|senior|วิศวกร/u', $t)) return 4;
    return 5;
}

function effectiveOrgLevel(?string $stored, string $name): int {
    $lv = intval($stored ?? 0);
    return isset(ORG_LEVELS[$lv]) ? $lv : guessOrgLevel($name);
}
