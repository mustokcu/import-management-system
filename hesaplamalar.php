<?php
/**
 * Otomatik Hesaplamalar Sayfası
 * Maliyet, kur farkı, komisyon kontrolleri ve uyarılar
 * ✅ GÜNCELLEME: Çoklu ürün sistemi desteği eklendi
 * ✅ Evrak uyarı sistemi aktif
 */

$db = getDB();

// Seçili ithalat ID'si (detaylı hesaplama için)
$secili_id = $_GET['id'] ?? null;

// ============================================
// 1. TÜM İTHALATLARIN MALİYET HESAPLAMALARI
// ✅ ÇOKLU ÜRÜN DESTEĞİ
// ============================================
$sql_maliyet = "SELECT 
    i.id,
    i.balik_dunyasi_dosya_no,
    i.tedarikci_firma,
    i.siparis_tarihi,
    i.ithalat_durumu,
    
    -- ✅ ÇOKLU ÜRÜN BİLGİLERİ
    COUNT(DISTINCT iu.id) as urun_cesit_sayisi,
    GROUP_CONCAT(DISTINCT uk.urun_cinsi SEPARATOR ', ') as urun_listesi,
    SUM(iu.miktar_kg) as toplam_kg,
    SUM(iu.toplam_tutar) as toplam_urun_tutari,
    
    -- Ödeme bilgileri
    o.ilk_alis_fiyati,
    o.para_birimi,
    o.tranship_ek_maliyet,
    o.komisyon_tutari,
    o.toplam_fatura_tutari,
    o.avans_1_tutari,
    o.avans_1_kur,
    o.avans_2_tutari,
    o.avans_2_kur,
    o.final_odeme_tutari,
    o.final_odeme_kur,
    
    -- Giderler
    g.toplam_gider,
    g.gumruk_ucreti,
    g.ardiye_ucreti,
    g.demoraj_ucreti,
    
    -- Sevkiyat
    s.tr_varis_tarihi
    
FROM ithalat i
LEFT JOIN ithalat_urunler iu ON i.id = iu.ithalat_id
LEFT JOIN urun_katalog uk ON iu.urun_katalog_id = uk.id
LEFT JOIN odemeler o ON i.id = o.ithalat_id
LEFT JOIN giderler g ON i.id = g.ithalat_id
LEFT JOIN sevkiyat s ON i.id = s.ithalat_id
GROUP BY i.id
ORDER BY i.siparis_tarihi DESC";

$stmt_maliyet = $db->query($sql_maliyet);
$maliyet_listesi = $stmt_maliyet->fetchAll();

// ============================================
// 2. KUR FARKI ANALİZİ (3 Aylık Ortalama)
// ============================================
$sql_kur = "SELECT 
    i.id,
    i.balik_dunyasi_dosya_no,
    i.tedarikci_firma,
    o.avans_1_tutari,
    o.avans_1_kur,
    o.avans_2_tutari,
    o.avans_2_kur,
    o.final_odeme_tutari,
    o.final_odeme_kur,
    o.kur_farki_gelir
FROM ithalat i
LEFT JOIN odemeler o ON i.id = o.ithalat_id
WHERE o.avans_1_kur IS NOT NULL
ORDER BY i.siparis_tarihi DESC
LIMIT 20";

$stmt_kur = $db->query($sql_kur);
$kur_analiz = $stmt_kur->fetchAll();

// ============================================
// 3. ✅ GÜNCELLENMIŞ UYARILAR 
// (Ödeme, Ardiye, Demoraj + EVRAK)
// ============================================
$bugun = date('Y-m-d');
$uyari_gun = 15; // Ödeme için 15 gün önceden uyarı

