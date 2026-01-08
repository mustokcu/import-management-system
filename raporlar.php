<?php
/**
 * Detaylı Raporlar Sayfası - TAM KAPSAMLI
 * Ülke, ürün, firma, maliyet, gider, trend, vergi ve GTIP analizleri
 */

$db = getDB();
global $URUN_TIPLERI;

// 📅 Akıllı tarih aralığı: Veritabanındaki min/max tarihleri kullan
$tarih_aralik_sql = "SELECT MIN(siparis_tarihi) as min_tarih, MAX(siparis_tarihi) as max_tarih FROM ithalat";
$tarih_stmt = $db->query($tarih_aralik_sql);
$tarih_data = $tarih_stmt->fetch();

$default_baslangic = $tarih_data['min_tarih'] ?? '2020-01-01';
$default_bitis = date('Y-m-d');

// Tarih aralığı filtresi
$baslangic_tarihi = $_GET['baslangic'] ?? $default_baslangic;
$bitis_tarihi = $_GET['bitis'] ?? $default_bitis;

// Filtreler
$filtre_ulke = $_GET['ulke'] ?? '';
$filtre_urun_tipi = $_GET['urun_tipi'] ?? '';
$filtre_urun = $_GET['urun'] ?? '';
$filtre_firma = $_GET['firma'] ?? '';

// Parametreler
$params = [
    ':baslangic' => $baslangic_tarihi,
    ':bitis' => $bitis_tarihi
];

// ============================================
// 1. ÜLKE BAZLI DETAYLI ANALİZ
// ============================================
$sql_ulke = "SELECT 
    i.tedarikci_ulke,
    COUNT(DISTINCT i.id) as toplam_ithalat,
    COUNT(DISTINCT i.tedarikci_firma) as tedarikci_sayisi,
    COALESCE(SUM(iu.miktar_kg), SUM(u.toplam_siparis_kg), 0) as toplam_kg,
    COALESCE(AVG(iu.birim_fiyat), AVG(o.ilk_alis_fiyati), 0) as ortalama_fiyat,
    COALESCE(MIN(iu.birim_fiyat), MIN(o.ilk_alis_fiyati), 0) as min_fiyat,
    COALESCE(MAX(iu.birim_fiyat), MAX(o.ilk_alis_fiyati), 0) as max_fiyat,
    COALESCE(SUM(iu.toplam_tutar), SUM(o.ilk_alis_fiyati * u.toplam_siparis_kg), 0) as toplam_tutar,
    COALESCE(SUM(g.toplam_gider), 0) as toplam_gider,
    AVG(DATEDIFF(s.tr_varis_tarihi, i.siparis_tarihi)) as ortalama_sure
FROM ithalat i
LEFT JOIN ithalat_urunler iu ON i.id = iu.ithalat_id
LEFT JOIN urun_detaylari u ON i.id = u.ithalat_id
LEFT JOIN odemeler o ON i.id = o.ithalat_id
LEFT JOIN giderler g ON i.id = g.ithalat_id
LEFT JOIN sevkiyat s ON i.id = s.ithalat_id
WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis";

if (!empty($filtre_ulke)) {
    $sql_ulke .= " AND i.tedarikci_ulke = :ulke";
    $params[':ulke'] = $filtre_ulke;
}

$sql_ulke .= " GROUP BY i.tedarikci_ulke ORDER BY toplam_tutar DESC";

$stmt_ulke = $db->prepare($sql_ulke);
$stmt_ulke->execute($params);
$ulke_rapor = $stmt_ulke->fetchAll();

// ============================================
// 2. ÜRÜN BAZLI DETAYLI ANALİZ
// ============================================
$sql_urun = "SELECT 
    COALESCE(uk.urun_cinsi, u.urun_cinsi) as urun_cinsi,
    COALESCE(uk.urun_latince_isim, u.urun_latince_isim) as urun_latince_isim,
    uk.kalibre,
    COALESCE(uk.urun_tipi, u.urun_tipi) as urun_tipi,
    uk.glz_orani,
    COUNT(DISTINCT i.id) as ithalat_sayisi,
    COALESCE(SUM(iu.miktar_kg), SUM(u.toplam_siparis_kg), 0) as toplam_kg,
    COALESCE(AVG(iu.birim_fiyat), AVG(o.ilk_alis_fiyati), 0) as ortalama_fiyat,
    COALESCE(MIN(iu.birim_fiyat), MIN(o.ilk_alis_fiyati), 0) as min_fiyat,
    COALESCE(MAX(iu.birim_fiyat), MAX(o.ilk_alis_fiyati), 0) as max_fiyat,
    COALESCE(SUM(iu.toplam_tutar), SUM(o.ilk_alis_fiyati * u.toplam_siparis_kg), 0) as toplam_tutar,
    COUNT(DISTINCT i.tedarikci_ulke) as kaynak_ulke_sayisi
FROM ithalat i
LEFT JOIN ithalat_urunler iu ON i.id = iu.ithalat_id
LEFT JOIN urun_katalog uk ON iu.urun_katalog_id = uk.id
LEFT JOIN urun_detaylari u ON i.id = u.ithalat_id
LEFT JOIN odemeler o ON i.id = o.ithalat_id
WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis";

$params_urun = $params;

if (!empty($filtre_urun_tipi)) {
    $sql_urun .= " AND (uk.urun_tipi = :urun_tipi OR u.urun_tipi = :urun_tipi)";
    $params_urun[':urun_tipi'] = $filtre_urun_tipi;
}

if (!empty($filtre_urun)) {
    $sql_urun .= " AND (uk.urun_cinsi LIKE :urun OR u.urun_cinsi LIKE :urun)";
    $params_urun[':urun'] = "%$filtre_urun%";
}

$sql_urun .= " GROUP BY urun_cinsi, urun_latince_isim, uk.kalibre, urun_tipi, uk.glz_orani
ORDER BY toplam_kg DESC LIMIT 20";

$stmt_urun = $db->prepare($sql_urun);
$stmt_urun->execute($params_urun);
$urun_rapor = $stmt_urun->fetchAll();

// ============================================
// 3. FİRMA BAZLI ANALİZ
// ============================================
$sql_firma = "SELECT 
    if2.firma_adi,
    if2.dosya_no_prefix,
    COUNT(DISTINCT i.id) as ithalat_sayisi,
    COALESCE(SUM(iu.miktar_kg), SUM(u.toplam_siparis_kg), 0) as toplam_kg,
    COALESCE(SUM(iu.toplam_tutar), SUM(o.ilk_alis_fiyati * u.toplam_siparis_kg), 0) as toplam_tutar,
    COALESCE(SUM(g.toplam_gider), 0) as toplam_gider,
    COALESCE(SUM(iu.toplam_tutar), SUM(o.ilk_alis_fiyati * u.toplam_siparis_kg), 0) - COALESCE(SUM(g.toplam_gider), 0) as net_tutar,
    COUNT(DISTINCT i.tedarikci_ulke) as ulke_sayisi
FROM ithalat i
LEFT JOIN ithalatci_firmalar if2 ON i.ithalatci_firma_id = if2.id
LEFT JOIN ithalat_urunler iu ON i.id = iu.ithalat_id
LEFT JOIN urun_detaylari u ON i.id = u.ithalat_id
LEFT JOIN odemeler o ON i.id = o.ithalat_id
LEFT JOIN giderler g ON i.id = g.ithalat_id
WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis";

$params_firma = $params;

if (!empty($filtre_firma)) {
    $sql_firma .= " AND if2.firma_adi LIKE :firma";
    $params_firma[':firma'] = "%$filtre_firma%";
}

$sql_firma .= " GROUP BY if2.firma_adi, if2.dosya_no_prefix ORDER BY toplam_tutar DESC";

$stmt_firma = $db->prepare($sql_firma);
$stmt_firma->execute($params_firma);
$firma_rapor = $stmt_firma->fetchAll();

// ============================================
// 4. MALİYET VE GİDER ANALİZİ
// ============================================
$sql_maliyet = "SELECT 
    i.id,
    i.balik_dunyasi_dosya_no,
    i.tedarikci_ulke,
    i.tedarikci_firma,
    COALESCE(uk.urun_cinsi, u.urun_cinsi) as urun_cinsi,
    COALESCE(SUM(iu.miktar_kg), u.toplam_siparis_kg, 0) as miktar_kg,
    COALESCE(SUM(iu.toplam_tutar), o.ilk_alis_fiyati * u.toplam_siparis_kg, 0) as urun_tutari,
    COALESCE(g.toplam_gider, 0) as toplam_gider,
    COALESCE(g.gumruk_ucreti, 0) as gumruk,
    COALESCE(g.tarim_hizmet_ucreti, 0) as tarim,
    COALESCE(g.nakliye_bedeli, 0) as nakliye,
    COALESCE(g.sigorta_bedeli, 0) as sigorta,
    COALESCE(g.ardiye_ucreti, 0) as ardiye,
    COALESCE(g.demoraj_ucreti, 0) as demoraj,
    COALESCE(g.diger_giderler, 0) as diger,
    (COALESCE(SUM(iu.toplam_tutar), o.ilk_alis_fiyati * u.toplam_siparis_kg, 0) + COALESCE(g.toplam_gider, 0)) as toplam_maliyet,
    ((COALESCE(SUM(iu.toplam_tutar), o.ilk_alis_fiyati * u.toplam_siparis_kg, 0) + COALESCE(g.toplam_gider, 0)) / NULLIF(COALESCE(SUM(iu.miktar_kg), u.toplam_siparis_kg, 0), 0)) as birim_maliyet
