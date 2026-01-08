# 📁 Images Klasörü

Bu klasör, İthalat Yönetim Sistemi için gerekli tüm görselleri içerir.

## 📋 Gerekli Görseller

### 1. **logo.png**
- **Boyut:** 200x50 piksel (önerilen)
- **Format:** PNG (şeffaf arka plan)
- **Kullanım:** Sistem başlığında ve bildirimler için
- **Konum:** `assets/images/logo.png`

### 2. **favicon.ico**
- **Boyut:** 32x32 piksel
- **Format:** ICO
- **Kullanım:** Tarayıcı sekmesinde
- **Konum:** `assets/images/favicon.ico`

### 3. **default-avatar.png** (opsiyonel)
- **Boyut:** 128x128 piksel
- **Format:** PNG
- **Kullanım:** Kullanıcı profil fotoğrafı yoksa
- **Konum:** `assets/images/default-avatar.png`

### 4. **no-image.png** (opsiyonel)
- **Boyut:** 300x200 piksel
- **Format:** PNG
- **Kullanım:** Ürün görseli yoksa
- **Konum:** `assets/images/no-image.png`

### 5. **loading.gif** (opsiyonel)
- **Boyut:** 50x50 piksel
- **Format:** GIF (animasyonlu)
- **Kullanım:** Yüklenme ekranı
- **Konum:** `assets/images/loading.gif`

## 🎨 Görsel Önerileri

### Logo için:
- Kocamanlar Balık logosunu kullanın
- Şeffaf arka plan (PNG)
- Yüksek çözünürlük
- Beyaz ya da koyu arka planda görünür renk

### Favicon için:
- Logodan türetilmiş basit ikon
- Küçük boyutta net görünür
- ICO formatı (PNG de kullanılabilir)

## 📥 Görselleri Yükleme

1. Görselleri hazırlayın
2. Bu klasöre yükleyin: `/assets/images/`
3. Dosya isimlerini yukarıdaki gibi adlandırın

## 🔗 HTML'de Kullanım

```html
<!-- Logo -->
<img src="assets/images/logo.png" alt="Kocamanlar Balık Logo">

<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
```

## ⚠️ Önemli Notlar

- Tüm görseller için telif hakkına dikkat edin
- Dosya boyutlarını optimize edin (hızlı yükleme için)
- Responsive tasarım için yüksek çözünürlük kullanın
- Görsel olmadan sistem çalışır, ancak logo eksikliği fark edilir

## 📂 Klasör Yapısı

```
assets/images/
├── logo.png              (Zorunlu)
├── favicon.ico           (Önerilen)
├── default-avatar.png    (Opsiyonel)
├── no-image.png          (Opsiyonel)
├── loading.gif           (Opsiyonel)
└── README.md             (Bu dosya)
```

---

**Not:** Eğer logo dosyanız yoksa, sistem düzgün çalışacak ancak logo yerine metin görünecektir.