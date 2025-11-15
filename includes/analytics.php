<?php

function getDeviceType($userAgent) {
    $userAgent = strtolower($userAgent);
    
    if (preg_match('/(tablet|ipad|playbook)|(android(?!.*mobile))/i', $userAgent)) {
        return 'Tablet';
    }
    
    if (preg_match('/(mobile|iphone|ipod|blackberry|android.*mobile|windows phone)/i', $userAgent)) {
        return 'Mobile';
    }
    
    return 'Desktop';
}

function ensureAnalyticsSession() {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    if (!isset($_SESSION['analytics_session_id'])) {
        $_SESSION['analytics_session_id'] = bin2hex(random_bytes(16));
    }
    
    return $_SESSION['analytics_session_id'];
}

function trackPageVisit($pageUrl, $pageTitle = '') {
    $sessionId = ensureAnalyticsSession();
    if (!$sessionId) {
        return false;
    }
    
    $db = getDb();
    
    try {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $deviceType = getDeviceType($userAgent);
        
        $stmt = $db->prepare("
            INSERT INTO page_visits (session_id, page_url, page_title, referrer, user_agent, ip_address, device_type, visit_date, visit_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, DATE('now'), TIME('now'))
        ");
        
        $stmt->execute([
            $sessionId,
            $pageUrl,
            $pageTitle,
            $_SERVER['HTTP_REFERER'] ?? '',
            $userAgent,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $deviceType
        ]);
        
        $stmt = $db->prepare("
            INSERT INTO session_summary (session_id, first_visit, last_visit, total_pages, is_bounce)
            VALUES (?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1, 1)
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
    $sessionId = ensureAnalyticsSession();
    if (!$sessionId) {
        return false;
    }
    
    $db = getDb();
    try {
        $stmt = $db->prepare("
            INSERT INTO page_interactions (session_id, page_url, action_type, action_target, template_id)
            VALUES (?, ?, 'view', 'template', ?)
        ");
        $stmt->execute([
            $sessionId,
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
    $sessionId = ensureAnalyticsSession();
    if (!$sessionId) {
        return false;
    }
    
    $db = getDb();
    try {
        $stmt = $db->prepare("
            INSERT INTO page_interactions (session_id, page_url, action_type, action_target, template_id)
            VALUES (?, ?, 'click', 'template', ?)
        ");
        $stmt->execute([
            $sessionId,
            $_SERVER['REQUEST_URI'] ?? '',
            $templateId
        ]);
        return true;
    } catch (PDOException $e) {
        error_log('Template click tracking error: ' . $e->getMessage());
        return false;
    }
}

function trackSearch($searchTerm, $resultsCount = 0) {
    $sessionId = ensureAnalyticsSession();
    if (!$sessionId) {
        return false;
    }
    
    $db = getDb();
    try {
        // NOTE: For 'search' action types, we use the time_spent column to store result count
        // This is a known pattern - time_spent only means "time" for non-search interactions
        $stmt = $db->prepare("
            INSERT INTO page_interactions (session_id, page_url, action_type, action_target, time_spent)
            VALUES (?, ?, 'search', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['analytics_session_id'],
            $_SERVER['REQUEST_URI'] ?? '',
            $searchTerm,
            $resultsCount
        ]);
        return true;
    } catch (PDOException $e) {
        error_log('Search tracking error: ' . $e->getMessage());
        return false;
    }
}

function trackAffiliateAction($affiliateId, $actionType) {
    $db = getDb();
    try {
        $stmt = $db->prepare("
            INSERT INTO affiliate_actions (affiliate_id, action_type, ip_address, user_agent)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $affiliateId,
            $actionType,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        return true;
    } catch (PDOException $e) {
        error_log('Affiliate action tracking error: ' . $e->getMessage());
        return false;
    }
}

function trackButtonClick($buttonName, $buttonContext = '') {
    $sessionId = ensureAnalyticsSession();
    if (!$sessionId) {
        return false;
    }
    
    $db = getDb();
    try {
        $stmt = $db->prepare("
            INSERT INTO page_interactions (session_id, page_url, action_type, action_target)
            VALUES (?, ?, 'button_click', ?)
        ");
        $target = $buttonContext ? $buttonName . ' - ' . $buttonContext : $buttonName;
        $stmt->execute([
            $_SESSION['analytics_session_id'],
            $_SERVER['REQUEST_URI'] ?? '',
            $target
        ]);
        return true;
    } catch (PDOException $e) {
        error_log('Button click tracking error: ' . $e->getMessage());
        return false;
    }
}

function trackFormSubmission($formName, $formData = []) {
    $sessionId = ensureAnalyticsSession();
    if (!$sessionId) {
        return false;
    }
    
    $db = getDb();
    try {
        $stmt = $db->prepare("
            INSERT INTO page_interactions (session_id, page_url, action_type, action_target)
            VALUES (?, ?, 'form_submit', ?)
        ");
        $stmt->execute([
            $_SESSION['analytics_session_id'],
            $_SERVER['REQUEST_URI'] ?? '',
            $formName
        ]);
        return true;
    } catch (PDOException $e) {
        error_log('Form submission tracking error: ' . $e->getMessage());
        return false;
    }
}

function trackAffiliateClick($affiliateCode) {
    $sessionId = ensureAnalyticsSession();
    if (!$sessionId) {
        return false;
    }
    
    $db = getDb();
    try {
        $stmt = $db->prepare("
            INSERT INTO page_interactions (session_id, page_url, action_type, action_target)
            VALUES (?, ?, 'affiliate_click', ?)
        ");
        $stmt->execute([
            $_SESSION['analytics_session_id'],
            $_SERVER['REQUEST_URI'] ?? '',
            $affiliateCode
        ]);
        
        $stmt = $db->prepare("UPDATE affiliates SET total_clicks = total_clicks + 1 WHERE code = ?");
        $stmt->execute([$affiliateCode]);
        
        return true;
    } catch (PDOException $e) {
        error_log('Affiliate click tracking error: ' . $e->getMessage());
        return false;
    }
}

function trackOrderStart($templateId) {
    $sessionId = ensureAnalyticsSession();
    if (!$sessionId) {
        return false;
    }
    
    $db = getDb();
    try {
        $stmt = $db->prepare("
            INSERT INTO page_interactions (session_id, page_url, action_type, action_target, template_id)
            VALUES (?, ?, 'order_start', 'template', ?)
        ");
        $stmt->execute([
            $_SESSION['analytics_session_id'],
            $_SERVER['REQUEST_URI'] ?? '',
            $templateId
        ]);
        return true;
    } catch (PDOException $e) {
        error_log('Order start tracking error: ' . $e->getMessage());
        return false;
    }
}
