/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `level` varchar(50) DEFAULT 'info',
  `category` varchar(50) DEFAULT 'hr',
  `expirationDate` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_categories` (
  `cat_id` int(11) NOT NULL AUTO_INCREMENT,
  `cat_name` varchar(255) NOT NULL,
  `cat_abbr` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`cat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asset_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_db_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` varchar(50) DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  KEY `asset_db_id` (`asset_db_id`)
) ENGINE=InnoDB AUTO_INCREMENT=247 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` varchar(50) DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `target_id` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_group_invites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `inviter_id` varchar(50) NOT NULL,
  `invitee_id` varchar(50) NOT NULL,
  `status` enum('pending','accepted','declined','expired') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expired_at` datetime DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_chat_group_invites_group` (`group_id`),
  KEY `idx_chat_group_invites_invitee` (`invitee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_group_member_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `actor_id` varchar(50) DEFAULT NULL,
  `target_id` varchar(50) DEFAULT NULL,
  `action` enum('invite_sent','invite_accepted','invite_declined','member_joined','member_removed') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_chat_group_member_logs_group` (`group_id`),
  KEY `idx_chat_group_member_logs_actor` (`actor_id`),
  KEY `idx_chat_group_member_logs_target` (`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `creator_id` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_group_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_user` (`group_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `chat_group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `chat_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` varchar(50) NOT NULL,
  `receiver_id` varchar(50) NOT NULL,
  `company` varchar(10) NOT NULL,
  `message_text` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`message_id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `company` (`company`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chat_typing` (
  `user_id` varchar(50) NOT NULL,
  `typing_to` varchar(50) NOT NULL,
  `timestamp` int(11) NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_checklist_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company` varchar(10) NOT NULL DEFAULT '' COMMENT 'PTA, PT4, PTE หรือว่างสำหรับใช้ร่วมกัน',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_daily_items_company` (`company`),
  KEY `idx_daily_items_active` (`is_active`),
  KEY `idx_daily_items_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_checklist_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `checklist_item_id` int(11) NOT NULL,
  `checklist_date` date NOT NULL,
  `company` varchar(10) NOT NULL DEFAULT '',
  `user_id` varchar(50) NOT NULL,
  `user_name` varchar(120) DEFAULT NULL,
  `checked_at` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_item_date` (`checklist_item_id`,`checklist_date`),
  KEY `idx_daily_logs_date` (`checklist_date`),
  KEY `idx_daily_logs_company` (`company`),
  KEY `idx_daily_logs_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `department_contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company` varchar(10) NOT NULL,
  `department` varchar(100) NOT NULL,
  `sub_department` varchar(100) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_contact` (`company`,`department`,`sub_department`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `dept_id` int(11) NOT NULL AUTO_INCREMENT,
  `dept_name` varchar(255) NOT NULL,
  `dept_abbr` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`dept_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_assignments` (
  `assignment_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(50) NOT NULL,
  `company` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`assignment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=743 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_th` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `is_executive` tinyint(1) NOT NULL DEFAULT 0,
  `is_management` tinyint(1) NOT NULL DEFAULT 0,
  `is_technician` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_stock` tinyint(1) NOT NULL DEFAULT 0,
  `phone` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `responsible_department` varchar(255) DEFAULT NULL,
  `remember_token` varchar(64) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `employment_status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_employees_status` (`employment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `holidays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `it_assets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `equipment_id` varchar(100) NOT NULL COMMENT 'ID อุปกรณ์ (ตัวหลัก)',
  `name` varchar(255) NOT NULL COMMENT 'ชื่ออุปกรณ์',
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `owning_department` int(11) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL COMMENT 'IP Address',
  `mac_address` varchar(50) DEFAULT NULL COMMENT 'MAC Address',
  `company` varchar(255) DEFAULT NULL,
  `custodian_employee_id` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL COMMENT 'รายละเอียด',
  `spec_details` text DEFAULT NULL COMMENT 'สเปคโดยละเอียด',
  `image_url` text DEFAULT NULL COMMENT 'รูปภาพ (URL)',
  `images` text DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'ว่าง' COMMENT 'สถานะ',
  `is_loanable` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = For Loan, 0 = Not for Loan',
  `asset_pool` varchar(50) NOT NULL DEFAULT 'general',
  `borrower` varchar(255) DEFAULT NULL COMMENT 'ผู้ยืม',
  `department` varchar(100) DEFAULT NULL COMMENT 'แผนกผู้ยืม',
  `borrower_id` varchar(50) DEFAULT NULL,
  `expected_return_date` date DEFAULT NULL,
  `serial_number` varchar(255) DEFAULT NULL COMMENT 'Serial Number',
  `asset_id` varchar(255) DEFAULT NULL COMMENT 'เลขทะเบียนทรัพย์สิน (จากบัญชี)',
  `asset_category` int(11) DEFAULT NULL COMMENT 'หมวดหมู่อุปกรณ์',
  `purchase_date` date DEFAULT NULL COMMENT 'วันที่ซื้อ',
  `warranty_expires_on` date DEFAULT NULL,
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL COMMENT 'ผู้จำหน่าย/ร้านค้า',
  `po_number` varchar(100) DEFAULT NULL COMMENT 'เลขที่ใบสั่งซื้อ',
  `invoice_number` varchar(100) DEFAULT NULL COMMENT 'เลขที่ใบกำกับภาษี',
  `current_value` decimal(10,2) DEFAULT NULL,
  `accessories` text DEFAULT NULL,
  `purpose` text DEFAULT NULL COMMENT 'วัตถุประสงค์ที่ขอ',
  `return_date` date DEFAULT NULL COMMENT 'วันกำหนดคืน (จาก loan.html)',
  `checkout_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `equipment_id` (`equipment_id`),
  KEY `idx_borrower_id` (`borrower_id`),
  KEY `idx_it_assets_status` (`status`),
  KEY `idx_it_assets_company` (`company`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `it_loan_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_id` varchar(100) NOT NULL COMMENT 'ID อุปกรณ์',
  `equipment_name` varchar(255) NOT NULL COMMENT 'ชื่ออุปกรณ์',
  `borrower` varchar(255) NOT NULL COMMENT 'ผู้ยืม',
  `department` varchar(100) DEFAULT NULL COMMENT 'แผนก',
  `borrower_id` varchar(50) DEFAULT NULL,
  `purpose` text DEFAULT NULL COMMENT 'วัตถุประสงค์',
  `expected_return_date` date DEFAULT NULL,
  `approver_name` varchar(255) DEFAULT NULL,
  `borrower_signature` varchar(255) DEFAULT NULL COMMENT 'เก็บ path/URL รูปไฟล์ลายเซ็นผู้ยืม',
  `return_approver_signature` varchar(255) DEFAULT NULL COMMENT 'เก็บ path/URL รูปไฟล์ลายเซ็นผู้รับคืน',
  `condition_notes` text DEFAULT NULL,
  `lend_date` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'วันที่ยืม',
  `return_date` datetime DEFAULT NULL COMMENT 'วันที่คืน',
  `return_receiver_name` varchar(255) DEFAULT NULL,
  `return_condition` varchar(255) DEFAULT NULL,
  `return_notes` text DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  KEY `equipment_id_idx` (`equipment_id`),
  KEY `idx_borrower_id_history` (`borrower_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `it_log_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `log_id` varchar(50) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_filename` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `log_id_index` (`log_id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `it_log_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `log_id` varchar(50) NOT NULL COMMENT 'เชื่อมโยงกับ it_logs.id',
  `user_id` varchar(50) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_log_id` (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `it_logs` (
  `id` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `company` varchar(50) DEFAULT NULL,
  `requester` varchar(255) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `asset_id` varchar(100) DEFAULT NULL COMMENT 'รหัสทรัพย์สินที่เกี่ยวข้อง',
  `requester_id` varchar(50) DEFAULT NULL,
  `request_uid` varchar(64) DEFAULT NULL,
  `servicedBy` varchar(255) DEFAULT NULL,
  `servicedById` varchar(50) DEFAULT NULL,
  `asset` varchar(100) DEFAULT NULL,
  `problem` text NOT NULL,
  `contact` varchar(100) DEFAULT NULL COMMENT 'เบอร์ติดต่อกลับ',
  `image_path` varchar(255) DEFAULT NULL COMMENT 'รูปภาพประกอบการแจ้งซ่อม',
  `solution` text DEFAULT NULL,
  `root_cause` text DEFAULT NULL,
  `doc_number` varchar(30) DEFAULT NULL,
  `user_confirmed` tinyint(1) DEFAULT 0,
  `user_confirmed_at` datetime DEFAULT NULL,
  `user_confirmed_by` varchar(100) DEFAULT NULL,
  `repair_cost` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `rating` int(1) DEFAULT NULL COMMENT 'คะแนนความพึงพอใจ 1-5',
  `completed_date` datetime DEFAULT NULL,
  `last_work_start_time` datetime DEFAULT NULL COMMENT 'เวลาที่เริ่มทำงานล่าสุด',
  `total_work_duration_seconds` int(11) NOT NULL DEFAULT 0 COMMENT 'เวลารวมที่ทำงาน (วินาที)',
  `type` varchar(50) NOT NULL,
  `urgency` varchar(100) DEFAULT 'ปกติ',
  `notes` text DEFAULT NULL,
  `startDate` date DEFAULT NULL,
  `endDate` date DEFAULT NULL,
  `appointment_date` datetime DEFAULT NULL,
  `closed_date` datetime DEFAULT NULL,
  `evaluation_hardware` text DEFAULT NULL COMMENT 'ผลประเมินฮาร์ดแวร์',
  `evaluation_software` text DEFAULT NULL COMMENT 'ผลประเมินซอฟต์แวร์',
  `evaluation_repair_cost` decimal(10,2) DEFAULT NULL COMMENT 'ประมาณการค่าใช้จ่ายซ่อม',
  `evaluation_recommendation` varchar(20) DEFAULT NULL COMMENT 'ข้อแนะนำ: repair/replace',
  `evaluation_reason` text DEFAULT NULL COMMENT 'เหตุผลประกอบการตัดสินใจ',
  `pr_no` varchar(50) DEFAULT NULL,
  `po_no` varchar(50) DEFAULT NULL,
  `vendor_name` varchar(255) DEFAULT NULL,
  `eta_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_requester_id` (`requester_id`),
  KEY `idx_servicedById` (`servicedById`),
  KEY `idx_it_logs_asset_id` (`asset_id`),
  KEY `idx_it_logs_status` (`status`),
  KEY `idx_it_logs_company` (`company`),
  KEY `idx_it_logs_type` (`type`),
  KEY `idx_request_uid` (`request_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 999,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_position_name` (`name`),
  KEY `idx_sort_order` (`sort_order`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=187 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company` varchar(10) NOT NULL COMMENT 'PT4, PTA, PTE',
  `department` varchar(100) DEFAULT NULL,
  `building` varchar(255) DEFAULT NULL COMMENT 'รายชื่อตึก',
  `job_name` varchar(255) NOT NULL,
  `responsible` varchar(255) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `is_receive` tinyint(1) DEFAULT 0,
  `is_doing` tinyint(1) DEFAULT 0,
  `is_send` tinyint(1) DEFAULT 0,
  `is_approve` tinyint(1) DEFAULT 0,
  `is_done` tinyint(1) DEFAULT 0,
  `last_update` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by_id` varchar(50) DEFAULT NULL,
  `created_by_name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kb_articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `author_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `view_count` int(11) NOT NULL DEFAULT 0,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `author_id` (`author_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kb_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT 'fa-book',
  `sort_order` int(11) DEFAULT 99,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `company` varchar(10) DEFAULT NULL COMMENT 'PTA, PT4, PTE or NULL for all',
  `is_active` tinyint(1) DEFAULT 1,
  `image` varchar(255) DEFAULT NULL COMMENT 'Path to map image',
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permission_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `permissions_json` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_task_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` varchar(64) DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `progress_pct` tinyint(3) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_comment_task` (`task_id`),
  KEY `idx_comment_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_task_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `user_name` varchar(120) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) DEFAULT NULL,
  `old_priority` varchar(20) DEFAULT NULL,
  `new_priority` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_history_task` (`task_id`),
  KEY `idx_history_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_tasks` (
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_personal_tasks_user` (`user_id`),
  KEY `idx_personal_tasks_due` (`due_date`),
  KEY `idx_personal_tasks_status` (`status`),
  KEY `idx_personal_tasks_company` (`company`),
  KEY `idx_personal_tasks_department` (`department`),
  KEY `idx_personal_tasks_share_scope` (`share_scope`),
  KEY `idx_personal_tasks_type` (`task_type`),
  KEY `idx_personal_tasks_workflow` (`workflow_stage`),
  KEY `idx_personal_tasks_legacy_job` (`legacy_job_ticket_id`),
  KEY `idx_personal_tasks_recurring_parent` (`recurring_parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `print_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
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
  PRIMARY KEY (`id`),
  KEY `idx_print_logs_ts` (`timestamp`),
  KEY `idx_print_logs_user` (`user_id`),
  KEY `idx_print_logs_doc` (`document_type`,`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `setup_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `log_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
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
  `checklist_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`checklist_data`)),
  PRIMARY KEY (`id`),
  KEY `asset_code_index` (`asset_code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_items` (
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_item_code_company` (`item_code`,`company`)
) ENGINE=InnoDB AUTO_INCREMENT=151 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_request_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `qty_requested` int(11) NOT NULL DEFAULT 1,
  `qty_approved` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_sri_request` (`request_id`),
  KEY `fk_sri_item` (`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company` enum('PTA','PT4','PTE') NOT NULL,
  `stock_type` enum('stationery','maid') NOT NULL,
  `branch` varchar(20) DEFAULT NULL,
  `requester_id` varchar(50) NOT NULL,
  `requester_name` varchar(255) NOT NULL,
  `department` varchar(255) DEFAULT NULL,
  `request_date` datetime DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `deny_reason` text DEFAULT NULL,
  `approved_by` varchar(255) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `company` enum('PTA','PT4','PTE') NOT NULL,
  `type` enum('received','issued','adjusted') NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `transaction_date` datetime DEFAULT current_timestamp(),
  `branch` varchar(20) DEFAULT NULL,
  `requester_name` varchar(255) DEFAULT NULL,
  `requester_id` varchar(50) DEFAULT NULL,
  `request_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_st_item` (`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=117 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
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
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_company_name` (`company`,`name`),
  KEY `idx_name` (`name`),
  KEY `idx_company` (`company`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supply_categories` (
  `cat_id` int(11) NOT NULL AUTO_INCREMENT,
  `cat_name` varchar(255) NOT NULL,
  PRIMARY KEY (`cat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supply_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_code` varchar(50) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_description` text DEFAULT NULL,
  `cat_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `quantity_on_hand` int(11) NOT NULL DEFAULT 0,
  `quantity_reserved` int(11) NOT NULL DEFAULT 0,
  `min_stock_level` int(11) NOT NULL DEFAULT 0,
  `expiry_date` date DEFAULT NULL,
  `item_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`item_id`),
  UNIQUE KEY `item_code` (`item_code`),
  KEY `cat_id` (`cat_id`),
  KEY `unit_id` (`unit_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supply_requisition_details` (
  `detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `req_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_name_snapshot` varchar(255) NOT NULL,
  `quantity_requested` int(11) NOT NULL,
  PRIMARY KEY (`detail_id`),
  KEY `fk_req` (`req_id`),
  CONSTRAINT `fk_req` FOREIGN KEY (`req_id`) REFERENCES `supply_requisitions` (`req_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supply_requisitions` (
  `req_id` int(11) NOT NULL AUTO_INCREMENT,
  `request_by_id` varchar(100) DEFAULT NULL,
  `request_by_username` varchar(100) NOT NULL,
  `request_by_name` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `company` varchar(50) DEFAULT NULL,
  `request_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL DEFAULT 'Pending Manager',
  `approver_1_username` varchar(100) DEFAULT NULL,
  `approval_1_date` datetime DEFAULT NULL,
  `admin_dispense_username` varchar(100) DEFAULT NULL,
  `dispense_date` datetime DEFAULT NULL,
  `denial_reason` text DEFAULT NULL,
  `req_type` enum('OFFICE_SUPPLY','IT_LOAN') NOT NULL DEFAULT 'OFFICE_SUPPLY',
  `purpose` text DEFAULT NULL,
  PRIMARY KEY (`req_id`),
  KEY `request_by_username` (`request_by_username`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supply_units` (
  `unit_id` int(11) NOT NULL AUTO_INCREMENT,
  `unit_name` varchar(100) NOT NULL,
  PRIMARY KEY (`unit_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_permission_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `role_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_role` (`user_id`,`role_id`),
  KEY `idx_upr_user` (`user_id`),
  KEY `fk_upr_role` (`role_id`),
  CONSTRAINT `fk_upr_role` FOREIGN KEY (`role_id`) REFERENCES `permission_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `permission_key` varchar(100) NOT NULL,
  `is_granted` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_perm` (`user_id`,`permission_key`),
  KEY `idx_up_user` (`user_id`),
  KEY `idx_up_perm` (`permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `role` enum('admin','staff','user') NOT NULL DEFAULT 'user',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
