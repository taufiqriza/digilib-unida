<?php
require_once __DIR__ . '/inc/bootstrap.php';

if (empty($_SESSION[PA_SESSION_KEY])) {
    header('Location: index.php');
    exit;
}

$locations = $pa_service->getLocationList(true);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pilih Lokasi Presensi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo PA_ASSET_URL; ?>css/public_attendance.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>
    <div class="pa-container">
        <div class="pa-header">
            <h1><?php echo __('Lokasi Aktif'); ?></h1>
            <p class="pa-tagline"><?php echo __('Pilih titik presensi yang sesuai dengan posisi Anda.'); ?></p>
        </div>

        <div class="pa-card">
            <div class="pa-chip" data-pa-gps><?php echo __('Mendeteksi posisi Anda...'); ?></div>
            <div class="pa-tagline"><?php echo __('Tombol check-in otomatis aktif jika Anda berada di dalam radius.'); ?></div>
        </div>

        <?php if ($locations): ?>
            <div class="pa-grid">
                <?php foreach ($locations as $loc): ?>
                    <div class="pa-location-card" data-pa-location data-lat="<?php echo htmlspecialchars($loc['latitude']); ?>" data-lng="<?php echo htmlspecialchars($loc['longitude']); ?>" data-radius="<?php echo htmlspecialchars($loc['radius_meters']); ?>">
                        <h3><?php echo htmlspecialchars($loc['name']); ?></h3>
                        <span><?php echo htmlspecialchars($loc['description'] ?? ''); ?></span>
                        <span class="pa-pill"><i class="fas fa-ruler-combined"></i> <?php echo htmlspecialchars($loc['radius_meters']); ?> m</span>
                        <span class="pa-tagline" data-pa-distance><?php echo __('Menunggu GPS'); ?></span>
                        <button class="pa-button" data-pa-checkin data-token="<?php echo htmlspecialchars($loc['qr_token']); ?>" disabled>
                            <i class="fas fa-check-circle"></i> <?php echo __('Check-in di sini'); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="pa-location-map" class="pa-map"></div>
        <?php else: ?>
            <div class="pa-alert"><?php echo __('Belum ada lokasi aktif. Hubungi admin.'); ?></div>
        <?php endif; ?>

        <div class="pa-footer">
            <a href="scan.php" class="pa-button secondary"><i class="fas fa-arrow-left"></i> <?php echo __('Kembali ke Scan'); ?></a>
        </div>
    </div>

    <div class="pa-toast"></div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/js/all.min.js" defer></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="<?php echo PA_ASSET_URL; ?>js/public_attendance.js"></script>
    <script>
        document.addEventListener('pa:gps-ready', function () {
            if (window.paRenderMap) {
                window.paRenderMap('pa-location-map', <?php echo json_encode($locations); ?>, { zoom: 18 });
            }
        });
        if (window.paRenderMap) {
            window.paRenderMap('pa-location-map', <?php echo json_encode($locations); ?>, { zoom: 18 });
        }
    </script>
</body>
</html>
