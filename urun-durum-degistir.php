<?php
/**
 * =================================
 * DOSYA 4: api/urun-durum-degistir.php
 * =================================
 */
?>
<?php
require_once '../config/database.php';
require_once '../config/settings.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek metodu']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    $aktif = intval($data['aktif'] ?? 0);
    
    if (!$id) {
        throw new Exception('ID belirtilmedi!');
    }
    
    $db = getDB();
    
    $sql = "UPDATE urun_katalog SET aktif = :aktif WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id, ':aktif' => $aktif]);
    
    echo json_encode([
        'success' => true,
        'message' => $aktif ? 'Ürün aktif hale getirildi!' : 'Ürün pasif hale getirildi!'
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
