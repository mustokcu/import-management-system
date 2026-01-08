<?php
/**
 * Ülke Silme API
 * Kullanılmayan ülkeleri siler
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
    
    if (!isset($data['ulke_kodu'])) {
        jsonResponse(false, 'Ülke kodu gerekli');
    }
    
    $ulke_kodu = cleanInput($data['ulke_kodu']);
    
    $db = getDB();
    
    // Ülkenin kullanılıp kullanılmadığını kontrol et
    $check_sql = "SELECT COUNT(*) FROM ithalat 
                  WHERE tedarikci_ulke = :ulke_kodu OR mensei_ulke = :ulke_kodu";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->execute([':ulke_kodu' => $ulke_kodu]);
    
    if ($check_stmt->fetchColumn() > 0) {
        jsonResponse(false, 'Bu ülke ithalat kayıtlarında kullanılıyor, silinemez!');
    }
    
    // "diger" ülkesi silinemez
    if ($ulke_kodu === 'diger') {
        jsonResponse(false, '"Diğer" ülkesi sistem tarafından korunuyor, silinemez!');
    }
    
    // Ülkeyi sil
    $sql = "DELETE FROM ulkeler WHERE ulke_kodu = :ulke_kodu";
    $stmt = $db->prepare($sql);
    $stmt->execute([':ulke_kodu' => $ulke_kodu]);
    
    if ($stmt->rowCount() > 0) {
        jsonResponse(true, 'Ülke başarıyla silindi');
    } else {
        jsonResponse(false, 'Silinecek ülke bulunamadı');
    }
    
} catch(PDOException $e) {
    jsonResponse(false, 'Veritabanı hatası: ' . $e->getMessage());
} catch(Exception $e) {
    jsonResponse(false, 'Hata: ' . $e->getMessage());
}
?>