$sql_uyarilar = "SELECT 
    i.id,
    i.balik_dunyasi_dosya_no,
    i.tedarikci_firma,
    i.ithalat_durumu,
    
    -- ✅ ÇOKLU ÜRÜN
    COUNT(DISTINCT iu.id) as urun_cesit_sayisi,
    GROUP_CONCAT(DISTINCT uk.urun_cinsi SEPARATOR ', ') as urun_listesi,
    
    -- Ödeme
    o.final_odeme_tarihi,
    DATEDIFF(o.final_odeme_tarihi, CURDATE()) as kalan_gun,
    
    -- Giderler
    g.ardiye_ucreti,
    g.demoraj_ucreti,
    
    -- Sevkiyat & Evrak
    s.tr_varis_tarihi,
    s.tahmini_varis_tarihi,
    s.original_evrak_durumu,
    s.telex_durumu,
    DATEDIFF(CURDATE(), s.tr_varis_tarihi) as liman_bekleme_gun,
    DATEDIFF(COALESCE(s.tr_varis_tarihi, s.tahmini_varis_tarihi), CURDATE()) as varis_kalan_gun
    
FROM ithalat i
LEFT JOIN ithalat_urunler iu ON i.id = iu.ithalat_id
LEFT JOIN urun_katalog uk ON iu.urun_katalog_id = uk.id
LEFT JOIN odemeler o ON i.id = o.ithalat_id
LEFT JOIN giderler g ON i.id = g.ithalat_id
LEFT JOIN sevkiyat s ON i.id = s.ithalat_id
WHERE (
    -- Ödeme uyarısı
    (o.final_odeme_tarihi IS NOT NULL AND DATEDIFF(o.final_odeme_tarihi, CURDATE()) <= 15 AND DATEDIFF(o.final_odeme_tarihi, CURDATE()) >= 0)
    -- Liman bekleme uyarısı
    OR (i.ithalat_durumu = 'limanda' AND s.tr_varis_tarihi IS NOT NULL AND DATEDIFF(CURDATE(), s.tr_varis_tarihi) > 3)
    -- ✅ EVRAK UYARILARI (7 gün önceden)
    OR (
        (s.tr_varis_tarihi IS NOT NULL AND DATEDIFF(s.tr_varis_tarihi, CURDATE()) <= 7 AND DATEDIFF(s.tr_varis_tarihi, CURDATE()) >= 0)
        OR (s.tr_varis_tarihi IS NULL AND s.tahmini_varis_tarihi IS NOT NULL AND DATEDIFF(s.tahmini_varis_tarihi, CURDATE()) <= 7 AND DATEDIFF(s.tahmini_varis_tarihi, CURDATE()) >= 0)
    )
    -- Eksik evrak kontrolü
    OR (s.original_evrak_durumu = 'bekleniyor' OR s.telex_durumu = 'bekleniyor')
)
GROUP BY i.id
ORDER BY varis_kalan_gun ASC, kalan_gun ASC, liman_bekleme_gun DESC";

$stmt_uyarilar = $db->query($sql_uyarilar);
$uyari_listesi = $stmt_uyarilar->fetchAll();

// ============================================
// 4. KOMİSYON KONTROL (✅ ÇOKLU ÜRÜN)
// ============================================
$sql_komisyon = "SELECT 
    i.id,
    i.balik_dunyasi_dosya_no,
    i.tedarikci_firma,
    
    -- ✅ ÇOKLU ÜRÜN
    COUNT(DISTINCT iu.id) as urun_cesit_sayisi,
    GROUP_CONCAT(DISTINCT uk.urun_cinsi SEPARATOR ', ') as urun_listesi,
    SUM(iu.toplam_tutar) as toplam_siparis_tutari,
    
    -- Komisyon
    o.komisyon_firma,
    o.komisyon_tutari,
    o.komisyon_anlasmasi
    
FROM ithalat i
LEFT JOIN ithalat_urunler iu ON i.id = iu.ithalat_id
LEFT JOIN urun_katalog uk ON iu.urun_katalog_id = uk.id
LEFT JOIN odemeler o ON i.id = o.ithalat_id
WHERE o.komisyon_tutari IS NOT NULL AND o.komisyon_tutari > 0
GROUP BY i.id
ORDER BY i.siparis_tarihi DESC
LIMIT 15";

$stmt_komisyon = $db->query($sql_komisyon);
$komisyon_listesi = $stmt_komisyon->fetchAll();

// ============================================
// YARDIMCI FONKSİYONLAR
// ============================================

