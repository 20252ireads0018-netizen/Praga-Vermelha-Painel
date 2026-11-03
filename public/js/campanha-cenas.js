/**
 * Cenas da campanha: animações de tela cheia que o mestre dispara ("Você descobriu…", "Como você quer matar ele?").
 * A cena chega pelo mesmo polling do estado (campanha.js solta o evento "campanha:estado"); cada navegador mostra
 * uma vez cada cena nova. O som é sintetizado na hora e só toca depois de um clique na página (regra do navegador).
 * O mestre dispara pelo formulário #cena-form; "Prévia só para mim" mostra a cena sem enviar.
 */
(function () {
    const DURACAO = { descobriu: 8000, matar: 11000 };
    let vista = null; // "seq-em" da última cena vista; null até a primeira consulta (não mostra cenas antigas ao abrir)
    let atual = null;

    /* ---------- som (Web Audio, sem arquivos) ---------- */
    let ctx = null;
    document.addEventListener('pointerdown', () => {
        try { ctx = ctx || new (window.AudioContext || window.webkitAudioContext)(); if (ctx.state === 'suspended') ctx.resume(); } catch (_) { /* sem áudio */ }
    }, { passive: true });
    function tom(freq, ini, dur, tipo, vol, ate) {
        const o = ctx.createOscillator(), g = ctx.createGain(), t = ctx.currentTime + ini;
        o.type = tipo; o.frequency.setValueAtTime(freq, t);
        if (ate) o.frequency.exponentialRampToValueAtTime(ate, t + dur);
        g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(vol, t + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
        o.connect(g).connect(ctx.destination); o.start(t); o.stop(t + dur + 0.05);
    }
    function ruido(ini, dur, vol) {
        const b = ctx.createBuffer(1, Math.floor(ctx.sampleRate * dur), ctx.sampleRate), d = b.getChannelData(0);
        for (let i = 0; i < d.length; i++) d[i] = (Math.random() * 2 - 1) * (1 - i / d.length);
        const s = ctx.createBufferSource(), g = ctx.createGain(), f = ctx.createBiquadFilter(), t = ctx.currentTime + ini;
        f.type = 'bandpass'; f.frequency.value = 2400; g.gain.value = vol;
        s.buffer = b; s.connect(f).connect(g).connect(ctx.destination); s.start(t);
    }
    function soar(tipo) {
        if (!ctx || ctx.state !== 'running') return;
        try {
            if (tipo === 'descobriu') { // brilho subindo
                [523, 659, 784, 1047].forEach((f, i) => tom(f, 0.55 + i * 0.12, 1.6, 'sine', 0.08));
                tom(130, 0.4, 2.2, 'triangle', 0.1);
            } else { // corte + pancada + batida de coração
                ruido(0.12, 0.35, 0.5);
                tom(90, 0.5, 0.9, 'sine', 0.5, 38);
                [1.5, 1.75, 2.6, 2.85].forEach(t => tom(62, t, 0.25, 'sine', 0.35, 40));
            }
        } catch (_) { /* sem áudio */ }
    }

    /* ---------- a cena ---------- */
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    // cada letra anima sozinha, mas as palavras não quebram no meio
    const letras = t => { let i = 0; return String(t).split(' ').map(p => `<span class="p">${[...p].map(c => `<span class="l" style="--i:${i++}">${esc(c)}</span>`).join('')}</span>`).join(' '); };
    const sorte = (a, b) => a + Math.random() * (b - a);

    function fechar() {
        if (!atual) return;
        const el = atual;
        atual = null;
        clearTimeout(el._tempo);
        document.removeEventListener('keydown', teclas);
        el.classList.add('saindo');
        setTimeout(() => el.remove(), 600);
    }
    function teclas(e) { if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') fechar(); }

    function mostrar(c) {
        if (atual) { clearTimeout(atual._tempo); atual.remove(); atual = null; } // a cena anterior sai na hora (e o tempo dela não fecha a nova)
        const tipo = c.tipo === 'matar' ? 'matar' : 'descobriu';
        const titulo = c.titulo || (tipo === 'matar' ? 'Como você quer matar ele?' : 'Você descobriu…');
        const el = document.createElement('div');
        el.className = 'cena cena-' + tipo;
        el.setAttribute('role', 'alertdialog');
        el.setAttribute('aria-label', titulo + (c.texto ? ' ' + c.texto : ''));
        el.style.setProperty('--n', [...titulo].length);
        let extra = '';
        if (tipo === 'descobriu') {
            extra = `<div class="cena-sigilo" aria-hidden="true"><svg viewBox="-100 -100 200 200">
                    <g class="gira"><circle r="92"/><circle r="84"/><polygon points="0,-84 72.7,42 -72.7,42"/><polygon points="0,84 72.7,-42 -72.7,-42"/></g>
                    <g class="gira-volta"><circle r="52"/><path d="M0-52V52M-52 0H52M-36.8-36.8L36.8 36.8M36.8-36.8L-36.8 36.8"/><circle r="18"/></g>
                </svg></div>
                <div class="cena-brasas" aria-hidden="true">${Array.from({ length: 26 }, () =>
                    `<i style="--x:${sorte(0, 100).toFixed(1)}%;--t:${sorte(3, 7).toFixed(1)}px;--d:${sorte(4, 8).toFixed(2)}s;--a:${sorte(0, 3).toFixed(2)}s;--v:${sorte(-60, 60).toFixed(0)}px"></i>`).join('')}</div>`;
        } else {
            extra = `<div class="cena-clarao" aria-hidden="true"></div><div class="cena-corte" aria-hidden="true"></div>
                <div class="cena-gotas" aria-hidden="true">${Array.from({ length: 18 }, () =>
                    `<i style="--x:${sorte(0, 100).toFixed(1)}%;--t:${sorte(4, 11).toFixed(1)}px;--d:${sorte(1.5, 4).toFixed(2)}s;--a:${sorte(0.6, 2.5).toFixed(2)}s"></i>`).join('')}</div>`;
        }
        el.innerHTML = `<div class="cena-fundo"></div>${extra}
            <div class="cena-conteudo"><h2 class="cena-titulo">${letras(titulo)}</h2>${c.texto ? `<p class="cena-texto">${esc(c.texto)}</p>` : ''}</div>
            <div class="cena-dica">Toque para fechar</div>`;
        el.addEventListener('click', fechar);
        document.body.appendChild(el);
        document.addEventListener('keydown', teclas);
        atual = el;
        el._tempo = setTimeout(fechar, DURACAO[tipo]);
        soar(tipo);
    }
    window.CampanhaCenas = { mostrar };

    window.addEventListener('campanha:estado', ev => {
        const c = ev.detail && ev.detail.cena;
        const id = c ? c.seq + '-' + c.em : '';
        if (vista === null) { // ao abrir a página: só mostra se a cena acabou de ser disparada
            vista = id;
            if (c && c.agora - c.em <= 8) mostrar(c);
            return;
        }
        if (c && id !== vista) {
            vista = id;
            if (c.agora - c.em <= 60) mostrar(c);
        }
    });

    /* ---------- formulário do mestre ---------- */
    function ligarFormulario() {
        const form = document.getElementById('cena-form');
        if (!form) return;
        const status = document.getElementById('cena-status');
        const app = document.getElementById('camp-app');
        const csrf = (app && app.dataset.csrf) || (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        const dados = () => ({ tipo: form.tipo.value, texto: form.texto.value.trim(), titulo: form.titulo.value.trim(), alvo: form.alvo.value ? +form.alvo.value : null });
        const placeholders = { descobriu: ['Ex.: o selo de Vassago no porão', 'Você descobriu…'], matar: ['Ex.: o Carniçal de três cabeças', 'Como você quer matar ele?'] };
        form.addEventListener('change', e => {
            if (e.target.name !== 'tipo') return;
            [form.texto.placeholder, form.titulo.placeholder] = placeholders[form.tipo.value];
        });
        document.getElementById('cena-previa').addEventListener('click', () => mostrar(dados()));
        form.addEventListener('submit', async e => {
            e.preventDefault();
            const botao = form.querySelector('[type="submit"]');
            botao.disabled = true;
            status.textContent = 'Enviando…';
            try {
                const r = await fetch(form.dataset.url, { method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(dados()) });
                const j = await r.json().catch(() => ({}));
                if (!r.ok) throw new Error(j.erro || j.message || 'Erro ' + r.status);
                const quem = form.alvo.value ? form.alvo.selectedOptions[0].textContent : 'todos na página';
                status.textContent = 'Mostrando para ' + quem + '.';
            } catch (err) {
                status.textContent = err.message;
            } finally {
                botao.disabled = false;
            }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ligarFormulario); else ligarFormulario();
})();
