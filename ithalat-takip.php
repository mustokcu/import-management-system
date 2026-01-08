<?php
/**
 * İthalat Takip Sayfası
 * Tüm ithalatları listeler, filtreler ve yönetir
 * ✅ GÜNCELLEME: Çoklu ürün sistemi ile tam entegre
 * ✅ YENİ: BD Dosya Numarası gösterimi eklendi
 */

global $ITHALAT_DURUMLARI, $DURUM_RENKLERI;

// ✅ DİNAMİK ÜLKE LİSTESİ
$ULKELER = getUlkeler();

// Veritabanından ithalatları çek
$db = getDB();

// ✅ FİLTRE QUERY STRING KONTROLÜ
$query_params = [];
$base_url = '?page=ithalat-takip';

// Filtreleme parametreleri
$filtre_durum = isset($_GET['durum']) ? cleanInput($_GET['durum']) : '';
$filtre_tedarikci = isset($_GET['tedarikci']) ? cleanInput($_GET['tedarikci']) : '';
$filtre_tarih_baslangic = isset($_GET['tarih_baslangic']) ? cleanInput($_GET['tarih_baslangic']) : '';
$filtre_tarih_bitis = isset($_GET['tarih_bitis']) ? cleanInput($_GET['tarih_bitis']) : '';
$arama = isset($_GET['arama']) ? cleanInput($_GET['arama']) : '';

// ✅ YENİ SQL SORGUSU - ÇOKLU ÜRÜN SİSTEMİ + BD DOSYA NO
$sql = "SELECT 
    i.id,
    i.balik_dunyasi_dosya_no,
    i.tedarikci_siparis_no,
    i.tedarikci_firma,
    i.tedarikci_ulke,
    i.siparis_tarihi,
    i.tahmini_teslim_ayi,
    i.ithalat_durumu,
    i.sigorta_durumu,
    
    -- ✅ ÜRÜN TOPLAMLAR (Subquery ile)
    COALESCE((SELECT COUNT(*) 
     FROM ithalat_urunler iu 
     WHERE iu.ithalat_id = i.id), 0) as urun_sayisi,
    
    COALESCE((SELECT SUM(iu.miktar_kg) 
     FROM ithalat_urunler iu 
     WHERE iu.ithalat_id = i.id), u.toplam_siparis_kg, 0) as toplam_kg,
    
    COALESCE((SELECT SUM(iu.toplam_tutar) 
     FROM ithalat_urunler iu 
     WHERE iu.ithalat_id = i.id), 0) as toplam_tutar,
    
    COALESCE((SELECT AVG(iu.birim_fiyat) 
     FROM ithalat_urunler iu 
     WHERE iu.ithalat_id = i.id), o.ilk_alis_fiyati, 0) as ortalama_fiyat,
    
    -- ✅ İLK ÜRÜN BİLGİSİ (Önizleme için)
    COALESCE(
        (SELECT uk.urun_cinsi 
         FROM ithalat_urunler iu
         LEFT JOIN urun_katalog uk ON iu.urun_katalog_id = uk.id
         WHERE iu.ithalat_id = i.id
         ORDER BY iu.id ASC
         LIMIT 1),
        u.urun_cinsi
    ) as ilk_urun_cinsi,
    
    COALESCE(
        (SELECT uk.urun_latince_isim 
         FROM ithalat_urunler iu
         LEFT JOIN urun_katalog uk ON iu.urun_katalog_id = uk.id
         WHERE iu.ithalat_id = i.id
         ORDER BY iu.id ASC
         LIMIT 1),
        u.urun_latince_isim
    ) as ilk_urun_latince,
    
    -- ✅ ÖDEME BİLGİLERİ
    o.para_birimi,
    o.toplam_fatura_tutari,
    o.odeme_id,
    o.avans_1_tutari,
    o.final_odeme_tarihi,
    
    -- ✅ SEVKİYAT BİLGİLERİ
    s.yukleme_tarihi,
    s.tahmini_varis_tarihi,
    s.bosaltma_limani,
    s.konteyner_numarasi,
    
    -- ✅ KONŞİMENTO KONTROL
    CASE 
        WHEN s.yukleme_tarihi IS NOT NULL 
         AND s.bosaltma_limani IS NOT NULL 
         AND s.konteyner_numarasi IS NOT NULL 
        THEN 1 
        ELSE 0 
    END as konsimento_tamam,
    
    -- ✅ ESKİ SİSTEM KONTROLÜ
    CASE 
        WHEN (SELECT COUNT(*) FROM ithalat_urunler iu WHERE iu.ithalat_id = i.id) > 0 
        THEN 1 
        ELSE 0 
    END as yeni_sistem

