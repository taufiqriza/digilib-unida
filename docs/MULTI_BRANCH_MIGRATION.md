# Panduan Migrasi dari Multiple SLiMS ke Multi-Branch Central

## 1. Overview Migrasi

### Kondisi Awal
- 10 instalasi SLiMS terpisah
- Masing-masing punya database sendiri
- Data tidak terhubung

### Target
- 1 SLiMS central dengan multi-branch
- Semua data terkonsolidasi
- Tetap terpisah per branch

## 2. Persiapan Sebelum Migrasi

### 2.1 Inventarisasi Database Source

Buat spreadsheet dengan informasi:

| No | Nama Perpustakaan | DB Host | DB Name | DB User | Versi SLiMS | Jumlah Biblio | Jumlah Member |
|----|-------------------|---------|---------|---------|-------------|---------------|---------------|
| 1 | Perpus Pusat | localhost | slims_pusat | root | 9.4.2 | 5000 | 1200 |
| 2 | Perpus Cabang A | localhost | slims_cabang_a | root | 9.3.1 | 2000 | 500 |
| ... | ... | ... | ... | ... | ... | ... | ... |

### 2.2 Backup Semua Database

```bash
# Script backup semua database source
#!/bin/bash

BACKUP_DIR="/backup/slims_migration_$(date +%Y%m%d)"
mkdir -p $BACKUP_DIR

# Database list
declare -A DATABASES=(
  ["slims_pusat"]="Perpustakaan Pusat"
  ["slims_cabang_a"]="Cabang A"
  ["slims_cabang_b"]="Cabang B"
  # ... tambahkan semua
)

for db in "${!DATABASES[@]}"; do
  echo "Backing up $db..."
  mysqldump -u root -p --single-transaction --routines --triggers \
    $db > "$BACKUP_DIR/${db}_backup.sql"
done

echo "Backup completed in $BACKUP_DIR"
```

### 2.3 Validasi Struktur Database

```sql
-- Jalankan di setiap database source untuk cek struktur
SELECT 
  TABLE_NAME,
  TABLE_ROWS,
  DATA_LENGTH,
  INDEX_LENGTH
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'nama_database_source'
ORDER BY TABLE_ROWS DESC;
```

## 3. Urutan Migrasi

### 3.1 Dependency Order

Migrasi harus mengikuti urutan dependency:

```
1. branches (buat dulu semua branch)
   ↓
2. Master Data (author, publisher, topic, gmd, dll)
   ↓
3. mst_location (per branch)
   ↓
4. member (per branch)
   ↓
5. biblio (per branch)
   ↓
6. item (per branch, depends on biblio)
   ↓
7. loan & loan_history (depends on member & item)
   ↓
8. reserve, fines (depends on member & biblio)
   ↓
9. visitor_count, comment, dll
```

### 3.2 Tabel Master Data Strategy

**Opsi A: Merge All (Recommended)**
- Gabungkan semua author/publisher/topic dari semua branch
- Deduplicate berdasarkan nama
- Set branch_id = NULL (shared)

**Opsi B: Keep Separate**
- Setiap branch punya master data sendiri
- Set branch_id sesuai branch

```sql
-- Contoh merge author dengan deduplicate
INSERT INTO central_db.mst_author (author_name, authority_type, branch_id)
SELECT DISTINCT author_name, authority_type, NULL
FROM source_db.mst_author
WHERE author_name NOT IN (SELECT author_name FROM central_db.mst_author);
```

## 4. Step-by-Step Migration

### Step 1: Setup Central Database

```sql
-- 1. Jalankan schema multi-branch
SOURCE /path/to/MULTI_BRANCH_ARCHITECTURE.sql;

-- 2. Buat semua branch
INSERT INTO branches (branch_code, branch_name, branch_address, is_active) VALUES
('PUSAT', 'Perpustakaan Pusat', 'Jl. Utama No. 1', 1),
('CAB-A', 'Perpustakaan Cabang A', 'Jl. Cabang A No. 10', 1),
('CAB-B', 'Perpustakaan Cabang B', 'Jl. Cabang B No. 20', 1),
-- ... dst untuk 10 branch
('CAB-J', 'Perpustakaan Cabang J', 'Jl. Cabang J No. 100', 1);
```

