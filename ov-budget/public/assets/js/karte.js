/*
 * Karten: ein Kartenausschnitt mit eigenen Symbolen – ohne fremde Bibliothek.
 *
 * Die Karte besteht aus Kacheln von 256 Pixeln (Web-Mercator, wie bei
 * OpenStreetMap). Der Zustand ist nur Mittelpunkt und Zoom; daraus rechnet
 * zeichnen() bei jeder Bewegung, welche Kacheln zu sehen sind und wo die
 * Symbole sitzen.
 *
 *   ziehen            verschieben          Mausrad, +/−, Doppelklick   zoomen
 *   Rechtsklick       Symbol setzen        (am Handy: lange drücken)
 *   Symbol ziehen     versetzen            Symbol anklicken            beschriften, Größe, Drehung, entfernen
 *
 * Alles, was die Seite weiß, steckt als JSON im Attribut data-lagekarte;
 * gespeichert wird über die Adresse in K.api (karten_api.php).
 */
(function () {
  var wurzel = document.querySelector('[data-lagekarte]');
  if (!wurzel) { return; }
  var K;
  try { K = JSON.parse(wurzel.getAttribute('data-lagekarte') || ''); } catch (e) { return; }
  var flaeche = wurzel.querySelector('[data-karte-flaeche]');
  if (!K || !flaeche) { return; }

  var KACHEL = 256;
  var zustand = { lat: K.lat, lng: K.lng, zoom: K.zoom };
  var kacheln = {};          // 'z/x/y' → <img>
  var marker = [];           // { d: Daten, el: Element, bild: <img>, text: <span> }
  var fahrzeuge = [];        // { d: Daten, el: Element }
  var gewaehlt = null;       // der Eintrag aus marker, der gerade bearbeitet wird

  function neu(tag, klasse, text) {
    var el = document.createElement(tag);
    if (klasse) { el.className = klasse; }
    if (text !== undefined) { el.textContent = text; }
    return el;
  }
  var kachelEbene = flaeche.appendChild(neu('div', 'lagekarte__kacheln'));
  var markerEbene = flaeche.appendChild(neu('div', 'lagekarte__marker-ebene'));
  var meldung = wurzel.querySelector('[data-karte-meldung]');

  /* ---------- Rechnen: Grad ↔ Pixel der Weltkarte in einer Zoomstufe ---------- */
  function weltX(lng, z) { return (lng + 180) / 360 * KACHEL * Math.pow(2, z); }
  function weltY(lat, z) {
    var s = Math.sin(Math.max(-85.05, Math.min(85.05, lat)) * Math.PI / 180);
    return (0.5 - Math.log((1 + s) / (1 - s)) / (4 * Math.PI)) * KACHEL * Math.pow(2, z);
  }
  function lngVon(x, z) { return x / (KACHEL * Math.pow(2, z)) * 360 - 180; }
  function latVon(y, z) {
    var n = Math.PI - 2 * Math.PI * y / (KACHEL * Math.pow(2, z));
    return 180 / Math.PI * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n)));
  }
  function begrenzen(v, min, max) { return Math.max(min, Math.min(max, v)); }
  function runden6(v) { return Math.round(v * 1e6) / 1e6; }

  /** Linke obere Ecke des Ausschnitts in Weltpixeln */
  function ecke() {
    return {
      x: weltX(zustand.lng, zustand.zoom) - flaeche.clientWidth / 2,
      y: weltY(zustand.lat, zustand.zoom) - flaeche.clientHeight / 2
    };
  }
  /** Punkt in der Fläche (Pixel ab links oben) → Grad */
  function gradBei(px, py) {
    var e = ecke();
    var lng = lngVon(e.x + px, zustand.zoom);
    // über die Datumsgrenze hinaus geschoben: zurück in −180 … 180
    lng = ((lng + 180) % 360 + 360) % 360 - 180;
    return { lat: runden6(latVon(e.y + py, zustand.zoom)), lng: runden6(lng) };
  }
  function punktIn(ev) {
    var r = flaeche.getBoundingClientRect();
    return { x: ev.clientX - r.left, y: ev.clientY - r.top };
  }

  function kachelAdresse(z, x, y) {
    return K.kacheln.replace('{s}', 'abc'.charAt((x + y) % 3)).replace('{z}', z).replace('{x}', x).replace('{y}', y);
  }

  /* ---------- Zeichnen ---------- */
  var geplant = false;
  function zeichnen() {
    if (geplant) { return; }
    geplant = true;
    window.requestAnimationFrame(function () { geplant = false; jetztZeichnen(); });
  }
  function jetztZeichnen() {
    var z = zustand.zoom;
    var n = Math.pow(2, z);
    zustand.lat = begrenzen(zustand.lat, -85.05, 85.05);
    var e = ecke();
    var b = flaeche.clientWidth;
    var h = flaeche.clientHeight;
    var gebraucht = {};
    for (var x = Math.floor(e.x / KACHEL); x <= Math.floor((e.x + b) / KACHEL); x++) {
      for (var y = Math.max(0, Math.floor(e.y / KACHEL)); y <= Math.min(n - 1, Math.floor((e.y + h) / KACHEL)); y++) {
        var key = z + '/' + x + '/' + y;
        gebraucht[key] = true;
        var img = kacheln[key];
        if (!img) {
          img = neu('img', 'lagekarte__kachel');
          img.alt = '';
          img.draggable = false;
          // Die Kachelserver von OpenStreetMap verlangen eine Herkunftsangabe; die Seite selbst gibt nach außen keine mit
          img.referrerPolicy = 'strict-origin-when-cross-origin';
          img.onerror = function () { this.style.visibility = 'hidden'; };
          img.src = kachelAdresse(z, ((x % n) + n) % n, y);   // die Welt wiederholt sich nach links und rechts
          kacheln[key] = kachelEbene.appendChild(img);
        }
        img.style.transform = 'translate(' + Math.round(x * KACHEL - e.x) + 'px,' + Math.round(y * KACHEL - e.y) + 'px)';
      }
    }
    Object.keys(kacheln).forEach(function (key) {
      if (!gebraucht[key]) { kachelEbene.removeChild(kacheln[key]); delete kacheln[key]; }
    });
    marker.forEach(function (m) { setzen(m.el, m.d, e); });
    fahrzeuge.forEach(function (f) { setzen(f.el, f.d, e); });
  }
  function setzen(el, d, e) {
    el.style.left = Math.round(weltX(d.lng, zustand.zoom) - e.x) + 'px';
    el.style.top = Math.round(weltY(d.lat, zustand.zoom) - e.y) + 'px';
  }

  /** Zoomen – die Stelle unter (px, py) bleibt dabei, wo sie ist */
  function zoomen(stufe, px, py) {
    stufe = begrenzen(Math.round(stufe), K.zoomMin, K.zoomMax);
    if (stufe === zustand.zoom) { return; }
    if (px === undefined) { px = flaeche.clientWidth / 2; py = flaeche.clientHeight / 2; }
    var halt = gradBei(px, py);
    zustand.zoom = stufe;
    zustand.lng = lngVon(weltX(halt.lng, stufe) - px + flaeche.clientWidth / 2, stufe);
    zustand.lat = latVon(weltY(halt.lat, stufe) - py + flaeche.clientHeight / 2, stufe);
    menueZu();
    zeichnen();
  }

  /* ---------- Meldungen und Speichern ---------- */
  var meldungUhr = null;
  function melden(text, fehler) {
    if (!meldung) { return; }
    meldung.textContent = text;
    meldung.classList.toggle('is-fehler', !!fehler);
    meldung.hidden = text === '';
    window.clearTimeout(meldungUhr);
    if (text !== '') { meldungUhr = window.setTimeout(function () { meldung.hidden = true; }, fehler ? 8000 : 3000); }
  }
  function senden(daten, fertig) {
    var fd = new FormData();
    fd.append('_csrf', K.csrf);
    Object.keys(daten).forEach(function (k) { fd.append(k, daten[k]); });
    window.fetch(K.api, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json().then(function (d) { return r.ok && d && d.ok ? d : { fehler: (d && d.fehler) || 'Das hat nicht geklappt.' }; }); })
      .catch(function () { return { fehler: 'Keine Verbindung – nicht gespeichert.' }; })
      .then(function (d) {
        if (d.fehler) { melden(d.fehler, true); }
        if (fertig) { fertig(d.fehler ? null : d); }
      });
  }

  /* ---------- Gesetzte Symbole ---------- */
  function markerDazu(d) {
    var el = neu('div', 'lagekarte__marker');
    var bild = el.appendChild(neu('img'));
    bild.alt = d.name || 'Symbol';
    bild.draggable = false;
    bild.src = d.src;
    var text = el.appendChild(neu('span', 'lagekarte__beschriftung'));
    var m = { d: d, el: el, bild: bild, text: text };
    markerAussehen(m);
    if (K.setzen) {
      el.classList.add('is-beweglich');
      markerZiehbar(m);
    }
    marker.push(m);
    markerEbene.appendChild(el);
    return m;
  }
  function markerAussehen(m) {
    m.bild.style.width = m.d.groesse + 'px';
    m.bild.style.transform = m.d.drehung ? 'rotate(' + m.d.drehung + 'deg)' : '';
    m.text.textContent = m.d.text || '';
    m.text.hidden = !m.d.text;
    m.el.title = (m.d.name || '') + (m.d.text ? ' – ' + m.d.text : '');
  }
  function markerZiehbar(m) {
    var start = null;
    m.el.addEventListener('pointerdown', function (ev) {
      if (ev.button !== 0) { return; }
      ev.stopPropagation();   // die Karte bleibt liegen
      ev.preventDefault();
      menueZu();
      start = { x: ev.clientX, y: ev.clientY, lat: m.d.lat, lng: m.d.lng, bewegt: false };
      m.el.setPointerCapture(ev.pointerId);
    });
    m.el.addEventListener('pointermove', function (ev) {
      if (!start) { return; }
      if (!start.bewegt && Math.abs(ev.clientX - start.x) + Math.abs(ev.clientY - start.y) < 5) { return; }
      start.bewegt = true;
      m.el.classList.add('is-gezogen');
      var p = punktIn(ev);
      var g = gradBei(begrenzen(p.x, 0, flaeche.clientWidth), begrenzen(p.y, 0, flaeche.clientHeight));
      m.d.lat = g.lat;
      m.d.lng = g.lng;
      setzen(m.el, m.d, ecke());
    });
    function ende(ev) {
      if (!start) { return; }
      var s = start;
      start = null;
      m.el.classList.remove('is-gezogen');
      try { m.el.releasePointerCapture(ev.pointerId); } catch (e) { /* schon frei */ }
      if (!s.bewegt) { bearbeiten(m); return; }
      senden({ action: 'marker_aendern', id: m.d.id, lat: m.d.lat, lng: m.d.lng }, function (d) {
        if (!d) { m.d.lat = s.lat; m.d.lng = s.lng; zeichnen(); }   // nicht gespeichert: zurück an die alte Stelle
      });
    }
    m.el.addEventListener('pointerup', ende);
    m.el.addEventListener('pointercancel', ende);
  }

  /* ---------- Ein Symbol bearbeiten: Beschriftung, Größe, Drehung, entfernen ---------- */
  var tafel = null;
  var tafelUhr = null;
  var offen = null;          // noch nicht gespeicherte Änderungen: { m: Symbol, felder: {…} }
  function tafelZu() {
    jetztSpeichern();
    if (tafel) { tafel.parentNode.removeChild(tafel); tafel = null; }
    if (gewaehlt) { gewaehlt.el.classList.remove('is-gewaehlt'); gewaehlt = null; }
  }
  /** Änderungen sammeln und kurz nach der letzten in einem Rutsch speichern – so geht keine verloren */
  function spaeterSpeichern(m, feld) {
    if (offen && offen.m !== m) { jetztSpeichern(); }
    offen = offen || { m: m, felder: {} };
    offen.felder[feld] = feld === 'beschriftung' ? m.d.text : m.d[feld];
    window.clearTimeout(tafelUhr);
    tafelUhr = window.setTimeout(jetztSpeichern, 400);
  }
  function jetztSpeichern() {
    window.clearTimeout(tafelUhr);
    if (!offen) { return; }
    var daten = { action: 'marker_aendern', id: offen.m.d.id };
    Object.keys(offen.felder).forEach(function (k) { daten[k] = offen.felder[k]; });
    offen = null;
    senden(daten);
  }
  function regler(m, beschriftung, feld, min, max, einheit) {
    var zeile = neu('label', 'lagekarte__regler');
    var wert = neu('span', 'muted small', m.d[feld] + einheit);
    var kopf = zeile.appendChild(neu('span', '', beschriftung + ' '));
    kopf.appendChild(wert);
    var r = zeile.appendChild(neu('input'));
    r.type = 'range';
    r.min = min;
    r.max = max;
    r.value = m.d[feld];
    r.addEventListener('input', function () {
      m.d[feld] = parseInt(r.value, 10);
      wert.textContent = m.d[feld] + einheit;
      markerAussehen(m);
      spaeterSpeichern(m, feld);
    });
    return zeile;
  }
  function bearbeiten(m) {
    tafelZu();
    menueZu();
    gewaehlt = m;
    m.el.classList.add('is-gewaehlt');
    tafel = neu('div', 'lagekarte__tafel');
    tafel.addEventListener('pointerdown', function (ev) { ev.stopPropagation(); });
    tafel.addEventListener('wheel', function (ev) { ev.stopPropagation(); });
    tafel.appendChild(neu('strong', '', m.d.name || 'Symbol'));
    var feld = tafel.appendChild(neu('label', 'lagekarte__regler'));
    feld.appendChild(neu('span', '', 'Beschriftung'));
    var eingabe = feld.appendChild(neu('input'));
    eingabe.type = 'text';
    eingabe.maxLength = 150;
    eingabe.value = m.d.text || '';
    eingabe.addEventListener('input', function () {
      m.d.text = eingabe.value;
      markerAussehen(m);
      spaeterSpeichern(m, 'beschriftung');
    });
    tafel.appendChild(regler(m, 'Größe', 'groesse', K.groesse.min, K.groesse.max, ' px'));
    tafel.appendChild(regler(m, 'Drehung', 'drehung', 0, 359, '°'));
    var knoepfe = tafel.appendChild(neu('div', 'btnrow'));
    var weg = knoepfe.appendChild(neu('button', 'btn btn--sec btn--sm', 'Entfernen'));
    weg.type = 'button';
    weg.addEventListener('click', function () {
      // was noch nicht gespeichert ist, braucht es nicht mehr
      window.clearTimeout(tafelUhr);
      offen = null;
      senden({ action: 'marker_weg', id: m.d.id }, function (d) {
        if (!d) { return; }
        markerEbene.removeChild(m.el);
        marker.splice(marker.indexOf(m), 1);
        tafelZu();
        melden('Symbol entfernt.');
      });
    });
    var zu = knoepfe.appendChild(neu('button', 'btn btn--sm', 'Fertig'));
    zu.type = 'button';
    zu.addEventListener('click', tafelZu);
    flaeche.appendChild(tafel);
  }

  /* ---------- Rechtsklick: Symbol aus der Bibliothek setzen ---------- */
  var menue = null;
  function menueZu() {
    if (menue) { menue.parentNode.removeChild(menue); menue = null; }
  }
  function menueAuf(px, py) {
    menueZu();
    tafelZu();
    var ort = gradBei(px, py);
    menue = neu('div', 'lagekarte__menue');
    menue.setAttribute('role', 'menu');
    menue.addEventListener('pointerdown', function (ev) { ev.stopPropagation(); });
    menue.addEventListener('wheel', function (ev) { ev.stopPropagation(); });
    menue.addEventListener('contextmenu', function (ev) { ev.preventDefault(); ev.stopPropagation(); });
    menue.appendChild(neu('div', 'lagekarte__menue-kopf', 'Symbol hier setzen'));
    if (!K.symbole.length) {
      menue.appendChild(neu('p', 'small muted', 'Noch kein Symbol in der Bibliothek.'));
      if (K.symbolSeite) {
        var hin = menue.appendChild(neu('a', 'btn btn--sm', 'Zur Symbolbibliothek'));
        hin.href = K.symbolSeite;
      }
    } else {
      var liste = neu('div', 'lagekarte__menue-liste');
      var suche = null;
      if (K.symbole.length > 8) {
        suche = menue.appendChild(neu('input'));
        suche.type = 'search';
        suche.placeholder = 'Symbol suchen';
        suche.setAttribute('aria-label', 'Symbol suchen');
      }
      var fuellen = function (q) {
        liste.textContent = '';
        var gruppe = null;
        var treffer = 0;
        K.symbole.forEach(function (s) {
          if (q && (s.name + ' ' + s.gruppe).toLowerCase().indexOf(q) === -1) { return; }
          treffer++;
          if (s.gruppe !== gruppe) {
            gruppe = s.gruppe;
            liste.appendChild(neu('div', 'lagekarte__menue-gruppe', gruppe || 'ohne Gruppe'));
          }
          var knopf = liste.appendChild(neu('button', 'lagekarte__menue-eintrag'));
          knopf.type = 'button';
          knopf.setAttribute('role', 'menuitem');
          var bild = knopf.appendChild(neu('img'));
          bild.alt = '';
          bild.src = s.src;
          knopf.appendChild(neu('span', '', s.name));
          knopf.addEventListener('click', function () {
            menueZu();
            senden({ action: 'marker_neu', karte_id: K.id, symbol_id: s.id, lat: ort.lat, lng: ort.lng }, function (d) {
              if (!d) { return; }
              markerDazu(d.marker);
              zeichnen();
              melden('„' + s.name + '“ gesetzt – anklicken zum Beschriften, ziehen zum Versetzen.');
            });
          });
        });
        if (!treffer) { liste.appendChild(neu('p', 'small muted', 'Nichts gefunden.')); }
      };
      fuellen('');
      if (suche) { suche.addEventListener('input', function () { fuellen(suche.value.trim().toLowerCase()); }); }
      menue.appendChild(liste);
    }
    flaeche.appendChild(menue);
    // ganz in der Fläche halten
    menue.style.left = Math.max(4, Math.min(px, flaeche.clientWidth - menue.offsetWidth - 4)) + 'px';
    menue.style.top = Math.max(4, Math.min(py, flaeche.clientHeight - menue.offsetHeight - 4)) + 'px';
    var erstes = menue.querySelector('input, button, a');
    if (erstes && window.matchMedia && window.matchMedia('(pointer: fine)').matches) { erstes.focus(); }
  }
  flaeche.addEventListener('contextmenu', function (ev) {
    if (!K.setzen) { return; }
    ev.preventDefault();
    var p = punktIn(ev);
    menueAuf(p.x, p.y);
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') { menueZu(); tafelZu(); }
  });

  /* ---------- Verschieben, Zoomen, lange drücken ---------- */
  var zeiger = {};           // pointerId → {x, y}
  var zug = null;            // Verschieben: Start in Weltpixeln
  var spreizung = null;      // Abstand zweier Finger beim letzten Zoomschritt
  var druckUhr = null;
  function zeigerZahl() { return Object.keys(zeiger).length; }
  function abstand() {
    var k = Object.keys(zeiger);
    return Math.hypot(zeiger[k[0]].x - zeiger[k[1]].x, zeiger[k[0]].y - zeiger[k[1]].y);
  }
  flaeche.addEventListener('pointerdown', function (ev) {
    if (ev.button !== 0) { return; }
    menueZu();
    zeiger[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
    try { flaeche.setPointerCapture(ev.pointerId); } catch (e) { /* ohne geht es auch */ }
    window.clearTimeout(druckUhr);
    if (zeigerZahl() === 2) {
      zug = null;
      spreizung = abstand();
      return;
    }
    zug = { x: ev.clientX, y: ev.clientY, wx: weltX(zustand.lng, zustand.zoom), wy: weltY(zustand.lat, zustand.zoom), bewegt: false };
    // Am Handy gibt es keinen Rechtsklick: lange drücken öffnet das Menü
    if (K.setzen && ev.pointerType === 'touch') {
      var p = punktIn(ev);
      druckUhr = window.setTimeout(function () {
        if (zug && !zug.bewegt && zeigerZahl() === 1) { zug = null; menueAuf(p.x, p.y); }
      }, 600);
    }
  });
  flaeche.addEventListener('pointermove', function (ev) {
    if (!zeiger[ev.pointerId]) { return; }
    zeiger[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
    if (zeigerZahl() === 2 && spreizung) {
      var a = abstand();
      if (a > spreizung * 1.5) { zoomen(zustand.zoom + 1); spreizung = a; }
      else if (a < spreizung / 1.5) { zoomen(zustand.zoom - 1); spreizung = a; }
      return;
    }
    if (!zug) { return; }
    var dx = ev.clientX - zug.x;
    var dy = ev.clientY - zug.y;
    if (!zug.bewegt && Math.abs(dx) + Math.abs(dy) < 5) { return; }
    zug.bewegt = true;
    window.clearTimeout(druckUhr);
    flaeche.classList.add('is-gezogen');
    zustand.lng = lngVon(zug.wx - dx, zustand.zoom);
    zustand.lat = latVon(zug.wy - dy, zustand.zoom);
    zeichnen();
  });
  function zeigerEnde(ev) {
    delete zeiger[ev.pointerId];
    window.clearTimeout(druckUhr);
    if (zeigerZahl() < 2) { spreizung = null; }
    if (zeigerZahl() === 0) {
      if (zug && !zug.bewegt) { tafelZu(); }   // Klick auf die leere Karte schließt die Tafel
      zug = null;
      flaeche.classList.remove('is-gezogen');
    }
  }
  flaeche.addEventListener('pointerup', zeigerEnde);
  flaeche.addEventListener('pointercancel', zeigerEnde);

  var radZeit = 0;
  flaeche.addEventListener('wheel', function (ev) {
    ev.preventDefault();
    var jetzt = Date.now();
    if (jetzt - radZeit < 140 || ev.deltaY === 0) { return; }   // ein Schritt je Raster, auch auf dem Tastfeld
    radZeit = jetzt;
    var p = punktIn(ev);
    zoomen(zustand.zoom + (ev.deltaY < 0 ? 1 : -1), p.x, p.y);
  }, { passive: false });
  flaeche.addEventListener('dblclick', function (ev) {
    if (ev.target.closest('.lagekarte__marker, .lagekarte__tafel, .lagekarte__menue, .lagekarte__knoepfe, .lagekarte__fahrzeug')) { return; }
    var p = punktIn(ev);
    zoomen(zustand.zoom + 1, p.x, p.y);
  });

  /* ---------- Knöpfe auf der Karte ---------- */
  var knoepfe = flaeche.appendChild(neu('div', 'lagekarte__knoepfe'));
  knoepfe.addEventListener('pointerdown', function (ev) { ev.stopPropagation(); });
  knoepfe.addEventListener('dblclick', function (ev) { ev.stopPropagation(); });
  function knopf(zeichen, titel, tun) {
    var b = knoepfe.appendChild(neu('button', '', zeichen));
    b.type = 'button';
    b.title = titel;
    b.setAttribute('aria-label', titel);
    b.addEventListener('click', tun);
    return b;
  }
  knopf('+', 'Hineinzoomen', function () { zoomen(zustand.zoom + 1); });
  knopf('−', 'Herauszoomen', function () { zoomen(zustand.zoom - 1); });
  knopf('⌂', 'Zurück zum gemerkten Ausschnitt', function () {
    zustand.lat = K.lat; zustand.lng = K.lng; zustand.zoom = K.zoom;
    menueZu();
    zeichnen();
  });

  var quelle = flaeche.appendChild(neu('div', 'lagekarte__quelle'));
  quelle.addEventListener('pointerdown', function (ev) { ev.stopPropagation(); });
  if (/openstreetmap/i.test(K.quelle + K.kacheln)) {
    var verweis = quelle.appendChild(neu('a', '', K.quelle || '© OpenStreetMap'));
    verweis.href = 'https://www.openstreetmap.org/copyright';
    verweis.target = '_blank';
    verweis.rel = 'noopener';
  } else {
    quelle.textContent = K.quelle || '';
  }

  /* ---------- Ausschnitt merken ---------- */
  var merken = wurzel.querySelector('[data-karte-merken]');
  if (merken) {
    merken.addEventListener('click', function () {
      var lat = runden6(zustand.lat);
      var lng = runden6(((zustand.lng + 180) % 360 + 360) % 360 - 180);
      senden({ action: 'ausschnitt', karte_id: K.id, lat: lat, lng: lng, zoom: zustand.zoom }, function (d) {
        if (!d) { return; }
        K.lat = lat; K.lng = lng; K.zoom = zustand.zoom;
        melden('Ausschnitt gemerkt – so öffnet die Karte ab jetzt.');
      });
    });
  }

  /* ---------- Symbol setzen ohne Rechtsklick: Knopf über der Karte, Menü in der Kartenmitte ---------- */
  var setzKnopf = wurzel.querySelector('[data-karte-setzen]');
  if (setzKnopf && K.setzen) {
    setzKnopf.addEventListener('click', function () {
      flaeche.scrollIntoView({ block: 'nearest' });
      menueAuf(flaeche.clientWidth / 2, flaeche.clientHeight / 2);
    });
  }

  /* ---------- Fahrzeuge einblenden ---------- */
  var schalter = wurzel.querySelector('[data-karte-fahrzeuge]');
  var fzStand = wurzel.querySelector('[data-karte-fahrzeuge-stand]');
  var fzUhr = null;
  var merkName = 'ovb-karte-fahrzeuge-' + K.id;
  function fahrzeugeWeg() {
    fahrzeuge.forEach(function (f) { markerEbene.removeChild(f.el); });
    fahrzeuge = [];
  }
  function fahrzeugeHolen() {
    window.fetch(K.api + (K.api.indexOf('?') === -1 ? '?' : '&') + 'action=fahrzeuge', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!schalter.checked) { return; }
        if (!d || !d.ok) { if (fzStand) { fzStand.textContent = (d && d.fehler) || 'Fahrzeuge nicht abrufbar.'; } return; }
        fahrzeugeWeg();
        d.fahrzeuge.forEach(function (fz) {
          var el = neu('a', 'lagekarte__fahrzeug' + (fz.alt ? ' is-alt' : ''));
          el.href = fz.url;
          el.target = '_blank';
          el.rel = 'noopener';
          el.title = fz.name + ' · ' + fz.stand;
          el.addEventListener('pointerdown', function (ev) { ev.stopPropagation(); });
          el.appendChild(neu('span', 'lagekarte__fahrzeug-punkt'));
          el.appendChild(neu('span', 'lagekarte__fahrzeug-name', fz.kurz));
          fahrzeuge.push({ d: fz, el: markerEbene.appendChild(el) });
        });
        if (fzStand) {
          fzStand.textContent = d.fahrzeuge.length
            ? (d.fahrzeuge.length === 1 ? '1 Fahrzeug' : d.fahrzeuge.length + ' Fahrzeuge') + ' · Stand ' + d.stand
            : 'Kein Fahrzeug hat eine Position gemeldet.';
        }
        zeichnen();
      })
      .catch(function () { if (fzStand) { fzStand.textContent = 'Fahrzeuge nicht abrufbar.'; } });
  }
  function fahrzeugeSchalten() {
    window.clearInterval(fzUhr);
    try { window.localStorage.setItem(merkName, schalter.checked ? '1' : '0'); } catch (e) { /* ohne Speicher eben nicht gemerkt */ }
    if (!schalter.checked) {
      fahrzeugeWeg();
      if (fzStand) { fzStand.textContent = ''; }
      return;
    }
    fahrzeugeHolen();
    // jede Minute frisch – aber nur, solange die Seite auch zu sehen ist
    fzUhr = window.setInterval(function () { if (!document.hidden) { fahrzeugeHolen(); } }, 60000);
  }
  if (schalter && K.fahrzeuge) {
    var gemerkt = null;
    try { gemerkt = window.localStorage.getItem(merkName); } catch (e) { gemerkt = null; }
    schalter.checked = gemerkt === null ? K.fahrzeugeAn : gemerkt === '1';
    schalter.addEventListener('change', fahrzeugeSchalten);
    if (schalter.checked) { fahrzeugeSchalten(); }
  }

  // Der Browser schiebt den Inhalt eines Kastens mit, wenn darin etwas den Fokus bekommt, das am Rand
  // liegt – dann säßen Kacheln und Symbole schief. Die Fläche bleibt deshalb immer am Ursprung.
  flaeche.addEventListener('scroll', function () {
    if (flaeche.scrollLeft !== 0 || flaeche.scrollTop !== 0) { flaeche.scrollLeft = 0; flaeche.scrollTop = 0; }
  });

  /* ---------- Los ---------- */
  K.marker.forEach(markerDazu);
  window.addEventListener('resize', zeichnen);
  wurzel.classList.add('is-bereit');
  jetztZeichnen();
})();
