<?php
require_once __DIR__ . '/../controllers/OrderController.php';

function handleOrderRoute($route, $method) {
    // Connection initialization runs automatically via constructor calls now
    $controller = new OrderController();

    // Standardize routing engine bounds
    $cleanRoute = str_replace('/api', '', $route);
    $cleanRoute = explode('?', $cleanRoute)[0];
    $cleanRoute = '/' . trim($cleanRoute, '/');

    // 1. POST: Process Checkout
    if ($cleanRoute === '/orders/checkout' && $method === 'POST') {
        $controller->processCheckout();
        return;
    }

    // 2. GET: Get Profile Autofill Suggestions
    if ($cleanRoute === '/orders/suggestions' && $method === 'GET') {
        $controller->getCheckoutSuggestions();
        return;
    }

    // 3. GET: Fetch Authenticated User Orders
    if (($cleanRoute === '/orders/my-orders' || $cleanRoute === '/orders' || $cleanRoute === '/order') && $method === 'GET') {
        $controller->getMyOrders();
        return;
    }

    // 4. POST: Cancel an Order Tracking Entry Row
    if (strpos($cleanRoute, '/orders/cancel/') === 0 && $method === 'POST') {
        $parts = explode('/', $cleanRoute);
        $id = end($parts);
        if (is_numeric($id)) {
            $controller->cancelOrder(intval($id));
            return;
        }
    }

    // 5. POST: Process Returned Gear Matrix (Admin only gate validation internal)
    if (strpos($cleanRoute, '/orders/return/') === 0 && $method === 'POST') {
        $parts = explode('/', $cleanRoute);
        $id = end($parts);
        if (is_numeric($id)) {
            $controller->processOrderReturn(intval($id));
            return;
        }
    }

    // FALLBACK BOUNDARY TRIGGER
    http_response_code(405);
    echo json_encode([
        "success" => false, 
        "message" => "Endpoint routing verification bounds mismatch inside Order Domain.",
        "debug_computed" => $cleanRoute,
        "debug_method" => $method
    ]);
}
?>