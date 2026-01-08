<?php
/**
 * GTIP Örnek Veriler API
 * Sık kullanılan GTIP kodlarını otomatik ekler
 */

require_once '../config/database.php';
require_once '../config/settings.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    // Örnek GTIP kodları
    $ornekler = [
        ['1605.51.00', 'Ahtapot, hazır veya konserve', 'Deniz Ürünleri', 5, 0, 20],
        ['1605.52.00', 'Kalamar, hazır veya konserve', 'Deniz Ürünleri', 5, 0, 20],
        ['1605.21.10', 'Karides, Black Tiger, donmuş', 'Deniz Ürünleri', 5, 10, 20],
        ['1605.21.90', 'Karides, diğer, donmuş', 'Deniz Ürünleri', 5, 10, 20],
        ['0303.89.90', 'Diğer balıklar, donmuş', 'Deniz Ürünleri', 3, 0, 20],
        ['1604.20.05', 'Balık filetosu, hazırlanmış', 'Deniz Ürünleri', 8, 0, 20],
        ['1605.10.00', 'İstakoz, hazır veya konserve', 'Deniz Ürünleri', 5, 10, 20],
        ['1605.30.90', 'Mürekkep balığı, diğer', 'Deniz Ürünleri', 5, 0, 20],
        ['0307.71.00', 'Midye, canlı, taze veya soğutulmuş', 'Deniz Ürünleri', 3, 0, 20],
        ['0307.91.00', 'Deniz kestanesi, canlı, taze', 'Deniz Ürünleri', 3, 0, 20]
    ];
    
    $eklenen = 0;
    
    foreach ($ornekler as $ornek) {
        // Zaten var mı kontrol et
        $sql_check = "SELECT COUNT(*) as sayi FROM gtip_kodlari WHERE gtip_kodu = :gtip_kodu";
        $stmt_check = $db->prepare($sql_check);
        $stmt_check->execute([':gtip_kodu' => $ornek[0]]);
        $check = $stmt_check->fetch();
        
        if ($check['sayi'] == 0) {
            // Ekle
            $sql = "INSERT INTO gtip_kodlari (
                gtip_kodu, 
                aciklama, 
                kategori, 
                varsayilan_gumruk_orani, 
                varsayilan_otv_orani, 
                varsayilan_kdv_orani, 
                aktif
            ) VALUES (?, ?, ?, ?, ?, ?, 1)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($ornek);
            $eklenen++;
        }
    }
    
    jsonResponse(true, 'Örnek veriler eklendi!', [
        'eklenen' => $eklenen,
        'toplam' => count($ornekler)
    ]);
    
} catch(Exception $e) {
    error_log("Örnek Veriler Hatası: " . $e->getMessage());
    jsonResponse(false, 'Bir hata oluştu: ' . $e->getMessage());
}