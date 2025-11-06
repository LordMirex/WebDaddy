<?php

function trackPageVisit($pageUrl, $pageTitle = '') {
    if (session_status() === PHP_SESSION_NONE) {
        return false;
    }
    
    if (!isset($_SESSION['analytics_session_id'])) {
        $_SESSION['analytics_session_id'] = bin2hex(random_bytes(16));
    }
    
    $sessionId = $_SESSION['analytics_session_id'];
    $db = getDb();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO page_visits (session_id, page_url, page_title, referrer, user_agent, ip_address, visit_date, visit_time)
            VALUES (?, ?, ?, ?, ?, ?, DATE('now'), TIME('now'))
        ");
        
        $stmt->execute([
            $sessionId,
            $pageUrl,
            $pageTitle,
            $_SERVER['HTTP_REFERER'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
        
        $stmt = $db->prepare("
            INSERT INTO session_summary (session_id, first_visit, last_visit, total_pages)
            VALUES (?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1)
            ON CONFLICT(session_id) DO UPDATE SET
                last_visit = CURRENT_TIMESTAMP,
                total_pages = total_pages + 1,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$sessionId]);
        
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM page_visits WHERE session_id = ?
        ");
        $stmt->execute([$sessionId]);
        $pageCount = $stmt->fetchColumn();
        
        if ($pageCount > 1) {
            $stmt = $db->prepare("
                UPDATE session_summary SET is_bounce = 0 WHERE session_id = ?
            ");
            $stmt->execute([$sessionId]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log('Analytics tracking error: ' . $e->getMessage());
        return false;
    }
}

function trackTemplateView($templateId) {
    if (!isset($_SESSION['analytics_session_id'])) {
        return false;
    }
    
    $db = getDb();
    try {
        $stmt = $db->prepare("
            INSERT INTO page_interactions (session_id, page_url, action_type, action_target, template_id)
            VALUES (?, ?, 'view', 'template', ?)
        ");
        $stmt->execute([
            $_SESSION['analytics_session_id'],
            $_SERVER['REQUEST_URI'] ?? '',
            $templateId
        ]);
        return true;
    } catch (PDOException $e) {
        error_log('Template view tracking error: ' . $e->getMessage());
        return false;
    }
}

function trackTemplateClick($templateId) {
    if (!isset($_SESSION['analytics_session_id'])) {
        return false;
    }
    
    $db = getDb();
    try {
        $stmt = $db->prepare("
            INSERT INTO page_interactions (session_id, page_url, action_type, action_target, template_id)
            VALUES (?, ?, 'click', 'template', ?)
        ");
        $stmt->execute([
            $_SESSION['analytics_session_id'],
            $_SERVER['REQUEST_URI'] ?? '',
            $templateId
        ]);
        return true;
    } catch (PDOException $e) {
        error_log('Template click tracking error: ' . $e->getMessage());
        return false;
    }
}