### Step 2: Migrasi Master Data

```sql
-- Migrasi GMD (biasanya sama di semua SLiMS)
INSERT INTO central_db.mst_gmd (gmd_code, gmd_name, icon_image, branch_id)
SELECT gmd_code, gmd_name, icon_image, NULL
FROM source_db_1.mst_gmd
ON DUPLICATE KEY UPDATE gmd_name = VALUES(gmd_name);

-- Migrasi Collection Type
INSERT INTO central_db.mst_coll_type (coll_type_name, branch_id)
SELECT DISTINCT coll_type_name, NULL
FROM source_db_1.mst_coll_type
WHERE coll_type_name NOT IN (SELECT coll_type_name FROM central_db.mst_coll_type);

-- Migrasi Author (merge dari semua source)
-- Jalankan untuk setiap source database
INSERT INTO central_db.mst_author (author_name, author_year, authority_type, auth_list, branch_id)
SELECT author_name, author_year, authority_type, auth_list, NULL
FROM source_db_1.mst_author a
WHERE NOT EXISTS (
  SELECT 1 FROM central_db.mst_author ca 
  WHERE LOWER(ca.author_name) = LOWER(a.author_name)
);
```

### Step 3: Migrasi Location per Branch

```sql
-- Untuk setiap branch, migrasi location dengan branch_id
-- Contoh untuk Branch ID = 2 (Cabang A)

INSERT INTO central_db.mst_location (location_id, location_name, branch_id)
SELECT location_id, location_name, 2
FROM slims_cabang_a.mst_location;
```

### Step 4: Migrasi Member

```sql
-- Migrasi member dengan ID mapping
-- Branch ID = 2 (Cabang A)

-- 4a. Insert member dengan ID baru
INSERT INTO central_db.member (
  member_id, member_name, gender, birth_date, member_type_id,
  member_email, member_address, postal_code, inst_name,
  member_image, pin, member_phone, member_fax,
  member_since_date, register_date, expire_date,
  member_notes, is_pending, is_new, branch_id
)
SELECT 
  NULL, -- auto increment ID baru
  member_name, gender, birth_date, member_type_id,
  member_email, member_address, postal_code, inst_name,
  member_image, pin, member_phone, member_fax,
  member_since_date, register_date, expire_date,
  member_notes, is_pending, is_new, 
  2 -- branch_id
FROM slims_cabang_a.member;

-- 4b. Simpan ID mapping
INSERT INTO central_db.id_mapping (import_log_id, branch_id, table_name, old_id, new_id)
SELECT 
  1, -- import_log_id
  2, -- branch_id
  'member',
  src.member_id,
  dst.member_id
FROM slims_cabang_a.member src
JOIN central_db.member dst ON dst.member_name = src.member_name 
  AND dst.branch_id = 2
  AND dst.register_date = src.register_date;
```

### Step 5: Migrasi Biblio

```sql
-- Migrasi biblio dengan relasi ke master data
-- Branch ID = 2 (Cabang A)

INSERT INTO central_db.biblio (
  biblio_id, branch_id, title, sor, edition, isbn_issn,
  publisher_id, publish_year, collation, series_title,
  call_number, language_id, source, publish_place_id,
  classification, notes, image, file_att, opac_hide,
  promoted, labels, frequency_id, spec_detail_info,
  content_type_id, media_type_id, carrier_type_id,
  input_date, last_update, uid
)
SELECT 
  NULL, -- auto increment
  2, -- branch_id
  title, sor, edition, isbn_issn,
  -- Map publisher_id ke central
  (SELECT publisher_id FROM central_db.mst_publisher 
   WHERE publisher_name = (SELECT publisher_name FROM slims_cabang_a.mst_publisher WHERE publisher_id = src.publisher_id)),
  publish_year, collation, series_title,
  call_number, language_id, source, publish_place_id,
  classification, notes, image, file_att, opac_hide,
  promoted, labels, frequency_id, spec_detail_info,
  content_type_id, media_type_id, carrier_type_id,
  input_date, last_update, uid
FROM slims_cabang_a.biblio src;

-- Simpan ID mapping untuk biblio
INSERT INTO central_db.id_mapping (import_log_id, branch_id, table_name, old_id, new_id)
SELECT 
  1, 2, 'biblio', src.biblio_id, dst.biblio_id
FROM slims_cabang_a.biblio src
JOIN central_db.biblio dst ON dst.title = src.title 
  AND dst.branch_id = 2 
  AND dst.isbn_issn = src.isbn_issn;
```

