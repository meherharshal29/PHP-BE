<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/UserReview.php';
require_once __DIR__ . '/AuthController.php';

class ReviewController {
    private $db;

    public function __dirname_init() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
public function getReviewsByItem() {
    try {
        // Reads directly from the angular parameters ?referenceId=X&type=Y
        $referenceId = isset($_GET['referenceId']) ? $_GET['referenceId'] : null;
        $type = isset($_GET['type']) ? $_GET['type'] : null;

        $reviewModel = new UserReview($this->db);
        $rawReviews = $reviewModel->findByItem($referenceId, $type);

        $formatted = [];
        foreach ($rawReviews as $row) {
            $formatted[] = [
                "id" => intval($row['id']),
                "rating" => intval($row['rating']),
                "comment" => $row['comment'],
                "userId" => intval($row['userId']),
                "referenceId" => intval($row['referenceId']),
                "type" => $row['type'],
                "created_at" => $row['created_at'],
                "updated_at" => $row['updated_at'],
                "author" => $row['userId'] ? [
                    "name" => $row['authorName'],
                    "role" => $row['authorRole'],
                    "avatar" => "https://ui-avatars.com/api/?name=" . urlencode($row['authorName']) // Added fallback for UI avatar to prevent mapping exceptions
                ] : null
            ];
        }

        echo json_encode(["success" => true, "data" => $formatted]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}
    // 2. GET MY PERSONAL REVIEWS
    public function getMyReviews() {
        $auth = new AuthController(); $auth->__dirname_init(); $user = $auth->protect();

        try {
            $reviewModel = new UserReview($this->db);
            $reviews = $reviewModel->findByUserId($user->id); // Now perfectly available!

            foreach ($reviews as &$r) {
                $r['id'] = intval($r['id']);
                $r['rating'] = intval($r['rating']);
                $r['userId'] = intval($r['userId']);
                $r['referenceId'] = intval($r['referenceId']);
            }

            echo json_encode(["success" => true, "data" => $reviews]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    // 3. CREATE / ADD REVIEW
    public function createReview() {
        $auth = new AuthController(); $auth->__dirname_init(); $user = $auth->protect();
        $data = json_decode(file_get_contents("php://input"), true);

        try {
            $rating = isset($data['rating']) ? intval($data['rating']) : 0;
            $comment = isset($data['comment']) ? trim($data['comment']) : '';
            $referenceId = isset($data['referenceId']) ? intval($data['referenceId']) : 0;
            $type = isset($data['type']) ? $data['type'] : 'general';

            if ($rating < 1 || $rating > 5 || empty($comment)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Validation failed. Complete rating (1-5) and comment required."]);
                return;
            }

            $reviewModel = new UserReview($this->db);
            $insertedId = $reviewModel->create($rating, $comment, $referenceId, $type, $user->id);

            http_response_code(201);
            echo json_encode([
                "success" => true,
                "data" => [
                    "id" => intval($insertedId),
                    "rating" => $rating,
                    "comment" => htmlspecialchars(strip_tags($comment)),
                    "referenceId" => $referenceId,
                    "type" => $type,
                    "userId" => $user->id
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    // 4. UPDATE REVIEW
    public function updateReview($id) {
        $auth = new AuthController(); $auth->__dirname_init(); $user = $auth->protect();
        $data = json_decode(file_get_contents("php://input"), true);

        try {
            $reviewModel = new UserReview($this->db);
            $rating = isset($data['rating']) ? intval($data['rating']) : null;
            $comment = isset($data['comment']) ? trim($data['comment']) : null;

            $executed = $reviewModel->updateItem($id, $user->id, $rating, $comment); // Now perfectly available!
            if (!$executed) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Review record allocation reference missing or unauthorized."]);
                return;
            }

            echo json_encode(["success" => true, "message" => "Updated"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    // 5. DELETE REVIEW
    public function deleteReview($id) {
        $auth = new AuthController(); $auth->__dirname_init(); $user = $auth->protect();

        try {
            $reviewModel = new UserReview($this->db);
            $deleted = $reviewModel->destroy($id, $user->id); // Now perfectly available!

            if (!$deleted) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Target data sequence missing or unauthorized."]);
                return;
            }

            echo json_encode(["success" => true, "message" => "Deleted"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }
}
?>