# Project Cleanup Log

**Date:** 2025-12-07
**Purpose:** Remove unused/junk files from SLiMS project

## Files Removed

### 1. Old Hotfix/Changelog MD Files (Root)
| File | Size | Reason |
|------|------|--------|
| FIX_EDIT_BUTTON_AND_INPUT_BY.md | 10.6 KB | Old hotfix doc |
| HOTFIX_TABLE_NOT_SHOWING.md | 6.6 KB | Old hotfix doc |
| FINAL_FIX_ALL_ISSUES.md | 7.3 KB | Old hotfix doc |
| ROLLBACK_TO_ORIGINAL.md | 6.1 KB | Old hotfix doc |
| FINAL_CLEAN_LAYOUT.md | 11.8 KB | Old hotfix doc |
| HOTFIX_V2_SIMPLIFIED.md | 7.2 KB | Old hotfix doc |
| PERBAIKAN_FINAL.md | 10.9 KB | Old hotfix doc |
| CHANGELOG_BIBLIOGRAPHY_UI.md | 11.7 KB | Old changelog |
| UPLOAD_INFO.md | 4 KB | Old info doc |
| task.md | 3.4 KB | Old task doc |

### 2. ZIP Backup Files
| File | Size | Reason |
|------|------|--------|
| admin.zip | 9.3 MB | Backup archive |
| lib.zip | 7.2 MB | Backup archive |
| template/mylib.zip | 18.9 MB | Backup archive |
| plugins/eddc_mtr.zip | 306 KB | Backup archive |
| plugins/mylib_theme.zip | 13.7 KB | Backup archive |

### 3. macOS Junk Files
| File | Reason |
|------|--------|
| .DS_Store | macOS metadata |
| template/.DS_Store | macOS metadata |
| plugins/.DS_Store | macOS metadata |

### 4. Old/Backup Files
| File | Reason |
|------|--------|
| js/updater.js.old | Old backup |

### 5. Empty/Unused Folders
| Folder | Reason |
|--------|--------|
| Plugin_e-DDC_(SLiMS_8)/ | Empty folder |

### 6. Dev Config Files
| File | Reason |
|------|--------|
| fiveserver.config.js | Dev server config |

## Total Space Freed
~36 MB

## Folders Removed (Phase 2)

| Folder | Reason |
|--------|--------|
| `.claude/` | Claude AI config, not used |
| `sample/` | ISIS sample data, not used |
| `m/` | Old mobile version, SLiMS 9 is responsive |

## Folders Kept
| Folder | Reason |
|--------|--------|
| `public/staff_attendance/` | Custom module, may be in use |
| `repository/` | File attachments storage |
| `indexing_engine/` | Elasticsearch/Solr config |

## Notes
- All important documentation moved to `/docs/` folder
- ZIP files should be stored externally if needed for backup
- Add `.DS_Store` to `.gitignore` to prevent future commits