FROM ithalat i
LEFT JOIN ithalat_urunler iu ON i.id = iu.ithalat_id
LEFT JOIN urun_katalog uk ON iu.urun_katalog_id = uk.id
LEFT JOIN urun_detaylari u ON i.id = u.ithalat_id
LEFT JOIN odemeler o ON i.id = o.ithalat_id
LEFT JOIN giderler g ON i.id = g.ithalat_id
WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis
GROUP BY i.id
ORDER BY toplam_maliyet DESC
LIMIT 20";

$params_maliyet = [
    ':baslangic' => $baslangic_tarihi,
    ':bitis' => $bitis_tarihi
];

$stmt_maliyet = $db->prepare($sql_maliyet);
$stmt_maliyet->execute($params_maliyet);
$maliyet_rapor = $stmt_maliyet->fetchAll();

// ============================================
// 5. AYLIK TREND ANALİZİ
// ============================================
$sql_aylik = "SELECT 
    DATE_FORMAT(i.siparis_tarihi, '%Y-%m') as ay,
    COUNT(DISTINCT i.id) as ithalat_sayisi,
    COALESCE(SUM(iu.miktar_kg), SUM(u.toplam_siparis_kg), 0) as toplam_kg,
    COALESCE(SUM(iu.toplam_tutar), SUM(o.ilk_alis_fiyati * u.toplam_siparis_kg), 0) as toplam_tutar,
    COALESCE(SUM(g.toplam_gider), 0) as toplam_gider,
    COALESCE(AVG(iu.birim_fiyat), AVG(o.ilk_alis_fiyati), 0) as ortalama_fiyat
FROM ithalat i
LEFT JOIN ithalat_urunler iu ON i.id = iu.ithalat_id
LEFT JOIN urun_detaylari u ON i.id = u.ithalat_id
LEFT JOIN odemeler o ON i.id = o.ithalat_id
LEFT JOIN giderler g ON i.id = g.ithalat_id
WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis
GROUP BY DATE_FORMAT(i.siparis_tarihi, '%Y-%m')
ORDER BY ay ASC";

$params_aylik = [
    ':baslangic' => $baslangic_tarihi,
    ':bitis' => $bitis_tarihi
];

$stmt_aylik = $db->prepare($sql_aylik);
$stmt_aylik->execute($params_aylik);
$aylik_rapor = $stmt_aylik->fetchAll();

// ============================================
// 6. GÜNLÜK TREND ANALİZİ (Son 30 gün)
// ============================================
$sql_gunluk = "SELECT 
    DATE(i.siparis_tarihi) as gun,
    COUNT(DISTINCT i.id) as ithalat_sayisi,
    COALESCE(SUM(iu.miktar_kg), SUM(u.toplam_siparis_kg), 0) as toplam_kg,
    COALESCE(SUM(iu.toplam_tutar), SUM(o.ilk_alis_fiyati * u.toplam_siparis_kg), 0) as toplam_tutar
FROM ithalat i
LEFT JOIN ithalat_urunler iu ON i.id = iu.ithalat_id
LEFT JOIN urun_detaylari u ON i.id = u.ithalat_id
LEFT JOIN odemeler o ON i.id = o.ithalat_id
WHERE i.siparis_tarihi BETWEEN DATE_SUB(:bitis, INTERVAL 30 DAY) AND :bitis2
GROUP BY DATE(i.siparis_tarihi)
ORDER BY gun ASC";

$params_gunluk = [
    ':bitis' => $bitis_tarihi,
    ':bitis2' => $bitis_tarihi
];

$stmt_gunluk = $db->prepare($sql_gunluk);
$stmt_gunluk->execute($params_gunluk);
$gunluk_rapor = $stmt_gunluk->fetchAll();

// ============================================
// 7. GİDER DAĞILIMI ANALİZİ
// ============================================
$sql_gider_dagilim = "SELECT 
    COALESCE(SUM(g.gumruk_ucreti), 0) as toplam_gumruk,
    COALESCE(SUM(g.tarim_hizmet_ucreti), 0) as toplam_tarim,
    COALESCE(SUM(g.nakliye_bedeli), 0) as toplam_nakliye,
    COALESCE(SUM(g.sigorta_bedeli), 0) as toplam_sigorta,
    COALESCE(SUM(g.ardiye_ucreti), 0) as toplam_ardiye,
    COALESCE(SUM(g.demoraj_ucreti), 0) as toplam_demoraj,
    COALESCE(SUM(g.gec_teslim_bedeli), 0) as toplam_gec_teslim,
    COALESCE(SUM(g.bekleme_ucreti), 0) as toplam_bekleme,
    COALESCE(SUM(g.diger_giderler), 0) as toplam_diger,
    COALESCE(SUM(g.toplam_gider), 0) as genel_toplam,
    COUNT(DISTINCT i.id) as ithalat_sayisi
FROM ithalat i
LEFT JOIN giderler g ON i.id = g.ithalat_id
WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis";

$params_gider = [
    ':baslangic' => $baslangic_tarihi,
    ':bitis' => $bitis_tarihi
];

$stmt_gider = $db->prepare($sql_gider_dagilim);
$stmt_gider->execute($params_gider);
$gider_dagilim = $stmt_gider->fetch();

// ============================================
// 8. VERGİ ANALİZİ
// ============================================
$sql_vergi = "SELECT 
    i.id,
    i.balik_dunyasi_dosya_no,
    i.tedarikci_firma,
    i.tedarikci_ulke,
    i.siparis_tarihi,
    
    -- Kur bilgisi
    o.usd_kur,
    o.kur_tarihi,
    
    -- Ürün toplamları
    COUNT(DISTINCT iu.id) as urun_cesit_sayisi,
    SUM(iu.miktar_kg) as toplam_kg,
    SUM(iu.toplam_tutar) as toplam_usd,
    
    -- Vergi toplamları
    SUM(iu.gumruk_vergisi_tutar) as toplam_gumruk,
    SUM(iu.otv_tutar) as toplam_otv,
    SUM(iu.kdv_tutar) as toplam_kdv,
    SUM(iu.toplam_vergi) as toplam_vergi,
    SUM(iu.vergi_dahil_tutar) as toplam_vergi_dahil,
    
    -- Ortalama vergi oranları
    AVG(iu.gumruk_vergisi_oran) as ort_gumruk_oran,
    AVG(iu.otv_oran) as ort_otv_oran,
    AVG(iu.kdv_oran) as ort_kdv_oran
    
FROM ithalat i
LEFT JOIN ithalat_urunler iu ON i.id = iu.ithalat_id
LEFT JOIN odemeler o ON i.id = o.ithalat_id
WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis
AND iu.toplam_vergi > 0
GROUP BY i.id
ORDER BY toplam_vergi DESC
LIMIT 20";

$params_vergi = [
    ':baslangic' => $baslangic_tarihi,
    ':bitis' => $bitis_tarihi
];

$stmt_vergi = $db->prepare($sql_vergi);
$stmt_vergi->execute($params_vergi);
$vergi_rapor = $stmt_vergi->fetchAll();

// Genel vergi özeti
$sql_vergi_ozet = "SELECT 
    COUNT(DISTINCT i.id) as ithalat_sayisi,
    SUM(iu.gumruk_vergisi_tutar) as genel_gumruk,
    SUM(iu.otv_tutar) as genel_otv,
    SUM(iu.kdv_tutar) as genel_kdv,
    SUM(iu.toplam_vergi) as genel_toplam_vergi,
    AVG(iu.gumruk_vergisi_oran) as ort_gumruk,
    AVG(iu.otv_oran) as ort_otv,
    AVG(iu.kdv_oran) as ort_kdv
FROM ithalat i
LEFT JOIN ithalat_urunler iu ON i.id = iu.ithalat_id
WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis
AND iu.toplam_vergi > 0";

$stmt_vergi_ozet = $db->prepare($sql_vergi_ozet);
$stmt_vergi_ozet->execute($params_vergi);
$vergi_ozet = $stmt_vergi_ozet->fetch();

