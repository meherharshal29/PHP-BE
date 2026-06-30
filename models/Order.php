<?php
class Order {
    private $conn;
    private $table_name = "orders";

    // --- Object Schema Fields ---
    public $id;
    public $userId;
    public $cameraId;
    public $quantity;
    public $rentalStartDate;
    public $rentalEndDate;
    public $totalPrice;
    public $cancellationFee;
    public $refundAmount;
    public $shippingAddress;
    public $adharNumber;
    public $paymentMethod;
    public $fulfillmentMethod;
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Inserts an order tracking item with parameters for logistics and penalties
     */
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (userId, cameraId, quantity, rentalStartDate, rentalEndDate, totalPrice, cancellationFee, refundAmount, shippingAddress, adharNumber, paymentMethod, fulfillmentMethod, status) 
                  VALUES 
                  (:userId, :cameraId, :quantity, :rentalStartDate, :rentalEndDate, :totalPrice, :cancellationFee, :refundAmount, :shippingAddress, :adharNumber, :paymentMethod, :fulfillmentMethod, :status)";
        
        $stmt = $this->conn->prepare($query);
        
        // Dynamic evaluation to secure defaults if parameters are unassigned
        $cancellationFee = floatval($data['cancellationFee'] ?? 0.00);
        $refundAmount    = floatval($data['refundAmount'] ?? 0.00);
        $fulfillment     = strtolower($data['fulfillmentMethod'] ?? 'pickup');
        $initialStatus   = $data['status'] ?? (($fulfillment === 'delivery' && strtolower($data['paymentMethod']) !== 'cod') ? 'confirmed' : 'pending');

        $stmt->execute([
            ':userId'            => intval($data['userId']),
            ':cameraId'          => intval($data['cameraId']),
            ':quantity'          => intval($data['quantity']),
            ':rentalStartDate'   => $data['rentalStartDate'],
            ':rentalEndDate'     => $data['rentalEndDate'],
            ':totalPrice'        => floatval($data['totalPrice']),
            ':cancellationFee'   => $cancellationFee,
            ':refundAmount'      => $refundAmount,
            ':shippingAddress'   => htmlspecialchars(strip_tags(trim($data['shippingAddress']))),
            ':adharNumber'       => htmlspecialchars(strip_tags(trim($data['adharNumber']))),
            ':paymentMethod'     => strtolower($data['paymentMethod'] ?? 'cod'),
            ':fulfillmentMethod' => $fulfillment,
            ':status'            => $initialStatus
        ]);
        
        return $this->conn->lastInsertId();
    }
}
?>