<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/AuthController.php';

class CartController {
    private $db;

    // Use a native constructor so the DB instantly connects when instantiated
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function addToCart() {
        $auth = new AuthController(); 
        if (method_exists($auth, '__construct')) {
            // Adjust if your AuthController still uses the old init naming layout
        } else if (method_exists($auth, '__dirname_init')) {
            $auth->__dirname_init(); 
        }
        $userPayload = $auth->protect();
        
        $data = json_decode(file_get_contents("php://input"));
        try {
            $cameraId = isset($data->cameraId) ? intval($data->cameraId) : null;
            $quantity = isset($data->quantity) ? intval($data->quantity) : 1;
            
            // Check both layout names to prevent crash alignment drops
            $rentalShifts = 1;
            if (isset($data->rentalShifts)) {
                $rentalShifts = intval($data->rentalShifts);
            } elseif (isset($data->rentalDays)) {
                $rentalShifts = intval($data->rentalDays);
            }

            if (!$cameraId) {
                http_response_code(400); 
                echo json_encode(["success" => false, "message" => "Camera ID is required"]); 
                return;
            }

            $cartModel = new Cart($this->db);
            $cartItem = $cartModel->findOne($userPayload->id, $cameraId);

            if ($cartItem) {
                $newQuantity = $cartItem['quantity'] + $quantity;
                $newShifts = $rentalShifts ? $rentalShifts : $cartItem['rentalShifts'];
                $cartModel->updateItem($cartItem['id'], $userPayload->id, $newQuantity, $newShifts);
            } else {
                $cartModel->create($userPayload->id, $cameraId, $quantity, $rentalShifts);
            }

            echo json_encode([
                "success" => true, 
                "message" => "Item mapped to shifts successfully.", 
                "count" => $cartModel->countUserItems($userPayload->id)
            ]);
        } catch (Exception $e) {
            http_response_code(500); 
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function getUserCart() {
        $auth = new AuthController();
        if (method_exists($auth, '__dirname_init')) { $auth->__dirname_init(); }
        $userPayload = $auth->protect();
        
        try {
            $cartModel = new Cart($this->db);
            $rawItems = $cartModel->findUserCart($userPayload->id);
            $cartSubtotal = 0; 
            $formattedItems = [];

            foreach ($rawItems as $item) {
                // Ensure fallback maps correctly to prevent 0 multiplication breaks
                $price = floatval($item['pricePerDay']); 
                $shifts = max(1, intval($item['rentalShifts'] ?? 1));
                $qty = intval($item['quantity']);
                
                $itemTotal = $price * $qty * $shifts;
                $cartSubtotal += $itemTotal;

                $formattedItems[] = [
                    "id"           => intval($item['id']),
                    "userId"       => intval($item['userId']),
                    "cameraId"     => intval($item['cameraId']),
                    "quantity"     => $qty,
                    "rentalShifts" => $shifts,
                    "itemTotal"    => $itemTotal,
                    "Camera" => [
                        "name" => $item['cameraName'], 
                        "brand" => $item['brand'], 
                        "pricePerShift" => $price,
                        "images" => $item['primaryImage'] ? [["url" => $item['primaryImage']]] : []
                    ]
                ];
            }
            echo json_encode([
                "success" => true, 
                "count" => count($formattedItems), 
                "cartSubtotal" => $cartSubtotal, 
                "data" => $formattedItems
            ]);
        } catch (Exception $e) {
            http_response_code(500); 
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function updateCartItem($id) {
        $auth = new AuthController(); 
        if (method_exists($auth, '__dirname_init')) { $auth->__dirname_init(); }
        $userPayload = $auth->protect();
        
        $data = json_decode(file_get_contents("php://input"));
        try {
            $cartModel = new Cart($this->db);
            
            // Check if item exists
            $stmt = $this->db->prepare("SELECT * FROM carts WHERE id = :id AND userId = :userId LIMIT 1");
            $stmt->execute([':id' => $id, ':userId' => $userPayload->id]); 
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) { 
                http_response_code(404); 
                echo json_encode(["success" => false, "message" => "Item missing"]); 
                return; 
            }

            $quantity = isset($data->quantity) ? max(1, intval($data->quantity)) : intval($current['quantity']);
            
            // Checking shift and day keys interchangeably to completely safeguard runtime parameters
            $rentalShifts = intval($current['rentalShifts']);
            if (isset($data->rentalShifts)) {
                $rentalShifts = max(1, intval($data->rentalShifts));
            } elseif (isset($data->rentalDays)) {
                $rentalShifts = max(1, intval($data->rentalDays));
            }

            $cartModel->updateItem($id, $userPayload->id, $quantity, $rentalShifts);
            echo json_encode(["success" => true, "message" => "Shifts configuration updated successfully."]);
        } catch (Exception $e) {
            http_response_code(500); 
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function removeFromCart($id) {
        $auth = new AuthController(); 
        if (method_exists($auth, '__dirname_init')) { $auth->__dirname_init(); }
        $userPayload = $auth->protect();
        
        try {
            $cartModel = new Cart($this->db);
            if (!$cartModel->deleteItem($id, $userPayload->id)) { 
                http_response_code(404); 
                echo json_encode(["success" => false, "message" => "Not found"]); 
                return; 
            }
            echo json_encode([
                "success" => true, 
                "message" => "Removed", 
                "count" => $cartModel->countUserItems($userPayload->id)
            ]);
        } catch (Exception $e) {
            http_response_code(500); 
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }
}
?>