<?php
$pageTitle = 'Orders Management';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/delivery.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();
$successMessage = '';
$errorMessage = '';

// No action needed here - retry is now handled via POST below

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Handle delivery retry with CSRF protection (Phase 3)
        if ($action === 'retry_delivery') {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== getCsrfToken()) {
                header("Location: /admin/orders.php?error=" . urlencode('Invalid security token. Please try again.'));
                exit;
            }
            
            $orderId = intval($_POST['order_id']);
            if ($orderId <= 0) {
                header("Location: /admin/orders.php?error=" . urlencode('Invalid order ID.'));
                exit;
            }
            
            // Validate order exists and is paid before retrying delivery
            $order = getOrderById($orderId);
            if (!$order) {
                header("Location: /admin/orders.php?error=" . urlencode("Order #$orderId not found."));
                exit;
            } elseif ($order['status'] !== 'paid') {
                header("Location: /admin/orders.php?error=" . urlencode("Cannot create delivery for Order #$orderId - order must be paid first. Current status: " . htmlspecialchars($order['status'])));
                exit;
            } else {
                        require_once __DIR__ . '/../includes/delivery.php';
                        try {
                            $existingDeliveries = getDeliveryStatus($orderId);
                            if (empty($existingDeliveries)) {
                                createDeliveryRecords($orderId);
                                $successMsg = "✅ Delivery records created successfully for Order #$orderId. Customers will receive download links via email.";
                                logActivity('delivery_retry', "Manually retried delivery creation for Order #$orderId", getAdminId());
                                header("Location: /admin/orders.php?success=" . urlencode($successMsg));
                                exit;
                            } else {
                                $successMsg = "ℹ️ Delivery records already exist for Order #$orderId. No action needed.";
                                header("Location: /admin/orders.php?success=" . urlencode($successMsg));
                                exit;
                            }
                        } catch (Exception $e) {
                            // Retry failed - log and redirect with persistent error banner and retry button
                            logActivity('delivery_retry_failed', "Delivery retry failed for Order #$orderId: " . $e->getMessage(), getAdminId());
                            $retryErrorMsg = "⚠️ DELIVERY RETRY FAILED for Order #$orderId: " . htmlspecialchars($e->getMessage());
                            header("Location: /admin/orders.php?delivery_error=" . urlencode($retryErrorMsg) . "&delivery_error_order=" . $orderId);
                            exit;
                        }
                    }
        } elseif ($action === 'mark_paid') {
            $orderId = intval($_POST['order_id']);
            $paymentNotes = sanitizeInput($_POST['payment_notes'] ?? '');
            
            if ($orderId <= 0) {
                $errorMessage = 'Invalid order ID.';
            } else {
                $order = getOrderById($orderId);
                if (!$order) {
                    $errorMessage = 'Order not found.';
                } else {
                    $orderItems = getOrderItems($orderId);
                    $amountPaid = computeFinalAmount($order, $orderItems);
                    
                    if ($amountPaid <= 0) {
                        $errorMessage = 'Invalid order amount. Cannot process payment.';
                    } else {
                        // Handle domain assignments before marking as paid
                        $domainAssignmentErrors = [];
                        foreach ($orderItems as $item) {
                            if ($item['product_type'] === 'template') {
                                $itemId = $item['id'];
                                $domainFieldName = 'domain_id_' . $itemId;
                                
                                if (isset($_POST[$domainFieldName]) && !empty($_POST[$domainFieldName])) {
                                    $domainId = intval($_POST[$domainFieldName]);
                                    if ($domainId > 0) {
                                        $result = setOrderItemDomain($itemId, $domainId, $orderId);
                                        if (!$result['success']) {
                                            $domainAssignmentErrors[] = $result['message'];
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Proceed with payment confirmation
                        if (empty($domainAssignmentErrors)) {
                            $result = markOrderPaid($orderId, getAdminId(), $amountPaid, $paymentNotes);
                            if ($result['success']) {
                                $successMessage = 'Order confirmed successfully! Amount: ' . formatCurrency($amountPaid);
                                if (!empty($paymentNotes)) {
                                    $successMessage .= ' Payment notes have been saved.';
                                }
                                
                                // CRITICAL: Warn admin if delivery creation failed - persist in URL for visibility
                                if (!empty($result['delivery_error'])) {
                                    $deliveryErrorMsg = '⚠️ DELIVERY FAILURE: Order confirmed but delivery creation failed. Error: ' . htmlspecialchars($result['delivery_error']);
                                    header("Location: /admin/orders.php?delivery_error=" . urlencode($deliveryErrorMsg) . "&delivery_error_order=" . $orderId . "&success=" . urlencode($successMessage));
                                    exit;
                                }
                                
                                logActivity('order_marked_paid', "Order #$orderId marked as paid with amount " . formatCurrency($amountPaid), getAdminId());
                            } else {
                                $errorMessage = 'Failed to confirm order: ' . ($result['message'] ?? 'Unknown error. Please try again.');
                            }
                        } else {
                            $errorMessage = 'Domain assignment failed: ' . implode(', ', $domainAssignmentErrors);
                        }
                    }
                }
            }
        } elseif ($action === 'update_order_domains') {
            $orderId = intval($_POST['order_id']);
            $paymentNotes = sanitizeInput($_POST['payment_notes'] ?? '');
            
            if ($orderId <= 0) {
                $errorMessage = 'Invalid order ID.';
            } else {
                // SECURITY: Check if order is paid before allowing domain assignment
                $order = getOrderById($orderId);
                if (!$order) {
                    $errorMessage = "Order #$orderId not found.";
                } elseif ($order['status'] !== 'paid') {
                    $errorMessage = "❌ SECURITY BLOCK: Cannot assign domains to this order. Order status is '{$order['status']}' - only PAID orders can have domains assigned. This prevents fraud and unauthorized access.";
                } else {
                    $updateErrors = [];
                    $updateCount = 0;
                    
                    // Update payment notes
                    if (!empty($paymentNotes)) {
                        $stmt = $db->prepare("UPDATE pending_orders SET payment_notes = ? WHERE id = ?");
                        if ($stmt->execute([$paymentNotes, $orderId])) {
                            $updateCount++;
                        }
                    }
                    
                    // Process all domain assignments
                    foreach ($_POST as $key => $value) {
                        if (strpos($key, 'domain_id_') === 0 && !empty($value)) {
                            $orderItemId = intval(str_replace('domain_id_', '', $key));
                            $domainId = intval($value);
                            
                            if ($orderItemId > 0 && $domainId > 0) {
                                $result = setOrderItemDomain($orderItemId, $domainId, $orderId);
                                if ($result['success']) {
                                    $updateCount++;
                                } else {
                                    $updateErrors[] = $result['message'];
                                }
                            }
                        }
                    }
                }
                
                if (empty($updateErrors)) {
                    if ($updateCount > 0) {
                        $successMessage = "Updated $updateCount item(s) successfully!";
                        logActivity('order_updated', "Order #$orderId updated with domains and notes", getAdminId());
                    } else {
                        $errorMessage = 'No changes were made.';
                    }
                } else {
                    $errorMessage = 'Some updates failed: ' . implode(', ', $updateErrors);
                }
                
                // Redirect back to the view modal
                header("Location: /admin/orders.php?view=$orderId" . ($successMessage ? "&success=" . urlencode($successMessage) : "") . ($errorMessage ? "&error=" . urlencode($errorMessage) : ""));
                exit;
            }
        } elseif ($action === 'assign_domain') {
            $orderId = intval($_POST['order_id']);
            $domainId = intval($_POST['domain_id']);
            $orderItemId = isset($_POST['order_item_id']) && !empty($_POST['order_item_id']) ? intval($_POST['order_item_id']) : null;
            
            if ($orderId <= 0 || $domainId <= 0) {
                $errorMessage = 'Invalid order or domain ID.';
            } else {
                // SECURITY: Check if order is paid before allowing domain assignment
                $order = getOrderById($orderId);
                if (!$order) {
                    $errorMessage = "Order #$orderId not found.";
                } elseif ($order['status'] !== 'paid') {
                    $errorMessage = "❌ SECURITY BLOCK: Cannot assign domain to this order. Order status is '{$order['status']}' - only PAID orders can have domains assigned. This prevents fraud and unauthorized access.";
                } elseif ($orderItemId) {
                    $result = setOrderItemDomain($orderItemId, $domainId, $orderId);
                    if ($result['success']) {
                        $successMessage = $result['message'];
                        logActivity('domain_assigned', "Domain #$domainId assigned to order #$orderId (item #$orderItemId)", getAdminId());
                    } else {
                        $errorMessage = 'Failed to assign domain: ' . $result['message'];
                    }
                } else {
                    try {
                        $db->beginTransaction();
                        
                        if (!assignDomainToCustomer($domainId, $orderId)) {
                            throw new Exception('Failed to assign domain globally');
                        }
                        
                        $stmt = $db->prepare("UPDATE pending_orders SET chosen_domain_id = ? WHERE id = ?");
                        $stmt->execute([$domainId, $orderId]);
                        
                        $db->commit();
                        $successMessage = 'Domain assigned successfully!';
                        logActivity('domain_assigned', "Domain #$domainId assigned to order #$orderId", getAdminId());
                    } catch (Exception $e) {
                        $db->rollBack();
                        $errorMessage = 'Failed to assign domain: ' . $e->getMessage();
                        error_log('Domain assignment error: ' . $e->getMessage());
                    }
                }
            }
        } elseif ($action === 'cancel_order') {
            $orderId = intval($_POST['order_id']);
            $result = cancelOrder($orderId, 'Order cancelled by administrator', getAdminId());
            
            if ($result['success']) {
                $successMessage = $result['message'] . ' Customer has been notified by email.';
            } else {
                $errorMessage = 'Failed to cancel order: ' . $result['message'];
            }
        } elseif ($action === 'bulk_mark_paid') {
            $orderIds = $_POST['order_ids'] ?? [];
            $successCount = 0;
            $failCount = 0;
            $deliveryFailures = []; // Track orders with delivery creation failures
            
            foreach ($orderIds as $orderId) {
                $orderId = intval($orderId);
                if ($orderId > 0) {
                    // Get order details with template/tool prices as fallback for legacy orders
                    $stmt = $db->prepare("
                        SELECT po.*,
                               t.price as template_price,
                               tool.price as tool_price
                        FROM pending_orders po
                        LEFT JOIN templates t ON po.template_id = t.id
                        LEFT JOIN tools tool ON po.tool_id = tool.id
                        WHERE po.id = ? AND po.status = 'pending'
                    ");
                    $stmt->execute([$orderId]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($order) {
                        // Priority order for calculating payable amount:
                        // 1. Use final_amount if set (most accurate)
                        // 2. Use original_price if set
                        // 3. Calculate from order_items
                        // 4. Fall back to template_price or tool_price for legacy orders
                        $payableAmount = $order['final_amount'] ?? $order['original_price'] ?? 0;
                        
                        if ($payableAmount == 0) {
                            // Try to get from order_items
                            $itemsStmt = $db->prepare("SELECT SUM(final_amount) as total FROM order_items WHERE pending_order_id = ?");
                            $itemsStmt->execute([$orderId]);
                            $itemsTotal = $itemsStmt->fetchColumn();
                            
                            if ($itemsTotal > 0) {
                                $payableAmount = $itemsTotal;
                            } else {
                                // Last resort: use template or tool price for legacy orders
                                $basePrice = $order['template_price'] ?? $order['tool_price'] ?? 0;
                                if ($basePrice > 0) {
                                    // Apply affiliate discount if applicable
                                    if (!empty($order['affiliate_code'])) {
                                        $discountRate = CUSTOMER_DISCOUNT_RATE;
                                        $payableAmount = $basePrice * (1 - $discountRate);
                                    } else {
                                        $payableAmount = $basePrice;
                                    }
                                }
                            }
                        }
                        
                        if ($payableAmount > 0) {
                            $result = markOrderPaid($orderId, getAdminId(), $payableAmount, 'Bulk processed');
                            if ($result['success']) {
                                $successCount++;
                                // Track delivery errors in bulk processing for UI feedback
                                if (!empty($result['delivery_error'])) {
                                    $deliveryFailures[] = $orderId;
                                    error_log("Order #{$orderId} delivery creation failed during bulk processing: " . $result['delivery_error']);
                                }
                            } else {
                                $failCount++;
                                error_log("Bulk payment failed for order #{$orderId}: " . ($result['message'] ?? 'Unknown error'));
                            }
                        } else {
                            $failCount++;
                            error_log("Bulk payment failed for order #{$orderId}: payableAmount = {$payableAmount}");
                        }
                    } else {
                        $failCount++;
                    }
                }
            }
            
            if ($successCount > 0) {
                $successMessage = "Successfully marked {$successCount} order(s) as paid";
                if ($failCount > 0) {
                    $successMessage .= ". {$failCount} failed.";
                }
                
                // Alert admin about delivery failures in bulk processing
                if (!empty($deliveryFailures)) {
                    $failedOrdersList = implode(', #', $deliveryFailures);
                    $errorMessage = "⚠️ WARNING: Delivery creation failed for " . count($deliveryFailures) . " order(s): #" . $failedOrdersList . ". Please retry delivery creation individually for each order.";
                }
                
                logActivity('bulk_orders_processed', "Bulk processed {$successCount} orders", getAdminId());
            } else {
                $errorMessage = 'No orders were processed.';
            }
        } elseif ($action === 'bulk_cancel') {
            $orderIds = $_POST['order_ids'] ?? [];
            $successCount = 0;
            $failCount = 0;
            
            foreach ($orderIds as $orderId) {
                $orderId = intval($orderId);
                if ($orderId > 0) {
                    $result = cancelOrder($orderId, 'Bulk cancelled by administrator', getAdminId());
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $failCount++;
                    }
                }
            }
            
            if ($successCount > 0) {
                $successMessage = "Cancelled {$successCount} order(s) successfully!";
                if ($failCount > 0) {
                    $successMessage .= " {$failCount} failed.";
                }
            } else {
                $errorMessage = 'No orders were cancelled.';
            }
        } elseif ($action === 'save_template_credentials') {
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $errorMessage = 'Security verification failed. Please refresh and try again.';
            } else {
                require_once __DIR__ . '/../includes/delivery.php';
                
                $deliveryId = intval($_POST['delivery_id'] ?? 0);
                $hostedDomain = sanitizeInput($_POST['hosted_domain'] ?? '');
                $hostedUrl = sanitizeInput($_POST['hosted_url'] ?? '');
                $adminNotes = sanitizeInput($_POST['admin_notes'] ?? '');
                $sendEmail = isset($_POST['send_email']) && $_POST['send_email'] === '1';
                
                $credentials = [
                    'username' => sanitizeInput($_POST['template_admin_username'] ?? ''),
                    'password' => $_POST['template_admin_password'] ?? '',
                    'login_url' => sanitizeInput($_POST['template_login_url'] ?? ''),
                    'hosting_provider' => sanitizeInput($_POST['hosting_provider'] ?? 'custom')
                ];
                
                if ($deliveryId <= 0) {
                    $errorMessage = 'Invalid delivery ID.';
                } else {
                    $result = saveTemplateCredentials($deliveryId, $credentials, $hostedDomain, $hostedUrl, $adminNotes, $sendEmail);
                    
                    if ($result['success']) {
                        $successMessage = $result['message'];
                        logActivity('template_credentials_saved', "Credentials saved for delivery #$deliveryId" . ($sendEmail ? ' and email sent' : ''), getAdminId());
                        
                        $delivery = getDeliveryById($deliveryId);
                        $orderId = $delivery ? $delivery['pending_order_id'] : ($_POST['order_id'] ?? null);
                        
                        if ($orderId) {
                            header("Location: /admin/orders.php?view=" . intval($orderId) . "&success=" . urlencode($successMessage));
                            exit;
                        }
                    } else {
                        $errorMessage = $result['message'];
                    }
                }
            }
        } elseif ($action === 'batch_template_credentials') {
            // Phase 5.3: Batch Template Assignment handler
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $errorMessage = 'Security verification failed. Please refresh and try again.';
            } else {
                require_once __DIR__ . '/../includes/delivery.php';
                
                $deliveries = $_POST['deliveries'] ?? [];
                $orderId = intval($_POST['order_id'] ?? 0);
                $sendEmails = isset($_POST['send_emails']) && $_POST['send_emails'] === '1';
                
                if (empty($deliveries)) {
                    $errorMessage = 'No template deliveries provided.';
                } elseif ($orderId <= 0) {
                    $errorMessage = 'Invalid order ID.';
                } else {
                    $successCount = 0;
                    $failCount = 0;
                    $failMessages = [];
                    
                    foreach ($deliveries as $d) {
                        $deliveryId = intval($d['delivery_id'] ?? 0);
                        if ($deliveryId <= 0) {
                            $failCount++;
                            continue;
                        }
                        
                        $hostedDomain = sanitizeInput($d['hosted_domain'] ?? '');
                        $hostedUrl = sanitizeInput($d['hosted_url'] ?? '');
                        
                        $credentials = [
                            'username' => sanitizeInput($d['username'] ?? ''),
                            'password' => $d['password'] ?? '',
                            'login_url' => sanitizeInput($d['login_url'] ?? ''),
                            'hosting_provider' => sanitizeInput($d['hosting_provider'] ?? 'custom')
                        ];
                        
                        if (empty($hostedDomain) || empty($credentials['username']) || empty($credentials['password'])) {
                            $failCount++;
                            $failMessages[] = "Delivery #$deliveryId: Missing required fields";
                            continue;
                        }
                        
                        $result = saveTemplateCredentials($deliveryId, $credentials, $hostedDomain, $hostedUrl, '', $sendEmails);
                        
                        if ($result['success']) {
                            $successCount++;
                            logActivity('batch_template_credentials', "Batch delivered template #$deliveryId", getAdminId());
                        } else {
                            $failCount++;
                            $failMessages[] = "Delivery #$deliveryId: " . $result['message'];
                        }
                    }
                    
                    // Update order delivery status after batch processing
                    updateOrderDeliveryStatus($orderId);
                    
                    if ($successCount > 0) {
                        $successMessage = "Successfully delivered {$successCount} template(s)";
                        if ($sendEmails) {
                            $successMessage .= " with email notifications sent";
                        }
                        if ($failCount > 0) {
                            $successMessage .= ". {$failCount} failed.";
                        }
                        
                        header("Location: /admin/orders.php?view=" . $orderId . "&success=" . urlencode($successMessage));
                        exit;
                    } else {
                        $errorMessage = 'No templates were delivered. ' . implode('; ', $failMessages);
                    }
                }
            }
        } elseif ($action === 'resend_template_email') {
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $errorMessage = 'Security verification failed. Please refresh and try again.';
            } else {
                require_once __DIR__ . '/../includes/delivery.php';
                
                $deliveryId = intval($_POST['delivery_id'] ?? 0);
                
                if ($deliveryId <= 0) {
                    $errorMessage = 'Invalid delivery ID.';
                } else {
                    $result = deliverTemplateWithCredentials($deliveryId);
                    
                    if ($result['success']) {
                        $successMessage = 'Email resent successfully!';
                        logActivity('template_email_resent', "Resent template delivery email for delivery #$deliveryId", getAdminId());
                    } else {
                        $errorMessage = $result['message'];
                    }
                }
            }
        } elseif ($action === 'regenerate_download_link') {
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $errorMessage = 'Security verification failed. Please refresh and try again.';
            } else {
                require_once __DIR__ . '/../includes/tool_files.php';
                
                $tokenId = intval($_POST['token_id'] ?? 0);
                $orderId = intval($_POST['order_id'] ?? 0);
                
                if ($tokenId <= 0) {
                    $errorMessage = 'Invalid download token ID.';
                } else {
                    $result = regenerateDownloadLink($tokenId);
                    
                    if ($result['success']) {
                        $successMessage = 'Download link regenerated successfully! New link: ' . $result['link']['url'];
                        logActivity('download_link_regenerated', "Regenerated download link for token #$tokenId, order #$orderId", getAdminId());
                        
                        if ($orderId > 0) {
                            header("Location: /admin/orders.php?view=" . $orderId . "&success=" . urlencode($successMessage));
                            exit;
                        }
                    } else {
                        $errorMessage = $result['message'];
                    }
                }
            }
        } elseif ($action === 'resend_tool_email') {
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $errorMessage = 'Security verification failed. Please refresh and try again.';
            } else {
                require_once __DIR__ . '/../includes/delivery.php';
                
                $deliveryId = intval($_POST['delivery_id'] ?? 0);
                $orderId = intval($_POST['order_id'] ?? 0);
                
                if ($deliveryId <= 0) {
                    $errorMessage = 'Invalid delivery ID.';
                } else {
                    $result = resendToolDeliveryEmail($deliveryId);
                    
                    if ($result['success']) {
                        $successMessage = 'Tool delivery email resent successfully!';
                        logActivity('tool_email_resent', "Resent tool delivery email for delivery #$deliveryId", getAdminId());
                        
                        if ($orderId > 0) {
                            header("Location: /admin/orders.php?view=" . $orderId . "&success=" . urlencode($successMessage));
                            exit;
                        }
                    } else {
                        $errorMessage = $result['message'];
                    }
                }
            }
        } elseif ($action === 'save_template_credentials') {
            if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $errorMessage = 'Security verification failed. Please refresh and try again.';
            } else {
                require_once __DIR__ . '/../includes/delivery.php';
                
                $deliveryId = intval($_POST['delivery_id'] ?? 0);
                $orderId = intval($_POST['order_id'] ?? 0);
                
                if ($deliveryId <= 0) {
                    $errorMessage = 'Invalid delivery ID.';
                } else {
                    $hostedDomain = sanitizeInput($_POST['hosted_domain'] ?? '');
                    $hostedUrl = sanitizeInput($_POST['hosted_url'] ?? '');
                    $loginUrl = sanitizeInput($_POST['template_login_url'] ?? '');
                    $hostingProvider = sanitizeInput($_POST['hosting_provider'] ?? 'custom');
                    $adminUsername = sanitizeInput($_POST['template_admin_username'] ?? '');
                    $adminPassword = $_POST['template_admin_password'] ?? '';
                    $adminNotes = sanitizeInput($_POST['admin_notes'] ?? '');
                    $sendEmail = isset($_POST['send_email']) && $_POST['send_email'] === '1';
                    
                    if (empty($hostedDomain)) {
                        $errorMessage = 'Domain name is required.';
                    } else {
                        $stmt = $db->prepare("SELECT * FROM deliveries WHERE id = ?");
                        $stmt->execute([$deliveryId]);
                        $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$delivery) {
                            $errorMessage = 'Delivery not found.';
                        } else {
                            $encryptedPassword = $delivery['template_admin_password'];
                            if (!empty($adminPassword)) {
                                $encryptedPassword = encryptCredential($adminPassword);
                            }
                            
                            $updateStmt = $db->prepare("
                                UPDATE deliveries SET
                                    hosted_domain = ?,
                                    hosted_url = ?,
                                    template_login_url = ?,
                                    hosting_provider = ?,
                                    template_admin_username = ?,
                                    template_admin_password = ?,
                                    admin_notes = ?
                                WHERE id = ?
                            ");
                            $updateStmt->execute([
                                $hostedDomain,
                                $hostedUrl,
                                $loginUrl,
                                $hostingProvider,
                                $adminUsername,
                                $encryptedPassword,
                                $adminNotes,
                                $deliveryId
                            ]);
                            
                            if ($sendEmail) {
                                $result = deliverTemplateWithCredentials($deliveryId);
                                if ($result['success']) {
                                    $successMessage = 'Credentials updated and email resent successfully!';
                                    logActivity('template_credentials_updated', "Updated and resent credentials for delivery #$deliveryId", getAdminId());
                                } else {
                                    $successMessage = 'Credentials updated but email sending failed. Please resend manually.';
                                    logActivity('template_credentials_updated_email_failed', "Updated credentials for delivery #$deliveryId but email failed", getAdminId());
                                }
                            } else {
                                $successMessage = 'Credentials updated successfully!';
                                logActivity('template_credentials_updated', "Updated credentials for delivery #$deliveryId (no email sent)", getAdminId());
                            }
                            
                            if ($orderId > 0) {
                                header("Location: /admin/orders.php?view=" . $orderId . "&success=" . urlencode($successMessage));
                                exit;
                            }
                        }
                    }
                }
            }
        }
    }
}

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $sql = "SELECT po.*, t.name as template_name, t.price as template_price,
            tool.name as tool_name, tool.price as tool_price, d.domain_name,
            (SELECT COUNT(*) FROM sales WHERE pending_order_id = po.id) as is_paid,
            (SELECT COUNT(*) FROM order_items WHERE pending_order_id = po.id) as item_count
            FROM pending_orders po
            LEFT JOIN templates t ON po.template_id = t.id
            LEFT JOIN tools tool ON po.tool_id = tool.id
            LEFT JOIN domains d ON po.chosen_domain_id = d.id
            ORDER BY po.created_at DESC";
    
    $orders = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="orders_export_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    $output = fopen('php://output', 'w');
    
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['Order ID', 'Order Type', 'Customer Name', 'Email', 'Phone', 'Products', 'Item Count', 'Price (NGN)', 'Affiliate Code', 'Domain', 'Status', 'Is Paid', 'Order Date'], ',', '"');
    
    foreach ($orders as $order) {
        // Get order items for accurate product list
        $orderItems = getOrderItems($order['id']);
        $orderType = $order['order_type'] ?? 'template';
        $productsList = '';
        $itemCount = 0;
        
        if (!empty($orderItems)) {
            $productNames = [];
            foreach ($orderItems as $item) {
                $productName = $item['product_type'] === 'template' ? $item['template_name'] : $item['tool_name'];
                $qty = $item['quantity'] > 1 ? ' (x' . $item['quantity'] . ')' : '';
                $productNames[] = $productName . $qty;
            }
            $productsList = implode('; ', $productNames);
            $itemCount = count($orderItems);
        } elseif ($order['template_name']) {
            $productsList = $order['template_name'];
            $itemCount = 1;
        } elseif ($order['tool_name']) {
            $productsList = $order['tool_name'];
            $itemCount = 1;
        }
        
        // Use final_amount for accurate pricing
        $payableAmount = $order['final_amount'] ?? $order['original_price'] ?? $order['template_price'] ?? $order['tool_price'] ?? 0;
        
        fputcsv($output, [
            (string)($order['id'] ?? ''),
            ucfirst($orderType),
            $order['customer_name'] ?? '',
            $order['customer_email'] ?? '',
            $order['customer_phone'] ?? '',
            $productsList,
            $itemCount,
            number_format($payableAmount, 2, '.', ''),
            $order['affiliate_code'] ?? 'Direct',
            $order['domain_name'] ?? 'Not assigned',
            $order['status'] ?? '',
            $order['is_paid'] ? 'Yes' : 'No',
            $order['created_at'] ? date('Y-m-d H:i:s', strtotime($order['created_at'])) : ''
        ], ',', '"');
    }
    
    fclose($output);
    exit;
}

