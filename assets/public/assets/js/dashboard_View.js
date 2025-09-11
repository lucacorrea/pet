// dashboard_View.js
// Scripts específicos do dashboard do Super Admin

// Solicita CNPJ se faltar ao aprovar
function aprovarHandler(form) {
    const btn = form.querySelector('button[type="submit"]');
    if (btn && btn.dataset.needCnpj === '1') {
        const empresa = btn.dataset.empresa || 'a empresa';
        let cnpj = prompt('Informe o CNPJ para aprovar ' + empresa + ' (somente números):');
        if (!cnpj) return false;
        cnpj = cnpj.replace(/\D+/g, '');
        if (cnpj.length < 14) {
            alert('CNPJ inválido.');
            return false;
        }
        form.querySelector('input[name="cnpj"]').value = cnpj;
    }
    return true;
}

// Gráfico único: Empresas ativas
(function () {
    const el = document.getElementById('sa-main-chart');
    if (!el) return;

    const LABELS = window.DASHBOARD_LABELS || [];
    const SERIES_EMP = window.DASHBOARD_SERIES || [];

    if (typeof ApexCharts === 'undefined') {
        el.innerHTML = '<pre>Empresas ativas: ' + SERIES_EMP.join(', ') + '</pre>';
        return;
    }

    const options = {
        chart: {
            type: 'line',
            height: 360,
            toolbar: { show: false }
        },
        series: [{
            name: 'Empresas ativas',
            data: SERIES_EMP
        }],
        xaxis: { categories: LABELS },
        stroke: { width: 3, curve: 'smooth' },
        markers: { size: 3 },
        dataLabels: { enabled: false },
        legend: { position: 'top' },
        grid: { borderColor: 'rgba(0,0,0,0.1)' },
        fill: {
            type: 'gradient',
            gradient: { opacityFrom: 0.3, opacityTo: 0.0 }
        }
    };

    new ApexCharts(el, options).render();
})();
