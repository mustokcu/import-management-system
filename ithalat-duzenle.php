<?php
/**
 * İthalat Düzenleme Sayfası - TAM SÜRÜM
 * ✅ Duplicate sevkiyat bölümü kaldırıldı
 * ✅ JavaScript hataları düzeltildi
 * ✅ Vergi sistemi entegre
 * ✅ Çoklu ürün desteği
 */

// Yardımcı fonksiyon
function getDurumAciklama($durum) {
    $aciklamalar = [
        'siparis_verildi' => 'Sipariş tedarikçiye verildi',
        'yolda' => 'Ürün gemi veya uçakla yola çıktı',
        'transitte' => 'Transit limanda aktarma bekliyor',
        'limanda' => 'Türkiye limanına ulaştı, gümrükleme aşamasında',
        'teslim_edildi' => 'Ürün depoya teslim edildi'
    ];
    return $aciklamalar[$durum] ?? 'Bilinmiyor';
}

// Dinamik ülke listesi
$ULKELER = getUlkeler();
$ULKELER_BOLGE = getUlkelerByRegion();

// Global değişkenler
global $URUN_TIPLERI, $KALITE_TERIMLERI, $KOLI_MARKALARI, 
       $GTIP_TIPLERI, $ON_ODEME_ORANLARI, $PARA_BIRIMLERI;

$db = getDB();

// ID kontrolü
$ithalat_id = $_GET['id'] ?? null;

if (!$ithalat_id) {
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> İthalat ID belirtilmedi!</div>';
    exit;
}

// Mevcut verileri çek
$sql = "SELECT 
    i.*,
    u.*,
    o.*,
    g.*,
    s.*,
    i.id as ithalat_id,
    i.notlar as ithalat_notlar
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
    echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> İthalat kaydı bulunamadı!</div>';
    exit;
}

// ✅ Mevcut ürünleri çek (VERGİ BİLGİLERİYLE)
$sql_urunler = "SELECT 
    iu.*,
    uk.urun_latince_isim,
    uk.urun_cinsi,
    uk.urun_tipi,
    uk.kalibre
FROM ithalat_urunler iu
LEFT JOIN urun_katalog uk ON iu.urun_katalog_id = uk.id
WHERE iu.ithalat_id = :ithalat_id
ORDER BY iu.id";

$stmt_urunler = $db->prepare($sql_urunler);
$stmt_urunler->execute([':ithalat_id' => $ithalat_id]);
$mevcut_urunler = $stmt_urunler->fetchAll();

// ✅ Aktif ürün kataloğunu çek
$sql_katalog = "SELECT * FROM urun_katalog WHERE aktif = 1 ORDER BY urun_tipi, urun_cinsi, kalibre";
$stmt_katalog = $db->query($sql_katalog);
$urun_katalog = $stmt_katalog->fetchAll();

// ✅ GTIP kodlarını çek
$sql_gtip = "SELECT gtip_kodu, aciklama, varsayilan_gumruk_orani, varsayilan_otv_orani, varsayilan_kdv_orani 
             FROM gtip_kodlari 
             WHERE aktif = 1 
             ORDER BY gtip_kodu";
$gtip_listesi_tum = $db->query($sql_gtip)->fetchAll();
?>