// Handle persistent delivery error messages from redirects (Phase 3)
if (isset($_GET['delivery_error'])) {
    $errorMessage = $_GET['delivery_error'];
}
if (isset($_GET['success']) && empty($successMessage)) {
    $successMessage = $_GET['success'];
}

$searchTerm = $_GET['search'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterTemplate = $_GET['template'] ?? '';
$filterOrderType = $_GET['order_type'] ?? '';
$filterPaymentMethod = $_GET['payment_method'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$filterDeliveryStatus = $_GET['delivery_status'] ?? '';

$sql = "SELECT po.*, po.payment_notes, t.name as template_name, t.price as template_price, 
        tool.name as tool_name, tool.price as tool_price, d.domain_name,
        (SELECT COUNT(*) FROM sales WHERE pending_order_id = po.id) as is_paid,
        (SELECT COUNT(*) FROM order_items WHERE pending_order_id = po.id) as item_count
        FROM pending_orders po
        LEFT JOIN templates t ON po.template_id = t.id
        LEFT JOIN tools tool ON po.tool_id = tool.id
        LEFT JOIN domains d ON po.chosen_domain_id = d.id
        WHERE 1=1";
$params = [];

if (!empty($searchTerm)) {
    $sql .= " AND (po.customer_name LIKE ? OR po.customer_email LIKE ? OR po.customer_phone LIKE ? OR po.business_name LIKE ? 
              OR t.name LIKE ? OR tool.name LIKE ?
              OR EXISTS (SELECT 1 FROM order_items oi WHERE oi.pending_order_id = po.id AND (oi.template_name LIKE ? OR oi.tool_name LIKE ?)))";
    $searchPattern = '%' . $searchTerm . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

if (!empty($filterStatus)) {
    $sql .= " AND po.status = ?";
    $params[] = $filterStatus;
}

if (!empty($filterTemplate)) {
    $sql .= " AND po.template_id = ?";
    $params[] = intval($filterTemplate);
}

if (!empty($filterOrderType)) {
    if ($filterOrderType === 'templates_only') {
        $sql .= " AND (
                    (EXISTS (SELECT 1 FROM order_items WHERE pending_order_id = po.id AND product_type = 'template')
                     AND NOT EXISTS (SELECT 1 FROM order_items WHERE pending_order_id = po.id AND product_type = 'tool'))
                    OR (po.template_id IS NOT NULL AND po.tool_id IS NULL 
                        AND NOT EXISTS (SELECT 1 FROM order_items WHERE pending_order_id = po.id))
                  )";
    } elseif ($filterOrderType === 'tools_only') {
        $sql .= " AND (
                    (EXISTS (SELECT 1 FROM order_items WHERE pending_order_id = po.id AND product_type = 'tool')
                     AND NOT EXISTS (SELECT 1 FROM order_items WHERE pending_order_id = po.id AND product_type = 'template'))
                    OR (po.tool_id IS NOT NULL AND po.template_id IS NULL 
                        AND NOT EXISTS (SELECT 1 FROM order_items WHERE pending_order_id = po.id))
                  )";
    } elseif ($filterOrderType === 'mixed') {
        $sql .= " AND EXISTS (SELECT 1 FROM order_items WHERE pending_order_id = po.id AND product_type = 'template')
                  AND EXISTS (SELECT 1 FROM order_items WHERE pending_order_id = po.id AND product_type = 'tool')";
    }
}

