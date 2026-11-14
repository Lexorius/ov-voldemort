/* OV-Multitool – Maske für den Einsatzauftrag (Fb Fü 3).
 *
 * Liest die Felder, setzt den Vordruck mit der Bibliothek einsatzauftrag (EUPL-1.2,
 * assets/vendor/einsatzauftrag) über jsPDF (MIT, assets/vendor/jspdf) und zeigt ihn als
 * Vorschau. „Speichern" schickt das PDF samt den Feldern an die Anwendung, die es am
 * Einsatz oder an der Veranstaltung ablegt. Beide Bibliotheken werden nur über ihre öffentliche Schnittstelle benutzt.
 */
(function () {
  'use strict';

  var wurzel = document.querySelector('[data-einsatzauftrag]');
  if (!wurzel) { return; }
  var K;
  try { K = JSON.parse(wurzel.getAttribute('data-einsatzauftrag')); } catch (e) { return; }
  var meldung = document.querySelector('[data-ea-meldung]');
  var rahmen = wurzel.querySelector('[data-ea-vorschau]');
  var stand = wurzel.querySelector('[data-ea-stand]');

  function melden(text, gut) {
    if (!meldung) { return; }
    meldung.textContent = text || '';
    meldung.hidden = !text;
    meldung.className = 'alert ' + (gut ? 'alert--success' : 'alert--error');
  }
  if (!window.jspdf || !window.jspdf.jsPDF || !window.Einsatzauftrag) {
    melden('Die Bibliotheken für das PDF konnten nicht geladen werden – bitte die Seite neu laden.');
    return;
  }

  /* Die Felder der Maske, wie die Bibliothek sie erwartet */
  function lesen() {
    var d = {};
    wurzel.querySelectorAll('[data-ea]').forEach(function (f) {
      var wert = f.value.replace(/\r\n?/g, '\n');
      if (wert.trim() !== '') { d[f.getAttribute('data-ea')] = wert; }
    });
    wurzel.querySelectorAll('[data-ea-punkt]').forEach(function (f) {
      var wert = f.value.replace(/\r\n?/g, '\n');
      if (wert.trim() !== '') {
        d.unterpunkte = d.unterpunkte || {};
        d.unterpunkte[f.getAttribute('data-ea-punkt')] = wert;
      }
    });
    var uebung = wurzel.querySelector('[data-ea-uebung]');
    if (uebung && uebung.checked) { d.uebung = true; }
    return d;
  }

  function setzen(daten) {
    var pdf = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4', compress: true });
    var satz = window.Einsatzauftrag.zeichneEinsatzauftrag(pdf, daten, K.fusszeile ? { fusszeile: K.fusszeile } : {});
    pdf.setProperties({ title: 'Einsatzauftrag' + (daten.laufendeNummer ? ' Nr. ' + daten.laufendeNummer : ''), subject: daten.einsatz || '', creator: 'OV-Multitool' });
    return { pdf: pdf, satz: satz };
  }

  function dateiname(daten) {
    var nr = (daten.laufendeNummer || '').replace(/[^0-9A-Za-z_-]+/g, '');
    return 'Einsatzauftrag' + (nr ? '-' + nr : '') + '.pdf';
  }

  /* Vorschau: kurz nach der letzten Eingabe neu setzen */
  var uhr = null;
  var letzteAdresse = null;
  function vorschau() {
    try {
      var e = setzen(lesen());
      var adresse = URL.createObjectURL(e.pdf.output('blob'));
      if (rahmen) { rahmen.src = adresse + '#toolbar=0&view=FitH'; }
      if (letzteAdresse) { URL.revokeObjectURL(letzteAdresse); }
      letzteAdresse = adresse;
      if (stand) { stand.textContent = (e.satz.seiten.length === 1 ? '1 Seite' : e.satz.seiten.length + ' Seiten') + ' · Schrift ' + String(e.satz.schriftgroesse).replace('.', ',') + ' pt'; }
    } catch (ex) {
      melden('Der Vordruck ließ sich nicht setzen: ' + ex.message);
    }
  }
  // Die Eingabetaste in einem Feld schickt nichts ab – gespeichert wird nur über den Knopf
  var formular = wurzel.querySelector('[data-ea-form]');
  if (formular) { formular.addEventListener('submit', function (ev) { ev.preventDefault(); }); }

  wurzel.addEventListener('input', function () {
    window.clearTimeout(uhr);
    uhr = window.setTimeout(vorschau, 500);
  });
  wurzel.addEventListener('change', function () { window.clearTimeout(uhr); vorschau(); });
  vorschau();

  var laden = wurzel.querySelector('[data-ea-laden]');
  if (laden) {
    laden.addEventListener('click', function () {
      var daten = lesen();
      setzen(daten).pdf.save(dateiname(daten));
    });
  }

  var speichern = wurzel.querySelector('[data-ea-speichern]');
  if (speichern) {
    speichern.addEventListener('click', function () {
      var daten = lesen();
      if (!daten.empfaenger) {
        melden('Bitte einen Empfänger eintragen – unter ihm steht der Auftrag später am Einsatz.');
        var feld = wurzel.querySelector('[data-ea="empfaenger"]');
        if (feld) { feld.focus(); }
        return;
      }
      var fd = new FormData();
      fd.append('_csrf', K.csrf);
      // Wohin gespeichert wird (Einsatz oder Veranstaltung), sagt die Seite: action, Kennung, Herkunft
      Object.keys(K.felder || {}).forEach(function (name) { fd.append(name, K.felder[name]); });
      fd.append('daten', JSON.stringify(daten));
      fd.append('pdf[]', setzen(daten).pdf.output('blob'), dateiname(daten));
      speichern.disabled = true;
      melden('');
      window.fetch(K.ablage, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json().then(function (d) { return r.ok && d && d.ok ? d : { fehler: (d && d.fehler) || 'Das hat nicht geklappt.' }; }); })
        .catch(function () { return { fehler: 'Keine Verbindung – nicht gespeichert.' }; })
        .then(function (d) {
          if (d.fehler) {
            speichern.disabled = false;
            melden(d.fehler);
            return;
          }
          melden('„' + d.titel + '" ist gespeichert.', true);
          window.location.href = K.zurueck;
        });
    });
  }
})();
