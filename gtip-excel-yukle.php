<?php
/**
 * GTIP Excel Yükleme API
 * Excel veya CSV dosyasından toplu GTIP kodu yükler
 * 
 * Beklenen format:
 * GTIP Kodu | Açıklama | Kategori | Gümrük % | ÖTV % | KDV %
 */

require_once '../config/database.php';
require_once '../config/settings.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = getDB();
    
    // Dosya yüklendi mi kontrol et
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, 'Dosya yüklenemedi!');
    }
    
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Dosya tipi kontrolü
    if (!in_array($fileExt, ['csv', 'xlsx', 'xls'])) {
        jsonResponse(false, 'Sadece CSV, XLS veya XLSX dosyaları kabul edilir!');
    }
    
    $eklenen = 0;
    $guncellenen = 0;
    $hatalar = [];
    
    // CSV dosyası ise
    if ($fileExt === 'csv') {
        $handle = fopen($fileTmpName, 'r');
        
        if ($handle === false) {
            jsonResponse(false, 'CSV dosyası açılamadı!');
        }
        
        // İlk satırı atla (başlık satırı)
        $header = fgetcsv($handle, 1000, ',');
        
        $satir = 1;
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $satir++;
            
            // En az 2 sütun olmalı (GTIP + Açıklama)
            if (count($data) < 2) {
                $hatalar[] = "Satır $satir: Yetersiz veri";
                continue;
            }
            
            $gtipKodu = trim($data[0]);
            $aciklama = trim($data[1]);
            $kategori = isset($data[2]) ? trim($data[2]) : '';
            $gumruk = isset($data[3]) ? floatval($data[3]) : 0;
            $otv = isset($data[4]) ? floatval($data[4]) : 0;
            $kdv = isset($data[5]) ? floatval($data[5]) : 20;
            
            // Boş satır atla
            if (empty($gtipKodu) || empty($aciklama)) {
                continue;
            }
            
            try {
                // Var mı kontrol et
                $sql_check = "SELECT id FROM gtip_kodlari WHERE gtip_kodu = :gtip_kodu";
                $stmt_check = $db->prepare($sql_check);
                $stmt_check->execute([':gtip_kodu' => $gtipKodu]);
                $existing = $stmt_check->fetch();
                
                if ($existing) {
                    // Güncelle
                    $sql_update = "UPDATE gtip_kodlari SET
                        aciklama = :aciklama,
                        kategori = :kategori,
                        varsayilan_gumruk_orani = :gumruk,
                        varsayilan_otv_orani = :otv,
                        varsayilan_kdv_orani = :kdv
                    WHERE gtip_kodu = :gtip_kodu";
                    
                    $stmt_update = $db->prepare($sql_update);
                    $stmt_update->execute([
                        ':aciklama' => $aciklama,
                        ':kategori' => $kategori,
                        ':gumruk' => $gumruk,
                        ':otv' => $otv,
                        ':kdv' => $kdv,
                        ':gtip_kodu' => $gtipKodu
                    ]);
                    
                    $guncellenen++;
                } else {
                    // Ekle
                    $sql_insert = "INSERT INTO gtip_kodlari (
                        gtip_kodu, 
                        aciklama, 
                        kategori, 
                        varsayilan_gumruk_orani, 
                        varsayilan_otv_orani, 
                        varsayilan_kdv_orani, 
                        aktif
                    ) VALUES (
                        :gtip_kodu, 
                        :aciklama, 
                        :kategori, 
                        :gumruk, 
                        :otv, 
                        :kdv, 
                        1
                    )";
                    
                    $stmt_insert = $db->prepare($sql_insert);
                    $stmt_insert->execute([
                        ':gtip_kodu' => $gtipKodu,
                        ':aciklama' => $aciklama,
                        ':kategori' => $kategori,
                        ':gumruk' => $gumruk,
                        ':otv' => $otv,
                        ':kdv' => $kdv
                    ]);
                    
                    $eklenen++;
                }
            } catch(Exception $e) {
                $hatalar[] = "Satır $satir: " . $e->getMessage();
            }
        }
        
        fclose($handle);
        
    } else {
        // Excel dosyası için (basit yöntem - CSV'ye çevir)
        jsonResponse(false, 'Excel desteği için lütfen dosyayı CSV olarak kaydedin!');
    }
    
    $mesaj = "Yükleme tamamlandı! Eklenen: $eklenen, Güncellenen: $guncellenen";
    if (count($hatalar) > 0) {
        $mesaj .= "\n\nHatalar:\n" . implode("\n", array_slice($hatalar, 0, 10));
    }
    
    jsonResponse(true, $mesaj, [
        'eklenen' => $eklenen,
        'guncellenen' => $guncellenen,
        'hata_sayisi' => count($hatalar),
        'hatalar' => array_slice($hatalar, 0, 10)
    ]);
    
} catch(Exception $e) {
    error_log("GTIP Excel Yükleme Hatası: " . $e->getMessage());
    jsonResponse(false, 'Bir hata oluştu: ' . $e->getMessage());
}