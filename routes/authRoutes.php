<?php
require_once __DIR__ . '/../controllers/AuthController.php';

function handleAuthRoute($route, $method) {
    $authController = new AuthController();
    $authController->__dirname_init();
    
    // Normalize string endpoints (removes potential API prefixes)
    $cleanRoute = str_replace('/api', '', $route);

    if ($cleanRoute === '/auth/user/register' && $method === 'POST') {
        $authController->registerUser();
    } 
    elseif ($cleanRoute === '/auth/user/login' && $method === 'POST') {
        $authController->loginUser();
    } 
    elseif ($cleanRoute === '/auth/user/send-otp' && $method === 'POST') {
        $authController->sendLoginOtp();
    } 
    elseif ($cleanRoute === '/auth/user/verify-otp' && $method === 'POST') {
        $authController->verifyOtp();
    } 
    elseif ($cleanRoute === '/auth/admin/login' && $method === 'POST') {
        $authController->loginAdmin();
    } 
    elseif ($cleanRoute === '/auth/admin/register' && $method === 'POST') {
        $authController->registerAdmin();
    } 
    // PROTECTED ROUTES
    elseif ($cleanRoute === '/auth/user/profile' && $method === 'GET') {
        $userPayload = $authController->protect('user');
        $authController->getProfile($userPayload, 'user');
    } 
    elseif ($cleanRoute === '/auth/admin/profile' && $method === 'GET') {
        $userPayload = $authController->protect('admin');
        $authController->getProfile($userPayload, 'admin');
    } 
    elseif ($cleanRoute === '/auth/logout' && $method === 'POST') {
        $userPayload = $authController->protect(); 
        $authController->logout($userPayload);
    } 
    else {
        http_response_code(405);
        echo json_encode([
            "success" => false, 
            "message" => "Endpoint or HTTP request method not allowed inside Domain.",
            "requested_route" => $route,
            "requested_method" => $method
        ]);
    }
}
?>