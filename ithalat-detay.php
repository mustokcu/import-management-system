<?php
/**
 * İthalat Detay Sayfası - TAM SÜRÜM
 * ✅ Tüm eksik alanlar eklendi
 * ✅ GTIP, Kontrol Belgesi, Anlaşmalar
 * ✅ Vergi hesaplamaları düzeltildi
 * ✅ Kur çevirimi eklendi
 * ✅ Firma renkleri dinamik
 */

// Helper: Renk koyulaştırma fonksiyonu
function adjustBrightness($hex, $steps) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));
    
    return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) 
               . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) 
               . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
}

$db = getDB();

// URL'den ID al
$ithalat_id = $_GET['id'] ?? null;

if (!$ithalat_id) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> İthalat ID belirtilmedi!</div>';
    exit;
}

// ✅ İthalat verilerini çek (TÜM ALANLAR + FİRMA BİLGİSİ)
$sql = "SELECT 
    i.*,
    i.id as ithalat_id,
    i.notlar as ithalat_notlar,
    i.gtip_kodu as ithalat_gtip,
    i.gtip_tipi,
    i.ithal_kontrol_belgesi_16,
    i.tarim_bakanlik_onay_tarihi,
    i.kontrol_belgesi_suresi,
    i.tek_fabrika,
    i.depozito_anlasmasi,
    i.komisyon_anlasmasi,
    u.*,
    o.*,
    o.usd_kur,
    o.kur_tarihi,
    o.kur_notu,
    g.*,
    s.*,
    f.firma_adi as ithalatci_firma_adi,
    f.firma_kodu as ithalatci_firma_kodu,
    f.dosya_no_prefix,
    f.renk_kodu as firma_renk
FROM ithalat i
LEFT JOIN urun_detaylari u ON i.id = u.ithalat_id
LEFT JOIN odemeler o ON i.id = o.ithalat_id
LEFT JOIN giderler g ON i.id = g.ithalat_id
LEFT JOIN sevkiyat s ON i.id = s.ithalat_id
LEFT JOIN ithalatci_firmalar f ON i.ithalatci_firma_id = f.id
WHERE i.id = :id";

$stmt = $db->prepare($sql);
$stmt->execute([':id' => $ithalat_id]);
$ithalat = $stmt->fetch();

if (!$ithalat) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> İthalat kaydı bulunamadı!</div>';
    exit;
}

// ✅ ÇOKLU ÜRÜNLERİ VERGİ BİLGİLERİYLE ÇEK
$sql_urunler = "SELECT 
    iu.*,
    uk.urun_latince_isim,
    uk.urun_cinsi,
    uk.urun_tipi,
    uk.kalibre,
    uk.glz_orani,
    uk.kalite_terimi
FROM ithalat_urunler iu
LEFT JOIN urun_katalog uk ON iu.urun_katalog_id = uk.id
WHERE iu.ithalat_id = :ithalat_id
ORDER BY iu.id";

$stmt_urunler = $db->prepare($sql_urunler);
$stmt_urunler->execute([':ithalat_id' => $ithalat_id]);
$urun_listesi = $stmt_urunler->fetchAll();

// ✅ KUR BİLGİSİ
$usd_kur = floatval($ithalat['usd_kur'] ?? 0);

// ✅ VERGİ TOPLAMLARINI HESAPLA (TL CİNSİNDEN)
$toplam_kg = 0;
$toplam_urun_tutari_usd = 0;
$toplam_urun_tutari_tl = 0;
$toplam_gumruk = 0;
$toplam_otv = 0;
$toplam_kdv = 0;
$toplam_vergi_genel = 0;
$toplam_vergi_dahil = 0;

if (count($urun_listesi) > 0) {
    // Yeni sistem - Çoklu ürün
    foreach($urun_listesi as $urun) {
        $urun_tutar_usd = floatval($urun['toplam_tutar']);
        $urun_tutar_tl = $usd_kur > 0 ? $urun_tutar_usd * $usd_kur : 0;
        
        $toplam_kg += floatval($urun['miktar_kg']);
        $toplam_urun_tutari_usd += $urun_tutar_usd;
        $toplam_urun_tutari_tl += $urun_tutar_tl;
        $toplam_gumruk += floatval($urun['gumruk_vergisi_tutar'] ?? 0);
        $toplam_otv += floatval($urun['otv_tutar'] ?? 0);
        $toplam_kdv += floatval($urun['kdv_tutar'] ?? 0);
        $toplam_vergi_genel += floatval($urun['toplam_vergi'] ?? 0);
        $toplam_vergi_dahil += floatval($urun['vergi_dahil_tutar'] ?? 0);
    }
    
    $toplam_siparis_usd = $toplam_urun_tutari_usd;
    $toplam_siparis_tl = $toplam_urun_tutari_tl;
} else {
    // Eski sistem - Fallback
    $toplam_kg = floatval($ithalat['toplam_siparis_kg'] ?? 0);
    $toplam_siparis_usd = $toplam_kg * floatval($ithalat['ilk_alis_fiyati'] ?? 0);
    $toplam_siparis_tl = $usd_kur > 0 ? $toplam_siparis_usd * $usd_kur : 0;
}

