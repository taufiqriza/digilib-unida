# Multi-Branch Admin Guide

## 1. User Roles & Permissions

### 1.1 Hierarki User

| Role | Scope | Capabilities |
|------|-------|--------------|
| **Super Admin** | All Branches | Full control, manage branches, view all data |
| **Branch Admin** | Single Branch | Full control within branch, manage staff |
| **Librarian** | Single Branch | Catalog, members, circulation |
| **Staff** | Single Branch | Circulation only |
| **Counter** | Single Branch | Circulation counter only |

### 1.2 Permission Matrix

```
┌─────────────────────┬───────┬────────┬───────────┬───────┬─────────┐
│ Feature             │ Super │ Branch │ Librarian │ Staff │ Counter │
├─────────────────────┼───────┼────────┼───────────┼───────┼─────────┤
│ Manage Branches     │   ✓   │   ✗    │     ✗     │   ✗   │    ✗    │
│ Import Data         │   ✓   │   ✓    │     ✗     │   ✗   │    ✗    │
│ View All Branches   │   ✓   │   ✗    │     ✗     │   ✗   │    ✗    │
│ Branch Settings     │   ✓   │   ✓    │     ✗     │   ✗   │    ✗    │
│ Manage Staff        │   ✓   │   ✓    │     ✗     │   ✗   │    ✗    │
│ Bibliography        │   ✓   │   ✓    │     ✓     │   ✗   │    ✗    │
│ Membership          │   ✓   │   ✓    │     ✓     │   ✗   │    ✗    │
│ Circulation         │   ✓   │   ✓    │     ✓     │   ✓   │    ✓    │
│ Reports             │   ✓   │   ✓    │     ✓     │   ✗   │    ✗    │
│ Stock Take          │   ✓   │   ✓    │     ✓     │   ✗   │    ✗    │
└─────────────────────┴───────┴────────┴───────────┴───────┴─────────┘
```

## 2. Branch Management

### 2.1 Membuat Branch Baru

1. Login sebagai Super Admin
2. Menu: **System → Branch Management**
3. Klik **Add New Branch**
4. Isi form:
   - Branch Code: Kode unik (contoh: `CAB-A`)
   - Branch Name: Nama lengkap
   - Address, City, Phone, Email
   - Subdomain (opsional): untuk akses `cabang-a.perpus.com`
   - Logo: Upload logo branch
5. Klik **Save**

### 2.2 Konfigurasi Branch

Setiap branch bisa punya konfigurasi berbeda:

```json
{
  "library": {
    "name": "Perpustakaan Cabang A",
    "tagline": "Melayani dengan Sepenuh Hati"
  },
  "template": {
    "theme": "mylib",
    "primary_color": "#1a73e8"
  },
  "circulation": {
    "loan_limit": 5,
    "loan_period_days": 14,
    "fine_per_day": 1000,
    "allow_renewal": true,
    "max_renewal": 2
  },
  "membership": {
    "expire_period_days": 365,
    "require_approval": false,
    "allow_self_register": true
  },
  "features": {
    "enable_reservation": true,
    "enable_comment": true,
    "enable_rating": false
  }
}
```

### 2.3 Branch Selector (Super Admin)

Super Admin melihat dropdown di header untuk switch branch:

```
┌──────────────────────────────────────────────────────────┐
│ SLiMS Admin    [Branch: ▼ Semua Cabang        ]  [Logout]│
├──────────────────────────────────────────────────────────┤
│                         ┌─────────────────────┐          │
│                         │ ○ Semua Cabang      │          │
│                         │ ○ Perpus Pusat      │          │
│                         │ ○ Cabang A          │          │
│                         │ ○ Cabang B          │          │
│                         │ ...                 │          │
│                         └─────────────────────┘          │
└──────────────────────────────────────────────────────────┘
```

## 3. Import Data dari SLiMS Lama

### 3.1 Persiapan

1. Pastikan database source bisa diakses
2. Backup database source
3. Catat kredensial database

### 3.2 Langkah Import

1. **Menu**: System → Branch Management → Import Data
2. **Step 1**: Pilih target branch & source
3. **Step 2**: Preview data & pilih tabel
4. **Step 3**: Execute import
5. **Step 4**: Validasi hasil

### 3.3 Import via CLI (Alternatif)

```bash
# Full import dari database
php cli/import.php --source=slims_cabang_a --branch=2 --type=full

# Import tabel tertentu
php cli/import.php --source=slims_cabang_a --branch=2 --tables=biblio,item

# Import dari SQL file
php cli/import.php --file=/backup/slims_cabang_a.sql --branch=2

# Dry run (preview tanpa import)
php cli/import.php --source=slims_cabang_a --branch=2 --dry-run
```

### 3.4 Monitoring Import

```sql
-- Cek status import
SELECT * FROM import_logs ORDER BY log_id DESC LIMIT 10;

-- Cek progress per tabel
SELECT table_name, records_total, records_imported, records_failed
FROM import_logs WHERE log_id = ?;

-- Cek ID mapping
SELECT * FROM id_mapping WHERE branch_id = ? AND table_name = 'biblio' LIMIT 100;
```

## 4. Reporting Multi-Branch

### 4.1 Dashboard Super Admin

