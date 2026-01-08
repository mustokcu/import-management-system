<?php
/**
 * GTIP Kodu Güncelle API
 * Mevcut GTIP kodunu düzenler
 */

require_once '../config/database.php';
require_once '../config/settings.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    // POST verilerini al
    $id = intval($_POST['id'] ?? 0);
    $gtip_kodu = cleanInput($_POST['gtip_kodu'] ?? '');
    $aciklama = cleanInput($_POST['aciklama'] ?? '');
    $kategori = cleanInput($_POST['kategori'] ?? '');
    $gumruk = floatval($_POST['varsayilan_gumruk_orani'] ?? 0);
    $otv = floatval($_POST['varsayilan_otv_orani'] ?? 0);
    $kdv = floatval($_POST['varsayilan_kdv_orani'] ?? 20);
    $notlar = cleanInput($_POST['notlar'] ?? '');
    $aktif = intval($_POST['aktif'] ?? 1);
    
    // Validasyon
    if ($id <= 0) {
        jsonResponse(false, 'Geçersiz ID!');
    }
    
    if (empty($gtip_kodu)) {
        jsonResponse(false, 'GTIP kodu boş olamaz!');
    }
    
    if (empty($aciklama)) {
        jsonResponse(false, 'Açıklama boş olamaz!');
    }
    
    // Aynı kod başka kayıtta var mı kontrol et
    $sql_check = "SELECT COUNT(*) as sayi FROM gtip_kodlari WHERE gtip_kodu = :gtip_kodu AND id != :id";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->execute([':gtip_kodu' => $gtip_kodu, ':id' => $id]);
    $check = $stmt_check->fetch();
    
    if ($check['sayi'] > 0) {
        jsonResponse(false, 'Bu GTIP kodu başka bir kayıtta zaten var!');
    }
    
    // Güncelle
    $sql = "UPDATE gtip_kodlari SET
        gtip_kodu = :gtip_kodu,
        aciklama = :aciklama,
        kategori = :kategori,
        varsayilan_gumruk_orani = :gumruk,
        varsayilan_otv_orani = :otv,
        varsayilan_kdv_orani = :kdv,
        notlar = :notlar,
        aktif = :aktif
    WHERE id = :id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':gtip_kodu' => $gtip_kodu,
        ':aciklama' => $aciklama,
        ':kategori' => $kategori,
        ':gumruk' => $gumruk,
        ':otv' => $otv,
        ':kdv' => $kdv,
        ':notlar' => $notlar,
        ':aktif' => $aktif,
        ':id' => $id
    ]);
    
    jsonResponse(true, 'GTIP kodu başarıyla güncellendi!');
    
} catch(Exception $e) {
    error_log("GTIP Güncelle Hatası: " . $e->getMessage());
    jsonResponse(false, 'Bir hata oluştu: ' . $e->getMessage());
}