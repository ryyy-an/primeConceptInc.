<?php

declare(strict_types=1);

/**
 * Global Model for shared POS and product catalog database logic.
 * Serves both Admin and Showroom interfaces.
 */

function get_inventory_cards(PDO $pdo): array
{
    // Added safety check for pc.qty_needed to avoid division by zero
    $sql = "SELECT 
                p.id AS prod_id,
                p.code,
                p.name,
                p.description AS `desc`,
                p.category,
                p.default_image,
                p.price,
                p.discount,
                p.is_on_sale,
                pv.id AS variant_id,
                pv.variant,
                pv.min_buildable_qty,
                SUM(COALESCE(ss.qty_on_hand, 0)) AS variant_stocks_in_SR,
                MAX(COALESCE(ws_agg.buildable_qty, 0)) AS variant_stocks_in_WH,
                (SUM(COALESCE(ss.qty_on_hand, 0)) + MAX(COALESCE(ws_agg.buildable_qty, 0))) AS overall_stocks,
                loc_agg.locations
            FROM 
                products p
            LEFT JOIN 
                product_variant pv ON p.id = pv.prod_id AND pv.is_deleted = 0
            LEFT JOIN 
                showroom_stocks ss ON pv.id = ss.variant_id
            LEFT JOIN (
                SELECT 
                    ws.variant_id, 
                    FLOOR(MIN(ws.qty_on_hand / NULLIF(pc.qty_needed, 0))) as buildable_qty
                FROM warehouse_stocks ws
                JOIN product_components pc ON ws.product_comp_id = pc.id
                GROUP BY ws.variant_id
            ) ws_agg ON pv.id = ws_agg.variant_id
            LEFT JOIN (
                SELECT prod_id, GROUP_CONCAT(DISTINCT location SEPARATOR ', ') as locations
                FROM product_components
                WHERE is_deleted = 0
                GROUP BY prod_id
            ) loc_agg ON p.id = loc_agg.prod_id
            WHERE p.is_deleted = 0
            GROUP BY pv.id, p.id
            ORDER BY p.name ASC";

    $stmt = $pdo->query($sql);
    if (!$stmt) return [];
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $products = [];
    foreach ($results as $row) {
        $pid = $row['prod_id'];

        if (!isset($products[$pid])) {
            $rawFileName = trim($row['default_image'] ?? 'default-placeholder.png');
            $encodedFileName = rawurlencode($rawFileName);
            $imagePath = "../../public/assets/img/furnitures/" . $encodedFileName;

            $products[$pid] = [
                'code'        => $row['code'],
                'name'        => $row['name'],
                'desc'        => $row['desc'],
                'category'    => $row['category'] ?? 'Uncategorized',
                'price'       => (float)$row['price'],
                'discount'    => (int)($row['discount'] ?? 0),
                'is_on_sale'  => (bool)($row['is_on_sale'] ?? false),
                'image'       => $imagePath,
                'locations'   => $row['locations'] ?? 'N/A',
                'placeholder' => "../../public/assets/img/furnitures/default-placeholder.png",
                'total_sr'    => 0,
                'total_wh'    => 0,
                'overall'     => 0,
                'variants'    => []
            ];

            $products[$pid]['variants_seen'] = [];
        }

        $v_id = (int)$row['variant_id'];

        if ($v_id && !in_array($v_id, $products[$pid]['variants_seen'])) {
            $v_sr = (int)$row['variant_stocks_in_SR'];
            $v_wh = (int)$row['variant_stocks_in_WH'];

            $products[$pid]['total_sr'] += $v_sr;
            $products[$pid]['total_wh'] += $v_wh;
            $products[$pid]['overall'] += ($v_sr + $v_wh);

            $products[$pid]['variants'][] = [
                'id'             => $v_id,
                'name'           => $row['variant'],
                'sr'             => $v_sr,
                'wh'             => $v_wh,
                'buildable'      => (int)($row['min_buildable_qty'] ?? 0)
            ];

            $products[$pid]['variants_seen'][] = $v_id;
        }
    }

    foreach ($products as &$p) {
        unset($p['variants_seen']);
    }

    return $products;
}

