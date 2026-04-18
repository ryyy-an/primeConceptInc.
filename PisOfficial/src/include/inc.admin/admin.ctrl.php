<?php

declare(strict_types=1);

// 1. Buffer & Security Headers
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

// 2. Dependency Loading 
require_once __DIR__ . '/../config.php';
$baseDir    = __DIR__ . '/../';
$configPath = $baseDir . 'config.php';
$dbhPath    = $baseDir . 'dbh.inc.php';
$modelPath  = __DIR__ . '/admin.model.php';

if (file_exists($configPath) && file_exists($dbhPath)) {
    require_once $configPath;
    require_once $dbhPath;
    $pdo = $pdo ?? $conn; // Standardizing to $pdo
} else {
    sendJsonResponse(['error' => 'Core files missing'], 500);
}

if (file_exists($modelPath)) {
    require_once $modelPath;
}

// 3. Action Capture
$action = $_REQUEST['action'] ?? '';

/**
 * --- SECTION A: AJAX / JSON ACTIONS ---
 * Ginagamit para sa data fetching at status updates (Walang page reload)
 */

// Fetch items for a specific request modal
if ($action === 'get_items') {
    $pr_no = (int)($_GET['pr_no'] ?? 0);
    if ($pr_no <= 0) sendJsonResponse(['success' => false, 'error' => 'Invalid ID']);

    $res = get_order_details_shared($pdo, $pr_no);
    if (empty($res)) sendJsonResponse(['success' => false, 'error' => 'Order not found']);

    sendJsonResponse([
        'success' => true,
        'details' => $res['details'],
        'items'   => $res['items']
    ]);
}

// Update product request status (Approved/Rejected)
if ($action === 'update_status') {
    $order_id = (int)($_POST['pr_no'] ?? 0);
    $status   = trim($_POST['status'] ?? '');
    $discount = floatval($_POST['discount'] ?? 0);
    $comment  = trim($_POST['notes'] ?? $_POST['comment'] ?? '');

    // Map status from frontend to internal DB strings if needed
    // 'approve' -> 'Approved', 'reject' -> 'Rejected'
    $dbStatus = ($status === 'approve') ? 'Approved' : (($status === 'reject') ? 'Rejected' : $status);

    if ($order_id <= 0 || !in_array($dbStatus, ['Approved', 'Rejected'])) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid parameters']);
    }

    $success = update_order_status($pdo, $order_id, $dbStatus, $discount, $comment);

    if ($success) {
        sendJsonResponse(['success' => true, 'message' => "Order $dbStatus successfully"]);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Database error']);
    }
}

// Live polling for online status
if ($action === 'get_user_status') {
    try {
        $stmt = $pdo->query("SELECT id, is_online FROM users");
        sendJsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        sendJsonResponse([]);
    }
}

// Global Order History for the sidebar log
if ($action === 'get_history_log') {
    $search = $_GET['search'] ?? '';
    $history = get_global_order_history($pdo, 15, $search);
    sendJsonResponse([
        'success' => true,
        'history' => $history
    ]);
}

// Sales Report Data Fetching
if ($action === 'get_report_sales') {
    $start  = $_GET['start'] ?? null;
    $end    = $_GET['end'] ?? null;
    $status = $_GET['status'] ?? 'All';
    $plan   = $_GET['plan'] ?? 'All';

    $data = get_sales_report_data($pdo, $start, $end, $status, $plan);
    sendJsonResponse([
        'success' => true,
        'data' => $data
    ]);
}

// Sales Revenue Trend Data (Chart)
if ($action === 'get_revenue_trend') {
    $start = $_GET['start'] ?? null;
    $end   = $_GET['end'] ?? null;

    $trend = get_monthly_sales_trend($pdo, $start, $end);
    sendJsonResponse([
        'success' => true,
        'labels'  => array_column($trend, 'month_name'),
        'data'    => array_column($trend, 'revenue')
    ]);
}

if ($action === 'get_top_products') {
    $period = $_GET['period'] ?? 'all';
    $data = get_top_performing_products($pdo, 5, $period);
    sendJsonResponse([
        'success' => true,
        'data' => $data
    ]);
}

if ($action === 'get_orders_report') {
    $status = $_GET['status'] ?? 'All';
    $data = get_all_orders_data($pdo, $status);
    sendJsonResponse([
        'success' => true,
        'data' => $data
    ]);
}

