<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/AuthController.php';

class CategoryController {
    private $db;

    public function __dirname_init() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    // 1. GET ALL CATEGORIES
    public function getAllCategories() {
        try {
            $categoryModel = new Category($this->db);
            $categories = $categoryModel->findAll();
            
            echo json_encode(["success" => true, "data" => $categories]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    // 2. SEED MULTIPLE CATEGORIES AT ONCE (20+ ITEMS WITH IGNORE DUPLICATES)
    public function seedCategories() {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');

        try {
            $categoryList = [
                ['name' => 'Mirrorless Cameras', 'description' => 'Modern professional mirrorless bodies'],
                ['name' => 'DSLR Cameras', 'description' => 'Traditional digital SLR bodies'],
                ['name' => 'Cinema Cameras', 'description' => 'High-end film production equipment'],
                ['name' => 'Action Cameras', 'description' => 'Rugged and compact video gear'],
                ['name' => '360 Cameras', 'description' => 'Spherical VR video capture'],
                ['name' => 'Prime Lenses', 'description' => 'Fixed focal length glass'],
                ['name' => 'Zoom Lenses', 'description' => 'Variable focal length glass'],
                ['name' => 'Macro Lenses', 'description' => 'Extreme close-up photography'],
                ['name' => 'Anamorphic Lenses', 'description' => 'Widescreen cinematic lenses'],
                ['name' => 'Drones', 'description' => 'Aerial photography and videography'],
                ['name' => 'Gimbals & Stabilizers', 'description' => '3-axis stabilization systems'],
                ['name' => 'Tripods & Monopods', 'description' => 'Camera support systems'],
                ['name' => 'LED Continuous Lights', 'description' => 'Constant lighting for video'],
                ['name' => 'RGB Studio Lights', 'description' => 'Creative colored lighting'],
                ['name' => 'External Monitors', 'description' => 'Field field monitors and recorders'],
                ['name' => 'Wireless Video Systems', 'description' => 'Remote video transmitters'],
                ['name' => 'Audio Recorders', 'description' => 'Field and studio sound recorders'],
                ['name' => 'Wireless Microphones', 'description' => 'Lav and clip-on systems'],
                ['name' => 'Shotgun Microphones', 'description' => 'Directional production audio'],
                ['name' => 'Production Monitors', 'description' => 'Large director viewing screens']
            ];

            // Pure SQL 'INSERT IGNORE' mapping mimicking Sequelize { ignoreDuplicates: true }
            $query = "INSERT IGNORE INTO categories (name, description) VALUES (:name, :description)";
            $stmt = $this->db->prepare($query);

            $this->db->beginTransaction();
            foreach ($categoryList as $item) {
                $stmt->execute([
                    ':name' => htmlspecialchars(strip_tags($item['name'])),
                    ':description' => htmlspecialchars(strip_tags($item['description']))
                ]);
            }
            $this->db->commit();

            http_response_code(201);
            echo json_encode(["success" => true, "message" => "20 Categories processed successfully via ignore boundaries."]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    // 3. POST: ADD SINGLE CATEGORY MANUALLY
    public function createCategory() {
        $auth = new AuthController(); $auth->__dirname_init(); $auth->protect('admin');
        $data = json_decode(file_get_contents("php://input"), true);

        try {
            $name = isset($data['name']) ? trim($data['name']) : '';
            $description = isset($data['description']) ? trim($data['description']) : null;

            if (empty($name)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Category name field specification missing."]);
                return;
            }

            $categoryModel = new Category($this->db);
            $insertedId = $categoryModel->create($name, $description);

            http_response_code(201);
            echo json_encode([
                "success" => true,
                "data" => [
                    "id" => intval($insertedId),
                    "name" => htmlspecialchars(strip_tags($name)),
                    "description" => $description ? htmlspecialchars(strip_tags($description)) : null
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }
}
?>