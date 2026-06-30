<?php
require_once __DIR__ . '/../config/database.php';
if (file_exists(__DIR__ . '/AuthController.php')) {
    require_once __DIR__ . '/AuthController.php';
} elseif (file_exists(__DIR__ . '/authController.php')) {
    require_once __DIR__ . '/authController.php';
}

class OrderController {
    private $db;
    private $env;

    // Use constructor injection to guarantee active database connections instantly
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->env = file_exists(__DIR__ . '/../config/env.php') ? require __DIR__ . '/../config/env.php' : ['CLIENT_URL' => 'http://localhost:4200', 'ADMIN_EMAIL' => 'admin@smartmedia.com'];
    }

    private function formatImageUrl($url) {
        if (empty($url)) return null;
        if (filter_var($url, FILTER_VALIDATE_URL)) return $url;
        $clientUrl = $this->env['CLIENT_URL'] ?? 'http://localhost:4200';
        return $clientUrl . '/' . ltrim(str_replace('\\', '/', $url), '/');
    }

    public function getCheckoutSuggestions() {
        $auth = new AuthController(); 
        if (method_exists($auth, '__dirname_init')) { $auth->__dirname_init(); }
        $user = $auth->protect();
        
        try {
            $stmt = $this->db->prepare("SELECT defaultAddress, defaultCity, defaultAdharNo FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => intval($user->id)]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode([
                "success" => true,
                "data" => [
                    "address" => $profile['defaultAddress'] ?? "",
                    "city"    => $profile['defaultCity'] ?? "",
                    "adharNo" => $profile['defaultAdharNo'] ?? ""
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500); 
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function processCheckout() {
        $auth = new AuthController(); 
        if (method_exists($auth, '__dirname_init')) { $auth->__dirname_init(); }
        $user = $auth->protect();
        
        $data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

        $address           = $data['address'] ?? ''; 
        $city              = $data['city'] ?? ''; 
        $adharNo           = $data['adharNumber'] ?? $data['adharNo'] ?? ''; 
        $paymentMethod     = $data['paymentMethod'] ?? 'cod'; 
        $fulfillmentMethod = $data['fulfillmentMethod'] ?? 'pickup';
        $userName          = $user->name ?? 'Customer';

        if (empty($address) || empty($city) || empty($adharNo)) {
            http_response_code(400); 
            echo json_encode(["success" => false, "message" => "Validation failed. Parameters missing."]); 
            return;
        }

        try {
            $this->db->beginTransaction();

            $upUser = $this->db->prepare("UPDATE users SET defaultAddress = :address, defaultCity = :city, defaultAdharNo = :adhar WHERE id = :id");
            $upUser->execute([':address' => $address, ':city' => $city, ':adhar' => $adharNo, ':id' => intval($user->id)]);

            $cartStmt = $this->db->prepare("SELECT c.*, cam.name as camName, cam.brand, cam.pricePerDay FROM carts c INNER JOIN cameras cam ON c.cameraId = cam.id WHERE c.userId = :userId");
            $cartStmt->execute([':userId' => intval($user->id)]);
            $cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($cartItems)) {
                $this->db->rollBack(); 
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Your cart is empty."]); 
                return;
            }

            $grandTotal = 0; 
            $emailSummary = []; 
            $insertedOrders = [];
            $deliveryCharge = ($fulfillmentMethod === 'delivery') ? 100.00 : 0.00;

            $insOrder = $this->db->prepare("INSERT INTO orders (userId, cameraId, quantity, rentalStartDate, rentalEndDate, totalPrice, shippingAddress, adharNumber, paymentMethod, fulfillmentMethod, status) 
                                            VALUES (:userId, :cameraId, :quantity, :startDate, :endDate, :totalPrice, :address, :adhar, :payment, :fulfillment, :status)");

            foreach ($cartItems as $item) {
                $shifts = max(1, intval($item['rentalShifts'] ?? 1));
                $pricePerShift = floatval($item['pricePerDay']); 
                $itemTotal = $pricePerShift * intval($item['quantity']) * $shifts;
                
                $startDate = date('Y-m-d H:i:s');
                $hoursToAdd = $shifts * 12;
                $endDate = date('Y-m-d H:i:s', strtotime("+$hoursToAdd hours"));
                $fullAddress = trim("$address, $city", ", ");

                $initialStatus = ($fulfillmentMethod === 'delivery' && strtolower($paymentMethod) !== 'cod') ? 'confirmed' : 'pending';

                $insOrder->execute([
                    ':userId'      => intval($user->id), 
                    ':cameraId'    => intval($item['cameraId']), 
                    ':quantity'    => intval($item['quantity']),
                    ':startDate'   => $startDate, 
                    ':endDate'     => $endDate, 
                    ':totalPrice'  => $itemTotal, 
                    ':address'     => $fullAddress,
                    ':adhar'       => $adharNo, 
                    ':payment'     => $paymentMethod,
                    ':fulfillment' => $fulfillmentMethod,
                    ':status'      => $initialStatus
                ]);

                $orderId = $this->db->lastInsertId();
                $insertedOrders[] = ["id" => intval($orderId), "cameraId" => intval($item['cameraId']), "status" => $initialStatus];
                $emailSummary[] = "- {$item['brand']} {$item['camName']} [{$shifts} Shifts x{$item['quantity']}] -> ₹$itemTotal";
                $grandTotal += $itemTotal;
            }

            $grandTotal += $deliveryCharge;
            $this->db->prepare("DELETE FROM carts WHERE userId = :userId")->execute([':userId' => intval($user->id)]);
            $this->db->commit();

            $adminEmail = $this->env['ADMIN_EMAIL'] ?? '';
            if (!empty($adminEmail)) {
                $subject = "📸 NEW SHIFT ORDER [" . strtoupper($fulfillmentMethod) . "]: " . strtoupper($userName);
                $msgBody = "Order processed via $paymentMethod ($fulfillmentMethod).\n\n" . implode("\n", $emailSummary) . "\n\nDelivery Surcharge: ₹$deliveryCharge\nGrand Total: ₹$grandTotal";
                @mail($adminEmail, $subject, $msgBody, "From: noreply@smartmedialivesolution.com");
            }

            http_response_code(201); 
            echo json_encode(["success" => true, "message" => "Shift order placed successfully!", "data" => $insertedOrders, "grandTotal" => $grandTotal]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            http_response_code(500); 
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function cancelOrder($id) {
        $auth = new AuthController(); 
        if (method_exists($auth, '__dirname_init')) { $auth->__dirname_init(); }
        $user = $auth->protect();
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = :id AND userId = :userId LIMIT 1");
            $stmt->execute([':id' => intval($id), ':userId' => intval($user->id)]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                http_response_code(404); 
                echo json_encode(["success" => false, "message" => "Order track trace record not found."]); 
                return;
            }
            if (in_array($order['status'], ['cancelled', 'returned'])) {
                http_response_code(400); 
                echo json_encode(["success" => false, "message" => "This order status has already been finalized."]); 
                return;
            }
            if ($order['fulfillmentMethod'] !== 'delivery') {
                http_response_code(403); 
                echo json_encode(["success" => false, "message" => "Cancellation denied. Self-pickup orders cannot be canceled."]); 
                return;
            }

            $totalPaid = floatval($order['totalPrice']);
            $cancellationFee = 0.00;
            $refundAmount = $totalPaid;

            if (strtolower($order['paymentMethod']) !== 'cod') {
                $cancellationFee = $totalPaid * 0.20; // 20% Penalty Deduction Logic
                $refundAmount = $totalPaid - $cancellationFee;
            } else {
                $refundAmount = 0.00;
            }

            $this->db->beginTransaction();
            $this->db->prepare("UPDATE orders SET status = 'cancelled', cancellationFee = :fee, refundAmount = :refund WHERE id = :id")
                     ->execute([':fee' => $cancellationFee, ':refund' => $refundAmount, ':id' => intval($id)]);
            $this->db->commit();

            echo json_encode([
                "success" => true, 
                "message" => "Booking canceled successfully.", 
                "totalPaid" => $totalPaid, 
                "cancellationFee" => $cancellationFee, 
                "refundIssued" => $refundAmount
            ]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            http_response_code(500); 
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function processOrderReturn($id) {
        $auth = new AuthController(); 
        if (method_exists($auth, '__dirname_init')) { $auth->__dirname_init(); }
        $auth->protect('admin');
        
        try {
            $stmt = $this->db->prepare("SELECT o.*, c.pricePerDay FROM orders o INNER JOIN cameras c ON o.cameraId = c.id WHERE o.id = :id LIMIT 1");
            $stmt->execute([':id' => intval($id)]); 
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                http_response_code(404); 
                echo json_encode(["success" => false, "message" => "Target tracking entry missing."]); 
                return;
            }

            $currentTime = time();
            $scheduledEndTime = strtotime($order['rentalEndDate']);
            $extraCharges = 0.00; 
            $extraShiftsBilled = 0;

            if ($currentTime > $scheduledEndTime) {
                $overdueHours = ($currentTime - $scheduledEndTime) / 3600;
                $extraShiftsBilled = ceil($overdueHours / 12); // Compounded 12-hour rollover metric calculation
                $extraCharges = $extraShiftsBilled * floatval($order['pricePerDay']) * intval($order['quantity']);
            }

            $this->db->beginTransaction();
            $finalPrice = floatval($order['totalPrice']) + $extraCharges;
            $this->db->prepare("UPDATE orders SET status = 'returned', totalPrice = :finalPrice WHERE id = :id")
                     ->execute([':finalPrice' => $finalPrice, ':id' => intval($id)]);
            $this->db->commit();

            echo json_encode([
                "success" => true, 
                "message" => "Equipment returned.", 
                "basePrice" => floatval($order['totalPrice']), 
                "extraShiftsBilled" => $extraShiftsBilled, 
                "overstaySurcharge" => $extraCharges, 
                "finalAmountDue" => $finalPrice
            ]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            http_response_code(500); 
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function getMyOrders() {
        $auth = new AuthController(); 
        if (method_exists($auth, '__dirname_init')) { $auth->__dirname_init(); }
        $user = $auth->protect();
        
        try {
            $stmt = $this->db->prepare("SELECT o.*, c.name as cameraName, c.brand, (SELECT ci.url FROM camera_images ci WHERE ci.cameraId = o.cameraId LIMIT 1) as cameraImageUrl FROM orders o INNER JOIN cameras c ON o.cameraId = c.id WHERE o.userId = :userId ORDER BY o.created_at DESC");
            $stmt->execute([':userId' => intval($user->id)]); 
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $formatted = [];
            foreach ($orders as $o) {
                $formatted[] = [
                    "id" => intval($o['id']), 
                    "userId" => intval($o['userId']), 
                    "cameraId" => intval($o['cameraId']), 
                    "totalPrice" => floatval($o['totalPrice']), 
                    "cancellationFee" => floatval($o['cancellationFee'] ?? 0), 
                    "refundAmount" => floatval($o['refundAmount'] ?? 0), 
                    "status" => $o['status'], 
                    "rentalStartDate" => $o['rentalStartDate'], 
                    "rentalEndDate" => $o['rentalEndDate'], 
                    "quantity" => intval($o['quantity']), 
                    "shippingAddress" => $o['shippingAddress'], 
                    "paymentMethod" => $o['paymentMethod'], 
                    "fulfillmentMethod" => $o['fulfillmentMethod'], 
                    "created_at" => $o['created_at'],
                    "Camera" => [
                        "name" => $o['cameraName'], 
                        "brand" => $o['brand'], 
                        "images" => $o['cameraImageUrl'] ? [["url" => $this->formatImageUrl($o['cameraImageUrl'])]] : []
                    ]
                ];
            }
            echo json_encode(["success" => true, "count" => count($formatted), "data" => $formatted]);
        } catch (Exception $e) {
            http_response_code(500); 
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }
}
?>