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
     * Get branch by ID
     */
    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM branches WHERE branch_id = ? AND is_active = 1");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get branch by subdomain
     */
    public function getBySubdomain($subdomain)
    {
        $stmt = $this->db->prepare("SELECT * FROM branches WHERE branch_subdomain = ? AND is_active = 1");
        $stmt->execute([$subdomain]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get main branch
     */
    public function getMainBranch()
    {
        $stmt = $this->db->query("SELECT * FROM branches WHERE is_main_branch = 1 AND is_active = 1 LIMIT 1");
        $branch = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$branch) {
            // Fallback to first branch
            $stmt = $this->db->query("SELECT * FROM branches WHERE is_active = 1 ORDER BY branch_id LIMIT 1");
            $branch = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        return $branch;
    }

    /**
     * Get all active branches
     */
    public function getAllBranches()
    {
        if (self::$allBranches === null) {
            $stmt = $this->db->query("SELECT * FROM branches WHERE is_active = 1 ORDER BY branch_name");
            self::$allBranches = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        return self::$allBranches;
    }

    /**
     * Get branch statistics
     */
    public function getStats($branchId = null)
    {
        $branchId = $branchId ?? self::getCurrentBranchId();
        
        $stats = [];
        
        // Total biblio
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM biblio WHERE branch_id = ?");
        $stmt->execute([$branchId]);
        $stats['total_biblio'] = $stmt->fetchColumn();
        
        // Total items
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM item WHERE branch_id = ?");
        $stmt->execute([$branchId]);
        $stats['total_items'] = $stmt->fetchColumn();
        
        // Total members
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM member WHERE branch_id = ?");
        $stmt->execute([$branchId]);
        $stats['total_members'] = $stmt->fetchColumn();
        
        // Active loans
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM loan WHERE branch_id = ? AND is_return = 0");
        $stmt->execute([$branchId]);
        $stats['active_loans'] = $stmt->fetchColumn();
        
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
