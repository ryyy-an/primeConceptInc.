<?php

declare(strict_types=1);

require_once __DIR__ . '/../global.model.php';
function get_all_users(PDO $pdo): array
{
    try {
        // Added 'is_online' to the SELECT statement
        $sql = "SELECT id, username, full_name, role, is_online, created_at 
                FROM users 
                ORDER BY full_name ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fetch Users Error: " . $e->getMessage());
        return [];
    }
}

function get_user_summary_stats(PDO $pdo): array
{
    $sql = "SELECT 
                COUNT(*) as total_staff,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins,
                SUM(is_online) as active_now
            FROM users";
    $stmt = $pdo->query($sql);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'total_staff' => $res['total_staff'] ?? 0,
        'total_admins' => $res['total_admins'] ?? 0,
        'active_now' => $res['active_now'] ?? 0
    ];
}

// Get all unique categories
function get_unique_categories($pdo)
{
    $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE is_deleted = 0 AND category IS NOT NULL AND category != '' ORDER BY category ASC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get all existing components for the selection list
function get_all_components($pdo)
{
    $stmt = $pdo->query("SELECT component_name FROM components ORDER BY component_name ASC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Insert Product
function insert_product($pdo, $data)
{
    // 1. Check if product code already exists (even if deleted)
    $stmtCheck = $pdo->prepare("SELECT id, is_deleted FROM products WHERE code = ?");
    $stmtCheck->execute([$data['code']]);
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ((int)$existing['is_deleted'] === 0) {
            // Already active - throw exception for true duplicate
            throw new Exception("Duplicate Error: The product code '{$data['code']}' is already in use by another active product.");
        }

        // --- RESURRECTION LOGIC ---
        $prod_id = (int)$existing['id'];

        // A. Archive Old Variants (Soft-delete them so old order history stays valid)
        $pdo->prepare("UPDATE product_variant SET is_deleted = 1 WHERE prod_id = ?")->execute([$prod_id]);

        // B. Archive Old Component Linkages (Soft-delete them using the new column)
        $pdo->prepare("UPDATE product_components SET is_deleted = 1 WHERE prod_id = ?")->execute([$prod_id]);

        // C. Re-activate and Update the Product record
        $sql = "UPDATE products SET name = ?, description = ?, category = ?, price = ?, default_image = ?, is_deleted = 0 WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['description'],
            $data['category'],
            $data['price'],
            $data['default_image'],
            $prod_id
        ]);

        return $prod_id;
    }

    // 2. Normal Insertion for truly new codes
    $sql = "INSERT INTO products (name, code, description, category, price, default_image, discount, is_on_sale, is_deleted) 
            VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['name'],
        $data['code'],
        $data['description'],
        $data['category'],
        $data['price'],
        $data['default_image']
    ]);
    return $pdo->lastInsertId();
}

// Check if component exists, else insert, then return ID
function get_or_create_component($pdo, $name)
{
    $stmt = $pdo->prepare("SELECT id FROM components WHERE LOWER(component_name) = LOWER(?)");
    $stmt->execute([trim($name)]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return $id;
    }

    $stmt = $pdo->prepare("INSERT INTO components (component_name) VALUES (?)");
    $stmt->execute([trim($name)]);
    return $pdo->lastInsertId();
}

function link_product_component($pdo, $prod_id, $comp_id, $qty, $loc = '')
{
    // 1. Link the component to the product
    $stmt = $pdo->prepare("INSERT INTO product_components (prod_id, comp_id, qty_needed, location, is_deleted) VALUES (?, ?, ?, ?, 0)");
    $stmt->execute([$prod_id, $comp_id, $qty, empty(trim($loc)) ? 'Aisle Unknown' : trim($loc)]);
    $pc_id = $pdo->lastInsertId();

    // 2. CROSS-INITIALIZE: Create a warehouse stock row for THIS component link across ALL variants of this product
    $stmtVars = $pdo->prepare("SELECT id FROM product_variant WHERE prod_id = ? AND is_deleted = 0");
    $stmtVars->execute([$prod_id]);
    $variants = $stmtVars->fetchAll(PDO::FETCH_ASSOC);

    $stmtStock = $pdo->prepare("INSERT IGNORE INTO warehouse_stocks (prod_id, variant_id, product_comp_id, qty_on_hand, last_update) VALUES (?, ?, ?, 0, CURDATE())");
    foreach ($variants as $v) {
        $stmtStock->execute([$prod_id, $v['id'], $pc_id]);
    }
}

function insert_variant($pdo, $prod_id, $name, $img, $min_qty = 0)
{
    // 1. Insert the variant
    $stmt = $pdo->prepare("INSERT INTO product_variant (prod_id, variant, variant_image, min_buildable_qty, is_deleted) VALUES (?, ?, ?, ?, 0)");
    $stmt->execute([$prod_id, $name, $img, $min_qty]);
    $variantId = $pdo->lastInsertId();

    // 2. Initialize Showroom Stock for this variant
    $pdo->prepare("INSERT IGNORE INTO showroom_stocks (variant_id, qty_on_hand) VALUES (?, 0)")
        ->execute([$variantId]);

    // 3. CROSS-INITIALIZE: Create a warehouse stock row for THIS variant across ALL components linked to this product (Recipe)
    $stmtComps = $pdo->prepare("SELECT id FROM product_components WHERE prod_id = ?");
    $stmtComps->execute([$prod_id]);
    $components = $stmtComps->fetchAll(PDO::FETCH_ASSOC);

    $stmtStock = $pdo->prepare("INSERT IGNORE INTO warehouse_stocks (prod_id, variant_id, product_comp_id, qty_on_hand, last_update) VALUES (?, ?, ?, 0, CURDATE())");
    foreach ($components as $c) {
        $stmtStock->execute([$prod_id, $variantId, $c['id']]);
    }
}

