/*
 * Gestalter für Abstimmungen: Seiten und Fragen zusammenklicken.
 *
 * Die Wahrheit liegt im Textfeld #seiten_json – der Gestalter liest es
 * beim Start, baut daraus seine Oberfläche und schreibt bei jeder Änderung
 * zurück. Abgeschickt wird nur das JSON; ohne JavaScript bleibt das Feld
 * sichtbar und lässt sich von Hand bearbeiten.
 */
(function () {
  var wurzel = document.querySelector('[data-umfrage-editor]');
  var feld = document.getElementById('seiten_json');
  if (!wurzel || !feld) { return; }

  var TYPEN = {};
  try { TYPEN = JSON.parse(wurzel.getAttribute('data-typen') || '{}'); } catch (e) { TYPEN = {}; }

  var seiten;
  try { seiten = JSON.parse(feld.value || '[]'); } catch (e) { seiten = []; }
  if (!Array.isArray(seiten) || !seiten.length) { seiten = [{ titel: '', text: '', felder: [] }]; }
  seiten.forEach(function (s) { if (!Array.isArray(s.felder)) { s.felder = []; } });

  // Das Feld bleibt im Formular, aber unsichtbar – der Gestalter füllt es
  var jsonBlock = feld.closest('.field') || feld;
  jsonBlock.hidden = true;

  var naechsteId = 1;
  seiten.forEach(function (s) {
    s.felder.forEach(function (f) {
      var m = /^f(\d+)$/.exec(f.id || '');
      if (m) { naechsteId = Math.max(naechsteId, parseInt(m[1], 10) + 1); }
    });
  });

  function speichern() {
    feld.value = JSON.stringify(seiten);
  }

  function el(tag, attrs, kinder) {
    var e = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'text') { e.textContent = attrs[k]; }
      else if (k === 'html') { e.innerHTML = attrs[k]; }
      else if (k.indexOf('on') === 0) { e.addEventListener(k.slice(2), attrs[k]); }
      else if (attrs[k] === true) { e.setAttribute(k, ''); }
      else if (attrs[k] !== false && attrs[k] !== null && attrs[k] !== undefined) { e.setAttribute(k, attrs[k]); }
    });
    (kinder || []).forEach(function (k) { if (k) { e.appendChild(typeof k === 'string' ? document.createTextNode(k) : k); } });
    return e;
  }

  function knopf(text, titel, klick) {
    return el('button', { type: 'button', 'class': 'btn btn--sec btn--sm', title: titel, text: text, onclick: klick });
  }

  function neuesFeld(typ) {
    var f = { id: 'f' + (naechsteId++), typ: typ, frage: '', pflicht: typ === 'hinweis' ? 0 : 1 };
    if (typ === 'einfach' || typ === 'mehrfach') { f.optionen = ['', '']; }
    if (typ === 'skala') { f.min = 1; f.max = 5; f.links = ''; f.rechts = ''; }
    if (typ === 'text') { f.lang = 0; }
    return f;
  }

  function verschieben(liste, von, nach) {
    if (nach < 0 || nach >= liste.length) { return; }
    var e = liste.splice(von, 1)[0];
    liste.splice(nach, 0, e);
    render();
  }

  function textfeld(wert, attrs, aenderung) {
    var a = Object.assign({ type: 'text', value: wert || '' }, attrs || {});
    a.oninput = function (ev) { aenderung(ev.target.value); speichern(); };
    return el('input', a);
  }

  function frageKarte(f, s, fi) {
    var kopf = el('div', { 'class': 'umfrage-frage__kopf' }, [
      el('span', { 'class': 'badge badge--outline', text: TYPEN[f.typ] || f.typ }),
      el('span', { 'class': 'btnrow' }, [
        knopf('↑', 'nach oben', function () { verschieben(s.felder, fi, fi - 1); }),
        knopf('↓', 'nach unten', function () { verschieben(s.felder, fi, fi + 1); }),
        knopf('✕', 'Frage entfernen', function () { s.felder.splice(fi, 1); render(); })
      ])
    ]);
    var kinder = [kopf];

    kinder.push(el('div', { 'class': 'field' }, [
      el('label', { text: f.typ === 'hinweis' ? 'Hinweistext' : 'Frage' }),
      f.typ === 'hinweis'
        ? el('textarea', { rows: 2, maxlength: 500, text: f.frage || '', oninput: function (ev) { f.frage = ev.target.value; speichern(); } })
        : textfeld(f.frage, { maxlength: 500, placeholder: 'z. B. Welcher Termin passt dir?' }, function (v) { f.frage = v; })
    ]));

    if (f.typ === 'einfach' || f.typ === 'mehrfach') {
      kinder.push(el('div', { 'class': 'field' }, [
        el('label', { text: 'Antwortmöglichkeiten – eine je Zeile' }),
        el('textarea', { rows: Math.max(3, (f.optionen || []).length + 1), text: (f.optionen || []).join('\n'),
          oninput: function (ev) { f.optionen = ev.target.value.split('\n'); speichern(); } })
      ]));
    }
    if (f.typ === 'skala') {
      kinder.push(el('div', { 'class': 'grid2' }, [
        el('div', { 'class': 'field' }, [el('label', { text: 'Von' }),
          textfeld(String(f.min), { type: 'number', min: 0, max: 9 }, function (v) { f.min = parseInt(v, 10) || 0; })]),
        el('div', { 'class': 'field' }, [el('label', { text: 'Bis' }),
          textfeld(String(f.max), { type: 'number', min: 1, max: 10 }, function (v) { f.max = parseInt(v, 10) || 0; })]),
        el('div', { 'class': 'field' }, [el('label', { text: 'Beschriftung links' }),
          textfeld(f.links, { maxlength: 60, placeholder: 'z. B. gar nicht' }, function (v) { f.links = v; })]),
        el('div', { 'class': 'field' }, [el('label', { text: 'Beschriftung rechts' }),
          textfeld(f.rechts, { maxlength: 60, placeholder: 'z. B. sehr gern' }, function (v) { f.rechts = v; })])
      ]));
    }
    var schalter = [];
    if (f.typ === 'text') {
      schalter.push(el('label', { 'class': 'small' }, [
        el('input', { type: 'checkbox', checked: !!f.lang, onchange: function (ev) { f.lang = ev.target.checked ? 1 : 0; speichern(); } }),
        ' langer Text (mehrere Zeilen)'
      ]));
    }
    if (f.typ !== 'hinweis') {
      schalter.push(el('label', { 'class': 'small' }, [
        el('input', { type: 'checkbox', checked: !!f.pflicht, onchange: function (ev) { f.pflicht = ev.target.checked ? 1 : 0; speichern(); } }),
        ' Pflichtfrage'
      ]));
    }
    if (schalter.length) { kinder.push(el('div', { 'class': 'umfrage-frage__schalter' }, schalter)); }
    return el('div', { 'class': 'umfrage-frage' }, kinder);
  }

  function seiteKarte(s, si) {
    var auswahl = el('select', { 'aria-label': 'Fragetyp' }, Object.keys(TYPEN).map(function (k) {
      return el('option', { value: k, text: TYPEN[k] });
    }));
    var kopf = el('div', { 'class': 'card__head' }, [
      el('h3', { text: 'Seite ' + (si + 1) + ' von ' + seiten.length }),
      el('div', { 'class': 'btnrow' }, [
        knopf('↑', 'Seite nach oben', function () { verschieben(seiten, si, si - 1); }),
        knopf('↓', 'Seite nach unten', function () { verschieben(seiten, si, si + 1); }),
        knopf('Seite entfernen', 'Seite samt Fragen entfernen', function () {
          if (seiten.length === 1) { s.felder = []; s.titel = ''; s.text = ''; } else { seiten.splice(si, 1); }
          render();
        })
      ])
    ]);
    var felder = s.felder.map(function (f, fi) { return frageKarte(f, s, fi); });
    var leer = s.felder.length ? null : el('div', { 'class': 'empty small', text: 'Noch keine Frage auf dieser Seite.' });
    return el('section', { 'class': 'card umfrage-seite' }, [
      kopf,
      el('div', { 'class': 'grid2' }, [
        el('div', { 'class': 'field' }, [el('label', { text: 'Überschrift der Seite (freiwillig)' }),
          textfeld(s.titel, { maxlength: 200 }, function (v) { s.titel = v; })]),
        el('div', { 'class': 'field' }, [el('label', { text: 'Einleitung (freiwillig)' }),
          textfeld(s.text, { maxlength: 2000 }, function (v) { s.text = v; })])
      ]),
      el('div', {}, felder),
      leer,
      el('div', { 'class': 'btnrow umfrage-plus' }, [
        auswahl,
        el('button', { type: 'button', 'class': 'btn btn--sec btn--sm', text: '+ Frage', onclick: function () {
          s.felder.push(neuesFeld(auswahl.value));
          render();
        } })
      ])
    ]);
  }

  function render() {
    wurzel.innerHTML = '';
    seiten.forEach(function (s, si) { wurzel.appendChild(seiteKarte(s, si)); });
    wurzel.appendChild(el('div', { 'class': 'btnrow' }, [
      el('button', { type: 'button', 'class': 'btn btn--sec', text: '+ Seite', onclick: function () {
        seiten.push({ titel: '', text: '', felder: [] });
        render();
      } })
    ]));
    speichern();
  }

  render();
})();
