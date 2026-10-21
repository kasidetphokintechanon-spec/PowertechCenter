<?php
/**
 * HR&GA cross-company Task Center visibility (PTA · PT4 · PTE).
 */

const TASK_CENTER_HRGA_DEPT = 'HR and GA';
const TASK_CENTER_HRGA_COMPANIES = ['PTA', 'PT4', 'PTE'];

function taskCenterNormalizeDept(?string $dept): string
{
    return trim(preg_replace('/\s+/u', ' ', (string)$dept));
}

function taskCenterIsHrGaDepartment(?string $dept): bool
{
    $d = taskCenterNormalizeDept($dept);
    if ($d === '') {
        return false;
    }
    if (strcasecmp($d, TASK_CENTER_HRGA_DEPT) === 0) {
        return true;
    }
    if (preg_match('/^hr\s*(?:&|and|และ)\s*ga$/iu', $d)) {
        return true;
    }
    $stripped = trim((string)preg_replace('/^(?:ฝ่าย|แผนก|department|dept\.?)\s+/iu', '', $d));
    if ($stripped !== '' && strcasecmp($stripped, TASK_CENTER_HRGA_DEPT) === 0) {
        return true;
    }
    if ($stripped !== '' && preg_match('/^hr\s*(?:&|and|และ)\s*ga$/iu', $stripped)) {
        return true;
    }
    if (preg_match('/\bhr\b/iu', $d) && preg_match('/\bga\b/iu', $d)) {
        return true;
    }
    return false;
}

function taskCenterUserHasHrGaAssignment(mysqli $conn, string $userId): bool
{
    if ($userId === '') {
        return false;
    }
    $stmt = $conn->prepare('SELECT department FROM employee_assignments WHERE employee_id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if (taskCenterIsHrGaDepartment($row['department'] ?? '')) {
            return true;
        }
    }
    return false;
}

function taskCenterHrGaModeEnabled(string $role, ?string $sessionDept, ?mysqli $conn = null, ?string $userId = null): bool
{
    if (taskCenterIsHrGaDepartment($sessionDept)) {
        return true;
    }
    if ($conn instanceof mysqli && $userId !== null && $userId !== '') {
        if (taskCenterUserHasHrGaAssignment($conn, (string)$userId)) {
            return true;
        }
    }
    if ($role === 'admin' && trim((string)($_GET['hrga'] ?? $_GET['mode'] ?? '')) === '1') {
        return true;
    }
    return false;
}

/**
 * Append standard visibility OR-group for personal_tasks queries.
 */
function taskCenterAppendVisibility(
    mysqli $conn,
    string $role,
    string $userId,
    string $sessionCompany,
    string $sessionDept,
    array &$visibilityParts,
    array &$params,
    string &$types
): bool {
    $hrgaMode = taskCenterHrGaModeEnabled($role, $sessionDept, $conn, $userId);
    $visibilityParts[] = 'pt.user_id = ?';
    $params[] = $userId;
    $types .= 's';

    if ($hrgaMode) {
        $dept = TASK_CENTER_HRGA_DEPT;
        $visibilityParts[] = "(pt.share_scope IN ('department', 'company') AND pt.department = ?)";
        $params[] = $dept;
        $types .= 's';
        return true;
    }

    if ($sessionCompany !== '' && $sessionDept !== '') {
        $visibilityParts[] = "(pt.share_scope = 'department' AND (pt.company = ? OR FIND_IN_SET(?, REPLACE(COALESCE(pt.responsible_companies,''), ' ', '')) > 0) AND pt.department = ?)";
        $params[] = $sessionCompany;
        $params[] = $sessionCompany;
        $params[] = $sessionDept;
        $types .= 'sss';
    }

    if ($sessionCompany !== '') {
        $visibilityParts[] = "(pt.share_scope = 'company' AND (pt.company = ? OR FIND_IN_SET(?, REPLACE(COALESCE(pt.responsible_companies,''), ' ', '')) > 0))";
        $params[] = $sessionCompany;
        $params[] = $sessionCompany;
        $types .= 'ss';
    }

    return false;
}

function taskCenterHrGaDefaultResponsibleCompanies(): string
{
    return implode(',', TASK_CENTER_HRGA_COMPANIES);
}
