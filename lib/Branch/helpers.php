<?php
/**
 * Branch Helper Functions
 * Shortcut functions for common branch operations
 */

use SLiMS\Branch\BranchManager;

/**
 * Get current branch ID
 */
function getCurrentBranchId()
{
    return BranchManager::getCurrentBranchId();
}

/**
 * Get current branch data
 */
function getCurrentBranch()
{
    return BranchManager::getCurrentBranch();
}

/**
 * Check if user is super admin
 */
function isSuperAdmin()
{
    return BranchManager::isSuperAdmin();
}

/**
 * Add branch filter to query
 */
function branchFilter($query, $table = null, $allowShared = false)
{
    return BranchManager::addFilter($query, $table, $allowShared);
}

/**
 * Get all branches
 */
function getAllBranches()
{
    return BranchManager::getInstance()->getAllBranches();
}

/**
 * Get branch by ID
 */
function getBranch($id)
{
    return BranchManager::getInstance()->getById($id);
}