<style>
    .edit-header {
        background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(243, 156, 18, 0.3);
    }
    
    .section-box {
        background: #f8f9fa;
        border: 2px solid #e1e8ed;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 30px;
        transition: all 0.3s ease;
    }
    
    .section-header {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 3px solid #f39c12;
    }
    
    .section-number {
        background: linear-gradient(135deg, #f39c12, #e67e22);
        color: white;
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.2rem;
        margin-right: 15px;
        box-shadow: 0 4px 10px rgba(243, 156, 18, 0.3);
    }
    
    .btn-update-form {
        background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        color: white;
        padding: 15px 40px;
        border: none;
        border-radius: 50px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 10px 20px rgba(243, 156, 18, 0.3);
    }
    
    .btn-update-form:hover:not(:disabled) {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(243, 156, 18, 0.4);
    }
    
    .btn-update-form:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }
    
    .urun-duzenle-panel {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        border: 2px solid #4caf50;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
    }
    
    .urun-duzenle-panel h5 {
        color: #2e7d32;
        font-weight: 600;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .urun-edit-card {
        background: white;
        border: 2px solid #e1e8ed;
        border-left: 5px solid #3b82f6;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
    }
    
    .urun-edit-card:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .urun-edit-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        background: #f8fafc;
        padding: 15px;
        border-radius: 8px;
        margin-top: 10px;
    }
    
    .durum-panel {
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        border: 2px solid #2196f3;
        border-radius: 10px;
        padding: 20px;
        margin-top: 15px;
    }
    
    .vergi-section {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border: 2px solid #f59e0b;
        border-radius: 12px;
        padding: 15px;
        margin-top: 15px;
    }
    
    .vergi-section-header {
        font-size: 0.95rem;
        font-weight: 600;
        color: #92400e;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .vergi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
    }
    
    .vergi-item {
        background: white;
        padding: 10px;
        border-radius: 6px;
        border-left: 3px solid #f59e0b;
    }
    
    .vergi-item-label {
        font-size: 0.75rem;
        color: #78716c;
        font-weight: 600;
        margin-bottom: 4px;
    }
    
    .vergi-item-value {
        font-size: 1rem;
        font-weight: bold;
        color: #1c1917;
    }
    
    .gtip-kod-display {
        font-family: 'Courier New', monospace;
        background: linear-gradient(135deg, #2196f3, #1976d2);
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.9rem;
        display: inline-block;
    }
    
    .btn-urun-action {
        padding: 8px 15px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    
    .btn-urun-kaydet {
        background: #3b82f6;
        color: white;
    }
    
    .btn-urun-sil {
        background: #ef4444;
        color: white;
    }
    
    .btn-urun-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    
    .urun-add-panel {
        background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
        border: 2px solid #ffc107;
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
    }
    
    .urun-add-panel h6 {
        color: #856404;
        font-weight: 600;
        margin-bottom: 15px;
    }
    
    .urun-select-row {
        display: grid;
        grid-template-columns: 2fr 1.5fr 1fr 1fr auto;
        gap: 15px;
        align-items: end;
    }
    
    .btn-urun-ekle {
        background: linear-gradient(135deg, #4caf50, #388e3c);
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .gtip-bilgi-panel {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        border: 2px solid #4caf50;
        border-radius: 10px;
        padding: 15px;
        margin-top: 10px;
    }
    
    @media (max-width: 768px) {
        .urun-select-row,
        .urun-edit-form {
            grid-template-columns: 1fr;
        }
        
        .vergi-grid {
            grid-template-columns: 1fr;
        }
    }
    /* Tedarikçi Sipariş No Özel Stil */
.siparis-no-input-group {
    position: relative;
}

.siparis-no-input-group .form-control {
    padding-left: 45px;
    font-family: 'Courier New', monospace;
    font-weight: 600;
    font-size: 1.05rem;
    letter-spacing: 0.5px;
    border: 2px solid #e2e8f0;
}

.siparis-no-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #3498db;
    font-size: 1.1rem;
    z-index: 10;
    pointer-events: none;
}

.siparis-no-input-group .form-control:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
}
</style>

<div class="edit-header">
    <h2 class="mb-0">
        <i class="fas fa-edit"></i> İthalat Kaydı Düzenle 
        <?php echo formatBDDosyaNo($data['balik_dunyasi_dosya_no']); ?>
    </h2>
    <p class="mb-0 mt-2"><?php echo safeHtml($data['tedarikci_firma'] ?? ''); ?> - <?php echo formatTarih($data['siparis_tarihi'] ?? null); ?></p>
</div>

<div class="mb-3">
    <a href="?page=ithalat-detay&id=<?php echo $ithalat_id; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Detaya Geri Dön
    </a>
</div>

<form id="editIthalatForm" method="POST">
    <input type="hidden" name="ithalat_id" value="<?php echo $ithalat_id; ?>">
    
    <!-- 1. TEDARİKÇİ BİLGİLERİ -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">1</div>
            <h3 class="section-title">Tedarikçi Firma Bilgileri</h3>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Tedarikçi Firma Adı *</label>
                <input type="text" class="form-control" name="tedarikci_firma" 
                       value="<?php echo safeHtml($data['tedarikci_firma'] ?? ''); ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Tedarikçi Ülke *</label>
                <select class="form-select" name="tedarikci_ulke" required>
                    <option value="">Seçiniz</option>
                    <?php foreach($ULKELER_BOLGE as $bolge => $ulkeler): ?>
                        <optgroup label="<?php echo safeHtml($bolge); ?>">
                            <?php foreach($ulkeler as $key => $value): ?>
                                <option value="<?php echo $key; ?>" 
                                    <?php echo ($data['tedarikci_ulke'] ?? '') == $key ? 'selected' : ''; ?>>
                                    <?php echo safeHtml($value); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
    <label for="tedarikci_siparis_no" class="form-label">
        <i class="fas fa-hashtag"></i> Tedarikçi Sipariş Numarası
    </label>
    <div class="siparis-no-input-group">
        <i class="fas fa-receipt siparis-no-icon"></i>
        <input type="text" 
               class="form-control" 
               id="tedarikci_siparis_no" 
               name="tedarikci_siparis_no"
               value="<?php echo safeHtml($data['tedarikci_siparis_no'] ?? ''); ?>"
               placeholder="Örn: PO-2024-1234"
               maxlength="100">
    </div>
</div>
            <div class="col-md-6">
                <label class="form-label">Menşei Ülke</label>
                <select class="form-select" name="mensei_ulke">
                    <option value="">Seçiniz</option>
                    <?php foreach($ULKELER_BOLGE as $bolge => $ulkeler): ?>
                        <optgroup label="<?php echo safeHtml($bolge); ?>">
                            <?php foreach($ulkeler as $key => $value): ?>
                                <option value="<?php echo $key; ?>" 
                                    <?php echo ($data['mensei_ulke'] ?? '') == $key ? 'selected' : ''; ?>>
                                    <?php echo safeHtml($value); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Transit Detay (varsa)</label>
                <textarea class="form-control" name="transit_detay" rows="2"><?php echo safeHtml($data['transit_detay'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>
    
    <!-- 2. TARİH BİLGİLERİ + İTHALAT DURUMU -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">2</div>
            <h3 class="section-title">Sipariş Tarih Bilgileri</h3>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Sipariş Tarihi *</label>
                <input type="date" class="form-control" name="siparis_tarihi" 
                       value="<?php echo $data['siparis_tarihi'] ?? ''; ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">İlk Sipariş Tarihi</label>
                <input type="date" class="form-control" name="ilk_siparis_tarihi" 
                       value="<?php echo $data['ilk_siparis_tarihi'] ?? ''; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Tahmini Teslim Ayı</label>
                <input type="month" class="form-control" name="tahmini_teslim_ayi" 
                       value="<?php echo $data['tahmini_teslim_ayi'] ?? ''; ?>">
            </div>
            
            <!-- ✅ İTHALAT DURUMU -->
            <div class="col-12">
                <div class="durum-panel">
                    <label class="form-label" style="font-size: 1.1rem; font-weight: 600; color: #1565c0; margin-bottom: 15px;">
                        <i class="fas fa-flag"></i> İthalat Durumu
                    </label>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <select class="form-select" name="ithalat_durumu" id="ithalat_durumu" 
                                    style="border: 2px solid #2196f3; font-weight: 600;">
                                <option value="siparis_verildi" 
                                    <?php echo ($data['ithalat_durumu'] ?? 'siparis_verildi') == 'siparis_verildi' ? 'selected' : ''; ?>>
                                    📋 Sipariş Verildi
                                </option>
                                <option value="yolda" 
                                    <?php echo ($data['ithalat_durumu'] ?? '') == 'yolda' ? 'selected' : ''; ?>>
                                    🚢 Yolda
                                </option>
                                <option value="transitte" 
                                    <?php echo ($data['ithalat_durumu'] ?? '') == 'transitte' ? 'selected' : ''; ?>>
                                    🔄 Transit'te
                                </option>
                                <option value="limanda" 
                                    <?php echo ($data['ithalat_durumu'] ?? '') == 'limanda' ? 'selected' : ''; ?>>
                                    ⚓ Limanda
                                </option>
                                <option value="teslim_edildi" 
                                    <?php echo ($data['ithalat_durumu'] ?? '') == 'teslim_edildi' ? 'selected' : ''; ?>>
                                    ✅ Teslim Edildi
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div id="durum-bilgi" style="padding: 10px; background: white; border-radius: 8px; border: 1px solid #2196f3;">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    <span id="durum-aciklama"><?php echo getDurumAciklama($data['ithalat_durumu'] ?? 'siparis_verildi'); ?></span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 3. KUR BİLGİSİ -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">3</div>
            <h3 class="section-title">Kur Bilgileri</h3>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-dollar-sign"></i> USD Kuru (TL) *
                </label>
                <input type="number" class="form-control" id="usd_kur" name="usd_kur" 
                       value="<?php echo $data['usd_kur'] ?? ''; ?>"
                       step="0.0001" min="0" placeholder="34.5000" required>
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i> 
                    <a href="https://www.tcmb.gov.tr/wps/wcm/connect/tr/tcmb+tr/main+page+site+area/bugun" target="_blank">
                        TCMB'den güncel kur
                    </a>
                </small>
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-calendar"></i> Kur Tarihi *
                </label>
                <input type="date" class="form-control" name="kur_tarihi" 
                       value="<?php echo $data['kur_tarihi'] ?? date('Y-m-d'); ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-sticky-note"></i> Kur Notu
                </label>
                <input type="text" class="form-control" name="kur_notu" 
                       value="<?php echo safeHtml($data['kur_notu'] ?? ''); ?>"
                       placeholder="Örn: TCMB resmi kur">
            </div>
        </div>
        <div style="margin-top: 15px; padding: 12px; background: #e3f2fd; border-radius: 8px; border-left: 4px solid #2196f3;">
            <small class="text-muted">
                <i class="fas fa-lightbulb"></i> 
                <strong>Bilgi:</strong> Bu kur, vergi hesaplamalarında USD'yi TL'ye çevirmek için kullanılacaktır.
            </small>
        </div>
    </div>
    
    <!-- 4. ÜRÜN DÜZENLEME (VERGİ SİSTEMİYLE) -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">4</div>
            <h3 class="section-title">Ürün Bilgileri ve Vergi Hesaplamaları</h3>
        </div>
        
        <div class="urun-duzenle-panel">
            <h5><i class="fas fa-boxes"></i> Mevcut Ürünler</h5>
            
            <?php if (count($mevcut_urunler) > 0): ?>
                <div class="mevcut-urunler-liste" id="mevcut-urunler-liste">
                    <?php foreach($mevcut_urunler as $index => $urun): ?>
                        <div class="urun-edit-card" data-urun-id="<?php echo $urun['id']; ?>">
                            <div class="urun-edit-header">
                                <div>
                                    <div class="urun-edit-title">
                                        <?php echo $index + 1; ?>. <?php echo safeHtml($urun['urun_cinsi']); ?>
                                        <?php if ($urun['kalibre']): ?>
                                            <span style="color: #8b5cf6; font-size: 0.9rem;">
                                                (<?php echo safeHtml($urun['kalibre']); ?>)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="urun-edit-subtitle">
                                        <i class="fas fa-flask"></i> <?php echo safeHtml($urun['urun_latince_isim']); ?>
                                    </div>
                                    <?php if ($urun['gtip_kodu']): ?>
                                        <div style="margin-top: 5px;">
                                            <span class="gtip-kod-display">
                                                <i class="fas fa-barcode"></i> <?php echo safeHtml($urun['gtip_kodu']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="urun-edit-form">
                                <div>
                                    <label class="form-label" style="font-size: 0.85rem;">Miktar (KG)</label>
                                    <input type="number" class="form-control urun-miktar" 
                                           value="<?php echo $urun['miktar_kg']; ?>"
                                           data-urun-id="<?php echo $urun['id']; ?>"
                                           min="0" step="0.01">
                                </div>
                                <div>
                                    <label class="form-label" style="font-size: 0.85rem;">Birim Fiyat ($)</label>
                                    <input type="number" class="form-control urun-fiyat" 
                                           value="<?php echo $urun['birim_fiyat']; ?>"
                                           data-urun-id="<?php echo $urun['id']; ?>"
                                           min="0" step="0.01">
                                </div>
                                <div>
                                    <label class="form-label" style="font-size: 0.85rem;">GTIP Kodu</label>
                                    <input type="text" class="form-control gtip-input" 
                                           value="<?php echo safeHtml($urun['gtip_kodu'] ?? ''); ?>"
                                           data-urun-id="<?php echo $urun['id']; ?>"
                                           list="gtipList"
                                           placeholder="1605.51.00"
                                           onchange="gtipSecildiEdit(<?php echo $urun['id']; ?>)">
                                </div>
                                <div>
                                    <label class="form-label" style="font-size: 0.85rem;">Gümrük (%)</label>
                                    <input type="number" class="form-control gumruk-input" 
                                           value="<?php echo $urun['gumruk_vergisi_oran'] ?? 0; ?>"
                                           data-urun-id="<?php echo $urun['id']; ?>"
                                           min="0" step="0.01">
                                </div>
                                <div>
                                    <label class="form-label" style="font-size: 0.85rem;">ÖTV (%)</label>
                                    <input type="number" class="form-control otv-input" 
                                           value="<?php echo $urun['otv_oran'] ?? 0; ?>"
                                           data-urun-id="<?php echo $urun['id']; ?>"
                                           min="0" step="0.01">
                                </div>
                                <div>
                                    <label class="form-label" style="font-size: 0.85rem;">KDV (%)</label>
                                    <input type="number" class="form-control kdv-input" 
                                           value="<?php echo $urun['kdv_oran'] ?? 20; ?>"
                                           data-urun-id="<?php echo $urun['id']; ?>"
                                           min="0" step="0.01">
                                </div>
                                <div style="display: flex; gap: 8px; align-items: end;">
                                    <button type="button" class="btn-urun-action btn-urun-kaydet"
                                            onclick="urunGuncelle(<?php echo $urun['id']; ?>)">
                                        <i class="fas fa-save"></i> Kaydet
                                    </button>
                                    <button type="button" class="btn-urun-action btn-urun-sil"
                                            onclick="urunSil(<?php echo $urun['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Vergi Bilgileri Özet -->
                            <div class="vergi-section">
                                <div class="vergi-section-header">
                                    <i class="fas fa-percentage"></i>
                                    Vergi Hesaplamaları
                                </div>
                                <div class="vergi-grid">
                                    <div class="vergi-item">
                                        <div class="vergi-item-label">USD Toplam</div>
                                        <div class="vergi-item-value urun-usd-toplam" data-urun-id="<?php echo $urun['id']; ?>">
                                            $<?php echo number_format($urun['toplam_tutar'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="vergi-item">
                                        <div class="vergi-item-label">TL Tutar</div>
                                        <div class="vergi-item-value urun-tl-tutar" data-urun-id="<?php echo $urun['id']; ?>">
                                            ₺<?php echo number_format($urun['toplam_tutar'] * ($data['usd_kur'] ?? 1), 2); ?>
                                        </div>
                                    </div>
                                    <div class="vergi-item">
                                        <div class="vergi-item-label">Gümrük</div>
                                        <div class="vergi-item-value urun-gumruk" data-urun-id="<?php echo $urun['id']; ?>" style="color: #4caf50;">
                                            ₺<?php echo number_format($urun['gumruk_vergisi_tutar'] ?? 0, 2); ?>
                                        </div>
                                    </div>
                                    <div class="vergi-item">
                                        <div class="vergi-item-label">ÖTV</div>
                                        <div class="vergi-item-value urun-otv" data-urun-id="<?php echo $urun['id']; ?>" style="color: #ff9800;">
                                            ₺<?php echo number_format($urun['otv_tutar'] ?? 0, 2); ?>
                                        </div>
                                    </div>
                                    <div class="vergi-item">
                                        <div class="vergi-item-label">KDV</div>
                                        <div class="vergi-item-value urun-kdv" data-urun-id="<?php echo $urun['id']; ?>" style="color: #2196f3;">
                                            ₺<?php echo number_format($urun['kdv_tutar'] ?? 0, 2); ?>
                                        </div>
                                    </div>
                                    <div class="vergi-item" style="background: linear-gradient(135deg, #ffe0b2 0%, #ffcc80 100%); border-left-color: #e65100;">
                                        <div class="vergi-item-label">Toplam Vergi</div>
                                        <div class="vergi-item-value urun-toplam-vergi" data-urun-id="<?php echo $urun['id']; ?>" style="color: #e65100;">
                                            ₺<?php echo number_format($urun['toplam_vergi'] ?? 0, 2); ?>
                                        </div>
                                    </div>
                                    <div class="vergi-item" style="background: linear-gradient(135deg, #c8e6c9 0%, #a5d6a7 100%); border-left-color: #1b5e20; grid-column: 1 / -1;">
                                        <div class="vergi-item-label">Vergi Dahil Tutar</div>
                                        <div class="vergi-item-value urun-vergi-dahil" data-urun-id="<?php echo $urun['id']; ?>" style="color: #1b5e20; font-size: 1.2rem;">
                                            ₺<?php echo number_format($urun['vergi_dahil_tutar'] ?? 0, 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    Bu ithalat eski sistemde kaydedilmiş. Yeni ürün ekleyebilirsiniz.
                </div>
            <?php endif; ?>
            
            <!-- Yeni Ürün Ekleme -->
            <div class="urun-add-panel">
                <h6><i class="fas fa-plus-circle"></i> Yeni Ürün Ekle</h6>
                <div class="urun-select-row">
                    <div>
                        <label class="form-label" style="font-size: 0.85rem;">Ürün Seç</label>
                        <select class="form-select" id="yeni_urun_sec">
                            <option value="">Ürün seçiniz...</option>
                            <?php 
                            $current_tip = '';
                            foreach($urun_katalog as $urun): 
                                if ($current_tip != $urun['urun_tipi']) {
                                    if ($current_tip != '') echo '</optgroup>';
                                    $current_tip = $urun['urun_tipi'];
                                    echo '<optgroup label="' . safeHtml($URUN_TIPLERI[$current_tip] ?? $current_tip) . '">';
                                }
                                
                                $urun_text = $urun['urun_cinsi'];
                                if ($urun['kalibre']) {
                                    $urun_text .= ' (' . $urun['kalibre'] . ')';
                                }
                            ?>
                                <option value="<?php echo $urun['id']; ?>" 
                                        data-latince="<?php echo safeHtml($urun['urun_latince_isim']); ?>"
                                        data-cinsi="<?php echo safeHtml($urun['urun_cinsi']); ?>"
                                        data-kalibre="<?php echo safeHtml($urun['kalibre'] ?? ''); ?>">
                                    <?php echo safeHtml($urun_text . ' - ' . $urun['urun_latince_isim']); ?>
                                </option>
                            <?php 
                            endforeach; 
                            if ($current_tip != '') echo '</optgroup>';
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.85rem;">GTIP Kodu</label>
                        <input type="text" class="form-control" id="yeni_gtip_kodu" 
                               placeholder="1605.51.00" list="gtipList"
                               onchange="gtipSecildiYeni()">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.85rem;">Miktar (KG)</label>
                        <input type="number" class="form-control" id="yeni_miktar_kg" 
                               placeholder="0" min="0" step="0.01">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.85rem;">Birim Fiyat ($)</label>
                        <input type="number" class="form-control" id="yeni_birim_fiyat" 
                               placeholder="0.00" min="0" step="0.01">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 0.85rem;">&nbsp;</label>
                        <button type="button" class="btn-urun-ekle" onclick="yeniUrunEkle()">
                            <i class="fas fa-plus"></i> Ekle
                        </button>
                    </div>
                </div>
                
                <!-- GTIP Bilgi Paneli (Yeni Ürün için) -->
                <div id="gtip-bilgi-panel-yeni" style="display: none; margin-top: 15px;">
                    <div class="gtip-bilgi-panel">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <div style="flex: 1; min-width: 200px;">
                                <div style="font-size: 0.8rem; color: #2e7d32; font-weight: 600; margin-bottom: 5px;">
                                    📋 GTIP AÇIKLAMA:
                                </div>
                                <div id="gtip-aciklama-yeni" style="color: #1b5e20; font-weight: 500;"></div>
                            </div>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <span style="background: #4caf50; color: white; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.85rem;">
                                    Gümrük: <strong id="gtip-gumruk-yeni">0</strong>%
                                </span>
                                <span style="background: #ff9800; color: white; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.85rem;">
                                    ÖTV: <strong id="gtip-otv-yeni">0</strong>%
                                </span>
                                <span style="background: #2196f3; color: white; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.85rem;">
                                    KDV: <strong id="gtip-kdv-yeni">20</strong>%
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hidden inputs for vergi oranları -->
                    <input type="hidden" id="yeni_gumruk_oran" value="0">
                    <input type="hidden" id="yeni_otv_oran" value="0">
                    <input type="hidden" id="yeni_kdv_oran" value="20">
                </div>
            </div>
        </div>
        
        <!-- GTIP Datalist -->
        <datalist id="gtipList">
            <?php foreach($gtip_listesi_tum as $gtip): ?>
                <option value="<?php echo $gtip['gtip_kodu']; ?>" 
                        data-aciklama="<?php echo safeHtml($gtip['aciklama']); ?>"
                        data-gumruk="<?php echo $gtip['varsayilan_gumruk_orani']; ?>"
                        data-otv="<?php echo $gtip['varsayilan_otv_orani']; ?>"
                        data-kdv="<?php echo $gtip['varsayilan_kdv_orani']; ?>">
                    <?php echo safeHtml(mb_substr($gtip['aciklama'], 0, 40)); ?>
                </option>
            <?php endforeach; ?>
        </datalist>
    </div>
    
    <!-- 5. ÖDEME BİLGİLERİ -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">5</div>
            <h3 class="section-title">Ödeme Bilgileri</h3>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">İlk Alış Fiyatı (KG)</label>
                <input type="number" class="form-control" name="ilk_alis_fiyati" 
                       value="<?php echo $data['ilk_alis_fiyati'] ?? ''; ?>" min="0" step="0.01">
            </div>
            <div class="col-md-4">
                <label class="form-label">Para Birimi</label>
                <select class="form-select" name="para_birimi">
                    <?php foreach($PARA_BIRIMLERI as $pb): ?>
                        <option value="<?php echo $pb; ?>" <?php echo ($data['para_birimi'] ?? 'USD') == $pb ? 'selected' : ''; ?>>
                            <?php echo $pb; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tranship Ek Maliyet</label>
                <input type="number" class="form-control" name="tranship_ek_maliyet" 
                       value="<?php echo $data['tranship_ek_maliyet'] ?? ''; ?>" min="0" step="0.01">
            </div>
            <div class="col-md-6">
                <label class="form-label">Komisyon Firması</label>
                <input type="text" class="form-control" name="komisyon_firma" 
                       value="<?php echo safeHtml($data['komisyon_firma'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Komisyon Tutarı</label>
                <input type="number" class="form-control" name="komisyon_tutari" 
                       value="<?php echo $data['komisyon_tutari'] ?? ''; ?>" min="0" step="0.01">
            </div>
        </div>
    </div>
    
    <!-- 6. GİDERLER -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">6</div>
            <h3 class="section-title">Giderler</h3>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-university"></i> Gümrük Ücreti (₺)
                </label>
                <input type="number" class="form-control gider-input" name="gumruk_ucreti" 
                       value="<?php echo $data['gumruk_ucreti'] ?? ''; ?>" 
                       min="0" step="0.01" placeholder="0.00">
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-seedling"></i> Tarım Hizmet Ücreti (₺)
                </label>
                <input type="number" class="form-control gider-input" name="tarim_hizmet_ucreti" 
                       value="<?php echo $data['tarim_hizmet_ucreti'] ?? ''; ?>" 
                       min="0" step="0.01" placeholder="0.00">
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-truck"></i> Nakliye Bedeli (₺)
                </label>
                <input type="number" class="form-control gider-input" name="nakliye_bedeli" 
                       value="<?php echo $data['nakliye_bedeli'] ?? ''; ?>" 
                       min="0" step="0.01" placeholder="0.00">
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-shield-alt"></i> Sigorta Bedeli (₺)
                </label>
                <input type="number" class="form-control gider-input" name="sigorta_bedeli" 
                       value="<?php echo $data['sigorta_bedeli'] ?? ''; ?>" 
                       min="0" step="0.01" placeholder="0.00">
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-warehouse"></i> Ardiye Ücreti (₺)
                </label>
                <input type="number" class="form-control gider-input" name="ardiye_ucreti" 
                       value="<?php echo $data['ardiye_ucreti'] ?? ''; ?>" 
                       min="0" step="0.01" placeholder="0.00">
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-clock"></i> Demoraj Ücreti (₺)
                </label>
                <input type="number" class="form-control gider-input" name="demoraj_ucreti" 
                       value="<?php echo $data['demoraj_ucreti'] ?? ''; ?>" 
                       min="0" step="0.01" placeholder="0.00">
            </div>
        </div>
        
        <!-- Toplam Gider Özeti -->
        <div class="alert alert-info mt-3" id="gider-ozet" style="display: none;">
            <strong><i class="fas fa-calculator"></i> Toplam Gider:</strong>
            <span id="toplam-gider-goster" class="fs-4 fw-bold text-primary">₺0.00</span>
        </div>
    </div>
    
    <!-- 7. SEVKİYAT - TEK BÖLÜM -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">7</div>
            <h3 class="section-title">Sevkiyat Bilgileri</h3>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-ship"></i> Yükleme Limanı
                </label>
                <input type="text" class="form-control" name="yukleme_limani" 
                       value="<?php echo safeHtml($data['yukleme_limani'] ?? ''); ?>"
                       placeholder="Örn: Shanghai Port">
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-anchor"></i> Boşaltma Limanı
                </label>
                <input type="text" class="form-control" name="bosaltma_limani" 
                       value="<?php echo safeHtml($data['bosaltma_limani'] ?? ''); ?>"
                       placeholder="Örn: Gemlik Limanı">
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-box"></i> Konteyner Numarası
                </label>
                <input type="text" class="form-control" name="konteyner_numarasi" 
                       value="<?php echo safeHtml($data['konteyner_numarasi'] ?? ''); ?>"
                       placeholder="Örn: MSCU1234567">
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-ship"></i> Gemi Adı
                </label>
                <input type="text" class="form-control" name="gemi_adi" 
                       value="<?php echo safeHtml($data['gemi_adi'] ?? ''); ?>"
                       placeholder="Örn: MSC Marina">
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-calendar-alt"></i> Yükleme Tarihi
                </label>
                <input type="date" class="form-control" name="yukleme_tarihi" 
                       value="<?php echo $data['yukleme_tarihi'] ?? ''; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-calendar-check"></i> Tahmini Varış Tarihi
                </label>
                <input type="date" class="form-control" name="tahmini_varis_tarihi" 
                       value="<?php echo $data['tahmini_varis_tarihi'] ?? ''; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">
                    <i class="fas fa-flag-checkered"></i> TR Varış Tarihi
                </label>
                <input type="date" class="form-control" name="tr_varis_tarihi" 
                       value="<?php echo $data['tr_varis_tarihi'] ?? ''; ?>">
            </div>
            
            <!-- Nakliye Detayları -->
            <div class="col-md-4">
                <label class="form-label">Nakliye Dahil mi?</label>
                <select class="form-select" name="nakliye_dahil">
                    <option value="">Seçiniz</option>
                    <option value="evet" <?php echo ($data['nakliye_dahil'] ?? '') == 'evet' ? 'selected' : ''; ?>>
                        Evet, Nakliye Dahil
                    </option>
                    <option value="hayir" <?php echo ($data['nakliye_dahil'] ?? '') == 'hayir' ? 'selected' : ''; ?>>
                        Hayır, Ayrı Ödeme
                    </option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Navlun Ödeme Sorumlusu</label>
                <select class="form-select" name="navlun_odeme_sorumlusu">
                    <option value="">Seçiniz</option>
                    <option value="biz" <?php echo ($data['navlun_odeme_sorumlusu'] ?? '') == 'biz' ? 'selected' : ''; ?>>
                        Bizim Taraf
                    </option>
                    <option value="tedarikci" <?php echo ($data['navlun_odeme_sorumlusu'] ?? '') == 'tedarikci' ? 'selected' : ''; ?>>
                        Tedarikçi Taraf
                    </option>
                    <option value="armator" <?php echo ($data['navlun_odeme_sorumlusu'] ?? '') == 'armator' ? 'selected' : ''; ?>>
                        Armatör
                    </option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Konteyner Sorumlusu</label>
                <select class="form-select" name="konteyner_sorumlu">
                    <option value="">Seçiniz</option>
                    <option value="biz" <?php echo ($data['konteyner_sorumlu'] ?? '') == 'biz' ? 'selected' : ''; ?>>
                        Bizim Taraf
                    </option>
                    <option value="tedarikci" <?php echo ($data['konteyner_sorumlu'] ?? '') == 'tedarikci' ? 'selected' : ''; ?>>
                        Tedarikçi Taraf
                    </option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- 8. EVRAK TAKİP DURUMU -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">8</div>
            <h3 class="section-title">Evrak Takip Durumu</h3>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-file-alt"></i> Original Evrak Durumu
                </label>
                <select class="form-select" name="original_evrak_durumu">
                    <option value="bekleniyor" <?php echo ($data['original_evrak_durumu'] ?? 'bekleniyor') == 'bekleniyor' ? 'selected' : ''; ?>>
                        Bekleniyor
                    </option>
                    <option value="alindi" <?php echo ($data['original_evrak_durumu'] ?? '') == 'alindi' ? 'selected' : ''; ?>>
                        Alındı
                    </option>
                    <option value="teslim_edildi" <?php echo ($data['original_evrak_durumu'] ?? '') == 'teslim_edildi' ? 'selected' : ''; ?>>
                        Teslim Edildi
                    </option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-calendar"></i> Original Evrak Tarihi
                </label>
                <input type="date" class="form-control" name="original_evrak_tarih" 
                       value="<?php echo $data['original_evrak_tarih'] ?? ''; ?>">
            </div>
            
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-fax"></i> Telex Durumu
                </label>
                <select class="form-select" name="telex_durumu">
                    <option value="bekleniyor" <?php echo ($data['telex_durumu'] ?? 'bekleniyor') == 'bekleniyor' ? 'selected' : ''; ?>>
                        Bekleniyor
                    </option>
                    <option value="alindi" <?php echo ($data['telex_durumu'] ?? '') == 'alindi' ? 'selected' : ''; ?>>
                        Alındı
                    </option>
                    <option value="teslim_edildi" <?php echo ($data['telex_durumu'] ?? '') == 'teslim_edildi' ? 'selected' : ''; ?>>
                        Teslim Edildi
                    </option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">
                    <i class="fas fa-calendar"></i> Telex Tarihi
                </label>
                <input type="date" class="form-control" name="telex_tarih" 
                       value="<?php echo $data['telex_tarih'] ?? ''; ?>">
            </div>
            
            <div class="col-12">
                <label class="form-label">
                    <i class="fas fa-sticky-note"></i> Evrak Notları
                </label>
                <textarea class="form-control" name="evrak_notlari" rows="3"><?php echo safeHtml($data['evrak_notlari'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>
    
    <!-- 9. NOTLAR -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">9</div>
            <h3 class="section-title">Notlar</h3>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Notlar</label>
                <textarea class="form-control" name="notlar" rows="4"><?php echo safeHtml($data['ithalat_notlar'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4">
        <button type="submit" class="btn-update-form" id="submitBtn">
            <i class="fas fa-save"></i> Değişiklikleri Kaydet
        </button>
    </div>
</form>

<script>
// ============================================
// TEMEL DEĞİŞKENLER
// ============================================
const ithalatId = <?php echo $ithalat_id; ?>;
const gtipVeritabani = <?php echo json_encode($gtip_listesi_tum); ?>;

console.log('🚀 İthalat Düzenleme Sistemi Yükleniyor...');
console.log('📋 İthalat ID:', ithalatId);
console.log('📊 GTIP Veritabanı:', gtipVeritabani.length, 'kayıt');

// ============================================
// ÜRÜN FONKSİYONLARI
// ============================================

function gtipSecildiEdit(urunId) {
    const gtipInput = document.querySelector(`.gtip-input[data-urun-id="${urunId}"]`);
    if (!gtipInput) return;
    
    const gtipKodu = gtipInput.value.trim();
    if (!gtipKodu) return;
    
    const gtipBilgi = gtipVeritabani.find(g => g.gtip_kodu === gtipKodu);
    
    if (gtipBilgi) {
        const gumrukInput = document.querySelector(`.gumruk-input[data-urun-id="${urunId}"]`);
        const otvInput = document.querySelector(`.otv-input[data-urun-id="${urunId}"]`);
        const kdvInput = document.querySelector(`.kdv-input[data-urun-id="${urunId}"]`);
        
        if (gumrukInput) gumrukInput.value = gtipBilgi.varsayilan_gumruk_orani;
        if (otvInput) otvInput.value = gtipBilgi.varsayilan_otv_orani;
        if (kdvInput) kdvInput.value = gtipBilgi.varsayilan_kdv_orani;
        
        vergiHesaplaEdit(urunId);
        console.log('✅ GTIP seçildi:', gtipKodu);
    }
}

function gtipSecildiYeni() {
    const gtipInput = document.getElementById('yeni_gtip_kodu');
    if (!gtipInput) return;
    
    const gtipKodu = gtipInput.value.trim();
    const panel = document.getElementById('gtip-bilgi-panel-yeni');
    
    if (!gtipKodu) {
        if (panel) panel.style.display = 'none';
        return;
    }
    
    const gtipBilgi = gtipVeritabani.find(g => g.gtip_kodu === gtipKodu);
    
    if (gtipBilgi && panel) {
        document.getElementById('gtip-aciklama-yeni').textContent = gtipBilgi.aciklama;
        document.getElementById('gtip-gumruk-yeni').textContent = gtipBilgi.varsayilan_gumruk_orani;
        document.getElementById('gtip-otv-yeni').textContent = gtipBilgi.varsayilan_otv_orani;
        document.getElementById('gtip-kdv-yeni').textContent = gtipBilgi.varsayilan_kdv_orani;
        
        document.getElementById('yeni_gumruk_oran').value = gtipBilgi.varsayilan_gumruk_orani;
        document.getElementById('yeni_otv_oran').value = gtipBilgi.varsayilan_otv_orani;
        document.getElementById('yeni_kdv_oran').value = gtipBilgi.varsayilan_kdv_orani;
        
        panel.style.display = 'block';
        console.log('✅ GTIP seçildi (yeni):', gtipKodu);
    }
}

function vergiHesaplaEdit(urunId) {
    const miktarInput = document.querySelector(`.urun-miktar[data-urun-id="${urunId}"]`);
    const fiyatInput = document.querySelector(`.urun-fiyat[data-urun-id="${urunId}"]`);
    const kurInput = document.getElementById('usd_kur');
    
    if (!miktarInput || !fiyatInput || !kurInput) return;
    
    const miktar = parseFloat(miktarInput.value) || 0;
    const birimFiyat = parseFloat(fiyatInput.value) || 0;
    const kur = parseFloat(kurInput.value) || 0;
    
    if (miktar <= 0 || birimFiyat <= 0 || kur <= 0) return;
    
    const gumrukOran = parseFloat(document.querySelector(`.gumruk-input[data-urun-id="${urunId}"]`)?.value) || 0;
    const otvOran = parseFloat(document.querySelector(`.otv-input[data-urun-id="${urunId}"]`)?.value) || 0;
    const kdvOran = parseFloat(document.querySelector(`.kdv-input[data-urun-id="${urunId}"]`)?.value) || 20;
    
    const usdToplam = miktar * birimFiyat;
    const tlTutar = usdToplam * kur;
    
    const gumrukTutar = tlTutar * (gumrukOran / 100);
    const matrah1 = tlTutar + gumrukTutar;
    
    const otvTutar = matrah1 * (otvOran / 100);
    const matrah2 = matrah1 + otvTutar;
    
    const kdvTutar = matrah2 * (kdvOran / 100);
    
    const toplamVergi = gumrukTutar + otvTutar + kdvTutar;
    const vergiDahil = tlTutar + toplamVergi;
    
    const updateElement = (selector, value) => {
        const el = document.querySelector(selector);
        if (el) el.textContent = value;
    };
    
    updateElement(`.urun-usd-toplam[data-urun-id="${urunId}"]`, '$' + formatNumber(usdToplam));
    updateElement(`.urun-tl-tutar[data-urun-id="${urunId}"]`, '₺' + formatNumber(tlTutar));
    updateElement(`.urun-gumruk[data-urun-id="${urunId}"]`, '₺' + formatNumber(gumrukTutar));
    updateElement(`.urun-otv[data-urun-id="${urunId}"]`, '₺' + formatNumber(otvTutar));
    updateElement(`.urun-kdv[data-urun-id="${urunId}"]`, '₺' + formatNumber(kdvTutar));
    updateElement(`.urun-toplam-vergi[data-urun-id="${urunId}"]`, '₺' + formatNumber(toplamVergi));
    updateElement(`.urun-vergi-dahil[data-urun-id="${urunId}"]`, '₺' + formatNumber(vergiDahil));
}

function urunGuncelle(urunId) {
    const miktarInput = document.querySelector(`.urun-miktar[data-urun-id="${urunId}"]`);
    const fiyatInput = document.querySelector(`.urun-fiyat[data-urun-id="${urunId}"]`);
    const gtipInput = document.querySelector(`.gtip-input[data-urun-id="${urunId}"]`);
    const gumrukInput = document.querySelector(`.gumruk-input[data-urun-id="${urunId}"]`);
    const otvInput = document.querySelector(`.otv-input[data-urun-id="${urunId}"]`);
    const kdvInput = document.querySelector(`.kdv-input[data-urun-id="${urunId}"]`);
    const button = event.target;
    
    const miktar = parseFloat(miktarInput.value);
    const fiyat = parseFloat(fiyatInput.value);
    const gtipKodu = gtipInput ? gtipInput.value.trim() : '';
    const gumrukOran = parseFloat(gumrukInput.value) || 0;
    const otvOran = parseFloat(otvInput.value) || 0;
    const kdvOran = parseFloat(kdvInput.value) || 20;
    const kur = parseFloat(document.getElementById('usd_kur').value) || 0;
    
    if (isNaN(miktar) || miktar <= 0) {
        alert('❌ Geçerli miktar giriniz!');
        return;
    }
    
    if (isNaN(fiyat) || fiyat <= 0) {
        alert('❌ Geçerli fiyat giriniz!');
        return;
    }
    
    if (kur <= 0) {
        alert('❌ Lütfen kur bilgisini girin!');
        return;
    }
    
    const usdToplam = miktar * fiyat;
    const tlTutar = usdToplam * kur;
    
    const gumrukTutar = tlTutar * (gumrukOran / 100);
    const matrah1 = tlTutar + gumrukTutar;
    
    const otvTutar = matrah1 * (otvOran / 100);
    const matrah2 = matrah1 + otvTutar;
    
    const kdvTutar = matrah2 * (kdvOran / 100);
    
    const toplamVergi = gumrukTutar + otvTutar + kdvTutar;
    const vergiDahilTutar = tlTutar + toplamVergi;
    
    const gtipBilgi = gtipVeritabani.find(g => g.gtip_kodu === gtipKodu);
    const gtipAciklama = gtipBilgi ? gtipBilgi.aciklama : '';
    
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('api/urun-ithalat-guncelle.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            id: urunId,
            miktar_kg: miktar,
            birim_fiyat: fiyat,
            gtip_kodu: gtipKodu,
            gtip_aciklama: gtipAciklama,
            gumruk_oran: gumrukOran,
            gumruk_tutar: gumrukTutar,
            otv_oran: otvOran,
            otv_tutar: otvTutar,
            kdv_oran: kdvOran,
            kdv_tutar: kdvTutar,
            toplam_vergi: toplamVergi,
            vergi_dahil_tutar: vergiDahilTutar
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Ürün güncellendi!');
            vergiHesaplaEdit(urunId);
            tumToplamGuncelle();
            button.disabled = false;
            button.innerHTML = originalText;
        } else {
            alert('❌ Hata: ' + (data.message || 'Bilinmeyen hata'));
            button.disabled = false;
            button.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Bir hata oluştu!');
        button.disabled = false;
        button.innerHTML = originalText;
    });
}

function urunSil(urunId) {
    if (!confirm('Bu ürünü silmek istediğinizden emin misiniz?')) {
        return;
    }
    
    const button = event.target;
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('api/urun-ithalat-sil.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: urunId})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Ürün silindi!');
            location.reload();
        } else {
            alert('❌ Hata: ' + (data.message || 'Bilinmeyen hata'));
            button.disabled = false;
            button.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Bir hata oluştu!');
        button.disabled = false;
        button.innerHTML = originalText;
    });
}

function yeniUrunEkle() {
    const urunSelect = document.getElementById('yeni_urun_sec');
    const gtipInput = document.getElementById('yeni_gtip_kodu');
    const miktarInput = document.getElementById('yeni_miktar_kg');
    const fiyatInput = document.getElementById('yeni_birim_fiyat');
    const paraSelect = document.querySelector('select[name="para_birimi"]');
    const kur = parseFloat(document.getElementById('usd_kur').value) || 0;
    
    if (!urunSelect.value) {
        alert('❌ Lütfen bir ürün seçin!');
        return;
    }
    
    if (!gtipInput.value) {
        alert('❌ Lütfen GTIP kodu girin!');
        return;
    }
    
    if (!miktarInput.value || parseFloat(miktarInput.value) <= 0) {
        alert('❌ Geçerli bir miktar girin!');
        return;
    }
    
    if (!fiyatInput.value || parseFloat(fiyatInput.value) <= 0) {
        alert('❌ Geçerli bir fiyat girin!');
        return;
    }
    
    if (kur <= 0) {
        alert('❌ Lütfen kur bilgisini girin!');
        return;
    }
    
    const paraBirimi = paraSelect ? paraSelect.value : 'USD';
    const miktar = parseFloat(miktarInput.value);
    const fiyat = parseFloat(fiyatInput.value);
    const gtipKodu = gtipInput.value.trim();
    
    const gumrukOran = parseFloat(document.getElementById('yeni_gumruk_oran').value) || 0;
    const otvOran = parseFloat(document.getElementById('yeni_otv_oran').value) || 0;
    const kdvOran = parseFloat(document.getElementById('yeni_kdv_oran').value) || 20;
    
    const usdToplam = miktar * fiyat;
    const tlTutar = usdToplam * kur;
    
    const gumrukTutar = tlTutar * (gumrukOran / 100);
    const matrah1 = tlTutar + gumrukTutar;
    
    const otvTutar = matrah1 * (otvOran / 100);
    const matrah2 = matrah1 + otvTutar;
    
    const kdvTutar = matrah2 * (kdvOran / 100);
    
    const toplamVergi = gumrukTutar + otvTutar + kdvTutar;
    const vergiDahilTutar = tlTutar + toplamVergi;
    
    const gtipBilgi = gtipVeritabani.find(g => g.gtip_kodu === gtipKodu);
    const gtipAciklama = gtipBilgi ? gtipBilgi.aciklama : '';
    
    const addButton = event.target;
    const originalText = addButton.innerHTML;
    addButton.disabled = true;
    addButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ekleniyor...';
    
    fetch('api/urun-ithalat-ekle.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            ithalat_id: ithalatId,
            urun_katalog_id: parseInt(urunSelect.value),
            miktar_kg: miktar,
            birim_fiyat: fiyat,
            para_birimi: paraBirimi,
            gtip_kodu: gtipKodu,
            gtip_aciklama: gtipAciklama,
            gumruk_oran: gumrukOran,
            gumruk_tutar: gumrukTutar,
            otv_oran: otvOran,
            otv_tutar: otvTutar,
            kdv_oran: kdvOran,
            kdv_tutar: kdvTutar,
            toplam_vergi: toplamVergi,
            vergi_dahil_tutar: vergiDahilTutar
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Yeni ürün başarıyla eklendi!');
            location.reload();
        } else {
            alert('❌ Hata: ' + (data.message || 'Bilinmeyen hata'));
            addButton.disabled = false;
            addButton.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Bir hata oluştu!');
        addButton.disabled = false;
        addButton.innerHTML = originalText;
    });
}

function tumToplamGuncelle() {
    const miktarlar = document.querySelectorAll('.urun-miktar');
    const fiyatlar = document.querySelectorAll('.urun-fiyat');
    
    let toplamKg = 0;
    let toplamTutar = 0;
    let cesitSayisi = 0;
    
    miktarlar.forEach((input, index) => {
        const kg = parseFloat(input.value) || 0;
        const fiyat = parseFloat(fiyatlar[index].value) || 0;
        
        if (kg > 0 && fiyat > 0) {
            toplamKg += kg;
            toplamTutar += (kg * fiyat);
            cesitSayisi++;
        }
    });
    
    const cesitElement = document.getElementById('toplam-cesit-edit');
    const kgElement = document.getElementById('toplam-kg-edit');
    const tutarElement = document.getElementById('toplam-tutar-edit');
    
    if (cesitElement) cesitElement.textContent = cesitSayisi;
    if (kgElement) kgElement.textContent = formatNumber(toplamKg);
    if (tutarElement) tutarElement.textContent = '$' + formatNumber(toplamTutar);
}

function formatNumber(num) {
    return parseFloat(num).toLocaleString('tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// ============================================
// SAYFA YÜKLENDİĞİNDE
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('📦 Sayfa yüklendi, sistemler başlatılıyor...');
    
    // ============================================
    // GİDER HESAPLAMA
    // ============================================
    
    const giderInputlar = [
        'gumruk_ucreti',
        'tarim_hizmet_ucreti', 
        'nakliye_bedeli',
        'sigorta_bedeli',
        'ardiye_ucreti',
        'demoraj_ucreti'
    ];
    
    function giderHesapla() {
        let toplam = 0;
        let varMi = false;
        
        giderInputlar.forEach(name => {
            const input = document.querySelector(`input[name="${name}"]`);
            if (input) {
                const deger = parseFloat(input.value) || 0;
                toplam += deger;
                if (deger > 0) varMi = true;
            }
        });
        
        const ozetDiv = document.getElementById('gider-ozet');
        const toplamSpan = document.getElementById('toplam-gider-goster');
        
        if (ozetDiv && toplamSpan) {
            if (varMi) {
                ozetDiv.style.display = 'block';
                toplamSpan.textContent = '₺' + toplam.toLocaleString('tr-TR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            } else {
                ozetDiv.style.display = 'none';
            }
        }
    }
    
    giderInputlar.forEach(name => {
        const input = document.querySelector(`input[name="${name}"]`);
        if (input) {
            input.addEventListener('input', giderHesapla);
            console.log('✅ Event listener eklendi:', name);
        }
    });
    
    setTimeout(giderHesapla, 100);
    
    // ============================================
    // İTHALAT DURUMU DEĞİŞİMİ
    // ============================================
    
    const durumSelect = document.getElementById('ithalat_durumu');
    if (durumSelect) {
        durumSelect.addEventListener('change', function() {
            const durum = this.value;
            const aciklamalar = {
                'siparis_verildi': 'Sipariş tedarikçiye verildi, üretim/hazırlık aşamasında',
                'yolda': 'Ürün gemi veya uçakla yola çıktı',
                'transitte': 'Transit limanda aktarma bekliyor',
                'limanda': 'Türkiye limanına ulaştı, gümrükleme aşamasında',
                'teslim_edildi': 'Ürün depoya teslim edildi, ithalat tamamlandı'
            };
            
            const aciklamaEl = document.getElementById('durum-aciklama');
            if (aciklamaEl) {
                aciklamaEl.innerHTML = aciklamalar[durum] || 'Bilinmiyor';
            }
        });
        console.log('✅ İthalat durumu dinleyicisi aktif');
    }
    
    // ============================================
    // FORM SUBMIT - DEBUG MODLU
    // ============================================
    
    const form = document.getElementById('editIthalatForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            console.log('📤 Form gönderiliyor...');
            
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn.disabled) {
                console.warn('⚠️ Buton zaten disabled!');
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
            
            const formData = new FormData(this);
            
            console.log('📋 Gönderilen veriler:');
            for (let [key, value] of formData.entries()) {
                if (value) console.log(`  ${key}: ${value}`);
            }
            
            fetch('api/update-import.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('📥 Response alındı:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('📄 Raw response:', text.substring(0, 500));
                
                try {
                    const data = JSON.parse(text);
                    console.log('✅ JSON parse başarılı:', data);
                    
                    if(data.success) {
                        console.log('✅ Başarılı güncelleme!');
                        
                        if (data.debug_info) {
                            console.log('💾 Debug bilgileri:', data.debug_info);
                        }
                        
                        alert('✅ İthalat kaydı başarıyla güncellendi!\n\nToplam Gider: ₺' + (data.toplam_gider || '0'));
                        
                        setTimeout(() => {
                            window.location.href = '?page=ithalat-detay&id=' + ithalatId;
                        }, 500);
                    } else {
                        console.error('❌ Hata:', data);
                        alert('❌ Hata: ' + (data.message || 'Bilinmeyen hata'));
                        
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-save"></i> Değişiklikleri Kaydet';
                    }
                } catch(e) {
                    console.error('❌ JSON parse hatası:', e);
                    console.error('📄 Full response:', text);
                    alert('❌ Sunucu geçersiz yanıt döndü! Konsola bakın (F12).');
                    
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> Değişiklikleri Kaydet';
                }
            })
            .catch(error => {
                console.error('❌ Fetch hatası:', error);
                alert('❌ Bir hata oluştu!\n\n' + error.message);
                
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Değişiklikleri Kaydet';
            });
        });
        
        console.log('✅ Form submit dinleyicisi aktif');
    }
    
    console.log('✅✅✅ TÜM SİSTEMLER HAZIR! ✅✅✅');
});
</script>