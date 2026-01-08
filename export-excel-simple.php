<?php
/**
 * BASİT EXCEL EXPORT (Kütüphane Gerektirmez)
 * CSV formatında Excel benzeri dosya oluşturur
 */

require_once '../config/database.php';
require_once '../config/settings.php';

$baslangic = $_GET['baslangic'] ?? date('Y-01-01');
$bitis = $_GET['bitis'] ?? date('Y-m-d');

// ✅ Ek filtreler
$filtre_durum = $_GET['durum'] ?? '';
$filtre_tedarikci = $_GET['tedarikci'] ?? '';

try {
    $db = getDB();
    
    // Dinamik WHERE koşulları
    $where_conditions = ["i.siparis_tarihi BETWEEN :baslangic AND :bitis"];
    $params = [':baslangic' => $baslangic, ':bitis' => $bitis];
    
    if (!empty($filtre_durum)) {
        $where_conditions[] = "i.ithalat_durumu = :durum";
        $params[':durum'] = $filtre_durum;
    }
    
    if (!empty($filtre_tedarikci)) {
        $where_conditions[] = "i.tedarikci_firma LIKE :tedarikci";
        $params[':tedarikci'] = "%$filtre_tedarikci%";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Veri çek
    $sql = "SELECT 
        i.id,
        i.tedarikci_firma,
        i.tedarikci_ulke,
        i.siparis_tarihi,
        u.urun_latince_isim,
        u.urun_cinsi,
        u.toplam_siparis_kg,
        o.ilk_alis_fiyati,
        o.para_birimi,
        o.toplam_fatura_tutari,
        o.odeme_id,
        g.toplam_gider,
        i.ithalat_durumu
    FROM ithalat i
    LEFT JOIN urun_detaylari u ON i.id = u.ithalat_id
    LEFT JOIN odemeler o ON i.id = o.ithalat_id
    LEFT JOIN giderler g ON i.id = g.ithalat_id
    WHERE $where_clause
    ORDER BY i.siparis_tarihi DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    // Excel (CSV) dosyası olarak indir
    $filename = "ithalat_raporu_" . date('Ymd_His') . ".csv";
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // UTF-8 BOM ekle (Excel için Türkçe karakter desteği)
    echo "\xEF\xBB\xBF";
    
    // CSV çıktısı oluştur
    $output = fopen('php://output', 'w');
    
    // Başlık satırı
    fputcsv($output, [
        'ID',
        'Ödeme ID',
        'Tedarikçi',
        'Ülke',
        'Sipariş Tarihi',
        'Ürün (Latince)',
        'Ürün Cinsi',
        'Miktar (KG)',
        'Birim Fiyat',
        'Toplam Fatura',
        'Giderler (TL)',
        'Durum'
    ], ';'); // Excel Türkçe için ; kullanır
    
    // Veri satırları
    foreach($data as $row) {
        fputcsv($output, [
            $row['id'],
            $row['odeme_id'] ?? '-',
            $row['tedarikci_firma'],
            $row['tedarikci_ulke'],
            formatTarih($row['siparis_tarihi']),
            $row['urun_latince_isim'],
            $row['urun_cinsi'],
            number_format($row['toplam_siparis_kg'] ?? 0, 2, ',', '.'),
            ($row['para_birimi'] ?? 'USD') . ' ' . number_format($row['ilk_alis_fiyati'] ?? 0, 2, ',', '.'),
            ($row['para_birimi'] ?? 'USD') . ' ' . number_format($row['toplam_fatura_tutari'] ?? 0, 2, ',', '.'),
            'TL ' . number_format($row['toplam_gider'] ?? 0, 2, ',', '.'),
            getDurumText($row['ithalat_durumu'])
        ], ';');
    }
    
    fclose($output);
    exit;
    
} catch(Exception $e) {
    die('Hata: ' . $e->getMessage());
}

function getDurumText($durum) {
    $durumlar = [
        'siparis_verildi' => 'Sipariş Verildi',
        'yolda' => 'Yolda',
        'transitte' => 'Transit\'te',
        'limanda' => 'Limanda',
        'teslim_edildi' => 'Teslim Edildi'
    ];
    return $durumlar[$durum] ?? $durum;
}
?>