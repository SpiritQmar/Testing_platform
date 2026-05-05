(function() {
  'use strict';

  let chartInstances = {};

  async function loadChartData() {
    try {
      const response = await fetch('chart_data.php');
      const data = await response.json();

      if (data.error) {
        console.error('Chart data error:', data.error);
        return null;
      }

      return data;
    } catch (error) {
      console.error('Failed to load chart data:', error);
      return null;
    }
  }

  function createChart(canvasId, type, data, options = {}) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;

    if (chartInstances[canvasId]) {
      chartInstances[canvasId].destroy();
    }

    const ctx = canvas.getContext('2d');

    const defaultOptions = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: true,
          position: 'bottom',
          labels: {
            padding: 15,
            usePointStyle: true,
            font: {
              size: 12,
              family: "'Inter', sans-serif"
            }
          }
        },
        tooltip: {
          enabled: true,
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          padding: 12,
          cornerRadius: 8,
          titleFont: {
            size: 13,
            weight: '600'
          },
          bodyFont: {
            size: 12
          }
        }
      },
      animation: {
        duration: 400,
        easing: 'easeInOutQuart'
      }
    };

    chartInstances[canvasId] = new Chart(ctx, {
      type: type,
      data: data,
      options: { ...defaultOptions, ...options }
    });

    return chartInstances[canvasId];
  }

  async function initCharts() {
    const chartData = await loadChartData();
    if (!chartData) return;

    if (chartData.questionQuality) {
      createChart('qualityChart', 'doughnut', {
        labels: chartData.questionQuality.labels,
        datasets: [{
          data: chartData.questionQuality.data,
          backgroundColor: chartData.questionQuality.colors,
          borderWidth: 0,
          hoverOffset: 10
        }]
      });
    }

    if (chartData.correlation) {
      createChart('correlationChart', 'bar', {
        labels: chartData.correlation.labels,
        datasets: [{
          data: chartData.correlation.data,
          backgroundColor: chartData.correlation.colors,
          borderRadius: 8,
          borderSkipped: false
        }]
      }, {
        indexAxis: 'y',
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          x: {
            beginAtZero: true,
            ticks: {
              precision: 0
            }
          }
        }
      });
    }

    if (chartData.studentRisk) {
      createChart('studentsChart', 'bar', {
        labels: chartData.studentRisk.labels,
        datasets: [{
          label: 'Количество студентов',
          data: chartData.studentRisk.data,
          backgroundColor: chartData.studentRisk.colors,
          borderRadius: 8,
          borderSkipped: false
        }]
      }, {
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              precision: 0
            }
          }
        }
      });
    }

    if (chartData.validation) {
      createChart('validationChart', 'doughnut', {
        labels: chartData.validation.labels,
        datasets: [{
          data: chartData.validation.data,
          backgroundColor: chartData.validation.colors,
          borderWidth: 0,
          hoverOffset: 10
        }]
      });
    }

    if (chartData.semantic) {
      createChart('semanticChart', 'bar', {
        labels: chartData.semantic.labels,
        datasets: [{
          data: chartData.semantic.data,
          backgroundColor: chartData.semantic.colors,
          borderRadius: 8,
          borderSkipped: false
        }]
      }, {
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              precision: 0
            }
          }
        }
      });
    }

    if (chartData.criteria) {
      createChart('criteriaChart', 'radar', {
        labels: chartData.criteria.labels,
        datasets: [{
          data: chartData.criteria.data,
          backgroundColor: chartData.criteria.colors.map(c => c + '40'),
          borderColor: chartData.criteria.colors[0],
          borderWidth: 2,
          pointBackgroundColor: chartData.criteria.colors,
          pointBorderColor: '#fff',
          pointHoverBackgroundColor: '#fff',
          pointHoverBorderColor: chartData.criteria.colors
        }]
      }, {
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          r: {
            beginAtZero: true,
            ticks: {
              precision: 0
            }
          }
        }
      });
    }

    if (chartData.plagiarism) {
      createChart('answersChart', 'line', {
        labels: chartData.plagiarism.labels,
        datasets: [{
          data: chartData.plagiarism.data,
          fill: true,
          tension: 0.4,
          borderColor: chartData.plagiarism.colors[0],
          backgroundColor: chartData.plagiarism.colors[0] + '40',
          borderWidth: 2,
          pointBackgroundColor: chartData.plagiarism.colors[0],
          pointBorderColor: '#fff',
          pointHoverBackgroundColor: '#fff',
          pointHoverBorderColor: chartData.plagiarism.colors[0],
          pointRadius: 4,
          pointHoverRadius: 6
        }]
      }, {
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              precision: 0
            }
          }
        }
      });
    }
  }

  function replaceStaticCharts() {
    const chartMappings = [
      { id: 'chart-quality', canvasId: 'qualityChart' },
      { id: 'chart-correlation', canvasId: 'correlationChart' },
      { id: 'chart-students', canvasId: 'studentsChart' },
      { id: 'chart-validation', canvasId: 'validationChart' },
      { id: 'chart-semantic', canvasId: 'semanticChart' },
      { id: 'chart-criteria', canvasId: 'criteriaChart' },
      { id: 'chart-answers', canvasId: 'answersChart' }
    ];

    chartMappings.forEach(mapping => {
      const container = document.getElementById(mapping.id);
      if (container) {
        const existingCanvas = container.querySelector('canvas');
        if (!existingCanvas) {
          const chartContainer = container.querySelector('.chart-container');
          if (chartContainer) {
            chartContainer.innerHTML = `<canvas id="${mapping.canvasId}"></canvas>`;
          }
        }
      }
    });
  }

  window.showChart = function(chartType) {
    const charts = ['quality', 'correlation', 'students', 'validation', 'semantic', 'criteria', 'answers'];
    charts.forEach(type => {
      const chartDiv = document.getElementById('chart-' + type);
      if (chartDiv) {
        chartDiv.style.display = type === chartType ? 'block' : 'none';
      }
    });

    charts.forEach(type => {
      const btn = document.getElementById('btn-' + type);
      if (btn) {
        btn.classList.toggle('active', type === chartType);
      }
    });

    setTimeout(() => {
      Object.keys(chartInstances).forEach(key => {
        if (chartInstances[key]) {
          chartInstances[key].resize();
        }
      });
    }, 100);
  };

})();