FROM ithalat i
LEFT JOIN urun_detaylari u ON i.id = u.ithalat_id
LEFT JOIN odemeler o ON i.id = o.ithalat_id
LEFT JOIN sevkiyat s ON i.id = s.ithalat_id
WHERE 1=1";

$params = [];

// Filtreleri ekle
if (!empty($filtre_durum)) {
    $sql .= " AND i.ithalat_durumu = :durum";
    $params[':durum'] = $filtre_durum;
    $query_params['durum'] = $filtre_durum;
}

if (!empty($filtre_tedarikci)) {
    $sql .= " AND i.tedarikci_firma LIKE :tedarikci";
    $params[':tedarikci'] = "%$filtre_tedarikci%";
    $query_params['tedarikci'] = $filtre_tedarikci;
}

if (!empty($filtre_tarih_baslangic)) {
    $sql .= " AND i.siparis_tarihi >= :tarih_baslangic";
    $params[':tarih_baslangic'] = $filtre_tarih_baslangic;
    $query_params['tarih_baslangic'] = $filtre_tarih_baslangic;
}

if (!empty($filtre_tarih_bitis)) {
    $sql .= " AND i.siparis_tarihi <= :tarih_bitis";
    $params[':tarih_bitis'] = $filtre_tarih_bitis;
    $query_params['tarih_bitis'] = $filtre_tarih_bitis;
}

if (!empty($arama)) {
    $sql .= " AND (i.tedarikci_firma LIKE :arama 
              OR i.balik_dunyasi_dosya_no LIKE :arama
              OR (SELECT GROUP_CONCAT(uk.urun_cinsi SEPARATOR ', ') 
                  FROM ithalat_urunler iu 
                  LEFT JOIN urun_katalog uk ON iu.urun_katalog_id = uk.id 
                  WHERE iu.ithalat_id = i.id) LIKE :arama
              OR u.urun_cinsi LIKE :arama 
              OR u.urun_latince_isim LIKE :arama 
              OR o.odeme_id LIKE :arama)";
    $params[':arama'] = "%$arama%";
    $query_params['arama'] = $arama;
}

$sql .= " ORDER BY i.siparis_tarihi DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$ithalatlar = $stmt->fetchAll();

// İstatistikler
$sql_stats = "SELECT 
    COUNT(*) as toplam,
    SUM(CASE WHEN ithalat_durumu = 'siparis_verildi' THEN 1 ELSE 0 END) as siparis_verildi,
    SUM(CASE WHEN ithalat_durumu = 'yolda' THEN 1 ELSE 0 END) as yolda,
    SUM(CASE WHEN ithalat_durumu = 'limanda' THEN 1 ELSE 0 END) as limanda,
    SUM(CASE WHEN ithalat_durumu = 'teslim_edildi' THEN 1 ELSE 0 END) as teslim_edildi
FROM ithalat";
$stats = $db->query($sql_stats)->fetch();

// ✅ Query string builder fonksiyonu
function buildQueryString($params) {
    $query = '?page=ithalat-takip';
    foreach($params as $key => $value) {
        if (!empty($value)) {
            $query .= '&' . urlencode($key) . '=' . urlencode($value);
        }
    }
    return $query;
}
?>

