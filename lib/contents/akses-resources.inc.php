<?php
/**
 * Digital Resources Access Page
 * Halaman untuk menampilkan daftar e-book dan jurnal digital
 *
 * Copyright (C) 2025
 * This page provides access to various digital library resources
 */

// key to authenticate
if (!defined('INDEX_AUTH')) {
    die("Direct access not permitted");
}

// Page title
$page_title = '';

// Resources data
$resources = [
    [
        "title" => "Shamela Library",
        "url" => "https://shamela.ws/",
        "type" => "ebook",
        "description" => "Perpustakaan digital Islam klasik",
        "notes" => ""
    ],
    [
        "title" => "Perpustakaan Islam Digital",
        "url" => "https://perpustakaanislamdigital.com/",
        "type" => "ebook",
        "description" => "Koleksi buku-buku Islam digital",
        "notes" => ""
    ],
    [
        "title" => "Rumah Fiqih - PDF",
        "url" => "https://www.rumahfiqih.com/",
        "type" => "ebook",
        "description" => "Kumpulan PDF buku-buku fiqih",
        "notes" => ""
    ],
    [
        "title" => "Harvard DASH",
        "url" => "https://dash.harvard.edu/",
        "type" => "ebook",
        "description" => "Digital Access to Scholarship at Harvard",
        "notes" => ""
    ],
    [
        "title" => "Waqfeya",
        "url" => "https://waqfeya.net/index.php",
        "type" => "ebook",
        "description" => "Perpustakaan digital berbahasa Arab",
        "notes" => ""
    ],
    [
        "title" => "ManyBooks",
        "url" => "https://manybooks.net/",
        "type" => "ebook",
        "description" => "Free e-books collection",
        "notes" => ""
    ],
    [
        "title" => "Noor-Book",
        "url" => "https://www.noor-book.com/en/",
        "type" => "ebook",
        "description" => "Digital library in multiple languages",
        "notes" => ""
    ],
    [
        "title" => "PDF Books World",
        "url" => "https://www.pdfbooksworld.com/",
        "type" => "ebook",
        "description" => "Classic literature and academic books",
        "notes" => ""
    ],
    [
        "title" => "Open Library",
        "url" => "https://openlibrary.org/",
        "type" => "ebook",
        "description" => "Internet Archive's open library",
        "notes" => ""
    ],
    [
        "title" => "PDF Drive",
        "url" => "https://www.pdfdrive.com/",
        "type" => "ebook",
        "description" => "Search engine for PDF files",
        "notes" => ""
    ],
    [
        "title" => "NYU Arabic Collections Online",
        "url" => "http://dlib.nyu.edu/aco/",
        "type" => "ebook",
        "description" => "Arabic digital collections from NYU",
        "notes" => ""
    ],
    [
        "title" => "Gale (Teknik)",
        "url" => "https://link.gale.com/apps/SPJ.SP01?u=idfpptij",
        "type" => "database",
        "description" => "Jurnal teknik & sains dari Gale",
        "notes" => "Use the credentials below"
    ],
    [
        "title" => "Gale (Humaniora)",
        "url" => "https://link.gale.com/apps/SPJ.SP02?u=fpptijwt",
        "type" => "database",
        "description" => "Referensi humaniora dan sosial",
        "notes" => "Use the credentials below"
    ],
    [
        "title" => "ProQuest (Login)",
        "url" => "https://www.proquest.com/login",
        "type" => "journal",
        "description" => "Academic journals and dissertations",
        "notes" => "Use the credentials below"
    ],
    // resources list ends here
];

$credentials = [
    [
        'title' => 'Gale (Teknik & Humaniora)',
        'description' => 'Koleksi jurnal teknik dan humaniora dari Gale Academic OneFile.',
        'username' => 'UnivKanB',
        'password' => 'FPPTIjatim@1'
    ],
    [
        'title' => 'ProQuest (Ekonomi & Kesehatan)',
        'description' => 'Jurnal ekonomi, bisnis, dan kesehatan melalui portal ProQuest.',
        'username' => 'UDarussalam',
        'password' => 'FPPTIjatim@1'
    ]
];

// Convert to JSON for JavaScript
$resources_json = json_encode($resources, JSON_HEX_APOS | JSON_HEX_QUOT);

// Set page title
$opac->page_title = '';
?>

