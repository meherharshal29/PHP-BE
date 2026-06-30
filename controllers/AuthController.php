<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Admin.php';

class AuthController {
    private $db;
    private $jwt_secret;

    // Initialization block to bind dependency engines dynamically
    public function __dirname_init() {
        $database = new Database();
        $this->db = $database->getConnection();
        
        // Load configurations centralized dynamically from config manager
        $env = require __DIR__ . '/../config/env.php';
        $this->jwt_secret = isset($env['JWT_SECRET']) ? $env['JWT_SECRET'] : "YOUR_SUPER_SECRET_KEY_12345";
    }

    /**
     * Helper: Persistent JWT Token Generator (30 days session matching token standards)
     */
    private function generateToken($id, $role) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'id' => intval($id),
            'role' => $role,
            'iat' => time(),
            'exp' => time() + (30 * 24 * 60 * 60) // 30 Days life cycle period
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->jwt_secret, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Auth Middleware Helper (Failsafe access boundary protection)
     */
    public function protect($requiredRole = null) {
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $jwt = $matches[1];
            $tokenParts = explode('.', $jwt);
            if (count($tokenParts) === 3) {
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])));
                
                if ($payload && $payload->exp > time()) {
                    if ($requiredRole && $payload->role !== $requiredRole) {
                        http_response_code(403);
                        echo json_encode(["success" => false, "message" => "Access denied. Unauthorized role matrix boundary."]);
                        exit();
                    }
                    return $payload; 
                }
            }
        }
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Not authorized, token validation failed."]);
        exit();
    }

    // ====================================================================
    // 1. ADMIN AUTHENTICATION METHODS
    // ====================================================================

    public function registerAdmin() {
        $data = json_decode(file_get_contents("php://input"));
        try {
            if (empty($data->name) || empty($data->email) || empty($data->password)) {
                throw new Exception("Incomplete schema data detail specifications provided.");
            }

            $adminModel = new Admin($this->db);
            $adminExists = $adminModel->findByEmailWithPassword($data->email);
            if ($adminExists) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Admin email already exists inside domain registry."]);
                return;
            }

            $data->password = password_hash($data->password, PASSWORD_BCRYPT);
            $data->role = 'admin';
            $data->isOnline = 1; 
            $data->isActive = 1;

            $adminId = $adminModel->create($data);
            if ($adminId) {
                $token = $this->generateToken($adminId, 'admin');
                http_response_code(201);
                echo json_encode([
                    "success" => true,
                    "message" => "Admin registered successfully",
                    "token" => $token,
                    "admin" => ["id" => intval($adminId), "name" => $data->name, "email" => $data->email, "role" => "admin"]
                ]);
            } else {
                throw new Exception("Admin structural database creation mapping query failed.");
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function loginAdmin() {
        $data = json_decode(file_get_contents("php://input"));
        try {
            if (empty($data->email) || empty($data->password)) {
                throw new Exception("Email and Password fields cannot be empty inside verification flow.");
            }

            $adminModel = new Admin($this->db);
            $admin = $adminModel->findByEmailWithPassword($data->email);

            // Validation Track 1: Account registration existence assertion check
            if (!$admin) {
                http_response_code(401);
                echo json_encode(["success" => false, "message" => "Admin account with this email does not exist."]);
                return;
            }

            // Validation Track 2: Password hash decryption check flags verify
            if (!password_verify($data->password, $admin['password'])) {
                http_response_code(401);
                echo json_encode(["success" => false, "message" => "Password verification failed. Secure hash mismatch."]);
                return;
            }

            // Set operational status matrix state
            $adminModel->updateOnlineStatus($admin['id'], 1);

            echo json_encode([
                "success" => true,
                "token" => $this->generateToken($admin['id'], 'admin'),
                "admin" => [
                    "id" => intval($admin['id']), 
                    "name" => $admin['name'], 
                    "email" => $admin['email'], 
                    "role" => isset($admin['role']) ? $admin['role'] : "admin"
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    // ====================================================================
    // 2. USER AUTHENTICATION METHODS
    // ====================================================================

    public function registerUser() {
        $data = json_decode(file_get_contents("php://input"));
        try {
            if (empty($data->name) || empty($data->email) || empty($data->password)) {
                throw new Exception("Incomplete fields provided during signup runtime.");
            }

            $userModel = new User($this->db);
            $userExists = $userModel->findByEmail($data->email);
            if ($userExists) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Email already exists inside core registry."]);
                return;
            }

            $data->password = password_hash($data->password, PASSWORD_BCRYPT);
            $data->isOnline = 0;
            $data->isActive = 1;

            $userId = $userModel->create($data);
            if ($userId) {
                $this->sendLoginOtp($data->email);
            } else {
                throw new Exception("User instantiation database pipeline execution crashed.");
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function loginUser() {
        $data = json_decode(file_get_contents("php://input"));
        try {
            $userModel = new User($this->db);
            $user = $userModel->findByEmail($data->email);

            if (!$user || !password_verify($data->password, $user['password'])) {
                http_response_code(401);
                echo json_encode(["success" => false, "message" => "Invalid credentials context data verified."]);
                return;
            }

            $userModel->updateOnlineStatus($user['id'], 1);

            echo json_encode([
                "success" => true,
                "token" => $this->generateToken($user['id'], 'user'),
                "user" => ["id" => intval($user['id']), "name" => $user['name'], "email" => $user['email'], "role" => $user['role']]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function sendLoginOtp($explicitEmail = null) {
        $email = $explicitEmail;
        if (!$email) {
            $data = json_decode(file_get_contents("php://input"));
            $email = isset($data->email) ? $data->email : null;
        }

        try {
            $userModel = new User($this->db);
            $user = $userModel->findByEmail($email);

            if (!$user) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "User context tracking metrics index out of bounds."]);
                return;
            }

            $otp = (string)rand(100000, 900000);
            $hashedOtp = password_hash($otp, PASSWORD_BCRYPT);
            $expiry = time() + (10 * 60); // 10 minutes validation threshold window

            $userModel->updateOTP($user['id'], $hashedOtp, $expiry);

            $subject = "Verification Code";
            $message = "Hello " . $user['name'] . ",\n\nYour OTP Verification code is: " . $otp;
            $headers = "From: noreply@smartmedialivesolution.com\r\nContent-Type: text/plain; charset=UTF-8";
            
            @mail($user['email'], $subject, $message, $headers);

            echo json_encode(["success" => true, "message" => "OTP dispatched successfully to remote inbox mailboxes."]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function verifyOtp() {
        $data = json_decode(file_get_contents("php://input"));
        try {
            $userModel = new User($this->db);
            $user = $userModel->findByEmail($data->email);

            if (!$user || !isset($user['otp']) || empty($user['otp']) || time() > $user['otpExpires']) {
                http_response_code(401);
                echo json_encode(["success" => false, "message" => "OTP expiration window closed or code corrupted."]);
                return;
            }

            if (!password_verify((string)$data->otp, $user['otp'])) {
                http_response_code(401);
                echo json_encode(["success" => false, "message" => "Invalid token specification code verification parameters index incorrect."]);
                return;
            }

            $userModel->updateOTP($user['id'], null, null);
            $userModel->updateOnlineStatus($user['id'], 1);

            echo json_encode([
                "success" => true,
                "token" => $this->generateToken($user['id'], 'user'),
                "user" => ["id" => intval($user['id']), "name" => $user['name'], "email" => $user['email'], "role" => $user['role']]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function logout($userPayload) {
        try {
            if ($userPayload->role === 'admin') {
                $adminModel = new Admin($this->db);
                $adminModel->updateOnlineStatus($userPayload->id, 0);
            } else {
                $userModel = new User($this->db);
                $userModel->updateOnlineStatus($userPayload->id, 0);
            }
            echo json_encode(["success" => true, "message" => "Logged out securely from active device stack session."]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function getProfile($userPayload, $role) {
        try {
            if ($role === 'admin') {
                $adminModel = new Admin($this->db);
                $profile = $adminModel->findByEmail($userPayload->email); 
            } else {
                $stmt = $this->db->prepare("SELECT id, name, email, phone, role, isActive, accountStatus, defaultAddress, defaultCity, defaultAdharNo FROM users WHERE id = :id");
                $stmt->execute([':id' => $userPayload->id]);
                $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            echo json_encode(["success" => true, $role => $profile]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }
}
?>