$toplam_gider = floatval($ithalat['toplam_gider'] ?? 0);
$genel_toplam_tl = $toplam_siparis_tl + $toplam_gider + $toplam_vergi_genel;
$kg_basi_maliyet_tl = $toplam_kg > 0 ? $genel_toplam_tl / $toplam_kg : 0;

// ✅ Konşimento Kontrolü
$konsimento_eksikler = [];
if (empty($ithalat['yukleme_tarihi'])) $konsimento_eksikler[] = 'Yükleme Tarihi';
if (empty($ithalat['tahmini_varis_tarihi'])) $konsimento_eksikler[] = 'Tahmini Varış Tarihi';
if (empty($ithalat['yukleme_limani'])) $konsimento_eksikler[] = 'Yükleme Limanı';
if (empty($ithalat['bosaltma_limani'])) $konsimento_eksikler[] = 'Boşaltma Limanı';
if (empty($ithalat['konteyner_numarasi'])) $konsimento_eksikler[] = 'Konteyner Numarası';
if (empty($ithalat['gemi_adi'])) $konsimento_eksikler[] = 'Gemi Adı';

$konsimento_tamam = count($konsimento_eksikler) == 0;

// ✅ GTIP Kontrolü
$gtip_eksik = empty($ithalat['ithalat_gtip']) && empty($ithalat['gtip_tipi']);

// ✅ Kontrol Belgesi Kontrolü
$belge_eksikler = [];
if (empty($ithalat['ithal_kontrol_belgesi_16'])) $belge_eksikler[] = 'İthal Kontrol Belgesi (Form 16)';
if (empty($ithalat['tarim_bakanlik_onay_tarihi'])) $belge_eksikler[] = 'Tarım Bakanlık Onay Tarihi';
if (empty($ithalat['kontrol_belgesi_suresi'])) $belge_eksikler[] = 'Kontrol Belgesi Süresi';

$belge_tamam = count($belge_eksikler) == 0;

// ✅ EVRAK UYARI KONTROLÜ
$evrak_uyari = false;
$evrak_uyari_mesaj = '';

$varis_tarihi = $ithalat['tr_varis_tarihi'] ?: $ithalat['tahmini_varis_tarihi'];
if ($varis_tarihi) {
    $varis_timestamp = strtotime($varis_tarihi);
    $bugun = strtotime(date('Y-m-d'));
    $kalan_gun = floor(($varis_timestamp - $bugun) / 86400);
    
    if ($kalan_gun <= 7 && $kalan_gun >= 0) {
        $evrak_uyari = true;
        $evrak_uyari_mesaj = "Varış tarihine {$kalan_gun} gün kaldı! Original evrak ve telex kontrolü yapın.";
    }
}

$original_bekleniyor = ($ithalat['original_evrak_durumu'] ?? 'bekleniyor') == 'bekleniyor';
$telex_bekleniyor = ($ithalat['telex_durumu'] ?? 'bekleniyor') == 'bekleniyor';

if ($original_bekleniyor || $telex_bekleniyor) {
    $evrak_uyari = true;
    $eksik_evraklar = [];
    if ($original_bekleniyor) $eksik_evraklar[] = 'Original Evrak';
    if ($telex_bekleniyor) $eksik_evraklar[] = 'Telex';
    $evrak_uyari_mesaj .= ' Eksik evraklar: ' . implode(', ', $eksik_evraklar);
}
?>

