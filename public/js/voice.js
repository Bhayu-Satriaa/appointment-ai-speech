// Sesi percakapan suara dengan Gemini Live API.
//
// Catatan penting: audio dari model adalah PCM 24 kHz yang diterima LANGSUNG
// (speech-to-speech), bukan hasil text-to-speech. Transkrip hanya efek samping
// yang kita minta lewat outputAudioTranscription — jadi menyembunyikannya
// tidak mengubah apa pun pada audionya.
//
// Mesin state: menyiapkan → mendengarkan ⇄ berpikir → berbicara → (berhenti)
// Empat state inti saja; kejadian spesifik (mis. tool apa yang dipanggil)
// dikirim sebagai "detail" untuk label status, bukan sebagai state baru.

const RATE_IN = 16000;
const RATE_OUT = 24000;
const AMBANG_BICARA = 0.035;   // ambang level mikrofon untuk deteksi interupsi
const JEDA_SELESAI = 700;      // ms tanpa audio baru sebelum kembali "mendengarkan"

function floatKePcm16(f32) {
  const out = new Int16Array(f32.length);
  for (let i = 0; i < f32.length; i++) {
    const s = Math.max(-1, Math.min(1, f32[i]));
    out[i] = s < 0 ? s * 0x8000 : s * 0x7fff;
  }
  return out;
}

function keBase64(pcm16) {
  const bytes = new Uint8Array(pcm16.buffer);
  let biner = '';
  const blok = 0x8000;
  for (let i = 0; i < bytes.length; i += blok) {
    biner += String.fromCharCode.apply(null, bytes.subarray(i, i + blok));
  }
  return btoa(biner);
}

function dariBase64(b64) {
  const biner = atob(b64);
  const bytes = new Uint8Array(biner.length);
  for (let i = 0; i < biner.length; i++) bytes[i] = biner.charCodeAt(i);
  return new Int16Array(bytes.buffer);
}

function resample(f32, dari, ke) {
  if (dari === ke) return f32;
  const rasio = dari / ke;
  const panjang = Math.round(f32.length / rasio);
  const out = new Float32Array(panjang);
  for (let i = 0; i < panjang; i++) {
    const pos = i * rasio;
    const a = Math.floor(pos);
    const b = Math.min(a + 1, f32.length - 1);
    out[i] = f32[a] + (f32[b] - f32[a]) * (pos - a);
  }
  return out;
}

function levelRms(f32) {
  let jumlah = 0;
  for (let i = 0; i < f32.length; i++) jumlah += f32[i] * f32[i];
  return Math.min(1, Math.sqrt(jumlah / f32.length) * 3.2);
}

export class SesiSuara {
  constructor(panggilan = {}) {
    this.panggilan = panggilan;
    this.ws = null;
    this.aktif = false;
    this.state = 'menyiapkan';
    this.transkrip = [];
    this.bisu = false;

    this.ctxIn = null;
    this.ctxOut = null;
    this.stream = null;
    this.node = null;

    this.antreanMain = 0;
    this.sumberAktif = new Set();
    this.audioTerakhir = 0;
    this.timerJeda = null;
    this.levelTerakhir = 0;
  }

  setState(state, detail = '') {
    if (this.state === state && !detail) return;
    this.state = state;
    this.panggilan.onState?.(state, detail);
  }

  setLevel(nilai) {
    // redam supaya orb tidak bergetar berlebihan
    const halus = this.levelTerakhir * 0.6 + nilai * 0.4;
    this.levelTerakhir = halus;
    this.panggilan.onLevel?.(halus);
  }

  tambahTranskrip(peran, teks) {
    const akhir = this.transkrip[this.transkrip.length - 1];
    if (akhir && akhir.peran === peran) akhir.teks += teks;
    else this.transkrip.push({ peran, teks });
    this.panggilan.onTranskrip?.(this.transkrip);
  }

  // ------------------------------------------------------------------ Mulai
  async mulai() {
    this.setState('menyiapkan');

    const r = await fetch('/api/live/token', { method: 'POST', headers: { Accept: 'application/json' } });
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'Gagal mendapatkan token');

    this.wsUrl = data.ws_url;
    this.model = data.model;
    const cfg = await (await fetch('/api/live/config')).json();

