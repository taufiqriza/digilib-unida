<?php
require_once __DIR__ . '/inc/bootstrap.php';

$feedback = null;
if (!empty($_SESSION[PA_SESSION_KEY])) {
    header('Location: scan.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = trim($_POST['pin'] ?? '');
    if ($pin === '') {
        $feedback = __('PIN wajib diisi.');
    } else {
        $staff = $pa_service->authenticatePin($pin);
        if ($staff) {
            $_SESSION[PA_SESSION_KEY] = [
                'id' => (int)$staff['id'],
                'name' => $staff['full_name'],
                'code' => $staff['staff_code'],
                'hint' => $staff['pin_hint']
            ];
            header('Location: scan.php');
            exit;
        }
        $feedback = __('PIN tidak valid atau akun tidak aktif.');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Absensi Staf Perpustakaan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo PA_ASSET_URL; ?>css/public_attendance.css">
</head>
<body>
    <div class="pa-container">
        <div class="pa-header">
            <h1><?php echo __('Portal Presensi Staf'); ?></h1>
            <p class="pa-tagline">Senayan Library Management System &middot; <?php echo date('d M Y'); ?></p>
        </div>

        <div class="pa-card">
            <div class="pa-status">
                <div class="pa-chip"><i class="fas fa-lock"></i> <?php echo __('Masuk dengan PIN pribadi'); ?></div>
                <div class="pa-chip"><i class="fas fa-qrcode"></i> <?php echo __('Lanjutkan scan QR untuk presensi'); ?></div>
            </div>
        </div>

        <?php if ($feedback): ?>
            <div class="pa-alert"><?php echo htmlspecialchars($feedback); ?></div>
        <?php endif; ?>

        <form method="post" class="pa-form">
            <label for="pin" style="font-weight:600;color:var(--pa-muted);">PIN Staf</label>
            <input type="password" name="pin" id="pin" class="pa-input" maxlength="8" inputmode="numeric" autocomplete="one-time-code" placeholder="******" autofocus required>
            <button type="submit" class="pa-button"><i class="fas fa-sign-in-alt"></i> <span><?php echo __('Masuk &amp; Scan'); ?></span></button>
        </form>

        <div class="pa-footer">
            <p><?php echo __('Butuh bantuan? Hubungi admin perpustakaan.'); ?></p>
        </div>
    </div>

    <div class="pa-toast"></div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/js/all.min.js" defer></script>
    <script src="<?php echo PA_ASSET_URL; ?>js/public_attendance.js" defer></script>
</body>
</html>