```
┌─────────────────────────────────────────────────────────────────┐
│                    DASHBOARD MULTI-BRANCH                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  TOTAL SEMUA CABANG                                             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐        │
│  │  15,234  │  │   3,456  │  │    892   │  │    45    │        │
│  │  Judul   │  │  Member  │  │Pinjam/hr │  │ Terlambat│        │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘        │
│                                                                  │
│  STATISTIK PER CABANG                                           │
│  ┌─────────────┬────────┬────────┬─────────┬──────────┐        │
│  │ Branch      │ Judul  │ Member │ Pinjam  │ Status   │        │
│  ├─────────────┼────────┼────────┼─────────┼──────────┤        │
│  │ Pusat       │  8,234 │  1,500 │   450   │ 🟢 Active│        │
│  │ Cabang A    │  4,000 │  1,200 │   280   │ 🟢 Active│        │
│  │ Cabang B    │  3,000 │    756 │   162   │ 🟢 Active│        │
│  └─────────────┴────────┴────────┴─────────┴──────────┘        │
│                                                                  │
│  [Chart: Trend Peminjaman per Branch - 30 hari terakhir]        │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### 4.2 Report Queries

```sql
-- Statistik per branch
SELECT 
  b.branch_name,
  COUNT(DISTINCT bib.biblio_id) as titles,
  COUNT(DISTINCT i.item_id) as items,
  COUNT(DISTINCT m.member_id) as members,
  COUNT(DISTINCT l.loan_id) as active_loans
FROM branches b
LEFT JOIN biblio bib ON bib.branch_id = b.branch_id
LEFT JOIN item i ON i.branch_id = b.branch_id
LEFT JOIN member m ON m.branch_id = b.branch_id
LEFT JOIN loan l ON l.branch_id = b.branch_id AND l.is_return = 0
WHERE b.is_active = 1
GROUP BY b.branch_id;

-- Top 10 buku terpinjam (semua branch)
SELECT 
  bib.title,
  br.branch_name,
  COUNT(*) as loan_count
FROM loan_history lh
JOIN biblio bib ON bib.biblio_id = lh.biblio_id
JOIN branches br ON br.branch_id = lh.branch_id
GROUP BY lh.biblio_id, lh.branch_id
ORDER BY loan_count DESC
LIMIT 10;
```

## 5. OPAC Multi-Branch

### 5.1 Akses OPAC per Branch

| Metode | URL | Keterangan |
|--------|-----|------------|
| Subdomain | `cabang-a.perpus.com` | Otomatis detect branch |
| Parameter | `perpus.com?branch=2` | Manual select |
| Path | `perpus.com/cabang-a/` | URL rewrite |

### 5.2 Union Catalog

Menampilkan katalog gabungan semua branch:

```
URL: perpus.com?union=1

┌─────────────────────────────────────────────────────────────┐
│                    KATALOG GABUNGAN                          │
│              Semua Perpustakaan dalam Jaringan              │
├─────────────────────────────────────────────────────────────┤
│ Search: [________________________] [🔍]                      │
│                                                              │
│ Filter Branch: [✓] Pusat [✓] Cabang A [✓] Cabang B          │
├─────────────────────────────────────────────────────────────┤
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 📚 Pemrograman PHP                                      │ │
│ │ Tersedia di: Pusat (3), Cabang A (2), Cabang B (1)     │ │
│ └─────────────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 📚 Database MySQL                                       │ │
│ │ Tersedia di: Pusat (2), Cabang A (1)                   │ │
│ └─────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### 5.3 Branch Switcher di OPAC

```html
<!-- Di header OPAC -->
<div class="branch-switcher">
  <label>Perpustakaan:</label>
  <select onchange="location.href='?branch='+this.value">
    <option value="all">Semua Cabang</option>
    <option value="1">Perpustakaan Pusat</option>
    <option value="2">Cabang A</option>
    <option value="3">Cabang B</option>
  </select>
</div>
```

## 6. Troubleshooting

### 6.1 Import Gagal

```sql
-- Cek error log
SELECT * FROM import_logs WHERE status = 'failed' ORDER BY log_id DESC;

-- Rollback import tertentu
DELETE FROM loan WHERE branch_id = ? AND input_date > '2024-01-01';
DELETE FROM item WHERE branch_id = ? AND input_date > '2024-01-01';
DELETE FROM biblio WHERE branch_id = ? AND input_date > '2024-01-01';
DELETE FROM member WHERE branch_id = ? AND register_date > '2024-01-01';
DELETE FROM id_mapping WHERE branch_id = ?;
```

### 6.2 Data Tidak Muncul

```sql
-- Cek branch_id pada data
SELECT branch_id, COUNT(*) FROM biblio GROUP BY branch_id;

-- Cek user branch access
SELECT u.username, uba.branch_id, b.branch_name
FROM user u
JOIN user_branch_access uba ON uba.user_id = u.user_id
JOIN branches b ON b.branch_id = uba.branch_id;
```

### 6.3 Performance Issues

```sql
-- Pastikan index ada
SHOW INDEX FROM biblio WHERE Key_name LIKE '%branch%';
SHOW INDEX FROM item WHERE Key_name LIKE '%branch%';
SHOW INDEX FROM member WHERE Key_name LIKE '%branch%';

-- Analyze table
ANALYZE TABLE biblio, item, member, loan;
```

## 7. Backup & Restore

### 7.1 Backup per Branch

```bash
# Backup data branch tertentu
mysqldump -u root -p slims_central \
  --where="branch_id=2" \
  biblio item member loan loan_history \
  > backup_branch_2.sql
```

### 7.2 Restore ke Branch Baru

```bash
# Restore dengan update branch_id
sed 's/branch_id=2/branch_id=5/g' backup_branch_2.sql > restore_branch_5.sql
mysql -u root -p slims_central < restore_branch_5.sql
```

## 8. Best Practices

1. **Backup rutin** sebelum import data baru
2. **Test di staging** sebelum import ke production
3. **Validasi count** setelah setiap import
4. **Monitor performance** setelah data bertambah
5. **Document** setiap perubahan konfigurasi branch
6. **Train staff** untuk memahami konsep multi-branch
