// AudioWorklet: mengumpulkan suara mikrofon dalam potongan kecil
// lalu mengirimkannya ke thread utama untuk dikirim ke Gemini Live.
class PcmCapture extends AudioWorkletProcessor {
    constructor() {
        super();
        this.buffer = [];
        this.ukuran = 0;
        // kirim setiap ~100 ms (16000 Hz * 0.1)
        this.batas = 1600;
    }

    process(inputs) {
        const input = inputs[0];
        if (!input || !input[0]) return true;
        const kanal = input[0];
        this.buffer.push(new Float32Array(kanal));
        this.ukuran += kanal.length;

        if (this.ukuran >= this.batas) {
            const gabung = new Float32Array(this.ukuran);
            let pos = 0;
            for (const b of this.buffer) {
                gabung.set(b, pos);
                pos += b.length;
            }
            this.buffer = [];
            this.ukuran = 0;
            this.port.postMessage(gabung);
        }
        return true;
    }
}

registerProcessor('pcm-capture', PcmCapture);