### Step 6: Migrasi Biblio Relations

```sql
-- Migrasi biblio_author dengan ID mapping
INSERT INTO central_db.biblio_author (biblio_id, author_id, level, branch_id)
SELECT 
  (SELECT new_id FROM central_db.id_mapping WHERE branch_id = 2 AND table_name = 'biblio' AND old_id = ba.biblio_id),
  (SELECT author_id FROM central_db.mst_author WHERE author_name = 
    (SELECT author_name FROM slims_cabang_a.mst_author WHERE author_id = ba.author_id) LIMIT 1),
  ba.level,
  2
FROM slims_cabang_a.biblio_author ba;

-- Migrasi biblio_topic
INSERT INTO central_db.biblio_topic (biblio_id, topic_id, level, branch_id)
SELECT 
  (SELECT new_id FROM central_db.id_mapping WHERE branch_id = 2 AND table_name = 'biblio' AND old_id = bt.biblio_id),
  (SELECT topic_id FROM central_db.mst_topic WHERE topic = 
    (SELECT topic FROM slims_cabang_a.mst_topic WHERE topic_id = bt.topic_id) LIMIT 1),
  bt.level,
  2
FROM slims_cabang_a.biblio_topic bt;
```

### Step 7: Migrasi Item

```sql
-- Migrasi item dengan mapping biblio_id
INSERT INTO central_db.item (
  item_id, branch_id, biblio_id, call_number, coll_type_id,
  item_code, inventory_code, received_date, supplier_id,
  order_no, location_id, order_date, item_status_id,
  site, source, invoice, price, price_currency,
  invoice_date, input_date, last_update, uid
)
SELECT 
  NULL,
  2, -- branch_id
  (SELECT new_id FROM central_db.id_mapping WHERE branch_id = 2 AND table_name = 'biblio' AND old_id = src.biblio_id),
  call_number, coll_type_id, item_code, inventory_code,
  received_date, supplier_id, order_no, location_id,
  order_date, item_status_id, site, source, invoice,
  price, price_currency, invoice_date, input_date, last_update, uid
FROM slims_cabang_a.item src;
```

### Step 8: Migrasi Loan & History

```sql
-- Migrasi loan aktif
INSERT INTO central_db.loan (
  loan_id, branch_id, item_code, member_id, loan_date,
  due_date, renewed, is_lent, is_return, return_date, input_date, uid
)
SELECT 
  NULL,
  2,
  item_code,
  (SELECT new_id FROM central_db.id_mapping WHERE branch_id = 2 AND table_name = 'member' AND old_id = src.member_id),
  loan_date, due_date, renewed, is_lent, is_return, return_date, input_date, uid
FROM slims_cabang_a.loan src;

-- Migrasi loan_history
INSERT INTO central_db.loan_history (
  loan_history_id, branch_id, item_code, biblio_id, title,
  call_number, classification, gmd_name, language_name,
  location_name, coll_type_name, member_id, member_name,
  member_type_name, loan_date, due_date, renewed, is_lent,
  is_return, return_date, input_date, uid
)
SELECT 
  NULL, 2, item_code,
  (SELECT new_id FROM central_db.id_mapping WHERE branch_id = 2 AND table_name = 'biblio' AND old_id = src.biblio_id),
  title, call_number, classification, gmd_name, language_name,
  location_name, coll_type_name,
  (SELECT new_id FROM central_db.id_mapping WHERE branch_id = 2 AND table_name = 'member' AND old_id = src.member_id),
  member_name, member_type_name, loan_date, due_date, renewed,
  is_lent, is_return, return_date, input_date, uid
FROM slims_cabang_a.loan_history src;
```

## 5. Validasi Post-Migration