function add_to_cart(PDO $pdo, int $userId, int $variantId, int $qty, string $source): bool
{
    try {
        $stockSql = ($source === 'SR')
            ? "SELECT qty_on_hand FROM showroom_stocks WHERE variant_id = ?"
            : "SELECT MIN(qty_on_hand) FROM warehouse_stocks WHERE variant_id = ?";

        $stockStmt = $pdo->prepare($stockSql);
        $stockStmt->execute([$variantId]);
        $availableStock = (int)$stockStmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT id, qty FROM cart WHERE user_id = ? AND variant_id = ? AND source = ?");
        $stmt->execute([$userId, $variantId, $source]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $currentCartQty = $existing ? (int)$existing['qty'] : 0;
        $totalPotentialQty = $currentCartQty + $qty;

        if ($totalPotentialQty > $availableStock) {
            return false;
        }

        if ($existing) {
            $updateStmt = $pdo->prepare("UPDATE cart SET qty = ? WHERE id = ?");
            return $updateStmt->execute([$totalPotentialQty, $existing['id']]);
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO cart (user_id, variant_id, qty, source) VALUES (?, ?, ?, ?)");
            return $insertStmt->execute([$userId, $variantId, $qty, $source]);
        }
    } catch (PDOException $e) {
        error_log("Add to Cart Error: " . $e->getMessage());
        return false;
    }
}

function get_cart_items(PDO $pdo, int $userId): array
{
    try {
        $sql = "SELECT 
                    c.id as cart_id,
                    c.user_id,
                    c.variant_id,
                    c.qty,
                    c.source,
                    p.id as prod_id,
                    p.name,
                    p.price,
                    pv.variant,
                    pv.variant_image,
                    p.default_image,
                    CASE 
                        WHEN c.source = 'SR' THEN COALESCE(ss.qty_on_hand, 0)
                        WHEN c.source = 'WH' THEN COALESCE(ws_agg.min_qty, 0)
                        ELSE 0
                    END AS available_stock
                FROM cart c
                JOIN product_variant pv ON c.variant_id = pv.id AND pv.is_deleted = 0
                JOIN products p ON pv.prod_id = p.id
                LEFT JOIN showroom_stocks ss ON pv.id = ss.variant_id
                LEFT JOIN (
                    SELECT variant_id, MIN(qty_on_hand) as min_qty 
                    FROM warehouse_stocks 
                    GROUP BY variant_id
                ) ws_agg ON pv.id = ws_agg.variant_id
                WHERE c.user_id = ? AND p.is_deleted = 0
                ORDER BY c.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cartItems = [];
        foreach ($results as $row) {
            $img = !empty($row['variant_image']) ? $row['variant_image'] : $row['default_image'];
            $encodedFileName = rawurlencode(trim($img ?? 'default-placeholder.png'));
            $imagePath = "../../public/assets/img/furnitures/" . $encodedFileName;

            $cartItems[] = [
                'cart_id'         => $row['cart_id'],
                'prod_id'         => $row['prod_id'],
                'variant_id'      => $row['variant_id'],
                'name'            => $row['name'],
                'variant'         => $row['variant'],
                'qty'             => (int)$row['qty'],
                'price'           => (float)$row['price'],
                'source'          => $row['source'],
                'image'           => $imagePath,
                'available_stock' => (int)$row['available_stock']
            ];
        }
        return $cartItems;
    } catch (PDOException $e) {
        error_log("Get Cart Items Error: " . $e->getMessage());
        return [];
    }
}



function get_order_details_shared(PDO $pdo, int $orderId): array
{
    try {
        $sqlHeader = "SELECT o.*, u.full_name as requested_by, COALESCE(c.name, o.temp_customer_name) AS customer_name 
                      FROM orders o 
                      LEFT JOIN users u ON o.created_by = u.id 
                      LEFT JOIN customers c ON o.customer_id = c.id
                      WHERE o.id = ?";
        $stmtHeader = $pdo->prepare($sqlHeader);
        $stmtHeader->execute([$orderId]);
        $details = $stmtHeader->fetch(PDO::FETCH_ASSOC);

        if (!$details) return [];

        $sqlItems = "SELECT 
                        p.name, 
                        pv.variant, 
                        oi.get_from as location, 
                        oi.qty, 
                        oi.unit_price, 
                        p.category,
                        CASE 
                            WHEN oi.get_from = 'SR' THEN COALESCE(ss.qty_on_hand, 0)
                            WHEN oi.get_from = 'WH' THEN COALESCE(ws_agg.min_qty, 0)
                            ELSE 0
                        END AS current_stock
                     FROM order_items oi
                     JOIN product_variant pv ON oi.variant_id = pv.id AND pv.is_deleted = 0
                     JOIN products p ON pv.prod_id = p.id
                     LEFT JOIN showroom_stocks ss ON pv.id = ss.variant_id
                     LEFT JOIN (
                        SELECT variant_id, MIN(qty_on_hand) as min_qty 
                        FROM warehouse_stocks 
                        GROUP BY variant_id
                     ) ws_agg ON pv.id = ws_agg.variant_id
                     WHERE oi.order_id = ?";
        $stmtItems = $pdo->prepare($sqlItems);
        $stmtItems->execute([$orderId]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        return [
            'details' => $details,
            'items'   => $items
        ];
    } catch (PDOException $e) {
        error_log("Get Order Details Shared Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Customer Management
 */
function get_or_create_customer(PDO $pdo, array $data): int
{
    $name = trim($data['name']);
    $type = trim($data['client_type'] ?? 'Private / Individual');
    $branch = !empty($data['gov_branch']) ? trim($data['gov_branch']) : null;
    $contact = trim($data['contact_no'] ?? '');

    $sql = "SELECT id FROM customers 
            WHERE LOWER(name) = LOWER(?) 
              AND LOWER(client_type) = LOWER(?) 
              AND ( (LOWER(gov_branch) = LOWER(?)) OR (gov_branch IS NULL AND ? IS NULL) )
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $type, $branch, $branch]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int)$id;
    }

    $sql = "INSERT INTO customers (name, contact_no, client_type, gov_branch) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $contact, $type, $branch]);
    return (int)$pdo->lastInsertId();
}
/**
 * --- NOTIFICATION SYSTEM ---
 */

/**
 * Creates a notification entry in the database.
 */
function create_notification(PDO $pdo, int $senderId, string $title, string $message, string $type, ?int $targetUserId = null, ?string $targetRole = null, ?string $link = null): bool
{
    try {
        $sql = "INSERT INTO notifications (target_user_id, target_role, sender_id, type, title, message, link) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$targetUserId, $targetRole, $senderId, $type, $title, $message, $link]);
    } catch (PDOException $e) {
        error_log("Create Notification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetches the latest notifications for a specific user based on their ID and Role.
 */
function get_user_notifications(PDO $pdo, int $userId, string $role, int $limit = 20): array
{
    try {
        $sql = "SELECT n.*, u.full_name as sender_name 
                FROM notifications n
                LEFT JOIN users u ON n.sender_id = u.id
                WHERE (n.target_user_id = ? OR n.target_role = ?)
                ORDER BY n.created_at DESC
                LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $role, PDO::PARAM_STR);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get Notifications Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Gets unread count for the bell icon badge.
 */
function get_unread_notif_count(PDO $pdo, int $userId, string $role): int
{
    try {
        $sql = "SELECT COUNT(*) FROM notifications 
                WHERE (target_user_id = ? OR target_role = ?) AND is_read = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $role]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Marks all notifications as read for the current user/role.
 */
function mark_notifs_as_read(PDO $pdo, int $userId, string $role): bool
{
    try {
        $sql = "UPDATE notifications SET is_read = 1 
                WHERE (target_user_id = ? OR target_role = ?) AND is_read = 0";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$userId, $role]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Initializes the notifications table if it doesn't exist.
 * This ensures the table schema remains consistent across environments.
 */
function init_notifications_table(PDO $pdo): void
{
    try {
        $sql = "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            target_user_id INT NULL,
            target_role VARCHAR(20) NULL,
            sender_id INT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            link VARCHAR(255) NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (target_user_id),
            INDEX (target_role),
            INDEX (is_read),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    } catch (PDOException $e) {
        error_log("Init Notifications Table Error: " . $e->getMessage());
    }
}
