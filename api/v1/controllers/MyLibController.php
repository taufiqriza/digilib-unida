<?php

/**
 * @author              : Garidinh
 * @Date                : 2024-06-07 15:08:08
 * @Last Modified by    : garidinh
 * @Last Modified time  : 2024-06-07 15:08:08
 *
 * Copyright (C) 2024  Garidinh (garidinh@gmail.com)
 */

require_once 'Controller.php';
require_once __DIR__ . '/../helpers/Image.php';
require_once __DIR__ . '/../helpers/Cache.php';

class MyLibController extends Controller
{

    use Image;

    protected $sysconf;

    /**
     * @var mysqli
     */
    protected $db;

    function __construct($sysconf, $obj_db)
    {
        $this->sysconf = $sysconf;
        $this->db = $obj_db;
    }

    public function getPopular()
    {
        $cache_name = 'biblio_popular';
        if (!is_null($json = Cache::get($cache_name))) return parent::withJson($json);

        $limit = $this->sysconf['template']['classic_popular_collection_item'];

        // First query to get popular items
        $sql = "SELECT b.biblio_id, b.title, b.image, GROUP_CONCAT(DISTINCT mst_a.author_name SEPARATOR '; ') AS authors, COUNT(*) AS total
            FROM loan AS l
            LEFT JOIN item AS i ON l.item_code=i.item_code
            LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
            LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
            LEFT JOIN mst_author AS mst_a ON ba.author_id=mst_a.author_id
            WHERE b.title IS NOT NULL AND b.opac_hide < 1
            GROUP BY b.biblio_id
            ORDER BY total DESC
            LIMIT {$limit}";

        $query = $this->db->query($sql);
        $return = array();
        while ($data = $query->fetch_assoc()) {
            $data['image'] = $this->getImagePath($data['image']);
            $return[] = $data;
        }

        Cache::set($cache_name, json_encode($return));
        parent::withJson($return);
    }


    public function getLatest() {
        $limit = $this->sysconf['template']['classic_new_collection_item'];
        $sql = "SELECT b.biblio_id, b.title, b.image, GROUP_CONCAT(DISTINCT mst_a.author_name SEPARATOR '; ') AS authors
          FROM biblio b
          LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
          LEFT JOIN mst_author AS mst_a ON ba.author_id=mst_a.author_id
          WHERE b.opac_hide < 1
          GROUP BY b.biblio_id
          ORDER BY b.last_update DESC
          LIMIT {$limit}";

        $query = $this->db->query($sql);
        $return = array();
        while ($data = $query->fetch_assoc()) {
            $data['image'] = $this->getImagePath($data['image']);
            $return[] = $data;
        }

        parent::withJson($return);
    }

    private function extractFirstImage($content_desc) {
        $matches = [];
        preg_match('/<img[^>]+src=["\']?([^"\'>]+)["\']?[^>]*>/', $content_desc, $matches);
        return $matches[1] ?? null;
    }
    
    public function getNewsLatest() {
       
        global $sysconf;
        if (isset($_COOKIE['select_lang'])) $sysconf['default_lang'] = trim(strip_tags($_COOKIE['select_lang']));
        $limit = $this->sysconf['template']['classic_news_latest_item'];
        $sql = "SELECT content_id, content_title, content_desc, content_path, last_update
          FROM content WHERE is_news=1 AND is_draft=0
          ORDER BY last_update DESC
          LIMIT {$limit}";

        $query = $this->db->query($sql);
        $return = array();
        while ($data = $query->fetch_assoc()) {
            $data['path_url'] = 'index.php?p='.$data['content_path'];
            $data['desc'] = strip_tags($data['content_desc']);
            $data['image'] = $this->extractFirstImage($data['content_desc']);
            $return[] = $data;
        }

        parent::withJson($return);
    }