/**
 * ✅ GÜNCELLEME: Çoklu ürün için toplam maliyet
 */
function hesaplaToplam($toplam_urun_tutari, $komisyon, $giderler) {
    $urun_tutari = $toplam_urun_tutari ?? 0;
    $komisyon_tutar = $komisyon ?? 0;
    $diger_giderler = $giderler ?? 0;
    
    return $urun_tutari + $komisyon_tutar + $diger_giderler;
}

/**
 * Kur Farkı Hesaplama
 */
function hesaplaKurFarki($tutar, $eski_kur, $yeni_kur) {
    if (!$tutar || !$eski_kur || !$yeni_kur) return 0;
    return ($tutar * $yeni_kur) - ($tutar * $eski_kur);
}

/**
 * ✅ YENİ: Ürün listesini kısalt ve formatla
 */
function formatUrunListesi($urun_listesi, $cesit_sayisi) {
    if (empty($urun_listesi)) return '-';
    
    $urunler = explode(', ', $urun_listesi);
    
    if ($cesit_sayisi == 1) {
        return $urunler[0];
    }
    
    if ($cesit_sayisi <= 3) {
        return implode(', ', $urunler);
    }
    
    // 3'ten fazla ürün varsa
    $ilk_iki = array_slice($urunler, 0, 2);
    return implode(', ', $ilk_iki) . ' <span class="badge bg-info">+' . ($cesit_sayisi - 2) . ' çeşit</span>';
}
?>