### 5.1 Count Validation

```sql
-- Bandingkan jumlah record source vs destination
SELECT 
  'biblio' as table_name,
  (SELECT COUNT(*) FROM slims_cabang_a.biblio) as source_count,
  (SELECT COUNT(*) FROM central_db.biblio WHERE branch_id = 2) as dest_count,
  (SELECT COUNT(*) FROM slims_cabang_a.biblio) - 
  (SELECT COUNT(*) FROM central_db.biblio WHERE branch_id = 2) as diff
UNION ALL
SELECT 
  'item',
  (SELECT COUNT(*) FROM slims_cabang_a.item),
  (SELECT COUNT(*) FROM central_db.item WHERE branch_id = 2),
  (SELECT COUNT(*) FROM slims_cabang_a.item) - 
  (SELECT COUNT(*) FROM central_db.item WHERE branch_id = 2)
UNION ALL
SELECT 
  'member',
  (SELECT COUNT(*) FROM slims_cabang_a.member),
  (SELECT COUNT(*) FROM central_db.member WHERE branch_id = 2),
  (SELECT COUNT(*) FROM slims_cabang_a.member) - 
  (SELECT COUNT(*) FROM central_db.member WHERE branch_id = 2);
```

### 5.2 Integrity Check

```sql
-- Cek orphan records
SELECT 'Orphan items (no biblio)' as issue, COUNT(*) as count
FROM central_db.item i
WHERE NOT EXISTS (SELECT 1 FROM central_db.biblio b WHERE b.biblio_id = i.biblio_id)

UNION ALL

SELECT 'Orphan loans (no member)', COUNT(*)
FROM central_db.loan l
WHERE NOT EXISTS (SELECT 1 FROM central_db.member m WHERE m.member_id = l.member_id)

UNION ALL

SELECT 'Orphan biblio_author (no biblio)', COUNT(*)
FROM central_db.biblio_author ba
WHERE NOT EXISTS (SELECT 1 FROM central_db.biblio b WHERE b.biblio_id = ba.biblio_id);
```

### 5.3 Sample Data Check

```sql
-- Cek sample data untuk verifikasi manual
SELECT b.biblio_id, b.title, b.branch_id, br.branch_name,
  (SELECT COUNT(*) FROM item i WHERE i.biblio_id = b.biblio_id) as item_count
FROM biblio b
JOIN branches br ON br.branch_id = b.branch_id
ORDER BY RAND()
LIMIT 10;
```

## 6. Rollback Procedure

Jika migrasi gagal:

```sql
-- Rollback per branch
DELETE FROM loan WHERE branch_id = 2;
DELETE FROM loan_history WHERE branch_id = 2;
DELETE FROM item WHERE branch_id = 2;
DELETE FROM biblio_author WHERE branch_id = 2;
DELETE FROM biblio_topic WHERE branch_id = 2;
DELETE FROM biblio WHERE branch_id = 2;
DELETE FROM member WHERE branch_id = 2;
DELETE FROM mst_location WHERE branch_id = 2;
DELETE FROM id_mapping WHERE branch_id = 2;
DELETE FROM import_logs WHERE branch_id = 2;
```

## 7. Checklist Migrasi per Branch

```
□ Backup database source
□ Buat entry di tabel branches
□ Migrasi mst_location
□ Migrasi member + ID mapping
□ Migrasi biblio + ID mapping
□ Migrasi biblio_author
□ Migrasi biblio_topic
□ Migrasi item + ID mapping
□ Migrasi loan
□ Migrasi loan_history
□ Migrasi reserve
□ Migrasi fines
□ Migrasi visitor_count
□ Validasi count
□ Validasi integrity
□ Sample check
□ Update import_logs status
```

## 8. Timeline Migrasi 10 Branch

| Hari | Aktivitas |
|------|-----------|
| 1 | Setup central DB, migrasi Branch 1 (Pusat) |
| 2 | Migrasi Branch 2-3 |
| 3 | Migrasi Branch 4-5 |
| 4 | Migrasi Branch 6-7 |
| 5 | Migrasi Branch 8-10 |
| 6-7 | Validasi & fixing |
