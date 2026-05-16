/**
 * CorpNet Intranet — JavaScript principal
 * Meridian SA
 */

document.addEventListener('DOMContentLoaded', function () {

    // Activation des tooltips Bootstrap
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(el => new bootstrap.Tooltip(el));

    // Auto-dismiss des alertes après 4 secondes
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 4000);
    });

    // Confirmation avant actions destructives
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // Mise en évidence de la ligne de tableau au clic
    document.querySelectorAll('table.table-hover tbody tr').forEach(row => {
        row.style.cursor = 'pointer';
    });

});
