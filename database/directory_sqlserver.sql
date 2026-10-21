-- ============================================================
-- DATABASE: PowertechCenter - Directory Module
-- Platform: Microsoft SQL Server
-- Created: 2026-05-22
-- ============================================================

-- ============================================================
-- TABLE 1: employees
-- ข้อมูลพนักงานหลัก
-- ============================================================
CREATE TABLE employees (
    id                      NVARCHAR(20)    NOT NULL PRIMARY KEY,   -- รหัสพนักงาน เช่น EMP001, PTA001
    name                    NVARCHAR(150)   NULL,                   -- ชื่อภาษาอังกฤษ
    name_th                 NVARCHAR(150)   NULL,                   -- ชื่อภาษาไทย
    position                NVARCHAR(100)   NULL,                   -- ตำแหน่งงาน
    is_executive            TINYINT         NOT NULL DEFAULT 0,     -- 1 = ระดับ Executive
    is_management           TINYINT         NOT NULL DEFAULT 0,     -- 1 = ระดับ Management
    is_technician           TINYINT         NOT NULL DEFAULT 0,     -- 1 = ทีมช่าง
    can_manage_stock        TINYINT         NOT NULL DEFAULT 0,     -- 1 = จัดการสต็อกได้
    phone                   NVARCHAR(30)    NULL,                   -- เบอร์โทรศัพท์
    birthdate               DATE            NULL,                   -- วันเกิด
    start_date              DATE            NULL,                   -- วันเริ่มงาน
    employment_status       NVARCHAR(20)    NOT NULL DEFAULT 'active',  -- active | inactive
    username                NVARCHAR(100)   NULL,                   -- ชื่อผู้ใช้สำหรับ login
    password                NVARCHAR(255)   NULL,                   -- password (hashed)
    role                    NVARCHAR(20)    NOT NULL DEFAULT 'user',    -- admin | staff | user
    image                   NVARCHAR(255)   NULL,                   -- ชื่อไฟล์รูปภาพ
    responsible_department  NVARCHAR(255)   NULL,                   -- แผนกที่รับผิดชอบ (สำหรับ staff)
    created_at              DATETIME        NOT NULL DEFAULT GETDATE(),
    updated_at              DATETIME        NULL
);
GO

