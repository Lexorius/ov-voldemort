/*
 * Abstimmung – Umfrage mit einer oder mehreren Seiten, verschlüsselt für OV-Multitool.
 *
 * Gleicher Weg wie bei den Standortmeldungen: flüchtiges Schlüsselpaar,
 * ECDH auf P-256, HKDF, AES-256-GCM. Der Connector bekommt nur Geheimtext;
 * er weiß, dass jemand geantwortet hat, aber nicht was.
 */
(function () {
  function b64uZuBytes(s) {
    var rest = '='.repeat((4 - (s.length % 4)) % 4);
    var roh = atob((s + rest).replace(/-/g, '+').replace(/_/g, '/'));
    var b = new Uint8Array(roh.length);
    for (var i = 0; i < roh.length; i++) { b[i] = roh.charCodeAt(i); }
    return b;
  }

  function bytesZuB64u(bytes) {
    var s = '';
    var b = new Uint8Array(bytes);
    for (var i = 0; i < b.length; i++) { s += String.fromCharCode(b[i]); }
    return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  /* "Sa., 5. Dezember 2026, 18:00 Uhr" in der Zeitzone des Geräts */
  function zeitText(ts) {
    var d = new Date(ts * 1000);
    try {
      return d.toLocaleDateString('de-DE', { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' })
        + ', ' + d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }) + ' Uhr';
    } catch (e) {
      return d.toLocaleString();
    }
  }

  // Zeitfenster anzeigen – auch wenn die Abstimmung noch nicht oder nicht mehr offen ist
  var zeitraum = document.getElementById('zeitraum');
  if (zeitraum) {
    var beginn = parseInt(zeitraum.getAttribute('data-beginn') || '0', 10);
    var ende = parseInt(zeitraum.getAttribute('data-ende') || '0', 10);
    var jetzt = Math.floor(Date.now() / 1000);
    var teile = [];
    if (beginn > jetzt) { teile.push('Offen ab ' + zeitText(beginn)); }
    if (ende > 0) { teile.push((ende < jetzt ? 'Beendet am ' : 'Offen bis ') + zeitText(ende)); }
    zeitraum.textContent = teile.join(' · ');
  }

  var box = document.getElementById('box');
  if (!box) { return; }

  var code = box.getAttribute('data-code');
  var kennung = box.getAttribute('data-kennung') || '';
  var basis = box.getAttribute('data-basis') || '';
  var serverSchluessel = box.getAttribute('data-schluessel');
  var dank = box.getAttribute('data-dank') || '';
  var formular = document.getElementById('formular');
  var meldung = document.getElementById('meldung');
  var seiten = Array.prototype.slice.call(formular.querySelectorAll('.seite'));
  var weiter = document.getElementById('weiter');
  var zurueck = document.getElementById('zurueck');
  var seitenzahl = document.getElementById('seitenzahl');
  var balken = document.getElementById('balken');
  var aktuell = 0;
  var merker = 'ovb_umfrage_' + kennung.slice(0, 16);

  function sage(text, art) {
    meldung.textContent = text;
    meldung.className = 'meldung' + (art ? ' ' + art : '');
  }

  if (!window.isSecureContext || !window.crypto || !window.crypto.subtle) {
    sage('Dieses Gerät kann hier nicht antworten. Nötig sind eine verschlüsselte Verbindung (https) '
      + 'und ein aktueller Browser.', 'schlecht');
    weiter.disabled = true;
    return;
  }

  try {
    if (window.localStorage.getItem(merker)) {
      sage('Von diesem Gerät wurde hier schon einmal geantwortet. Eine weitere Antwort zählt zusätzlich.');
    }
  } catch (e) { /* privater Modus: dann eben nicht */ }

  function zeigeSeite(n) {
    aktuell = Math.max(0, Math.min(seiten.length - 1, n));
    seiten.forEach(function (s, i) { s.hidden = i !== aktuell; });
    zurueck.hidden = aktuell === 0;
    weiter.textContent = aktuell === seiten.length - 1 ? 'Antwort senden' : 'Weiter';
    if (seitenzahl) { seitenzahl.textContent = 'Seite ' + (aktuell + 1) + ' von ' + seiten.length; }
    if (balken) { balken.style.width = Math.round(((aktuell + 1) / seiten.length) * 100) + '%'; }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  /* Antwort eines Feldes lesen – null heißt: nichts angegeben */
  function wert(feld) {
    var typ = feld.getAttribute('data-typ');
    var gewaehlt;
    if (typ === 'einfach' || typ === 'janein' || typ === 'skala') {
      gewaehlt = feld.querySelector('input:checked');
      if (!gewaehlt) { return null; }
      return typ === 'janein' ? gewaehlt.value : parseInt(gewaehlt.value, 10);
    }
    if (typ === 'mehrfach') {
      var liste = Array.prototype.map.call(feld.querySelectorAll('input:checked'), function (i) { return parseInt(i.value, 10); });
      return liste.length ? liste : null;
    }
    var eingabe = feld.querySelector('textarea, input[type=text]');
    var text = eingabe ? eingabe.value.trim() : '';
    return text === '' ? null : text;
  }

  function seitePruefen(seite) {
    var fehlt = 0;
    Array.prototype.forEach.call(seite.querySelectorAll('[data-feld]'), function (feld) {
      var pflicht = feld.getAttribute('data-pflicht') === '1';
      var leer = wert(feld) === null;
      feld.classList.toggle('fehlt', pflicht && leer);
      if (pflicht && leer) { fehlt++; }
    });
    return fehlt;
  }

  function einsammeln() {
    var antworten = {};
    var name = '';
    Array.prototype.forEach.call(formular.querySelectorAll('[data-feld]'), function (feld) {
      var id = feld.getAttribute('data-feld');
      var w = wert(feld);
      if (id === '_name') { name = w === null ? '' : String(w).slice(0, 60); return; }
      if (w !== null) { antworten[id] = w; }
    });
    return { antworten: antworten, name: name, zeit: Math.floor(Date.now() / 1000) };
  }

  async function verschluesseln(inhalt) {
    var empfaenger = await crypto.subtle.importKey(
      'raw', b64uZuBytes(serverSchluessel), { name: 'ECDH', namedCurve: 'P-256' }, false, []);
    var eigenes = await crypto.subtle.generateKey({ name: 'ECDH', namedCurve: 'P-256' }, false, ['deriveBits']);
    var geheim = await crypto.subtle.deriveBits({ name: 'ECDH', public: empfaenger }, eigenes.privateKey, 256);
    var eigenerPunkt = new Uint8Array(await crypto.subtle.exportKey('raw', eigenes.publicKey));

    var salz = crypto.getRandomValues(new Uint8Array(16));
    var basisSchluessel = await crypto.subtle.importKey('raw', geheim, 'HKDF', false, ['deriveBits']);
    var info = new TextEncoder().encode('OV-Budget Standort v1');
    var abgeleitet = new Uint8Array(await crypto.subtle.deriveBits(
      { name: 'HKDF', hash: 'SHA-256', salt: salz, info: info }, basisSchluessel, 352));
    var schluessel = await crypto.subtle.importKey('raw', abgeleitet.slice(0, 32), 'AES-GCM', false, ['encrypt']);
    var nonce = abgeleitet.slice(32, 44);

    var geheimtext = new Uint8Array(await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv: nonce }, schluessel, new TextEncoder().encode(JSON.stringify(inhalt))));

    var paket = new Uint8Array(1 + 65 + 16 + geheimtext.length);
    paket[0] = 1;
    paket.set(eigenerPunkt, 1);
    paket.set(salz, 66);
    paket.set(geheimtext, 82);
    return bytesZuB64u(paket);
  }

  async function senden() {
    var daten = await verschluesseln(einsammeln());
    var antwort = await fetch(basis + 'index.php?p=umfrage_antwort', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ u: code, daten: daten })
    });
    var ergebnis = await antwort.json().catch(function () { return {}; });
    if (!antwort.ok || !ergebnis.ok) {
      throw new Error(ergebnis.fehler || ('Server antwortete mit ' + antwort.status));
    }
  }

  zurueck.addEventListener('click', function () { sage(''); zeigeSeite(aktuell - 1); });

  formular.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var fehlt = seitePruefen(seiten[aktuell]);
    if (fehlt > 0) {
      sage(fehlt === 1 ? 'Eine Pflichtfrage auf dieser Seite ist noch offen.'
        : fehlt + ' Pflichtfragen auf dieser Seite sind noch offen.', 'schlecht');
      var erstes = seiten[aktuell].querySelector('.fehlt');
      if (erstes) { erstes.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
      return;
    }
    sage('');
    if (aktuell < seiten.length - 1) {
      zeigeSeite(aktuell + 1);
      return;
    }
    weiter.disabled = true;
    zurueck.disabled = true;
    sage('Wird verschlüsselt und gesendet …');
    senden().then(function () {
      try { window.localStorage.setItem(merker, String(Date.now())); } catch (e) { /* egal */ }
      formular.hidden = true;
      sage(dank !== '' ? dank : 'Danke, deine Antwort ist angekommen.', 'gut');
    }).catch(function (fehler) {
      weiter.disabled = false;
      zurueck.disabled = false;
      sage('Hat nicht geklappt: ' + ((fehler && fehler.message) || 'unbekannter Grund') + '.', 'schlecht');
    });
  });

  zeigeSeite(0);
})();