    this.ws = new WebSocket(`${this.wsUrl}?access_token=${encodeURIComponent(data.token)}`);

    // Tahap penyiapan sesi. Kegagalan di sini dilaporkan sedetail mungkin —
    // kode penutupan dan pesan dari Gemini jauh lebih berguna daripada
    // sekadar "timeout".
    const siap = new Promise((ok, gagal) => {
      let sudah = false;

      const batas = setTimeout(() => {
        if (sudah) return;
        gagal(new Error(
          'Gemini tidak menjawab dalam 25 detik. Cek koneksi internet, lalu coba lagi.'
        ));
      }, 25000);

      const bersih = () => {
        this.ws.removeEventListener('message', onPesan);
        this.ws.removeEventListener('close', onTutup);
      };

      const onPesan = async (ev) => {
        const teks = typeof ev.data === 'string' ? ev.data
          : ev.data instanceof Blob ? await ev.data.text()
          : new TextDecoder().decode(ev.data);

        if (teks.includes('setupComplete')) {
          sudah = true;
          clearTimeout(batas);
          bersih();
          ok();
          return;
        }

        // Gemini menolak sesi — misalnya model tidak tersedia atau kuota habis.
        try {
          const m = JSON.parse(teks);
          if (m.error) {
            sudah = true;
            clearTimeout(batas);
            bersih();
            const pesan = m.error.message || JSON.stringify(m.error);
            console.error('[suara] Gemini menolak sesi:', m.error);
            gagal(new Error('Gemini menolak sesi: ' + pesan));
          }
        } catch { /* bukan JSON, abaikan */ }
      };

      const onTutup = (e) => {
        if (sudah) return;
        sudah = true;
        clearTimeout(batas);
        bersih();
        console.error('[suara] koneksi ditutup saat penyiapan', { code: e.code, reason: e.reason });
        gagal(new Error(
          `Koneksi ke Gemini ditutup saat penyiapan (kode ${e.code}` +
          (e.reason ? `: ${e.reason}` : '') + ')'
        ));
      };

      this.ws.addEventListener('message', onPesan);
      this.ws.addEventListener('close', onTutup);
    });

    this.ws.addEventListener('open', () => {
      this.ws.send(JSON.stringify({
        setup: {
          model: this.model,
          generationConfig: {
            responseModalities: ['AUDIO'],
            speechConfig: { voiceConfig: { prebuiltVoiceConfig: { voiceName: cfg.voice || 'Kore' } } },
            thinkingConfig: { thinkingBudget: 0 },
          },
          systemInstruction: { parts: [{ text: cfg.system_instruction }] },
          tools: cfg.tools,
          inputAudioTranscription: {},
          outputAudioTranscription: {},
        },
      }));
      this.kirimTeks('Halo, saya ingin membuat janji temu di klinik.');
    });

    this.ws.addEventListener('message', async (ev) => {
      let teks;
      if (typeof ev.data === 'string') teks = ev.data;
      else if (ev.data instanceof Blob) teks = await ev.data.text();
      else teks = new TextDecoder().decode(ev.data);
      let m;
      try { m = JSON.parse(teks); } catch { return; }
      this.tangani(m);
    });

    this.ws.addEventListener('error', () => {
      this.setState('gagal', 'Koneksi ke Gemini bermasalah');
      this.panggilan.onError?.('Koneksi ke Gemini bermasalah');
    });

    this.ws.addEventListener('close', (e) => {
      this.hentikanLokal();
      this.panggilan.onSelesai?.(e.reason || '', this.transkrip);
    });

