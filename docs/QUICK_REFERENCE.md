# Quick Reference - Multi-Branch Migration

## Checklist Migrasi Cepat

### Persiapan (1x)
```bash
# 1. Backup database central
mysqldump -u root -p slims_central > backup_central_$(date +%Y%m%d).sql

# 2. Jalankan migration script
mysql -u root -p slims_central < docs/sql/multi_branch_migration.sql

# 3. Verify
mysql -u root -p slims_central -e "SELECT * FROM branches;"
```

### Per Branch (ulangi untuk 10 branch)

```sql
-- 1. Buat branch baru
INSERT INTO branches (branch_code, branch_name, branch_address) 
VALUES ('CAB-X', 'Nama Perpustakaan', 'Alamat');

-- Catat branch_id yang dihasilkan (misal: 2)
```

```bash
# 2. Import data
php admin/modules/branch_management/cli/import.php \
  --source-host=localhost \
  --source-db=slims_cabang_x \
  --source-user=root \
  --source-pass=password \
  --target-branch=2
```

```sql
-- 3. Validasi
SELECT 
  (SELECT COUNT(*) FROM biblio WHERE branch_id = 2) as biblio,
  (SELECT COUNT(*) FROM item WHERE branch_id = 2) as item,
  (SELECT COUNT(*) FROM member WHERE branch_id = 2) as member;
```

## Command Cheatsheet

### Database Operations

```sql
-- Lihat semua branch
SELECT branch_id, branch_code, branch_name, is_active FROM branches;

-- Statistik per branch
SELECT * FROM v_branch_statistics;

-- Cek import logs
SELECT log_id, branch_id, status, records_imported, error_message 
FROM import_logs ORDER BY log_id DESC LIMIT 5;

-- Cek ID mapping
SELECT table_name, COUNT(*) as mapped 
FROM id_mapping WHERE branch_id = ? GROUP BY table_name;

-- Hapus data branch (HATI-HATI!)
DELETE FROM loan WHERE branch_id = ?;
DELETE FROM item WHERE branch_id = ?;
DELETE FROM biblio_author WHERE branch_id = ?;
DELETE FROM biblio_topic WHERE branch_id = ?;
DELETE FROM biblio WHERE branch_id = ?;
DELETE FROM member WHERE branch_id = ?;
DELETE FROM id_mapping WHERE branch_id = ?;
```

### File Locations

```
docs/
├── MULTI_BRANCH_OVERVIEW.md      # Overview sistem
├── MULTI_BRANCH_ARCHITECTURE.md  # Database schema
├── MULTI_BRANCH_MIGRATION.md     # Panduan migrasi detail
├── MULTI_BRANCH_IMPORT_TOOL.md   # Dokumentasi import tool
├── MULTI_BRANCH_ADMIN_GUIDE.md   # Panduan admin
├── QUICK_REFERENCE.md            # File ini
└── sql/
    └── multi_branch_migration.sql # SQL migration script
```

## Urutan Import (Dependency)

```
1. branches        ← Buat dulu
2. mst_* (shared)  ← Master data global
3. mst_location    ← Per branch
4. member          ← Per branch
5. biblio          ← Per branch
6. biblio_author   ← Depends on biblio
7. biblio_topic    ← Depends on biblio
8. item            ← Depends on biblio
9. loan            ← Depends on member, item
10. loan_history   ← Depends on member, biblio
```

## Troubleshooting Cepat

| Problem | Solution |
|---------|----------|
| Import gagal di tengah | Cek `import_logs`, rollback dengan DELETE per branch |
| Data tidak muncul | Cek `branch_id` pada data, cek user branch access |
| Duplicate entry | Gunakan conflict mode 'skip' atau 'update' |
| Foreign key error | Pastikan urutan import benar |
| Performance lambat | Cek index, jalankan `ANALYZE TABLE` |

## Kontak & Support

- Dokumentasi: `/docs/`
- Log files: `/files/logs/`
- Import logs: Tabel `import_logs`
