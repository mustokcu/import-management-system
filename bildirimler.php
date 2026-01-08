<?php
/**
 * Bildirim Merkezi - GELİŞMİŞ VERSİYON
 * Otomatik ithalat kontrolleri + Manuel bildirimler
 */

$db = getDB();

// Filtre
$filtre = $_GET['filtre'] ?? 'tumu'; // tumu, kritik, evrak, gtip, konsimento, belge

// ============================================
// 1. OTOMATIK İTHALAT KONTROL SİSTEMİ
// ============================================

$otomatik_bildirimler = [];

// Tüm aktif ithalatları çek
$sql_ithalat = "SELECT 
    i.*,
    s.yukleme_tarihi,
    s.tahmini_varis_tarihi,
    s.tr_varis_tarihi,
    s.yukleme_limani,
    s.bosaltma_limani,
    s.konteyner_numarasi,
    s.gemi_adi
FROM ithalat i
LEFT JOIN sevkiyat s ON i.id = s.ithalat_id
WHERE i.ithalat_durumu NOT IN ('teslim_edildi', 'tamamlandi', 'iptal')
ORDER BY i.siparis_tarihi DESC";

$stmt_ithalat = $db->query($sql_ithalat);
$ithalatlar = $stmt_ithalat->fetchAll();

foreach($ithalatlar as $ithalat) {
    $ithalat_id = $ithalat['id'];
    $dosya_no = $ithalat['balik_dunyasi_dosya_no'];
    
    // === 1. EVRAK KONTROLÜ ===
    $evrak_sorunlari = [];
    
    $original_bekleniyor = ($ithalat['original_evrak_durumu'] ?? 'bekleniyor') == 'bekleniyor';
    $telex_bekleniyor = ($ithalat['telex_durumu'] ?? 'bekleniyor') == 'bekleniyor';
    
    if ($original_bekleniyor) $evrak_sorunlari[] = 'Original Evrak';
    if ($telex_bekleniyor) $evrak_sorunlari[] = 'Telex';
    
    // Varış tarihine yaklaşma kontrolü
    $varis_tarihi = $ithalat['tr_varis_tarihi'] ?: $ithalat['tahmini_varis_tarihi'];
    if ($varis_tarihi) {
        $varis_timestamp = strtotime($varis_tarihi);
        $bugun = strtotime(date('Y-m-d'));
        $kalan_gun = floor(($varis_timestamp - $bugun) / 86400);
        
        if ($kalan_gun <= 7 && $kalan_gun >= 0 && count($evrak_sorunlari) > 0) {
            $otomatik_bildirimler[] = [
                'ithalat_id' => $ithalat_id,
                'tip' => 'evrak',
                'oncelik' => $kalan_gun <= 3 ? 'kritik' : 'yuksek',
                'baslik' => "KRİTİK: Evrak Eksikliği - {$dosya_no}",
                'mesaj' => "Varışa {$kalan_gun} gün kaldı! Eksik evraklar: " . implode(', ', $evrak_sorunlari),
                'tarih' => date('Y-m-d H:i:s')
            ];
        } elseif (count($evrak_sorunlari) > 0) {
            $otomatik_bildirimler[] = [
                'ithalat_id' => $ithalat_id,
                'tip' => 'evrak',
                'oncelik' => 'orta',
                'baslik' => "Evrak Eksikliği - {$dosya_no}",
                'mesaj' => "Eksik evraklar: " . implode(', ', $evrak_sorunlari),
                'tarih' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    // === 2. KONŞİMENTO KONTROLÜ ===
    $konsimento_eksikler = [];
    if (empty($ithalat['yukleme_tarihi'])) $konsimento_eksikler[] = 'Yükleme Tarihi';
    if (empty($ithalat['tahmini_varis_tarihi'])) $konsimento_eksikler[] = 'Tahmini Varış Tarihi';
    if (empty($ithalat['yukleme_limani'])) $konsimento_eksikler[] = 'Yükleme Limanı';
    if (empty($ithalat['bosaltma_limani'])) $konsimento_eksikler[] = 'Boşaltma Limanı';
    if (empty($ithalat['konteyner_numarasi'])) $konsimento_eksikler[] = 'Konteyner Numarası';
    if (empty($ithalat['gemi_adi'])) $konsimento_eksikler[] = 'Gemi Adı';
    
    if (count($konsimento_eksikler) > 0) {
        $otomatik_bildirimler[] = [
            'ithalat_id' => $ithalat_id,
            'tip' => 'konsimento',
            'oncelik' => count($konsimento_eksikler) >= 4 ? 'yuksek' : 'orta',
            'baslik' => "Konşimento Bilgileri Eksik - {$dosya_no}",
            'mesaj' => "Eksik alanlar (" . count($konsimento_eksikler) . "): " . implode(', ', $konsimento_eksikler),
            'tarih' => date('Y-m-d H:i:s')
        ];
    }
    
    // === 3. GTIP KONTROLÜ ===
    $gtip_eksik = empty($ithalat['gtip_kodu']) && empty($ithalat['gtip_tipi']);
    
    if ($gtip_eksik) {
        $otomatik_bildirimler[] = [
            'ithalat_id' => $ithalat_id,
            'tip' => 'gtip',
            'oncelik' => 'yuksek',
            'baslik' => "GTIP Bilgileri Eksik - {$dosya_no}",
            'mesaj' => "GTIP kodu ve tipi gümrük işlemleri için zorunludur!",
            'tarih' => date('Y-m-d H:i:s')
        ];
    }
    
    // === 4. KONTROL BELGESİ KONTROLÜ ===
    $belge_eksikler = [];
    if (empty($ithalat['ithal_kontrol_belgesi_16'])) $belge_eksikler[] = 'İthal Kontrol Belgesi (Form 16)';
    if (empty($ithalat['tarim_bakanlik_onay_tarihi'])) $belge_eksikler[] = 'Tarım Bakanlık Onay Tarihi';
    if (empty($ithalat['kontrol_belgesi_suresi'])) $belge_eksikler[] = 'Kontrol Belgesi Süresi';
    
    if (count($belge_eksikler) > 0) {
        $otomatik_bildirimler[] = [
            'ithalat_id' => $ithalat_id,
            'tip' => 'belge',
            'oncelik' => count($belge_eksikler) >= 2 ? 'yuksek' : 'orta',
            'baslik' => "Kontrol Belgesi Bilgileri Eksik - {$dosya_no}",
            'mesaj' => "Eksik belgeler (" . count($belge_eksikler) . "): " . implode(', ', $belge_eksikler),
            'tarih' => date('Y-m-d H:i:s')
        ];
    }
}

// ============================================
// 2. MANUEL BİLDİRİMLERİ ÇEK
// ============================================

$sql_manuel = "SELECT 
    b.*,
    i.balik_dunyasi_dosya_no,
    i.tedarikci_firma,
    i.ithalat_durumu
FROM bildirimler b
LEFT JOIN ithalat i ON b.ithalat_id = i.id
WHERE 1=1";

$params = [];

if ($filtre == 'okunmamis') {
    $sql_manuel .= " AND b.okundu = 0";
} elseif ($filtre == 'acil') {
    $sql_manuel .= " AND b.oncelik IN ('acil', 'yuksek', 'kritik') AND b.okundu = 0";
}

$sql_manuel .= " ORDER BY 
    CASE b.oncelik 
        WHEN 'kritik' THEN 1
        WHEN 'acil' THEN 2 
        WHEN 'yuksek' THEN 3 
        WHEN 'orta' THEN 4
        WHEN 'normal' THEN 5 
        ELSE 6 
    END,
    b.olusturma_tarihi DESC
LIMIT 100";

$stmt_manuel = $db->prepare($sql_manuel);
$stmt_manuel->execute($params);
$manuel_bildirimler = $stmt_manuel->fetchAll();

// ============================================
// 3. TÜM BİLDİRİMLERİ BİRLEŞTİR VE FİLTRELE
// ============================================

// Otomatik bildirimleri filtrele
if ($filtre == 'kritik') {
    $otomatik_bildirimler = array_filter($otomatik_bildirimler, function($b) {
        return $b['oncelik'] == 'kritik';
    });
} elseif ($filtre == 'evrak') {
    $otomatik_bildirimler = array_filter($otomatik_bildirimler, function($b) {
        return $b['tip'] == 'evrak';
    });
} elseif ($filtre == 'gtip') {
    $otomatik_bildirimler = array_filter($otomatik_bildirimler, function($b) {
        return $b['tip'] == 'gtip';
    });
} elseif ($filtre == 'konsimento') {
    $otomatik_bildirimler = array_filter($otomatik_bildirimler, function($b) {
        return $b['tip'] == 'konsimento';
    });
} elseif ($filtre == 'belge') {
    $otomatik_bildirimler = array_filter($otomatik_bildirimler, function($b) {
        return $b['tip'] == 'belge';
    });
}

// Öncelik sıralaması
usort($otomatik_bildirimler, function($a, $b) {
    $oncelik_degerleri = ['kritik' => 1, 'yuksek' => 2, 'orta' => 3, 'dusuk' => 4];
    $a_deger = $oncelik_degerleri[$a['oncelik']] ?? 5;
    $b_deger = $oncelik_degerleri[$b['oncelik']] ?? 5;
    return $a_deger - $b_deger;
});

// İstatistikleri hesapla
$toplam_kritik = count(array_filter($otomatik_bildirimler, fn($b) => $b['oncelik'] == 'kritik'));
$toplam_evrak = count(array_filter($otomatik_bildirimler, fn($b) => $b['tip'] == 'evrak'));
$toplam_gtip = count(array_filter($otomatik_bildirimler, fn($b) => $b['tip'] == 'gtip'));
$toplam_konsimento = count(array_filter($otomatik_bildirimler, fn($b) => $b['tip'] == 'konsimento'));
$toplam_belge = count(array_filter($otomatik_bildirimler, fn($b) => $b['tip'] == 'belge'));
$toplam_otomatik = count($otomatik_bildirimler);
$toplam_manuel = count($manuel_bildirimler);

// Manuel bildirimleri çek (view için)
$sql_ozet = "SELECT * FROM bildirim_ozetleri";
$stmt_ozet = $db->query($sql_ozet);
$ozet = $stmt_ozet->fetch();
?>

<style>
    .notification-header {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(231, 76, 60, 0.3);
    }
    
    .notification-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }
    
    .stat-card-notif {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        border-left: 4px solid #3498db;
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .stat-card-notif:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }
    
    .stat-card-notif.kritik {
        border-left-color: #e74c3c;
        background: linear-gradient(135deg, #fff5f5, #ffe6e6);
    }
    
    .stat-card-notif.evrak {
        border-left-color: #3498db;
    }
    
    .stat-card-notif.gtip {
        border-left-color: #9b59b6;
    }
    
    .stat-card-notif.konsimento {
        border-left-color: #f39c12;
    }
    
    .stat-card-notif.belge {
        border-left-color: #16a085;
    }
    
    .stat-card-notif.manuel {
        border-left-color: #95a5a6;
    }
    
    .stat-number {
        font-size: 2.5rem;
        font-weight: bold;
        color: #2c3e50;
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: #7f8c8d;
        margin-top: 5px;
    }
    
    .filter-buttons {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .notification-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        border-left: 5px solid #3498db;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .notification-card:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.15);
    }
    
    .notification-card.auto {
        border-left-width: 7px;
    }
    
    .notification-card.kritik {
        border-left-color: #e74c3c;
        background: linear-gradient(135deg, #fff5f5 0%, #ffe6e6 100%);
    }
    
    .notification-card.yuksek {
        border-left-color: #f39c12;
        background: linear-gradient(135deg, #fffbf0 0%, #fff3cd 100%);
    }
    
    .notification-card.evrak {
        border-left-color: #3498db;
    }
    
    .notification-card.gtip {
        border-left-color: #9b59b6;
    }
    
    .notification-card.konsimento {
        border-left-color: #f39c12;
    }
    
    .notification-card.belge {
        border-left-color: #16a085;
    }
    
    .notification-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-right: 15px;
    }
    
    .notification-icon.evrak {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
    }
    
    .notification-icon.gtip {
        background: linear-gradient(135deg, #9b59b6, #8e44ad);
        color: white;
    }
    
    .notification-icon.konsimento {
        background: linear-gradient(135deg, #f39c12, #e67e22);
        color: white;
    }
    
    .notification-icon.belge {
        background: linear-gradient(135deg, #16a085, #138d75);
        color: white;
    }
    
    .notification-icon.kritik {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        color: white;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .notification-content {
        flex: 1;
    }
    
    .notification-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
    }
    
    .notification-message {
        color: #5a6c7d;
        line-height: 1.6;
    }
    
    .notification-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #ecf0f1;
    }
    
    .notification-time {
        font-size: 0.85rem;
        color: #95a5a6;
    }
    
    .priority-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .priority-badge.kritik {
        background: #e74c3c;
        color: white;
        animation: blink 1.5s infinite;
    }
    
    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    .priority-badge.yuksek {
        background: #f39c12;
        color: white;
    }
    
    .priority-badge.orta {
        background: #3498db;
        color: white;
    }
    
    .priority-badge.dusuk {
        background: #95a5a6;
        color: white;
    }
    
    .auto-badge {
        background: linear-gradient(135deg, #27ae60, #229954);
        color: white;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-left: 8px;
    }
    
    .no-notifications {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .no-notifications i {
        font-size: 5rem;
        color: #bdc3c7;
        margin-bottom: 20px;
    }
    
    .section-divider {
        border-top: 3px solid #3498db;
        margin: 30px 0;
        padding-top: 20px;
    }
    
    .section-divider h4 {
        color: #2c3e50;
        font-weight: 600;
        margin-bottom: 20px;
    }
</style>

<div class="notification-header">
    <h2 class="mb-0"><i class="fas fa-bell"></i> Gelişmiş Bildirim Merkezi</h2>
    <p class="mb-0 mt-2">Otomatik kontroller + Manuel bildirimler</p>
</div>

<!-- İstatistikler -->
<div class="notification-stats">
    <div class="stat-card-notif kritik">
        <div class="stat-number"><?php echo $toplam_kritik; ?></div>
        <div class="stat-label"><i class="fas fa-exclamation-triangle"></i> Kritik</div>
    </div>
    
    <div class="stat-card-notif evrak">
        <div class="stat-number"><?php echo $toplam_evrak; ?></div>
        <div class="stat-label"><i class="fas fa-file-invoice"></i> Evrak</div>
    </div>
    
    <div class="stat-card-notif gtip">
        <div class="stat-number"><?php echo $toplam_gtip; ?></div>
        <div class="stat-label"><i class="fas fa-barcode"></i> GTIP</div>
    </div>
    
    <div class="stat-card-notif konsimento">
        <div class="stat-number"><?php echo $toplam_konsimento; ?></div>
        <div class="stat-label"><i class="fas fa-ship"></i> Konşimento</div>
    </div>
    
    <div class="stat-card-notif belge">
        <div class="stat-number"><?php echo $toplam_belge; ?></div>
        <div class="stat-label"><i class="fas fa-file-medical"></i> Belge</div>
    </div>
    
    <div class="stat-card-notif manuel">
        <div class="stat-number"><?php echo $toplam_manuel; ?></div>
        <div class="stat-label"><i class="fas fa-hand-paper"></i> Manuel</div>
    </div>
</div>

<!-- Filtre Butonları -->
<div class="filter-buttons">
    <div class="btn-group" role="group">
        <a href="?page=bildirimler&filtre=tumu" 
           class="btn <?php echo $filtre == 'tumu' ? 'btn-primary' : 'btn-outline-primary'; ?>">
            <i class="fas fa-list"></i> Tümü (<?php echo $toplam_otomatik + $toplam_manuel; ?>)
        </a>
        <a href="?page=bildirimler&filtre=kritik" 
           class="btn <?php echo $filtre == 'kritik' ? 'btn-danger' : 'btn-outline-danger'; ?>">
            <i class="fas fa-exclamation-triangle"></i> Kritik (<?php echo $toplam_kritik; ?>)
        </a>
        <a href="?page=bildirimler&filtre=evrak" 
           class="btn <?php echo $filtre == 'evrak' ? 'btn-info' : 'btn-outline-info'; ?>">
            <i class="fas fa-file-invoice"></i> Evrak (<?php echo $toplam_evrak; ?>)
        </a>
        <a href="?page=bildirimler&filtre=gtip" 
           class="btn <?php echo $filtre == 'gtip' ? 'btn-primary' : 'btn-outline-primary'; ?>">
            <i class="fas fa-barcode"></i> GTIP (<?php echo $toplam_gtip; ?>)
        </a>
        <a href="?page=bildirimler&filtre=konsimento" 
           class="btn <?php echo $filtre == 'konsimento' ? 'btn-warning' : 'btn-outline-warning'; ?>">
            <i class="fas fa-ship"></i> Konşimento (<?php echo $toplam_konsimento; ?>)
        </a>
        <a href="?page=bildirimler&filtre=belge" 
           class="btn <?php echo $filtre == 'belge' ? 'btn-success' : 'btn-outline-success'; ?>">
            <i class="fas fa-file-medical"></i> Belge (<?php echo $toplam_belge; ?>)
        </a>
    </div>
    
    <div class="float-end">
        <button class="btn btn-info" onclick="location.reload()">
            <i class="fas fa-sync"></i> Yenile
        </button>
    </div>
</div>

<!-- OTOMATİK BİLDİRİMLER -->
<?php if (count($otomatik_bildirimler) > 0): ?>
<div class="section-divider">
    <h4><i class="fas fa-robot"></i> Otomatik Kontrol Bildirimleri (<?php echo count($otomatik_bildirimler); ?>)</h4>
</div>

<div class="notifications-list">
    <?php foreach($otomatik_bildirimler as $bildirim): ?>
        <div class="notification-card auto <?php echo $bildirim['oncelik']; ?> <?php echo $bildirim['tip']; ?>">
            <div class="d-flex">
                <!-- İkon -->
                <div class="notification-icon <?php echo $bildirim['tip']; ?> <?php echo $bildirim['oncelik']; ?>">
                    <?php
                    $icons = [
                        'evrak' => 'fa-file-invoice',
                        'gtip' => 'fa-barcode',
                        'konsimento' => 'fa-ship',
                        'belge' => 'fa-file-medical'
                    ];
                    $icon = $icons[$bildirim['tip']] ?? 'fa-exclamation-triangle';
                    ?>
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                
                <!-- İçerik -->
                <div class="notification-content">
                    <div class="notification-title">
                        <?php echo safeHtml($bildirim['baslik']); ?>
                        <span class="auto-badge">OTOMATİK</span>
                    </div>
                    
                    <div class="notification-message">
                        <?php echo safeHtml($bildirim['mesaj']); ?>
                    </div>
                    
                    <div class="notification-footer">
                        <div>
                            <span class="priority-badge <?php echo $bildirim['oncelik']; ?>">
                                <?php echo strtoupper($bildirim['oncelik']); ?>
                            </span>
                            <span class="notification-time ms-2">
                                <i class="fas fa-robot"></i> Sistem Kontrolü
                            </span>
                        </div>
                        
                        <div class="notification-actions">
                            <a href="?page=ithalat-detay&id=<?php echo $bildirim['ithalat_id']; ?>" 
                               class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i> İncele
                            </a>
                            <a href="?page=ithalat-duzenle&id=<?php echo $bildirim['ithalat_id']; ?>" 
                               class="btn btn-sm btn-warning">
                                <i class="fas fa-edit"></i> Düzenle
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- MANUEL BİLDİRİMLER -->
<?php if (count($manuel_bildirimler) > 0): ?>
<div class="section-divider">
    <h4><i class="fas fa-user"></i> Manuel Bildirimler (<?php echo count($manuel_bildirimler); ?>)</h4>
</div>

<div class="notifications-list">
    <?php foreach($manuel_bildirimler as $bildirim): ?>
        <div class="notification-card <?php echo $bildirim['okundu'] ? '' : 'unread'; ?> 
                    <?php echo $bildirim['oncelik']; ?> 
                    <?php echo $bildirim['bildirim_tipi']; ?>"
             id="bildirim-<?php echo $bildirim['id']; ?>">
            
            <div class="d-flex">
                <!-- İkon -->
                <div class="notification-icon <?php echo $bildirim['bildirim_tipi']; ?>">
                    <?php
                    $icons = [
                        'odeme' => 'fa-money-bill-wave',
                        'evrak' => 'fa-file-invoice',
                        'liman' => 'fa-anchor',
                        'uyari' => 'fa-exclamation-triangle',
                        'genel' => 'fa-info-circle'
                    ];
                    $icon = $icons[$bildirim['bildirim_tipi']] ?? 'fa-bell';
                    ?>
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                
                <!-- İçerik -->
                <div class="notification-content">
                    <div class="notification-title">
                        <?php echo safeHtml($bildirim['baslik']); ?>
                        <?php if (!$bildirim['okundu']): ?>
                            <span class="badge bg-primary ms-2">YENİ</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="notification-message">
                        <?php echo safeHtml($bildirim['mesaj']); ?>
                    </div>
                    
                    <div class="notification-footer">
                        <div>
                            <span class="priority-badge <?php echo $bildirim['oncelik']; ?>">
                                <?php echo strtoupper($bildirim['oncelik']); ?>
                            </span>
                            <span class="notification-time ms-2">
                                <i class="fas fa-clock"></i> <?php echo formatTarih($bildirim['olusturma_tarihi'], 'd.m.Y H:i'); ?>
                            </span>
                        </div>
                        
                        <div class="notification-actions">
                            <?php if ($bildirim['ithalat_id']): ?>
                                <a href="?page=ithalat-detay&id=<?php echo $bildirim['ithalat_id']; ?>" 
                                   class="btn btn-sm btn-primary"
                                   onclick="bildirimOkundu(<?php echo $bildirim['id']; ?>)">
                                    <i class="fas fa-eye"></i> İncele
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!$bildirim['okundu']): ?>
                                <button class="btn btn-sm btn-success" 
                                        onclick="bildirimOkundu(<?php echo $bildirim['id']; ?>)">
                                    <i class="fas fa-check"></i> Okundu
                                </button>
                            <?php endif; ?>
                            
                            <button class="btn btn-sm btn-danger" 
                                    onclick="bildirimSil(<?php echo $bildirim['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- HİÇ BİLDİRİM YOK -->
<?php if (count($otomatik_bildirimler) == 0 && count($manuel_bildirimler) == 0): ?>
<div class="no-notifications">
    <i class="fas fa-check-circle" style="color: #27ae60;"></i>
    <h3>Tebrikler!</h3>
    <p>Herhangi bir bildirim bulunmuyor. Tüm ithalatlar kontrol edildi ve sorun yok.</p>
</div>
<?php endif; ?>

<script>
// Bildirimi okundu işaretle
function bildirimOkundu(id) {
    fetch('api/bildirim-okundu.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            const elem = document.getElementById('bildirim-' + id);
            if(elem) {
                elem.classList.remove('unread');
                const badge = elem.querySelector('.badge');
                if(badge) badge.remove();
            }
        }
    });
}

// Bildirimi sil
function bildirimSil(id) {
    if(!confirm('Bu bildirimi silmek istediğinizden emin misiniz?')) return;
    
    fetch('api/bildirim-sil.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            document.getElementById('bildirim-' + id).remove();
            alert('Bildirim silindi');
        }
    });
}

console.log('✅ Bildirim Sistemi Yüklendi:');
console.log('Otomatik Bildirimler:', <?php echo $toplam_otomatik; ?>);
console.log('Manuel Bildirimler:', <?php echo $toplam_manuel; ?>);
console.log('Kritik:', <?php echo $toplam_kritik; ?>);
console.log('Evrak:', <?php echo $toplam_evrak; ?>);
console.log('GTIP:', <?php echo $toplam_gtip; ?>);
console.log('Konşimento:', <?php echo $toplam_konsimento; ?>);
console.log('Belge:', <?php echo $toplam_belge; ?>);
</script>