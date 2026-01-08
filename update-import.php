<?php
/**
 * İthalat Güncelleme API - DEBUG MODLU SÜRÜM
 * ✅ Her adımda detaylı log
 * ✅ POST verilerini göster
 * ✅ SQL sorguları ve sonuçları logla
 * ✅ Hata yakalama mekanizması
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';

header('Content-Type: application/json; charset=utf-8');

// ============================================
// DEBUG: POST Verilerini Logla
// ============================================
error_log("════════════════════════════════════════════");
error_log("🔄 İTHALAT GÜNCELLEME BAŞLADI");
error_log("📅 Zaman: " . date('Y-m-d H:i:s'));
error_log("📋 POST Verileri:");
foreach ($_POST as $key => $value) {
    if (is_array($value)) {
        error_log("  $key: " . json_encode($value));
    } else {
        error_log("  $key: " . ($value ?: 'BOŞ'));
    }
}
error_log("════════════════════════════════════════════");

// POST kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("❌ HATA: POST değil, " . $_SERVER['REQUEST_METHOD']);
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz istek metodu'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ID kontrolü
if (!isset($_POST['ithalat_id'])) {
    error_log("❌ HATA: ithalat_id yok!");
    echo json_encode([
        'success' => false,
        'message' => 'İthalat ID gerekli',
        'post_keys' => array_keys($_POST)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$ithalat_id = (int)$_POST['ithalat_id'];
error_log("✅ İthalat ID: $ithalat_id");

try {
    $db = getDB();
    $db->beginTransaction();
    error_log("✅ Transaction başladı");
    
    // ========================================
    // 1. İTHALAT ANA KAYDI
    // ========================================
    error_log("📝 ADIM 1: İthalat ana kayıt güncelleniyor...");
    
    $sql_ithalat = "UPDATE ithalat SET 
        tedarikci_firma = :tedarikci_firma,
        tedarikci_ulke = :tedarikci_ulke,
        tedarikci_siparis_no = :tedarikci_siparis_no,
        mensei_ulke = :mensei_ulke,
        transit_detay = :transit_detay,
        siparis_tarihi = :siparis_tarihi,
        ilk_siparis_tarihi = :ilk_siparis_tarihi,
        tahmini_teslim_ayi = :tahmini_teslim_ayi,
        ithalat_durumu = :ithalat_durumu,
        sigorta_durumu = :sigorta_durumu,
        gtip_kodu = :gtip_kodu,
        gtip_tipi = :gtip_tipi,
        ithal_kontrol_belgesi_16 = :ithal_kontrol_belgesi_16,
        tarim_bakanlik_onay_tarihi = :tarim_bakanlik_onay_tarihi,
        kontrol_belgesi_suresi = :kontrol_belgesi_suresi,
        tek_fabrika = :tek_fabrika,
        depozito_anlasmasi = :depozito_anlasmasi,
        komisyon_anlasmasi = :komisyon_anlasmasi,
        notlar = :notlar,
        guncelleme_tarihi = NOW()
    WHERE id = :ithalat_id";
    
    $stmt_ithalat = $db->prepare($sql_ithalat);
    $result = $stmt_ithalat->execute([
        ':tedarikci_firma' => cleanInput($_POST['tedarikci_firma'] ?? ''),
        ':tedarikci_ulke' => cleanInput($_POST['tedarikci_ulke'] ?? null),
        ':tedarikci_siparis_no' => cleanInput($_POST['tedarikci_siparis_no'] ?? null),  // ✅ YENİ SATIR
        ':mensei_ulke' => cleanInput($_POST['mensei_ulke'] ?? null),
        ':transit_detay' => cleanInput($_POST['transit_detay'] ?? null),
        ':siparis_tarihi' => !empty($_POST['siparis_tarihi']) ? $_POST['siparis_tarihi'] : date('Y-m-d'),
        ':ilk_siparis_tarihi' => !empty($_POST['ilk_siparis_tarihi']) ? $_POST['ilk_siparis_tarihi'] : null,
        ':tahmini_teslim_ayi' => !empty($_POST['tahmini_teslim_ayi']) ? $_POST['tahmini_teslim_ayi'] : null,
        ':ithalat_durumu' => cleanInput($_POST['ithalat_durumu'] ?? 'siparis_verildi'),
        ':sigorta_durumu' => cleanInput($_POST['sigorta_durumu'] ?? null),
        ':gtip_kodu' => cleanInput($_POST['gtip_kodu'] ?? null),
        ':gtip_tipi' => cleanInput($_POST['gtip_tipi'] ?? null),
        ':ithal_kontrol_belgesi_16' => cleanInput($_POST['ithal_kontrol_belgesi_16'] ?? null),
        ':tarim_bakanlik_onay_tarihi' => !empty($_POST['tarim_bakanlik_onay_tarihi']) ? $_POST['tarim_bakanlik_onay_tarihi'] : null,
        ':kontrol_belgesi_suresi' => cleanInput($_POST['kontrol_belgesi_suresi'] ?? null),
        ':tek_fabrika' => cleanInput($_POST['tek_fabrika'] ?? null),
        ':depozito_anlasmasi' => cleanInput($_POST['depozito_anlasmasi'] ?? null),
        ':komisyon_anlasmasi' => cleanInput($_POST['komisyon_anlasmasi'] ?? null),
        ':notlar' => cleanInput($_POST['notlar'] ?? null),
        ':ithalat_id' => $ithalat_id
    ]);
    
    $rowCount = $stmt_ithalat->rowCount();
    error_log("✅ ADIM 1 TAMAMLANDI - Etkilenen satır: $rowCount");
    
    // ========================================
    // 2. ÜRÜN TOPLAM HESAPLAMA
    // ========================================
    error_log("📝 ADIM 2: Ürün toplamları hesaplanıyor...");
    
    $sql_toplam = "SELECT 
        SUM(miktar_kg) as toplam_kg,
        SUM(toplam_tutar) as toplam_tutar
    FROM ithalat_urunler 
    WHERE ithalat_id = :ithalat_id";
    
    $stmt_toplam = $db->prepare($sql_toplam);
    $stmt_toplam->execute([':ithalat_id' => $ithalat_id]);
    $toplam = $stmt_toplam->fetch();
    
    $toplam_kg = floatval($toplam['toplam_kg'] ?? 0);
    $toplam_urun_tutari = floatval($toplam['toplam_tutar'] ?? 0);
    
    error_log("✅ ADIM 2 TAMAMLANDI - Toplam KG: $toplam_kg");
    
    // ========================================
    // 3. ÜRÜN DETAYLARI (Eski tablo)
    // ========================================
    error_log("📝 ADIM 3: Ürün detayları güncelleniyor...");
    
    $stmt_urun_detay = $db->prepare("UPDATE urun_detaylari SET 
        toplam_siparis_kg = ?
    WHERE ithalat_id = ?");
    $stmt_urun_detay->execute([$toplam_kg, $ithalat_id]);
    
    error_log("✅ ADIM 3 TAMAMLANDI - Etkilenen satır: " . $stmt_urun_detay->rowCount());
    
    // ========================================
    // 4. ÖDEME BİLGİLERİ
    // ========================================
    error_log("📝 ADIM 4: Ödeme bilgileri güncelleniyor...");
    
    $ilk_alis_fiyati = floatval($_POST['ilk_alis_fiyati'] ?? 0);
    $tranship = floatval($_POST['tranship_ek_maliyet'] ?? 0);
    $komisyon = floatval($_POST['komisyon_tutari'] ?? 0);
    
    $koli_marka = null;
    if (isset($_POST['koli_tasarim_marka']) && is_array($_POST['koli_tasarim_marka'])) {
        $koli_marka = implode(',', array_map('cleanInput', $_POST['koli_tasarim_marka']));
    }
    
    error_log("  - İlk Alış: $ilk_alis_fiyati");
    error_log("  - Kur: " . ($_POST['usd_kur'] ?? 'YOK'));
    error_log("  - Komisyon: $komisyon");
    
    $sql_odeme = "UPDATE odemeler SET 
        ilk_alis_fiyati = :ilk_alis_fiyati,
        para_birimi = :para_birimi,
        usd_kur = :usd_kur,
        kur_tarihi = :kur_tarihi,
        kur_notu = :kur_notu,
        tranship_ek_maliyet = :tranship_ek_maliyet,
        komisyon_firma = :komisyon_firma,
        komisyon_tutari = :komisyon_tutari,
        on_odeme_orani = :on_odeme_orani,
        avans_1_tutari = :avans_1_tutari,
        avans_1_tarihi = :avans_1_tarihi,
        avans_1_kur = :avans_1_kur,
        avans_2_tutari = :avans_2_tutari,
        avans_2_tarihi = :avans_2_tarihi,
        avans_2_kur = :avans_2_kur,
        final_odeme_tutari = :final_odeme_tutari,
        final_odeme_tarihi = :final_odeme_tarihi,
        final_odeme_kur = :final_odeme_kur,
        koli_tasarim_marka = :koli_tasarim_marka
    WHERE ithalat_id = :ithalat_id";
    
    $stmt_odeme = $db->prepare($sql_odeme);
    $stmt_odeme->execute([
        ':ilk_alis_fiyati' => $ilk_alis_fiyati > 0 ? $ilk_alis_fiyati : null,
        ':para_birimi' => cleanInput($_POST['para_birimi'] ?? 'USD'),
        ':usd_kur' => !empty($_POST['usd_kur']) ? floatval($_POST['usd_kur']) : null,
        ':kur_tarihi' => !empty($_POST['kur_tarihi']) ? $_POST['kur_tarihi'] : null,
        ':kur_notu' => cleanInput($_POST['kur_notu'] ?? null),
        ':tranship_ek_maliyet' => $tranship > 0 ? $tranship : null,
        ':komisyon_firma' => cleanInput($_POST['komisyon_firma'] ?? null),
        ':komisyon_tutari' => $komisyon > 0 ? $komisyon : null,
        ':on_odeme_orani' => cleanInput($_POST['on_odeme_orani'] ?? null),
        ':avans_1_tutari' => !empty($_POST['avans_1_tutari']) ? floatval($_POST['avans_1_tutari']) : null,
        ':avans_1_tarihi' => !empty($_POST['avans_1_tarihi']) ? $_POST['avans_1_tarihi'] : null,
        ':avans_1_kur' => !empty($_POST['avans_1_kur']) ? floatval($_POST['avans_1_kur']) : null,
        ':avans_2_tutari' => !empty($_POST['avans_2_tutari']) ? floatval($_POST['avans_2_tutari']) : null,
        ':avans_2_tarihi' => !empty($_POST['avans_2_tarihi']) ? $_POST['avans_2_tarihi'] : null,
        ':avans_2_kur' => !empty($_POST['avans_2_kur']) ? floatval($_POST['avans_2_kur']) : null,
        ':final_odeme_tutari' => !empty($_POST['final_odeme_tutari']) ? floatval($_POST['final_odeme_tutari']) : null,
        ':final_odeme_tarihi' => !empty($_POST['final_odeme_tarihi']) ? $_POST['final_odeme_tarihi'] : null,
        ':final_odeme_kur' => !empty($_POST['final_odeme_kur']) ? floatval($_POST['final_odeme_kur']) : null,
        ':koli_tasarim_marka' => $koli_marka,
        ':ithalat_id' => $ithalat_id
    ]);
    
    error_log("✅ ADIM 4 TAMAMLANDI - Etkilenen satır: " . $stmt_odeme->rowCount());
    
    // ========================================
    // 5. GİDERLER - ÖNEMLİ BÖLÜM!
    // ========================================
    error_log("📝 ADIM 5: Giderler güncelleniyor...");
    
    $gumruk = floatval($_POST['gumruk_ucreti'] ?? 0);
    $tarim = floatval($_POST['tarim_hizmet_ucreti'] ?? 0);
    $nakliye = floatval($_POST['nakliye_bedeli'] ?? 0);
    $sigorta = floatval($_POST['sigorta_bedeli'] ?? 0);
    $ardiye = floatval($_POST['ardiye_ucreti'] ?? 0);
    $demoraj = floatval($_POST['demoraj_ucreti'] ?? 0);
    
    $toplam_gider = $gumruk + $tarim + $nakliye + $sigorta + $ardiye + $demoraj;
    
    error_log("  💰 Gümrük: ₺" . number_format($gumruk, 2));
    error_log("  💰 Tarım: ₺" . number_format($tarim, 2));
    error_log("  💰 Nakliye: ₺" . number_format($nakliye, 2));
    error_log("  💰 Sigorta: ₺" . number_format($sigorta, 2));
    error_log("  💰 Ardiye: ₺" . number_format($ardiye, 2));
    error_log("  💰 Demoraj: ₺" . number_format($demoraj, 2));
    error_log("  📊 TOPLAM: ₺" . number_format($toplam_gider, 2));
    
    $sql_gider = "UPDATE giderler SET 
        gumruk_ucreti = :gumruk_ucreti,
        tarim_hizmet_ucreti = :tarim_hizmet_ucreti,
        nakliye_bedeli = :nakliye_bedeli,
        sigorta_bedeli = :sigorta_bedeli,
        ardiye_ucreti = :ardiye_ucreti,
        demoraj_ucreti = :demoraj_ucreti,
        toplam_gider = :toplam_gider
    WHERE ithalat_id = :ithalat_id";
    
    $stmt_gider = $db->prepare($sql_gider);
    $gider_result = $stmt_gider->execute([
        ':gumruk_ucreti' => $gumruk > 0 ? $gumruk : null,
        ':tarim_hizmet_ucreti' => $tarim > 0 ? $tarim : null,
        ':nakliye_bedeli' => $nakliye > 0 ? $nakliye : null,
        ':sigorta_bedeli' => $sigorta > 0 ? $sigorta : null,
        ':ardiye_ucreti' => $ardiye > 0 ? $ardiye : null,
        ':demoraj_ucreti' => $demoraj > 0 ? $demoraj : null,
        ':toplam_gider' => $toplam_gider > 0 ? $toplam_gider : null,
        ':ithalat_id' => $ithalat_id
    ]);
    
    $gider_row_count = $stmt_gider->rowCount();
    error_log("✅ ADIM 5 TAMAMLANDI - Etkilenen satır: $gider_row_count");
    
    if ($gider_row_count === 0) {
        error_log("⚠️ UYARI: Giderler tablosunda satır güncellenmedi!");
    }
    
    // ========================================
    // 6. SEVKİYAT - ÖNEMLİ BÖLÜM!
    // ========================================
    error_log("📝 ADIM 6: Sevkiyat bilgileri güncelleniyor...");
    
    error_log("  🚢 Yükleme Limanı: " . ($_POST['yukleme_limani'] ?? 'BOŞ'));
    error_log("  ⚓ Boşaltma Limanı: " . ($_POST['bosaltma_limani'] ?? 'BOŞ'));
    error_log("  📦 Konteyner No: " . ($_POST['konteyner_numarasi'] ?? 'BOŞ'));
    error_log("  🚢 Gemi Adı: " . ($_POST['gemi_adi'] ?? 'BOŞ'));
    error_log("  📅 Yükleme Tarihi: " . ($_POST['yukleme_tarihi'] ?? 'BOŞ'));
    error_log("  📅 Tahmini Varış: " . ($_POST['tahmini_varis_tarihi'] ?? 'BOŞ'));
    error_log("  📅 TR Varış: " . ($_POST['tr_varis_tarihi'] ?? 'BOŞ'));
    
    $sql_sevkiyat = "UPDATE sevkiyat SET 
        yukleme_limani = :yukleme_limani,
        bosaltma_limani = :bosaltma_limani,
        konteyner_numarasi = :konteyner_numarasi,
        gemi_adi = :gemi_adi,
        yukleme_tarihi = :yukleme_tarihi,
        tahmini_varis_tarihi = :tahmini_varis_tarihi,
        tr_varis_tarihi = :tr_varis_tarihi,
        nakliye_dahil = :nakliye_dahil,
        navlun_odeme_sorumlusu = :navlun_odeme_sorumlusu,
        konteyner_sorumlu = :konteyner_sorumlu,
        original_evrak_durumu = :original_evrak_durumu,
        original_evrak_tarih = :original_evrak_tarih,
        telex_durumu = :telex_durumu,
        telex_tarih = :telex_tarih,
        evrak_notlari = :evrak_notlari
    WHERE ithalat_id = :ithalat_id";
    
    $stmt_sevkiyat = $db->prepare($sql_sevkiyat);
    $sevkiyat_result = $stmt_sevkiyat->execute([
        ':yukleme_limani' => cleanInput($_POST['yukleme_limani'] ?? null),
        ':bosaltma_limani' => cleanInput($_POST['bosaltma_limani'] ?? null),
        ':konteyner_numarasi' => cleanInput($_POST['konteyner_numarasi'] ?? null),
        ':gemi_adi' => cleanInput($_POST['gemi_adi'] ?? null),
        ':yukleme_tarihi' => !empty($_POST['yukleme_tarihi']) ? $_POST['yukleme_tarihi'] : null,
        ':tahmini_varis_tarihi' => !empty($_POST['tahmini_varis_tarihi']) ? $_POST['tahmini_varis_tarihi'] : null,
        ':tr_varis_tarihi' => !empty($_POST['tr_varis_tarihi']) ? $_POST['tr_varis_tarihi'] : null,
        ':nakliye_dahil' => cleanInput($_POST['nakliye_dahil'] ?? null),
        ':navlun_odeme_sorumlusu' => cleanInput($_POST['navlun_odeme_sorumlusu'] ?? null),
        ':konteyner_sorumlu' => cleanInput($_POST['konteyner_sorumlu'] ?? null),
        ':original_evrak_durumu' => cleanInput($_POST['original_evrak_durumu'] ?? 'bekleniyor'),
        ':original_evrak_tarih' => !empty($_POST['original_evrak_tarih']) ? $_POST['original_evrak_tarih'] : null,
        ':telex_durumu' => cleanInput($_POST['telex_durumu'] ?? 'bekleniyor'),
        ':telex_tarih' => !empty($_POST['telex_tarih']) ? $_POST['telex_tarih'] : null,
        ':evrak_notlari' => cleanInput($_POST['evrak_notlari'] ?? null),
        ':ithalat_id' => $ithalat_id
    ]);
    
    $sevkiyat_row_count = $stmt_sevkiyat->rowCount();
    error_log("✅ ADIM 6 TAMAMLANDI - Etkilenen satır: $sevkiyat_row_count");
    
    if ($sevkiyat_row_count === 0) {
        error_log("⚠️ UYARI: Sevkiyat tablosunda satır güncellenmedi!");
    }
    
    // ========================================
    // 7. COMMIT
    // ========================================
    error_log("📝 ADIM 7: Transaction commit ediliyor...");
    
    $db->commit();
    
    error_log("✅ ADIM 7 TAMAMLANDI - Transaction commit başarılı!");
    error_log("════════════════════════════════════════════");
    error_log("✅✅✅ TÜM İŞLEM BAŞARILI! ✅✅✅");
    error_log("════════════════════════════════════════════");
    
    // Başarılı response
    echo json_encode([
        'success' => true,
        'message' => 'Güncelleme başarılı!',
        'ithalat_id' => $ithalat_id,
        'toplam_kg' => number_format($toplam_kg, 2),
        'toplam_gider' => number_format($toplam_gider, 2),
        'debug_info' => [
            'gumruk' => $gumruk,
            'tarim' => $tarim,
            'nakliye' => $nakliye,
            'sigorta' => $sigorta,
            'yukleme_limani' => $_POST['yukleme_limani'] ?? null,
            'konteyner_numarasi' => $_POST['konteyner_numarasi'] ?? null,
            'gider_row_count' => $gider_row_count,
            'sevkiyat_row_count' => $sevkiyat_row_count
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
        error_log("🔄 Transaction rollback yapıldı");
    }
    
    error_log("════════════════════════════════════════════");
    error_log("❌❌❌ HATA OLUŞTU! ❌❌❌");
    error_log("Mesaj: " . $e->getMessage());
    error_log("Dosya: " . $e->getFile());
    error_log("Satır: " . $e->getLine());
    error_log("Stack: " . $e->getTraceAsString());
    error_log("════════════════════════════════════════════");
    
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ], JSON_UNESCAPED_UNICODE);
}
?>