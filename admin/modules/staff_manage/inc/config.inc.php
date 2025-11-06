<?php
/**
 * Staff Manage module bootstrap.
 *
 * Loads SLiMS environment, checks privileges, prepares shared services, and
 * exposes helper variables used by all module pages.
 */

if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}

if (!defined('DB_ACCESS')) {
    define('DB_ACCESS', 'fa');
}

if (!defined('SB')) {
    require_once '../../../sysconfig.inc.php';
    require_once SB . 'admin/default/session.inc.php';
}

require_once LIB . 'ip_based_access.inc.php';
do_checkIP('smc');

require_once SB . 'admin/default/session_check.inc.php';
require_once LIB . 'modules.inc.php';

utility::loadSettings($dbs);

require_once __DIR__ . '/function.inc.php';
require_once __DIR__ . '/ui.inc.php';

use StaffManage\StaffManageServiceFactory;
use StaffManage\StaffManageSetup;

if (!defined('SM_MODULE_PATH')) {
    define('SM_MODULE_PATH', realpath(__DIR__ . '/..'));
}

if (!defined('SM_MODULE_URL')) {
    define('SM_MODULE_URL', MWB . 'staff_manage/');
}

if (!defined('SM_PUBLIC_URL')) {
    define('SM_PUBLIC_URL', SWB . 'public/staff_attendance/');
}

try {
    StaffManageSetup::bootstrap($dbs);
} catch (Throwable $bootstrapError) {
    utility::writeLogs(
        $dbs,
        'system',
        $_SESSION['uid'] ?? '0',
        'staff_manage',
        'Bootstrap error: ' . $bootstrapError->getMessage()
    );
}

$sm_module_path = 'staff_manage';

if (!function_exists('sm_staff_manage_sync_privileges')) {
    function sm_staff_manage_sync_privileges(mysqli $db, string $modulePath): void
    {
        if (!isset($_SESSION['uid'])) {
            return;
        }

        if (!isset($_SESSION['groups']) || !is_array($_SESSION['groups'])) {
            $userId = (int)$_SESSION['uid'];
            $userQuery = $db->query('SELECT `groups` FROM `user` WHERE user_id=' . $userId . ' LIMIT 1');
            if ($userQuery && $userQuery->num_rows > 0) {
                $serialized = $userQuery->fetch_row()[0];
                $groups = @unserialize($serialized);
                if (is_array($groups)) {
                    $_SESSION['groups'] = $groups;
                } else {
                    $_SESSION['groups'] = [];
                }
            } else {
                $_SESSION['groups'] = [];
            }
            if ($userQuery) {
                $userQuery->free();
            }
        }

        $groupIds = array_filter(array_map('intval', $_SESSION['groups'] ?? []));
        if (!$groupIds) {
            $groupIds = [1];
        }

        if (!isset($_SESSION['priv']) || !is_array($_SESSION['priv'])) {
            $_SESSION['priv'] = [];
        }

        $groupIdList = implode(',', $groupIds);
        $privQuery = $db->query(
            'SELECT ga.group_id, ga.r, ga.w, mdl.module_path
             FROM group_access AS ga
             LEFT JOIN mst_module AS mdl ON mdl.module_id = ga.module_id
             WHERE ga.group_id IN (' . $groupIdList . ')'
        );
        if ($privQuery) {
            while ($priv = $privQuery->fetch_assoc()) {
                $path = $priv['module_path'];
                if (!$path) {
                    continue;
                }
                if (!isset($_SESSION['priv'][$path]) || !is_array($_SESSION['priv'][$path])) {
                    $_SESSION['priv'][$path] = ['r' => false, 'w' => false, 'menus' => []];
                }
                if ((int)$priv['r'] === 1) {
                    $_SESSION['priv'][$path]['r'] = true;
                }
                if ((int)$priv['w'] === 1) {
                    $_SESSION['priv'][$path]['w'] = true;
                }
            }
            $privQuery->free();
        }

        if (!isset($_SESSION['priv'][$modulePath])) {
            $_SESSION['priv'][$modulePath] = ['r' => true, 'w' => false, 'menus' => []];
        }
    }
}

sm_staff_manage_sync_privileges($dbs, $sm_module_path);

$sm_can_read = utility::havePrivilege('staff_manage', 'r');
$sm_can_write = utility::havePrivilege('staff_manage', 'w');

if (isset($_SESSION['uid']) && (string)$_SESSION['uid'] === '1') {
    $sm_can_read = true;
    $sm_can_write = true;
}

if (!$sm_can_read) {
    die('<div class="errorBox">' . __('You are not authorized to view this section') . '</div>');
}

$sm_service = StaffManageServiceFactory::make($dbs);

// Common assets for every page.
$sm_common_css = [
    SM_MODULE_URL . 'assets/css/staff_manage.css'
];
$sm_common_js = [
    'https://cdn.jsdelivr.net/npm/chart.js',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    SM_MODULE_URL . 'assets/js/staff_manage.js'
];
