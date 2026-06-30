<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Camera.php';
require_once __DIR__ . '/AuthController.php';
require_once __DIR__ . '/../utils/CloudinaryUploader.php';

class CameraController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    private function handleCloudinaryUploads($maxSlots = 5) {
        $uploadedUrls = [];
        if (!isset($_FILES['images'])) return $uploadedUrls;

        $files = $_FILES['images'];
        $fileCount = is_array($files['name']) ? count($files['name']) : 1;
        $takeCount = min($fileCount, $maxSlots);

        for ($i = 0; $i < $takeCount; $i++) {
            $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];

            if ($error === UPLOAD_ERR_OK && !empty($tmpName)) {
                try {
                    $cloudUrl = CloudinaryUploader::upload($tmpName);
                    if ($cloudUrl) {
                        $uploadedUrls[] = $cloudUrl;
                    }
                } catch (Exception $e) {
                }
            }
        }
        return $uploadedUrls;
    }

    public function getAllCameras() {
        $categoryId = isset($_GET['categoryId']) ? $_GET['categoryId'] : null;
        $cameraModel = new Camera($this->db);
        header('Content-Type: application/json');
        echo json_encode(["success" => true, "data" => $cameraModel->findAll($categoryId)]);
    }

    public function getCameraById($id) {
        $cameraModel = new Camera($this->db);
        $camera = $cameraModel->findByPk($id);
        header('Content-Type: application/json');
        if (!$camera) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Item not found"]);
            return;
        }
        echo json_encode(["success" => true, "data" => $camera]);
    }

    public function addCamera() {
        $auth = new AuthController();
        $auth->protect('admin');
        header('Content-Type: application/json');

        $fileCount = isset($_FILES['images']['name']) ? (is_array($_FILES['images']['name']) ? count($_FILES['images']['name']) : 1) : 0;
        
        if ($fileCount < 1) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Minimum 1 image is required."]);
            return;
        }
        if ($fileCount > 5) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Maximum of 5 images permitted."]);
            return;
        }

        try {
            $this->db->beginTransaction();

            $name = $_POST['name'] ?? '';
            $brand = $_POST['brand'] ?? null;
            $modelNumber = $_POST['modelNumber'] ?? null;
            $pricePerDay = isset($_POST['pricePerDay']) ? floatval($_POST['pricePerDay']) : 0.0;
            $description = $_POST['description'] ?? null;
            $categoryId = isset($_POST['categoryId']) ? intval($_POST['categoryId']) : 0;
            
            $specifications = $_POST['specifications'] ?? '{}';
            if (is_string($specifications)) {
                json_decode($specifications);
                if (json_last_error() !== JSON_ERROR_NONE) $specifications = '{}';
            } else {
                $specifications = json_encode($specifications);
            }

            $query = "INSERT INTO cameras (name, brand, modelNumber, pricePerDay, description, specifications, categoryId) 
                      VALUES (:name, :brand, :modelNumber, :pricePerDay, :description, :specifications, :categoryId)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':name' => $name, ':brand' => $brand, ':modelNumber' => $modelNumber,
                ':pricePerDay' => $pricePerDay, ':description' => $description,
                ':specifications' => $specifications, ':categoryId' => $categoryId
            ]);

            $cameraId = $this->db->lastInsertId();
            $uploadedUrls = $this->handleCloudinaryUploads(5);

            if (!empty($uploadedUrls)) {
                foreach ($uploadedUrls as $index => $url) {
                    $isPrimary = ($index === 0) ? 1 : 0;
                    $imgQuery = "INSERT INTO camera_images (url, cameraId, isPrimary) VALUES (:url, :cameraId, :isPrimary)";
                    $imgStmt = $this->db->prepare($imgQuery);
                    $imgStmt->execute([':url' => $url, ':cameraId' => $cameraId, ':isPrimary' => $isPrimary]);
                }
            }

            $this->db->commit();
            echo json_encode(["success" => true, "message" => "Added Successfully via Cloudinary Assets System", "id" => intval($cameraId)]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function manageGallery($cameraId) {
        $auth = new AuthController(); 
        $auth->protect('admin');
        header('Content-Type: application/json');

        try {
            $this->db->beginTransaction();

            $removeImageIds = $_POST['removeImageIds'] ?? null;
            if ($removeImageIds) {
                $ids = is_array($removeImageIds) ? $removeImageIds : [$removeImageIds];
                foreach ($ids as $imgId) {
                    $del = $this->db->prepare("DELETE FROM camera_images WHERE id = :id AND cameraId = :cameraId");
                    $del->execute([':id' => $imgId, ':cameraId' => $cameraId]);
                }
            }

            $countStmt = $this->db->prepare("SELECT COUNT(id) as count FROM camera_images WHERE cameraId = :cameraId");
            $countStmt->execute([':cameraId' => $cameraId]);
            $currentCount = intval($countStmt->fetch(PDO::FETCH_ASSOC)['count']);
            $slots = 5 - $currentCount;

            if ($slots > 0) {
                $newUrls = $this->handleCloudinaryUploads($slots);
                foreach ($newUrls as $url) {
                    $ins = $this->db->prepare("INSERT INTO camera_images (url, cameraId, isPrimary) VALUES (:url, :cameraId, 0)");
                    $ins->execute([':url' => $url, ':cameraId' => $cameraId]);
                }
            }

            $checkPrimary = $this->db->prepare("SELECT id FROM camera_images WHERE cameraId = :cameraId AND isPrimary = 1");
            $checkPrimary->execute([':cameraId' => $cameraId]);
            if (!$checkPrimary->fetch()) {
                $setPrimary = $this->db->prepare("UPDATE camera_images SET isPrimary = 1 WHERE cameraId = :cameraId LIMIT 1");
                $setPrimary->execute([':cameraId' => $cameraId]);
            }

            $this->db->commit();
            echo json_encode(["success" => true, "message" => "Cloudinary gallery synced successfully"]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function updateCamera($id) {
        $auth = new AuthController(); 
        $auth->protect('admin');
        header('Content-Type: application/json');

        try {
            $this->db->beginTransaction();

            $cameraModel = new Camera($this->db);
            $camera = $cameraModel->findByPk($id);
            if (!$camera) {
                $this->db->rollBack();
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Not found"]);
                return;
            }

            $keepImageIds = $_POST['keepImageIds'] ?? [];
            if (is_string($keepImageIds)) {
                $keepImageIds = json_decode($keepImageIds, true) ?? [];
            }
            $keepList = is_array($keepImageIds) ? array_map('intval', $keepImageIds) : [];

            if (!empty($keepList)) {
                $inClause = implode(',', $keepList);
                $delQuery = "DELETE FROM camera_images WHERE cameraId = :id AND id NOT IN ($inClause)";
                $this->db->prepare($delQuery)->execute([':id' => $id]);
            } else {
                $this->db->prepare("DELETE FROM camera_images WHERE cameraId = :id")->execute([':id' => $id]);
            }

            $countStmt = $this->db->prepare("SELECT COUNT(id) as count FROM camera_images WHERE cameraId = :id");
            $countStmt->execute([':id' => $id]);
            $retainedCount = intval($countStmt->fetch(PDO::FETCH_ASSOC)['count']);

            $newFileCount = isset($_FILES['images']['name']) ? (is_array($_FILES['images']['name']) ? count($_FILES['images']['name']) : 1) : 0;
            $totalProjectedImages = $retainedCount + $newFileCount;

            if ($totalProjectedImages < 1) {
                $this->db->rollBack();
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Minimum 1 image is required."]);
                return;
            }
            if ($totalProjectedImages > 5) {
                $this->db->rollBack();
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Maximum of 5 images allowed."]);
                return;
            }

            $slotsLeft = 5 - $retainedCount;
            if ($slotsLeft > 0 && $newFileCount > 0) {
                $newUploaded = $this->handleCloudinaryUploads($slotsLeft);
                foreach ($newUploaded as $url) {
                    $this->db->prepare("INSERT INTO camera_images (url, cameraId, isPrimary) VALUES (:url, :id, 0)")
                             ->execute([':url' => $url, ':id' => $id]);
                }
            }

            $checkPrimary = $this->db->prepare("SELECT id FROM camera_images WHERE cameraId = :id AND isPrimary = 1");
            $checkPrimary->execute([':id' => $id]);
            if (!$checkPrimary->fetch()) {
                $this->db->prepare("UPDATE camera_images SET isPrimary = 1 WHERE cameraId = :id LIMIT 1")
                         ->execute([':id' => $id]);
            }

            $name = $_POST['name'] ?? $camera['name'];
            $brand = $_POST['brand'] ?? $camera['brand'];
            $pricePerDay = isset($_POST['pricePerDay']) ? floatval($_POST['pricePerDay']) : $camera['pricePerDay'];
            $description = $_POST['description'] ?? $camera['description'];
            $categoryId = isset($_POST['categoryId']) ? intval($_POST['categoryId']) : $camera['categoryId'];
            $specifications = $_POST['specifications'] ?? json_encode($camera['specifications']);

            $upQuery = "UPDATE cameras SET name = :name, brand = :brand, pricePerDay = :pricePerDay, 
                        description = :description, specifications = :specifications, categoryId = :categoryId WHERE id = :id";
            $this->db->prepare($upQuery)->execute([
                ':name' => $name, ':brand' => $brand, ':pricePerDay' => $pricePerDay,
                ':description' => $description, ':specifications' => $specifications, ':categoryId' => $categoryId, ':id' => $id
            ]);

            $this->db->commit();
            echo json_encode(["success" => true, "message" => "Cloudinary device tracking parameters synchronized"]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function deleteCamera($id) {
        $auth = new AuthController(); 
        $auth->protect('admin');
        header('Content-Type: application/json');

        try {
            $this->db->beginTransaction();
            
            $this->db->prepare("DELETE FROM camera_images WHERE cameraId = :id")->execute([':id' => $id]);
            $stmt = $this->db->prepare("DELETE FROM cameras WHERE id = :id");
            $stmt->execute([':id' => $id]);

            $this->db->commit();
            echo json_encode(["success" => true, "message" => "Deleted Successfully"]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }
}
?>