// ============================================
// 9. GTIP KOD BAZLI ANALİZ
// ============================================
$sql_gtip_rapor = "SELECT 
    iu.gtip_kodu,
    iu.gtip_aciklama,
    COUNT(DISTINCT i.id) as ithalat_sayisi,
    COUNT(DISTINCT i.tedarikci_ulke) as ulke_sayisi,
    SUM(iu.miktar_kg) as toplam_kg,
    SUM(iu.toplam_tutar) as toplam_usd,
    AVG(iu.birim_fiyat) as ort_birim_fiyat,
    
    -- Vergi bilgileri
    AVG(iu.gumruk_vergisi_oran) as ort_gumruk_oran,
    AVG(iu.otv_oran) as ort_otv_oran,
    AVG(iu.kdv_oran) as ort_kdv_oran,
    SUM(iu.toplam_vergi) as toplam_vergi
    
FROM ithalat i
LEFT JOIN ithalat_urunler iu ON i.id = iu.ithalat_id
WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis
AND iu.gtip_kodu IS NOT NULL
GROUP BY iu.gtip_kodu, iu.gtip_aciklama
ORDER BY toplam_kg DESC
LIMIT 20";

$stmt_gtip_rapor = $db->prepare($sql_gtip_rapor);
$stmt_gtip_rapor->execute($params_vergi);
$gtip_rapor = $stmt_gtip_rapor->fetchAll();

// ============================================
// 10. VERGİ TÜRÜ BAZLI ANALİZ
// ============================================
$sql_vergi_turu = "SELECT 
    CASE 
        WHEN iu.gumruk_vergisi_oran > 0 AND iu.otv_oran > 0 THEN 'Gümrük + ÖTV + KDV'
        WHEN iu.gumruk_vergisi_oran > 0 AND iu.otv_oran = 0 THEN 'Gümrük + KDV'
        WHEN iu.gumruk_vergisi_oran = 0 AND iu.otv_oran > 0 THEN 'ÖTV + KDV'
        ELSE 'Sadece KDV'
    END as vergi_turu,
    COUNT(DISTINCT i.id) as ithalat_sayisi,
    COUNT(DISTINCT iu.id) as urun_sayisi,
    SUM(iu.miktar_kg) as toplam_kg,
    SUM(iu.toplam_tutar) as toplam_usd,
    SUM(iu.gumruk_vergisi_tutar) as toplam_gumruk,
    SUM(iu.otv_tutar) as toplam_otv,
    SUM(iu.kdv_tutar) as toplam_kdv,
    SUM(iu.toplam_vergi) as toplam_vergi,
    AVG(iu.gumruk_vergisi_oran) as ort_gumruk_oran,
    AVG(iu.otv_oran) as ort_otv_oran,
    AVG(iu.kdv_oran) as ort_kdv_oran,
    (SUM(iu.toplam_vergi) / NULLIF(SUM(iu.toplam_tutar), 0) * 100) as efektif_vergi_orani
FROM ithalat i
LEFT JOIN ithalat_urunler iu ON i.id = iu.ithalat_id
WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis
AND iu.toplam_vergi > 0
GROUP BY vergi_turu
ORDER BY toplam_vergi DESC";

$stmt_vergi_turu = $db->prepare($sql_vergi_turu);
$stmt_vergi_turu->execute($params_vergi);
$vergi_turu_rapor = $stmt_vergi_turu->fetchAll();

// Aktif filtre listeleri
$sql_aktif_ulkeler = "SELECT DISTINCT tedarikci_ulke FROM ithalat WHERE tedarikci_ulke IS NOT NULL AND tedarikci_ulke != '' ORDER BY tedarikci_ulke";
$stmt_au = $db->query($sql_aktif_ulkeler);
$aktif_ulkeler = $stmt_au->fetchAll(PDO::FETCH_COLUMN);

$sql_aktif_firmalar = "SELECT DISTINCT firma_adi FROM ithalatci_firmalar WHERE aktif = 1 ORDER BY firma_adi";
$stmt_af = $db->query($sql_aktif_firmalar);
$aktif_firmalar = $stmt_af->fetchAll(PDO::FETCH_COLUMN);
?>

<style>
    .report-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    }
    
    .filter-panel {
        background: white;
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        border-left: 5px solid #667eea;
    }
    
    .report-section {
        background: white;
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .section-title {
        font-size: 1.4rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 3px solid #667eea;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .section-icon {
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
    }
    
    .table-detailed {
        width: 100%;
        margin-top: 20px;
        font-size: 0.9rem;
    }
    
    .table-detailed thead {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }
    
    .table-detailed thead th {
        padding: 12px 8px;
        font-weight: 600;
        border: none;
        font-size: 0.85rem;
    }
    
    .table-detailed tbody tr {
        transition: all 0.2s ease;
    }
    
    .table-detailed tbody tr:hover {
        background: #f8f9fa;
        transform: scale(1.005);
    }
    
    .table-detailed td {
        padding: 10px 8px;
        border-bottom: 1px solid #dee2e6;
        font-size: 0.85rem;
    }
    
    .highlight-cell {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        font-weight: bold;
        color: #2e7d32;
    }
    
    .chart-container-detail {
        position: relative;
        height: 350px;
        margin-top: 20px;
    }
    
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .stat-box {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 20px;
        border-radius: 10px;
        border-left: 4px solid #667eea;
        transition: all 0.3s ease;
    }
    
    .stat-box:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.2);
    }
    
    .stat-value {
        font-size: 1.8rem;
        font-weight: bold;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 0.85rem;
        color: #7f8c8d;
    }
    
    .export-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    @media print {
        .filter-panel, .export-buttons {
            display: none;
        }
    }
</style>

<div class="report-header">
    <h2 class="mb-0"><i class="fas fa-chart-bar"></i> Kapsamlı Raporlar ve Analizler</h2>
    <p class="mb-0 mt-2">Ülke, ürün, firma, maliyet, vergi ve trend analizleri</p>
</div>

<!-- FİLTRE PANELİ -->
<div class="filter-panel">
    <form method="GET" class="row g-3">
        <input type="hidden" name="page" value="raporlar">
        
        <div class="col-md-2">
            <label class="form-label"><i class="fas fa-calendar-alt"></i> Başlangıç</label>
            <input type="date" class="form-control" name="baslangic" value="<?php echo $baslangic_tarihi; ?>">
        </div>
        
        <div class="col-md-2">
            <label class="form-label"><i class="fas fa-calendar-alt"></i> Bitiş</label>
            <input type="date" class="form-control" name="bitis" value="<?php echo $bitis_tarihi; ?>">
        </div>
        
        <div class="col-md-2">
            <label class="form-label"><i class="fas fa-globe"></i> Ülke</label>
            <select class="form-select" name="ulke">
                <option value="">Tümü</option>
                <?php foreach($aktif_ulkeler as $ulke): ?>
                    <option value="<?php echo $ulke; ?>" <?php echo $filtre_ulke == $ulke ? 'selected' : ''; ?>>
                        <?php echo safeHtml($ulke); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2">
            <label class="form-label"><i class="fas fa-layer-group"></i> Ürün Tipi</label>
            <select class="form-select" name="urun_tipi">
                <option value="">Tümü</option>
                <?php foreach($URUN_TIPLERI as $key => $value): ?>
                    <option value="<?php echo $key; ?>" <?php echo $filtre_urun_tipi == $key ? 'selected' : ''; ?>>
                        <?php echo $value; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2">
            <label class="form-label"><i class="fas fa-building"></i> Firma</label>
            <select class="form-select" name="firma">
                <option value="">Tümü</option>
                <?php foreach($aktif_firmalar as $firma): ?>
                    <option value="<?php echo $firma; ?>" <?php echo $filtre_firma == $firma ? 'selected' : ''; ?>>
                        <?php echo safeHtml($firma); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="fas fa-filter"></i> Filtrele
            </button>
            <a href="?page=raporlar" class="btn btn-secondary">
                <i class="fas fa-redo"></i>
            </a>
        </div>
    </form>
    
    <div class="export-buttons mt-3">
        <button class="btn btn-success" onclick="window.print()">
            <i class="fas fa-print"></i> Yazdır
        </button>
        <button class="btn btn-info" onclick="exportToExcel()">
            <i class="fas fa-file-excel"></i> Excel'e Aktar
        </button>
    </div>
</div>

