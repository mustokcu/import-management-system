<?php
/**
 * Excel Export API
 * İthalat raporlarını Excel formatında indirir
 */

require_once '../config/database.php';
require_once '../config/settings.php';

// PhpSpreadsheet kütüphanesini yükle
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Parametreleri al
$baslangic = $_GET['baslangic'] ?? date('Y-01-01');
$bitis = $_GET['bitis'] ?? date('Y-m-d');
$rapor_tipi = $_GET['tip'] ?? 'genel'; // genel, tedarikci, urun, gider

try {
    $db = getDB();
    
    // Spreadsheet oluştur
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('Kocamanlar Balık')
        ->setTitle('İthalat Raporu')
        ->setSubject('İthalat Yönetim Sistemi')
        ->setDescription('Otomatik oluşturulmuş ithalat raporu');
    
    // Rapor tipine göre veri çek ve export et
    switch($rapor_tipi) {
        case 'genel':
            exportGenelRapor($spreadsheet, $db, $baslangic, $bitis);
            break;
        case 'tedarikci':
            exportTedarikciRapor($spreadsheet, $db, $baslangic, $bitis);
            break;
        case 'urun':
            exportUrunRapor($spreadsheet, $db, $baslangic, $bitis);
            break;
        case 'gider':
            exportGiderRapor($spreadsheet, $db, $baslangic, $bitis);
            break;
        case 'detayli':
            exportDetayliRapor($spreadsheet, $db, $baslangic, $bitis);
            break;
        default:
            exportGenelRapor($spreadsheet, $db, $baslangic, $bitis);
    }
    
    // Excel dosyasını indir
    $filename = "ithalat_raporu_{$rapor_tipi}_" . date('Ymd_His') . ".xlsx";
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
} catch(Exception $e) {
    die('Excel oluşturma hatası: ' . $e->getMessage());
}

// ================== RAPOR FONKSİYONLARI ==================

/**
 * Genel İthalat Raporu
 */
