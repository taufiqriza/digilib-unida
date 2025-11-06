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
$can_write = utility::havePrivilege('staff_manage', 'w');

if (!$can_read) {
    die('<div class="errorBox">' . __('You are not authorized to view this section') . '</div>');
}

// page title
$page_title = 'Staff Attendance';

// handle form submission
if (isset($_POST['save'])) {
    if (!$can_write) {
        die('<div class="errorBox">' . __('You are not authorized to perform this action') . '</div>');
    }

    $staff_id = $dbs->escape_string($_POST['staff_id']);
    $staff_name = $dbs->escape_string($_POST['staff_name']);
    $check_in_time = $dbs->escape_string($_POST['check_in_time']);
    $check_out_time = $dbs->escape_string($_POST['check_out_time']);
    $location = $dbs->escape_string($_POST['location']);
    $status = $dbs->escape_string($_POST['status']);

    $sql = "INSERT INTO staff_attendance (staff_id, staff_name, check_in_time, check_out_time, location, status) VALUES ('$staff_id', '$staff_name', '$check_in_time', '$check_out_time', '$location', '$status')";
    if ($dbs->query($sql)) {
        utility::jsToastr('Success', __('Attendance record saved successfully'), 'success');
    } else {
        utility::jsToastr('Error', __('Failed to save attendance record'), 'error');
    }
}

// build query
$sql = "SELECT * FROM staff_attendance";
$where = [];
if (isset($_GET['date']) && $_GET['date'] != '') {
    $date = $dbs->escape_string($_GET['date']);
    $where[] = "DATE(check_in_time) = '$date'";
}
if (isset($_GET['location']) && $_GET['location'] != '') {
    $location = $dbs->escape_string($_GET['location']);
    $where[] = "location = '$location'";
}
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY check_in_time DESC";

$attendance_q = $dbs->query($sql);

?>

<style>
    .attendance-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 10px;
    }
    .attendance-table th {
        background-color: #f9fafb;
        padding: 12px 15px;
        text-align: left;
        font-weight: 600;
        color: #4b5563;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
    }
    .attendance-table td {
        background-color: #ffffff;
        padding: 15px;
        border-bottom: 1px solid #e5e7eb;
    }
    .attendance-table tbody tr {
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .badge {
        display: inline-block;
        padding: .25em .4em;
        font-size: 75%;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: .25rem;
    }
    .badge-success { color: #fff; background-color: #10B981; }
    .badge-warning { color: #fff; background-color: #F59E0B; }
    .badge-danger { color: #fff; background-color: #EF4444; }
    .filter-form {
        margin-bottom: 20px;
    }
</style>

<div class="menuBox">
    <div class="menuBoxInner systemIcon">
        <div class="per_title">
            <h2><?php echo __('Staff Attendance'); ?></h2>
        </div>
        <div class="sub_section">

            <form action="" method="get" class="filter-form form-inline">
                <input type="hidden" name="mod" value="staff_manage">
                <input type="hidden" name="view" value="attendance">
                <div class="form-group mb-2">
                    <label for="date" class="sr-only"><?php echo __('Date'); ?></label>
                    <input type="date" name="date" id="date" class="form-control" value="<?php echo isset($_GET['date']) ? $_GET['date'] : ''; ?>">
                </div>
                <div class="form-group mx-sm-3 mb-2">
                    <label for="location" class="sr-only"><?php echo __('Location'); ?></label>
                    <input type="text" name="location" id="location" class="form-control" placeholder="<?php echo __('Location'); ?>" value="<?php echo isset($_GET['location']) ? $_GET['location'] : ''; ?>">
                </div>
                <button type="submit" class="btn btn-primary mb-2"><?php echo __('Filter'); ?></button>
            </form>

            <?php if ($can_write) : ?>
            <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#addModal"><?php echo __('Add New Record'); ?></button>
            <?php endif; ?>

            <table class="attendance-table">
                <thead>
                    <tr>
                        <th><?php echo __('Staff Name'); ?></th>
                        <th><?php echo __('Check-in'); ?></th>
                        <th><?php echo __('Check-out'); ?></th>
                        <th><?php echo __('Location'); ?></th>
                        <th><?php echo __('Status'); ?></th>
                        <th><?php echo __('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($attendance_q->num_rows > 0) : ?>
                        <?php while ($rec = $attendance_q->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rec['staff_name']); ?></td>
                                <td><?php echo $rec['check_in_time'] ? date('d M Y, H:i', strtotime($rec['check_in_time'])) : '-'; ?></td>
                                <td><?php echo $rec['check_out_time'] ? date('d M Y, H:i', strtotime($rec['check_out_time'])) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($rec['location']); ?></td>
                                <td><span class="badge badge-<?php echo strtolower($rec['status']) == 'present' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($rec['status']); ?></span></td>
                                <td>
                                    <?php if ($can_write) : ?>
                                    <a href="#" class="btn btn-sm btn-warning"><?php echo __('Edit'); ?></a>
                                    <a href="#" class="btn btn-sm btn-danger"><?php echo __('Delete'); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6" style="text-align: center;"><?php echo __('No attendance records found.'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="addModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addModalLabel"><?php echo __('Add New Attendance Record'); ?></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form action="" method="post">
          <div class="modal-body">
              <div class="form-group">
                  <label for="staff_id"><?php echo __('Staff ID'); ?></label>
                  <input type="text" name="staff_id" id="staff_id" class="form-control" required>
              </div>
              <div class="form-group">
                  <label for="staff_name"><?php echo __('Staff Name'); ?></label>
                  <input type="text" name="staff_name" id="staff_name" class="form-control" required>
              </div>
              <div class="form-group">
                  <label for="check_in_time"><?php echo __('Check-in Time'); ?></label>
                  <input type="datetime-local" name="check_in_time" id="check_in_time" class="form-control">
              </div>
              <div class="form-group">
                  <label for="check_out_time"><?php echo __('Check-out Time'); ?></label>
                  <input type="datetime-local" name="check_out_time" id="check_out_time" class="form-control">
              </div>
              <div class="form-group">
                  <label for="location"><?php echo __('Location'); ?></label>
                  <input type="text" name="location" id="location" class="form-control">
              </div>
              <div class="form-group">
                  <label for="status"><?php echo __('Status'); ?></label>
                  <select name="status" id="status" class="form-control">
                      <option value="Present">Present</option>
                      <option value="Absent">Absent</option>
                      <option value="Late">Late</option>
                  </select>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo __('Close'); ?></button>
            <button type="submit" name="save" class="btn btn-primary"><?php echo __('Save Record'); ?></button>
          </div>
      </form>
    </div>
  </div>
</div>