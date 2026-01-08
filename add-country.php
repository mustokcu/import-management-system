<?php
/**
 * Ülke Ekleme API
 * Yeni ülke kaydı ekler
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/settings.php';

// POST kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Geçersiz istek metodu');
}

try {
    $db = getDB();
    
    // Form verilerini al
    $ulke_adi = cleanInput($_POST['ulke_adi'] ?? '');
    $ulke_adi_en = cleanInput($_POST['ulke_adi_en'] ?? null);
    $bolge = cleanInput($_POST['bolge'] ?? '');
    
    // Validasyon
    if (empty($ulke_adi)) {
        jsonResponse(false, 'Ülke adı gerekli');
    }
    
    if (empty($bolge)) {
        jsonResponse(false, 'Bölge seçimi gerekli');
    }
    
    // Ülke kodu oluştur (küçük harf, boşluk yerine alt çizgi, Türkçe karakter dönüşümü)
    $ulke_kodu = mb_strtolower($ulke_adi, 'UTF-8');
    $ulke_kodu = str_replace(
        ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', ' ', '.', ',', '-'],
        ['i', 'g', 'u', 's', 'o', 'c', '_', '', '', '_'],
        $ulke_kodu
    );
    
    // Özel karakterleri temizle
    $ulke_kodu = preg_replace('/[^a-z0-9_]/', '', $ulke_kodu);
    
    // Aynı kodun olup olmadığını kontrol et
    $check_sql = "SELECT COUNT(*) FROM ulkeler WHERE ulke_kodu = :ulke_kodu";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->execute([':ulke_kodu' => $ulke_kodu]);
    
    if ($check_stmt->fetchColumn() > 0) {
        // Kod zaten varsa sonuna sayı ekle
        $counter = 1;
        $original_code = $ulke_kodu;
        while(true) {
            $ulke_kodu = $original_code . '_' . $counter;
            $check_stmt->execute([':ulke_kodu' => $ulke_kodu]);
            if ($check_stmt->fetchColumn() == 0) break;
            $counter++;
        }
    }
    
    // Ülkeyi ekle
    $sql = "INSERT INTO ulkeler (ulke_kodu, ulke_adi, ulke_adi_en, bolge, aktif) 
            VALUES (:ulke_kodu, :ulke_adi, :ulke_adi_en, :bolge, 1)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':ulke_kodu' => $ulke_kodu,
        ':ulke_adi' => $ulke_adi,
        ':ulke_adi_en' => $ulke_adi_en,
        ':bolge' => $bolge
    ]);
    
    jsonResponse(true, 'Ülke başarıyla eklendi', [
        'ulke_kodu' => $ulke_kodu,
        'ulke_adi' => $ulke_adi,
        'id' => $db->lastInsertId()
    ]);
    
} catch(PDOException $e) {
    jsonResponse(false, 'Veritabanı hatası: ' . $e->getMessage());
} catch(Exception $e) {
    jsonResponse(false, 'Hata: ' . $e->getMessage());
}
?>