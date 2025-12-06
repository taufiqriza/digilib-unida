<?php
/**
 * SLiMS Multi-Branch Manager
 * Handles branch detection, switching, and data isolation
 */

namespace SLiMS\Branch;

class BranchManager
{
    private static $instance = null;
    private static $currentBranch = null;
    private static $allBranches = null;
    private $db;

    private function __construct($db)
    {
        $this->db = $db;
    }

    public static function getInstance($db = null)
    {
        if (self::$instance === null) {
            global $dbs;
            self::$instance = new self($db ?? $dbs);
        }
        return self::$instance;
    }

    /**
     * Detect current branch from subdomain, URL param, or session
     */
    public function detect()
    {
        if (self::$currentBranch !== null) {
            return self::$currentBranch;
        }

        // 1. Check subdomain
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $parts = explode('.', $host);
        if (count($parts) > 2) {
            $subdomain = $parts[0];
            $branch = $this->getBySubdomain($subdomain);
            if ($branch) {
                return self::$currentBranch = $branch;
            }
        }

        // 2. Check URL parameter
        if (isset($_GET['branch']) && is_numeric($_GET['branch'])) {
            $branch = $this->getById((int)$_GET['branch']);
            if ($branch) {
                $_SESSION['current_branch_id'] = $branch['branch_id'];
                return self::$currentBranch = $branch;
            }
        }

        // 3. Check session
        if (isset($_SESSION['current_branch_id'])) {
            $branch = $this->getById($_SESSION['current_branch_id']);
            if ($branch) {
                return self::$currentBranch = $branch;
            }
        }

        // 4. Default to main branch
        return self::$currentBranch = $this->getMainBranch();
    }

    /**
     * Get current branch ID
     */
    public static function getCurrentBranchId()
    {
        if (self::$currentBranch === null) {
            self::getInstance()->detect();
        }
        return self::$currentBranch['branch_id'] ?? 1;
    }

    /**
     * Get current branch data
     */
    public static function getCurrentBranch()
    {
        if (self::$currentBranch === null) {
            self::getInstance()->detect();
        }
        return self::$currentBranch;
    }

    /**
     * Check if current user is super admin
     */
    public static function isSuperAdmin()
    {
        return isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] == 1;
    }

    /**
     * Switch to different branch (for super admin)
     */
    public function switchBranch($branchId)
    {
        if (!self::isSuperAdmin() && $branchId != self::getCurrentBranchId()) {
            return false;
        }
        
        $branch = $this->getById($branchId);
        if ($branch) {
            $_SESSION['current_branch_id'] = $branchId;
            self::$currentBranch = $branch;
            return true;
        }
        return false;
    }

    /**
     * Get branch by ID (mysqli version)
     */
    public function getById($id)
    {
        $id = (int)$id;
        $result = $this->db->query("SELECT * FROM branches WHERE branch_id = {$id} AND is_active = 1");
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }

    /**
     * Get branch by subdomain (mysqli version)
     */
    public function getBySubdomain($subdomain)
    {
        $subdomain = $this->db->escape_string($subdomain);
        $result = $this->db->query("SELECT * FROM branches WHERE branch_subdomain = '{$subdomain}' AND is_active = 1");
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }

    /**
     * Get main branch (mysqli version)
     */
    public function getMainBranch()
    {
        $result = $this->db->query("SELECT * FROM branches WHERE is_main_branch = 1 AND is_active = 1 LIMIT 1");
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        // Fallback to first branch
        $result = $this->db->query("SELECT * FROM branches WHERE is_active = 1 ORDER BY branch_id LIMIT 1");
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        // Return default if no branches exist
        return [
            'branch_id' => 1,
            'branch_code' => 'MAIN',
            'branch_name' => 'Default Branch',
            'is_main_branch' => 1,
            'is_active' => 1
        ];
    }

    /**
     * Get all active branches (mysqli version)
     */
    public function getAllBranches()
    {
        if (self::$allBranches === null) {
            self::$allBranches = [];
            $result = $this->db->query("SELECT * FROM branches WHERE is_active = 1 ORDER BY branch_name");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    self::$allBranches[] = $row;
                }
            }
        }
        return self::$allBranches;
    }

    /**
     * Get branch statistics
     */
    public function getStats($branchId = null)
    {
        $branchId = (int)($branchId ?? self::getCurrentBranchId());
        
        $stats = [];
        
        // Total biblio
        $result = $this->db->query("SELECT COUNT(*) as cnt FROM biblio WHERE branch_id = {$branchId}");
        $stats['total_biblio'] = $result ? $result->fetch_assoc()['cnt'] : 0;
        
        // Total items
        $result = $this->db->query("SELECT COUNT(*) as cnt FROM item WHERE branch_id = {$branchId}");
        $stats['total_items'] = $result ? $result->fetch_assoc()['cnt'] : 0;
        
        // Total members
        $result = $this->db->query("SELECT COUNT(*) as cnt FROM member WHERE branch_id = {$branchId}");
        $stats['total_members'] = $result ? $result->fetch_assoc()['cnt'] : 0;
        
        // Active loans
        $result = $this->db->query("SELECT COUNT(*) as cnt FROM loan WHERE branch_id = {$branchId} AND is_return = 0");
        $stats['active_loans'] = $result ? $result->fetch_assoc()['cnt'] : 0;
        
        return $stats;
    }

    /**
     * Add branch filter to SQL query
     */
    public static function addFilter($query, $table = null, $allowShared = false)
    {
        $branchId = self::getCurrentBranchId();
        $prefix = $table ? "{$table}." : "";
        
        // Super admin viewing all branches
        if (self::isSuperAdmin() && isset($_SESSION['view_all_branches']) && $_SESSION['view_all_branches']) {
            return $query;
        }
        
        if ($allowShared) {
            return $query . " AND ({$prefix}branch_id = {$branchId} OR {$prefix}branch_id IS NULL)";
        }
        
        return $query . " AND {$prefix}branch_id = {$branchId}";
    }

    /**
     * Get branch ID for INSERT operations
     */
    public static function getBranchIdForInsert()
    {
        return self::getCurrentBranchId();
    }
}
