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
$page_title = 'Location Settings';

// handle form submission
if (isset($_POST['save'])) {
    if (!$can_write) {
        die('<div class="errorBox">' . __('You are not authorized to perform this action') . '</div>');
    }

    $location_id = (int)$_POST['location_id'];
    $name = $dbs->escape_string($_POST['name']);
    $latitude = $dbs->escape_string($_POST['latitude']);
    $longitude = $dbs->escape_string($_POST['longitude']);
    $radius = (int)$_POST['radius'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($location_id > 0) {
        // update
        $sql = "UPDATE staff_locations SET name='$name', latitude='$latitude', longitude='$longitude', radius=$radius, is_active=$is_active WHERE location_id=$location_id";
        $action = 'updated';
    } else {
        // insert
        $sql = "INSERT INTO staff_locations (name, latitude, longitude, radius, is_active) VALUES ('$name', '$latitude', '$longitude', $radius, $is_active)";
        $action = 'saved';
    }

    if ($dbs->query($sql)) {
        utility::jsToastr('Success', __('Location ' . $action . ' successfully'), 'success');
    } else {
        utility::jsToastr('Error', __('Failed to ' . $action . ' location'), 'error');
    }
}

// handle delete
if (isset($_GET['delete'])) {
    if (!$can_write) {
        die('<div class="errorBox">' . __('You are not authorized to perform this action') . '</div>');
    }

    $location_id = (int)$_GET['delete'];
    $sql = "DELETE FROM staff_locations WHERE location_id=$location_id";
    if ($dbs->query($sql)) {
        utility::jsToastr('Success', __('Location deleted successfully'), 'success');
    } else {
        utility::jsToastr('Error', __('Failed to delete location'), 'error');
    }
    // redirect to avoid re-deleting on refresh
    echo '<script>window.location.href = ' . "'" . MWB . 'staff_manage/location.php' . "'" . ';</script>';
    exit;
}

// get locations
$locations_q = $dbs->query("SELECT * FROM staff_locations ORDER BY name");
$locations = [];
while ($loc = $locations_q->fetch_assoc()) {
    $locations[] = $loc;
}

?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

<style>
    #map {
        height: 400px;
        border-radius: 0.5rem;
        margin-bottom: 20px;
    }
    .location-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 10px;
    }
    .location-table th, .location-table td {
        padding: 12px 15px;
    }
    .location-table th {
        background-color: #f9fafb;
        font-weight: 600;
        color: #4b5563;
        text-transform: uppercase;
        font-size: 0.75rem;
    }
    .location-table td {
        background-color: #ffffff;
    }
    .location-table tbody tr {
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        border-radius: 0.5rem;
    }
    .badge-active { background-color: #10B981; color: #fff; }
    .badge-inactive { background-color: #6B7280; color: #fff; }
</style>

<div class="menuBox">
    <div class="menuBoxInner systemIcon">
        <div class="per_title">
            <h2><?php echo __('Location Settings'); ?></h2>
        </div>
        <div class="sub_section">

            <?php if ($can_write) : ?>
            <button type="button" class="btn btn-primary mb-3" onclick="openModal()"><?php echo __('Add New Location'); ?></button>
            <?php endif; ?>

            <div id="map"></div>

            <table class="location-table">
                <thead>
                    <tr>
                        <th><?php echo __('Name'); ?></th>
                        <th><?php echo __('Coordinates (Lat, Long)'); ?></th>
                        <th><?php echo __('Radius (m)'); ?></th>
                        <th><?php echo __('Status'); ?></th>
                        <th><?php echo __('Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locations as $loc) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($loc['name']); ?></td>
                            <td><?php echo $loc['latitude'] . ', ' . $loc['longitude']; ?></td>
                            <td><?php echo $loc['radius']; ?></td>
                            <td><span class="badge <?php echo $loc['is_active'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $loc['is_active'] ? __('Active') : __('Inactive'); ?></span></td>
                            <td>
                                <?php if ($can_write) : ?>
                                <button class="btn btn-sm btn-warning" onclick='openModal(<?php echo json_encode($loc); ?>)'><?php echo __('Edit'); ?></button>
                                <a href="?delete=<?php echo $loc['location_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('<?php echo __("Are you sure you want to delete this location?"); ?>')"><?php echo __('Delete'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Location Modal -->
<div class="modal fade" id="locationModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form action="" method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle"><?php echo __('Add New Location'); ?></h5>
          <button type="button" class="close" data-dismiss="modal">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="location_id" id="location_id" value="0">
          <div class="form-group">
            <label><?php echo __('Location Name'); ?></label>
            <input type="text" name="name" id="name" class="form-control" required>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label><?php echo __('Latitude'); ?></label>
              <input type="text" name="latitude" id="latitude" class="form-control" required>
            </div>
            <div class="form-group col-md-6">
              <label><?php echo __('Longitude'); ?></label>
              <input type="text" name="longitude" id="longitude" class="form-control" required>
            </div>
          </div>
          <div class="form-group">
            <label><?php echo __('Radius (meters)'); ?></label>
            <input type="number" name="radius" id="radius" class="form-control" required>
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
    var map = L.map('map').setView([-7.977, 112.634], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var locations = <?php echo json_encode($locations); ?>;
    locations.forEach(function(loc) {
        if (loc.is_active) {
            var marker = L.marker([loc.latitude, loc.longitude]).addTo(map);
            marker.bindPopup('<b>' + loc.name + '</b><br>Radius: ' + loc.radius + 'm');
            var circle = L.circle([loc.latitude, loc.longitude], {
                color: '#3B82F6',
                fillColor: '#3B82F6',
                fillOpacity: 0.2,
                radius: parseInt(loc.radius)
            }).addTo(map);
        }
    });

    function openModal(data = null) {
        if (data) {
            $('#modalTitle').text('<?php echo __("Edit Location"); ?>');
            $('#location_id').val(data.location_id);
            $('#name').val(data.name);
            $('#latitude').val(data.latitude);
            $('#longitude').val(data.longitude);
            $('#radius').val(data.radius);
            $('#is_active').prop('checked', data.is_active == 1);
        } else {
            $('#modalTitle').text('<?php echo __("Add New Location"); ?>');
            $('#location_id').val(0);
            $('#name').val('');
            $('#latitude').val('');
            $('#longitude').val('');
            $('#radius').val('');
            $('#is_active').prop('checked', true);
        }
        $('#locationModal').modal('show');
    }
</script>