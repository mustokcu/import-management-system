<?php
/**
 * ===========================================
 * DOSYA 1: api/bildirim-okundu.php
 * Bildirimi okundu olarak işaretle
 * ===========================================
 */
// api/bildirim-okundu.php
<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID gerekli']);
    exit;
}

try {
    $db = getDB();
    $sql = "UPDATE bildirimler SET okundu = 1, okunma_tarihi = NOW() WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $data['id']]);
    
    echo json_encode(['success' => true]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

<?php
/**
 * ===========================================
 * DOSYA 2: api/bildirim-sil.php
 * Bildirimi sil
 * ===========================================
 */
// api/bildirim-sil.php
<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID gerekli']);
    exit;
}

try {
    $db = getDB();
    $sql = "DELETE FROM bildirimler WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $data['id']]);
    
    echo json_encode(['success' => true]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

<?php
/**
 * ===========================================
 * DOSYA 3: api/bildirim-tumunu-okundu.php
 * Tüm bildirimleri okundu işaretle
 * ===========================================
 */
// api/bildirim-tumunu-okundu.php
<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $db = getDB();
    $sql = "UPDATE bildirimler SET okundu = 1, okunma_tarihi = NOW() WHERE okundu = 0";
    $stmt = $db->query($sql);
    
    echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

<?php
/**
 * ===========================================
 * DOSYA 4: api/bildirim-olustur.php
 * Manuel bildirim oluştur (admin için)
 * ===========================================
 */
// api/bildirim-olustur.php
<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST gerekli']);
    exit;
}

$ithalat_id = $_POST['ithalat_id'] ?? null;
$bildirim_tipi = $_POST['bildirim_tipi'] ?? 'genel';
$baslik = $_POST['baslik'] ?? '';
$mesaj = $_POST['mesaj'] ?? '';
$oncelik = $_POST['oncelik'] ?? 'normal';

if (empty($baslik) || empty($mesaj)) {
    echo json_encode(['success' => false, 'message' => 'Başlık ve mesaj gerekli']);
    exit;
}

try {
    $db = getDB();
    $sql = "INSERT INTO bildirimler (ithalat_id, bildirim_tipi, baslik, mesaj, oncelik) 
            VALUES (:ithalat_id, :bildirim_tipi, :baslik, :mesaj, :oncelik)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':ithalat_id' => $ithalat_id,
        ':bildirim_tipi' => $bildirim_tipi,
        ':baslik' => $baslik,
        ':mesaj' => $mesaj,
        ':oncelik' => $oncelik
    ]);
    
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

<?php
/**
 * ===========================================
 * DOSYA 5: api/bildirim-sayisi.php
 * Okunmamış bildirim sayısı (AJAX için)
 * ===========================================
 */
// api/bildirim-sayisi.php
<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $db = getDB();
    $sql = "SELECT COUNT(*) as sayi FROM bildirimler WHERE okundu = 0";
    $stmt = $db->query($sql);
    $result = $stmt->fetch();
    
    echo json_encode(['success' => true, 'count' => $result['sayi']]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

<?php
/**
 * ===========================================
 * DOSYA 6: api/bildirim-otomatik-olustur.php
 * Otomatik bildirim oluşturma (Cron için)
 * ===========================================
 */
// api/bildirim-otomatik-olustur.php
<?php
require_once '../config/database.php';

try {
    $db = getDB();
    
    // Stored procedure'ü çağır
    $db->query("CALL otomatik_bildirim_olustur()");
    
    echo "✅ Otomatik bildirimler oluşturuldu: " . date('Y-m-d H:i:s');
} catch(Exception $e) {
    echo "❌ Hata: " . $e->getMessage();
}
?>