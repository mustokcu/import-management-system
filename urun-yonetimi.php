<?php
/**
 * Ürün Yönetimi Sayfası
 * Ürün kataloğunu yönet - Ekle, Düzenle, Sil
 */

// Değişkenleri dahil et
global $URUN_TIPLERI, $KALITE_TERIMLERI;

$db = getDB();

// Filtreleme
$arama = $_GET['arama'] ?? '';
$filtre_tip = $_GET['tip'] ?? '';
$filtre_durum = $_GET['durum'] ?? '1'; // Varsayılan: sadece aktif

// Ürünleri çek
$sql = "SELECT * FROM urun_katalog WHERE 1=1";
$params = [];

if (!empty($arama)) {
    $sql .= " AND (urun_cinsi LIKE :arama OR urun_latince_isim LIKE :arama OR kalibre LIKE :arama)";
    $params[':arama'] = "%$arama%";
}

if (!empty($filtre_tip)) {
    $sql .= " AND urun_tipi = :tip";
    $params[':tip'] = $filtre_tip;
}

if ($filtre_durum !== '') {
    $sql .= " AND aktif = :durum";
    $params[':durum'] = $filtre_durum;
}

$sql .= " ORDER BY urun_tipi, urun_cinsi, kalibre";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$urunler = $stmt->fetchAll();

// İstatistikler
$sql_stats = "SELECT 
    COUNT(*) as toplam,
    SUM(CASE WHEN aktif = 1 THEN 1 ELSE 0 END) as aktif,
    SUM(CASE WHEN aktif = 0 THEN 1 ELSE 0 END) as pasif
FROM urun_katalog";
$stats = $db->query($sql_stats)->fetch();
?>

