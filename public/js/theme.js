// Pengalih tema terang/gelap.
// Terang adalah default (tidak lagi mengikuti setelan sistem); pilihan
// pengguna disimpan di localStorage dan dipasang lebih dulu oleh skrip
// kecil di <head> supaya tidak ada kedipan tema saat halaman dimuat.

(() => {
  const html = document.documentElement;
  const btn = document.getElementById('btnTema');
  const GELAP = 'dark';
  const TERANG = 'light';

  function terapkan(tema) {
    const gelap = tema === GELAP;

    html.dataset.theme = gelap ? GELAP : TERANG;
    try { localStorage.setItem('tema', html.dataset.theme); } catch { /* mode privat */ }

    if (!btn) return;
    const label = gelap ? 'Ganti ke tema terang' : 'Ganti ke tema gelap';
    btn.setAttribute('aria-pressed', String(gelap));
    btn.setAttribute('aria-label', label);
    btn.title = label;
  }

  if (btn) {
    btn.addEventListener('click', () => {
      terapkan(html.dataset.theme === GELAP ? TERANG : GELAP);
    });
  }

  // Selaraskan label tombol dengan tema yang sudah dipasang skrip di <head>.
  terapkan(html.dataset.theme === GELAP ? GELAP : TERANG);
})();
