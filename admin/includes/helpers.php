<?php
/**
 * Admin Helper Functions
 * Common utilities for admin pages
 */

/**
 * Set success message and redirect (Post/Redirect/Get pattern)
 * This prevents modal bounce-back and form resubmission
 */
function redirectWithSuccess($message, $url = null) {
    $_SESSION['success_message'] = $message;
    if ($url === null) {
        $url = $_SERVER['PHP_SELF'];
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Set error message and redirect
 */
function redirectWithError($message, $url = null) {
    $_SESSION['error_message'] = $message;
    if ($url === null) {
        $url = $_SERVER['PHP_SELF'];
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Get and clear success message from session
 */
function getSuccessMessage() {
    if (isset($_SESSION['success_message'])) {
        $message = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
        return $message;
    }
    return '';
}

/**
 * Get and clear error message from session
 */
function getErrorMessage() {
    if (isset($_SESSION['error_message'])) {
        $message = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
        return $message;
    }
    return '';
}
