<?php
/**
 * PDF Export API - KAPSAMLI İTHALAT RAPORU
 * ✅ Tüm detaylar dahil
 * ✅ Vergi hesaplamaları
 * ✅ Giderler özeti
 * ✅ A4 formatına optimize
 */

require_once '../config/database.php';
require_once '../config/settings.php';
require_once '../lib/fpdf.php';

class PDF extends FPDF {
    private function tr($text) {
        return iconv('UTF-8', 'windows-1254//TRANSLIT', $text);
    }
    
    function Header() {
        // Logo bölgesi (logo varsa)
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(52, 152, 219);
        $this->Cell(0, 8, $this->tr('KOCAMANLAR BALIK'), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, $this->tr('İthalat Detay Raporu'), 0, 1, 'C');
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(3);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(1);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(95, 5, 'Sayfa ' . $this->PageNo(), 0, 0, 'L');
        $this->Cell(95, 5, $this->tr('Oluşturma Tarihi: ' . date('d.m.Y H:i')), 0, 0, 'R');
        $this->SetTextColor(0, 0, 0);
    }
    
    function SectionTitle($title, $icon = '') {
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(52, 152, 219);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 6, $icon . ' ' . $this->tr($title), 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(1);
    }
    
    function InfoBox($data) {
        $this->SetFillColor(245, 245, 245);
        $y_start = $this->GetY();
        $this->Rect(10, $y_start, 190, count($data) * 5 + 2, 'F');
        
        $this->SetFont('Arial', '', 8);
        foreach($data as $row) {
            $this->Cell(50, 5, $this->tr($row[0] . ':'), 0, 0, 'L');
            $this->SetFont('Arial', 'B', 8);
            $this->Cell(140, 5, $this->tr($row[1]), 0, 1, 'L');
            $this->SetFont('Arial', '', 8);
        }
        $this->Ln(2);
    }
    
    function InfoRow2Col($label1, $value1, $label2, $value2) {
        $this->SetFont('Arial', '', 8);
        $this->Cell(35, 4, $this->tr($label1 . ':'), 0, 0, 'L');
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(60, 4, $this->tr($value1), 0, 0, 'L');
        
        $this->SetFont('Arial', '', 8);
        $this->Cell(35, 4, $this->tr($label2 . ':'), 0, 0, 'L');
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(60, 4, $this->tr($value2), 0, 1, 'L');
    }
    
    function TableHeader($headers, $widths) {
        $this->SetFont('Arial', 'B', 7);
        $this->SetFillColor(52, 152, 219);
        $this->SetTextColor(255, 255, 255);
        foreach($headers as $i => $header) {
            $this->Cell($widths[$i], 5, $this->tr($header), 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetTextColor(0, 0, 0);
    }
    
    function TableRow($cells, $widths, $aligns, $fill = false) {
        $this->SetFont('Arial', '', 7);
        if($fill) $this->SetFillColor(250, 250, 250);
        foreach($cells as $i => $cell) {
            $this->Cell($widths[$i], 4, $this->tr($cell), 1, 0, $aligns[$i], $fill);
        }
        $this->Ln();
    }
}

// Parametreler
$ithalat_id = $_GET['id'] ?? null;

if (!$ithalat_id) {
    die('Hata: İthalat ID belirtilmedi!');
}

try {
    $db = getDB();
    
    // ========================================
    // VERİ ÇEKME - TÜM TABLOLAR
    // ========================================
    
    $sql = "SELECT 
        i.*,
        i.id as ithalat_id,
        i.notlar as ithalat_notlar,
        o.usd_kur,
        o.kur_tarihi,
        o.kur_notu,
        o.ilk_alis_fiyati,
        o.komisyon_firma,
        o.komisyon_tutari,
        o.on_odeme_orani,
        o.avans_1_tutari,
        o.avans_1_tarihi,
        o.avans_1_kur,
        o.avans_2_tutari,
        o.avans_2_tarihi,
        o.avans_2_kur,
        o.final_odeme_tutari,
        o.final_odeme_tarihi,
        o.final_odeme_kur,
        g.gumruk_ucreti,
        g.tarim_hizmet_ucreti,
        g.nakliye_bedeli,
        g.sigorta_bedeli,
        g.ardiye_ucreti,
        g.demoraj_ucreti,
        g.toplam_gider,
        s.yukleme_limani,
        s.bosaltma_limani,
        s.konteyner_numarasi,
        s.gemi_adi,
        s.yukleme_tarihi,
        s.tahmini_varis_tarihi,
        s.tr_varis_tarihi,
        s.nakliye_dahil,
        s.navlun_odeme_sorumlusu,
        s.konteyner_sorumlu,
        s.original_evrak_durumu,
        s.original_evrak_tarih,
        s.telex_durumu,
        s.telex_tarih,
        f.firma_adi as ithalatci_firma_adi,
        f.dosya_no_prefix
    FROM ithalat i
    LEFT JOIN odemeler o ON i.id = o.ithalat_id
    LEFT JOIN giderler g ON i.id = g.ithalat_id
    LEFT JOIN sevkiyat s ON i.id = s.ithalat_id
    LEFT JOIN ithalatci_firmalar f ON i.ithalatci_firma_id = f.id
    WHERE i.id = :id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $ithalat_id]);
    $data = $stmt->fetch();
    
