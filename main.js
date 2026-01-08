/**
 * İthalat Yönetim Sistemi - Ana JavaScript Dosyası
 * Kocamanlar Balık
 */

// ==================== GLOBAL VARIABLES ====================
const APP = {
    name: 'İthalat Yönetim Sistemi',
    version: '1.0.0',
    debug: true
};

// ==================== UTILITY FUNCTIONS ====================

/**
 * Console Log Helper
 */
function log(message, type = 'info') {
    if (!APP.debug) return;
    
    const styles = {
        info: 'color: #3498db',
        success: 'color: #27ae60',
        warning: 'color: #f39c12',
        error: 'color: #e74c3c'
    };
    
    console.log(`%c[${APP.name}] ${message}`, styles[type] || styles.info);
}

/**
 * Number Formatter (Turkish locale)
 */
function formatNumber(number, decimals = 2) {
    return new Intl.NumberFormat('tr-TR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(number);
}

/**
 * Currency Formatter
 */
function formatCurrency(amount, currency = 'USD') {
    const formatted = formatNumber(amount, 2);
    const symbols = {
        'USD': '$',
        'EUR': '€',
        'TL': '₺'
    };
    return `${symbols[currency] || currency} ${formatted}`;
}

/**
 * Date Formatter
 */
function formatDate(dateString, format = 'dd.mm.yyyy') {
    if (!dateString) return '-';
    
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    
    return format.replace('dd', day).replace('mm', month).replace('yyyy', year);
}

/**
 * Show Loading Overlay
 */
function showLoading(message = 'Yükleniyor...') {
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.id = 'loadingOverlay';
    overlay.innerHTML = `
        <div class="loading-content">
            <div class="spinner"></div>
            <p class="mt-3">${message}</p>
        </div>
    `;
    document.body.appendChild(overlay);
}

/**
 * Hide Loading Overlay
 */
function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}

/**
 * Show Toast Notification
 */
function showToast(message, type = 'info', duration = 3000) {
    const colors = {
        success: '#27ae60',
        error: '#e74c3c',
        warning: '#f39c12',
        info: '#3498db'
    };
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-times-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colors[type] || colors.info};
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideInRight 0.3s ease-in-out;
    `;
    
    toast.innerHTML = `
        <i class="fas ${icons[type] || icons.info}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideInRight 0.3s ease-in-out reverse';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/**
 * Confirm Dialog
 */
function confirmDialog(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * AJAX Helper Function
 */
async function ajaxRequest(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        return result;
    } catch (error) {
        log('AJAX Error: ' + error.message, 'error');
        throw error;
    }
}

// ==================== FORM VALIDATION ====================

/**
 * Validate Form
 */
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('is-invalid');
            showToast(`${field.name} alanı zorunludur!`, 'error');
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

/**
 * Clear Form
 */
function clearForm(formId) {
    const form = document.getElementById(formId);
    if (form) {
        form.reset();
        form.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
    }
}

// ==================== TABLE FUNCTIONS ====================

/**
 * Filter Table
 */
function filterTable(tableId, searchTerm) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.getElementsByTagName('tr');
    searchTerm = searchTerm.toLowerCase();
    
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const text = row.textContent.toLowerCase();
        
        if (text.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}

/**
 * Sort Table
 */
