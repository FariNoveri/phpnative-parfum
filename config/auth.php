<?php
// config/auth.php

/**
 * Check if user is logged in
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

/**
 * Get current user ID
 */
if (!function_exists('getUserId')) {
    function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}

/**
 * Redirect to URL
 */
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit();
    }
}

/**
 * Require admin login
 */
if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
            redirect('admin_login.php');
        }
    }
}

/**
 * Get current user name
 */
if (!function_exists('getUserName')) {
    function getUserName() {
        return $_SESSION['user_name'] ?? 'Guest';
    }
}
?>