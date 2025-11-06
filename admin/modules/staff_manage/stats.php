<?php
require_once __DIR__ . '/inc/config.inc.php';

$months = 6;
$summary = $sm_service->getOverviewSummary();
$attendanceSeries = $sm_service->getMonthlyAttendanceSeries($months);

$periodFilters = [
    'start_date' => date('Y-m-01'),
    'end_date' => date('Y-m-d')
];
$monthlyAttendance = $sm_service->getAttendance($periodFilters, 500);

$locationBreakdown = [];
$statusBreakdown = ['on_time' => 0, 'late' => 0, 'early' => 0, 'absent' => 0];
$sourceBreakdown = [];
foreach ($monthlyAttendance as $row) {
    $locationKey = $row['location_name'] ?? __('Manual/Remote');
    $locationBreakdown[$locationKey] = ($locationBreakdown[$locationKey] ?? 0) + 1;
    $statusBreakdown[$row['status']] = ($statusBreakdown[$row['status']] ?? 0) + 1;
    $source = strtoupper($row['check_in_source'] ?? 'MANUAL');
    $sourceBreakdown[$source] = ($sourceBreakdown[$source] ?? 0) + 1;
}

$locationChart = json_encode([
    'labels' => array_keys($locationBreakdown),
    'datasets' => [
        [
            'label' => __('Total Kehadiran'),
            'data' => array_values($locationBreakdown),
            'backgroundColor' => ['#2563eb', '#38bdf8', '#60a5fa', '#1d4ed8', '#0ea5e9']
        ]
    ]
]);

$statusChart = json_encode([
    'labels' => [__('Tepat Waktu'), __('Terlambat'), __('Pulang Cepat'), __('Tidak Hadir')],
    'datasets' => [
        [
            'label' => __('Status'),
            'data' => [
                $statusBreakdown['on_time'] ?? 0,
                $statusBreakdown['late'] ?? 0,
                $statusBreakdown['early'] ?? 0,
                $statusBreakdown['absent'] ?? 0
            ],
            'backgroundColor' => ['#22c55e', '#f59e0b', '#f97316', '#ef4444']
        ]
    ]
]);

$todoChart = json_encode([
    'labels' => [__('Belum'), __('Proses'), __('Selesai')],
    'datasets' => [
        [
            'label' => __('Todo'),
            'data' => [
                $summary['todo_summary']['pending'] ?? 0,
                $summary['todo_summary']['in_progress'] ?? 0,
                $summary['todo_summary']['completed'] ?? 0
            ],
            'backgroundColor' => ['#60a5fa', '#0ea5e9', '#22c55e']
        ]
    ]
]);

$sourceChart = json_encode([
    'labels' => array_keys($sourceBreakdown),
    'datasets' => [
        [
            'label' => __('Metode Masuk'),
            'data' => array_values($sourceBreakdown),
            'backgroundColor' => ['#2563eb', '#16a34a', '#f97316', '#6366f1', '#0ea5e9']
        ]
    ]
]);

$topPerformers = [];
$lateRecords = [];
foreach ($monthlyAttendance as $row) {
    $staffId = $row['staff_id'];
    if (!isset($topPerformers[$staffId])) {
        $topPerformers[$staffId] = [
            'name' => $row['full_name'],
            'code' => $row['staff_code'],
            'total' => 0,
            'late' => 0
        ];
    }
    $topPerformers[$staffId]['total']++;
    if ($row['status'] === 'late') {
        $topPerformers[$staffId]['late']++;
        $lateRecords[] = $row;
    }
}

usort($topPerformers, function ($a, $b) {
    return $b['total'] <=> $a['total'];
});
$topPerformers = array_slice($topPerformers, 0, 5);