<style>
    .urun-header {
        background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(39, 174, 96, 0.3);
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        border-left: 5px solid #27ae60;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }
    
    .stat-card.info { border-left-color: #3498db; }
    .stat-card.success { border-left-color: #27ae60; }
    .stat-card.secondary { border-left-color: #95a5a6; }
    
    .stat-number {
        font-size: 2.5rem;
        font-weight: bold;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 0.95rem;
        color: #7f8c8d;
    }
    
    .filter-toolbar {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .table thead {
        background: linear-gradient(135deg, #27ae60, #229954);
        color: white;
    }
    
    .table thead th {
        font-weight: 600;
        padding: 15px 10px;
        border: none;
    }
    
    .table tbody tr {
        transition: all 0.2s ease;
    }
    
    .table tbody tr:hover {
        background: #f8f9fa;
        transform: scale(1.01);
    }
    
    .badge-kalibre {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        padding: 6px 12px;
        border-radius: 15px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .urun-durumu-badges {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    
    .urun-durumu-badges .badge {
        font-size: 0.75rem;
    }
    
    .action-btns {
        display: flex;
        gap: 5px;
    }
    
    .btn-add-product {
        background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 50px;
        font-weight: 600;
        box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        transition: all 0.3s ease;
    }
    
    .btn-add-product:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(39, 174, 96, 0.4);
        color: white;
    }
    
    .modal-header {
        background: linear-gradient(135deg, #27ae60, #229954);
        color: white;
    }
    
    .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    
    .form-section {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 4px solid #27ae60;
    }
    
    .form-section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 15px;
    }
    
    .no-products {
        text-align: center;
        padding: 60px 20px;
    }
    
    .no-products i {
        font-size: 5rem;
        color: #bdc3c7;
        margin-bottom: 20px;
    }
</style>

<div class="urun-header">
    <h2 class="mb-0"><i class="fas fa-box-open"></i> Ürün Kataloğu Yönetimi</h2>
    <p class="mb-0 mt-2">Tüm ürünleri tek merkezden yönetin</p>
</div>

<!-- İstatistikler -->
<div class="stats-grid">
    <div class="stat-card info">
        <div class="stat-number"><?php echo $stats['toplam'] ?? 0; ?></div>
        <div class="stat-label"><i class="fas fa-database"></i> Toplam Ürün</div>
    </div>
    
    <div class="stat-card success">
        <div class="stat-number"><?php echo $stats['aktif'] ?? 0; ?></div>
        <div class="stat-label"><i class="fas fa-check-circle"></i> Aktif Ürün</div>
    </div>
    
    <div class="stat-card secondary">
        <div class="stat-number"><?php echo $stats['pasif'] ?? 0; ?></div>
        <div class="stat-label"><i class="fas fa-times-circle"></i> Pasif Ürün</div>
    </div>
</div>

<!-- Filtre ve Arama -->
<div class="filter-toolbar">
    <form method="GET" class="row g-3 align-items-end">
        <input type="hidden" name="page" value="urun-yonetimi">
        
        <div class="col-md-4">
            <label class="form-label"><i class="fas fa-search"></i> Arama</label>
            <input type="text" class="form-control" name="arama" 
                   placeholder="Ürün adı, latince isim, kalibre..." 
                   value="<?php echo safeHtml($arama); ?>">
        </div>
        
        <div class="col-md-3">
            <label class="form-label"><i class="fas fa-filter"></i> Ürün Tipi</label>
            <select class="form-select" name="tip">
                <option value="">Tümü</option>
                <?php foreach($URUN_TIPLERI as $key => $value): ?>
                    <option value="<?php echo $key; ?>" <?php echo $filtre_tip == $key ? 'selected' : ''; ?>>
                        <?php echo $value; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2">
            <label class="form-label"><i class="fas fa-toggle-on"></i> Durum</label>
            <select class="form-select" name="durum">
                <option value="">Tümü</option>
                <option value="1" <?php echo $filtre_durum == '1' ? 'selected' : ''; ?>>Aktif</option>
                <option value="0" <?php echo $filtre_durum == '0' ? 'selected' : ''; ?>>Pasif</option>
            </select>
        </div>
        
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filtrele
            </button>
            <a href="?page=urun-yonetimi" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Temizle
            </a>
        </div>
    </form>
    
    <div class="mt-3 text-end">
        <button class="btn-add-product" onclick="yeniUrunModal()">
            <i class="fas fa-plus-circle"></i> Yeni Ürün Ekle
        </button>
    </div>
</div>

<!-- Ürünler Tablosu -->
<div class="table-container">
    <?php if (count($urunler) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ürün Cinsi</th>
                        <th>Latince İsim</th>
                        <th>Tip</th>
                        <th>Kalibre</th>
                        <th>GLZ</th>
                        <th>Kalite</th>
                        <th>Ürün Durumu</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($urunler as $urun): ?>
                        <tr>
                            <td><strong>#<?php echo $urun['id']; ?></strong></td>
                            <td><strong><?php echo safeHtml($urun['urun_cinsi']); ?></strong></td>
                            <td><em><?php echo safeHtml($urun['urun_latince_isim']); ?></em></td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo safeHtml($URUN_TIPLERI[$urun['urun_tipi']] ?? $urun['urun_tipi']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($urun['kalibre']): ?>
                                    <span class="badge-kalibre"><?php echo safeHtml($urun['kalibre']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $urun['glz_orani'] ? $urun['glz_orani'] . '%' : '-'; ?>
                            </td>
                            <td>
                                <?php if ($urun['kalite_terimi']): ?>
                                    <span class="badge bg-warning text-dark">
                                        <?php echo $urun['kalite_terimi']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="urun-durumu-badges">
                                    <?php 
                                    if ($urun['urun_durumu']) {
                                        $durumlar = explode(',', $urun['urun_durumu']);
                                        foreach($durumlar as $durum) {
                                            echo '<span class="badge bg-secondary">' . trim($durum) . '</span>';
                                        }
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($urun['aktif']): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><i class="fas fa-times"></i> Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn btn-sm btn-warning" 
                                            onclick="duzenleUrun(<?php echo $urun['id']; ?>)"
                                            title="Düzenle">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-<?php echo $urun['aktif'] ? 'secondary' : 'success'; ?>" 
                                            onclick="durumDegistir(<?php echo $urun['id']; ?>, <?php echo $urun['aktif'] ? 0 : 1; ?>)"
                                            title="<?php echo $urun['aktif'] ? 'Pasif Yap' : 'Aktif Yap'; ?>">
                                        <i class="fas fa-<?php echo $urun['aktif'] ? 'toggle-off' : 'toggle-on'; ?>"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" 
                                            onclick="silUrun(<?php echo $urun['id']; ?>)"
                                            title="Sil">
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
        <div class="no-products">
            <i class="fas fa-box-open"></i>
            <h4>Ürün Bulunamadı</h4>
            <p>Henüz ürün eklenmemiş veya filtrelere uygun ürün yok.</p>
            <button class="btn-add-product mt-3" onclick="yeniUrunModal()">
                <i class="fas fa-plus-circle"></i> İlk Ürünü Ekle
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- Ürün Ekleme/Düzenleme Modal -->
<div class="modal fade" id="urunModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">
                    <i class="fas fa-plus-circle"></i> Yeni Ürün Ekle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="urunForm">
                    <input type="hidden" id="urun_id" name="id">
                    
                    <!-- Temel Bilgiler -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-info-circle"></i> Temel Bilgiler
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Ürün Latince İsim *</label>
                                <input type="text" class="form-control" name="urun_latince_isim" 
                                       id="urun_latince_isim" required
                                       placeholder="Örn: Octopus Vulgaris">
                                <small class="text-muted">Bilimsel/Latince adı</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ürün Cinsi *</label>
                                <input type="text" class="form-control" name="urun_cinsi" 
                                       id="urun_cinsi" required
                                       placeholder="Örn: Ahtapot">
                                <small class="text-muted">Türkçe ürün adı</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ürün Tipi *</label>
                                <select class="form-select" name="urun_tipi" id="urun_tipi" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach($URUN_TIPLERI as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kalibre (Opsiyonel)</label>
                                <input type="text" class="form-control" name="kalibre" 
                                       id="kalibre" placeholder="Örn: 16/20, 21/25">
                                <small class="text-muted">Varsa boy/kalibre bilgisi</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Teknik Detaylar -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-cogs"></i> Teknik Detaylar
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">GLZ Oranı (%)</label>
                                <input type="number" class="form-control" name="glz_orani" 
                                       id="glz_orani" min="0" max="100" step="0.1"
                                       placeholder="0-100">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Kalite Terimi</label>
                                <select class="form-select" name="kalite_terimi" id="kalite_terimi">
                                    <option value="">Seçiniz</option>
                                    <?php foreach($KALITE_TERIMLERI as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Koli KG Çeşidi</label>
                                <input type="text" class="form-control" name="koli_kg_cesidi" 
                                       id="koli_kg_cesidi" placeholder="Örn: 20kg">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Ürün Durumu</label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="urun_durumu[]" value="hoso" id="dur_hoso">
                                        <label class="form-check-label" for="dur_hoso">HOSO</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="urun_durumu[]" value="kabuklu" id="dur_kabuklu">
                                        <label class="form-check-label" for="dur_kabuklu">Kabuklu</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="urun_durumu[]" value="kabuksuz" id="dur_kabuksuz">
                                        <label class="form-check-label" for="dur_kabuksuz">Kabuksuz</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Avcılık Bol Dönemi</label>
                                <input type="text" class="form-control" name="avcilik_bol_donemi" 
                                       id="avcilik_bol_donemi" placeholder="Örn: Mayıs-Haziran">
                                <small class="text-muted">En uygun alım ayı/dönemi</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Durum -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-toggle-on"></i> Durum
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" 
                                   name="aktif" id="aktif" value="1" checked>
                            <label class="form-check-label" for="aktif">
                                <strong>Aktif</strong> (Veri girişte görünsün)
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> İptal
                </button>
                <button type="button" class="btn btn-success" onclick="kaydetUrun()">
                    <i class="fas fa-save"></i> Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ✅ Modal'ı sayfa yüklendikten SONRA başlat
let urunModal;

document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('urunModal');
    if (modalElement) {
        urunModal = new bootstrap.Modal(modalElement);
    }
});

// Yeni ürün modalı
function yeniUrunModal() {
    if (!urunModal) {
        urunModal = new bootstrap.Modal(document.getElementById('urunModal'));
    }
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Yeni Ürün Ekle';
    document.getElementById('urunForm').reset();
    document.getElementById('urun_id').value = '';
    document.getElementById('aktif').checked = true;
    urunModal.show();
}

// Ürün düzenle
function duzenleUrun(id) {
    if (!urunModal) {
        urunModal = new bootstrap.Modal(document.getElementById('urunModal'));
    }
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Ürün Düzenle';
    
    fetch(`api/urun-detay.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const urun = data.urun;
                document.getElementById('urun_id').value = urun.id;
                document.getElementById('urun_latince_isim').value = urun.urun_latince_isim || '';
                document.getElementById('urun_cinsi').value = urun.urun_cinsi || '';
                document.getElementById('urun_tipi').value = urun.urun_tipi || '';
                document.getElementById('kalibre').value = urun.kalibre || '';
                document.getElementById('glz_orani').value = urun.glz_orani || '';
                document.getElementById('kalite_terimi').value = urun.kalite_terimi || '';
                document.getElementById('koli_kg_cesidi').value = urun.koli_kg_cesidi || '';
                document.getElementById('avcilik_bol_donemi').value = urun.avcilik_bol_donemi || '';
                document.getElementById('aktif').checked = urun.aktif == 1;
                
                // Ürün durumu checkboxları
                document.getElementById('dur_hoso').checked = false;
                document.getElementById('dur_kabuklu').checked = false;
                document.getElementById('dur_kabuksuz').checked = false;
                if (urun.urun_durumu) {
                    const durumlar = urun.urun_durumu.split(',');
                    durumlar.forEach(d => {
                        const checkbox = document.getElementById('dur_' + d.trim());
                        if (checkbox) checkbox.checked = true;
                    });
                }
                
                urunModal.show();
            } else {
                alert('❌ Ürün bilgileri yüklenemedi!');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Bir hata oluştu!');
        });
}

// Ürün kaydet
function kaydetUrun() {
    const formData = new FormData(document.getElementById('urunForm'));
    const id = document.getElementById('urun_id').value;
    const url = id ? 'api/urun-guncelle.php' : 'api/urun-ekle.php';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Ürün başarıyla kaydedildi!');
            urunModal.hide();
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

// Durum değiştir (aktif/pasif)
function durumDegistir(id, yeniDurum) {
    const mesaj = yeniDurum ? 'aktif' : 'pasif';
    if (!confirm(`Bu ürünü ${mesaj} yapmak istediğinizden emin misiniz?`)) return;
    
    fetch('api/urun-durum-degistir.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id, aktif: yeniDurum})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Durum değiştirildi!');
            location.reload();
        } else {
            alert('❌ Hata: ' + data.message);
        }
    });
}

// Ürün sil
function silUrun(id) {
    if (!confirm('Bu ürünü silmek istediğinizden emin misiniz?\n\nNot: Eğer bu ürün bir ithalatta kullanılmışsa silinemez!')) return;
    
    fetch('api/urun-sil.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Ürün silindi!');
            location.reload();
        } else {
            alert('❌ Hata: ' + data.message);
        }
    });
}
</script>