/**
 * WiFi Hotspot Billing - Modern JavaScript
 * Theme toggle, sidebar, AJAX, charts, notifications
 */

// ============================================
// THEME MANAGEMENT
// ============================================
const ThemeManager = {
    init() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        this.setTheme(savedTheme);

        const toggleBtn = document.getElementById('themeToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this.toggle());
        }
    },

    setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);

        const icon = document.querySelector('#themeToggle i');
        if (icon) {
            icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon';
        }
    },

    toggle() {
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        this.setTheme(next);
    }
};

// ============================================
// SIDEBAR MANAGEMENT
// ============================================
const SidebarManager = {
    init() {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        const wrapper = document.querySelector('.main-wrapper');
        const overlay = document.getElementById('mobileOverlay');

        // Desktop collapse
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                wrapper.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });
        }

        // Restore state
        if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 991) {
            sidebar.classList.add('collapsed');
            wrapper.classList.add('sidebar-collapsed');
        }

        // Mobile toggle
        const mobileToggle = document.getElementById('mobileMenuToggle');
        if (mobileToggle) {
            mobileToggle.addEventListener('click', () => {
                sidebar.classList.add('mobile-show');
                overlay.classList.add('active');
            });
        }

        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('mobile-show');
                overlay.classList.remove('active');
            });
        }
    }
};

// ============================================
// NOTIFICATIONS / TOASTS
// ============================================
const Notifications = {
    container: null,

    init() {
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
    },

    show(message, type = 'success', duration = 5000) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;

        const icons = {
            success: 'bi-check-circle-fill',
            error: 'bi-x-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info: 'bi-info-circle-fill'
        };

        toast.innerHTML = `
            <i class="bi ${icons[type] || icons.success}"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;color:var(--text-muted);cursor:pointer;">
                <i class="bi bi-x"></i>
            </button>
        `;

        this.container.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideInRight 0.3s ease reverse';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    success(msg) { this.show(msg, 'success'); },
    error(msg) { this.show(msg, 'error'); },
    warning(msg) { this.show(msg, 'warning'); },
    info(msg) { this.show(msg, 'info'); }
};

// ============================================
// AJAX HELPER
// ============================================
const API = {
    async request(url, options = {}) {
        const defaultOptions = {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        };

        if (options.body && !(options.body instanceof FormData)) {
            defaultOptions.headers['Content-Type'] = 'application/json';
        }

        try {
            const response = await fetch(url, { ...defaultOptions, ...options });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Request failed');
            }

            return data;
        } catch (error) {
            Notifications.error(error.message);
            throw error;
        }
    },

    get(url) {
        return this.request(url, { method: 'GET' });
    },

    post(url, data) {
        const options = {
            method: 'POST',
            body: data instanceof FormData ? data : JSON.stringify(data)
        };
        return this.request(url, options);
    },

    delete(url) {
        return this.request(url, { method: 'DELETE' });
    }
};

// ============================================
// CONFIRM DIALOG
// ============================================
function confirmAction(message, callback) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay active';
    overlay.innerHTML = `
        <div class="modal" style="max-width:400px;">
            <div class="modal-header">
                <h5>Confirm Action</h5>
                <button class="header-btn" onclick="this.closest('.modal-overlay').remove()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>${message}</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="this.closest('.modal-overlay').remove()">Cancel</button>
                <button class="btn btn-danger" id="confirmBtn">Confirm</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    overlay.querySelector('#confirmBtn').addEventListener('click', () => {
        callback();
        overlay.remove();
    });

    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.remove();
    });
}

// ============================================
// TABLE SEARCH & SORT
// ============================================
const TableManager = {
    init(tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;

        const searchInput = document.querySelector(`[data-table-search="${tableId}"]`);
        if (searchInput) {
            searchInput.addEventListener('input', (e) => this.search(table, e.target.value));
        }
    },

    search(table, query) {
        const rows = table.querySelectorAll('tbody tr');
        const lowerQuery = query.toLowerCase();

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(lowerQuery) ? '' : 'none';
        });
    }
};

// ============================================
// REAL-TIME UPDATES (WebSocket fallback to polling)
// ============================================
const Realtime = {
    interval: null,

    start(endpoint, callback, interval = 30000) {
        this.stop();

        const poll = async () => {
            try {
                const data = await API.get(endpoint);
                callback(data);
            } catch (e) {
                console.error('Poll error:', e);
            }
        };

        poll();
        this.interval = setInterval(poll, interval);
    },

    stop() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    }
};

// ============================================
// CHART HELPERS
// ============================================
const Charts = {
    defaults: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    color: 'var(--text-secondary)',
                    font: { family: 'Inter', size: 12 }
                }
            }
        },
        scales: {
            x: {
                grid: { color: 'var(--border-color)', drawBorder: false },
                ticks: { color: 'var(--text-muted)', font: { family: 'Inter', size: 11 } }
            },
            y: {
                grid: { color: 'var(--border-color)', drawBorder: false },
                ticks: { color: 'var(--text-muted)', font: { family: 'Inter', size: 11 } }
            }
        }
    },

    create(ctx, type, data, options = {}) {
        return new Chart(ctx, {
            type,
            data,
            options: { ...this.defaults, ...options }
        });
    }
};

// ============================================
// LOADING SPINNER
// ============================================
function showLoading(element) {
    element.dataset.originalContent = element.innerHTML;
    element.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
    element.disabled = true;
}

function hideLoading(element) {
    element.innerHTML = element.dataset.originalContent;
    element.disabled = false;
}

// ============================================
// FORM VALIDATION
// ============================================
function validateForm(form) {
    const inputs = form.querySelectorAll('[required]');
    let valid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            valid = false;
            input.style.borderColor = 'var(--danger)';

            const error = input.parentElement.querySelector('.form-error');
            if (!error) {
                const msg = document.createElement('small');
                msg.className = 'form-error';
                msg.style.color = 'var(--danger)';
                msg.textContent = 'This field is required';
                input.parentElement.appendChild(msg);
            }
        } else {
            input.style.borderColor = '';
            const error = input.parentElement.querySelector('.form-error');
            if (error) error.remove();
        }
    });

    return valid;
}

// ============================================
// NUMBER ANIMATION
// ============================================
function animateNumber(element, target, duration = 1000) {
    const start = 0;
    const startTime = performance.now();

    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeOut = 1 - Math.pow(1 - progress, 3);
        const current = Math.floor(start + (target - start) * easeOut);

        element.textContent = current.toLocaleString();

        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            element.textContent = target.toLocaleString();
        }
    }

    requestAnimationFrame(update);
}

// ============================================
// INIT
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    ThemeManager.init();
    SidebarManager.init();
    Notifications.init();

    // Animate stat numbers
    document.querySelectorAll('.stat-value[data-count]').forEach(el => {
        animateNumber(el, parseInt(el.dataset.count));
    });

    // Initialize tables with search
    document.querySelectorAll('[data-table-search]').forEach(input => {
        const tableId = input.getAttribute('data-table-search');
        TableManager.init(tableId);
    });

    // Auto-hide alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
});
