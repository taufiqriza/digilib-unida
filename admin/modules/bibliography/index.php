<!-- //
// Perubahan: Badge Last Update kini mengambil data dari kolom yang benar (kolom terakhir pada datagrid)
// dan formatnya selalu tanggal-bulan-tahun jam:menit.
// Untuk revert, kembalikan mapping index kolom badge ke versi sebelumnya.
// Last update: 2025-10-26, by Copilot -->
<?php
/**
 * Copyright (C) 2007,2008,2009,2010  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/* Bibliography Management section */

// key to authenticate
if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}

#use SLiMS\AdvancedLogging;
use SLiMS\AlLibrarian;
use SLiMS\Plugins;

// key to get full database access
define('DB_ACCESS', 'fa');

if (!defined('SB')) {
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
    require SB . 'admin/default/session.inc.php';
}
// IP based access limitation
require LIB . 'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-bibliography');

require SB . 'admin/default/session_check.inc.php';
require SIMBIO . 'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO . 'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO . 'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO . 'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO . 'simbio_DB/simbio_dbop.inc.php';
require SIMBIO . 'simbio_FILE/simbio_file_upload.inc.php';
require MDLBS . 'system/biblio_indexer.inc.php';

// privileges checking
$can_read = utility::havePrivilege('bibliography', 'r');
$can_write = utility::havePrivilege('bibliography', 'w');

if (!$can_read) {
    die('<div class="errorBox">' . __('You are not authorized to view this section') . '</div>');
}

// execute registered hook
Plugins::getInstance()->execute(Plugins::BIBLIOGRAPHY_INIT);

// load settings
utility::loadSettings($dbs);

$in_pop_up = false;
// check if we are inside pop-up window
if (isset($_GET['inPopUp'])) {
    $in_pop_up = true;
}

if (!function_exists('getimagesizefromstring')) {
    function getimagesizefromstring($string_data)
    {
        $uri = 'data://application/octet-stream;base64,' . base64_encode($string_data);
        return getimagesize($uri);
    }
}

// RDA Content, Media and Carrier
$rda_cmc = array('content' => 'Content Type', 'media' => 'Media Type', 'carrier' => 'Carrier Type');

