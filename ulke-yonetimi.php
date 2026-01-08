<?php
/**
 * Ülke Yönetimi Sayfası
 * Dinamik ülke ekleme, silme ve listeleme
 */

$db = getDB();

// Ülkeleri çek
$sql_ulkeler = "SELECT 
    u.*,
    COALESCE(s.toplam_ithalat, 0) as kullanim_sayisi,
    COALESCE(s.toplam_kg, 0) as toplam_kg,
    s.son_kullanim
FROM ulkeler u
LEFT JOIN ulke_istatistik s ON u.ulke_kodu = s.ulke_kodu
ORDER BY u.bolge, u.ulke_adi";

$stmt_ulkeler = $db->query($sql_ulkeler);
$ulkeler = $stmt_ulkeler->fetchAll();

// Bölgelere göre grupla
$ulkeler_bolge = [];
foreach($ulkeler as $ulke) {
    $bolge = $ulke['bolge'] ?: 'Diğer';
    $ulkeler_bolge[$bolge][] = $ulke;
}
?>

<style>
    .country-header {
        background: linear-gradient(135deg, #16a085 0%, #27ae60 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(22, 160, 133, 0.3);
    }
    
    .add-country-box {
        background: white;
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        border-left: 5px solid #16a085;
    }
    
    .region-section {
        background: white;
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 25px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .region-title {
        font-size: 1.3rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 3px solid #16a085;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .region-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #16a085, #27ae60);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
    }
    
    .country-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 15px;
    }
    
    .country-card {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        border: 2px solid #e1e8ed;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .country-card:hover {
        border-color: #16a085;
        box-shadow: 0 5px 15px rgba(22, 160, 133, 0.2);
        transform: translateY(-3px);
    }
    
    .country-card.used {
        background: linear-gradient(135deg, #e8f8f5 0%, #d5f4e6 100%);
        border-color: #27ae60;
    }
    
    .country-card.not-used {
        opacity: 0.7;
    }
    
    .country-name {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .country-flag {
        font-size: 1.5rem;
    }
    
    .country-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #dee2e6;
    }
    
    .usage-badge {
        background: #3498db;
        color: white;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .usage-badge.active {
        background: #27ae60;
    }
    
    .usage-badge.inactive {
        background: #95a5a6;
    }
    
    .country-actions {
        display: flex;
        gap: 5px;
    }
    
    .btn-delete-country {
        padding: 4px 10px;
        font-size: 0.85rem;
        border-radius: 5px;
    }
    
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-box {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        border-left: 4px solid #16a085;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: bold;
        color: #16a085;
    }
    
    .stat-label {
        color: #7f8c8d;
        font-size: 0.9rem;
        margin-top: 5px;
    }
</style>

<div class="country-header">
    <h2 class="mb-0"><i class="fas fa-globe-americas"></i> Ülke Yönetimi</h2>
    <p class="mb-0 mt-2">İthalat yapılan ülkeleri yönetin, yeni ülkeler ekleyin</p>
</div>

<!-- İstatistikler -->
<?php
$toplam_ulke = count($ulkeler);
$kullanilan_ulke = count(array_filter($ulkeler, function($u) { return $u['kullanim_sayisi'] > 0; }));
$toplam_bolge = count($ulkeler_bolge);
?>

<div class="stats-row">
    <div class="stat-box">
        <div class="stat-value"><?php echo $toplam_ulke; ?></div>
        <div class="stat-label"><i class="fas fa-flag"></i> Toplam Ülke</div>
    </div>
    <div class="stat-box">
        <div class="stat-value"><?php echo $kullanilan_ulke; ?></div>
        <div class="stat-label"><i class="fas fa-check-circle"></i> Kullanılan Ülke</div>
    </div>
    <div class="stat-box">
        <div class="stat-value"><?php echo $toplam_bolge; ?></div>
        <div class="stat-label"><i class="fas fa-map"></i> Bölge Sayısı</div>
    </div>
    <div class="stat-box">
        <div class="stat-value"><?php echo $toplam_ulke - $kullanilan_ulke; ?></div>
        <div class="stat-label"><i class="fas fa-minus-circle"></i> Kullanılmayan</div>
    </div>
</div>

<!-- Yeni Ülke Ekleme Formu -->
<div class="add-country-box">
    <h3 class="mb-3"><i class="fas fa-plus-circle"></i> Yeni Ülke Ekle</h3>
    <form id="addCountryForm" class="row g-3">
        <div class="col-md-3">
            <label for="ulke_adi" class="form-label">Ülke Adı *</label>
            <input type="text" class="form-control" id="ulke_adi" name="ulke_adi" required 
                   placeholder="Örnek: Arnavutluk">
        </div>
        <div class="col-md-3">
            <label for="ulke_adi_en" class="form-label">İngilizce Adı</label>
            <input type="text" class="form-control" id="ulke_adi_en" name="ulke_adi_en" 
                   placeholder="Örnek: Albania">
        </div>
        <div class="col-md-3">
            <label for="bolge" class="form-label">Bölge *</label>
            <select class="form-select" id="bolge" name="bolge" required>
                <option value="">Seçiniz</option>
                <option value="Avrupa">Avrupa</option>
                <option value="Asya">Asya</option>
                <option value="Afrika">Afrika</option>
                <option value="Amerika">Amerika</option>
                <option value="Okyanusya">Okyanusya</option>
                <option value="Diğer">Diğer</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-success w-100">
                <i class="fas fa-plus"></i> Ülke Ekle
            </button>
        </div>
    </form>
</div>

<!-- Ülke Listesi (Bölgelere Göre) -->
<?php foreach($ulkeler_bolge as $bolge => $bolge_ulkeleri): ?>
    <div class="region-section">
        <div class="region-title">
            <div class="region-icon">
                <?php
                $icons = [
                    'Avrupa' => 'fa-landmark',
                    'Asya' => 'fa-torii-gate',
                    'Afrika' => 'fa-mountain',
                    'Amerika' => 'fa-flag-usa',
                    'Okyanusya' => 'fa-island-tropical',
                    'Diğer' => 'fa-globe'
                ];
                $icon = $icons[$bolge] ?? 'fa-globe';
                ?>
                <i class="fas <?php echo $icon; ?>"></i>
            </div>
            <?php echo $bolge; ?> <span class="text-muted">(<?php echo count($bolge_ulkeleri); ?> ülke)</span>
        </div>
        
        <div class="country-grid">
            <?php foreach($bolge_ulkeleri as $ulke): ?>
                <div class="country-card <?php echo $ulke['kullanim_sayisi'] > 0 ? 'used' : 'not-used'; ?>">
                    <div class="country-name">
                        <span class="country-flag">🌍</span>
                        <?php echo safeHtml($ulke['ulke_adi']); ?>
                    </div>
                    <?php if ($ulke['ulke_adi_en']): ?>
                        <small class="text-muted"><?php echo safeHtml($ulke['ulke_adi_en']); ?></small>
                    <?php endif; ?>
                    
                    <div class="country-info">
                        <div>
                            <?php if ($ulke['kullanim_sayisi'] > 0): ?>
                                <span class="usage-badge active">
                                    <i class="fas fa-check"></i> 
                                    <?php echo $ulke['kullanim_sayisi']; ?> ithalat
                                </span>
                            <?php else: ?>
                                <span class="usage-badge inactive">
                                    <i class="fas fa-minus"></i> Kullanılmamış
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="country-actions">
                            <?php if ($ulke['kullanim_sayisi'] == 0 && $ulke['ulke_kodu'] != 'diger'): ?>
                                <button class="btn btn-sm btn-danger btn-delete-country" 
                                        onclick="deleteCountry('<?php echo $ulke['ulke_kodu']; ?>', '<?php echo safeHtml($ulke['ulke_adi']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php else: ?>
                                <span class="text-muted" title="Kullanılan ülke silinemez">
                                    <i class="fas fa-lock"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($ulke['son_kullanim']): ?>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-clock"></i> Son: <?php echo formatTarih($ulke['son_kullanim']); ?>
                        </small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<script>
// Yeni ülke ekleme
document.getElementById('addCountryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('api/add-country.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            alert('✅ Ülke başarıyla eklendi!');
            location.reload();
        } else {
            alert('❌ Hata: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Bir hata oluştu!');
    });
});

// Ülke silme
function deleteCountry(ulke_kodu, ulke_adi) {
    if(!confirm(`"${ulke_adi}" ülkesini silmek istediğinizden emin misiniz?`)) {
        return;
    }
    
    fetch('api/delete-country.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ ulke_kodu: ulke_kodu })
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            alert('✅ Ülke silindi!');
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
</script>