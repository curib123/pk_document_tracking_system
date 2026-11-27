/* The server-rendered counts are available independently of the charts. */
(function () {
  "use strict";

  function applyTheme(type, data, options) {
    var palette = getComputedStyle(document.documentElement);
    var color = function (name) {
      return palette.getPropertyValue(name).trim();
    };
    var pie = type === "doughnut";
    var dataset = data.datasets[0];
    dataset.backgroundColor = pie
      ? data.labels.map(function (label) {
          return color(label === "Hardcopy" ? "--ui-blue-700" : "--ui-brand-600");
        })
      : color("--ui-brand-600");
    dataset.hoverBackgroundColor = pie
      ? data.labels.map(function (label) {
          return color(label === "Hardcopy" ? "--ui-blue-900" : "--ui-brand-800");
        })
      : color("--ui-brand-800");
    dataset.borderColor = color("--surface");
    if (!pie) {
      options.scales.x.grid.color = color("--ui-line");
      options.scales.x.ticks.color = color("--ui-ink-600");
      options.scales.y.ticks.color = color("--ui-ink-600");
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    if (!window.Chart) return;

    var charts = [];
    var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
    var animation = { duration: 320 };
    var chartTypeNodes = document.querySelectorAll(".dashboard-page canvas[data-chart-type]");
    chartTypeNodes.forEach(function (canvas) {
      var box = canvas.closest(".dashboard-chart-box");
      if (!box) return;

      try {
        var type = canvas.dataset.chartType;
        var labels = JSON.parse(canvas.dataset.chartLabels);
        var values = JSON.parse(canvas.dataset.chartCounts).map(Number);
        var total = values.reduce(function (sum, value) {
          return sum + value;
        }, 0);
        var pie = type === "doughnut";
        var fonts = { family: "'Poppins', sans-serif", size: 11 };
        var dataset = {
          label: "Records",
          data: values,
          borderWidth: pie ? 4 : 0,
          borderRadius: pie ? 0 : 7,
          maxBarThickness: 22,
          categoryPercentage: 0.66,
          barPercentage: 0.76,
        };
        var data = { labels: labels, datasets: [dataset] };
        var options = {
          responsive: true,
          maintainAspectRatio: false,
          indexAxis: pie ? "x" : "y",
          animation: reducedMotion.matches ? false : animation,
          ...(pie
            ? { cutout: "76%", layout: { padding: 12 } }
            : {
                layout: {
                  padding: { top: 10, right: 9, bottom: 1, left: 4 },
                },
              }),
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: "#1c2c43",
              titleColor: "#ffffff",
              bodyColor: "#f3f6fb",
              padding: 12,
              cornerRadius: 10,
              displayColors: pie,
              titleFont: { family: fonts.family, size: 11, weight: "700" },
              bodyFont: { family: fonts.family, size: 11 },
              callbacks: {
                label: function (item) {
                  var count = values[item.dataIndex];
                  var share = total ? ((100 * count) / total).toFixed(1) : "0";
                  return count.toLocaleString("en-PH") + " (" + share + "%)";
                },
              },
            },
          },
          ...(pie
            ? {}
            : {
                scales: {
                  x: {
                    beginAtZero: true,
                    grid: {},
                    border: { display: false },
                    ticks: {
                      precision: 0,
                      font: fonts,
                      padding: 8,
                    },
                  },
                  y: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                      font: fonts,
                      autoSkip: false,
                      padding: 9,
                    },
                  },
                },
              }),
        };
        applyTheme(type, data, options);
        box.hidden = false;
        var chart = new window.Chart(canvas, { type: type, data: data, options: options });
        if (chart.ctx) {
          charts.push(chart);
        } else {
          box.hidden = true;
          chart.destroy();
        }
      } catch (error) {
        box.hidden = true;
        var failedChart = window.Chart.getChart(canvas);
        if (failedChart) failedChart.destroy();
      }
    });

    if (!charts.length) return;
    window.addEventListener("pk-theme-change", function () {
      charts.forEach(function (chart) {
        applyTheme(chart.config.type, chart.data, chart.options);
        chart.update("none");
      });
    });
    reducedMotion.addEventListener("change", function () {
      charts.forEach(function (chart) {
        chart.stop();
        chart.options.animation = reducedMotion.matches ? false : animation;
        chart.update("none");
      });
    });
  });
})();
