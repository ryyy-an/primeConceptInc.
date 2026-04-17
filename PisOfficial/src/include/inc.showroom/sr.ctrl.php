<?php

declare(strict_types=1);

// Prevent PHP from outputting HTML errors that break JSON
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once '../config.php';
require_once '../dbh.inc.php';

/**
 * PATH FIX: 
 * We are using __DIR__ to ensure the script looks in the same folder 
 * where sr.ctrl.php is located.
 */
$modelPath = __DIR__ . '/sr.model.php';

if (file_exists($modelPath)) {
    require_once $modelPath;
} else {
    header("Content-Type: application/json");
    echo json_encode(['success' => false, 'error' => 'Critical Error: model.php not found.']);
    exit;
}

header("Content-Type: application/json");

// Capture Action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// --- Handle JSON Input separately ---
$jsonData = [];
$rawInput = file_get_contents("php://input");
if (!empty($rawInput)) {
    $jsonData = json_decode($rawInput, true) ?? [];
    if (empty($action)) {
        $action = $jsonData['action'] ?? '';
    }
}

$userId = (int)($_SESSION['user_id'] ?? 0);

// 1. PLACE ORDER REQUEST
if ($action === 'place_request') {
    $fname = $_POST['fname'] ?? 'Guest';
    $lname = $_POST['lname'] ?? '';
    $date = date('Y-m-d');

    try {
        $pdo->beginTransaction();

        // Calculate Total from cart
        $stmtTotal = $pdo->prepare("SELECT SUM(p.price * c.qty) 
                                     FROM cart c 
                                     JOIN product_variant pv ON c.variant_id = pv.id
                                     JOIN products p ON pv.prod_id = p.id
                                     WHERE c.user_id = ?");
        $stmtTotal->execute([$userId]);
        $totalPrice = (float)$stmtTotal->fetchColumn() ?: 0;

        if ($totalPrice == 0) throw new Exception("Your cart is empty.");

        $status = 'For Review';
        $customerName = trim($fname . ' ' . $lname);

        // Insert into orders
        $sqlOrder = "INSERT INTO orders (created_by, status, temp_customer_name, total_ammount, balance, created_at) 
                     VALUES (:user_id, :status, :customer_name, :total, :balance, :date)";

        $stmt = $pdo->prepare($sqlOrder);
        $stmt->execute([
            ':user_id' => $userId,
            ':status'  => $status,
            ':customer_name' => $customerName,
            ':total'   => $totalPrice,
            ':balance' => $totalPrice,
            ':date'    => $date
        ]);

        $orderId = $pdo->lastInsertId();

        // Move items from cart to order_items
        $moveItemsSql = "INSERT INTO order_items (order_id, variant_id, qty, unit_price, get_from)
                         SELECT :order_id, c.variant_id, c.qty, p.price, c.source 
                         FROM cart c
                         JOIN product_variant pv ON c.variant_id = pv.id
                         JOIN products p ON pv.prod_id = p.id
                         WHERE c.user_id = :user_id";

        $stmtMove = $pdo->prepare($moveItemsSql);
        $stmtMove->execute([
            ':order_id' => $orderId,
            ':user_id'  => $userId
        ]);

        // Delete items from cart
        $stmtClear = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmtClear->execute([$userId]);

        $pdo->commit();

        // Notification: Showroom -> Admin (Order Request)

        $senderName = $_SESSION['full_name'] ?? 'Showroom User';
        create_notification($pdo, $userId, 'Order Request', "Status: For Review\nSummary: Request #$orderId has been filed by $senderName.", 'request', null, 'admin');

        ob_clean();
        echo json_encode(['success' => true, 'pr_no' => $orderId]);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        ob_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}


// 4. GET FULL REQUEST DETAILS
if ($action === 'get_items') {
    $pr_no = isset($_GET['pr_no']) ? (int)trim($_GET['pr_no']) : 0;

    if ($pr_no <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID is missing']);
        exit;
    }

    try {
        // Fetch order details
        $stmtMain = $pdo->prepare("SELECT o.*, u.full_name as requested_by, 
                                          COALESCE(c.name, o.temp_customer_name) AS customer_name,
                                          c.contact_no, c.gov_branch 
                                   FROM orders o 
                                   LEFT JOIN users u ON o.created_by = u.id 
                                   LEFT JOIN customers c ON o.customer_id = c.id
                                   WHERE o.id = ?");
        $stmtMain->execute([$pr_no]);
        $details = $stmtMain->fetch(PDO::FETCH_ASSOC);

        if (!$details) {
            echo json_encode(['success' => false, 'error' => 'Order not found']);
            exit;
        }

        // Fetch order items
        $stmtItems = $pdo->prepare("SELECT p.name as prod_name, pv.variant, oi.get_from as location, oi.qty as quantity, oi.unit_price as price 
                                    FROM order_items oi
                                    JOIN product_variant pv ON oi.variant_id = pv.id
                                    JOIN products p ON pv.prod_id = p.id
                                    WHERE oi.order_id = ?");
        $stmtItems->execute([$pr_no]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'details' => $details,
            'items'   => $items ?: []
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database Error: ' . $e->getMessage()]);
    }
    exit;
}

// 5. CANCEL PRODUCT REQUEST
if ($action === 'cancel_request') {
    $prNo = $_POST['pr_no'] ?? $jsonData['pr_no'] ?? '';

    if (empty($prNo)) {
        echo json_encode(['success' => false, 'error' => 'No PR Number provided']);
        exit;
    }

    $success = cancel_product_request($pdo, $prNo);
    echo json_encode(['success' => $success]);
    exit;
}

// 6. FINALIZE APPROVED ORDER
if ($action === 'finalize_order') {
    try {
        $order_id        = (int)($_POST['order_id'] ?? 0);

        if ($order_id <= 0) {
            throw new Exception("Missing Order ID.");
        }

        // Data collection (Full payload matching Admin POS)
        $data = [
            'user_id'          => $userId,
            'order_id'         => $order_id,
            'customer_name'    => trim($_POST['customer_name'] ?? ''),
            'clientType'       => $_POST['clientType'] ?? 'Private / Individual',
            'govBranch'        => $_POST['govBranch'] ?? null,
            'contact_no'       => trim($_POST['contact_no'] ?? ''),
            'adminDiscount'    => (float)($_POST['adminDiscount'] ?? 0),
            'shipping_type'    => $_POST['order_type'] ?? 'pickup',
            'delivery_address' => trim($_POST['address'] ?? ''),
            'transactionType'  => $_POST['transactionType'] ?? 'full',
            'interestRate'     => (float)($_POST['interestRate'] ?? 0),
            'installmentTerm'  => (int)($_POST['installmentTerm'] ?? 0),
            'paymentMethod'    => $_POST['paymentMethod'] ?? '',
            'paymentRef'       => trim($_POST['paymentRef'] ?? ''),
            'amountPaid'       => (float)($_POST['amountPaid'] ?? 0),
            'paymentRemarks'   => trim($_POST['paymentRemarks'] ?? ''),
            'totalWithInterest' => (float)($_POST['totalWithInterest'] ?? 0),
            'balance'          => (float)($_POST['balance'] ?? 0)
        ];

        if (empty($data['customer_name'])) {
            throw new Exception("Missing Customer Name.");
        }

        $result = process_showroom_finalize_order($pdo, $data);

        if ($result['success']) {

            create_notification($pdo, $userId, 'Order to Fulfill', "Status: Processing\nSummary: Order #$order_id is ready for Warehouse fulfillment.", 'fulfillment', null, 'warehouse');
        }

        if (ob_get_length()) ob_clean();
        echo json_encode($result);
    } catch (Exception $e) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 6. FALLBACK
echo json_encode(['status' => 'error', 'message' => 'Unknown action: ' . $action]);
exit;
