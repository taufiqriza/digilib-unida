(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  function qs(selector, scope) {
    return (scope || document).querySelector(selector);
  }

  function qsa(selector, scope) {
    return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
  }

  function renderCharts() {
    if (typeof Chart === 'undefined') {
      return;
    }
    qsa('[data-sm-chart]').forEach(function (canvas) {
      var payload;
      try {
        payload = JSON.parse(canvas.getAttribute('data-chart-payload'));
      } catch (err) {
        console.error('Invalid chart payload', err);
        return;
      }
      var type = canvas.getAttribute('data-sm-chart') || 'line';
      var options = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: true, labels: { font: { family: 'Inter' } } }
        },
        scales: {
          x: { grid: { display: false }, ticks: { font: { family: 'Inter' } } },
          y: { grid: { color: 'rgba(148, 163, 184, 0.35)' }, ticks: { font: { family: 'Inter' } } }
        }
      };
      var datasets = (payload.datasets || []).map(function (dataset) {
        var gradient = canvas.getContext('2d').createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, dataset.gradientStart || 'rgba(37, 99, 235, 0.3)');
        gradient.addColorStop(1, dataset.gradientEnd || 'rgba(37, 99, 235, 0.05)');
        return Object.assign({
          tension: 0.4,
          fill: true,
          borderWidth: 3,
          pointRadius: 0,
          backgroundColor: gradient,
          borderColor: dataset.color || '#2563eb'
        }, dataset);
      });

      new Chart(canvas, {
        type: type,
        data: { labels: payload.labels || [], datasets: datasets },
        options: options
      });
    });
  }

  function bindProgressInputs() {
    qsa('[data-sm-progress]').forEach(function (input) {
      var target = qs(input.getAttribute('data-sm-progress'));
      if (!target) {
        return;
      }
      var update = function () {
        target.textContent = input.value + '%';
      };
      input.addEventListener('input', update);
      update();
    });
  }

  function autoSubmitToggles() {
    qsa('[data-sm-submit-on-change]').forEach(function (element) {
      element.addEventListener('change', function () {
        var form = element.closest('form');
        if (form) {
          form.submit();
        }
      });
    });
  }

  function registerLeafletHelpers() {
    if (typeof L === 'undefined') {
      return;
    }
    window.staffManage = window.staffManage || {};
    window.staffManage.renderMap = function (elId, locations, options) {
      var el = qs('#' + elId);
      if (!el) {
        return;
      }
      var fallback = options && options.fallbackCenter || { lat: -6.175392, lng: 106.827153 };
      var map = L.map(elId).setView([fallback.lat, fallback.lng], options && options.zoom || 16);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);
      if (!Array.isArray(locations) || !locations.length) {
        return;
      }
      locations.forEach(function (loc) {
        if (!loc.latitude || !loc.longitude) {
          return;
        }
        var marker = L.circle([loc.latitude, loc.longitude], {
          color: loc.color_hex || '#2563eb',
          radius: (loc.radius_meters || 50),
          fillOpacity: 0.25
        });
        marker.bindPopup('<strong>' + loc.name + '</strong><br>' + (loc.description || ''));
        marker.addTo(map);
      });
    };
  }

  ready(function () {
    renderCharts();
    bindProgressInputs();
    autoSubmitToggles();
    registerLeafletHelpers();
  });
})();
