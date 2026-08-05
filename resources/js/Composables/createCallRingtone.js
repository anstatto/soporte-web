/**
 * Ringtone Web Audio (sin archivos).
 * mode: 'incoming' (doble tono) | 'outgoing' (tono suave)
 */
export function createCallRingtone() {
    let ctx = null;
    let timer = null;
    let oscillators = [];

    const stopTone = () => {
        oscillators.forEach((o) => {
            try {
                o.stop();
            } catch {
                /* */
            }
        });
        oscillators = [];
    };

    const beep = (freq, durationMs, gain = 0.07) => {
        if (!ctx) return;
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        o.type = 'sine';
        o.frequency.value = freq;
        g.gain.value = gain;
        o.connect(g);
        g.connect(ctx.destination);
        o.start();
        oscillators.push(o);
        setTimeout(() => {
            try {
                o.stop();
            } catch {
                /* */
            }
            oscillators = oscillators.filter((x) => x !== o);
        }, durationMs);
    };

    const playPattern = (mode) => {
        if (mode === 'incoming') {
            beep(880, 180, 0.09);
            setTimeout(() => beep(988, 180, 0.09), 220);
        } else {
            beep(620, 280, 0.045);
        }
    };

    const start = (mode = 'incoming') => {
        stop();
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            ctx = new Ctx();
            if (ctx.state === 'suspended') ctx.resume();
            playPattern(mode);
            const interval = mode === 'incoming' ? 1800 : 2400;
            timer = setInterval(() => playPattern(mode), interval);
        } catch {
            /* ignore */
        }
    };

    const stop = () => {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
        stopTone();
        if (ctx) {
            try {
                ctx.close();
            } catch {
                /* */
            }
            ctx = null;
        }
    };

    return { start, stop };
}