function exportGenelRapor($spreadsheet, $db, $baslangic, $bitis) {
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Genel Rapor');
    
    // Başlık
    $sheet->setCellValue('A1', 'KOCAMANLAR BALIK - İTHALAT RAPORU');
    $sheet->mergeCells('A1:J1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', "Tarih Aralığı: " . formatTarih($baslangic) . " - " . formatTarih($bitis));
    $sheet->mergeCells('A2:J2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Veri çek
    $sql = "SELECT 
        i.id,
        i.tedarikci_firma,
        i.tedarikci_ulke,
        i.siparis_tarihi,
        i.ithalat_durumu,
        u.urun_latince_isim,
        u.urun_cinsi,
        u.toplam_siparis_kg,
        o.ilk_alis_fiyati,
        o.para_birimi,
        o.toplam_fatura_tutari,
        o.odeme_id,
        g.toplam_gider,
        s.tr_varis_tarihi
    FROM ithalat i
    LEFT JOIN urun_detaylari u ON i.id = u.ithalat_id
    LEFT JOIN odemeler o ON i.id = o.ithalat_id
    LEFT JOIN giderler g ON i.id = g.ithalat_id
    LEFT JOIN sevkiyat s ON i.id = s.ithalat_id
    WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis
    ORDER BY i.siparis_tarihi DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':baslangic' => $baslangic, ':bitis' => $bitis]);
    $data = $stmt->fetchAll();
    
    // Header satırı (4. satır)
    $headers = ['ID', 'Ödeme ID', 'Tedarikçi', 'Ülke', 'Sipariş Tarihi', 'Ürün', 
                'Miktar (KG)', 'Birim Fiyat', 'Toplam Fatura', 'Giderler', 'Durum'];
    
    $col = 'A';
    foreach($headers as $header) {
        $sheet->setCellValue($col . '4', $header);
        $col++;
    }
    
    // Header stilini ayarla
    $sheet->getStyle('A4:K4')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '3498db']
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    
    // Veri satırları
    $row = 5;
    $toplam_kg = 0;
    $toplam_fatura = 0;
    $toplam_gider_sum = 0;
    
    foreach($data as $item) {
        $kg = $item['toplam_siparis_kg'] ?? 0;
        $fatura = $item['toplam_fatura_tutari'] ?? 0;
        $gider = $item['toplam_gider'] ?? 0;
        
        $sheet->setCellValue('A' . $row, $item['id']);
        $sheet->setCellValue('B' . $row, $item['odeme_id']);
        $sheet->setCellValue('C' . $row, $item['tedarikci_firma']);
        $sheet->setCellValue('D' . $row, $item['tedarikci_ulke']);
        $sheet->setCellValue('E' . $row, formatTarih($item['siparis_tarihi']));
        $sheet->setCellValue('F' . $row, $item['urun_latince_isim'] . ' - ' . $item['urun_cinsi']);
        $sheet->setCellValue('G' . $row, number_format($kg, 2, ',', '.'));
        $sheet->setCellValue('H' . $row, ($item['para_birimi'] ?? 'USD') . ' ' . number_format($item['ilk_alis_fiyati'] ?? 0, 2, ',', '.'));
        $sheet->setCellValue('I' . $row, ($item['para_birimi'] ?? 'USD') . ' ' . number_format($fatura, 2, ',', '.'));
        $sheet->setCellValue('J' . $row, 'TL ' . number_format($gider, 2, ',', '.'));
        $sheet->setCellValue('K' . $row, getDurumText($item['ithalat_durumu']));
        
        $toplam_kg += $kg;
        $toplam_fatura += $fatura;
        $toplam_gider_sum += $gider;
        
        // Alternatif satır rengi
        if ($row % 2 == 0) {
            $sheet->getStyle('A' . $row . ':K' . $row)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8F9FA']
                ]
            ]);
        }
        
        $row++;
    }
    
    // Toplam satırı
    $sheet->setCellValue('A' . $row, 'TOPLAM');
    $sheet->mergeCells('A' . $row . ':F' . $row);
    $sheet->setCellValue('G' . $row, number_format($toplam_kg, 2, ',', '.') . ' KG');
    $sheet->setCellValue('I' . $row, 'USD ' . number_format($toplam_fatura, 2, ',', '.'));
    $sheet->setCellValue('J' . $row, 'TL ' . number_format($toplam_gider_sum, 2, ',', '.'));
    
    $sheet->getStyle('A' . $row . ':K' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 12],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E8F5E9']
        ],
        'borders' => [
            'top' => ['borderStyle' => Border::BORDER_THICK]
        ]
    ]);
    
    // Kolon genişliklerini ayarla
    $sheet->getColumnDimension('A')->setWidth(8);
    $sheet->getColumnDimension('B')->setWidth(18);
    $sheet->getColumnDimension('C')->setWidth(25);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(30);
    $sheet->getColumnDimension('G')->setWidth(15);
    $sheet->getColumnDimension('H')->setWidth(15);
    $sheet->getColumnDimension('I')->setWidth(15);
    $sheet->getColumnDimension('J')->setWidth(15);
    $sheet->getColumnDimension('K')->setWidth(15);
    
    // Tüm hücrelere border ekle
    $sheet->getStyle('A4:K' . ($row))->applyFromArray([
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]
        ]
    ]);
}

/**
 * Tedarikçi Bazlı Rapor
 */