function sortTable(tableId, columnIndex, ascending = true) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = Array.from(tbody.getElementsByTagName('tr'));
    
    rows.sort((a, b) => {
        const aValue = a.getElementsByTagName('td')[columnIndex].textContent;
        const bValue = b.getElementsByTagName('td')[columnIndex].textContent;
        
        if (ascending) {
            return aValue.localeCompare(bValue, 'tr');
        } else {
            return bValue.localeCompare(aValue, 'tr');
        }
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// ==================== DATA CALCULATIONS ====================

/**
 * Calculate Total Cost
 */
function calculateTotalCost(kg, unitPrice, additionalCosts = 0) {
    return (kg * unitPrice) + additionalCosts;
}

/**
 * Calculate Exchange Rate Difference
 */
function calculateExchangeDifference(amount, oldRate, newRate) {
    return amount * (newRate - oldRate);
}

/**
 * Calculate Cost per KG
 */
function calculateCostPerKg(totalCost, totalKg) {
    return totalKg > 0 ? totalCost / totalKg : 0;
}

// ==================== LOCAL STORAGE HELPERS ====================

/**
 * Save to Local Storage
 */
function saveToStorage(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
        return true;
    } catch (error) {
        log('Storage Error: ' + error.message, 'error');
        return false;
    }
}

/**
 * Get from Local Storage
 */
function getFromStorage(key) {
    try {
        const value = localStorage.getItem(key);
        return value ? JSON.parse(value) : null;
    } catch (error) {
        log('Storage Error: ' + error.message, 'error');
        return null;
    }
}

/**
 * Remove from Local Storage
 */
function removeFromStorage(key) {
    try {
        localStorage.removeItem(key);
        return true;
    } catch (error) {
        log('Storage Error: ' + error.message, 'error');
        return false;
    }
}

// ==================== EXPORT FUNCTIONS ====================

/**
 * Export Table to CSV
 */
function exportTableToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = Array.from(cols).map(col => {
            return '"' + col.textContent.replace(/"/g, '""') + '"';
        });
        csv.push(rowData.join(','));
    });
    
    // Download CSV
    const csvContent = csv.join('\n');
    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Print Page
 */
function printPage() {
    window.print();
}

// ==================== AUTO-SAVE FORM ====================

/**
 * Auto-save Form Data
 */
function enableAutoSave(formId, interval = 30000) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    setInterval(() => {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        saveToStorage(`autosave_${formId}`, data);
        log('Form otomatik kaydedildi', 'success');
    }, interval);
}

/**
 * Restore Auto-saved Form
 */
function restoreAutoSave(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const savedData = getFromStorage(`autosave_${formId}`);
    if (!savedData) return false;
    
    Object.keys(savedData).forEach(key => {
        const field = form.elements[key];
        if (field) {
            field.value = savedData[key];
        }
    });
    
    showToast('Kaydedilmiş form verileri geri yüklendi', 'info');
    return true;
}

// ==================== NOTIFICATION SYSTEM ====================

/**
 * Request Notification Permission
 */
function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

/**
 * Show Browser Notification
 */
function showNotification(title, body, icon = null) {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(title, {
            body: body,
            icon: icon || '/assets/images/logo.png'
        });
    }
}

// ==================== INITIALIZATION ====================

document.addEventListener('DOMContentLoaded', function() {
    log('Sistem başlatıldı', 'success');
    
    // Tooltip initialization (Bootstrap)
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Prevent double submit
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                setTimeout(() => {
                    submitBtn.disabled = false;
                }, 3000);
            }
        });
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
    
    log('Tüm event listener\'lar yüklendi', 'success');
});

// ==================== EXPORT FUNCTIONS ====================
window.APP = APP;
window.log = log;
window.formatNumber = formatNumber;
window.formatCurrency = formatCurrency;
window.formatDate = formatDate;
window.showLoading = showLoading;
window.hideLoading = hideLoading;
window.showToast = showToast;
window.confirmDialog = confirmDialog;
window.ajaxRequest = ajaxRequest;
window.validateForm = validateForm;
window.clearForm = clearForm;
window.filterTable = filterTable;
window.sortTable = sortTable;
window.calculateTotalCost = calculateTotalCost;
window.calculateExchangeDifference = calculateExchangeDifference;
window.calculateCostPerKg = calculateCostPerKg;
window.exportTableToCSV = exportTableToCSV;
window.printPage = printPage;
window.enableAutoSave = enableAutoSave;
window.restoreAutoSave = restoreAutoSave;
window.showNotification = showNotification;

log('Tüm fonksiyonlar global scope\'a eklendi', 'success');