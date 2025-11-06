<?php
// key to authenticate
if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}

// main system configuration
require '../../../sysconfig.inc.php';
// start the session
require SB . 'admin/default/session.inc.php';
// IP based access limitation
require LIB . 'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-system');

require SB . 'admin/default/session_check.inc.php';

// privileges checking
$can_read = utility::havePrivilege('staff_manage', 'r');

if (!$can_read) {
    die('<div class="errorBox">' . __('You are not authorized to view this section') . '</div>');
}

// page title
$page_title = 'Staff Management - Setup';

$messages = [];

// Create staff_activity_log table
$table_log_sql = "CREATE TABLE IF NOT EXISTS `staff_activity_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `staff_name` varchar(255) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($dbs->query($table_log_sql)) {
    $messages[] = 'Table `staff_activity_log` created successfully or already exists.';

    // Check if dummy data exists
    $check_data_q = $dbs->query("SELECT COUNT(*) FROM staff_activity_log");
    $data_exists = $check_data_q->fetch_row()[0] > 0;

    if (!$data_exists) {
        $dummy_data_sql = "INSERT INTO `staff_activity_log` (`staff_id`, `staff_name`, `action`, `location`, `timestamp`) VALUES
        (1, 'Super Admin', 'Logged in', 'Main Library', '2025-10-27 08:00:00'),
        (2, 'Jane Doe', 'Checked out item B00001', 'Circulation Desk', '2025-10-27 09:15:00'),
        (1, 'Super Admin', 'Added new member: John Doe', 'Membership Desk', '2025-10-27 10:30:00'),
        (3, 'John Smith', 'Logged out', 'Main Library', '2025-10-27 11:00:00');";
        if ($dbs->query($dummy_data_sql)) {
            $messages[] = 'Dummy data inserted into `staff_activity_log`.';
        } else {
            $messages[] = 'Error inserting dummy data: ' . $dbs->error;
        }
    } else {
        $messages[] = 'Dummy data already exists in `staff_activity_log`.';
    }

} else {
    $messages[] = 'Error creating table `staff_activity_log`: ' . $dbs->error;
}

// Create staff_attendance table
$table_attendance_sql = "CREATE TABLE IF NOT EXISTS `staff_attendance` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `staff_name` varchar(255) DEFAULT NULL,
  `check_in_time` datetime DEFAULT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Present',
  PRIMARY KEY (`attendance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($dbs->query($table_attendance_sql)) {
    $messages[] = 'Table `staff_attendance` created successfully or already exists.';

    // Check if dummy data exists
    $check_data_q = $dbs->query("SELECT COUNT(*) FROM staff_attendance");
    $data_exists = $check_data_q->fetch_row()[0] > 0;

    if (!$data_exists) {
        $dummy_data_sql = "INSERT INTO `staff_attendance` (`staff_id`, `staff_name`, `check_in_time`, `check_out_time`, `location`, `status`) VALUES
        (1, 'Super Admin', '2025-10-27 08:00:00', '2025-10-27 17:00:00', 'Main Library', 'Present'),
        (2, 'Jane Doe', '2025-10-27 09:15:00', NULL, 'Circulation Desk', 'Present'),
        (3, 'John Smith', '2025-10-26 08:05:00', '2025-10-26 17:00:00', 'Main Library', 'Present');";
        if ($dbs->query($dummy_data_sql)) {
            $messages[] = 'Dummy data inserted into `staff_attendance`.';
        } else {
            $messages[] = 'Error inserting dummy data for attendance: ' . $dbs->error;
        }
    } else {
        $messages[] = 'Dummy data already exists in `staff_attendance`.';
    }

} else {
    $messages[] = 'Error creating table `staff_attendance`: ' . $dbs->error;
}

// Create staff_locations table
$table_locations_sql = "CREATE TABLE IF NOT EXISTS `staff_locations` (
  `location_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `latitude` decimal(10, 8) NOT NULL,
  `longitude` decimal(11, 8) NOT NULL,
  `radius` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($dbs->query($table_locations_sql)) {
    $messages[] = 'Table `staff_locations` created successfully or already exists.';

    // Check if dummy data exists
    $check_data_q = $dbs->query("SELECT COUNT(*) FROM staff_locations");
    $data_exists = $check_data_q->fetch_row()[0] > 0;

    if (!$data_exists) {
        $dummy_data_sql = "INSERT INTO `staff_locations` (`name`, `latitude`, `longitude`, `radius`, `is_active`) VALUES
        ('Main Library', -7.97700000, 112.63402500, 100, 1),
        ('Circulation Desk', -7.97710000, 112.63412500, 50, 1),
        ('Processing Room', -7.97720000, 112.63422500, 50, 0);";
        if ($dbs->query($dummy_data_sql)) {
            $messages[] = 'Dummy data inserted into `staff_locations`.';
        } else {
            $messages[] = 'Error inserting dummy data for locations: ' . $dbs->error;
        }
    } else {
        $messages[] = 'Dummy data already exists in `staff_locations`.';
    }

} else {
    $messages[] = 'Error creating table `staff_locations`: ' . $dbs->error;
}

// Create staff_schedules table
$table_schedules_sql = "CREATE TABLE IF NOT EXISTS `staff_schedules` (
  `schedule_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `staff_name` varchar(255) DEFAULT NULL,
  `day_of_week` tinyint(1) NOT NULL, 
  `shift_name` varchar(100) NOT NULL,
  `location` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`schedule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($dbs->query($table_schedules_sql)) {
    $messages[] = 'Table `staff_schedules` created successfully or already exists.';

    // Check if dummy data exists
    $check_data_q = $dbs->query("SELECT COUNT(*) FROM staff_schedules");
    $data_exists = $check_data_q->fetch_row()[0] > 0;

    if (!$data_exists) {
        $dummy_data_sql = "INSERT INTO `staff_schedules` (`staff_id`, `staff_name`, `day_of_week`, `shift_name`, `location`, `start_time`, `end_time`, `is_active`) VALUES
        (1, 'Super Admin', 1, 'Morning Shift', 'Receptionist', '08:00:00', '12:00:00', 1),
        (2, 'Jane Doe', 1, 'Morning Shift', 'Processing', '08:00:00', '12:00:00', 1),
        (3, 'John Smith', 2, 'Afternoon Shift', 'Reference', '12:00:00', '16:00:00', 1);";
        if ($dbs->query($dummy_data_sql)) {
            $messages[] = 'Dummy data inserted into `staff_schedules`.';
        } else {
            $messages[] = 'Error inserting dummy data for schedules: ' . $dbs->error;
        }
    } else {
        $messages[] = 'Dummy data already exists in `staff_schedules`.';
    }

} else {
    $messages[] = 'Error creating table `staff_schedules`: ' . $dbs->error;
}

?>

<div class="menuBox">
    <div class="menuBoxInner systemIcon">
        <div class="per_title">
            <h2><?php echo __('Module Setup'); ?></h2>
        </div>
        <div class="sub_section">
            <h3>Setup Log:</h3>
            <ul>
                <?php foreach ($messages as $message) : ?>
                    <li><?php echo $message; ?></li>
                <?php endforeach; ?>
            </ul>
            <p>Setup is complete. You can now use the module features.</p>
        </div>
    </div>
</div>