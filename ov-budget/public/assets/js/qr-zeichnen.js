/*
 * Zeichnet den QR-Code zu einem Fahrzeug und bietet ihn zum Drucken an.
 * Die Erzeugung übernimmt die Bibliothek qrcode.js (siehe deren Kopf).
 */
(function () {
  var block = document.querySelector('[data-qr]');
  if (!block || typeof qrcode !== 'function') {
    return;
  }
  var ziel = document.getElementById('qr-bild');
  var text = block.getAttribute('data-qr');

  // Fassung 0 heißt: die Bibliothek sucht die kleinste passende selbst
  var code = qrcode(0, 'M');
  code.addData(text);
  code.make();
  ziel.innerHTML = code.createSvgTag({ cellSize: 6, margin: 4, scalable: true });

  var knopf = document.getElementById('qr-drucken');
  if (!knopf) {
    return;
  }
  knopf.addEventListener('click', function () {
    var titel = document.querySelector('h1');
    var fenster = window.open('', '_blank');
    if (!fenster) {
      window.print();
      return;
    }
    fenster.document.write('<!doctype html><html lang="de"><head><meta charset="utf-8">'
      + '<title>Standort melden</title><style>'
      + 'body{font:15px/1.5 system-ui,sans-serif;text-align:center;margin:2cm 1cm;color:#111}'
      + 'h1{font-size:1.4rem;margin:0 0 .2rem}h2{font-size:1rem;font-weight:400;color:#475569;margin:0 0 1.2rem}'
      + 'svg{width:11cm;height:11cm}p{font-size:.85rem;color:#475569;margin-top:1rem}'
      + '</style></head><body>'
      + '<h1>' + (titel ? titel.textContent : 'Fahrzeug') + '</h1>'
      + '<h2>Standort melden</h2>'
      + ziel.innerHTML
      + '<p>Mit der Kamera scannen und dem Ortsverband melden, wo dieses Fahrzeug steht.</p>'
      + '</body></html>');
    fenster.document.close();
    fenster.focus();
    fenster.print();
  });
})();
