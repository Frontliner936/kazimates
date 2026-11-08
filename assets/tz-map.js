/* Tanzania map: member counts per region and the viewer's connections (region level only). */
(function () {
  var el = document.getElementById('tz-map');
  var raw = document.getElementById('tz-data');
  if (!el || !raw) return;
  if (!window.L) { el.innerHTML = '<p class="tz-hint" style="padding:20px">The map could not load. Check your internet connection and refresh.</p>'; return; }
  var data = JSON.parse(raw.textContent);
  var map = L.map(el, { minZoom: 5, maxZoom: 12, zoomControl: true, maxBounds: [[-13.5, 27.5], [0.5, 42.5]], maxBoundsViscosity: 0.9 }).setView([-6.4, 34.9], 6);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors' }).addTo(map);

  function node(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function plural(n, word) { return n + ' ' + word + (n === 1 ? '' : 's'); }

  var membersLayer = L.layerGroup(), connectionsLayer = L.layerGroup(), points = [];

  data.members.forEach(function (m) {
    var s = Math.round(Math.min(26 + Math.sqrt(m.count) * 6, 64));
    var icon = L.divIcon({ className: 'tz-pin', html: '<span class="tz-bubble tz-bubble-members" style="width:' + s + 'px;height:' + s + 'px;line-height:' + s + 'px">' + m.count + '</span>', iconSize: [s, s], iconAnchor: [s / 2, s / 2] });
    var box = node('div', 'tz-popup');
    box.appendChild(node('strong', null, m.region));
    box.appendChild(node('div', null, plural(m.count, 'member')));
    var link = node('a', null, 'View members'); link.href = 'discover.php?region=' + encodeURIComponent(m.filter); box.appendChild(link);
    L.marker([m.lat, m.lng], { icon: icon, title: m.region + ': ' + plural(m.count, 'member') }).bindPopup(box).addTo(membersLayer);
    points.push([m.lat, m.lng]);
  });

  data.connections.forEach(function (c) {
    var n = c.people.length, s = 26;
    var icon = L.divIcon({ className: 'tz-pin', html: '<span class="tz-bubble tz-bubble-connections" style="width:' + s + 'px;height:' + s + 'px;line-height:' + (s - 4) + 'px">' + n + '</span>', iconSize: [s, s], iconAnchor: [-4, -4] });
    var box = node('div', 'tz-popup');
    box.appendChild(node('strong', null, c.region));
    box.appendChild(node('div', null, plural(n, 'connection')));
    c.people.forEach(function (p) { var a = node('a', 'tz-person', p.name); a.href = 'chat.php?with=' + p.id; box.appendChild(a); });
    L.marker([c.lat, c.lng], { icon: icon, title: c.region + ': ' + plural(n, 'connection'), zIndexOffset: 500 }).bindPopup(box).addTo(connectionsLayer);
    points.push([c.lat, c.lng]);
  });

  membersLayer.addTo(map); connectionsLayer.addTo(map);
  if (points.length > 1) map.fitBounds(points, { padding: [50, 50], maxZoom: 8 });
  else if (points.length === 1) map.setView(points[0], 8);

  function bind(id, layer) {
    var box = document.getElementById(id);
    box.addEventListener('change', function () { box.checked ? layer.addTo(map) : map.removeLayer(layer); });
  }
  bind('tz-show-members', membersLayer); bind('tz-show-connections', connectionsLayer);
  setTimeout(function () { map.invalidateSize(); }, 300);   // the page fades in; re-measure once it has settled
})();
