<?php
/**
 * GTIP Kod Yönetimi Sayfası
 * GTIP kodlarını ekleme, düzenleme, silme ve toplu yükleme
 */

$db = getDB();

// İstatistikler
$sql_stats = "SELECT 
    COUNT(*) as toplam,
    COUNT(CASE WHEN aktif = 1 THEN 1 END) as aktif,
    COUNT(CASE WHEN varsayilan_gumruk_orani > 0 THEN 1 END) as gumruk_var,
    COUNT(CASE WHEN varsayilan_otv_orani > 0 THEN 1 END) as otv_var
FROM gtip_kodlari";
$stats = $db->query($sql_stats)->fetch();

// Filtreleme
$arama = $_GET['arama'] ?? '';
$kategori = $_GET['kategori'] ?? '';

// GTIP listesi
$sql = "SELECT * FROM gtip_kodlari WHERE 1=1";
$params = [];

if (!empty($arama)) {
    $sql .= " AND (gtip_kodu LIKE :arama OR aciklama LIKE :arama)";
    $params[':arama'] = "%$arama%";
}

if (!empty($kategori)) {
    $sql .= " AND kategori = :kategori";
    $params[':kategori'] = $kategori;
}

$sql .= " ORDER BY gtip_kodu ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$gtip_listesi = $stmt->fetchAll();

