
<?php
/**
 * GTIP Kodu Kaydet API
 * Yeni GTIP kodu ekler
 */

require_once '../config/database.php';
require_once '../config/settings.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    // POST verilerini al
    $gtip_kodu = cleanInput($_POST['gtip_kodu'] ?? '');
    $aciklama = cleanInput($_POST['aciklama'] ?? '');
    $kategori = cleanInput($_POST['kategori'] ?? '');
    $gumruk = floatval($_POST['varsayilan_gumruk_orani'] ?? 0);
    $otv = floatval($_POST['varsayilan_otv_orani'] ?? 0);
    $kdv = floatval($_POST['varsayilan_kdv_orani'] ?? 20);
    $notlar = cleanInput($_POST['notlar'] ?? '');
    $aktif = intval($_POST['aktif'] ?? 1);
    
    // Validasyon
    if (empty($gtip_kodu)) {
        jsonResponse(false, 'GTIP kodu boş olamaz!');
    }
    
    if (empty($aciklama)) {
        jsonResponse(false, 'Açıklama boş olamaz!');
    }
    
    // Aynı kod var mı kontrol et
    $sql_check = "SELECT COUNT(*) as sayi FROM gtip_kodlari WHERE gtip_kodu = :gtip_kodu";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->execute([':gtip_kodu' => $gtip_kodu]);
    $check = $stmt_check->fetch();
    
    if ($check['sayi'] > 0) {
        jsonResponse(false, 'Bu GTIP kodu zaten kayıtlı!');
    }
    
    // Kaydet
    $sql = "INSERT INTO gtip_kodlari (
        gtip_kodu, 
        aciklama, 
        kategori, 
        varsayilan_gumruk_orani, 
        varsayilan_otv_orani, 
        varsayilan_kdv_orani, 
        notlar, 
        aktif
    ) VALUES (
        :gtip_kodu, 
        :aciklama, 
        :kategori, 
        :gumruk, 
        :otv, 
        :kdv, 
        :notlar, 
        :aktif
    )";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':gtip_kodu' => $gtip_kodu,
        ':aciklama' => $aciklama,
        ':kategori' => $kategori,
        ':gumruk' => $gumruk,
        ':otv' => $otv,
        ':kdv' => $kdv,
        ':notlar' => $notlar,
        ':aktif' => $aktif
    ]);
    
    jsonResponse(true, 'GTIP kodu başarıyla eklendi!', [
        'id' => $db->lastInsertId(),
        'gtip_kodu' => $gtip_kodu
    ]);
    
} catch(Exception $e) {
    error_log("GTIP Kaydet Hatası: " . $e->getMessage());
    jsonResponse(false, 'Bir hata oluştu: ' . $e->getMessage());
}