if (!empty($filterPaymentMethod)) {
    if ($filterPaymentMethod === 'manual') {
        $sql .= " AND EXISTS (SELECT 1 FROM sales WHERE pending_order_id = po.id AND payment_method = 'manual')";
    } elseif ($filterPaymentMethod === 'automatic') {
        $sql .= " AND EXISTS (SELECT 1 FROM sales WHERE pending_order_id = po.id AND payment_method != 'manual')";
    }
}

if (!empty($filterDateFrom)) {
    $sql .= " AND date(po.created_at) >= ?";
    $params[] = $filterDateFrom;
}

if (!empty($filterDateTo)) {
    $sql .= " AND date(po.created_at) <= ?";
    $params[] = $filterDateTo;
}

if (!empty($filterDeliveryStatus)) {
    if ($filterDeliveryStatus === 'delivered') {
        $sql .= " AND EXISTS (SELECT 1 FROM deliveries WHERE pending_order_id = po.id AND delivery_status = 'delivered')";
    } elseif ($filterDeliveryStatus === 'pending_delivery') {
        $sql .= " AND EXISTS (SELECT 1 FROM deliveries WHERE pending_order_id = po.id AND delivery_status IN ('pending', 'in_progress', 'ready'))";
    } elseif ($filterDeliveryStatus === 'no_delivery') {
        $sql .= " AND NOT EXISTS (SELECT 1 FROM deliveries WHERE pending_order_id = po.id)";
    }
}

$sql .= " ORDER BY po.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$templates = getTemplates(false);