    await siap;
    await this.mulaiMikrofon();
    this.aktif = true;
    this.setState('mendengarkan');
    this.mulaiPantauMikrofon();
    this.mulaiPemantauJeda();
  }

  // ------------------------------------------------------------------ Mic
  async mulaiMikrofon() {
    this.stream = await navigator.mediaDevices.getUserMedia({
      audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true, autoGainControl: true },
    });

    const jejak = this.stream.getAudioTracks()[0];
    if (jejak) {
      console.info('[suara] mikrofon:', jejak.label, jejak.getSettings());
      if (jejak.muted) {
        throw new Error(
          'Mikrofon dilaporkan nonaktif oleh sistem. Periksa izin mikrofon dan ' +
          'perangkat input yang dipilih di pengaturan suara.'
        );
      }
    }

    this.ctxIn = new AudioContext({ sampleRate: RATE_IN });

    // AudioContext bisa lahir dalam keadaan suspended (kebijakan autoplay).
    // Tanpa resume(), worklet tidak pernah dijalankan sehingga tidak ada satu
    // pun potongan suara yang terkirim — sesi tampak normal tapi Gemini tidak
    // mendengar apa pun.
    if (this.ctxIn.state === 'suspended') await this.ctxIn.resume();
    console.info('[suara] AudioContext:', this.ctxIn.state, this.ctxIn.sampleRate + ' Hz');

    await this.ctxIn.audioWorklet.addModule('/js/pcm-capture.js');

    const sumber = this.ctxIn.createMediaStreamSource(this.stream);
    this.node = new AudioWorkletNode(this.ctxIn, 'pcm-capture');

    this.frameMasuk = 0;
    this.node.port.onmessage = (e) => {
      this.frameMasuk++;

      let f32 = e.data;
      if (this.ctxIn.sampleRate !== RATE_IN) f32 = resample(f32, this.ctxIn.sampleRate, RATE_IN);

      const level = levelRms(f32);
      if (this.state === 'mendengarkan') this.setLevel(level);

      // Interupsi: user bicara sementara Rani masih bicara
      if (this.state === 'berbicara' && level > AMBANG_BICARA) this.interupsi();

      if (this.bisu || !this.aktif || this.ws?.readyState !== 1) return;
      this.ws.send(JSON.stringify({
        realtimeInput: { mediaChunks: [{ mimeType: `audio/pcm;rate=${RATE_IN}`, data: keBase64(floatKePcm16(f32)) }] },
      }));
    };

    sumber.connect(this.node);

    // CATATAN: sengaja TIDAK menyambung node ke ctxIn.destination.
    // Menyalurkan mikrofon ke speaker membuat suaranya berputar balik dan
    // echoCancellation bisa ikut membatalkan sinyal mikrofon itu sendiri.
  }

  /** Pantau apakah mikrofon benar-benar mengirim data. */
  mulaiPantauMikrofon() {
    clearInterval(this.timerMic);
    this.timerMic = setInterval(() => {
      if (!this.aktif || this.bisu) return;

      if ((this.frameMasuk || 0) === 0) {
        this.setState('gagal',
          'Mikrofon tidak mengirim suara. Periksa izin mikrofon di browser ' +
          'dan perangkat input di pengaturan suara Windows.');
        this.panggilan.onError?.('Mikrofon tidak mengirim suara');
        clearInterval(this.timerMic);
      }
    }, 4000);
  }

  kirimTeks(teks) {
    if (this.ws?.readyState !== 1) return;
    this.ws.send(JSON.stringify({
      clientContent: { turns: [{ role: 'user', parts: [{ text: teks }] }], turnComplete: true },
    }));
  }

  // --------------------------------------------------------------- Tangani
  tangani(m) {
    if (m.toolCall?.functionCalls) {
      const nama = m.toolCall.functionCalls.map((f) => f.name.replace(/_/g, ' ')).join(', ');
      this.setState('berpikir', 'Mengecek sistem: ' + nama);
      this.jalankanTool(m.toolCall.functionCalls);
      return;
    }

    const sc = m.serverContent;
    if (!sc) return;

    if (sc.inputTranscription?.text) {
      this.tambahTranskrip('kamu', sc.inputTranscription.text);
      if (this.state !== 'berbicara') this.setState('mendengarkan');
    }
    if (sc.outputTranscription?.text) this.tambahTranskrip('rani', sc.outputTranscription.text);

    let adaAudio = false;
    for (const p of (sc.modelTurn?.parts || [])) {
      if (p.inlineData?.data) {
        this.putar(p.inlineData.data, p.inlineData.mimeType || `audio/pcm;rate=${RATE_OUT}`);
        adaAudio = true;
      }
    }
    if (adaAudio) {
      this.audioTerakhir = Date.now();
      if (this.state !== 'berbicara') this.setState('berbicara');
    } else if (sc.turnComplete && this.state === 'mendengarkan') {
      this.setState('berpikir');
    }
  }

  /** Kembalikan state ke "mendengarkan" setelah audio Rani benar-benar habis. */
  mulaiPemantauJeda() {
    clearInterval(this.timerJeda);
    this.timerJeda = setInterval(() => {
      if (this.state !== 'berbicara') return;
      const audioHabis = !this.ctxOut || this.antreanMain <= this.ctxOut.currentTime + 0.02;
      if (audioHabis && Date.now() - this.audioTerakhir > JEDA_SELESAI) {
        this.setState('mendengarkan');
        this.setLevel(0);
      }
    }, 200);
  }

  /** Interupsi: hentikan pemutaran segera, kembali mendengarkan. */
  interupsi() {
    for (const s of this.sumberAktif) {
      try { s.stop(); } catch { /* sudah berhenti */ }
    }
    this.sumberAktif.clear();
    if (this.ctxOut) this.antreanMain = this.ctxOut.currentTime;
    this.setState('mendengarkan', 'Dilanjutkan oleh Anda');
  }

  async jalankanTool(panggilanTool) {
    const hasil = [];
    for (const fc of panggilanTool) {
      this.panggilan.onTool?.(fc.name, fc.args);
      try {
        const r = await fetch('/api/tool', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ nama: fc.name, args: fc.args || {} }),
        });
        const d = await r.json();
        hasil.push({ id: fc.id, name: fc.name, response: d.hasil });
        if (d.janji_mendatang) this.panggilan.onJadwal?.(d.janji_mendatang);
      } catch (e) {
        hasil.push({ id: fc.id, name: fc.name, response: { sukses: false, pesan: 'Sistem gagal: ' + e.message } });
      }
    }
    if (this.ws?.readyState === 1) {
      this.ws.send(JSON.stringify({ toolResponse: { functionResponses: hasil } }));
    }
  }

  putar(b64, mime) {
    const cocok = /rate=(\d+)/.exec(mime || '');
    const rate = cocok ? parseInt(cocok[1], 10) : RATE_OUT;
    if (!this.ctxOut || this.ctxOut.sampleRate !== rate) {
      if (this.ctxOut) this.ctxOut.close().catch(() => {});
      this.ctxOut = new AudioContext({ sampleRate: rate });
      this.antreanMain = 0;
    }

    const pcm = dariBase64(b64);
    const f32 = new Float32Array(pcm.length);
    for (let i = 0; i < pcm.length; i++) f32[i] = pcm[i] / 0x8000;
    this.setLevel(levelRms(f32));

    const buf = this.ctxOut.createBuffer(1, f32.length, rate);
    buf.copyToChannel(f32, 0);
    const src = this.ctxOut.createBufferSource();
    src.buffer = buf;
    src.connect(this.ctxOut.destination);
    src.onended = () => this.sumberAktif.delete(src);
    this.sumberAktif.add(src);

    const sekarang = this.ctxOut.currentTime;
    const mulai = Math.max(sekarang, this.antreanMain);
    src.start(mulai);
    this.antreanMain = mulai + buf.duration;
  }

  setBisu(nilai) {
    this.bisu = !!nilai;
    this.panggilan.onBisu?.(this.bisu);
    if (this.bisu) this.setLevel(0);
  }

  hentikanLokal() {
    this.aktif = false;
    clearInterval(this.timerJeda);
    clearInterval(this.timerMic);
    for (const s of this.sumberAktif) { try { s.stop(); } catch { /* abaikan */ } }
    this.sumberAktif.clear();
    try { this.node?.disconnect(); } catch { /* abaikan */ }
    try { this.stream?.getTracks().forEach((t) => t.stop()); } catch { /* abaikan */ }
    try { this.ctxIn?.close(); } catch { /* abaikan */ }
    try { this.ctxOut?.close(); } catch { /* abaikan */ }
    this.ctxIn = this.ctxOut = this.node = this.stream = null;
  }

  stop() {
    this.hentikanLokal();
    try { this.ws?.close(); } catch { /* abaikan */ }
    this.ws = null;
  }
}
