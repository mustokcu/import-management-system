<?php
/**
 * Tek İthalat Detay CSV Export
 * Belirli bir ithalat kaydının tüm detaylarını CSV olarak indirir
 */

require_once '../config/database.php';
require_once '../config/settings.php';

$ithalat_id = $_GET['id'] ?? null;

if (!$ithalat_id) {
    die('İthalat ID gerekli!');
}

try {
    $db = getDB();
    
    // Detaylı veri çek
    $sql = "SELECT 
        i.id,
        i.tedarikci_firma,
        i.tedarikci_ulke,
        i.mensei_ulke,
        i.siparis_tarihi,
        i.ilk_siparis_tarihi,
        i.tahmini_teslim_ayi,
        i.ithalat_durumu,
        i.notlar,
        u.urun_latince_isim,
        u.urun_cinsi,
        u.urun_tipi,
        u.glz_orani,
        u.kalite_terimi,
        u.kalibrasyon_detay,
        u.toplam_siparis_kg,
        o.odeme_id,
        o.ilk_alis_fiyati,
        o.para_birimi,
        o.toplam_fatura_tutari,
        o.tranship_ek_maliyet,
        o.komisyon_firma,
        o.komisyon_tutari,
        o.avans_1_tutari,
        o.avans_1_tarihi,
        o.avans_1_kur,
        o.avans_2_tutari,
        o.avans_2_tarihi,
        o.avans_2_kur,
        o.final_odeme_tutari,
        o.final_odeme_tarihi,
        o.final_odeme_kur,
        g.gumruk_ucreti,
        g.tarim_hizmet_ucreti,
        g.nakliye_bedeli,
        g.sigorta_bedeli,
        g.ardiye_ucreti,
        g.demoraj_ucreti,
        g.toplam_gider,
        s.yukleme_limani,
        s.bosaltma_limani,
        s.konteyner_numarasi,
        s.gemi_adi,
        s.yukleme_tarihi,
        s.tahmini_varis_tarihi,
        s.tr_varis_tarihi,
        s.original_evrak_durumu,
        s.telex_durumu
    FROM ithalat i
    LEFT JOIN urun_detaylari u ON i.id = u.ithalat_id
    LEFT JOIN odemeler o ON i.id = o.ithalat_id
    LEFT JOIN giderler g ON i.id = g.ithalat_id
    LEFT JOIN sevkiyat s ON i.id = s.ithalat_id
    WHERE i.id = :id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $ithalat_id]);
    $data = $stmt->fetch();
    
    if (!$data) {
        die('İthalat kaydı bulunamadı!');
    }
    
    // CSV dosyası olarak indir
    $filename = "ithalat_detay_{$ithalat_id}_" . date('Ymd_His') . ".csv";
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // UTF-8 BOM
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Dikey format (Alan Adı - Değer)
    fputcsv($output, ['İTHALAT DETAY RAPORU - ID: ' . $ithalat_id], ';');
    fputcsv($output, [''], ';'); // Boş satır
    
    // Tedarikçi Bilgileri
    fputcsv($output, ['TEDARİKÇİ BİLGİLERİ'], ';');
    fputcsv($output, ['Tedarikçi Firma', $data['tedarikci_firma']], ';');
    fputcsv($output, ['Tedarikçi Ülke', $data['tedarikci_ulke']], ';');
    fputcsv($output, ['Menşei Ülke', $data['mensei_ulke'] ?: '-'], ';');
    fputcsv($output, ['Sipariş Tarihi', formatTarih($data['siparis_tarihi'])], ';');
    fputcsv($output, ['İlk Sipariş Tarihi', formatTarih($data['ilk_siparis_tarihi'])], ';');
    fputcsv($output, [''], ';');
    
    // Ürün Bilgileri
    fputcsv($output, ['ÜRÜN BİLGİLERİ'], ';');
    fputcsv($output, ['Ürün Latince İsim', $data['urun_latince_isim']], ';');
    fputcsv($output, ['Ürün Cinsi', $data['urun_cinsi']], ';');
    fputcsv($output, ['Ürün Tipi', $data['urun_tipi']], ';');
    fputcsv($output, ['GLZ Oranı', ($data['glz_orani'] ?: '-') . '%'], ';');
    fputcsv($output, ['Kalite Terimi', $data['kalite_terimi'] ?: '-'], ';');
    fputcsv($output, ['Toplam Sipariş', number_format($data['toplam_siparis_kg'] ?? 0, 2, ',', '.') . ' KG'], ';');
    fputcsv($output, [''], ';');
    
    // Ödeme Bilgileri
    fputcsv($output, ['ÖDEME BİLGİLERİ'], ';');
    fputcsv($output, ['Ödeme ID', $data['odeme_id']], ';');
    fputcsv($output, ['Birim Fiyat', ($data['para_birimi'] ?? 'USD') . ' ' . number_format($data['ilk_alis_fiyati'] ?? 0, 2, ',', '.')], ';');
    fputcsv($output, ['Toplam Fatura', ($data['para_birimi'] ?? 'USD') . ' ' . number_format($data['toplam_fatura_tutari'] ?? 0, 2, ',', '.')], ';');
    fputcsv($output, ['Tranship Ek Maliyet', number_format($data['tranship_ek_maliyet'] ?? 0, 2, ',', '.')], ';');
    fputcsv($output, ['Komisyon Firma', $data['komisyon_firma'] ?: '-'], ';');
    fputcsv($output, ['Komisyon Tutarı', number_format($data['komisyon_tutari'] ?? 0, 2, ',', '.')], ';');
    fputcsv($output, [''], ';');
    
    // Avans Bilgileri
    fputcsv($output, ['AVANS BİLGİLERİ'], ';');
    if ($data['avans_1_tutari']) {
        fputcsv($output, ['1. Avans', number_format($data['avans_1_tutari'], 2, ',', '.') . ' (' . formatTarih($data['avans_1_tarihi']) . ')'], ';');
        fputcsv($output, ['1. Avans Kur', 'TL ' . number_format($data['avans_1_kur'] ?? 0, 4, ',', '.')], ';');
    }
    if ($data['avans_2_tutari']) {
        fputcsv($output, ['2. Avans', number_format($data['avans_2_tutari'], 2, ',', '.') . ' (' . formatTarih($data['avans_2_tarihi']) . ')'], ';');
        fputcsv($output, ['2. Avans Kur', 'TL ' . number_format($data['avans_2_kur'] ?? 0, 4, ',', '.')], ';');
    }
    if ($data['final_odeme_tutari']) {
        fputcsv($output, ['Final Ödeme', number_format($data['final_odeme_tutari'], 2, ',', '.') . ' (' . formatTarih($data['final_odeme_tarihi']) . ')'], ';');
        fputcsv($output, ['Final Ödeme Kur', 'TL ' . number_format($data['final_odeme_kur'] ?? 0, 4, ',', '.')], ';');
    }
    fputcsv($output, [''], ';');
    
    // Giderler
    fputcsv($output, ['GİDERLER (TL)'], ';');
    fputcsv($output, ['Gümrük Ücreti', number_format($data['gumruk_ucreti'] ?? 0, 2, ',', '.')], ';');
    fputcsv($output, ['Tarım Hizmet Ücreti', number_format($data['tarim_hizmet_ucreti'] ?? 0, 2, ',', '.')], ';');
    fputcsv($output, ['Nakliye Bedeli', number_format($data['nakliye_bedeli'] ?? 0, 2, ',', '.')], ';');
    fputcsv($output, ['Sigorta Bedeli', number_format($data['sigorta_bedeli'] ?? 0, 2, ',', '.')], ';');
    fputcsv($output, ['Ardiye Ücreti', number_format($data['ardiye_ucreti'] ?? 0, 2, ',', '.')], ';');
    fputcsv($output, ['Demoraj Ücreti', number_format($data['demoraj_ucreti'] ?? 0, 2, ',', '.')], ';');
    fputcsv($output, ['TOPLAM GİDER', number_format($data['toplam_gider'] ?? 0, 2, ',', '.')], ';');
    fputcsv($output, [''], ';');
    
    // Sevkiyat
    fputcsv($output, ['SEVKİYAT BİLGİLERİ'], ';');
    fputcsv($output, ['Yükleme Limanı', $data['yukleme_limani'] ?: '-'], ';');
    fputcsv($output, ['Boşaltma Limanı', $data['bosaltma_limani'] ?: '-'], ';');
    fputcsv($output, ['Konteyner Numarası', $data['konteyner_numarasi'] ?: '-'], ';');
    fputcsv($output, ['Gemi Adı', $data['gemi_adi'] ?: '-'], ';');
    fputcsv($output, ['Yükleme Tarihi', formatTarih($data['yukleme_tarihi'])], ';');
    fputcsv($output, ['Tahmini Varış', formatTarih($data['tahmini_varis_tarihi'])], ';');
    fputcsv($output, ['TR Varış Tarihi', formatTarih($data['tr_varis_tarihi'])], ';');
    fputcsv($output, [''], ';');
    
    // Evrak Durumu
    fputcsv($output, ['EVRAK DURUMU'], ';');
    fputcsv($output, ['Original Evrak', getEvrakDurum($data['original_evrak_durumu'])], ';');
    fputcsv($output, ['Telex Durumu', getEvrakDurum($data['telex_durumu'])], ';');
    fputcsv($output, [''], ';');
    
    // Durum
    fputcsv($output, ['DURUM BİLGİSİ'], ';');
    fputcsv($output, ['İthalat Durumu', getDurumText($data['ithalat_durumu'])], ';');
    
    if ($data['notlar']) {
        fputcsv($output, [''], ';');
        fputcsv($output, ['NOTLAR'], ';');
        fputcsv($output, [$data['notlar']], ';');
    }
    
    fclose($output);
    exit;
    
} catch(Exception $e) {
    die('Hata: ' . $e->getMessage());
}

function getDurumText($durum) {
    $durumlar = [
        'siparis_verildi' => 'Sipariş Verildi',
        'yolda' => 'Yolda',
        'transitte' => 'Transit\'te',
        'limanda' => 'Limanda',
        'teslim_edildi' => 'Teslim Edildi'
    ];
    return $durumlar[$durum] ?? $durum;
}

function getEvrakDurum($durum) {
    $durumlar = [
        'bekleniyor' => 'Bekleniyor',
        'alindi' => 'Alındı',
        'teslim_edildi' => 'Teslim Edildi'
    ];
    return $durumlar[$durum] ?? $durum;
}
?>