// Halaman pasien: chat teks + mode percakapan suara.
import { SesiSuara } from '/js/voice.js';

const log = document.getElementById('chatLog');
const form = document.getElementById('formChat');
const input = document.getElementById('inputPesan');
const btnKirim = document.getElementById('btnKirim');
const btnSuara = document.getElementById('btnSuara');
const labelSuara = document.getElementById('labelSuara');

const daftarJanji = document.getElementById('daftarJanji');
const jumlahJanji = document.getElementById('jumlahJanji');

// Elemen mode suara
const overlay = document.getElementById('voiceOverlay');
const orb = document.getElementById('orb');
const statusTeks = document.getElementById('statusTeks');
const statusDetail = document.getElementById('statusDetail');
const transkripIsi = document.getElementById('transkripIsi');
const btnBisu = document.getElementById('btnBisu');
const labelBisu = document.getElementById('labelBisu');
const btnHentikan = document.getElementById('btnHentikan');

let riwayat = [];
let sibuk = false;
let sesi = null;

/* ------------------------------------------------------- Gulir otomatis */

const AMBANG_BAWAH = 120;   // px — jarak yang masih dianggap "di bawah"
let mauIkut = true;         // apakah kita boleh mengikuti pesan baru
let gulirSendiri = false;   // penanda: kita sedang menggulir secara program
let interaksiTerakhir = 0;  // kapan pengguna terakhir berinteraksi dengan log
const btnTurun = document.getElementById('btnTurun');

/** Apakah posisi gulir sekarang sudah dekat dasar? */
function diDekatBawah() {
  return log.scrollHeight - log.scrollTop - log.clientHeight <= AMBANG_BAWAH;
}

/**
 * Gulir ke dasar.
 * Membaca scrollHeight memaksa tata letak dihitung, jadi nilainya sudah benar
 * dan bisa langsung dipakai — tanpa menunggu requestAnimationFrame, yang
 * callback-nya tidak dijalankan di tab yang tidak aktif.
 * Diulang sekali lewat setTimeout untuk menangkap perubahan tinggi susulan
 * (font atau gambar yang baru selesai dimuat).
 */
function gulirKeBawah(paksa = false) {
  if (paksa) mauIkut = true;
  if (!mauIkut) return;

  gulirSendiri = true;
  log.scrollTop = log.scrollHeight;
  perbaruiTombolTurun();

  setTimeout(() => {
    if (mauIkut) {
      log.scrollTop = log.scrollHeight;
      perbaruiTombolTurun();
    }
    gulirSendiri = false;
  }, 60);
}

/** Lompat ke pesan terbaru dengan halus — dipakai tombol, bukan pembaruan otomatis. */
function lompatKeBawah() {
  mauIkut = true;
  gulirSendiri = true;
  log.scrollTo({ top: log.scrollHeight, behavior: 'smooth' });
  setTimeout(() => {
    gulirSendiri = false;
    perbaruiTombolTurun();
  }, 450);
}

function perbaruiTombolTurun() {
  if (!btnTurun) return;
  btnTurun.hidden = diDekatBawah();
}

/** Catat interaksi nyata pengguna — supaya kita bisa membedakannya dari gulir program. */
for (const jenis of ['wheel', 'touchstart', 'touchmove', 'mousedown', 'keydown']) {
  log.addEventListener(jenis, () => { interaksiTerakhir = Date.now(); }, { passive: true });
}

/**
 * Hanya hormati "pengguna menjauh" kalau memang dia yang menggulir.
 * Tanpa penjagaan ini, event scroll yang dipicu gulir program kita sendiri
 * akan salah menandai bahwa pengguna sudah pindah, dan gulir otomatis mati.
 */
log.addEventListener('scroll', () => {
  if (!gulirSendiri && Date.now() - interaksiTerakhir < 2000) {
    mauIkut = diDekatBawah();
  }
  perbaruiTombolTurun();
}, { passive: true });

// Setiap perubahan isi log memicu gulir — termasuk saat indikator "mengetik"
// dihapus, badge tool muncul, atau font selesai dimuat.
new MutationObserver(() => gulirKeBawah())
  .observe(log, { childList: true, subtree: true, characterData: true });

window.addEventListener('resize', () => gulirKeBawah());

btnTurun?.addEventListener('click', lompatKeBawah);

const JAM = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });

const LABEL_STATE = {
  menyiapkan: 'Menyiapkan sesi suara…',
  mendengarkan: 'Mendengarkan…',
  berpikir: 'Rani sedang berpikir…',
  berbicara: 'Rani sedang berbicara…',
  gagal: 'Ada masalah pada sesi suara',
};

