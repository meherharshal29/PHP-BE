<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AuthController.php';

class AdminController {
    private $db;
    private $env;

    public function __dirname_init() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->env = require __DIR__ . '/../config/env.php';
    }

    private function formatImageUrl($url) {
        if (empty($url)) return null;
        if (filter_var($url, FILTER_VALIDATE_URL)) return $url;
        return $this->env['CLIENT_URL'] . '/' . ltrim(str_replace('\\', '/', $url), '/');
    }

    // 1. DASHBOARD OVERVIEW & COMPACT KPIS METRICS
    public function getAdminDashboard() {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');

        try {
            // Recent Logistics History (Top 10 Orders)
            $ordQuery = "SELECT o.*, u.name as userName, u.email as userEmail, u.phone as userPhone, u.isOnline as userOnline,
                                c.name as cameraName, c.brand,
                                (SELECT ci.url FROM camera_images ci WHERE ci.cameraId = o.cameraId LIMIT 1) as cameraImageUrl
                         FROM orders o
                         INNER JOIN users u ON o.userId = u.id
                         INNER JOIN cameras c ON o.cameraId = c.id
                         ORDER BY o.created_at DESC LIMIT 10";
            $ordersStmt = $this->db->query($ordQuery);
            $rawOrders = $ordersStmt->fetchAll();

            $formattedOrders = [];
            foreach ($rawOrders as $o) {
                $formattedOrders[] = [
                    "id" => intval($o['id']),
                    "userId" => intval($o['userId']),
                    "cameraId" => intval($o['cameraId']),
                    "quantity" => intval($o['quantity']),
                    "totalPrice" => floatval($o['totalPrice']),
                    "rentalStartDate" => $o['rentalStartDate'],
                    "rentalEndDate" => $o['rentalEndDate'],
                    "status" => $o['status'],
                    "created_at" => $o['created_at'],
                    "user" => [
                        "id" => intval($o['userId']),
                        "name" => $o['userName'],
                        "email" => $o['userEmail'],
                        "phone" => $o['userPhone'],
                        "isOnline" => (int)$o['userOnline'] === 1
                    ],
                    "Camera" => [
                        "id" => intval($o['cameraId']),
                        "name" => $o['cameraName'],
                        "brand" => $o['brand'],
                        "images" => $o['cameraImageUrl'] ? [["url" => $this->formatImageUrl($o['cameraImageUrl'])]] : []
                    ]
                ];
            }

            // Aggregate Total Company Revenue Matrix
            $revQuery = "SELECT SUM(totalPrice) as totalRevenue FROM orders WHERE status IN ('confirmed', 'shipped', 'delivered', 'returned')";
            $totalRevenue = floatval($this->db->query($revQuery)->fetch()['totalRevenue'] ?? 0);

            // Current Month Performance Metrics
            $firstDayOfMonth = date('Y-m-01 00:00:00');
            $monQuery = "SELECT SUM(totalPrice) as monthlyRevenue FROM orders WHERE status IN ('confirmed', 'shipped', 'delivered') AND created_at >= :firstDay";
            $mStmt = $this->db->prepare($monQuery); $mStmt->execute([':firstDay' => $firstDayOfMonth]);
            $monthlyRevenue = floatval($mStmt->fetch()['monthlyRevenue'] ?? 0);

            // Logistics Status Aggregation Loops
            $statusCounts = $this->db->query("SELECT status, COUNT(id) as count FROM orders GROUP BY status")->fetchAll();
            $rentals = ['pending' => 0, 'confirmed' => 0, 'shipped' => 0, 'delivered' => 0, 'returned' => 0, 'cancelled' => 0];
            foreach ($statusCounts as $sc) {
                $statusKey = strtolower($sc['status']);
                if ($statusKey === 'cancel') $statusKey = 'cancelled';
                if (array_key_exists($statusKey, $rentals)) $rentals[$statusKey] = intval($sc['count']);
            }

            // Global System Users Counter Matrices
            $totalUsers = intval($this->db->query("SELECT COUNT(*) FROM users")->fetchColumn());
            $onlineUsers = intval($this->db->query("SELECT COUNT(*) FROM users WHERE isOnline = 1")->fetchColumn());
            $activeShoots = intval($this->db->query("SELECT COUNT(*) FROM bookings WHERE status = 'Confirmed'")->fetchColumn());

            // Support Call Requests Allocation Tally
            $callStats = $this->db->query("SELECT status, COUNT(id) as count FROM call_requests GROUP BY status")->fetchAll();
            $callRequests = ['pending' => 0, 'completed' => 0, 'cancelled' => 0];
            foreach ($callStats as $cs) {
                $cKey = strtolower($cs['status']);
                if (array_key_exists($cKey, $callRequests)) $callRequests[$cKey] = intval($cs['count']);
            }

            echo json_encode([
                "success" => true,
                "stats" => [
                    "totalRevenue" => $totalRevenue,
                    "monthlyRevenue" => $monthlyRevenue,
                    "totalUsers" => $totalUsers,
                    "onlineUsers" => $onlineUsers,
                    "activeShoots" => $activeShoots,
                    "rentals" => $rentals,
                    "callRequests" => $callRequests
                ],
                "orders" => $formattedOrders
            ]);
        } catch (Exception $e) {
            http_response_code(500); echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    // 2. ANALYTICS (TRENDING GEARS & MONTHLY GRAPHS BAR DATA)
    public function getAdminAnalytics() {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');

        try {
            $mostRentedQuery = "SELECT o.cameraId, COUNT(o.id) as rentalCount, c.name, c.brand 
                                FROM orders o INNER JOIN cameras c ON o.cameraId = c.id 
                                GROUP BY o.cameraId, c.id, c.name, c.brand 
                                ORDER BY rentalCount DESC LIMIT 5";
            $mostRented = $this->db->query($mostRentedQuery)->fetchAll();
            foreach ($mostRented as &$mr) { $mr['rentalCount'] = intval($mr['rentalCount']); $mr['cameraId'] = intval($mr['cameraId']); }

            $chartQuery = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(totalPrice) as monthlyRevenue 
                           FROM orders WHERE status IN ('confirmed', 'shipped', 'returned', 'delivered') 
                           GROUP BY month ORDER BY month ASC LIMIT 6";
            $revenueChart = $this->db->query($chartQuery)->fetchAll();
            foreach ($revenueChart as &$rc) { $rc['monthlyRevenue'] = floatval($rc['monthlyRevenue']); }

            echo json_encode(["success" => true, "analytics" => ["mostRented" => $mostRented, "revenueChart" => $revenueChart]]);
        } catch (Exception $e) {
            http_response_code(500); echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    // 3. GET ALL REGISTERED CLIENTS DATA
    public function getAllUsers() {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');

        $stmt = $this->db->query("SELECT id, name, email, phone, isOnline, isActive, role, created_at FROM users ORDER BY isOnline DESC, created_at DESC");
        $users = $stmt->fetchAll();
        foreach ($users as &$u) {
            $u['id'] = intval($u['id']);
            $u['isOnline'] = (int)$u['isOnline'] === 1;
            $u['isActive'] = (int)$u['isActive'] === 1;
        }
        echo json_encode(["success" => true, "users" => $users]);
    }

    // 4. DEEP-DIVE USER RADAR METRICS LINK
    public function getUserAllDetails($id) {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');

        try {
            $uStmt = $this->db->prepare("SELECT id, name, email, phone, role, isOnline, isActive, created_at FROM users WHERE id = :id LIMIT 1");
            $uStmt->execute([':id' => $id]); $user = $uStmt->fetch();

            if (!$user) { http_response_code(404); echo json_encode(["success" => false, "message" => "User profile data missing"]); return; }
            $user['isOnline'] = (int)$user['isOnline'] === 1; $user['isActive'] = (int)$user['isActive'] === 1;

            // Operational gear history array list
            $ordStmt = $this->db->prepare("SELECT o.*, c.name as cameraName, c.brand, (SELECT ci.url FROM camera_images ci WHERE ci.cameraId = o.cameraId LIMIT 1) as imgUrl FROM orders o INNER JOIN cameras c ON o.cameraId = c.id WHERE o.userId = :id ORDER BY o.created_at DESC");
            $ordStmt->execute([':id' => $id]); $rawOrders = $ordStmt->fetchAll();
            $orders = [];
            foreach ($rawOrders as $ro) {
                $orders[] = [
                    "id" => intval($ro['id']), "cameraId" => intval($ro['cameraId']), "quantity" => intval($ro['quantity']), "totalPrice" => floatval($ro['totalPrice']),
                    "rentalStartDate" => $ro['rentalStartDate'], "rentalEndDate" => $ro['rentalEndDate'], "status" => $ro['status'], "created_at" => $ro['created_at'],
                    "Camera" => ["name" => $ro['cameraName'], "brand" => $ro['brand'], "images" => $ro['imgUrl'] ? [["url" => $this->formatImageUrl($ro['imgUrl'])]] : []]
                ];
            }

            // Shoot Event Bookings log traces
            $bkStmt = $this->db->prepare("SELECT b.*, p.title, p.price, p.coverImage FROM bookings b INNER JOIN packages p ON b.packageId = p.id WHERE b.userId = :id ORDER BY b.eventDate DESC");
            $bkStmt->execute([':id' => $id]); $bookings = $bkStmt->fetchAll();

            // Call tasks logs list array
            $clStmt = $this->db->prepare("SELECT * FROM call_requests WHERE userId = :id ORDER BY created_at DESC");
            $clStmt->execute([':id' => $id]); $callRequests = $clStmt->fetchAll();

            echo json_encode(["success" => true, "data" => ["profile" => $user, "orderHistory" => $orders, "bookingHistory" => $bookings, "callRequests" => $callRequests]]);
        } catch (Exception $e) {
            http_response_code(500); echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function updateUser($id) {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');
        $data = json_decode(file_get_contents("php://input"), true);

        $updates = []; $params = [':id' => $id];
        foreach ($data as $key => $val) {
            if (in_array($key, ['name', 'phone', 'role', 'isActive'])) {
                $updates[] = "`$key` = :$key";
                $params[":$key"] = $key === 'isActive' ? ($val ? 1 : 0) : $val;
            }
        }
        if (empty($updates)) { echo json_encode(["success" => true, "message" => "No explicit parameters to alter"]); return; }

        $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
        $this->db->prepare($query)->execute($params);
        echo json_encode(["success" => true, "message" => "User metrics tracking fields modified successfully"]);
    }

    public function deleteUser($id) {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(["success" => true, "message" => "User deleted."]);
    }

    // 5. LOGISTICS STATUS MUTATOR WITH DISPATCH AUTO-MAILER
    public function updateOrderStatus($id) {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');
        $data = json_decode(file_get_contents("php://input"), true);
        $status = strtolower($data['status'] ?? '');

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("SELECT o.*, u.name as userName, u.email as userEmail, c.name as cameraName, c.brand, c.pricePerDay FROM orders o INNER JOIN users u ON o.userId = u.id INNER JOIN cameras c ON o.cameraId = c.id WHERE o.id = :id FOR UPDATE");
            $stmt->execute([':id' => $id]); $order = $stmt->fetch();

            if (!$order) { $this->db->rollBack(); http_response_code(404); echo json_encode(["success" => false, "message" => "Order not found"]); return; }

            $this->db->prepare("UPDATE orders SET status = :status WHERE id = :id")->execute([':status' => $status, ':id' => $id]);
            $this->db->commit();

            // Native notification trigger mechanics via @mail channel
            try {
                $subject = "Order #{$id} Status Update Alert Tracking";
                $msgBody = "Hello {$order['userName']},\n\nYour Order Matrix Checkpoint dynamic state has advanced to: " . strtoupper($status);
                if ($status === 'returned') {
                    $subject = "✅ Device Return Audit Verification Complete | Order #{$id}";
                    $msgBody = "Dear {$order['userName']},\n\nWe have completed system verification checks on the hardware items returned. This confirms safe inventory log entry storage completion.";
                }
                $headers = "From: noreply@smartmedialivesolution.com\r\nContent-Type: text/plain; charset=UTF-8";
                @mail($order['userEmail'], $subject, $msgBody, $headers);
            } catch (Exception $e) {}

            echo json_encode(["success" => true, "message" => "Status updated successfully."]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            http_response_code(500); echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    // 6. GLOBAL MASS NETWORK BROADCAST MAIL UTILITIES (BATCH MECHANICS)
    public function sendBroadcast() {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');
        $data = json_decode(file_get_contents("php://input"), true);
        $subject = $data['subject'] ?? ''; $message = $data['message'] ?? '';

        if (empty($subject) || empty($message)) { http_response_code(400); echo json_encode(["success" => false, "message" => "Subject and Message required"]); return; }

        $users = $this->db->query("SELECT name, email FROM users WHERE isActive = 1")->fetchAll();
        $successCount = 0;

        foreach ($users as $u) {
            if (!filter_var($u['email'], FILTER_VALIDATE_EMAIL)) continue;
            $personalMsg = "Hello " . $u['name'] . ",\n\n" . $message;
            $headers = "From: noreply@smartmedialivesolution.com\r\nContent-Type: text/plain; charset=UTF-8";
            if (@mail($u['email'], $subject, $personalMsg, $headers)) { $successCount++; }
        }

        echo json_encode(["success" => true, "message" => "Broadcast processing matrix complete. Dispatched: {$successCount} successful deliveries."]);
    }

    public function getAllCallRequests() {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');
        $query = "SELECT cr.*, u.id as userId, u.name as userName, u.email as userEmail, u.phone as userPhone FROM call_requests cr INNER JOIN users u ON cr.userId = u.id ORDER BY cr.created_at DESC";
        echo json_encode(["success" => true, "data" => $this->db->query($query)->fetchAll()]);
    }

    public function updateCallStatus($id) {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');
        $data = json_decode(file_get_contents("php://input"), true);
        $status = strtolower($data['status'] ?? 'pending');

        $this->db->prepare("UPDATE call_requests SET status = :status WHERE id = :id")->execute([':status' => $status, ':id' => $id]);
        echo json_encode(["success" => true, "message" => "Call status metrics index tracking state updated successfully."]);
    }
}
?>