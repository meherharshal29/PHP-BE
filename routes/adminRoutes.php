<?php
require_once __DIR__ . '/../controllers/AdminController.php';

function handleAdminRoute($route, $method) {
    $controller = new AdminController();
    $controller->__dirname_init();

    $cleanRoute = str_replace('/api', '', $route);
    $cleanRoute = explode('?', $cleanRoute)[0];
    $parts = explode('/', trim($cleanRoute, '/'));

    if (count($parts) === 2 && $parts[0] === 'admin' && $parts[1] === 'dashboard' && $method === 'GET') {
        $controller->getAdminDashboard();
    }
    elseif (count($parts) === 2 && $parts[0] === 'admin' && $parts[1] === 'analytics' && $method === 'GET') {
        $controller->getAdminAnalytics();
    }
    elseif (count($parts) === 2 && $parts[0] === 'admin' && $parts[1] === 'users' && $method === 'GET') {
        $controller->getAllUsers();
    }
    elseif (count($parts) === 4 && $parts[0] === 'admin' && $parts[1] === 'users' && is_numeric($parts[2]) && $parts[3] === 'full-details' && $method === 'GET') {
        $controller->getUserAllDetails(intval($parts[2]));
    }
    elseif (count($parts) === 3 && $parts[0] === 'admin' && $parts[1] === 'users' && is_numeric($parts[2]) && $method === 'PUT') {
        $controller->updateUser(intval($parts[2]));
    }
    elseif (count($parts) === 3 && $parts[0] === 'admin' && $parts[1] === 'users' && is_numeric($parts[2]) && $method === 'DELETE') {
        $controller->deleteUser(intval($parts[2]));
    }
    elseif (count($parts) === 3 && $parts[0] === 'admin' && $parts[1] === 'order-status' && is_numeric($parts[2]) && $method === 'PUT') {
        $controller->updateOrderStatus(intval($parts[2]));
    }
    elseif (count($parts) === 2 && $parts[0] === 'admin' && $parts[1] === 'broadcast' && $method === 'POST') {
        $controller->sendBroadcast();
    }
    elseif (count($parts) === 2 && $parts[0] === 'admin' && $parts[1] === 'call-requests' && $method === 'GET') {
        $controller->getAllCallRequests();
    }
    elseif (count($parts) === 4 && $parts[0] === 'admin' && $parts[1] === 'call-requests' && is_numeric($parts[2]) && $parts[3] === 'status' && $method === 'PATCH') {
        $controller->updateCallStatus(intval($parts[2]));
    }
    else {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Endpoint or operational configuration matrix not defined under Admin space."]);
    }
}
?>