/* ------------------------------------------------------------------ Chat */

function tambahPesan(peran, teks, opsi = {}) {
  const el = document.createElement('div');
  el.className = 'msg ' + (peran === 'user' ? 'msg--user' : peran === 'system' ? 'msg--system' : 'msg--bot');
  el.textContent = teks;

  if (opsi.tool?.length) {
    const meta = document.createElement('div');
    meta.className = 'msg__meta';
    for (const t of opsi.tool) {
      const b = document.createElement('span');
      const gagal = t.hasil && t.hasil.sukses === false;
      b.className = 'badge ' + (gagal ? 'badge--warn' : 'badge--ok');
      b.textContent = t.nama.replace(/_/g, ' ');
      b.title = JSON.stringify(t.hasil);
      meta.appendChild(b);
    }
    el.appendChild(meta);
  }

  if (peran !== 'system' && !opsi.tanpaWaktu) {
    const w = document.createElement('div');
    w.className = 'msg__time';
    w.textContent = JAM.format(new Date());
    el.appendChild(w);
  }

  log.appendChild(el);
  gulirKeBawah();
  return el;
}

function indikatorMengetik() {
  const el = document.createElement('div');
  el.className = 'msg msg--bot';
  el.innerHTML = '<span class="typing" aria-label="Rani sedang menjawab"><span></span><span></span><span></span></span>';
  log.appendChild(el);
  gulirKeBawah();
  return el;
}

function renderJanji(daftar) {
  const list = Array.isArray(daftar) ? daftar : [];
  jumlahJanji.textContent = String(list.length);

  if (!list.length) {
    daftarJanji.innerHTML = `
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
             stroke-linecap="round" aria-hidden="true">
          <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 11h18M8 3v4M16 3v4"/>
        </svg>
        <div>Belum ada janji temu.<br>Mulai obrolan untuk membuat yang pertama.</div>
      </div>`;
    return;
  }

  daftarJanji.innerHTML = '';
  for (const j of list) {
    const el = document.createElement('div');
    el.className = 'appt';

    const b1 = document.createElement('div');
    b1.className = 'appt__when';
    b1.textContent = `${j.hari || ''} ${j.jam} — ${j.nama_pasien}`;

    const b2 = document.createElement('div');
    b2.className = 'appt__who';
    b2.textContent = `${j.dokter || '-'}${j.keluhan ? ' · ' + j.keluhan : ''}`;

    const b3 = document.createElement('div');
    b3.className = 'appt__code';
    b3.textContent = j.kode_booking;

    el.append(b1, b2, b3);
    daftarJanji.appendChild(el);
  }
}

async function muatJanji() {
  try {
    const d = await (await fetch('/api/jadwal', { headers: { Accept: 'application/json' } })).json();
    renderJanji(d.janji_mendatang);
  } catch { /* panel dibiarkan apa adanya */ }
}

async function kirimTeks(teks) {
  if (!teks.trim() || sibuk) return;
  sibuk = true;
  btnKirim.disabled = true;
  tambahPesan('user', teks);
  gulirKeBawah(true);          // pesan sendiri selalu menarik tampilan ke bawah
  const mengetik = indikatorMengetik();

  try {
    const res = await fetch('/api/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ pesan: teks, riwayat, sumber: 'chat' }),
    });
    const data = await res.json();
    mengetik.remove();
    tambahPesan('bot', data.balasan || '(tidak ada balasan)', { tool: data.tool });
    riwayat.push({ role: 'user', content: teks });
    riwayat.push({ role: 'assistant', content: data.balasan || '' });
    renderJanji(data.janji_mendatang);
  } catch (e) {
    mengetik.remove();
    tambahPesan('system', 'Gagal menghubungi server: ' + e.message);
  } finally {
    sibuk = false;
    btnKirim.disabled = false;
    input.focus();
  }
}

form.addEventListener('submit', (e) => {
  e.preventDefault();
  const teks = input.value;
  input.value = '';
  kirimTeks(teks);
});

/* ------------------------------------------------------------- Mode suara */

function setLevel(nilai) {
  orb.style.setProperty('--level', nilai.toFixed(3));
}

function setState(state, detail) {
  overlay.dataset.state = state;
  orb.dataset.state = state;
  statusTeks.textContent = LABEL_STATE[state] || state;
  statusDetail.textContent = detail || '';
}

