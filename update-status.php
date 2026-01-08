<?php
/**
 * İthalat Durumu Güncelleme API
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/settings.php';

// POST kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Geçersiz istek metodu');
}

try {
    // JSON verisini al
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!isset($data['id']) || !isset($data['durum'])) {
        jsonResponse(false, 'Eksik parametreler');
    }
    
    $id = (int)$data['id'];
    $durum = cleanInput($data['durum']);
    
    // Geçerli durum kontrolü
    $gecerli_durumlar = ['siparis_verildi', 'yolda', 'transitte', 'limanda', 'teslim_edildi'];
    if (!in_array($durum, $gecerli_durumlar)) {
        jsonResponse(false, 'Geçersiz durum değeri');
    }
    
    $db = getDB();
    
    // Durumu güncelle
    $sql = "UPDATE ithalat SET ithalat_durumu = :durum WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':durum' => $durum,
        ':id' => $id
    ]);
    
    if ($stmt->rowCount() > 0) {
        jsonResponse(true, 'Durum başarıyla güncellendi');
    } else {
        jsonResponse(false, 'Kayıt bulunamadı veya güncelleme yapılmadı');
    }
    
} catch(PDOException $e) {
    jsonResponse(false, 'Veritabanı hatası: ' . $e->getMessage());
} catch(Exception $e) {
    jsonResponse(false, 'Hata: ' . $e->getMessage());
}
?>