function get_product_full_details(PDO $pdo, string $code): ?array
{
    $sql = "SELECT 
        p.id AS prod_id, p.code, p.name, p.description, p.category, p.default_image,
        p.price, p.discount, p.is_on_sale,  
        pv.id AS variant_id, pv.variant, pv.variant_image, pv.min_buildable_qty,
        c.id AS component_id, c.component_name AS product_component, pc.id AS pc_id, pc.qty_needed AS quantity_needed,
        pc.location AS component_location,
        COALESCE((SELECT ss.qty_on_hand FROM showroom_stocks ss WHERE ss.variant_id = pv.id LIMIT 1), 0) AS v_sr,
        COALESCE((SELECT FLOOR(MIN(ws.qty_on_hand / pc_sub.qty_needed)) 
                  FROM warehouse_stocks ws 
                  JOIN product_components pc_sub ON ws.product_comp_id = pc_sub.id 
                  WHERE ws.variant_id = pv.id), 0) AS v_wh,
        COALESCE((SELECT SUM(ws.qty_on_hand) FROM warehouse_stocks ws WHERE ws.product_comp_id = pc.id), 0) AS c_wh
    FROM products p
    LEFT JOIN product_variant pv ON p.id = pv.prod_id AND pv.is_deleted = 0
    LEFT JOIN product_components pc ON p.id = pc.prod_id AND pc.is_deleted = 0
    LEFT JOIN components c ON pc.comp_id = c.id
    WHERE p.code = ? AND p.is_deleted = 0
    ORDER BY p.name ASC, pv.variant ASC, c.component_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$code]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)) return null;

    $product = [
        'code' => $results[0]['code'],
        'name' => $results[0]['name'],
        'description' => $results[0]['description'],
        'category' => $results[0]['category'],
        'default_image' => $results[0]['default_image'],
        'price' => $results[0]['price'],
        'discount' => $results[0]['discount'],
        'is_on_sale' => $results[0]['is_on_sale'],
        'total_wh' => 0,
        'total_sr' => 0,
        'variants' => [],
        'components' => []
    ];

    $recordedVariants = [];
    $recordedComponents = [];

    foreach ($results as $row) {
        if ($row['variant_id'] && !isset($recordedVariants[$row['variant_id']])) {
            $recordedVariants[$row['variant_id']] = true;
            $product['variants'][] = [
                'variant_id' => $row['variant_id'],
                'variant' => $row['variant'],
                'variant_image' => $row['variant_image'],
                'min_buildable_qty' => $row['min_buildable_qty'],
                'v_sr' => $row['v_sr'],
                'v_wh' => $row['v_wh']
            ];
        }

        if ($row['component_id'] && !isset($recordedComponents[$row['component_id']])) {
            $recordedComponents[$row['component_id']] = true;
            $product['components'][] = [
                'pc_id' => $row['pc_id'],
                'component_id' => $row['component_id'],
                'component_name' => $row['product_component'],
                'qty_needed' => $row['quantity_needed'],
                'location' => $row['component_location'],
                'c_wh' => $row['c_wh']
            ];
        }
    }

    // Calculate totals
    foreach ($product['variants'] as $v) {
        $product['total_wh'] += (int)($v['v_wh'] ?? 0);
        $product['total_sr'] += (int)($v['v_sr'] ?? 0);
    }

    return $product;
}

/**
 * POS Sales Management
 */

/**
 * Main function to process Admin POS Sale
 * Handles Order, Items, Stocks, Transactions, and Payment tracking.
 */
function process_admin_pos_sale(PDO $pdo, array $data): array
{
    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }

        $userId = (int)($data['user_id'] ?? 0);
        if ($userId <= 0) throw new Exception("Invalid User Session");

        // 1. Get or Create Customer
        $customerId = get_or_create_customer($pdo, [
            'name'        => $data['clientName'],
            'contact_no'  => $data['clientContact'],
            'client_type' => $data['clientType'],
            'gov_branch'  => $data['govBranch'] ?? null
        ]);

        // 2. Insert Order
        // For Admin POS, it is automatically 'Approved'
        $orderStatus = 'Success';
        $whStatus = 'To Release';

        $totalAmount = (float)($data['totalAmount'] ?? 0);
        $amountPaid  = (float)($data['amountPaid'] ?? 0);
        $balance     = $totalAmount - $amountPaid;

        $sql = "INSERT INTO orders (created_by, status, wh_status, customer_id, temp_customer_name, shipping_type, delivery_address, payment_mode, admin_discount, total_ammount, balance, comments) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $userId,
            $orderStatus,
            $whStatus,
            $customerId,
            $data['clientName'],
            $data['shippingMode'],
            $data['deliveryAddress'] ?? '',
            $data['paymentMethod'],
            (int)($data['adminDiscount'] ?? 0),
            $totalAmount,
            $balance,
            $data['paymentRemarks'] ?? ''
        ]);
        $orderId = (int)$pdo->lastInsertId();

        // 3. Process Cart Items (Order Items ONLY - No Stock Deduction yet)
        $cartItems = get_cart_items($pdo, $userId);
        if (empty($cartItems)) throw new Exception("Cart is empty.");

        foreach ($cartItems as $item) {
            $vId = (int)$item['variant_id'];
            $neededQty = (int)$item['qty'];

            // Get prod_id for logging
            $stmtProd = $pdo->prepare("SELECT prod_id FROM product_variant WHERE id = ?");
            $stmtProd->execute([$vId]);
            $prodId = (int)($stmtProd->fetchColumn() ?: 0);

            // Insert into order_items
            $sql = "INSERT INTO order_items (order_id, variant_id, qty, get_from, unit_price) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$orderId, $vId, $neededQty, $item['source'], $item['price']]);

            // Stock Deduction & Logging
            if ($item['source'] === 'SR') {
                // LOCK and CHECK Showroom Stock
                $stmtCheck = $pdo->prepare("SELECT qty_on_hand FROM showroom_stocks WHERE variant_id = ? FOR UPDATE");
                $stmtCheck->execute([$vId]);
                $currentSR = (int)($stmtCheck->fetchColumn() ?: 0);

                if ($currentSR < $neededQty) {
                    throw new Exception("Insufficient showroom stock. Available: $currentSR, Needed: $neededQty");
                }

                $pdo->prepare("UPDATE showroom_stocks SET qty_on_hand = qty_on_hand - ? WHERE variant_id = ?")
                    ->execute([$neededQty, $vId]);

                // Showroom Log
                $pdo->prepare("INSERT INTO showroom_logs (variant_id, prod_id, action, qty) VALUES (?, ?, ?, ?)")
                    ->execute([$vId, $prodId, "Sold (Order #$orderId)", -$neededQty]);
            } else {
                // Warehouse Stock Deduction
                $stmtRecipe = $pdo->prepare("SELECT pc.id, pc.comp_id, pc.qty_needed FROM product_components pc JOIN product_variant pv ON pc.prod_id = pv.prod_id WHERE pv.id = ? AND pc.is_deleted = 0");
                $stmtRecipe->execute([$vId]);
                $recipe = $stmtRecipe->fetchAll(PDO::FETCH_ASSOC);

                if (empty($recipe)) throw new Exception("Product recipe missing for variant ID: " . $vId);

                foreach ($recipe as $r) {
                    $deduction = $neededQty * (int)$r['qty_needed'];

                    // LOCK and CHECK Warehouse Stock
                    $stmtCheckWH = $pdo->prepare("SELECT qty_on_hand FROM warehouse_stocks WHERE variant_id = ? AND product_comp_id = ? FOR UPDATE");
                    $stmtCheckWH->execute([$vId, $r['id']]);
                    $currentWH = (int)($stmtCheckWH->fetchColumn() ?: 0);

                    if ($currentWH < $deduction) {
                        throw new Exception("Insufficient warehouse stock for one or more components (Order #$orderId).");
                    }

                    $pdo->prepare("UPDATE warehouse_stocks SET qty_on_hand = qty_on_hand - ? WHERE variant_id = ? AND product_comp_id = ?")
                        ->execute([$deduction, $vId, $r['id']]);

                    $newWH = $currentWH - $deduction;
                    if ($newWH <= 10) { // Threshold for alert

                        $adminId = (int)($_SESSION['user_id'] ?? 0);
                        create_notification($pdo, $adminId, 'Low Stocks Alert', "Status: Critical\nSummary: Component ID {$r['comp_id']} is below threshold ($newWH units).", 'low_stock', null, 'admin');
                    }

                    // Log each component deduction with full context
                    $pdo->prepare("INSERT INTO warehouse_logs (comp_id, prod_id, variant_id, action, qty) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$r['comp_id'], $prodId, $vId, "Sold (Order #$orderId)", -$deduction]);
                }
            }
        }

        // 4. Record Transaction
        $paymentType = ($data['transactionType'] ?? 'full') === 'installment' ? 'Installment' : 'Full';
        $transStatus = ($paymentType === 'Installment') ? 'Ongoing' : 'Success';

        $sql = "INSERT INTO transactions (order_id, transaction_date, or_number, amount, interest, total_with_interest, installment_term, payment_type, status) 
                VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $orderId,
            $data['paymentRef'] ?: ('POS-REF-' . $orderId),
            (float)($data['totalAmount'] ?? 0),
            (float)($data['interestRate'] ?? 0),
            (float)($data['totalWithInterest'] ?? $data['totalAmount']),
            (int)($data['installmentTerm'] ?? 0),
            $paymentType,
            $transStatus
        ]);
        $transId = (int)$pdo->lastInsertId();

        // 5. Payment Tracker — only for Installment transactions
        if ($paymentType === 'Installment') {
            $amountPaid = (float)($data['amountPaid'] ?? 0);
            $totalWithInterest = (float)($data['totalWithInterest'] ?? $data['totalAmount']);
            $term = (int)($data['installmentTerm'] ?? 0);

            // A. Initial Equity / Downpayment (Paid)
            $sqlInitial = "INSERT INTO payment_tracker (trans_id, amount_paid, date_paid, due_date, payment_method, reference_no, status, remarks) 
                            VALUES (?, ?, NOW(), CURDATE(), ?, ?, 'Paid', 'Downpayment / Equity')";
            $pdo->prepare($sqlInitial)->execute([
                $transId,
                $amountPaid,
                $data['paymentMethod'] ?? 'cash',
                $data['paymentRef'] ?: ('POS-REF-' . $orderId)
            ]);

            // B. Scheduled Monthly Installments (Pending)
            if ($term > 0) {
                $remaining = $totalWithInterest - $amountPaid;
                $monthly = ($remaining > 0) ? ($remaining / $term) : 0;

                $sqlInstallment = "INSERT INTO payment_tracker (trans_id, amount_paid, date_paid, due_date, status, remarks) 
                                   VALUES (?, ?, NULL, DATE_ADD(CURDATE(), INTERVAL ? MONTH), 'Pending', ?)";
                $stmtInstallment = $pdo->prepare($sqlInstallment);

                for ($i = 1; $i <= $term; $i++) {
                    $stmtInstallment->execute([
                        $transId,
                        $monthly,
                        $i,
                        "Scheduled Installment #$i"
                    ]);
                }
            }
        }

        // 6. Clear Cart
        $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);

        $pdo->commit();

        // Notification: Notify Warehouse if there are WH items
        $hasWHItems = false;
        foreach ($cartItems as $item) {
            if ($item['source'] === 'WH') {
                $hasWHItems = true;
                break;
            }
        }

        if ($hasWHItems) {
            $adminId = (int)($_SESSION['user_id'] ?? 0);
            $msg = "Order #$orderId (POS) is paid and ready for fulfillment.";
            create_notification($pdo, $adminId, 'Order to Fulfill', "Status: Paid\nSummary: $msg", 'fulfillment', null, 'warehouse');
        }

        return ['success' => true, 'order_id' => $orderId];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Handle direct stock adjustments for variants and components
 */