<!-- 1. ÜLKE BAZLI ANALİZ -->
<div class="report-section">
    <h3 class="section-title">
        <div class="section-icon"><i class="fas fa-globe-americas"></i></div>
        Ülke Bazlı Analiz
    </h3>
    
    <?php if (count($ulke_rapor) > 0): ?>
        <div class="table-responsive">
            <table class="table table-detailed table-hover">
                <thead>
                    <tr>
                        <th>Ülke</th>
                        <th>İthalat</th>
                        <th>Tedarikçi</th>
                        <th>Toplam KG</th>
                        <th>Ort. Fiyat</th>
                        <th>Fiyat Aralığı</th>
                        <th>Toplam Tutar</th>
                        <th>Toplam Gider</th>
                        <th>Ort. Teslimat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($ulke_rapor as $ur): ?>
                        <tr>
                            <td><strong><?php echo safeHtml($ur['tedarikci_ulke']); ?></strong></td>
                            <td><?php echo $ur['toplam_ithalat']; ?></td>
                            <td><?php echo $ur['tedarikci_sayisi']; ?></td>
                            <td class="highlight-cell"><?php echo safeNumber($ur['toplam_kg'], 0); ?> KG</td>
                            <td>$<?php echo safeNumber($ur['ortalama_fiyat']); ?></td>
                            <td><small>$<?php echo safeNumber($ur['min_fiyat']); ?> - $<?php echo safeNumber($ur['max_fiyat']); ?></small></td>
                            <td class="highlight-cell"><strong>$<?php echo safeNumber($ur['toplam_tutar'], 0); ?></strong></td>
                            <td>₺<?php echo safeNumber($ur['toplam_gider'], 0); ?></td>
                            <td>
                                <?php if ($ur['ortalama_sure']): ?>
                                    <span class="badge bg-info"><?php echo round($ur['ortalama_sure']); ?> gün</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Ülke verisi bulunamadı.
        </div>
    <?php endif; ?>
</div>

<!-- 2. ÜRÜN BAZLI ANALİZ -->
<div class="report-section">
    <h3 class="section-title">
        <div class="section-icon"><i class="fas fa-fish"></i></div>
        Ürün Bazlı Analiz
    </h3>
    
    <?php if (count($urun_rapor) > 0): ?>
        <div class="table-responsive">
            <table class="table table-detailed table-hover">
                <thead>
                    <tr>
                        <th>Ürün Cinsi</th>
                        <th>Latince İsim</th>
                        <th>Kalibre</th>
                        <th>Tip</th>
                        <th>GLZ</th>
                        <th>İthalat</th>
                        <th>Ülke</th>
                        <th>Toplam KG</th>
                        <th>Ort. Fiyat</th>
                        <th>Toplam Tutar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($urun_rapor as $uru): ?>
                        <tr>
                            <td><strong><?php echo safeHtml($uru['urun_cinsi']); ?></strong></td>
                            <td><em><?php echo safeHtml($uru['urun_latince_isim']); ?></em></td>
                            <td><?php echo $uru['kalibre'] ? '<span class="badge bg-info">' . safeHtml($uru['kalibre']) . '</span>' : '-'; ?></td>
                            <td><?php echo $URUN_TIPLERI[$uru['urun_tipi']] ?? $uru['urun_tipi']; ?></td>
                            <td><?php echo $uru['glz_orani'] ? $uru['glz_orani'] . '%' : '-'; ?></td>
                            <td><?php echo $uru['ithalat_sayisi']; ?></td>
                            <td><span class="badge bg-secondary"><?php echo $uru['kaynak_ulke_sayisi']; ?></span></td>
                            <td class="highlight-cell"><?php echo safeNumber($uru['toplam_kg'], 0); ?> KG</td>
                            <td><strong>$<?php echo safeNumber($uru['ortalama_fiyat']); ?></strong></td>
                            <td class="highlight-cell"><strong>$<?php echo safeNumber($uru['toplam_tutar'], 0); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Ürün verisi bulunamadı.
        </div>
    <?php endif; ?>
</div>

<!-- 3. FİRMA BAZLI ANALİZ -->
<div class="report-section">
    <h3 class="section-title">
        <div class="section-icon"><i class="fas fa-building"></i></div>
        Firma Bazlı Analiz
    </h3>
    
    <?php if (count($firma_rapor) > 0): ?>
        <div class="table-responsive">
            <table class="table table-detailed table-hover">
                <thead>
                    <tr>
                        <th>Firma</th>
                        <th>Prefix</th>
                        <th>İthalat Sayısı</th>
                        <th>Toplam KG</th>
                        <th>Toplam Tutar</th>
                        <th>Toplam Gider</th>
                        <th>Net Tutar</th>
                        <th>Ülke Sayısı</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($firma_rapor as $fr): ?>
                        <tr>
                            <td><strong><?php echo safeHtml($fr['firma_adi']); ?></strong></td>
                            <td><span class="badge bg-primary"><?php echo safeHtml($fr['dosya_no_prefix']); ?></span></td>
                            <td><?php echo $fr['ithalat_sayisi']; ?></td>
                            <td><?php echo safeNumber($fr['toplam_kg'], 0); ?> KG</td>
                            <td class="highlight-cell"><strong>$<?php echo safeNumber($fr['toplam_tutar'], 0); ?></strong></td>
                            <td>₺<?php echo safeNumber($fr['toplam_gider'], 0); ?></td>
                            <td class="highlight-cell"><strong>$<?php echo safeNumber($fr['net_tutar'], 0); ?></strong></td>
                            <td><span class="badge bg-secondary"><?php echo $fr['ulke_sayisi']; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Firma verisi bulunamadı.
        </div>
    <?php endif; ?>
</div>

<!-- 4. MALİYET VE GİDER ANALİZİ -->
<div class="report-section">
    <h3 class="section-title">
        <div class="section-icon"><i class="fas fa-money-bill-wave"></i></div>
        Maliyet ve Gider Analizi
    </h3>
    
    <!-- Gider Özeti Kartları -->
    <?php if ($gider_dagilim && $gider_dagilim['ithalat_sayisi'] > 0): ?>
        <div class="stats-summary mb-4">
            <div class="stat-box">
                <div class="stat-value">₺<?php echo safeNumber($gider_dagilim['toplam_gumruk'], 0); ?></div>
                <div class="stat-label">Gümrük Ücreti</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">₺<?php echo safeNumber($gider_dagilim['toplam_tarim'], 0); ?></div>
                <div class="stat-label">Tarım Hizmet</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">₺<?php echo safeNumber($gider_dagilim['toplam_nakliye'], 0); ?></div>
                <div class="stat-label">Nakliye</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">₺<?php echo safeNumber($gider_dagilim['toplam_sigorta'], 0); ?></div>
                <div class="stat-label">Sigorta</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">₺<?php echo safeNumber($gider_dagilim['toplam_ardiye'], 0); ?></div>
                <div class="stat-label">Ardiye</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">₺<?php echo safeNumber($gider_dagilim['toplam_demoraj'], 0); ?></div>
                <div class="stat-label">Demoraj</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">₺<?php echo safeNumber($gider_dagilim['toplam_diger'], 0); ?></div>
                <div class="stat-label">Diğer Giderler</div>
            </div>
            <div class="stat-box" style="border-left-color: #e74c3c;">
                <div class="stat-value" style="color: #e74c3c;">₺<?php echo safeNumber($gider_dagilim['genel_toplam'], 0); ?></div>
                <div class="stat-label"><strong>TOPLAM GİDER</strong></div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Detaylı Maliyet Tablosu -->
    <?php if (count($maliyet_rapor) > 0): ?>
        <div class="table-responsive">
            <table class="table table-detailed table-hover">
                <thead>
                    <tr>
                        <th>Dosya No</th>
                        <th>Ülke</th>
                        <th>Tedarikçi</th>
                        <th>Ürün</th>
                        <th>KG</th>
                        <th>Ürün Tutarı</th>
                        <th>Toplam Gider</th>
                        <th>Toplam Maliyet</th>
                        <th>Birim Maliyet</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($maliyet_rapor as $mr): ?>
                        <tr>
                            <td><code><?php echo safeHtml($mr['balik_dunyasi_dosya_no']); ?></code></td>
                            <td><?php echo safeHtml($mr['tedarikci_ulke']); ?></td>
                            <td><small><?php echo safeHtml($mr['tedarikci_firma']); ?></small></td>
                            <td><?php echo safeHtml($mr['urun_cinsi']); ?></td>
                            <td><?php echo safeNumber($mr['miktar_kg'], 0); ?></td>
                            <td>$<?php echo safeNumber($mr['urun_tutari'], 0); ?></td>
                            <td>₺<?php echo safeNumber($mr['toplam_gider'], 0); ?></td>
                            <td class="highlight-cell"><strong>$<?php echo safeNumber($mr['toplam_maliyet'], 0); ?></strong></td>
                            <td><strong>$<?php echo safeNumber($mr['birim_maliyet']); ?>/kg</strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Maliyet verisi bulunamadı.
        </div>
    <?php endif; ?>
</div>

