<?php

declare(strict_types=1);

/**
 * Warehouse Model
 * Handles data fetching and logic specifically for Warehouse operations.
 */

/**
 * Fetches pending orders that require fulfillment from the warehouse.
 */
function get_pending_warehouse_requests(PDO $pdo, int $limit = 5): array
{
    try {
        $sql = "SELECT o.id, o.wh_status, o.created_at, 
                       u.role AS requester_role,
                       COALESCE(c.name, o.temp_customer_name) as customer, 
                       COUNT(oi.id) as item_count, 
                       SUM(oi.qty) as total_qty
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN users u ON o.created_by = u.id
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE LOWER(o.status) IN ('success', 'ongoing') 
                  AND LOWER(o.wh_status) = 'to release'
                  AND (oi.get_from = 'WH' OR oi.get_from = 'Warehouse')
                GROUP BY o.id
                ORDER BY o.created_at DESC, o.id DESC
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get Pending WH Requests Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches an overview of warehouse stock levels, prioritized by lowest stock.
 */
function get_warehouse_stock_overview(PDO $pdo, int $limit = 5): array
{
    try {
        // We aggregate stock by variant for the overview
        $sql = "SELECT p.name, p.default_image, pv.variant, pv.variant_image, 
                       pv.min_buildable_qty,
                       SUM(ws.qty_on_hand) as total_qty, 
                       pc.location
                FROM warehouse_stocks ws
                JOIN product_variant pv ON ws.variant_id = pv.id AND pv.is_deleted = 0
                JOIN products p ON pv.prod_id = p.id
                LEFT JOIN product_components pc ON ws.product_comp_id = pc.id
                WHERE p.is_deleted = 0
                GROUP BY pv.id
                ORDER BY total_qty ASC
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get Warehouse Stock Overview Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Calculates health statistics for warehouse inventory.
 */
function get_warehouse_health_stats(PDO $pdo): array
{
    try {
        // Total products in warehouse
        $sqlTotal = "SELECT COUNT(DISTINCT ws.variant_id) FROM warehouse_stocks ws JOIN product_variant pv ON ws.variant_id = pv.id WHERE pv.is_deleted = 0";
        $totalVariants = (int)$pdo->query($sqlTotal)->fetchColumn();

        if ($totalVariants === 0) {
            return [
                'well_stocked' => 0,
                'restock'      => 0,
                'total'        => 0,
                'health'       => 0,
                'total_units'  => 0
            ];
        }

        // We define health based on variant buildable limits
        $sqlHealth = "SELECT 
                        pv.id,
                        pv.min_buildable_qty,
                        COALESCE(ws_agg.buildable_qty, 0) as current_qty
                      FROM product_variant pv
                      JOIN (
                          SELECT 
                              ws.variant_id, 
                              FLOOR(MIN(ws.qty_on_hand / pc.qty_needed)) as buildable_qty
                          FROM warehouse_stocks ws
                          JOIN product_components pc ON ws.product_comp_id = pc.id
                          GROUP BY ws.variant_id
                      ) ws_agg ON pv.id = ws_agg.variant_id";

        $stmt = $pdo->query($sqlHealth);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $wellStocked = 0;
        $restock = 0;
        foreach ($rows as $row) {
            if ((int)$row['current_qty'] > (int)$row['min_buildable_qty']) {
                $wellStocked++;
            } else {
                $restock++;
            }
        }

        $totalUnits = (int)$pdo->query("SELECT SUM(qty_on_hand) FROM warehouse_stocks")->fetchColumn();

        return [
            'well_stocked' => $wellStocked,
            'restock'      => $restock,
            'total'        => count($rows),
            'health'       => count($rows) > 0 ? round(($wellStocked / count($rows)) * 100) : 0,
            'total_units'  => $totalUnits
        ];
    } catch (PDOException $e) {
        error_log("Get Warehouse Health Stats Error: " . $e->getMessage());
        return [
            'well_stocked' => 0, 'restock' => 0, 'total' => 0, 'health' => 0, 'total_units' => 0
        ];
    }
}

/**
 * Helper to parse a standardized location string.
 */
function parse_location(?string $location): array
{
    if (empty($location)) {
        return ['aisle' => 'Unknown', 'shelf' => 'Unknown', 'bin' => 'Unknown'];
    }

    $parts = explode('-', $location);
    return [
        'aisle' => $parts[0] ?? 'N/A',
        'shelf' => $parts[1] ?? 'N/A',
        'bin'   => $parts[2] ?? 'N/A'
    ];
}

/**
 * Fetches detailed items ready for warehouse fulfillment.
 * Includes multi-location support for components.
 */
function get_fulfillment_ready_items(PDO $pdo, ?int $orderId = null): array
{
    try {
        $filter = $orderId ? " AND o.id = ? " : "";

        // Main Query: Fetch paid orders with their items
        $sql = "SELECT 
                    o.id AS order_id,
                    COALESCE(c.name, o.temp_customer_name) AS customer_name,
                    u.full_name AS requested_by,
                    o.created_at,
                    p.name AS prod_name,
                    p.code AS prod_code,
                    pv.variant AS variant_name,
                    COALESCE(pv.variant_image, p.default_image) as img,
                    u.role AS requester_role,
                    oi.qty AS qty_to_pick,
                    oi.id AS item_id,
                    oi.wh_item_status,
                    pv.id AS variant_id,
                    p.id AS prod_id,
                    p.description
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN product_variant pv ON oi.variant_id = pv.id AND pv.is_deleted = 0
                JOIN products p ON pv.prod_id = p.id
                LEFT JOIN users u ON o.created_by = u.id
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE LOWER(o.status) = 'success' 
                  AND LOWER(o.wh_status) = 'to release'
                  AND p.is_deleted = 0
                  $filter
                ORDER BY o.created_at DESC, o.id DESC";

        $stmt = $pdo->prepare($sql);
        if ($orderId) {
            $stmt->execute([$orderId]);
        } else {
            $stmt->execute();
        }
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($items)) return [];

        // Fetch components and stocks for these variants
        $variantIds = array_column($items, 'variant_id');
        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));

        // Available Buildable Stock
        $sqlStock = "SELECT 
                        ws.variant_id, 
                        FLOOR(MIN(ws.qty_on_hand / pc.qty_needed)) as buildable_qty
                    FROM warehouse_stocks ws
                    JOIN product_components pc ON ws.product_comp_id = pc.id
                    WHERE ws.variant_id IN ($placeholders)
                    GROUP BY ws.variant_id";
        $stmtStock = $pdo->prepare($sqlStock);
        $stmtStock->execute($variantIds);
        $stocks = $stmtStock->fetchAll(PDO::FETCH_KEY_PAIR);

        // Components and Locations
        $prodIds = array_unique(array_column($items, 'prod_id'));
        $pPlaceholders = implode(',', array_fill(0, count($prodIds), '?'));

        $sqlComp = "SELECT 
                        pc.prod_id,
                        comp.component_name,
                        pc.location,
                        pc.qty_needed
                    FROM product_components pc
                    JOIN components comp ON pc.comp_id = comp.id
                    WHERE pc.prod_id IN ($pPlaceholders)";
        $stmtComp = $pdo->prepare($sqlComp);
        $stmtComp->execute(array_values($prodIds));
        $allComponents = $stmtComp->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            $vId = (int)$item['variant_id'];
            $pId = (int)$item['prod_id'];
            $item['available_stock'] = (int)($stocks[$vId] ?? 0);
            $item['components'] = $allComponents[$pId] ?? [];
            $item['formatted_date'] = date('M d, Y', strtotime($item['created_at']));
        }

        return $items;
    } catch (PDOException $e) {
        error_log("Get Fulfillment Ready Items Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetches fulfillment items grouped by Order.
 */
function get_fulfillment_ready_orders(PDO $pdo): array
{
    $items = get_fulfillment_ready_items($pdo);
    if (empty($items)) return [];

    $orders = [];
    foreach ($items as $item) {
        $oId = (string)$item['order_id'];
        if (!isset($orders[$oId])) {
            $orders[$oId] = [
                'order_id'       => $item['order_id'],
                'customer_name'  => $item['customer_name'],
                'requested_by'   => $item['requested_by'],
                'source'         => ($item['requester_role'] === 'admin' ? 'Admin' : 'Lobby'),
                'created_at'     => $item['created_at'],
                'formatted_date' => $item['formatted_date'],
                'products'       => []
            ];
        }
        $orders[$oId]['products'][] = [
            'item_id'         => $item['item_id'],
            'wh_item_status'  => $item['wh_item_status'] ?: 'pending',
            'prod_name'       => $item['prod_name'],
            'prod_code'       => $item['prod_code'],
            'variant_name'    => $item['variant_name'],
            'img'             => $item['img'],
            'qty_to_pick'     => $item['qty_to_pick'],
            'available_stock' => $item['available_stock'],
            'components'      => $item['components'],
            'description'     => $item['description']
        ];
    }

    return array_values($orders);
}

/**
 * Fetches a single fulfillment order by its ID.
 */
function get_fulfillment_order_by_id(PDO $pdo, int $orderId): ?array
{
    $items = get_fulfillment_ready_items($pdo, $orderId);
    if (empty($items)) return null;

    $item = $items[0];
    $order = [
        'order_id'       => $item['order_id'],
        'customer_name'  => $item['customer_name'],
        'requested_by'   => $item['requested_by'],
        'source'         => ($item['requester_role'] === 'admin' ? 'Admin' : 'Lobby'),
        'created_at'     => $item['created_at'],
        'formatted_date' => $item['formatted_date'],
        'products'       => []
    ];

    foreach ($items as $itm) {
        $order['products'][] = [
            'item_id'         => $itm['item_id'],
            'wh_item_status'  => $itm['wh_item_status'] ?: 'pending',
            'prod_name'       => $itm['prod_name'],
            'prod_code'       => $itm['prod_code'],
            'variant_name'    => $itm['variant_name'],
            'img'             => $itm['img'],
            'qty_to_pick'     => $itm['qty_to_pick'],
            'available_stock' => $itm['available_stock'],
            'components'      => $itm['components'],
            'description'     => $itm['description']
        ];
    }

    return $order;
}

/**
 * Updates the fulfillment status of a specific order item.
 */
function update_warehouse_item_status(PDO $pdo, int $itemId, string $status): bool
{
    try {
        $allowedStatus = ['pending', 'ready', 'fulfilled'];
        if (!in_array($status, $allowedStatus)) return false;

        $stmt = $pdo->prepare("UPDATE order_items SET wh_item_status = ? WHERE id = ?");
        return $stmt->execute([$status, $itemId]);
    } catch (PDOException $e) {
        error_log("Update WH Item Status Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Marks an entire order as fulfilled in the warehouse.
 */
function fulfill_warehouse_order(PDO $pdo, int $orderId): bool
{
    try {
        $stmt = $pdo->prepare("UPDATE orders SET wh_status = 'Fulfilled', status = 'Success' WHERE id = ?");
        return $stmt->execute([$orderId]);
    } catch (PDOException $e) {
        error_log("Fulfill WH Order Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Resets all warehouse items in an order to 'pending' status.
 */
function reset_order_items_status(PDO $pdo, int $orderId): bool
{
    try {
        $stmt = $pdo->prepare("UPDATE order_items 
                               SET wh_item_status = 'pending' 
                               WHERE order_id = ?");
        return $stmt->execute([$orderId]);
    } catch (PDOException $e) {
        error_log("Reset Order Items Status Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetches warehouse stock logs.
 */
function get_warehouse_logs(PDO $pdo, array $filters = [], int $limit = 5, int $offset = 0): array
{
    try {
        $sql = "SELECT 
                    wl.log_id, wl.action, wl.qty, wl.log_date, 
                    COALESCE(p_new.code, p_old.code) AS product_code,
                    COALESCE(p_new.name, p_old.name) AS product_name,
                    COALESCE(pv_new.variant, pv_old.variant) AS variant_name,
                    COALESCE(c_new.component_name, c_old.component_name) AS component_name
                FROM warehouse_logs wl
                LEFT JOIN products p_new ON wl.prod_id = p_new.id
                LEFT JOIN product_variant pv_new ON wl.variant_id = pv_new.id
                LEFT JOIN components c_new ON wl.comp_id = c_new.id AND wl.prod_id IS NOT NULL
                LEFT JOIN warehouse_stocks ws ON wl.comp_id = ws.id AND wl.prod_id IS NULL
                LEFT JOIN products p_old ON ws.prod_id = p_old.id
                LEFT JOIN product_variant pv_old ON ws.variant_id = pv_old.id
                LEFT JOIN product_components pc ON ws.product_comp_id = pc.id
                LEFT JOIN components c_old ON pc.comp_id = c_old.id
                WHERE 1 = 1";

        $params = [];
        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(wl.log_date) >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(wl.log_date) <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }

        $sql .= " ORDER BY wl.log_date DESC, wl.log_id DESC LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) $stmt->bindValue($key, $val);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Get Warehouse Logs Error: " . $e->getMessage());
        return [];
    }
}

function count_warehouse_logs(PDO $pdo, array $filters = []): int
{
    try {
        $sql = "SELECT COUNT(*) FROM warehouse_logs wl WHERE 1 = 1";
        $params = [];
        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(wl.log_date) >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(wl.log_date) <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) $stmt->bindValue($key, $val);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Count Warehouse Logs Error: " . $e->getMessage());
        return 0;
    }
}

function get_warehouse_dashboard_stats(PDO $pdo, int $userId): array
{
    try {
        $totalProducts = (int)$pdo->query("SELECT COUNT(DISTINCT p.id) 
                                    FROM products p
                                    JOIN product_variant pv ON p.id = pv.prod_id
                                    JOIN warehouse_stocks ws ON pv.id = ws.variant_id
                                    WHERE ws.qty_on_hand > 0 AND p.is_deleted = 0")->fetchColumn();

        $stmtUser = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE created_by = ?");
        $stmtUser->execute([$userId]);
        $userTransactions = (int)$stmtUser->fetchColumn();

        $pendingWH = (int)$pdo->query("SELECT COUNT(DISTINCT o.id) FROM orders o 
                                 JOIN order_items oi ON o.id = oi.order_id 
                                 WHERE LOWER(o.status) = 'success'")->fetchColumn();

        $pendingSR = (int)$pdo->query("SELECT COUNT(DISTINCT o.id) FROM orders o 
                                 JOIN order_items oi ON o.id = oi.order_id 
                                 WHERE LOWER(o.status) = 'approved' AND (oi.get_from = 'SR' OR oi.get_from = 'Showroom')")->fetchColumn();

        return [
            'total_products'    => $totalProducts,
            'user_transactions' => $userTransactions,
            'pending_wh'        => $pendingWH,
            'pending_sr'        => $pendingSR
        ];
    } catch (PDOException $e) {
        error_log("Dashboard Stats Error: " . $e->getMessage());
        return ['total_products' => 0, 'user_transactions' => 0, 'pending_wh' => 0, 'pending_sr' => 0];
    }
}

function get_warehouse_stock_alerts(PDO $pdo): array
{
    try {
        $sql = "SELECT p.name as prod_name, pv.variant as variant_name, 
                       COALESCE(pv.variant_image, p.default_image) as img, 
                       ws.qty_on_hand, pv.min_buildable_qty
                FROM warehouse_stocks ws
                JOIN product_variant pv ON ws.variant_id = pv.id
                JOIN products p ON pv.prod_id = p.id
                WHERE ws.qty_on_hand <= pv.min_buildable_qty
                ORDER BY ws.qty_on_hand ASC";
        
        $alerts = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $oos = [];
        $low = [];

        foreach ($alerts as $a) {
            if ((int)$a['qty_on_hand'] === 0) {
                $oos[] = $a;
            } else {
                $low[] = $a;
            }
        }

        return [
            'out_of_stock' => $oos,
            'low_stock'    => $low
        ];
    } catch (PDOException $e) {
        error_log("Warehouse Alerts Error: " . $e->getMessage());
        return ['out_of_stock' => [], 'low_stock' => []];
    }
}
