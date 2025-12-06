-- =====================================================
-- SLiMS MULTI-BRANCH MIGRATION SCRIPT
-- Version: 1.0
-- Date: 2024
-- =====================================================

-- IMPORTANT: Backup database sebelum menjalankan script ini!
-- mysqldump -u root -p slims_database > backup_before_multibranch.sql

-- =====================================================
-- PART 1: CREATE NEW TABLES
-- =====================================================

-- 1.1 Tabel branches
CREATE TABLE IF NOT EXISTS `branches` (
  `branch_id` INT(11) NOT NULL AUTO_INCREMENT,
  `branch_code` VARCHAR(20) NOT NULL,
  `branch_name` VARCHAR(255) NOT NULL,
  `branch_address` TEXT,
  `branch_city` VARCHAR(100),
  `branch_province` VARCHAR(100),
  `branch_postal_code` VARCHAR(10),
  `branch_phone` VARCHAR(50),
  `branch_fax` VARCHAR(50),
  `branch_email` VARCHAR(100),
  `branch_website` VARCHAR(255),
  `branch_logo` VARCHAR(255),
  `branch_subdomain` VARCHAR(100),
  `branch_config` JSON,
  `is_main_branch` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`branch_id`),
  UNIQUE KEY `branch_code` (`branch_code`),
  UNIQUE KEY `branch_subdomain` (`branch_subdomain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1.2 Tabel user_branch_access
CREATE TABLE IF NOT EXISTS `user_branch_access` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `branch_id` INT(11) NOT NULL,
  `role` ENUM('super_admin','branch_admin','librarian','staff','counter') NOT NULL DEFAULT 'staff',
  `permissions` JSON,
  `is_primary` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_branch` (`user_id`, `branch_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1.3 Tabel import_logs
CREATE TABLE IF NOT EXISTS `import_logs` (
  `log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `branch_id` INT(11) NOT NULL,
  `source_db` VARCHAR(100),
  `source_host` VARCHAR(100),
  `source_type` ENUM('database','sql_file','csv') NOT NULL,
  `import_type` ENUM('full','incremental','selective') NOT NULL,
  `tables_imported` TEXT,
  `records_total` INT(11) DEFAULT 0,
  `records_imported` INT(11) DEFAULT 0,
  `records_skipped` INT(11) DEFAULT 0,
  `records_failed` INT(11) DEFAULT 0,
  `status` ENUM('pending','running','completed','failed','rolled_back') DEFAULT 'pending',
  `error_message` TEXT,
  `started_at` DATETIME,
  `completed_at` DATETIME,
  `imported_by` INT(11),
  `notes` TEXT,
  PRIMARY KEY (`log_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1.4 Tabel id_mapping
CREATE TABLE IF NOT EXISTS `id_mapping` (
  `mapping_id` INT(11) NOT NULL AUTO_INCREMENT,
  `import_log_id` INT(11),
  `branch_id` INT(11) NOT NULL,
  `table_name` VARCHAR(100) NOT NULL,
  `old_id` INT(11) NOT NULL,
  `new_id` INT(11) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `unique_mapping` (`branch_id`, `table_name`, `old_id`),
  KEY `idx_lookup` (`branch_id`, `table_name`, `old_id`),
  KEY `idx_import_log` (`import_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- PART 2: ALTER EXISTING TABLES - ADD branch_id
-- =====================================================

-- 2.1 Bibliografi & Koleksi
ALTER TABLE `biblio` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NOT NULL DEFAULT 1 AFTER `biblio_id`;
ALTER TABLE `item` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NOT NULL DEFAULT 1 AFTER `item_id`;
ALTER TABLE `biblio_attachment` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `biblio_author` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `biblio_topic` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `search_biblio` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NOT NULL DEFAULT 1;

-- 2.2 Keanggotaan
ALTER TABLE `member` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NOT NULL DEFAULT 1 AFTER `member_id`;

-- 2.3 Sirkulasi
ALTER TABLE `loan` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NOT NULL DEFAULT 1 AFTER `loan_id`;
ALTER TABLE `loan_history` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `reserve` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `fines` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NOT NULL DEFAULT 1;

-- 2.4 Master Data (NULL = shared)
ALTER TABLE `mst_author` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_publisher` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_topic` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_gmd` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_coll_type` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_item_status` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_supplier` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_place` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_language` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) DEFAULT NULL;

-- 2.5 Location (per branch)
ALTER TABLE `mst_location` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NOT NULL DEFAULT 1;

-- 2.6 User
ALTER TABLE `user` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) DEFAULT NULL AFTER `user_id`;
ALTER TABLE `user` ADD COLUMN IF NOT EXISTS `is_super_admin` TINYINT(1) DEFAULT 0;

-- 2.7 Stock Take
ALTER TABLE `stock_take` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `stock_take_item` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NOT NULL DEFAULT 1;

-- 2.8 Others
ALTER TABLE `content` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `visitor_count` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `comment` ADD COLUMN IF NOT EXISTS `branch_id` INT(11) NOT NULL DEFAULT 1;

-- =====================================================
-- PART 3: CREATE INDEXES
-- =====================================================

-- Drop existing indexes if any (ignore errors)
-- ALTER TABLE `biblio` DROP INDEX IF EXISTS `idx_branch`;

-- Create indexes
CREATE INDEX `idx_branch` ON `biblio` (`branch_id`);
CREATE INDEX `idx_branch` ON `item` (`branch_id`);
CREATE INDEX `idx_branch` ON `member` (`branch_id`);
CREATE INDEX `idx_branch` ON `loan` (`branch_id`);
CREATE INDEX `idx_branch` ON `loan_history` (`branch_id`);
CREATE INDEX `idx_branch` ON `reserve` (`branch_id`);
CREATE INDEX `idx_branch` ON `mst_location` (`branch_id`);
CREATE INDEX `idx_branch` ON `visitor_count` (`branch_id`);
CREATE INDEX `idx_branch` ON `user` (`branch_id`);

-- Composite indexes for common queries
CREATE INDEX `idx_branch_title` ON `biblio` (`branch_id`, `title`(100));
CREATE INDEX `idx_branch_biblio` ON `item` (`branch_id`, `biblio_id`);
CREATE INDEX `idx_branch_member` ON `loan` (`branch_id`, `member_id`);
CREATE INDEX `idx_branch_date` ON `loan` (`branch_id`, `loan_date`);

-- =====================================================
-- PART 4: INSERT DEFAULT DATA
-- =====================================================

-- Insert default branch (existing library)
INSERT INTO `branches` (`branch_id`, `branch_code`, `branch_name`, `is_main_branch`, `is_active`) 
VALUES (1, 'MAIN', 'Perpustakaan Pusat', 1, 1)
ON DUPLICATE KEY UPDATE `branch_name` = VALUES(`branch_name`);

-- Set existing admin as super admin
UPDATE `user` SET `is_super_admin` = 1 WHERE `user_id` = 1;

-- Create branch access for existing admin
INSERT INTO `user_branch_access` (`user_id`, `branch_id`, `role`, `is_primary`)
SELECT `user_id`, 1, 'super_admin', 1 FROM `user` WHERE `user_id` = 1
ON DUPLICATE KEY UPDATE `role` = 'super_admin';

-- =====================================================
-- PART 5: CREATE VIEWS
-- =====================================================

-- View: Branch Statistics
CREATE OR REPLACE VIEW `v_branch_statistics` AS
SELECT 
  b.branch_id,
  b.branch_code,
  b.branch_name,
  b.is_active,
  (SELECT COUNT(*) FROM biblio WHERE branch_id = b.branch_id) as total_titles,
  (SELECT COUNT(*) FROM item WHERE branch_id = b.branch_id) as total_items,
  (SELECT COUNT(*) FROM member WHERE branch_id = b.branch_id) as total_members,
  (SELECT COUNT(*) FROM loan WHERE branch_id = b.branch_id AND is_return = 0) as active_loans,
  (SELECT COUNT(*) FROM loan WHERE branch_id = b.branch_id AND DATE(loan_date) = CURDATE()) as loans_today,
  (SELECT COUNT(*) FROM visitor_count WHERE branch_id = b.branch_id AND DATE(checkin_date) = CURDATE()) as visitors_today
FROM branches b;

-- View: Union Catalog
CREATE OR REPLACE VIEW `v_union_catalog` AS
SELECT 
  bi.biblio_id,
  bi.title,
  bi.isbn_issn,
  bi.publish_year,
  bi.branch_id,
  br.branch_name,
  br.branch_code,
  (SELECT GROUP_CONCAT(a.author_name SEPARATOR '; ') 
   FROM biblio_author ba 
   JOIN mst_author a ON a.author_id = ba.author_id 
   WHERE ba.biblio_id = bi.biblio_id) as authors,
  (SELECT COUNT(*) FROM item i WHERE i.biblio_id = bi.biblio_id AND i.branch_id = bi.branch_id) as total_copies,
  (SELECT COUNT(*) FROM item i WHERE i.biblio_id = bi.biblio_id AND i.branch_id = bi.branch_id 
   AND i.item_id NOT IN (SELECT item_code FROM loan WHERE is_return = 0)) as available_copies
FROM biblio bi
JOIN branches br ON br.branch_id = bi.branch_id
WHERE br.is_active = 1;

-- =====================================================
-- PART 6: STORED PROCEDURES
-- =====================================================

DELIMITER //

-- Procedure: Get branch statistics
CREATE PROCEDURE IF NOT EXISTS `sp_get_branch_stats`(IN p_branch_id INT)
BEGIN
  SELECT 
    b.branch_name,
    COUNT(DISTINCT bib.biblio_id) as total_titles,
    COUNT(DISTINCT i.item_id) as total_items,
    COUNT(DISTINCT m.member_id) as total_members,
    COUNT(DISTINCT CASE WHEN l.is_return = 0 THEN l.loan_id END) as active_loans
  FROM branches b
  LEFT JOIN biblio bib ON bib.branch_id = b.branch_id
  LEFT JOIN item i ON i.branch_id = b.branch_id
  LEFT JOIN member m ON m.branch_id = b.branch_id
  LEFT JOIN loan l ON l.branch_id = b.branch_id
  WHERE b.branch_id = p_branch_id OR p_branch_id IS NULL
  GROUP BY b.branch_id;
END //

-- Procedure: Validate import
CREATE PROCEDURE IF NOT EXISTS `sp_validate_import`(IN p_branch_id INT, IN p_log_id INT)
BEGIN
  DECLARE v_orphan_items INT;
  DECLARE v_orphan_loans INT;
  
  -- Check orphan items
  SELECT COUNT(*) INTO v_orphan_items
  FROM item i
  WHERE i.branch_id = p_branch_id
  AND NOT EXISTS (SELECT 1 FROM biblio b WHERE b.biblio_id = i.biblio_id);
  
  -- Check orphan loans
  SELECT COUNT(*) INTO v_orphan_loans
  FROM loan l
  WHERE l.branch_id = p_branch_id
  AND NOT EXISTS (SELECT 1 FROM member m WHERE m.member_id = l.member_id);
  
  -- Return validation result
  SELECT 
    v_orphan_items as orphan_items,
    v_orphan_loans as orphan_loans,
    CASE WHEN v_orphan_items = 0 AND v_orphan_loans = 0 THEN 'VALID' ELSE 'HAS_ISSUES' END as status;
END //

DELIMITER ;

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================

-- Verify branch_id columns added
SELECT 
  TABLE_NAME, 
  COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND COLUMN_NAME = 'branch_id'
ORDER BY TABLE_NAME;

-- Verify indexes created
SELECT 
  TABLE_NAME,
  INDEX_NAME,
  COLUMN_NAME
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND INDEX_NAME LIKE '%branch%'
ORDER BY TABLE_NAME;

-- Count records per table
SELECT 'branches' as tbl, COUNT(*) as cnt FROM branches
UNION ALL SELECT 'biblio', COUNT(*) FROM biblio
UNION ALL SELECT 'item', COUNT(*) FROM item
UNION ALL SELECT 'member', COUNT(*) FROM member
UNION ALL SELECT 'loan', COUNT(*) FROM loan;