function exportTedarikciRapor($spreadsheet, $db, $baslangic, $bitis) {
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Tedarikçi Raporu');
    
    // Başlık
    $sheet->setCellValue('A1', 'TEDARİKÇİ BAZLI ANALİZ RAPORU');
    $sheet->mergeCells('A1:F1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Veri çek
    $sql = "SELECT 
        i.tedarikci_firma,
        i.tedarikci_ulke,
        COUNT(i.id) as ithalat_sayisi,
        SUM(u.toplam_siparis_kg) as toplam_kg,
        AVG(o.ilk_alis_fiyati) as ortalama_fiyat,
        SUM(o.ilk_alis_fiyati * u.toplam_siparis_kg) as toplam_tutar
    FROM ithalat i
    LEFT JOIN urun_detaylari u ON i.id = u.ithalat_id
    LEFT JOIN odemeler o ON i.id = o.ithalat_id
    WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis
    GROUP BY i.tedarikci_firma, i.tedarikci_ulke
    ORDER BY toplam_tutar DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':baslangic' => $baslangic, ':bitis' => $bitis]);
    $data = $stmt->fetchAll();
    
    // Header
    $headers = ['Tedarikçi', 'Ülke', 'İthalat Sayısı', 'Toplam KG', 'Ort. Fiyat', 'Toplam Tutar'];
    $col = 'A';
    foreach($headers as $header) {
        $sheet->setCellValue($col . '3', $header);
        $col++;
    }
    
    $sheet->getStyle('A3:F3')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '27ae60']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    
    // Veri
    $row = 4;
    foreach($data as $item) {
        $sheet->setCellValue('A' . $row, $item['tedarikci_firma']);
        $sheet->setCellValue('B' . $row, $item['tedarikci_ulke']);
        $sheet->setCellValue('C' . $row, $item['ithalat_sayisi']);
        $sheet->setCellValue('D' . $row, number_format($item['toplam_kg'] ?? 0, 2, ',', '.'));
        $sheet->setCellValue('E' . $row, 'USD ' . number_format($item['ortalama_fiyat'] ?? 0, 2, ',', '.'));
        $sheet->setCellValue('F' . $row, 'USD ' . number_format($item['toplam_tutar'] ?? 0, 2, ',', '.'));
        $row++;
    }
    
    // Kolon genişlikleri
    foreach(range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

/**
 * Ürün Bazlı Rapor
 */
function exportUrunRapor($spreadsheet, $db, $baslangic, $bitis) {
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Ürün Raporu');
    
    $sheet->setCellValue('A1', 'ÜRÜN BAZLI ANALİZ RAPORU');
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $sql = "SELECT 
        u.urun_latince_isim,
        u.urun_cinsi,
        COUNT(i.id) as ithalat_sayisi,
        SUM(u.toplam_siparis_kg) as toplam_kg,
        AVG(o.ilk_alis_fiyati) as ortalama_fiyat
    FROM ithalat i
    LEFT JOIN urun_detaylari u ON i.id = u.ithalat_id
    LEFT JOIN odemeler o ON i.id = o.ithalat_id
    WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis
    GROUP BY u.urun_latince_isim, u.urun_cinsi
    ORDER BY toplam_kg DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':baslangic' => $baslangic, ':bitis' => $bitis]);
    $data = $stmt->fetchAll();
    
    // Header
    $headers = ['Latince İsim', 'Ürün Cinsi', 'İthalat Sayısı', 'Toplam KG', 'Ort. KG Fiyatı'];
    $col = 'A';
    foreach($headers as $header) {
        $sheet->setCellValue($col . '3', $header);
        $col++;
    }
    
    $sheet->getStyle('A3:E3')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f39c12']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    
    $row = 4;
    foreach($data as $item) {
        $sheet->setCellValue('A' . $row, $item['urun_latince_isim']);
        $sheet->setCellValue('B' . $row, $item['urun_cinsi']);
        $sheet->setCellValue('C' . $row, $item['ithalat_sayisi']);
        $sheet->setCellValue('D' . $row, number_format($item['toplam_kg'] ?? 0, 2, ',', '.'));
        $sheet->setCellValue('E' . $row, 'USD ' . number_format($item['ortalama_fiyat'] ?? 0, 2, ',', '.'));
        $row++;
    }
    
    foreach(range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

/**
 * Gider Raporu
 */
function exportGiderRapor($spreadsheet, $db, $baslangic, $bitis) {
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Gider Raporu');
    
    $sheet->setCellValue('A1', 'GİDER ANALİZ RAPORU');
    $sheet->mergeCells('A1:G1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $sql = "SELECT 
        SUM(g.gumruk_ucreti) as toplam_gumruk,
        SUM(g.tarim_hizmet_ucreti) as toplam_tarim,
        SUM(g.nakliye_bedeli) as toplam_nakliye,
        SUM(g.sigorta_bedeli) as toplam_sigorta,
        SUM(g.ardiye_ucreti) as toplam_ardiye,
        SUM(g.demoraj_ucreti) as toplam_demoraj,
        SUM(g.toplam_gider) as genel_toplam
    FROM ithalat i
    LEFT JOIN giderler g ON i.id = g.ithalat_id
    WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':baslangic' => $baslangic, ':bitis' => $bitis]);
    $gider = $stmt->fetch();
    
    $row = 3;
    $gider_tipleri = [
        ['Gümrük Ücretleri', $gider['toplam_gumruk']],
        ['Tarım Bakanlığı Ücretleri', $gider['toplam_tarim']],
        ['Nakliye Bedelleri', $gider['toplam_nakliye']],
        ['Sigorta Bedelleri', $gider['toplam_sigorta']],
        ['Ardiye Ücretleri', $gider['toplam_ardiye']],
        ['Demoraj Ücretleri', $gider['toplam_demoraj']]
    ];
    
    $sheet->setCellValue('A' . $row, 'Gider Türü');
    $sheet->setCellValue('B' . $row, 'Tutar (TL)');
    $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'e74c3c']]
    ]);
    
    $row++;
    foreach($gider_tipleri as $tip) {
        $sheet->setCellValue('A' . $row, $tip[0]);
        $sheet->setCellValue('B' . $row, 'TL ' . number_format($tip[1] ?? 0, 2, ',', '.'));
        $row++;
    }
    
    $row++;
    $sheet->setCellValue('A' . $row, 'TOPLAM GİDERLER');
    $sheet->setCellValue('B' . $row, 'TL ' . number_format($gider['genel_toplam'] ?? 0, 2, ',', '.'));
    $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 14],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFEB3B']]
    ]);
    
    $sheet->getColumnDimension('A')->setWidth(30);
    $sheet->getColumnDimension('B')->setWidth(20);
}

