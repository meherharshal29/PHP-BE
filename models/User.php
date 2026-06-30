<?php
class User {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    // 1. Find User by Email
    public function findByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 2. Create User
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
            (name, email, password, phone, role, isActive, isOnline, accountStatus, defaultAddress, defaultCity, defaultAdharNo) 
            VALUES 
            (:name, :email, :password, :phone, :role, :isActive, :isOnline, :accountStatus, :defaultAddress, :defaultCity, :defaultAdharNo)";
        
        $stmt = $this->conn->prepare($query);

        $role = isset($data->role) ? $data->role : 'user';
        $isActive = isset($data->isActive) ? $data->isActive : 1;
        $isOnline = isset($data->isOnline) ? $data->isOnline : 0;
        $accountStatus = isset($data->accountStatus) ? $data->accountStatus : 'active';
        $phone = isset($data->phone) ? $data->phone : null;
        $defaultAddress = isset($data->defaultAddress) ? $data->defaultAddress : null;
        $defaultCity = isset($data->defaultCity) ? $data->defaultCity : null;
        $defaultAdharNo = isset($data->defaultAdharNo) ? $data->defaultAdharNo : null;

        $stmt->bindParam(':name', $data->name);
        $stmt->bindParam(':email', $data->email);
        $stmt->bindParam(':password', $data->password); 
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':isActive', $isActive, PDO::PARAM_INT);
        $stmt->bindParam(':isOnline', $isOnline, PDO::PARAM_INT);
        $stmt->bindParam(':accountStatus', $accountStatus);
        $stmt->bindParam(':defaultAddress', $defaultAddress);
        $stmt->bindParam(':defaultCity', $defaultCity);
        $stmt->bindParam(':defaultAdharNo', $defaultAdharNo);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // 3. Update OTP Details
    public function updateOTP($id, $hashedOtp, $expiry) {
        $query = "UPDATE " . $this->table_name . " SET otp = :otp, otpExpires = :expiry WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':otp' => $hashedOtp,
            ':expiry' => $expiry,
            ':id' => $id
        ]);
    }

    // 4. Update Online Status
    public function updateOnlineStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " SET isOnline = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':status' => $status,
            ':id' => $id
        ]);
    }
}
?>