if ($action === 'get_order_details') {
    $orderId = (int)($_GET['order_id'] ?? 0);
    if ($orderId <= 0) {
        sendJsonResponse(['success' => false, 'error' => 'Invalid Order ID']);
    }

    $summary = get_order_summary_by_id($pdo, $orderId);
    $items = get_order_items_report($pdo, $orderId);
    $schedule = [];

    if ($summary && isset($summary['trans_id'])) {
        $schedule = get_payment_schedule_by_trans_id($pdo, (int)$summary['trans_id']);
    }

    sendJsonResponse([
        'success' => true,
        'summary' => $summary,
        'items' => $items,
        'schedule' => $schedule
    ]);
}

if ($action === 'get_receivables') {
    $data = get_pending_receivables($pdo);
    $stats = get_receivables_summary($pdo);
    sendJsonResponse([
        'success' => true,
        'data' => $data,
        'stats' => $stats
    ]);
}

if ($action === 'record_collection') {
    $orderId   = (int)($_POST['order_id'] ?? 0);
    $amount    = (float)($_POST['amount'] ?? 0);
    $reference = trim($_POST['reference'] ?? '');
    $method    = trim($_POST['payment_method'] ?? 'Cash');
    $remarks   = trim($_POST['remarks'] ?? '');

    if ($orderId <= 0 || $amount <= 0) {
        sendJsonResponse(['success' => false, 'error' => 'Invalid amount or order ID']);
    }

    $success = record_manual_collection($pdo, $orderId, $amount, $reference, $method, $remarks);
    sendJsonResponse([
        'success' => $success,
        'message' => $success ? 'Payment recorded successfully' : 'Failed to record payment'
    ]);
}

// Fetch system diagnostics
if ($action === 'get_diagnostics') {
    $data = get_system_diagnostic_data($pdo);
    sendJsonResponse(['success' => true, 'data' => $data]);
}

// Fetch specific table schema
if ($action === 'get_table_schema') {
    $table = $_GET['table'] ?? '';
    if (empty($table)) sendJsonResponse(['success' => false, 'message' => 'Table name required']);
    $schema = get_table_schema($pdo, $table);
    sendJsonResponse(['success' => true, 'schema' => $schema]);
}