    public function getTotalAll()
    {
        $query = $this->db->query("SELECT COUNT(biblio_id) FROM biblio WHERE opac_hide < 1");
        parent::withJson([
            'data' => ($query->fetch_row())[0]
        ]);
    }

    public function getByGmd($gmd) {
        $limit = 3;
        $sql = "SELECT b.biblio_id, b.title, b.image, b.notes
          FROM biblio AS b, mst_gmd AS g
          WHERE b.gmd_id=g.gmd_id AND g.gmd_name='$gmd' AND b.opac_hide < 1
          ORDER BY b.last_update DESC
          LIMIT {$limit}";
        $query = $this->db->query($sql);
        $return = array();
        while ($data = $query->fetch_assoc()) {
            $data['image'] = $this->getImagePath($data['image']);
            $return[] = $data;
        }
    
        parent::withJson($return);
    }

    public function getByCollType($coll_type) {
        $limit = 3;
        $sql = "SELECT b.biblio_id, b.title, b.image, b.notes
          FROM biblio AS b, item AS i, mst_coll_type AS c
          WHERE b.biblio_id=i.biblio_id AND i.coll_type_id=c.coll_type_id AND c.coll_type_name='$coll_type' AND b.opac_hide < 1
          ORDER BY b.last_update DESC
          LIMIT {$limit}";
        $query = $this->db->query($sql);
        $return = array();
        while ($data = $query->fetch_assoc()) {
            $data['image'] = $this->getImagePath($data['image']);
            $return[] = $data;
        }
    
        parent::withJson($return);
    }

    function getPopularSubject() {

        $limit = 30;
        $year = date('Y');
        $cache_name = 'subject_popular';
        $json = Cache::get($cache_name);
        if (!is_null($json) && $json !== 'null') return parent::withJson($json);

        $sql = "SELECT DISTINCT mt.topic, COUNT(*) AS total
          FROM loan AS l
          LEFT JOIN item AS i ON l.item_code=i.item_code
          LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
          LEFT JOIN biblio_topic AS bt ON i.biblio_id=bt.biblio_id
          LEFT JOIN mst_topic AS mt ON bt.topic_id=mt.topic_id
          WHERE mt.topic IS NOT NULL AND YEAR(l.loan_date) = '%s'
          GROUP BY bt.topic_id
          ORDER BY total DESC
          LIMIT %d";

        $query = $this->db->query(sprintf($sql, $year, $limit));
        $return = array();
        while ($data = $query->fetch_row()) {
            $return[] = $data[0];
        }
       

         // parent::withJson($return);
        Cache::set($cache_name, json_encode($return));
    }

    function getTopMember() {
        $limit = 4;
        $year = date('Y');
        $sql = "SELECT m.member_name, mm.member_type_name, m.member_image, COUNT(*) AS total, GROUP_CONCAT(i.biblio_id SEPARATOR ';') AS biblio_id
          FROM loan AS l
          LEFT JOIN member AS m ON l.member_id=m.member_id
          LEFT JOIN mst_member_type AS mm ON m.member_type_id=mm.member_type_id
          LEFT JOIN item As i ON l.item_code=i.item_code
          WHERE
            l.loan_date LIKE '{$year}-%' AND
            m.member_name IS NOT NULL
          GROUP BY m.member_id
          ORDER BY total DESC
          LIMIT {$limit}";

        $query = $this->db->query($sql);
        $return = array();
        if ($query) {
            while ($data = $query->fetch_assoc()) {
                $title = array_unique(explode(';', $data['biblio_id']));
                $return[] = array(
                    'name' => $data['member_name'],
                    'type' => $data['member_type_name'],
                    'image' =>  $this->getImagePath($data['member_image'], 'persons'),
                    'total' => $data['total'],
                    'total_title' => count($title),
                    'order' => $data['total']+count($title));
            }
        }

        usort($return, function ($a, $b) {
            return $b['order'] <=> $a['order'];
        });

        parent::withJson($return);
    }



}