<?php
/**
 * Evrak Durumu Güncelleme API
 * Original evrak ve telex durumlarını günceller
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/settings.php';

// POST kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Geçersiz istek metodu');
}

try {
    // Form verilerini al
    $ithalat_id = (int)($_POST['ithalat_id'] ?? 0);
    $original_evrak_durumu = cleanInput($_POST['original_evrak_durumu'] ?? 'bekleniyor');
    $original_evrak_tarih = cleanInput($_POST['original_evrak_tarih'] ?? null);
    $telex_durumu = cleanInput($_POST['telex_durumu'] ?? 'bekleniyor');
    $telex_tarih = cleanInput($_POST['telex_tarih'] ?? null);
    $evrak_notlari = cleanInput($_POST['evrak_notlari'] ?? null);
    
    // Validasyon
    if ($ithalat_id <= 0) {
        jsonResponse(false, 'Geçersiz ithalat ID');
    }
    
    // Geçerli durum kontrolü
    $gecerli_durumlar = ['bekleniyor', 'alindi', 'teslim_edildi'];
    if (!in_array($original_evrak_durumu, $gecerli_durumlar)) {
        jsonResponse(false, 'Geçersiz original evrak durumu');
    }
    if (!in_array($telex_durumu, $gecerli_durumlar)) {
        jsonResponse(false, 'Geçersiz telex durumu');
    }
    
    // Tarih kontrolü (boş ise NULL olsun)
    if (empty($original_evrak_tarih)) $original_evrak_tarih = null;
    if (empty($telex_tarih)) $telex_tarih = null;
    if (empty($evrak_notlari)) $evrak_notlari = null;
    
    $db = getDB();
    
    // Sevkiyat kaydı var mı kontrol et
    $check_sql = "SELECT id FROM sevkiyat WHERE ithalat_id = :ithalat_id";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->execute([':ithalat_id' => $ithalat_id]);
    
    if (!$check_stmt->fetch()) {
        jsonResponse(false, 'Sevkiyat kaydı bulunamadı');
    }
    
    // Evrak durumunu güncelle
    $sql = "UPDATE sevkiyat SET 
        original_evrak_durumu = :original_evrak_durumu,
        original_evrak_tarih = :original_evrak_tarih,
        telex_durumu = :telex_durumu,
        telex_tarih = :telex_tarih,
        evrak_notlari = :evrak_notlari
    WHERE ithalat_id = :ithalat_id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':original_evrak_durumu' => $original_evrak_durumu,
        ':original_evrak_tarih' => $original_evrak_tarih,
        ':telex_durumu' => $telex_durumu,
        ':telex_tarih' => $telex_tarih,
        ':evrak_notlari' => $evrak_notlari,
        ':ithalat_id' => $ithalat_id
    ]);
    
    if ($stmt->rowCount() > 0) {
        jsonResponse(true, 'Evrak durumu başarıyla güncellendi', [
            'ithalat_id' => $ithalat_id,
            'original_evrak_durumu' => $original_evrak_durumu,
            'telex_durumu' => $telex_durumu
        ]);
    } else {
        jsonResponse(false, 'Güncelleme yapılmadı veya değişiklik yok');
    }
    
} catch(PDOException $e) {
    jsonResponse(false, 'Veritabanı hatası: ' . $e->getMessage());
} catch(Exception $e) {
    jsonResponse(false, 'Hata: ' . $e->getMessage());
}
?>