$viewOrder = null;
$viewOrderItems = [];
$availableDomains = [];
if (isset($_GET['view'])) {
    $viewOrder = getOrderById(intval($_GET['view']));
    if ($viewOrder) {
        $viewOrderItems = getOrderItems($viewOrder['id']);
        if ($viewOrder['template_id']) {
            $availableDomains = getAvailableDomains($viewOrder['template_id']);
        }
    }
    
    // Handle success/error messages from redirect
    if (isset($_GET['success'])) {
        $successMessage = $_GET['success'];
    }
    if (isset($_GET['error'])) {
        $errorMessage = $_GET['error'];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-cart text-primary-600"></i> Orders Management
    </h1>
</div>

<?php if ($successMessage): ?>
<div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6 flex items-center justify-between" x-data="{ show: true }" x-show="show">
    <div class="flex items-center gap-3">
        <i class="bi bi-check-circle text-xl"></i>
        <span><?php echo htmlspecialchars($successMessage); ?></span>
    </div>
    <button @click="show = false" class="text-green-700 hover:text-green-900">
        <i class="bi bi-x-lg"></i>
    </button>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6" x-data="{ show: true }" x-show="show">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-3">
            <i class="bi bi-exclamation-triangle text-xl"></i>
            <span><?php echo $errorMessage; ?></span>
        </div>
        <button @click="show = false" class="text-red-700 hover:text-red-900">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <?php if (isset($_GET['delivery_error_order'])): ?>
    <form method="POST" class="mt-3 inline-block">
        <input type="hidden" name="action" value="retry_delivery">
        <input type="hidden" name="order_id" value="<?php echo intval($_GET['delivery_error_order']); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
        <button type="submit" class="bg-red-700 hover:bg-red-800 text-white font-bold py-2 px-4 rounded-lg transition-colors inline-flex items-center gap-2">
            <i class="bi bi-arrow-clockwise"></i>
            Retry Delivery Creation for Order #<?php echo intval($_GET['delivery_error_order']); ?>
        </button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-md border border-gray-100 mb-6" x-data="{ showAdvanced: false }">
    <div class="p-6">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
                    <input type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search by name, email, phone, products...">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Order Type</label>
                    <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="order_type">
                        <option value="">All Types</option>
                        <option value="templates_only" <?php echo $filterOrderType === 'templates_only' ? 'selected' : ''; ?>>Templates Only</option>
                        <option value="tools_only" <?php echo $filterOrderType === 'tools_only' ? 'selected' : ''; ?>>Tools Only</option>
                        <option value="mixed" <?php echo $filterOrderType === 'mixed' ? 'selected' : ''; ?>>Mixed Orders</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                    <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $filterStatus === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Template</label>
                    <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="template">
                        <option value="">All Templates</option>
                        <?php foreach ($templates as $tpl): ?>
                        <option value="<?php echo $tpl['id']; ?>" <?php echo $filterTemplate == $tpl['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tpl['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold rounded-lg transition-all shadow-lg">
                        <i class="bi bi-search mr-2"></i> Filter
                    </button>
                    <button type="button" @click="showAdvanced = !showAdvanced" class="px-3 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition-colors" title="Advanced Filters">
                        <i class="bi bi-sliders"></i>
                    </button>
                </div>
            </div>
            
            <div x-show="showAdvanced" x-transition class="grid grid-cols-1 md:grid-cols-4 gap-4 pt-4 border-t border-gray-200">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Method</label>
                    <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="payment_method">
                        <option value="">All Methods</option>
                        <option value="manual" <?php echo $filterPaymentMethod === 'manual' ? 'selected' : ''; ?>>Manual Payment</option>
                        <option value="automatic" <?php echo $filterPaymentMethod === 'automatic' ? 'selected' : ''; ?>>Automatic (Paystack)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Delivery Status</label>
                    <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="delivery_status">
                        <option value="">All Deliveries</option>
                        <option value="delivered" <?php echo $filterDeliveryStatus === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="pending_delivery" <?php echo $filterDeliveryStatus === 'pending_delivery' ? 'selected' : ''; ?>>Pending Delivery</option>
                        <option value="no_delivery" <?php echo $filterDeliveryStatus === 'no_delivery' ? 'selected' : ''; ?>>No Delivery Record</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">From Date</label>
                    <input type="date" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">To Date</label>
                    <input type="date" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                </div>
            </div>
            
            <?php
            $hasFilters = !empty($searchTerm) || !empty($filterStatus) || !empty($filterTemplate) || !empty($filterOrderType) || !empty($filterPaymentMethod) || !empty($filterDateFrom) || !empty($filterDateTo) || !empty($filterDeliveryStatus);
            if ($hasFilters):
            ?>
            <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                <div class="flex flex-wrap gap-2 text-sm">
                    <span class="text-gray-500">Active Filters:</span>
                    <?php if (!empty($searchTerm)): ?>
                    <span class="bg-primary-100 text-primary-800 px-2 py-1 rounded-full text-xs">Search: "<?php echo htmlspecialchars($searchTerm); ?>"</span>
                    <?php endif; ?>
                    <?php if (!empty($filterStatus)): ?>
                    <span class="bg-primary-100 text-primary-800 px-2 py-1 rounded-full text-xs"><?php echo ucfirst($filterStatus); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($filterOrderType)): ?>
                    <span class="bg-primary-100 text-primary-800 px-2 py-1 rounded-full text-xs"><?php echo ucwords(str_replace('_', ' ', $filterOrderType)); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($filterPaymentMethod)): ?>
                    <span class="bg-primary-100 text-primary-800 px-2 py-1 rounded-full text-xs"><?php echo ucfirst($filterPaymentMethod); ?> Payment</span>
                    <?php endif; ?>
                    <?php if (!empty($filterDeliveryStatus)): ?>
                    <span class="bg-primary-100 text-primary-800 px-2 py-1 rounded-full text-xs"><?php echo ucwords(str_replace('_', ' ', $filterDeliveryStatus)); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($filterDateFrom) || !empty($filterDateTo)): ?>
                    <span class="bg-primary-100 text-primary-800 px-2 py-1 rounded-full text-xs">
                        <?php echo $filterDateFrom ?: '*'; ?> to <?php echo $filterDateTo ?: '*'; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <a href="/admin/orders.php" class="text-red-600 hover:text-red-700 text-sm font-semibold">
                    <i class="bi bi-x-circle mr-1"></i> Clear All
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="bg-white rounded-xl shadow-md border border-gray-100">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center px-6 py-4 border-b border-gray-200 gap-3">
        <h5 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-cart text-primary-600"></i> Orders (<?php echo count($orders); ?>)
        </h5>
        <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <a href="/admin/orders.php?export=csv" class="w-full sm:w-auto px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors text-sm text-center whitespace-nowrap">
                <i class="bi bi-download mr-1"></i> Export CSV
            </a>
            <button type="button" class="w-full sm:w-auto px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors text-sm disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap" id="bulkMarkPaidBtn" disabled>
                <i class="bi bi-check-circle mr-1"></i> Mark Selected as Paid
            </button>
            <button type="button" class="w-full sm:w-auto px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors text-sm disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap" id="bulkCancelBtn" disabled>
                <i class="bi bi-x-circle mr-1"></i> Cancel Selected
            </button>
        </div>
    </div>
    <div class="p-6">
        <form id="bulkActionsForm" method="POST" action="">
            <input type="hidden" name="action" id="bulkAction" value="">
            
            <!-- Desktop Table View -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-300">
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm" style="width: 40px;">
                                <input type="checkbox" id="selectAll" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                            </th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Order ID</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Customer</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Products</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Total</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Notes</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Status</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Date</th>
                            <th class="text-left py-3 px-2 font-semibold text-gray-700 text-sm">Actions</th>
                        </tr>
                    </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-12">
                            <i class="bi bi-inbox text-6xl text-gray-300"></i>
                            <p class="text-gray-500 mt-4">No orders found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-2">
                            <?php if ($order['status'] === 'pending'): ?>
                            <input type="checkbox" name="order_ids[]" value="<?php echo $order['id']; ?>" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 order-checkbox">
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-2 font-bold text-gray-900">#<?php echo $order['id']; ?></td>
                        <td class="py-3 px-2">
                            <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                            <div class="text-xs text-gray-500 space-y-0.5 mt-1">
                                <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($order['customer_email']); ?></div>
                                <div><i class="bi bi-phone"></i> <?php echo htmlspecialchars($order['customer_phone']); ?></div>
                            </div>
                        </td>
                        <td class="py-3 px-2">
                            <?php
                            // Use order_items as canonical source, fallback to legacy data
                            $orderItems = getOrderItems($order['id']);
                            $orderType = $order['order_type'] ?? 'template';
                            
                            if (!empty($orderItems)) {
                                $itemCount = count($orderItems);
                                $hasTemplates = false;
                                $hasTools = false;
                                
                                foreach ($orderItems as $item) {
                                    if ($item['product_type'] === 'template') $hasTemplates = true;
                                    if ($item['product_type'] === 'tool') $hasTools = true;
                                }
                                
                                // Order type badge
                                if ($hasTemplates && $hasTools) {
                                    echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800"><i class="bi bi-box-seam mr-1"></i>Mixed</span></div>';
                                } elseif ($hasTools) {
                                    echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800"><i class="bi bi-tools mr-1"></i>Tools</span></div>';
                                } else {
                                    echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800"><i class="bi bi-palette mr-1"></i>Template</span></div>';
                                }
                                
                                echo '<div class="text-sm text-gray-900">';
                                foreach (array_slice($orderItems, 0, 2) as $item) {
                                    $productType = $item['product_type'];
                                    $productName = $productType === 'template' ? $item['template_name'] : $item['tool_name'];
                                    $typeIcon = ($productType === 'template') ? '🎨' : '🔧';
                                    $qty = $item['quantity'] > 1 ? ' (x' . $item['quantity'] . ')' : '';
                                    echo $typeIcon . ' ' . htmlspecialchars($productName) . $qty . '<br/>';
                                }
                                if ($itemCount > 2) {
                                    echo '<span class="text-xs text-gray-500">+' . ($itemCount - 2) . ' more item' . ($itemCount - 2 > 1 ? 's' : '') . '</span>';
                                }
                                echo '</div>';
                            } elseif ($order['template_name']) {
                                // Legacy template-only order
                                echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800"><i class="bi bi-palette mr-1"></i>Template</span></div>';
                                echo '<div class="text-gray-900 text-sm">🎨 ' . htmlspecialchars($order['template_name']) . '</div>';
                            } elseif ($order['tool_name']) {
                                // Legacy tool-only order
                                echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800"><i class="bi bi-tools mr-1"></i>Tool</span></div>';
                                echo '<div class="text-gray-900 text-sm">🔧 ' . htmlspecialchars($order['tool_name']) . '</div>';
                            } else {
                                echo '<span class="text-gray-400">No items</span>';
                            }
                            ?>
                        </td>
                        <td class="py-3 px-2">
                            <?php
                            // Show final amount with full fallback chain for all order types
                            $totalAmount = $order['final_amount'] ?? $order['original_price'] ?? $order['template_price'] ?? $order['tool_price'] ?? 0;
                            
                            // Apply discount if affiliate code present
                            if (!empty($order['affiliate_code'])) {
                                echo '<div class="text-gray-900 font-bold">' . formatCurrency($totalAmount) . '</div>';
                                echo '<div class="text-xs text-green-600">Affiliate: ' . htmlspecialchars($order['affiliate_code']) . '</div>';
                            } else {
                                echo '<div class="text-gray-900 font-bold">' . formatCurrency($totalAmount) . '</div>';
                            }
                            ?>
                        </td>
                        <td class="py-3 px-2">
                            <?php if (!empty($order['payment_notes'])): ?>
                            <div class="text-xs text-gray-700 max-w-xs group relative">
                                <i class="bi bi-sticky text-primary-600"></i>
                                <span class="truncate inline-block align-middle" style="max-width: 200px;" title="<?php echo htmlspecialchars($order['payment_notes']); ?>">
                                    <?php echo htmlspecialchars(strlen($order['payment_notes']) > 50 ? substr($order['payment_notes'], 0, 50) . '...' : $order['payment_notes']); ?>
                                </span>
                                <div class="hidden group-hover:block absolute z-10 w-64 p-3 bg-gray-900 text-white text-xs rounded-lg shadow-xl -top-2 left-0 transform -translate-y-full">
                                    <div class="font-semibold mb-1">Payment Notes:</div>
                                    <?php echo nl2br(htmlspecialchars($order['payment_notes'])); ?>
                                    <div class="absolute bottom-0 left-4 transform translate-y-1/2 rotate-45 w-2 h-2 bg-gray-900"></div>
                                </div>
                            </div>
                            <?php else: ?>
                            <span class="text-xs text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-2">
                            <div class="flex flex-col gap-2">
                                <?php
                                $statusColors = [
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'paid' => 'bg-green-100 text-green-800',
                                    'cancelled' => 'bg-red-100 text-red-800'
                                ];
                                $statusIcons = [
                                    'pending' => 'hourglass-split',
                                    'paid' => 'check-circle',
                                    'cancelled' => 'x-circle'
                                ];
                                $color = $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-800';
                                $icon = $statusIcons[$order['status']] ?? 'circle';
                                ?>
                                <span class="inline-flex items-center px-3 py-1 <?php echo $color; ?> rounded-full text-xs font-semibold whitespace-nowrap">
                                    <i class="bi bi-<?php echo $icon; ?>"></i>
                                    <span class="hidden sm:inline sm:ml-1"><?php echo ucfirst($order['status']); ?></span>
                                </span>
                                
                                <?php
                                $orderDeliveries = getDeliveryStatus($order['id']);
                                $templateDeliveries = array_filter($orderDeliveries, function($d) { return $d['product_type'] === 'template'; });
                                $deliveredCount = count(array_filter($templateDeliveries, function($d) { return $d['delivery_status'] === 'delivered'; }));
                                $pendingCount = count(array_filter($templateDeliveries, function($d) { return $d['delivery_status'] !== 'delivered'; }));
                                
                                if (!empty($templateDeliveries)):
                                    if ($deliveredCount > 0 && $pendingCount === 0):
                                ?>
                                <span class="inline-flex items-center px-2 py-0.5 bg-green-100 text-green-800 rounded-full text-xs font-semibold whitespace-nowrap">
                                    <i class="bi bi-check-circle-fill mr-1"></i> Templates Delivered
                                </span>
                                <?php elseif ($pendingCount > 0): ?>
                                <span class="inline-flex items-center px-2 py-0.5 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold whitespace-nowrap">
                                    <i class="bi bi-clock mr-1"></i> <?php echo $pendingCount; ?> Template<?php echo $pendingCount > 1 ? 's' : ''; ?> Pending
                                </span>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="py-3 px-2 text-gray-700 text-sm">
                            <div class="font-medium"><?php echo date('D, M d, Y', strtotime($order['created_at'])); ?></div>
                            <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($order['created_at'])); ?></div>
                        </td>
                        <td class="py-3 px-2">
                            <a href="?view=<?php echo $order['id']; ?>" class="px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors text-sm inline-flex items-center gap-1">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Mobile Card View -->
        <div class="md:hidden">
            <?php if (empty($orders)): ?>
                <div class="text-center py-12">
                    <i class="bi bi-inbox text-6xl text-gray-300"></i>
                    <p class="text-gray-500 mt-4">No orders found</p>
                </div>
            <?php else: ?>
                <!-- Mobile Bulk Actions -->
                <div class="mb-4 p-3 bg-gray-100 rounded-lg">
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-3">
                        <input type="checkbox" id="selectAllMobile" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                        <span>Select All Orders</span>
                    </label>
                    <div class="flex gap-2">
                        <button type="button" class="flex-1 px-3 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors text-xs disabled:opacity-50 disabled:cursor-not-allowed" id="bulkMarkPaidBtnMobile" disabled>
                            <i class="bi bi-check-circle"></i> Mark Paid
                        </button>
                        <button type="button" class="flex-1 px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors text-xs disabled:opacity-50 disabled:cursor-not-allowed" id="bulkCancelBtnMobile" disabled>
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                    </div>
                </div>
                
                <div class="space-y-4">
                <?php foreach ($orders as $order): ?>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <?php if ($order['status'] === 'pending'): ?>
                            <input type="checkbox" name="order_ids[]" value="<?php echo $order['id']; ?>" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 order-checkbox">
                            <?php endif; ?>
                            <span class="font-bold text-gray-900">#<?php echo $order['id']; ?></span>
                        </div>
                        <?php
                        $statusColors = [
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'paid' => 'bg-green-100 text-green-800',
                            'cancelled' => 'bg-red-100 text-red-800'
                        ];
                        $statusIcons = [
                            'pending' => 'hourglass-split',
                            'paid' => 'check-circle',
                            'cancelled' => 'x-circle'
                        ];
                        $color = $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-800';
                        $icon = $statusIcons[$order['status']] ?? 'circle';
                        ?>
                        <span class="inline-flex items-center px-3 py-1 <?php echo $color; ?> rounded-full text-xs font-semibold">
                            <i class="bi bi-<?php echo $icon; ?> mr-1"></i><?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                    
                    <div class="space-y-2 mb-3">
                        <div>
                            <div class="text-sm font-semibold text-gray-700">Customer</div>
                            <div class="text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                            <div class="text-xs text-gray-500 mt-1">
                                <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($order['customer_email']); ?></div>
                                <div><i class="bi bi-phone"></i> <?php echo htmlspecialchars($order['customer_phone']); ?></div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="text-sm font-semibold text-gray-700">Products</div>
                            <?php
                            $orderItems = getOrderItems($order['id']);
                            $orderType = $order['order_type'] ?? 'template';
                            
                            if (!empty($orderItems)) {
                                $itemCount = count($orderItems);
                                $hasTemplates = false;
                                $hasTools = false;
                                
                                foreach ($orderItems as $item) {
                                    if ($item['product_type'] === 'template') $hasTemplates = true;
                                    if ($item['product_type'] === 'tool') $hasTools = true;
                                }
                                
                                if ($hasTemplates && $hasTools) {
                                    echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800"><i class="bi bi-box-seam mr-1"></i>Mixed</span></div>';
                                } elseif ($hasTools) {
                                    echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800"><i class="bi bi-tools mr-1"></i>Tools</span></div>';
                                } else {
                                    echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800"><i class="bi bi-palette mr-1"></i>Template</span></div>';
                                }
                                
                                echo '<div class="text-sm text-gray-900">';
                                foreach (array_slice($orderItems, 0, 3) as $item) {
                                    $productType = $item['product_type'];
                                    $productName = $productType === 'template' ? $item['template_name'] : $item['tool_name'];
                                    $typeIcon = ($productType === 'template') ? '🎨' : '🔧';
                                    $qty = $item['quantity'] > 1 ? ' (x' . $item['quantity'] . ')' : '';
                                    echo $typeIcon . ' ' . htmlspecialchars($productName) . $qty . '<br/>';
                                }
                                if ($itemCount > 3) {
                                    echo '<span class="text-xs text-gray-500">+' . ($itemCount - 3) . ' more</span>';
                                }
                                echo '</div>';
                            } elseif ($order['template_name']) {
                                echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800"><i class="bi bi-palette mr-1"></i>Template</span></div>';
                                echo '<div class="text-gray-900 text-sm">🎨 ' . htmlspecialchars($order['template_name']) . '</div>';
                            } elseif ($order['tool_name']) {
                                echo '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800"><i class="bi bi-tools mr-1"></i>Tool</span></div>';
                                echo '<div class="text-gray-900 text-sm">🔧 ' . htmlspecialchars($order['tool_name']) . '</div>';
                            } else {
                                echo '<span class="text-gray-400">No items</span>';
                            }
                            ?>
                        </div>
                        
                        <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                            <div>
                                <div class="text-sm font-semibold text-gray-700">Total</div>
                                <?php
                                $totalAmount = $order['final_amount'] ?? $order['original_price'] ?? $order['template_price'] ?? $order['tool_price'] ?? 0;
                                ?>
                                <div class="text-lg font-bold text-gray-900"><?php echo formatCurrency($totalAmount); ?></div>
                                <?php if (!empty($order['affiliate_code'])): ?>
                                <div class="text-xs text-green-600">Affiliate: <?php echo htmlspecialchars($order['affiliate_code']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-semibold text-gray-700">Date</div>
                                <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></div>
                                <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($order['created_at'])); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <a href="?view=<?php echo $order['id']; ?>" class="block w-full px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors text-sm text-center font-medium">
                        <i class="bi bi-eye mr-1"></i> View Details
                    </a>
                </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        </form>
    </div>
