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
$page_title = 'Duty Schedule';

// handle form submission
if (isset($_POST['save'])) {
    if (!$can_write) {
        die('<div class="errorBox">' . __('You are not authorized to perform this action') . '</div>');
    }

    $schedule_id = (int)$_POST['schedule_id'];
    $staff_id = (int)$_POST['staff_id'];
    $staff_name = $dbs->escape_string($_POST['staff_name']);
    $day_of_week = (int)$_POST['day_of_week'];
    $shift_name = $dbs->escape_string($_POST['shift_name']);
    $location = $dbs->escape_string($_POST['location']);
    $start_time = $dbs->escape_string($_POST['start_time']);
    $end_time = $dbs->escape_string($_POST['end_time']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($schedule_id > 0) {
        // update
        $sql = "UPDATE staff_schedules SET staff_id=$staff_id, staff_name='$staff_name', day_of_week=$day_of_week, shift_name='$shift_name', location='$location', start_time='$start_time', end_time='$end_time', is_active=$is_active WHERE schedule_id=$schedule_id";
        $action = 'updated';
    } else {
        // insert
        $sql = "INSERT INTO staff_schedules (staff_id, staff_name, day_of_week, shift_name, location, start_time, end_time, is_active) VALUES ($staff_id, '$staff_name', $day_of_week, '$shift_name', '$location', '$start_time', '$end_time', $is_active)";
        $action = 'saved';
    }

    if ($dbs->query($sql)) {
        utility::jsToastr('Success', __('Schedule ' . $action . ' successfully'), 'success');
    } else {
        utility::jsToastr('Error', __('Failed to ' . $action . ' schedule'), 'error');
    }
}

// handle delete
if (isset($_GET['delete'])) {
    if (!$can_write) {
        die('<div class="errorBox">' . __('You are not authorized to perform this action') . '</div>');
    }

    $schedule_id = (int)$_GET['delete'];
    $sql = "DELETE FROM staff_schedules WHERE schedule_id=$schedule_id";
    if ($dbs->query($sql)) {
        utility::jsToastr('Success', __('Schedule deleted successfully'), 'success');
    } else {
        utility::jsToastr('Error', __('Failed to delete schedule'), 'error');
    }
    // redirect to avoid re-deleting on refresh
    echo '<script>window.location.href = ' . "'" . MWB . 'staff_manage/schedule.php' . "'" . ';</script>';
    exit;
}

// get schedules
$schedules_q = $dbs->query("SELECT * FROM staff_schedules ORDER BY day_of_week, start_time");
$schedules = [];
while ($sch = $schedules_q->fetch_assoc()) {
    $schedules[$sch['day_of_week']][] = $sch;
}

$days_of_week = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

?>

<style>
    .schedule-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
    }
    .day-column {
        background-color: #fff;
        border-radius: 0.5rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    }
    .day-header {
        background-color: #f9fafb;
        padding: 12px 15px;
        font-weight: 600;
        color: #1f2937;
        border-bottom: 1px solid #e5e7eb;
        border-top-left-radius: 0.5rem;
        border-top-right-radius: 0.5rem;
    }
    .schedule-card {
        padding: 15px;
        border-bottom: 1px solid #e5e7eb;
    }
    .schedule-card:last-child {
        border-bottom: none;
    }
    .schedule-time {
        font-weight: 600;
        font-size: 0.9rem;
    }
    .schedule-details {
        font-size: 0.85rem;
        color: #4b5563;
    }
</style>

