/* ============================================================
   LÓGICA DEL DASHBOARD (GRÁFICOS) - MINAS BURGUER
   ============================================================ */

document.addEventListener('DOMContentLoaded', function() {
    
    // Obtener los datos pasados desde PHP
    const config = window.DASHBOARD_CONFIG || {};
    if (Object.keys(config).length === 0) return;

    // Detectar modo oscuro para adaptar los colores
    const isDark = document.body.classList.contains('dark-mode') || localStorage.getItem('theme') === 'dark';
    const colorTexto = isDark ? '#e0e0e0' : '#333';
    const colorGrid  = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';

    Chart.defaults.color = colorTexto;
    Chart.defaults.font.family = "'Poppins', sans-serif";

    // 1. Ventas (Línea)
    if (document.getElementById('chartVentas')) {
        new Chart(document.getElementById('chartVentas'), {
            type: 'line',
            data: {
                labels: config.labelsVentas,
                datasets: [{ 
                    label: 'Ventas ($)', 
                    data: config.dataVentas, 
                    borderColor: '#28a745', 
                    backgroundColor: 'rgba(40, 167, 69, 0.1)', 
                    borderWidth: 3, 
                    pointBackgroundColor: '#28a745', 
                    pointBorderColor: '#fff', 
                    pointBorderWidth: 2, 
                    pointRadius: 4, 
                    fill: true, 
                    tension: 0.4 
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { legend: { display: false } }, 
                scales: { 
                    y: { beginAtZero: true, grid: { color: colorGrid }, ticks: { callback: v => '$' + v } }, 
                    x: { grid: { display: false } } 
                } 
            }
        });
    }

    // 2. Estados (Dona)
    if (document.getElementById('chartEstados')) {
        new Chart(document.getElementById('chartEstados'), {
            type: 'doughnut',
            data: { 
                labels: config.estadosLabels, 
                datasets: [{ 
                    data: config.estadosData, 
                    backgroundColor: config.estadosColors, 
                    borderWidth: 0, 
                    hoverOffset: 10 
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                cutout: '70%', 
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } } 
            }
        });
    }

    // 3. Métodos de Pago (POLAR AREA)
    if (document.getElementById('chartMetodos')) {
        new Chart(document.getElementById('chartMetodos'), {
            type: 'polarArea',
            data: { 
                labels: config.metodosNombres, 
                datasets: [{ 
                    data: config.metodosTotales, 
                    backgroundColor: ['rgba(211, 47, 47, 0.7)', 'rgba(255, 152, 0, 0.7)', 'rgba(255, 193, 7, 0.7)', 'rgba(40, 167, 69, 0.7)', 'rgba(23, 162, 184, 0.7)'],
                    borderWidth: 1,
                    borderColor: isDark ? '#1a1a1a' : '#fff'
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } }, 
                scales: { r: { grid: { color: colorGrid }, ticks: { display: false } } } 
            }
        });
    }

    // 4. Delivery vs Recoger (PIE)
    if (document.getElementById('chartEntrega')) {
        new Chart(document.getElementById('chartEntrega'), {
            type: 'pie',
            data: { 
                labels: ['Delivery', 'Recoger'], 
                datasets: [{ 
                    data: [config.entregaDelivery, config.entregaRecoger], 
                    backgroundColor: ['#17a2b8', '#28a745'], 
                    borderWidth: 2, 
                    borderColor: isDark ? '#1a1a1a' : '#fff' 
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } } 
            }
        });
    }

    // 5. Ventas por Día de la Semana (Barras Verticales con Gradiente)
    if (document.getElementById('chartDiasSemana')) {
        const ctxDias = document.getElementById('chartDiasSemana').getContext('2d');
        const gradientDias = ctxDias.createLinearGradient(0, 0, 0, 300);
        gradientDias.addColorStop(0, '#28a745');
        gradientDias.addColorStop(1, 'rgba(40, 167, 69, 0.2)');

        new Chart(document.getElementById('chartDiasSemana'), {
            type: 'bar',
            data: { 
                labels: config.labelsDias, 
                datasets: [{ 
                    data: config.dataDias, 
                    backgroundColor: gradientDias, 
                    borderRadius: 8, 
                    maxBarThickness: 35 
                }] 
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false, 
                plugins: { legend: { display: false } }, 
                scales: { 
                    y: { beginAtZero: true, grid: { color: colorGrid }, ticks: { callback: v => '$' + v } }, 
                    x: { grid: { display: false } } 
                } 
            }
        });
    }

});