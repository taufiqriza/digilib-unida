# SLiMS Multi-Branch System Documentation

## Overview

Dokumentasi lengkap untuk mengubah SLiMS menjadi sistem multi-branch terpusat dengan fitur migrasi dari 10+ database SLiMS terpisah.

## Daftar Dokumentasi

| File | Deskripsi |
|------|-----------|
| [MULTI_BRANCH_ARCHITECTURE.md](MULTI_BRANCH_ARCHITECTURE.md) | Arsitektur & database schema |
| [MULTI_BRANCH_MIGRATION.md](MULTI_BRANCH_MIGRATION.md) | Panduan migrasi dari SLiMS terpisah |
| [MULTI_BRANCH_IMPORT_TOOL.md](MULTI_BRANCH_IMPORT_TOOL.md) | Tool import data & penggunaan |
| [MULTI_BRANCH_ADMIN_GUIDE.md](MULTI_BRANCH_ADMIN_GUIDE.md) | Panduan admin & konfigurasi |

## Quick Start

### Kondisi Saat Ini
- 10 instalasi SLiMS terpisah
- Masing-masing punya database sendiri
- Perlu dikonsolidasi ke 1 sistem terpusat

### Target
- 1 SLiMS terpusat dengan multi-branch
- Data terpisah per branch (isolasi)
- Super Admin bisa kontrol semua
- Tool migrasi untuk import dari SLiMS lama

## Arsitektur Singkat

```
┌─────────────────────────────────────────────────────┐
│              SLiMS MULTI-BRANCH CENTRAL             │
│                   (1 Database)                       │
├─────────────────────────────────────────────────────┤
│  Branch 1  │  Branch 2  │  Branch 3  │  ... │ B.10 │
│  (Pusat)   │ (Cabang A) │ (Cabang B) │      │      │
└─────────────────────────────────────────────────────┘
        ▲           ▲           ▲
        │           │           │
   [IMPORT]    [IMPORT]    [IMPORT]
        │           │           │
┌───────┴───┐ ┌─────┴─────┐ ┌───┴───────┐
│ SLiMS #1  │ │  SLiMS #2 │ │  SLiMS #3 │  ... (10 SLiMS lama)
│ (DB lama) │ │ (DB lama) │ │ (DB lama) │
└───────────┘ └───────────┘ └───────────┘
```

## Fitur Import Tool

1. **Database-to-Database Import** - Langsung dari MySQL source
2. **SQL File Import** - Dari file dump .sql
3. **CSV Import** - Untuk data tertentu
4. **Incremental Import** - Update data yang sudah ada
5. **Conflict Resolution** - Handle duplicate data
6. **Rollback Support** - Batalkan jika error

## Timeline Implementasi

| Phase | Durasi | Aktivitas |
|-------|--------|-----------|
| 1 | 1 minggu | Setup database schema multi-branch |
| 2 | 1 minggu | Develop import tool |
| 3 | 2 minggu | Migrasi 10 database |
| 4 | 1 minggu | Testing & validation |
| 5 | 1 minggu | Go-live & monitoring |

**Total: 6 minggu**
