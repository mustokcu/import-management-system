<?php
/**
 * =================================
 * DOSYA 1: api/urun-ekle.php
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
    
    // Ürün durumu (checkbox array)
    $urun_durumu = null;
    if (isset($_POST['urun_durumu']) && is_array($_POST['urun_durumu'])) {
        $urun_durumu = implode(',', array_map('cleanInput', $_POST['urun_durumu']));
    }
    
    // Validasyon
    if (empty($urun_latince_isim) || empty($urun_cinsi) || empty($urun_tipi)) {
        throw new Exception('Zorunlu alanlar doldurulmalıdır!');
    }
    
    // Aynı ürün var mı kontrol et
    $check_sql = "SELECT id FROM urun_katalog 
                  WHERE urun_cinsi = :cinsi 
                  AND urun_latince_isim = :latince 
                  AND COALESCE(kalibre, '') = COALESCE(:kalibre, '')";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->execute([
        ':cinsi' => $urun_cinsi,
        ':latince' => $urun_latince_isim,
        ':kalibre' => $kalibre
    ]);
    
    if ($check_stmt->fetch()) {
        throw new Exception('Bu ürün zaten kayıtlı! (Aynı isim ve kalibre)');
    }
    
    // Ürünü ekle
    $sql = "INSERT INTO urun_katalog (
        urun_latince_isim, urun_cinsi, urun_tipi, kalibre,
        glz_orani, kalite_terimi, urun_durumu, koli_kg_cesidi,
        avcilik_bol_donemi, aktif
    ) VALUES (
        :urun_latince_isim, :urun_cinsi, :urun_tipi, :kalibre,
        :glz_orani, :kalite_terimi, :urun_durumu, :koli_kg_cesidi,
        :avcilik_bol_donemi, :aktif
    )";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
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
        'message' => 'Ürün başarıyla eklendi!',
        'id' => $db->lastInsertId()
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>