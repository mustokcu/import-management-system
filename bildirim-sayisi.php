<?php
/**
 * Bildirim Sayısı API
 * Okunmamış bildirim sayısını döndürür
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    // Okunmamış bildirim sayısını çek
    $sql = "SELECT COUNT(*) as sayi FROM bildirimler WHERE okundu = 0";
    $stmt = $db->query($sql);
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true, 
        'count' => (int)$result['sayi']
    ], JSON_UNESCAPED_UNICODE);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false, 
        'count' => 0,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>