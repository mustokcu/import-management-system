<?php
/**
 * İthalat Ürünü Güncelle
 * Mevcut ürünün miktar, fiyat ve vergi bilgilerini günceller
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
    $urun_id = intval($data['id'] ?? 0);
    $miktar_kg = floatval($data['miktar_kg'] ?? 0);
    $birim_fiyat = floatval($data['birim_fiyat'] ?? 0);
    
    // Input kontrol
    if (!$urun_id) {
        throw new Exception('Ürün ID gerekli!');
    }
    
    if ($miktar_kg <= 0) {
        throw new Exception('Miktar 0\'dan büyük olmalı!');
    }
    
    if ($birim_fiyat <= 0) {
        throw new Exception('Birim fiyat 0\'dan büyük olmalı!');
    }
    
    // ============================================
    // 2. VERGİ BİLGİLERİ
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
    // 3. ÜRÜN VAR MI KONTROL
    // ============================================
    $check_urun = $db->prepare("
        SELECT 
            iu.*,
            uk.urun_cinsi,
            uk.urun_latince_isim,
            uk.kalibre
        FROM ithalat_urunler iu
        LEFT JOIN urun_katalog uk ON iu.urun_katalog_id = uk.id
        WHERE iu.id = :id
    ");
    $check_urun->execute([':id' => $urun_id]);
    $mevcut_urun = $check_urun->fetch();
    
    if (!$mevcut_urun) {
        throw new Exception('Ürün bulunamadı!');
    }
    
    // ============================================
    // 4. ÜRÜNÜ GÜNCELLE (VERGİ BİLGİLERİYLE)
    // ============================================
    $sql = "UPDATE ithalat_urunler SET
        miktar_kg = :miktar_kg,
        birim_fiyat = :birim_fiyat,
        toplam_tutar = :toplam_tutar,
        gtip_kodu = :gtip_kodu,
        gtip_aciklama = :gtip_aciklama,
        gumruk_vergisi_oran = :gumruk_oran,
        gumruk_vergisi_tutar = :gumruk_tutar,
        otv_oran = :otv_oran,
        otv_tutar = :otv_tutar,
        kdv_oran = :kdv_oran,
        kdv_tutar = :kdv_tutar,
        toplam_vergi = :toplam_vergi,
        vergi_dahil_tutar = :vergi_dahil_tutar
    WHERE id = :id";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':miktar_kg' => $miktar_kg,
        ':birim_fiyat' => $birim_fiyat,
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
        ':vergi_dahil_tutar' => $vergi_dahil_tutar,
        ':id' => $urun_id
    ]);
    
    if (!$result) {
        throw new Exception('Ürün güncellenemedi!');
    }
    
    // ============================================
    // 5. DEĞİŞİKLİK KONTROLÜ
    // ============================================
    $degisiklikler = [];
    
    if ($mevcut_urun['miktar_kg'] != $miktar_kg) {
        $degisiklikler[] = "Miktar: {$mevcut_urun['miktar_kg']} → {$miktar_kg} KG";
    }
    
    if ($mevcut_urun['birim_fiyat'] != $birim_fiyat) {
        $degisiklikler[] = "Fiyat: \${$mevcut_urun['birim_fiyat']} → \${$birim_fiyat}";
    }
    
    if ($mevcut_urun['gtip_kodu'] != $gtip_kodu) {
        $degisiklikler[] = "GTIP: {$mevcut_urun['gtip_kodu']} → {$gtip_kodu}";
    }
    
    if ($mevcut_urun['gumruk_vergisi_oran'] != $gumruk_oran) {
        $degisiklikler[] = "Gümrük: {$mevcut_urun['gumruk_vergisi_oran']}% → {$gumruk_oran}%";
    }
    
    if ($mevcut_urun['otv_oran'] != $otv_oran) {
        $degisiklikler[] = "ÖTV: {$mevcut_urun['otv_oran']}% → {$otv_oran}%";
    }
    
    if ($mevcut_urun['kdv_oran'] != $kdv_oran) {
        $degisiklikler[] = "KDV: {$mevcut_urun['kdv_oran']}% → {$kdv_oran}%";
    }
    
    // ============================================
    // 6. LOG KAYDI
    // ============================================
    $degisiklik_log = count($degisiklikler) > 0 ? implode(', ', $degisiklikler) : 'Değişiklik yok';
    error_log("✅ Ürün Güncellendi: ID=$urun_id, Ürün={$mevcut_urun['urun_cinsi']}, GTIP=$gtip_kodu, Vergi=₺" . number_format($toplam_vergi, 2) . " | Değişiklikler: $degisiklik_log");
    
    // ============================================
    // 7. BAŞARILI RESPONSE
    // ============================================
    echo json_encode([
        'success' => true,
        'message' => 'Ürün başarıyla güncellendi!',
        'data' => [
            'id' => $urun_id,
            'urun_cinsi' => $mevcut_urun['urun_cinsi'],
            'urun_latince_isim' => $mevcut_urun['urun_latince_isim'],
            'kalibre' => $mevcut_urun['kalibre'],
            'miktar_kg' => $miktar_kg,
            'birim_fiyat' => $birim_fiyat,
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
            'vergi_dahil_tutar' => $vergi_dahil_tutar,
            'degisiklikler' => $degisiklikler
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch(Exception $e) {
    // Hata log
    error_log("❌ Ürün Güncelleme Hatası: " . $e->getMessage() . " | Dosya: " . $e->getFile() . " | Satır: " . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}