<style>
    .detail-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }
    
    .detail-header h2 {
        margin: 0;
        font-size: 2rem;
    }
    
    .detail-header .meta {
        margin-top: 10px;
        opacity: 0.95;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .info-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        border-left: 4px solid #3498db;
        transition: all 0.3s ease;
    }
    
    .info-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }
    
    .info-card.warning {
        border-left-color: #f39c12;
    }
    
    .info-card.success {
        border-left-color: #27ae60;
    }
    
    .info-card.danger {
        border-left-color: #e74c3c;
    }
    
    .info-card-title {
        font-size: 0.9rem;
        color: #7f8c8d;
        font-weight: 600;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .info-card-value {
        font-size: 1.4rem;
        font-weight: bold;
        color: #2c3e50;
    }
    
    .section-box {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .section-header {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 3px solid #3498db;
    }
    
    .section-icon {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin-right: 15px;
        box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
    }
    
    .section-title {
        font-size: 1.3rem;
        font-weight: 600;
        color: #2c3e50;
        margin: 0;
    }
    
    .detail-table {
        width: 100%;
        margin-top: 15px;
    }
    
    .detail-table tr {
        border-bottom: 1px solid #ecf0f1;
    }
    
    .detail-table tr:last-child {
        border-bottom: none;
    }
    
    .detail-table td {
        padding: 12px 10px;
    }
    
    .detail-table td:first-child {
        font-weight: 600;
        color: #7f8c8d;
        width: 40%;
    }
    
    .detail-table td:last-child {
        color: #2c3e50;
    }
    
    .action-toolbar {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .btn-group-custom {
        display: flex;
        gap: 10px;
    }
    
    .cost-summary {
        background: linear-gradient(135deg, #f39c12, #e67e22);
        color: white;
        padding: 25px;
        border-radius: 12px;
        margin-top: 30px;
    }
    
    .cost-summary h4 {
        margin: 0 0 15px 0;
        font-size: 1.3rem;
    }
    
    .cost-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid rgba(255,255,255,0.2);
    }
    
    .cost-item:last-child {
        border-bottom: none;
        font-size: 1.2rem;
        font-weight: bold;
        padding-top: 15px;
        margin-top: 10px;
        border-top: 2px solid rgba(255,255,255,0.3);
    }
    
    .warning-box {
        background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
        border: 2px solid #ffc107;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3);
    }
    
    .warning-box h4 {
        color: #856404;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .warning-box ul {
        margin: 15px 0;
        padding-left: 20px;
    }
    
    .warning-box li {
        color: #856404;
        margin-bottom: 8px;
    }
    
    .danger-box {
        background: linear-gradient(135deg, #ffe6e6 0%, #ffcccc 100%);
        border: 2px solid #dc3545;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
    }
    
    .danger-box h4 {
        color: #721c24;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .success-box {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        border: 2px solid #28a745;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
    }
    
    .success-box h4 {
        color: #155724;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .urun-liste-container {
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        border: 2px solid #3b82f6;
        border-radius: 12px;
        padding: 25px;
        margin: 20px 0;
    }
    
    .urun-liste-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #3b82f6;
    }
    
    .urun-liste-header h4 {
        color: #1e40af;
        margin: 0;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .urun-card {
        background: white;
        border: 2px solid #e5e7eb;
        border-left: 5px solid #3b82f6;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .urun-card:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 15px rgba(59, 130, 246, 0.2);
        border-left-color: #2563eb;
    }
    
    .urun-card:nth-child(2n) {
        border-left-color: #8b5cf6;
    }
    
    .urun-card:nth-child(3n) {
        border-left-color: #ec4899;
    }
    
    .urun-card:nth-child(4n) {
        border-left-color: #10b981;
    }
    
    .urun-number {
        position: absolute;
        top: -12px;
        left: 20px;
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: white;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1rem;
        box-shadow: 0 4px 10px rgba(59, 130, 246, 0.4);
    }
    
    .urun-card-header {
        margin-bottom: 15px;
        padding-left: 25px;
    }
    
    .urun-card-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 5px;
    }
    
    .urun-card-subtitle {
        font-size: 0.9rem;
        color: #64748b;
        font-style: italic;
    }
    
    .urun-card-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        background: #f8fafc;
        padding: 15px;
        border-radius: 8px;
    }
    
    .urun-detail-item {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .urun-detail-label {
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .urun-detail-value {
        font-size: 1.1rem;
        font-weight: bold;
        color: #1e293b;
    }
    
    .urun-detail-value.highlight {
        color: #059669;
        font-size: 1.3rem;
    }
    
    .vergi-section {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border: 2px solid #f59e0b;
        border-radius: 12px;
        padding: 20px;
        margin-top: 15px;
    }
    
    .vergi-section-header {
        font-size: 1rem;
        font-weight: 600;
        color: #92400e;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .vergi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
    }
    
    .vergi-item {
        background: white;
        padding: 12px;
        border-radius: 8px;
        border-left: 4px solid #f59e0b;
    }
    
    .vergi-item-label {
        font-size: 0.8rem;
        color: #78716c;
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .vergi-item-value {
        font-size: 1.1rem;
        font-weight: bold;
        color: #1c1917;
    }
    
    .vergi-item-percentage {
        font-size: 0.85rem;
        color: #78716c;
        margin-top: 3px;
    }
    
    .vergi-toplam {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: white;
        padding: 15px;
        border-radius: 8px;
        margin-top: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .vergi-toplam-label {
        font-size: 1rem;
        font-weight: 600;
    }
    
    .vergi-toplam-value {
        font-size: 1.5rem;
        font-weight: bold;
    }
    
    .kur-bilgi {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 12px;
        margin-top: 10px;
        font-size: 0.9rem;
        color: #475569;
    }
    
    .kur-bilgi strong {
        color: #1e293b;
    }
    
    .urun-toplam-box {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
        padding: 20px;
        border-radius: 10px;
        margin-top: 20px;
        box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
    }
    
    .urun-toplam-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 20px;
        text-align: center;
    }
    
    .urun-toplam-item {
        padding: 10px;
    }
    
    .urun-toplam-item label {
        font-size: 0.9rem;
        opacity: 0.9;
        display: block;
        margin-bottom: 8px;
    }
    
    .urun-toplam-item .value {
        font-size: 1.8rem;
        font-weight: bold;
    }
    
    .badge-kalibre {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: white;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-block;
        margin-left: 10px;
    }
    
    .no-urun-message {
        text-align: center;
        padding: 40px;
        color: #64748b;
        background: #f8fafc;
        border-radius: 8px;
        border: 2px dashed #cbd5e1;
    }
    
    .no-urun-message i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    
    .gtip-badge {
        background: linear-gradient(135deg, #06b6d4, #0891b2);
        color: white;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        display: inline-block;
    }
    
    @media (max-width: 768px) {
        .urun-card-details {
            grid-template-columns: 1fr;
        }
        
        .urun-toplam-grid {
            grid-template-columns: 1fr;
        }
        
        .vergi-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="detail-header" style="background: linear-gradient(135deg, <?php echo $ithalat['firma_renk'] ?? '#667eea'; ?> 0%, <?php echo adjustBrightness($ithalat['firma_renk'] ?? '#667eea', -20); ?> 100%);">
    <h2>
        <i class="fas fa-file-invoice"></i> 
        <?php echo formatBDDosyaNo($ithalat['balik_dunyasi_dosya_no']); ?>
    </h2>
    <div class="meta">
        <span><i class="fas fa-building"></i> <?php echo safeHtml($ithalat['ithalatci_firma_adi'] ?? 'Firma Bilgisi Yok'); ?></span>
        <span class="ms-3"><i class="fas fa-user-tie"></i> <?php echo safeHtml($ithalat['tedarikci_firma'] ?? '-'); ?></span>
        <span class="ms-3"><i class="fas fa-calendar"></i> <?php echo formatTarih($ithalat['siparis_tarihi'] ?? null); ?></span>
        <span class="ms-3"><?php echo getDurumBadge($ithalat['ithalat_durumu'] ?? 'siparis_verildi'); ?></span>
    </div>
</div>

<!-- Action Toolbar -->
<div class="action-toolbar">
    <div class="btn-group-custom">
        <a href="?page=ithalat-takip" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Listeye Dön
        </a>
        <a href="?page=ithalat-duzenle&id=<?php echo $ithalat['ithalat_id']; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i> Düzenle
        </a>
    </div>
    <div class="btn-group-custom">
        <a href="api/export-pdf.php?id=<?php echo $ithalat['ithalat_id']; ?>" target="_blank" class="btn btn-success">
    <i class="fas fa-file-pdf"></i> PDF İndir
</a>
        <a href="api/export-single-csv.php?id=<?php echo $ithalat['ithalat_id']; ?>" class="btn btn-info">
            <i class="fas fa-file-excel"></i> CSV İndir
        </a>
        <button class="btn btn-danger" onclick="ithalatSil(<?php echo $ithalat['ithalat_id']; ?>)">
            <i class="fas fa-trash"></i> Sil
        </button>
    </div>
</div>

<!-- ✅ EVRAK UYARI -->
<?php if ($evrak_uyari): ?>
<div class="danger-box">
    <h4>
        <i class="fas fa-exclamation-triangle fa-2x"></i>
        DİKKAT: Evrak Kontrol Uyarısı!
    </h4>
    <p><strong><?php echo $evrak_uyari_mesaj; ?></strong></p>
    <div class="mt-3">
        <a href="#evrak-section" class="btn btn-danger">
            <i class="fas fa-file-alt"></i> Evrak Durumunu Kontrol Et
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ✅ KONŞİMENTO UYARI -->
<?php if (!$konsimento_tamam): ?>
<div class="warning-box">
    <h4>
        <i class="fas fa-exclamation-triangle fa-2x"></i>
        DİKKAT: Konşimento Bilgileri Eksik!
    </h4>
    <p><strong>Eksik Bilgiler:</strong></p>
    <ul>
        <?php foreach($konsimento_eksikler as $eksik): ?>
            <li><i class="fas fa-times-circle"></i> <?php echo $eksik; ?></li>
        <?php endforeach; ?>
    </ul>
    <div class="mt-3">
        <a href="?page=ithalat-duzenle&id=<?php echo $ithalat['ithalat_id']; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i> Şimdi Düzenle
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ✅ GTIP UYARI -->
<?php if ($gtip_eksik): ?>
<div class="warning-box">
    <h4>
        <i class="fas fa-exclamation-circle fa-2x"></i>
        DİKKAT: GTIP Bilgileri Eksik!
    </h4>
    <p><strong>GTIP kodu ve tipi gümrük işlemleri için zorunludur!</strong></p>
    <div class="mt-3">
        <a href="?page=ithalat-duzenle&id=<?php echo $ithalat['ithalat_id']; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i> GTIP Ekle
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ✅ KONTROL BELGESİ UYARI -->
<?php if (!$belge_tamam): ?>
<div class="warning-box">
    <h4>
        <i class="fas fa-file-medical fa-2x"></i>
        DİKKAT: Kontrol Belgesi Bilgileri Eksik!
    </h4>
    <p><strong>Eksik Belgeler:</strong></p>
    <ul>
        <?php foreach($belge_eksikler as $eksik): ?>
            <li><i class="fas fa-times-circle"></i> <?php echo $eksik; ?></li>
        <?php endforeach; ?>
    </ul>
    <div class="mt-3">
        <a href="?page=ithalat-duzenle&id=<?php echo $ithalat['ithalat_id']; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i> Belge Bilgilerini Ekle
        </a>
    </div>
</div>
<?php endif; ?>

<!-- ✅ ÖZET KARTLAR (DÜZELTİLMİŞ - TL CİNSİNDEN) -->
<div class="info-grid">
    <div class="info-card">
        <div class="info-card-title"><i class="fas fa-weight"></i> Toplam Miktar</div>
        <div class="info-card-value"><?php echo safeNumber($toplam_kg); ?> KG</div>
        <?php if (count($urun_listesi) > 1): ?>
            <small class="text-muted mt-1 d-block"><?php echo count($urun_listesi); ?> farklı ürün</small>
        <?php endif; ?>
    </div>
    
    <div class="info-card warning">
        <div class="info-card-title"><i class="fas fa-dollar-sign"></i> Ürün Tutarı (USD)</div>
        <div class="info-card-value">$<?php echo safeNumber($toplam_siparis_usd); ?></div>
        <small class="text-muted mt-1 d-block">≈ ₺<?php echo safeNumber($toplam_siparis_tl); ?></small>
    </div>
    
    <div class="info-card success">
        <div class="info-card-title"><i class="fas fa-receipt"></i> Toplam Vergi</div>
        <div class="info-card-value">₺<?php echo safeNumber($toplam_vergi_genel); ?></div>
    </div>
    
    <div class="info-card danger">
        <div class="info-card-title"><i class="fas fa-chart-line"></i> KG Başı Maliyet</div>
        <div class="info-card-value">₺<?php echo safeNumber($kg_basi_maliyet_tl); ?></div>
        <small class="text-muted mt-1 d-block">(Ürün + Gider + Vergi)</small>
    </div>
</div>

<!-- ✅ 1. GTIP VE KONTROL BELGESİ BİLGİLERİ -->
<div class="section-box">
    <div class="section-header">
        <div class="section-icon"><i class="fas fa-barcode"></i></div>
        <h3 class="section-title">GTIP ve Kontrol Belgesi Bilgileri</h3>
    </div>
    <table class="detail-table">
        <tr>
            <td><i class="fas fa-barcode"></i> GTIP Kodu</td>
            <td>
                <?php if (!empty($ithalat['ithalat_gtip'])): ?>
                    <span class="gtip-badge"><?php echo safeHtml($ithalat['ithalat_gtip']); ?></span>
                <?php else: ?>
                    <span class="text-danger"><i class="fas fa-times-circle"></i> Girilmedi</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td><i class="fas fa-tag"></i> GTIP Tipi</td>
            <td><?php echo safeHtml($ithalat['gtip_tipi'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td><i class="fas fa-file-medical"></i> İthal Kontrol Belgesi (Form 16)</td>
            <td>
                <?php if (!empty($ithalat['ithal_kontrol_belgesi_16'])): ?>
                    <span class="badge bg-success"><?php echo safeHtml($ithalat['ithal_kontrol_belgesi_16']); ?></span>
                <?php else: ?>
                    <span class="text-danger"><i class="fas fa-times-circle"></i> Girilmedi</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td><i class="fas fa-calendar-check"></i> Tarım Bakanlık Onay Tarihi</td>
            <td>
                <?php 
                if (!empty($ithalat['tarim_bakanlik_onay_tarihi'])) {
                    echo formatTarih($ithalat['tarim_bakanlik_onay_tarihi']);
                } else {
                    echo '<span class="text-danger"><i class="fas fa-times-circle"></i> Girilmedi</span>';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td><i class="fas fa-clock"></i> Kontrol Belgesi Süresi</td>
            <td><?php echo safeHtml($ithalat['kontrol_belgesi_suresi'] ?? '-'); ?></td>
        </tr>
    </table>
</div>

<!-- ✅ 2. TEDARİKÇİ BİLGİLERİ -->
<div class="section-box">
    <div class="section-header">
        <div class="section-icon"><i class="fas fa-building"></i></div>
        <h3 class="section-title">Tedarikçi Bilgileri</h3>
    </div>
    <table class="detail-table">
        <tr>
            <td>Tedarikçi Firma</td>
            <td><strong><?php echo safeHtml($ithalat['tedarikci_firma'] ?? '-'); ?></strong></td>
        </tr>
        <tr>
    <td>Tedarikçi Sipariş No</td>
    <td>
        <?php if (!empty($ithalat['tedarikci_siparis_no'])): ?>
            <span style="font-family: 'Courier New', monospace; background: #e3f2fd; color: #1565c0; padding: 4px 10px; border-radius: 6px; font-weight: 600;">
                <i class="fas fa-receipt"></i> <?php echo safeHtml($ithalat['tedarikci_siparis_no']); ?>
            </span>
        <?php else: ?>
            <span class="text-muted">-</span>
        <?php endif; ?>
    </td>
</tr>
        <tr>
            <td>Tedarikçi Ülke</td>
            <td><?php echo safeHtml($ithalat['tedarikci_ulke'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td>Menşei Ülke</td>
            <td><?php echo safeHtml($ithalat['mensei_ulke'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td>Transit Detay</td>
            <td><?php echo safeHtml($ithalat['transit_detay'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td><i class="fas fa-industry"></i> Tek Fabrika</td>
            <td>
                <?php 
                if ($ithalat['tek_fabrika'] == 'evet') {
                    echo '<span class="badge bg-success">Evet</span>';
                } elseif ($ithalat['tek_fabrika'] == 'hayir') {
                    echo '<span class="badge bg-secondary">Hayır</span>';
                } else {
                    echo '-';
                }
                ?>
            </td>
        </tr>
    </table>
</div>

<!-- ✅ 3. ANLAŞMALAR -->
<div class="section-box">
    <div class="section-header">
        <div class="section-icon"><i class="fas fa-handshake"></i></div>
        <h3 class="section-title">Anlaşmalar ve Komisyon</h3>
    </div>
    <table class="detail-table">
        <tr>
            <td><i class="fas fa-piggy-bank"></i> Depozito Anlaşması</td>
            <td>
                <?php 
                if ($ithalat['depozito_anlasmasi'] == 'var') {
                    echo '<span class="badge bg-success">Var</span>';
                } elseif ($ithalat['depozito_anlasmasi'] == 'yok') {
                    echo '<span class="badge bg-secondary">Yok</span>';
                } else {
                    echo '-';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td><i class="fas fa-percent"></i> Komisyon Anlaşması</td>
            <td>
                <?php 
                if ($ithalat['komisyon_anlasmasi'] == 'var') {
                    echo '<span class="badge bg-success">Var</span>';
                } elseif ($ithalat['komisyon_anlasmasi'] == 'yok') {
                    echo '<span class="badge bg-secondary">Yok</span>';
                } else {
                    echo '-';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td>Komisyon Firma</td>
            <td><?php echo safeHtml($ithalat['komisyon_firma'] ?? '-'); ?></td>
        </tr>
        <tr>
            <td>Komisyon Tutarı</td>
            <td>
                <?php 
                if (!empty($ithalat['komisyon_tutari'])) {
                    echo '$' . safeNumber($ithalat['komisyon_tutari']);
                } else {
                    echo '-';
                }
                ?>
            </td>
        </tr>
    </table>
</div>

<!-- ✅ 4. ÜRÜN LİSTESİ (VERGİ BİLGİLERİ DAHİL) -->
<div class="section-box">
    <div class="section-header">
        <div class="section-icon"><i class="fas fa-fish"></i></div>
        <h3 class="section-title">Ürün Listesi ve Vergi Bilgileri</h3>
    </div>
    
    <?php if (count($urun_listesi) > 0): ?>
        <div class="urun-liste-container">
            <div class="urun-liste-header">
                <h4><i class="fas fa-list-ol"></i> Sipariş Edilen Ürünler</h4>
                <span class="badge bg-primary" style="font-size: 1rem; padding: 8px 15px;">
                    <?php echo count($urun_listesi); ?> Çeşit
                </span>
            </div>
            
            <?php foreach($urun_listesi as $index => $urun): 
                $urun_tutar_usd = floatval($urun['toplam_tutar']);
                $urun_tutar_tl = $usd_kur > 0 ? $urun_tutar_usd * $usd_kur : 0;
            ?>
                <div class="urun-card">
                    <div class="urun-number"><?php echo $index + 1; ?></div>
                    
                    <div class="urun-card-header">
                        <div class="urun-card-title">
                            <?php echo safeHtml($urun['urun_cinsi']); ?>
                            <?php if ($urun['kalibre']): ?>
                                <span class="badge-kalibre"><?php echo safeHtml($urun['kalibre']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="urun-card-subtitle">
                            <i class="fas fa-flask"></i> <?php echo safeHtml($urun['urun_latince_isim']); ?>
                        </div>
                        <?php if (!empty($urun['gtip_kodu'])): ?>
                            <div class="mt-2">
                                <span class="gtip-badge">GTIP: <?php echo safeHtml($urun['gtip_kodu']); ?></span>
                                <?php if (!empty($urun['gtip_aciklama'])): ?>
                                    <small class="text-muted ms-2"><?php echo safeHtml($urun['gtip_aciklama']); ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="urun-card-details">
                        <div class="urun-detail-item">
                            <div class="urun-detail-label">
                                <i class="fas fa-weight"></i> Miktar
                            </div>
                            <div class="urun-detail-value">
                                <?php echo safeNumber($urun['miktar_kg']); ?> KG
                            </div>
                        </div>
                        
                        <div class="urun-detail-item">
                            <div class="urun-detail-label">
                                <i class="fas fa-dollar-sign"></i> Birim Fiyat
                            </div>
                            <div class="urun-detail-value">
                                $<?php echo safeNumber($urun['birim_fiyat']); ?>/KG
                            </div>
                        </div>
                        
                        <div class="urun-detail-item">
                            <div class="urun-detail-label">
                                <i class="fas fa-calculator"></i> Toplam (USD)
                            </div>
                            <div class="urun-detail-value highlight">
                                $<?php echo safeNumber($urun_tutar_usd); ?>
                            </div>
                        </div>
                        
                        <div class="urun-detail-item">
                            <div class="urun-detail-label">
                                <i class="fas fa-lira-sign"></i> Toplam (TL)
                            </div>
                            <div class="urun-detail-value highlight">
                                ₺<?php echo safeNumber($urun_tutar_tl); ?>
                            </div>
                        </div>
                        
                        <?php if ($urun['glz_orani']): ?>
                        <div class="urun-detail-item">
                            <div class="urun-detail-label">
                                <i class="fas fa-snowflake"></i> GLZ Oranı
                            </div>
                            <div class="urun-detail-value">
                                <?php echo $urun['glz_orani']; ?>%
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($urun['kalite_terimi']): ?>
                        <div class="urun-detail-item">
                            <div class="urun-detail-label">
                                <i class="fas fa-award"></i> Kalite
                            </div>
                            <div class="urun-detail-value">
                                <?php echo $urun['kalite_terimi']; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- ✅ VERGİ BİLGİLERİ -->
                    <?php if (isset($urun['gumruk_vergisi_oran']) || isset($urun['otv_oran']) || isset($urun['kdv_oran'])): ?>
                    <div class="vergi-section">
                        <div class="vergi-section-header">
                            <i class="fas fa-percentage"></i>
                            Vergi Hesaplamaları (TL)
                        </div>
                        
                        <div class="vergi-grid">
                            <?php if (isset($urun['gumruk_vergisi_oran'])): ?>
                            <div class="vergi-item">
                                <div class="vergi-item-label">Gümrük Vergisi</div>
                                <div class="vergi-item-value">
                                    ₺<?php echo safeNumber($urun['gumruk_vergisi_tutar'] ?? 0); ?>
                                </div>
                                <div class="vergi-item-percentage">
                                    (%<?php echo $urun['gumruk_vergisi_oran']; ?>)
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($urun['otv_oran'])): ?>
                            <div class="vergi-item">
                                <div class="vergi-item-label">ÖTV</div>
                                <div class="vergi-item-value">
                                    ₺<?php echo safeNumber($urun['otv_tutar'] ?? 0); ?>
                                </div>
                                <div class="vergi-item-percentage">
                                    (%<?php echo $urun['otv_oran']; ?>)
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($urun['kdv_oran'])): ?>
                            <div class="vergi-item">
                                <div class="vergi-item-label">KDV</div>
                                <div class="vergi-item-value">
                                    ₺<?php echo safeNumber($urun['kdv_tutar'] ?? 0); ?>
                                </div>
                                <div class="vergi-item-percentage">
                                    (%<?php echo $urun['kdv_oran']; ?>)
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="vergi-toplam">
                            <div class="vergi-toplam-label">
                                <i class="fas fa-receipt"></i> Toplam Vergi
                            </div>
                            <div class="vergi-toplam-value">
                                ₺<?php echo safeNumber($urun['toplam_vergi'] ?? 0); ?>
                            </div>
                        </div>
                        
                        <div class="vergi-toplam" style="background: linear-gradient(135deg, #16a34a, #15803d); margin-top: 10px;">
                            <div class="vergi-toplam-label">
                                <i class="fas fa-coins"></i> Vergi Dahil Tutar
                            </div>
                            <div class="vergi-toplam-value">
                                ₺<?php echo safeNumber($urun['vergi_dahil_tutar'] ?? 0); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <!-- ✅ TOPLAM ÖZET -->
            <div class="urun-toplam-box">
                <div class="urun-toplam-grid">
                    <div class="urun-toplam-item">
                        <label><i class="fas fa-boxes"></i> Ürün Çeşidi</label>
                        <div class="value"><?php echo count($urun_listesi); ?></div>
                    </div>
                    <div class="urun-toplam-item">
                        <label><i class="fas fa-weight"></i> Toplam Miktar</label>
                        <div class="value"><?php echo safeNumber($toplam_kg); ?> KG</div>
                    </div>
                    <div class="urun-toplam-item">
                        <label><i class="fas fa-dollar-sign"></i> Ürün Tutarı (USD)</label>
                        <div class="value">$<?php echo safeNumber($toplam_urun_tutari_usd); ?></div>
                    </div>
                    <div class="urun-toplam-item">
                        <label><i class="fas fa-lira-sign"></i> Ürün Tutarı (TL)</label>
                        <div class="value">₺<?php echo safeNumber($toplam_urun_tutari_tl); ?></div>
                    </div>
                    <div class="urun-toplam-item">
                        <label><i class="fas fa-percent"></i> Toplam Gümrük</label>
                        <div class="value">₺<?php echo safeNumber($toplam_gumruk); ?></div>
                    </div>
                    <div class="urun-toplam-item">
                        <label><i class="fas fa-percent"></i> Toplam ÖTV</label>
                        <div class="value">₺<?php echo safeNumber($toplam_otv); ?></div>
                    </div>
                    <div class="urun-toplam-item">
                        <label><i class="fas fa-percent"></i> Toplam KDV</label>
                        <div class="value">₺<?php echo safeNumber($toplam_kdv); ?></div>
                    </div>
                    <div class="urun-toplam-item">
                        <label><i class="fas fa-receipt"></i> Toplam Vergi</label>
                        <div class="value">₺<?php echo safeNumber($toplam_vergi_genel); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- ✅ KUR BİLGİSİ -->
            <?php if ($ithalat['usd_kur']): ?>
            <div class="kur-bilgi">
                <i class="fas fa-exchange-alt"></i>
                <strong>Döviz Kuru Bilgisi:</strong> 
                1 USD = <strong><?php echo safeNumber($ithalat['usd_kur']); ?> ₺</strong>
                <?php if ($ithalat['kur_tarihi']): ?>
                    (<?php echo formatTarih($ithalat['kur_tarihi']); ?>)
                <?php endif; ?>
                <?php if ($ithalat['kur_notu']): ?>
                    <br><small><i class="fas fa-info-circle"></i> <?php echo safeHtml($ithalat['kur_notu']); ?></small>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Eski sistem için fallback -->
        <div class="no-urun-message">
            <i class="fas fa-info-circle"></i>
            <p class="mb-2"><strong>Bu ithalat eski sistemde kaydedilmiş</strong></p>
            <p class="mb-0">Ürün detayları aşağıdaki genel bilgilerde görüntülenmektedir.</p>
        </div>
        
        <table class="detail-table mt-3">
            <tr>
                <td>Ürün Latince İsim</td>
                <td><strong><?php echo safeHtml($ithalat['urun_latince_isim'] ?? '-'); ?></strong></td>
            </tr>
            <tr>
                <td>Ürün Cinsi</td>
                <td><?php echo safeHtml($ithalat['urun_cinsi'] ?? '-'); ?></td>
            </tr>
            <tr>
                <td>Ürün Tipi</td>
                <td><?php echo safeHtml($ithalat['urun_tipi'] ?? '-'); ?></td>
            </tr>
            <tr>
                <td>Toplam Sipariş</td>
                <td><strong><?php echo safeNumber($ithalat['toplam_siparis_kg'] ?? 0); ?> KG</strong></td>
            </tr>
            <?php if (!empty($ithalat['kalibrasyon_detay'])): ?>
            <tr>
                <td>Kalibre Detayları</td>
                <td><?php echo nl2br(safeHtml($ithalat['kalibrasyon_detay'])); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    <?php endif; ?>
</div>

<!-- ✅ MALİYET ÖZETİ (DÜZELTİLMİŞ - TL CİNSİNDEN) -->
<div class="cost-summary">
    <h4><i class="fas fa-calculator"></i> Maliyet Özeti (₺ - Türk Lirası)</h4>
    <div class="cost-item">
        <span>Toplam Sipariş Değeri (USD → TL):</span>
        <span>₺<?php echo safeNumber($toplam_siparis_tl); ?></span>
    </div>
    <div class="cost-item">
        <span>Toplam Giderler:</span>
        <span>₺<?php echo safeNumber($toplam_gider); ?></span>
    </div>
    <div class="cost-item">
        <span>Toplam Vergiler (Gümrük + ÖTV + KDV):</span>
        <span>₺<?php echo safeNumber($toplam_vergi_genel); ?></span>
    </div>
    <div class="cost-item">
        <span>KG Başı Maliyet:</span>
        <span>₺<?php echo safeNumber($kg_basi_maliyet_tl); ?>/KG</span>
    </div>
    <div class="cost-item">
        <span>GENEL TOPLAM MALİYET:</span>
        <span>₺<?php echo safeNumber($genel_toplam_tl); ?></span>
    </div>
</div>

<!-- ✅ NOTLAR -->
<?php if (!empty($ithalat['ithalat_notlar'])): ?>
<div class="section-box">
    <div class="section-header">
        <div class="section-icon"><i class="fas fa-sticky-note"></i></div>
        <h3 class="section-title">Notlar</h3>
    </div>
    <div class="alert alert-info mb-0">
        <i class="fas fa-info-circle"></i>
        <?php echo nl2br(safeHtml($ithalat['ithalat_notlar'])); ?>
    </div>
</div>
<?php endif; ?>

<script>
console.log('✅ İthalat Detay - TAM Analiz:');
console.log('=================================');
console.log('Firma:', '<?php echo $ithalat['ithalatci_firma_adi'] ?? 'YOK'; ?>');
console.log('Firma Kodu:', '<?php echo $ithalat['ithalatci_firma_kodu'] ?? 'YOK'; ?>');
console.log('Dosya No:', '<?php echo $ithalat['balik_dunyasi_dosya_no']; ?>');
console.log('Tedarikçi:', '<?php echo $ithalat['tedarikci_firma'] ?? 'YOK'; ?>');
console.log('GTIP:', '<?php echo $ithalat['ithalat_gtip'] ?? 'YOK'; ?>');
console.log('Kontrol Belgesi:', '<?php echo $ithalat['ithal_kontrol_belgesi_16'] ?? 'YOK'; ?>');
console.log('Depozito:', '<?php echo $ithalat['depozito_anlasmasi'] ?? 'YOK'; ?>');
console.log('Komisyon:', '<?php echo $ithalat['komisyon_anlasmasi'] ?? 'YOK'; ?>');
console.log('USD Kur:', <?php echo $usd_kur; ?>);
console.log('Toplam KG:', <?php echo $toplam_kg; ?>);
console.log('Toplam USD:', <?php echo $toplam_siparis_usd; ?>);
console.log('Toplam TL:', <?php echo $toplam_siparis_tl; ?>);
console.log('Toplam Vergi TL:', <?php echo $toplam_vergi_genel; ?>);
console.log('Genel Toplam TL:', <?php echo $genel_toplam_tl; ?>);
console.log('Ürün Sayısı:', <?php echo count($urun_listesi); ?>);
console.log('=================================');
</script>