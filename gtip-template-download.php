<?php
/**
 * GTIP Excel Şablonu İndirme API
 * Örnek CSV dosyası oluşturur
 */

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="gtip-sablon.csv"');

// UTF-8 BOM ekle (Excel için Türkçe karakter desteği)
echo "\xEF\xBB\xBF";

// Başlıklar
$basliklar = [
    'GTIP Kodu',
    'Açıklama',
    'Kategori',
    'Gümrük %',
    'ÖTV %',
    'KDV %'
];

echo implode(',', $basliklar) . "\n";

// Örnek satırlar
$ornekler = [
    ['1605.51.00', 'Ahtapot, hazır veya konserve', 'Deniz Ürünleri', '5', '0', '20'],
    ['1605.52.00', 'Kalamar, hazır veya konserve', 'Deniz Ürünleri', '5', '0', '20'],
    ['1605.21.10', 'Karides, Black Tiger, donmuş', 'Deniz Ürünleri', '5', '10', '20'],
    ['1605.21.90', 'Karides, diğer, donmuş', 'Deniz Ürünleri', '5', '10', '20'],
    ['0303.89.90', 'Diğer balıklar, donmuş', 'Deniz Ürünleri', '3', '0', '20']
];

foreach ($ornekler as $ornek) {
    echo implode(',', $ornek) . "\n";
}

exit;