    if (!$data) {
        die('Hata: İthalat kaydı bulunamadı!');
    }
    
    // Ürün listesi (vergi dahil)
    $sql_urunler = "SELECT 
        iu.*,
        uk.urun_latince_isim,
        uk.urun_cinsi,
        uk.kalibre
    FROM ithalat_urunler iu
    LEFT JOIN urun_katalog uk ON iu.urun_katalog_id = uk.id
    WHERE iu.ithalat_id = :ithalat_id
    ORDER BY iu.id";
    
    $stmt_urunler = $db->prepare($sql_urunler);
    $stmt_urunler->execute([':ithalat_id' => $ithalat_id]);
    $urun_listesi = $stmt_urunler->fetchAll();
    
    // ========================================
    // PDF OLUŞTUR
    // ========================================
    
    $pdf = new PDF();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 20);
    
    // ========================================
    // BAŞLIK BÖLGESİ
    // ========================================
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetFillColor(52, 152, 219);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(95, 8, 'DOSYA NO: ' . $data['balik_dunyasi_dosya_no'], 1, 0, 'C', true);
    $pdf->Cell(95, 8, 'ITHALAT ID: #' . $ithalat_id, 1, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(3);
    
    // ========================================
    // 1. İTHALATÇI FİRMA
    // ========================================
    
    $pdf->SectionTitle('1. ITHALATCI FIRMA BILGISI', '>>');
    $pdf->InfoBox([
        ['Firma', $data['ithalatci_firma_adi'] ?? 'Kocamanlar Balik'],
        ['Dosya No Formati', $data['dosya_no_prefix'] . '-YYYY-NNNN']
    ]);
    
    // ========================================
    // 2. TEDARİKÇİ BİLGİLERİ
    // ========================================
   $pdf->SectionTitle('2. TEDARIKCI BILGILERI', '>>');
$pdf->InfoRow2Col(
    'Tedarikci', $data['tedarikci_firma'] ?? '-',
    'Siparis Tarihi', formatTarih($data['siparis_tarihi'])
);
$pdf->InfoRow2Col(
    'Ted. Siparis No', $data['tedarikci_siparis_no'] ?? '-',
    'Tedarikci Ulke', getUlkeAdi($data['tedarikci_ulke'])
);
$pdf->InfoRow2Col(
    'Mensei Ulke', getUlkeAdi($data['mensei_ulke'] ?? '-'),
    'Ithalat Durumu', getDurumText($data['ithalat_durumu'])
);
$pdf->InfoRow2Col(
    'Tahmini Teslim', !empty($data['tahmini_teslim_ayi']) ? date('m/Y', strtotime($data['tahmini_teslim_ayi'])) : '-',
    '', ''
);
$pdf->Ln(2);
    
    // ========================================
    // 3. KUR BİLGİSİ
    // ========================================
    
    if (!empty($data['usd_kur'])) {
        $pdf->SectionTitle('3. KUR BILGISI', '>>');
        $pdf->InfoRow2Col(
            'USD/TL Kuru', number_format($data['usd_kur'], 4, ',', '.'),
            'Kur Tarihi', formatTarih($data['kur_tarihi'])
        );
        if (!empty($data['kur_notu'])) {
            $pdf->SetFont('Arial', 'I', 7);
            $pdf->Cell(0, 4, 'Not: ' . $data['kur_notu'], 0, 1);
        }
        $pdf->Ln(2);
    }
    
    // ========================================
    // 4. ÜRÜN LİSTESİ & VERGİ HESAPLAMALARI
    // ========================================
    
    $pdf->SectionTitle('4. URUN LISTESI & VERGI HESAPLAMALARI', '>>');
    
    if (count($urun_listesi) > 0) {
        $toplam_kg = 0;
        $toplam_usd = 0;
        $toplam_tl = 0;
        $toplam_vergi_tumu = 0;
        $toplam_vergi_dahil_tumu = 0;
        
        foreach($urun_listesi as $index => $urun) {
            // Ürün başlığı
            $urun_adi = $urun['urun_cinsi'];
            if ($urun['kalibre']) {
                $urun_adi .= ' (' . $urun['kalibre'] . ')';
            }
            
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(230, 240, 255);
            $pdf->Cell(190, 5, ($index + 1) . '. ' . $urun_adi . ' - ' . $urun['urun_latince_isim'], 1, 1, 'L', true);
            
            // Temel bilgiler
            $miktar = $urun['miktar_kg'] ?? 0;
            $birim_fiyat = $urun['birim_fiyat'] ?? 0;
            $usd_tutar = $urun['toplam_tutar'] ?? 0;
            $tl_tutar = $usd_tutar * ($data['usd_kur'] ?? 1);
            
            $toplam_kg += $miktar;
            $toplam_usd += $usd_tutar;
            $toplam_tl += $tl_tutar;
            
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell(40, 4, '  Miktar: ' . number_format($miktar, 2, ',', '.') . ' KG', 1, 0);
            $pdf->Cell(40, 4, 'Birim: $' . number_format($birim_fiyat, 2, ',', '.'), 1, 0);
            $pdf->Cell(50, 4, 'USD Toplam: $' . number_format($usd_tutar, 2, ',', '.'), 1, 0);
            $pdf->Cell(60, 4, 'TL Tutar: ' . number_format($tl_tutar, 2, ',', '.') . ' TL', 1, 1);
            
            // GTIP ve vergi bilgileri
            if (!empty($urun['gtip_kodu'])) {
                $pdf->SetFont('Arial', 'I', 6);
                $pdf->Cell(190, 3, '  GTIP: ' . $urun['gtip_kodu'] . ' - ' . substr($urun['gtip_aciklama'] ?? '', 0, 80), 1, 1);
                
                // Vergi detayları
                $gumruk_tutar = $urun['gumruk_vergisi_tutar'] ?? 0;
                $otv_tutar = $urun['otv_tutar'] ?? 0;
                $kdv_tutar = $urun['kdv_tutar'] ?? 0;
                $toplam_vergi = $urun['toplam_vergi'] ?? 0;
                $vergi_dahil = $urun['vergi_dahil_tutar'] ?? 0;
                
                $toplam_vergi_tumu += $toplam_vergi;
                $toplam_vergi_dahil_tumu += $vergi_dahil;
                
                $pdf->SetFont('Arial', '', 6);
                $pdf->Cell(47.5, 3, '  Gumruk: ' . number_format($gumruk_tutar, 0, ',', '.') . ' TL', 1, 0);
                $pdf->Cell(47.5, 3, 'OTV: ' . number_format($otv_tutar, 0, ',', '.') . ' TL', 1, 0);
                $pdf->Cell(47.5, 3, 'KDV: ' . number_format($kdv_tutar, 0, ',', '.') . ' TL', 1, 0);
                $pdf->Cell(47.5, 3, 'Top.Vergi: ' . number_format($toplam_vergi, 0, ',', '.') . ' TL', 1, 1);
                
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->SetFillColor(255, 250, 200);
                $pdf->Cell(190, 4, '  VERGI DAHIL TUTAR: ' . number_format($vergi_dahil, 2, ',', '.') . ' TL', 1, 1, 'R', true);
            }
            
            $pdf->Ln(1);
        }
        
        // Genel Toplam
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(200, 230, 200);
        $pdf->Cell(40, 5, 'GENEL TOPLAM', 1, 0, 'C', true);
        $pdf->Cell(40, 5, number_format($toplam_kg, 2, ',', '.') . ' KG', 1, 0, 'C', true);
        $pdf->Cell(50, 5, '$' . number_format($toplam_usd, 2, ',', '.'), 1, 0, 'C', true);
        $pdf->Cell(60, 5, number_format($toplam_tl, 2, ',', '.') . ' TL', 1, 1, 'C', true);
        
        if ($toplam_vergi_tumu > 0) {
            $pdf->SetFillColor(255, 220, 220);
            $pdf->Cell(130, 5, 'TOPLAM VERGI', 1, 0, 'R', true);
            $pdf->Cell(60, 5, number_format($toplam_vergi_tumu, 2, ',', '.') . ' TL', 1, 1, 'C', true);
            
            $pdf->SetFillColor(220, 255, 220);
            $pdf->Cell(130, 5, 'GENEL TOPLAM (VERGI DAHIL)', 1, 0, 'R', true);
            $pdf->Cell(60, 5, number_format($toplam_vergi_dahil_tumu, 2, ',', '.') . ' TL', 1, 1, 'C', true);
        }
        
    } else {
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 5, 'Urun bilgisi bulunamadi', 0, 1, 'C');
    }
    
    $pdf->Ln(3);
    
    // ========================================
    // 5. GİDERLER ÖZETİ
    // ========================================
    
    $giderler_var = ($data['toplam_gider'] ?? 0) > 0;
    
    if ($giderler_var) {
        $pdf->SectionTitle('5. GIDERLER OZETI', '>>');
        
        $pdf->SetFont('Arial', '', 7);
        $widths = [63, 63, 64];
        
        $giderler = [
            ['Gumruk Ucreti', $data['gumruk_ucreti'] ?? 0],
            ['Tarim Hizmet', $data['tarim_hizmet_ucreti'] ?? 0],
            ['Nakliye Bedeli', $data['nakliye_bedeli'] ?? 0],
            ['Sigorta Bedeli', $data['sigorta_bedeli'] ?? 0],
            ['Ardiye Ucreti', $data['ardiye_ucreti'] ?? 0],
            ['Demoraj Ucreti', $data['demoraj_ucreti'] ?? 0]
        ];
        
        $row_count = 0;
        $temp_row = [];
        
        foreach($giderler as $gider) {
            if ($gider[1] > 0) {
                $temp_row[] = $gider;
                if (count($temp_row) == 3) {
                    foreach($temp_row as $i => $g) {
                        $pdf->Cell($widths[$i], 4, $g[0] . ': ' . number_format($g[1], 2, ',', '.') . ' TL', 1, 0, 'L');
                    }
                    $pdf->Ln();
                    $temp_row = [];
                }
            }
        }
        
        if (count($temp_row) > 0) {
            foreach($temp_row as $i => $g) {
                $pdf->Cell($widths[$i], 4, $g[0] . ': ' . number_format($g[1], 2, ',', '.') . ' TL', 1, 0, 'L');
            }
            for($i = count($temp_row); $i < 3; $i++) {
                $pdf->Cell($widths[$i], 4, '', 1, 0);
            }
            $pdf->Ln();
        }
        
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(255, 200, 200);
        $pdf->Cell(130, 5, 'TOPLAM GIDER', 1, 0, 'R', true);
        $pdf->Cell(60, 5, number_format($data['toplam_gider'], 2, ',', '.') . ' TL', 1, 1, 'C', true);
        
        $pdf->Ln(2);
    }
    
    // ========================================
    // 6. SEVKİYAT BİLGİLERİ
    // ========================================
    
    $pdf->SectionTitle('6. SEVKIYAT BILGILERI', '>>');
    $pdf->InfoRow2Col(
        'Yukleme Limani', $data['yukleme_limani'] ?? '-',
        'Bosaltma Limani', $data['bosaltma_limani'] ?? '-'
    );
    $pdf->InfoRow2Col(
        'Konteyner No', $data['konteyner_numarasi'] ?? '-',
        'Gemi Adi', $data['gemi_adi'] ?? '-'
    );
    $pdf->InfoRow2Col(
        'Yukleme Tarihi', formatTarih($data['yukleme_tarihi']),
        'TR Varis Tarihi', formatTarih($data['tr_varis_tarihi'])
    );
    $pdf->InfoRow2Col(
        'Nakliye Dahil', $data['nakliye_dahil'] == 'evet' ? 'Evet' : 'Hayir',
        'Navlun Sorumlu', $data['navlun_odeme_sorumlusu'] ?? '-'
    );
    $pdf->Ln(2);
    
    // ========================================
    // 7. ÖDEME BİLGİLERİ
    // ========================================
    
    $odeme_var = !empty($data['avans_1_tutari']) || !empty($data['komisyon_tutari']);
    
    if ($odeme_var) {
        $pdf->SectionTitle('7. ODEME BILGILERI', '>>');
        
        if (!empty($data['ilk_alis_fiyati'])) {
            $pdf->InfoRow2Col(
                'Ilk Alis Fiyati', '$' . number_format($data['ilk_alis_fiyati'], 2, ',', '.'),
                'On Odeme Orani', $data['on_odeme_orani'] ?? '-'
            );
        }
        
        if (!empty($data['avans_1_tutari'])) {
            $pdf->InfoRow2Col(
                '1. Avans', '$' . number_format($data['avans_1_tutari'], 2, ',', '.') . ' (' . formatTarih($data['avans_1_tarihi']) . ')',
                'Kur', !empty($data['avans_1_kur']) ? number_format($data['avans_1_kur'], 4, ',', '.') : '-'
            );
        }
        
        if (!empty($data['avans_2_tutari'])) {
            $pdf->InfoRow2Col(
                '2. Avans', '$' . number_format($data['avans_2_tutari'], 2, ',', '.') . ' (' . formatTarih($data['avans_2_tarihi']) . ')',
                'Kur', !empty($data['avans_2_kur']) ? number_format($data['avans_2_kur'], 4, ',', '.') : '-'
            );
        }
        
        if (!empty($data['final_odeme_tutari'])) {
            $pdf->InfoRow2Col(
                'Final Odeme', '$' . number_format($data['final_odeme_tutari'], 2, ',', '.') . ' (' . formatTarih($data['final_odeme_tarihi']) . ')',
                'Kur', !empty($data['final_odeme_kur']) ? number_format($data['final_odeme_kur'], 4, ',', '.') : '-'
            );
        }
        
        if (!empty($data['komisyon_firma'])) {
            $pdf->InfoRow2Col(
                'Komisyon Firma', $data['komisyon_firma'],
                'Komisyon Tutari', !empty($data['komisyon_tutari']) ? '$' . number_format($data['komisyon_tutari'], 2, ',', '.') : '-'
            );
        }
        
        $pdf->Ln(2);
    }
    
    // ========================================
    // 8. EVRAK DURUMU
    // ========================================
    
    $pdf->SectionTitle('8. EVRAK TAKIP DURUMU', '>>');
    $pdf->InfoRow2Col(
        'Original Evrak', getEvrakDurum($data['original_evrak_durumu']) . ' (' . formatTarih($data['original_evrak_tarih']) . ')',
        'Telex', getEvrakDurum($data['telex_durumu']) . ' (' . formatTarih($data['telex_tarih']) . ')'
    );
    $pdf->Ln(2);
    
    // ========================================
    // 9. GTIP & KONTROL BELGESİ
    // ========================================
    
    $gtip_var = !empty($data['gtip_kodu']) || !empty($data['ithal_kontrol_belgesi_16']);
    
    if ($gtip_var) {
        $pdf->SectionTitle('9. GTIP & KONTROL BELGESI', '>>');
        
        if (!empty($data['gtip_kodu'])) {
            $pdf->InfoRow2Col(
                'GTIP Kodu', $data['gtip_kodu'],
                'GTIP Tipi', $data['gtip_tipi'] ?? '-'
            );
        }
        
        if (!empty($data['ithal_kontrol_belgesi_16'])) {
            $pdf->InfoRow2Col(
                'Ithal Kontrol Belgesi', $data['ithal_kontrol_belgesi_16'],
                'Tarim Bakanlik Onay', formatTarih($data['tarim_bakanlik_onay_tarihi'])
            );
        }
        
        $pdf->Ln(2);
    }
    
    // ========================================
    // 10. NOTLAR
    // ========================================
    
    if (!empty($data['ithalat_notlar'])) {
        $pdf->SectionTitle('10. NOTLAR', '>>');
        $pdf->SetFont('Arial', '', 8);
        $pdf->MultiCell(0, 4, $data['ithalat_notlar'], 0, 'L');
    }
    
    // ========================================
    // PDF İNDİR
    // ========================================
    
    $filename = "ithalat_" . $data['balik_dunyasi_dosya_no'] . "_" . date('Ymd') . ".pdf";
    $pdf->Output('D', $filename);
    exit;
    
} catch(Exception $e) {
    die('PDF olusturma hatasi: ' . $e->getMessage());
}

// ========================================
// YARDIMCI FONKSİYONLAR
// ========================================

function getDurumText($durum) {
    $durumlar = [
        'siparis_verildi' => 'Siparis Verildi',
        'yolda' => 'Yolda',
        'transitte' => 'Transit\'te',
        'limanda' => 'Limanda',
        'teslim_edildi' => 'Teslim Edildi'
    ];
    return $durumlar[$durum] ?? $durum;
}

function getEvrakDurum($durum) {
    $durumlar = [
        'bekleniyor' => 'Bekleniyor',
        'alindi' => 'Alindi',
        'teslim_edildi' => 'Teslim Edildi'
    ];
    return $durumlar[$durum] ?? $durum;
}

function getUlkeAdi($ulke_kodu) {
    if (empty($ulke_kodu) || $ulke_kodu == '-') return '-';
    
    $ulkeler = getUlkeler();
    return $ulkeler[$ulke_kodu] ?? $ulke_kodu;
}

// formatTarih() fonksiyonu settings.php'de tanımlı - tekrar tanımlamaya gerek yok

?>