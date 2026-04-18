<?php
declare(strict_types=1);

require_once "../../include/config.php";
require_once "../../include/dbh.inc.php";
require_once "../../include/inc.warehouse/wh.model.php";
require_once "../../include/global.model.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Check logged in
if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

$action = $_POST['action'] ?? '';

/** @var PDO $pdo */

try {
    switch ($action) {
        case 'get_order_details':
            $orderId = (int) ($_POST['order_id'] ?? 0);
            if ($orderId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid Order ID.']);
                exit();
            }
            $order = get_fulfillment_order_by_id($pdo, $orderId);
            if ($order) {
                echo json_encode(['success' => true, 'order' => $order]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Order not found.']);
            }
            break;

        case 'mark_item_ready':
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $status = $_POST['status'] ?? 'ready';

            if ($itemId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid Item ID.']);
                exit();
            }

            $success = update_warehouse_item_status($pdo, $itemId, $status);
            echo json_encode(['success' => $success]);
            break;

        case 'fulfill_order':
            $orderId = (int) ($_POST['order_id'] ?? 0);

            if ($orderId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid Order ID.']);
                exit();
            }

            // Note: User requested NO automatic stock deduction in this step.
            $success = fulfill_warehouse_order($pdo, $orderId);

            if ($success) {
                // Determine order creator to notify them
                $stmt = $pdo->prepare("SELECT created_by, customer_id, temp_customer_name FROM orders WHERE id = ?");
                $stmt->execute([$orderId]);
                $orderInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($orderInfo && $orderInfo['created_by']) {
                    $creatorId = (int)$orderInfo['created_by'];
                    $whUserId = (int)$_SESSION['user_id'];
                    $msg = "Order #$orderId has been fulfilled by the Warehouse.";
                    create_notification($pdo, $whUserId, 'Order Fulfilled', $msg, 'result', $creatorId);
                }
            }
            echo json_encode(['success' => $success]);
            break;

        case 'reset_all_ready':
            $orderId = (int) ($_POST['order_id'] ?? 0);
            if ($orderId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid Order ID.']);
                exit();
            }
            $success = reset_order_items_status($pdo, $orderId);
            echo json_encode(['success' => $success]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
