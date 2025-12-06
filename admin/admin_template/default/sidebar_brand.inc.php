<?php
/**
 * Sidebar Brand Component
 */
$libraryName = $sysconf['library_name'] ?? 'SLiMS Library';
$shortName = strlen($libraryName) > 20 ? substr($libraryName, 0, 20) . '...' : $libraryName;
?>
<div class="sidebar-brand">
    <a href="<?php echo AWB; ?>">
        <svg width="32" height="32" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="40" height="40" rx="8" fill="url(#gradient)"/>
            <path d="M12 28V12h4v12h8V12h4v16H12z" fill="white"/>
            <defs>
                <linearGradient id="gradient" x1="0" y1="0" x2="40" y2="40">
                    <stop stop-color="#3b82f6"/>
                    <stop offset="1" stop-color="#1d4ed8"/>
                </linearGradient>
            </defs>
        </svg>
        <span class="sidebar-brand-text"><?php echo htmlspecialchars($shortName); ?></span>
    </a>
</div>
