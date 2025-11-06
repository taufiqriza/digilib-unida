<?php
require_once __DIR__ . '/inc/bootstrap.php';

if (empty($_SESSION[PA_SESSION_KEY])) {
    header('Location: index.php');
    exit;
}

$staffSession = $_SESSION[PA_SESSION_KEY];
$staffId = (int)$staffSession['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $payload = json_decode(file_get_contents('php://input'), true);
    $token = trim($payload['token'] ?? '');
    if ($token === '') {
        echo json_encode(['status' => 'error', 'message' => __('QR tidak dikenali.')]);
        exit;
    }
    $location = $pa_service->validateQrToken($token);
    if (!$location || empty($location['is_active'])) {
        echo json_encode(['status' => 'error', 'message' => __('Token tidak valid atau lokasi nonaktif.')]);
        exit;
    }
    $gps = $payload['gps'] ?? [];
    $method = $payload['method'] ?? 'qr';
    $note = $method === 'pin-gps' ? 'Check-in via GPS/Tombol publik' : 'Check-in via QR mandiri';
    $checkSource = $method === 'pin-gps' ? 'pin' : 'qr';
    $result = $pa_service->createPublicAttendance($staffId, (int)$location['id'], $gps, $note, $checkSource);
    if ($result['success']) {
        $pa_service->logActivity($staffId, (int)$location['id'], 'attendance', $note, $_SERVER['REMOTE_ADDR'] ?? null);
        echo json_encode([
            'status' => 'success',
            'message' => __('Presensi berhasil dicatat.'),
            'detail' => sprintf(__('Lokasi: %s (status: %s)'), $location['name'], $result['status'])
        ]);
        exit;
    }
    echo json_encode(['status' => 'error', 'message' => __('Presensi gagal disimpan.')]);
    exit;
}

if (isset($_GET['logout'])) {
    unset($_SESSION[PA_SESSION_KEY]);
    header('Location: index.php');
    exit;
}

$activeLocations = $pa_service->getLocationList(true);

function pa_format_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }
    return date('d M Y H:i', $timestamp);
}

$recentAttendance = $pa_service->getAttendance([
    'staff_id' => $staffId,
    'start_date' => date('Y-m-d', strtotime('-7 days')),
    'end_date' => date('Y-m-d')
], 5);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scan Presensi Staf</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo PA_ASSET_URL; ?>css/public_attendance.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>
    <div class="pa-container">
        <div class="pa-header">
            <h1><?php echo __('Presensi Mandiri Staf'); ?></h1>
            <p class="pa-tagline"><?php echo htmlspecialchars($staffSession['name']); ?> &middot; <?php echo date('l, d M Y'); ?></p>
        </div>

        <div class="pa-card">
            <div class="pa-status">
                <div class="pa-status-card">
                    <div class="pa-avatar"><?php echo strtoupper(substr($staffSession['name'], 0, 1)); ?></div>
                    <div>
                        <strong><?php echo htmlspecialchars($staffSession['name']); ?></strong>
                        <div class="pa-tagline">ID: <?php echo htmlspecialchars($staffSession['code']); ?></div>
                        <div class="pa-tagline" data-pa-gps><?php echo __('Mendeteksi lokasi...'); ?></div>
                    </div>
                </div>
                <div class="pa-cta">
                    <a class="pa-button secondary" href="location_select.php"><i class="fas fa-map-marker-alt"></i> <?php echo __('Pilih Lokasi'); ?></a>
                    <a class="pa-button secondary" href="?logout=1"><i class="fas fa-sign-out-alt"></i> <?php echo __('Keluar'); ?></a>
                </div>
            </div>
        </div>

        <div class="pa-card">
            <div class="pa-status" style="margin-bottom:1rem;">
                <div class="pa-chip"><i class="fas fa-qrcode"></i> <?php echo __('Arahkan kamera ke QR lokasi aktif'); ?></div>
                <div class="pa-chip" data-pa-status><?php echo __('Belum ada presensi'); ?></div>
            </div>
            <div id="qr-reader"></div>
        </div>

        <?php if ($recentAttendance): ?>
            <div class="pa-card">
                <h2 style="margin-top:0;"><?php echo __('Riwayat 7 Hari'); ?></h2>
                <ul class="pa-list">
                    <?php foreach ($recentAttendance as $log): ?>
                        <li>
                            <div>
                                <span><?php echo htmlspecialchars($log['location_name'] ?? __('Manual')); ?></span>
                                <div class="pa-tagline"><?php echo pa_format_datetime($log['check_in_time']); ?></div>
                            </div>
                            <div class="pa-tagline"><?php echo ucfirst($log['status']); ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="pa-toast"></div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/js/all.min.js" defer></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/minified/html5-qrcode.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="<?php echo PA_ASSET_URL; ?>js/public_attendance.js"></script>
</body>
</html>