function renderTranskrip(daftar) {
  if (!daftar.length) return;
  transkripIsi.innerHTML = '';
  for (const baris of daftar) {
    const el = document.createElement('div');
    el.className = 'transkrip-baris' + (baris.peran === 'kamu' ? ' transkrip-baris--kamu' : '');
    const siapa = document.createElement('strong');
    siapa.textContent = baris.peran === 'kamu' ? 'Kamu' : 'Rani';
    const isi = document.createElement('span');
    isi.textContent = baris.teks;
    el.append(siapa, isi);
    transkripIsi.appendChild(el);
  }
}

function bukaOverlay() {
  overlay.dataset.aktif = 'true';
  document.body.style.overflow = 'hidden';
  btnHentikan.focus();
}

function tutupOverlay() {
  overlay.dataset.aktif = 'false';
  document.body.style.overflow = '';
  orb.style.setProperty('--level', '0');
  btnSuara.focus();
}

/** Setelah sesi berakhir: simpan transkrip ke riwayat chat sebagai blok tertutup. */
function ringkasanSesi(transkrip) {
  if (!transkrip?.length) {
    tambahPesan('system', 'Sesi suara berakhir tanpa percakapan.');
    return;
  }

  const jumlah = transkrip.filter((t) => t.peran === 'kamu').length;
  const el = document.createElement('div');
  el.className = 'msg msg--bot';

  const judul = document.createElement('div');
  judul.textContent = `Sesi suara berakhir — ${jumlah} giliran Anda berbicara.`;

  const detail = document.createElement('details');
  detail.className = 'transkrip-tutup';
  detail.style.marginTop = '8px';
  detail.style.color = 'var(--color-foreground)';
  detail.style.background = 'var(--color-background)';
  detail.style.borderColor = 'var(--color-border)';

  const ringkas = document.createElement('summary');
  ringkas.textContent = 'Lihat transkrip sesi suara';

  const isi = document.createElement('div');
  isi.className = 'transkrip-isi';
  for (const baris of transkrip) {
    const baris1 = document.createElement('div');
    baris1.className = 'transkrip-baris';
    const siapa = document.createElement('strong');
    siapa.textContent = baris.peran === 'kamu' ? 'Kamu' : 'Rani';
    siapa.style.color = baris.peran === 'kamu' ? 'var(--color-destructive)' : 'var(--color-primary)';
    const teks = document.createElement('span');
    teks.style.color = 'var(--color-foreground)';
    teks.textContent = baris.teks;
    baris1.append(siapa, teks);
    isi.appendChild(baris1);
  }

  detail.append(ringkas, isi);
  el.append(judul, detail);
  log.appendChild(el);
  gulirKeBawah(true);
}

async function mulaiSuara() {
  if (sesi) return;
  btnSuara.disabled = true;
  setState('menyiapkan');
  setLevel(0);
  bukaOverlay();

  transkripIsi.innerHTML = '<p class="voice-card__hint">Transkrip akan muncul di sini setelah ada percakapan.</p>';

  try {
    sesi = new SesiSuara({
      onState: setState,
      onLevel: setLevel,
      onTranskrip: renderTranskrip,
      onJadwal: renderJanji,
      onTool: (nama, args) => {
        setState('berpikir', 'Menjalankan: ' + nama.replace(/_/g, ' '));
        console.info('[suara] tool', nama, args);
      },
      onBisu: (bisu) => {
        btnBisu.setAttribute('aria-pressed', String(bisu));
        labelBisu.textContent = bisu ? 'Bunyikan' : 'Bisukan';
      },
      onError: (pesan) => setState('gagal', pesan),
      onSelesai: (alasan, transkrip) => {
        sesi = null;
        labelSuara.textContent = 'Bicara';
        btnSuara.setAttribute('aria-pressed', 'false');
        tutupOverlay();
        if (alasan) tambahPesan('system', 'Sesi suara berakhir: ' + alasan);
        ringkasanSesi(transkrip);
      },
    });

    await sesi.mulai();
    labelSuara.textContent = 'Hentikan';
    btnSuara.setAttribute('aria-pressed', 'true');
  } catch (e) {
    sesi = null;
    console.error('[suara] gagal memulai sesi:', e);
    setState('gagal', e.message + ' — tekan Bicara untuk coba lagi');
    setTimeout(() => { if (!sesi) tutupOverlay(); }, 9000);
  } finally {
    btnSuara.disabled = false;
  }
}

btnSuara.addEventListener('click', () => {
  if (sesi) {
    sesi.stop();
    return;
  }
  mulaiSuara();
});

btnHentikan.addEventListener('click', () => sesi?.stop());

btnBisu.addEventListener('click', () => sesi?.setBisu(!btnBisu.matches('[aria-pressed="true"]')));

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && sesi) sesi.stop();
});

muatJanji();
