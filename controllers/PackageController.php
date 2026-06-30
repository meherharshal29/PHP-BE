<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Package.php';
require_once __DIR__ . '/AuthController.php';

class PackageController {
    private $db;
    private $env;

    public function __dirname_init() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->env = require __DIR__ . '/../config/env.php';
    }

    // HELPER: Upload asset direct to Cloudinary API Endpoint via cURL
    private function uploadToCloudinary($files, $folder) {
        if (!isset($files['tmp_name']) || empty($files['tmp_name'])) return [];
        
        $uploadedAssets = [];
        $fileCount = is_array($files['name']) ? count($files['name']) : 1;

        for ($i = 0; $i < $fileCount; $i++) {
            $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];

            if ($error !== UPLOAD_ERR_OK) continue;

            $timestamp = time();
            $signatureString = "folder=" . $folder . "&timestamp=" . $timestamp . $this->env['CLOUD_API_SECRET'];
            $signature = sha1($signatureString);

            $cFile = new CURLFile($tmpName, is_array($files['type']) ? $files['type'][$i] : $files['type'], is_array($files['name']) ? $files['name'][$i] : $files['name']);

            $postFields = [
                'file' => $cFile,
                'folder' => $folder,
                'api_key' => $this->env['CLOUD_API_KEY'],
                'timestamp' => $timestamp,
                'signature' => $signature
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.cloudinary.com/v1_1/" . $this->env['CLOUD_NAME'] . "/auto/upload");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (isset($response['secure_url'])) {
                $uploadedAssets[] = [
                    'url' => $response['secure_url'],
                    'cloudinary_id' => $response['public_id']
                ];
            }
        }
        return $uploadedAssets;
    }

    // HELPER: Delete image element completely from Cloudinary network
    private function destroyCloudinaryAsset($publicId) {
        $timestamp = time();
        $signatureString = "public_id=" . $publicId . "&timestamp=" . $timestamp . $this->env['CLOUD_API_SECRET'];
        $signature = sha1($signatureString);

        $postFields = [
            'public_id' => $publicId,
            'api_key' => $this->env['CLOUD_API_KEY'],
            'timestamp' => $timestamp,
            'signature' => $signature
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.cloudinary.com/v1_1/" . $this->env['CLOUD_NAME'] . "/image/destroy");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    private function parseIncomingPackageFields($data, $isUpdate = false, $fallback = []) {
        $toBool = function($val) { return ($val === 'true' || $val === true || $val === 1 || $val === '1') ? 1 : 0; };
        
        $albumDetails = isset($data['albumDetails']) ? $data['albumDetails'] : ($isUpdate ? $fallback['albumDetails'] : '{}');
        $deliverables = isset($data['deliverables']) ? $data['deliverables'] : ($isUpdate ? $fallback['deliverables'] : '[]');

        return [
            ':title' => isset($data['title']) ? $data['title'] : ($isUpdate ? $fallback['title'] : ''),
            ':category' => isset($data['category']) ? $data['category'] : ($isUpdate ? $fallback['category'] : 'Wedding'),
            ':price' => isset($data['price']) ? floatval($data['price']) : ($isUpdate ? floatval($fallback['price']) : 0.00),
            ':description' => isset($data['description']) ? $data['description'] : ($isUpdate ? $fallback['description'] : null),
            ':includesDrone' => isset($data['includesDrone']) ? $toBool($data['includesDrone']) : ($isUpdate ? $fallback['includesDrone'] : 0),
            ':includesCandid' => isset($data['includesCandid']) ? $toBool($data['includesCandid']) : ($isUpdate ? $fallback['includesCandid'] : 0),
            ':includesCinematicVideo' => isset($data['includesCinematicVideo']) ? $toBool($data['includesCinematicVideo']) : ($isUpdate ? $fallback['includesCinematicVideo'] : 0),
            ':includesTraditionalVideo' => isset($data['includesTraditionalVideo']) ? $toBool($data['includesTraditionalVideo']) : ($isUpdate ? $fallback['includesTraditionalVideo'] : 1),
            ':includesPreWedding' => isset($data['includesPreWedding']) ? $toBool($data['includesPreWedding']) : ($isUpdate ? $fallback['includesPreWedding'] : 0),
            ':includesLiveStreaming' => isset($data['includesLiveStreaming']) ? $toBool($data['includesLiveStreaming']) : ($isUpdate ? $fallback['includesLiveStreaming'] : 0),
            ':includesCrane' => isset($data['includesCrane']) ? $toBool($data['includesCrane']) : ($isUpdate ? $fallback['includesCrane'] : 0),
            ':includesLedWall' => isset($data['includesLedWall']) ? $toBool($data['includesLedWall']) : ($isUpdate ? $fallback['includesLedWall'] : 0),
            ':albumDetails' => is_string($albumDetails) ? $albumDetails : json_encode($albumDetails),
            ':deliverables' => is_string($deliverables) ? $deliverables : json_encode($deliverables)
        ];
    }

    public function getAllPackages() {
        $packageModel = new Package($this->db);
        echo json_encode(["success" => true, "data" => $packageModel->findAll()]);
    }

    public function getPackageById($id) {
        $packageModel = new Package($this->db);
        $pkg = $packageModel->findByPk($id);
        if (!$pkg) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Package not found"]);
            return;
        }
        echo json_encode(["success" => true, "data" => $pkg]);
    }

    public function bookPackage() {
        $auth = new AuthController(); $auth->__dirname_init(); $user = $auth->protect();
        $data = json_decode(file_get_contents("php://input"), true);

        try {
            if (empty($data['packageId']) || empty($data['eventDate']) || empty($data['eventLocation'])) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Missing booking operational parameters"]);
                return;
            }

            $ins = $this->db->prepare("INSERT INTO bookings (packageId, userId, eventDate, eventLocation, specialRequirements, status) 
                                       VALUES (:packageId, :userId, :eventDate, :eventLocation, :specialRequirements, 'Pending')");
            $ins->execute([
                ':packageId' => intval($data['packageId']),
                ':userId' => $user->id,
                ':eventDate' => $data['eventDate'],
                ':eventLocation' => $data['eventLocation'],
                ':specialRequirements' => isset($data['specialRequirements']) ? $data['specialRequirements'] : null
            ]);

            http_response_code(201);
            echo json_encode(["success" => true, "bookingId" => $this->db->lastInsertId()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function getMyBookings() {
        $auth = new AuthController(); $auth->__dirname_init(); $user = $auth->protect();

        $stmt = $this->db->prepare("SELECT b.*, p.title, p.price, p.coverImage FROM bookings b 
                                    INNER JOIN packages p ON b.packageId = p.id 
                                    WHERE b.userId = :userId ORDER BY b.created_at DESC");
        $stmt->execute([':userId' => $user->id]);
        echo json_encode(["success" => true, "data" => $stmt->fetchAll()]);
    }

    public function createPackage() {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');
        $this->db->beginTransaction();

        try {
            $fields = $this->parseIncomingPackageFields($_POST);
            $query = "INSERT INTO packages (title, category, price, description, includesDrone, includesCandid, includesCinematicVideo, includesTraditionalVideo, includesPreWedding, includesLiveStreaming, includesCrane, includesLedWall, albumDetails, deliverables) 
                      VALUES (:title, :category, :price, :description, :includesDrone, :includesCandid, :includesCinematicVideo, :includesTraditionalVideo, :includesPreWedding, :includesLiveStreaming, :includesCrane, :includesLedWall, :albumDetails, :deliverables)";
            
            $this->db->prepare($query)->execute($fields);
            $packageId = $this->db->lastInsertId();

            if (isset($_FILES['images'])) {
                $gallery = $this->uploadToCloudinary($_FILES['images'], 'smart_media_packages');
                if (!empty($gallery)) {
                    foreach ($gallery as $index => $img) {
                        $this->db->prepare("INSERT INTO package_images (packageId, url, cloudinary_id) VALUES (:packageId, :url, :cloudinary_id)")
                                 ->execute([':packageId' => $packageId, ':url' => $img['url'], ':cloudinary_id' => $img['cloudinary_id']]);
                        
                        if ($index === 0) {
                            $this->db->prepare("UPDATE packages SET coverImage = :cover WHERE id = :id")->execute([':cover' => $gallery[0]['url'], ':id' => $packageId]);
                        }
                    }
                }
            }

            $this->db->commit();
            http_response_code(201);
            echo json_encode(["success" => true, "packageId" => $packageId]);
        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function updatePackage($id) {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');
        $this->db->beginTransaction();

        try {
            $sel = $this->db->prepare("SELECT * FROM packages WHERE id = :id LIMIT 1");
            $sel->execute([':id' => $id]);
            $current = $sel->fetch();

            if (!$current) {
                $this->db->rollBack(); http_response_code(404);
                echo json_encode(["success" => false, "message" => "Package target structure missing"]);
                return;
            }

            $fields = $this->parseIncomingPackageFields($_POST, true, $current);
            $fields[':id'] = $id;

            $upQuery = "UPDATE packages SET title = :title, category = :category, price = :price, description = :description, includesDrone = :includesDrone, includesCandid = :includesCandid, includesCinematicVideo = :includesCinematicVideo, includesTraditionalVideo = :includesTraditionalVideo, includesPreWedding = :includesPreWedding, includesLiveStreaming = :includesLiveStreaming, includesCrane = :includesCrane, includesLedWall = :includesLedWall, albumDetails = :albumDetails, deliverables = :deliverables WHERE id = :id";
            $this->db->prepare($upQuery)->execute($fields);

            if (isset($_FILES['images'])) {
                $newImgs = $this->uploadToCloudinary($_FILES['images'], 'smart_media_packages');
                foreach ($newImgs as $img) {
                    $this->db->prepare("INSERT INTO package_images (packageId, url, cloudinary_id) VALUES (:packageId, :url, :cloudinary_id)")
                             ->execute([':packageId' => $id, ':url' => $img['url'], ':cloudinary_id' => $img['cloudinary_id']]);
                }
                if (empty($current['coverImage']) && !empty($newImgs)) {
                    $this->db->prepare("UPDATE packages SET coverImage = :cover WHERE id = :id")->execute([':cover' => $newImgs[0]['url'], ':id' => $id]);
                }
            }

            $this->db->commit();
            echo json_encode(["success" => true, "message" => "Package tracking matrices update executed successfully"]);
        } catch (Exception $e) {
            $this->db->rollBack(); http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function deletePackage($id) {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("SELECT cloudinary_id FROM package_images WHERE packageId = :id");
            $stmt->execute([':id' => $id]);
            $images = $stmt->fetchAll();

            foreach ($images as $img) {
                if (!empty($img['cloudinary_id'])) $this->destroyCloudinaryAsset($img['cloudinary_id']);
            }

            $del = $this->db->prepare("DELETE FROM packages WHERE id = :id");
            $del->execute([':id' => $id]);

            $this->db->commit();
            echo json_encode(["success" => true, "message" => "Package and remote Cloudinary assets wiped completely"]);
        } catch (Exception $e) {
            $this->db->rollBack(); http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function deleteImage($imageId) {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');
        
        $stmt = $this->db->prepare("SELECT * FROM package_images WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $imageId]);
        $img = $stmt->fetch();

        if (!$img) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Target object image allocation index out of bounds"]);
            return;
        }

        if (!empty($img['cloudinary_id'])) $this->destroyCloudinaryAsset($img['cloudinary_id']);
        
        $this->db->prepare("DELETE FROM package_images WHERE id = :id")->execute([':id' => $imageId]);
        echo json_encode(["success" => true, "message" => "Asset target item cleared from dashboard layer"]);
    }

    public function getUserFullDetails($userId) {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');

        $userStmt = $this->db->prepare("SELECT id, name, email, phone, accountStatus, role FROM users WHERE id = :id LIMIT 1");
        $userStmt->execute([':id' => $userId]);
        $profile = $userStmt->fetch();

        if (!$profile) {
            http_response_code(404); echo json_encode(["success" => false, "message" => "User record profiles not exist"]); return;
        }

        // Fetch user rental orders context history tracking
        $ordStmt = $this->db->prepare("SELECT o.*, c.name as cameraName, c.brand FROM orders o INNER JOIN cameras c ON o.cameraId = c.id WHERE o.userId = :id ORDER BY o.created_at DESC");
        $ordStmt->execute([':id' => $userId]);
        $orders = $ordStmt->fetchAll();

        // Fetch user packages bookings transaction logs matrix
        $bkStmt = $this->db->prepare("SELECT b.*, p.title, p.price, p.coverImage FROM bookings b INNER JOIN packages p ON b.packageId = p.id WHERE b.userId = :id ORDER BY b.created_at DESC");
        $bkStmt->execute([':id' => $userId]);
        $bookings = $bkStmt->fetchAll();

        echo json_encode([
            "success" => true,
            "data" => [
                "profile" => $profile,
                "orderHistory" => $orders,
                "bookingHistory" => $bookings
            ]
        ]);
    }

    public function getAllBookings() {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');

        $query = "SELECT b.*, u.name as userName, u.email as userEmail, u.phone as userPhone, p.title as packageTitle, p.price as packagePrice, p.coverImage 
                  FROM bookings b
                  INNER JOIN users u ON b.userId = u.id
                  INNER JOIN packages p ON b.packageId = p.id
                  ORDER BY b.created_at DESC";
        
        $stmt = $this->db->query($query);
        echo json_encode(["success" => true, "data" => $stmt->fetchAll()]);
    }

    public function updateBookingStatus($id) {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');
        $data = json_decode(file_get_contents("php://input"), true);

        $status = isset($data['status']) ? $data['status'] : '';
        if (!in_array($status, ['Pending', 'Confirmed', 'Cancelled', 'Completed'])) {
            http_response_code(400); echo json_encode(["success" => false, "message" => "Invalid booking status variant type"]); return;
        }

        $stmt = $this->db->prepare("UPDATE bookings SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);
        echo json_encode(["success" => true, "message" => "Booking lifecycle updated state to: " . $status]);
    }
}
?>