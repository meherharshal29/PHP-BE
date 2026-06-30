<?php
// 1. DYNAMIC CORS & SECURITY HEADERS
$allowedOrigins = [
    'https://smartmedialivesolution.com',
    'https://www.smartmedialivesolution.com',
    'http://localhost:4200',
    'http://localhost:3000',
    'http://127.0.0.1:4200'
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: " . $origin);
} else {
    header("Access-Control-Allow-Origin: *");
}

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, Accept, Origin");
header("Access-Control-Allow-Credentials: true");

header_remove("X-Powered-By");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 2. GLOBAL ERROR HANDLING MIDDLEWARE
set_error_handler(function($severity, $message, $file, $line) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Internal Server Error",
        "error" => $message,
        "file" => $file,
        "line" => $line
    ]);
    exit();
});

// 3. CLEAN DYNAMIC ROUTING ENGINE 
$requestUri = explode('?', $_SERVER['REQUEST_URI'])[0]; 
$scriptName = $_SERVER['SCRIPT_NAME']; 

// Derive basePath even if accessing cleanly via mod_rewrite execution pipelines
$basePath = dirname($scriptName);
if ($basePath === DIRECTORY_SEPARATOR || $basePath === '\\') {
    $basePath = '';
}

// Pull relative path without directory context strings
$route = substr($requestUri, strlen($basePath));

if (empty($route) || $route === '/index.php') {
    $route = '/';
}

// Add mandatory normalized leading slash
if (strpos($route, '/') !== 0) {
    $route = '/' . $route;
}

// Strip trailing slashes safely
if (strlen($route) > 1 && substr($route, -1) === '/') {
    $route = rtrim($route, '/');
}

$method = $_SERVER['REQUEST_METHOD'];

// 4. CENTRAL ROUTER SWITCH
switch (true) {
    
    // HEALTH CHECK
    case ($route === '/health' && $method === 'GET'):
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Server Running Smoothly"
        ]);
        break;

    // ADMIN SUB-ROUTES (Fixes the dashboard/analytics 404)
    case (strpos($route, '/api/admin') === 0 || strpos($route, '/admin') === 0):
        require_once __DIR__ . '/routes/adminRoutes.php';
        handleAdminRoute($route, $method);
        break;

    // AUTH SUB-ROUTES
    case (strpos($route, '/api/auth') === 0 || strpos($route, '/auth') === 0):
        require_once __DIR__ . '/routes/authRoutes.php';
        handleAuthRoute($route, $method);
        break;

    // CAMERA SUB-ROUTES
    case (strpos($route, '/api/cameras') === 0 || strpos($route, '/cameras') === 0):
        require_once __DIR__ . '/routes/cameraRoutes.php';
        handleCameraRoute($route, $method);
        break;

    // CART SUB-ROUTES
    case (strpos($route, '/api/cart') === 0 || strpos($route, '/cart') === 0):
        require_once __DIR__ . '/routes/cartRoutes.php';
        handleCartRoute($route, $method);
        break;

    // PACKAGES SUB-ROUTES
    case (strpos($route, '/api/packages') === 0 || strpos($route, '/packages') === 0):
        require_once __DIR__ . '/routes/packageRoutes.php';
        handlePackageRoute($route, $method);
        break;

    // REVIEWS SUB-ROUTES
    case (strpos($route, '/api/user-reviews') === 0 || strpos($route, '/user-reviews') === 0):
        require_once __DIR__ . '/routes/reviewRoutes.php';
        handleReviewRoute($route, $method);
        break;

    // CATEGORIES SUB-ROUTES
    case (strpos($route, '/api/categories') === 0 || strpos($route, '/categories') === 0):
        require_once __DIR__ . '/routes/categoryRoutes.php';
        handleCategoryRoute($route, $method);
        break;

    // ORDERS SUB-ROUTES
    case (strpos($route, '/api/orders') === 0 || strpos($route, '/orders') === 0 || strpos($route, '/api/order') === 0 || strpos($route, '/order') === 0):
        require_once __DIR__ . '/routes/orderRoutes.php';
        handleOrderRoute($route, $method);
        break;
        
    // FALLBACK 404 ROUTE
    default:
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "API endpoint '{$route}' not found on this server.",
            "debug_computed" => $route,
            "debug_method" => $method
        ]);
        break;
}
?>