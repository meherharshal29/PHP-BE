<?php
// File: routes/cameraRoutes.php

require_once __DIR__ . '/../controllers/CameraController.php';

function handleCameraRoute($route, $method) {
    $controller = new CameraController();
    $cleanRoute = str_replace('/api', '', $route);
    $parts = explode('/', trim($cleanRoute, '/'));

    if ($cleanRoute === '/cameras' && $method === 'GET') {
        $controller->getAllCameras();
    } 
    elseif (count($parts) === 2 && $parts[0] === 'cameras' && is_numeric($parts[1]) && $method === 'GET') {
        $controller->getCameraById(intval($parts[1]));
    } 
    elseif ($cleanRoute === '/cameras/add' && $method === 'POST') {
        $controller->addCamera();
    } 
    elseif (count($parts) === 3 && $parts[0] === 'cameras' && $parts[1] === 'delete' && $method === 'DELETE') {
        $controller->deleteCamera(intval($parts[2]));
    } 
    elseif (count($parts) === 3 && $parts[0] === 'cameras' && $parts[1] === 'update-full' && $method === 'PUT') {
        $controller->updateCamera(intval($parts[2]));
    } 
    elseif (count($parts) === 3 && $parts[0] === 'cameras' && $parts[1] === 'update-gallery' && $method === 'PUT') {
        $controller->manageGallery(intval($parts[2]));
    } 
    else {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(["success" => false, "message" => "Method or Route configuration missing inside Camera context."]);
    }
}
?>