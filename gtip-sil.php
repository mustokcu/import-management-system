<?php
/**
 * GTIP Kodu Sil API
 * GTIP kodunu siler
 */

require_once '../config/database.php';
require_once '../config/settings.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    // JSON input al
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    
    if ($id <= 0) {
        jsonResponse(false, 'Geçersiz ID!');
    }
    
    // Bu GTIP kullanılıyor mu kontrol et
    $sql_check = "SELECT COUNT(*) as sayi FROM ithalat_urunler WHERE gtip_kodu = (SELECT gtip_kodu FROM gtip_kodlari WHERE id = :id)";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->execute([':id' => $id]);
    $check = $stmt_check->fetch();
    
    if ($check['sayi'] > 0) {
        jsonResponse(false, 'Bu GTIP kodu ' . $check['sayi'] . ' üründe kullanılıyor! Önce ürünleri güncelleyin.');
    }
    
    // Sil
    $sql = "DELETE FROM gtip_kodlari WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    jsonResponse(true, 'GTIP kodu silindi!');
    
} catch(Exception $e) {
    error_log("GTIP Sil Hatası: " . $e->getMessage());
    jsonResponse(false, 'Bir hata oluştu: ' . $e->getMessage());
}