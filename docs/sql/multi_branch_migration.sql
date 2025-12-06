-- =====================================================
-- SLiMS MULTI-BRANCH MIGRATION SCRIPT
-- Version: 1.1 (MySQL 5.7 Compatible)
-- =====================================================

-- PART 1: CREATE NEW TABLES
-- =====================================================

CREATE TABLE IF NOT EXISTS `branches` (
  `branch_id` INT(11) NOT NULL AUTO_INCREMENT,
  `branch_code` VARCHAR(20) NOT NULL,
  `branch_name` VARCHAR(255) NOT NULL,
  `branch_address` TEXT,
  `branch_city` VARCHAR(100),
  `branch_province` VARCHAR(100),
  `branch_phone` VARCHAR(50),
  `branch_email` VARCHAR(100),
  `branch_logo` VARCHAR(255),
  `branch_subdomain` VARCHAR(100),
  `branch_config` TEXT,
  `is_main_branch` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`branch_id`),
  UNIQUE KEY `branch_code` (`branch_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_branch_access` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `branch_id` INT(11) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'staff',
  `permissions` TEXT,
  `is_primary` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_branch` (`user_id`, `branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `import_logs` (
  `log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `branch_id` INT(11) NOT NULL,
  `source_db` VARCHAR(100),
  `source_type` VARCHAR(20) NOT NULL,
  `import_type` VARCHAR(20) NOT NULL,
  `records_total` INT(11) DEFAULT 0,
  `records_imported` INT(11) DEFAULT 0,
  `records_skipped` INT(11) DEFAULT 0,
  `records_failed` INT(11) DEFAULT 0,
  `status` VARCHAR(20) DEFAULT 'pending',
  `error_message` TEXT,
  `started_at` DATETIME,
  `completed_at` DATETIME,
  `imported_by` INT(11),
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `id_mapping` (
  `mapping_id` INT(11) NOT NULL AUTO_INCREMENT,
  `import_log_id` INT(11),
  `branch_id` INT(11) NOT NULL,
  `table_name` VARCHAR(100) NOT NULL,
  `old_id` INT(11) NOT NULL,
  `new_id` INT(11) NOT NULL,
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `unique_mapping` (`branch_id`, `table_name`, `old_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- PART 2: ADD branch_id COLUMNS (with error handling)
-- =====================================================

-- Helper procedure to add column if not exists
DROP PROCEDURE IF EXISTS add_branch_id_column;
DELIMITER //
CREATE PROCEDURE add_branch_id_column(IN tbl VARCHAR(100), IN after_col VARCHAR(100), IN default_val INT)
BEGIN
  DECLARE col_exists INT DEFAULT 0;
  SELECT COUNT(*) INTO col_exists FROM information_schema.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = 'branch_id';
  
  IF col_exists = 0 THEN
    IF after_col IS NOT NULL AND after_col != '' THEN
      SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `branch_id` INT(11) DEFAULT ', default_val, ' AFTER `', after_col, '`');
    ELSE
      SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `branch_id` INT(11) DEFAULT ', default_val);
    END IF;
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END //
DELIMITER ;

-- Add branch_id to tables
CALL add_branch_id_column('biblio', 'biblio_id', 1);
CALL add_branch_id_column('item', 'item_id', 1);
CALL add_branch_id_column('member', 'member_id', 1);
CALL add_branch_id_column('loan', 'loan_id', 1);
CALL add_branch_id_column('loan_history', 'loan_history_id', 1);
CALL add_branch_id_column('reserve', 'reserve_id', 1);
CALL add_branch_id_column('fines', 'fines_id', 1);
CALL add_branch_id_column('mst_location', 'location_id', 1);
CALL add_branch_id_column('mst_author', 'author_id', NULL);
CALL add_branch_id_column('mst_publisher', 'publisher_id', NULL);
CALL add_branch_id_column('mst_topic', 'topic_id', NULL);
CALL add_branch_id_column('mst_gmd', 'gmd_id', NULL);
CALL add_branch_id_column('mst_coll_type', 'coll_type_id', NULL);
CALL add_branch_id_column('mst_item_status', 'item_status_id', NULL);
CALL add_branch_id_column('mst_supplier', 'supplier_id', NULL);
CALL add_branch_id_column('mst_place', 'place_id', NULL);
CALL add_branch_id_column('mst_language', 'language_id', NULL);
CALL add_branch_id_column('visitor_count', 'visitor_id', 1);
CALL add_branch_id_column('content', 'content_id', NULL);
CALL add_branch_id_column('comment', 'comment_id', 1);
CALL add_branch_id_column('biblio_author', '', 1);
CALL add_branch_id_column('biblio_topic', '', 1);
CALL add_branch_id_column('search_biblio', '', 1);
CALL add_branch_id_column('stock_take', 'stock_take_id', 1);

-- Add is_super_admin to user table
DROP PROCEDURE IF EXISTS add_user_columns;
DELIMITER //
CREATE PROCEDURE add_user_columns()
BEGIN
  DECLARE col1_exists INT DEFAULT 0;
  DECLARE col2_exists INT DEFAULT 0;
  
  SELECT COUNT(*) INTO col1_exists FROM information_schema.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user' AND COLUMN_NAME = 'branch_id';
  
  SELECT COUNT(*) INTO col2_exists FROM information_schema.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user' AND COLUMN_NAME = 'is_super_admin';
  
  IF col1_exists = 0 THEN
    ALTER TABLE `user` ADD COLUMN `branch_id` INT(11) DEFAULT NULL AFTER `user_id`;
  END IF;
  
  IF col2_exists = 0 THEN
    ALTER TABLE `user` ADD COLUMN `is_super_admin` TINYINT(1) DEFAULT 0;
  END IF;
END //
DELIMITER ;

CALL add_user_columns();

-- Cleanup procedures
DROP PROCEDURE IF EXISTS add_branch_id_column;
DROP PROCEDURE IF EXISTS add_user_columns;

-- =====================================================
-- PART 3: CREATE INDEXES
-- =====================================================

-- Create indexes (ignore if exists)
CREATE INDEX idx_biblio_branch ON biblio(branch_id);
CREATE INDEX idx_item_branch ON item(branch_id);
CREATE INDEX idx_member_branch ON member(branch_id);
CREATE INDEX idx_loan_branch ON loan(branch_id);
CREATE INDEX idx_location_branch ON mst_location(branch_id);

-- =====================================================
-- PART 4: INSERT DEFAULT DATA
-- =====================================================

INSERT INTO `branches` (`branch_id`, `branch_code`, `branch_name`, `is_main_branch`, `is_active`) 
VALUES (1, 'MAIN', 'Perpustakaan Pusat', 1, 1)
ON DUPLICATE KEY UPDATE `branch_name` = VALUES(`branch_name`);

-- Set first admin as super admin
UPDATE `user` SET `is_super_admin` = 1 WHERE `user_id` = 1;

-- Create branch access for admin
INSERT IGNORE INTO `user_branch_access` (`user_id`, `branch_id`, `role`, `is_primary`)
VALUES (1, 1, 'super_admin', 1);

-- =====================================================
-- VERIFICATION
-- =====================================================
SELECT 'Migration completed!' as status;
SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'branch_id' 
ORDER BY TABLE_NAME LIMIT 20;
