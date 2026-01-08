<?php
/**
 * İthalat Silme API
 * CASCADE ilişkisi sayesinde tüm ilgili kayıtlar otomatik silinir
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
    
    if (!isset($data['id'])) {
        jsonResponse(false, 'İthalat ID gerekli');
    }
    
    $id = (int)$data['id'];
    
    $db = getDB();
    
    // İthalatı sil (CASCADE sayesinde ilgili tüm kayıtlar silinir)
    $sql = "DELETE FROM ithalat WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    if ($stmt->rowCount() > 0) {
        jsonResponse(true, 'İthalat kaydı ve ilgili tüm veriler başarıyla silindi');
    } else {
        jsonResponse(false, 'Silinecek kayıt bulunamadı');
    }
    
} catch(PDOException $e) {
    jsonResponse(false, 'Veritabanı hatası: ' . $e->getMessage());
} catch(Exception $e) {
    jsonResponse(false, 'Hata: ' . $e->getMessage());
}
?>