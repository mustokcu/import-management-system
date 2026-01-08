<?php
/**
 * =================================
 * DOSYA 2: api/urun-detay.php
 * =================================
 */
?>
<?php
require_once '../config/database.php';
require_once '../config/settings.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID belirtilmedi']);
    exit;
}

try {
    $db = getDB();
    $id = intval($_GET['id']);
    
    $sql = "SELECT * FROM urun_katalog WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id]);
    $urun = $stmt->fetch();
    
    if (!$urun) {
        throw new Exception('Ürün bulunamadı!');
    }
    
    echo json_encode([
        'success' => true,
        'urun' => $urun
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