</div>

<script>
// Select All functionality (Desktop)
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    toggleBulkButtons();
});

// Select All functionality (Mobile)
document.getElementById('selectAllMobile')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    toggleBulkButtons();
});

// Individual checkbox change
document.querySelectorAll('.order-checkbox').forEach(cb => {
    cb.addEventListener('change', toggleBulkButtons);
});

function toggleBulkButtons() {
    const checked = document.querySelectorAll('.order-checkbox:checked');
    const markPaidBtn = document.getElementById('bulkMarkPaidBtn');
    const cancelBtn = document.getElementById('bulkCancelBtn');
    const markPaidBtnMobile = document.getElementById('bulkMarkPaidBtnMobile');
    const cancelBtnMobile = document.getElementById('bulkCancelBtnMobile');
    
    if (checked.length > 0) {
        markPaidBtn.disabled = false;
        cancelBtn.disabled = false;
        if (markPaidBtnMobile) markPaidBtnMobile.disabled = false;
        if (cancelBtnMobile) cancelBtnMobile.disabled = false;
    } else {
        markPaidBtn.disabled = true;
        cancelBtn.disabled = true;
        if (markPaidBtnMobile) markPaidBtnMobile.disabled = true;
        if (cancelBtnMobile) cancelBtnMobile.disabled = true;
    }
}

// Bulk Mark Paid (Desktop)
document.getElementById('bulkMarkPaidBtn').addEventListener('click', function() {
    const checked = document.querySelectorAll('.order-checkbox:checked');
    if (checked.length > 0 && confirm(`Mark ${checked.length} order(s) as paid?`)) {
        document.getElementById('bulkAction').value = 'bulk_mark_paid';
        document.getElementById('bulkActionsForm').submit();
    }
});

// Bulk Cancel (Desktop)
document.getElementById('bulkCancelBtn').addEventListener('click', function() {
    const checked = document.querySelectorAll('.order-checkbox:checked');
    if (checked.length > 0 && confirm(`Cancel ${checked.length} order(s)? This cannot be undone.`)) {
        document.getElementById('bulkAction').value = 'bulk_cancel';
        document.getElementById('bulkActionsForm').submit();
    }
});

// Bulk Mark Paid (Mobile)
document.getElementById('bulkMarkPaidBtnMobile')?.addEventListener('click', function() {
    const checked = document.querySelectorAll('.order-checkbox:checked');
    if (checked.length > 0 && confirm(`Mark ${checked.length} order(s) as paid?`)) {
        document.getElementById('bulkAction').value = 'bulk_mark_paid';
        document.getElementById('bulkActionsForm').submit();
    }
});

// Bulk Cancel (Mobile)
document.getElementById('bulkCancelBtnMobile')?.addEventListener('click', function() {
    const checked = document.querySelectorAll('.order-checkbox:checked');
    if (checked.length > 0 && confirm(`Cancel ${checked.length} order(s)? This cannot be undone.`)) {
        document.getElementById('bulkAction').value = 'bulk_cancel';
        document.getElementById('bulkActionsForm').submit();
    }
});
</script>

