<?php
/**
 * İthalattan ürünü çıkar
 */
require_once '../config/database.php';
require_once '../config/settings.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek metodu'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    
    if (!$id) {
        throw new Exception('ID belirtilmedi!');
    }
    
    $db = getDB();
    
    // Önce ithalat_id'yi al
    $check_sql = "SELECT ithalat_id FROM ithalat_urunler WHERE id = :id";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->execute([':id' => $id]);
    $check = $check_stmt->fetch();
    
    if (!$check) {
        throw new Exception('Ürün bulunamadı!');
    }
    
    // Son ürün kontrolü
    $count_sql = "SELECT COUNT(*) as adet FROM ithalat_urunler WHERE ithalat_id = :ithalat_id";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute([':ithalat_id' => $check['ithalat_id']]);
    $count = $count_stmt->fetch();
    
    if ($count['adet'] <= 1) {
        throw new Exception('En az 1 ürün bulunmalıdır! Bu ürünü silemezsiniz.');
    }
    
    // Sil
    $sql = "DELETE FROM ithalat_urunler WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Ürün silindi!'
    ], JSON_UNESCAPED_UNICODE);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>