<style>
    .calc-header {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(231, 76, 60, 0.3);
    }
    
    .calc-section {
        background: white;
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 25px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .warning-card {
        background: linear-gradient(135deg, #fff3cd, #ffe69c);
        border-left: 5px solid #f39c12;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
    }
    
    .warning-card:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 15px rgba(243, 156, 18, 0.3);
    }
    
    .danger-card {
        background: linear-gradient(135deg, #f8d7da, #f5c2c7);
        border-left: 5px solid #e74c3c;
    }
    
    .success-card {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        border-left: 5px solid #27ae60;
    }
    
    .evrak-warning-card {
        background: linear-gradient(135deg, #e3f2fd, #bbdefb);
        border-left: 5px solid #2196f3;
    }
    
    .calc-result {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border: 2px solid #dee2e6;
        margin: 10px 0;
    }
    
    .calc-label {
        font-weight: 600;
        color: #6c757d;
        font-size: 0.9rem;
    }
    
    .calc-value {
        font-size: 1.3rem;
        font-weight: bold;
        color: #2c3e50;
    }
    
    .calc-value.positive {
        color: #27ae60;
    }
    
    .calc-value.negative {
        color: #e74c3c;
    }
    
    .badge-lg {
        padding: 8px 15px;
        font-size: 0.95rem;
    }
    
    .timeline {
        position: relative;
        padding-left: 30px;
    }
    
    .timeline-item {
        position: relative;
        padding-bottom: 20px;
    }
    
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -24px;
        top: 0;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #3498db;
        border: 3px solid white;
        box-shadow: 0 0 0 2px #3498db;
    }
    
    .timeline-item::after {
        content: '';
        position: absolute;
        left: -19px;
        top: 12px;
        width: 2px;
        height: calc(100% - 12px);
        background: #dee2e6;
    }
    
    .timeline-item:last-child::after {
        display: none;
    }
    
    .uyari-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .uyari-stat-box {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        border-left: 4px solid #e74c3c;
    }
    
    .uyari-stat-value {
        font-size: 2rem;
        font-weight: bold;
        color: #e74c3c;
    }
    
    .uyari-stat-label {
        font-size: 0.85rem;
        color: #6c757d;
        margin-top: 5px;
    }
    
    /* ✅ YENİ: Çoklu ürün badge stili */
    .urun-cesit-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
        cursor: pointer;
    }
</style>

<div class="calc-header">
    <h2 class="mb-0"><i class="fas fa-calculator"></i> Otomatik Hesaplamalar ve Maliyet Analizi</h2>
    <p class="mb-0 mt-2">Gerçek zamanlı maliyet, kur farkı ve uyarı sistemi <span class="badge bg-light text-dark">✅ Çoklu Ürün Destekli</span></p>
</div>

<!-- ✅ UYARILAR SEKSİYONU (GÜNCELLENMİŞ) -->
<div class="calc-section">
    <h3 class="section-title"><i class="fas fa-exclamation-triangle"></i> Acil Uyarılar</h3>
    
    <?php if (count($uyari_listesi) > 0): ?>
        
        <!-- ✅ UYARI İSTATİSTİKLERİ -->
        <?php
        $odeme_uyari_sayisi = 0;
        $liman_uyari_sayisi = 0;
        $evrak_uyari_sayisi = 0;
        
        foreach($uyari_listesi as $uyari) {
            if ($uyari['kalan_gun'] !== null && $uyari['kalan_gun'] >= 0 && $uyari['kalan_gun'] <= 15) {
                $odeme_uyari_sayisi++;
            }
            if ($uyari['liman_bekleme_gun'] !== null && $uyari['liman_bekleme_gun'] > 3) {
                $liman_uyari_sayisi++;
            }
            if (
                ($uyari['varis_kalan_gun'] !== null && $uyari['varis_kalan_gun'] <= 7 && $uyari['varis_kalan_gun'] >= 0) ||
                $uyari['original_evrak_durumu'] == 'bekleniyor' ||
                $uyari['telex_durumu'] == 'bekleniyor'
            ) {
                $evrak_uyari_sayisi++;
            }
        }
        ?>
        
        <div class="uyari-stats">
            <div class="uyari-stat-box">
                <div class="uyari-stat-value"><?php echo $odeme_uyari_sayisi; ?></div>
                <div class="uyari-stat-label"><i class="fas fa-money-bill"></i> Ödeme Uyarısı</div>
            </div>
            <div class="uyari-stat-box">
                <div class="uyari-stat-value"><?php echo $liman_uyari_sayisi; ?></div>
                <div class="uyari-stat-label"><i class="fas fa-anchor"></i> Liman Uyarısı</div>
            </div>
            <div class="uyari-stat-box" style="border-left-color: #2196f3;">
                <div class="uyari-stat-value" style="color: #2196f3;"><?php echo $evrak_uyari_sayisi; ?></div>
                <div class="uyari-stat-label"><i class="fas fa-file-invoice"></i> Evrak Uyarısı</div>
            </div>
        </div>
        
        <?php foreach($uyari_listesi as $uyari): ?>
            <?php
            $uyari_tipleri = [];
            $uyari_mesajlar = [];
            $uyari_css = 'warning-card';
            $uyari_ikon = 'fa-clock';
            
            // Ödeme uyarısı kontrolü
            if ($uyari['kalan_gun'] !== null && $uyari['kalan_gun'] >= 0) {
                if ($uyari['kalan_gun'] <= 5) {
                    $uyari_css = 'danger-card';
                    $uyari_ikon = 'fa-exclamation-circle';
                }
                $uyari_tipleri[] = '<span class="badge bg-warning"><i class="fas fa-money-bill"></i> Ödeme</span>';
                $uyari_mesajlar[] = "Final ödeme tarihi yaklaşıyor! <strong>{$uyari['kalan_gun']} gün</strong> kaldı.";
            }
            
            // Liman bekleme uyarısı
            if ($uyari['liman_bekleme_gun'] !== null && $uyari['liman_bekleme_gun'] > 3) {
                $uyari_css = 'danger-card';
                $uyari_ikon = 'fa-anchor';
                $uyari_tipleri[] = '<span class="badge bg-danger"><i class="fas fa-anchor"></i> Liman</span>';
                $uyari_mesajlar[] = "Limanda <strong>{$uyari['liman_bekleme_gun']} gün</strong> bekliyor! Ardiye/Demoraj riski!";
            }
            
            // ✅ EVRAK UYARILARI
            $evrak_uyari_var = false;
            
            // Varış tarihi yaklaşıyor
            if ($uyari['varis_kalan_gun'] !== null && $uyari['varis_kalan_gun'] <= 7 && $uyari['varis_kalan_gun'] >= 0) {
                $evrak_uyari_var = true;
                $uyari_tipleri[] = '<span class="badge bg-info"><i class="fas fa-ship"></i> Varış</span>';
                $uyari_mesajlar[] = "Varış tarihine <strong>{$uyari['varis_kalan_gun']} gün</strong> kaldı!";
            }
            
            // Eksik evraklar
            $eksik_evraklar = [];
            if ($uyari['original_evrak_durumu'] == 'bekleniyor') {
                $eksik_evraklar[] = 'Original Evrak';
            }
            if ($uyari['telex_durumu'] == 'bekleniyor') {
                $eksik_evraklar[] = 'Telex';
            }
            
            if (count($eksik_evraklar) > 0) {
                $evrak_uyari_var = true;
                $uyari_tipleri[] = '<span class="badge bg-primary"><i class="fas fa-file-invoice"></i> Evrak</span>';
                $uyari_mesajlar[] = "Eksik evraklar: <strong>" . implode(', ', $eksik_evraklar) . "</strong>";
            }
            
            if ($evrak_uyari_var && $uyari_css == 'warning-card') {
                $uyari_css = 'evrak-warning-card';
                $uyari_ikon = 'fa-file-invoice';
            }
            
            // En az bir uyarı varsa göster
            if (count($uyari_mesajlar) > 0):
            ?>
            
            <div class="<?php echo $uyari_css; ?>">
                <div class="d-flex align-items-start">
                    <div class="me-3">
                        <i class="fas <?php echo $uyari_ikon; ?> fa-2x"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-2">
                            <a href="?page=ithalat-detay&id=<?php echo $uyari['id']; ?>">
                                <?php echo formatBDDosyaNo($uyari['balik_dunyasi_dosya_no']); ?> 
                                - <?php echo safeHtml($uyari['tedarikci_firma']); ?>
                            </a>
                        </h5>
                        <div class="mb-2">
                            <?php echo implode(' ', $uyari_tipleri); ?>
                            <?php if ($uyari['urun_cesit_sayisi'] > 1): ?>
                                <span class="urun-cesit-badge">
                                    <i class="fas fa-boxes"></i> <?php echo $uyari['urun_cesit_sayisi']; ?> Çeşit
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php foreach($uyari_mesajlar as $mesaj): ?>
                            <p class="mb-1"><?php echo $mesaj; ?></p>
                        <?php endforeach; ?>
                        <small class="text-muted">
                            <i class="fas fa-box"></i> <?php echo formatUrunListesi($uyari['urun_listesi'], $uyari['urun_cesit_sayisi']); ?>
                        </small>
                    </div>
                    <div>
                        <a href="?page=ithalat-detay&id=<?php echo $uyari['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye"></i> İncele
                        </a>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        <?php endforeach; ?>
        
    <?php else: ?>
        <div class="success-card">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle fa-2x me-3"></i>
                <div>
                    <h5 class="mb-1">Tüm İşlemler Zamanında!</h5>
                    <p class="mb-0">Acil uyarı gerektiren bir durum bulunmamaktadır.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ✅ MALİYET HESAPLAMALARI (ÇOKLU ÜRÜN) -->
<div class="calc-section">
    <h3 class="section-title"><i class="fas fa-money-bill-wave"></i> Detaylı Maliyet Hesaplamaları</h3>
    <p class="text-muted mb-3"><i class="fas fa-info-circle"></i> Çoklu ürün içeren ithalatlar için toplam maliyet analizi</p>
    
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Dosya No</th>
                    <th>Tedarikçi</th>
                    <th>Ürünler</th>
                    <th>Toplam KG</th>
                    <th>Ürün Maliyeti</th>
                    <th>Komisyon</th>
                    <th>Giderler</th>
                    <th>Toplam Maliyet</th>
                    <th>KG Başı</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($maliyet_listesi as $m): 
                    $kg = $m['toplam_kg'] ?? 0;
                    $urun_maliyeti = $m['toplam_urun_tutari'] ?? 0;
                    $komisyon = $m['komisyon_tutari'] ?? 0;
                    $giderler = $m['toplam_gider'] ?? 0;
                    
                    $toplam = $urun_maliyeti + $komisyon + $giderler;
                    $kg_basi = $kg > 0 ? $toplam / $kg : 0;
                ?>
                    <tr onclick="window.location='?page=ithalat-detay&id=<?php echo $m['id']; ?>'">
                        <td><?php echo formatBDDosyaNo($m['balik_dunyasi_dosya_no']); ?></td>
                        <td>
                            <strong><?php echo safeHtml($m['tedarikci_firma']); ?></strong>
                            <br><small class="text-muted"><?php echo formatTarih($m['siparis_tarihi']); ?></small>
                        </td>
                        <td>
                            <?php if ($m['urun_cesit_sayisi'] > 1): ?>
                                <span class="urun-cesit-badge">
                                    <i class="fas fa-boxes"></i> <?php echo $m['urun_cesit_sayisi']; ?> Çeşit
                                </span>
                                <br>
                            <?php endif; ?>
                            <small class="text-muted"><?php echo formatUrunListesi($m['urun_listesi'], $m['urun_cesit_sayisi']); ?></small>
                        </td>
                        <td><strong><?php echo safeNumber($kg); ?> KG</strong></td>
                        <td><strong>$<?php echo safeNumber($urun_maliyeti); ?></strong></td>
                        <td><?php echo $komisyon > 0 ? '$' . safeNumber($komisyon) : '-'; ?></td>
                        <td><?php echo $giderler > 0 ? '₺' . safeNumber($giderler) : '-'; ?></td>
                        <td><span class="badge bg-primary badge-lg">$<?php echo safeNumber($toplam); ?></span></td>
                        <td><span class="badge bg-success badge-lg">$<?php echo safeNumber($kg_basi); ?>/KG</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- KUR FARKI ANALİZİ -->
<div class="calc-section">
    <h3 class="section-title"><i class="fas fa-exchange-alt"></i> Kur Farkı Gelir/Gider Analizi</h3>
    <p class="text-muted mb-4">Avans ödemelerinden final ödemeye kadar kur farkı hesaplamaları</p>
    
    <div class="row">
        <?php 
        $toplam_kur_farki = 0;
        foreach($kur_analiz as $ka): 
            $avans1_kur_farki = hesaplaKurFarki(
                $ka['avans_1_tutari'], 
                $ka['avans_1_kur'], 
                $ka['final_odeme_kur']
            );
            
            $avans2_kur_farki = hesaplaKurFarki(
                $ka['avans_2_tutari'], 
                $ka['avans_2_kur'], 
                $ka['final_odeme_kur']
            );
            
            $toplam_kur_farki_ithalat = $avans1_kur_farki + $avans2_kur_farki;
            $toplam_kur_farki += $toplam_kur_farki_ithalat;
        ?>
            <div class="col-md-6 mb-3">
                <div class="calc-result">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="calc-label"><?php echo formatBDDosyaNo($ka['balik_dunyasi_dosya_no']); ?></div>
                            <small class="text-muted"><?php echo safeHtml($ka['tedarikci_firma']); ?></small>
                        </div>
                        <span class="badge <?php echo $toplam_kur_farki_ithalat >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo $toplam_kur_farki_ithalat >= 0 ? 'Gelir' : 'Gider'; ?>
                        </span>
                    </div>
                    
                    <div class="timeline">
                        <div class="timeline-item">
                            <small class="text-muted">1. Avans Kuru:</small>
                            <strong>₺<?php echo safeNumber($ka['avans_1_kur'], 4); ?></strong>
                        </div>
                        <?php if ($ka['avans_2_kur']): ?>
                        <div class="timeline-item">
                            <small class="text-muted">2. Avans Kuru:</small>
                            <strong>₺<?php echo safeNumber($ka['avans_2_kur'], 4); ?></strong>
                        </div>
                        <?php endif; ?>
                        <div class="timeline-item">
                            <small class="text-muted">Final Kuru:</small>
                            <strong>₺<?php echo safeNumber($ka['final_odeme_kur'], 4); ?></strong>
                        </div>
                    </div>
                    
                    <div class="calc-value <?php echo $toplam_kur_farki_ithalat >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $toplam_kur_farki_ithalat >= 0 ? '+' : ''; ?>₺<?php echo safeNumber($toplam_kur_farki_ithalat); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="alert alert-info mt-4">
        <div class="row">
            <div class="col-md-6">
                <h5><i class="fas fa-info-circle"></i> Toplam Kur Farkı Özeti</h5>
                <p class="mb-0">Tüm ithalatların toplam kur farkı gelir/gider durumu</p>
            </div>
            <div class="col-md-6 text-end">
                <div class="calc-value <?php echo $toplam_kur_farki >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $toplam_kur_farki >= 0 ? 'Toplam Gelir: +' : 'Toplam Gider: '; ?>₺<?php echo safeNumber(abs($toplam_kur_farki)); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ✅ KOMİSYON KONTROL (ÇOKLU ÜRÜN) -->
<div class="calc-section">
    <h3 class="section-title"><i class="fas fa-handshake"></i> Komisyon Kontrol Sistemi</h3>
    <p class="text-muted mb-4">Proforma ile anlaşılan komisyon tutarlarının kontrolü</p>
    
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Dosya No</th>
                    <th>Tedarikçi</th>
                    <th>Ürünler</th>
                    <th>Komisyon Firması</th>
                    <th>Anlaşılan Komisyon</th>
                    <th>Toplam Sipariş Değeri</th>
                    <th>Komisyon Oranı</th>
                    <th>Durum</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($komisyon_listesi as $kom): 
                    $siparis_degeri = $kom['toplam_siparis_tutari'] ?? 0;
                    $komisyon_tutari = $kom['komisyon_tutari'] ?? 0;
                    $komisyon_orani = $siparis_degeri > 0 ? (($komisyon_tutari / $siparis_degeri) * 100) : 0;
                ?>
                    <tr onclick="window.location='?page=ithalat-detay&id=<?php echo $kom['id']; ?>'">
                        <td><?php echo formatBDDosyaNo($kom['balik_dunyasi_dosya_no']); ?></td>
                        <td><strong><?php echo safeHtml($kom['tedarikci_firma']); ?></strong></td>
                        <td>
                            <?php if ($kom['urun_cesit_sayisi'] > 1): ?>
                                <span class="urun-cesit-badge">
                                    <i class="fas fa-boxes"></i> <?php echo $kom['urun_cesit_sayisi']; ?> Çeşit
                                </span>
                            <?php else: ?>
                                <small><?php echo safeHtml($kom['urun_listesi']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo safeHtml($kom['komisyon_firma']); ?></td>
                        <td><strong>$<?php echo safeNumber($komisyon_tutari); ?></strong></td>
                        <td>$<?php echo safeNumber($siparis_degeri); ?></td>
                        <td><span class="badge bg-info badge-lg"><?php echo safeNumber($komisyon_orani); ?>%</span></td>
                        <td>
                            <?php if ($komisyon_orani <= 5): ?>
                                <span class="badge bg-success"><i class="fas fa-check"></i> Normal</span>
                            <?php elseif ($komisyon_orani <= 10): ?>
                                <span class="badge bg-warning"><i class="fas fa-exclamation"></i> Yüksek</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="fas fa-times"></i> Çok Yüksek</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Sayfa yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Otomatik Hesaplamalar hazır! (Çoklu Ürün Destekli)');
    
    // Uyarı sayısını göster
    const uyariSayisi = <?php echo count($uyari_listesi); ?>;
    if (uyariSayisi > 0) {
        console.warn(`⚠️ ${uyariSayisi} adet acil uyarı var!`);
    }
    
    // Çoklu ürün istatistikleri
    const cokluUrunSayisi = <?php echo count(array_filter($maliyet_listesi, function($m) { return $m['urun_cesit_sayisi'] > 1; })); ?>;
    console.log(`📦 ${cokluUrunSayisi} ithalat çoklu ürün içeriyor.`);
});
</script>