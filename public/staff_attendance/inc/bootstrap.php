<?php
/**
 * Public attendance bootstrap shared across all public pages.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../..' . '/sysconfig.inc.php';
require_once __DIR__ . '/../../../admin/modules/staff_manage/inc/function.inc.php';

use StaffManage\StaffManageServiceFactory;
use StaffManage\StaffManageSetup;

utility::loadSettings($dbs);

StaffManageSetup::bootstrap($dbs);

$pa_service = StaffManageServiceFactory::make($dbs);

if (!defined('PA_ASSET_URL')) {
    define('PA_ASSET_URL', SWB . 'public/staff_attendance/assets/');
}

if (!defined('PA_SESSION_KEY')) {
    define('PA_SESSION_KEY', 'sm_public_staff');
}
