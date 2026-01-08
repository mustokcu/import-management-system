<?php
/**
 * =================================
 * DOSYA 3: api/urun-guncelle.php
 * =================================
 */
?>
<?php
require_once '../config/database.php';
require_once '../config/settings.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek metodu']);
    exit;
}

try {
    $db = getDB();
    
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        throw new Exception('ID belirtilmedi!');
    }
    
    // Form verilerini al
    $urun_latince_isim = cleanInput($_POST['urun_latince_isim'] ?? '');
    $urun_cinsi = cleanInput($_POST['urun_cinsi'] ?? '');
    $urun_tipi = cleanInput($_POST['urun_tipi'] ?? '');
    $kalibre = cleanInput($_POST['kalibre'] ?? null);
    $glz_orani = !empty($_POST['glz_orani']) ? floatval($_POST['glz_orani']) : null;
    $kalite_terimi = cleanInput($_POST['kalite_terimi'] ?? null);
    $koli_kg_cesidi = cleanInput($_POST['koli_kg_cesidi'] ?? null);
    $avcilik_bol_donemi = cleanInput($_POST['avcilik_bol_donemi'] ?? null);
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    
    // Ürün durumu
    $urun_durumu = null;
    if (isset($_POST['urun_durumu']) && is_array($_POST['urun_durumu'])) {
        $urun_durumu = implode(',', array_map('cleanInput', $_POST['urun_durumu']));
    }
    
    // Validasyon
    if (empty($urun_latince_isim) || empty($urun_cinsi) || empty($urun_tipi)) {
        throw new Exception('Zorunlu alanlar doldurulmalıdır!');
    }
    
    // Güncelle
    $sql = "UPDATE urun_katalog SET
        urun_latince_isim = :urun_latince_isim,
        urun_cinsi = :urun_cinsi,
        urun_tipi = :urun_tipi,
        kalibre = :kalibre,
        glz_orani = :glz_orani,
        kalite_terimi = :kalite_terimi,
        urun_durumu = :urun_durumu,
        koli_kg_cesidi = :koli_kg_cesidi,
        avcilik_bol_donemi = :avcilik_bol_donemi,
        aktif = :aktif
    WHERE id = :id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':urun_latince_isim' => $urun_latince_isim,
        ':urun_cinsi' => $urun_cinsi,
        ':urun_tipi' => $urun_tipi,
        ':kalibre' => $kalibre,
        ':glz_orani' => $glz_orani,
        ':kalite_terimi' => $kalite_terimi,
        ':urun_durumu' => $urun_durumu,
        ':koli_kg_cesidi' => $koli_kg_cesidi,
        ':avcilik_bol_donemi' => $avcilik_bol_donemi,
        ':aktif' => $aktif
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Ürün başarıyla güncellendi!'
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