<!-- Digital Resources Access Page -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<div class="digital-resources-wrapper">
    <!-- Hero Section - Compact -->
    <div class="resources-hero">
        <div class="hero-content">
            <svg class="hero-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
            </svg>
            <div>
                <h1 class="hero-title"><?php echo __('Digital Resources Access'); ?></h1>
            </div>
        </div>
    </div>

    <!-- Mobile Toggle -->
    <button type="button" class="controls-toggle" id="controlsToggleBtn" aria-expanded="false">
        <span class="toggle-label">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="toggle-icon">
                <path d="M4 6h16"></path>
                <path d="M6 12h12"></path>
                <path d="M10 18h4"></path>
            </svg>
            <span><?php echo __('Filter & Search'); ?></span>
        </span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="toggle-caret">
            <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
    </button>

    <!-- Control Bar -->
    <div class="resources-controls is-open" id="resourcesControls">
        <div class="control-group">
            <div class="search-box">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <input type="text" id="searchInput" placeholder="Cari sumber resource..." class="search-input">
            </div>
        </div>

        <div class="control-group">
            <div class="filter-group">
                <label class="filter-label">Filter:</label>
                <select id="filterType" class="filter-select">
                    <option value="all">Semua Tipe</option>
                    <option value="ebook">E-Book</option>
                    <option value="journal">Journal</option>
                    <option value="database">Database</option>
                    <option value="other">Lainnya</option>
                </select>
            </div>

            <div class="sort-group">
                <label class="sort-label">Urutan:</label>
                <select id="sortOrder" class="sort-select">
                    <option value="asc">A-Z</option>
                    <option value="desc">Z-A</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Resources Grid -->
    <div class="resources-grid" id="resourcesGrid">
        <!-- Will be populated by JavaScript -->
    </div>

    <!-- Info Section -->
    <div class="info-section">
        <div class="info-card">
            <svg class="info-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <div class="info-content">
                <h3 class="info-title">Informasi Akses</h3>
                <p class="info-text">Beberapa sumber memerlukan akses institusi. Pastikan Anda terhubung melalui jaringan institusi atau menggunakan VPN institusi untuk mengakses database berbayar.</p>
            </div>
        </div>
    </div>

    <div class="credentials-section">
        <div class="credentials-header">
            <div>
                <h2><?php echo __('Kredensial Database'); ?></h2>
                <p><?php echo __('Gunakan akun berikut untuk mengakses database berlangganan.'); ?></p>
            </div>
            <small><?php echo __('Jaga kerahasiaan kredensial ini.'); ?></small>
        </div>
        <div class="credential-grid">
            <?php foreach ($credentials as $cred): ?>
                <div class="credential-card">
                    <div class="credential-card__icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="credential-card__body">
                        <h3><?php echo htmlspecialchars($cred['title']); ?></h3>
                        <p><?php echo htmlspecialchars($cred['description']); ?></p>
                        <div class="credential-row">
                            <span><?php echo __('Username'); ?></span>
                            <div class="credential-value" data-value="<?php echo htmlspecialchars($cred['username']); ?>">••••••</div>
                            <div class="credential-actions">
                                <button class="reveal-btn" type="button"><?php echo __('Reveal'); ?></button>
                                <button class="copy-btn" type="button" data-label="<?php echo __('Username'); ?>"><?php echo __('Copy'); ?></button>
                            </div>
                        </div>
                        <div class="credential-row">
                            <span><?php echo __('Password'); ?></span>
                            <div class="credential-value" data-value="<?php echo htmlspecialchars($cred['password']); ?>">••••••</div>
                            <div class="credential-actions">
                                <button class="reveal-btn" type="button"><?php echo __('Reveal'); ?></button>
                                <button class="copy-btn" type="button" data-label="<?php echo __('Password'); ?>"><?php echo __('Copy'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
/* Modern Premium Design - Digital Resources */
.digital-resources-wrapper {
    max-width: 1300px;
    margin: 0 auto;
    padding: 24px 16px 48px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

/* Hero Section - Compact */
.resources-hero {
    margin-bottom: 28px;
    padding: 28px 24px;
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    border-radius: 16px;
    color: white;
    box-shadow: 0 8px 24px rgba(37, 99, 235, 0.3);
}

.hero-content {
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    gap: 20px;
    max-width: 800px;
    margin: 0 auto;
}

.hero-icon {
    width: 48px;
    height: 48px;
    opacity: 0.95;
    flex-shrink: 0;
}

.hero-title {
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 4px 0;
    letter-spacing: -0.3px;
}

.hero-subtitle {
    font-size: 15px;
    opacity: 0.9;
    margin: 0;
    font-weight: 400;
}

/* Controls */
.resources-controls {
    display: flex;
    gap: 20px;
    margin-bottom: 28px;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
}

.controls-toggle {
    display: none;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    padding: 12px 18px;
    margin-bottom: 16px;
    border: 2px solid #dbeafe;
    border-radius: 14px;
    background: #f8fbff;
    color: #1e3a8a;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.controls-toggle:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.controls-toggle .toggle-label {
    display: flex;
    align-items: center;
    gap: 10px;
}

.controls-toggle .toggle-icon {
    width: 18px;
    height: 18px;
}

.controls-toggle .toggle-caret {
    width: 18px;
    height: 18px;
    transition: transform 0.2s ease;
}

.controls-toggle.is-active .toggle-caret {
    transform: rotate(180deg);
}

.control-group {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
}

.search-box {
    position: relative;
    flex: 1;
    min-width: 280px;
}

.search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    color: #9ca3af;
    pointer-events: none;
}

.search-input {
    width: 100%;
    padding: 14px 16px 14px 48px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: white;
}

.search-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.filter-group, .sort-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-label, .sort-label {
    font-size: 14px;
    font-weight: 600;
    color: #4b5563;
}

.filter-select, .sort-select {
    padding: 10px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-select:hover, .sort-select:hover {
    border-color: #667eea;
}

.filter-select:focus, .sort-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Resources Grid */
.resources-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.resource-card {
    background: #f0f6ff;
    border-radius: 18px;
    padding: 24px;
    box-shadow: 0 10px 24px rgba(37, 99, 235, 0.12);
    transition: all 0.3s ease;
    border: 2px solid #d7e3ff;
    position: relative;
    overflow: hidden;
}

.resource-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 12px;
    right: 12px;
    height: 4px;
    border-radius: 999px;
    background: linear-gradient(90deg, #3b82f6, #0ea5e9);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.resource-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 38px rgba(37, 99, 235, 0.25);
    border-color: #3b82f6;
}

.resource-card:hover::before {
    transform: scaleX(1);
}

.resource-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
}

.resource-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #2563eb 0%, #0ea5e9 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.25);
}

