/*
 * "Ist am Lagerort" – verschlüsselt für OV-Budget.
 *
 * Gleicher Weg wie bei den Standortmeldungen: flüchtiges Schlüsselpaar,
 * ECDH auf P-256, HKDF, AES-256-GCM. Der Connector bekommt nur Geheimtext;
 * er weiß, dass jemand gemeldet hat, aber nicht was.
 */
(function () {
  var box = document.getElementById('box');
  if (!box) { return; }

  // Bezeichnung steht im Anker der Adresse und bleibt im Browser
  try {
    var anker = new URLSearchParams((window.location.hash || '').replace(/^#/, ''));
    var name = anker.get('n');
    if (name) {
      var feld = document.getElementById('was');
      var lesbar = decodeURIComponent(escape(atob(name.replace(/-/g, '+').replace(/_/g, '/'))));
      if (feld && lesbar) { feld.textContent = lesbar; document.title = lesbar + ' – am Lagerort'; }
    }
  } catch (e) { /* ohne Anker bleibt es bei "Funkgerät" */ }

  var token = box.getAttribute('data-token');
  var basis = box.getAttribute('data-basis') || '';
  var serverSchluessel = box.getAttribute('data-schluessel');
  var istGruppe = box.getAttribute('data-gruppe') === '1';
  var gesamt = parseInt(box.getAttribute('data-anzahl'), 10) || 0;

  var meldung = document.getElementById('meldung');
  var melder = document.getElementById('melder');
  var knopfAlle = document.getElementById('alle');
  var knopfTeil = document.getElementById('teilweise');
  var feldAnzahl = document.getElementById('feld-anzahl');
  var anzahl = document.getElementById('anzahl');
  var knopfAnzahl = document.getElementById('senden-anzahl');

  function sage(text, art) {
    meldung.textContent = text;
    meldung.className = 'meldung' + (art ? ' ' + art : '');
  }

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

  // Name auf diesem Gerät merken – er verlässt es nur mit einer Meldung
  try {
    var gemerkt = window.localStorage.getItem('ovb_melder');
    if (gemerkt && melder) { melder.value = gemerkt; }
  } catch (e) { /* privater Modus: dann eben nicht */ }

  if (!window.isSecureContext || !window.crypto || !window.crypto.subtle) {
    sage('Dieses Gerät kann hier nicht melden. Nötig sind eine verschlüsselte Verbindung (https) '
      + 'und ein aktueller Browser.', 'schlecht');
    [knopfAlle, knopfTeil, knopfAnzahl].forEach(function (k) { if (k) { k.disabled = true; } });
    return;
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
    paket[0] = 1;                       // Fassung des Formats
    paket.set(eigenerPunkt, 1);
    paket.set(salz, 66);
    paket.set(geheimtext, 82);
    return bytesZuB64u(paket);
  }

  async function melden(wieViele) {
    var name = melder ? melder.value.trim().slice(0, 60) : '';
    try { window.localStorage.setItem('ovb_melder', name); } catch (e) { /* egal */ }

    var daten = await verschluesseln({
      da: true,
      anzahl: istGruppe ? wieViele : null,
      melder: name,
      zeit: Math.floor(Date.now() / 1000)
    });

    var antwort = await fetch(basis + 'index.php?p=bestandsmeldung', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ g: token, daten: daten })
    });
    var ergebnis = await antwort.json().catch(function () { return {}; });
    if (!antwort.ok || !ergebnis.ok) {
      throw new Error(ergebnis.fehler || ('Server antwortete mit ' + antwort.status));
    }
  }

  function zeitText() {
    var d = new Date();
    return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
  }

  function senden(wieViele, knopf) {
    [knopfAlle, knopfTeil, knopfAnzahl].forEach(function (k) { if (k) { k.disabled = true; } });
    sage('Wird gesendet …');
    melden(wieViele).then(function () {
      sage((istGruppe
        ? (wieViele === gesamt ? 'Alle ' + gesamt + ' Geräte' : wieViele + ' von ' + gesamt + ' Geräten')
        : 'Gerät') + ' um ' + zeitText() + ' als da gemeldet. Danke!', 'gut');
      if (knopf) { knopf.textContent = 'Nochmal melden'; }
      [knopfAlle, knopfTeil, knopfAnzahl].forEach(function (k) { if (k) { k.disabled = false; } });
    }).catch(function (fehler) {
      [knopfAlle, knopfTeil, knopfAnzahl].forEach(function (k) { if (k) { k.disabled = false; } });
      sage('Hat nicht geklappt: ' + ((fehler && fehler.message) || 'unbekannter Grund') + '.', 'schlecht');
    });
  }

  knopfAlle.addEventListener('click', function () { senden(gesamt, knopfAlle); });
  if (knopfTeil && feldAnzahl) {
    knopfTeil.addEventListener('click', function () {
      feldAnzahl.hidden = !feldAnzahl.hidden;
      if (!feldAnzahl.hidden && anzahl) { anzahl.focus(); }
    });
  }
  if (knopfAnzahl && anzahl) {
    knopfAnzahl.addEventListener('click', function () {
      var wert = parseInt(anzahl.value, 10);
      if (isNaN(wert) || wert < 0) { sage('Bitte eine Anzahl eintragen.', 'schlecht'); return; }
      senden(Math.min(wert, gesamt || wert), knopfAnzahl);
    });
  }
})();
