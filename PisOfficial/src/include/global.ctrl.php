<?php

declare(strict_types=1);

require_once 'config.php';
require_once 'dbh.inc.php';
require_once 'global.model.php';

/** @var PDO $pdo */
init_notifications_table($pdo);

/**
 * Helper to standard JSON response.
 */
function sendJsonResponse(array $data, int $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/**
 * Get Cart Count
 */
if ($action === 'get_cart_count') {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        sendJsonResponse(['success' => false, 'count' => 0]);
    }
    
    try {
        $stmt = $pdo->prepare("SELECT SUM(qty) as count FROM cart WHERE user_id = ?");
        $stmt->execute([$userId]);
        $count = (int)$stmt->fetchColumn();
        // Fallback: If they want count of distinct items instead of sum of quantity:
        // $stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
        
        // Wait! Let's check how $totalCartItems is calculated in home-page.php.
        // It does: count($cartItems) which means it's the number of distinct rows, NOT the sum of quantities!
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
        $stmt->execute([$userId]);
        $count = (int)$stmt->fetchColumn();
        
        sendJsonResponse(['success' => true, 'count' => $count]);
    } catch (PDOException $e) {
        sendJsonResponse(['success' => false, 'count' => 0]);
    }
}

/**
 * Add To Cart
 */
