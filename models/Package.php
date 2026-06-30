<?php
class Package {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }
    public function findAll() {
        $query = "SELECT p.*, 
                  GROUP_CONCAT(
                      IF(pi.id IS NOT NULL, CONCAT(pi.id, '::', pi.url), NULL) 
                      SEPARATOR '||'
                  ) as gallery_string
                  FROM packages p
                  LEFT JOIN package_images pi ON p.id = pi.packageId
                  GROUP BY p.id ORDER BY p.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($packages as &$pkg) {
            $this->formatPackageFields($pkg);
        }
        return $packages;
    }

    // 2. Fetch single entry specs
    public function findByPk($id) {
        $query = "SELECT p.*, 
                  GROUP_CONCAT(
                      IF(pi.id IS NOT NULL, CONCAT(pi.id, '::', pi.url, '::', IFNULL(pi.cloudinary_id, 'NULL')), NULL) 
                      SEPARATOR '||'
                  ) as gallery_string
                  FROM packages p
                  LEFT JOIN package_images pi ON p.id = pi.packageId
                  WHERE p.id = :id GROUP BY p.id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $id]);
        $pkg = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pkg) {
            $this->formatPackageFields($pkg, true);
        }
        return $pkg;
    }

    // Helper method to format data types and parse image strings seamlessly
    private function formatPackageFields(&$pkg, $includeCloudinaryId = false) {
        $pkg['price'] = floatval($pkg['price']);
        $pkg['includesDrone'] = (int)$pkg['includesDrone'] === 1;
        $pkg['includesCandid'] = (int)$pkg['includesCandid'] === 1;
        $pkg['includesCinematicVideo'] = (int)$pkg['includesCinematicVideo'] === 1;
        $pkg['includesTraditionalVideo'] = (int)$pkg['includesTraditionalVideo'] === 1;
        $pkg['includesPreWedding'] = (int)$pkg['includesPreWedding'] === 1;
        $pkg['includesLiveStreaming'] = (int)$pkg['includesLiveStreaming'] === 1;
        $pkg['includesCrane'] = (int)$pkg['includesCrane'] === 1;
        $pkg['includesLedWall'] = (int)$pkg['includesLedWall'] === 1;
        
        // Decode JSON elements securely
        $pkg['albumDetails'] = json_decode($pkg['albumDetails']);
        $pkg['deliverables'] = json_decode($pkg['deliverables']);
        
        // Parse the gallery string built by GROUP_CONCAT
        $pkg['images'] = [];
        if (!empty($pkg['gallery_string'])) {
            $items = explode('||', $pkg['gallery_string']);
            foreach ($items as $item) {
                $parts = explode('::', $item);
                if (count($parts) >= 2) {
                    $imgArray = [
                        'id' => intval($parts[0]),
                        'url' => $parts[1]
                    ];
                    // If single view, pack cloudinary_id as well for clean dashboard unlinks
                    if ($includeCloudinaryId && isset($parts[2]) && $parts[2] !== 'NULL') {
                        $imgArray['cloudinary_id'] = $parts[2];
                    }
                    $images[] = $imgArray;
                }
            }
            $pkg['images'] = $images;
        }
        unset($pkg['gallery_string']);
    }
}
?>