/* OV-Multitool – die Lage einer Karte als PDF in einen Einsatz speichern.
 *
 * Nimmt, was gerade auf der Karte zu sehen ist (Kacheln, Einheiten, Fahrzeuge), holt Stärke,
 * Einsatzzeiten und Protokoll aus der Anwendung und setzt daraus mit jsPDF (MIT,
 * assets/vendor/jspdf) ein PDF. Das PDF geht an die Anwendung, die es am gewählten Einsatz
 * ablegt – auf dem Server, in der Dateiablage unter Einsätze.
 */
(function () {
  'use strict';

  var wurzel = document.querySelector('[data-lagekarte]');
  var knopf = document.querySelector('[data-karte-einsatz-speichern]');
  if (!wurzel || !knopf) { return; }
  var K;
  try { K = JSON.parse(wurzel.getAttribute('data-lagekarte')); } catch (e) { return; }
  var wahl = document.querySelector('[data-karte-einsatz-wahl]');
  var ausgabe = document.querySelector('[data-karte-einsatz-meldung]');
  var flaeche = wurzel.querySelector('[data-karte-flaeche]');

  function melden(text, art, verweis) {
    if (!ausgabe) { return; }
    ausgabe.hidden = !text;
    ausgabe.className = 'alert ' + (art === 'gut' ? 'alert--success' : art === 'info' ? 'alert--info' : 'alert--error');
    ausgabe.textContent = text || '';
    if (verweis) {
      var a = document.createElement('a');
      a.href = verweis.href;
      a.textContent = verweis.text;
      ausgabe.appendChild(document.createTextNode(' '));
      ausgabe.appendChild(a);
    }
  }

  /* Die Standardschriften der PDF kennen nur westeuropäische Zeichen – alles andere wird ersetzt */
  function rein(text) {
    return String(text === null || text === undefined ? '' : text)
      .replace(/[→➔]/g, '->').replace(/″/g, '"')
      .replace(/[^\x09\x0A\x20-\x7E -ÿ–—‘’‚“”„•…€]/g, '?');
  }

  /* ---------- Die Karte, wie sie gerade zu sehen ist, auf eine Zeichenfläche bringen ---------- */
  function rechteck(el, bezug) {
    var r = el.getBoundingClientRect();
    return { x: r.left - bezug.left, y: r.top - bezug.top, b: r.width, h: r.height };
  }
  function ladeKachel(adresse) {
    return new Promise(function (fertig) {
      var bild = new Image();
      // Mit Herkunftsfreigabe laden: nur dann darf der Browser die Zeichenfläche wieder auslesen
      bild.crossOrigin = 'anonymous';
      bild.referrerPolicy = 'strict-origin-when-cross-origin';
      var uhr = window.setTimeout(function () { fertig(null); }, 8000);
      bild.onload = function () { window.clearTimeout(uhr); fertig(bild); };
      bild.onerror = function () { window.clearTimeout(uhr); fertig(null); };
      bild.src = adresse;
    });
  }
  function schild(ctx, text, mitteX, oben) {
    if (!text) { return; }
    ctx.font = '600 12px system-ui, -apple-system, "Segoe UI", sans-serif';
    var b = ctx.measureText(text).width + 8;
    ctx.fillStyle = 'rgba(255,255,255,.9)';
    ctx.fillRect(mitteX - b / 2, oben, b, 16);
    ctx.fillStyle = '#111827';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, mitteX, oben + 8.5);
  }
  function karteZeichnen() {
    var bezug = flaeche.getBoundingClientRect();
    var breite = Math.round(bezug.width);
    var hoehe = Math.round(bezug.height);
    var faktor = 2;   // doppelte Auflösung, damit das Bild im PDF nicht matschig wird
    var flaecheNeu = document.createElement('canvas');
    flaecheNeu.width = breite * faktor;
    flaecheNeu.height = hoehe * faktor;
    var ctx = flaecheNeu.getContext('2d');
    ctx.scale(faktor, faktor);
    ctx.fillStyle = '#e5e7eb';
    ctx.fillRect(0, 0, breite, hoehe);

    var kacheln = Array.prototype.slice.call(flaeche.querySelectorAll('.lagekarte__kachel'));
    return Promise.all(kacheln.map(function (k) {
      var wo = rechteck(k, bezug);
      return ladeKachel(k.currentSrc || k.src).then(function (bild) { return { bild: bild, wo: wo }; });
    })).then(function (geladen) {
      var fehlend = 0;
      geladen.forEach(function (g) {
        if (g.bild) { ctx.drawImage(g.bild, g.wo.x, g.wo.y, g.wo.b, g.wo.h); } else { fehlend++; }
      });
      // Einheiten: das Symbol in seiner Drehung, darunter Beschriftung und Stärke
      flaeche.querySelectorAll('.lagekarte__marker').forEach(function (m) {
        if (m.hidden) { return; }
        var bild = m.querySelector('img');
        if (!bild || !bild.complete || !bild.naturalWidth && !bild.offsetWidth) { return; }
        var b = bild.offsetWidth;
        var h = bild.offsetHeight || b;
        var wo = rechteck(bild, bezug);
        var mx = wo.x + wo.b / 2;
        var my = wo.y + wo.h / 2;
        var grad = /rotate\((-?[\d.]+)deg\)/.exec(bild.style.transform || '');
        ctx.save();
        ctx.translate(mx, my);
        if (grad) { ctx.rotate(parseFloat(grad[1]) * Math.PI / 180); }
        try { ctx.drawImage(bild, -b / 2, -h / 2, b, h); } catch (e) { /* ein kaputtes Symbol hält die Karte nicht auf */ }
        ctx.restore();
        var text = m.querySelector('.lagekarte__beschriftung');
        if (text && !text.hidden) { schild(ctx, text.textContent, mx, my + h / 2 + 2); }
      });
      // Fahrzeuge: taktisches Zeichen oder Punkt, darunter der Name
      flaeche.querySelectorAll('.lagekarte__fahrzeug').forEach(function (f) {
        var zeichen = f.querySelector('.lagekarte__fahrzeug-zeichen');
        var punkt = f.querySelector('.lagekarte__fahrzeug-punkt');
        var el = zeichen || punkt;
        if (!el) { return; }
        var wo = rechteck(el, bezug);
        if (zeichen && zeichen.complete && zeichen.naturalWidth) {
          try { ctx.drawImage(zeichen, wo.x, wo.y, wo.b, wo.h); } catch (e) { /* weiter */ }
        } else {
          ctx.beginPath();
          ctx.arc(wo.x + wo.b / 2, wo.y + wo.h / 2, 8, 0, Math.PI * 2);
          ctx.fillStyle = '#003399';
          ctx.fill();
          ctx.lineWidth = 3;
          ctx.strokeStyle = '#ffffff';
          ctx.stroke();
        }
        var name = f.querySelector('.lagekarte__fahrzeug-name');
        if (name) { schild(ctx, name.textContent, wo.x + wo.b / 2, wo.y + wo.h + 2); }
      });
      var daten = null;
      try { daten = flaecheNeu.toDataURL('image/jpeg', 0.88); } catch (e) { daten = null; }   // Kachelserver ohne Herkunftsfreigabe
      return { daten: daten, breite: breite, hoehe: hoehe, fehlend: fehlend, kacheln: kacheln.length };
    });
  }

  /* ---------- Das PDF ---------- */
  var RAND = 15;
  var BREITE = 210 - 2 * RAND;
  function neuesBlatt(pdf, zustand) {
    pdf.addPage('a4', 'p');
    zustand.y = RAND;
  }
  function platz(pdf, zustand, hoehe) {
    if (zustand.y + hoehe > 297 - RAND - 6) { neuesBlatt(pdf, zustand); return true; }
    return false;
  }
  function ueberschrift(pdf, zustand, text) {
    platz(pdf, zustand, 14);
    zustand.y += 4;
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(12);
    pdf.setTextColor(0, 51, 153);
    pdf.text(rein(text), RAND, zustand.y);
    pdf.setTextColor(0, 0, 0);
    zustand.y += 5;
  }
  function absatz(pdf, zustand, text, groesse) {
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(groesse || 9);
    pdf.splitTextToSize(rein(text), BREITE).forEach(function (zeile) {
      platz(pdf, zustand, 4.5);
      pdf.text(zeile, RAND, zustand.y);
      zustand.y += 4.2;
    });
  }
  /* Eine einfache Tabelle: spalten = [{titel, breite (Anteil), rechts}], zeilen = [[Text, …]] */
  function tabelle(pdf, zustand, spalten, zeilen) {
    var summe = spalten.reduce(function (s, sp) { return s + sp.breite; }, 0);
    var x = [RAND];
    spalten.forEach(function (sp, i) { x[i + 1] = x[i] + BREITE * sp.breite / summe; });
    function kopf() {
      pdf.setFillColor(238, 242, 247);
      pdf.rect(RAND, zustand.y - 3.6, BREITE, 5.2, 'F');
      pdf.setFont('helvetica', 'bold');
      pdf.setFontSize(8);
      spalten.forEach(function (sp, i) {
        pdf.text(rein(sp.titel), sp.rechts ? x[i + 1] - 1.2 : x[i] + 1.2, zustand.y, { align: sp.rechts ? 'right' : 'left' });
      });
      zustand.y += 4.6;
    }
    platz(pdf, zustand, 12);
    kopf();
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(8);
    zeilen.forEach(function (zeile) {
      var teile = zeile.map(function (text, i) { return pdf.splitTextToSize(rein(text), x[i + 1] - x[i] - 2.4); });
      var hoehe = Math.max.apply(null, teile.map(function (t) { return t.length; })) * 3.5 + 1.2;
      if (platz(pdf, zustand, hoehe)) { kopf(); pdf.setFont('helvetica', 'normal'); pdf.setFontSize(8); }
      teile.forEach(function (t, i) {
        t.forEach(function (stueck, n) {
          pdf.text(stueck, spalten[i].rechts ? x[i + 1] - 1.2 : x[i] + 1.2, zustand.y + n * 3.5, { align: spalten[i].rechts ? 'right' : 'left' });
        });
      });
      zustand.y += hoehe;
      pdf.setDrawColor(215, 220, 228);
      pdf.setLineWidth(0.1);
      pdf.line(RAND, zustand.y - 3.2, RAND + BREITE, zustand.y - 3.2);
    });
    zustand.y += 2;
  }

  function pdfBauen(lage, bild, einsatzName) {
    var pdf = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4', compress: true });
    var zustand = { y: RAND };
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(16);
    pdf.text(rein('Lagekarte ' + (lage.karte || K.name || '')), RAND, zustand.y + 2);
    zustand.y += 8;
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(10);
    pdf.text(rein('Stand ' + lage.stand + (einsatzName ? '  ·  Einsatz ' + einsatzName : '')), RAND, zustand.y);
    zustand.y += 5;

    if (bild && bild.daten) {
      var hoehe = Math.min(150, BREITE * bild.hoehe / bild.breite);
      var breite = hoehe * bild.breite / bild.hoehe;
      pdf.addImage(bild.daten, 'JPEG', RAND, zustand.y, breite, hoehe);
      pdf.setDrawColor(150, 150, 150);
      pdf.setLineWidth(0.2);
      pdf.rect(RAND, zustand.y, breite, hoehe);
      zustand.y += hoehe + 3.5;
      pdf.setFontSize(7);
      pdf.setTextColor(110, 110, 110);
      pdf.text(rein('Karte: ' + (K.quelle || '')), RAND, zustand.y);
      pdf.setTextColor(0, 0, 0);
      zustand.y += 3;
    } else {
      absatz(pdf, zustand, 'Das Kartenbild ließ sich nicht übernehmen (der Kachelserver gibt seine Bilder nicht zum Auslesen frei). Stärke, Einsatzzeiten und Protokoll stehen unten.', 9);
      zustand.y += 2;
    }

    // Stärke nach Abschnitten
    var marker = lage.marker || [];
    var abschnitte = (lage.abschnitte || []).concat([{ id: null, name: 'Ohne Abschnitt' }]);
    ueberschrift(pdf, zustand, 'Stärke  ' + ((lage.staerke && lage.staerke.text) || '0/0/0/0') + '   (Führer / Unterführer / Helfer / Gesamt)');
    var zeilen = [];
    abschnitte.forEach(function (a) {
      marker.filter(function (m) { return (m.abschnitt || null) === (a.id || null); }).forEach(function (m) {
        zeilen.push([a.name, m.text || m.name || 'Einheit', m.staerke && m.staerke.gesamt > 0 ? m.staerke.text : '–', m.lat === null ? 'nur in der Liste' : m.lat.toFixed(5) + ', ' + m.lng.toFixed(5)]);
      });
    });
    if (zeilen.length) {
      tabelle(pdf, zustand, [{ titel: 'Abschnitt', breite: 3 }, { titel: 'Einheit', breite: 4 }, { titel: 'Stärke', breite: 2 }, { titel: 'Stelle', breite: 3 }], zeilen);
    } else {
      absatz(pdf, zustand, 'Keine Einheiten auf dieser Karte.');
    }

    ueberschrift(pdf, zustand, 'Einsatzzeiten');
    if ((lage.zeiten || []).length) {
      tabelle(pdf, zustand, [{ titel: 'Einheit / Fahrzeug', breite: 4 }, { titel: 'Art', breite: 1.6 }, { titel: 'seit', breite: 2.4 }, { titel: 'bis', breite: 2.4 },
        { titel: 'Dauer', breite: 1.8, rechts: true }, { titel: 'bewegt', breite: 1.6, rechts: true }],
        lage.zeiten.map(function (z) { return [z.name, z.art_text, z.seit_text, z.bis_text, z.dauer_text, z.strecke_text || '–']; }));
    } else {
      absatz(pdf, zustand, 'Noch nichts protokolliert.');
    }

    ueberschrift(pdf, zustand, 'Protokoll');
    if ((lage.protokoll || []).length) {
      tabelle(pdf, zustand, [{ titel: 'Zeit', breite: 2.3 }, { titel: 'Einheit / Fahrzeug', breite: 3.4 }, { titel: 'Was', breite: 2.8 }, { titel: 'von', breite: 2.4 }, { titel: 'nach', breite: 2.4 }, { titel: 'Wer', breite: 2 }],
        lage.protokoll.map(function (p) { return [p.zeit, p.name, p.was, p.von || '–', p.nach || '–', p.wer || '–']; }));
    } else {
      absatz(pdf, zustand, 'Noch nichts protokolliert.');
    }

    var seiten = pdf.getNumberOfPages();
    for (var s = 1; s <= seiten; s++) {
      pdf.setPage(s);
      pdf.setFont('helvetica', 'normal');
      pdf.setFontSize(7);
      pdf.setTextColor(110, 110, 110);
      pdf.text(rein('Lagekarte ' + (lage.karte || '') + ' · Stand ' + lage.stand), RAND, 297 - 8);
      pdf.text('Seite ' + s + ' von ' + seiten, RAND + BREITE, 297 - 8, { align: 'right' });
    }
    pdf.setProperties({ title: 'Lagekarte ' + (lage.karte || ''), creator: 'OV-Multitool' });
    return pdf;
  }

  knopf.addEventListener('click', function () {
    var einsatzId = wahl ? parseInt(wahl.value, 10) || 0 : (K.einsatzId || 0);
    if (!einsatzId) {
      melden('Bitte zuerst den Einsatz wählen, in den die Lage gespeichert werden soll.');
      if (wahl) { wahl.focus(); }
      return;
    }
    if (!window.jspdf || !window.jspdf.jsPDF) {
      melden('Die Bibliothek für das PDF konnte nicht geladen werden – bitte die Seite neu laden.');
      return;
    }
    var einsatzName = wahl && wahl.selectedIndex >= 0 ? wahl.options[wahl.selectedIndex].text.trim() : '';
    knopf.disabled = true;
    melden('Die Lage wird als PDF gesetzt …', 'info');
    var adresse = K.api + (K.api.indexOf('?') === -1 ? '?' : '&') + 'action=protokoll&karte_id=' + encodeURIComponent(K.id);
    Promise.all([
      window.fetch(adresse, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }),
      karteZeichnen()
    ]).then(function (teile) {
      var lage = teile[0];
      if (!lage || !lage.ok) { throw new Error((lage && lage.fehler) || 'Die Lage ließ sich nicht abrufen.'); }
      var pdf = pdfBauen(lage, teile[1], einsatzName);
      var fd = new FormData();
      fd.append('_csrf', K.csrf);
      fd.append('action', 'pdf');
      fd.append('herkunft', 'karte');
      fd.append('einsatz_id', einsatzId);
      fd.append('karte_id', K.id);
      fd.append('pdf[]', pdf.output('blob'), 'Lagekarte-' + String(K.name || K.id).replace(/[^0-9A-Za-zÄÖÜäöüß_-]+/g, '-').replace(/^-+|-+$/g, '') + '.pdf');
      return window.fetch(K.ablage, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json().then(function (d) { if (!r.ok || !d || !d.ok) { throw new Error((d && d.fehler) || 'Das hat nicht geklappt.'); } return d; }); })
        .then(function (d) {
          melden('„' + d.titel + '" ist im Einsatz gespeichert' + (teile[1].daten ? '.' : ' – ohne Kartenbild, der Kachelserver gibt es nicht frei.'), 'gut',
            { href: d.einsatz, text: 'Zum Einsatz' });
        });
    }).catch(function (ex) {
      melden(ex && ex.message ? ex.message : 'Keine Verbindung – nicht gespeichert.');
    }).then(function () { knopf.disabled = false; });
  });
})();
