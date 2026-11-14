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
 *   ⛶ / „Vollbild"    Karte und Leiste auf den ganzen Bildschirm (Esc beendet)
 *   Symbol ziehen     versetzen            Symbol anklicken            beschriften, Stärke, Abschnitt, Größe, Drehung, löschen
 *
 * Einheiten und Abschnitte holt die Karte im Takt neu (K.lageSekunden) und arbeitet die Antwort an
 * Ort und Stelle ein – so kommt an, was jemand an einem anderen Gerät setzt, auch auf dem Monitor:
 * Neues ploppt auf (mit Ton, wenn der Schalter an ist), Versetztes gleitet an die neue Stelle.
 *
 * Jedes gesetzte Symbol ist eine Einheit mit Stärke (Führer/Unterführer/Helfer). Neben der
 * Karte steht die Gesamtstärke, darunter die Abschnitte mit Teilstärke und ihren Einheiten;
 * dort lassen sich Abschnitte und Einheiten anlegen, ändern und löschen – Einheiten auch ohne
 * Stelle auf der Karte (lat/lng null: sie stehen dann nur in der Liste).
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
  var abschnitte = K.abschnitte || [];   // { id, name } – Abschnitte dieser Karte

  function neu(tag, klasse, text) {
    var el = document.createElement(tag);
    if (klasse) { el.className = klasse; }
    if (text !== undefined) { el.textContent = text; }
    return el;
  }
  var kachelEbene = flaeche.appendChild(neu('div', 'lagekarte__kacheln'));
  var markerEbene = flaeche.appendChild(neu('div', 'lagekarte__marker-ebene'));
  var meldung = wurzel.querySelector('[data-karte-meldung]');
  var seite = wurzel.querySelector('[data-karte-seite]');
  function ohneOrt(m) { return m.d.lat === null || m.d.lat === undefined; }

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
    marker.forEach(function (m) { if (!ohneOrt(m)) { setzen(m.el, m.d, e); } });
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
    var m = { d: d, el: el, bild: bild, text: text, seit: Date.now(), zieht: false };
    markerAussehen(m);
    if (K.setzen) {
      el.classList.add('is-beweglich');
      markerZiehbar(m);
    }
    marker.push(m);
    markerEbene.appendChild(el);
    return m;
  }
  function staerkeText(st) { return st && st.gesamt > 0 ? st.text : ''; }
  function markerAussehen(m) {
    m.el.hidden = ohneOrt(m);   // ohne Stelle steht die Einheit nur in der Liste
    if (m.bild.getAttribute('src') !== m.d.src) { m.bild.src = m.d.src; }
    m.bild.alt = m.d.name || 'Symbol';
    m.bild.style.width = m.d.groesse + 'px';
    m.bild.style.transform = m.d.drehung ? 'rotate(' + m.d.drehung + 'deg)' : '';
    // unter dem Symbol: Beschriftung und Stärke, wie auf der Lagekarte üblich
    var zeile = (m.d.text || '') + (staerkeText(m.d.staerke) ? (m.d.text ? ' ' : '') + staerkeText(m.d.staerke) : '');
    m.text.textContent = zeile;
    m.text.hidden = !zeile;
    m.el.title = (m.d.name || '') + (m.d.text ? ' – ' + m.d.text : '') + (staerkeText(m.d.staerke) ? ' · Stärke ' + staerkeText(m.d.staerke) : '');
  }
  function markerZiehbar(m) {
    var start = null;
    m.el.addEventListener('pointerdown', function (ev) {
      if (ev.button !== 0) { return; }
      ev.stopPropagation();   // die Karte bleibt liegen
      ev.preventDefault();
      menueZu();
      start = { x: ev.clientX, y: ev.clientY, lat: m.d.lat, lng: m.d.lng, bewegt: false };
      m.zieht = true;   // der Abruf im Takt lässt die Einheit in Ruhe
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
      if (!s.bewegt) { m.zieht = false; bearbeiten(m); return; }
      senden({ action: 'marker_aendern', id: m.d.id, lat: m.d.lat, lng: m.d.lng }, function (d) {
        m.zieht = false;
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
    if (gewaehlt) {
      gewaehlt.el.classList.remove('is-gewaehlt');
      if (gewaehlt.li) { gewaehlt.li.classList.remove('is-gewaehlt'); }
      gewaehlt = null;
    }
  }
  /** Änderungen sammeln und kurz nach der letzten in einem Rutsch speichern – so geht keine verloren */
  function spaeterSpeichern(m, feld, wert) {
    if (offen && offen.m !== m) { jetztSpeichern(); }
    offen = offen || { m: m, felder: {} };
    offen.felder[feld] = wert;
    window.clearTimeout(tafelUhr);
    tafelUhr = window.setTimeout(jetztSpeichern, 400);
  }
  function jetztSpeichern() {
    window.clearTimeout(tafelUhr);
    if (!offen) { return; }
    var m = offen.m;
    var daten = { action: 'marker_aendern', id: m.d.id };
    Object.keys(offen.felder).forEach(function (k) { daten[k] = offen.felder[k]; });
    offen = null;
    senden(daten, function (d) { if (d) { uebernehmen(m, d.marker); } });
  }
  /** Was der Server zurückgibt, gilt: Stärke (gelesen und begrenzt), Abschnitt, Symbol. Stelle, Größe,
   *  Drehung und Beschriftung bleiben, wie sie hier gerade sind – daran kann die Hand noch sein. */
  function uebernehmen(m, d) {
    if (!d) { return; }
    m.d.staerke = d.staerke;
    m.d.abschnitt = d.abschnitt;
    m.d.symbol = d.symbol;
    m.d.src = d.src;
    m.d.name = d.name;
    markerAussehen(m);
    seiteZeichnen();
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
      spaeterSpeichern(m, feld, m.d[feld]);
    });
    return zeile;
  }
  function auswahl(elternteil, beschriftung) {
    var feld = elternteil.appendChild(neu('label', 'lagekarte__regler'));
    feld.appendChild(neu('span', '', beschriftung));
    return feld.appendChild(neu('select'));
  }
  function bearbeiten(m) {
    tafelZu();
    menueZu();
    gewaehlt = m;
    m.el.classList.add('is-gewaehlt');
    if (m.li) { m.li.classList.add('is-gewaehlt'); }
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
      if (m.li) { m.li.querySelector('.lagekarte__einheit-name').firstChild.nodeValue = einheitName(m); }
      spaeterSpeichern(m, 'beschriftung', eingabe.value);
    });
    // Stärke, wie sie gemeldet wird: Führer/Unterführer/Helfer – der Server liest und begrenzt sie
    var stFeld = tafel.appendChild(neu('label', 'lagekarte__regler'));
    stFeld.appendChild(neu('span', '', 'Stärke (Führer/Unterführer/Helfer)'));
    var stEingabe = stFeld.appendChild(neu('input'));
    stEingabe.type = 'text';
    stEingabe.inputMode = 'numeric';
    stEingabe.maxLength = 20;
    stEingabe.placeholder = 'z. B. 1/2/9 oder 12';
    stEingabe.value = m.d.staerke && m.d.staerke.gesamt > 0 ? m.d.staerke.f + '/' + m.d.staerke.u + '/' + m.d.staerke.h : '';
    stEingabe.addEventListener('input', function () { spaeterSpeichern(m, 'staerke', stEingabe.value); });
    if (abschnitte.length) {
      var abWahl = auswahl(tafel, 'Abschnitt');
      var keiner = abWahl.appendChild(neu('option', '', '– kein Abschnitt –'));
      keiner.value = '0';
      abschnitte.forEach(function (a) {
        var o = abWahl.appendChild(neu('option', '', a.name));
        o.value = String(a.id);
        o.selected = m.d.abschnitt === a.id;
      });
      abWahl.addEventListener('change', function () {
        senden({ action: 'marker_aendern', id: m.d.id, abschnitt_id: parseInt(abWahl.value, 10) || 0 }, function (d) { if (d) { uebernehmen(m, d.marker); } });
      });
    }
    if (K.symbole.length > 1) {
      var syWahl = auswahl(tafel, 'Symbol');
      var gruppe = null;
      var og = null;
      K.symbole.forEach(function (s) {
        if (s.gruppe !== gruppe || !og) {
          gruppe = s.gruppe;
          og = syWahl.appendChild(neu('optgroup'));
          og.label = gruppe || 'Ohne Gruppe';
        }
        var o = og.appendChild(neu('option', '', s.name));
        o.value = String(s.id);
        o.selected = s.id === m.d.symbol;
      });
      syWahl.addEventListener('change', function () {
        senden({ action: 'marker_aendern', id: m.d.id, symbol_id: parseInt(syWahl.value, 10) || 0 }, function (d) { if (d) { uebernehmen(m, d.marker); } });
      });
    }
    tafel.appendChild(regler(m, 'Größe', 'groesse', K.groesse.min, K.groesse.max, ' px'));
    tafel.appendChild(regler(m, 'Drehung', 'drehung', 0, 359, '°'));
    var knoepfe = tafel.appendChild(neu('div', 'btnrow'));
    var stelle = knoepfe.appendChild(neu('button', 'btn btn--sec btn--sm', ohneOrt(m) ? 'In die Kartenmitte' : 'Von der Karte nehmen'));
    stelle.type = 'button';
    stelle.title = ohneOrt(m) ? 'Die Einheit in der Kartenmitte auf die Karte setzen'
                              : 'Die Einheit bleibt mit ihrer Stärke in der Liste, verschwindet aber von der Karte';
    stelle.addEventListener('click', function () { jetztSpeichern(); einheitStelle(m, ohneOrt(m)); });
    var weg = knoepfe.appendChild(neu('button', 'btn btn--sec btn--sm', 'Löschen'));
    weg.type = 'button';
    weg.addEventListener('click', function () { einheitWeg(m); });
    var zu = knoepfe.appendChild(neu('button', 'btn btn--sm', 'Fertig'));
    zu.type = 'button';
    zu.addEventListener('click', tafelZu);
    flaeche.appendChild(tafel);
  }

  /* ---------- Neben der Karte: Gesamtstärke, Abschnitte mit Teilstärke, Einheiten ---------- */
  function staerkeSumme(liste) {
    var s = { f: 0, u: 0, h: 0 };
    liste.forEach(function (m) {
      var st = m.d.staerke || {};
      s.f += st.f || 0; s.u += st.u || 0; s.h += st.h || 0;
    });
    s.gesamt = s.f + s.u + s.h;
    s.text = s.f + '/' + s.u + '/' + s.h + '/' + s.gesamt;
    return s;
  }
  function einheitName(m) { return m.d.text || m.d.name || 'Einheit'; }
  function abschnittVon(id) {
    for (var i = 0; i < abschnitte.length; i++) { if (abschnitte[i].id === id) { return abschnitte[i]; } }
    return null;
  }
  function kleinKnopf(zeichen, titel, tun) {
    var b = neu('button', 'lagekarte__klein', zeichen);
    b.type = 'button';
    b.title = titel;
    b.setAttribute('aria-label', titel);
    b.addEventListener('click', function (ev) { ev.stopPropagation(); tun(); });
    return b;
  }
  function seiteZeichnen() {
    if (!seite) { return; }
    seite.textContent = '';
    var gesamt = staerkeSumme(marker);
    var kopf = seite.appendChild(neu('div', 'lagekarte__gesamt'));
    kopf.appendChild(neu('div', 'lagekarte__gesamt-kopf', 'Gesamtstärke'));
    var zahl = kopf.appendChild(neu('div', 'lagekarte__gesamt-zahl', gesamt.text));
    zahl.setAttribute('data-karte-gesamt', '');
    kopf.appendChild(neu('div', 'small muted', 'Führer / Unterführer / Helfer / Gesamt · ' + (marker.length === 1 ? '1 Einheit' : marker.length + ' Einheiten')));
    var stand = kopf.appendChild(neu('div', 'small muted lagekarte__lage-stand', lageStandText));
    stand.setAttribute('data-karte-lage-stand', '');
    stand.classList.toggle('is-schlecht', lageSchlecht);
    abschnitte.forEach(function (a) {
      seite.appendChild(abschnittKasten(a, marker.filter(function (m) { return m.d.abschnitt === a.id; })));
    });
    seite.appendChild(abschnittKasten(null, marker.filter(function (m) { return !m.d.abschnitt || !abschnittVon(m.d.abschnitt); })));
    if (K.setzen) {
      var dazu = seite.appendChild(neu('button', 'btn btn--sec btn--sm lagekarte__seite-knopf', '+ Abschnitt'));
      dazu.type = 'button';
      dazu.title = 'Einen Abschnitt anlegen – etwa einen Einsatzabschnitt oder Unterabschnitt';
      dazu.addEventListener('click', abschnittNeu);
    }
  }
  function abschnittKasten(a, liste) {
    var kasten = neu('section', 'lagekarte__abschnitt');
    var st = staerkeSumme(liste);
    var kopf = kasten.appendChild(neu('div', 'lagekarte__abschnitt-kopf'));
    kopf.appendChild(neu('span', 'lagekarte__abschnitt-name', a ? a.name : (abschnitte.length ? 'Ohne Abschnitt' : 'Einheiten')));
    kopf.appendChild(neu('span', 'lagekarte__abschnitt-staerke', st.text));
    kopf.title = (liste.length === 1 ? '1 Einheit' : liste.length + ' Einheiten') + ' · Stärke ' + st.text;
    if (a && K.setzen) {
      kopf.appendChild(kleinKnopf('✎', 'Abschnitt umbenennen', function () { abschnittUmbenennen(a); }));
      kopf.appendChild(kleinKnopf('×', 'Abschnitt löschen', function () { abschnittWeg(a); }));
    }
    var ul = kasten.appendChild(neu('ul', 'lagekarte__einheiten'));
    if (!liste.length) {
      ul.appendChild(neu('li', 'lagekarte__einheit lagekarte__einheit--leer small muted',
        a ? 'Noch keine Einheit in diesem Abschnitt.' : (abschnitte.length ? 'Jede Einheit steht in einem Abschnitt.' : 'Noch keine Einheit auf dieser Karte.')));
    }
    liste.forEach(function (m) { ul.appendChild(einheitZeile(m)); });
    if (K.setzen) {
      var dazu = kasten.appendChild(neu('button', 'btn btn--sec btn--sm lagekarte__seite-knopf', '+ Einheit'));
      dazu.type = 'button';
      dazu.title = 'Ein Symbol aus der Bibliothek als Einheit hinzufügen' + (a ? ' – in „' + a.name + '“' : '');
      dazu.addEventListener('click', function () {
        flaeche.scrollIntoView({ block: 'nearest' });
        menueAuf(flaeche.clientWidth / 2, flaeche.clientHeight / 2, { abschnitt: a ? a.id : 0, abschnittName: a ? a.name : '', ausListe: true });
      });
    }
    return kasten;
  }
  function einheitZeile(m) {
    var li = neu('li', 'lagekarte__einheit');
    m.li = li;
    li.classList.toggle('is-ohne-ort', ohneOrt(m));
    li.classList.toggle('is-gewaehlt', gewaehlt === m);
    var bild = li.appendChild(neu('img'));
    bild.alt = '';
    bild.src = m.d.src;
    var name = li.appendChild(neu('button', 'lagekarte__einheit-name', einheitName(m)));
    name.type = 'button';
    name.title = (ohneOrt(m) ? 'Steht nicht auf der Karte' : 'Auf der Karte zeigen') + (K.setzen ? ' – öffnet die Bearbeitung' : '')
      + (m.d.text && m.d.name && m.d.text !== m.d.name ? ' · Symbol: ' + m.d.name : '');
    name.addEventListener('click', function () { einheitZeigen(m); });
    if (ohneOrt(m)) { name.appendChild(neu('small', 'lagekarte__einheit-hinweis', 'nicht auf der Karte')); }
    li.appendChild(neu('span', 'lagekarte__einheit-staerke', staerkeText(m.d.staerke) || '–'));
    if (K.setzen) {
      li.appendChild(kleinKnopf('✎', 'Einheit bearbeiten', function () { einheitZeigen(m); }));
      li.appendChild(kleinKnopf('×', 'Einheit löschen', function () { einheitWeg(m); }));
    }
    return li;
  }
  function einheitZeigen(m) {
    if (!ohneOrt(m)) {
      zustand.lat = m.d.lat;
      zustand.lng = m.d.lng;
      zeichnen();
      flaeche.scrollIntoView({ block: 'nearest' });
    }
    if (K.setzen) { bearbeiten(m); }
  }
  function einheitWeg(m) {
    var frage = '„' + einheitName(m) + '“' + (staerkeText(m.d.staerke) ? ' (Stärke ' + staerkeText(m.d.staerke) + ')' : '') + ' löschen?';
    if (!window.confirm(frage)) { return; }
    // was noch nicht gespeichert ist, braucht es nicht mehr
    window.clearTimeout(tafelUhr);
    if (offen && offen.m === m) { offen = null; }
    senden({ action: 'marker_weg', id: m.d.id }, function (d) {
      if (!d) { return; }
      if (gewaehlt === m) { tafelZu(); }
      lageGeloescht[m.d.id] = Date.now();
      markerEbene.removeChild(m.el);
      marker.splice(marker.indexOf(m), 1);
      seiteZeichnen();
      melden('„' + einheitName(m) + '“ gelöscht.');
    });
  }
  /** Eine Einheit in die Kartenmitte setzen – oder von der Karte nehmen; in der Liste bleibt sie */
  function einheitStelle(m, aufKarte) {
    var mitte = gradBei(flaeche.clientWidth / 2, flaeche.clientHeight / 2);
    senden({ action: 'marker_aendern', id: m.d.id, lat: aufKarte ? mitte.lat : '', lng: aufKarte ? mitte.lng : '' }, function (d) {
      if (!d) { return; }
      m.d.lat = d.marker.lat;
      m.d.lng = d.marker.lng;
      markerAussehen(m);
      zeichnen();
      seiteZeichnen();
      if (gewaehlt === m) { bearbeiten(m); }   // die Tafel neu, der Knopf wechselt
      melden(aufKarte ? '„' + einheitName(m) + '“ steht in der Kartenmitte – ziehen versetzt sie.' : '„' + einheitName(m) + '“ steht jetzt nur in der Liste.');
    });
  }
  function abschnittNeu() {
    var name = window.prompt('Name des neuen Abschnitts – etwa „EA 1 Nord“ oder „UA Bergung“:', '');
    if (name === null || name.trim() === '') { return; }
    senden({ action: 'abschnitt_neu', karte_id: K.id, name: name }, function (d) {
      if (!d) { return; }
      abschnitte.push(d.abschnitt);
      seiteZeichnen();
      if (gewaehlt) { bearbeiten(gewaehlt); }
      melden('Abschnitt „' + d.abschnitt.name + '“ angelegt – „+ Einheit“ darunter fügt Einheiten hinzu.');
    });
  }
  function abschnittUmbenennen(a) {
    var name = window.prompt('Neuer Name für den Abschnitt:', a.name);
    if (name === null || name.trim() === '' || name.trim() === a.name) { return; }
    senden({ action: 'abschnitt_aendern', id: a.id, name: name }, function (d) {
      if (!d) { return; }
      a.name = d.abschnitt.name;
      seiteZeichnen();
      if (gewaehlt) { bearbeiten(gewaehlt); }
    });
  }
  function abschnittWeg(a) {
    var drin = marker.filter(function (m) { return m.d.abschnitt === a.id; }).length;
    var frage = 'Abschnitt „' + a.name + '“ löschen?' + (drin ? ' ' + (drin === 1 ? 'Seine Einheit bleibt' : 'Seine ' + drin + ' Einheiten bleiben') + ' erhalten und ' + (drin === 1 ? 'steht' : 'stehen') + ' dann ohne Abschnitt.' : '');
    if (!window.confirm(frage)) { return; }
    senden({ action: 'abschnitt_weg', id: a.id }, function (d) {
      if (!d) { return; }
      abschnitte.splice(abschnitte.indexOf(a), 1);
      marker.forEach(function (m) { if (m.d.abschnitt === a.id) { m.d.abschnitt = null; } });
      seiteZeichnen();
      if (gewaehlt) { bearbeiten(gewaehlt); }
      melden('Abschnitt „' + a.name + '“ gelöscht.');
    });
  }

  /* ---------- Rechtsklick: Symbol aus der Bibliothek setzen ---------- */
  var menue = null;
  function menueZu() {
    if (menue) { menue.parentNode.removeChild(menue); menue = null; }
  }
  /** $ziel (wahlweise): { abschnitt, abschnittName, ausListe } – „+ Einheit“ neben der Karte */
  function menueAuf(px, py, ziel) {
    menueZu();
    tafelZu();
    ziel = ziel || {};
    var ort = gradBei(px, py);
    menue = neu('div', 'lagekarte__menue');
    menue.setAttribute('role', 'menu');
    menue.addEventListener('pointerdown', function (ev) { ev.stopPropagation(); });
    menue.addEventListener('wheel', function (ev) { ev.stopPropagation(); });
    menue.addEventListener('contextmenu', function (ev) { ev.preventDefault(); ev.stopPropagation(); });
    menue.appendChild(neu('div', 'lagekarte__menue-kopf', ziel.ausListe ? 'Einheit hinzufügen' + (ziel.abschnittName ? ' – ' + ziel.abschnittName : '') : 'Symbol hier setzen'));
    var nurListe = null;
    if (ziel.ausListe && K.symbole.length) {
      var zeile = menue.appendChild(neu('label', 'lagekarte__menue-haken small'));
      nurListe = zeile.appendChild(neu('input'));
      nurListe.type = 'checkbox';
      zeile.appendChild(neu('span', '', ' nur in die Liste, nicht auf die Karte'));
    }
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
            var daten = { action: 'marker_neu', karte_id: K.id, symbol_id: s.id, lat: ort.lat, lng: ort.lng };
            if (ziel.abschnitt) { daten.abschnitt_id = ziel.abschnitt; }
            if (nurListe && nurListe.checked) { daten.lat = ''; daten.lng = ''; }
            menueZu();
            senden(daten, function (d) {
              if (!d) { return; }
              var m = markerDazu(d.marker);
              zeichnen();
              seiteZeichnen();
              if (ziel.ausListe) {
                bearbeiten(m);   // gleich Stärke und Beschriftung eintragen
              } else {
                melden('„' + s.name + '“ gesetzt – anklicken für Beschriftung und Stärke, ziehen zum Versetzen.');
              }
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
    if (ev.key !== 'Escape') { return; }
    menueZu();
    tafelZu();
    // das echte Vollbild beendet der Browser selbst; die Ersatzlösung hier
    if (koerper.classList.contains('is-vollbild')) { koerper.classList.remove('is-vollbild'); vollbildAnzeigen(); }
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

  /* ---------- Vollbild: Karte samt Leiste auf den ganzen Bildschirm – für den Monitor im Führungsraum ---------- */
  var koerper = flaeche.parentNode;   // .lagekarte__koerper: Fläche und Leiste nebeneinander
  var vollbildKnoepfe = [];
  // Ohne Vollbild-Schnittstelle (etwa iPhone) legt sich der Kasten per Klasse über die Seite
  function imVollbild() { return document.fullscreenElement === koerper || koerper.classList.contains('is-vollbild'); }
  var vollbildUhr = null;
  function vollbildAnzeigen() {
    window.clearTimeout(vollbildUhr);
    // echtes Vollbild da: die Ersatzlösung braucht es nicht mehr
    if (document.fullscreenElement === koerper) { koerper.classList.remove('is-vollbild'); }
    var an = imVollbild();
    vollbildKnoepfe.forEach(function (b) {
      b.setAttribute('aria-pressed', an ? 'true' : 'false');
      b.title = an ? 'Vollbild beenden (Esc)' : 'Karte und Stärke auf den ganzen Bildschirm – etwa für den Monitor im Führungsraum; Esc beendet';
    });
    zeichnen();   // die Fläche hat eine andere Größe
  }
  function vollbildUmschalten() {
    menueZu();
    tafelZu();
    if (imVollbild()) {
      if (document.fullscreenElement && document.exitFullscreen) { document.exitFullscreen(); }
      koerper.classList.remove('is-vollbild');
      vollbildAnzeigen();
      return;
    }
    var ersatz = function () { if (!document.fullscreenElement) { koerper.classList.add('is-vollbild'); vollbildAnzeigen(); } };
    var versprochen = null;
    try { versprochen = koerper.requestFullscreen ? koerper.requestFullscreen({ navigationUI: 'hide' }) : null; } catch (e) { versprochen = null; }
    if (versprochen && versprochen.catch) {
      versprochen.catch(ersatz);
      // manche Umgebungen antworten gar nicht – dann nach kurzer Zeit die Ersatzlösung
      window.clearTimeout(vollbildUhr);
      vollbildUhr = window.setTimeout(ersatz, 700);
    } else {
      ersatz();
    }
  }
  document.addEventListener('fullscreenchange', vollbildAnzeigen);
  vollbildKnoepfe.push(knopf('⛶', 'Vollbild', vollbildUmschalten));
  var vollbildKnopf = wurzel.querySelector('[data-karte-vollbild]');
  if (vollbildKnopf) {
    vollbildKnoepfe.push(vollbildKnopf);
    vollbildKnopf.addEventListener('click', vollbildUmschalten);
  }
  vollbildAnzeigen();

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
  var merkName = 'ovb-karte-fahrzeuge-' + K.id;
  var fzWecker = null;        // der nächste Abruf
  var fzOhne = [];            // Fahrzeuge im Dienst ohne Position – nur für die, die sie setzen dürfen
  var fzLaeuft = false;       // ein Abruf ist unterwegs
  var fzSekunden = Math.max(10, parseInt(K.fahrzeugSekunden, 10) || 30);
  function fahrzeugeWeg() {
    fahrzeuge.forEach(function (f) { markerEbene.removeChild(f.el); });
    fahrzeuge = [];
  }
  function fahrzeugeMelden(text, schlecht) {
    if (!fzStand) { return; }
    fzStand.textContent = text;
    fzStand.classList.toggle('is-schlecht', !!schlecht);
  }
  // Das taktische Zeichen des Fahrzeugs; ohne – oder wenn es nicht lädt – der Punkt
  function fahrzeugBild(el, fz) {
    var alt = el.querySelector('.lagekarte__fahrzeug-zeichen, .lagekarte__fahrzeug-punkt');
    var bild;
    if (fz.src) {
      bild = neu('img', 'lagekarte__fahrzeug-zeichen');
      bild.alt = '';
      bild.draggable = false;
      bild.addEventListener('error', function () {
        if (bild.parentNode) { bild.parentNode.replaceChild(neu('span', 'lagekarte__fahrzeug-punkt'), bild); }
      });
      bild.src = fz.src;
    } else {
      bild = neu('span', 'lagekarte__fahrzeug-punkt');
    }
    if (alt) { el.replaceChild(bild, alt); } else { el.insertBefore(bild, el.firstChild); }
  }
  function fahrzeugNeu(fz) {
    var el = neu('a', 'lagekarte__fahrzeug');
    el.target = '_blank';
    el.rel = 'noopener';
    el.draggable = false;
    el.appendChild(neu('span', 'lagekarte__fahrzeug-name', fz.kurz));
    fahrzeugBild(el, fz);
    var f = { d: fz, el: markerEbene.appendChild(el), zieht: false, gezogen: false };
    fahrzeugZiehbar(f);
    return f;
  }
  // Ein Fahrzeug ohne frische GPS-Meldung lässt sich ziehen; die neue Stelle wird am Fahrzeug gespeichert.
  // Ob es geht, sagt der Server je Fahrzeug (setzbar) – und prüft es beim Speichern noch einmal.
  function fahrzeugZiehbar(f) {
    var start = null;
    f.el.addEventListener('dragstart', function (ev) { ev.preventDefault(); });
    f.el.addEventListener('pointerdown', function (ev) {
      ev.stopPropagation();   // die Karte bleibt liegen
      f.gezogen = false;
      if (!K.fahrzeugSetzen || !f.d.setzbar || ev.button !== 0) { return; }
      menueZu();
      start = { x: ev.clientX, y: ev.clientY, lat: f.d.lat, lng: f.d.lng, bewegt: false };
      f.el.setPointerCapture(ev.pointerId);
    });
    f.el.addEventListener('pointermove', function (ev) {
      if (!start) { return; }
      if (!start.bewegt && Math.abs(ev.clientX - start.x) + Math.abs(ev.clientY - start.y) < 5) { return; }
      start.bewegt = true;
      f.zieht = true;
      f.gezogen = true;
      f.el.classList.remove('is-gleitend');
      f.el.classList.add('is-gezogen');
      var p = punktIn(ev);
      var g = gradBei(begrenzen(p.x, 0, flaeche.clientWidth), begrenzen(p.y, 0, flaeche.clientHeight));
      f.d.lat = g.lat;
      f.d.lng = g.lng;
      setzen(f.el, f.d, ecke());
    });
    function ende(ev) {
      if (!start) { return; }
      var s = start;
      start = null;
      f.el.classList.remove('is-gezogen');
      try { f.el.releasePointerCapture(ev.pointerId); } catch (e) { /* schon frei */ }
      if (!s.bewegt) { return; }   // nur angeklickt: der Verweis öffnet das Fahrzeug
      senden({ action: 'fahrzeug_setzen', id: f.d.id, lat: f.d.lat, lng: f.d.lng }, function (d) {
        f.zieht = false;
        if (!d) {
          // nicht gespeichert: zurück an die alte Stelle
          f.d.lat = s.lat;
          f.d.lng = s.lng;
        } else {
          f.d = d.fahrzeug;
          fahrzeugBeschriften(f);
          melden('„' + f.d.name + '“ gesetzt – die Position steht jetzt am Fahrzeug.');
        }
        zeichnen();
      });
    }
    f.el.addEventListener('pointerup', ende);
    f.el.addEventListener('pointercancel', ende);
    // nach dem Ziehen soll der Verweis nicht aufgehen
    f.el.addEventListener('click', function (ev) {
      if (f.gezogen) { ev.preventDefault(); f.gezogen = false; }
    });
  }
  function fahrzeugBeschriften(f) {
    var ziehbar = !!(K.fahrzeugSetzen && f.d.setzbar);
    // ohne Zugang zum Fahrzeugmodul kommt keine Adresse mit – dann ist das Fahrzeug kein Verweis
    if (f.d.url) { f.el.href = f.d.url; } else { f.el.removeAttribute('href'); }
    f.el.title = f.d.name + ' · ' + f.d.stand + (ziehbar ? ' · ziehen setzt die Position von Hand' : '');
    f.el.classList.toggle('is-setzbar', ziehbar);
    f.el.classList.toggle('is-alt', !!f.d.alt);
    var name = f.el.querySelector('.lagekarte__fahrzeug-name');
    if (name && name.textContent !== f.d.kurz) { name.textContent = f.d.kurz; }
  }
  // Die neue Liste über die alte legen: Bekannte Fahrzeuge bleiben stehen und werden nur
  // angepasst – nichts flackert, kein Zeichen lädt neu. Wer sich bewegt hat, gleitet.
  function fahrzeugeZeigen(liste) {
    var nachId = {};
    fahrzeuge.forEach(function (f) { nachId[f.d.id] = f; });
    var jetzt = [];
    var bewegt = [];
    liste.forEach(function (fz) {
      var f = nachId[fz.id];
      if (f) {
        delete nachId[fz.id];
        // wird das Fahrzeug gerade gezogen, bleibt es unter dem Finger
        if (f.zieht) { fz.lat = f.d.lat; fz.lng = f.d.lng; }
        if (f.d.lat !== fz.lat || f.d.lng !== fz.lng) { bewegt.push(f); }
        if (f.d.src !== fz.src) { fahrzeugBild(f.el, fz); }
        f.d = fz;
      } else {
        f = fahrzeugNeu(fz);
      }
      fahrzeugBeschriften(f);
      jetzt.push(f);
    });
    // wer keine Position mehr meldet oder außer Dienst ist, verschwindet
    Object.keys(nachId).forEach(function (id) { markerEbene.removeChild(nachId[id].el); });
    fahrzeuge = jetzt;
    bewegt.forEach(function (f) { f.el.classList.add('is-gleitend'); });
    zeichnen();
    if (bewegt.length) {
      window.setTimeout(function () { bewegt.forEach(function (f) { f.el.classList.remove('is-gleitend'); }); }, 1300);
    }
  }
  function fahrzeugeHolen() {
    window.clearTimeout(fzWecker);
    // Im Hintergrund ruht der Abruf – beim Zurückkommen wird sofort geholt (siehe unten)
    if (!schalter.checked || document.hidden || fzLaeuft) { return; }
    fzLaeuft = true;
    window.fetch(K.api + (K.api.indexOf('?') === -1 ? '?' : '&') + 'action=fahrzeuge', { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!schalter.checked) { return; }
        if (!d || !d.ok) { fahrzeugeMelden((d && d.fehler) || 'Fahrzeuge nicht abrufbar.', true); return; }
        fzOhne = d.ohne || [];
        fahrzeugeZeigen(d.fahrzeuge);
        fahrzeugeMelden((d.fahrzeuge.length
          ? (d.fahrzeuge.length === 1 ? '1 Fahrzeug' : d.fahrzeuge.length + ' Fahrzeuge')
          : 'Kein Fahrzeug hat eine Position gemeldet') + ' · Stand ' + d.stand + ' · erneuert sich alle ' + fzSekunden + ' s', false);
      })
      .catch(function () {
        // die Fahrzeuge bleiben stehen, wo sie zuletzt waren
        fahrzeugeMelden('Fahrzeuge gerade nicht abrufbar – nächster Versuch in ' + fzSekunden + ' s. Bleibt das so, die Seite neu laden.', true);
      })
      .then(function () {
        fzLaeuft = false;
        window.clearTimeout(fzWecker);
        if (schalter.checked) { fzWecker = window.setTimeout(fahrzeugeHolen, fzSekunden * 1000); }
      });
  }
  function fahrzeugeSchalten() {
    window.clearTimeout(fzWecker);
    try { window.localStorage.setItem(merkName, schalter.checked ? '1' : '0'); } catch (e) { /* ohne Speicher eben nicht gemerkt */ }
    if (!schalter.checked) {
      fahrzeugeWeg();
      fahrzeugeMelden('', false);
      return;
    }
    fahrzeugeHolen();
  }
  /* ---------- Ein Fahrzeug ohne Position in die Kartenmitte setzen ---------- */
  function fahrzeugMenue(liste) {
    menueZu();
    tafelZu();
    var mitte = gradBei(flaeche.clientWidth / 2, flaeche.clientHeight / 2);
    menue = neu('div', 'lagekarte__menue');
    menue.setAttribute('role', 'menu');
    menue.addEventListener('pointerdown', function (ev) { ev.stopPropagation(); });
    menue.addEventListener('wheel', function (ev) { ev.stopPropagation(); });
    menue.addEventListener('contextmenu', function (ev) { ev.preventDefault(); ev.stopPropagation(); });
    menue.appendChild(neu('div', 'lagekarte__menue-kopf', 'Fahrzeug in die Kartenmitte setzen'));
    if (!liste.length) {
      menue.appendChild(neu('p', 'small muted', 'Jedes Fahrzeug im Dienst hat schon eine Position. Steht eines falsch und meldet sich nicht per GPS, lässt es sich auf der Karte ziehen.'));
    } else {
      var kasten = menue.appendChild(neu('div', 'lagekarte__menue-liste'));
      liste.forEach(function (fz) {
        var knopf = kasten.appendChild(neu('button', 'lagekarte__menue-eintrag'));
        knopf.type = 'button';
        knopf.setAttribute('role', 'menuitem');
        if (fz.src) {
          var bild = knopf.appendChild(neu('img'));
          bild.alt = '';
          bild.src = fz.src;
        }
        knopf.appendChild(neu('span', '', fz.kurz !== fz.name ? fz.kurz + ' – ' + fz.name : fz.name));
        knopf.addEventListener('click', function () {
          menueZu();
          senden({ action: 'fahrzeug_setzen', id: fz.id, lat: mitte.lat, lng: mitte.lng }, function (d) {
            if (!d) { return; }
            melden('„' + fz.name + '“ steht jetzt in der Kartenmitte – ziehen versetzt es.');
            if (schalter && !schalter.checked) { schalter.checked = true; fahrzeugeSchalten(); } else { fahrzeugeHolen(); }
          });
        });
      });
    }
    flaeche.appendChild(menue);
    menue.style.left = Math.max(4, Math.round((flaeche.clientWidth - menue.offsetWidth) / 2)) + 'px';
    menue.style.top = Math.max(4, Math.round((flaeche.clientHeight - menue.offsetHeight) / 2)) + 'px';
  }
  var fzSetzKnopf = wurzel.querySelector('[data-karte-fahrzeug-setzen]');
  if (fzSetzKnopf && K.fahrzeugSetzen) {
    fzSetzKnopf.addEventListener('click', function () {
      flaeche.scrollIntoView({ block: 'nearest' });
      // frisch fragen: die Liste soll stimmen, auch wenn die Fahrzeuge gerade ausgeblendet sind
      window.fetch(K.api + (K.api.indexOf('?') === -1 ? '?' : '&') + 'action=fahrzeuge', { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { fahrzeugMenue(d && d.ok ? (d.ohne || []) : fzOhne); })
        .catch(function () { fahrzeugMenue(fzOhne); });
    });
  }

  /* ---------- Die Lage im Takt holen: was andere Geräte setzen, ändern oder löschen, kommt hier an ---------- */
  var lageWecker = null;
  var lageLaeuft = false;
  var lageSekunden = Math.max(5, parseInt(K.lageSekunden, 10) || 15);
  var lageStandText = '';
  var lageSchlecht = false;
  var lageGeloescht = {};     // id → Zeitpunkt: hier gelöscht – eine ältere Antwort bringt sie nicht zurück
  function lageMelden(text, schlecht) {
    lageStandText = text;
    lageSchlecht = !!schlecht;
    var el = seite ? seite.querySelector('[data-karte-lage-stand]') : null;
    if (el) { el.textContent = text; el.classList.toggle('is-schlecht', lageSchlecht); }
  }
  function gleich(a, b) { return JSON.stringify(a) === JSON.stringify(b); }

  /* ---------- Plopp: ein kurzer Ton, wenn eine Einheit von anderswo auf der Karte erscheint ---------- */
  // Der Ton entsteht im Browser (WebAudio), es gibt keine Tondatei. Browser geben Ton erst nach einer
  // Geste auf der Seite frei – der Schalter „Ton“ ist eine; danach weckt jede Berührung den Ton neu auf.
  var tonSchalter = wurzel.querySelector('[data-karte-ton]');
  var tonMerk = 'ovb-karte-ton-' + K.id;
  var tonKontext = null;
  function tonAn() { return !!(tonSchalter && tonSchalter.checked); }
  function tonWecken() {
    var AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) { return null; }
    if (!tonKontext) {
      try { tonKontext = new AC(); } catch (e) { return null; }
    }
    if (tonKontext.state === 'suspended' && tonKontext.resume) { tonKontext.resume().catch(function () { /* noch keine Geste */ }); }
    return tonKontext;
  }
  function plopp() {
    if (!tonAn()) { return; }
    var ac = tonWecken();
    if (!ac || ac.state !== 'running') { return; }
    var t = ac.currentTime;
    var ton = ac.createOscillator();
    var laut = ac.createGain();
    ton.type = 'sine';
    ton.frequency.setValueAtTime(900, t);
    ton.frequency.exponentialRampToValueAtTime(260, t + 0.12);
    laut.gain.setValueAtTime(0.0001, t);
    laut.gain.exponentialRampToValueAtTime(0.3, t + 0.012);
    laut.gain.exponentialRampToValueAtTime(0.0001, t + 0.2);
    ton.connect(laut);
    laut.connect(ac.destination);
    ton.start(t);
    ton.stop(t + 0.22);
  }
  ['pointerdown', 'keydown'].forEach(function (art) {
    document.addEventListener(art, function () { if (tonAn()) { tonWecken(); } }, { passive: true });
  });
  if (tonSchalter) {
    var tonGemerkt = null;
    try { tonGemerkt = window.localStorage.getItem(tonMerk); } catch (e) { tonGemerkt = null; }
    tonSchalter.checked = tonGemerkt !== '0';
    tonSchalter.addEventListener('change', function () {
      try { window.localStorage.setItem(tonMerk, tonSchalter.checked ? '1' : '0'); } catch (e) { /* dann eben nicht gemerkt */ }
      if (tonSchalter.checked) {
        tonWecken();
        window.setTimeout(plopp, 150);   // Probe – und der Kontext ist nach der Geste wach
      }
    });
  }
  // Die Antwort über den eigenen Stand legen. Was hier gerade in Arbeit ist – gezogen, in der Tafel,
  // noch nicht gespeichert, eben erst gesetzt oder gelöscht –, bleibt, wie es ist; der nächste Abruf gleicht an.
  function lageUebernehmen(d, angefragt) {
    var geaendert = false;
    var gleiten = [];   // anderswo versetzt: gleitet an die neue Stelle
    var neue = [];      // neu auf der Karte: ploppt auf
    var neueAbschnitte = d.abschnitte || [];
    if (!gleich(neueAbschnitte, abschnitte)) {
      abschnitte.length = 0;
      neueAbschnitte.forEach(function (a) { abschnitte.push(a); });
      geaendert = true;
    }
    var nachId = {};
    marker.forEach(function (m) { nachId[m.d.id] = m; });
    (d.marker || []).forEach(function (fremd) {
      var m = nachId[fremd.id];
      if (m) {
        delete nachId[fremd.id];
        if (m === gewaehlt || m.zieht || (offen && offen.m === m)) { return; }
        if (!gleich(m.d, fremd)) {
          var warDa = !ohneOrt(m);
          var istDa = fremd.lat !== null && fremd.lat !== undefined;
          if (warDa && istDa && (m.d.lat !== fremd.lat || m.d.lng !== fremd.lng)) { gleiten.push(m); }
          else if (!warDa && istDa) { neue.push(m); }
          m.d = fremd;
          markerAussehen(m);
          geaendert = true;
        }
      } else if (!lageGeloescht[fremd.id] || lageGeloescht[fremd.id] < angefragt - 60 * 1000) {
        var dazu = markerDazu(fremd);
        if (!ohneOrt(dazu)) { neue.push(dazu); }
        geaendert = true;
      }
    });
    Object.keys(nachId).forEach(function (id) {
      var m = nachId[id];
      if (m.seit > angefragt) { return; }   // hier eben erst gesetzt, die Antwort ist älter
      if (m === gewaehlt) { tafelZu(); melden('„' + einheitName(m) + '“ wurde an einem anderen Gerät gelöscht.'); }
      markerEbene.removeChild(m.el);
      marker.splice(marker.indexOf(m), 1);
      geaendert = true;
    });
    if (geaendert) {
      // die Klasse vor dem Zeichnen: dann gleitet die Einheit von der alten zur neuen Stelle
      gleiten.forEach(function (m) { m.el.classList.add('is-gleitend'); });
      neue.forEach(function (m) { m.el.classList.add('is-neu'); });
      zeichnen();
      seiteZeichnen();
      if (gleiten.length) {
        window.setTimeout(function () { gleiten.forEach(function (m) { m.el.classList.remove('is-gleitend'); }); }, 1100);
      }
      if (neue.length) {
        plopp();
        window.setTimeout(function () { neue.forEach(function (m) { m.el.classList.remove('is-neu'); }); }, 700);
      }
    }
  }
  function lageHolen() {
    window.clearTimeout(lageWecker);
    // Im Hintergrund ruht der Abruf – beim Zurückkommen wird sofort geholt (siehe unten)
    if (document.hidden || lageLaeuft) { return; }
    lageLaeuft = true;
    var angefragt = Date.now();
    window.fetch(K.api + (K.api.indexOf('?') === -1 ? '?' : '&') + 'action=lage&karte_id=' + encodeURIComponent(K.id), { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) { lageMelden((d && d.fehler) || 'Lage nicht abrufbar.', true); return; }
        lageUebernehmen(d, angefragt);
        lageMelden('Stand ' + d.stand + ' · erneuert sich alle ' + lageSekunden + ' s', false);
      })
      .catch(function () {
        // die Einheiten bleiben stehen, wo sie zuletzt waren
        lageMelden('Lage gerade nicht abrufbar – nächster Versuch in ' + lageSekunden + ' s.', true);
      })
      .then(function () {
        lageLaeuft = false;
        window.clearTimeout(lageWecker);
        lageWecker = window.setTimeout(lageHolen, lageSekunden * 1000);
      });
  }

  // Zurück auf der Seite oder wieder im Netz: nicht auf den Takt warten
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) { return; }
    lageHolen();
    if (schalter && K.fahrzeuge && schalter.checked) { fahrzeugeHolen(); }
  });
  window.addEventListener('online', function () {
    lageHolen();
    if (schalter && K.fahrzeuge && schalter.checked) { fahrzeugeHolen(); }
  });
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
  seiteZeichnen();
  lageWecker = window.setTimeout(lageHolen, lageSekunden * 1000);
  window.addEventListener('resize', zeichnen);
  wurzel.classList.add('is-bereit');
  jetztZeichnen();
})();
