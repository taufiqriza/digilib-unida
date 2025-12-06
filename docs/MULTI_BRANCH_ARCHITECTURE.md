# Multi-Branch Architecture & Database Schema

## 1. Database Model

**Pendekatan: Single Database + Branch ID**

Semua data dalam 1 database, dipisahkan dengan kolom `branch_id`.

## 2. Tabel Baru

### 2.1 Tabel `branches`

```sql
CREATE TABLE `branches` (
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
```

### 2.2 Tabel `user_branch_access`

```sql
CREATE TABLE `user_branch_access` (
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
  KEY `idx_branch` (`branch_id`),
  CONSTRAINT `fk_uba_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uba_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.3 Tabel `import_logs`

```sql
CREATE TABLE `import_logs` (
  `log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `branch_id` INT(11) NOT NULL,
  `source_db` VARCHAR(100),
  `source_type` ENUM('database','sql_file','csv') NOT NULL,
  `import_type` ENUM('full','incremental','selective') NOT NULL,
  `table_name` VARCHAR(100),
  `records_total` INT(11) DEFAULT 0,
  `records_imported` INT(11) DEFAULT 0,
  `records_skipped` INT(11) DEFAULT 0,
  `records_failed` INT(11) DEFAULT 0,
  `status` ENUM('pending','running','completed','failed','rolled_back') DEFAULT 'pending',
  `error_message` TEXT,
  `started_at` DATETIME,
  `completed_at` DATETIME,
  `imported_by` INT(11),
  `rollback_data` LONGTEXT,
  PRIMARY KEY (`log_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.4 Tabel `id_mapping`

```sql
-- Untuk tracking ID lama vs ID baru saat import
CREATE TABLE `id_mapping` (
  `mapping_id` INT(11) NOT NULL AUTO_INCREMENT,
  `import_log_id` INT(11) NOT NULL,
  `branch_id` INT(11) NOT NULL,
  `table_name` VARCHAR(100) NOT NULL,
  `old_id` INT(11) NOT NULL,
  `new_id` INT(11) NOT NULL,
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `unique_mapping` (`branch_id`, `table_name`, `old_id`),
  KEY `idx_lookup` (`branch_id`, `table_name`, `old_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 3. Modifikasi Tabel Existing

### 3.1 Script Alter Table

```sql
-- =====================================================
-- MIGRATION SCRIPT: Add branch_id to existing tables
-- =====================================================

-- 1. Bibliografi & Koleksi
ALTER TABLE `biblio` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1 AFTER `biblio_id`;
ALTER TABLE `biblio` ADD INDEX `idx_branch` (`branch_id`);

ALTER TABLE `item` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1 AFTER `item_id`;
ALTER TABLE `item` ADD INDEX `idx_branch` (`branch_id`);

ALTER TABLE `biblio_attachment` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `biblio_author` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `biblio_topic` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `biblio_relation` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `biblio_custom` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `biblio_log` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `search_biblio` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;

-- 2. Keanggotaan
ALTER TABLE `member` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1 AFTER `member_id`;
ALTER TABLE `member` ADD INDEX `idx_branch` (`branch_id`);

ALTER TABLE `member_custom` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;

-- 3. Sirkulasi
ALTER TABLE `loan` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1 AFTER `loan_id`;
ALTER TABLE `loan` ADD INDEX `idx_branch` (`branch_id`);

ALTER TABLE `loan_history` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `loan_history` ADD INDEX `idx_branch` (`branch_id`);

ALTER TABLE `reserve` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `reserve` ADD INDEX `idx_branch` (`branch_id`);

ALTER TABLE `fines` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `fines` ADD INDEX `idx_branch` (`branch_id`);

-- 4. Master Data (NULL = shared across branches)
ALTER TABLE `mst_author` ADD COLUMN `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_author` ADD INDEX `idx_branch` (`branch_id`);

ALTER TABLE `mst_publisher` ADD COLUMN `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_topic` ADD COLUMN `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_gmd` ADD COLUMN `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_coll_type` ADD COLUMN `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_item_status` ADD COLUMN `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_supplier` ADD COLUMN `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_place` ADD COLUMN `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_frequency` ADD COLUMN `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_language` ADD COLUMN `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_carrier_type` ADD COLUMN `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_content_type` ADD COLUMN `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_media_type` ADD COLUMN `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `mst_label` ADD COLUMN `branch_id` INT(11) DEFAULT NULL;

-- 5. Lokasi (per branch)
ALTER TABLE `mst_location` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `mst_location` ADD INDEX `idx_branch` (`branch_id`);

-- 6. User & Staff
ALTER TABLE `user` ADD COLUMN `branch_id` INT(11) DEFAULT NULL AFTER `user_id`;
ALTER TABLE `user` ADD COLUMN `is_super_admin` TINYINT(1) DEFAULT 0;
ALTER TABLE `user` ADD INDEX `idx_branch` (`branch_id`);

-- 7. Stock Take
ALTER TABLE `stock_take` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `stock_take_item` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;

-- 8. Serial Control
ALTER TABLE `serial` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `kardex` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;

-- 9. Content & Visitor
ALTER TABLE `content` ADD COLUMN `branch_id` INT(11) DEFAULT NULL;
ALTER TABLE `visitor_count` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `visitor_count` ADD INDEX `idx_branch` (`branch_id`);

-- 10. Comments & Others
ALTER TABLE `comment` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;
ALTER TABLE `biblio_mark` ADD COLUMN `branch_id` INT(11) NOT NULL DEFAULT 1;

-- 11. Insert default branch
INSERT INTO `branches` (`branch_id`, `branch_code`, `branch_name`, `is_main_branch`, `is_active`) 
VALUES (1, 'MAIN', 'Perpustakaan Pusat', 1, 1);
```

## 4. Tabel yang Perlu Branch ID

### Kategori A: Wajib Branch ID (Data Terpisah)
| Tabel | Keterangan |
|-------|------------|
| biblio | Katalog bibliografi |
| item | Eksemplar/copy |
| member | Anggota |
| loan | Peminjaman aktif |
| loan_history | Riwayat peminjaman |
| reserve | Reservasi |
| fines | Denda |
| mst_location | Lokasi rak |
| visitor_count | Statistik pengunjung |
| stock_take | Stock opname |
| comment | Komentar |

### Kategori B: Opsional Branch ID (Bisa Shared)
| Tabel | Keterangan |
|-------|------------|
| mst_author | Pengarang (NULL = shared) |
| mst_publisher | Penerbit |
| mst_topic | Subjek |
| mst_gmd | GMD |
| mst_coll_type | Tipe koleksi |
| mst_language | Bahasa |
| content | Konten/berita |

### Kategori C: Global (Tanpa Branch ID)
| Tabel | Keterangan |
|-------|------------|
| setting | Konfigurasi global |
| group_access | Template permission |
| plugins | Plugin registry |

## 5. Index Strategy

```sql
-- Composite index untuk query umum
ALTER TABLE `biblio` ADD INDEX `idx_branch_title` (`branch_id`, `title`(100));
ALTER TABLE `item` ADD INDEX `idx_branch_biblio` (`branch_id`, `biblio_id`);
ALTER TABLE `member` ADD INDEX `idx_branch_name` (`branch_id`, `member_name`(100));
ALTER TABLE `loan` ADD INDEX `idx_branch_date` (`branch_id`, `loan_date`);
ALTER TABLE `loan` ADD INDEX `idx_branch_member` (`branch_id`, `member_id`);
```

## 6. Foreign Key Constraints

```sql
-- Tambahkan setelah semua data termigrasi
ALTER TABLE `biblio` 
  ADD CONSTRAINT `fk_biblio_branch` 
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`);

ALTER TABLE `item` 
  ADD CONSTRAINT `fk_item_branch` 
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`);

ALTER TABLE `member` 
  ADD CONSTRAINT `fk_member_branch` 
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`);

ALTER TABLE `loan` 
  ADD CONSTRAINT `fk_loan_branch` 
  FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`);
```

## 7. View untuk Reporting

```sql
-- View statistik per branch
CREATE VIEW `v_branch_statistics` AS
SELECT 
  b.branch_id,
  b.branch_code,
  b.branch_name,
  (SELECT COUNT(*) FROM biblio WHERE branch_id = b.branch_id) as total_titles,
  (SELECT COUNT(*) FROM item WHERE branch_id = b.branch_id) as total_items,
  (SELECT COUNT(*) FROM member WHERE branch_id = b.branch_id) as total_members,
  (SELECT COUNT(*) FROM loan WHERE branch_id = b.branch_id) as active_loans,
  (SELECT COUNT(*) FROM loan WHERE branch_id = b.branch_id AND DATE(loan_date) = CURDATE()) as loans_today
FROM branches b
WHERE b.is_active = 1;

-- View union catalog
CREATE VIEW `v_union_catalog` AS
SELECT 
  bi.biblio_id,
  bi.title,
  bi.isbn_issn,
  bi.publish_year,
  br.branch_name,
  br.branch_code,
  (SELECT COUNT(*) FROM item i WHERE i.biblio_id = bi.biblio_id AND i.branch_id = bi.branch_id) as copies
FROM biblio bi
JOIN branches br ON br.branch_id = bi.branch_id
WHERE br.is_active = 1;
```
