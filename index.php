<?php
/**
 * Ana Sayfa - İthalat Yönetim Sistemi
 * Kocamanlar Balık - Modern Minimal Design
 */

require_once 'config/database.php';
require_once 'config/settings.php';

// Aktif sekmeyi belirle
$active_page = $_GET['page'] ?? 'veri-giris';
$allowed_pages = [
    'veri-giris', 
    'ithalat-takip', 
    'ithalat-detay',
    'ithalat-duzenle',
    'raporlar',
    'hesaplamalar',
    'ulke-yonetimi',
    'urun-yonetimi',
    'gtip-yonetimi',
    'bildirimler'
];

if (!in_array($active_page, $allowed_pages)) {
    $active_page = 'veri-giris';
}

// Detay sayfaları için navbar'ı gizle
$hide_navbar = in_array($active_page, ['ithalat-detay', 'ithalat-duzenle']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SYSTEM_TITLE . ' - ' . COMPANY_NAME; ?></title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
            --dark: #0f172a;
            --light: #f8fafc;
            --border: #e2e8f0;
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--light);
            color: var(--dark);
            font-size: 14px;
            line-height: 1.6;
        }
        
        /* Modern Container */
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--border);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--dark);
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--info));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .logo-text h1 {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            color: var(--dark);
        }
        
        .logo-text p {
            font-size: 11px;
            color: var(--secondary);
            margin: 0;
        }
        
        /* Navigation */
        .nav-menu {
            padding: 20px 12px;
        }
        
        .nav-section {
            margin-bottom: 24px;
        }
        
        .nav-section-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 12px;
            margin-bottom: 8px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            margin-bottom: 4px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--secondary);
            transition: all 0.2s ease;
            position: relative;
            font-weight: 500;
        }
        
        .nav-item:hover {
            background: var(--light);
            color: var(--dark);
        }
        
        .nav-item.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: var(--shadow);
        }
        
        .nav-item i {
            width: 20px;
            font-size: 16px;
            margin-right: 12px;
        }
        
        .nav-badge {
            margin-left: auto;
            background: var(--danger);
            color: white;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            min-height: 100vh;
        }
        
        .main-content.full-width {
            margin-left: 0;
            width: 100%;
        }
        
        /* Topbar */
        .topbar {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .topbar-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .topbar-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .topbar-btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: white;
            color: var(--dark);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .topbar-btn:hover {
            background: var(--light);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .topbar-btn.primary {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .topbar-btn.primary:hover {
            background: var(--primary-dark);
        }
        
        /* Content Area */
        .content-wrapper {
            padding: 32px;
        }
        
        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
        }
        
        .toast-custom {
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            animation: slideIn 0.3s ease;
        }
        
        .toast-custom.success { border-left-color: var(--success); }
        .toast-custom.error { border-left-color: var(--danger); }
        .toast-custom.warning { border-left-color: var(--warning); }
        .toast-custom.info { border-left-color: var(--info); }
        
        .toast-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .toast-icon.success { background: #d1fae5; color: var(--success); }
        .toast-icon.error { background: #fee2e2; color: var(--danger); }
        .toast-icon.warning { background: #fef3c7; color: var(--warning); }
        .toast-icon.info { background: #cffafe; color: var(--info); }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-title {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 2px;
        }
        
        .toast-message {
            font-size: 12px;
            color: var(--secondary);
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 56px;
            height: 56px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            border: none;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
            z-index: 1001;
            cursor: pointer;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .mobile-menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .topbar {
                padding: 12px 16px;
            }
            
            .content-wrapper {
                padding: 16px;
            }
            
            .topbar-title {
                font-size: 16px;
            }
        }
        
        /* Scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 10px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: var(--secondary);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php if (!$hide_navbar): ?>
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="?page=veri-giris" class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-ship"></i>
                    </div>
                    <div class="logo-text">
                        <h1><?php echo COMPANY_NAME; ?></h1>
                        <p>İthalat Yönetim</p>
                    </div>
                </a>
            </div>
            
            <nav class="nav-menu">
                <div class="nav-section">
                    <div class="nav-section-title">Ana Menü</div>
                    <a href="?page=veri-giris" class="nav-item <?php echo $active_page == 'veri-giris' ? 'active' : ''; ?>">
                        <i class="fas fa-plus-circle"></i>
                        Yeni İthalat
                    </a>
                    <a href="?page=ithalat-takip" class="nav-item <?php echo $active_page == 'ithalat-takip' ? 'active' : ''; ?>">
                        <i class="fas fa-list-alt"></i>
                        İthalat Takibi
                    </a>
                    <a href="?page=bildirimler" class="nav-item <?php echo $active_page == 'bildirimler' ? 'active' : ''; ?>">
                        <i class="fas fa-bell"></i>
                        Bildirimler
                        <span class="nav-badge" id="nav-badge" style="display: none;">0</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Analiz & Raporlar</div>
                    <a href="?page=raporlar" class="nav-item <?php echo $active_page == 'raporlar' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i>
                        Detaylı Raporlar
                    </a>
                    <a href="?page=hesaplamalar" class="nav-item <?php echo $active_page == 'hesaplamalar' ? 'active' : ''; ?>">
                        <i class="fas fa-calculator"></i>
                        Hesaplamalar
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Yönetim</div>
                    <a href="?page=urun-yonetimi" class="nav-item <?php echo $active_page == 'urun-yonetimi' ? 'active' : ''; ?>">
                        <i class="fas fa-box-open"></i>
                        Ürün Kataloğu
                    </a>
                    <a href="?page=gtip-yonetimi" class="nav-item <?php echo $active_page == 'gtip-yonetimi' ? 'active' : ''; ?>">
                        <i class="fas fa-barcode"></i>
                        GTIP Kodları
                    </a>
                    <a href="?page=ulke-yonetimi" class="nav-item <?php echo $active_page == 'ulke-yonetimi' ? 'active' : ''; ?>">
                        <i class="fas fa-globe-americas"></i>
                        Ülke Ayarları
                    </a>
                </div>
            </nav>
        </aside>
        <?php endif; ?>
        
        <!-- Main Content -->
        <main class="main-content <?php echo $hide_navbar ? 'full-width' : ''; ?>">
            <?php if (!$hide_navbar): ?>
            <div class="topbar">
                <div class="topbar-title">
                    <i class="fas fa-<?php 
                        $icons = [
                            'veri-giris' => 'plus-circle',
                            'ithalat-takip' => 'list-alt',
                            'bildirimler' => 'bell',
                            'raporlar' => 'chart-line',
                            'hesaplamalar' => 'calculator',
                            'urun-yonetimi' => 'box-open',
                            'gtip-yonetimi' => 'barcode',
                            'ulke-yonetimi' => 'globe-americas'
                        ];
                        echo $icons[$active_page] ?? 'file';
                    ?>"></i>
                    <?php 
                        $titles = [
                            'veri-giris' => 'Yeni İthalat Kaydı',
                            'ithalat-takip' => 'İthalat Takip Listesi',
                            'bildirimler' => 'Bildirim Merkezi',
                            'raporlar' => 'Detaylı Raporlar',
                            'hesaplamalar' => 'Otomatik Hesaplamalar',
                            'urun-yonetimi' => 'Ürün Kataloğu Yönetimi',
                            'gtip-yonetimi' => 'GTIP Kod Yönetimi',
                            'ulke-yonetimi' => 'Ülke Yönetimi'
                        ];
                        echo $titles[$active_page] ?? 'Sistem';
                    ?>
                </div>
                <div class="topbar-actions">
                    <button class="topbar-btn" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i>
                        Yenile
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="content-wrapper">
                <?php
                // Seçilen sayfayı dahil et
                $page_file = "pages/{$active_page}.php";
                
                if (file_exists($page_file)) {
                    include $page_file;
                } else {
                    echo '<div class="alert alert-danger">';
                    echo '<i class="fas fa-exclamation-triangle"></i> ';
                    echo 'Sayfa bulunamadı: ' . htmlspecialchars($active_page);
                    echo '</div>';
                }
                ?>
            </div>
        </main>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- Mobile Menu Toggle -->
    <?php if (!$hide_navbar): ?>
    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
        <i class="fas fa-bars"></i>
    </button>
    <?php endif; ?>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <script>
        // Mobile Menu Toggle
        function toggleMobileMenu() {
            document.getElementById('sidebar').classList.toggle('active');
        }
        
        // Bildirim Sayısı Güncelle
        function bildirimSayisiGuncelle() {
            fetch('api/bildirim-sayisi.php')
                .then(response => response.json())
                .then(data => {
                    if(data.success && data.count > 0) {
                        const badge = document.getElementById('nav-badge');
                        if(badge) {
                            badge.textContent = data.count;
                            badge.style.display = 'inline-block';
                            document.title = '(' + data.count + ') ' + '<?php echo SYSTEM_TITLE; ?>';
                        }
                    } else {
                        const badge = document.getElementById('nav-badge');
                        if(badge) badge.style.display = 'none';
                        document.title = '<?php echo SYSTEM_TITLE . " - " . COMPANY_NAME; ?>';
                    }
                })
                .catch(error => console.error('Bildirim hatası:', error));
        }
        
        // Modern Toast Notification
        function showToast(message, type = 'info', title = '') {
            const container = document.getElementById('toastContainer');
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-times-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            const titles = {
                success: title || 'Başarılı',
                error: title || 'Hata',
                warning: title || 'Uyarı',
                info: title || 'Bilgi'
            };
            
            const toast = document.createElement('div');
            toast.className = `toast-custom ${type}`;
            toast.innerHTML = `
                <div class="toast-icon ${type}">
                    <i class="fas ${icons[type]}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${titles[type]}</div>
                    <div class="toast-message">${message}</div>
                </div>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // Sayfa Yüklendiğinde
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ Modern İthalat Sistemi Hazır!');
            bildirimSayisiGuncelle();
            setInterval(bildirimSayisiGuncelle, 30000);
        });
    </script>
</body>
</html>