if ($action === 'add_to_cart') {
    $userId = $_SESSION['user_id'] ?? null;
    $variantId = (int)($_POST['variant_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);
    $source = $_POST['source'] ?? 'SR'; // 'SR' or 'WH'

    if (!$userId) {
        sendJsonResponse(['success' => false, 'message' => 'User not logged in'], 401);
    }
    if ($variantId <= 0 || $qty <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid parameters']);
    }

    $result = add_to_cart($pdo, (int)$userId, $variantId, $qty, $source);
    if ($result['success']) {
        sendJsonResponse(['success' => true]);
    } else {
        $reason = $result['reason'] ?? 'unknown';
        if ($reason === 'no_stock') {
            $msg = 'No available stock for this item from the selected source.';
        } elseif ($reason === 'exceeds_stock') {
            $available = $result['available'] ?? 0;
            $inCart    = $result['in_cart'] ?? 0;
            $remaining = max(0, $available - $inCart);
            if ($inCart > 0) {
                $msg = "You already have {$inCart} of this item in your cart. Only {$remaining} more can be added (available: {$available}).";
            } else {
                $msg = "Cannot add {$qty} item(s). Only {$available} available in stock.";
            }
        } else {
            $msg = 'Failed to add item to cart. Please try again.';
        }
        sendJsonResponse(['success' => false, 'message' => $msg]);
    }
}

/**
 * Update Cart Qty
 */
if ($action === 'update_cart_qty') {
    $cartId = (int)($_POST['cart_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 0);
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId || $cartId <= 0 || $qty < 1) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid parameters']);
    }

    try {
        // 1. Get variant details and source for this cart item
        $cartStmt = $pdo->prepare("SELECT variant_id, source FROM cart WHERE id = ? AND user_id = ?");
        $cartStmt->execute([$cartId, $userId]);
        $cartItem = $cartStmt->fetch(PDO::FETCH_ASSOC);

        if (!$cartItem) {
            sendJsonResponse(['success' => false, 'message' => 'Cart item not found']);
        }

        $variantId = (int)$cartItem['variant_id'];
        $source = $cartItem['source'];

        // 2. Check current available stock incorporating reservations
        $availableStock = get_effective_available_stock($pdo, $variantId, $source);

        if ($qty > $availableStock) {
            sendJsonResponse([
                'success' => false, 
                'message' => 'Cannot update quantity. Only ' . $availableStock . ' items are available (including reserved units).'
            ]);
        }

        // 3. Update the cart
        $stmt = $pdo->prepare("UPDATE cart SET qty = ? WHERE id = ? AND user_id = ?");
        $success = $stmt->execute([$qty, $cartId, $userId]);
        sendJsonResponse(['success' => $success]);
    } catch (Exception $e) {
        sendJsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Delete Cart Item
 */
if ($action === 'delete_cart_item') {
    $cartId = (int)($_POST['cart_id'] ?? 0);
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId || $cartId <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid parameters']);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $success = $stmt->execute([$cartId, $userId]);
        sendJsonResponse(['success' => $success]);
    } catch (Exception $e) {
        sendJsonResponse(['success' => false, 'message' => 'Database error']);
    }
}
/**
 * Get Cart Items
 */
if ($action === 'get_cart_items') {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        sendJsonResponse(['success' => false, 'message' => 'User not logged in'], 401);
    }

    $items = get_cart_items($pdo, (int)$userId);
    sendJsonResponse(['success' => true, 'items' => $items]);
}
/**
 * Search Customers (Autocomplete)
 */
if ($action === 'search_customers') {
    $query = $_GET['query'] ?? '';
    if (empty($query)) sendJsonResponse([]);

    try {
        $stmt = $pdo->prepare("SELECT id, name, contact_no, client_type, gov_branch 
                               FROM customers 
                               WHERE name LIKE ? 
                               LIMIT 10");
        $stmt->execute(['%' . $query . '%']);
        sendJsonResponse(['success' => true, 'customers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        sendJsonResponse(['success' => false, 'message' => 'Search error']);
    }
}

/**
 * Get Customer Transaction History
 */
if ($action === 'get_customer_history') {
    $customerId = (int)($_GET['customer_id'] ?? 0);
    $name = trim($_GET['name'] ?? '');

    try {
        if ($customerId > 0) {
            $sql = "SELECT o.id, o.total_ammount as total_amount, o.status, o.created_at, t.or_number, t.id as transaction_id
                    FROM orders o
                    LEFT JOIN transactions t ON o.id = t.order_id
                    WHERE o.customer_id = ?
                    ORDER BY o.created_at DESC
                    LIMIT 20";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$customerId]);
        } elseif (!empty($name)) {
            // Search by name in both registered customers and temp name
            $sql = "SELECT o.id, o.total_ammount as total_amount, o.status, o.created_at, t.or_number, t.id as transaction_id
                    FROM orders o
                    LEFT JOIN transactions t ON o.id = t.order_id
                    LEFT JOIN customers c ON o.customer_id = c.id
                    WHERE LOWER(c.name) = LOWER(?) OR LOWER(o.temp_customer_name) = LOWER(?)
                    ORDER BY o.created_at DESC
                    LIMIT 20";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $name]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'No ID or Name provided']);
        }

        sendJsonResponse(['success' => true, 'history' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        sendJsonResponse(['success' => false, 'message' => 'History fetch error: ' . $e->getMessage()]);
    }
}
/**
 * Get Notifications
 */
if ($action === 'get_notifications') {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $role = $_SESSION['role'] ?? '';
    
    // Release session lock early to prevent UI blocking for other requests
    session_write_close(); 

    if (!$userId || !$role) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $notifications = get_user_notifications($pdo, $userId, $role);
    $unread = get_unread_notif_count($pdo, $userId, $role);
    sendJsonResponse(['success' => true, 'notifications' => $notifications, 'unread' => $unread]);
}

/**
 * Mark Notifications as Read
 */
if ($action === 'mark_notifs_read') {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $role = $_SESSION['role'] ?? '';
    if (!$userId || !$role) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    mark_notifs_as_read($pdo, $userId, $role);
    sendJsonResponse(['success' => true]);
}
/**
 * Clear All Notifications for the current user
 */
if ($action === 'clear_all_notifs') {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $role = $_SESSION['role'] ?? '';
    if (!$userId || !$role) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $success = clear_user_notifications($pdo, $userId, $role);
    sendJsonResponse(['success' => $success]);
}
