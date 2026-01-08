<?php
/**
 * İthalata Yeni Ürün Ekle
 * ✅ FIX: Para birimi form'dan alınıyor
 * ✅ FIX: Detaylı validasyon eklendi
 * ✅ FIX: Duplicate kontrolü geliştirildi
 * ✅ VERGİ SİSTEMİ: GTIP, Gümrük, ÖTV, KDV entegrasyonu eklendi
 */
require_once '../config/database.php';
require_once '../config/settings.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'Geçersiz istek metodu'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // JSON veya POST data
    $data = null;
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
    } else {
        $data = $_POST;
    }
    
    if (!$data) {
        throw new Exception('Veri alınamadı!');
    }
    
    // ============================================
    // 1. TEMEL BİLGİLER - Validasyon
    // ============================================
    $ithalat_id = intval($data['ithalat_id'] ?? 0);
    $urun_katalog_id = intval($data['urun_katalog_id'] ?? 0);
    $miktar_kg = floatval($data['miktar_kg'] ?? 0);
    $birim_fiyat = floatval($data['birim_fiyat'] ?? 0);
    $para_birimi = cleanInput($data['para_birimi'] ?? 'USD');
    
    // Input kontrol
    if (!$ithalat_id) {
        throw new Exception('İthalat ID gerekli!');
    }
    
    if (!$urun_katalog_id) {
        throw new Exception('Ürün seçilmedi!');
    }
    
    if ($miktar_kg <= 0) {
        throw new Exception('Miktar 0\'dan büyük olmalı!');
    }
    
    if ($birim_fiyat <= 0) {
        throw new Exception('Birim fiyat 0\'dan büyük olmalı!');
    }
    
    // ============================================
    // 2. VERGİ BİLGİLERİ - Validasyon
    // ============================================
    $gtip_kodu = cleanInput($data['gtip_kodu'] ?? '');
    $gtip_aciklama = cleanInput($data['gtip_aciklama'] ?? '');
    $gumruk_oran = floatval($data['gumruk_oran'] ?? 0);
    $gumruk_tutar = floatval($data['gumruk_tutar'] ?? 0);
    $otv_oran = floatval($data['otv_oran'] ?? 0);
    $otv_tutar = floatval($data['otv_tutar'] ?? 0);
    $kdv_oran = floatval($data['kdv_oran'] ?? 20);
    $kdv_tutar = floatval($data['kdv_tutar'] ?? 0);
    $toplam_vergi = floatval($data['toplam_vergi'] ?? 0);
    $vergi_dahil_tutar = floatval($data['vergi_dahil_tutar'] ?? 0);
    
    // Toplam tutar hesapla
    $toplam_tutar = $miktar_kg * $birim_fiyat;
    
    $db = getDB();
    
    // ============================================
    // 3. VERİTABANI KONTROLLER
    // ============================================
    
    // 1️⃣ İthalat var mı kontrol
    $check_ithalat = $db->prepare("SELECT id FROM ithalat WHERE id = :id");
    $check_ithalat->execute([':id' => $ithalat_id]);
    if (!$check_ithalat->fetch()) {
        throw new Exception('İthalat kaydı bulunamadı!');
    }
    
    // 2️⃣ Ürün katalogda var mı kontrol
    $check_urun = $db->prepare("SELECT id, urun_cinsi, urun_latince_isim, kalibre FROM urun_katalog WHERE id = :id");
    $check_urun->execute([':id' => $urun_katalog_id]);
    $urun_bilgi = $check_urun->fetch();
    
    if (!$urun_bilgi) {
        throw new Exception('Ürün katalogda bulunamadı!');
    }
    
    // 3️⃣ Aynı ürün zaten ekli mi kontrol
    $check_duplicate = $db->prepare("
        SELECT id FROM ithalat_urunler 
        WHERE ithalat_id = :ithalat_id 
        AND urun_katalog_id = :urun_katalog_id
    ");
    $check_duplicate->execute([
        ':ithalat_id' => $ithalat_id,
        ':urun_katalog_id' => $urun_katalog_id
    ]);
    
    if ($check_duplicate->fetch()) {
        throw new Exception('Bu ürün zaten ekli! Miktarını güncelleyebilirsiniz.');
    }
    
    // ============================================
    // 4. ÜRÜNÜ EKLE (VERGİ BİLGİLERİYLE)
    // ============================================
    $sql = "INSERT INTO ithalat_urunler (
        ithalat_id,
        urun_katalog_id,
        miktar_kg,
        birim_fiyat,
        para_birimi,
        toplam_tutar,
        gtip_kodu,
        gtip_aciklama,
        gumruk_vergisi_oran,
        gumruk_vergisi_tutar,
        otv_oran,
        otv_tutar,
        kdv_oran,
        kdv_tutar,
        toplam_vergi,
        vergi_dahil_tutar
    ) VALUES (
        :ithalat_id,
        :urun_katalog_id,
        :miktar_kg,
        :birim_fiyat,
        :para_birimi,
        :toplam_tutar,
        :gtip_kodu,
        :gtip_aciklama,
        :gumruk_oran,
        :gumruk_tutar,
        :otv_oran,
        :otv_tutar,
        :kdv_oran,
        :kdv_tutar,
        :toplam_vergi,
        :vergi_dahil_tutar
    )";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':ithalat_id' => $ithalat_id,
        ':urun_katalog_id' => $urun_katalog_id,
        ':miktar_kg' => $miktar_kg,
        ':birim_fiyat' => $birim_fiyat,
        ':para_birimi' => $para_birimi,
        ':toplam_tutar' => $toplam_tutar,
        ':gtip_kodu' => $gtip_kodu,
        ':gtip_aciklama' => $gtip_aciklama,
        ':gumruk_oran' => $gumruk_oran,
        ':gumruk_tutar' => $gumruk_tutar,
        ':otv_oran' => $otv_oran,
        ':otv_tutar' => $otv_tutar,
        ':kdv_oran' => $kdv_oran,
        ':kdv_tutar' => $kdv_tutar,
        ':toplam_vergi' => $toplam_vergi,
        ':vergi_dahil_tutar' => $vergi_dahil_tutar
    ]);
    
    if (!$result) {
        throw new Exception('Ürün eklenemedi!');
    }
    
    $yeni_id = $db->lastInsertId();
    
    // ============================================
    // 5. LOG KAYDI
    // ============================================
    error_log("✅ Yeni Ürün Eklendi: ID=$yeni_id, İthalat=$ithalat_id, Ürün={$urun_bilgi['urun_cinsi']}, GTIP=$gtip_kodu, Vergi=₺" . number_format($toplam_vergi, 2));
    
    // ============================================
    // 6. BAŞARILI RESPONSE
    // ============================================
    echo json_encode([
        'success' => true,
        'message' => 'Ürün başarıyla eklendi!',
        'data' => [
            'id' => $yeni_id,
            'urun_cinsi' => $urun_bilgi['urun_cinsi'],
            'urun_latince_isim' => $urun_bilgi['urun_latince_isim'],
            'kalibre' => $urun_bilgi['kalibre'],
            'miktar_kg' => $miktar_kg,
            'birim_fiyat' => $birim_fiyat,
            'para_birimi' => $para_birimi,
            'toplam_tutar' => $toplam_tutar,
            'gtip_kodu' => $gtip_kodu,
            'gtip_aciklama' => $gtip_aciklama,
            'gumruk_oran' => $gumruk_oran,
            'gumruk_tutar' => $gumruk_tutar,
            'otv_oran' => $otv_oran,
            'otv_tutar' => $otv_tutar,
            'kdv_oran' => $kdv_oran,
            'kdv_tutar' => $kdv_tutar,
            'toplam_vergi' => $toplam_vergi,
            'vergi_dahil_tutar' => $vergi_dahil_tutar
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch(Exception $e) {
    // Hata log
    error_log("❌ Ürün Ekleme Hatası: " . $e->getMessage() . " | Dosya: " . $e->getFile() . " | Satır: " . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}