// Kategoriler
$kategoriler = $db->query("SELECT DISTINCT kategori FROM gtip_kodlari WHERE kategori IS NOT NULL ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);
?>

<style>
    .gtip-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        border-left: 4px solid #667eea;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: bold;
        color: #2c3e50;
    }
    
    .stat-label {
        color: #7f8c8d;
        font-size: 0.9rem;
        margin-top: 5px;
    }
    
    .action-bar {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .filter-box {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        border: 2px solid #e1e8ed;
    }
    
    .gtip-table-container {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .gtip-table {
        width: 100%;
        margin-bottom: 0;
    }
    
    .gtip-table thead {
        background: linear-gradient(135deg, #2c3e50, #34495e);
        color: white;
    }
    
    .gtip-table thead th {
        padding: 15px 10px;
        font-weight: 600;
        border: none;
    }
    
    .gtip-table tbody tr {
        transition: all 0.2s ease;
    }
    
    .gtip-table tbody tr:hover {
        background: #f8f9fa;
    }
    
    .gtip-table td {
        padding: 12px 10px;
        vertical-align: middle;
    }
    
    .gtip-kod-badge {
        font-family: 'Courier New', monospace;
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.95rem;
        display: inline-block;
    }
    
    .vergi-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-right: 5px;
    }
    
    .vergi-badge.gumruk {
        background: #e8f5e9;
        color: #2e7d32;
    }
    
    .vergi-badge.otv {
        background: #fff3e0;
        color: #e65100;
    }
    
    .vergi-badge.kdv {
        background: #e3f2fd;
        color: #1565c0;
    }
    
    .btn-action {
        padding: 6px 12px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 0.85rem;
        transition: all 0.2s ease;
        margin-right: 5px;
    }
    
    .btn-action:hover {
        transform: translateY(-2px);
    }
    
    .modal-lg {
        max-width: 800px;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .form-grid.full {
        grid-template-columns: 1fr;
    }
    
    .upload-zone {
        border: 3px dashed #3498db;
        background: #f8f9fa;
        padding: 40px;
        border-radius: 10px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .upload-zone:hover {
        background: #e3f2fd;
        border-color: #2980b9;
    }
    
    .upload-zone i {
        font-size: 3rem;
        color: #3498db;
        margin-bottom: 15px;
    }
    
    .template-link {
        margin-top: 15px;
        padding: 10px;
        background: #fff3cd;
        border-radius: 8px;
        border: 1px solid #ffc107;
    }
    
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="gtip-header">
    <h2 class="mb-0"><i class="fas fa-barcode"></i> GTIP Kod Yönetimi</h2>
    <p class="mb-0 mt-2">Gümrük Tarife İstatistik Pozisyonu (GTIP/HS) Kod Veritabanı</p>
</div>

<!-- İstatistikler -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['toplam']; ?></div>
        <div class="stat-label"><i class="fas fa-database"></i> Toplam GTIP Kodu</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['aktif']; ?></div>
        <div class="stat-label"><i class="fas fa-check-circle"></i> Aktif Kodlar</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['gumruk_var']; ?></div>
        <div class="stat-label"><i class="fas fa-shield-alt"></i> Gümrük Vergili</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['otv_var']; ?></div>
        <div class="stat-label"><i class="fas fa-percentage"></i> ÖTV'li</div>
    </div>
</div>

<!-- Aksiyon Butonları -->
<div class="action-bar">
    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addGtipModal">
        <i class="fas fa-plus"></i> Yeni GTIP Kodu Ekle
    </button>
    <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="fas fa-file-excel"></i> Excel/CSV Yükle
    </button>
    <button class="btn btn-info btn-lg" onclick="exportToExcel()">
        <i class="fas fa-download"></i> Excel'e Aktar
    </button>
    <button class="btn btn-secondary btn-lg" onclick="ornekVerilerEkle()">
        <i class="fas fa-magic"></i> Örnek Veriler Ekle
    </button>
</div>

<!-- Filtreleme -->
<div class="filter-box">
    <form method="GET" class="row g-3">
        <input type="hidden" name="page" value="gtip-yonetimi">
        
        <div class="col-md-5">
            <label class="form-label"><i class="fas fa-search"></i> Arama</label>
            <input type="text" class="form-control" name="arama" 
                   placeholder="GTIP kodu veya açıklama..." value="<?php echo safeHtml($arama); ?>">
        </div>
        
        <div class="col-md-3">
            <label class="form-label"><i class="fas fa-filter"></i> Kategori</label>
            <select class="form-select" name="kategori">
                <option value="">Tümü</option>
                <?php foreach($kategoriler as $kat): ?>
                    <option value="<?php echo safeHtml($kat); ?>" <?php echo $kategori == $kat ? 'selected' : ''; ?>>
                        <?php echo safeHtml($kat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-filter"></i> Filtrele
            </button>
        </div>
        
        <div class="col-md-2 d-flex align-items-end">
            <a href="?page=gtip-yonetimi" class="btn btn-outline-secondary w-100">
                <i class="fas fa-times"></i> Temizle
            </a>
        </div>
    </form>
</div>

<!-- GTIP Listesi Tablosu -->
<div class="gtip-table-container">
    <?php if (count($gtip_listesi) > 0): ?>
        <table class="gtip-table table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>GTIP Kodu</th>
                    <th>Açıklama</th>
                    <th>Kategori</th>
                    <th>Vergi Oranları</th>
                    <th>Durum</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($gtip_listesi as $gtip): ?>
                    <tr>
                        <td><strong>#<?php echo $gtip['id']; ?></strong></td>
                        <td>
                            <span class="gtip-kod-badge">
                                <?php echo safeHtml($gtip['gtip_kodu']); ?>
                            </span>
                        </td>
                        <td>
                            <strong><?php echo safeHtml(mb_substr($gtip['aciklama'], 0, 60)); ?></strong>
                            <?php if (mb_strlen($gtip['aciklama']) > 60) echo '...'; ?>
                        </td>
                        <td>
                            <?php if ($gtip['kategori']): ?>
                                <span class="badge bg-secondary"><?php echo safeHtml($gtip['kategori']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($gtip['varsayilan_gumruk_orani'] > 0): ?>
                                <span class="vergi-badge gumruk">
                                    Gümrük: %<?php echo $gtip['varsayilan_gumruk_orani']; ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($gtip['varsayilan_otv_orani'] > 0): ?>
                                <span class="vergi-badge otv">
                                    ÖTV: %<?php echo $gtip['varsayilan_otv_orani']; ?>
                                </span>
                            <?php endif; ?>
                            
                            <span class="vergi-badge kdv">
                                KDV: %<?php echo $gtip['varsayilan_kdv_orani']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($gtip['aktif']): ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning btn-action" 
                                    onclick="gtipDuzenle(<?php echo htmlspecialchars(json_encode($gtip)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger btn-action" 
                                    onclick="gtipSil(<?php echo $gtip['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div style="padding: 50px; text-align: center; color: #7f8c8d;">
            <i class="fas fa-inbox" style="font-size: 4rem; opacity: 0.5; margin-bottom: 15px;"></i>
            <h4>Henüz GTIP Kodu Eklenmemiş</h4>
            <p>Yukarıdaki butonlardan yeni kod ekleyebilir veya Excel yükleyebilirsiniz.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Yeni GTIP Ekleme Modal -->
<div class="modal fade" id="addGtipModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Yeni GTIP Kodu Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addGtipForm">
                    <div class="form-grid">
                        <div>
                            <label class="form-label">GTIP Kodu *</label>
                            <input type="text" class="form-control" name="gtip_kodu" required
                                   placeholder="Örn: 1605.51.00">
                        </div>
                        <div>
                            <label class="form-label">Kategori</label>
                            <input type="text" class="form-control" name="kategori"
                                   placeholder="Örn: Deniz Ürünleri" list="kategoriList">
                            <datalist id="kategoriList">
                                <?php foreach($kategoriler as $kat): ?>
                                    <option value="<?php echo safeHtml($kat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    
                    <div class="form-grid full mt-3">
                        <div>
                            <label class="form-label">Açıklama *</label>
                            <textarea class="form-control" name="aciklama" rows="2" required
                                      placeholder="Örn: Ahtapot, işlenmiş, hazır veya konserve"></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6 style="border-bottom: 2px solid #e1e8ed; padding-bottom: 10px;">
                            <i class="fas fa-percentage"></i> Varsayılan Vergi Oranları
                        </h6>
                    </div>
                    
                    <div class="form-grid mt-3">
                        <div>
                            <label class="form-label">Gümrük Vergisi (%)</label>
                            <input type="number" class="form-control" name="varsayilan_gumruk_orani" 
                                   step="0.01" min="0" max="100" value="0">
                        </div>
                        <div>
                            <label class="form-label">ÖTV (%)</label>
                            <input type="number" class="form-control" name="varsayilan_otv_orani" 
                                   step="0.01" min="0" max="100" value="0">
                        </div>
                        <div>
                            <label class="form-label">KDV (%)</label>
                            <input type="number" class="form-control" name="varsayilan_kdv_orani" 
                                   step="0.01" min="0" max="100" value="20">
                        </div>
                        <div>
                            <label class="form-label">Durum</label>
                            <select class="form-select" name="aktif">
                                <option value="1">Aktif</option>
                                <option value="0">Pasif</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-grid full mt-3">
                        <div>
                            <label class="form-label">Notlar</label>
                            <textarea class="form-control" name="notlar" rows="2"
                                      placeholder="Ek bilgiler..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary" onclick="gtipKaydet()">
                    <i class="fas fa-save"></i> Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Düzenleme Modal -->
<div class="modal fade" id="editGtipModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f39c12, #e67e22); color: white;">
                <h5 class="modal-title"><i class="fas fa-edit"></i> GTIP Kodu Düzenle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editGtipForm">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="form-grid">
                        <div>
                            <label class="form-label">GTIP Kodu *</label>
                            <input type="text" class="form-control" name="gtip_kodu" id="edit_gtip_kodu" required>
                        </div>
                        <div>
                            <label class="form-label">Kategori</label>
                            <input type="text" class="form-control" name="kategori" id="edit_kategori">
                        </div>
                    </div>
                    
                    <div class="form-grid full mt-3">
                        <div>
                            <label class="form-label">Açıklama *</label>
                            <textarea class="form-control" name="aciklama" id="edit_aciklama" rows="2" required></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6 style="border-bottom: 2px solid #e1e8ed; padding-bottom: 10px;">
                            <i class="fas fa-percentage"></i> Varsayılan Vergi Oranları
                        </h6>
                    </div>
                    
                    <div class="form-grid mt-3">
                        <div>
                            <label class="form-label">Gümrük Vergisi (%)</label>
                            <input type="number" class="form-control" name="varsayilan_gumruk_orani" 
                                   id="edit_gumruk" step="0.01" min="0" max="100">
                        </div>
                        <div>
                            <label class="form-label">ÖTV (%)</label>
                            <input type="number" class="form-control" name="varsayilan_otv_orani" 
                                   id="edit_otv" step="0.01" min="0" max="100">
                        </div>
                        <div>
                            <label class="form-label">KDV (%)</label>
                            <input type="number" class="form-control" name="varsayilan_kdv_orani" 
                                   id="edit_kdv" step="0.01" min="0" max="100">
                        </div>
                        <div>
                            <label class="form-label">Durum</label>
                            <select class="form-select" name="aktif" id="edit_aktif">
                                <option value="1">Aktif</option>
                                <option value="0">Pasif</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-grid full mt-3">
                        <div>
                            <label class="form-label">Notlar</label>
                            <textarea class="form-control" name="notlar" id="edit_notlar" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-warning" onclick="gtipGuncelle()">
                    <i class="fas fa-save"></i> Güncelle
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Excel Yükleme Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #27ae60, #229954); color: white;">
                <h5 class="modal-title"><i class="fas fa-file-excel"></i> Excel/CSV Toplu Yükleme</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="upload-zone" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h5>Excel veya CSV Dosyası Seçin</h5>
                    <p class="text-muted">Dosyayı buraya sürükleyin veya tıklayın</p>
                    <input type="file" id="fileInput" accept=".xlsx,.xls,.csv" style="display: none;" 
                           onchange="dosyaSecildi(this)">
                </div>
                
                <div id="dosyaBilgi" style="display: none; margin-top: 20px; padding: 15px; background: #e8f5e9; border-radius: 8px;">
                    <i class="fas fa-check-circle" style="color: #27ae60;"></i>
                    <strong id="dosyaAdi"></strong> hazır
                </div>
                
                <div class="template-link">
                    <i class="fas fa-info-circle"></i>
                    <strong>Excel Formatı:</strong> Sütunlar: GTIP Kodu | Açıklama | Kategori | Gümrük % | ÖTV % | KDV %
                    <br>
                    <a href="api/gtip-template-download.php" class="btn btn-sm btn-warning mt-2">
                        <i class="fas fa-download"></i> Örnek Şablon İndir
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-success" onclick="excelYukle()" id="uploadBtn" disabled>
                    <i class="fas fa-upload"></i> Yükle
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedFile = null;

// GTIP kaydet
function gtipKaydet() {
    const formData = new FormData(document.getElementById('addGtipForm'));
    
    fetch('api/gtip-kaydet.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ GTIP kodu başarıyla eklendi!');
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

// GTIP düzenle
function gtipDuzenle(gtip) {
    document.getElementById('edit_id').value = gtip.id;
    document.getElementById('edit_gtip_kodu').value = gtip.gtip_kodu;
    document.getElementById('edit_kategori').value = gtip.kategori || '';
    document.getElementById('edit_aciklama').value = gtip.aciklama;
    document.getElementById('edit_gumruk').value = gtip.varsayilan_gumruk_orani;
    document.getElementById('edit_otv').value = gtip.varsayilan_otv_orani;
    document.getElementById('edit_kdv').value = gtip.varsayilan_kdv_orani;
    document.getElementById('edit_aktif').value = gtip.aktif;
    document.getElementById('edit_notlar').value = gtip.notlar || '';
    
    const modal = new bootstrap.Modal(document.getElementById('editGtipModal'));
    modal.show();
}

// GTIP güncelle
function gtipGuncelle() {
    const formData = new FormData(document.getElementById('editGtipForm'));
    
    fetch('api/gtip-guncelle.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ GTIP kodu başarıyla güncellendi!');
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

// GTIP sil
function gtipSil(id) {
    if (!confirm('Bu GTIP kodunu silmek istediğinizden emin misiniz?')) return;
    
    fetch('api/gtip-sil.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ GTIP kodu silindi!');
            location.reload();
        } else {
            alert('❌ Hata: ' + data.message);
        }
    });
}

// Dosya seçildi
function dosyaSecildi(input) {
    if (input.files && input.files[0]) {
        selectedFile = input.files[0];
        document.getElementById('dosyaBilgi').style.display = 'block';
        document.getElementById('dosyaAdi').textContent = selectedFile.name;
        document.getElementById('uploadBtn').disabled = false;
    }
}

// Excel yükle
function excelYukle() {
    if (!selectedFile) {
        alert('❌ Lütfen dosya seçin!');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', selectedFile);
    
    const uploadBtn = document.getElementById('uploadBtn');
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Yükleniyor...';
    
    fetch('api/gtip-excel-yukle.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Excel başarıyla yüklendi!\n\nEklenen: ' + data.eklenen + '\nGüncellenen: ' + data.guncellenen);
            location.reload();
        } else {
            alert('❌ Hata: ' + data.message);
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Yükle';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Bir hata oluştu!');
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Yükle';
    });
}

// Örnek veriler ekle
function ornekVerilerEkle() {
    if (!confirm('10 adet örnek GTIP kodu eklenecek. Onaylıyor musunuz?')) return;
    
    fetch('api/gtip-ornek-veriler.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Örnek veriler eklendi: ' + data.eklenen + ' adet');
            location.reload();
        } else {
            alert('❌ Hata: ' + data.message);
        }
    });
}

// Export to Excel
function exportToExcel() {
    window.location.href = 'api/gtip-excel-export.php';
}

console.log('✅ GTIP Yönetim Sayfası Hazır!');
</script>
<?php