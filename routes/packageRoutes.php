<?php
require_once __DIR__ . '/../controllers/PackageController.php';

function handlePackageRoute($route, $method) {
    $controller = new PackageController();
    $controller->__dirname_init();

    $cleanRoute = str_replace('/api', '', $route);
    $parts = explode('/', trim($cleanRoute, '/'));

    if ($cleanRoute === '/packages' && $method === 'GET') {
        $controller->getAllPackages();
    }
    elseif ($cleanRoute === '/packages/book' && $method === 'POST') {
        $controller->bookPackage();
    }
    elseif ($cleanRoute === '/packages/my-bookings' && $method === 'GET') {
        $controller->getMyBookings();
    }
    elseif ($cleanRoute === '/packages/create' && $method === 'POST') {
        $controller->createPackage();
    }
    elseif ($cleanRoute === '/packages/admin/bookings/all' && $method === 'GET') {
        $controller->getAllBookings();
    }
    // Dynamic Parameterized Routing Layers
    elseif (count($parts) === 2 && $parts[0] === 'packages' && is_numeric($parts[1]) && $method === 'GET') {
        $controller->getPackageById(intval($parts[1]));
    }
    elseif (count($parts) === 2 && $parts[0] === 'packages' && is_numeric($parts[1]) && $method === 'POST') { 
        // Note: Post used for full updates if multi-part file content configuration mapping bypasses standard PUT stream context
        $controller->updatePackage(intval($parts[1]));
    }
    elseif (count($parts) === 2 && $parts[0] === 'packages' && is_numeric($parts[1]) && $method === 'DELETE') {
        $controller->deletePackage(intval($parts[1]));
    }
    elseif (count($parts) === 5 && $parts[0] === 'packages' && $parts[1] === 'admin' && $parts[2] === 'users' && is_numeric($parts[3]) && $parts[4] === 'full-details' && $method === 'GET') {
        $controller->getUserFullDetails(intval($parts[3]));
    }
    elseif (count($parts) === 5 && $parts[0] === 'packages' && $parts[1] === 'admin' && $parts[2] === 'bookings' && is_numeric($parts[3]) && $parts[4] === 'status' && $method === 'PATCH') {
        $controller->updateBookingStatus(intval($parts[3]));
    }
    elseif (count($parts) === 3 && $parts[0] === 'packages' && $parts[1] === 'images' && is_numeric($parts[2]) && $method === 'DELETE') {
        $controller->deleteImage(intval($parts[2]));
    }
    else {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Endpoint or HTTP request target not matching inside Packages context"]);
    }
}
?>