<?php

declare(strict_types=1);

require_once __DIR__ . '/../global.model.php';

/**
 * Fetches order requests. If $userId is provided (>0), filters by that user.
 */
function fetch_requests(PDO $pdo, int $userId = 0): array
{
    try {
        $sql = "SELECT 
                    o.*, 
                    u.full_name, 
                    o.id as pr_no,
                    o.admin_discount as discount,
                    o.comments as comment,
                    o.created_at as date,
                    c.name AS customer_name
                FROM orders o
                LEFT JOIN users u ON o.created_by = u.id
                LEFT JOIN customers c ON o.customer_id = c.id";
        
        if ($userId > 0) {
            $sql .= " WHERE o.created_by = :user_id";
        }
        
        $sql .= " ORDER BY o.id DESC";

        $stmt = $pdo->prepare($sql);
        if ($userId > 0) {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Fetch Requests Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Process the finalization of an order request in the showroom.
 */
function process_showroom_finalize_order(PDO $pdo, array $data): array
{
    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }

        $userId = (int)($data['user_id'] ?? 0);
        $orderId = (int)($data['order_id'] ?? 0);
        if ($orderId <= 0) throw new Exception("Invalid Order ID");

        // 1. Get or Create Customer
        $customerId = get_or_create_customer($pdo, [
            'name'        => $data['customer_name'],
            'contact_no'  => $data['contact_no'],
            'client_type' => $data['clientType'],
            'gov_branch'  => $data['govBranch'] ?? null
        ]);

        // 2. Update Order
        $sql = "UPDATE orders SET 
                    customer_id = ?, 
                    temp_customer_name = ?, 
                    shipping_type = ?, 
                    delivery_address = ?, 
                    status = 'Success',
                    balance = ?,
                    wh_status = 'To Release',
                    payment_mode = ?,
                    admin_discount = ?,
                    comments = ?
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $customerId,
            $data['customer_name'],
            $data['shipping_type'],
            $data['delivery_address'] ?? '',
            (float)($data['balance'] ?? 0),
            $data['paymentMethod'],
            (float)($data['adminDiscount'] ?? 0),
            $data['paymentRemarks'] ?? '',
            $orderId
        ]);

        // 3. Process Items & Deduct Stock
        $stmtItems = $pdo->prepare("SELECT variant_id, qty, get_from FROM order_items WHERE order_id = ?");
        $stmtItems->execute([$orderId]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $neededQty = (int)$item['qty'];
            if ($item['get_from'] === 'SR') {
                // A. Showroom Stock Locking & Validation
                $stmtCheck = $pdo->prepare("SELECT qty_on_hand FROM showroom_stocks WHERE variant_id = ? FOR UPDATE");
                $stmtCheck->execute([$item['variant_id']]);
                $current = (int)($stmtCheck->fetchColumn() ?: 0);

                if ($current < $neededQty) {
                    throw new Exception("Insufficient stock in Showroom for Order #$orderId. Available: $current, Needed: $neededQty");
                }

                $pdo->prepare("UPDATE showroom_stocks SET qty_on_hand = qty_on_hand - ? WHERE variant_id = ?")
                    ->execute([$neededQty, $item['variant_id']]);
                
                $pdo->prepare("INSERT INTO showroom_logs (variant_id, action, qty) VALUES (?, ?, ?)")
                    ->execute([$item['variant_id'], "Showroom Sale (Finalized Request #$orderId)", -$neededQty]);
            } else {
                // B. Warehouse Stock Locking & Validation (Recipe Based)
                $stmtRecipe = $pdo->prepare("SELECT pc.id, pc.comp_id, pc.qty_needed 
                                           FROM product_components pc 
                                           JOIN product_variant pv ON pc.prod_id = pv.prod_id 
                                           WHERE pv.id = ? AND pc.is_deleted = 0");
                $stmtRecipe->execute([$item['variant_id']]);
                $recipe = $stmtRecipe->fetchAll(PDO::FETCH_ASSOC);

                if (empty($recipe)) throw new Exception("Product recipe missing for variant ID: " . $item['variant_id']);

                foreach ($recipe as $r) {
                    $deduction = $neededQty * (int)$r['qty_needed'];

                    // Lock and Check Warehouse Component Row
                    $stmtCheckWH = $pdo->prepare("SELECT qty_on_hand FROM warehouse_stocks WHERE variant_id = ? AND product_comp_id = ? FOR UPDATE");
                    $stmtCheckWH->execute([$item['variant_id'], $r['id']]);
                    $currentWH = (int)($stmtCheckWH->fetchColumn() ?: 0);

                    if ($currentWH < $deduction) {
                        throw new Exception("Insufficient warehouse component stock for one or more items in Order #$orderId.");
                    }

                    $pdo->prepare("UPDATE warehouse_stocks SET qty_on_hand = qty_on_hand - ? WHERE variant_id = ? AND product_comp_id = ?")
                        ->execute([$deduction, $item['variant_id'], $r['id']]);
                    
                    $pdo->prepare("INSERT INTO warehouse_logs (comp_id, action, qty) VALUES (?, ?, ?)")
                        ->execute([$r['comp_id'], "Sold (Finalized Showroom Request #$orderId)", -$deduction]);
                }
            }
        }

        // 4. Record Transaction
        $sqlTrans = "INSERT INTO transactions (order_id, transaction_date, or_number, amount, interest, total_with_interest, installment_term, status) 
                     VALUES (?, CURDATE(), ?, ?, ?, ?, ?, 'Success')";
        $pdo->prepare($sqlTrans)->execute([
            $orderId,
            $data['paymentRef'] ?: ('SR-REF-' . $orderId),
            (float)($data['amountPaid'] ?? 0),
            (float)($data['interestRate'] ?? 0),
            (float)($data['totalWithInterest'] ?? 0),
            (int)($data['installmentTerm'] ?? 0)
        ]);
        $transId = (int)$pdo->lastInsertId();

        // 5. Payment Tracker
        $sqlPay = "INSERT INTO payment_tracker (trans_id, amount_paid, date_paid, payment_method, reference_no, remarks) 
                   VALUES (?, ?, CURDATE(), ?, ?, ?)";
        $pdo->prepare($sqlPay)->execute([
            $transId,
            (float)($data['amountPaid'] ?? 0),
            $data['paymentMethod'],
            $data['paymentRef'] ?: ('SR-REF-' . $orderId),
            $data['paymentRemarks'] ?: "Finalized Showroom Request"
        ]);

        $pdo->commit();
        return ['success' => true];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Finalize Order Error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function get_customer_history(PDO $pdo, string $customerName): array
{
    try {
        $sql = "SELECT o.id as pr_no, o.created_at as date, o.status, o.total_ammount
                FROM orders o
                JOIN customers c ON o.customer_id = c.id
                WHERE c.name = :customer_name 
                ORDER BY o.created_at DESC LIMIT 10";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':customer_name' => $customerName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("History Fetch Error: " . $e->getMessage());
        return [];
    }
}

function cancel_product_request(PDO $pdo, string $prNo): bool
{
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ?");
        return $stmt->execute([$prNo]);
    } catch (PDOException $e) {
        error_log("Cancel Request Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetches transaction history for a specific Showroom user.
 */
function fetch_sr_transaction_history(PDO $pdo, int $userId): array
{
    try {
        $sql = "SELECT 
                    t.id AS trans_id,
                    c.name AS customer_name,
                    c.client_type,
                    c.gov_branch,
                    o.payment_mode AS method,
                    t.transaction_date AS date,
                    t.status,
                    t.amount
                FROM transactions t
                JOIN orders o ON t.order_id = o.id
                JOIN customers c ON o.customer_id = c.id
                WHERE o.created_by = :user_id
                ORDER BY t.transaction_date DESC, t.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Fetch SR Transaction History Error: " . $e->getMessage());
        return [];
    }
}