<?php if ($viewOrder): ?>
<div class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
            <h3 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-cart text-primary-600"></i> Order #<?php echo $viewOrder['id']; ?> Details
            </h3>
            <a href="/admin/orders.php" class="text-gray-400 hover:text-gray-600 text-2xl">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>
        <div class="p-6">
            <div class="grid md:grid-cols-2 gap-6 mb-6">
                <div>
                    <h6 class="text-gray-500 font-semibold mb-3 text-sm uppercase">Customer Information</h6>
                    <div class="space-y-2">
                        <p class="text-gray-700"><span class="font-semibold">Name:</span> <?php echo htmlspecialchars($viewOrder['customer_name']); ?></p>
                        <p class="text-gray-700"><span class="font-semibold">Email:</span> <?php echo htmlspecialchars($viewOrder['customer_email']); ?></p>
                        <p class="text-gray-700"><span class="font-semibold">Phone:</span> <?php echo htmlspecialchars($viewOrder['customer_phone']); ?></p>
                        <?php if (!empty($viewOrder['business_name'])): ?>
                        <p class="text-gray-700"><span class="font-semibold">Business:</span> <?php echo htmlspecialchars($viewOrder['business_name']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <h6 class="text-gray-500 font-semibold mb-3 text-sm uppercase">Order Information</h6>
                    <div class="space-y-2">
                        <p class="text-gray-700">
                            <span class="font-semibold">Order Type:</span> 
                            <?php
                            $orderType = $viewOrder['order_type'] ?? 'template';
                            $typeColors = ['template' => 'bg-blue-100 text-blue-800', 'tool' => 'bg-purple-100 text-purple-800', 'tools' => 'bg-purple-100 text-purple-800', 'mixed' => 'bg-green-100 text-green-800'];
                            $typeColor = $typeColors[$orderType] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="px-3 py-1 <?php echo $typeColor; ?> rounded-full text-xs font-semibold uppercase">
                                <?php echo htmlspecialchars($orderType); ?>
                            </span>
                        </p>
                        <p class="text-gray-700">
                            <span class="font-semibold">Items:</span> 
                            <span class="font-bold"><?php echo count($viewOrderItems); ?></span>
                        </p>
                        <p class="text-gray-700">
                            <span class="font-semibold">Total Amount:</span> 
                            <span class="text-lg font-bold text-green-600"><?php echo formatCurrency($viewOrder['final_amount'] ?? 0); ?></span>
                        </p>
                        
                        <p class="text-gray-700 flex items-center gap-2">
                            <span class="font-semibold">Status:</span>
                            <?php
                            $statusColors = ['pending' => 'bg-yellow-100 text-yellow-800', 'paid' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-red-100 text-red-800'];
                            $color = $statusColors[$viewOrder['status']] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="px-3 py-1 <?php echo $color; ?> rounded-full text-xs font-semibold">
                                <?php echo ucfirst($viewOrder['status']); ?>
                            </span>
                        </p>
                        <p class="text-gray-700"><span class="font-semibold">Date:</span> <?php echo date('M d, Y H:i', strtotime($viewOrder['created_at'])); ?></p>
                        <?php if (!empty($viewOrder['affiliate_code'])): ?>
                        <p class="text-gray-700"><span class="font-semibold">Affiliate Code:</span> <code class="bg-gray-100 px-2 py-1 rounded text-sm"><?php echo htmlspecialchars($viewOrder['affiliate_code']); ?></code></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($viewOrder['payment_notes'])): ?>
            <div class="mb-6">
                <h6 class="text-gray-500 font-semibold mb-2 text-sm uppercase flex items-center gap-2">
                    <i class="bi bi-sticky text-primary-600"></i> Payment Notes
                </h6>
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg">
                    <p class="text-sm text-gray-800 whitespace-pre-wrap"><?php echo htmlspecialchars($viewOrder['payment_notes']); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($viewOrderItems)): ?>
            <div class="mb-6">
                <h6 class="text-gray-500 font-semibold mb-3 text-sm uppercase">Order Items</h6>
                <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Type</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase">Qty</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Unit Price</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Discount</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($viewOrderItems as $item): 
                                $productType = $item['product_type'];
                                $productName = $productType === 'template' ? $item['template_name'] : $item['tool_name'];
                                $typeLabel = ($productType === 'template') ? '🎨 Template' : '🔧 Tool';
                                
                                $metadata = !empty($item['metadata_json']) ? json_decode($item['metadata_json'], true) : null;
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($productName); ?></div>
                                    <?php if ($metadata && !empty($metadata['category'])): ?>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($metadata['category']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700"><?php echo $typeLabel; ?></td>
                                <td class="px-4 py-3 text-center text-sm text-gray-900 font-medium"><?php echo $item['quantity']; ?></td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700"><?php echo formatCurrency($item['unit_price']); ?></td>
                                <td class="px-4 py-3 text-right text-sm text-green-600">
                                    <?php echo $item['discount_amount'] > 0 ? '-' . formatCurrency($item['discount_amount']) : '-'; ?>
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900"><?php echo formatCurrency($item['final_amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-gray-700">Subtotal:</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-gray-900"><?php echo formatCurrency($viewOrder['original_price'] ?? 0); ?></td>
                            </tr>
                            <?php if (!empty($viewOrder['affiliate_code']) && ($viewOrder['discount_amount'] ?? 0) > 0): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-right text-sm font-semibold text-gray-700">
                                    Affiliate Discount (20%):
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-green-600">-<?php echo formatCurrency($viewOrder['discount_amount']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="border-t-2 border-gray-300">
                                <td colspan="5" class="px-4 py-3 text-right text-base font-bold text-gray-900">TOTAL:</td>
                                <td class="px-4 py-3 text-right text-lg font-extrabold text-primary-600"><?php echo formatCurrency($viewOrder['final_amount'] ?? 0); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($viewOrder['custom_fields'])): ?>
            <div class="mb-6">
                <h6 class="text-gray-500 font-semibold mb-2 text-sm uppercase">Custom Fields</h6>
                <div class="bg-gray-50 border border-gray-200 p-4 rounded-lg">
                    <pre class="text-sm text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($viewOrder['custom_fields']); ?></pre>
                </div>
            </div>
            <?php endif; ?>
            
            <?php 
            // Only show Domain Assignment section for non-pending orders with templates
            if ($viewOrder['status'] !== 'pending'): 
                $templateItems = [];
                if (!empty($viewOrderItems)) {
                    foreach ($viewOrderItems as $item) {
                        if ($item['product_type'] === 'template') {
                            $templateItems[] = $item;
                        }
                    }
                } elseif (!empty($viewOrder['template_id'])) {
                    $templateItems[] = [
                        'id' => null,
                        'product_id' => $viewOrder['template_id'],
                        'template_name' => $viewOrder['template_name'],
                        'metadata_json' => null
                    ];
                }
                
                if (!empty($templateItems)):
            ?>
            <div class="mb-6">
                <h6 class="text-gray-500 font-semibold mb-3 text-sm uppercase">Domain Assignment & Notes</h6>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_order_domains">
                    <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                    
                    <?php foreach ($templateItems as $idx => $item):
                        $metadata = [];
                        if (!empty($item['metadata_json'])) {
                            $metadata = json_decode($item['metadata_json'], true) ?: [];
                        }
                        $assignedDomainId = $metadata['domain_id'] ?? null;
                        $assignedDomainName = null;
                        
                        if ($assignedDomainId) {
                            $domainStmt = $db->prepare("SELECT domain_name, status FROM domains WHERE id = ?");
                            $domainStmt->execute([$assignedDomainId]);
                            $domainRow = $domainStmt->fetch(PDO::FETCH_ASSOC);
                            if ($domainRow) {
                                $assignedDomainName = $domainRow['domain_name'];
                            }
                        } elseif ($idx === 0 && !empty($viewOrder['domain_name'])) {
                            $assignedDomainName = $viewOrder['domain_name'];
                        }
                        
                        $templateId = $item['product_id'];
                        $availableDomainsForTemplate = getAvailableDomains($templateId);
                        
                        // If a domain is already assigned, make sure it's in the list even if it's "in_use"
                        $domainInList = false;
                        if ($assignedDomainId) {
                            foreach ($availableDomainsForTemplate as $d) {
                                if ($d['id'] == $assignedDomainId) {
                                    $domainInList = true;
                                    break;
                                }
                            }
                            // If assigned domain is not in the available list, add it
                            if (!$domainInList && $assignedDomainName) {
                                $availableDomainsForTemplate[] = [
                                    'id' => $assignedDomainId,
                                    'domain_name' => $assignedDomainName,
                                    'status' => 'in_use'
                                ];
                            }
                        }
                    ?>
                    
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="bi bi-palette text-primary-600"></i>
                            <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($item['template_name']); ?></span>
                            <?php if ($assignedDomainName): ?>
                            <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">
                                <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($assignedDomainName); ?>
                            </span>
                            <?php else: ?>
                            <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full">
                                <i class="bi bi-exclamation-circle"></i> Not assigned
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($availableDomainsForTemplate)): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="bi bi-globe mr-1"></i> 
                                <?php echo $assignedDomainName ? 'Change Domain (Optional)' : 'Assign Domain (Optional)'; ?>
                            </label>
                            <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="domain_id_<?php echo $item['id']; ?>">
                                <option value="">-- Leave unchanged / No domain --</option>
                                <?php foreach ($availableDomainsForTemplate as $domain): ?>
                                <option value="<?php echo $domain['id']; ?>" <?php echo ($assignedDomainId == $domain['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($domain['domain_name']); ?>
                                    <?php if (isset($domain['status']) && $domain['status'] === 'in_use' && $domain['id'] == $assignedDomainId): ?>
                                    (Current)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php else: ?>
                        <div class="bg-orange-50 border-l-4 border-orange-500 p-3 rounded text-sm">
                            <div class="flex items-center gap-2 text-orange-800">
                                <i class="bi bi-info-circle"></i>
                                <span>No available domains. <a href="/admin/domains.php" class="underline font-semibold">Add domains</a></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php endforeach; ?>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-lg transition-colors shadow-lg">
                            <i class="bi bi-check-circle mr-2"></i> Update All Changes
                        </button>
                    </div>
                </form>
            </div>
            
            <?php
            require_once __DIR__ . '/../includes/delivery.php';
            $orderDeliveries = getDeliveryStatus($viewOrder['id']);
            $templateDeliveries = array_filter($orderDeliveries, function($d) { return $d['product_type'] === 'template'; });
            $toolDeliveriesAll = array_filter($orderDeliveries, function($d) { return $d['product_type'] === 'tool'; });
            
            // Phase 5.1: Mixed Order Delivery Summary - Show clear split for orders with both tools and templates
            if (!empty($templateDeliveries) && !empty($toolDeliveriesAll)):
                // Count delivery statuses
                $toolsDeliveredCount = count(array_filter($toolDeliveriesAll, function($d) { 
                    return in_array($d['delivery_status'], ['delivered', 'ready', 'sent']); 
                }));
                $toolsPendingCount = count($toolDeliveriesAll) - $toolsDeliveredCount;
                
                $templatesDeliveredCount = count(array_filter($templateDeliveries, function($d) { 
                    return $d['delivery_status'] === 'delivered'; 
                }));
                $templatesPendingCount = count($templateDeliveries) - $templatesDeliveredCount;
                
                $totalItems = count($orderDeliveries);
                $totalDelivered = $toolsDeliveredCount + $templatesDeliveredCount;
                $deliveryPercentage = $totalItems > 0 ? round(($totalDelivered / $totalItems) * 100) : 0;
                
                $allDelivered = ($totalDelivered === $totalItems);
            ?>
            <div class="mb-6">
                <h6 class="text-gray-500 font-semibold mb-3 text-sm uppercase flex items-center gap-2">
                    <i class="bi bi-box-seam text-primary-600"></i> Mixed Order Delivery Status
                </h6>
                
                <div class="bg-gradient-to-r <?php echo $allDelivered ? 'from-green-50 to-emerald-50 border-green-200' : 'from-blue-50 to-indigo-50 border-blue-200'; ?> border-2 rounded-xl p-5 shadow-sm">
                    <!-- Overall Progress -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center h-12 w-12 rounded-full <?php echo $allDelivered ? 'bg-green-100' : 'bg-blue-100'; ?>">
                                <?php if ($allDelivered): ?>
                                <i class="bi bi-check-circle-fill text-green-600 text-xl"></i>
                                <?php else: ?>
                                <i class="bi bi-hourglass-split text-blue-600 text-xl"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-900 text-lg">
                                    <?php echo $allDelivered ? 'Order Fully Delivered' : 'Partial Delivery'; ?>
                                </h4>
                                <p class="text-sm text-gray-600">
                                    <?php echo $totalDelivered; ?> of <?php echo $totalItems; ?> items delivered
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-3xl font-extrabold <?php echo $allDelivered ? 'text-green-600' : 'text-blue-600'; ?>">
                                <?php echo $deliveryPercentage; ?>%
                            </div>
                            <div class="text-xs text-gray-500">Complete</div>
                        </div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="bg-gray-200 rounded-full h-2.5 mb-5">
                        <div class="<?php echo $allDelivered ? 'bg-green-500' : 'bg-blue-500'; ?> h-2.5 rounded-full transition-all duration-500" style="width: <?php echo $deliveryPercentage; ?>%"></div>
                    </div>
                    
                    <!-- Delivery Split -->
                    <div class="grid md:grid-cols-2 gap-4">
                        <!-- Tools (Immediate) -->
                        <div class="bg-white rounded-lg p-4 border border-gray-200">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-lg">🔧</span>
                                <h5 class="font-semibold text-gray-800">Immediate Delivery (Tools)</h5>
                            </div>
                            
                            <?php if (count($toolDeliveriesAll) > 0): ?>
                            <div class="space-y-2">
                                <?php foreach ($toolDeliveriesAll as $td): 
                                    $isToolDelivered = in_array($td['delivery_status'], ['delivered', 'ready', 'sent']);
                                ?>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-700"><?php echo htmlspecialchars($td['product_name']); ?></span>
                                    <?php if ($isToolDelivered): ?>
                                    <span class="bg-green-100 text-green-700 text-xs px-2 py-1 rounded-full font-semibold">
                                        <i class="bi bi-check-circle-fill mr-1"></i>Delivered
                                    </span>
                                    <?php else: ?>
                                    <span class="bg-yellow-100 text-yellow-700 text-xs px-2 py-1 rounded-full font-semibold">
                                        <i class="bi bi-clock mr-1"></i>Pending
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-3 pt-3 border-t border-gray-100 text-xs text-gray-500">
                                <i class="bi bi-info-circle mr-1"></i>
                                Tools are delivered automatically via email upon payment
                            </div>
                            <?php else: ?>
                            <p class="text-gray-500 text-sm">No tools in this order</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Templates (Pending) -->
                        <div class="bg-white rounded-lg p-4 border border-gray-200">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-lg">🎨</span>
                                <h5 class="font-semibold text-gray-800">Pending Delivery (Templates)</h5>
                            </div>
                            
                            <?php if (count($templateDeliveries) > 0): ?>
                            <div class="space-y-2">
                                <?php foreach ($templateDeliveries as $td): 
                                    $isTemplateDelivered = $td['delivery_status'] === 'delivered';
                                ?>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-700"><?php echo htmlspecialchars($td['product_name']); ?></span>
                                    <?php if ($isTemplateDelivered): ?>
                                    <span class="bg-green-100 text-green-700 text-xs px-2 py-1 rounded-full font-semibold">
                                        <i class="bi bi-check-circle-fill mr-1"></i>Delivered
                                    </span>
                                    <?php elseif (!empty($td['hosted_domain'])): ?>
                                    <span class="bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded-full font-semibold">
                                        <i class="bi bi-gear mr-1"></i>In Progress
                                    </span>
                                    <?php else: ?>
                                    <span class="bg-yellow-100 text-yellow-700 text-xs px-2 py-1 rounded-full font-semibold">
                                        <i class="bi bi-clock mr-1"></i>Awaiting Setup
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-3 pt-3 border-t border-gray-100 text-xs text-gray-500">
                                <i class="bi bi-info-circle mr-1"></i>
                                Templates require domain assignment and credentials setup
                            </div>
                            <?php else: ?>
                            <p class="text-gray-500 text-sm">No templates in this order</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Actions Needed Alert -->
                    <?php if ($templatesPendingCount > 0): ?>
                    <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <i class="bi bi-exclamation-triangle text-yellow-600 text-xl"></i>
                            <div>
                                <h5 class="font-semibold text-yellow-800 mb-1">
                                    <?php echo $templatesPendingCount; ?> Template(s) Need Action
                                </h5>
                                <p class="text-sm text-yellow-700 mb-2">
                                    Scroll down to assign domain and credentials for each pending template.
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($templateDeliveries as $td): 
                                        if ($td['delivery_status'] !== 'delivered'):
                                    ?>
                                    <a href="#template-delivery-<?php echo $td['id']; ?>" class="inline-flex items-center px-3 py-1.5 bg-yellow-200 hover:bg-yellow-300 text-yellow-800 text-xs font-semibold rounded-full transition-colors">
                                        <i class="bi bi-arrow-right mr-1"></i>
                                        <?php echo htmlspecialchars($td['product_name']); ?>
                                    </a>
                                    <?php 
                                        endif;
                                    endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php 
            // Phase 5.3: Batch Template Assignment - Only show if multiple pending templates
            $pendingTemplateDeliveries = array_filter($templateDeliveries, function($d) { 
                return $d['delivery_status'] !== 'delivered'; 
            });
            
            if (count($pendingTemplateDeliveries) > 1):
            ?>
            <div class="mb-6" id="batch-template-assignment">
                <div class="flex items-center justify-between mb-3">
                    <h6 class="text-gray-500 font-semibold text-sm uppercase flex items-center gap-2">
                        <i class="bi bi-lightning-charge text-purple-600"></i> Quick Batch Assignment
                    </h6>
                    <button type="button" onclick="document.getElementById('batch-form-section').classList.toggle('hidden')" class="text-sm text-purple-600 hover:text-purple-700 font-semibold flex items-center gap-1">
                        <i class="bi bi-chevron-down" id="batch-toggle-icon"></i> Toggle Form
                    </button>
                </div>
                
                <div id="batch-form-section" class="bg-gradient-to-r from-purple-50 to-indigo-50 border-2 border-purple-200 rounded-xl p-5 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex items-center justify-center h-10 w-10 rounded-full bg-purple-100">
                            <i class="bi bi-lightning-charge-fill text-purple-600 text-lg"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-900">Deliver <?php echo count($pendingTemplateDeliveries); ?> Templates at Once</h4>
                            <p class="text-sm text-gray-600">Fill in credentials for all pending templates in one go</p>
                        </div>
                    </div>
                    
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="batch_template_credentials">
                        <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                        
                        <?php foreach ($pendingTemplateDeliveries as $pIdx => $pendingDelivery): ?>
                        <div class="bg-white rounded-lg p-4 border border-purple-100">
                            <input type="hidden" name="deliveries[<?php echo $pIdx; ?>][delivery_id]" value="<?php echo $pendingDelivery['id']; ?>">
                            
                            <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100">
                                <span class="text-lg">🎨</span>
                                <span class="font-bold text-gray-900"><?php echo htmlspecialchars($pendingDelivery['product_name']); ?></span>
                                <span class="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded-full">Template #<?php echo $pIdx + 1; ?></span>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                                        <i class="bi bi-globe mr-1"></i> Domain *
                                    </label>
                                    <input type="text" name="deliveries[<?php echo $pIdx; ?>][hosted_domain]" placeholder="example.com" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                                        <i class="bi bi-link-45deg mr-1"></i> Website URL
                                    </label>
                                    <input type="url" name="deliveries[<?php echo $pIdx; ?>][hosted_url]" placeholder="https://example.com" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                                        <i class="bi bi-server mr-1"></i> Hosting Type
                                    </label>
                                    <select name="deliveries[<?php echo $pIdx; ?>][hosting_provider]" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                        <option value="wordpress">WordPress</option>
                                        <option value="cpanel">cPanel</option>
                                        <option value="custom" selected>Custom Admin</option>
                                        <option value="static">Static Site</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                                        <i class="bi bi-link mr-1"></i> Login URL
                                    </label>
                                    <input type="url" name="deliveries[<?php echo $pIdx; ?>][login_url]" placeholder="https://example.com/admin" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                                        <i class="bi bi-person mr-1"></i> Username *
                                    </label>
                                    <input type="text" name="deliveries[<?php echo $pIdx; ?>][username]" placeholder="admin" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                                        <i class="bi bi-key mr-1"></i> Password *
                                    </label>
                                    <input type="password" name="deliveries[<?php echo $pIdx; ?>][password]" placeholder="••••••••" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" required>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-center gap-3">
                            <input type="checkbox" name="send_emails" value="1" id="batch_send_emails" checked class="w-5 h-5 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                            <label for="batch_send_emails" class="text-sm text-blue-800">
                                <strong>Send credentials to customer via email</strong>
                                <br><span class="text-blue-600">Each template will be delivered with its own email</span>
                            </label>
                        </div>
                        
                        <div class="flex gap-3">
                            <button type="submit" class="flex-1 px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-bold rounded-lg transition-all shadow-lg flex items-center justify-center gap-2">
                                <i class="bi bi-lightning-charge-fill"></i>
                                Deliver All <?php echo count($pendingTemplateDeliveries); ?> Templates
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($templateDeliveries)):
            ?>
            <div class="mb-6">
                <h6 class="text-gray-500 font-semibold mb-3 text-sm uppercase flex items-center gap-2">
                    <i class="bi bi-key text-primary-600"></i> Template Credentials & Delivery
                </h6>
                
                <?php foreach ($templateDeliveries as $delivery): 
                    $progress = getTemplateDeliveryProgress($delivery['id']);
                    $isComplete = $progress['is_complete'] ?? false;
                    $decryptedPassword = '';
                    if (!empty($delivery['template_admin_password'])) {
                        $decryptedPassword = decryptCredential($delivery['template_admin_password']);
                    }
                ?>
                <div id="template-delivery-<?php echo $delivery['id']; ?>" class="bg-white border-2 <?php echo $isComplete ? 'border-green-200' : 'border-yellow-200'; ?> rounded-xl p-5 mb-4 shadow-sm scroll-mt-20">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <span class="text-lg">🎨</span>
                            <span class="font-bold text-gray-900"><?php echo htmlspecialchars($delivery['product_name']); ?></span>
                            <?php if ($isComplete): ?>
                            <span class="bg-green-100 text-green-800 text-xs font-semibold px-3 py-1 rounded-full">
                                <i class="bi bi-check-circle-fill mr-1"></i> Delivered
                            </span>
                            <?php else: ?>
                            <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-3 py-1 rounded-full">
                                <i class="bi bi-clock mr-1"></i> Pending Delivery
                            </span>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs text-gray-500">Delivery #<?php echo $delivery['id']; ?></span>
                    </div>
                    
                    <div class="mb-4 bg-gray-50 rounded-lg p-4">
                        <h5 class="text-xs font-bold text-gray-700 mb-3 uppercase tracking-wide">📋 Delivery Checklist</h5>
                        <div class="space-y-2">
                            <?php foreach ($progress['steps'] ?? [] as $step): ?>
                            <div class="flex items-center gap-3">
                                <div class="flex-shrink-0">
                                    <?php if ($step['status']): ?>
                                    <div class="flex items-center justify-center h-5 w-5 rounded-full bg-green-100">
                                        <i class="bi bi-check text-green-600 text-sm"></i>
                                    </div>
                                    <?php else: ?>
                                    <div class="flex items-center justify-center h-5 w-5 rounded-full bg-gray-200">
                                        <i class="bi bi-circle text-gray-400 text-xs"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <span class="text-sm <?php echo $step['status'] ? 'text-green-700 font-semibold' : 'text-gray-500'; ?>">
                                    <?php echo htmlspecialchars($step['label']); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                            <div class="bg-gradient-to-r from-primary-500 to-green-500 h-full transition-all duration-500" style="width: <?php echo $progress['percentage'] ?? 0; ?>%"></div>
                        </div>
                        <div class="text-xs text-gray-600 mt-2 text-center font-semibold">
                            <?php echo $progress['completed'] ?? 0; ?>/<?php echo $progress['total'] ?? 5; ?> Steps Complete
                        </div>
                    </div>
                    
                    <?php if ($isComplete): ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <h4 class="font-semibold text-green-800 mb-3 flex items-center gap-2">
                            <i class="bi bi-check-circle-fill"></i> Delivered Credentials
                        </h4>
                        <div class="grid md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">Domain:</span>
                                <span class="font-semibold text-gray-900 ml-2"><?php echo htmlspecialchars($delivery['hosted_domain'] ?? 'Not set'); ?></span>
                            </div>
                            <div>
                                <span class="text-gray-600">Website URL:</span>
                                <a href="<?php echo htmlspecialchars($delivery['hosted_url'] ?? '#'); ?>" target="_blank" class="font-semibold text-primary-600 ml-2 hover:underline"><?php echo htmlspecialchars($delivery['hosted_url'] ?? 'Not set'); ?></a>
                            </div>
                            <div>
                                <span class="text-gray-600">Username:</span>
                                <code class="font-mono bg-gray-100 px-2 py-1 rounded ml-2"><?php echo htmlspecialchars($delivery['template_admin_username'] ?? 'Not set'); ?></code>
                            </div>
                            <div>
                                <span class="text-gray-600">Password:</span>
                                <code class="font-mono bg-gray-100 px-2 py-1 rounded ml-2"><?php echo htmlspecialchars($decryptedPassword ? maskPassword($decryptedPassword) : 'Not set'); ?></code>
                            </div>
                            <div>
                                <span class="text-gray-600">Login URL:</span>
                                <a href="<?php echo htmlspecialchars($delivery['template_login_url'] ?? '#'); ?>" target="_blank" class="font-semibold text-primary-600 ml-2 hover:underline"><?php echo htmlspecialchars($delivery['template_login_url'] ?? 'Not set'); ?></a>
                            </div>
                            <div>
                                <span class="text-gray-600">Delivered:</span>
                                <span class="font-semibold text-gray-900 ml-2"><?php echo $delivery['credentials_sent_at'] ? date('M d, Y H:i', strtotime($delivery['credentials_sent_at'])) : 'Not yet'; ?></span>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-4 border-t border-green-200 flex flex-wrap gap-3">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="resend_template_email">
                                <input type="hidden" name="delivery_id" value="<?php echo $delivery['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                    <i class="bi bi-envelope mr-1"></i> Resend Email
                                </button>
                            </form>
                            <button type="button" onclick="document.getElementById('update-creds-<?php echo $delivery['id']; ?>').classList.toggle('hidden')" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                <i class="bi bi-pencil mr-1"></i> Update Credentials
                            </button>
                        </div>
                        
                        <div id="update-creds-<?php echo $delivery['id']; ?>" class="hidden mt-4 pt-4 border-t border-green-200">
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="save_template_credentials">
                                <input type="hidden" name="delivery_id" value="<?php echo $delivery['id']; ?>">
                                <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <h5 class="font-semibold text-blue-800 mb-3 flex items-center gap-2">
                                        <i class="bi bi-pencil-square"></i> Update & Re-deliver Credentials
                                    </h5>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Domain Name</label>
                                            <input type="text" name="hosted_domain" value="<?php echo htmlspecialchars($delivery['hosted_domain'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Website URL</label>
                                            <input type="url" name="hosted_url" value="<?php echo htmlspecialchars($delivery['hosted_url'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Login URL</label>
                                            <input type="url" name="template_login_url" value="<?php echo htmlspecialchars($delivery['template_login_url'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Hosting Type</label>
                                            <select name="hosting_provider" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                                <option value="wordpress" <?php echo ($delivery['hosting_provider'] ?? '') === 'wordpress' ? 'selected' : ''; ?>>WordPress</option>
                                                <option value="cpanel" <?php echo ($delivery['hosting_provider'] ?? '') === 'cpanel' ? 'selected' : ''; ?>>cPanel</option>
                                                <option value="custom" <?php echo ($delivery['hosting_provider'] ?? '') === 'custom' || empty($delivery['hosting_provider']) ? 'selected' : ''; ?>>Custom Admin</option>
                                                <option value="static" <?php echo ($delivery['hosting_provider'] ?? '') === 'static' ? 'selected' : ''; ?>>Static Site</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Admin Username</label>
                                            <input type="text" name="template_admin_username" value="<?php echo htmlspecialchars($delivery['template_admin_username'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">New Password (leave blank to keep)</label>
                                            <input type="password" name="template_admin_password" placeholder="Leave blank to keep current" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Special Instructions</label>
                                        <textarea name="admin_notes" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"><?php echo htmlspecialchars($delivery['admin_notes'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-3 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                    <input type="checkbox" name="send_email" value="1" id="resend_after_update_<?php echo $delivery['id']; ?>" checked class="w-5 h-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                                    <label for="resend_after_update_<?php echo $delivery['id']; ?>" class="text-sm text-yellow-800">
                                        <strong>Send updated credentials to customer</strong>
                                    </label>
                                </div>
                                
                                <div class="flex gap-3">
                                    <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors">
                                        <i class="bi bi-save mr-1"></i> Update & Send
                                    </button>
                                    <button type="button" onclick="document.getElementById('update-creds-<?php echo $delivery['id']; ?>').classList.add('hidden')" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold rounded-lg transition-colors">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php else: 
                        $hasExistingPassword = !empty($delivery['template_admin_password']);
                    ?>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="save_template_credentials">
                        <input type="hidden" name="delivery_id" value="<?php echo $delivery['id']; ?>">
                        <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="bi bi-globe mr-1"></i> Domain Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="hosted_domain" value="<?php echo htmlspecialchars($delivery['hosted_domain'] ?? ''); ?>" placeholder="example.com" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" required>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="bi bi-link-45deg mr-1"></i> Website URL
                                </label>
                                <input type="url" name="hosted_url" value="<?php echo htmlspecialchars($delivery['hosted_url'] ?? ''); ?>" placeholder="https://example.com" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                            </div>
                        </div>
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <h4 class="font-semibold text-yellow-800 mb-3 flex items-center gap-2">
                                <i class="bi bi-key"></i> Login Credentials
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Hosting Type</label>
                                    <select name="hosting_provider" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                                        <option value="wordpress" <?php echo ($delivery['hosting_provider'] ?? '') === 'wordpress' ? 'selected' : ''; ?>>WordPress</option>
                                        <option value="cpanel" <?php echo ($delivery['hosting_provider'] ?? '') === 'cpanel' ? 'selected' : ''; ?>>cPanel</option>
                                        <option value="custom" <?php echo ($delivery['hosting_provider'] ?? '') === 'custom' || empty($delivery['hosting_provider']) ? 'selected' : ''; ?>>Custom Admin</option>
                                        <option value="static" <?php echo ($delivery['hosting_provider'] ?? '') === 'static' ? 'selected' : ''; ?>>Static Site (No Login)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Login URL</label>
                                    <input type="url" name="template_login_url" value="<?php echo htmlspecialchars($delivery['template_login_url'] ?? ''); ?>" placeholder="https://example.com/admin" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Admin Username</label>
                                    <input type="text" name="template_admin_username" value="<?php echo htmlspecialchars($delivery['template_admin_username'] ?? ''); ?>" placeholder="admin" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Admin Password</label>
                                    <input type="password" name="template_admin_password" placeholder="<?php echo $hasExistingPassword ? '••••••••• (Leave blank)' : 'Enter password'; ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all text-sm sm:text-base">
                                    <p class="text-xs text-gray-500 mt-1"><?php echo $hasExistingPassword ? 'Leave blank to keep current' : 'Encrypted before storage'; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="bi bi-chat-text mr-1"></i> Special Instructions for Customer
                            </label>
                            <textarea name="admin_notes" rows="3" placeholder="e.g., First login may take a few minutes..." class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all"><?php echo htmlspecialchars($delivery['admin_notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <input type="checkbox" name="send_email" value="1" id="send_email_<?php echo $delivery['id']; ?>" checked class="w-5 h-5 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                            <label for="send_email_<?php echo $delivery['id']; ?>" class="text-sm text-blue-800">
                                <strong>Send email to customer immediately</strong>
                                <br><span class="text-blue-600">Customer will receive domain and login credentials via email</span>
                            </label>
                        </div>
                        
                        <div class="flex gap-3">
                            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-bold rounded-lg transition-all shadow-lg">
                                <i class="bi bi-send mr-2"></i> Save & Deliver Template
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php 
            endif;
            ?>
                <?php 
                endif;
                endif;
            ?>
            
            <?php
            require_once __DIR__ . '/../includes/tool_files.php';
            $toolDeliveries = array_filter($orderDeliveries ?? [], function($d) { return $d['product_type'] === 'tool'; });
            
            if (!empty($toolDeliveries)):
            ?>
            <div class="mb-6">
                <h6 class="text-gray-500 font-semibold mb-3 text-sm uppercase flex items-center gap-2">
                    <i class="bi bi-tools text-primary-600"></i> Tool Downloads & Delivery
                </h6>
                
                <?php foreach ($toolDeliveries as $delivery): 
                    $tokens = getDownloadTokens($viewOrder['id'], $delivery['product_id']);
                    $isDelivered = $delivery['delivery_status'] === 'delivered';
                    $retryCount = $delivery['retry_count'] ?? 0;
                ?>
                <div class="bg-white border-2 <?php echo $isDelivered ? 'border-green-200' : 'border-yellow-200'; ?> rounded-xl p-5 mb-4 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <span class="text-lg">🔧</span>
                            <span class="font-bold text-gray-900"><?php echo htmlspecialchars($delivery['product_name']); ?></span>
                            <?php if ($isDelivered): ?>
                            <span class="bg-green-100 text-green-800 text-xs font-semibold px-3 py-1 rounded-full">
                                <i class="bi bi-check-circle-fill mr-1"></i> Delivered
                            </span>
                            <?php elseif ($delivery['delivery_status'] === 'pending_retry'): ?>
                            <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-3 py-1 rounded-full">
                                <i class="bi bi-arrow-repeat mr-1"></i> Retry #<?php echo $retryCount; ?> Pending
                            </span>
                            <?php elseif ($delivery['delivery_status'] === 'failed'): ?>
                            <span class="bg-red-100 text-red-800 text-xs font-semibold px-3 py-1 rounded-full">
                                <i class="bi bi-x-circle-fill mr-1"></i> Failed (<?php echo $retryCount; ?> retries)
                            </span>
                            <?php else: ?>
                            <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-3 py-1 rounded-full">
                                <i class="bi bi-clock mr-1"></i> Pending
                            </span>
                            <?php endif; ?>
                        </div>
                        <span class="text-xs text-gray-500">Delivery #<?php echo $delivery['id']; ?></span>
                    </div>
                    
                    <?php if (!empty($tokens)): ?>
                    <div class="mb-4 bg-gray-50 rounded-lg p-4">
                        <h5 class="text-xs font-bold text-gray-700 mb-3 uppercase tracking-wide">📥 Download Links</h5>
                        <div class="space-y-3">
                            <?php foreach ($tokens as $token): 
                                $isExpired = strtotime($token['expires_at']) < time();
                                $expiresIn = strtotime($token['expires_at']) - time();
                                $daysLeft = ceil($expiresIn / 86400);
                                $downloadsUsed = $token['download_count'] ?? 0;
                                $maxDownloads = $token['max_downloads'] ?? 10;
                            ?>
                            <div class="flex items-center justify-between bg-white border border-gray-200 rounded-lg p-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i class="bi bi-file-earmark-zip text-primary-600"></i>
                                        <span class="font-medium text-gray-900 text-sm"><?php echo htmlspecialchars($token['file_name']); ?></span>
                                        <span class="text-xs text-gray-500">(<?php echo formatFileSize($token['file_size']); ?>)</span>
                                    </div>
                                    <div class="flex items-center gap-4 text-xs text-gray-500">
                                        <span>
                                            <i class="bi bi-download mr-1"></i>
                                            <?php echo $downloadsUsed; ?>/<?php echo $maxDownloads; ?> downloads
                                        </span>
                                        <?php if ($isExpired): ?>
                                        <span class="text-red-600 font-semibold">
                                            <i class="bi bi-exclamation-circle mr-1"></i> Expired
                                        </span>
                                        <?php elseif ($daysLeft <= 3): ?>
                                        <span class="text-yellow-600 font-semibold">
                                            <i class="bi bi-clock mr-1"></i> Expires in <?php echo $daysLeft; ?> day(s)
                                        </span>
                                        <?php else: ?>
                                        <span class="text-green-600">
                                            <i class="bi bi-clock mr-1"></i> Valid for <?php echo $daysLeft; ?> days
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <?php if ($isExpired || $downloadsUsed >= $maxDownloads): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="regenerate_download_link">
                                        <input type="hidden" name="token_id" value="<?php echo $token['id']; ?>">
                                        <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                        <button type="submit" class="px-3 py-2 bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold rounded-lg transition-colors" title="Generate new link">
                                            <i class="bi bi-arrow-repeat"></i> Regenerate
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <button type="button" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition-colors copy-link-btn" data-url="<?php echo htmlspecialchars(SITE_URL . '/download.php?token=' . $token['token']); ?>" title="Copy download link">
                                        <i class="bi bi-clipboard"></i> Copy
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex gap-3">
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="resend_tool_email">
                            <input type="hidden" name="delivery_id" value="<?php echo $delivery['id']; ?>">
                            <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                            <button type="submit" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                <i class="bi bi-envelope mr-1"></i> Resend Download Email
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <script>
            document.querySelectorAll('.copy-link-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var url = this.getAttribute('data-url');
                    navigator.clipboard.writeText(url).then(function() {
                        btn.innerHTML = '<i class="bi bi-check"></i> Copied!';
                        setTimeout(function() {
                            btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy';
                        }, 2000);
                    });
                });
            });
            </script>
            <?php endif; ?>
            
            <?php 
            // Phase 5.2: Delivery Timeline - Show for paid orders
            if ($viewOrder['status'] === 'paid' && !empty($orderDeliveries)):
                $timeline = getDeliveryTimeline($viewOrder['id']);
                $deliveryStats = getOrderDeliveryStats($viewOrder['id']);
            ?>
            <div class="mb-6">
                <h6 class="text-gray-500 font-semibold mb-3 text-sm uppercase flex items-center gap-2">
                    <i class="bi bi-clock-history text-primary-600"></i> Delivery Timeline
                </h6>
                
                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                    <!-- Delivery Stats Summary -->
                    <div class="flex flex-wrap gap-4 mb-5 pb-5 border-b border-gray-100">
                        <div class="bg-gray-50 rounded-lg px-4 py-2 text-center min-w-[100px]">
                            <div class="text-2xl font-bold text-gray-900"><?php echo $deliveryStats['total_items']; ?></div>
                            <div class="text-xs text-gray-500">Total Items</div>
                        </div>
                        <div class="bg-green-50 rounded-lg px-4 py-2 text-center min-w-[100px]">
                            <div class="text-2xl font-bold text-green-600"><?php echo $deliveryStats['delivered_items']; ?></div>
                            <div class="text-xs text-gray-500">Delivered</div>
                        </div>
                        <div class="bg-yellow-50 rounded-lg px-4 py-2 text-center min-w-[100px]">
                            <div class="text-2xl font-bold text-yellow-600"><?php echo $deliveryStats['pending_items']; ?></div>
                            <div class="text-xs text-gray-500">Pending</div>
                        </div>
                        <div class="<?php echo $deliveryStats['is_fully_delivered'] ? 'bg-green-100' : 'bg-blue-50'; ?> rounded-lg px-4 py-2 text-center min-w-[120px]">
                            <div class="text-2xl font-bold <?php echo $deliveryStats['is_fully_delivered'] ? 'text-green-600' : 'text-blue-600'; ?>">
                                <?php echo $deliveryStats['delivery_percentage']; ?>%
                            </div>
                            <div class="text-xs text-gray-500">Complete</div>
                        </div>
                    </div>
                    
                    <!-- Timeline -->
                    <div class="relative">
                        <?php foreach ($timeline as $idx => $event): 
                            $colorClasses = [
                                'blue' => 'bg-blue-100 text-blue-600 border-blue-200',
                                'green' => 'bg-green-100 text-green-600 border-green-200',
                                'gray' => 'bg-gray-100 text-gray-500 border-gray-200',
                                'indigo' => 'bg-indigo-100 text-indigo-600 border-indigo-200',
                                'yellow' => 'bg-yellow-100 text-yellow-600 border-yellow-200'
                            ];
                            $colorClass = $colorClasses[$event['color']] ?? $colorClasses['gray'];
                        ?>
                        <div class="flex gap-4 <?php echo $idx < count($timeline) - 1 ? 'pb-6' : ''; ?>">
                            <!-- Timeline dot and line -->
                            <div class="flex flex-col items-center">
                                <div class="flex items-center justify-center h-8 w-8 rounded-full border-2 <?php echo $colorClass; ?>">
                                    <i class="bi <?php echo $event['icon']; ?> text-sm"></i>
                                </div>
                                <?php if ($idx < count($timeline) - 1): ?>
                                <div class="w-0.5 flex-1 bg-gray-200 mt-1"></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Event content -->
                            <div class="flex-1 pb-2">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($event['title']); ?></span>
                                </div>
                                <div class="text-sm text-gray-600"><?php echo htmlspecialchars($event['description']); ?></div>
                                <div class="text-xs text-gray-400 mt-1">
                                    <?php echo date('M d, Y g:i A', strtotime($event['timestamp'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($timeline)): ?>
                        <div class="text-center py-6 text-gray-500">
                            <i class="bi bi-clock text-2xl mb-2"></i>
                            <p>No delivery events yet</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($viewOrder['status'] === 'pending'): ?>
            <div class="mb-6">
                <h6 class="text-gray-500 font-semibold mb-2 text-sm uppercase">Confirm Order</h6>
                <form method="POST">
                    <input type="hidden" name="action" value="mark_paid">
                    <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                    <?php 
                    $finalPayableAmount = computeFinalAmount($viewOrder, $viewOrderItems);
                    ?>
                    <input type="hidden" name="amount_paid" value="<?php echo $finalPayableAmount; ?>">
                    
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg mb-4">
                        <div class="flex items-start">
                            <i class="bi bi-info-circle text-blue-700 text-xl mr-3"></i>
                            <div>
                                <p class="text-sm text-blue-700 font-semibold mb-1">Amount to be paid</p>
                                <p class="text-2xl font-extrabold text-blue-900"><?php echo formatCurrency($finalPayableAmount); ?></p>
                                <p class="text-xs text-blue-600 mt-1">This amount is automatically calculated based on order total<?php echo !empty($viewOrder['affiliate_code']) ? ' with 20% affiliate discount applied' : ''; ?>.</p>
                            </div>
                        </div>
                    </div>
                    
                    <?php
                    // Show domain selection for templates that don't have domains assigned yet
                    if (!empty($templateItems)):
                        foreach ($templateItems as $item):
                            $metadata = [];
                            if (!empty($item['metadata_json'])) {
                                $metadata = json_decode($item['metadata_json'], true) ?: [];
                            }
                            $assignedDomainId = $metadata['domain_id'] ?? null;
                            
                            // Only show dropdown if domain not already assigned
                            if (!$assignedDomainId):
                                $templateId = $item['product_id'];
                                $availableDomainsForTemplate = getAvailableDomains($templateId);
                                
                                if (!empty($availableDomainsForTemplate)):
                    ?>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="bi bi-globe mr-1"></i> Domain for "<?php echo htmlspecialchars($item['template_name']); ?>" (Optional)
                        </label>
                        <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="domain_id_<?php echo $item['id']; ?>">
                            <option value="">-- Select a domain or leave unassigned --</option>
                            <?php foreach ($availableDomainsForTemplate as $domain): ?>
                            <option value="<?php echo $domain['id']; ?>">
                                <?php echo htmlspecialchars($domain['domain_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php
                                endif;
                            endif;
                        endforeach;
                    endif;
                    ?>
                    
                    <!-- Payment Notes for all orders (especially for tool-only orders) -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="bi bi-sticky mr-1"></i> Payment Notes (Optional)
                        </label>
                        <textarea class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all" name="payment_notes" rows="3" placeholder="e.g., Customer paid half upfront, remaining payment on delivery..."><?php echo htmlspecialchars($viewOrder['payment_notes'] ?? ''); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="bi bi-info-circle"></i> These notes will be saved with the order for future reference.
                        </p>
                    </div>
                    
                    <button type="submit" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition-colors">
                        <i class="bi bi-check-circle mr-2"></i> Confirm Order
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
            <?php if ($viewOrder['status'] === 'pending'): ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="cancel_order">
                <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                <button type="submit" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg transition-colors" onclick="return confirm('Are you sure you want to cancel this order?')">
                    <i class="bi bi-x-circle mr-2"></i> Cancel Order
                </button>
            </form>
            <?php endif; ?>
            <a href="/admin/orders.php" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition-colors">Close</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