-- ============================================================
-- TABLE 2: employee_assignments
-- สังกัด/บริษัทที่พนักงานประจำอยู่ (1 คน มีได้หลายสังกัด)
-- ============================================================
CREATE TABLE employee_assignments (
    id              INT             NOT NULL IDENTITY(1,1) PRIMARY KEY,
    employee_id     NVARCHAR(20)    NOT NULL,
    company         NVARCHAR(20)    NULL,       -- PTA | PT4 | PTE
    department      NVARCHAR(100)   NULL,       -- ชื่อแผนก
    email           NVARCHAR(150)   NULL,       -- อีเมลประจำบริษัท
    is_primary      TINYINT         NOT NULL DEFAULT 0,  -- 1 = สังกัดหลัก

    CONSTRAINT FK_assignments_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
GO

-- ============================================================
-- TABLE 3: departments
-- ข้อมูลแผนกทั้งหมดในองค์กร
-- ============================================================
CREATE TABLE departments (
    id              INT             NOT NULL IDENTITY(1,1) PRIMARY KEY,
    dept_name       NVARCHAR(100)   NOT NULL,   -- ชื่อแผนก
    company         NVARCHAR(20)    NULL,        -- PTA | PT4 | PTE | NULL = ทุกบริษัท
    sort_order      INT             NOT NULL DEFAULT 0
);
GO

-- ============================================================
-- INDEXES
-- ============================================================
CREATE INDEX IX_employees_status     ON employees(employment_status);
CREATE INDEX IX_employees_role       ON employees(role);
CREATE INDEX IX_assignments_empid    ON employee_assignments(employee_id);
CREATE INDEX IX_assignments_company  ON employee_assignments(company);
CREATE INDEX IX_assignments_dept     ON employee_assignments(department);
GO

-- ============================================================
-- SAMPLE DATA: departments
-- ============================================================
INSERT INTO departments (dept_name, company, sort_order) VALUES
(N'Management',           NULL,    1),
(N'IT',                   NULL,    2),
(N'HR and GA',            NULL,    3),
(N'Accounting',           NULL,    4),
(N'Production',           'PTA',   5),
(N'Production',           'PT4',   5),
(N'Production Engineer',  'PT4',   6),
(N'Maintenance',          'PTA',   7),
(N'Logistics',            NULL,    8),
(N'Quality Management',   'PTE',   9),
(N'Safety & Environment', 'PTE',  10);
GO

-- ============================================================
-- SAMPLE DATA: employees
-- ============================================================
INSERT INTO employees (id, name, name_th, position, is_executive, is_management, phone, start_date, birthdate, employment_status, role)
VALUES
('EMP001', 'Somchai Prasert',      N'สมชาย ประเสริฐ',       'Managing Director',    1, 1, '+66 89 123 4567', '2010-01-15', '1975-03-15', 'active',   'admin'),
('EMP002', 'Nattaporn Chaiwat',    N'ณัฐพร ชัยวัฒน์',      'Manager',              0, 1, '+66 81 234 5678', '2015-06-01', NULL,         'active',   'user'),
('EMP003', 'Wipada Srisawat',      N'วิภาดา ศรีสวัสดิ์',   'Senior Officer',       0, 0, '+66 92 345 6789', '2018-03-15', NULL,         'active',   'user'),
('EMP004', 'Kittipong Suwan',      N'กิตติพงษ์ สุวรรณ',    'Senior Programmer',    0, 0, '+66 83 456 7890', '2019-08-01', NULL,         'active',   'staff'),
('EMP005', 'Arisa Tanaka',         N'อริสา ทานากะ',         'Engineer(Lv3)',         0, 0, '+66 94 567 8901', '2020-02-01', '1992-01-29', 'active',   'user'),
('EMP006', 'Piyapong Rattana',     N'ปิยะพงษ์ รัตนะ',      'Senior Supervisor',    0, 0, '+66 85 678 9012', '2016-11-15', NULL,         'active',   'user'),
('EMP007', 'Siriporn Maneerat',    N'ศิริพร มณีรัตน์',     'Officer',              0, 0, '+66 86 789 0123', '2021-04-01', NULL,         'active',   'user'),
('EMP008', 'Thanawat Chaidee',     N'ธนวัฒน์ ใจดี',        'Senior Technician',    0, 0, '+66 87 890 1234', '2017-07-01', NULL,         'active',   'user'),
('EMP009', 'Pattaraporn Boonma',   N'ภัทราภรณ์ บุญมา',     'Staff',                0, 0, '+66 88 901 2345', '2022-01-15', NULL,         'active',   'user'),
('EMP010', 'Worawut Phongsak',     N'วรวุฒิ พงศ์ศักดิ์',   'Supervisor (Lv2)',     0, 0, '+66 89 012 3456', '2018-09-01', NULL,         'active',   'user'),
('EMP011', 'Chonlada Wongsa',      N'ชลลดา วงศา',           'Manager',              0, 1, '+66 90 123 4567', '2014-05-01', NULL,         'active',   'user'),
('EMP012', 'Prawit Sangthong',     N'ประวิทย์ แสงทอง',     'Manager',              0, 1, '+66 91 234 5678', '2013-08-15', NULL,         'active',   'user'),
('EMP013', 'Sumalee Inthachai',    N'สุมาลี อินทไชย',      'Senior Officer',       0, 0, '+66 82 345 6789', '2019-02-01', NULL,         'inactive', 'user'),
('EMP014', 'Natthawut Jitprasert', N'ณัฐวุฒิ จิตประเสริฐ', 'Programmer',           0, 0, '+66 93 456 7890', '2023-06-01', NULL,         'active',   'user'),
('EMP015', 'Kanokwan Phetdum',     N'กนกวรรณ เพชรดำ',      'Officer',              0, 0, '+66 84 567 8901', '2021-11-01', NULL,         'active',   'user');
GO

-- ============================================================
-- SAMPLE DATA: employee_assignments
-- ============================================================
INSERT INTO employee_assignments (employee_id, company, department, email, is_primary) VALUES
('EMP001', 'PTA', N'Management',           'somchai.p@pta.co.th',      1),
('EMP001', 'PT4', N'Management',           'somchai.p@pt4.co.th',      0),
('EMP002', 'PTA', N'HR and GA',            'nattaporn.c@pta.co.th',    1),
('EMP003', 'PTA', N'Accounting',           'wipada.s@pta.co.th',       1),
('EMP004', 'PTA', N'IT',                   'kittipong.s@pta.co.th',    1),
('EMP004', 'PT4', N'IT',                   'kittipong.s@pt4.co.th',    0),
('EMP004', 'PTE', N'IT',                   'kittipong.s@pte.co.th',    0),
('EMP005', 'PT4', N'Production Engineer',  'arisa.t@pt4.co.th',        1),
('EMP006', 'PT4', N'Production',           'piyapong.r@pt4.co.th',     1),
('EMP007', 'PTE', N'Quality Management',   'siriporn.m@pte.co.th',     1),
('EMP008', 'PTA', N'Maintenance',          'thanawat.c@pta.co.th',     1),
('EMP009', 'PT4', N'Logistics',            'pattaraporn.b@pt4.co.th',  1),
('EMP010', 'PTE', N'Safety & Environment', 'worawut.p@pte.co.th',      1),
('EMP011', 'PT4', N'Accounting',           'chonlada.w@pt4.co.th',     1),
('EMP012', 'PTA', N'Production',           'prawit.s@pta.co.th',       1),
('EMP013', 'PTA', N'HR and GA',            'sumalee.i@pta.co.th',      1),
('EMP014', 'PTA', N'IT',                   'natthawut.j@pta.co.th',    1),
('EMP015', 'PTE', N'Logistics',            'kanokwan.p@pte.co.th',     1);
GO
