/**
 * Aviso de vez nos jogos da campanha: som curto, vibração e notificação do navegador quando a partida passa a
 * esperar pelo jogador. A notificação do sistema só aparece com a aba fora de vista (com a aba aberta, a mesa já
 * mostra a faixa "Sua vez"). Ligar/desligar fica guardado neste navegador.
 * - JGAviso.ligado(): avisos ligados (o padrão).
 * - JGAviso.permitido(): o navegador deixou mostrar notificações.
 * - JGAviso.alternar(): liga (pedindo a permissão, se preciso) ou desliga; devolve uma Promise com o novo estado.
 * - JGAviso.vez({ titulo, texto, url, tag }): avisa.
 */
window.JGAviso = window.JGAviso || (function () {
    'use strict';

    const CHAVE = 'jg-avisos';
    const suporta = 'Notification' in window;
    const ligado = () => { try { return localStorage.getItem(CHAVE) !== '0'; } catch (_) { return true; } };
    const gravar = v => { try { localStorage.setItem(CHAVE, v ? '1' : '0'); } catch (_) { /* sem armazenamento */ } };
    const permitido = () => suporta && Notification.permission === 'granted';

    // o navegador só deixa tocar som depois de um toque na página: o contexto de áudio nasce no primeiro clique
    let audio = null;
    function prepararAudio() {
        if (audio) return;
        try { audio = new (window.AudioContext || window.webkitAudioContext)(); } catch (_) { audio = null; }
    }
    document.addEventListener('pointerdown', prepararAudio, { once: true, capture: true });
    document.addEventListener('keydown', prepararAudio, { once: true, capture: true });

    function tocar() {
        if (!audio) return;
        try {
            if (audio.state === 'suspended') audio.resume();
            const t = audio.currentTime;
            [[660, 0], [880, 0.14]].forEach(([freq, atraso]) => { // dois toques curtos, subindo
                const o = audio.createOscillator();
                const g = audio.createGain();
                o.type = 'sine';
                o.frequency.value = freq;
                g.gain.setValueAtTime(0.0001, t + atraso);
                g.gain.exponentialRampToValueAtTime(0.18, t + atraso + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, t + atraso + 0.22);
                o.connect(g).connect(audio.destination);
                o.start(t + atraso);
                o.stop(t + atraso + 0.25);
            });
        } catch (_) { /* sem som */ }
    }

    async function alternar() {
        if (ligado() && (permitido() || !suporta || Notification.permission === 'denied')) {
            gravar(false);
            return false;
        }
        gravar(true);
        if (suporta && Notification.permission === 'default') {
            try { await Notification.requestPermission(); } catch (_) { /* navegador antigo */ }
        }
        return true;
    }

    function vez({ titulo, texto, url, tag }) {
        if (!ligado()) return;
        tocar();
        if (navigator.vibrate) { try { navigator.vibrate([120, 60, 120]); } catch (_) { /* sem vibração */ } }
        if (!document.hidden || !permitido()) return;
        try {
            const n = new Notification(titulo, { body: texto || '', tag: tag || 'jg-vez', icon: '/favicon.ico', renotify: true });
            n.onclick = () => {
                window.focus();
                if (url && location.href.split('#')[0] !== url) location.href = url;
                n.close();
            };
            setTimeout(() => n.close(), 15000);
        } catch (_) { /* celular sem notificação fora de service worker */ }
    }

    return { ligado, permitido, suporta, alternar, vez };
})();