sm_asset_tags($sm_common_css, $sm_common_js);
?>
<div class="sm-page">
    <?php
    sm_render_page_header(
        __('Statistik Kinerja'),
        __('Analitik kehadiran, ketepatan waktu, dan produktivitas tugas'),
        [
            [
                'label' => __('Export CSV'),
                'href' => '#',
                'icon' => 'fas fa-file-export',
                'class' => 'sm-btn-secondary'
            ]
        ]
    );
    ?>

    <div class="sm-panel">
        <div class="sm-panel-header">
            <h2><?php echo __('Tren Kehadiran'); ?></h2>
            <span><?php echo __('Performa enam bulan terakhir'); ?></span>
        </div>
        <div style="height: 320px;">
            <canvas data-sm-chart="line" data-chart-payload='<?php echo htmlspecialchars(json_encode([
                'labels' => $attendanceSeries['labels'],
                'datasets' => [
                    [
                        'label' => __('Tepat Waktu'),
                        'data' => $attendanceSeries['on_time'],
                        'color' => '#2563eb',
                        'gradientStart' => 'rgba(37,99,235,0.35)',
                        'gradientEnd' => 'rgba(37,99,235,0.08)'
                    ],
                    [
                        'label' => __('Terlambat'),
                        'data' => $attendanceSeries['late'],
                        'color' => '#f97316',
                        'gradientStart' => 'rgba(249,115,22,0.35)',
                        'gradientEnd' => 'rgba(249,115,22,0.05)'
                    ]
                ]
            ]), ENT_QUOTES); ?>'></canvas>
        </div>
    </div>

    <div class="sm-card-grid">
        <div class="sm-panel">
            <div class="sm-panel-header">
                <h2><?php echo __('Distribusi Lokasi'); ?></h2>
                <span><?php echo __('Bulan berjalan'); ?></span>
            </div>
            <div style="height: 260px;">
                <canvas data-sm-chart="bar" data-chart-payload='<?php echo htmlspecialchars($locationChart, ENT_QUOTES); ?>'></canvas>
            </div>
        </div>
        <div class="sm-panel">
            <div class="sm-panel-header">
                <h2><?php echo __('Status Kehadiran'); ?></h2>
                <span><?php echo __('Bulan berjalan'); ?></span>
            </div>
            <div style="height: 260px;">
                <canvas data-sm-chart="doughnut" data-chart-payload='<?php echo htmlspecialchars($statusChart, ENT_QUOTES); ?>'></canvas>
            </div>
        </div>
        <div class="sm-panel">
            <div class="sm-panel-header">
                <h2><?php echo __('Progres Todo'); ?></h2>
                <span><?php echo __('Snapshot realisasi tugas'); ?></span>
            </div>
            <div style="height: 260px;">
                <canvas data-sm-chart="doughnut" data-chart-payload='<?php echo htmlspecialchars($todoChart, ENT_QUOTES); ?>'></canvas>
            </div>
        </div>
        <div class="sm-panel">
            <div class="sm-panel-header">
                <h2><?php echo __('Metode Check-in'); ?></h2>
                <span><?php echo __('Perbandingan QR, GPS, manual'); ?></span>
            </div>
            <div style="height: 260px;">
                <canvas data-sm-chart="bar" data-chart-payload='<?php echo htmlspecialchars($sourceChart, ENT_QUOTES); ?>'></canvas>
            </div>
        </div>
    </div>

    <div class="sm-panel">
        <div class="sm-panel-header">
            <h2><?php echo __('Top Performer Bulan Ini'); ?></h2>
            <span><?php echo __('Berdasarkan jumlah kehadiran tervalidasi'); ?></span>
        </div>
        <?php if ($topPerformers): ?>
            <table class="sm-table">
                <thead>
                    <tr>
                        <th><?php echo __('Staf'); ?></th>
                        <th><?php echo __('Total Kehadiran'); ?></th>
                        <th><?php echo __('Terlambat'); ?></th>
                        <th><?php echo __('Kualitas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($topPerformers as $row): ?>
                    <?php $quality = $row['total'] > 0 ? round(100 - ($row['late'] / $row['total'] * 100), 1) : 0; ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['name'] ?? '-'); ?></strong><div class="sm-card-meta"><?php echo htmlspecialchars($row['code'] ?? ''); ?></div></td>
                        <td><?php echo number_format($row['total']); ?></td>
                        <td><?php echo number_format($row['late']); ?></td>
                        <td><?php echo sm_badge($quality . '%', $quality >= 95 ? 'success' : ($quality >= 80 ? 'default' : 'warning')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <?php sm_empty_state(__('Belum ada data kehadiran bulan ini.')); ?>
        <?php endif; ?>
    </div>
</div>
