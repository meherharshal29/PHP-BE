<?php
require_once __DIR__ . '/../controllers/CategoryController.php';

function handleCategoryRoute($route, $method) {
    $controller = new CategoryController();
    $controller->__dirname_init();

    $cleanRoute = str_replace('/api', '', $route);
    $cleanRoute = explode('?', $cleanRoute)[0];
    $parts = explode('/', trim($cleanRoute, '/'));

    // 1. GET: /categories (Fetch all listings)
    if (count($parts) === 1 && $parts[0] === 'categories' && $method === 'GET') {
        $controller->getAllCategories();
    }
    // 2. POST: /categories/seed (Admin multiple array push)
    elseif (count($parts) === 2 && $parts[0] === 'categories' && $parts[1] === 'seed' && $method === 'POST') {
        $controller->seedCategories();
    }
    // 3. POST: /categories (Add single asset entry)
    elseif (count($parts) === 1 && $parts[0] === 'categories' && $method === 'POST') {
        $controller->createCategory();
    }
    else {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method endpoint context not tracked in Categories logic loop."]);
    }
}
?>