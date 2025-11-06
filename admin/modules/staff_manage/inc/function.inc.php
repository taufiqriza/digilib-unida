<?php
/**
 * Staff Manage module data layer and services.
 *
 * This file contains lightweight repository and service classes used by the
 * admin and public interfaces. All queries are centralized here to keep the
 * page scripts lean and focused on presentation.
 */

declare(strict_types=1);

namespace StaffManage;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use mysqli;
use RuntimeException;

/**
 * Table name catalogue to avoid magic strings throughout the codebase.
 */
final class Tables
{
    public const STAFF = 'sm_staff';
    public const LOCATIONS = 'sm_locations';
    public const ATTENDANCE = 'sm_attendance';
    public const SCHEDULE = 'sm_schedule';
    public const TODO = 'sm_todo';
    public const ACTIVITY = 'sm_activity_log';
}

/**
 * Handles schema creation and dummy seed records.
 */
final class StaffManageSetup
{
    /**
     * Ensure schema and seeds are available. Only executed once per request.
     */
    public static function bootstrap(mysqli $db): void
    {
        static $bootstrapped = false;
        if ($bootstrapped) {
            return;
        }

        self::createTables($db);
        self::seed($db);
        self::registerModule($db);
        $bootstrapped = true;
    }

    /**
     * Create required tables with minimal relational constraints.
     */
    private static function createTables(mysqli $db): void
    {
        $queries = [
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `staff_code` VARCHAR(30) NOT NULL UNIQUE,
                    `full_name` VARCHAR(120) NOT NULL,
                    `role` VARCHAR(120) NOT NULL,
                    `pin_hash` VARCHAR(255) NOT NULL,
                    `pin_hint` VARCHAR(20) DEFAULT NULL,
                    `email` VARCHAR(120) DEFAULT NULL,
                    `phone` VARCHAR(40) DEFAULT NULL,
                    `avatar` VARCHAR(255) DEFAULT NULL,
                    `status` ENUM(\'active\', \'inactive\') NOT NULL DEFAULT \'active\',
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                Tables::STAFF
            ),
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(120) NOT NULL,
                    `description` TEXT DEFAULT NULL,
                    `latitude` DECIMAL(10,6) DEFAULT NULL,
                    `longitude` DECIMAL(10,6) DEFAULT NULL,
                    `radius_meters` INT UNSIGNED NOT NULL DEFAULT 50,
                    `qr_token` VARCHAR(64) NOT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `require_qr` TINYINT(1) NOT NULL DEFAULT 1,
                    `color_hex` VARCHAR(12) DEFAULT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY (`qr_token`),
                    INDEX (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                Tables::LOCATIONS
            ),
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `staff_id` INT UNSIGNED NOT NULL,
                    `location_id` INT UNSIGNED DEFAULT NULL,
                    `check_in_time` DATETIME NOT NULL,
                    `check_out_time` DATETIME DEFAULT NULL,
                    `check_in_lat` DECIMAL(10,6) DEFAULT NULL,
                    `check_in_lng` DECIMAL(10,6) DEFAULT NULL,
                    `check_out_lat` DECIMAL(10,6) DEFAULT NULL,
                    `check_out_lng` DECIMAL(10,6) DEFAULT NULL,
                    `check_in_source` ENUM(\'qr\',\'manual\',\'gps\',\'pin\') NOT NULL DEFAULT \'manual\',
                    `check_out_source` ENUM(\'qr\',\'manual\',\'gps\',\'pin\') DEFAULT NULL,
                    `status` ENUM(\'on_time\',\'late\',\'early\',\'absent\') NOT NULL DEFAULT \'on_time\',
                    `notes` VARCHAR(255) DEFAULT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `staff_idx` (`staff_id`),
                    KEY `location_idx` (`location_id`),
                    KEY `check_in_time_idx` (`check_in_time`),
                    CONSTRAINT `fk_att_staff` FOREIGN KEY (`staff_id`) REFERENCES `%s`(`id`) ON DELETE CASCADE,
                    CONSTRAINT `fk_att_loc` FOREIGN KEY (`location_id`) REFERENCES `%s`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                Tables::ATTENDANCE,
                Tables::STAFF,
                Tables::LOCATIONS
            ),
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `staff_id` INT UNSIGNED DEFAULT NULL,
                    `location_id` INT UNSIGNED DEFAULT NULL,
                    `shift_date` DATE NOT NULL,
                    `shift_start` TIME NOT NULL,
                    `shift_end` TIME NOT NULL,
                    `status` ENUM(\'planned\',\'completed\',\'cancelled\') NOT NULL DEFAULT \'planned\',
                    `notes` VARCHAR(255) DEFAULT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `shift_date_idx` (`shift_date`),
                    KEY `status_idx` (`status`),
                    CONSTRAINT `fk_sched_staff` FOREIGN KEY (`staff_id`) REFERENCES `%s`(`id`) ON DELETE SET NULL,
                    CONSTRAINT `fk_sched_loc` FOREIGN KEY (`location_id`) REFERENCES `%s`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                Tables::SCHEDULE,
                Tables::STAFF,
                Tables::LOCATIONS
            ),
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `task_title` VARCHAR(160) NOT NULL,
                    `task_description` TEXT DEFAULT NULL,
                    `assigned_to` INT UNSIGNED DEFAULT NULL,
                    `priority` ENUM(\'low\',\'medium\',\'high\',\'critical\') NOT NULL DEFAULT \'medium\',
                    `status` ENUM(\'pending\',\'in_progress\',\'completed\') NOT NULL DEFAULT \'pending\',
                    `progress` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                    `due_date` DATE DEFAULT NULL,
                    `is_team_task` TINYINT(1) NOT NULL DEFAULT 0,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `status_idx` (`status`),
                    KEY `due_date_idx` (`due_date`),
                    CONSTRAINT `fk_todo_staff` FOREIGN KEY (`assigned_to`) REFERENCES `%s`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                Tables::TODO,
                Tables::STAFF
            ),
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `staff_id` INT UNSIGNED DEFAULT NULL,
                    `location_id` INT UNSIGNED DEFAULT NULL,
                    `activity_type` VARCHAR(60) NOT NULL,
                    `activity_desc` TEXT DEFAULT NULL,
                    `logged_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `ip_address` VARCHAR(45) DEFAULT NULL,
                    KEY `logged_at_idx` (`logged_at`),
                    CONSTRAINT `fk_act_staff` FOREIGN KEY (`staff_id`) REFERENCES `%s`(`id`) ON DELETE SET NULL,
                    CONSTRAINT `fk_act_loc` FOREIGN KEY (`location_id`) REFERENCES `%s`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                Tables::ACTIVITY,
                Tables::STAFF,
                Tables::LOCATIONS
            )
        ];

        foreach ($queries as $sql) {
            if (!$db->query($sql)) {
                throw new RuntimeException('Failed to initialize Staff Manage module schema: ' . $db->error);
            }
        }
    }

    /**
     * Populate baseline data to help administrators envision the UI.
     */
    private static function seed(mysqli $db): void
    {
        if (self::tableCount($db, Tables::STAFF) === 0) {
            self::seedStaff($db);
        }
        if (self::tableCount($db, Tables::LOCATIONS) === 0) {
            self::seedLocations($db);
        }
        if (self::tableCount($db, Tables::TODO) === 0) {
            self::seedTodos($db);
        }
        if (self::tableCount($db, Tables::SCHEDULE) === 0) {
            self::seedSchedule($db);
        }
        if (self::tableCount($db, Tables::ATTENDANCE) === 0) {
            self::seedAttendance($db);
        }
        if (self::tableCount($db, Tables::ACTIVITY) === 0) {
            self::seedActivities($db);
        }
    }

    private static function seedStaff(mysqli $db): void
    {
        $rows = [
            ['STF-001', 'Ayu Pratama', 'Koordinator Layanan', '123456', 'ayu.pratama@example.com', '628123001122', 'active'],
            ['STF-002', 'Budi Santoso', 'Staf Referensi', '112233', 'budi.santoso@example.com', '628123334455', 'active'],
            ['STF-003', 'Citra Rahmawati', 'Staf Sirkulasi', '445566', 'citra.rahmawati@example.com', '628129998877', 'active'],
            ['STF-004', 'Dewi Lestari', 'Pengelola Koleksi', '778899', 'dewi.lestari@example.com', '628122221111', 'inactive']
        ];
        $stmt = $db->prepare(
            sprintf(
                'INSERT INTO `%s` (staff_code, full_name, role, pin_hash, pin_hint, email, phone, status) VALUES (?,?,?,?,?,?,?,?)',
                Tables::STAFF
            )
        );
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare staff seed statement: ' . $db->error);
        }
        foreach ($rows as $row) {
            [$code, $name, $role, $pin, $email, $phone, $status] = $row;
            $hash = password_hash($pin, PASSWORD_DEFAULT);
            $hint = substr($pin, 0, 2) . '**' . substr($pin, -2);
            $stmt->bind_param('ssssssss', $code, $name, $role, $hash, $hint, $email, $phone, $status);
            $stmt->execute();
        }
        $stmt->close();
    }

    private static function seedLocations(mysqli $db): void
    {
        $rows = [
            ['Layanan Sirkulasi', 'Meja sirkulasi utama lantai 1', -6.174465, 106.822745, 60, '#2563eb'],
            ['Ruang Referensi', 'Area referensi dan konsultasi koleksi', -6.174780, 106.823120, 45, '#38bdf8'],
            ['Ruang Pengolahan', 'Pengolahan koleksi dan katalogisasi', -6.174210, 106.822390, 40, '#1d4ed8']
        ];
        $stmt = $db->prepare(
            sprintf(
                'INSERT INTO `%s` (name, description, latitude, longitude, radius_meters, qr_token, color_hex) VALUES (?,?,?,?,?,?,?)',
                Tables::LOCATIONS
            )
        );
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare location seed statement: ' . $db->error);
        }
        foreach ($rows as $row) {
            [$name, $desc, $lat, $lng, $radius, $color] = $row;
            $token = self::randomToken();
            $stmt->bind_param('ssddiss', $name, $desc, $lat, $lng, $radius, $token, $color);
            $stmt->execute();
        }
        $stmt->close();
    }

    private static function seedTodos(mysqli $db): void
    {
        $staffMap = self::idMap($db, Tables::STAFF, 'staff_code');
        $rows = [
            ['Update katalog koleksi terbaru', 'Sinkronkan data katalog dengan koleksi donasi terbaru.', $staffMap['STF-003'] ?? null, 'high', 'in_progress', 55, (new DateTimeImmutable('+3 days'))->format('Y-m-d'), 0],
            ['Siapkan laporan statistik kunjungan', 'Susun laporan triwulan untuk pimpinan perpustakaan.', $staffMap['STF-001'] ?? null, 'medium', 'pending', 10, (new DateTimeImmutable('+7 days'))->format('Y-m-d'), 0],
            ['Pelatihan staf baru', 'Agenda pelatihan internal sistem otomasi.', null, 'critical', 'pending', 0, (new DateTimeImmutable('+14 days'))->format('Y-m-d'), 1],
            ['Audit koleksi referensi', 'Periksa kondisi fisik dan kelengkapan koleksi referensi.', $staffMap['STF-002'] ?? null, 'medium', 'in_progress', 35, (new DateTimeImmutable('+10 days'))->format('Y-m-d'), 0],
        ];
        $stmt = $db->prepare(
            sprintf(
                'INSERT INTO `%s` (task_title, task_description, assigned_to, priority, status, progress, due_date, is_team_task) VALUES (?,?,?,?,?,?,?,?)',
                Tables::TODO
            )
        );
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare todo seed statement: ' . $db->error);
        }
        foreach ($rows as $row) {
            [$title, $desc, $assigned, $priority, $status, $progress, $due, $isTeam] = $row;
            $stmt->bind_param('ssissisi', $title, $desc, $assigned, $priority, $status, $progress, $due, $isTeam);
            $stmt->execute();
        }
        $stmt->close();
    }

    private static function seedSchedule(mysqli $db): void
    {
        $staffMap = self::idMap($db, Tables::STAFF, 'staff_code');
        $locMap = self::idMap($db, Tables::LOCATIONS, 'name');
        $baseDate = new DateTimeImmutable('today');
        $rows = [
            [$staffMap['STF-001'] ?? null, $locMap['Layanan Sirkulasi'] ?? null, $baseDate->format('Y-m-d'), '08:00:00', '15:00:00', 'planned', 'Shift pagi layanan sirkulasi'],
            [$staffMap['STF-002'] ?? null, $locMap['Ruang Referensi'] ?? null, $baseDate->add(new DateInterval('P1D'))->format('Y-m-d'), '09:00:00', '16:00:00', 'planned', 'Pendampingan pengguna'],
            [$staffMap['STF-003'] ?? null, $locMap['Ruang Pengolahan'] ?? null, $baseDate->add(new DateInterval('P2D'))->format('Y-m-d'), '08:30:00', '14:30:00', 'planned', 'Pengolahan koleksi baru']
        ];
        $stmt = $db->prepare(
            sprintf(
                'INSERT INTO `%s` (staff_id, location_id, shift_date, shift_start, shift_end, status, notes) VALUES (?,?,?,?,?,?,?)',
                Tables::SCHEDULE
            )
        );
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare schedule seed statement: ' . $db->error);
        }
        foreach ($rows as $row) {
            [$staffId, $locationId, $date, $start, $end, $status, $notes] = $row;
            $stmt->bind_param('iisssss', $staffId, $locationId, $date, $start, $end, $status, $notes);
            $stmt->execute();
        }
        $stmt->close();
    }

    private static function seedAttendance(mysqli $db): void
    {
        $staffMap = self::idMap($db, Tables::STAFF, 'staff_code');
        $locMap = self::idMap($db, Tables::LOCATIONS, 'name');
        $today = new DateTimeImmutable('today');
        $rows = [
            [$staffMap['STF-001'] ?? null, $locMap['Layanan Sirkulasi'] ?? null, $today->sub(new DateInterval('P1D'))->setTime(7, 55), $today->sub(new DateInterval('P1D'))->setTime(15, 10), 'gps', 'qr', 'on_time', 'Tepat waktu dan menyelesaikan briefing.'],
            [$staffMap['STF-002'] ?? null, $locMap['Ruang Referensi'] ?? null, $today->sub(new DateInterval('P2D'))->setTime(9, 5), $today->sub(new DateInterval('P2D'))->setTime(16, 3), 'qr', 'qr', 'late', 'Terlambat karena sesi konsultasi daring.'],
            [$staffMap['STF-003'] ?? null, $locMap['Ruang Pengolahan'] ?? null, $today->sub(new DateInterval('P3D'))->setTime(8, 15), $today->sub(new DateInterval('P3D'))->setTime(14, 40), 'manual', 'manual', 'on_time', 'Lembur untuk selesaikan backlog katalog.']
        ];
        $stmt = $db->prepare(
            sprintf(
                'INSERT INTO `%s` (staff_id, location_id, check_in_time, check_out_time, check_in_source, check_out_source, status, notes) VALUES (?,?,?,?,?,?,?,?)',
                Tables::ATTENDANCE
            )
        );
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare attendance seed statement: ' . $db->error);
        }
        foreach ($rows as $row) {
            [$staffId, $locationId, $checkIn, $checkOut, $inSource, $outSource, $status, $notes] = $row;
            if ($staffId === null) {
                continue;
            }
            $checkInStr = $checkIn instanceof DateTimeInterface ? $checkIn->format('Y-m-d H:i:s') : $checkIn;
            $checkOutStr = $checkOut instanceof DateTimeInterface ? $checkOut->format('Y-m-d H:i:s') : $checkOut;
            $stmt->bind_param('iissssss', $staffId, $locationId, $checkInStr, $checkOutStr, $inSource, $outSource, $status, $notes);
            $stmt->execute();
        }
        $stmt->close();
    }

    private static function seedActivities(mysqli $db): void
    {
        $staffMap = self::idMap($db, Tables::STAFF, 'staff_code');
        $locMap = self::idMap($db, Tables::LOCATIONS, 'name');
        $rows = [
            [$staffMap['STF-001'] ?? null, $locMap['Layanan Sirkulasi'] ?? null, 'briefing', 'Briefing pagi dengan tim layanan.', new DateTimeImmutable('-1 day 08:10:00')],
            [$staffMap['STF-002'] ?? null, $locMap['Ruang Referensi'] ?? null, 'consultation', 'Sesi konsultasi referensi dengan mahasiswa.', new DateTimeImmutable('-2 day 10:15:00')],
            [$staffMap['STF-003'] ?? null, $locMap['Ruang Pengolahan'] ?? null, 'cataloguing', 'Input metadata koleksi baru.', new DateTimeImmutable('-3 day 13:45:00')]
        ];
        $stmt = $db->prepare(
            sprintf(
                'INSERT INTO `%s` (staff_id, location_id, activity_type, activity_desc, logged_at) VALUES (?,?,?,?,?)',
                Tables::ACTIVITY
            )
        );
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare activity seed statement: ' . $db->error);
        }
        foreach ($rows as $row) {
            [$staffId, $locationId, $type, $desc, $loggedAt] = $row;
            $loggedAtStr = $loggedAt instanceof DateTimeInterface ? $loggedAt->format('Y-m-d H:i:s') : $loggedAt;
            $stmt->bind_param('iisss', $staffId, $locationId, $type, $desc, $loggedAtStr);
            $stmt->execute();
        }
        $stmt->close();
    }

    private static function tableCount(mysqli $db, string $table): int
    {
        $result = $db->query(sprintf('SELECT COUNT(*) AS total FROM `%s`', $table));
        if (!$result) {
            return 0;
        }
        $row = $result->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    /**
     * Map identifying column to id for quick lookups during seed.
     */
    private static function idMap(mysqli $db, string $table, string $keyColumn): array
    {
        $map = [];
        $result = $db->query(sprintf('SELECT `id`, `%s` FROM `%s`', $keyColumn, $table));
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $map[$row[$keyColumn]] = (int)$row['id'];
            }
            $result->free();
        }
        return $map;
    }

    public static function randomToken(int $length = 24): string
    {
        return bin2hex(random_bytes(max(8, (int)ceil($length / 2))));
    }

    private static function registerModule(mysqli $db): void
    {
        $modulePath = 'staff_manage';
        $moduleName = 'staff_manage';
        $moduleDesc = 'Manajemen SDM';

        $moduleId = null;
        $check = $db->query("SELECT module_id FROM mst_module WHERE module_path='{$modulePath}' LIMIT 1");
        if ($check && $check->num_rows > 0) {
            $moduleId = (int)$check->fetch_assoc()['module_id'];
            $check->free();
        } else {
            $stmt = $db->prepare('INSERT INTO mst_module (module_name, module_path, module_desc) VALUES (?,?,?)');
            if ($stmt) {
                $stmt->bind_param('sss', $moduleName, $modulePath, $moduleDesc);
                if ($stmt->execute()) {
                    $moduleId = (int)$db->insert_id;
                }
                $stmt->close();
            }
        }

        if (!$moduleId) {
            $retry = $db->query("SELECT module_id FROM mst_module WHERE module_path='{$modulePath}' LIMIT 1");
            if ($retry && $retry->num_rows > 0) {
                $moduleId = (int)$retry->fetch_assoc()['module_id'];
                $retry->free();
            }
        }

        if (!$moduleId) {
            return;
        }

        $groupCheck = $db->query(sprintf('SELECT 1 FROM group_access WHERE group_id=1 AND module_id=%d', $moduleId));
        if ($groupCheck && $groupCheck->num_rows > 0) {
            $groupCheck->free();
            return;
        }
        if ($groupCheck) {
            $groupCheck->free();
        }

        $stmtAccess = $db->prepare('INSERT INTO group_access (group_id, module_id, menus, r, w) VALUES (1, ?, NULL, 1, 1)');
        if ($stmtAccess) {
            $stmtAccess->bind_param('i', $moduleId);
            $stmtAccess->execute();
            $stmtAccess->close();
        }

        $db->query(
            sprintf(
                'INSERT INTO group_access (group_id, module_id, menus, r, w)
                 SELECT ug.group_id, %1$d, NULL, 1, IF(ug.group_id=1,1,0)
                 FROM user_group AS ug
                 LEFT JOIN group_access AS ga ON ga.group_id=ug.group_id AND ga.module_id=%1$d
                 WHERE ga.group_id IS NULL',
                (int)$moduleId
            )
        );

        $groups = [];
        $groupResult = $db->query('SELECT group_id FROM user_group');
        if ($groupResult) {
            while ($groupRow = $groupResult->fetch_assoc()) {
                $groups[] = (int)$groupRow['group_id'];
            }
            $groupResult->free();
        }
        if (!$groups) {
            $groups = [1];
        }

        if (isset($_SESSION)) {
            if (!isset($_SESSION['priv']) || !is_array($_SESSION['priv'])) {
                $_SESSION['priv'] = [];
            }
            $sessionGroups = [];
            if (isset($_SESSION['groups']) && is_array($_SESSION['groups'])) {
                $sessionGroups = array_map('intval', $_SESSION['groups']);
            } elseif (isset($_SESSION['group'])) {
                $sessionGroups = [(int)$_SESSION['group']];
            }
            $hasWrite = in_array(1, $sessionGroups, true);
            if (!isset($_SESSION['priv'][$modulePath]) || !is_array($_SESSION['priv'][$modulePath])) {
                $_SESSION['priv'][$modulePath] = [
                    'r' => true,
                    'w' => $hasWrite,
                    'menus' => []
                ];
            } else {
                $_SESSION['priv'][$modulePath]['r'] = true;
                if ($hasWrite) {
                    $_SESSION['priv'][$modulePath]['w'] = true;
                }
                $_SESSION['priv'][$modulePath]['menus'] = $_SESSION['priv'][$modulePath]['menus'] ?? [];
            }
        }

        $pluginFile = realpath(SB . 'plugins/staff_manage_bootstrap.plugin.php');
        if ($pluginFile) {
            $pluginId = md5($pluginFile);
            $pluginCheck = $db->query(sprintf(
                "SELECT id, deleted_at FROM plugins WHERE id='%s' LIMIT 1",
                $db->real_escape_string($pluginId)
            ));
            $existsActive = false;
            $needReactivate = false;
            if ($pluginCheck && $pluginCheck->num_rows > 0) {
                $row = $pluginCheck->fetch_assoc();
                $existsActive = empty($row['deleted_at']);
                $needReactivate = !$existsActive;
            }
            if ($pluginCheck) {
                $pluginCheck->free();
            }
            if ($needReactivate) {
                $db->query(sprintf(
                    "UPDATE plugins SET path='%s', deleted_at=NULL, updated_at='%s' WHERE id='%s'",
                    $db->real_escape_string($pluginFile),
                    $db->real_escape_string(date('Y-m-d H:i:s')),
                    $db->real_escape_string($pluginId)
                ));
            } elseif (!$existsActive) {
                $createdAt = $db->real_escape_string(date('Y-m-d H:i:s'));
                $uid = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 1;
                $db->query(sprintf(
                    "INSERT INTO plugins (id, path, options, created_at, uid) VALUES ('%s','%s',NULL,'%s',%d)",
                    $db->real_escape_string($pluginId),
                    $db->real_escape_string($pluginFile),
                    $createdAt,
                    $uid
                ));
            }
        }
    }
}

/**
 * Simple geographic helper used to calculate distance between coordinates.
 */
final class Geo
{
    private const EARTH_RADIUS = 6371000; // meters

    public static function distanceMeters(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?float
    {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
            return null;
        }
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lng2);
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return self::EARTH_RADIUS * $angle;
    }
}

/**
 * Data access layer.
 */
final class StaffManageRepository
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function getStaff(bool $onlyActive = false): array
    {
        $sql = sprintf('SELECT * FROM `%s`%s ORDER BY full_name ASC', Tables::STAFF, $onlyActive ? " WHERE status='active'" : '');
        return $this->fetchAll($sql);
    }

    public function getLocations(bool $onlyActive = false): array
    {
        $sql = sprintf('SELECT * FROM `%s`%s ORDER BY name ASC', Tables::LOCATIONS, $onlyActive ? " WHERE is_active=1" : '');
        return $this->fetchAll($sql);
    }

    public function findLocationById(int $id): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE id=%d', Tables::LOCATIONS, $id);
        return $this->fetchOne($sql);
    }

    public function findStaffById(int $id): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE id=%d', Tables::STAFF, $id);
        return $this->fetchOne($sql);
    }

    public function findStaffByCode(string $code): ?array
    {
        $escaped = $this->db->real_escape_string($code);
        $sql = sprintf("SELECT * FROM `%s` WHERE staff_code='%s'", Tables::STAFF, $escaped);
        return $this->fetchOne($sql);
    }

    public function findStaffByPin(string $pin): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE status=\'active\'', Tables::STAFF);
        $result = $this->db->query($sql);
        if (!$result) {
            return null;
        }
        while ($row = $result->fetch_assoc()) {
            if (password_verify($pin, $row['pin_hash'])) {
                $result->free();
                return $row;
            }
        }
        $result->free();
        return null;
    }

    public function getAttendance(array $filters, int $limit = 100): array
    {
        $where = [];
        if (!empty($filters['start_date'])) {
            $where[] = sprintf("a.check_in_time >= '%s 00:00:00'", $this->db->real_escape_string($filters['start_date']));
        }
        if (!empty($filters['end_date'])) {
            $where[] = sprintf("a.check_in_time <= '%s 23:59:59'", $this->db->real_escape_string($filters['end_date']));
        }
        if (!empty($filters['location_id'])) {
            $where[] = 'a.location_id=' . (int)$filters['location_id'];
        }
        if (!empty($filters['staff_id'])) {
            $where[] = 'a.staff_id=' . (int)$filters['staff_id'];
        }
        $sql = sprintf(
            'SELECT a.*, s.full_name, s.staff_code, l.name AS location_name FROM `%s` AS a
             LEFT JOIN `%s` AS s ON a.staff_id=s.id
             LEFT JOIN `%s` AS l ON a.location_id=l.id
             %s ORDER BY a.check_in_time DESC LIMIT %d',
            Tables::ATTENDANCE,
            Tables::STAFF,
            Tables::LOCATIONS,
            $where ? 'WHERE ' . implode(' AND ', $where) : '',
            $limit
        );
        return $this->fetchAll($sql);
    }

    public function getAttendanceById(int $id): ?array
    {
        $sql = sprintf(
            'SELECT a.*, s.full_name, s.staff_code, l.name AS location_name FROM `%s` AS a
             LEFT JOIN `%s` AS s ON a.staff_id=s.id
             LEFT JOIN `%s` AS l ON a.location_id=l.id
             WHERE a.id=%d',
            Tables::ATTENDANCE,
            Tables::STAFF,
            Tables::LOCATIONS,
            $id
        );
        return $this->fetchOne($sql);
    }

    public function insertAttendance(array $payload): bool
    {
        $stmt = $this->db->prepare(
            sprintf(
                'INSERT INTO `%s` (staff_id, location_id, check_in_time, check_out_time, check_in_lat, check_in_lng, check_out_lat, check_out_lng, check_in_source, check_out_source, status, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                Tables::ATTENDANCE
            )
        );
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare attendance insert statement: ' . $this->db->error);
        }
        $stmt->bind_param(
            'iissddddssss',
            $payload['staff_id'],
            $payload['location_id'],
            $payload['check_in_time'],
            $payload['check_out_time'],
            $payload['check_in_lat'],
            $payload['check_in_lng'],
            $payload['check_out_lat'],
            $payload['check_out_lng'],
            $payload['check_in_source'],
            $payload['check_out_source'],
            $payload['status'],
            $payload['notes']
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function updateAttendance(int $id, array $payload): bool
    {
        $stmt = $this->db->prepare(
            sprintf(
                'UPDATE `%s` SET staff_id=?, location_id=?, check_in_time=?, check_out_time=?, check_in_lat=?, check_in_lng=?, check_out_lat=?, check_out_lng=?, check_in_source=?, check_out_source=?, status=?, notes=? WHERE id=?',
                Tables::ATTENDANCE
            )
        );
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare attendance update statement: ' . $this->db->error);
        }
        $stmt->bind_param(
            'iissddddssssi',
            $payload['staff_id'],
            $payload['location_id'],
            $payload['check_in_time'],
            $payload['check_out_time'],
            $payload['check_in_lat'],
            $payload['check_in_lng'],
            $payload['check_out_lat'],
            $payload['check_out_lng'],
            $payload['check_in_source'],
            $payload['check_out_source'],
            $payload['status'],
            $payload['notes'],
            $id
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteAttendance(int $id): bool
    {
        $sql = sprintf('DELETE FROM `%s` WHERE id=%d', Tables::ATTENDANCE, $id);
        return (bool)$this->db->query($sql);
    }

    public function getSchedule(array $filters = []): array
    {
        $where = [];
        if (!empty($filters['start_date'])) {
            $where[] = sprintf("s.shift_date >= '%s'", $this->db->real_escape_string($filters['start_date']));
        }
        if (!empty($filters['end_date'])) {
            $where[] = sprintf("s.shift_date <= '%s'", $this->db->real_escape_string($filters['end_date']));
        }
        if (!empty($filters['staff_id'])) {
            $where[] = 's.staff_id=' . (int)$filters['staff_id'];
        }
        if (!empty($filters['id'])) {
            $where[] = 's.id=' . (int)$filters['id'];
        }
        $sql = sprintf(
            'SELECT s.*, st.full_name, st.staff_code, l.name AS location_name FROM `%s` AS s
             LEFT JOIN `%s` AS st ON st.id=s.staff_id
             LEFT JOIN `%s` AS l ON l.id=s.location_id
             %s ORDER BY s.shift_date ASC, s.shift_start ASC',
            Tables::SCHEDULE,
            Tables::STAFF,
            Tables::LOCATIONS,
            $where ? 'WHERE ' . implode(' AND ', $where) : ''
        );
        return $this->fetchAll($sql);
    }

    public function upsertSchedule(?int $id, array $payload): bool
    {
        if ($id === null) {
            $stmt = $this->db->prepare(
                sprintf('INSERT INTO `%s` (staff_id, location_id, shift_date, shift_start, shift_end, status, notes) VALUES (?,?,?,?,?,?,?)', Tables::SCHEDULE)
            );
            if (!$stmt) {
                throw new RuntimeException('Failed to prepare schedule insert statement: ' . $this->db->error);
            }
            $stmt->bind_param('iisssss', $payload['staff_id'], $payload['location_id'], $payload['shift_date'], $payload['shift_start'], $payload['shift_end'], $payload['status'], $payload['notes']);
        } else {
            $stmt = $this->db->prepare(
                sprintf('UPDATE `%s` SET staff_id=?, location_id=?, shift_date=?, shift_start=?, shift_end=?, status=?, notes=? WHERE id=?', Tables::SCHEDULE)
            );
            if (!$stmt) {
                throw new RuntimeException('Failed to prepare schedule update statement: ' . $this->db->error);
            }
            $stmt->bind_param('iisssssi', $payload['staff_id'], $payload['location_id'], $payload['shift_date'], $payload['shift_start'], $payload['shift_end'], $payload['status'], $payload['notes'], $id);
        }
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteSchedule(int $id): bool
    {
        $sql = sprintf('DELETE FROM `%s` WHERE id=%d', Tables::SCHEDULE, $id);
        return (bool)$this->db->query($sql);
    }

    public function getTodos(array $filters = []): array
    {
        $where = [];
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = sprintf("t.status='%s'", $this->db->real_escape_string($filters['status']));
        }
        if (!empty($filters['priority']) && $filters['priority'] !== 'all') {
            $where[] = sprintf("t.priority='%s'", $this->db->real_escape_string($filters['priority']));
        }
        if (!empty($filters['id'])) {
            $where[] = 't.id=' . (int)$filters['id'];
        }
        $sql = sprintf(
            'SELECT t.*, s.full_name, s.staff_code FROM `%s` AS t
             LEFT JOIN `%s` AS s ON s.id=t.assigned_to
             %s ORDER BY t.due_date IS NULL, t.due_date ASC, t.created_at DESC',
            Tables::TODO,
            Tables::STAFF,
            $where ? 'WHERE ' . implode(' AND ', $where) : ''
        );
        return $this->fetchAll($sql);
    }

    public function upsertTodo(?int $id, array $payload): bool
    {
        if ($id === null) {
            $stmt = $this->db->prepare(
                sprintf('INSERT INTO `%s` (task_title, task_description, assigned_to, priority, status, progress, due_date, is_team_task) VALUES (?,?,?,?,?,?,?,?)', Tables::TODO)
            );
            if (!$stmt) {
                throw new RuntimeException('Failed to prepare todo insert statement: ' . $this->db->error);
            }
            $stmt->bind_param('ssissisi', $payload['task_title'], $payload['task_description'], $payload['assigned_to'], $payload['priority'], $payload['status'], $payload['progress'], $payload['due_date'], $payload['is_team_task']);
        } else {
            $stmt = $this->db->prepare(
                sprintf('UPDATE `%s` SET task_title=?, task_description=?, assigned_to=?, priority=?, status=?, progress=?, due_date=?, is_team_task=? WHERE id=?', Tables::TODO)
            );
            if (!$stmt) {
                throw new RuntimeException('Failed to prepare todo update statement: ' . $this->db->error);
            }
            $stmt->bind_param('ssissisii', $payload['task_title'], $payload['task_description'], $payload['assigned_to'], $payload['priority'], $payload['status'], $payload['progress'], $payload['due_date'], $payload['is_team_task'], $id);
        }
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteTodo(int $id): bool
    {
        $sql = sprintf('DELETE FROM `%s` WHERE id=%d', Tables::TODO, $id);
        return (bool)$this->db->query($sql);
    }

    public function getActivities(int $limit = 50, array $filters = []): array
    {
        $where = [];
        if (!empty($filters['staff_id'])) {
            $where[] = 'a.staff_id=' . (int)$filters['staff_id'];
        }
        if (!empty($filters['location_id'])) {
            $where[] = 'a.location_id=' . (int)$filters['location_id'];
        }
        if (!empty($filters['start_date'])) {
            $where[] = sprintf("a.logged_at >= '%s 00:00:00'", $this->db->real_escape_string($filters['start_date']));
        }
        if (!empty($filters['end_date'])) {
            $where[] = sprintf("a.logged_at <= '%s 23:59:59'", $this->db->real_escape_string($filters['end_date']));
        }
        $sql = sprintf(
            'SELECT a.*, s.full_name, s.staff_code, l.name AS location_name FROM `%s` AS a
             LEFT JOIN `%s` AS s ON s.id=a.staff_id
             LEFT JOIN `%s` AS l ON l.id=a.location_id
             %s
             ORDER BY a.logged_at DESC LIMIT %d',
            Tables::ACTIVITY,
            Tables::STAFF,
            Tables::LOCATIONS,
            $where ? 'WHERE ' . implode(' AND ', $where) : '',
            $limit
        );
        return $this->fetchAll($sql);
    }

    public function insertActivity(array $payload): bool
    {
        $stmt = $this->db->prepare(
            sprintf('INSERT INTO `%s` (staff_id, location_id, activity_type, activity_desc, ip_address) VALUES (?,?,?,?,?)', Tables::ACTIVITY)
        );
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare activity insert statement: ' . $this->db->error);
        }
        $stmt->bind_param('iisss', $payload['staff_id'], $payload['location_id'], $payload['activity_type'], $payload['activity_desc'], $payload['ip_address']);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function upsertLocation(?int $id, array $payload): bool
    {
        if ($id === null) {
            $stmt = $this->db->prepare(
                sprintf('INSERT INTO `%s` (name, description, latitude, longitude, radius_meters, qr_token, is_active, require_qr, color_hex) VALUES (?,?,?,?,?,?,?,?,?)', Tables::LOCATIONS)
            );
            if (!$stmt) {
                throw new RuntimeException('Failed to prepare location insert statement: ' . $this->db->error);
            }
            $stmt->bind_param(
                'ssssisiis',
                $payload['name'],
                $payload['description'],
                $payload['latitude'],
                $payload['longitude'],
                $payload['radius_meters'],
                $payload['qr_token'],
                $payload['is_active'],
                $payload['require_qr'],
                $payload['color_hex']
            );
        } else {
            $stmt = $this->db->prepare(
                sprintf('UPDATE `%s` SET name=?, description=?, latitude=?, longitude=?, radius_meters=?, qr_token=?, is_active=?, require_qr=?, color_hex=? WHERE id=?', Tables::LOCATIONS)
            );
            if (!$stmt) {
                throw new RuntimeException('Failed to prepare location update statement: ' . $this->db->error);
            }
            $stmt->bind_param(
                'ssssisiisi',
                $payload['name'],
                $payload['description'],
                $payload['latitude'],
                $payload['longitude'],
                $payload['radius_meters'],
                $payload['qr_token'],
                $payload['is_active'],
                $payload['require_qr'],
                $payload['color_hex'],
                $id
            );
        }
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteLocation(int $id): bool
    {
        $sql = sprintf('DELETE FROM `%s` WHERE id=%d', Tables::LOCATIONS, $id);
        return (bool)$this->db->query($sql);
    }

    public function updateTodoStatus(int $id, string $status, int $progress): bool
    {
        $stmt = $this->db->prepare(
            sprintf('UPDATE `%s` SET status=?, progress=? WHERE id=?', Tables::TODO)
        );
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare todo status update statement: ' . $this->db->error);
        }
        $stmt->bind_param('sii', $status, $progress, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function updateScheduleStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare(
            sprintf('UPDATE `%s` SET status=? WHERE id=?', Tables::SCHEDULE)
        );
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare schedule status update statement: ' . $this->db->error);
        }
        $stmt->bind_param('si', $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function setLocationStatus(int $id, bool $isActive): bool
    {
        $stmt = $this->db->prepare(
            sprintf('UPDATE `%s` SET is_active=? WHERE id=?', Tables::LOCATIONS)
        );
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare location status update statement: ' . $this->db->error);
        }
        $active = $isActive ? 1 : 0;
        $stmt->bind_param('ii', $active, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function getMonthlyAttendanceMetrics(int $months = 6): array
    {
        $months = max(1, min(12, $months));
        $sql = sprintf(
            'SELECT DATE_FORMAT(check_in_time, "%%Y-%%m") AS period,
                    COUNT(*) AS total,
                    SUM(CASE WHEN status=\'late\' THEN 1 ELSE 0 END) AS late_count,
                    SUM(CASE WHEN status=\'on_time\' THEN 1 ELSE 0 END) AS on_time_count
             FROM `%s`
             WHERE check_in_time >= DATE_SUB(CURDATE(), INTERVAL %d MONTH)
             GROUP BY period
             ORDER BY period ASC',
            Tables::ATTENDANCE,
            $months
        );
        return $this->fetchAll($sql);
    }

    public function getTodoSummary(): array
    {
        $sql = sprintf(
            'SELECT status, COUNT(*) AS total FROM `%s` GROUP BY status',
            Tables::TODO
        );
        $data = $this->fetchAll($sql);
        $summary = ['pending' => 0, 'in_progress' => 0, 'completed' => 0];
        foreach ($data as $row) {
            $summary[$row['status']] = (int)$row['total'];
        }
        return $summary;
    }

    public function countStaff(bool $onlyActive = false): int
    {
        $sql = sprintf('SELECT COUNT(*) AS total FROM `%s`%s', Tables::STAFF, $onlyActive ? " WHERE status='active'" : '');
        $row = $this->fetchOne($sql);
        return (int)($row['total'] ?? 0);
    }

    public function countAttendanceByDate(string $date): int
    {
        $sql = sprintf("SELECT COUNT(*) AS total FROM `%s` WHERE DATE(check_in_time)='%s'", Tables::ATTENDANCE, $this->db->real_escape_string($date));
        $row = $this->fetchOne($sql);
        return (int)($row['total'] ?? 0);
    }

    public function countCompletedTodos(): int
    {
        $sql = sprintf("SELECT COUNT(*) AS total FROM `%s` WHERE status='completed'", Tables::TODO);
        $row = $this->fetchOne($sql);
        return (int)($row['total'] ?? 0);
    }

    public function countActiveLocations(): int
    {
        $sql = sprintf('SELECT COUNT(*) AS total FROM `%s` WHERE is_active=1', Tables::LOCATIONS);
        $row = $this->fetchOne($sql);
        return (int)($row['total'] ?? 0);
    }

    public function getRecentActivities(int $limit = 8): array
    {
        return $this->getActivities($limit);
    }

    private function fetchAll(string $sql): array
    {
        $result = $this->db->query($sql);
        if (!$result) {
            return [];
        }
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
        return $rows;
    }

    private function fetchOne(string $sql): ?array
    {
        $result = $this->db->query($sql);
        if (!$result) {
            return null;
        }
        $row = $result->fetch_assoc();
        $result->free();
        return $row ?: null;
    }
}

/**
 * Domain service exposing higher-level operations to the UI layer.
 */
final class StaffManageService
{
    private StaffManageRepository $repo;

    public function __construct(StaffManageRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getOverviewSummary(): array
    {
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        return [
            'total_staff' => $this->repo->countStaff(),
            'staff_active' => $this->repo->countStaff(true),
            'present_today' => $this->repo->countAttendanceByDate($today),
            'tasks_completed' => $this->repo->countCompletedTodos(),
            'locations_active' => $this->repo->countActiveLocations(),
            'todo_summary' => $this->repo->getTodoSummary(),
            'recent_activities' => $this->repo->getRecentActivities()
        ];
    }

    public function getMonthlyAttendanceSeries(int $months = 6): array
    {
        $data = $this->repo->getMonthlyAttendanceMetrics($months);
        $labels = [];
        $onTime = [];
        $late = [];
        foreach ($data as $row) {
            $labels[] = $row['period'];
            $onTime[] = (int)$row['on_time_count'];
            $late[] = (int)$row['late_count'];
        }
        return [
            'labels' => $labels,
            'on_time' => $onTime,
            'late' => $late
        ];
    }

    public function getStaffList(bool $onlyActive = false): array
    {
        return $this->repo->getStaff($onlyActive);
    }

    public function getLocationList(bool $onlyActive = false): array
    {
        return $this->repo->getLocations($onlyActive);
    }

    public function getAttendance(array $filters, int $limit = 100): array
    {
        return $this->repo->getAttendance($filters, $limit);
    }

    public function getAttendanceById(int $id): ?array
    {
        return $this->repo->getAttendanceById($id);
    }

    public function saveAttendance(array $payload, ?int $id = null): array
    {
        $clean = $this->normalizeAttendancePayload($payload);
        $location = null;
        if (!empty($clean['location_id'])) {
            $location = $this->repo->findLocationById((int)$clean['location_id']);
        }
        $clean['status'] = $this->determineAttendanceStatus($clean, $location);
        $success = $id === null
            ? $this->repo->insertAttendance($clean)
            : $this->repo->updateAttendance($id, $clean);
        return ['success' => $success, 'status' => $clean['status']];
    }

    public function deleteAttendance(int $id): bool
    {
        return $this->repo->deleteAttendance($id);
    }

    public function getSchedule(array $filters = []): array
    {
        return $this->repo->getSchedule($filters);
    }

    public function getScheduleById(int $id): ?array
    {
        $result = $this->repo->getSchedule(['id' => $id]);
        foreach ($result as $row) {
            if ((int)$row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    public function saveSchedule(array $payload, ?int $id = null): bool
    {
        $clean = $this->normalizeSchedulePayload($payload);
        return $this->repo->upsertSchedule($id, $clean);
    }

    public function deleteSchedule(int $id): bool
    {
        return $this->repo->deleteSchedule($id);
    }

    public function updateScheduleStatus(int $id, string $status): bool
    {
        return $this->repo->updateScheduleStatus($id, $status);
    }

    public function getTodos(array $filters = []): array
    {
        return $this->repo->getTodos($filters);
    }

    public function getTodoById(int $id): ?array
    {
        $result = $this->repo->getTodos(['id' => $id]);
        foreach ($result as $row) {
            if ((int)$row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    public function saveTodo(array $payload, ?int $id = null): bool
    {
        $clean = $this->normalizeTodoPayload($payload);
        return $this->repo->upsertTodo($id, $clean);
    }

    public function updateTodoStatus(int $id, string $status, int $progress): bool
    {
        $progress = max(0, min(100, $progress));
        return $this->repo->updateTodoStatus($id, $status, $progress);
    }

    public function deleteTodo(int $id): bool
    {
        return $this->repo->deleteTodo($id);
    }

    public function saveLocation(array $payload, ?int $id = null): bool
    {
        $clean = $this->normalizeLocationPayload($payload);
        if ($id === null) {
            $clean['qr_token'] = $clean['qr_token'] ?: StaffManageSetup::randomToken();
            return $this->repo->upsertLocation(null, $clean);
        }
        if (empty($clean['qr_token'])) {
            $clean['qr_token'] = StaffManageSetup::randomToken();
        }
        return $this->repo->upsertLocation($id, $clean);
    }

    public function setLocationStatus(int $id, bool $isActive): bool
    {
        return $this->repo->setLocationStatus($id, $isActive);
    }

    public function deleteLocation(int $id): bool
    {
        return $this->repo->deleteLocation($id);
    }

    public function logActivity(?int $staffId, ?int $locationId, string $type, string $description, ?string $ip = null): bool
    {
        return $this->repo->insertActivity([
            'staff_id' => $staffId,
            'location_id' => $locationId,
            'activity_type' => $type,
            'activity_desc' => $description,
            'ip_address' => $ip
        ]);
    }

    public function getActivities(int $limit = 50, array $filters = []): array
    {
        return $this->repo->getActivities($limit, $filters);
    }

    public function authenticatePin(string $pin): ?array
    {
        return $this->repo->findStaffByPin($pin);
    }

    public function createPublicAttendance(int $staffId, int $locationId, array $gps, string $note = '', string $method = 'qr'): array
    {
        $payload = [
            'staff_id' => $staffId,
            'location_id' => $locationId,
            'check_in_time' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'check_out_time' => null,
            'check_in_lat' => $gps['lat'] ?? null,
            'check_in_lng' => $gps['lng'] ?? null,
            'check_out_lat' => null,
            'check_out_lng' => null,
            'check_in_source' => $method,
            'check_out_source' => null,
            'notes' => $note
        ];
        return $this->saveAttendance($payload);
    }

    public function validateQrToken(string $token): ?array
    {
        $locations = $this->repo->getLocations(false);
        foreach ($locations as $location) {
            if (hash_equals($location['qr_token'], $token)) {
                return $location;
            }
        }
        return null;
    }

    /**
     * Compute attendance status based on location geofence.
     */
    private function determineAttendanceStatus(array $payload, ?array $location): string
    {
        if (!$location) {
            return 'on_time';
        }
        $distance = Geo::distanceMeters(
            $payload['check_in_lat'],
            $payload['check_in_lng'],
            $location['latitude'] !== null ? (float)$location['latitude'] : null,
            $location['longitude'] !== null ? (float)$location['longitude'] : null
        );
        if ($distance !== null && (float)$location['radius_meters'] > 0 && $distance > (float)$location['radius_meters']) {
            return 'late';
        }
        return 'on_time';
    }

    private function normalizeAttendancePayload(array $payload): array
    {
        $checkInTime = $payload['check_in_time'] ?? (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $checkOutTime = $payload['check_out_time'] ?? null;
        return [
            'staff_id' => (int)($payload['staff_id'] ?? 0),
            'location_id' => !empty($payload['location_id']) ? (int)$payload['location_id'] : null,
            'check_in_time' => $checkInTime,
            'check_out_time' => $checkOutTime,
            'check_in_lat' => isset($payload['check_in_lat']) ? (float)$payload['check_in_lat'] : null,
            'check_in_lng' => isset($payload['check_in_lng']) ? (float)$payload['check_in_lng'] : null,
            'check_out_lat' => isset($payload['check_out_lat']) ? (float)$payload['check_out_lat'] : null,
            'check_out_lng' => isset($payload['check_out_lng']) ? (float)$payload['check_out_lng'] : null,
            'check_in_source' => $payload['check_in_source'] ?? 'manual',
            'check_out_source' => $payload['check_out_source'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'status' => $payload['status'] ?? 'on_time'
        ];
    }

    private function normalizeSchedulePayload(array $payload): array
    {
        return [
            'staff_id' => !empty($payload['staff_id']) ? (int)$payload['staff_id'] : null,
            'location_id' => !empty($payload['location_id']) ? (int)$payload['location_id'] : null,
            'shift_date' => $payload['shift_date'] ?? (new DateTimeImmutable())->format('Y-m-d'),
            'shift_start' => $payload['shift_start'] ?? '08:00:00',
            'shift_end' => $payload['shift_end'] ?? '16:00:00',
            'status' => $payload['status'] ?? 'planned',
            'notes' => $payload['notes'] ?? null
        ];
    }

    private function normalizeTodoPayload(array $payload): array
    {
        return [
            'task_title' => $payload['task_title'] ?? 'Untitled task',
            'task_description' => $payload['task_description'] ?? null,
            'assigned_to' => !empty($payload['assigned_to']) ? (int)$payload['assigned_to'] : null,
            'priority' => $payload['priority'] ?? 'medium',
            'status' => $payload['status'] ?? 'pending',
            'progress' => max(0, min(100, (int)($payload['progress'] ?? 0))),
            'due_date' => $payload['due_date'] ?? null,
            'is_team_task' => !empty($payload['is_team_task']) ? 1 : 0
        ];
    }

    private function normalizeLocationPayload(array $payload): array
    {
        return [
            'name' => $payload['name'] ?? 'Lokasi Baru',
            'description' => $payload['description'] ?? null,
            'latitude' => isset($payload['latitude']) && $payload['latitude'] !== '' ? (float)$payload['latitude'] : null,
            'longitude' => isset($payload['longitude']) && $payload['longitude'] !== '' ? (float)$payload['longitude'] : null,
            'radius_meters' => (int)($payload['radius_meters'] ?? 50),
            'qr_token' => $payload['qr_token'] ?? '',
            'is_active' => !empty($payload['is_active']) ? 1 : 0,
            'require_qr' => !empty($payload['require_qr']) ? 1 : 0,
            'color_hex' => $payload['color_hex'] ?? '#2563eb'
        ];
    }
}

/**
 * Simple factory to build service instances with lazy caching.
 */
final class StaffManageServiceFactory
{
    public static function make(mysqli $db): StaffManageService
    {
        static $service = null;
        if ($service === null) {
            $service = new StaffManageService(new StaffManageRepository($db));
        }
        return $service;
    }
}
