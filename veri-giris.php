<?php
/**
 * Veri Giriş Formu Sayfası
 * Kapsamlı İthalat Veri Giriş Formu
 * ✅ GÜNCELLEME: Firma seçimi eklendi
 */

// ============================================
// 👇 BURAYA EKLE (Dosyanın EN BAŞINDA)
// ============================================

// Global değişkenler
global $URUN_TIPLERI, $KALITE_TERIMLERI, $KOLI_MARKALARI, 
       $GTIP_TIPLERI, $ON_ODEME_ORANLARI, $PARA_BIRIMLERI;

// ✅ Dinamik ülke listesi
$ULKELER = getUlkeler();
$ULKELER_BOLGE = getUlkelerByRegion();

// ✅ Aktif ürün kataloğunu çek
$db = getDB();
$sql_urunler = "SELECT * FROM urun_katalog WHERE aktif = 1 ORDER BY urun_tipi, urun_cinsi, kalibre";
$stmt_urunler = $db->query($sql_urunler);
$urun_katalog = $stmt_urunler->fetchAll();

// ✅ FİRMA LİSTESİNİ ÇEK (YENİ)
$firmalar = getIthalatciFirmalar();
?>

<style>
    .section-box {
        background: #f8f9fa;
        border: 2px solid #e1e8ed;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 30px;
        transition: all 0.3s ease;
    }
    
    .section-box:hover {
        border-color: #3498db;
        box-shadow: 0 8px 25px rgba(52, 152, 219, 0.15);
    }
    
    .section-header {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 3px solid #3498db;
    }
    
    .section-number {
        background: linear-gradient(135deg, #3498db, #2980b9);
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
        box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
    }
    
    .section-title {
        font-size: 1.3rem;
        font-weight: 600;
        color: #2c3e50;
        margin: 0;
    }
    
    .form-label {
        font-weight: 600;
        color: #4a5568;
        margin-bottom: 8px;
    }
    
    .form-control, .form-select {
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 15px;
        transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }
    
    .info-box {
        background: #e3f2fd;
        border-left: 4px solid #2196f3;
        padding: 15px;
        border-radius: 0 8px 8px 0;
        margin: 15px 0;
        font-size: 0.9rem;
        color: #1565c0;
    }
    
    
    .warning-box {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 15px;
        border-radius: 0 8px 8px 0;
        margin: 15px 0;
        font-size: 0.9rem;
        color: #856404;
    }
    
    /* ✅ YENİ: Ürün Ekleme Stili */
    .urun-ekleme-panel {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        border: 2px solid #4caf50;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
    }
    
    .urun-ekleme-panel h5 {
        color: #2e7d32;
        font-weight: 600;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .urun-select-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
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
        box-shadow: 0 4px 10px rgba(76, 175, 80, 0.3);
    }
    
    .btn-urun-ekle:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(76, 175, 80, 0.4);
    }
    
    .eklenmis-urunler {
        margin-top: 30px;
    }
    
    .eklenmis-urunler h5 {
        color: #2c3e50;
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #3498db;
    }
    
    .urun-card {
        background: white;
        border: 2px solid #e1e8ed;
        border-left: 5px solid #3498db;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .urun-card:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .urun-card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 12px;
    }
    
    .urun-card-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2c3e50;
    }
    
    .urun-card-subtitle {
        font-size: 0.9rem;
        color: #7f8c8d;
        font-style: italic;
        margin-top: 3px;
    }
    
    .urun-card-details {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #ecf0f1;
    }
    
    .urun-detail-item {
        text-align: center;
    }
    
    .urun-detail-label {
        font-size: 0.85rem;
        color: #7f8c8d;
        margin-bottom: 5px;
    }
    
    .urun-detail-value {
        font-size: 1.1rem;
        font-weight: bold;
        color: #2c3e50;
    }
    
    .urun-card-actions {
        display: flex;
        gap: 8px;
    }
    
    .btn-urun-action {
        padding: 6px 12px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.85rem;
    }
    
    .btn-urun-duzenle {
        background: #f39c12;
        color: white;
    }
    
    .btn-urun-sil {
        background: #e74c3c;
        color: white;
    }
    
    .btn-urun-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    
    .urun-toplam {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 10px;
        margin-top: 20px;
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }
    
    .urun-toplam-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        text-align: center;
    }
    
    .urun-toplam-item label {
        font-size: 0.9rem;
        opacity: 0.9;
        display: block;
        margin-bottom: 5px;
    }
    
    .urun-toplam-item .value {
        font-size: 1.8rem;
        font-weight: bold;
    }
    
    .no-urun-message {
        text-align: center;
        padding: 40px;
        color: #7f8c8d;
        background: #f8f9fa;
        border-radius: 8px;
        border: 2px dashed #dee2e6;
    }
    
    .no-urun-message i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    
    .btn-submit-form {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 40px;
        border: none;
        border-radius: 50px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }
    
    .btn-submit-form:hover:not(:disabled) {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
    }
    
    .btn-submit-form:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }
    
    .checkbox-group, .radio-group {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .checkbox-item, .radio-item {
        display: flex;
        align-items: center;
        background: white;
        padding: 10px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .checkbox-item:hover, .radio-item:hover {
        border-color: #3498db;
        background: #f7fafc;
    }
    
    .checkbox-item input, .radio-item input {
        margin-right: 8px;
    }
    
    .quick-add-country {
        display: flex;
        gap: 10px;
        margin-top: 10px;
        padding: 10px;
        background: #e8f5e9;
        border-radius: 8px;
        align-items: center;
    }
    
    .quick-add-country input {
        flex: 1;
    }
    
    @media (max-width: 768px) {
        .urun-select-row {
            grid-template-columns: 1fr;
        }
        
        .urun-card-details {
            grid-template-columns: 1fr;
        }
        
        .urun-toplam-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<h2 class="page-title">
    <i class="fas fa-plus-circle"></i> Yeni İthalat Kaydı Oluştur
</h2>

<form id="ithalatForm" method="POST" action="api/save-import.php">>
    
    <!-- 1. TEDARİKÇİ FİRMA BİLGİLERİ -->
    <div class="section-box firma-secim-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
    <div class="section-header" style="border-bottom-color: rgba(255,255,255,0.3);">
        <div class="section-number" style="background: white; color: #667eea; box-shadow: 0 4px 15px rgba(255,255,255,0.3);">
            0
        </div>
        <h3 class="section-title" style="color: white;">
            <i class="fas fa-building"></i> İthalatçı Firma Seçimi
        </h3>
    </div>
    
    <div class="row g-3">
        <div class="col-md-12">
            <label for="ithalatci_firma_id" class="form-label" style="color: white; font-size: 1.2rem; font-weight: 600;">
                <i class="fas fa-hand-point-right"></i> Hangi Firma Adına İthalat Yapılıyor? *
            </label>
            <select class="form-select form-select-lg" 
                    id="ithalatci_firma_id" 
                    name="ithalatci_firma_id" 
                    required 
                    style="border: 3px solid white; font-size: 1.2rem; font-weight: 600; padding: 15px;">
                <option value="">▼ Firma Seçiniz...</option>
                <?php foreach($firmalar as $firma): ?>
                    <option value="<?php echo $firma['id']; ?>" 
                            data-prefix="<?php echo $firma['dosya_no_prefix']; ?>"
                            data-renk="<?php echo $firma['renk_kodu']; ?>">
                        <?php 
                        // İkon belirle
                        $icon = '🏢';
                        if ($firma['firma_kodu'] == 'balik_dunyasi') {
                            $icon = '🐟';
                        } elseif ($firma['firma_kodu'] == 'fishtime') {
                            $icon = '🦐';
                        }
                        echo $icon . ' ' . safeHtml($firma['firma_adi']) . ' (' . $firma['dosya_no_prefix'] . ')';
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <!-- Bilgilendirme -->
            <div class="firma-info-box" id="firma-info-box" style="
                display: none;
                margin-top: 15px;
                padding: 15px;
                background: rgba(255,255,255,0.2);
                border-radius: 8px;
                color: white;
            ">
                <div class="d-flex align-items-center gap-3">
                    <div style="font-size: 2.5rem;" id="firma-icon">🏢</div>
                    <div style="flex: 1;">
                        <div style="font-size: 1.1rem; font-weight: 600; margin-bottom: 5px;">
                            <span id="firma-adi-goster">-</span>
                        </div>
                        <div style="font-size: 0.9rem; opacity: 0.9;">
                            📋 Dosya No Formatı: <strong id="dosya-format">-</strong>
                        </div>
                        <div style="font-size: 0.85rem; opacity: 0.8; margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> Bu firmaya özel otomatik dosya numarası üretilecek
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Firma seçim kutusu özel stili */
.firma-secim-box {
    animation: slideInDown 0.5s ease-out;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.firma-secim-box .form-select {
    transition: all 0.3s ease;
}

.firma-secim-box .form-select:focus {
    border-color: white !important;
    box-shadow: 0 0 0 5px rgba(255,255,255,0.3) !important;
    transform: scale(1.02);
}

.firma-info-box {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
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

.siparis-no-input-group .form-control::placeholder {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-weight: normal;
    font-size: 0.9rem;
}
</style>

<script>
// Firma seçildiğinde bilgi göster
document.getElementById('ithalatci_firma_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const infoBox = document.getElementById('firma-info-box');
    
    if (this.value) {
        const firmaAdi = selectedOption.textContent.trim();
        const prefix = selectedOption.getAttribute('data-prefix');
        const yil = new Date().getFullYear();
        
        // İkon belirle
        let icon = '🏢';
        if (firmaAdi.includes('Balık Dünyası') || firmaAdi.includes('🐟')) {
            icon = '🐟';
        } else if (firmaAdi.includes('Fishtime') || firmaAdi.includes('🦐')) {
            icon = '🦐';
        }
        
        // Bilgileri güncelle
        document.getElementById('firma-icon').textContent = icon;
        document.getElementById('firma-adi-goster').textContent = firmaAdi.split('(')[0].trim();
        document.getElementById('dosya-format').textContent = `${prefix}-${yil}-0001, ${prefix}-${yil}-0002...`;
        
        // Göster
        infoBox.style.display = 'block';
        
        console.log('✅ Firma seçildi:', firmaAdi, '| Prefix:', prefix);
    } else {
        // Gizle
        infoBox.style.display = 'none';
    }
});

// Form submit kontrolü - Firma seçimi zorunlu
document.getElementById('ithalatForm').addEventListener('submit', function(e) {
    const firmaId = document.getElementById('ithalatci_firma_id').value;
    
    if (!firmaId) {
        e.preventDefault();
        alert('❌ Lütfen önce İthalatçı Firma seçin!');
        document.getElementById('ithalatci_firma_id').focus();
        document.getElementById('ithalatci_firma_id').style.borderColor = 'red';
        return false;
    }
    
    console.log('📋 Form gönderiliyor - Firma ID:', firmaId);
    // Diğer kontroller devam edecek...
});
</script>
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">1</div>
            <h3 class="section-title">Tedarikçi Firma Bilgileri</h3>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="tedarikci_firma" class="form-label">Tedarikçi Firma Adı *</label>
                <input type="text" class="form-control" id="tedarikci_firma" name="tedarikci_firma" required>
            </div>
            
            <div class="col-md-6">
    <label for="tedarikci_ulke" class="form-label">Tedarikçi Ülke *</label>
    <select class="form-select" id="tedarikci_ulke" name="tedarikci_ulke" required>
        <option value="">Seçiniz</option>
        <?php foreach($ULKELER_BOLGE as $bolge => $ulkeler): ?>
            <optgroup label="<?php echo safeHtml($bolge); ?>">
                <?php foreach($ulkeler as $key => $value): ?>
                    <option value="<?php echo $key; ?>"><?php echo safeHtml($value); ?></option>
                <?php endforeach; ?>
            </optgroup>
        <?php endforeach; ?>
    </select>
    <div class="quick-add-country">
        <i class="fas fa-info-circle text-success"></i>
        <small class="text-muted">Ülke bulamadınız mı? 
            <a href="?page=ulke-yonetimi" target="_blank" class="text-success fw-bold">
                <i class="fas fa-plus"></i> Ülke Yönetimi'ne git
            </a>
        </small>
    </div>
</div>

<!-- ✅ YENİ ALAN: Tedarikçi Sipariş Numarası -->
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
               placeholder="Örn: PO-2024-1234, ORD-5678..."
               maxlength="100">
    </div>
    <small class="text-muted">
        <i class="fas fa-info-circle"></i> Opsiyonel - Tedarikçinin verdiği sipariş no
    </small>
</div>
            
            <div class="col-md-6">
                <label for="mensei_ulke" class="form-label">Menşei Ülke</label>
                <select class="form-select" id="mensei_ulke" name="mensei_ulke">
                    <option value="">Seçiniz</option>
                    <?php foreach($ULKELER_BOLGE as $bolge => $ulkeler): ?>
                        <optgroup label="<?php echo safeHtml($bolge); ?>">
                            <?php foreach($ulkeler as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo safeHtml($value); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Ürünün gerçek üretim ülkesi</small>
            </div>
            
            <div class="col-md-6">
                <label for="transit_detay" class="form-label">Transit Detay (varsa)</label>
                <textarea class="form-control" id="transit_detay" name="transit_detay" 
                          rows="2" placeholder="Transit firma veya liman bilgisi..."></textarea>
                <small class="text-muted">Opsiyonel - Gerekirse manuel yazın</small>
            </div>
        </div>
    </div>
    
    <!-- 2. SİPARİŞ TARİH BİLGİLERİ -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">2</div>
            <h3 class="section-title">Sipariş Tarih Bilgileri</h3>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="siparis_tarihi" class="form-label">Sipariş Verdiğimiz Tarih *</label>
                <input type="date" class="form-control" id="siparis_tarihi" name="siparis_tarihi" required>
            </div>
            <div class="col-md-4">
                <label for="ilk_siparis_tarihi" class="form-label">İlk Sipariş Tarihi</label>
                <input type="date" class="form-control" id="ilk_siparis_tarihi" name="ilk_siparis_tarihi">
            </div>
            <div class="col-md-4">
                <label for="tahmini_teslim_ayi" class="form-label">Tahmini Teslim Ayı</label>
                <input type="month" class="form-control" id="tahmini_teslim_ayi" name="tahmini_teslim_ayi">
            </div>
            
            <div class="col-md-6">
                <label for="yukleme_tarihi" class="form-label">
                    <i class="fas fa-ship"></i> Yükleme Tarihi
                    <small class="text-muted">(Opsiyonel - Sonradan eklenebilir)</small>
                </label>
                <input type="date" class="form-control" id="yukleme_tarihi" name="yukleme_tarihi">
            </div>
            <div class="col-md-6">
                <label for="tahmini_varis_tarihi" class="form-label">
                    <i class="fas fa-calendar-check"></i> Tahmini Varış Tarihi
                    <small class="text-muted">(Opsiyonel - Sonradan eklenebilir)</small>
                </label>
                <input type="date" class="form-control" id="tahmini_varis_tarihi" name="tahmini_varis_tarihi">
            </div>
        </div>
        <div class="info-box">
            <i class="fas fa-info-circle"></i> Yükleme ve tahmini varış tarihleri şimdi girilmese de sonradan düzenleme sayfasından eklenebilir. Bu bilgiler konşimento takibi için önemlidir.
        </div>
    </div>
    
    <!-- ✅ 3. ÜRÜN BİLGİLERİ (YENİ SİSTEM) -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">3</div>
            <h3 class="section-title">Ürün Bilgileri</h3>
        </div>
        
        <!-- Ürün Ekleme Paneli -->
<div class="urun-ekleme-panel">
    <h5>
        <i class="fas fa-plus-circle"></i> Ürün Ekle
    </h5>
    
    <!-- ✅ GTIP Listesini PHP'de hazırla -->
    <?php
    $sql_gtip = "SELECT gtip_kodu, aciklama, varsayilan_gumruk_orani, varsayilan_otv_orani, varsayilan_kdv_orani 
                 FROM gtip_kodlari 
                 WHERE aktif = 1 
                 ORDER BY gtip_kodu";
    $gtip_listesi_tum = $db->query($sql_gtip)->fetchAll();
    ?>
    
    <div class="urun-select-row" style="grid-template-columns: 1.5fr 1fr 0.8fr 0.8fr auto; gap: 10px;">
        <div>
            <label class="form-label">Ürün Seç *</label>
            <select class="form-select" id="urun_sec">
                <option value="">Ürün seçiniz...</option>
                <?php 
$current_tip = '';
foreach($urun_katalog as $urun): 
    if ($current_tip != $urun['urun_tipi']) {
        if ($current_tip != '') echo '</optgroup>';
        $current_tip = $urun['urun_tipi'];
        echo '<optgroup label="' . safeHtml($URUN_TIPLERI[$current_tip] ?? $current_tip) . '">';
    }
    
    // ✅ YENİ: Detaylı görüntü metni oluştur
    $option_text = $urun['urun_cinsi'];
    
    // Kalibre ekle
    if (!empty($urun['kalibre'])) {
        $option_text .= ' (' . $urun['kalibre'] . ')';
    }
    
    // Latince isim kısalt (ilk 15 karakter)
    $latince_kisalt = mb_substr($urun['urun_latince_isim'], 0, 15);
    if (mb_strlen($urun['urun_latince_isim']) > 15) {
        $latince_kisalt .= '.';
    }
    $option_text .= ' *' . $latince_kisalt . '*';
    
    // GLZ oranı ekle
    if (!empty($urun['glz_orani'])) {
        $option_text .= ' | GLZ: ' . $urun['glz_orani'] . '%';
    }
    
    // Kalite terimi ekle
    if (!empty($urun['kalite_terimi'])) {
        $option_text .= ' | ' . $urun['kalite_terimi'];
    }
?>
    <option value="<?php echo $urun['id']; ?>" 
            data-latince="<?php echo safeHtml($urun['urun_latince_isim']); ?>"
            data-cinsi="<?php echo safeHtml($urun['urun_cinsi']); ?>"
            data-kalibre="<?php echo safeHtml($urun['kalibre'] ?? ''); ?>"
            data-tipi="<?php echo safeHtml($urun['urun_tipi']); ?>"
            data-glz="<?php echo safeHtml($urun['glz_orani'] ?? ''); ?>"
            data-kalite="<?php echo safeHtml($urun['kalite_terimi'] ?? ''); ?>">
        <?php echo safeHtml($option_text); ?>
    </option>
<?php 
endforeach; 
if ($current_tip != '') echo '</optgroup>';
?>
            </select>
        </div>
        
        <!-- ✅ YENİ: GTIP Kodu Alanı -->
        <div>
            <label class="form-label">
                <i class="fas fa-barcode"></i> GTIP Kodu *
            </label>
            <input type="text" 
                   class="form-control" 
                   id="gtip_kodu_input" 
                   placeholder="1605.51.00" 
                   list="gtipList"
                   onchange="gtipSecildi()">
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
        
        <div>
            <label class="form-label">Miktar (KG) *</label>
            <input type="number" class="form-control" id="miktar_kg" 
                   placeholder="0" min="0" step="0.01" oninput="vergiHesapla()">
        </div>
        <div>
            <label class="form-label">Birim Fiyat ($) *</label>
            <input type="number" class="form-control" id="birim_fiyat" 
                   placeholder="0.00" min="0" step="0.01" oninput="vergiHesapla()">
        </div>
        <div>
            <label class="form-label">&nbsp;</label>
            <button type="button" class="btn-urun-ekle" onclick="urunEkle()">
                <i class="fas fa-plus"></i> Ekle
            </button>
        </div>
    </div>
    
    <!-- ✅ YENİ: GTIP Bilgi Paneli (Otomatik açılır) -->
    <div id="gtip-bilgi-panel" style="display: none; margin-top: 15px; padding: 15px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-radius: 10px; border: 2px solid #4caf50; box-shadow: 0 3px 10px rgba(76, 175, 80, 0.2);">
        <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 15px;">
            <div style="flex: 1; min-width: 250px;">
                <div style="font-size: 0.85rem; color: #2e7d32; font-weight: 600; margin-bottom: 5px;">
                    📋 GTIP AÇIKLAMA:
                </div>
                <div id="gtip-aciklama-text" style="color: #1b5e20; font-weight: 500;"></div>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                <span style="background: linear-gradient(135deg, #4caf50, #388e3c); color: white; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.9rem;">
                    Gümrük: <strong id="gtip-gumruk">0</strong>%
                </span>
                <span style="background: linear-gradient(135deg, #ff9800, #f57c00); color: white; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.9rem;">
                    ÖTV: <strong id="gtip-otv">0</strong>%
                </span>
                <span style="background: linear-gradient(135deg, #2196f3, #1976d2); color: white; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.9rem;">
                    KDV: <strong id="gtip-kdv">20</strong>%
                </span>
            </div>
        </div>
        
        <!-- ✅ YENİ: Vergi Hesaplama Önizlemesi -->
        <div id="vergi-hesap-panel" style="display: none; margin-top: 15px; padding: 15px; background: white; border-radius: 8px; border: 1px solid #4caf50;">
            <div style="font-size: 0.9rem; color: #2e7d32; font-weight: 600; margin-bottom: 10px;">
                💰 VERGİ HESAPLAMA ÖNİZLEMESİ:
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; font-size: 0.85rem;">
                <div>
                    <span style="color: #666;">TL Tutar:</span>
                    <strong id="onizleme-tl-tutar" style="color: #2e7d32;">₺0.00</strong>
                </div>
                <div>
                    <span style="color: #666;">Gümrük:</span>
                    <strong id="onizleme-gumruk" style="color: #4caf50;">₺0.00</strong>
                </div>
                <div>
                    <span style="color: #666;">ÖTV:</span>
                    <strong id="onizleme-otv" style="color: #ff9800;">₺0.00</strong>
                </div>
                <div>
                    <span style="color: #666;">KDV:</span>
                    <strong id="onizleme-kdv" style="color: #2196f3;">₺0.00</strong>
                </div>
                <div style="grid-column: 1 / -1; padding-top: 10px; border-top: 2px solid #4caf50; margin-top: 5px;">
                    <span style="color: #666;">Toplam Vergi:</span>
                    <strong id="onizleme-toplam-vergi" style="color: #e65100; font-size: 1.1rem;">₺0.00</strong>
                    <br>
                    <span style="color: #666;">Vergi Dahil:</span>
                    <strong id="onizleme-vergi-dahil" style="color: #1b5e20; font-size: 1.2rem;">₺0.00</strong>
                </div>
            </div>
        </div>
        
        <!-- ✅ Vergi Oranlarını Düzenleme -->
        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #4caf50;">
            <button type="button" class="btn btn-sm btn-outline-success" onclick="toggleVergiDuzenle()" style="font-size: 0.85rem;">
                <i class="fas fa-edit"></i> Vergi Oranlarını Düzenle
            </button>
            
            <div id="vergi-duzenle-panel" style="display: none; margin-top: 10px; padding: 12px; background: #f1f8e9; border-radius: 6px;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                    <div>
                        <label style="font-size: 0.8rem; color: #666;">Gümrük (%)</label>
                        <input type="number" class="form-control form-control-sm" id="gumruk_oran_edit" 
                               step="0.01" min="0" max="100" oninput="vergiHesapla()">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; color: #666;">ÖTV (%)</label>
                        <input type="number" class="form-control form-control-sm" id="otv_oran_edit" 
                               step="0.01" min="0" max="100" oninput="vergiHesapla()">
                    </div>
                    <div>
                        <label style="font-size: 0.8rem; color: #666;">KDV (%)</label>
                        <input type="number" class="form-control form-control-sm" id="kdv_oran_edit" 
                               step="0.01" min="0" max="100" oninput="vergiHesapla()">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-3">
        <small class="text-muted">
            <i class="fas fa-lightbulb"></i> 
            <strong>İpucu:</strong> Katalogda olmayan bir ürün mü? 
            <a href="?page=urun-yonetimi" target="_blank" class="text-success fw-bold">
                Ürün Yönetimi'nden ekleyebilirsiniz
            </a>
            | 
            <a href="?page=gtip-yonetimi" target="_blank" class="text-primary fw-bold">
                GTIP Yönetimi'nden yeni kod ekleyebilirsiniz
            </a>
        </small>
    </div>
</div>

        
        <!-- Eklenmiş Ürünler -->
        <div class="eklenmis-urunler" id="eklenmis-urunler-container">
            <h5><i class="fas fa-list"></i> Eklenmiş Ürünler</h5>
            <div id="urun-listesi">
                <div class="no-urun-message">
                    <i class="fas fa-box-open"></i>
                    <p class="mb-0">Henüz ürün eklenmedi. Yukarıdaki formdan ürün ekleyebilirsiniz.</p>
                </div>
            </div>
        </div>
        
        <!-- Toplam Özet -->
        <div class="urun-toplam" id="urun-toplam" style="display: none;">
            <div class="urun-toplam-grid">
                <div class="urun-toplam-item">
                    <label>Toplam Ürün Çeşidi</label>
                    <div class="value" id="toplam-cesit">0</div>
                </div>
                <div class="urun-toplam-item">
                    <label>Toplam Miktar (KG)</label>
                    <div class="value" id="toplam-kg">0</div>
                </div>
                <div class="urun-toplam-item">
                    <label>Toplam Tutar</label>
                    <div class="value" id="toplam-tutar">$0.00</div>
                </div>
            </div>
        </div>
        
        <!-- Hidden Input: Ürünler JSON -->
        <input type="hidden" name="urunler_json" id="urunler_json">
    </div>

    <!-- DİĞER BÖLÜMLER AYNEN KALACAK (4-12) -->
    
    <!-- 4. GTIP KODU (16 ile başlayanlar) -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">4</div>
            <h3 class="section-title">GTIP Kodu (16 ile başlayanlar) - İleri İşlenmiş Ürünler</h3>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="gtip_kodu" class="form-label">GTIP Kodu</label>
                <input type="text" class="form-control" id="gtip_kodu" name="gtip_kodu" 
                       placeholder="16... veya 03...">
            </div>
            <div class="col-md-4">
                <label for="gtip_tipi" class="form-label">GTIP Kod Tipi</label>
                <select class="form-select" id="gtip_tipi" name="gtip_tipi">
                    <option value="">Seçiniz</option>
                    <?php foreach($GTIP_TIPLERI as $key => $value): ?>
                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="ithal_kontrol_belgesi_16" class="form-label">İthal Kontrol Belgesi (16 için)</label>
                <select class="form-select" id="ithal_kontrol_belgesi_16" name="ithal_kontrol_belgesi_16">
                    <option value="">Seçiniz</option>
                    <option value="alindi">Alındı</option>
                    <option value="basvuru_yapildi">Başvuru Yapıldı</option>
                    <option value="beklemede">Beklemede</option>
                    <option value="gerekli_degil">Gerekli Değil</option>
                </select>
            </div>
            <div class="col-md-6">
                <label for="tarim_bakanlik_onay_tarihi" class="form-label">Tarım Bakanlığı Onay Tarihi</label>
                <input type="date" class="form-control" id="tarim_bakanlik_onay_tarihi" name="tarim_bakanlik_onay_tarihi">
            </div>
        </div>
        <div class="info-box">
            <i class="fas fa-info-circle"></i> Ürün içerik teyidi Ankara Tarım ile yapılmalı, ödeme öncesi İthal Kontrol Belgesi mutlaka alınmalıdır.
        </div>
    </div>
    
    <!-- 5. GTIP KODU (03 ile başlayanlar) -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">5</div>
            <h3 class="section-title">Dondurulmuş Ürünler - Kontrol Belgesi Bilgileri</h3>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="kontrol_belgesi_suresi" class="form-label">Kontrol Belgesi Alınma Süresi</label>
                <select class="form-select" id="kontrol_belgesi_suresi" name="kontrol_belgesi_suresi">
                    <option value="">Seçiniz</option>
                    <option value="2_hafta">2 Hafta Önceden</option>
                    <option value="1_hafta">1 Hafta Önceden</option>
                    <option value="diger">Diğer</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="tek_fabrika" class="form-label">Tek Fabrika mı?</label>
                <select class="form-select" id="tek_fabrika" name="tek_fabrika">
                    <option value="">Seçiniz</option>
                    <option value="evet">Evet</option>
                    <option value="hayir">Hayır</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="toplam_siparis_kg" class="form-label">Toplam Sipariş KG</label>
                <input type="number" class="form-control" id="toplam_siparis_kg" name="toplam_siparis_kg" 
                       min="0" step="0.01" readonly>
                <small class="text-muted">Otomatik hesaplanır</small>
            </div>
        </div>
    </div>
    
    <!-- 6. KOLİ TASARIM VE MARKA -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">6</div>
            <h3 class="section-title">Koli Tasarım ve Marka Bilgileri</h3>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Sipariş Verilen Ürünün Koli Tasarım Markası</label>
                <div class="checkbox-group">
                    <?php foreach($KOLI_MARKALARI as $key => $value): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" id="marka_<?php echo $key; ?>" 
                                   name="koli_tasarim_marka[]" value="<?php echo $key; ?>">
                            <label for="marka_<?php echo $key; ?>"><?php echo $value; ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 7. TESLİM ŞEKLİ VE NAKLİYE -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">7</div>
            <h3 class="section-title">Teslim Şekli ve Nakliye</h3>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nakliye Dahil mi?</label>
                <div class="radio-group">
                    <div class="radio-item">
                        <input type="radio" id="nakliye_evet" name="nakliye_dahil" value="evet">
                        <label for="nakliye_evet">Evet, Nakliye Dahil</label>
                    </div>
                    <div class="radio-item">
                        <input type="radio" id="nakliye_hayir" name="nakliye_dahil" value="hayir">
                        <label for="nakliye_hayir">Hayır, Ayrı Ödeme</label>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <label for="navlun_odeme_sorumlusu" class="form-label">Navlun Kim Tarafından Ödenecek?</label>
                <select class="form-select" id="navlun_odeme_sorumlusu" name="navlun_odeme_sorumlusu">
                    <option value="">Seçiniz</option>
                    <option value="biz">Bizim Taraf</option>
                    <option value="tedarikci">Tedarikçi Taraf</option>
                    <option value="armator">Armatör</option>
                </select>
            </div>
            <div class="col-12">
                <label for="depozito_anlasmasi" class="form-label">Depozito Anlaşması</label>
                <textarea class="form-control" id="depozito_anlasmasi" name="depozito_anlasmasi" 
                          rows="2" placeholder="Depozito ve vade anlaşmaları detayı..."></textarea>
            </div>
        </div>
    </div>
    
    <!-- 8. KONTEYNER VE SİGORTA -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">8</div>
            <h3 class="section-title">Konteyner ve Sigorta Bilgileri</h3>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="konteyner_sorumlu" class="form-label">Konteyner Kim Tarafından Sağlandı?</label>
                <select class="form-select" id="konteyner_sorumlu" name="konteyner_sorumlu">
                    <option value="">Seçiniz</option>
                    <option value="biz">Bizim Taraf</option>
                    <option value="tedarikci">Tedarikçi Taraf</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Sigorta İşlemi Yapıldı mı?</label>
                <div class="radio-group">
                    <div class="radio-item">
                        <input type="radio" id="sigorta_evet" name="sigorta_durumu" value="evet">
                        <label for="sigorta_evet">Evet</label>
                    </div>
                    <div class="radio-item">
                        <input type="radio" id="sigorta_hayir" name="sigorta_durumu" value="hayir">
                        <label for="sigorta_hayir">Hayır</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i> Sigortasız ürün kesinlikle kendi firmalarımız için kabul edilmiyor olmalı.
        </div>
    </div>
    
    <!-- 9. ALIŞ FİYAT BİLGİLERİ -->
    <!-- 9. KUR BİLGİSİ (YENİ) -->
<div class="section-box">
    <div class="section-header">
        <div class="section-number">9</div>
        <h3 class="section-title">Kur Bilgileri</h3>
    </div>
    <div class="row g-3">
        <div class="col-md-4">
            <label for="usd_kur" class="form-label">
                <i class="fas fa-dollar-sign"></i> USD Kuru (TL) *
            </label>
            <input type="number" class="form-control" id="usd_kur" name="usd_kur" 
                   step="0.0001" min="0" placeholder="34.5000" required>
            <small class="text-muted">
                <i class="fas fa-info-circle"></i> 
                <a href="https://www.tcmb.gov.tr/wps/wcm/connect/tr/tcmb+tr/main+page+site+area/bugun" target="_blank">
                    TCMB'den güncel kur
                </a>
            </small>
        </div>
        <div class="col-md-4">
            <label for="kur_tarihi" class="form-label">
                <i class="fas fa-calendar"></i> Kur Tarihi *
            </label>
            <input type="date" class="form-control" id="kur_tarihi" name="kur_tarihi" 
                   value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div class="col-md-4">
            <label for="kur_notu" class="form-label">
                <i class="fas fa-sticky-note"></i> Kur Notu
            </label>
            <input type="text" class="form-control" id="kur_notu" name="kur_notu" 
                   placeholder="Örn: TCMB resmi kur">
        </div>
    </div>
    <div class="info-box mt-3">
        <i class="fas fa-lightbulb"></i> 
        <strong>Bilgi:</strong> Bu kur, vergi hesaplamalarında USD'yi TL'ye çevirmek için kullanılacaktır.
    </div>
</div>
    
    <!-- 10. KOMİSYON BİLGİLERİ -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">10</div>
            <h3 class="section-title">Komisyon Bilgileri</h3>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="komisyon_firma" class="form-label">Komisyon Firması</label>
                <input type="text" class="form-control" id="komisyon_firma" name="komisyon_firma">
            </div>
            <div class="col-md-6">
                <label for="komisyon_tutari" class="form-label">Konteyner Bazlı Komisyon Tutarı</label>
                <input type="number" class="form-control" id="komisyon_tutari" name="komisyon_tutari" 
                       min="0" step="0.01">
            </div>
            <div class="col-12">
                <label for="komisyon_anlasmasi" class="form-label">Komisyon Anlaşması Detayı</label>
                <textarea class="form-control" id="komisyon_anlasmasi" name="komisyon_anlasmasi" 
                          rows="2"></textarea>
            </div>
        </div>
        <div class="info-box">
            <i class="fas fa-info-circle"></i> Proforma ile anlaşılan komisyon sistemi tarafından kontrol edilecektir.
        </div>
    </div>
    
    <!-- 11. ÖN ÖDEME VE AVANSLAR -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">11</div>
            <h3 class="section-title">Ön Ödeme ve Avans Bilgileri</h3>
        </div>
        <div class="row g-3">
            <div class="col-md-3">
                <label for="on_odeme_orani" class="form-label">Ön Ödeme Oranı</label>
                <select class="form-select" id="on_odeme_orani" name="on_odeme_orani">
                    <option value="">Seçiniz</option>
                    <?php foreach($ON_ODEME_ORANLARI as $key => $value): ?>
                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="avans_1_tutari" class="form-label">1. Avans Tutarı</label>
                <input type="number" class="form-control" id="avans_1_tutari" name="avans_1_tutari" 
                       min="0" step="0.01">
            </div>
            <div class="col-md-3">
                <label for="avans_1_tarihi" class="form-label">1. Avans Tarihi</label>
                <input type="date" class="form-control" id="avans_1_tarihi" name="avans_1_tarihi">
            </div>
            <div class="col-md-3">
                <label for="avans_1_kur" class="form-label">1. Avans Kur</label>
                <input type="number" class="form-control" id="avans_1_kur" name="avans_1_kur" 
                       min="0" step="0.0001" placeholder="TL/USD">
            </div>
            <div class="col-md-4">
                <label for="avans_2_tutari" class="form-label">2. Avans Tutarı</label>
                <input type="number" class="form-control" id="avans_2_tutari" name="avans_2_tutari" 
                       min="0" step="0.01">
            </div>
            <div class="col-md-4">
                <label for="avans_2_tarihi" class="form-label">2. Avans Tarihi</label>
                <input type="date" class="form-control" id="avans_2_tarihi" name="avans_2_tarihi">
            </div>
            <div class="col-md-4">
                <label for="avans_2_kur" class="form-label">2. Avans Kur</label>
                <input type="number" class="form-control" id="avans_2_kur" name="avans_2_kur" 
                       min="0" step="0.0001" placeholder="TL/USD">
            </div>
            <div class="col-md-4">
                <label for="final_odeme_tutari" class="form-label">Final Ödeme Tutarı</label>
                <input type="number" class="form-control" id="final_odeme_tutari" name="final_odeme_tutari" 
                       min="0" step="0.01">
            </div>
            <div class="col-md-4">
                <label for="final_odeme_tarihi" class="form-label">Final Ödeme Tarihi</label>
                <input type="date" class="form-control" id="final_odeme_tarihi" name="final_odeme_tarihi">
            </div>
            <div class="col-md-4">
                <label for="final_odeme_kur" class="form-label">Final Ödeme Kur</label>
                <input type="number" class="form-control" id="final_odeme_kur" name="final_odeme_kur" 
                       min="0" step="0.0001" placeholder="TL/USD">
            </div>
        </div>
    </div>
    
    <!-- 12. NOTLAR -->
    <div class="section-box">
        <div class="section-header">
            <div class="section-number">12</div>
            <h3 class="section-title">Ek Notlar ve Özel Durumlar</h3>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <label for="notlar" class="form-label">Notlar</label>
                <textarea class="form-control" id="notlar" name="notlar" rows="4" 
                          placeholder="İthalat süreciyle ilgili ek bilgiler, özel durumlar, hatırlatmalar..."></textarea>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4">
        <button type="submit" class="btn-submit-form" id="submitBtn">
            <i class="fas fa-save"></i> İthalat Kaydını Kaydet
        </button>
    </div>
</form>

<script>
// ============================================
// ✅ GTIP VERİTABANI VE FONKSİYONLAR
// ============================================

// GTIP veritabanını JavaScript'e aktar
const gtipVeritabani = <?php echo json_encode($gtip_listesi_tum); ?>;

// GTIP seçildiğinde
function gtipSecildi() {
    const gtipKodu = document.getElementById('gtip_kodu_input').value.trim();
    
    if (!gtipKodu) {
        document.getElementById('gtip-bilgi-panel').style.display = 'none';
        return;
    }
    
    // GTIP veritabanından bul
    const gtipBilgi = gtipVeritabani.find(g => g.gtip_kodu === gtipKodu);
    
    if (gtipBilgi) {
        // Bilgileri göster
        document.getElementById('gtip-aciklama-text').textContent = gtipBilgi.aciklama;
        document.getElementById('gtip-gumruk').textContent = gtipBilgi.varsayilan_gumruk_orani;
        document.getElementById('gtip-otv').textContent = gtipBilgi.varsayilan_otv_orani;
        document.getElementById('gtip-kdv').textContent = gtipBilgi.varsayilan_kdv_orani;
        
        // Düzenleme inputlarına da yaz
        document.getElementById('gumruk_oran_edit').value = gtipBilgi.varsayilan_gumruk_orani;
        document.getElementById('otv_oran_edit').value = gtipBilgi.varsayilan_otv_orani;
        document.getElementById('kdv_oran_edit').value = gtipBilgi.varsayilan_kdv_orani;
        
        document.getElementById('gtip-bilgi-panel').style.display = 'block';
        
        // Vergi hesapla
        vergiHesapla();
    } else {
        document.getElementById('gtip-bilgi-panel').style.display = 'none';
    }
}

// Vergi düzenleme panelini aç/kapa
function toggleVergiDuzenle() {
    const panel = document.getElementById('vergi-duzenle-panel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

// Vergi hesapla (önizleme)
function vergiHesapla() {
    const miktar = parseFloat(document.getElementById('miktar_kg').value) || 0;
    const birimFiyat = parseFloat(document.getElementById('birim_fiyat').value) || 0;
    const kur = parseFloat(document.getElementById('usd_kur').value) || 0;
    
    if (miktar <= 0 || birimFiyat <= 0 || kur <= 0) {
        document.getElementById('vergi-hesap-panel').style.display = 'none';
        return;
    }
    
    // Vergi oranları
    const gumrukOran = parseFloat(document.getElementById('gumruk_oran_edit').value) || 0;
    const otvOran = parseFloat(document.getElementById('otv_oran_edit').value) || 0;
    const kdvOran = parseFloat(document.getElementById('kdv_oran_edit').value) || 20;
    
    // Hesaplama
    const usdToplam = miktar * birimFiyat;
    const tlTutar = usdToplam * kur;
    
    const gumrukTutar = tlTutar * (gumrukOran / 100);
    const matrah1 = tlTutar + gumrukTutar;
    
    const otvTutar = matrah1 * (otvOran / 100);
    const matrah2 = matrah1 + otvTutar;
    
    const kdvTutar = matrah2 * (kdvOran / 100);
    
    const toplamVergi = gumrukTutar + otvTutar + kdvTutar;
    const vergiDahil = tlTutar + toplamVergi;
    
    // Göster
    document.getElementById('onizleme-tl-tutar').textContent = '₺' + tlTutar.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('onizleme-gumruk').textContent = '₺' + gumrukTutar.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('onizleme-otv').textContent = '₺' + otvTutar.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('onizleme-kdv').textContent = '₺' + kdvTutar.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('onizleme-toplam-vergi').textContent = '₺' + toplamVergi.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('onizleme-vergi-dahil').textContent = '₺' + vergiDahil.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    document.getElementById('vergi-hesap-panel').style.display = 'block';
}

// ============================================
// ✅ ÜRÜN YÖNETİMİ
// ============================================

let eklenmisUrunler = [];

// Sayı formatlama
function formatNumber(num) {
    return parseFloat(num).toLocaleString('tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// ÜRÜN EKLE FONKSİYONU
function urunEkle() {
    const urunSelect = document.getElementById('urun_sec');
    const miktarKg = document.getElementById('miktar_kg');
    const birimFiyat = document.getElementById('birim_fiyat');
    const gtipKodu = document.getElementById('gtip_kodu_input');
    const kur = document.getElementById('usd_kur');
    
    // Validasyon
    if (!urunSelect.value) {
        alert('❌ Lütfen bir ürün seçin!');
        urunSelect.focus();
        return;
    }
    
    if (!gtipKodu.value) {
        alert('❌ Lütfen GTIP kodu girin!');
        gtipKodu.focus();
        return;
    }
    
    if (!miktarKg.value || parseFloat(miktarKg.value) <= 0) {
        alert('❌ Lütfen geçerli bir miktar girin!');
        miktarKg.focus();
        return;
    }
    
    if (!birimFiyat.value || parseFloat(birimFiyat.value) <= 0) {
        alert('❌ Lütfen geçerli bir birim fiyat girin!');
        birimFiyat.focus();
        return;
    }
    
    if (!kur.value || parseFloat(kur.value) <= 0) {
        alert('❌ Lütfen USD kuru girin! (Bölüm 9)');
        kur.focus();
        return;
    }
    
    // Seçili ürün bilgilerini al
    const selectedOption = urunSelect.options[urunSelect.selectedIndex];
    
    // Vergi hesaplamaları
    const miktar = parseFloat(miktarKg.value);
    const fiyat = parseFloat(birimFiyat.value);
    const kurDeger = parseFloat(kur.value);
    const gtipKod = gtipKodu.value.trim();
    
    // Vergi oranları (düzenleme panelinden veya varsayılan)
    const gumrukOran = parseFloat(document.getElementById('gumruk_oran_edit').value) || 0;
    const otvOran = parseFloat(document.getElementById('otv_oran_edit').value) || 0;
    const kdvOran = parseFloat(document.getElementById('kdv_oran_edit').value) || 20;
    
    // GTIP açıklama
    const gtipBilgi = gtipVeritabani.find(g => g.gtip_kodu === gtipKod);
    const gtipAciklama = gtipBilgi ? gtipBilgi.aciklama : '';
    
    // Hesaplamalar
    const usdToplam = miktar * fiyat;
    const tlTutar = usdToplam * kurDeger;
    
    const gumrukTutar = tlTutar * (gumrukOran / 100);
    const matrah1 = tlTutar + gumrukTutar;
    
    const otvTutar = matrah1 * (otvOran / 100);
    const matrah2 = matrah1 + otvTutar;
    
    const kdvTutar = matrah2 * (kdvOran / 100);
    
    const toplamVergi = gumrukTutar + otvTutar + kdvTutar;
    const vergiDahilTutar = tlTutar + toplamVergi;
    
    // Ürün objesi oluştur
    const urun = {
        id: Date.now(),
        urun_katalog_id: urunSelect.value,
        urun_cinsi: selectedOption.getAttribute('data-cinsi'),
        urun_latince_isim: selectedOption.getAttribute('data-latince'),
        kalibre: selectedOption.getAttribute('data-kalibre') || '',
        urun_tipi: selectedOption.getAttribute('data-tipi'),
        miktar_kg: miktar,
        birim_fiyat: fiyat,
        toplam_tutar: usdToplam,
        
        // Vergi bilgileri
        gtip_kodu: gtipKod,
        gtip_aciklama: gtipAciklama,
        gumruk_oran: gumrukOran,
        gumruk_tutar: gumrukTutar,
        otv_oran: otvOran,
        otv_tutar: otvTutar,
        kdv_oran: kdvOran,
        kdv_tutar: kdvTutar,
        toplam_vergi: toplamVergi,
        vergi_dahil_tutar: vergiDahilTutar,
        kur: kurDeger,
        tl_tutar: tlTutar
    };
    
    // Listeye ekle
    eklenmisUrunler.push(urun);
    
    // UI'ı güncelle
    urunListesiniGuncelle();
    
    // Formu temizle
    urunSelect.value = '';
    miktarKg.value = '';
    birimFiyat.value = '';
    gtipKodu.value = '';
    document.getElementById('gtip-bilgi-panel').style.display = 'none';
    document.getElementById('vergi-hesap-panel').style.display = 'none';
    
    console.log('✅ Ürün eklendi (vergi dahil):', urun);
}

// Ürün sil
function urunSil(id) {
    if (!confirm('Bu ürünü silmek istediğinizden emin misiniz?')) return;
    
    eklenmisUrunler = eklenmisUrunler.filter(u => u.id !== id);
    urunListesiniGuncelle();
}

// Ürün listesini güncelle
function urunListesiniGuncelle() {
    const container = document.getElementById('urun-listesi');
    const toplamDiv = document.getElementById('urun-toplam');
    
    if (eklenmisUrunler.length === 0) {
        container.innerHTML = `
            <div class="no-urun-message">
                <i class="fas fa-box-open"></i>
                <p class="mb-0">Henüz ürün eklenmedi. Yukarıdaki formdan ürün ekleyebilirsiniz.</p>
            </div>
        `;
        toplamDiv.style.display = 'none';
        return;
    }
    
    // Ürün kartlarını oluştur
    let html = '';
    let toplamVergiTumu = 0;
    
    eklenmisUrunler.forEach((urun, index) => {
        toplamVergiTumu += urun.toplam_vergi || 0;
        
        html += `
            <div class="urun-card">
                <div class="urun-card-header">
                    <div>
                        <div class="urun-card-title">
                            ${index + 1}. ${urun.urun_cinsi}
                            ${urun.kalibre ? ' (' + urun.kalibre + ')' : ''}
                        </div>
                        <div class="urun-card-subtitle">
                            <i class="fas fa-flask"></i> ${urun.urun_latince_isim}
                        </div>
                        ${urun.gtip_kodu ? `
                        <div style="margin-top: 5px;">
                            <span style="font-family: monospace; background: #2196f3; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem;">
                                <i class="fas fa-barcode"></i> ${urun.gtip_kodu}
                            </span>
                        </div>
                        ` : ''}
                    </div>
                    <div class="urun-card-actions">
                        <button type="button" class="btn-urun-action btn-urun-sil" onclick="urunSil(${urun.id})">
                            <i class="fas fa-trash"></i> Sil
                        </button>
                    </div>
                </div>
                
                <div class="urun-card-details" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                    <div class="urun-detail-item">
                        <div class="urun-detail-label">Miktar</div>
                        <div class="urun-detail-value">${formatNumber(urun.miktar_kg)} KG</div>
                    </div>
                    <div class="urun-detail-item">
                        <div class="urun-detail-label">Birim Fiyat</div>
                        <div class="urun-detail-value">$${formatNumber(urun.birim_fiyat)}</div>
                    </div>
                    <div class="urun-detail-item">
                        <div class="urun-detail-label">USD Toplam</div>
                        <div class="urun-detail-value" style="color: #27ae60;">$${formatNumber(urun.toplam_tutar)}</div>
                    </div>
                    
                    ${urun.vergi_dahil_tutar > 0 ? `
                    <div class="urun-detail-item" style="grid-column: 1 / -1; background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); padding: 15px; border-radius: 8px; margin-top: 10px; border: 2px solid #ff9800;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 10px;">
                            <div>
                                <div style="font-size: 0.75rem; color: #666; margin-bottom: 3px;">TL Tutar</div>
                                <div style="font-size: 1rem; font-weight: bold; color: #2c3e50;">₺${formatNumber(urun.tl_tutar)}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #666; margin-bottom: 3px;">Gümrük (${urun.gumruk_oran}%)</div>
                                <div style="font-size: 1rem; font-weight: bold; color: #4caf50;">₺${formatNumber(urun.gumruk_tutar)}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #666; margin-bottom: 3px;">ÖTV (${urun.otv_oran}%)</div>
                                <div style="font-size: 1rem; font-weight: bold; color: #ff9800;">₺${formatNumber(urun.otv_tutar)}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #666; margin-bottom: 3px;">KDV (${urun.kdv_oran}%)</div>
                                <div style="font-size: 1rem; font-weight: bold; color: #2196f3;">₺${formatNumber(urun.kdv_tutar)}</div>
                            </div>
                        </div>
                        <div style="padding-top: 10px; border-top: 2px solid #ff9800; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <span style="font-size: 0.9rem; color: #666;">Toplam Vergi:</span>
                                <strong style="font-size: 1.3rem; color: #e65100; margin-left: 10px;">₺${formatNumber(urun.toplam_vergi)}</strong>
                            </div>
                            <div>
                                <span style="font-size: 0.9rem; color: #666;">Vergi Dahil:</span>
                                <strong style="font-size: 1.4rem; color: #1b5e20; margin-left: 10px;">₺${formatNumber(urun.vergi_dahil_tutar)}</strong>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Toplamları hesapla
    const toplamCesit = eklenmisUrunler.length;
    const toplamKg = eklenmisUrunler.reduce((sum, u) => sum + u.miktar_kg, 0);
    const toplamTutar = eklenmisUrunler.reduce((sum, u) => sum + u.toplam_tutar, 0);
    
    document.getElementById('toplam-cesit').textContent = toplamCesit;
    document.getElementById('toplam-kg').textContent = formatNumber(toplamKg);
    document.getElementById('toplam-tutar').textContent = '$' + formatNumber(toplamTutar);
    
    // Toplam sipariş kg'yi otomatik doldur
    document.getElementById('toplam_siparis_kg').value = toplamKg.toFixed(2);
    
    toplamDiv.style.display = 'block';
    
    // Hidden input'a JSON olarak kaydet
    document.getElementById('urunler_json').value = JSON.stringify(eklenmisUrunler);
    
    console.log('📊 Toplam Vergi Tüm Ürünler:', formatNumber(toplamVergiTumu));
}

// ============================================
// ✅ FORM SUBMIT
// ============================================

document.getElementById('ithalatForm').addEventListener('submit', function(e) {
    e.preventDefault();

        // ✅ DEBUG: Form verilerini konsola yazdır
    console.log('═══════════════════════════════════');
    console.log('🔍 FORM VERİLERİ:');
    console.log('═══════════════════════════════════');
    console.log('📋 Tedarikçi Sipariş No:', document.getElementById('tedarikci_siparis_no')?.value || 'BOŞ');
    console.log('🏭 Tedarikçi Firma:', document.getElementById('tedarikci_firma')?.value || 'BOŞ');
    console.log('🌍 Tedarikçi Ülke:', document.getElementById('tedarikci_ulke')?.value || 'BOŞ');
    console.log('═══════════════════════════════════');
    
    // En az 1 ürün kontrolü
    if (eklenmisUrunler.length === 0) {
        alert('❌ En az 1 ürün eklemelisiniz!');
        document.getElementById('urun_sec').focus();
        return;
    }
    
    // Butonu disable et (Çift tıklama önleme)
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn.disabled) {
        return;
    }
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
    
    console.log('📤 Form gönderiliyor...');
    console.log('📦 Ürün sayısı:', eklenmisUrunler.length);
    
    const formData = new FormData(this);
    
    fetch('api/save-import.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('📥 Response status:', response.status);
        
        return response.text().then(text => {
            console.log('📄 RAW RESPONSE (ilk 500 char):', text.substring(0, 500));
            
            try {
                const data = JSON.parse(text);
                console.log('✅ Parsed JSON:', data);
                return data;
            } catch(e) {
                console.error('❌ JSON Parse hatası:', e);
                console.error('📄 Full response:', text);
                
                const errorMatch = text.match(/<b>(.*?)<\/b>/);
                if (errorMatch) {
                    console.error('🔥 PHP HATASI:', errorMatch[1]);
                }
                
                throw new Error('SUNUCU HATASI - Konsola bakın!');
            }
        });
    })
    .then(data => {
        if(data.success) {
            console.log('✅ Başarılı:', data);
            alert('✅ İthalat kaydı başarıyla oluşturuldu!\n\n' + 
                  'Dosya No: ' + data.balik_dunyasi_dosya_no + '\n' +
                  'Ürün Sayısı: ' + data.urun_sayisi + '\n' +
                  'Toplam KG: ' + data.toplam_kg + '\n' +
                  'Toplam Tutar: $' + data.toplam_tutar);
            
            setTimeout(() => {
                window.location.href = '?page=ithalat-takip';
            }, 1000);
        } else {
            console.error('❌ Hata:', data);
            
            if (data.already_processed) {
                alert('⚠️ Bu form zaten gönderilmiş! Sayfa yenileniyor...');
                setTimeout(() => location.reload(), 1000);
            } else {
                alert('❌ Hata: ' + data.message);
                
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> İthalat Kaydını Kaydet';
            }
        }
    })
    .catch(error => {
        console.error('❌ Fetch hatası:', error);
        alert('❌ Bir hata oluştu!\n\n' + error.message + '\n\nDetaylar için konsolu kontrol edin (F12)');
        
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> İthalat Kaydını Kaydet';
    });
});

// Enter tuşu ile ürün ekleme
document.getElementById('birim_fiyat').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        urunEkle();
    }
});

console.log('✅ Tüm sistemler hazır!');
</script>