/* REMOVE IMAGE */
if (isset($_POST['removeImage']) && isset($_POST['bimg']) && isset($_POST['img'])) {
    // validate post image
    $biblio_id = utility::filterData('bimg', 'post', true, true, true);
    $image_name = utility::filterData('img', 'post', true, true, true);

    $query_image = $dbs->query("SELECT biblio_id FROM biblio WHERE biblio_id='{$biblio_id}' AND image='{$image_name}'");
    if ($query_image->num_rows > 0) {
        $_delete = $dbs->query(sprintf('UPDATE biblio SET image=NULL WHERE biblio_id=%d', $biblio_id));
        $_delete2 = $dbs->query(sprintf('UPDATE search_biblio SET image=NULL WHERE biblio_id=%d', $biblio_id));
        if ($_delete) {
            $postImage = stripslashes($_POST['img']);
            $postImage = str_replace('/', '', $postImage);
            @unlink(sprintf(IMGBS . 'docs/%s', $postImage));
            utility::jsToastr('Bibliography', str_replace('{imageFilename}', $_POST['img'], __('{imageFilename} successfully removed!')), 'success');
            // exit('<script type="text/javascript">$(\'#biblioImage, #imageFilename\').remove();</script>');
            exit('<img src="../lib/minigalnano/createthumb.php?filename=images/default/image.png&width=130" class="img-fluid rounded" alt="">');
        }
    }
    exit();
}
/* RECORD OPERATION */
if (isset($_POST['saveData']) AND $can_read AND $can_write) {
    if (!simbio_form_maker::isTokenValid()) {
        utility::jsToastr('Bibliography', __('Invalid form submission token!'), 'error');
        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'system', 'Invalid form submission token, might be a CSRF attack from ' . $_SERVER['REMOTE_ADDR']);
        exit();
    }
    $title = trim(strip_tags($_POST['title']));
    // check form validity
    if (empty($title)) {
        utility::jsToastr('Bibliography', __('Title can not be empty'), 'error');
        exit();
    } else {
        // include custom fields file
        if (file_exists(MDLBS . 'bibliography/custom_fields.inc.php')) {
            include MDLBS . 'bibliography/custom_fields.inc.php';
        }

        // create biblio_indexer class instance
        $indexer = new biblio_indexer($dbs);

        /**
         * Custom fields
         */
        if (isset($biblio_custom_fields)) {
            if (is_array($biblio_custom_fields) && $biblio_custom_fields) {
                foreach ($biblio_custom_fields as $fid => $cfield) {
                    // custom field data
                    $cf_dbfield = $cfield['dbfield'];
                    if (isset($_POST[$cf_dbfield])) {
                        if (is_array($_POST[$cf_dbfield])) {
                            foreach ($_POST[$cf_dbfield] as $value) {
                                $arr[$value] = $value;
                            }
                            $custom_data[$cf_dbfield] = serialize($arr);
                        } else {
                            $cf_val = $dbs->escape_string(strip_tags(trim($_POST[$cf_dbfield]), $sysconf['content']['allowable_tags']));
                            if ($cfield['type'] == 'numeric' && (!is_numeric($cf_val) && $cf_val != '')) {
                                utility::jsToastr(__('Bibliography'), sprintf(__('Field %s only number for allowed'), $cfield['label']), 'error');
                                exit();
                            } elseif ($cfield['type'] == 'date' && $cf_val == '') {
                                utility::jsToastr(__('Bibliography'), sprintf(__('Field %s is date format, empty not allowed'), $cfield['label']), 'error');
                                exit();
                            }
                            $custom_data[$cf_dbfield] = $cf_val;
                        }
                    } else {
                        $custom_data[$cf_dbfield] = serialize(array());
                    }
                }
            }
        }

        // Register advance custom field data
        Plugins::getInstance()->execute(Plugins::BIBLIOGRAPHY_CUSTOM_FIELD_DATA, ['custom_data' => &$custom_data]);

        $data['title'] = $dbs->escape_string($title);
        /* modified by hendro */
        $data['sor'] = trim($dbs->escape_string(strip_tags($_POST['sor'])));
        /* end of modification */
        $data['edition'] = trim($dbs->escape_string(strip_tags($_POST['edition'])));
        $data['gmd_id'] = $_POST['gmdID'];
        $data['isbn_issn'] = trim($dbs->escape_string(strip_tags($_POST['isbn_issn'])));

        $class = str_ireplace('NEW:', '', trim(strip_tags($_POST['class'])));
        $data['classification'] = trim($dbs->escape_string(strip_tags($class)));
        $data['uid'] = $_SESSION['uid'];

        // check publisher
        // echo stripos($_POST['publisherID'], 'NEW:');
        if (stripos($_POST['publisherID'], 'NEW:') === 0) {
            $new_publisher = str_ireplace('NEW:', '', trim(strip_tags($_POST['publisherID'])));
            $new_id = utility::getID($dbs, 'mst_publisher', 'publisher_id', 'publisher_name', $new_publisher);
            $data['publisher_id'] = $new_id;
        } else if (intval($_POST['publisherID']) > 0) {
            $data['publisher_id'] = intval($_POST['publisherID']);
        }
        
        $data['publish_year'] = trim($dbs->escape_string(strip_tags($_POST['year'])));
        $data['collation'] = trim($dbs->escape_string(strip_tags($_POST['collation'])));
        $data['series_title'] = trim($dbs->escape_string(strip_tags($_POST['seriesTitle'])));
        $data['call_number'] = trim($dbs->escape_string(strip_tags($_POST['callNumber'])));
        $data['language_id'] = trim($dbs->escape_string(strip_tags($_POST['languageID'])));
        // check place
        if (stripos($_POST['placeID'], 'NEW:') === 0) {
            $new_place = str_ireplace('NEW:', '', trim(strip_tags($_POST['placeID'])));
            $new_id = utility::getID($dbs, 'mst_place', 'place_id', 'place_name', $new_place);
            $data['publish_place_id'] = $new_id;
        } else if (intval($_POST['placeID']) > 0) {
            $data['publish_place_id'] = intval($_POST['placeID']);
        }

        $data['notes'] = trim($dbs->escape_string(strip_tags($_POST['notes'], '<br><p><div><span><i><em><strong><b><code>s')));
        $data['opac_hide'] = ($_POST['opacHide'] == '0') ? 'literal{0}' : '1';
        $data['promoted'] = ($_POST['promote'] == '0') ? 'literal{0}' : '1';
        // labels
        $arr_label = array();
        if (!empty($_POST['labels'])) {
            foreach ($_POST['labels'] as $label) {
                if (trim($label) != '') {
                    $arr_label[] = array($label, isset($_POST['label_urls'][$label]) ? $_POST['label_urls'][$label] : null);
                }
            }
        }

        $data['labels'] = $arr_label ? serialize($arr_label) : 'literal{NULL}';
        $data['frequency_id'] = ($_POST['frequencyID'] == '0') ? 'literal{0}' : (integer)$_POST['frequencyID'];
        $data['spec_detail_info'] = trim($dbs->escape_string(strip_tags($_POST['specDetailInfo'])));

        // RDA Content, Media anda Carrier Type
        foreach ($rda_cmc as $cmc => $cmc_name) {
            if (isset($_POST[$cmc . 'TypeID']) && $_POST[$cmc . 'TypeID'] <> 0) {
                $data[$cmc . '_type_id'] = filter_input(INPUT_POST, $cmc . 'TypeID', FILTER_SANITIZE_NUMBER_INT);
            }
        }

        $data['input_date'] = date('Y-m-d H:i:s');
        $data['last_update'] = date('Y-m-d H:i:s');

        // image uploading
        if (!empty($_FILES['image']) AND $_FILES['image']['size']) {
            // create upload object
            $image_upload = new simbio_file_upload();
            $image_upload->setAllowableFormat($sysconf['allowed_images']);
            $image_upload->setMaxSize($sysconf['max_image_upload'] * 1024);
            $image_upload->setUploadDir(IMGBS . 'docs');

            $img_title = $data['title'].'_'.date("YmdHis");
            if(strlen($data['title']) > 70){
                $img_title = substr($data['title'], 0, 70).'_'.date("YmdHis");
            }

            $new_filename = strtolower('cover_'. preg_replace("/[^a-zA-Z0-9]+/", "-", $img_title));
            // upload the file and change all space characters to underscore
            $img_upload_status = $image_upload->doUpload('image', $new_filename);
            if ($img_upload_status == UPLOAD_SUCCESS) {
                $data['image'] = $dbs->escape_string($image_upload->new_filename);
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', $_SESSION['realname'] . ' upload image file ' . $image_upload->new_filename);
                utility::jsToastr('Bibliography', __('Image Uploaded Successfully'), 'success');
            } else {
                // write log
                $data['image'] = NULL;
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', 'ERROR : ' . $_SESSION['realname'] . ' FAILED TO upload image file ' . $image_upload->new_filename . ', with error (' . $image_upload->error . ')');
                utility::jsToastr('Bibliography', __('Image Uploaded Failed').'<br/>'.$image_upload->error, 'error');
            }
        } else if (!empty($_POST['base64picstring'])) {
            list($filedata, $filedom) = explode('#image/type#', $_POST['base64picstring']);
            $filedata = base64_decode($filedata);
            $fileinfo = getimagesizefromstring($filedata);
            $valid = strlen($filedata) / 1024 < $sysconf['max_image_upload'];
            $valid = (!$fileinfo || $valid === false) ? false : in_array($fileinfo['mime'], $sysconf['allowed_images_mimetype']);
            $new_filename = strtolower('cover_'
                . preg_replace("/[^a-zA-Z0-9]+/", "_", $data['title'])
                . '.' . $filedom);

            if ($valid AND file_put_contents(IMGBS . 'docs/' . $new_filename, $filedata)) {
                $data['image'] = $dbs->escape_string($new_filename);
                if (!defined('UPLOAD_SUCCESS')) define('UPLOAD_SUCCESS', 1);
                $upload_status = UPLOAD_SUCCESS;
            }
        }

        // create sql op object
        $sql_op = new simbio_dbop($dbs);
        if (isset($_POST['updateRecordID'])) {
            if ($sysconf['log']['biblio']) {
                $_prevrawdata = api::biblio_load($dbs, $_POST['updateRecordID']);
            }

            // Remove previous popular biblio data from cache if opac use default template
            if ($sysconf['template']['theme'] == 'default' && $_POST['opacHide'] != $_POST['opacHideOrigin']) {
                require SB . 'api/v1/helpers/Cache.php';
                Cache::destroy('biblio_popular');
            }

            /* UPDATE RECORD MODE */
            // remove input date
            unset($data['input_date']);
            unset($data['uid']);
            // filter update record ID
            $updateRecordID = (integer)$_POST['updateRecordID'];
            Plugins::getInstance()->execute(Plugins::BIBLIOGRAPHY_BEFORE_UPDATE, ['data' => array_merge($data, ['biblio_id' => $updateRecordID])]);
            // update data
            $update = $sql_op->update('biblio', $data, 'biblio_id=' . $updateRecordID);
            // send an alert
            if ($update) {

                // execute registered hook
                Plugins::getInstance()->execute(Plugins::BIBLIOGRAPHY_AFTER_UPDATE, ['data' => array_merge($data, ['biblio_id' => $updateRecordID])]);

                // update custom data
                if (isset($custom_data)) {
                    // check if custom data for this record exists
                    $_sql_check_custom_q = sprintf('SELECT biblio_id FROM biblio_custom WHERE biblio_id=%d', $updateRecordID);
                    $check_custom_q = $dbs->query($_sql_check_custom_q);
                    if ($check_custom_q->num_rows) {
                        $update2 = @$sql_op->update('biblio_custom', $custom_data, 'biblio_id=' . $updateRecordID);
                    } else {
                        $custom_data['biblio_id'] = $updateRecordID;
                        @$sql_op->insert('biblio_custom', $custom_data);
                    }
                }
                if ($sysconf['bibliography_update_notification']) {
                    utility::jsToastr('Bibliography', __('Bibliography Data Successfully Updated'), 'success');
                }
                // auto insert catalog to UCS if enabled
                if ($sysconf['ucs']['enable']) {
                    echo '<script type="text/javascript">parent.ucsUpload(\'' . MWB . 'bibliography/ucs_upload.php\', \'itemID[]=' . $updateRecordID . '\', false);</script>';
                }
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', $_SESSION['realname'] . ' update bibliographic data (' . $data['title'] . ') with biblio_id (' . $updateRecordID . ')');

                if ($sysconf['log']['biblio']) {
                    $_currrawdata = api::biblio_load($dbs, $updateRecordID);
                    api::bibliolog_compare($dbs, $updateRecordID, $_SESSION['uid'], $_SESSION['realname'], $data['title'], $_currrawdata, $_SESSION['_prevrawdata'][$updateRecordID]);
                    unset($_SESSION['_prevrawdata'][$updateRecordID]);
                }
                if ($sysconf['index']['engine']['enable']) {
                    api::update_to_index($_currrawdata);
                }
                // close window OR redirect main page
                if ($in_pop_up) {
                    $itemCollID = (integer)$_POST['itemCollID'];
                    echo '<script type="text/javascript">top.$(\'#mainContent\').simbioAJAX(parent.jQuery.ajaxHistory[0].url, {method: \'post\', addData: \'' . ($itemCollID ? 'itemID=' . $itemCollID . '&detail=true' : '') . '\'});</script>';
                    echo '<script type="text/javascript">top.closeHTMLpop();</script>';
                } else {
                    echo '<script type="text/javascript">top.$(\'#mainContent\').simbioAJAX(parent.jQuery.ajaxHistory[0].url);</script>';
                }
                // update index
                $indexer->updateIndex($updateRecordID);
            } else {
                utility::jsToastr('Bibliography', __('Bibliography Data FAILED to Updated. Please Contact System Administrator') . "\n" . $sql_op->error, 'error');
            }
        } else {

            // execute registered hook
            Plugins::getInstance()->execute(Plugins::BIBLIOGRAPHY_BEFORE_SAVE, ['data' => $data]);

            /* INSERT RECORD MODE */
            // insert the data
            $insert = $sql_op->insert('biblio', $data);
            if ($insert) {
                // get auto id of this record
                $last_biblio_id = $sql_op->insert_id;

                // execute registered hook
                $data['biblio_id'] = $last_biblio_id;
                Plugins::getInstance()->execute(Plugins::BIBLIOGRAPHY_AFTER_SAVE, ['data' => $data]);

                // add authors
                if ($_SESSION['biblioAuthor']) {
                    foreach ($_SESSION['biblioAuthor'] as $author) {
                        $sql_op->insert('biblio_author', array('biblio_id' => $last_biblio_id, 'author_id' => $author[0], 'level' => $author[1]));
                    }
                }
                // add topics
                if ($_SESSION['biblioTopic']) {
                    foreach ($_SESSION['biblioTopic'] as $topic) {
                        $sql_op->insert('biblio_topic', array('biblio_id' => $last_biblio_id, 'topic_id' => $topic[0], 'level' => $topic[1]));
                    }
                }
                // add attachment
                if ($_SESSION['biblioAttach']) {
                    foreach ($_SESSION['biblioAttach'] as $attachment) {
                        $sql_op->insert('biblio_attachment', array('biblio_id' => $last_biblio_id, 'file_id' => $attachment['file_id'], 'access_type' => $attachment['access_type']));
                    }
                }
                // biblio to biblio
                if ($_SESSION['biblioToBiblio']) {
                    foreach ($_SESSION['biblioToBiblio'] as $rel_biblio_id) {
                        $sql_op->insert('biblio_relation', array('biblio_id' => $last_biblio_id, 'rel_biblio_id' => $rel_biblio_id[0]));
                    }
                }
                // insert custom data
                if (isset($custom_data)) {
                    $custom_data['biblio_id'] = $last_biblio_id;
                    @$sql_op->insert('biblio_custom', $custom_data);
                }


                utility::jsToastr('Bibliography', __('New Bibliography Data Successfully Saved'), 'success');
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', $_SESSION['realname'] . ' insert bibliographic data (' . $data['title'] . ') with biblio_id (' . $last_biblio_id . ')');
                if ($sysconf['log']['biblio']) {
                    $_rawdata = api::biblio_load($dbs, $last_biblio_id);
                    api::bibliolog_write($dbs, $last_biblio_id, $_SESSION['uid'], $_SESSION['realname'], $data['title'], 'create', 'description', $_rawdata, 'New data. Bibliography.');
                    api::bibliolog_compare($dbs, $last_biblio_id, $_SESSION['uid'], $_SESSION['realname'], $data['title'], $_rawdata, NULL);
                }
                if ($sysconf['index']['engine']['enable']) {
                    api::update_to_index($_rawdata);
                }
                // clear related sessions
                $_SESSION['biblioAuthor'] = array();
                $_SESSION['biblioTopic'] = array();
                $_SESSION['biblioAttach'] = array();
                $_SESSION['biblioToBiblio'] = array();

                // make index
                $indexer->makeIndex($last_biblio_id);
                $indexer->makeIndexWord($last_biblio_id);
                
                // Update item information in search_biblio after inserting new bibliography
                // This ensures that items added individually are also reflected in the search index
                $indexer->updateItems($last_biblio_id);

                // auto insert catalog to UCS if enabled
                if ($sysconf['ucs']['enable'] && $sysconf['ucs']['auto_insert']) {
                    echo '<script type="text/javascript">parent.ucsUpload(\'' . MWB . 'bibliography/ucs_upload.php\', \'itemID[]=' . $last_biblio_id . '\');</script>';
                }
            } else {
                utility::jsToastr('Bibliography', __('Bibliography Data FAILED to Save. Please Contact System Administrator') . "\n" . $sql_op->error, 'error');
            }
        }

        // item batch insert
        if (trim($_POST['itemCodePattern']) != '' && $_POST['totalItems'] > 0) {
            $pattern = trim($_POST['itemCodePattern']);
            $total = (integer)$_POST['totalItems'];
            $regex = '/0{3,}/';

            if ($total > $sysconf['max_insert_batch']) {
                utility::jsToastr('Bibliography', sprintf(__('Item Data FAILED to Save. Insert batch item maximum %s copies'), $sysconf['max_insert_batch']), 'warning');
                die();
            }

            // get zeros
            preg_match($regex, $pattern, $result);
            $zeros = strlen($result[0]);

            // get chars
            $chars = preg_split($regex, $pattern);

            $chars_last = (isset($chars[1]) && !empty(trim($chars[1]))) ? trim($chars[1]) : '';

            // get last number from database
            $last_q = $dbs->query('SELECT item_code FROM item WHERE item_code REGEXP \'^' . $chars[0] . '[0-9]{3,}' . $chars_last . '$\' ORDER BY item_code DESC LIMIT 1');
            if (!$dbs->errno && $last_q->num_rows > 0) {
                $last_d = $last_q->fetch_row();
                // get last  number
                $ptn = '/' . $chars[0] . '|' . $chars_last . '$/';
                $last = preg_replace($ptn, '', $last_d[0]);
                $start = intval($last) + 1;
            } else {
                $start = 1;
            }

            $end = $start + $total;
            for ($b = $start; $b < $end; $b++) {
                $len = strlen($b);
                $itemcode = $chars[0];
                if ($zeros > 0) {
                    $itemcode .= preg_replace('@0{' . $len . '}$@i', $b, $result[0]);
                } else {
                    $itemcode .= $b;
                }
                $itemcode .= $chars[1];

                $item_insert_sql = sprintf("INSERT IGNORE INTO item (biblio_id, item_code, received_date, supplier_id, order_no, order_date, item_status_id, site, source, invoice, price, price_currency, invoice_date, call_number, coll_type_id, location_id, input_date, last_update, uid)
        VALUES ( %d, '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d)",
                    isset($updateRecordID) ? $updateRecordID : $last_biblio_id, $itemcode, $dbs->escape_string($_POST['recvDate']), intval($_POST['supplierID']), $dbs->escape_string($_POST['ordNo']), $dbs->escape_string($_POST['orDate']), $dbs->escape_string($_POST['itemStatusID']), $dbs->escape_string($_POST['itemSite']), intval($_POST['source']), $dbs->escape_string($_POST['invoice']), intval($_POST['price']), $dbs->escape_string($_POST['priceCurrency']), $dbs->escape_string($_POST['invcDate']), $data['call_number'], intval($_POST['collTypeID']), $dbs->escape_string($_POST['locationID']), date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $_SESSION['uid']);
                $item_query_result = @$dbs->query($item_insert_sql);
                if (!$item_query_result) {
                    // Log the error for debugging
                    utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', 'ERROR: Failed to insert item: ' . $dbs->error);
                }
            }

            // update items data into search_biblio
            $indexer->updateItems((isset($updateRecordID) ? $updateRecordID : $last_biblio_id));
        }

        // Also update items data into search_biblio for cases where items were added separately
        $indexer->updateItems((isset($updateRecordID) ? $updateRecordID : $last_biblio_id));
        
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\'' . MWB . 'bibliography/index.php\', {method: \'post\', addData: \'itemID=' . (isset($updateRecordID) ? $updateRecordID : $last_biblio_id) . '&detail=true\'});</script>';
        exit();
    }
    exit();
} else if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {
    if (!($can_read AND $can_write)) {
        die();
    }
    if (!simbio_form_maker::isTokenValid()) {
        utility::jsToastr('Bibliography', __('Invalid form submission token!'), 'error');
        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'system', 'Invalid form submission token, might be a CSRF attack from ' . $_SERVER['REMOTE_ADDR']);
        exit();
    }

    $indexer = new biblio_indexer($dbs);

    /* DATA DELETION PROCESS */
    // create sql op object
    $sql_op = new simbio_dbop($dbs);
    $failed_array = array();
    $error_num = 0;
    $still_have_item = array();
    if (!is_array($_POST['itemID'])) {
        // make an array
        $_POST['itemID'] = array((integer)$_POST['itemID']);
    }
    // loop array
    $http_query = '';
    foreach ($_POST['itemID'] as $itemID) {
        $itemID = (integer)$itemID;
        // check if this biblio data still have an item
        $_sql_biblio_item_q = sprintf('SELECT b.title, COUNT(item_id) FROM biblio AS b
      LEFT JOIN item AS i ON b.biblio_id=i.biblio_id
      WHERE b.biblio_id=%d GROUP BY title', $itemID);
        $biblio_item_q = $dbs->query($_sql_biblio_item_q);
        $biblio_item_d = $biblio_item_q->fetch_row();
        if ($biblio_item_d[1] < 1) {

            if ($sysconf['log']['biblio']) {
                $_rawdata = api::biblio_load($dbs, $itemID);
                api::bibliolog_write($dbs, $itemID, $_SESSION['uid'], $_SESSION['realname'], $biblio_item_d[0], 'delete', 'description', $_rawdata, 'Data bibliografi dihapus.');
            }

            // execute registered hook
            Plugins::getInstance()->execute(Plugins::BIBLIOGRAPHY_BEFORE_DELETE, [$itemID]);
            
            if (!$sql_op->delete('biblio', "biblio_id=$itemID")) {
                $error_num++;
            } else {

                // execute registered hook
                Plugins::getInstance()->execute(Plugins::BIBLIOGRAPHY_AFTER_DELETE, [$itemID]);

                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', $_SESSION['realname'] . ' DELETE bibliographic data (' . $biblio_item_d[0] . ') with biblio_id (' . $itemID . ')');
                // delete related data
                $sql_op->delete('biblio_topic', "biblio_id=$itemID");
                $sql_op->delete('biblio_author', "biblio_id=$itemID");
                $sql_op->delete('biblio_attachment', "biblio_id=$itemID");
                $sql_op->delete('biblio_relation', "biblio_id=$itemID");

                # delete from index
                $indexer->deleteIndex($itemID);

                // delete serial data
                // check kardex if exist
                $_sql_serial_kardex_q = sprintf('SELECT b.title, COUNT(kardex_id),s.serial_id FROM biblio AS b
          LEFT JOIN `serial` AS s ON b.biblio_id=s.biblio_id
          LEFT JOIN kardex AS k ON s.serial_id=k.serial_id
          WHERE b.biblio_id=%d GROUP BY title', $itemID);
                $serial_kardex_q = $dbs->query($_sql_serial_kardex_q);
                if ($serial_kardex_q) {
                    $serial_kardex_d = $serial_kardex_q->fetch_row();
                    // delete kardex
                    if ($serial_kardex_d[1] > 1) {
                        $sql_op->delete('kardex', "serial_id=" . $serial_kardex_d[2]);
                    }
                }
                //delete serial data
                $sql_op->delete('serial', "biblio_id=$itemID");

                // add to http query for UCS delete
                $http_query .= "itemID[]=$itemID&";
            }
        } else {
            $still_have_item[] = substr($biblio_item_d[0], 0, 45) . '... still have ' . $biblio_item_d[1] . ' copies';
            $error_num++;
        }
    }

    if ($still_have_item) {
        $titles = '';
        foreach ($still_have_item as $title) {
            $titles .= $title . "\n";
        }
        utility::jsToastr('Bibliography', __('Below data can not be deleted:') . "\n" . $titles, 'error');
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\'' . $_SERVER['PHP_SELF'] . '\', {addData: \'' . $_POST['lastQueryStr'] . '\'});</script>';
        exit();
    }
    // auto delete data on UCS if enabled
    if ($http_query && $sysconf['ucs']['enable'] && $sysconf['ucs']['auto_delete']) {
        echo '<script type="text/javascript">parent.ucsUpdate(\'' . MWB . 'bibliography/ucs_update.php\', \'nodeOperation=delete&' . $http_query . '\');</script>';
    }
    // error alerting
    if ($error_num == 0) {
        utility::jsToastr('Bibliography', __('All Data Successfully Deleted'), 'success');
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\'' . $_SERVER['PHP_SELF'] . '\', {addData: \'' . $_POST['lastQueryStr'] . '\'});</script>';
    } else {
        utility::jsToastr('Bibliography', __('Some or All Data NOT deleted successfully!\nPlease contact system administrator'), 'warning');
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\'' . $_SERVER['PHP_SELF'] . '\', {addData: \'' . $_POST['lastQueryStr'] . '\'});</script>';
    }
    exit();
}
if (!function_exists('renderLastUpdateBadge')) {
    function renderLastUpdateBadge($obj_db, $array_data)
    {
        $rawValue = '';
        if (is_array($array_data)) {
            $values = array_values($array_data);
            $length = count($values);
            // Ambil kolom terakhir sebagai last_update
            $idx = $length - 1;
            $rawValue = $values[$idx] ?? '';
        } elseif (is_string($array_data) || is_numeric($array_data)) {
            $rawValue = (string)$array_data;
        }
        $rawValue = trim(strip_tags((string)$rawValue));
        if ($rawValue === '') {
            return '<span class="biblio-badge biblio-badge--last-update biblio-badge--empty">–</span>';
        }
        $timestamp = strtotime($rawValue);
        if ($timestamp) {
            $datePart = htmlspecialchars(date('d M Y', $timestamp), ENT_QUOTES, 'UTF-8');
            $timePart = htmlspecialchars(date('H:i', $timestamp), ENT_QUOTES, 'UTF-8');
            return '<span class="biblio-badge biblio-badge--last-update"><span class="biblio-badge__date">' . $datePart . '</span> <span class="biblio-badge__time">' . $timePart . '</span></span>';
        }
        return '<span class="biblio-badge biblio-badge--last-update">' . htmlspecialchars($rawValue, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

if (!function_exists('renderClassBadge')) {
    function renderClassBadge($obj_db, $array_data)
    {
        return renderBadgeByType('class', $array_data);
    }
}

if (!function_exists('renderYearBadge')) {
    function renderYearBadge($obj_db, $array_data)
    {
        return renderBadgeByType('year', $array_data);
    }
}

if (!function_exists('renderIsbnBadge')) {
    function renderIsbnBadge($obj_db, $array_data)
    {
        return renderBadgeByType('isbn', $array_data);
    }
}

if (!function_exists('renderCopiesBadge')) {
    function renderCopiesBadge($obj_db, $array_data)
    {
        return renderBadgeByType('copies', $array_data);
    }
}

if (!function_exists('renderBadgeByType')) {
    function renderBadgeByType(string $type, $array_data): string
    {
        $rawValue = '';
        if (is_array($array_data)) {
            $values = array_values($array_data);
            $length = count($values);
            // Mapping index kolom sesuai urutan datagrid yang baru:
            // For indexed search (can_write): [0]id, [1]title, [2]labels, [3]image, [4]copies, [5]author, [6]class, [7]year, [8]isbn, [9]last_update
            // For indexed search (can_read): [0]title, [1]labels, [2]image, [3]copies, [4]author, [5]class, [6]year, [7]isbn, [8]last_update, [9]inputby
            // For non-indexed search (can_write): [0]id, [1]bid, [2]title, [3]image, [4]copies, [5]class, [6]year, [7]isbn, [8]last_update
            // For non-indexed search (can_read): [0]bid, [1]title, [2]image, [3]copies, [4]class, [5]year, [6]isbn, [7]last_update, [8]inputby
            $indexMap = [
                'class' => [
                    10 => 6, // indexed search, can_write
                    9 => 5,  // indexed search, can_read OR non-indexed search, can_write
                    8 => 4   // non-indexed search, can_read
                ],
                'year' => [
                    10 => 7, // indexed search, can_write
                    9 => 6,  // indexed search, can_read OR non-indexed search, can_write
                    8 => 5   // non-indexed search, can_read
                ],
                'isbn' => [
                    10 => 8, // indexed search, can_write
                    9 => 7,  // indexed search, can_read OR non-indexed search, can_write
                    8 => 6   // non-indexed search, can_read
                ],
                'copies' => [
                    10 => 4, // indexed search, can_write
                    9 => 3,  // indexed search, can_read OR non-indexed search, can_write
                    8 => 3   // non-indexed search, can_read
                ]
            ];
            if ($type === 'last_update') {
                $idx = $length - 1;
            } else {
                $map = $indexMap[$type] ?? [];
                $idx = $map[$length] ?? ($length - 1);
            }
            $rawValue = $values[$idx] ?? '';
        } elseif (is_string($array_data) || is_numeric($array_data)) {
            $rawValue = (string)$array_data;
        }
        $rawValue = trim(strip_tags((string)$rawValue));
        $classes = ['biblio-badge', 'biblio-badge--' . $type];
        if ($rawValue === '') {
            $classes[] = 'biblio-badge--empty';
            return '<span class="' . implode(' ', $classes) . '">–</span>';
        }
        if ($type === 'last_update') {
            $timestamp = strtotime($rawValue);
            if ($timestamp) {
                $datePart = htmlspecialchars(date('d M Y', $timestamp), ENT_QUOTES, 'UTF-8');
                $timePart = htmlspecialchars(date('H:i', $timestamp), ENT_QUOTES, 'UTF-8');
                return '<span class="' . implode(' ', $classes) . '"><span class="biblio-badge__date">' . $datePart . '</span><span class="biblio-badge__time">' . $timePart . '</span></span>';
            }
        }
        if ($type === 'year') {
            $rawValue = preg_replace('/[^0-9]/', '', $rawValue);
        } elseif ($type === 'isbn') {
            $rawValue = strtoupper($rawValue);
        } elseif ($type === 'copies') {
            if (is_numeric($rawValue)) {
                $rawValue = (string) (int) $rawValue;
            }
        }
        if ($rawValue === '' || ($type === 'year' && ($rawValue === '0'))) {
            $classes[] = 'biblio-badge--empty';
            return '<span class="' . implode(' ', $classes) . '">–</span>';
        }
        $displayValue = htmlspecialchars($rawValue, ENT_QUOTES, 'UTF-8');
        // Special rendering for copies badge overlay on cover image
        if ($type === 'copies' && isset($array_data['image']) && $array_data['image']) {
            $imgSrc = '../images/docs/' . urlencode($array_data['image']);
            $imgTag = '<img src="' . $imgSrc . '" class="img-fluid rounded biblio-cover-img" alt="Image cover">';
            $badgeTag = '<span class="' . implode(' ', $classes) . ' biblio-badge--overlay">' . $displayValue . '</span>';
            return '<div class="biblio-cover-badge-wrapper" style="position:relative;display:inline-block;">' . $imgTag . $badgeTag . '</div>';
        }
        return '<span class="' . implode(' ', $classes) . '">' . $displayValue . '</span>';
    }
}

if (!$in_pop_up) {
    /* search form */
    ?>
    <div class="menuBox bibliography-hub">
        <div class="menuBoxInner biblioIcon">
            <style>
                .bibliography-hub {
                    background: #eef1fb;
                    padding: 1.1rem;
                    border-radius: 22px;
                    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.4);
                }
                .bibliography-hub .biblio-hero {
                    display: flex;
                    align-items: center;
                    justify-content: flex-start; /* Changed from space-between to flex-start to keep icon and text together */
                    gap: 8px; /* Gap between icon and text */
                    background: linear-gradient(118deg, #1f3bb3, #5563de 58%, #7b9dff);
                    border-radius: 18px;
                    padding: 16px 20px;
                    color: #fff;
                    box-shadow: 0 12px 28px rgba(31, 59, 179, 0.28);
                }
                .bibliography-hub .biblio-hero .biblio-hero__icon {
                    flex: 0 0 54px;
                    height: 54px;
                    border-radius: 16px;
                    background: rgba(255, 255, 255, 0.18);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 24px;
                    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.24);
                }
                .bibliography-hub .biblio-hero__actions {
                    margin-left: auto; /* Push actions to the right */
                }
                .bibliography-hub .biblio-hero__actions {
                    display: flex;
                    flex-direction: column; /* Stack buttons vertically */
                    align-items: flex-end; /* Align to right */
                    gap: 8px; /* Space between buttons */
                }
                .bibliography-hub .biblio-hero__icon {
                    flex: 0 0 54px;
                    height: 54px;
                    border-radius: 16px;
                    background: rgba(255, 255, 255, 0.18);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 24px;
                    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.24);
                }
                .bibliography-hub .biblio-hero__text {
                    display: flex;
                    flex-direction: column;
                    gap: 2px; /* Reduced gap between title and description */
                    min-width: 0;
                }
                .bibliography-hub .biblio-hero__text h2 {
                    margin: 0;
                    font-size: 1.15rem;
                    font-weight: 700;
                    letter-spacing: 0.25px;
                }
                .bibliography-hub .biblio-hero__text p {
                    margin: 0;
                    color: rgba(255, 255, 255, 0.82);
                    font-size: 0.92rem;
                    letter-spacing: 0.12px;
                    padding-right: 8px;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                .bibliography-hub .biblio-surface {
                    margin-top: 18px;
                    background: #fff;
                    border-radius: 18px;
                    padding: 18px 20px 20px;
                    box-shadow: 0 16px 34px rgba(24, 46, 116, 0.14);
                    position: relative;
                }
                .bibliography-hub .biblio-toolbar {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 14px;
                    margin-bottom: 16px;
                }
                .bibliography-hub .biblio-toolbar__actions {
                    display: flex;
                    gap: 10px;
                    align-items: center;
                    flex-wrap: wrap;
                }
                .bibliography-hub .biblio-toolbar__actions .btn {
                    border: none;
                    border-radius: 12px;
                    background: linear-gradient(135deg, #1f3bb3, #5563de);
                    color: #fff;
                    padding: 9px 18px;
                    font-weight: 600;
                    box-shadow: 0 12px 24px rgba(31, 59, 179, 0.28);
                    transition: all 0.2s ease;
                }
                .bibliography-hub .biblio-toolbar__actions .btn:hover,
                .bibliography-hub .biblio-toolbar__actions .btn:focus {
                    background: linear-gradient(135deg, #182f8b, #4451c4);
                    transform: translateY(-1px);
                    box-shadow: 0 16px 28px rgba(31, 59, 179, 0.32);
                }
                .bibliography-hub .eddc-button-wrapper {
                    position: relative;
                    display: inline-block;
                }
                .bibliography-hub .eddc-button-wrapper .eddc-badge {
                    position: absolute;
                    top: -8px;
                    right: -8px;
                    background: #3ccf91;
                    color: #0a1d33;
                    font-size: 0.6rem;
                    font-weight: 700;
                    padding: 2px 6px;
                    border-radius: 9px;
                    letter-spacing: 0.5px;
                    text-transform: uppercase;
                    box-shadow: 0 2px 6px rgba(48, 170, 120, 0.4);
                    pointer-events: none;
                    z-index: 2;
                }
                .bibliography-hub .eddc-button-wrapper a {
                    position: relative;
                    z-index: 1;
                    min-width: 200px; /* Equal width for both buttons */
                    text-align: center;
                }
                .bibliography-hub .biblio-toolbar__hint {
                    font-size: 0.82rem;
                    color: #66739c;
                    max-width: 320px;
                    line-height: 1.35;
                    flex: 1 1 auto;
                    text-align: right;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                .biblio-inputby {
                    margin-top: 4px;
                    font-size: 0.72rem;
                    letter-spacing: 0.05em;
                    color: #54607b;
                    text-transform: uppercase;
                }
                .bibliography-hub .biblio-search-grid {
                    display: grid;
                    grid-template-columns: minmax(220px, 1.4fr) minmax(170px, 1fr) max-content;
                    align-items: stretch;
                    gap: 12px;
                    background: #f7f9ff;
                    border: 1px solid #e3e7f5;
                    border-radius: 14px;
                    padding: 12px 16px;
                }
                .bibliography-hub .biblio-field {
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                    justify-content: center;
                }
                .bibliography-hub .biblio-field label {
                    font-size: 0.78rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    color: #6a7299;
                    margin: 0;
                }
                .bibliography-hub .biblio-field input,
                .bibliography-hub .biblio-field select {
                    border-radius: 10px;
                    border: 1px solid #d2d9ef;
                    background: #fff;
                    padding: 10px 12px;
                    color: #1f294a;
                    font-size: 0.94rem;
                    height: 42px;
                    transition: border-color 0.2s ease, box-shadow 0.2s ease;
                    align-self: stretch;
                }
                .bibliography-hub .biblio-field input:focus,
                .bibliography-hub .biblio-field select:focus {
                    border-color: #4c60df;
                    box-shadow: 0 0 0 3px rgba(76, 96, 223, 0.18);
                    outline: none;
                }
                .bibliography-hub .biblio-actions {
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    gap: 6px;
                    align-items: flex-end;
                }
                .bibliography-hub .biblio-actions-label {
                    font-size: 0.78rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    color: #6a7299;
                    margin: 0;
                }
                .bibliography-hub .biblio-actions-buttons {
                    display: flex;
                    gap: 10px;
                    align-items: stretch;
                }
                .bibliography-hub .biblio-search-btn,
                .bibliography-hub .biblio-advanced-toggle {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    border-radius: 12px;
                    padding: 0 18px;
                    height: 42px;
                    font-weight: 600;
                    border: none;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                .bibliography-hub .biblio-search-btn {
                    background: linear-gradient(135deg, #1f3bb3, #5563de);
                    color: #fff;
                    box-shadow: 0 12px 26px rgba(31, 59, 179, 0.35);
                }
                .bibliography-hub .biblio-search-btn:hover,
                .bibliography-hub .biblio-search-btn:focus {
                    background: linear-gradient(135deg, #182f8b, #4451c4);
                    box-shadow: 0 16px 28px rgba(31, 59, 179, 0.38);
                }
                .bibliography-hub .biblio-advanced-toggle {
                    background: #fff;
                    color: #28376a;
                    border: 1px solid rgba(40, 55, 106, 0.16);
                    box-shadow: 0 6px 14px rgba(40, 55, 106, 0.1);
                }
                .bibliography-hub .biblio-advanced-toggle:hover,
                .bibliography-hub .biblio-advanced-toggle:focus {
                    color: #fff;
                    background: #28376a;
                    border-color: transparent;
                }
                .bibliography-hub .biblio-advanced-panel {
                    margin-top: 18px;
                    background: #f9faff;
                    border-radius: 14px;
                    border: 1px dashed #d0d8f2;
                    padding: 16px;
                }
                .bibliography-hub .biblio-filter-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 14px;
                }
                .bibliography-hub .biblio-filter-grid label {
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                    font-size: 0.85rem;
                    font-weight: 600;
                    color: #4a5685;
                    letter-spacing: 0.02em;
                }
                .bibliography-hub .biblio-filter-grid select {
                    border-radius: 10px;
                    border: 1px solid #d2d9ef;
                    padding: 9px 12px;
                    background: #fff;
                    color: #1f294a;
                    height: 40px;
                    transition: border 0.2s ease, box-shadow 0.2s ease;
                }
                .bibliography-hub .biblio-filter-grid select:focus {
                    border-color: #4c60df;
                    box-shadow: 0 0 0 3px rgba(76, 96, 223, 0.18);
                    outline: none;
                }
                .bibliography-hub .biblio-ucs-link {
                    display: inline-flex;
                    align-items: center;
                    gap: 10px;
                    margin-top: 14px;
                    border-radius: 11px;
                    padding: 9px 16px;
                    background: #f0f4ff;
                    color: #1f2d5c !important;
                    border: 1px solid rgba(31, 45, 92, 0.1);
                    box-shadow: 0 8px 16px rgba(31, 45, 92, 0.12);
                    font-weight: 600;
                    transition: all 0.2s ease;
                }
                .bibliography-hub .biblio-ucs-link:hover {
                    text-decoration: none;
                    color: #fff !important;
                    background: #1f3bb3;
                    border-color: #1f3bb3;
                    box-shadow: 0 14px 26px rgba(31, 59, 179, 0.32);
                }
                .biblio-table-wrapper {
                    margin: 20px 0;
                    background: #f8f9fc;
                    border-radius: 20px;
                    padding: 20px;
                    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.5);
                }
                .biblio-table-card {
                    margin-top: 0;
                    background: #ffffff;
                    border-radius: 16px;
                    padding: 16px 20px;
                    box-shadow: 0 4px 20px rgba(21, 39, 102, 0.08);
                    overflow-x: auto;
                }
                .biblio-table-card #dataList.s-table {
                    width: 100%;
                    border-collapse: separate;
                    border-spacing: 0 12px;
                    margin: 0;
                }
                .biblio-table-card #dataList.s-table thead tr th {
                    background: transparent;
                    border: none;
                    text-transform: uppercase;
                    font-size: 0.72rem;
                    letter-spacing: 0.1em;
                    color: #7984ac;
                    padding: 0 16px 6px;
                }
                .biblio-table-card #dataList.s-table tbody tr {
                    position: relative;
                    transform: translateY(0);
                    transition: transform 0.2s ease, box-shadow 0.2s ease;
                }
                .biblio-table-card #dataList.s-table tbody tr::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    background: #f8f9ff;
                    border-radius: 20px;
                    box-shadow: 0 18px 36px rgba(29, 52, 123, 0.12);
                    z-index: 0;
                }
                .biblio-table-card #dataList.s-table tbody tr:hover {
                    transform: translateY(-4px);
                }
                .biblio-table-card #dataList.s-table tbody tr:hover::before {
                    box-shadow: 0 24px 42px rgba(29, 52, 123, 0.18);
                }
                .biblio-table-card #dataList.s-table tbody td {
                    border: none;
                    padding: 14px 18px;
                    color: #27345d;
                    font-size: 0.94rem;
                    line-height: 1.4;
                    background: transparent;
                    position: relative;
                    z-index: 1;
                }
                .biblio-table-card #dataList.s-table tbody tr td:first-child {
                    border-top-left-radius: 18px;
                    border-bottom-left-radius: 18px;
                }
                .biblio-table-card #dataList.s-table tbody tr td:last-child {
                    border-top-right-radius: 18px;
                    border-bottom-right-radius: 18px;
                }
                .biblio-table-card #totalData {
                    margin-top: 18px;
                    font-weight: 600;
                    color: #414d7a;
                }

                .biblio-cover-wrapper {
                    position: relative;
                    margin-right: 1rem;
                    min-width: 50px;
                    flex-shrink: 0;
                }

                .biblio-badge--overlay {
                    position: absolute;
                    top: -5px;
                    right: -5px;
                    z-index: 2;
                    background: #10b981;
                    color: white;
                    font-weight: 700;
                    font-size: 11px;
                    min-width: 24px;
                    height: 24px;
                    padding: 0 7px;
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: 2px solid white;
                    box-shadow: 0 3px 10px rgba(16, 185, 129, 0.5);
                }


                .biblio-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 4px 10px;
                    border-radius: 999px;
                    font-size: 0.75rem;
                    font-weight: 600;
                    line-height: 1;
                    white-space: nowrap;
                }
                .biblio-badge__date {
                    letter-spacing: 0.02em;
                }
                .biblio-badge__time {
                    font-size: 0.72rem;
                    letter-spacing: 0.02em;
                }
                .biblio-badge--last-update {
                    background: rgba(31, 59, 179, 0.1);
                    color: #203268;
                }
                .biblio-badge--last-update .biblio-badge__time {
                    color: #1f3bb3;
                }
                .biblio-badge--class {
                    background: rgba(59, 130, 246, 0.18);
                    color: #1e40af;
                }
                .biblio-badge--year {
                    background: rgba(46, 176, 152, 0.18);
                    color: #17695f;
                }
                .biblio-badge--isbn {
                    background: rgba(123, 111, 255, 0.18);
                    color: #4136a4;
                }
                .biblio-badge--copies {
                    background: #10b981;
                    color: white;
                }
                .biblio-badge--copies.biblio-badge--empty,
                .biblio-badge--isbn.biblio-badge--empty,
                .biblio-badge--year.biblio-badge--empty {
                    background: rgba(128, 138, 168, 0.18);
                    color: #6f7a9b;
                }
                .biblio-badge--empty {
                    background: rgba(128, 138, 168, 0.18);
                    color: #6f7a9b;
                }
                
                /* First column styling (Title) */
                #dataList tbody td:first-child,
                #dataList thead th:first-child {
                    padding-left: 12px;
                }
                
                /* Last column - merged checkbox + edit button */
                .biblio-actions-cell {
                    width: 90px !important;
                    text-align: right !important;
                    padding: 8px 12px 8px 8px !important;
                    vertical-align: middle !important;
                }
                
                /* Container for checkbox + edit button */
                .biblio-actions-merged {
                    display: flex;
                    align-items: center;
                    justify-content: flex-end;
                    gap: 8px;
                }
                
                /* Compact checkbox styling */
                .biblio-actions-merged input[type="checkbox"] {
                    width: 16px;
                    height: 16px;
                    cursor: pointer;
                    margin: 0;
                    flex-shrink: 0;
                }
                
                #dataList thead input[type="checkbox"] {
                    width: 16px;
                    height: 16px;
                    cursor: pointer;
                    margin: 0 auto;
                    display: block;
                }
                
                /* Edit button styling - simple and clean */
                .biblio-edit-btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 34px;
                    height: 34px;
                    border-radius: 8px;
                    background: linear-gradient(135deg, #3b82f6, #2563eb);
                    border: none;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    font-size: 16px;
                    line-height: 1;
                    text-decoration: none;
                    box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);
                    flex-shrink: 0;
                }
                
                .biblio-edit-btn:hover {
                    background: linear-gradient(135deg, #2563eb, #1d4ed8);
                    transform: translateY(-2px);
                    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.4);
                }
                

                
                /* Style untuk tombol bulk action bawaan yang lebih minimalis */
                form[name="itemListForm"] .bulkAction {
                    margin: 10px 0;
                    display: flex;
                    flex-wrap: wrap;
                    gap: 8px;
                    align-items: center;
                }
                
                form[name="itemListForm"] .bulkAction input[type="button"],
                form[name="itemListForm"] .bulkAction input[type="submit"] {
                    padding: 6px 12px;
                    font-size: 0.75rem;
                    font-weight: 600;
                    border-radius: 8px;
                    border: none;
                    background: linear-gradient(135deg, #f87171, #ef4444);
                    color: white;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                }
                
                form[name="itemListForm"] .bulkAction input[type="button"]:hover,
                form[name="itemListForm"] .bulkAction input[type="submit"]:hover {
                    background: linear-gradient(135deg, #ef4444, #dc2626);
                    transform: translateY(-1px);
                    box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4);
                }
                
                /* Ultra compact pagination styling */
                #pagingList {
                    margin: 12px 0 0;
                    display: flex;
                    flex-wrap: wrap;
                    gap: 4px;
                    justify-content: flex-end;
                }
                
                #pagingList a,
                #pagingList strong {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 30px;
                    height: 30px;
                    padding: 0;
                    border-radius: 8px;
                    background: #f8fafc;
                    color: #64748b;
                    font-weight: 500;
                    font-size: 0.75rem;
                    border: 1px solid #e2e8f0;
                    transition: all 0.2s ease;
                    text-decoration: none;
                }
                
                #pagingList strong {
                    background: linear-gradient(135deg, #1f3bb3, #3b82f6);
                    color: #fff;
                    border-color: transparent;
                    font-weight: 600;
                }
                
                #pagingList a:hover {
                    background: #eef2ff;
                    color: #3b82f6;
                    border-color: #c7d2fe;
                    transform: translateY(-1px);
                }
                
                #pagingList a:active,
                #pagingList strong:active {
                    transform: translateY(0);
                }
                
                .bibliography-hub .biblio-toolbar {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 14px;
                    margin-bottom: 18px;
                }
                .bibliography-hub .biblio-toolbar__actions {
                    display: flex;
                    gap: 10px;
                    align-items: center;
                    flex-wrap: wrap;
                    flex-direction: column; /* Stack buttons vertically */
                }
                .bibliography-hub .biblio-toolbar__actions .btn {
                    border: none;
                    border-radius: 12px;
                    background: linear-gradient(135deg, #1f3bb3, #5563de);
                    color: #fff;
                    padding: 9px 18px;
                    font-weight: 600;
                    box-shadow: 0 12px 24px rgba(31, 59, 179, 0.28);
                    transition: all 0.2s ease;
                }
                .bibliography-hub .biblio-toolbar__actions .btn:hover,
                .bibliography-hub .biblio-toolbar__actions .btn:focus {
                    background: linear-gradient(135deg, #182f8b, #4451c4);
                    transform: translateY(-1px);
                    box-shadow: 0 16px 28px rgba(31, 59, 179, 0.32);
                }
                .bibliography-hub .eddc-button-wrapper {
                    position: relative;
                    display: inline-flex;
                    flex-direction: column; /* Stack badge and button vertically */
                    align-items: center;
                    padding-right: 0;
                }
                .bibliography-hub .eddc-button-wrapper .eddc-badge {
                    position: absolute;
                    top: -18px;
                    right: auto;
                    left: 50%;
                    transform: translateX(-50%);
                    background: #3ccf91;
                    color: #0a1d33;
                    font-size: 0.62rem;
                    font-weight: 700;
                    padding: 3px 9px;
                    border-radius: 999px;
                    letter-spacing: 0.6px;
                    text-transform: uppercase;
                    box-shadow: 0 6px 14px rgba(48, 170, 120, 0.36);
                    pointer-events: none;
                    z-index: 2;
                }
                .bibliography-hub .eddc-button-wrapper a {
                    position: relative;
                    z-index: 1;
                }
                .bibliography-hub .biblio-toolbar__hint {
                    font-size: 0.82rem;
                    color: #66739c;
                    max-width: 320px;
                    line-height: 1.35;
                    flex: 1 1 auto;
                    text-align: right;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                
                .biblio-search-card {
                    margin-top: 18px;
                    background: #fff;
                    border-radius: 18px;
                    padding: 0;
                    box-shadow: 0 16px 34px rgba(24, 46, 116, 0.14);
                    position: relative;
                }
                
                .biblio-search-toggle {
                    padding: 16px 20px;
                    background: linear-gradient(118deg, #1f3bb3, #5563de 58%, #7b9dff);
                    color: #fff;
                    border-radius: 18px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    font-weight: 600;
                    transition: all 0.3s ease;
                }
                
                .biblio-search-toggle:hover {
                    background: linear-gradient(118deg, #182f8b, #4451c4 58%, #5a7de0);
                }
                
                .biblio-search-toggle .toggle-icon {
                    transition: transform 0.3s ease;
                }
                
                .biblio-search-toggle.collapsed .toggle-icon {
                    transform: rotate(-90deg);
                }
                
                .biblio-search-content {
                    padding: 18px 20px 20px;
                }
                
                .biblio-search-grid {
                    display: grid;
                    grid-template-columns: minmax(220px, 1.4fr) minmax(170px, 1fr) max-content;
                    align-items: stretch;
                    gap: 12px;
                    background: #f7f9ff;
                    border: 1px solid #e3e7f5;
                    border-radius: 14px;
                    padding: 12px 16px;
                }
                
                .biblio-field {
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                    justify-content: center;
                }
                
                .biblio-field label {
                    font-size: 0.78rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    color: #6a7299;
                    margin: 0;
                }
                
                .biblio-field input,
                .biblio-field select {
                    border-radius: 10px;
                    border: 1px solid #d2d9ef;
                    background: #fff;
                    padding: 10px 12px;
                    color: #1f294a;
                    font-size: 0.94rem;
                    height: 42px;
                    transition: border-color 0.2s ease, box-shadow 0.2s ease;
                    align-self: stretch;
                }
                
                .biblio-field input:focus,
                .biblio-field select:focus {
                    border-color: #4c60df;
                    box-shadow: 0 0 0 3px rgba(76, 96, 223, 0.18);
                    outline: none;
                }
                
                .biblio-actions {
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    gap: 6px;
                    align-items: flex-end;
                }
                
                .biblio-actions-label {
                    font-size: 0.78rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    color: #6a7299;
                    margin: 0;
                }
                
                .biblio-actions-buttons {
                    display: flex;
                    gap: 10px;
                    align-items: stretch;
                }
                
                .biblio-search-btn,
                .biblio-advanced-toggle {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    border-radius: 12px;
                    padding: 0 18px;
                    height: 42px;
                    font-weight: 600;
                    border: none;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                
                .biblio-search-btn {
                    background: linear-gradient(135deg, #1f3bb3, #5563de);
                    color: #fff;
                    box-shadow: 0 12px 26px rgba(31, 59, 179, 0.35);
                }
                
                .biblio-search-btn:hover,
                .biblio-search-btn:focus {
                    background: linear-gradient(135deg, #182f8b, #4451c4);
                    box-shadow: 0 16px 28px rgba(31, 59, 179, 0.38);
                }
                
                .biblio-advanced-toggle {
                    background: #fff;
                    color: #28376a;
                    border: 1px solid rgba(40, 55, 106, 0.16);
                    box-shadow: 0 6px 14px rgba(40, 55, 106, 0.1);
                }
                
                .biblio-advanced-toggle:hover,
                .biblio-advanced-toggle:focus {
                    color: #fff;
                    background: #28376a;
                    border-color: transparent;
                }
                
                /* Form styling to match the modern biblio list style */
                #mainForm {
                    background: #ffffff;
                    border-radius: 18px;
                    padding: 24px;
                    box-shadow: 0 16px 34px rgba(24, 46, 116, 0.14);
                    margin-top: 20px;
                }
                
                #mainForm table {
                    width: 100%;
                    border-collapse: collapse;
                }
                
                #mainForm table tr td {
                    padding: 14px 16px;
                    border-bottom: 1px solid #eef2f7;
                    vertical-align: top;
                }
                
                #mainForm table tr td:first-child {
                    width: 25%;
                    background: linear-gradient(135deg, #f8fafc, #eef2ff);
                    color: #4f46e5;
                    font-weight: 600;
                    border-radius: 12px 0 0 12px;
                    vertical-align: top;
                    padding: 18px 20px;
                }
                
                #mainForm table tr td:last-child {
                    border-radius: 0 12px 12px 0;
                }
                
                #mainForm table tr:last-child td {
                    border-bottom: none;
                }
                
                #mainForm table tr td input[type="text"],
                #mainForm table tr td input[type="number"],
                #mainForm table tr td input[type="date"],
                #mainForm table tr td input[type="file"],
                #mainForm table tr td textarea,
                #mainForm table tr td select {
                    width: 100%;
                    padding: 12px 14px;
                    border: 1px solid #d2d9ef;
                    border-radius: 10px;
                    font-size: 0.94rem;
                    background: #fff;
                    transition: all 0.2s ease;
                    box-sizing: border-box;
                }
                
                #mainForm table tr td input[type="text"]:focus,
                #mainForm table tr td input[type="number"]:focus,
                #mainForm table tr td input[type="date"]:focus,
                #mainForm table tr td textarea:focus,
                #mainForm table tr td select:focus {
                    outline: none;
                    border-color: #4c60df;
                    box-shadow: 0 0 0 3px rgba(76, 96, 223, 0.18);
                }
                
                #mainForm table tr td textarea {
                    min-height: 100px;
                    resize: vertical;
                }
                
                .s-btn, #mainForm input[type="submit"], #mainForm input[type="button"] {
                    border: none;
                    border-radius: 12px;
                    color: #fff;
                    padding: 10px 20px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    text-decoration: none;
                    display: inline-block;
                    text-align: center;
                    font-size: 0.9rem;
                    box-shadow: 0 4px 12px rgba(31, 59, 179, 0.25);
                }
                
                .s-btn.btn-default, #mainForm input[type="submit"] {
                    background: linear-gradient(135deg, #1f3bb3, #5563de);
                }
                
                .s-btn.btn-primary {
                    background: linear-gradient(135deg, #10b981, #059669);
                }
                
                .s-btn.btn-success {
                    background: linear-gradient(135deg, #22c55e, #16a34a);
                }
                
                .s-btn.btn-danger {
                    background: linear-gradient(135deg, #ef4444, #dc2626);
                }
                
                .s-btn:hover, #mainForm input[type="submit"]:hover, #mainForm input[type="button"]:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 16px rgba(31, 59, 179, 0.35);
                }
                
                .makeHidden {
                    display: none;
                }
                
                .s-margin__bottom-1 {
                    margin-bottom: 0.25rem;
                }
                
                .select2-container {
                    width: 100% !important;
                    min-height: 46px;
                }
                
                .select2-container--default .select2-selection--single {
                    height: 46px;
                    border: 1px solid #d2d9ef;
                    border-radius: 10px;
                    padding: 0 10px;
                }
                
                .select2-container--default .select2-selection--single .select2-selection__rendered {
                    line-height: 44px;
                    color: #1f294a;
                }
                
                .select2-container--default .select2-selection--single .select2-selection__arrow {
                    height: 44px;
                }
                
                .form-control {
                    display: block;
                    width: 100%;
                    padding: 12px 14px;
                    font-size: 0.94rem;
                    font-weight: 400;
                    line-height: 1.5;
                    color: #1f294a;
                    background-color: #fff;
                    background-clip: padding-box;
                    border: 1px solid #d2d9ef;
                    -webkit-appearance: none;
                    -moz-appearance: none;
                    appearance: none;
                    border-radius: 10px;
                    transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
                }
                
                .bibliography-hub .biblio-toolbar {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 14px;
                    margin-bottom: 18px;
                }
                .bibliography-hub .biblio-hero__actions .btn,
                .bibliography-hub .biblio-hero__actions .eddc-button-wrapper a {
                    border: none;
                    border-radius: 12px;
                    background: linear-gradient(135deg, #1f3bb3, #5563de);
                    color: #fff;
                    padding: 9px 18px;
                    font-weight: 600;
                    box-shadow: 0 12px 24px rgba(31, 59, 179, 0.28);
                    transition: all 0.2s ease;
                    text-align: center;
                    display: block;
                    text-decoration: none;
                    min-width: 200px; /* Equal width for both buttons */
                }
                .bibliography-hub .biblio-hero__actions .btn:hover,
                .bibliography-hub .biblio-hero__actions .eddc-button-wrapper a:hover,
                .bibliography-hub .biblio-hero__actions .btn:focus,
                .bibliography-hub .biblio-hero__actions .eddc-button-wrapper a:focus {
                    background: linear-gradient(135deg, #182f8b, #4451c4);
                    transform: translateY(-1px);
                    box-shadow: 0 16px 28px rgba(31, 59, 179, 0.32);
                }
                .bibliography-hub .biblio-toolbar__hint {
                    font-size: 0.82rem;
                    color: #66739c;
                    max-width: 320px;
                    line-height: 1.35;
                    flex: 1 1 auto;
                    text-align: right;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                
                /* Enhanced form header styling */
                .form-header {
                    text-align: center;
                    margin-bottom: 25px;
                    padding-bottom: 15px;
                    border-bottom: 1px solid #eef2f7;
                }
                
                .form-header .save-btn-container {
                    margin-bottom: 15px;
                }
                
                /* Collapsible section styling */
                .form-section {
                    margin-bottom: 18px;
                    border: 1px solid #e2e8f0;
                    border-radius: 10px;
                    overflow: hidden;
                }
                
                .form-section-header {
                    background: #f8fafc;
                    padding: 12px 16px;
                    cursor: pointer;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    font-weight: 600;
                    color: #4f46e5;
                    border-bottom: 1px solid #e2e8f0;
                }
                
                .form-section-content {
                    padding: 16px;
                    display: none; /* Hidden by default */
                }
                
                .form-section-content.expanded {
                    display: block;
                }
                
                .form-section-toggle {
                    display: inline-block;
                    width: 0;
                    height: 0;
                    border-left: 5px solid transparent;
                    border-right: 5px solid transparent;
                    border-top: 6px solid currentColor;
                    margin-left: 8px;
                    transition: transform 0.2s;
                }
                
                .form-section-header.active .form-section-toggle {
                    transform: rotate(180deg);
                }
                
                /* Basic form fields styling (always visible) */
                .basic-form-fields {
                    padding: 16px;
                }
                
                /* Grouped form elements */
                .form-group-container {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 16px;
                    margin-bottom: 12px;
                }
                
                .form-group-item {
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                }
                
                .form-group-label {
                    font-weight: 600;
                    color: #4f46e5;
                    font-size: 0.9rem;
                }
                
                .form-group-input {
                    width: 100%;
                    padding: 10px 12px;
                    border: 1px solid #d2d9ef;
                    border-radius: 8px;
                    font-size: 0.9rem;
                    background: #fff;
                    transition: border-color 0.2s ease, box-shadow 0.2s ease;
                }
                
                .form-group-input:focus {
                    outline: none;
                    border-color: #4c60df;
                    box-shadow: 0 0 0 3px rgba(76, 96, 223, 0.18);
                }
                
                /* Special styling for larger text areas */
                .form-textarea-large {
                    min-height: 120px !important;
                }
                
                /* Special styling for iframe containers */
                .form-iframe-container {
                    background: #f8fafc;
                    border-radius: 8px;
                    border: 1px solid #e2e8f0;
                    padding: 12px;
                    margin-top: 8px;
                }
                

                
                @media (max-width: 768px) {
                    .form-group-container {
                        grid-template-columns: 1fr;
                    }
                    
                    .bibliography-hub {
                        padding: 1rem;
                    }
                    .bibliography-hub .biblio-hero {
                        flex-direction: column;
                        align-items: flex-start;
                        text-align: left;
                        gap: 10px;
                    }
                    .bibliography-hub .biblio-hero__icon {
                        flex: 0 0 48px;
                        height: 48px;
                        font-size: 22px;
                    }
                    .bibliography-hub .biblio-hero__text p {
                        white-space: normal;
                        max-width: none;
                    }
                    .bibliography-hub .biblio-toolbar {
                        justify-content: flex-start;
                        flex-direction: column;
                        align-items: stretch;
                    }
                    .bibliography-hub .biblio-toolbar__hint {
                        max-width: none;
                        text-align: left;
                    }
                    .bibliography-hub .biblio-search-grid {
                        grid-template-columns: 1fr;
                    }
                    .bibliography-hub .biblio-actions {
                        justify-content: flex-start;
                    }
                    .bibliography-hub .biblio-table-card {
                        padding: 18px 16px;
                    }
                    .biblio-table-wrapper {
                        padding: 16px;
                    }
                }
            </style>
            <div class="biblio-hero">
                <div class="biblio-hero__icon">
                    <span class="fa fa-database" aria-hidden="true"></span>
                </div>
                <div class="biblio-hero__text">
                    <h2><?php echo __('Bibliography Workspace'); ?></h2>
                    <p><?php echo __('Tingkatkan kinerja pengelolaan koleksi, kurasi metadata, dan pencarian judul secara lebih efisien.'); ?></p>
                </div>
                <div class="biblio-hero__actions">
                    <div class="eddc-button-wrapper">
                        <span class="eddc-badge"><?php echo __('NEW E-DDC'); ?></span>
                        <a href="<?php echo MWB; ?>bibliography/index.php?action=detail"
                           class="btn btn-default <?php echo (isset($_GET['action']) && $_GET['action'] === 'detail') ? 'active' : ''; ?>"><?php echo __('Add New Bibliography'); ?></a>
                    </div>
                    <a href="<?php echo MWB; ?>bibliography/index.php"
                       class="btn btn-default <?php echo (!isset($_GET['action']) || $_GET['action'] !== 'detail') ? 'active' : ''; ?>"><?php echo __('Bibliographic List'); ?></a>
                </div>
            </div>
            
            <div class="biblio-search-card">
                    <div class="biblio-search-toggle collapsed" id="searchToggle">
                        <span class="fa fa-search" aria-hidden="true"></span>
                        <span><?php echo __('Search Bibliography'); ?></span>
                        <span class="fa fa-chevron-down toggle-icon" aria-hidden="true"></span>
                    </div>
                    <div class="biblio-search-content collapse" id="searchContent">
                        <form name="search" action="<?php echo MWB; ?>bibliography/index.php" id="search" method="get"
                              class="biblio-search-grid">
                            <div class="biblio-field biblio-field--keywords">
                                <label for="keywords"><?php echo __('Keywords'); ?></label>
                                <input type="text" name="keywords" id="keywords" class="form-control" placeholder="<?php echo __('Cari judul, pengarang, atau topik'); ?>"/>
                            </div>
                            <div class="biblio-field">
                                <label for="field"><?php echo __('Search Field'); ?></label>
                                <select name="field" id="field" class="form-control">
                                    <option value="0"><?php echo __('All Fields'); ?></option>
                                    <option value="title"><?php echo __('Title/Series Title'); ?> </option>
                                    <option value="subject"><?php echo __('Topics'); ?></option>
                                    <option value="author"><?php echo __('Authors'); ?></option>
                                    <option value="isbn"><?php echo __('ISBN/ISSN'); ?></option>
                                    <option value="publisher"><?php echo __('Publisher'); ?></option>
                                </select>
                            </div>
                            <div class="biblio-actions">
                                <span class="biblio-actions-label"><?php echo __('Actions'); ?></span>
                                <div class="biblio-actions-buttons">
                                    <button type="submit" id="doSearch" class="biblio-search-btn">
                                        <span class="fa fa-search" aria-hidden="true"></span>
                                        <span><?php echo __('Search'); ?></span>
                                    </button>
                                    <button type="button" class="biblio-advanced-toggle" data-toggle="collapse"
                                            data-target="#advancedFilter" aria-expanded="false" aria-controls="advancedFilter">
                                        <span class="fa fa-sliders" aria-hidden="true"></span>
                                        <span><?php echo __('Advanced Filter'); ?></span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <div class="collapse biblio-advanced-panel" id="advancedFilter">
                <div class="biblio-filter-grid">
                    <label>
                        <span><?php echo __('Hide in OPAC'); ?></span>
                        <select name="opac_hide" class="form-control">
                            <option value=""><?php echo __('ALL'); ?></option>
                            <option value="0"><?php echo __('Show'); ?> </option>
                            <option value="1"><?php echo __('Hide'); ?></option>
                        </select>
                    </label>
                    <label>
                        <span><?php echo __('Promote To Homepage'); ?></span>
                        <select name="promoted" class="form-control">
                            <option value=""><?php echo __('ALL'); ?></option>
                            <option value="0"><?php echo __('Don\'t Promote'); ?> </option>
                            <option value="1"><?php echo __('Promote'); ?></option>
                        </select>
                    </label>
                </div>
            </div>
            <?php
            // enable UCS?
            if ($sysconf['ucs']['enable']) {
                ?>
                <a href="#"
                   onclick="ucsUpload('<?php echo MWB; ?>bibliography/ucs_upload.php', serializeChbox('dataList'))"
                   class="biblio-ucs-link s-btn btn btn-default notAJAX">
                    <span class="fa fa-cloud-upload" aria-hidden="true"></span>
                    <span><?php echo __('Upload Selected Bibliographic data to Union Catalog Server*'); ?></span>
                </a>
                <?php
            }
            ?>
        </div>
    </div>
    <?php
    /* search form end */
}
/* main content */
if (isset($_GET['action']) && $_GET['action'] == 'history') {

    $biblioID = utility::filterData('biblioID', 'get', true, true, true);
    $table_spec = 'biblio_log AS bl';
    $criteria = 'bl.biblio_id=' . $biblioID;
    // create datagrid
    $datagrid = new simbio_datagrid();
    $datagrid->setSQLColumn('bl.date AS \'' . __('Date') . '\'',
        'bl.realname AS \'' . __('User Name') . '\'',
        'bl.additional_information AS \'' . __('Additional Information') . '\'');
    $datagrid->modifyColumnContent(2, 'callback{affectedDetail}');
    $datagrid->setSQLorder('bl.biblio_log_id DESC');
    $datagrid->sql_group_by = 'bl.date';
    $datagrid->setSQLCriteria($criteria);
    // set table and table header attributes
    $datagrid->table_attr = 'id="dataList" class="s-table table"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';

    function affectedDetail($obj_db, $array_data)
    {
        $_q = $obj_db->query("SELECT action,affectedrow,title,additional_information FROM biblio_log WHERE `date` LIKE '" . $array_data[0] . "'");
        $str = '';
        $title = '';
        if ($_q->num_rows > 0) {
            while ($_data = $_q->fetch_assoc()) {
                $title = $_data['title'];
                $str .= ' - ' . $_data['action'] . ' ' . $_data['affectedrow'] . ' : <i>' . $_data['additional_information'] . '</i><br/>';
            }
        }
        return $title . '</br>' . $str;
    }

    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, false);

    $_q = $dbs->query("SELECT title FROM biblio WHERE biblio_id=" . $biblioID);
    if ($_q->num_rows > 0) {
        $_d = $_q->fetch_row();
        echo '<div class="infoBox">' . __('Biblio Log') . ' : <strong>' . $_d[0] . '</strong></div>';
    }

    echo $datagrid_result;
} elseif (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {
    if ((isset($_GET['action'])) AND ($_GET['action'] == 'detail')) {
        # ADV LOG SYSTEM - STIIL EXPERIMENTAL
        $log = new AlLibrarian('1153', array("username" => $_SESSION['uname'], "uid" => $_SESSION['uid'], "realname" => $_SESSION['realname']));
    } elseif ((isset($_GET['itemID'])) AND (isset($_GET['detail'])) AND ($_GET['detail'] == true)) {
        $log = new AlLibrarian('1155', array("username" => $_SESSION['uname'], "uid" => $_SESSION['uid'], "realname" => $_SESSION['realname'], "biblio_id" => $_GET['itemID']));
    }

    if (!($can_read AND $can_write)) {

        die('<div class="errorBox">' . __('You are not authorized to view this section') . '</div>');
    }
    /* RECORD FORM */
    // try query
    $itemID = (integer)isset($_POST['itemID']) ? $_POST['itemID'] : 0;
    $_sql_rec_q = sprintf('SELECT b.*, p.publisher_name, pl.place_name FROM biblio AS b
    LEFT JOIN mst_publisher AS p ON b.publisher_id=p.publisher_id
    LEFT JOIN mst_place AS pl ON b.publish_place_id=pl.place_id
    WHERE biblio_id=%d', $itemID);
    $rec_q = $dbs->query($_sql_rec_q);
    $rec_d = $rec_q->fetch_assoc();

    // create new instance
    $form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'], 'post');
    
    $form->submit_button_attr = 'name="saveData" value="' . __('Save') . '" class="s-btn btn btn-default"'; // Keep the original button for compatibility
    // form table attributes
    $form->table_attr = 'id="dataList" cellpadding="0" cellspacing="0"';
    $form->table_header_attr = 'class="alterCell"';
    $form->table_content_attr = 'class="alterCell2"';

    //custom button
    if (isset($itemID)) {
        $form->addCustomBtn('history', __('Log'), $_SERVER['PHP_SELF'] . '?action=history&ajaxLoad=true&biblioID=' . $itemID, ' class="s-btn btn btn-success"');
    }

    $visibility = 'makeVisible s-margin__bottom-1';
    // edit mode flag set
    if ($rec_q->num_rows > 0) {
        $form->edit_mode = true;
        // record ID for delete process
        if (!$in_pop_up) {
            // form record id
            $form->record_id = $itemID;
        } else {
            $form->addHidden('updateRecordID', $itemID);
            if (isset($_POST['itemCollID'])) {
                $form->addHidden('itemCollID', $_POST['itemCollID']);
            }
            $form->back_button = false;
        }
        // form record title
        $form->record_title = $rec_d['title'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="' . __('Update') . '" class="s-btn btn btn-primary"';
        // element visibility class toogle
        $visibility = 'makeHidden s-margin__bottom-1';

        // custom field data query
        $_sql_rec_cust_q = sprintf('SELECT * FROM biblio_custom WHERE biblio_id=%d', $itemID);
        $rec_cust_q = $dbs->query($_sql_rec_cust_q);
        $rec_cust_d = $rec_cust_q->fetch_assoc();
    } else {
        $_SESSION['biblioToBiblio'] = array();
    }

    // include custom fields file
    if (file_exists(MDLBS . 'bibliography/custom_fields.inc.php')) {
        include MDLBS . 'bibliography/custom_fields.inc.php';
    }

    /* Form Element(s) */
    // biblio title
    $form->addTextField('textarea', 'title', __('Title') . '*', $rec_d['title'] ?? '', 'rows="1" class="form-control"',
        __('Main title of collection. Separate child title with colon and pararel title with equal (=) sign.'));

    // biblio authors
    // $str_input = '<div class="'.$visibility.'"><a class="notAJAX button" href="javascript: openHTMLpop(\''.MWB.'bibliography/pop_author.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.__('Authors/Roles').'\')">'.__('Add Author(s)').'</a></div>';
    $str_input = '<div class="' . $visibility . '"><a class="s-btn btn btn-default notAJAX openPopUp" href="' . MWB . 'bibliography/pop_author.php?biblioID=' . ($rec_d['biblio_id'] ?? '') . '" title="' . __('Authors/Roles') . '">' . __('Add Author(s)') . '</a></div>';
    $str_input .= '<iframe name="authorIframe" id="authorIframe" class="form-control" style="width: 100%; height: 100px;" src="' . MWB . 'bibliography/iframe_author.php?biblioID=' . ($rec_d['biblio_id'] ?? '') . '&block=1"></iframe>';
    $form->addAnything(__('Author(s)'), $str_input);

    // biblio edition
    $form->addTextField('text', 'edition', __('Edition'), $rec_d['edition'] ?? '', 'class="form-control" style="width: 50%;"', __('A version of publication having substantial changes or additions.'));
    // biblio specific detail info/area
    $form->addTextField('textarea', 'specDetailInfo', __('Specific Detail Info'), $rec_d['spec_detail_info'] ?? '', 'rows="2" class="form-control', __('explain more details about an item e.g. scale within a map, running time in a movie dvd.'));
    // biblio item batch add (by.ido alit)
    $pattern_options = array(
        // default value
        array($sysconf['batch_item_code_pattern'], $sysconf['batch_item_code_pattern'])
    );
    // get pattern from database
    $pattern_q = $dbs->query('SELECT setting_value FROM setting WHERE setting_name = \'batch_item_code_pattern\'');
    if (!$dbs->errno) {
        // empty pattern
        $pattern_options = array(array('', '-- ' . __('Choose pattern') . ' --'));
        $pattern_d = $pattern_q->fetch_row();
        $val = @unserialize($pattern_d[0]);
        if (!empty($val)) {
            foreach ($val as $v) {
                $pattern_options[] = array($v, $v);
            }
        }
    }

    // Modified by Eddy Subratha
    // To avoid a miss processing after a pattern created, I think we should hide the Item Code Manager below
    // $str_input .= '<a href="'.MWB.'master_file/item_code_pattern.php" height="420px" class="s-btn btn btn-default notAJAX openPopUp notIframe" title="'.__('Item code pattern manager').'">'.__('View available pattern').'</a>';
    $str_input = '<div class="form-inline">';
    $str_input .= simbio_form_element::selectList('itemCodePattern', $pattern_options, '', 'class="form-control col"') . '&nbsp;';
    $str_input .= '<input type="text" class="form-control col-4 noAutoFocus" name="totalItems" placeholder="' . __('Total item(s)') . '" />&nbsp;';
    $str_input .= '<div class="' . $visibility . '">
  <div class="bnt btn-group"><div class="btn btn-info" data-toggle="collapse" data-target="#batchItemDetail" aria-expanded="false" aria-controls="batchItemDetail">' . __('Options') . '</div>';
    $str_input .= '<a href="' . MWB . 'bibliography/pop_pattern.php" height="420px" class="s-btn btn btn-default notAJAX openPopUp notIframe"  title="' . __('Add new pattern') . '">' . __('Add New Pattern') . '</a></div></div>';
    $str_input .= '<div class="collapse" id="batchItemDetail" style="padding:10px;width:100%; text-align:left !important;">';

    // location
    $location_q = $dbs->query("SELECT location_id, location_name FROM mst_location");
    $location_options = array(array('', '-- ' . __('Location') . ' --'));
    while ($location_d = $location_q->fetch_row()) {
        $location_options[] = array($location_d[0], $location_d[1]);
    }
    $str_input .= '<div class="form-group divRow p-1"><div class="col-3">' . __('Location') . '</div>';
    $str_input .= simbio_form_element::selectList('locationID', $location_options, '', 'class="form-control col-4"') . '</div>';

    // item site
    $str_input .= '<div class="form-group divRow p-1"><div class="col-3">' . __('Shelf Location') . '</div>';
    $str_input .= simbio_form_element::textField('text', 'itemSite', '', 'class="form-control col-4"') . '</div>';

    // collection type
    $coll_type_q = $dbs->query("SELECT coll_type_id, coll_type_name FROM mst_coll_type");
    $coll_type_options = array(array('', '--' . __('Collection Type') . '--'));
    while ($coll_type_d = $coll_type_q->fetch_row()) {
        $coll_type_options[] = array($coll_type_d[0], $coll_type_d[1]);
    }
    $str_input .= '<div class="form-group divRow p-1"><div class="col-3">' . __('Collection Type') . '</div>';
    $str_input .= simbio_form_element::selectList('collTypeID', $coll_type_options, '', 'class="form-control col-4"') . '</div> ';

    // item status
    $item_status_q = $dbs->query("SELECT item_status_id, item_status_name FROM mst_item_status");
    $item_status_options[] = array('0', __('Available'));
    while ($item_status_d = $item_status_q->fetch_row()) {
        $item_status_options[] = array($item_status_d[0], $item_status_d[1]);
    }
    $str_input .= '<div class="form-group divRow p-1"><div class="col-3">' . __('Item Status') . '</div>';
    $str_input .= simbio_form_element::selectList('itemStatusID', $item_status_options, '', 'class="form-control col-4"') . '</div> ';

    // item source
    $source_options[] = array('1', __('Buy'));
    $source_options[] = array('2', __('Prize/Grant'));
    $str_input .= '<div class="form-group divRow p-1"><div class="col-3">' . __('Source') . '</div>';
    $str_input .= simbio_form_element::selectList('source', $source_options, '', 'class="form-control col-4"') . '</div> ';

    //order date
    $str_input .= '<div class="form-group divRow p-1"><div class="col-3">' . __('Order Date') . '</div>';
    $str_input .= simbio_form_element::dateField('orDate', date('Y-m-d'), 'class="form-control"') . '</div>';

    //receiving date
    $str_input .= '<div class="form-group divRow p-1"><div class="col-3">' . __('Receiving Date') . '</div>';
    $str_input .= simbio_form_element::dateField('recvDate', date('Y-m-d'), ' class="form-control col-12"') . '</div>';

    // order number
    $str_input .= '<div class="form-group divRow p-1"><div class="col-3">' . __('Order Number') . '</div>';
    $str_input .= simbio_form_element::textField('text', 'ordNo', '', 'class="form-control"') . '</div>';

    // invoice
    $str_input .= '<div class="form-group divRow p-1"><div class="col-3">' . __('Invoice') . '</div>';
    $str_input .= simbio_form_element::textField('text', 'invoice', '', 'class="form-control col-4"') . '</div>';

    // invoice date
    $str_input .= '<div class="form-group divRow p-1"><div class="col-3">' . __('Invoice Date') . '</div>';
    $str_input .= simbio_form_element::dateField('invcDate', date('Y-m-d'), ' class="form-control col-12"') . '</div>';

    // supplier
    $supplier_q = $dbs->query("SELECT supplier_id, supplier_name FROM mst_supplier");
    $supplier_options[] = array('0', __('Not Applicable'));
    while ($supplier_d = $supplier_q->fetch_row()) {
        $supplier_options[] = array($supplier_d[0], $supplier_d[1]);
    }
    $str_input .= '<div class="form-group divRow p-1"><div class="col-3">' . __('Supplier') . '</div>';
    $str_input .= simbio_form_element::selectList('supplierID', $supplier_options, '', 'class="form-control col-4"') . '</div> ';

    //price
    $str_input .= '<div class="form-group divRow p-1"><div class="col-3">' . __('Price') . '</div>';
    $str_input .= simbio_form_element::textField('text', 'price', '0', 'class="form-control col-3"');
    $str_input .= simbio_form_element::selectList('priceCurrency', $sysconf['currencies'], '', 'class="form-control col-2"') . '</div> ';

    $str_input .= '</div>';
    $form->addAnything(__('Item(s) code batch generator'), $str_input);
    // biblio item add
    if (!$in_pop_up AND $form->edit_mode) {
        // $str_input = '<div class="makeHidden s-margin__bottom-1"><a class="notAJAX button" href="javascript: openHTMLpop(\''.MWB.'bibliography/pop_item.php?inPopUp=true&action=detail&biblioID='.$rec_d['biblio_id'].'\', 650, 400, \''.__('Items/Copies').'\')">'.__('Add New Items').'</a></div>';
        $str_input = '<div class="makeHidden s-margin__bottom-1"><a class="s-btn btn btn-default notAJAX openPopUp" href="' . MWB . 'bibliography/pop_item.php?inPopUp=true&action=detail&biblioID=' . $rec_d['biblio_id'] . '" title="' . __('Items/Copies') . '" width="780" height="500">' . __('Add New Items') . '</a></div>';
        $str_input .= '<iframe name="itemIframe" id="itemIframe" class="form-control" style="width: 100%; height: 100px;" src="' . MWB . 'bibliography/iframe_item_list.php?biblioID=' . $rec_d['biblio_id'] . '&block=1"></iframe>' . "\n";
        $form->addAnything(__('Item(s) Data'), $str_input);
    }
    
    // biblio gmd
    // get gmd data related to this record from database
    $gmd_q = $dbs->query('SELECT gmd_id, gmd_name FROM mst_gmd');
    $gmd_options = array();
    while ($gmd_d = $gmd_q->fetch_row()) {
        $gmd_options[] = array($gmd_d[0], $gmd_d[1]);
    }
    $form->addSelectList('gmdID', __('GMD'), $gmd_options, $rec_d['gmd_id'] ?? '', 'class="select2"', __('General material designation. The physical form of publication.'));

    // biblio RDA content, media, carrier type
    foreach ($rda_cmc as $cmc => $cmc_name) {
        $cmc_options = array();
        $cmc_q = $dbs->query('SELECT id, ' . $cmc . '_type FROM mst_' . $cmc . '_type');
        $cmc_options = array();
        $cmc_options[] = array(0, __('Not set'));
        while (isset($cmc_q->num_rows) && $cmc_q->num_rows > 0 && $cmc_d = $cmc_q->fetch_row()) {
            $cmc_options[] = array($cmc_d[0], $cmc_d[1]);
        }
        if (isset($rec_d[$cmc . '_type_id'])) {
            $form->addSelectList($cmc . 'TypeID', __($cmc_name), $cmc_options, $rec_d[$cmc . '_type_id'] ?? '', 'class="select2"', __('RDA ' . $cmc_name . ' designation.'));
        } else {
            $form->addSelectList($cmc . 'TypeID', __($cmc_name), $cmc_options, '', 'class="select2"', __('RDA ' . $cmc_name . ' designation.'));
        }
    }


    // biblio publish frequencies
    // get frequency data related to this record from database
    $freq_q = $dbs->query('SELECT frequency_id, frequency FROM mst_frequency');
    $freq_options[] = array('0', __('Not Applicable'));
    while ($freq_d = $freq_q->fetch_row()) {
        $freq_options[] = array($freq_d[0], $freq_d[1]);
    }
    $str_input = '<div class="form-inline">';
    $str_input .= simbio_form_element::selectList('frequencyID', $freq_options, $rec_d['frequency_id'] ?? '', 'class="select2 col-3"');
    $str_input .= '<div class="col">' . __('Use this for Serial publication') . '</div>';
    $str_input .= '</div>';
    $form->addAnything(__('Frequency'), $str_input);
    // biblio ISBN/ISSN
    $form->addTextField('text', 'isbn_issn', __('ISBN/ISSN'), $rec_d['isbn_issn'] ?? '', 'class="form-control" style="width: 40%;"', __('Unique publishing number for each title of publication.'));
    // biblio publisher
    $publ_options[] = array('NONE', '');
    if (isset($rec_d['publisher_id'])) {
        $publ_q = $dbs->query(sprintf('SELECT publisher_id, publisher_name FROM mst_publisher WHERE publisher_id=%d', $rec_d['publisher_id'] ?? ''));
        while ($publ_d = $publ_q->fetch_row()) {
            $publ_options[] = array($publ_d[0], $publ_d[1]);
        }
    }
    $form->addSelectList('publisherID', __('Publisher'), $publ_options, $rec_d['publisher_id'] ?? '', 'class="select2" data-src="' . SWB . 'admin/AJAX_lookup_handler.php?format=json&allowNew=true" data-src-table="mst_publisher" data-src-cols="publisher_id:publisher_name"');
    // biblio publish year
    $form->addTextField('text', 'year', __('Publishing Year'), $rec_d['publish_year'] ?? '', 'class="form-control" style="width: 40%;"', __('Year of publication'));
    // biblio publish place
    $plc_options[] = array('NONE', '');
    if (isset($rec_d['publish_place_id'])) {
        $plc_q = $dbs->query(sprintf('SELECT place_id, place_name FROM mst_place WHERE place_id=%d', $rec_d['publish_place_id'] ?? ''));
        while ($plc_d = $plc_q->fetch_row()) {
            $plc_options[] = array($plc_d[0], $plc_d[1]);
        }
    }
    $form->addSelectList('placeID', __('Publishing Place'), $plc_options, $rec_d['publish_place_id'] ?? '', 'class="select2" data-src="' . SWB . 'admin/AJAX_lookup_handler.php?format=json&allowNew=true" data-src-table="mst_place" data-src-cols="place_id:place_name"');
    // biblio collation
    $form->addTextField('text', 'collation', __('Collation'), $rec_d['collation'] ?? '', 'class="form-control" style="width: 40%;"', __('Physical description of a publication e.g. publication length, width, page numbers, etc.'));
    // biblio classification
    $cls_options[] = array('NONE', '');
    if (isset($rec_d['classification'])) {
        $cls_options[] = array($rec_d['classification'], $rec_d['classification']);
    }
    $form->addSelectList('class', __('Classification'), $cls_options, $rec_d['classification'] ?? '', 'class="select2" data-src="' . SWB . 'admin/AJAX_lookup_handler.php?format=json&allowNew=true" data-src-table="mst_topic" data-src-cols="classification:classification:topic"');
    // biblio call_number
    $form->addTextField('text', 'callNumber', __('Call Number'), $rec_d['call_number'] ?? '', 'class="form-control" style="width: 40%;"', __('Sets of ID that put in the book spine.'));
    // biblio topics
    // $str_input = '<div class="'.$visibility.'"><a class="notAJAX button" href="javascript: openHTMLpop(\''.MWB.'bibliography/pop_topic.php?biblioID='.$rec_d['biblio_id'].'\', 500, 200, \''.__('Subjects/Topics').'\')">'.__('Add Subject(s)').'</a></div>';
    $str_input = '<div class="' . $visibility . ' s-margin__bottom-1"><a class="s-btn btn btn-default notAJAX openPopUp" href="' . MWB . 'bibliography/pop_topic.php?biblioID=' . ($rec_d['biblio_id'] ?? '') . '" title="' . __('Subjects/Topics') . '">' . __('Add Subject(s)') . '</a></div>';
    $str_input .= '<iframe name="topicIframe" id="topicIframe" class="form-control" style="width: 100%; height: 100px;" src="' . MWB . 'bibliography/iframe_topic.php?biblioID=' . ($rec_d['biblio_id'] ?? '') . '&block=1"></iframe>';
    $form->addAnything(__('Subject(s)'), $str_input);
    // biblio language
    // get language data related to this record from database
    $lang_q = $dbs->query("SELECT language_id, language_name FROM mst_language");
    $lang_options = array();
    while ($lang_d = $lang_q->fetch_row()) {
        $lang_options[] = array($lang_d[0], $lang_d[1]);
    }
    $form->addSelectList('languageID', __('Language'), $lang_options, $rec_d['language_id'] ?? '', 'class="select2"', __('Language use by publication.'));
    // biblio cover image
    $str_input = '<div class="row">';
    $str_input .= '<div class="col-2">';
    $str_input .= '<div id="imageFilename" class="s-margin__bottom-1">';
    $upper_dir = '';
    if ($in_pop_up) {
        $upper_dir = '../../';
    }
    if (isset($rec_d['image']) && file_exists('../../../images/docs/' . $rec_d['image'])) {
        $str_input .= '<a href="' . SWB . 'images/docs/' . ($rec_d['image'] ?? '') . '" class="openPopUp notAJAX" title="' . __('Click to enlarge preview') . '">';
        $str_input .= '<img src="' . $upper_dir . '../images/docs/' . urlencode($rec_d['image'] ?? '') . '" class="img-fluid rounded" alt="Image cover">';
        $str_input .= '</a>';
        $str_input .= '<a href="' . MWB . 'bibliography/index.php" postdata="removeImage=true&bimg=' . $itemID . '&img=' . ($rec_d['image'] ?? '') . '" loadcontainer="imageFilename" class="s-margin__bottom-1 mt-1 s-btn btn btn-danger btn-block makeHidden removeImage">' . __('Remove Image') . '</a>';
    } else {
        $str_input .= '<img src="' . $upper_dir . '../lib/minigalnano/createthumb.php?filename=images/default/image.png&width=130" class="img-fluid rounded" alt="Image cover">';
    }
    $str_input .= '</div>';
    $str_input .= '</div>';
    $str_input .= '<div class="custom-file col-7">';
    $str_input .= simbio_form_element::textField('file', 'image', '', 'class="custom-file-input" id="customFile"');
    $str_input .= '<label class="custom-file-label" for="customFile">' . __('Choose file') . '</label>';
    $str_input .= '<div style="padding: 10px;margin-left: -25px;">';
    $str_input .= '<div>' . __('Or download from url') . '</div>';
    $str_input .= '<div class="form-inline">
                  <input type="text" id="getUrl" class="form-control" style="width:190px" placeholder="Paste url address here">
                  <div class="input-group-append">
                  <button class="btn btn-default" type="button" id="getImage">' . __('Download') . ' <i class="fa fa-spin fa-cog hidden" id="imgLoader"></i></button>
                  <button class="btn btn-default openPopUp notAJAX ml-2" type="button" id="showEngine" onclick="toggle_search($(\'#title\').val());">' . __('Trying search in DuckduckGo') . '</button>
                  </div>
                  </div>';
    $str_input .= '</div>';
    $str_input .= '</div>';
    $str_input .= ' <div class="mt-2 ml-2">Maximum ' . $sysconf['max_image_upload'] . ' KB</div>';
    $str_input .= '</div>';
    $str_input .= '<textarea id="base64picstring" name="base64picstring" style="display: none;"></textarea>';
    $str_input .= '</div></div></div>';

    //for scanner
    if ($sysconf['scanner'] !== false) {
        $str_input .= '<p>' . __('or scan a cover') . '</p>';
        // $str_input .= '<textarea id="base64picstring" name="base64picstring" style="display: none;"></textarea>';

        if ($sysconf['scanner'] == 'html5') {
            $str_input .= '<input type="button" value="' . __('Show scan dialog') . '" class="button btn btn-default openPopUp" onclick="toggle_dialog();" />';
            $str_input .= '<input type="button" value="' . __('Reset') . '" class="button btn btn-danger openPopUp ml-1" onclick="scan_reset();" />';
            $str_input .= '<div id="scan_overlay" style="display: none; position: absolute; left: 0; top: 0; width: 100%; height: 100%; z-index: 1000; background: rgba(192, 194, 201, 0.5);">';
            $str_input .= '<div id="scan_dialog" title="' . __('Scan a cover') . '">';
            $str_input .= '<div id="scan_options_std" style="margin: 5px;"><label>' . __('Format:') . ' <select id="scan_type" onchange="scan_type();">';
            $str_input .= '<option value="png">PNG</option><option value="jpg">JPEG</option></select></label> ';
            $str_input .= '<input type="button" id="btn_getscan" class="button btn" onclick="scan()" value="' . __('Scan') . '" />';
            $str_input .= '<i style="margin-left: 10px; cursor: pointer; cursor: hand;" title="' . __('Click to show or hide options') . '" onclick="toggle_options()" class="fa fa-gear fa-2x"></i></div>';
            $str_input .= '<div id="scan_options" class="makeHidden s-margin__bottom-1" style="margin: 5px;">';
            $str_input .= '<p style="padding: 3px 0;"><label>' . __('History index:') . ' <input type="text" id="scan_history" value="1" style="width: 60px;" /></label> <input type="button" id="btn_getrecall" class="button btn" onclick="scan_recall" value="' . __('Recall') . '" /></p>';
            $str_input .= '<p style="padding: 3px 0;"><label>' . __('Host:') . ' <input type="text" id="scan_host" value="localhost" /></label> | <label>Port: <input type="text" id="scan_port" patter="\d*" maxlength="6" size="6" style="width: 60px;" value="8811" /></label> <input type="button" id="btn_getmachine" class="button btn" onclick="scan_init()" value="' . __('Get machine') . '" /></p>';
            $str_input .= '<p style="padding: 3px 0;"><label>' . __('Scanner:') . ' <select id="scan_machine" readonly><option>' . __('Default') . '</option></select></label></p>';
            $str_input .= '<p style="padding: 3px 0;">' . __('Resolution') . ', <label>' . __('Horizontal:') . ' <input type="text" id="scan_res_x" value="300" pattern="\d*" maxlength="3" size="3" style="width: 60px;" />dpi</label> - <label>' . __('Vertical:') . ' <input type="text" id="scan_res_y" value="300" pattern="\d*" maxlength="3" size="3" style="width: 60px;" />dpi</label></p>';
            $str_input .= '<p style="padding: 3px 0;">' . __('Capture') . ', <label>' . __('Width:') . ' <input type="text" id="scan_capture_w" value="2550" pattern="\d*" maxlength="4" size="4" style="width: 60px;" />px</label> - <label>' . __('Height:') . ' <input type="text" id="scan_capture_h" value="3507" pattern="\d*" maxlength="4" size="4" style="width: 60px;" />px</label></p>';
            $str_input .= '<p>' . __('Result') . ', <label>' . __('Max Width:') . ' <input type="text" id="scan_max_w" value="360" pattern="\d*" maxlength="3" size="3" style="width: 60px;" />px</label> - <label>' . __('Max Height:') . ' <input type="text" id="scan_max_h" value="480" pattern="\d*" maxlength="3" size="3" style="width: 60px;" />px</label></p></div>';
            $str_input .= '<div id="scan_container" style="margin: 5px;"><div style="height: 550px; width: 390px; overflow: auto; float: left;"><p>' . __('Scan result') . '</p><img id="my_imgdata" style="margin: auto;" /></div>';
            $str_input .= '<div style="padding-left: 10px; height: 550; width: 400px; overflow: auto; float: left;"><p>' . __('Preview') . ' <input type="button" class="button btn" value="' . __('Rotate Left') . '" onclick="scan_rotate(\'left\')" /> <input type="button" class="button btn" value="' . __('Rotate Right') . '" onclick="scan_rotate(\'right\')" /></p><canvas id="my_selected" style="border: 1px solid #CCC; margin: auto;"></canvas></div></div></div></div>';
        }
    }

    $form->addAnything(__('Image'), $str_input);

    // biblio attachment/digital files
    if (!$in_pop_up) {
        if ($form->edit_mode) {
            // Edit mode: show button and iframe with existing attachments
            $str_input = '<div class="s-margin__bottom-1"><a class="s-btn btn btn-primary notAJAX openPopUp" width="900" height="600" href="' . MWB . 'bibliography/pop_attach.php?biblioID=' . $rec_d['biblio_id'] . '" title="' . __('Digital Content/Attachment') . '" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border: none; padding: 8px 16px; border-radius: 6px; color: white; font-weight: 600;"><i class="fa fa-upload"></i> ' . __('Add Digital Content/Attachment') . '</a></div>';
            $str_input .= '<iframe name="attachIframe" id="attachIframe" class="form-control" style="width: 100%; height: 120px; border: 1px solid #e5e7eb; border-radius: 8px;" src="' . MWB . 'bibliography/iframe_attach.php?biblioID=' . $rec_d['biblio_id'] . '&block=1"></iframe>' . "\n";
        } else {
            // Add new mode: show button and iframe for session-based attachments
            $str_input = '<div class="s-margin__bottom-1"><a class="s-btn btn btn-primary notAJAX openPopUp" width="900" height="600" href="' . MWB . 'bibliography/pop_attach.php" title="' . __('Digital Content/Attachment') . '" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border: none; padding: 8px 16px; border-radius: 6px; color: white; font-weight: 600;"><i class="fa fa-upload"></i> ' . __('Add Digital Content/Attachment') . '</a></div>';
            $str_input .= '<iframe name="attachIframe" id="attachIframe" class="form-control" style="width: 100%; height: 120px; border: 1px solid #e5e7eb; border-radius: 8px;" src="' . MWB . 'bibliography/iframe_attach.php"></iframe>' . "\n";
        }
        $form->addAnything(__('Digital Content/Attachment'), $str_input);
    }

    if (isset($biblio_custom_fields)) {
        if (is_array($biblio_custom_fields) && $biblio_custom_fields) {
            foreach ($biblio_custom_fields as $fid => $cfield) {

                // custom field properties
                $cf_dbfield = $cfield['dbfield'];
                $cf_label = $cfield['label'];
                $cf_default = $cfield['default'];
                $cf_class = $cfield['class'] ?? '';
                $cf_note = $cfield['note'] ?? '';
                $cf_data = (isset($cfield['data']) && $cfield['data']) ? unserialize($cfield['data']) : array();

                // get data field record
                if (isset($rec_cust_d[$cf_dbfield]) && @unserialize($rec_cust_d[$cf_dbfield]) !== false) {
                    $rec_cust_d[$cf_dbfield] = unserialize($rec_cust_d[$cf_dbfield]);
                }

                // custom field processing
                if (in_array($cfield['type'], array('text', 'longtext', 'numeric'))) {
                    $cf_max = isset($cfield['max']) ? $cfield['max'] : '200';
                    $cf_width = isset($cfield['width']) ? $cfield['width'] : '50';
                    $form->addTextField(($cfield['type'] == 'longtext') ? 'textarea' : 'text', $cf_dbfield, $cf_label, $rec_cust_d[$cf_dbfield] ?? $cf_default, ' class="form-control ' . $cf_class . '" style="width: ' . $cf_width . '%;" maxlength="' . $cf_max . '"', $cf_note);
                } else if ($cfield['type'] == 'dropdown') {
                    $form->addSelectList($cf_dbfield, $cf_label, $cf_data, $rec_cust_d[$cf_dbfield] ?? $cf_default, ' class="form-control ' . $cf_class . '"');
                } else if ($cfield['type'] == 'checklist') {
                    $form->addCheckBox($cf_dbfield, $cf_label, $cf_data, $rec_cust_d[$cf_dbfield] ?? $cf_default, ' class="form-control ' . $cf_class . '"');
                } else if ($cfield['type'] == 'choice') {
                    $form->addRadio($cf_dbfield, $cf_label, $cf_data, $rec_cust_d[$cf_dbfield] ?? $cf_default, ' class="form-control ' . $cf_class . '"');
                } else if ($cfield['type'] == 'date') {
                    $form->addDateField($cf_dbfield, $cf_label, $rec_cust_d[$cf_dbfield] ?? NULL, ' class="form-control ' . $cf_class . '"');
                }
                unset($cf_data);
            }
        }
    }
    
    // get advance custom field based on plugin
    $js = '';
    Plugins::getInstance()->execute(Plugins::BIBLIOGRAPHY_CUSTOM_FIELD_FORM, ['form' => $form, 'js' => &$js]);

    // biblio hide from opac
    $hide_options[] = array('0', __('Show'));
    $hide_options[] = array('1', __('Hide'));
    $form->addHidden('opacHideOrigin', $rec_d['opac_hide']??0);
    $form->addRadio('opacHide', __('Hide in OPAC'), $hide_options, isset($rec_d['opac_hide']) && $rec_d['opac_hide'] ? '1' : '0');
    // biblio promote to front page
    $promote_options[] = array('0', __('Don\'t Promote'));
    $promote_options[] = array('1', __('Promote'));
    $form->addRadio('promote', __('Promote To Homepage'), $promote_options, isset($rec_d['promoted']) && $rec_d['promoted'] ? '1' : '0');
    
    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="s-alert infoBox">'
            . __('You are going to edit biblio data') . ' : <b>' . $rec_d['title'] . '</b>  <br />' . __('Last Updated') . '&nbsp;' . date('d F Y h:i:s', strtotime($rec_d['last_update'])); //mfc
        if (isset($rec_d['image'])) {
            if (file_exists(IMGBS . 'docs/' . $rec_d['image'])) {
                $upper_dir = '';
                if ($in_pop_up) {
                    $upper_dir = '../../';
                }
                // Modified by Eddy Subratha
                // We removed image near the upload form
                // echo '<div id="biblioImage" class="s-biblio__cover"><img src="'.$upper_dir.'../lib/minigalnano/createthumb.php?`filename`=../../images/docs/'.urlencode($rec_d['image']).'&width=53" /></div>';
            }
        }
        echo '</div>' . "\n";
    }
    // print out the form object
    echo $form->printOut();
    // javascript
    ?>
    <script type="text/javascript">
        $(document).ready(function () {
            // popup pattern
            $('#class').change(function () {
                $('#callNumber').val($(this).val().replace('NEW:', ''));
            });

            $('.removeImage').click(function (e) {
                if (confirm('Are you sure you want to permanently remove this image?')) {
                    return true;
                } else {
                    return false;
                }
            });

            $(document).on('change', '.custom-file-input', function () {
                // $('#imageFilename img').attr('src',document.getElementById("image").files[0].name);
                var input = document.querySelector("#image");
                var fReader = new FileReader();
                fReader.readAsDataURL(input.files[0]);
                fReader.onloadend = function (event) {
                    var img = document.querySelector("#imageFilename img");
                    img.src = event.target.result;
                }
                let fileName = $(this).val().replace(/\\/g, '/').replace(/.*\//, '');
                $(this).parent('.custom-file').find('.custom-file-label').text(fileName);
            });

            $('#getImage').click(function () {
                $.post("<?php echo MWB ?>bibliography/scrape_image.php", {imageURL: $('#getUrl').val()})
                    .done(function (data) {
                        if (data.status == 'VALID') {
                            $('#base64picstring').val(data.image);
                            $('#imageFilename img').attr('src', data.message);
                        } else {
                            $('#base64picstring, #getUrl').val('');
                            parent.toastr.error("<?php echo __('Current url is not valid or your internet is down.') ?>", "Bibliography Image", {
                                "closeButton": true,
                                "debug": false,
                                "newestOnTop": false,
                                "progressBar": false,
                                "positionClass": "toast-top-right",
                                "preventDuplicates": false,
                                "onclick": null,
                                "showDuration": 300,
                                "hideDuration": 1000,
                                "timeOut": 5000,
                                "extendedTimeOut": 1000,
                                "showEasing": "swing",
                                "hideEasing": "linear",
                                "showMethod": "fadeIn",
                                "hideMethod": "fadeOut"
                            })
                        }
                    });
            });

            // Initialize collapsible sections
            $('.form-section-header').click(function() {
                var content = $(this).siblings('.form-section-content');
                var toggle = $(this).find('.form-section-toggle');
                
                if (content.hasClass('expanded')) {
                    content.removeClass('expanded');
                    $(this).removeClass('active');
                } else {
                    content.addClass('expanded');
                    $(this).addClass('active');
                }
            });
            

            
            <?php
            if (isset($js) && !empty($js))
            {
                echo $js;
            }
            ?>
        });
    </script>
    <?php
} else {
    # ADV LOG SYSTEM - STIIL EXPERIMENTAL
    $log = new AlLibrarian('1151', array("username" => $_SESSION['uname'], "uid" => $_SESSION['uid'], "realname" => $_SESSION['realname']));

    require SIMBIO . 'simbio_UTILS/simbio_tokenizecql.inc.php';
    require MDLBS . 'bibliography/biblio_utils.inc.php';
    require LIB . 'biblio_list_model.inc.php';

    // number of records to show in list
    $biblio_result_num = ($sysconf['biblio_result_num'] > 100) ? 100 : $sysconf['biblio_result_num'];

    // create datagrid
    $datagrid = new simbio_datagrid();

    // index choice
    if ($sysconf['index']['type'] == 'index' || $sysconf['index']['type'] == 'sphinx') {
        if ($sysconf['index']['type'] == 'sphinx') {
            require LIB . 'sphinx/sphinxapi.php';
            require LIB . 'biblio_list_sphinx.inc.php';
        } else {
            require LIB . 'biblio_list_index.inc.php';
        }

        // table spec
        $table_spec = 'search_biblio AS `index`
            LEFT JOIN biblio ON `index`.biblio_id=biblio.biblio_id
            LEFT JOIN user AS u ON biblio.uid=u.user_id
            LEFT JOIN item ON `index`.biblio_id=item.biblio_id';
        $str_criteria = 'index.biblio_id IS NOT NULL';
        $lastUpdateColumnIndex = null;
        if ($can_read AND $can_write) {
            $datagrid->setSQLColumn(
                'index.biblio_id',
                "index.title AS '" . __('Title') . "'",
                'index.labels',
                'index.image',
                "COUNT(item.item_id) AS 'copies_count'",
                'index.author',
                "index.classification AS '" . __('Class') . "'",
                "index.publish_year AS '" . __('Year') . "'",
                "index.isbn_issn AS '" . __('ISBN/ISSN') . "'",
                "index.last_update AS '" . __('Last Update') . "'"
            );
            $datagrid->modifyColumnContent(1, 'callback{showTitleAuthors}');
            $datagrid->modifyColumnContent(6, 'callback{renderClassBadge}');
            $datagrid->modifyColumnContent(7, 'callback{renderYearBadge}');
            $datagrid->modifyColumnContent(8, 'callback{renderIsbnBadge}');
            $datagrid->modifyColumnContent(9, 'callback{renderLastUpdateBadge}');
            $lastUpdateColumnIndex = 9;
        } else {
            $datagrid->setSQLColumn(
                "index.title AS '" . __('Title') . "'",
                'index.labels',
                'index.image',
                "COUNT(item.item_id) AS 'copies_count'",
                'index.author',
                "index.classification AS '" . __('Class') . "'",
                "index.publish_year AS '" . __('Year') . "'",
                "index.isbn_issn AS '" . __('ISBN/ISSN') . "'",
                "index.last_update AS '" . __('Last Update') . "'",
                "MAX(IFNULL(u.realname, u.username)) AS '" . __('Input By') . "'"
            );
            $datagrid->modifyColumnContent(0, 'callback{showTitleAuthors}');
            $datagrid->modifyColumnContent(5, 'callback{renderClassBadge}');
            $datagrid->modifyColumnContent(6, 'callback{renderYearBadge}');
            $datagrid->modifyColumnContent(7, 'callback{renderIsbnBadge}');
            $datagrid->modifyColumnContent(8, 'callback{renderLastUpdateBadge}');
            $lastUpdateColumnIndex = 8;
        }
        if ($lastUpdateColumnIndex !== null) {
            $datagrid->modifyColumnContent($lastUpdateColumnIndex, 'callback{renderLastUpdateBadge}');
        }
    $datagrid->invisible_fields = array(1, 2, 3, 4);
        $datagrid->setSQLorder('index.last_update DESC');

        // set group by
        $datagrid->sql_group_by = 'index.biblio_id';

    } else {
        require LIB . 'biblio_list.inc.php';

        // table spec
        $table_spec = 'biblio LEFT JOIN user AS u ON biblio.uid=u.user_id LEFT JOIN item ON biblio.biblio_id=item.biblio_id';
        $str_criteria = 'biblio.biblio_id IS NOT NULL';
        $lastUpdateColumnIndex = null;
        if ($can_read AND $can_write) {
            $datagrid->setSQLColumn(
                'biblio.biblio_id',
                'biblio.biblio_id AS bid',
                "biblio.title AS '" . __('Title') . "'",
                'biblio.image AS image_col',
                "COUNT(item.item_id) AS 'copies_count'",
                "biblio.classification AS '" . __('Class') . "'",
                "biblio.publish_year AS '" . __('Year') . "'",
                "biblio.isbn_issn AS '" . __('ISBN/ISSN') . "'",
                "biblio.last_update AS '" . __('Last Update') . "'"
            );
            $datagrid->modifyColumnContent(2, 'callback{showTitleAuthors}');
            $datagrid->modifyColumnContent(5, 'callback{renderClassBadge}');
            $datagrid->modifyColumnContent(6, 'callback{renderYearBadge}');
            $datagrid->modifyColumnContent(7, 'callback{renderIsbnBadge}');
            $datagrid->modifyColumnContent(8, 'callback{renderLastUpdateBadge}');
            $lastUpdateColumnIndex = 8;
        } else {
            $datagrid->setSQLColumn(
                'biblio.biblio_id AS bid',
                "biblio.title AS '" . __('Title') . "'",
                'biblio.image AS image_col',
                "COUNT(item.item_id) AS 'copies_count'",
                "biblio.classification AS '" . __('Class') . "'",
                "biblio.publish_year AS '" . __('Year') . "'",
                "biblio.isbn_issn AS '" . __('ISBN/ISSN') . "'",
                "biblio.last_update AS '" . __('Last Update') . "'",
                // Ambil langsung realname terakhir dari biblio_log
                "(SELECT bl.realname FROM biblio_log bl WHERE bl.biblio_id = biblio.biblio_id ORDER BY bl.biblio_log_id DESC LIMIT 1) AS '" . __('Input By') . "'"
            );
            // modify column value
            $datagrid->modifyColumnContent(1, 'callback{showTitleAuthors}');
            $datagrid->modifyColumnContent(4, 'callback{renderClassBadge}');
            $datagrid->modifyColumnContent(5, 'callback{renderYearBadge}');
            $datagrid->modifyColumnContent(6, 'callback{renderIsbnBadge}');
            $datagrid->modifyColumnContent(7, 'callback{renderLastUpdateBadge}');
            $lastUpdateColumnIndex = 7;
        }
        if ($lastUpdateColumnIndex !== null) {
            $datagrid->modifyColumnContent($lastUpdateColumnIndex, 'callback{renderLastUpdateBadge}');
        }
    $datagrid->invisible_fields = array(0, 2, 3);
        $datagrid->setSQLorder('biblio.last_update DESC');

        // set group by
        $datagrid->sql_group_by = 'biblio.biblio_id';
    }

    $stopwords = "@\sAnd\s|\sOr\s|\sNot\s|\sThe\s|\sDan\s|\sAtau\s|\sAn\s|\sA\s@i";

    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $keywords = $dbs->escape_string(trim($_GET['keywords']));
        $keywords = preg_replace($stopwords, ' ', $keywords);
        $searchable_fields = array('title', 'author', 'subject', 'isbn', 'publisher');
        if ($_GET['field'] != '0' AND in_array($_GET['field'], $searchable_fields)) {
            $field = $_GET['field'];
            $search_str = $field . '=' . $keywords;
        } else {
            $search_str = '';
            foreach ($searchable_fields as $search_field) {
                $search_str .= $search_field . '=' . $keywords . ' OR ';
            }
            $search_str = substr_replace($search_str, '', -4);
        }
        $biblio_list = new biblio_list($dbs, $biblio_result_num);
        $criteria = $biblio_list->setSQLcriteria($search_str);
        $str_criteria .= ' AND (' . $criteria['sql_criteria'] . ')';
    }

    if (isset($_GET['opac_hide']) && $_GET['opac_hide'] != '') {
        $opac_hide = $dbs->escape_string($_GET['opac_hide']);
        $str_criteria .= ' AND opac_hide =' . $opac_hide;
    }

    if (isset($_GET['promoted']) && $_GET['promoted'] != '') {
        $promoted = $dbs->escape_string($_GET['promoted']);
        $str_criteria .= ' AND promoted =' . $promoted;
    }
    //debug
    //echo $str_criteria;

    $datagrid->setSQLcriteria($str_criteria);

    // set table and table header attributes
    $datagrid->table_attr = 'id="dataList" class="s-table table"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];
    $datagrid->debug = true;

    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, $biblio_result_num, ($can_read AND $can_write));
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<div class="infoBox">' . $msg . ' : "' . htmlentities($_GET['keywords']) . '"<div>' . __('Query took') . ' <b>' . $datagrid->query_time . '</b> ' . __('second(s) to complete') . '</div></div>'; //mfc
    }

    echo '<div class="biblio-table-wrapper"><div class="biblio-table-card">', $datagrid_result, '</div></div>';
    
    // Add JavaScript for handling action button transformation
    ?>
    <script type="text/javascript">
    $(document).ready(function() {
        // STEP 1: Gabungkan checkbox dan edit button dalam satu cell di kanan
        // New structure: Title | Year | ISBN | Last Update | [✓ Checkbox + ✏️ Edit Button]
        
        // Process header
        var $headerRow = $('#dataList thead tr');
        var $checkboxHeader = $headerRow.find('th:eq(0)'); // Checkbox at index 0
        var $editHeaderCell = $headerRow.find('th:eq(1)'); // Edit at index 1
        
        // Remove edit header and update checkbox header
        if ($editHeaderCell.length > 0) {
            $editHeaderCell.remove();
        }
        if ($checkboxHeader.length > 0) {
            $checkboxHeader.remove();
            $checkboxHeader.html(''); // Empty text for cleaner look
            $headerRow.append($checkboxHeader); // Move to last position
        }
        
        // Process body rows - merge checkbox and edit into one cell
        $('#dataList tbody tr').each(function() {
            var $row = $(this);
            var $checkboxCell = $row.find('td:eq(0)'); // Checkbox cell
            var $editCell = $row.find('td:eq(1)'); // Edit cell
            
            if ($checkboxCell.length > 0 && $editCell.length > 0) {
                // Get checkbox and edit link
                var $checkbox = $checkboxCell.find('input[type="checkbox"]');
                var $editLink = $editCell.find('a');
                
                // Create merged cell content
                var $mergedContent = $('<div class="biblio-actions-merged"></div>');
                
                // Add edit button FIRST (left side)
                if ($editLink.length > 0) {
                    $mergedContent.append($editLink.clone());
                }
                
                // Add checkbox SECOND (right side)
                if ($checkbox.length > 0) {
                    $mergedContent.append($checkbox.clone());
                }
                
                // Remove both original cells
                $editCell.remove();
                $checkboxCell.remove();
                
                // Add merged cell at the end
                var $newCell = $('<td class="biblio-actions-cell"></td>');
                $newCell.append($mergedContent);
                $row.append($newCell);
            }
        });
        
        // STEP 2: Update header text for edit column
        setTimeout(function() {
            // Find the last header (merged checkbox/edit column)
            var $lastHeader = $('#dataList thead tr th:last');
            if ($lastHeader.length > 0) {
                $lastHeader.html('<?php echo __("Edit"); ?>');
                $lastHeader.css({'text-align': 'center', 'font-weight': 'bold'});
            }
        }, 100);
        
        // STEP 3: Handle search toggle functionality
        $('#searchToggle').click(function(e) {
            e.preventDefault();
            $(this).toggleClass('collapsed');
            $('#searchContent').collapse('toggle');
            $(this).find('.toggle-icon').toggleClass('fa-chevron-down fa-chevron-up');
        });
        
        // Initialize toggle state - search is collapsed by default
        if ($('#searchToggle').hasClass('collapsed')) {
            $('#searchToggle').find('.toggle-icon').removeClass('fa-chevron-down').addClass('fa-chevron-up');
        }
    });
    </script>
    <?php
}
/* main content end */
