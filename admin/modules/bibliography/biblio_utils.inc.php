<!-- /*
 * Perubahan: Input By di bawah author kini mengambil user terakhir dari log (biblio_log),
 * jika tidak ada log maka fallback ke user di biblio (uid).
 * Untuk revert, hapus blok fallback dan kembalikan ke pengambilan user dari field biblio.uid saja.
 *
 * Last update: 2025-10-26, by Copilot
 */ -->
<?php
/**
 * Copyright (C) 2015  Arie Nugraha (dicarve@yahoo.com)
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

/**
 * Utility function to get author ID
 **/
function getAuthorID($str_author_name, $str_author_type, &$arr_cache = false)
{
  global $dbs;
  $str_value = trim($str_author_name);
  $str_author_type = $dbs->escape_string($str_author_type);
  if ($arr_cache) {
      if (isset($arr_cache[$str_value])) {
          return $arr_cache[$str_value];
      }
  }

  $str_value = $dbs->escape_string($str_value);
  $_sql_id_q = sprintf('SELECT author_id FROM mst_author WHERE author_name=\'%s\'', $str_value);
  $id_q = $dbs->query($_sql_id_q);
  if ($id_q->num_rows > 0) {
      $id_d = $id_q->fetch_row();
      unset($id_q);
      // cache
      if ($arr_cache) { $arr_cache[$str_value] = $id_d[0]; }
      return $id_d[0];
  } else {
      $_curr_date = date('Y-m-d');
      // if not found then we insert it as new value
      $_sql_insert_author = sprintf('INSERT IGNORE INTO mst_author (author_name, authority_type, input_date, last_update)'
          .' VALUES (\'%s\', \'%s\', \'%s\', \'%s\')', $str_value, $str_author_type, $_curr_date, $_curr_date);
      $dbs->query($_sql_insert_author);
      if (!$dbs->error) {
          // cache
          if ($arr_cache) { $arr_cache[$str_value] = $dbs->insert_id; }
          return $dbs->insert_id;
      }
  }
}


/**
 * Utility function to get subject ID
 **/
function getSubjectID($str_subject, $str_subject_type, &$arr_cache = false, $str_class_number = '')
{
  global $dbs;
  $str_value = trim($str_subject);
  if ($arr_cache) {
    if (isset($arr_cache[$str_value])) {
      return $arr_cache[$str_value];
    }
  }

  $str_value = $dbs->escape_string($str_value);
  $_sql_id_q = sprintf('SELECT topic_id FROM mst_topic WHERE topic=\'%s\'', $str_value);
  $id_q = $dbs->query($_sql_id_q);
  if ($id_q->num_rows > 0) {
      $id_d = $id_q->fetch_row();
      unset($id_q);
      // cache
      if ($arr_cache) { $arr_cache[$str_value] = $id_d[0]; }
      return $id_d[0];
  } else {
      $_curr_date = date('Y-m-d');
      // if not found then we insert it as new value
      $_sql_insert_topic = sprintf('INSERT IGNORE INTO mst_topic (topic, topic_type, classification, input_date, last_update)'
          .' VALUES (\'%s\', \'%s\', \'%s\', \'%s\', \'%s\')', $str_value, $str_subject_type, $str_class_number, $_curr_date, $_curr_date);
      $dbs->query($_sql_insert_topic);
      if (!$dbs->error) {
          // cache
          if ($arr_cache) { $arr_cache[$str_value] = $dbs->insert_id; }
          return $dbs->insert_id;
      } else {
          echo $dbs->error;
      }
  }
}