.resource-icon svg {
    width: 24px;
    height: 24px;
    color: white;
}

.resource-info {
    flex: 1;
    min-width: 0;
}

.resource-title {
    font-size: 18px;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 6px 0;
    line-height: 1.3;
}

.resource-type {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.type-ebook {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.type-journal {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

.type-database {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.type-other {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    color: white;
}

.resource-description {
    color: #6b7280;
    font-size: 14px;
    margin: 0 0 12px 0;
    line-height: 1.6;
}

.resource-notes {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: #fef3c7;
    border-left: 3px solid #f59e0b;
    border-radius: 6px;
    margin-bottom: 16px;
}

.resource-notes svg {
    width: 16px;
    height: 16px;
    color: #d97706;
    flex-shrink: 0;
}

.resource-notes-text {
    font-size: 13px;
    color: #92400e;
    margin: 0;
    font-weight: 500;
}

.resource-actions {
    display: flex;
    gap: 8px;
}

.btn-resource {
    flex: 1;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 8px 18px rgba(37, 99, 235, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.4);
}

.credentials-section {
    margin-top: 48px;
    background: #e0ecff;
    border-radius: 20px;
    padding: 28px;
    border: 1px solid #c7d7ff;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);
}

.credentials-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}

.credentials-header h2 {
    margin: 0;
    font-size: 22px;
    color: #1e3a8a;
}

.credentials-header p {
    margin: 6px 0 0;
    color: #475569;
}

.credential-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 18px;
}

.credential-card {
    display: flex;
    gap: 16px;
    padding: 18px 20px;
    border-radius: 16px;
    background: #fff;
    border: 1px solid #dbeafe;
    box-shadow: 0 8px 16px rgba(15, 23, 42, 0.12);
}

.credential-card__icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, #0284c7, #0ea5e9);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.credential-card__body h3 {
    margin: 0 0 4px;
    font-size: 18px;
    color: #0f172a;
}

.credential-card__body p {
    margin: 0 0 12px;
    color: #475569;
    font-size: 14px;
}

.credential-row {
    display: grid;
    grid-template-columns: 90px 1fr auto;
    gap: 8px;
    align-items: center;
    padding: 8px 0;
    border-top: 1px solid #e2e8f0;
}

.credential-row:first-of-type {
    border-top: none;
}

.credential-row span {
    font-size: 11px;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: #94a3b8;
}

.credential-value {
    font-weight: 600;
    font-size: 16px;
    color: #1d4ed8;
    letter-spacing: 0.08em;
}

.credential-actions {
    display: flex;
    gap: 6px;
}

.credential-actions button {
    border: none;
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 12px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s ease;
}

.reveal-btn {
    background: #dbeafe;
    color: #1e40af;
}

.copy-btn {
    background: #bbf7d0;
    color: #065f46;
}

.credential-actions button:hover {
    filter: brightness(0.95);
}

.btn-primary svg {
    width: 16px;
    height: 16px;
}


/* Info Section */
.info-section {
    margin-top: 48px;
}

.info-card {
    display: flex;
    gap: 20px;
    padding: 24px;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-left: 4px solid #3b82f6;
    border-radius: 16px;
    box-shadow: 0 12px 28px rgba(59, 130, 246, 0.18);
    align-items: center;
}

.info-icon {
    width: 36px;
    height: 36px;
    color: #2563eb;
    flex-shrink: 0;
}

.info-content {
    flex: 1;
}

.info-title {
    font-size: 18px;
    font-weight: 700;
    color: #1e40af;
    margin: 0 0 8px 0;
}

.info-text {
    font-size: 15px;
    color: #1e3a8a;
    margin: 0;
    line-height: 1.6;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 80px 20px;
    grid-column: 1 / -1;
}

.empty-state svg {
    width: 80px;
    height: 80px;
    color: #d1d5db;
    margin-bottom: 16px;
}

.empty-state-text {
    font-size: 18px;
    color: #6b7280;
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .digital-resources-wrapper {
        padding: 14px 10px 28px;
    }

    .resources-hero {
        padding: 22px 16px;
        margin-bottom: 18px;
    }

    .hero-content {
        flex-direction: row;
        gap: 14px;
        justify-content: flex-start;
        text-align: left;
        max-width: 100%;
        margin: 0;
    }

    .hero-icon {
        width: 40px;
        height: 40px;
    }

    .hero-title {
        font-size: 22px;
        line-height: 1.25;
    }

    .controls-toggle {
        display: flex;
    }

    .resources-controls {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }

    body.js-controls-ready .resources-controls {
        display: none;
    }

    body.js-controls-ready .resources-controls.is-open {
        display: flex;
    }

    .control-group {
        width: 100%;
        gap: 12px;
    }

    .search-box {
        min-width: 100%;
    }

    .filter-group,
    .sort-group {
        flex-direction: column;
        align-items: flex-start;
        width: 100%;
        gap: 6px;
    }

    .filter-select,
    .sort-select {
        width: 100%;
    }

    .resources-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .resource-card {
        padding: 18px;
    }

    .resource-actions .btn-resource {
        width: 100%;
        justify-content: center;
    }

    .credential-grid {
        grid-template-columns: 1fr;
    }

    .credential-card {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .credential-row {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .credential-actions {
        justify-content: center;
    }

    .info-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<script>
// Resources data
const resources = <?php echo $resources_json; ?>;

// State
let filteredResources = [...resources];

// Icons SVG
const icons = {
    ebook: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>',
    journal: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
    database: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>',
    other: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>',
    external: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>',
    alert: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>'
};

// Render resources
function renderResources() {
    const grid = document.getElementById('resourcesGrid');

    if (filteredResources.length === 0) {
        grid.innerHTML = `
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <p class="empty-state-text">Tidak ada resource yang ditemukan</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = filteredResources.map(resource => {
        const typeClass = `type-${resource.type}`;
        const typeLabel = {
            'ebook': 'E-Book',
            'journal': 'Journal',
            'database': 'Database',
            'other': 'Lainnya'
        }[resource.type] || resource.type;

        return `
            <div class="resource-card" data-type="${resource.type}">
                <div class="resource-header">
                    <div class="resource-icon">
                        ${icons[resource.type] || icons.other}
                    </div>
                    <div class="resource-info">
                        <h3 class="resource-title">${resource.title}</h3>
                        <span class="resource-type ${typeClass}">${typeLabel}</span>
                    </div>
                </div>

                ${resource.description ? `<p class="resource-description">${resource.description}</p>` : ''}

                ${resource.notes ? `
                    <div class="resource-notes">
                        ${icons.alert}
                        <p class="resource-notes-text">${resource.notes}</p>
                    </div>
                ` : ''}

                <div class="resource-actions">
                    <a href="${resource.url}" class="btn-resource btn-primary" rel="noopener">
                        ${icons.external}
                        Buka Resource
                    </a>
                </div>
            </div>
        `;
    }).join('');
}

// Filter and sort
function filterAndSort() {
    const searchInput = document.getElementById('searchInput');
    const filterSelect = document.getElementById('filterType');
    const sortSelect = document.getElementById('sortOrder');

    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    const filterType = filterSelect ? filterSelect.value : 'all';
    const sortOrder = sortSelect ? sortSelect.value : 'asc';

    // Filter
    filteredResources = resources.filter(resource => {
        const matchesSearch = resource.title.toLowerCase().includes(searchTerm) ||
                            (resource.description && resource.description.toLowerCase().includes(searchTerm));
        const matchesType = filterType === 'all' || resource.type === filterType;
        return matchesSearch && matchesType;
    });

    // Sort
    filteredResources.sort((a, b) => {
        if (sortOrder === 'asc') {
            return a.title.localeCompare(b.title);
        } else {
            return b.title.localeCompare(a.title);
        }
    });

    renderResources();
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Initial render
    renderResources();

    // Search
    const searchInput = document.getElementById('searchInput');
    const filterSelect = document.getElementById('filterType');
    const sortSelect = document.getElementById('sortOrder');

    if (searchInput) {
        searchInput.addEventListener('input', filterAndSort);
    }

    if (filterSelect) {
        filterSelect.addEventListener('change', filterAndSort);
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', filterAndSort);
    }

    // Credential handlers
    document.querySelectorAll('.credential-card').forEach(card => {
        card.querySelectorAll('.reveal-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const row = btn.closest('.credential-row');
                const valueEl = row.querySelector('.credential-value');
                const revealed = valueEl.dataset.state === 'visible';
                if (revealed) {
                    valueEl.textContent = '••••••';
                    valueEl.dataset.state = 'hidden';
                    btn.textContent = <?php echo json_encode(__('Reveal')); ?>;
                } else {
                    valueEl.textContent = valueEl.dataset.value;
                    valueEl.dataset.state = 'visible';
                    btn.textContent = <?php echo json_encode(__('Hide')); ?>;
                }
            });
        });

        card.querySelectorAll('.copy-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const row = btn.closest('.credential-row');
                const valueEl = row.querySelector('.credential-value');
                const label = btn.dataset.label || 'Value';
                try {
                    await navigator.clipboard.writeText(valueEl.dataset.value);
                    const original = btn.textContent;
                    btn.textContent = <?php echo json_encode(__('Copied')); ?>;
                    btn.disabled = true;
                    setTimeout(() => {
                        btn.textContent = original;
                        btn.disabled = false;
                    }, 1600);
                } catch (err) {
                    alert(label + ' ' + <?php echo json_encode(__('could not be copied')); ?>);
                }
            });
        });
    });

    // Mobile controls toggle
    const controlsToggleBtn = document.getElementById('controlsToggleBtn');
    const controlsPanel = document.getElementById('resourcesControls');
    if (controlsToggleBtn && controlsPanel) {
        document.body.classList.add('js-controls-ready');

        let wasDesktop = window.innerWidth > 768;
        if (!wasDesktop) {
            controlsPanel.classList.remove('is-open');
        }

        const syncToggleState = () => {
            if (window.innerWidth > 768) {
                controlsPanel.classList.add('is-open');
                controlsToggleBtn.classList.remove('is-active');
                controlsToggleBtn.setAttribute('aria-expanded', 'false');
            } else {
                const expanded = controlsPanel.classList.contains('is-open');
                controlsToggleBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                controlsToggleBtn.classList.toggle('is-active', expanded);
            }
        };

        controlsToggleBtn.addEventListener('click', function() {
            const isOpen = controlsPanel.classList.toggle('is-open');
            controlsToggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            controlsToggleBtn.classList.toggle('is-active', isOpen);
        });

        window.addEventListener('resize', () => {
            const isDesktop = window.innerWidth > 768;
            if (!isDesktop && wasDesktop) {
                controlsPanel.classList.remove('is-open');
                controlsToggleBtn.classList.remove('is-active');
                controlsToggleBtn.setAttribute('aria-expanded', 'false');
            }
            syncToggleState();
            wasDesktop = isDesktop;
        });

        syncToggleState();
    }

});
</script>