<div class="menuBox">
    <div class="menuBoxInner systemIcon">
        <div class="per_title">
            <h2><?php echo __('Duty Schedule'); ?></h2>
        </div>
        <div class="sub_section">

            <?php if ($can_write) : ?>
            <button type="button" class="btn btn-primary mb-3" onclick="openModal()"><?php echo __('Add New Schedule'); ?></button>
            <?php endif; ?>

            <div class="schedule-grid">
                <?php foreach ($days_of_week as $day_num => $day_name) : ?>
                    <div class="day-column">
                        <div class="day-header"><?php echo __($day_name); ?></div>
                        <?php if (isset($schedules[$day_num])) : ?>
                            <?php foreach ($schedules[$day_num] as $sch) : ?>
                                <div class="schedule-card">
                                    <div class="schedule-time"><?php echo date('H:i', strtotime($sch['start_time'])) . ' - ' . date('H:i', strtotime($sch['end_time'])); ?></div>
                                    <div class="schedule-details">
                                        <strong><?php echo htmlspecialchars($sch['staff_name']); ?></strong><br>
                                        <?php echo htmlspecialchars($sch['shift_name']); ?> at <?php echo htmlspecialchars($sch['location']); ?><br>
                                        <span class="badge <?php echo $sch['is_active'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $sch['is_active'] ? __('Active') : __('Inactive'); ?></span>
                                    </div>
                                    <?php if ($can_write) : ?>
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-warning" onclick='openModal(<?php echo json_encode($sch); ?>)'><?php echo __('Edit'); ?></button>
                                        <a href="?delete=<?php echo $sch['schedule_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('<?php echo __("Are you sure you want to delete this schedule?"); ?>')"><?php echo __('Delete'); ?></a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <div class="schedule-card text-muted"><?php echo __('No schedules for today.'); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form action="" method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle"><?php echo __('Add New Schedule'); ?></h5>
          <button type="button" class="close" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="schedule_id" id="schedule_id" value="0">
          <div class="form-group">
            <label><?php echo __('Staff ID'); ?></label>
            <input type="text" name="staff_id" id="staff_id" class="form-control" required>
          </div>
          <div class="form-group">
            <label><?php echo __('Staff Name'); ?></label>
            <input type="text" name="staff_name" id="staff_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label><?php echo __('Day of Week'); ?></label>
            <select name="day_of_week" id="day_of_week" class="form-control" required>
                <?php foreach ($days_of_week as $day_num => $day_name) : ?>
                    <option value="<?php echo $day_num; ?>"><?php echo __($day_name); ?></option>
                <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label><?php echo __('Shift Name'); ?></label>
            <input type="text" name="shift_name" id="shift_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label><?php echo __('Location'); ?></label>
            <input type="text" name="location" id="location" class="form-control" required>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label><?php echo __('Start Time'); ?></label>
              <input type="time" name="start_time" id="start_time" class="form-control" required>
            </div>
            <div class="form-group col-md-6">
              <label><?php echo __('End Time'); ?></label>
              <input type="time" name="end_time" id="end_time" class="form-control" required>
            </div>
          </div>
          <div class="form-check">
            <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1">
            <label class="form-check-label"><?php echo __('Is Active'); ?></label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo __('Close'); ?></button>
          <button type="submit" name="save" class="btn btn-primary"><?php echo __('Save'); ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
    function openModal(data = null) {
        if (data) {
            $('#modalTitle').text('<?php echo __("Edit Schedule"); ?>');
            $('#schedule_id').val(data.schedule_id);
            $('#staff_id').val(data.staff_id);
            $('#staff_name').val(data.staff_name);
            $('#day_of_week').val(data.day_of_week);
            $('#shift_name').val(data.shift_name);
            $('#location').val(data.location);
            $('#start_time').val(data.start_time);
            $('#end_time').val(data.end_time);
            $('#is_active').prop('checked', data.is_active == 1);
        } else {
            $('#modalTitle').text('<?php echo __("Add New Schedule"); ?>');
            $('#schedule_id').val(0);
            $('#staff_id').val('');
            $('#staff_name').val('');
            $('#day_of_week').val(1);
            $('#shift_name').val('');
            $('#location').val('');
            $('#start_time').val('');
            $('#end_time').val('');
            $('#is_active').prop('checked', true);
        }
        $('#scheduleModal').modal('show');
    }
</script>