<?php
require_once __DIR__ . '/../controllers/ReviewController.php';

function handleReviewRoute($route, $method) {
    $controller = new ReviewController();
    $controller->__dirname_init();

    // 1. Clean parameters from API base prefix
    $cleanRoute = str_replace('/api', '', $route);
    
    // 2. Strict query string separation (?id=1&type=camera ko clean karna)
    $cleanRoute = explode('?', $cleanRoute)[0];
    $parts = explode('/', trim($cleanRoute, '/'));

    // --- ANGULAR ROUTING MAP EXECUTION ---

    // URL Match: /user-reviews/filter
    if (count($parts) === 2 && $parts[0] === 'user-reviews' && $parts[1] === 'filter' && $method === 'GET') {
        $controller->getReviewsByItem();
    }
    // URL Match: /user-reviews/my-reviews
    elseif (count($parts) === 2 && $parts[0] === 'user-reviews' && $parts[1] === 'my-reviews' && $method === 'GET') {
        $controller->getMyReviews();
    }
    // URL Match: /user-reviews/add
    elseif (count($parts) === 2 && $parts[0] === 'user-reviews' && $parts[1] === 'add' && $method === 'POST') {
        $controller->createReview();
    }
    // URL Match: /user-reviews/update/{id}
    elseif (count($parts) === 3 && $parts[0] === 'user-reviews' && $parts[1] === 'update' && is_numeric($parts[2]) && $method === 'PUT') {
        $controller->updateReview(intval($parts[2]));
    }
    // URL Match: /user-reviews/delete/{id}
    elseif (count($parts) === 3 && $parts[0] === 'user-reviews' && $parts[1] === 'delete' && is_numeric($parts[2]) && $method === 'DELETE') {
        $controller->deleteReview(intval($parts[2]));
    }
    else {
        http_response_code(405);
        echo json_encode([
            "success" => false, 
            "message" => "Endpoint pattern inside UserReviews matrix mismatch.",
            "debug_computed_route" => $cleanRoute
        ]);
    }
}
?>