<?php
$content = file_get_contents('cart-checkout.php');
// Look for the apply_affiliate section and its messed up braces
$start_pattern = 'if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\' && isset($_POST[\'apply_affiliate\'])) {';
$end_pattern = '// Handle form submission';

$start_pos = strpos($content, $start_pattern);
$end_pos = strpos($content, $end_pattern);

if ($start_pos !== false && $end_pos !== false) {
    $before = substr($content, 0, $start_pos);
    $after = substr($content, $end_pos);
    
    $fixed_block = 'if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\' && isset($_POST[\'apply_affiliate\'])) {
    if (!validateCsrfToken($_POST[\'csrf_token\'] ?? \'\')) {
        $errors[] = \'Security validation failed. Please refresh the page and try again.\';
    } else {
        $submittedAffiliateCode = strtoupper(trim($_POST[\'affiliate_code\'] ?? \'\'));
        
        if (!empty($submittedAffiliateCode)) {
            if ($submittedAffiliateCode === $appliedBonusCode || $submittedAffiliateCode === $affiliateCode) {
                $errors[] = \'Code already applied.\';
            } else {
                $bonusCodeData = getBonusCodeByCode($submittedAffiliateCode);
                if ($bonusCodeData && $bonusCodeData[\'is_active\'] && 
                    (!$bonusCodeData[\'expires_at\'] || strtotime($bonusCodeData[\'expires_at\']) >= time())) {
                    
                    $appliedBonusCode = $submittedAffiliateCode;
                    $_SESSION[\'applied_bonus_code\'] = $appliedBonusCode;
                    $affiliateCode = null;
                    unset($_SESSION[\'affiliate_code\']);
                    setcookie(\'affiliate_code\', \'\', time() - 3600, \'/\');
                    $userReferralCode = null;
                    unset($_SESSION[\'referral_code\']);
                    setcookie(\'referral_code\', \'\', time() - 3600, \'/\');
                    $totals = getCartTotal(null, null, $appliedBonusCode, null);
                    $success = $bonusCodeData[\'discount_percent\'] . \'% discount applied with code \' . $submittedAffiliateCode . \'!\';
                    $submittedAffiliateCode = \'\';
                } else {
                    $lookupAffiliate = getAffiliateByCode($submittedAffiliateCode);
                    if ($lookupAffiliate && $lookupAffiliate[\'status\'] === \'active\') {
                        $affiliateCode = $submittedAffiliateCode;
                        $appliedBonusCode = null;
                        unset($_SESSION[\'applied_bonus_code\']);
                        $userReferralCode = null;
                        unset($_SESSION[\'referral_code\']);
                        setcookie(\'referral_code\', \'\', time() - 3600, \'/\');
                        $_SESSION[\'affiliate_code\'] = $affiliateCode;
                        setcookie(\'affiliate_code\', $affiliateCode, time() + 30 * 86400, \'/\');
                        if (function_exists(\'incrementAffiliateClick\')) incrementAffiliateClick($affiliateCode);
                        $totals = getCartTotal(null, $affiliateCode, null, null);
                        $success = (CUSTOMER_DISCOUNT_RATE * 100) . \'% affiliate discount applied successfully!\';
                        $submittedAffiliateCode = \'\';
                    } else {
                        $lookupReferral = getUserReferralByCode($submittedAffiliateCode);
                        if ($lookupReferral && $lookupReferral[\'status\'] === \'active\') {
                            $userReferralCode = $submittedAffiliateCode;
                            $appliedBonusCode = null;
                            unset($_SESSION[\'applied_bonus_code\']);
                            $affiliateCode = null;
                            unset($_SESSION[\'affiliate_code\']);
                            setcookie(\'affiliate_code\', \'\', time() - 3600, \'/\');
                            $_SESSION[\'referral_code\'] = $userReferralCode;
                            setcookie(\'referral_code\', $userReferralCode, time() + 30 * 86400, \'/\');
                            if (function_exists(\'incrementUserReferralClick\')) incrementUserReferralClick($userReferralCode);
                            $totals = getCartTotal(null, null, null, $userReferralCode);
                            $success = (CUSTOMER_DISCOUNT_RATE * 100) . \'% referral discount applied successfully!\';
                            $submittedAffiliateCode = \'\';
                        } else {
                            $errors[] = \'Invalid or inactive discount code.\';
                        }
                    }
                }
            }
        } else {
            $errors[] = \'Please enter a discount code.\';
        }
    }
}

';
    file_put_contents('cart-checkout.php', $before . $fixed_block . $after);
    echo "Fixed cart-checkout.php syntax\n";
} else {
    echo "Could not find patterns in cart-checkout.php\n";
}