function update_stock_adjustment(PDO $pdo, array $adjustments, int $userId): array
{
    try {
        if (!$pdo->inTransaction()) $pdo->beginTransaction();

        $warehouse_changes = []; // Key: "variant_id:product_comp_id" => net_diff
        $showroom_changes = [];  // Key: variant_id => net_diff
        $audit_logs = [];        // Queue for logging

        foreach ($adjustments as $adj) {
            $type = $adj['type'];
            $id = (int)$adj['id'];
            $diff = (int)$adj['diff'];

            if ($diff === 0) continue;

            if ($type === 'SR_VAR') {
                // Get prod_id for logging
                $stmtProd = $pdo->prepare("SELECT prod_id FROM product_variant WHERE id = ?");
                $stmtProd->execute([$id]);
                $prodId = (int)($stmtProd->fetchColumn() ?: 0);

                // 1. Update Showroom Stock
                $stmt = $pdo->prepare("INSERT INTO showroom_stocks (variant_id, qty_on_hand, last_update) 
                                     VALUES (?, ?, CURDATE()) 
                                     ON DUPLICATE KEY UPDATE qty_on_hand = qty_on_hand + ?, last_update = CURDATE()");
                $stmt->execute([$id, $diff, $diff]);

                // 2. Log Showroom Adjustment
                $stmtLog = $pdo->prepare("INSERT INTO showroom_logs (variant_id, prod_id, action, qty) VALUES (?, ?, 'INVENTORY_ADJUSTMENT', ?)");
                $stmtLog->execute([$id, $prodId, $diff]);

                // Buffer Warehouse Deduction (Transfer)
                $stmtRecipe = $pdo->prepare("SELECT pc.id, pc.qty_needed, pc.comp_id 
                                           FROM product_variant pv 
                                           JOIN product_components pc ON pv.prod_id = pc.prod_id 
                                           WHERE pv.id = ?");
                $stmtRecipe->execute([$id]);
                $components = $stmtRecipe->fetchAll(PDO::FETCH_ASSOC);

                foreach ($components as $comp) {
                    $deduction = $diff * (int)$comp['qty_needed'];

                    // 1. Deduct from warehouse component rows, capped at 0
                    $stmtDeduct = $pdo->prepare("UPDATE warehouse_stocks SET qty_on_hand = GREATEST(0, qty_on_hand - ?), last_update = CURDATE() 
                                               WHERE product_comp_id = ? AND variant_id = ?");
                    $stmtDeduct->execute([$deduction, $comp['id'], $id]);

                    // 2. Resolve actual Raw Component ID for logging
                    $stmtGetComp = $pdo->prepare("SELECT comp_id FROM product_components WHERE id = ?");
                    $stmtGetComp->execute([$comp['id']]);
                    $actualCompId = $stmtGetComp->fetchColumn();

                    // 3. Log Warehouse Deduction specifically for this part
                    $stmtLogWH = $pdo->prepare("INSERT INTO warehouse_logs (comp_id, prod_id, variant_id, action, qty) VALUES (?, ?, ?, ?, ?)");
                    $stmtLogWH->execute([(int)$actualCompId, $prodId, $id, "Stock Transfer to Showroom (Variant ID $id)", -$deduction]);
                }
            } else if ($type === 'WH_VAR') {
                // Get prod_id for logging
                $stmtProd = $pdo->prepare("SELECT prod_id FROM product_variant WHERE id = ?");
                $stmtProd->execute([$id]);
                $prodId = (int)($stmtProd->fetchColumn() ?: 0);


                // 2. Update Component Stocks based on Recipe Multiplier
                $stmtRecipe = $pdo->prepare("SELECT pc.id, pc.qty_needed 
                                           FROM product_variant pv 
                                           JOIN product_components pc ON pv.prod_id = pc.prod_id 
                                           WHERE pv.id = ?");
                $stmtRecipe->execute([$id]);
                $components = $stmtRecipe->fetchAll(PDO::FETCH_ASSOC);

                foreach ($components as $comp) {
                    $actualChange = $diff * (int)$comp['qty_needed'];

                    $stmtUpdateComp = $pdo->prepare("UPDATE warehouse_stocks SET qty_on_hand = GREATEST(0, qty_on_hand + ?), last_update = CURDATE() 
                                                   WHERE product_comp_id = ? AND variant_id = ?");
                    $stmtUpdateComp->execute([$actualChange, $comp['id'], $id]);

                    // Resolve actual Raw Component ID for logging
                    $stmtGetActual = $pdo->prepare("SELECT comp_id FROM product_components WHERE id = ?");
                    $stmtGetActual->execute([$comp['id']]);
                    $actualCompId = $stmtGetActual->fetchColumn();

                    // Log Component Level Change
                    $stmtLogComp = $pdo->prepare("INSERT INTO warehouse_logs (comp_id, prod_id, variant_id, action, qty) VALUES (?, ?, ?, ?, ?)");
                    $stmtLogComp->execute([(int)$actualCompId, $prodId, $id, "Recipe Adjusted (Variant ID $id multiplier: $diff)", $actualChange]);
                }
            } else if ($type === 'WH_COMP') {
                // Direct Manual Component Adjustment
                $stmt = $pdo->prepare("UPDATE warehouse_stocks SET qty_on_hand = GREATEST(0, qty_on_hand + ?), last_update = CURDATE() WHERE product_comp_id = ?");
                $stmt->execute([$diff, $id]);

                // Resolve Context for logging
                $stmtGetComp = $pdo->prepare("SELECT comp_id, prod_id FROM product_components WHERE id = ?");
                $stmtGetComp->execute([$id]);
                $compInfo = $stmtGetComp->fetch(PDO::FETCH_ASSOC);
                $actualCompId = (int)($compInfo['comp_id'] ?? 0);
                $prodId = (int)($compInfo['prod_id'] ?? 0);

                $stmtLog = $pdo->prepare("INSERT INTO warehouse_logs (comp_id, prod_id, variant_id, action, qty) VALUES (?, ?, NULL, 'MANUAL_ADJUSTMENT', ?)");
                $stmtLog->execute([$actualCompId, $prodId, $diff]);
            }
        }

        $pdo->commit();
        return ['success' => true];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Stock Update Error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Fetches variants that are at or below their minimum buildable quantity.
 * Real-time calculation based on components.
 */
function get_dashboard_low_stock(PDO $pdo): array
{
    try {
        $sql = "SELECT p.name as prod_name, pv.variant, pv.variant_image, pv.min_buildable_qty, 
                       p.default_image, p.code,
                COALESCE((SELECT ss.qty_on_hand FROM showroom_stocks ss WHERE ss.variant_id = pv.id LIMIT 1), 0) as sr_qty,
                COALESCE((SELECT FLOOR(MIN(ws.qty_on_hand / pc.qty_needed)) 
                          FROM warehouse_stocks ws 
                          JOIN product_components pc ON ws.product_comp_id = pc.id 
                          WHERE ws.variant_id = pv.id), 0) as wh_qty
                FROM product_variant pv
                JOIN products p ON pv.prod_id = p.id
                WHERE p.is_deleted = 0 AND pv.is_deleted = 0
                HAVING wh_qty <= pv.min_buildable_qty OR sr_qty <= pv.min_buildable_qty
                LIMIT 5";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get Dashboard Low Stock Error: " . $e->getMessage());
        return [];
    }
}

function get_pending_receivables(PDO $pdo): array
{
    try {
        $sql = "SELECT * FROM (
                    SELECT 
                        o.id, 
                        c.name as client_name,
                        c.gov_branch as branch,
                        c.client_type,
                        o.created_at, 
                        o.total_ammount as total, 
                        t.total_with_interest,
                        (t.total_with_interest - COALESCE((SELECT SUM(pt.amount_paid) FROM payment_tracker pt WHERE pt.trans_id = t.id AND pt.status = 'Paid'), 0)) as balance,
                        o.status as order_status,
                        t.id as trans_id,
                        t.or_number,
                        t.status as status
                    FROM orders o
                    JOIN customers c ON o.customer_id = c.id
                    JOIN transactions t ON o.id = t.order_id
                    WHERE t.payment_type = 'Installment'
                      AND t.status = 'Ongoing'
                      AND LOWER(c.client_type) = 'government'
                ) as sub
                WHERE balance > 0
                ORDER BY created_at DESC";
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get Receivables Error: " . $e->getMessage());
        return [];
    }
}

function get_receivables_summary(PDO $pdo): array
{
    try {
        $sql = "SELECT 
                    COUNT(*) as pending_accounts,
                    SUM(balance) as total_outstanding
                FROM (
                    SELECT 
                        o.id,
                        (t.total_with_interest - COALESCE((SELECT SUM(pt.amount_paid) FROM payment_tracker pt WHERE pt.trans_id = t.id AND pt.status = 'Paid'), 0)) as balance
                    FROM orders o
                    JOIN customers c ON o.customer_id = c.id
                    JOIN transactions t ON o.id = t.order_id
                    WHERE t.payment_type = 'Installment'
                      AND t.status = 'Ongoing'
                      AND LOWER(c.client_type) = 'government'
                ) as sub
                WHERE balance > 0";
        $stmt = $pdo->query($sql);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'count' => (int)($res['pending_accounts'] ?? 0),
            'total' => (float)($res['total_outstanding'] ?? 0)
        ];
    } catch (PDOException $e) {
        return ['count' => 0, 'total' => 0.0];
    }
}

/**
 * Records a manual payment, marks the oldest pending installment as paid,
 * and updates the order's remaining balance.
 */
function record_manual_collection(PDO $pdo, int $orderId, float $amount, string $reference, string $paymentMethod = 'Cash', string $remarks = ''): bool
{
    try {
        $pdo->beginTransaction();

        // 1. Get the transaction ID associated with this order
        $sqlTxn = "SELECT id FROM transactions WHERE order_id = ? LIMIT 1";
        $stmtTxn = $pdo->prepare($sqlTxn);
        $stmtTxn->execute([$orderId]);
        $transId = $stmtTxn->fetchColumn();

        if ($transId) {
            // 2. Find the earliest 'Pending' installment in tracker
            $sqlTracker = "SELECT id FROM payment_tracker 
                           WHERE trans_id = ? AND status = 'Pending' 
                           ORDER BY due_date ASC, id ASC LIMIT 1";
            $stmtTracker = $pdo->prepare($sqlTracker);
            $stmtTracker->execute([$transId]);
            $trackerId = $stmtTracker->fetchColumn();

            if ($trackerId) {
                // 3. Mark tracker as Paid
                $sqlUpdTracker = "UPDATE payment_tracker 
                                  SET status = 'Paid', amount_paid = ?, date_paid = NOW(), reference_no = ?, payment_method = ?, remarks = ? 
                                  WHERE id = ?";
                $stmtUpdTracker = $pdo->prepare($sqlUpdTracker);
                $stmtUpdTracker->execute([$amount, $reference, $paymentMethod, $remarks, $trackerId]);
            }
        }

        // 4. Update Global Order Balance
        $sqlOrder = "UPDATE orders SET balance = GREATEST(0, balance - ?) WHERE id = ?";
        $stmtOrder = $pdo->prepare($sqlOrder);
        $stmtOrder->execute([$amount, $orderId]);

        // 5. If balance is now 0, mark order as Completed and txn as Success
        $sqlFinal = "SELECT balance FROM orders WHERE id = ?";
        $stmtFinal = $pdo->prepare($sqlFinal);
        $stmtFinal->execute([$orderId]);
        if ((float)$stmtFinal->fetchColumn() <= 0) {
            $pdo->prepare("UPDATE orders SET status = 'Completed' WHERE id = ?")->execute([$orderId]);
            if ($transId) {
                $pdo->prepare("UPDATE transactions SET status = 'Success' WHERE id = ?")->execute([$transId]);
            }
        }

        $pdo->commit();

        // Notification: Notify Warehouse if order is now Ongoing/Success and has WH items
        // Check if there's any item from WH for this order
        $stmtWH = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ? AND (get_from = 'WH' OR get_from = 'Warehouse')");
        $stmtWH->execute([$orderId]);
        $hasWH = (int)$stmtWH->fetchColumn() > 0;

        if ($hasWH) {
            $adminId = (int)($_SESSION['user_id'] ?? 0);
            $msg = "Payment recorded for Order #$orderId. Ready for fulfillment.";
            create_notification($pdo, $adminId, 'Order to Fulfill', "Status: Ready\nSummary: $msg", 'fulfillment', null, 'warehouse');
        }

        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Record Collection Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Compatibility functions for legacy support
 */
function get_pending_government_orders(PDO $pdo): array { return get_pending_receivables($pdo, 'Government'); }
function get_total_government_outstanding(PDO $pdo): float { return get_receivables_summary($pdo)['total']; }

/**
 * Updates an order status and records any admin remarks/discounts.
 */
function update_order_status(PDO $pdo, int $orderId, string $status, float $discount, string $comment): bool
{
    try {
        if (!$pdo->inTransaction()) $pdo->beginTransaction();

        // 1. Get the creator and customer name for notification
        $stmtOrder = $pdo->prepare("
            SELECT o.created_by, c.name as customer_name 
            FROM orders o 
            LEFT JOIN customers c ON o.customer_id = c.id 
            WHERE o.id = ?");
        $stmtOrder->execute([$orderId]);
        $orderData = $stmtOrder->fetch(PDO::FETCH_ASSOC);
        $creatorId = (int)($orderData['created_by'] ?? 0);
        $customerName = $orderData['customer_name'] ?? 'Walk-in Customer';

        // 2. Update Order Details
        $sql = "UPDATE orders SET status = ?, admin_discount = ?, comments = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $discount, $comment, $orderId]);

        $pdo->commit();

        // Notification: Admin -> Showroom (Order Update Result)
        if ($creatorId > 0) {
            $adminId = (int)($_SESSION['user_id'] ?? 0);
            $msg = "Order for $customerName has been " . strtolower($status) . ".";
            create_notification($pdo, $adminId, "Order Update", "Status: " . ucfirst($status) . "\nSummary: $msg", 'result', $creatorId);

            // Notification: Admin -> Warehouse (Trigger removed here, moved to Payment step)
        }

        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Update Order Status Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch statistics for the Admin Order Requests page
 */
function get_admin_order_stats(PDO $pdo): array
{
    try {
        // 1. Total Distinct Products
        $totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE is_deleted = 0")->fetchColumn();

        // 2. Total Transactions
        $totalTransactions = $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();

        // 3. Status Breakdown
        $statusStmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
        $statuses = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // 4. Inventory Totals
        // Warehouse (Aggregate Buildable Units)
        $whTotal = $pdo->query("SELECT SUM(wh_stats.v_wh) 
                               FROM (SELECT COALESCE((SELECT FLOOR(MIN(ws.qty_on_hand / pc.qty_needed)) 
                                                     FROM warehouse_stocks ws 
                                                     JOIN product_components pc ON ws.product_comp_id = pc.id 
                                                     WHERE ws.variant_id = pv.id), 0) as v_wh
                                      FROM product_variant pv
                                      JOIN products p ON pv.prod_id = p.id
                                      WHERE p.is_deleted = 0 AND pv.is_deleted = 0) as wh_stats")->fetchColumn();

        // Showroom (Simple sum)
        $srTotal = $pdo->query("SELECT SUM(ss.qty_on_hand) FROM showroom_stocks ss JOIN product_variant pv ON ss.variant_id = pv.id JOIN products p ON pv.prod_id = p.id WHERE p.is_deleted = 0 AND pv.is_deleted = 0")->fetchColumn();

        // 5. Specific Pending Breakdown (from check_counts logic)
        $whPending = $pdo->query("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.status = 'Approved' AND (oi.get_from = 'WH' OR oi.get_from = 'Warehouse') AND o.wh_status != 'Released'")->fetchColumn();
        $srPending = $pdo->query("SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.status = 'For Review' AND (oi.get_from = 'SR' OR oi.get_from = 'Showroom')")->fetchColumn();

        return [
            'total_products' => (int)$totalProducts,
            'total_transactions' => (int)$totalTransactions,
            'pending_requests' => (int)($statuses['For Review'] ?? 0),
            'approved_requests' => (int)($statuses['Approved'] ?? 0),
            'rejected_requests' => (int)($statuses['Rejected'] ?? 0),
            'wh_total' => (int)$whTotal,
            'sr_total' => (int)$srTotal,
            'wh_pending_count' => (int)$whPending,
            'sr_pending_count' => (int)$srPending
        ];
    } catch (PDOException $e) {
        error_log("Get Admin Stats Error: " . $e->getMessage());
        return [];
    }
}

function get_sales_report_data(PDO $pdo, ?string $start = null, ?string $end = null, ?string $status = null, ?string $plan = null, ?string $client_type = null): array
{
    try {
        $params = [];
        $sql = "SELECT 
                    t.id as trans_id,
                    o.id as order_id,
                    t.transaction_date,
                    t.amount as amount_paid,
                    t.status as trans_status,
                    t.payment_type as plan,
                    t.or_number,
                    o.status as order_status,
                    o.payment_mode,
                    COALESCE(c.name, o.temp_customer_name) as customer_name,
                    COALESCE(c.client_type, 'Private') as client_type
                FROM transactions t
                JOIN orders o ON t.order_id = o.id
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE 1=1";

        if ($start) {
            $sql .= " AND t.transaction_date >= ?";
            $params[] = $start . " 00:00:00";
        }
        if ($end) {
            $sql .= " AND t.transaction_date <= ?";
            $params[] = $end . " 23:59:59";
        }
        if ($status && $status !== 'All') {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }
        if ($plan && $plan !== 'All') {
            $sql .= " AND t.payment_type = ?";
            $params[] = $plan;
        }
        if ($client_type && $client_type !== 'All') {
            $sql .= " AND COALESCE(c.client_type, 'Private') = ?";
            $params[] = $client_type;
        }

        $sql .= " ORDER BY t.transaction_date DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get Sales Report Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches all orders with optional status filtering for reporting.
 */
function get_all_orders_data(PDO $pdo, ?string $status = null): array
{
    try {
        $params = [];
        $sql = "SELECT 
                    o.id,
                    COALESCE(c.name, o.temp_customer_name) as customer_name,
                    COALESCE(c.client_type, 'Private') as client_type,
                    u.full_name as requested_by,
                    u.role as creator_role,
                    o.status,
                    o.total_ammount as amount,
                    o.balance,
                    o.created_at
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN users u ON o.created_by = u.id
                WHERE 1=1";

        if ($status && $status !== 'All') {
            $sql .= " AND o.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY o.created_at DESC, o.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get All Orders Report Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches monthly sales trend for the last 6 months
 */
function get_dashboard_sales_trend(PDO $pdo): array
{
    try {
        $sql = "SELECT 
                    DATE_FORMAT(transaction_date, '%b') as month,
                    SUM(amount) as total_sales,
                    DATE_FORMAT(transaction_date, '%Y-%m') as sort_key
                FROM transactions
                WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY month, sort_key
                ORDER BY sort_key ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Sales Trend Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches inventory levels grouped by category and location
 */
function get_dashboard_inventory_analytics(PDO $pdo): array
{
    try {
        $sql = "SELECT 
                    p.category,
                    COALESCE(SUM(ws.qty_on_hand), 0) as wh_qty,
                    COALESCE((SELECT SUM(ss.qty_on_hand) 
                              FROM showroom_stocks ss 
                              JOIN product_variant pv2 ON ss.variant_id = pv2.id 
                              JOIN products p2 ON pv2.prod_id = p2.id 
                              WHERE p2.category = p.category AND p2.is_deleted = 0), 0) as sr_qty
                FROM products p
                LEFT JOIN warehouse_stocks ws ON p.id = ws.prod_id
                WHERE p.category IS NOT NULL AND p.category != '' AND p.is_deleted = 0
                GROUP BY p.category
                LIMIT 5";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Inventory Analytics Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches the most recent orders for the dashboard table
 */
function get_recent_orders(PDO $pdo, int $limit = 5): array
{
    try {
        $sql = "SELECT id, status, total_ammount as total, created_at
                FROM orders 
                ORDER BY created_at DESC 
                LIMIT " . (int)$limit;
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get Recent Orders Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches revenue trends for the last 6 months for Chart.js
 */
function get_monthly_sales_trend(PDO $pdo, ?string $start = null, ?string $end = null): array
{
    try {
        $params = [];
        $whereSql = "WHERE status = 'Success'";

        if ($start) {
            $whereSql .= " AND transaction_date >= ?";
            $params[] = $start . " 00:00:00";
        }
        if ($end) {
            $whereSql .= " AND transaction_date <= ?";
            $params[] = $end . " 23:59:59";
        }

        // Fallback to last 6 months if no filters
        if (!$start && !$end) {
            $whereSql .= " AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
        }

        $sql = "SELECT DATE_FORMAT(transaction_date, '%b %Y') as month_name, SUM(amount) as revenue 
                FROM transactions 
                $whereSql 
                GROUP BY DATE_FORMAT(transaction_date, '%Y-%m'), month_name
                ORDER BY MIN(transaction_date) ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($data)) {
            return [['month_name' => date('M Y'), 'revenue' => 0]];
        }
        return $data;
    } catch (PDOException $e) {
        error_log("Sales Trend Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Calculates counts for Stock Health Doughnut chart
 */
function get_inventory_health_stats(PDO $pdo): array
{
    try {
        // We aggregate the buildable warehouse quantity for each variant
        // Healthy: > threshold, Low: <= threshold, Out: 0
        $sql = "SELECT 
                    SUM(CASE WHEN wh_qty > min_buildable_qty THEN 1 ELSE 0 END) as healthy,
                    SUM(CASE WHEN wh_qty <= min_buildable_qty AND wh_qty > 0 THEN 1 ELSE 0 END) as low_stock,
                    SUM(CASE WHEN wh_qty = 0 THEN 1 ELSE 0 END) as out_of_stock,
                    COUNT(*) as total_variants
                FROM (
                    SELECT pv.id, pv.min_buildable_qty,
                           COALESCE((SELECT FLOOR(MIN(ws.qty_on_hand / pc.qty_needed)) 
                                     FROM warehouse_stocks ws 
                                     JOIN product_components pc ON ws.product_comp_id = pc.id 
                                     WHERE ws.variant_id = pv.id), 0) as wh_qty
                    FROM product_variant pv
                    JOIN products p ON pv.prod_id = p.id
                    WHERE p.is_deleted = 0 AND pv.is_deleted = 0
                ) as stats";
        $stmt = $pdo->query($sql);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'healthy' => (int)($res['healthy'] ?? 0),
            'low_stock' => (int)($res['low_stock'] ?? 0),
            'out_of_stock' => (int)($res['out_of_stock'] ?? 0),
            'total_variants' => (int)($res['total_variants'] ?? 0)
        ];
    } catch (PDOException $e) {
        error_log("Health Stats Error: " . $e->getMessage());
        return ['healthy' => 0, 'low_stock' => 0, 'out_of_stock' => 0, 'total_variants' => 0];
    }
}

/**
 * Fetches top performing products by volume
 */
function get_top_performing_products(PDO $pdo, int $limit = 5, string $period = 'all'): array
{
    try {
        $whereSql = "WHERE p.is_deleted = 0";
        $params = [];

        if ($period === 'this_month') {
            $whereSql .= " AND t.first_txn_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        } else if ($period === 'last_month') {
            $whereSql .= " AND t.first_txn_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH) 
                           AND t.first_txn_date < DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        }

        $sql = "SELECT p.name, pv.variant, SUM(oi.qty) as total_sold, pv.variant_image, p.default_image
                FROM order_items oi
                JOIN product_variant pv ON oi.variant_id = pv.id
                JOIN products p ON pv.prod_id = p.id
                JOIN (
                    SELECT order_id, MIN(transaction_date) as first_txn_date
                    FROM transactions 
                    WHERE status = 'Success'
                    GROUP BY order_id
                ) t ON oi.order_id = t.order_id
                $whereSql
                GROUP BY pv.id
                ORDER BY total_sold DESC
                LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Top Products Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Categorical breakdown for Radar/Polar charts
 */
function get_category_distribution(PDO $pdo): array
{
    try {
        $sql = "SELECT category, COUNT(*) as count FROM products WHERE is_deleted = 0 GROUP BY category ORDER BY count DESC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Category Dist Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Warehouse Stock-In summary for the current month
 */
function get_monthly_stock_in_stats(PDO $pdo): array
{
    try {
        // Sum of positive adjustments in warehouse logs
        $sql = "SELECT SUM(qty) as total_qty FROM warehouse_logs WHERE qty > 0 AND log_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        $stmt = $pdo->query($sql);
        $qty = (int)$stmt->fetchColumn();

        // Estimated cost based on current product prices
        $sqlCost = "SELECT SUM(wl.qty * p.price) 
                    FROM warehouse_logs wl
                    JOIN warehouse_stocks ws ON wl.wh_stock_id = ws.id
                    JOIN products p ON ws.prod_id = p.id
                    WHERE wl.qty > 0 AND wl.log_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND p.is_deleted = 0";
        $cost = (float)$pdo->query($sqlCost)->fetchColumn();

        return [
            'qty_ordered' => $qty,
            'total_cost' => $cost
        ];
    } catch (PDOException $e) {
        return ['qty_ordered' => 0, 'total_cost' => 0];
    }
}

/**
 * Fetches total and monthly sales revenue statistics
 */
function get_revenue_stats(PDO $pdo): array
{
    try {
        // Overall Revenue (Success only)
        $totalSql = "SELECT SUM(amount) FROM transactions WHERE status = 'Success'";
        $total = (float)$pdo->query($totalSql)->fetchColumn();

        // Monthly Revenue (Current Month)
        $monthlySql = "SELECT SUM(amount) FROM transactions 
                       WHERE status = 'Success' 
                       AND transaction_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        $monthly = (float)$pdo->query($monthlySql)->fetchColumn();

        return [
            'total' => $total,
            'monthly' => $monthly
        ];
    } catch (PDOException $e) {
        error_log("Get Revenue Stats Error: " . $e->getMessage());
        return ['total' => 0.0, 'monthly' => 0.0];
    }
}

/**
 * Fetches the most recent stock logs for the Showroom (SR).
 */
function get_sr_stock_logs(PDO $pdo, int $limit = 3, ?string $fromDate = null, ?string $toDate = null): array
{
    try {
        $whereClauses = [];
        if ($fromDate) {
            $whereClauses[] = "DATE(sl.log_date) >= :from_date";
        }
        if ($toDate) {
            $whereClauses[] = "DATE(sl.log_date) <= :to_date";
        }
        
        $whereClause = "";
        if (!empty($whereClauses)) {
            $whereClause = "WHERE " . implode(" AND ", $whereClauses);
        }

        $sql = "SELECT 
                    sl.qty,
                    sl.log_date,
                    p.name as product_name,
                    p.code as prod_code,
                    pv.variant as variant_name
                FROM showroom_logs sl
                LEFT JOIN product_variant pv ON sl.variant_id = pv.id
                LEFT JOIN products p ON pv.prod_id = p.id
                $whereClause
                ORDER BY sl.log_date DESC, sl.log_id DESC
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($fromDate) {
            $stmt->bindValue(':from_date', $fromDate, PDO::PARAM_STR);
        }
        if ($toDate) {
            $stmt->bindValue(':to_date', $toDate, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Get SR Stock Logs Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches the most recent stock logs for the Warehouse (WH).
 */
function get_wh_stock_logs(PDO $pdo, int $limit = 3, ?string $fromDate = null, ?string $toDate = null): array
{
    try {
        $whereClauses = [];
        if ($fromDate) {
            $whereClauses[] = "DATE(wl.log_date) >= :from_date";
        }
        if ($toDate) {
            $whereClauses[] = "DATE(wl.log_date) <= :to_date";
        }

        $whereClause = "";
        if (!empty($whereClauses)) {
            $whereClause = "WHERE " . implode(" AND ", $whereClauses);
        }

        $sql = "SELECT 
                    wl.qty,
                    wl.log_date,
                    COALESCE(p_new.name, p_old.name) as product_name,
                    COALESCE(p_new.code, p_old.code) as prod_code,
                    COALESCE(pv_new.variant, pv_old.variant) as variant_name
                FROM warehouse_logs wl
                LEFT JOIN products p_new ON wl.prod_id = p_new.id
                LEFT JOIN product_variant pv_new ON wl.variant_id = pv_new.id
                LEFT JOIN warehouse_stocks ws ON wl.comp_id = ws.id AND wl.prod_id IS NULL
                LEFT JOIN products p_old ON ws.prod_id = p_old.id
                LEFT JOIN product_variant pv_old ON ws.variant_id = pv_old.id
                $whereClause
                ORDER BY wl.log_date DESC, wl.log_id DESC
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($fromDate) {
            $stmt->bindValue(':from_date', $fromDate, PDO::PARAM_STR);
        }
        if ($toDate) {
            $stmt->bindValue(':to_date', $toDate, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Get WH Stock Logs Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Status summary for the Sales Order Status table breakdown
 * Groups by Source (Admin/POS vs Showroom) and Order/WH Status
 */
function get_order_status_summary(PDO $pdo): array
{
    try {
        $sql = "SELECT 
                    u.role as source_role,
                    SUM(CASE WHEN o.status = 'Approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN o.wh_status = 'Partial' THEN 1 ELSE 0 END) as partial,
                    SUM(CASE WHEN o.wh_status = 'Released' OR o.wh_status = 'Shipped' THEN 1 ELSE 0 END) as shipped,
                    SUM(CASE WHEN o.wh_status = 'Delivered' THEN 1 ELSE 0 END) as delivered
                FROM orders o
                JOIN users u ON o.created_by = u.id
                GROUP BY u.role";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Order Status Summary Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches the most recent pending order requests for the dashboard
 */
function get_pending_order_requests(PDO $pdo, int $limit = 5, bool $onlyForReview = true): array
{
    try {
        $whereClause = $onlyForReview ? "WHERE o.status = 'For Review' OR o.status = 'Pending'" : "";

        $sql = "SELECT 
                    o.id, 
                    o.status,
                    COALESCE(c.name, o.temp_customer_name) as customer_name,
                    o.total_ammount as amount,
                    o.created_at,
                    u.full_name as requested_by,
                    o.status
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN users u ON o.created_by = u.id
                $whereClause
                ORDER BY 
                    o.created_at DESC, 
                    o.id DESC,
                    CASE o.status 
                        WHEN 'For Review' THEN 1 
                        WHEN 'Pending' THEN 1 
                        WHEN 'Approved' THEN 2 
                        WHEN 'Rejected' THEN 3 
                        WHEN 'Cancelled' THEN 4 
                        ELSE 5 
                    END ASC
                LIMIT " . (int)$limit;
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get Pending Requests Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches all items associated with a specific order ID
 */
function get_order_items_report(PDO $pdo, int $orderId): array
{
    try {
        $sql = "SELECT 
                    p.name as prod_name, 
                    pv.variant, 
                    oi.get_from as location, 
                    oi.qty as quantity, 
                    oi.unit_price as price 
                FROM order_items oi
                JOIN product_variant pv ON oi.variant_id = pv.id
                JOIN products p ON pv.prod_id = p.id
                WHERE oi.order_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get Order Items Report Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches a comprehensive summary of an order and its associated transaction by its ID
 */
function get_order_summary_by_id(PDO $pdo, int $orderId): ?array
{
    try {
        $sql = "SELECT 
                    o.id, 
                    COALESCE(c.name, o.temp_customer_name) as customer_name,
                    COALESCE(c.client_type, 'Private / Individual') as client_type,
                    COALESCE(c.contact_no, 'N/A') as contact_no,
                    u.full_name as requested_by,
                    u.role as creator_role,
                    o.payment_mode,
                    o.total_ammount as total,
                    o.balance,
                    o.status as order_status,
                    o.created_at,
                    t.id as trans_id,
                    t.or_number,
                    t.payment_type,
                    t.amount as principal_amount,
                    t.interest as interest_rate,
                    t.total_with_interest,
                    t.transaction_date
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                LEFT JOIN users u ON o.created_by = u.id
                LEFT JOIN transactions t ON o.id = t.order_id
                WHERE o.id = ?
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log("Get Order Summary Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Fetches the payment schedule/tracker records for a specific transaction
 */
function get_payment_schedule_by_trans_id(PDO $pdo, int $transId): array
{
    try {
        $sql = "SELECT id, amount_paid, date_paid, due_date, status, remarks 
                FROM payment_tracker 
                WHERE trans_id = ? 
                ORDER BY due_date ASC, id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$transId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get Payment Schedule Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches global order history for the History Log sidebar
 * Includes customer details, government branch, and amount paid.
 */
function get_global_order_history(PDO $pdo, int $limit = 10, string $search = ''): array
{
    try {
        $params = [];
        $sql = "SELECT 
                    t.id as trans_id,
                    t.transaction_date,
                    t.amount as amount_paid,
                    t.status as trans_status,
                    o.id as order_id,
                    COALESCE(c.name, o.temp_customer_name) as customer_name,
                    c.contact_no,
                    COALESCE(c.client_type, 'Private / Individual') as client_type,
                    c.gov_branch,
                    o.status as order_status
                FROM transactions t
                JOIN orders o ON t.order_id = o.id
                LEFT JOIN customers c ON o.customer_id = c.id";

        if (!empty($search)) {
            $sql .= " WHERE c.name LIKE ? OR o.temp_customer_name LIKE ? OR t.id LIKE ? OR o.id LIKE ?";
            $searchTerm = "%$search%";
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }

        $sql .= " ORDER BY t.transaction_date DESC, t.id DESC LIMIT " . (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get Global History Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches advanced system diagnostics (counts of all key tables)
 * Consolidates functionality from check.php and check_counts.php
 */
function get_system_diagnostic_data(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $counts = [];
        foreach ($tables as $t) {
            $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            $counts[$t] = $count;
        }

        // Get DB details
        $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $dbVersion = $pdo->query("SELECT VERSION()")->fetchColumn();

        return [
            'tables' => $tables,
            'counts' => $counts,
            'db_name' => $dbName,
            'db_version' => $dbVersion,
            'status' => 'Operational'
        ];
    } catch (PDOException $e) {
        error_log("Diagnostics Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches schema details for a specific table
 * Consolidates functionality from check_db.php and schema_dump.php
 */
function get_table_schema(PDO $pdo, string $tableName): array
{
    try {
        $stmt = $pdo->query("DESCRIBE `$tableName` ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Executes a full database migration from the resources SQL file.
 * Consolidates functionality from migrate.php
 * WARNING: This is destructive.
 */
function run_database_migration(PDO $pdo): array
{
    try {
        $sqlPath = __DIR__ . '/../../resources/pis-sys-db.sql';
        if (!file_exists($sqlPath)) {
            throw new Exception("Migration file not found at " . basename($sqlPath));
        }

        $sql = file_get_contents($sqlPath);
        
        // Use exec for multi-statement execution if supported, or handle separately
        // Note: $pdo->exec() works for multiple statements in some drivers
        $pdo->exec($sql);
        
        return ['success' => true, 'message' => 'Migration successful. Database has been reset.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Fetches a complete list of all products and variants with their respective stock levels
 * for the Full System Report.
 */
function get_full_inventory_report_data(PDO $pdo): array
{
    try {
        $sql = "SELECT 
                    p.code, p.name as prod_name, p.category, p.price,
                    pv.variant as variant_name,
                    COALESCE((SELECT ss.qty_on_hand FROM showroom_stocks ss WHERE ss.variant_id = pv.id LIMIT 1), 0) as sr_qty,
                    COALESCE((SELECT FLOOR(MIN(ws.qty_on_hand / pc.qty_needed)) 
                              FROM warehouse_stocks ws 
                              JOIN product_components pc ON ws.product_comp_id = pc.id 
                              WHERE ws.variant_id = pv.id), 0) as wh_qty
                FROM products p
                JOIN product_variant pv ON p.id = pv.prod_id
                WHERE p.is_deleted = 0 AND pv.is_deleted = 0
                ORDER BY p.category ASC, p.name ASC, pv.variant ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get Full Inventory Report Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches transaction records for the comprehensive system report section.
 */
function get_report_transactions(PDO $pdo, int $limit = 50, string $from = null, string $to = null): array
{
    try {
        $params = [];
        $sql = "SELECT 
                    t.id as trans_id,
                    t.transaction_date,
                    t.amount,
                    t.payment_type,
                    t.status as trans_status,
                    COALESCE(c.name, o.temp_customer_name) as customer_name,
                    COALESCE(c.client_type, 'Private / Individual') as client_type
                FROM transactions t
                JOIN orders o ON t.order_id = o.id
                LEFT JOIN customers c ON o.customer_id = c.id";

        $conditions = [];
        if(!empty($from)) {
            $conditions[] = "t.transaction_date >= ?";
            $params[] = $from . ' 00:00:00';
        }
        if(!empty($to)) {
            $conditions[] = "t.transaction_date <= ?";
            $params[] = $to . ' 23:59:59';
        }

        if(!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY t.transaction_date DESC LIMIT " . (int)$limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Report Transactions Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches a list of customers with aggregated order statistics.
 */
function get_report_customers(PDO $pdo, int $limit = 100): array
{
    try {
        $sql = "SELECT 
                    c.id, c.name, c.contact_no, c.client_type, c.gov_branch,
                    COUNT(o.id) as total_orders,
                    SUM(o.total_ammount) as total_spend
                FROM customers c
                LEFT JOIN orders o ON c.id = o.customer_id AND o.status != 'Cancelled'
                GROUP BY c.id
                ORDER BY total_spend DESC, c.name ASC
                LIMIT " . (int)$limit;
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Report Customers Error: " . $e->getMessage());
        return [];
    }
}

