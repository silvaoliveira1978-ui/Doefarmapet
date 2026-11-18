document.addEventListener('DOMContentLoaded', function () {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('./service-worker.js').catch(function (error) {
            console.error('Não foi possível registrar o modo instalável:', error);
        });
    }

    var deferredInstallPrompt = null;
    var statusBanner = document.createElement('div');
    statusBanner.className = 'pwa-status-banner';
    statusBanner.setAttribute('role', 'status');
    statusBanner.setAttribute('aria-live', 'polite');

    var installButton = document.createElement('button');
    installButton.type = 'button';
    installButton.className = 'pwa-install-button';
    installButton.textContent = 'Instalar app';
    installButton.hidden = true;
    installButton.addEventListener('click', async function () {
        if (!deferredInstallPrompt) {
            return;
        }
        deferredInstallPrompt.prompt();
        await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;
        installButton.hidden = true;
    });

    document.body.appendChild(statusBanner);
    document.querySelector('.topbar').appendChild(installButton);

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredInstallPrompt = event;
        installButton.hidden = false;
    });

    window.addEventListener('appinstalled', function () {
        deferredInstallPrompt = null;
        installButton.hidden = true;
        statusBanner.textContent = 'Adote Certo foi instalado.';
        statusBanner.classList.add('visible');
        window.setTimeout(function () {
            statusBanner.classList.remove('visible');
        }, 4000);
    });

    var updateConnectionStatus = function () {
        if (navigator.onLine) {
            statusBanner.classList.remove('offline');
            statusBanner.classList.remove('visible');
        } else {
            statusBanner.textContent = 'Você está offline. Algumas ações precisam de conexão.';
            statusBanner.classList.add('offline');
            statusBanner.classList.add('visible');
        }
    };

    window.addEventListener('online', updateConnectionStatus);
    window.addEventListener('offline', updateConnectionStatus);
    updateConnectionStatus();

    document.querySelectorAll('.auth-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.auth-tab').forEach(function (item) {
                item.classList.toggle('active', item === tab);
            });
            document.querySelectorAll('.auth-form').forEach(function (form) {
                form.classList.toggle('active', form.dataset.form === tab.dataset.tab);
            });
        });
    });

    var species = document.getElementById('species-select');
    var varieties = document.getElementById('variety-select');
    if (species && varieties && window.ADOTE_VARIETIES) {
        var fillVarieties = function () {
            varieties.replaceChildren();
            (window.ADOTE_VARIETIES[species.value] || []).forEach(function (name) {
                var option = document.createElement('option');
                option.value = name;
                option.textContent = name;
                varieties.appendChild(option);
            });
        };
        species.addEventListener('change', fillVarieties);
        fillVarieties();
    }

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var submit = form.querySelector('button[type="submit"]');
            if (!submit || form.dataset.confirm !== 'true') {
                return;
            }
            if (!window.confirm(form.dataset.confirmMessage || 'Confirmar esta ação?')) {
                event.preventDefault();
            }
        });
    });
});
