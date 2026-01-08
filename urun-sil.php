<?php
/**
 * =================================
 * DOSYA 5: api/urun-sil.php
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
    
    if (!$id) {
        throw new Exception('ID belirtilmedi!');
    }
    
    $db = getDB();
    
    // Önce kullanımda olup olmadığını kontrol et
    $check_sql = "SELECT COUNT(*) as kullanim FROM ithalat_urunler WHERE urun_katalog_id = :id";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->execute([':id' => $id]);
    $check = $check_stmt->fetch();
    
    if ($check['kullanim'] > 0) {
        throw new Exception('Bu ürün ' . $check['kullanim'] . ' ithalatta kullanılıyor, silinemez! Pasif yapabilirsiniz.');
    }
    
    // Sil
    $sql = "DELETE FROM urun_katalog WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Ürün başarıyla silindi!'
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>