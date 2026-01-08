<?php
/**
 * İthalat Kaydetme API - DÜZELTİLMİŞ
 * ✅ Çoklu ürün desteği
 * ✅ FİRMA BAZLI Dosya Numarası otomatik üretimi
 * ✅ VERGİ SİSTEMİ: GTIP, Gümrük, ÖTV, KDV
 * ✅ KUR BİLGİSİ kaydetme
 * ✅ EKSİK ALANLAR EKLENDİ (GTIP, Kontrol Belgesi, Depozito, vs.)
 * ✅ FIX: Çift kayıt sorunu tamamen giderildi
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/settings.php';
} catch(Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Config yüklenemedi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'Sadece POST metodu kabul edilir'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ✅ ÇİFTE KAYIT ÖNLEYİCİ
$lock_key = 'import_lock_' . session_id();
if (function_exists('apcu_fetch') && apcu_fetch($lock_key)) {
    echo json_encode([
        'success' => false,
        'message' => 'Lütfen bekleyin, bir işlem devam ediyor...'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Lock set et (5 saniye)
    if (function_exists('apcu_store')) {
        apcu_store($lock_key, true, 5);
    }
    
    $db = getDB();
    
    // ========================================
    // ✅ FİRMA ID KONTROLÜ
    // ========================================
    
    if (empty($_POST['ithalatci_firma_id'])) {
        throw new Exception('İthalatçı firma seçimi zorunludur!');
    }
    
    $ithalatci_firma_id = intval($_POST['ithalatci_firma_id']);
    
    // Firma var mı kontrol et
    $firma = getFirmaById($ithalatci_firma_id);
    if (!$firma) {
        throw new Exception('Seçilen firma bulunamadı! Lütfen sayfayı yenileyin.');
    }
    
    if ($firma['aktif'] != 1) {
        throw new Exception('Seçilen firma aktif değil!');
    }
    
    error_log("✅ Firma seçildi: {$firma['firma_adi']} (ID: $ithalatci_firma_id)");
    
    // ✅ TRANSACTION BAŞLAT
    $db->beginTransaction();
    
    // ========================================
    // ✅ FİRMA BAZLI DOSYA NUMARASI ÜRET
    // ========================================
    
    $balik_dunyasi_dosya_no = generateFirmaDosyaNo($db, $ithalatci_firma_id);
    
    if ($balik_dunyasi_dosya_no === false) {
        throw new Exception('Dosya numarası üretilemedi! Lütfen tekrar deneyin.');
    }
    
    error_log("✅ Dosya numarası üretildi: $balik_dunyasi_dosya_no (Firma: {$firma['firma_adi']})");
    
    // ✅ SON BİR KERE DAHA KONTROL ET
    $checkSQL = "SELECT COUNT(*) as sayi FROM ithalat WHERE balik_dunyasi_dosya_no = :bd_no";
    $checkStmt = $db->prepare($checkSQL);
    $checkStmt->execute([':bd_no' => $balik_dunyasi_dosya_no]);
    $checkResult = $checkStmt->fetch();
    
    if ($checkResult['sayi'] > 0) {
        throw new Exception('Bu dosya numarası zaten kullanılıyor! Lütfen sayfayı yenileyin.');
    }
    
    // ========================================
    // 2. İTHALAT ANA KAYDI - ✅ EKSİK ALANLAR EKLENDİ
    // ========================================
    
    $sql_ithalat = "INSERT INTO ithalat (
        ithalatci_firma_id,
        balik_dunyasi_dosya_no,
        tedarikci_firma,
        tedarikci_ulke,
        tedarikci_siparis_no, 
        mensei_ulke,
        transit_detay,
        siparis_tarihi,
        ilk_siparis_tarihi,
        tahmini_teslim_ayi,
        ithalat_durumu,
        sigorta_durumu,
        gtip_kodu,
        gtip_tipi,
        ithal_kontrol_belgesi_16,
        tarim_bakanlik_onay_tarihi,
        kontrol_belgesi_suresi,
        tek_fabrika,
        depozito_anlasmasi,
        komisyon_anlasmasi,
        notlar
    ) VALUES (
        :ithalatci_firma_id,
        :balik_dunyasi_dosya_no,
        :tedarikci_firma,
        :tedarikci_ulke,
        :tedarikci_siparis_no,
        :mensei_ulke,
        :transit_detay,
        :siparis_tarihi,
        :ilk_siparis_tarihi,
        :tahmini_teslim_ayi,
        'siparis_verildi',
        :sigorta_durumu,
        :gtip_kodu,
        :gtip_tipi,
        :ithal_kontrol_belgesi_16,
        :tarim_bakanlik_onay_tarihi,
        :kontrol_belgesi_suresi,
        :tek_fabrika,
        :depozito_anlasmasi,
        :komisyon_anlasmasi,
        :notlar
    )";
    
    $stmt_ithalat = $db->prepare($sql_ithalat);
    $stmt_ithalat->execute([
        ':ithalatci_firma_id' => $ithalatci_firma_id,
        ':balik_dunyasi_dosya_no' => $balik_dunyasi_dosya_no,
        ':tedarikci_firma' => cleanInput($_POST['tedarikci_firma'] ?? ''),
        ':tedarikci_ulke' => cleanInput($_POST['tedarikci_ulke'] ?? ''),
        ':tedarikci_siparis_no' => cleanInput($_POST['tedarikci_siparis_no'] ?? null),  // ✅ YENİ
        ':mensei_ulke' => cleanInput($_POST['mensei_ulke'] ?? null),
        ':transit_detay' => cleanInput($_POST['transit_detay'] ?? null),
        ':siparis_tarihi' => !empty($_POST['siparis_tarihi']) ? $_POST['siparis_tarihi'] : null,
        ':ilk_siparis_tarihi' => !empty($_POST['ilk_siparis_tarihi']) ? $_POST['ilk_siparis_tarihi'] : null,
        ':tahmini_teslim_ayi' => !empty($_POST['tahmini_teslim_ayi']) ? $_POST['tahmini_teslim_ayi'] : null,
        ':sigorta_durumu' => cleanInput($_POST['sigorta_durumu'] ?? null),
        // ✅ YENİ ALANLAR
        ':gtip_kodu' => cleanInput($_POST['gtip_kodu'] ?? null),
        ':gtip_tipi' => cleanInput($_POST['gtip_tipi'] ?? null),
        ':ithal_kontrol_belgesi_16' => cleanInput($_POST['ithal_kontrol_belgesi_16'] ?? null),
        ':tarim_bakanlik_onay_tarihi' => !empty($_POST['tarim_bakanlik_onay_tarihi']) ? $_POST['tarim_bakanlik_onay_tarihi'] : null,
        ':kontrol_belgesi_suresi' => cleanInput($_POST['kontrol_belgesi_suresi'] ?? null),
        ':tek_fabrika' => cleanInput($_POST['tek_fabrika'] ?? null),
        ':depozito_anlasmasi' => cleanInput($_POST['depozito_anlasmasi'] ?? null),
        ':komisyon_anlasmasi' => cleanInput($_POST['komisyon_anlasmasi'] ?? null),
        ':notlar' => cleanInput($_POST['notlar'] ?? null)
    ]);
    
    $ithalat_id = $db->lastInsertId();
    
    error_log("✅ İthalat ana kaydı oluşturuldu: ID=$ithalat_id");
    
    // ========================================
    // 3. ✅ ÇOKLU ÜRÜN KAYDI (VERGİ DAHİL)
    // ========================================
    
    $urunler_json = $_POST['urunler_json'] ?? '';
    
    if (empty($urunler_json)) {
        throw new Exception('En az 1 ürün eklemelisiniz!');
    }
    
    $urunler = json_decode($urunler_json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON hatası: ' . json_last_error_msg());
    }
    
    if (!is_array($urunler) || count($urunler) === 0) {
        throw new Exception('Ürün listesi boş!');
    }
    
    $check = $db->query("SHOW TABLES LIKE 'ithalat_urunler'")->fetch();
    if (!$check) {
        throw new Exception('ithalat_urunler tablosu yok! SQL scriptini çalıştırın.');
    }
    
    // ✅ VERGİ BİLGİLERİ DAHİL ÜRÜN KAYDI
    $sql_urun = "INSERT INTO ithalat_urunler (
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
    
    $stmt_urun = $db->prepare($sql_urun);
    
    $toplam_kg = 0;
    $toplam_tutar = 0;
    $toplam_vergi_tumu = 0;
    
    foreach ($urunler as $urun) {
        $miktar = floatval($urun['miktar_kg']);
        $birim_fiyat = floatval($urun['birim_fiyat']);
        $tutar = $miktar * $birim_fiyat;
        
        $stmt_urun->execute([
            ':ithalat_id' => $ithalat_id,
            ':urun_katalog_id' => intval($urun['urun_katalog_id']),
            ':miktar_kg' => $miktar,
            ':birim_fiyat' => $birim_fiyat,
            ':para_birimi' => 'USD',
            ':toplam_tutar' => $tutar,
            ':gtip_kodu' => cleanInput($urun['gtip_kodu'] ?? null),
            ':gtip_aciklama' => cleanInput($urun['gtip_aciklama'] ?? null),
            ':gumruk_oran' => floatval($urun['gumruk_oran'] ?? 0),
            ':gumruk_tutar' => floatval($urun['gumruk_tutar'] ?? 0),
            ':otv_oran' => floatval($urun['otv_oran'] ?? 0),
            ':otv_tutar' => floatval($urun['otv_tutar'] ?? 0),
            ':kdv_oran' => floatval($urun['kdv_oran'] ?? 20),
            ':kdv_tutar' => floatval($urun['kdv_tutar'] ?? 0),
            ':toplam_vergi' => floatval($urun['toplam_vergi'] ?? 0),
            ':vergi_dahil_tutar' => floatval($urun['vergi_dahil_tutar'] ?? 0)
        ]);
        
        $toplam_kg += $miktar;
        $toplam_tutar += $tutar;
        $toplam_vergi_tumu += floatval($urun['toplam_vergi'] ?? 0);
    }
    
    error_log("✅ Ürünler kaydedildi: " . count($urunler) . " çeşit, Toplam: $" . number_format($toplam_tutar, 2) . ", Vergi: ₺" . number_format($toplam_vergi_tumu, 2));
    
    // ========================================
    // 4. ÜRÜN DETAYLARI (Eski tablo - geriye uyumluluk)
    // ========================================
    
    $ilk_urun = $urunler[0];
    
    $kalibrasyon_detay = '';
    foreach ($urunler as $u) {
        $kalibrasyon_detay .= $u['urun_cinsi'];
        if (!empty($u['kalibre'])) {
            $kalibrasyon_detay .= ' (' . $u['kalibre'] . ')';
        }
        $kalibrasyon_detay .= ': ' . number_format($u['miktar_kg'], 2) . ' KG - $' . number_format($u['birim_fiyat'], 2) . '/KG';
        
        if (!empty($u['gtip_kodu'])) {
            $kalibrasyon_detay .= ' [GTIP: ' . $u['gtip_kodu'] . ']';
        }
        $kalibrasyon_detay .= "\n";
    }
    
    $urun_durumu = null;
    if (isset($_POST['urun_durumu']) && is_array($_POST['urun_durumu'])) {
        $urun_durumu = implode(',', array_map('cleanInput', $_POST['urun_durumu']));
    }
    
    $sql_urun_detay = "INSERT INTO urun_detaylari (
        ithalat_id,
        urun_latince_isim,
        urun_cinsi,
        urun_tipi,
        toplam_siparis_kg,
        urun_durumu,
        kalibrasyon_detay
    ) VALUES (
        :ithalat_id,
        :urun_latince_isim,
        :urun_cinsi,
        :urun_tipi,
        :toplam_siparis_kg,
        :urun_durumu,
        :kalibrasyon_detay
    )";
    
    $stmt_urun_detay = $db->prepare($sql_urun_detay);
    $stmt_urun_detay->execute([
        ':ithalat_id' => $ithalat_id,
        ':urun_latince_isim' => $ilk_urun['urun_latince_isim'],
        ':urun_cinsi' => $ilk_urun['urun_cinsi'],
        ':urun_tipi' => $ilk_urun['urun_tipi'],
        ':toplam_siparis_kg' => $toplam_kg,
        ':urun_durumu' => $urun_durumu,
        ':kalibrasyon_detay' => trim($kalibrasyon_detay)
    ]);
    
    // ========================================
    // 5. ✅ ÖDEME BİLGİLERİ (KUR DAHİL)
    // ========================================
    
    $koli_marka = null;
    if (isset($_POST['koli_tasarim_marka']) && is_array($_POST['koli_tasarim_marka'])) {
        $koli_marka = implode(',', array_map('cleanInput', $_POST['koli_tasarim_marka']));
    }
    
    $sql_odeme = "INSERT INTO odemeler (
        ithalat_id,
        ilk_alis_fiyati,
        para_birimi,
        usd_kur,
        kur_tarihi,
        kur_notu,
        tranship_ek_maliyet,
        komisyon_firma,
        komisyon_tutari,
        on_odeme_orani,
        avans_1_tutari,
        avans_1_tarihi,
        avans_1_kur,
        avans_2_tutari,
        avans_2_tarihi,
        avans_2_kur,
        final_odeme_tutari,
        final_odeme_tarihi,
        final_odeme_kur,
        toplam_fatura_tutari,
        koli_tasarim_marka
    ) VALUES (
        :ithalat_id,
        :ilk_alis_fiyati,
        :para_birimi,
        :usd_kur,
        :kur_tarihi,
        :kur_notu,
        :tranship_ek_maliyet,
        :komisyon_firma,
        :komisyon_tutari,
        :on_odeme_orani,
        :avans_1_tutari,
        :avans_1_tarihi,
        :avans_1_kur,
        :avans_2_tutari,
        :avans_2_tarihi,
        :avans_2_kur,
        :final_odeme_tutari,
        :final_odeme_tarihi,
        :final_odeme_kur,
        :toplam_fatura_tutari,
        :koli_tasarim_marka
    )";
    
    $stmt_odeme = $db->prepare($sql_odeme);
    
    // İlk alış fiyatı hesapla (ortalama)
    $ilk_alis_fiyati = $toplam_kg > 0 ? ($toplam_tutar / $toplam_kg) : null;
    
    $stmt_odeme->execute([
        ':ithalat_id' => $ithalat_id,
        ':ilk_alis_fiyati' => $ilk_alis_fiyati,
        ':para_birimi' => 'USD',
        ':usd_kur' => !empty($_POST['usd_kur']) ? floatval($_POST['usd_kur']) : null,
        ':kur_tarihi' => !empty($_POST['kur_tarihi']) ? $_POST['kur_tarihi'] : null,
        ':kur_notu' => cleanInput($_POST['kur_notu'] ?? null),
        ':tranship_ek_maliyet' => !empty($_POST['tranship_ek_maliyet']) ? floatval($_POST['tranship_ek_maliyet']) : null,
        ':komisyon_firma' => cleanInput($_POST['komisyon_firma'] ?? null),
        ':komisyon_tutari' => !empty($_POST['komisyon_tutari']) ? floatval($_POST['komisyon_tutari']) : null,
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
        ':toplam_fatura_tutari' => $toplam_tutar,
        ':koli_tasarim_marka' => $koli_marka
    ]);
    
    error_log("✅ Ödeme bilgileri kaydedildi - Kur: " . ($_POST['usd_kur'] ?? 'yok'));
    
    // ========================================
    // 6. SEVKİYAT
    // ========================================
    
    $sql_sevkiyat = "INSERT INTO sevkiyat (
        ithalat_id,
        yukleme_tarihi,
        tahmini_varis_tarihi,
        nakliye_dahil,
        navlun_odeme_sorumlusu,
        konteyner_sorumlu
    ) VALUES (
        :ithalat_id,
        :yukleme_tarihi,
        :tahmini_varis_tarihi,
        :nakliye_dahil,
        :navlun_odeme_sorumlusu,
        :konteyner_sorumlu
    )";
    
    $stmt_sevkiyat = $db->prepare($sql_sevkiyat);
    $stmt_sevkiyat->execute([
        ':ithalat_id' => $ithalat_id,
        ':yukleme_tarihi' => !empty($_POST['yukleme_tarihi']) ? $_POST['yukleme_tarihi'] : null,
        ':tahmini_varis_tarihi' => !empty($_POST['tahmini_varis_tarihi']) ? $_POST['tahmini_varis_tarihi'] : null,
        ':nakliye_dahil' => cleanInput($_POST['nakliye_dahil'] ?? null),
        ':navlun_odeme_sorumlusu' => cleanInput($_POST['navlun_odeme_sorumlusu'] ?? null),
        ':konteyner_sorumlu' => cleanInput($_POST['konteyner_sorumlu'] ?? null)
    ]);
    
    error_log("✅ Sevkiyat bilgileri kaydedildi");
    
    // ========================================
    // 7. GİDERLER
    // ========================================
    
    $sql_giderler = "INSERT INTO giderler (ithalat_id) VALUES (:ithalat_id)";
    $stmt_giderler = $db->prepare($sql_giderler);
    $stmt_giderler->execute([':ithalat_id' => $ithalat_id]);
    
    // ========================================
    // ✅ COMMIT - HER ŞEY BAŞARILI!
    // ========================================
    
    $db->commit();
    
    // Lock'u temizle
    if (function_exists('apcu_delete')) {
        apcu_delete($lock_key);
    }
    
    // ✅ LOG: Başarılı kayıt
    error_log("✅ İTHALAT KAYDI BAŞARILI: ID=$ithalat_id, BD_NO=$balik_dunyasi_dosya_no, Firma={$firma['firma_adi']}, Ürün=" . count($urunler) . ", KG=$toplam_kg, Tutar=$" . number_format($toplam_tutar, 2) . ", Vergi=₺" . number_format($toplam_vergi_tumu, 2));
    
    echo json_encode([
        'success' => true,
        'message' => 'İthalat kaydı başarıyla oluşturuldu!',
        'ithalat_id' => $ithalat_id,
        'ithalatci_firma' => $firma['firma_adi'],
        'firma_prefix' => $firma['dosya_no_prefix'],
        'balik_dunyasi_dosya_no' => $balik_dunyasi_dosya_no,
        'urun_sayisi' => count($urunler),
        'toplam_kg' => number_format($toplam_kg, 2),
        'toplam_tutar' => number_format($toplam_tutar, 2),
        'toplam_vergi' => number_format($toplam_vergi_tumu, 2),
        'kur' => floatval($_POST['usd_kur'] ?? 0)
    ], JSON_UNESCAPED_UNICODE);
    
} catch(Exception $e) {
    // ROLLBACK
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Lock'u temizle
    if (function_exists('apcu_delete') && isset($lock_key)) {
        apcu_delete($lock_key);
    }
    
    // ✅ LOG: Hata
    error_log("❌ İTHALAT KAYIT HATASI: " . $e->getMessage() . " | Dosya: " . $e->getFile() . " | Satır: " . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}