/**
 * Detaylı Rapor (Tüm alanlar)
 */
function exportDetayliRapor($spreadsheet, $db, $baslangic, $bitis) {
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Detaylı Rapor');
    
    $sheet->setCellValue('A1', 'DETAYLI İTHALAT RAPORU');
    $sheet->mergeCells('A1:P1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Çok detaylı SQL sorgusu
    $sql = "SELECT 
        i.id, i.tedarikci_firma, i.tedarikci_ulke, i.mensei_ulke,
        i.siparis_tarihi, i.tahmini_teslim_ayi, i.ithalat_durumu,
        u.urun_latince_isim, u.urun_cinsi, u.urun_tipi, u.glz_orani,
        u.toplam_siparis_kg,
        o.odeme_id, o.ilk_alis_fiyati, o.para_birimi, o.toplam_fatura_tutari,
        o.tranship_ek_maliyet, o.komisyon_tutari,
        g.gumruk_ucreti, g.tarim_hizmet_ucreti, g.nakliye_bedeli,
        g.ardiye_ucreti, g.demoraj_ucreti, g.toplam_gider,
        s.yukleme_tarihi, s.tahmini_varis_tarihi, s.tr_varis_tarihi,
        s.konteyner_numarasi, s.gemi_adi,
        s.original_evrak_durumu, s.telex_durumu
    FROM ithalat i
    LEFT JOIN urun_detaylari u ON i.id = u.ithalat_id
    LEFT JOIN odemeler o ON i.id = o.ithalat_id
    LEFT JOIN giderler g ON i.id = g.ithalat_id
    LEFT JOIN sevkiyat s ON i.id = s.ithalat_id
    WHERE i.siparis_tarihi BETWEEN :baslangic AND :bitis
    ORDER BY i.siparis_tarihi DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':baslangic' => $baslangic, ':bitis' => $bitis]);
    $data = $stmt->fetchAll();
    
    // 16 kolonlu detaylı header
    $headers = [
        'ID', 'Tedarikçi', 'Tedarikçi Ülke', 'Ürün', 'Sipariş Tarihi',
        'Miktar (KG)', 'Birim Fiyat', 'Toplam Fatura', 'Gümrük', 'Nakliye',
        'Toplam Gider', 'Konteyner No', 'Gemi', 'Yükleme', 'Varış', 'Durum'
    ];
    
    $col = 'A';
    foreach($headers as $header) {
        $sheet->setCellValue($col . '3', $header);
        $col++;
    }
    
    $sheet->getStyle('A3:P3')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8e44ad']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    
    $row = 4;
    foreach($data as $item) {
        $sheet->setCellValue('A' . $row, $item['id']);
        $sheet->setCellValue('B' . $row, $item['tedarikci_firma']);
        $sheet->setCellValue('C' . $row, $item['tedarikci_ulke']);
        $sheet->setCellValue('D' . $row, $item['urun_latince_isim']);
        $sheet->setCellValue('E' . $row, formatTarih($item['siparis_tarihi']));
        $sheet->setCellValue('F' . $row, number_format($item['toplam_siparis_kg'] ?? 0, 2));
        $sheet->setCellValue('G' . $row, number_format($item['ilk_alis_fiyati'] ?? 0, 2));
        $sheet->setCellValue('H' . $row, number_format($item['toplam_fatura_tutari'] ?? 0, 2));
        $sheet->setCellValue('I' . $row, number_format($item['gumruk_ucreti'] ?? 0, 2));
        $sheet->setCellValue('J' . $row, number_format($item['nakliye_bedeli'] ?? 0, 2));
        $sheet->setCellValue('K' . $row, number_format($item['toplam_gider'] ?? 0, 2));
        $sheet->setCellValue('L' . $row, $item['konteyner_numarasi']);
        $sheet->setCellValue('M' . $row, $item['gemi_adi']);
        $sheet->setCellValue('N' . $row, formatTarih($item['yukleme_tarihi']));
        $sheet->setCellValue('O' . $row, formatTarih($item['tr_varis_tarihi']));
        $sheet->setCellValue('P' . $row, getDurumText($item['ithalat_durumu']));
        $row++;
    }
    
    // Auto-size tüm kolonlar
    foreach(range('A', 'P') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

// Yardımcı fonksiyon
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