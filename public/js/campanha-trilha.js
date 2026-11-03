/**
 * Trilha da campanha: música ou vídeo que o mestre toca para todos que estão na página da campanha.
 * O servidor guarda o que toca, se está tocando, a posição e a hora (CampanhaController::trilha); cada navegador
 * calcula onde deveria estar e acerta o player quando passa de 2 s de diferença. Áudios vêm da biblioteca de sons
 * da campanha; vídeos, do YouTube (IFrame API). O volume é de cada um (fica lembrado no navegador).
 */
(function () {
    const caixa = document.getElementById('camp-trilha');
    if (!caixa) return;
    const D = JSON.parse(caixa.dataset.config || '{}');
    const mestre = !!D.mestre;
    const $ = s => caixa.querySelector(s);
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const tempo = s => { s = Math.max(0, Math.floor(s || 0)); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); };

    let estado = null;        // última trilha recebida
    let difRelogio = 0;       // relógio do servidor − navegador (s)
    let liberado = false;     // o navegador só toca som depois de um clique na página
    let audio = null, yt = null, ytPronto = false, ytCarregando = false, ytAtual = null;
    let volume = 0.7;
    try { const v = parseFloat(localStorage.getItem('camp-trilha-volume')); if (!isNaN(v)) volume = v; } catch (_) { /* sem armazenamento */ }

    const agora = () => Date.now() / 1000 + difRelogio;
    const posEsperada = t => t.tocando ? t.pos + (agora() - t.em) : t.pos;

    /* ---------- players ---------- */
    function pararTudo() {
        if (audio) { audio.pause(); }
        if (yt && ytPronto) { try { yt.stopVideo(); } catch (_) { /* nada */ } }
        $('#trilha-video').hidden = true;
    }
    function carregarYouTube(pronto) {
        if (ytPronto) return pronto();
        if (!ytCarregando) {
            ytCarregando = true;
            const antes = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = function () { if (antes) antes(); criarYT(pronto); };
            const s = document.createElement('script');
            s.src = 'https://www.youtube.com/iframe_api';
            document.head.appendChild(s);
        }
    }
    function criarYT(pronto) {
        yt = new YT.Player('trilha-yt', {
            width: '100%', height: '100%', playerVars: { playsinline: 1, rel: 0, modestbranding: 1 },
            events: {
                onReady: () => { ytPronto = true; yt.setVolume(volume * 100); pronto(); },
                onStateChange: ev => { if (ev.data === 0 && estado && estado.loop && estado.tocando) { yt.seekTo(0, true); yt.playVideo(); } },
            },
        });
    }

    function aplicar() {
        const t = estado;
        desenharStatus();
        if (!t || !t.tipo) { pararTudo(); return; }
        const alvo = posEsperada(t);
        if (t.tipo === 'som') {
            if (yt && ytPronto) { try { yt.stopVideo(); } catch (_) { /* nada */ } }
            $('#trilha-video').hidden = true;
            const src = D.sonsUrl.replace('__ID__', t.som_id);
            if (!audio) { audio = new Audio(); audio.preload = 'auto'; }
            audio.loop = !!t.loop;
            audio.volume = volume;
            if (!audio.src.endsWith(src)) { audio.src = src; }
            const acertar = () => {
                const dur = audio.duration || 0;
                let p = alvo;
                if (dur && p > dur) p = t.loop ? p % dur : dur;
                if (Math.abs(audio.currentTime - p) > 2) { try { audio.currentTime = p; } catch (_) { /* ainda carregando */ } }
                if (t.tocando && liberado) audio.play().catch(() => pedirClique());
                else if (!t.tocando) audio.pause();
                else pedirClique();
            };
            if (audio.readyState >= 1) acertar(); else audio.addEventListener('loadedmetadata', acertar, { once: true });
        } else if (t.tipo === 'youtube') {
            if (audio) audio.pause();
            $('#trilha-video').hidden = false;
            carregarYouTube(() => {
                if (ytAtual !== t.yt) { ytAtual = t.yt; yt.cueVideoById(t.yt, alvo); }
                yt.setVolume(volume * 100);
                const cur = yt.getCurrentTime ? yt.getCurrentTime() : 0;
                if (Math.abs(cur - alvo) > 2) yt.seekTo(alvo, true);
                if (t.tocando && liberado) yt.playVideo();
                else if (!t.tocando) yt.pauseVideo();
                else pedirClique();
            });
        }
    }
    function pedirClique() { $('#trilha-ouvir').hidden = liberado; }

    function desenharStatus() {
        const t = estado;
        $('#trilha-titulo').innerHTML = t && t.tipo ? icone(t.tocando ? 'play' : 'pausa') + ' ' + esc(t.titulo) : 'Nada tocando agora.';
        if (mestre) {
            $('#trilha-pausar').innerHTML = t && t.tocando ? icone('pausa') + ' Pausar' : icone('play') + ' Continuar';
            $('#trilha-pausar').disabled = !(t && t.tipo);
            $('#trilha-parar').disabled = !(t && t.tipo);
            $('#trilha-loop').checked = !!(t && t.loop);
            const sel = $('#trilha-fonte');
            const atual = sel.value;
            sel.innerHTML = '<option value="">Escolha o que tocar…</option>' +
                (D.sons.length ? '<optgroup label="Áudios da campanha">' + D.sons.map(s => `<option value="som:${s.id}">${esc(s.nome)}</option>`).join('') + '</optgroup>' : '') +
                ((t && t.links || []).length ? '<optgroup label="YouTube">' + t.links.map(l => `<option value="yt:${esc(l.yt)}" data-titulo="${esc(l.titulo)}" data-id="${esc(l.id)}">${esc(l.titulo)}</option>`).join('') + '</optgroup>' : '');
            if ([...sel.options].some(o => o.value === atual)) sel.value = atual;
            $('#trilha-apagar-link').hidden = !String(sel.value).startsWith('yt:');
        }
    }

    /* ---------- barra de tempo (todos veem; o mestre arrasta para mudar a posição) ---------- */
    setInterval(() => {
        const t = estado;
        const barra = $('#trilha-barra');
        if (!t || !t.tipo) { barra.value = 0; $('#trilha-tempo').textContent = ''; return; }
        let dur = 0, cur = posEsperada(t);
        if (t.tipo === 'som' && audio) { dur = audio.duration || 0; cur = audio.currentTime || cur; }
        if (t.tipo === 'youtube' && yt && ytPronto && yt.getDuration) { dur = yt.getDuration() || 0; cur = yt.getCurrentTime() || cur; }
        if (document.activeElement !== barra) { barra.max = dur ? Math.floor(dur) : 100; barra.value = dur ? Math.floor(cur) : 0; } // sem duração ainda: barra vazia
        $('#trilha-tempo').textContent = tempo(cur) + (dur ? ' / ' + tempo(dur) : '');
    }, 500);

    /* ---------- consultas ---------- */
    async function consultar() {
        try {
            const r = await fetch(D.estadoUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (!r.ok) return;
            const j = await r.json();
            receber(j.trilha);
        } catch (_) { /* tenta de novo */ }
    }
    function receber(t) {
        if (!t) { caixa.hidden = !mestre; return; }
        if (t.agora) difRelogio = t.agora - Date.now() / 1000;
        const mudou = !estado || t.seq !== estado.seq;
        estado = t;
        if (mudou) aplicar(); else desenharStatus();
    }
    consultar();
    setInterval(() => { if (!document.hidden) consultar(); }, 3000);
    setInterval(() => { if (estado && estado.tocando) aplicar(); }, 15000); // corrige pequenos desvios

    /* ---------- volume e liberação do som ---------- */
    $('#trilha-volume').value = Math.round(volume * 100);
    $('#trilha-volume').addEventListener('input', e => {
        volume = (+e.target.value || 0) / 100;
        try { localStorage.setItem('camp-trilha-volume', String(volume)); } catch (_) { /* sem armazenamento */ }
        if (audio) audio.volume = volume;
        if (yt && ytPronto) yt.setVolume(volume * 100);
    });
    $('#trilha-ouvir').addEventListener('click', () => { liberado = true; $('#trilha-ouvir').hidden = true; aplicar(); });
    document.addEventListener('click', () => { if (!liberado) { liberado = true; if (estado && estado.tocando) aplicar(); } }, { once: true });

    /* ---------- controles do mestre ---------- */
    if (!mestre) return;
    async function mandar(dados) {
        try {
            const r = await fetch(D.trilhaUrl, { method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': D.csrf }, body: JSON.stringify(dados) });
            const j = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(j.erro || j.message || 'Erro ' + r.status);
            liberado = true;
            receber(j.trilha);
        } catch (e) { $('#trilha-erro').textContent = e.message; setTimeout(() => { $('#trilha-erro').textContent = ''; }, 5000); }
    }
    $('#trilha-fonte').addEventListener('change', () => { $('#trilha-apagar-link').hidden = !$('#trilha-fonte').value.startsWith('yt:'); });
    $('#trilha-tocar').addEventListener('click', () => {
        const v = $('#trilha-fonte').value;
        if (!v) return;
        if (v.startsWith('som:')) mandar({ acao: 'tocar', tipo: 'som', som_id: +v.slice(4) });
        else mandar({ acao: 'tocar', tipo: 'youtube', url: v.slice(3), titulo: $('#trilha-fonte').selectedOptions[0].dataset.titulo });
    });
    $('#trilha-pausar').addEventListener('click', () => {
        if (!estado || !estado.tipo) return;
        if (estado.tocando) {
            let pos = posEsperada(estado);
            if (estado.tipo === 'som' && audio) pos = audio.currentTime;
            if (estado.tipo === 'youtube' && yt && ytPronto) pos = yt.getCurrentTime();
            mandar({ acao: 'pausar', pos });
        } else mandar({ acao: 'continuar' });
    });
    $('#trilha-parar').addEventListener('click', () => mandar({ acao: 'parar' }));
    $('#trilha-loop').addEventListener('change', e => mandar({ acao: 'repetir', loop: e.target.checked }));
    $('#trilha-barra').addEventListener('change', e => mandar({ acao: 'posicao', pos: +e.target.value }));
    $('#trilha-link-form').addEventListener('submit', e => {
        e.preventDefault();
        const f = e.target;
        if (!f.url.value.trim()) return;
        mandar({ acao: 'link', url: f.url.value.trim(), titulo: f.titulo.value.trim() }).then(() => { f.reset(); });
    });
    $('#trilha-apagar-link').addEventListener('click', () => {
        const o = $('#trilha-fonte').selectedOptions[0];
        if (o && o.dataset.id && confirm('Tirar este vídeo da lista?')) mandar({ acao: 'apagar_link', id: o.dataset.id });
    });
})();
