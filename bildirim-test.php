<?php
/**
 * Bildirim Sistemi Test Sayfası
 * Bu sayfayı çalıştırarak sistemin durumunu görebilirsiniz
 */

require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Bildirim Sistemi Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .test-box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔔 Bildirim Sistemi Test Raporu</h1>

    <?php
    $testler = [];
    
    // TEST 1: Veritabanı Bağlantısı
    echo "<div class='test-box'>";
    echo "<h2>1️⃣ Veritabanı Bağlantısı</h2>";
    try {
        $db = getDB();
        echo "<p class='success'>✅ Bağlantı başarılı</p>";
        $testler['db'] = true;
    } catch(Exception $e) {
        echo "<p class='error'>❌ Bağlantı hatası: " . $e->getMessage() . "</p>";
        $testler['db'] = false;
    }
    echo "</div>";
    
    // TEST 2: bildirimler Tablosu
    echo "<div class='test-box'>";
    echo "<h2>2️⃣ 'bildirimler' Tablosu Kontrolü</h2>";
    try {
        $sql = "SHOW TABLES LIKE 'bildirimler'";
        $stmt = $db->query($sql);
        $exists = $stmt->rowCount() > 0;
        
        if ($exists) {
            echo "<p class='success'>✅ Tablo mevcut</p>";
            
            // Kayıt sayısı
            $count = $db->query("SELECT COUNT(*) FROM bildirimler")->fetchColumn();
            echo "<p>📊 Toplam bildirim: <strong>$count</strong></p>";
            
            // Son 3 bildirim
            $last = $db->query("SELECT * FROM bildirimler ORDER BY id DESC LIMIT 3")->fetchAll();
            if ($last) {
                echo "<p>Son 3 bildirim:</p><pre>" . print_r($last, true) . "</pre>";
            }
            
            $testler['tablo_bildirimler'] = true;
        } else {
            echo "<p class='error'>❌ 'bildirimler' tablosu bulunamadı!</p>";
            echo "<p class='warning'>⚠️ Tabloyu oluşturmak için SQL çalıştırmanız gerekiyor.</p>";
            $testler['tablo_bildirimler'] = false;
        }
    } catch(Exception $e) {
        echo "<p class='error'>❌ Tablo kontrol hatası: " . $e->getMessage() . "</p>";
        $testler['tablo_bildirimler'] = false;
    }
    echo "</div>";
    
    // TEST 3: bildirim_ozetleri View
    echo "<div class='test-box'>";
    echo "<h2>3️⃣ 'bildirim_ozetleri' View Kontrolü</h2>";
    try {
        $sql = "SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_" . DB_NAME . " = 'bildirim_ozetleri'";
        $stmt = $db->query($sql);
        $exists = $stmt->rowCount() > 0;
        
        if ($exists) {
            echo "<p class='success'>✅ View mevcut</p>";
            
            // Özet istatistikler
            $ozet = $db->query("SELECT * FROM bildirim_ozetleri")->fetch();
            echo "<pre>" . print_r($ozet, true) . "</pre>";
            
            $testler['view_ozet'] = true;
        } else {
            echo "<p class='error'>❌ 'bildirim_ozetleri' view'i bulunamadı!</p>";
            $testler['view_ozet'] = false;
        }
    } catch(Exception $e) {
        echo "<p class='error'>❌ View kontrol hatası: " . $e->getMessage() . "</p>";
        $testler['view_ozet'] = false;
    }
    echo "</div>";
    
    // TEST 4: API Dosyaları
    echo "<div class='test-box'>";
    echo "<h2>4️⃣ API Dosyaları Kontrolü</h2>";
    
    $api_files = [
        'api/bildirim-okundu.php',
        'api/bildirim-sil.php',
        'api/bildirim-tumunu-okundu.php',
        'api/bildirim-olustur.php',
        'api/bildirim-sayisi.php'
    ];
    
    $eksik = [];
    foreach($api_files as $file) {
        if (file_exists($file)) {
            echo "<p class='success'>✅ $file</p>";
        } else {
            echo "<p class='error'>❌ $file eksik!</p>";
            $eksik[] = $file;
        }
    }
    
    $testler['api_files'] = empty($eksik);
    echo "</div>";
    
    // TEST 5: Stored Procedure
    echo "<div class='test-box'>";
    echo "<h2>5️⃣ Stored Procedure Kontrolü</h2>";
    try {
        $sql = "SHOW PROCEDURE STATUS WHERE Db = '" . DB_NAME . "' AND Name = 'otomatik_bildirim_olustur'";
        $stmt = $db->query($sql);
        $exists = $stmt->rowCount() > 0;
        
        if ($exists) {
            echo "<p class='success'>✅ 'otomatik_bildirim_olustur' procedure mevcut</p>";
            $testler['procedure'] = true;
        } else {
            echo "<p class='warning'>⚠️ 'otomatik_bildirim_olustur' procedure bulunamadı</p>";
            echo "<p>Bu procedure otomatik bildirimler için gerekli.</p>";
            $testler['procedure'] = false;
        }
    } catch(Exception $e) {
        echo "<p class='error'>❌ Procedure kontrol hatası: " . $e->getMessage() . "</p>";
        $testler['procedure'] = false;
    }
    echo "</div>";
    
    // ÖZET RAPOR
    echo "<div class='test-box' style='background: #e3f2fd;'>";
    echo "<h2>📋 Özet Rapor</h2>";
    
    $basarili = array_filter($testler);
    $toplam = count($testler);
    $oran = round((count($basarili) / $toplam) * 100);
    
    echo "<p><strong>Başarı Oranı:</strong> " . count($basarili) . "/$toplam ($oran%)</p>";
    
    if ($oran == 100) {
        echo "<p class='success' style='font-size: 1.2em;'>🎉 Bildirim sistemi tam olarak çalışıyor!</p>";
    } elseif ($oran >= 60) {
        echo "<p class='warning' style='font-size: 1.2em;'>⚠️ Sistem kısmen çalışıyor. Eksiklikler var.</p>";
    } else {
        echo "<p class='error' style='font-size: 1.2em;'>❌ Sistem çalışmıyor. Ciddi eksiklikler var.</p>";
    }
    
    echo "<h3>Eksik Olanlar:</h3><ul>";
    foreach($testler as $test => $sonuc) {
        if (!$sonuc) {
            echo "<li class='error'>$test</li>";
        }
    }
    echo "</ul>";
    
    echo "</div>";
    
    // TEST 6: Örnek Bildirim Oluştur
    if ($testler['tablo_bildirimler'] ?? false) {
        echo "<div class='test-box' style='background: #fff3cd;'>";
        echo "<h2>6️⃣ Test Bildirimi Oluştur</h2>";
        
        if (isset($_GET['test_create'])) {
            try {
                $sql = "INSERT INTO bildirimler 
                        (ithalat_id, bildirim_tipi, baslik, mesaj, oncelik) 
                        VALUES 
                        (NULL, 'genel', 'Test Bildirimi', 'Bu bir test bildirimidir - " . date('Y-m-d H:i:s') . "', 'normal')";
                $db->exec($sql);
                echo "<p class='success'>✅ Test bildirimi oluşturuldu! <a href='?page=bildirimler'>Bildirimleri görüntüle</a></p>";
            } catch(Exception $e) {
                echo "<p class='error'>❌ Bildirim oluşturulamadı: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p><a href='?test_create=1' class='btn'>🧪 Test Bildirimi Oluştur</a></p>";
        }
        
        echo "</div>";
    }
    ?>
    
    <div class="test-box">
        <h2>🔄 Yenile</h2>
        <p><a href="?">🔄 Sayfayı Yenile</a></p>
    </div>

</body>
</html>