<!-- 5. AYLIK TREND ANALİZİ -->
<div class="report-section">
    <h3 class="section-title">
        <div class="section-icon"><i class="fas fa-chart-line"></i></div>
        Aylık Trend Analizi
    </h3>
    
    <?php if (count($aylik_rapor) > 0): ?>
        <div class="chart-container-detail">
            <canvas id="aylikChart"></canvas>
        </div>
        
        <div class="table-responsive mt-4">
            <table class="table table-detailed table-sm">
                <thead>
                    <tr>
                        <th>Ay</th>
                        <th>İthalat</th>
                        <th>Toplam KG</th>
                        <th>Toplam Tutar</th>
                        <th>Toplam Gider</th>
                        <th>Ort. Fiyat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($aylik_rapor as $ar): ?>
                        <tr>
                            <td><strong><?php echo $ar['ay']; ?></strong></td>
                            <td><?php echo $ar['ithalat_sayisi']; ?></td>
                            <td><?php echo safeNumber($ar['toplam_kg'], 0); ?> KG</td>
                            <td>$<?php echo safeNumber($ar['toplam_tutar'], 0); ?></td>
                            <td>₺<?php echo safeNumber($ar['toplam_gider'], 0); ?></td>
                            <td>$<?php echo safeNumber($ar['ortalama_fiyat']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Aylık trend verisi bulunamadı.
        </div>
    <?php endif; ?>
</div>

<!-- 6. GÜNLÜK TREND ANALİZİ (Son 30 Gün) -->
<div class="report-section">
    <h3 class="section-title">
        <div class="section-icon"><i class="fas fa-calendar-day"></i></div>
        Günlük Trend (Son 30 Gün)
    </h3>
    
    <?php if (count($gunluk_rapor) > 0): ?>
        <div class="chart-container-detail">
            <canvas id="gunlukChart"></canvas>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Son 30 günde veri bulunamadı.
        </div>
    <?php endif; ?>
</div>

<!-- 8. VERGİ ANALİZİ RAPORU -->
<div class="report-section">
    <h3 class="section-title">
        <div class="section-icon" style="background: linear-gradient(135deg, #e65100, #bf360c);"><i class="fas fa-receipt"></i></div>
        Vergi Analizi ve Maliyet Dökümü
    </h3>
    
    <!-- Genel Vergi Özeti -->
    <?php if ($vergi_ozet && $vergi_ozet['ithalat_sayisi'] > 0): ?>
    <div class="stats-summary mb-4" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); padding: 25px; border-radius: 12px; border: 2px solid #ff9800;">
        <h5 style="color: #e65100; margin-bottom: 20px; font-weight: 600;">
            <i class="fas fa-chart-pie"></i> Genel Vergi Özeti
        </h5>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div style="background: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 0.9rem; color: #666; margin-bottom: 8px;">İthalat Sayısı</div>
                <div style="font-size: 2rem; font-weight: bold; color: #2c3e50;"><?php echo $vergi_ozet['ithalat_sayisi']; ?></div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 0.9rem; color: #666; margin-bottom: 8px;">Toplam Gümrük</div>
                <div style="font-size: 2rem; font-weight: bold; color: #4caf50;">₺<?php echo safeNumber($vergi_ozet['genel_gumruk'], 0); ?></div>
                <div style="font-size: 0.8rem; color: #999; margin-top: 5px;">Ort: %<?php echo safeNumber($vergi_ozet['ort_gumruk'], 1); ?></div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 0.9rem; color: #666; margin-bottom: 8px;">Toplam ÖTV</div>
                <div style="font-size: 2rem; font-weight: bold; color: #ff9800;">₺<?php echo safeNumber($vergi_ozet['genel_otv'], 0); ?></div>
                <div style="font-size: 0.8rem; color: #999; margin-top: 5px;">Ort: %<?php echo safeNumber($vergi_ozet['ort_otv'], 1); ?></div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 0.9rem; color: #666; margin-bottom: 8px;">Toplam KDV</div>
                <div style="font-size: 2rem; font-weight: bold; color: #2196f3;">₺<?php echo safeNumber($vergi_ozet['genel_kdv'], 0); ?></div>
                <div style="font-size: 0.8rem; color: #999; margin-top: 5px;">Ort: %<?php echo safeNumber($vergi_ozet['ort_kdv'], 1); ?></div>
            </div>
            <div style="background: linear-gradient(135deg, #e65100, #bf360c); padding: 20px; border-radius: 10px; text-align: center; grid-column: 1 / -1;">
                <div style="font-size: 1rem; color: rgba(255,255,255,0.9); margin-bottom: 8px; font-weight: 600;">TOPLAM VERGİ YÜKÜ</div>
                <div style="font-size: 2.5rem; font-weight: bold; color: white;">₺<?php echo safeNumber($vergi_ozet['genel_toplam_vergi'], 0); ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Detaylı Vergi Tablosu -->
    <?php if (count($vergi_rapor) > 0): ?>
        <div class="table-responsive">
            <table class="table table-detailed table-hover">
                <thead>
                    <tr>
                        <th>Dosya No</th>
                        <th>Tedarikçi</th>
                        <th>Ürün Çeşidi</th>
                        <th>USD Tutar</th>
                        <th>Kur</th>
                        <th>Gümrük</th>
                        <th>ÖTV</th>
                        <th>KDV</th>
                        <th>Toplam Vergi</th>
                        <th>Vergi Dahil</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($vergi_rapor as $vr): ?>
                        <tr onclick="window.location='?page=ithalat-detay&id=<?php echo $vr['id']; ?>'" style="cursor: pointer;">
                            <td><code><?php echo formatBDDosyaNo($vr['balik_dunyasi_dosya_no']); ?></code></td>
                            <td>
                                <strong><?php echo safeHtml($vr['tedarikci_firma']); ?></strong><br>
                                <small class="text-muted"><?php echo safeHtml($vr['tedarikci_ulke']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $vr['urun_cesit_sayisi']; ?> çeşit</span><br>
                                <small><?php echo safeNumber($vr['toplam_kg'], 0); ?> KG</small>
                            </td>
                            <td><strong>$<?php echo safeNumber($vr['toplam_usd'], 0); ?></strong></td>
                            <td>
                                <?php if ($vr['usd_kur']): ?>
                                    <span style="font-family: monospace;">₺<?php echo safeNumber($vr['usd_kur'], 4); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="background: #e8f5e9;">
                                <strong style="color: #2e7d32;">₺<?php echo safeNumber($vr['toplam_gumruk'], 0); ?></strong><br>
                                <small style="color: #666;">Ort: %<?php echo safeNumber($vr['ort_gumruk_oran'], 1); ?></small>
                            </td>
                            <td style="background: #fff3e0;">
                                <strong style="color: #e65100;">₺<?php echo safeNumber($vr['toplam_otv'], 0); ?></strong><br>
                                <small style="color: #666;">Ort: %<?php echo safeNumber($vr['ort_otv_oran'], 1); ?></small>
                            </td>
                            <td style="background: #e3f2fd;">
                                <strong style="color: #1565c0;">₺<?php echo safeNumber($vr['toplam_kdv'], 0); ?></strong><br>
                                <small style="color: #666;">Ort: %<?php echo safeNumber($vr['ort_kdv_oran'], 1); ?></small>
                            </td>
                            <td class="highlight-cell">
                                <strong style="font-size: 1.1rem; color: #d32f2f;">₺<?php echo safeNumber($vr['toplam_vergi'], 0); ?></strong>
                            </td>
                            <td class="highlight-cell">
                                <strong style="font-size: 1.1rem; color: #1b5e20;">₺<?php echo safeNumber($vr['toplam_vergi_dahil'], 0); ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Seçilen tarih aralığında vergi bilgisi olan ithalat bulunamadı.
        </div>
    <?php endif; ?>
</div>

<!-- 9. GTIP KOD BAZLI RAPOR -->
<div class="report-section">
    <h3 class="section-title">
        <div class="section-icon" style="background: linear-gradient(135deg, #2196f3, #1976d2);"><i class="fas fa-barcode"></i></div>
        GTIP Kod Bazlı İthalat Analizi
    </h3>
    
    <?php if (count($gtip_rapor) > 0): ?>
        <div class="table-responsive">
            <table class="table table-detailed table-hover">
                <thead>
                    <tr>
                        <th>GTIP Kodu</th>
                        <th>Açıklama</th>
                        <th>İthalat</th>
                        <th>Ülke</th>
                        <th>Toplam KG</th>
                        <th>Ort. Fiyat</th>
                        <th>Vergi Oranları</th>
                        <th>Toplam Vergi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($gtip_rapor as $gr): ?>
                        <tr>
                            <td>
                                <span style="font-family: 'Courier New', monospace; background: linear-gradient(135deg, #2196f3, #1976d2); color: white; padding: 6px 12px; border-radius: 6px; font-weight: 600; display: inline-block;">
                                    <?php echo safeHtml($gr['gtip_kodu']); ?>
                                </span>
                            </td>
                            <td><strong><?php echo safeHtml(mb_substr($gr['gtip_aciklama'], 0, 50)); ?></strong></td>
                            <td><?php echo $gr['ithalat_sayisi']; ?></td>
                            <td><span class="badge bg-secondary"><?php echo $gr['ulke_sayisi']; ?></span></td>
                            <td class="highlight-cell"><?php echo safeNumber($gr['toplam_kg'], 0); ?> KG</td>
                            <td><strong>$<?php echo safeNumber($gr['ort_birim_fiyat']); ?></strong></td>
                            <td>
                                <?php if ($gr['ort_gumruk_oran'] > 0): ?>
                                    <span style="background: #4caf50; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; margin-right: 4px;">
                                        G: <?php echo safeNumber($gr['ort_gumruk_oran'], 1); ?>%
                                    </span>
                                <?php endif; ?>
                                <?php if ($gr['ort_otv_oran'] > 0): ?>
                                    <span style="background: #ff9800; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; margin-right: 4px;">
                                        Ö: <?php echo safeNumber($gr['ort_otv_oran'], 1); ?>%
                                    </span>
                                <?php endif; ?>
                                <span style="background: #2196f3; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;">
                                    K: <?php echo safeNumber($gr['ort_kdv_oran'], 1); ?>%
                                </span>
                            </td>
                            <td class="highlight-cell">
                                <strong style="color: #d32f2f;">₺<?php echo safeNumber($gr['toplam_vergi'], 0); ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Seçilen tarih aralığında GTIP kodlu ithalat bulunamadı.
        </div>
    <?php endif; ?>
</div>

<!-- 10. VERGİ TÜRÜ BAZLI ANALİZ (YENİ) -->
<div class="report-section">
    <h3 class="section-title">
        <div class="section-icon" style="background: linear-gradient(135deg, #9c27b0, #7b1fa2);"><i class="fas fa-layer-group"></i></div>
        Vergi Türü Bazlı Analiz
    </h3>
    
    <?php if (count($vergi_turu_rapor) > 0): ?>
        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle"></i> Bu analiz, ürünlerin hangi vergi kombinasyonlarına tabi olduğunu gösterir.
        </div>
        
        <div class="table-responsive">
            <table class="table table-detailed table-hover">
                <thead>
                    <tr>
                        <th style="width: 200px;">Vergi Türü</th>
                        <th>İthalat</th>
                        <th>Ürün</th>
                        <th>Toplam KG</th>
                        <th>USD Tutar</th>
                        <th>Gümrük Ort.</th>
                        <th>ÖTV Ort.</th>
                        <th>KDV Ort.</th>
                        <th>Toplam Gümrük</th>
                        <th>Toplam ÖTV</th>
                        <th>Toplam KDV</th>
                        <th>Toplam Vergi</th>
                        <th>Efektif Oran</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($vergi_turu_rapor as $vtr): ?>
                        <tr>
                            <td>
                                <?php
                                $badge_color = 'bg-secondary';
                                $icon = 'fa-receipt';
                                if (strpos($vtr['vergi_turu'], 'Gümrük + ÖTV + KDV') !== false) {
                                    $badge_color = 'bg-danger';
                                    $icon = 'fa-layer-group';
                                } elseif (strpos($vtr['vergi_turu'], 'Gümrük + KDV') !== false) {
                                    $badge_color = 'bg-warning';
                                    $icon = 'fa-coins';
                                } elseif (strpos($vtr['vergi_turu'], 'ÖTV + KDV') !== false) {
                                    $badge_color = 'bg-info';
                                    $icon = 'fa-file-invoice-dollar';
                                } else {
                                    $badge_color = 'bg-success';
                                    $icon = 'fa-file-invoice';
                                }
                                ?>
                                <span class="badge <?php echo $badge_color; ?>" style="font-size: 0.9rem; padding: 8px 12px;">
                                    <i class="fas <?php echo $icon; ?>"></i> <?php echo safeHtml($vtr['vergi_turu']); ?>
                                </span>
                            </td>
                            <td><strong><?php echo $vtr['ithalat_sayisi']; ?></strong></td>
                            <td><span class="badge bg-secondary"><?php echo $vtr['urun_sayisi']; ?></span></td>
                            <td><?php echo safeNumber($vtr['toplam_kg'], 0); ?> KG</td>
                            <td><strong>$<?php echo safeNumber($vtr['toplam_usd'], 0); ?></strong></td>
                            <td>
                                <?php if ($vtr['ort_gumruk_oran'] > 0): ?>
                                    <span style="color: #4caf50; font-weight: 600;">%<?php echo safeNumber($vtr['ort_gumruk_oran'], 1); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($vtr['ort_otv_oran'] > 0): ?>
                                    <span style="color: #ff9800; font-weight: 600;">%<?php echo safeNumber($vtr['ort_otv_oran'], 1); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><span style="color: #2196f3; font-weight: 600;">%<?php echo safeNumber($vtr['ort_kdv_oran'], 1); ?></span></td>
                            <td style="background: #e8f5e9;">
                                <strong style="color: #2e7d32;">₺<?php echo safeNumber($vtr['toplam_gumruk'], 0); ?></strong>
                            </td>
                            <td style="background: #fff3e0;">
                                <strong style="color: #e65100;">₺<?php echo safeNumber($vtr['toplam_otv'], 0); ?></strong>
                            </td>
                            <td style="background: #e3f2fd;">
                                <strong style="color: #1565c0;">₺<?php echo safeNumber($vtr['toplam_kdv'], 0); ?></strong>
                            </td>
                            <td class="highlight-cell">
                                <strong style="font-size: 1.1rem; color: #d32f2f;">₺<?php echo safeNumber($vtr['toplam_vergi'], 0); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-dark" style="font-size: 0.9rem; padding: 6px 10px;">
                                    %<?php echo safeNumber($vtr['efektif_vergi_orani'], 1); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot style="background: #f8f9fa; font-weight: bold;">
                    <tr>
                        <td>GENEL TOPLAM</td>
                        <td><?php echo array_sum(array_column($vergi_turu_rapor, 'ithalat_sayisi')); ?></td>
                        <td><?php echo array_sum(array_column($vergi_turu_rapor, 'urun_sayisi')); ?></td>
                        <td><?php echo safeNumber(array_sum(array_column($vergi_turu_rapor, 'toplam_kg')), 0); ?> KG</td>
                        <td>$<?php echo safeNumber(array_sum(array_column($vergi_turu_rapor, 'toplam_usd')), 0); ?></td>
                        <td colspan="3" class="text-center">-</td>
                        <td style="background: #c8e6c9;">₺<?php echo safeNumber(array_sum(array_column($vergi_turu_rapor, 'toplam_gumruk')), 0); ?></td>
                        <td style="background: #ffe0b2;">₺<?php echo safeNumber(array_sum(array_column($vergi_turu_rapor, 'toplam_otv')), 0); ?></td>
                        <td style="background: #bbdefb;">₺<?php echo safeNumber(array_sum(array_column($vergi_turu_rapor, 'toplam_kdv')), 0); ?></td>
                        <td style="background: #ffcdd2;">₺<?php echo safeNumber(array_sum(array_column($vergi_turu_rapor, 'toplam_vergi')), 0); ?></td>
                        <td>-</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Pasta Grafiği -->
        <div class="chart-container-detail mt-4">
            <canvas id="vergiTuruChart"></canvas>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Seçilen tarih aralığında vergi türü analizi bulunamadı.
        </div>
    <?php endif; ?>
</div>
<!-- ============================================
     EKLENMESİ GEREKEN YER: 
     raporlar.php dosyasında "10. VERGİ TÜRÜ BAZLI ANALİZ" bölümünden
     sonra, son </script> etiketinden ÖNCE eklenecek
     ============================================ -->

<!-- ============================================
     11. ÖDEME BİLGİLERİ RAPORU
     ============================================ -->

<?php
// Ödeme Analizi Sorgusu
$sql_odeme_rapor = "SELECT 
    i.id,
    i.balik_dunyasi_dosya_no,
    i.tedarikci_firma,
    i.tedarikci_ulke,
    i.siparis_tarihi,
    
    -- Kur bilgileri
    o.usd_kur,
    o.kur_tarihi,
    o.kur_notu,
    
    -- Ödeme bilgileri
    o.on_odeme_orani,
    o.avans_1_tutari,
    o.avans_1_tarihi,
    o.avans_1_kur,
    o.avans_2_tutari,
    o.avans_2_tarihi,
    o.avans_2_kur,
    o.final_odeme_tutari,
    o.final_odeme_tarihi,
    o.final_odeme_kur,
    o.komisyon_firma,
    o.komisyon_tutari,
    
    -- Ürün toplamı
    SUM(iu.toplam_tutar) as toplam_usd,
    
    -- Hesaplamalar
    (o.avans_1_tutari + COALESCE(o.avans_2_tutari, 0)) as toplam_avans,
    (o.avans_1_tutari + COALESCE(o.avans_2_tutari, 0) + COALESCE(o.final_odeme_tutari, 0)) as toplam_odenen,
    (SUM(iu.toplam_tutar) - (o.avans_1_tutari + COALESCE(o.avans_2_tutari, 0) + COALESCE(o.final_odeme_tutari, 0))) as kalan_borc,
    
    -- Kur farkı hesaplama
    CASE 
        WHEN o.avans_1_tutari > 0 AND o.avans_1_kur > 0 THEN 
            (o.avans_1_tutari * o.usd_kur) - (o.avans_1_tutari * o.avans_1_kur)
        ELSE 0 
    END as avans_1_kur_farki,
    
    CASE 
        WHEN o.avans_2_tutari > 0 AND o.avans_2_kur > 0 THEN 
            (o.avans_2_tutari * o.usd_kur) - (o.avans_2_tutari * o.avans_2_kur)
        ELSE 0 
    END as avans_2_kur_farki,
    
    CASE 
        WHEN o.final_odeme_tutari > 0 AND o.final_odeme_kur > 0 THEN 
            (o.final_odeme_tutari * o.usd_kur) - (o.final_odeme_tutari * o.final_odeme_kur)
        ELSE 0 
    END as final_kur_farki
    
FROM ithalat i
LEFT JOIN odemeler o ON i.id = o.ithalat_id
LEFT JOIN ithalat_urunler iu ON i.id = iu.ithalat_id
WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis
AND (o.avans_1_tutari > 0 OR o.komisyon_tutari > 0)
GROUP BY i.id
ORDER BY i.siparis_tarihi DESC
LIMIT 50";

$params_odeme = [
    ':baslangic' => $baslangic_tarihi,
    ':bitis' => $bitis_tarihi
];

$stmt_odeme = $db->prepare($sql_odeme_rapor);
$stmt_odeme->execute($params_odeme);
$odeme_rapor = $stmt_odeme->fetchAll();

// Ödeme özet istatistikleri
$sql_odeme_ozet = "SELECT 
    COUNT(DISTINCT i.id) as ithalat_sayisi,
    SUM(o.avans_1_tutari) as genel_avans_1,
    SUM(o.avans_2_tutari) as genel_avans_2,
    SUM(o.final_odeme_tutari) as genel_final,
    SUM(o.komisyon_tutari) as genel_komisyon,
    SUM(iu.toplam_tutar) as genel_toplam_usd,
    AVG(o.usd_kur) as ortalama_kur,
    COUNT(CASE WHEN o.on_odeme_orani = '10' THEN 1 END) as on_odeme_10,
    COUNT(CASE WHEN o.on_odeme_orani = '20' THEN 1 END) as on_odeme_20,
    COUNT(CASE WHEN o.on_odeme_orani = '30' THEN 1 END) as on_odeme_30
FROM ithalat i
LEFT JOIN odemeler o ON i.id = o.ithalat_id
LEFT JOIN ithalat_urunler iu ON i.id = iu.ithalat_id
WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis
AND o.avans_1_tutari > 0";

$stmt_odeme_ozet = $db->prepare($sql_odeme_ozet);
$stmt_odeme_ozet->execute($params_odeme);
$odeme_ozet = $stmt_odeme_ozet->fetch();

// Kur farkı toplam hesaplama
$toplam_kur_farki = 0;
foreach($odeme_rapor as $or) {
    $toplam_kur_farki += $or['avans_1_kur_farki'] + $or['avans_2_kur_farki'] + $or['final_kur_farki'];
}
?>

<div class="report-section">
    <h3 class="section-title">
        <div class="section-icon" style="background: linear-gradient(135deg, #27ae60, #229954);"><i class="fas fa-hand-holding-usd"></i></div>
        Ödeme Bilgileri ve Kur Analizi
    </h3>
    
    <!-- Ödeme Özeti Kartları -->
    <?php if ($odeme_ozet && $odeme_ozet['ithalat_sayisi'] > 0): ?>
    <div class="stats-summary mb-4" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); padding: 25px; border-radius: 12px; border: 2px solid #27ae60;">
        <h5 style="color: #1b5e20; margin-bottom: 20px; font-weight: 600;">
            <i class="fas fa-chart-pie"></i> Genel Ödeme Özeti
        </h5>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px;">
            <div style="background: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 0.9rem; color: #666; margin-bottom: 8px;">İthalat Sayısı</div>
                <div style="font-size: 2rem; font-weight: bold; color: #2c3e50;"><?php echo $odeme_ozet['ithalat_sayisi']; ?></div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 0.9rem; color: #666; margin-bottom: 8px;">Toplam 1. Avans</div>
                <div style="font-size: 2rem; font-weight: bold; color: #27ae60;">$<?php echo safeNumber($odeme_ozet['genel_avans_1'], 0); ?></div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 0.9rem; color: #666; margin-bottom: 8px;">Toplam 2. Avans</div>
                <div style="font-size: 2rem; font-weight: bold; color: #16a085;">$<?php echo safeNumber($odeme_ozet['genel_avans_2'], 0); ?></div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 0.9rem; color: #666; margin-bottom: 8px;">Toplam Final Ödeme</div>
                <div style="font-size: 2rem; font-weight: bold; color: #2980b9;">$<?php echo safeNumber($odeme_ozet['genel_final'], 0); ?></div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 0.9rem; color: #666; margin-bottom: 8px;">Toplam Komisyon</div>
                <div style="font-size: 2rem; font-weight: bold; color: #e67e22;">$<?php echo safeNumber($odeme_ozet['genel_komisyon'], 0); ?></div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 10px; text-align: center;">
                <div style="font-size: 0.9rem; color: #666; margin-bottom: 8px;">Ortalama Kur</div>
                <div style="font-size: 2rem; font-weight: bold; color: #8e44ad;">₺<?php echo safeNumber($odeme_ozet['ortalama_kur'], 4); ?></div>
            </div>
            <div style="background: linear-gradient(135deg, #f39c12, #e67e22); padding: 20px; border-radius: 10px; text-align: center; grid-column: 1 / -1;">
                <div style="font-size: 1rem; color: rgba(255,255,255,0.9); margin-bottom: 8px; font-weight: 600;">TOPLAM KUR FARKI</div>
                <div style="font-size: 2.5rem; font-weight: bold; color: white;">
                    <?php 
                    $kur_farki_color = $toplam_kur_farki >= 0 ? '#27ae60' : '#e74c3c';
                    $kur_farki_icon = $toplam_kur_farki >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                    ?>
                    <i class="fas <?php echo $kur_farki_icon; ?>"></i> 
                    ₺<?php echo safeNumber(abs($toplam_kur_farki), 0); ?>
                    <?php if ($toplam_kur_farki >= 0): ?>
                        <small style="font-size: 0.5em;">(Lehimize)</small>
                    <?php else: ?>
                        <small style="font-size: 0.5em;">(Aleyhimize)</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Ön Ödeme Oranı Dağılımı -->
        <div style="margin-top: 25px; padding: 20px; background: white; border-radius: 10px;">
            <h6 style="color: #2c3e50; margin-bottom: 15px; font-weight: 600;">
                <i class="fas fa-percentage"></i> Ön Ödeme Oranı Dağılımı
            </h6>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 150px; text-align: center; padding: 15px; background: #e8f5e9; border-radius: 8px;">
                    <div style="font-size: 1.5rem; font-weight: bold; color: #27ae60;"><?php echo $odeme_ozet['on_odeme_10']; ?></div>
                    <div style="font-size: 0.85rem; color: #666;">%10 Ön Ödeme</div>
                </div>
                <div style="flex: 1; min-width: 150px; text-align: center; padding: 15px; background: #fff3e0; border-radius: 8px;">
                    <div style="font-size: 1.5rem; font-weight: bold; color: #f39c12;"><?php echo $odeme_ozet['on_odeme_20']; ?></div>
                    <div style="font-size: 0.85rem; color: #666;">%20 Ön Ödeme</div>
                </div>
                <div style="flex: 1; min-width: 150px; text-align: center; padding: 15px; background: #fce4ec; border-radius: 8px;">
                    <div style="font-size: 1.5rem; font-weight: bold; color: #e74c3c;"><?php echo $odeme_ozet['on_odeme_30']; ?></div>
                    <div style="font-size: 0.85rem; color: #666;">%30 Ön Ödeme</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Detaylı Ödeme Tablosu -->
    <?php if (count($odeme_rapor) > 0): ?>
        <div class="table-responsive">
            <table class="table table-detailed table-hover">
                <thead>
                    <tr>
                        <th>Dosya No</th>
                        <th>Tedarikçi</th>
                        <th>Sipariş</th>
                        <th>Toplam USD</th>
                        <th>Ana Kur</th>
                        <th>Ön Ödeme</th>
                        <th>1. Avans</th>
                        <th>2. Avans</th>
                        <th>Final Ödeme</th>
                        <th>Komisyon</th>
                        <th>Kalan Borç</th>
                        <th>Kur Farkı</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($odeme_rapor as $or): ?>
                        <tr onclick="window.location='?page=ithalat-detay&id=<?php echo $or['id']; ?>'" style="cursor: pointer;">
                            <td>
                                <code style="font-weight: 600;"><?php echo formatBDDosyaNo($or['balik_dunyasi_dosya_no']); ?></code>
                            </td>
                            <td>
                                <strong><?php echo safeHtml($or['tedarikci_firma']); ?></strong><br>
                                <small class="text-muted"><?php echo safeHtml($or['tedarikci_ulke']); ?></small>
                            </td>
                            <td>
                                <small><?php echo formatTarih($or['siparis_tarihi']); ?></small>
                            </td>
                            <td>
                                <strong>$<?php echo safeNumber($or['toplam_usd'], 0); ?></strong>
                            </td>
                            <td>
                                <span style="font-family: monospace; font-weight: 600; color: #8e44ad;">
                                    ₺<?php echo safeNumber($or['usd_kur'], 4); ?>
                                </span><br>
                                <small class="text-muted"><?php echo formatTarih($or['kur_tarihi']); ?></small>
                            </td>
                            <td>
                                <?php if ($or['on_odeme_orani']): ?>
                                    <span class="badge bg-info" style="font-size: 0.9rem;">
                                        %<?php echo $or['on_odeme_orani']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="background: #e8f5e9;">
                                <?php if ($or['avans_1_tutari'] > 0): ?>
                                    <strong style="color: #27ae60;">$<?php echo safeNumber($or['avans_1_tutari'], 0); ?></strong><br>
                                    <small style="color: #666;">
                                        <?php echo formatTarih($or['avans_1_tarihi']); ?><br>
                                        Kur: ₺<?php echo safeNumber($or['avans_1_kur'], 4); ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="background: #fff3e0;">
                                <?php if ($or['avans_2_tutari'] > 0): ?>
                                    <strong style="color: #f39c12;">$<?php echo safeNumber($or['avans_2_tutari'], 0); ?></strong><br>
                                    <small style="color: #666;">
                                        <?php echo formatTarih($or['avans_2_tarihi']); ?><br>
                                        Kur: ₺<?php echo safeNumber($or['avans_2_kur'], 4); ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="background: #e3f2fd;">
                                <?php if ($or['final_odeme_tutari'] > 0): ?>
                                    <strong style="color: #2980b9;">$<?php echo safeNumber($or['final_odeme_tutari'], 0); ?></strong><br>
                                    <small style="color: #666;">
                                        <?php echo formatTarih($or['final_odeme_tarihi']); ?><br>
                                        Kur: ₺<?php echo safeNumber($or['final_odeme_kur'], 4); ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($or['komisyon_tutari'] > 0): ?>
                                    <strong style="color: #e67e22;">$<?php echo safeNumber($or['komisyon_tutari'], 0); ?></strong><br>
                                    <small style="color: #666;"><?php echo safeHtml($or['komisyon_firma']); ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                               <td>
                                <?php 
                                $kalan = $or['kalan_borc'] ?? 0;
                                if (abs($kalan) < 0.01): ?>
                                    <span class="badge bg-success">Tamamlandı</span>
                                <?php elseif ($kalan > 0): ?>
                                    <strong style="color: #e74c3c;">$<?php echo safeNumber($kalan, 0); ?></strong>
                                <?php else: ?>
                                    <strong style="color: #27ae60;">+$<?php echo safeNumber(abs($kalan), 0); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $kur_farki = $or['avans_1_kur_farki'] + $or['avans_2_kur_farki'] + $or['final_kur_farki'];
                                if (abs($kur_farki) < 0.01): ?>
                                    <span class="text-muted">-</span>
                                <?php else: ?>
                                    <strong style="color: <?php echo $kur_farki >= 0 ? '#27ae60' : '#e74c3c'; ?>">
                                        <?php echo $kur_farki >= 0 ? '+' : ''; ?>₺<?php echo safeNumber($kur_farki, 0); ?>
                                    </strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo $kur_farki >= 0 ? 'Lehimize' : 'Aleyhimize'; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot style="background: #f8f9fa; font-weight: bold;">
                    <tr>
                        <td colspan="3">TOPLAM</td>
                        <td>$<?php echo safeNumber(array_sum(array_column($odeme_rapor, 'toplam_usd')), 0); ?></td>
                        <td>-</td>
                        <td>-</td>
                        <td style="background: #c8e6c9;">$<?php echo safeNumber(array_sum(array_column($odeme_rapor, 'avans_1_tutari')), 0); ?></td>
                        <td style="background: #ffe0b2;">$<?php echo safeNumber(array_sum(array_column($odeme_rapor, 'avans_2_tutari')), 0); ?></td>
                        <td style="background: #bbdefb;">$<?php echo safeNumber(array_sum(array_column($odeme_rapor, 'final_odeme_tutari')), 0); ?></td>
                        <td style="background: #ffccbc;">$<?php echo safeNumber(array_sum(array_column($odeme_rapor, 'komisyon_tutari')), 0); ?></td>
                        <td>$<?php echo safeNumber(array_sum(array_column($odeme_rapor, 'kalan_borc')), 0); ?></td>
                        <td style="color: <?php echo $toplam_kur_farki >= 0 ? '#27ae60' : '#e74c3c'; ?>;">
                            <?php echo $toplam_kur_farki >= 0 ? '+' : ''; ?>₺<?php echo safeNumber($toplam_kur_farki, 0); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Kur Farkı Açıklama -->
        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle"></i> 
            <strong>Kur Farkı Nedir?</strong> Ana kur ile ödeme yapıldığı andaki kur arasındaki fark. 
            Pozitif değer lehimize, negatif değer aleyhimize kur farkı anlamına gelir.
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Seçilen tarih aralığında ödeme bilgisi olan ithalat bulunamadı.
        </div>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Aylık Trend Grafiği
