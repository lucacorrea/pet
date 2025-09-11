// autoErp/public/assets/js/public/dashboardView.js

(function () {
  const el = document.getElementById('d-main');
  if (!el) return;

  const LABELS = Array.isArray(window.DASH_LABELS) ? window.DASH_LABELS : [];
  const SERIES = Array.isArray(window.DASH_SERIES) ? window.DASH_SERIES : [];

  if (typeof ApexCharts === 'undefined') {
    el.innerHTML = '<div class="text-muted">Biblioteca de gráfico indisponível.</div>';
    return;
  }

  const options = {
    chart: { type: 'line', height: 360, toolbar: { show: false } },
    series: [{ name: 'Faturamento', data: SERIES }],
    xaxis: { categories: LABELS },
    stroke: { width: 3, curve: 'smooth' }, // curva suave liga os pontos
    markers: { size: 3 },
    dataLabels: { enabled: false },
    legend: { position: 'top' },
    grid: { borderColor: 'rgba(0,0,0,0.1)' },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.0 } },
    tooltip: {
      y: { formatter: (val) => 'R$ ' + (Number(val || 0)).toLocaleString('pt-BR', {minimumFractionDigits: 2}) }
    }
  };

  new ApexCharts(el, options).render();
})();
