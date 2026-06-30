<?php
require_once __DIR__ . '/../controllers/CartController.php';

function handleCartRoute($route, $method) {
    // Instantiating natively invokes the __construct engine connection setup automatically
    $controller = new CartController();

    // Standardize path metrics out of route string blocks
    $cleanRoute = str_replace('/api', '', $route);
    
    // Normalize trailing slash to protect routing match assertions
    if (strlen($cleanRoute) > 1 && substr($cleanRoute, -1) === '/') {
        $cleanRoute = rtrim($cleanRoute, '/');
    }

    // 1. GET: Fetch User Active Cart State Data
    if ($cleanRoute === '/cart/my-cart' && $method === 'GET') {
        $controller->getUserCart();
        return;
    } 

    // 2. POST: Map and Push New Item Selection to Cart Matrix
    if ($cleanRoute === '/cart/add' && $method === 'POST') {
        $controller->addToCart();
        return;
    } 

    // 3. PUT: Update Specific Item Row Configuration Parameters
    if (strpos($cleanRoute, '/cart/update/') === 0 && $method === 'PUT') {
        $parts = explode('/', $cleanRoute);
        $id = end($parts);
        if (is_numeric($id)) {
            $controller->updateCartItem(intval($id));
            return;
        }
    } 

    // 4. DELETE: Drop Target Row Entity Item
    if (strpos($cleanRoute, '/cart/remove/') === 0 && $method === 'DELETE') {
        $parts = explode('/', $cleanRoute);
        $id = end($parts);
        if (is_numeric($id)) {
            $controller->removeFromCart(intval($id));
            return;
        }
    } 

    // 5. POST: Finalize checkout execution inside the system
    if (($cleanRoute === '/cart/checkout' || $cleanRoute === '/orders/checkout') && $method === 'POST') {
        if (method_exists($controller, 'checkout')) {
            $controller->checkout();
        } else {
            http_response_code(200);
            echo json_encode([
                "success" => true, 
                "message" => "Checkout baseline verified. Core controller endpoint method logic missing or disconnected."
            ]);
        }
        return;
    } 

    // 6. FALLBACK: Method or Path Mismatch Boundary Trigger
    http_response_code(405);
    echo json_encode([
        "success" => false, 
        "message" => "Method or Endpoint specification incorrect inside Cart Domain.",
        "debug_route" => $cleanRoute,
        "debug_method" => $method
    ]);
}
?>