if (!function_exists('biblio_extract_cell')) {
    function biblio_extract_cell($row, string $type)
    {
        if (!is_array($row)) {
            return '';
        }

        $values = array_values($row);
        $length = count($values);
        $maps = [
            10 => ['year' => 5, 'isbn' => 6, 'copies' => 7, 'input_by' => 8, 'last_update' => 9],
            9  => ['year' => 4, 'isbn' => 5, 'copies' => 6, 'input_by' => 7, 'last_update' => 8],
            8  => ['year' => 3, 'isbn' => 4, 'copies' => 5, 'input_by' => 6, 'last_update' => 7],
            7  => ['year' => 2, 'isbn' => 3, 'copies' => 4, 'input_by' => 5, 'last_update' => 6],
            6  => ['year' => 1, 'isbn' => 2, 'copies' => 3, 'input_by' => 4, 'last_update' => 5],
            5  => ['year' => 1, 'isbn' => 2, 'copies' => 3, 'input_by' => 4, 'last_update' => 4],
        ];

        if (!isset($maps[$length][$type])) {
            return '';
        }

        $index = $maps[$length][$type];
        return $values[$index] ?? '';
    }
}

/**
 * callback function to show title and authors in datagrid
 **/
function showTitleAuthors($obj_db, $array_data)
{
  global $sysconf;
  global $label_cache;
    $_opac_hide = false;
    $_promoted = false;
    $_labels = '';
    $_image = '';
    $_input_by = '';

  $img = 'images/default/image.png';

  // biblio author detail
    if ($sysconf['index']['type'] == 'default') {
            $_sql_biblio_q = sprintf('SELECT b.title, a.author_name, opac_hide, promoted, b.labels, b.image FROM biblio AS b LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id LEFT JOIN mst_author AS a ON ba.author_id=a.author_id WHERE b.biblio_id=%d', $array_data[0]);
            $_biblio_q = $obj_db->query($_sql_biblio_q);
            $_authors = '';
            $_title = '';
            $_image = '';
            $_opac_hide = 0;
            $_promoted = 0;
            $_labels = '';
            while ($_biblio_d = $_biblio_q->fetch_assoc()) {
                    $_title = $_biblio_d['title'];
                    $_image = $_biblio_d['image'];
                    $_authors .= $_biblio_d['author_name'] . ' - ';
                    $_opac_hide = (int) $_biblio_d['opac_hide'];
                    $_promoted = (int) $_biblio_d['promoted'];
                    $_labels = $_biblio_d['labels'];
            }
            $_authors = $_authors ? substr_replace($_authors, '', -3) : '';
            if ($_image != '' AND file_exists('../../../images/docs/' . $_image)) {
                $img = 'images/docs/' . urlencode($_image);
            }
            // Ambil user terakhir dari log biblio
            $_input_by = '';
              // Ambil user terakhir dari log, jika tidak ada fallback ke biblio.uid
              // Jika ingin revert, hapus blok fallback dan kembalikan ke pengambilan user dari field biblio.uid saja.
              $_input_by = '-';
              $biblio_id = intval($array_data[0]);
              $user_id = 0;
              $log_q = $obj_db->query('SELECT user_id FROM biblio_log WHERE biblio_id=' . $biblio_id . ' ORDER BY date DESC LIMIT 1');
              if ($log_q && $log_q->num_rows > 0) {
                  $log_d = $log_q->fetch_assoc();
                  $user_id = intval($log_d['user_id']);
              }
              if ($user_id > 0) {
                  $user_q = $obj_db->query('SELECT realname, username FROM user WHERE user_id=' . $user_id . ' LIMIT 1');
                  if ($user_q && $user_q->num_rows > 0) {
                      $user_d = $user_q->fetch_assoc();
                      if (!empty($user_d['realname']) && !is_numeric($user_d['realname'])) {
                          $_input_by = $user_d['realname'];
                      } else if (!empty($user_d['username']) && !is_numeric($user_d['username'])) {
                          $_input_by = $user_d['username'];
                      }
                  }
              } else {
                  // fallback ke biblio.uid
                  $biblio_q = $obj_db->query('SELECT uid FROM biblio WHERE biblio_id=' . $biblio_id . ' LIMIT 1');
                  if ($biblio_q && $biblio_q->num_rows > 0) {
                      $biblio_d = $biblio_q->fetch_assoc();
                      $uid = intval($biblio_d['uid']);
                      if ($uid > 0) {
                          $user_q = $obj_db->query('SELECT realname, username FROM user WHERE user_id=' . $uid . ' LIMIT 1');
                          if ($user_q && $user_q->num_rows > 0) {
                              $user_d = $user_q->fetch_assoc();
                              if (!empty($user_d['realname']) && !is_numeric($user_d['realname'])) {
                                  $_input_by = $user_d['realname'];
                              } else if (!empty($user_d['username']) && !is_numeric($user_d['username'])) {
                                  $_input_by = $user_d['username'];
                              }
                          }
                      }
                  }
              }
            
            // Ambil copies dari database untuk mode default
            $copies_count = 0;
            $copies_q = $obj_db->query("SELECT COUNT(item_id) as cnt FROM item WHERE biblio_id=" . intval($array_data[0]));
            if ($copies_q && $copies_q->num_rows > 0) {
                $copies_d = $copies_q->fetch_assoc();
                $copies_count = (int) $copies_d['cnt'];
            }
            
            $copies_badge = '';
            if ($copies_count > 0) {
                $copies_badge = '<span class="biblio-badge biblio-badge--copies biblio-badge--overlay">' . $copies_count . '</span>';
            }

            // Build badges for hide/promoted
            $status_badges = '';
            if ($_opac_hide) {
                $status_badges .= ' <span class="badge badge-dark" style="margin-left:6px;" title="' . __('Hidden in OPAC') . '">'.__('Hidden in OPAC').'</span>';
            }
            if ($_promoted) {
                $status_badges .= ' <span class="badge badge-info" style="margin-left:6px;" title="' . __('Promoted To Homepage') . '">'.__('Promoted To Homepage').'</span>';
            }
            
            $_output = '<div class="media">
                                        <div class="biblio-cover-wrapper">
                                            <img class="rounded" src="../lib/minigalnano/createthumb.php?filename=' . $img . '&width=50&height=65" alt="cover image">
                                            ' . $copies_badge . '
                                        </div>
                                        <div class="media-body">
                                            <div class="title">' . stripslashes($_title) . '</div><div class="authors">' . $_authors . '</div>';
            if (!empty($_input_by) || !empty($status_badges)) {
                    $_output .= '<div class="biblio-inputby">';
                    if (!empty($_input_by)) {
                        $_output .= '<span style="background:#1f3bb3;color:#fff;padding:2px 10px;border-radius:8px;font-size:0.78em;display:inline-block;">Input By: ' . htmlspecialchars($_input_by, ENT_QUOTES, 'UTF-8') . '</span>';
                    }
                    $_output .= $status_badges; // Add hide/promoted badges here
                    $_output .= '</div>';
            }
            $_output .= '
                                        </div>
                                    </div>';
  } else {
        // Mode index
        $_q = $obj_db->query("SELECT opac_hide,promoted FROM biblio WHERE biblio_id=".$array_data[0]);
        while ($_biblio_d = $_q->fetch_row()) {
          $_opac_hide = (integer)$_biblio_d[0];
          $_promoted  = (integer)$_biblio_d[1];
        }

      if($array_data[3]!='' AND file_exists('../../../images/docs/'.$array_data[3])){
        $img = 'images/docs/'.urlencode($array_data[3]);  
      }
      
      // Ambil copies dari database untuk mode index
      $biblio_id = isset($array_data[0]) && is_numeric($array_data[0]) ? intval($array_data[0]) : 0;
      $copies_count = 0;
      if ($biblio_id > 0) {
          $copies_q = $obj_db->query("SELECT COUNT(item_id) as cnt FROM item WHERE biblio_id=" . $biblio_id);
          if ($copies_q && $copies_q->num_rows > 0) {
              $copies_d = $copies_q->fetch_assoc();
              $copies_count = (int) $copies_d['cnt'];
          }
      }
      
      $copies_badge = '';
      if ($copies_count > 0) {
          $copies_badge = '<span class="biblio-badge biblio-badge--copies biblio-badge--overlay">' . $copies_count . '</span>';
      }
      
      // Ambil input_by dari biblio_log (priority) atau biblio.uid
      $_input_by = '';
      if ($biblio_id > 0) {
          // Coba ambil dari biblio_log dulu (last update)
          $log_q = $obj_db->query("SELECT realname FROM biblio_log WHERE biblio_id=" . $biblio_id . " ORDER BY biblio_log_id DESC LIMIT 1");
          if ($log_q && $log_q->num_rows > 0) {
              $log_d = $log_q->fetch_assoc();
              if (!empty($log_d['realname']) && !is_numeric($log_d['realname'])) {
                  $_input_by = $log_d['realname'];
              }
          }
          
          // Fallback ke biblio.uid jika tidak ada di log
          if (empty($_input_by)) {
              $user_q = $obj_db->query("SELECT u.realname, u.username FROM biblio b JOIN user u ON b.uid=u.user_id WHERE b.biblio_id=" . $biblio_id . " LIMIT 1");
              if ($user_q && $user_q->num_rows > 0) {
                  $user_d = $user_q->fetch_assoc();
                  $_input_by = !empty($user_d['realname']) ? $user_d['realname'] : $user_d['username'];
              }
          }
      }

      // Build badges for hide/promoted
      $status_badges = '';
      if ($_opac_hide) {
          $status_badges .= ' <span class="badge badge-dark" style="margin-left:6px;" title="' . __('Hidden in OPAC') . '">'.__('Hidden in OPAC').'</span>';
      }
      if ($_promoted) {
          $status_badges .= ' <span class="badge badge-info" style="margin-left:6px;" title="' . __('Promoted To Homepage') . '">'.__('Promoted To Homepage').'</span>';
      }
      
      $_output = '<div class="media">
                    <div class="biblio-cover-wrapper">
                        <img class="rounded" src="../lib/minigalnano/createthumb.php?filename=' . $img . '&width=50&height=65" alt="cover image">
                        ' . $copies_badge . '
                    </div>
                    <div class="media-body">
                      <div class="title">' . stripslashes($array_data[1]) . '</div><div class="authors">' . $array_data[5] . '</div>';
      if (!empty($_input_by) || !empty($status_badges)) {
          $_output .= '<div class="biblio-inputby">';
          if (!empty($_input_by)) {
              $_output .= '<span style="background:#1f3bb3;color:#fff;padding:2px 10px;border-radius:8px;font-size:0.78em;display:inline-block;">' . __('Input By') . ': ' . htmlspecialchars($_input_by, ENT_QUOTES, 'UTF-8') . '</span>';
          }
          $_output .= $status_badges; // Add hide/promoted badges here
          $_output .= '</div>';
      }
      $_output .= '
                    </div>
                  </div>';
      $_labels = $array_data[2];
  }
  // labels
  // Edit by Eddy Subratha
  if ($_labels) {
      $arr_labels = @unserialize($_labels);
      if ($arr_labels !== false) {
      foreach ($arr_labels as $label) {
          if (!isset($label_cache[$label[0]]['name'])) {
              $_label_q = $obj_db->query('SELECT label_name, label_desc, label_image FROM mst_label AS lb WHERE lb.label_name=\''.$label[0].'\'');
              $_label_d = $_label_q->fetch_row();
              $label_cache[$_label_d[0]] = array('name' => $_label_d[0], 'desc' => $_label_d[1], 'image' => $_label_d[2]);
          }
          $_output .= '<div class="badge badge-light">'.$label_cache[$label[0]]['desc'].'</div>&nbsp;';
      }
    }
  }
  return $_output;
}
