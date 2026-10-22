<?php
/**
 * Single source of truth for PowertechCenter permission keys.
 * Directory-facing employee flags (is_executive, is_management, is_technician, assignments)
 * are NOT permissions — they only affect directory.html display.
 */

if (!defined('POWERTECH_PERMISSION_REGISTRY')) {
    define('POWERTECH_PERMISSION_REGISTRY', true);
}

function getPowertechBaseRoleDefinitions(): array
{
    return [
        'user' => [
            'label' => 'User',
            'description' => 'ผู้ใช้ทั่วไป — เบิกของ/ตั๋ว ตามหน้าที่เปิดให้ ไม่เข้า Admin',
        ],
        'staff' => [
            'label' => 'Staff',
            'description' => 'เจ้าหน้าที่ — แก้ไขข้อมูลพนักงาน/Directory ได้ (ตามสิทธิ์ย่อย) ไม่ตั้ง Admin เอง',
        ],
        'admin' => [
            'label' => 'Admin',
            'description' => 'ผู้ดูแลระบบ — สิทธิ์เต็ม (*), ใช้เมื่อ IT หัวหน้าเท่านั้น',
        ],
    ];
}

function getPowertechPermissionDefinitions(): array
{
    return [
        [
            'key' => 'admin.access_admin',
            'label' => 'เข้าหน้า Admin',
            'group' => 'admin',
            'description' => 'เปิด admin.html และเมนูตามสิทธิ์ที่ติ๊ก',
        ],
        [
            'key' => 'it_log.access',
            'label' => 'เข้าหน้างานช่าง IT',
            'group' => 'it',
            'description' => 'log.html — บันทึกงาน, รับงาน, IT Command Center',
        ],
        [
            'key' => 'it_report.access',
            'label' => 'ดู IT Report',
            'group' => 'it',
            'description' => 'it_report.html — สถิติและ KPI งานช่าง',
        ],
        [
            'key' => 'admin.manage_permissions',
            'label' => 'จัดการสิทธิ์ผู้ใช้',
            'group' => 'admin',
            'description' => 'แก้ role / permission ของผู้อื่น',
        ],
        [
            'key' => 'employee.manage',
            'label' => 'จัดการพนักงาน',
            'group' => 'directory',
            'description' => 'เพิ่ม/แก้/ลบพนักงาน สังกัด — ข้อมูลที่ directory.html อ่าน',
        ],
        [
            'key' => 'asset.manage',
            'label' => 'จัดการพัสดุ IT',
            'group' => 'assets',
            'description' => 'พัสดุ หมวดหมู่ สถานที่',
        ],
        [
            'key' => 'stock.manage',
            'label' => 'จัดการคลังสินค้า',
            'group' => 'stock',
            'description' => 'stock_management — อนุมัติ/รับเข้า/ตั้งค่า',
        ],
        [
            'key' => 'loan.manage',
            'label' => 'จัดการยืม-คืน',
            'group' => 'operations',
            'description' => 'หน้าจัดการการยืม-คืน',
        ],
        [
            'key' => 'kb.manage',
            'label' => 'จัดการ Knowledge Base',
            'group' => 'content',
            'description' => 'บทความและหมวดหมู่ KB',
        ],
        [
            'key' => 'settings.notifications',
            'label' => 'ตั้งค่าแจ้งเตือน',
            'group' => 'settings',
            'description' => 'Telegram / LINE',
        ],
        [
            'key' => 'audit.view',
            'label' => 'ดู Audit Log',
            'group' => 'admin',
            'description' => 'audit_log.html',
        ],
    ];
}

function getPowertechPermissionKeys(): array
{
    return array_column(getPowertechPermissionDefinitions(), 'key');
}

function getPowertechAdminMenuMap(): array
{
    return [
        'menu-it_assets' => ['asset.manage'],
        'menu-asset_categories' => ['asset.manage'],
        'menu-locations' => ['asset.manage'],
        'menu-departments' => ['employee.manage'],
        'menu-employees' => ['employee.manage'],
        'menu-job_positions' => ['employee.manage'],
        'menu-permissions' => ['admin.manage_permissions'],
        'menu-borrow_management' => ['loan.manage'],
        'menu-kb_articles' => ['kb.manage'],
        'menu-kb_categories' => ['kb.manage'],
        'menu-announcements' => ['employee.manage'],
        'menu-holidays' => ['employee.manage'],
        'menu-services' => ['employee.manage'],
        'menu-admin_logo' => ['settings.notifications'],
        'menu-audit_log' => ['audit.view'],
        'menu-update_db' => ['admin.manage_permissions'],
        'menu-backup' => ['admin.manage_permissions'],
    ];
}

function userHasAnyPermission(array $effective, array $required): bool
{
    if (in_array('*', $effective, true)) {
        return true;
    }
    foreach ($required as $key) {
        if (in_array($key, $effective, true)) {
            return true;
        }
    }
    return false;
}
