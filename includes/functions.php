<?php
// includes/functions.php

// Format money in Nigerian Naira
function format_naira($amount) {
    return '₦' . number_format($amount, 2);
}

// Get current total stock for a product (sum of all batches)
function get_product_stock($pdo, $product_id) {
    $stmt = $pdo->prepare("
        SELECT SUM(quantity) AS total 
        FROM product_batches 
        WHERE product_id = ?
    ");
    $stmt->execute([$product_id]);
    return (int) $stmt->fetchColumn() ?: 0;
}

// Get products that are low on stock
function get_low_stock_products($pdo) {
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.name,
            p.low_stock_threshold,
            SUM(b.quantity) AS stock
        FROM products p
        LEFT JOIN product_batches b ON p.id = b.product_id
        GROUP BY p.id
        HAVING stock <= p.low_stock_threshold 
           AND stock > 0
        ORDER BY stock ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}