/**
 * --- SECTION B: FORM ACTIONS (USER MANAGEMENT) ---
 * Ginagamit para sa mga forms na nag-re-redirect sa settings.php
 */

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($action)) {

    // Auth Check
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        redirectWith("error=unauthorized");
    }

    try {
        // ADD USER
        if ($action === 'add_user') {
            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $role     = $_POST['role'] ?? 'staff';
            $passHash = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);

            // Validation: Full Name should not contain numbers
            if (preg_match('/\d/', $fullName)) {
                redirectWith("error=numeric_name");
            }

            $stmt = $pdo->prepare("INSERT INTO users (full_name, username, role, password_hash, created_at, is_online) 
                                   VALUES (?, ?, ?, ?, NOW(), 0)");
            $stmt->execute([$fullName, $username, $role, $passHash]);
            redirectWith("success=user_added");
        }

        // DELETE USER
        if ($action === 'delete_user') {
            $targetId = (int)($_POST['user_id'] ?? 0);

            if ($targetId === (int)$_SESSION['user_id']) {
                redirectWith("error=self_delete");
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$targetId]);
            redirectWith("success=user_deleted");
        }

        // RESET PASSWORD
        if ($action === 'reset_password') {
            $targetId = $_POST['user_id'] ?? null;
            $rawPass  = $_POST['new_password'] ?? '';

            if (!$targetId || empty($rawPass)) {
                redirectWith("error=invalid_input");
            }

            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([password_hash($rawPass, PASSWORD_DEFAULT), $targetId]);
            redirectWith("success=password_reset");
        }

        // RUN DATABASE MIGRATION (RESET)
        if ($action === 'run_migration') {
            $result = run_database_migration($pdo);
            if ($result['success']) {
                redirectWith("success=migration_complete");
            } else {
                redirectWith("error=migration_failed");
            }
        }
    } catch (PDOException $e) {
        error_log("Admin Form Action Error: " . $e->getMessage());
        $err = ($e->getCode() == 23000) ? 'exists' : 'db_error';
        redirectWith("error=" . $err);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_product') {
    header('Content-Type: application/json');
    try {
        $pdo->beginTransaction();

        $main_img = 'default.png';
        if (isset($_FILES['default_image']) && $_FILES['default_image']['error'] === 0) {
            if (!is_valid_image($_FILES['default_image'])) {
                throw new Exception("Invalid main image format. Only JPG and PNG are allowed.");
            }
            $main_img = time() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "", $_FILES['default_image']['name']);
            move_uploaded_file($_FILES['default_image']['tmp_name'], "../../../public/assets/img/furnitures/" . $main_img);
        }

        $prod_id = insert_product($pdo, [
            'name' => $_POST['name'] ?? '',
            'code' => $_POST['code'] ?? '',
            'description' => $_POST['description'] ?? '',
            'category' => $_POST['category'] ?? '',
            'price' => $_POST['price'] ?? 0,
            'default_image' => $main_img
        ]);

        if (!empty($_POST['comp_names'])) {
            foreach ($_POST['comp_names'] as $index => $c_name) {
                if (empty(trim($c_name))) continue;
                $c_loc = $_POST['comp_locs'][$index] ?? '';
                $comp_id = get_or_create_component($pdo, $c_name);
                link_product_component($pdo, $prod_id, $comp_id, $_POST['comp_qtys'][$index] ?? 0, $c_loc);
            }
        }

        if (!empty($_POST['variant_names'])) {
            foreach ($_POST['variant_names'] as $index => $v_name) {
                if (empty($v_name)) continue;
                $v_img = 'default_v.png';
                if (isset($_FILES['variant_imgs']['name'][$index]) && $_FILES['variant_imgs']['error'][$index] === 0) {
                    $file_arr = [
                        'name' => $_FILES['variant_imgs']['name'][$index],
                        'tmp_name' => $_FILES['variant_imgs']['tmp_name'][$index]
                    ];
                    if (!is_valid_image($file_arr)) {
                        throw new Exception("Invalid variant image format for '" . $v_name . "'. Only JPG and PNG are allowed.");
                    }
                    $v_img = time() . '_v_' . preg_replace("/[^a-zA-Z0-9._-]/", "", $_FILES['variant_imgs']['name'][$index]);
                    move_uploaded_file($_FILES['variant_imgs']['tmp_name'][$index], "../../../public/assets/img/furnitures/" . $v_img);
                }
                $min_qty = $_POST['variant_low_stocks'][$index] ?? 0;
                insert_variant($pdo, $prod_id, $v_name, $v_img, (int)$min_qty);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_product') {
    header('Content-Type: application/json');
    try {
        $code = $_POST['code'] ?? '';
        if (empty($code)) {
            throw new Exception("Product code is missing.");
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id FROM products WHERE code = ?");
        $stmt->execute([$code]);
        $prod_id = $stmt->fetchColumn();

        if ($prod_id) {
            // Soft delete the product
            $pdo->prepare("UPDATE products SET is_deleted = 1 WHERE id = ?")->execute([$prod_id]);
            // Also soft delete associated variants
            $pdo->prepare("UPDATE product_variant SET is_deleted = 1 WHERE prod_id = ?")->execute([$prod_id]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_product') {
    header('Content-Type: application/json');
    try {
        $pdo->beginTransaction();

        $old_code = $_POST['old_code'] ?? '';
        $stmt = $pdo->prepare("SELECT id, default_image FROM products WHERE code = ?");
        $stmt->execute([$old_code]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$prod) throw new Exception("Product not found.");

        $prod_id = $prod['id'];
        $main_img = $prod['default_image'];

        if (isset($_FILES['default_image']) && $_FILES['default_image']['error'] === 0) {
            if (!is_valid_image($_FILES['default_image'])) {
                throw new Exception("Invalid main image format. Only JPG and PNG are allowed.");
            }
            $main_img = time() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "", $_FILES['default_image']['name']);
            move_uploaded_file($_FILES['default_image']['tmp_name'], "../../../public/assets/img/furnitures/" . $main_img);
        }

        $is_on_sale = isset($_POST['is_on_sale']) && $_POST['is_on_sale'] === 'on' ? 1 : 0;
        $discount = floatval($_POST['discount'] ?? 0);

        $stmt = $pdo->prepare("UPDATE products SET name=?, code=?, description=?, category=?, price=?, default_image=?, discount=?, is_on_sale=? WHERE id=?");
        $stmt->execute([
            $_POST['name'] ?? '',
            $_POST['code'] ?? '',
            $_POST['description'] ?? '',
            $_POST['category'] ?? '',
            $_POST['price'] ?? 0,
            $main_img,
            $discount,
            $is_on_sale,
            $prod_id
        ]);

        $stmt = $pdo->prepare("SELECT id FROM product_components WHERE prod_id = ?");
        $stmt->execute([$prod_id]);
        $existing_pc_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $submitted_pc_ids = [];
        $i = 0;
        if (!empty($_POST['comp_names'])) {
            foreach ($_POST['comp_names'] as $index => $c_name) {
                if (empty(trim($c_name))) continue;
                $pc_id = $_POST['pc_ids'][$index] ?? '';
                $c_loc = $_POST['comp_locs'][$index] ?? '';
                $c_qty = $_POST['comp_qtys'][$index] ?? 0;

                $comp_id = get_or_create_component($pdo, $c_name);

                if (!empty($pc_id) && in_array($pc_id, $existing_pc_ids)) {
                    // Update existing link (preserves warehouse_stocks link)
                    $stmt = $pdo->prepare("UPDATE product_components SET comp_id=?, qty_needed=?, location=? WHERE id=?");
                    $stmt->execute([$comp_id, $c_qty, $c_loc, $pc_id]);
                    $submitted_pc_ids[] = $pc_id;
                } else {
                    // Create new link (initializes warehouse_stocks at 0)
                    // Note: link_product_component handles both insertion and stock initialization
                    link_product_component($pdo, $prod_id, $comp_id, $c_qty, $c_loc);
                    $submitted_pc_ids[] = $pdo->lastInsertId();
                }
            }
        }

        $components_to_delete = array_diff($existing_pc_ids, $submitted_pc_ids);
        if (!empty($components_to_delete)) {
            $inQuery = implode(',', array_fill(0, count($components_to_delete), '?'));
            // This will also trigger CASCADE if configured, or orphan the stocks (which is okay if the part is removed from recipe)
            $pdo->prepare("DELETE FROM product_components WHERE id IN ($inQuery)")->execute(array_values($components_to_delete));
        }

        $stmt = $pdo->prepare("SELECT id FROM product_variant WHERE prod_id = ?");
        $stmt->execute([$prod_id]);
        $db_variants = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $submitted_variant_ids = [];

        if (!empty($_POST['variant_names'])) {
            foreach ($_POST['variant_names'] as $index => $v_name) {
                if (empty($v_name)) continue;

                $v_id = $_POST['variant_ids'][$index] ?? '';
                $v_img = $_POST['existing_variant_imgs'][$index] ?? 'default_v.png';

                if (isset($_FILES['variant_imgs']['name'][$index]) && $_FILES['variant_imgs']['error'][$index] === 0) {
                    $file_arr = [
                        'name' => $_FILES['variant_imgs']['name'][$index],
                        'tmp_name' => $_FILES['variant_imgs']['tmp_name'][$index]
                    ];
                    if (!is_valid_image($file_arr)) {
                        throw new Exception("Invalid variant image format for '" . $v_name . "'. Only JPG and PNG are allowed.");
                    }
                    $v_img = time() . '_v_' . preg_replace("/[^a-zA-Z0-9._-]/", "", $_FILES['variant_imgs']['name'][$index]);
                    move_uploaded_file($_FILES['variant_imgs']['tmp_name'][$index], "../../../public/assets/img/furnitures/" . $v_img);
                }

                $min_qty = $_POST['variant_low_stocks'][$index] ?? 0;

                if (!empty($v_id)) {
                    $stmt = $pdo->prepare("UPDATE product_variant SET variant=?, variant_image=?, min_buildable_qty=? WHERE id=?");
                    $stmt->execute([$v_name, $v_img, $min_qty, $v_id]);
                    $submitted_variant_ids[] = $v_id;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO product_variant (prod_id, variant, variant_image, min_buildable_qty, is_deleted) VALUES (?, ?, ?, ?, 0)");
                    $stmt->execute([$prod_id, $v_name, $v_img, $min_qty]);
                    $submitted_variant_ids[] = $pdo->lastInsertId();
                }
            }
        }

        $variants_to_delete = array_diff($db_variants, $submitted_variant_ids);
        if (!empty($variants_to_delete)) {
            $inQuery = implode(',', array_fill(0, count($variants_to_delete), '?'));
            // Soft delete variants (Update is_deleted instead of DELETE)
            $pdo->prepare("UPDATE product_variant SET is_deleted = 1 WHERE id IN ($inQuery)")->execute(array_values($variants_to_delete));
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_sale') {
    header('Content-Type: application/json');
    try {
        $code = $_POST['code'] ?? '';
        $is_on_sale = (int)($_POST['is_on_sale'] ?? 0);

        if (empty($code)) {
            throw new Exception("Product code is missing.");
        }

        $stmt = $pdo->prepare("UPDATE products SET is_on_sale = ? WHERE code = ?");
        $stmt->execute([$is_on_sale, $code]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_product_details') {
    header('Content-Type: application/json');
    try {
        $code = $_POST['code'] ?? '';
        if (empty($code)) {
            throw new Exception("Product code is missing.");
        }

        $details = get_product_full_details($pdo, $code);
        if (!$details) throw new Exception("Product not found.");

        echo json_encode(['success' => true, 'product' => $details]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_sale') {
    header('Content-Type: application/json');
    try {
        $userId = $_SESSION['user_id'] ?? 0;
        if (!$userId) throw new Exception("Unauthorized access.");

        // Data collection
        $data = [
            'user_id'            => $userId,
            'clientName'         => $_POST['clientName'] ?? '',
            'clientType'         => $_POST['clientType'] ?? 'Private / Individual',
            'govBranch'          => $_POST['govBranch'] ?? null,
            'clientContact'      => $_POST['clientContact'] ?? '',
            'adminDiscount'      => $_POST['adminDiscount'] ?? 0,
            'shippingMode'       => $_POST['shippingMode'] ?? 'pickup',
            'deliveryAddress'    => $_POST['deliveryAddress'] ?? '',
            'transactionType'    => $_POST['transactionType'] ?? 'full',
            'interestRate'       => $_POST['interestRate'] ?? 0,
            'installmentTerm'    => $_POST['installmentTerm'] ?? 0,
            'paymentMethod'      => $_POST['paymentMethod'] ?? '',
            'paymentRef'         => $_POST['paymentRef'] ?? '',
            'amountPaid'         => $_POST['amountPaid'] ?? 0,
            'paymentRemarks'     => $_POST['paymentRemarks'] ?? '',
            'totalAmount'        => $_POST['totalAmount'] ?? 0,
            'totalWithInterest'  => $_POST['totalWithInterest'] ?? 0,
            'balance'            => $_POST['balance'] ?? 0
        ];

        if (empty($data['clientName'])) {
            throw new Exception("Customer Name is required.");
        }

        $result = process_admin_pos_sale($pdo, $data);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_stocks') {
    header('Content-Type: application/json');
    try {
        $userId = $_SESSION['user_id'] ?? 0;
        if (!$userId) throw new Exception("Unauthorized access.");

        $adjustments = json_decode($_POST['adjustments'] ?? '[]', true);
        if (empty($adjustments)) throw new Exception("No adjustments received.");

        $result = update_stock_adjustment($pdo, $adjustments, (int)$userId);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Default fallback
sendJsonResponse(['error' => 'Invalid action'], 404);


/**
 * HELPER FUNCTIONS
 */

function sendJsonResponse(array $data, int $code = 200): void
{
    ob_clean();
    http_response_code($code);
    header("Content-Type: application/json");
    echo json_encode($data);
    exit;
}

function redirectWith(string $query): void
{
    header("Location: ../../-admin/settings.php?" . $query);
    exit;
}

/**
 * Validates if the uploaded file is a JPG or PNG.
 */
function is_valid_image(array $file): bool
{
    $allowed_exts = ['jpg', 'jpeg', 'png'];
    $allowed_mimes = ['image/jpeg', 'image/png'];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Check extension
    if (!in_array($ext, $allowed_exts)) {
        return false;
    }

    // Check MIME type if available
    if (isset($file['tmp_name']) && !empty($file['tmp_name'])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed_mimes)) {
            return false;
        }
    }

    return true;
}