<style>
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    }
    
    .stat-card.success {
        background: linear-gradient(135deg, #27ae60, #229954);
    }
    
    .stat-card.warning {
        background: linear-gradient(135deg, #f39c12, #e67e22);
    }
    
    .stat-card.info {
        background: linear-gradient(135deg, #3498db, #2980b9);
    }
    
    .stat-card.danger {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
    }
    
    .stat-number {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 0.95rem;
        opacity: 0.9;
    }
    
    .filter-box {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        border: 2px solid #e1e8ed;
    }
    
    .table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .table {
        margin-bottom: 0;
    }
    
    .table thead {
        background: linear-gradient(135deg, #2c3e50, #34495e);
        color: white;
    }
    
    .table thead th {
        font-weight: 600;
        padding: 15px 10px;
        border: none;
        white-space: nowrap;
    }
    
    .table tbody tr {
        transition: all 0.2s ease;
    }
    
    .table tbody tr:hover {
        background: #f8f9fa;
        transform: scale(1.01);
    }
    
    .table td {
        vertical-align: middle;
        padding: 12px 10px;
    }
    
    .badge {
        padding: 6px 12px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .action-buttons {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    
    .btn-action {
        padding: 6px 12px;
        font-size: 0.85rem;
        border-radius: 6px;
        transition: all 0.2s ease;
    }
    
    .btn-action:hover {
        transform: translateY(-2px);
    }
    
    .no-data {
        text-align: center;
        padding: 50px;
        color: #7f8c8d;
    }
    
    .no-data i {
        font-size: 4rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    
    .toplam-fatura {
        font-weight: bold;
        color: #27ae60;
        font-size: 1.05rem;
    }
    
    .odeme-id {
        font-family: 'Courier New', monospace;
        background: #e8f5e9;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.85rem;
        color: #2e7d32;
    }
    
    .konsimento-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .konsimento-badge.complete {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .konsimento-badge.incomplete {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    /* ✅ YENİ: Ürün Bilgisi Stili */
    .urun-bilgi {
        max-width: 250px;
    }
    
    .urun-baslik {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 3px;
    }
    
    .urun-altbaslik {
        font-size: 0.85rem;
        color: #7f8c8d;
        font-style: italic;
    }
    
    .urun-sayisi-badge {
        display: inline-block;
        background: linear-gradient(135deg, #8e44ad, #9b59b6);
        color: white;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-top: 5px;
    }
    
    .urun-sayisi-badge.yeni-sistem {
        background: linear-gradient(135deg, #3498db, #2980b9);
    }
    
    .urun-sayisi-badge.eski-sistem {
        background: linear-gradient(135deg, #95a5a6, #7f8c8d);
    }
    
    /* ✅ YENİ: Toplam Tutar Stili */
    .tutar-container {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 3px;
    }
    
    .tutar-ana {
        font-size: 1.1rem;
        font-weight: bold;
        color: #27ae60;
    }
    
    .tutar-detay {
        font-size: 0.8rem;
        color: #7f8c8d;
    }
    
    .miktar-badge {
        background: #e3f2fd;
        color: #1565c0;
        padding: 4px 10px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    /* ✅ YENİ: BD Dosya No Özel Stili */
    .bd-dosya-no {
        font-family: 'Courier New', monospace;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 6px 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.95rem;
        letter-spacing: 0.5px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }
    
    .bd-dosya-no i {
        font-size: 0.9rem;
    }
</style>

<h2 class="page-title">
    <i class="fas fa-list-alt"></i> İthalat Kayıtları Takibi
</h2>

<!-- İstatistik Kartları -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-number"><?php echo $stats['toplam']; ?></div>
        <div class="stat-label"><i class="fas fa-database"></i> Toplam İthalat</div>
    </div>
    <div class="stat-card warning">
        <div class="stat-number"><?php echo $stats['siparis_verildi']; ?></div>
        <div class="stat-label"><i class="fas fa-shopping-cart"></i> Sipariş Verildi</div>
    </div>
    <div class="stat-card info">
        <div class="stat-number"><?php echo $stats['yolda']; ?></div>
        <div class="stat-label"><i class="fas fa-ship"></i> Yolda</div>
    </div>
    <div class="stat-card danger">
        <div class="stat-number"><?php echo $stats['limanda']; ?></div>
        <div class="stat-label"><i class="fas fa-anchor"></i> Limanda</div>
    </div>
    <div class="stat-card success">
        <div class="stat-number"><?php echo $stats['teslim_edildi']; ?></div>
        <div class="stat-label"><i class="fas fa-check-circle"></i> Teslim Edildi</div>
    </div>
</div>

<!-- FİLTRELEME KUTUSU -->
<div class="filter-box">
    <form method="GET" class="row g-3">
        <input type="hidden" name="page" value="ithalat-takip">
        
        <div class="col-md-3">
            <label class="form-label"><i class="fas fa-search"></i> Arama</label>
            <input type="text" class="form-control" name="arama" 
                   placeholder="Tedarikçi, ürün, BD No, ödeme ID..." value="<?php echo safeHtml($arama); ?>">
        </div>
        
        <div class="col-md-2">
            <label class="form-label"><i class="fas fa-filter"></i> Durum</label>
            <select class="form-select" name="durum">
                <option value="">Tümü</option>
                <?php foreach($ITHALAT_DURUMLARI as $key => $value): ?>
                    <option value="<?php echo $key; ?>" <?php echo $filtre_durum == $key ? 'selected' : ''; ?>>
                        <?php echo $value; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2">
            <label class="form-label"><i class="fas fa-building"></i> Tedarikçi</label>
            <input type="text" class="form-control" name="tedarikci" 
                   value="<?php echo safeHtml($filtre_tedarikci); ?>">
        </div>
        
        <div class="col-md-2">
            <label class="form-label"><i class="fas fa-calendar"></i> Başlangıç</label>
            <input type="date" class="form-control" name="tarih_baslangic" 
                   value="<?php echo $filtre_tarih_baslangic; ?>">
        </div>
        
        <div class="col-md-2">
            <label class="form-label"><i class="fas fa-calendar"></i> Bitiş</label>
            <input type="date" class="form-control" name="tarih_bitis" 
                   value="<?php echo $filtre_tarih_bitis; ?>">
        </div>
        
        <div class="col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-filter"></i> Filtrele
            </button>
        </div>
    </form>
    
    <?php if (!empty($query_params)): ?>
    <div class="mt-3">
        <a href="?page=ithalat-takip" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-times"></i> Filtreleri Temizle
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- ✅ YENİ: İthalat Listesi Tablosu - BD DOSYA NO İLE -->
<div class="table-container">
    <?php if (count($ithalatlar) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><i class="fas fa-folder"></i> BD Dosya No</th>
                        <th>Ödeme ID</th>
                        <th>Tedarikçi</th>
                        <th><i class="fas fa-box"></i> Ürün Bilgisi</th>
                        <th>Sipariş Tarihi</th>
                        <th><i class="fas fa-weight"></i> Toplam KG</th>
                        <th><i class="fas fa-dollar-sign"></i> Ort. Fiyat</th>
                        <th><i class="fas fa-calculator"></i> Toplam Tutar</th>
                        <th>Durum</th>
                        <th>Konşimento</th>
                        <th>Sigorta</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($ithalatlar as $ithalat): ?>
                        <tr>
                            <td><strong>#<?php echo $ithalat['id']; ?></strong></td>
                            
                            <!-- ✅ YENİ: BD DOSYA NO -->
                            <td>
    <!-- BD Dosya No -->
    <?php echo formatBDDosyaNo($ithalat['balik_dunyasi_dosya_no']); ?>
    
    <!-- ✅ YENİ: Tedarikçi Sipariş No (varsa göster) -->
    <?php if (!empty($ithalat['tedarikci_siparis_no'])): ?>
        <div style="margin-top: 5px; font-size: 0.85rem; color: #7f8c8d; font-style: italic;">
            <i class="fas fa-receipt" style="color: #3498db;"></i> 
            <?php echo safeHtml($ithalat['tedarikci_siparis_no']); ?>
        </div>
    <?php endif; ?>
</td>
                            
                            <td>
                                <?php if ($ithalat['odeme_id']): ?>
                                    <span class="odeme-id"><?php echo safeHtml($ithalat['odeme_id']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo safeHtml($ithalat['tedarikci_firma']); ?></strong><br>
                                <small class="text-muted"><?php echo safeHtml($ithalat['tedarikci_ulke']); ?></small>
                            </td>
                            
                            <!-- ✅ YENİ: Ürün Bilgisi Kolonu -->
                            <td>
                                <div class="urun-bilgi">
                                    <div class="urun-baslik">
                                        <?php echo safeHtml($ithalat['ilk_urun_cinsi'] ?: '-'); ?>
                                    </div>
                                    <?php if ($ithalat['ilk_urun_latince']): ?>
                                        <div class="urun-altbaslik">
                                            <?php echo safeHtml($ithalat['ilk_urun_latince']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($ithalat['urun_sayisi'] > 1): ?>
                                        <span class="urun-sayisi-badge <?php echo $ithalat['yeni_sistem'] ? 'yeni-sistem' : 'eski-sistem'; ?>">
                                            <i class="fas fa-boxes"></i> 
                                            +<?php echo $ithalat['urun_sayisi'] - 1; ?> çeşit daha
                                        </span>
                                    <?php elseif ($ithalat['urun_sayisi'] == 1): ?>
                                        <span class="urun-sayisi-badge <?php echo $ithalat['yeni_sistem'] ? 'yeni-sistem' : 'eski-sistem'; ?>">
                                            <i class="fas fa-box"></i> 1 çeşit
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td><?php echo formatTarih($ithalat['siparis_tarihi']); ?></td>
                            
                            <!-- ✅ YENİ: Toplam KG (Doğru Hesaplama) -->
                            <td>
                                <?php if ($ithalat['toplam_kg'] > 0): ?>
                                    <span class="miktar-badge">
                                        <?php echo safeNumber($ithalat['toplam_kg'], 0); ?> KG
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- ✅ YENİ: Ortalama Fiyat -->
                            <td>
                                <?php if ($ithalat['ortalama_fiyat'] > 0): ?>
                                    <strong>$<?php echo safeNumber($ithalat['ortalama_fiyat']); ?></strong>
                                    <small class="text-muted">/KG</small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- ✅ YENİ: Toplam Tutar (Öncelik: Hesaplanan, Fallback: Manuel) -->
                            <td>
                                <div class="tutar-container">
                                    <?php 
                                    $gosterilecek_tutar = 0;
                                    $tutar_kaynak = '';
                                    
                                    if ($ithalat['toplam_tutar'] > 0) {
                                        $gosterilecek_tutar = $ithalat['toplam_tutar'];
                                        $tutar_kaynak = 'Otomatik';
                                    } elseif ($ithalat['toplam_fatura_tutari'] > 0) {
                                        $gosterilecek_tutar = $ithalat['toplam_fatura_tutari'];
                                        $tutar_kaynak = 'Manuel';
                                    }
                                    ?>
                                    
                                    <?php if ($gosterilecek_tutar > 0): ?>
                                        <span class="tutar-ana">
                                            $<?php echo safeNumber($gosterilecek_tutar); ?>
                                        </span>
                                        <span class="tutar-detay">
                                            <i class="fas fa-info-circle"></i> <?php echo $tutar_kaynak; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td><?php echo getDurumBadge($ithalat['ithalat_durumu']); ?></td>
                            
                            <!-- Konşimento -->
                            <td>
                                <?php if ($ithalat['konsimento_tamam']): ?>
                                    <span class="konsimento-badge complete">
                                        <i class="fas fa-check-circle"></i> Tamamlandı
                                    </span>
                                <?php else: ?>
                                    <span class="konsimento-badge incomplete">
                                        <i class="fas fa-exclamation-triangle"></i> Eksik
                                    </span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Sigorta -->
                            <td>
                                <?php if ($ithalat['sigorta_durumu'] == 'evet'): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Var</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fas fa-times"></i> Yok</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- İşlemler -->
                            <td>
                                <div class="action-buttons">
                                    <a href="?page=ithalat-detay&id=<?php echo $ithalat['id']; ?>" 
                                       class="btn btn-sm btn-info btn-action" title="Detay">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="?page=ithalat-duzenle&id=<?php echo $ithalat['id']; ?>" 
                                       class="btn btn-sm btn-warning btn-action" title="Düzenle">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="durumGuncelle(<?php echo $ithalat['id']; ?>)" 
                                            class="btn btn-sm btn-primary btn-action" title="Durum Güncelle">
                                        <i class="fas fa-sync"></i>
                                    </button>
                                    <button onclick="ithalatSil(<?php echo $ithalat['id']; ?>)" 
                                            class="btn btn-sm btn-danger btn-action" title="Sil">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-inbox"></i>
            <h4>Henüz İthalat Kaydı Yok</h4>
            <p>Yeni ithalat kaydı eklemek için "Veri Giriş Formu" sekmesine gidin.</p>
            <a href="?page=veri-giris" class="btn btn-primary mt-3">
                <i class="fas fa-plus"></i> Yeni İthalat Ekle
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Durum Güncelleme Modal -->
<div class="modal fade" id="durumModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sync"></i> İthalat Durumu Güncelle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="durum_ithalat_id">
                <label class="form-label">Yeni Durum Seçin:</label>
                <select class="form-select" id="yeni_durum">
                    <?php foreach($ITHALAT_DURUMLARI as $key => $value): ?>
                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary" onclick="kaydetDurum()">
                    <i class="fas fa-save"></i> Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Durum güncelleme modalını aç
function durumGuncelle(id) {
    document.getElementById('durum_ithalat_id').value = id;
    const modal = new bootstrap.Modal(document.getElementById('durumModal'));
    modal.show();
}

// Durumu kaydet
function kaydetDurum() {
    const id = document.getElementById('durum_ithalat_id').value;
    const yeni_durum = document.getElementById('yeni_durum').value;
    
    fetch('api/update-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: id,
            durum: yeni_durum
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            alert('✅ Durum başarıyla güncellendi!');
            location.reload();
        } else {
            alert('❌ Hata: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Bir hata oluştu!');
    });
}

// İthalat silme
function ithalatSil(id) {
    if(!confirm('Bu ithalat kaydını silmek istediğinizden emin misiniz?')) {
        return;
    }
    
    fetch('api/delete-import.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            alert('✅ İthalat kaydı silindi!');
            location.reload();
        } else {
            alert('❌ Hata: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Bir hata oluştu!');
    });
}

console.log('✅ İthalat Takip - BD Dosya No Sistemi Aktif!');
console.log('🔍 Arama alanında BD numarası ile de arama yapabilirsiniz!');
</script>