<?php if (count($aylik_rapor) > 0): ?>
const aylikCtx = document.getElementById('aylikChart').getContext('2d');
const aylikChart = new Chart(aylikCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($aylik_rapor, 'ay')); ?>,
        datasets: [{
            label: 'Toplam KG',
            data: <?php echo json_encode(array_column($aylik_rapor, 'toplam_kg')); ?>,
            backgroundColor: 'rgba(102, 126, 234, 0.7)',
            borderColor: 'rgba(102, 126, 234, 1)',
            borderWidth: 2,
            yAxisID: 'y'
        }, {
            label: 'Toplam Tutar ($)',
            data: <?php echo json_encode(array_column($aylik_rapor, 'toplam_tutar')); ?>,
            type: 'line',
            borderColor: 'rgba(118, 75, 162, 1)',
            backgroundColor: 'rgba(118, 75, 162, 0.2)',
            borderWidth: 3,
            tension: 0.4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Toplam KG'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Toplam Tutar ($)'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});
<?php endif; ?>

// Günlük Trend Grafiği
<?php if (count($gunluk_rapor) > 0): ?>
const gunlukCtx = document.getElementById('gunlukChart').getContext('2d');
const gunlukChart = new Chart(gunlukCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($gunluk_rapor, 'gun')); ?>,
        datasets: [{
            label: 'Günlük İthalat',
            data: <?php echo json_encode(array_column($gunluk_rapor, 'ithalat_sayisi')); ?>,
            borderColor: 'rgba(102, 126, 234, 1)',
            backgroundColor: 'rgba(102, 126, 234, 0.2)',
            borderWidth: 3,
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
<?php endif; ?>

// Vergi Türü Pasta Grafiği
<?php if (count($vergi_turu_rapor) > 0): ?>
const vergiTuruCtx = document.getElementById('vergiTuruChart').getContext('2d');
const vergiTuruChart = new Chart(vergiTuruCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($vergi_turu_rapor, 'vergi_turu')); ?>,
        datasets: [{
            label: 'Toplam Vergi (₺)',
            data: <?php echo json_encode(array_column($vergi_turu_rapor, 'toplam_vergi')); ?>,
            backgroundColor: [
                'rgba(244, 67, 54, 0.8)',
                'rgba(255, 152, 0, 0.8)',
                'rgba(33, 150, 243, 0.8)',
                'rgba(76, 175, 80, 0.8)'
            ],
            borderColor: [
                'rgba(244, 67, 54, 1)',
                'rgba(255, 152, 0, 1)',
                'rgba(33, 150, 243, 1)',
                'rgba(76, 175, 80, 1)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            title: {
                display: true,
                text: 'Vergi Türlerine Göre Dağılım'
            }
        }
    }
});
<?php endif; ?>

// Excel Export Fonksiyonu
function exportToExcel() {
    showToast('Excel export özelliği yakında eklenecek!', 'info');
}

console.log('✅ Raporlar yüklendi:', {
    ulke: <?php echo count($ulke_rapor); ?>,
    urun: <?php echo count($urun_rapor); ?>,
    firma: <?php echo count($firma_rapor); ?>,
    maliyet: <?php echo count($maliyet_rapor); ?>,
    aylik: <?php echo count($aylik_rapor); ?>,
    gunluk: <?php echo count($gunluk_rapor); ?>,
    vergi: <?php echo count($vergi_rapor); ?>,
    gtip: <?php echo count($gtip_rapor); ?>,
    vergiTuru: <?php echo count($vergi_turu_rapor); ?>
});
</script>