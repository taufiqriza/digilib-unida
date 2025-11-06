(function () {
  'use strict';

  var state = {
    gps: null,
    scanLock: false
  };

  function ready(fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  function haversine(lat1, lon1, lat2, lon2) {
    function toRad(v) { return v * Math.PI / 180; }
    var R = 6371000;
    var dLat = toRad(lat2 - lat1);
    var dLon = toRad(lon2 - lon1);
    var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
    var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
  }

  function showToast(message) {
    var toast = document.querySelector('.pa-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.className = 'pa-toast';
      document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(function () { toast.classList.remove('show'); }, 3200);
  }

  function initGeolocation() {
    var gpsLabel = document.querySelector('[data-pa-gps]');
    if (!navigator.geolocation) {
      if (gpsLabel) {
        gpsLabel.textContent = 'GPS tidak didukung di perangkat ini';
      }
      return;
    }
    navigator.geolocation.getCurrentPosition(function (pos) {
      state.gps = {
        lat: pos.coords.latitude,
        lng: pos.coords.longitude,
        accuracy: pos.coords.accuracy
      };
      if (gpsLabel) {
        gpsLabel.textContent = 'Lokasi terdeteksi (' + state.gps.lat.toFixed(4) + ', ' + state.gps.lng.toFixed(4) + ')';
      }
      document.dispatchEvent(new CustomEvent('pa:gps-ready', { detail: state.gps }));
    }, function () {
      if (gpsLabel) {
        gpsLabel.textContent = 'GPS tidak diizinkan. Aktifkan lokasi untuk validasi radius.';
      }
    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
  }

  function initScanner() {
    var container = document.getElementById('qr-reader');
    if (!container) {
      return;
    }
    if (typeof Html5Qrcode === 'undefined') {
      container.innerHTML = '<div class="pa-alert">Scanner tidak tersedia. Pastikan koneksi internet aktif.</div>';
      return;
    }
    var html5QrCode = new Html5Qrcode('qr-reader');
    html5QrCode.start({ facingMode: 'environment' }, { fps: 10, qrbox: 280 }, function (decodedText) {
      if (state.scanLock) {
        return;
      }
      state.scanLock = true;
      setTimeout(function () { state.scanLock = false; }, 4000);
      var token = decodedText || '';
      if (token.indexOf(':') !== -1) {
        token = token.split(':').pop();
      }
      fetch('scan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: token.trim(), gps: state.gps })
      }).then(function (res) { return res.json(); }).then(function (json) {
        showToast(json.message || 'Berhasil memproses presensi');
        if (json.status === 'success') {
          var status = document.querySelector('[data-pa-status]');
          if (status) {
            status.textContent = json.detail || 'Check-in berhasil!';
          }
        }
      }).catch(function () {
        showToast('Gagal mengirim data, cek koneksi internet.');
      });
    }).catch(function (err) {
      container.innerHTML = '<div class="pa-alert">' + (err.message || err) + '</div>';
    });
  }

  function renderMap(elId, locations, options) {
    if (typeof L === 'undefined') {
      return;
    }
    var el = document.getElementById(elId);
    if (!el) {
      return;
    }
    var center = options && options.fallbackCenter || { lat: -6.175392, lng: 106.827153 };
    var map = L.map(elId).setView([center.lat, center.lng], options && options.zoom || 17);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    if (Array.isArray(locations)) {
      locations.forEach(function (loc) {
        if (!loc.latitude || !loc.longitude) {
          return;
        }
        var circle = L.circle([loc.latitude, loc.longitude], {
          color: loc.color_hex || '#2563eb',
          radius: loc.radius_meters || 50,
          fillOpacity: 0.25
        }).addTo(map);
        circle.bindPopup('<strong>' + loc.name + '</strong><br>' + (loc.description || ''));
      });
    }
  }

  function initLocationList() {
    var list = document.querySelectorAll('[data-pa-location]');
    if (!list.length) {
      return;
    }
    var updateDistance = function (gps) {
      list.forEach(function (item) {
        var lat = parseFloat(item.getAttribute('data-lat'));
        var lng = parseFloat(item.getAttribute('data-lng'));
        var radius = parseFloat(item.getAttribute('data-radius'));
        var indicator = item.querySelector('[data-pa-distance]');
        var button = item.querySelector('button');
        if (!indicator || isNaN(lat) || isNaN(lng)) {
          return;
        }
        if (!gps) {
          indicator.textContent = 'Aktifkan GPS untuk validasi radius';
          if (button) {
            button.disabled = true;
          }
          return;
        }
        var distance = haversine(gps.lat, gps.lng, lat, lng);
        indicator.textContent = distance.toFixed(1) + ' m dari titik';
        if (button) {
          button.disabled = distance > radius;
        }
        item.setAttribute('data-distance', distance);
      });
    };

    document.addEventListener('pa:gps-ready', function (event) {
      updateDistance(event.detail);
    });

    if (state.gps) {
      updateDistance(state.gps);
    }
  }

  ready(function () {
    initGeolocation();
    initScanner();
    initLocationList();
    window.paRenderMap = renderMap;

    document.addEventListener('click', function (event) {
      var target = event.target.closest('[data-pa-checkin]');
      if (!target) {
        return;
      }
      event.preventDefault();
      var token = target.getAttribute('data-token');
      if (!token) {
        return;
      }
      fetch('scan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: token, gps: state.gps, method: 'pin-gps' })
      }).then(function (res) { return res.json(); }).then(function (json) {
        showToast(json.message || 'Presensi diproses');
        if (json.status === 'success') {
          target.disabled = true;
        }
      }).catch(function () {
        showToast('Gagal memproses check-in.');
      });
    });
  });
})();
