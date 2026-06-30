<?php
// File: models/Admin.php

class Admin {
    private $conn;
    private $table_name = "admins";

    public function __construct($db) {
        $this->conn = $db;
    }

    // ==========================================   
    // 1. DATA READ METRICS (SCOPES)
    // ==========================================

    /**
     * Find Admin by Email Without Password (Default Scope behavior)
     */
    public function findByEmail($email) {
        $query = "SELECT id, name, email, phone, role, isOnline, isActive, created_at, updated_at 
                  FROM " . $this->table_name . " 
                  WHERE email = :email LIMIT 1";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find Admin With Password Scope specifically for login verification
     */
    public function findByEmailWithPassword($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ==========================================
    // 2. DATA MUTATION METHODS
    // ==========================================

    /**
     * Create Admin Registry Record
     */
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
            (name, email, phone, password, role, isOnline, isActive) 
            VALUES 
            (:name, :email, :phone, :password, :role, :isOnline, :isActive)";
        
        $stmt = $this->conn->prepare($query);

        // Fallbacks for default values mapping
        $role = isset($data->role) ? $data->role : 'admin';
        $isOnline = isset($data->isOnline) ? (int)$data->isOnline : 0;
        $isActive = isset($data->isActive) ? (int)$data->isActive : 1;
        $phone = isset($data->phone) ? $data->phone : null;

        // Secure context binding
        $stmt->bindParam(':name', $data->name);
        $stmt->bindParam(':email', $data->email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':password', $data->password); 
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':isOnline', $isOnline, PDO::PARAM_INT);
        $stmt->bindParam(':isActive', $isActive, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    /**
     * 3. Update Admin OTP Details (Fixes Intelephense P1013)
     */
    public function updateOTP($id, $hashedOtp, $expiry) {
        $query = "UPDATE " . $this->table_name . " SET otp = :otp, otpExpires = :expiry WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':otp' => $hashedOtp,
            ':expiry' => $expiry,
            ':id' => intval($id)
        ]);
    }

    /**
     * 4. Update Admin Online Status (Fixes Intelephense P1013)
     */
    public function updateOnlineStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " SET isOnline = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':status' => intval($status),
            ':id' => intval($id)
        ]);
    }
}
?>