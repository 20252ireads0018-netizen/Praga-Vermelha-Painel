// Posts de 04/11/2026 do cronograma (Painel Praga Vermelha). Roda dentro da página do Instagram:
// gera os slides em canvas (1080x1350) e as capas de story (1080x1920) no estilo dos destaques.
// Uso: window.PV.slides('tecnica') -> File[]; window.PV.story('tecnicas') -> File.
(() => {
    const VERMELHO = '#b62525', CREME = '#f3ece0';
    const SANS = '"Segoe UI", "Helvetica Neue", Arial, sans-serif';

    const POSTS = {
        tecnica: [
            { k: 'TÉCNICA DO DIA', t: 'VÔO', b: ['Maldição: Curso Forçado'], capa: true },
            { k: 'A MALDIÇÃO', t: 'CURSO FORÇADO', b: [
                'Tudo o que seu corpo faz segue um curso: o andar, os braços, a voz, o arremesso de uma faca.',
                'A maldição mostra esses cursos como linhas amarelas, quase transparentes, que só você vê.',
                'E sempre te leva pelo pior curso possível.'] },
            { k: 'A LÓGICA', t: 'IMBUIR, AMPLIFICAR E REVERTER', b: [
                'Amplifique a maldição escolhendo um curso de movimento possível.',
                'Force espíritos nesse curso e faça a maldição forçá-lo até você.',
                'Resultado: você se desloca pelo ar. Voa.'] },
            { k: 'NA FICHA', t: 'VÔO', b: [
                'Custo: 5 de aura por deslocamento.',
                'Nível de maldição 6, com Amplificação e Imbuir maldição.',
                'Uma ação de movimento: voo igual à sua ameaça +1, dobrado, em qualquer direção.'] },
            { k: 'APRIMORAMENTOS', t: 'E SE FOR MAIS LONGE?', b: [
                '+3 de aura: +2 metros de voo. Acumulativo.',
                '+5 de aura: em vez de voar, você se teletransporta na direção escolhida. Requisito: amplificar essa técnica.',
                'Qual curso você forçaria?'] },
        ],
        exposicao: [
            { k: 'PRAGA VERMELHA', t: 'TÉCNICA NÃO SE COMPRA. SE DECLARA.', b: ['Como funcionam as declarações lógicas'], capa: true },
            { k: 'O MUNDO', t: 'HEXOMANTES E FISIOMANTES', b: [
                'Um mundo normal, onde o sobrenatural é conhecido até pela sua avó.',
                'A política e as relações públicas giram em torno dos conhecimentos profanos e das artes hexomânticas.'] },
            { k: 'A REGRA', t: 'LÓGICA DE OBTENÇÃO', b: [
                'Toda técnica tem uma.',
                'Para descobrir uma técnica nova, o jogador anuncia a lógica: "mitigar e imbuir", "amplificar e reverter".',
                'O mestre libera a técnica que se encaixa nessa lógica.'] },
            { k: 'A ORDEM IMPORTA', t: 'IMBUIR → REVERTER', b: [
                'Imbuir e Reverter: você imbui a sua reversão. Com as 5 Chamas, o alvo congela.',
                'Reverter e Imbuir: você inverte o próprio Imbuir. A aura volta ao seu núcleo e vira uma Amplificação Reversa.'] },
            { k: 'DO LIVRO', t: 'OUTRAS COMBINAÇÕES', b: [
                'Reverter e Amplificar: Mitigação Reversa, com sua ameaça +2 em aprimoramentos.',
                'Amplificar e Mitigar: não faz nada, mas cobra o custo.',
                'Condução e Condução: +2 aprimoramentos na técnica conduzida.'] },
            { k: 'SUA VEZ', t: 'QUAL LÓGICA VOCÊ DECLARA?', b: ['Responde nos comentários.', 'Livro, fichas e campanhas: link na bio.'] },
        ],
        ferramenta: [
            { k: 'FERRAMENTA', t: 'O PAINEL DO MESTRE', b: ['Tudo o que você controla na campanha'], capa: true },
            { k: 'NA SESSÃO', t: 'CENA E TRILHA', b: [
                'Dispare uma animação em tela cheia para todos os jogadores.',
                'Toque música ou vídeo para a mesa inteira.',
                'Efeitos sonoros prontos ou enviados por você, com um clique.'] },
            { k: 'EM COMBATE', t: 'INICIATIVA E DADOS', b: [
                'Rastreador de iniciativa da mesa.',
                'Pote de dados: rolagens públicas que todos veem na hora.',
                'Vida e aura de todas as fichas na sua tela.'] },
            { k: 'ENTRE SESSÕES', t: 'TIME SKIP E CASSINO', b: [
                'Time skips com uma escolha para cada jogador.',
                'Abra ou feche o cassino de minijogos para cada jogador.',
                'Mesas do tabletop ligadas à campanha.'] },
            { k: 'COMECE AGORA', t: 'CRIE SUA CAMPANHA', b: ['De graça, no navegador.', 'praga-vermelha.websight.com.br'] },
        ],
    };

    // ícones das capas dos destaques, no quadro 1080x1080
    const ICONES = {
        sistema: ['M540 400 C460 350 340 350 270 380 L270 700 C340 670 460 670 540 720 C620 670 740 670 810 700 L810 380 C740 350 620 350 540 400 Z', 'M540 400 L540 720'],
        tecnicas: ['M590 300 L400 580 L530 580 L490 780 L680 500 L550 500 Z'],
        ferramentas: ['M330 380 L750 380 M330 540 L750 540 M330 700 L750 700', 'M470 340 L470 420 M640 500 L640 580 M420 660 L420 740'],
        memes: ['M540 320 A220 220 0 1 1 539.9 320 Z', 'M455 480 L455 500 M625 480 L625 500', 'M430 590 C480 680 600 680 650 590'],
        planos: ['M320 700 L320 410 L440 530 L540 380 L640 530 L760 410 L760 700 Z'],
    };

    async function fontes() {
        if (![...document.fonts].some(f => f.family === 'CinzelX')) {
            const f = new FontFace('CinzelX', 'url(https://fonts.gstatic.com/s/cinzel/v23/8vIU7ww63mVu7gtR-kwKxNvkNOjw-jHgTYo.ttf)', { weight: '700' });
            await f.load(); document.fonts.add(f);
        }
        await document.fonts.load('700 80px CinzelX');
    }

    function quebrar(x, texto, largura) {
        const linhas = []; let atual = '';
        for (const p of texto.split(' ')) {
            const tenta = atual ? atual + ' ' + p : p;
            if (x.measureText(tenta).width > largura && atual) { linhas.push(atual); atual = p; } else atual = tenta;
        }
        if (atual) linhas.push(atual);
        return linhas;
    }

    function arquivo(c, nome) {
        return new Promise(r => c.toBlob(b => r(new File([b], nome, { type: 'image/jpeg' })), 'image/jpeg', 0.95));
    }

    async function slide(s, i, total, nome) {
        await fontes();
        const W = 1080, H = 1350, M = 96, L = W - 2 * M;
        const c = document.createElement('canvas'); c.width = W; c.height = H;
        const x = c.getContext('2d');
        x.fillStyle = VERMELHO; x.fillRect(0, 0, W, H);
        x.fillStyle = x.strokeStyle = CREME; x.textBaseline = 'alphabetic';

        // topo: série e contador
        x.globalAlpha = .75; x.font = `600 30px ${SANS}`; x.letterSpacing = '4px';
        x.fillText(s.k, M, 150);
        x.textAlign = 'right'; x.fillText(`${i + 1}/${total}`, W - M, 150); x.textAlign = 'left';
        x.globalAlpha = 1; x.letterSpacing = '0px';
        x.fillRect(M, 180, 80, 6);

        // título: diminui até caber em 4 linhas
        let tam = s.capa ? 132 : 100, tl;
        do { x.font = `700 ${tam}px CinzelX`; tl = quebrar(x, s.t, L); tam -= 6; } while (tl.length > (s.capa ? 5 : 3) && tam > 50);
        tam += 6;
        let y = s.capa ? (tl.length > 3 ? 330 : 470) : 330;
        for (const l of tl) { x.fillText(l, M - 4, y); y += tam * 1.08; }

        // corpo
        y += s.capa ? 40 : 50;
        x.font = `500 ${s.capa ? 44 : 42}px ${SANS}`;
        for (const par of s.b) {
            for (const l of quebrar(x, par, L)) { x.fillText(l, M, y); y += 60; }
            y += 30;
        }
        if (s.capa) { x.font = `600 36px ${SANS}`; x.globalAlpha = .75; x.fillText('Arraste para o lado  →', M, Math.max(y + 10, H - 230)); x.globalAlpha = 1; }

        // rodapé
        x.globalAlpha = .7; x.font = `600 28px ${SANS}`;
        x.fillText('@editoravermelha_01', M, H - 90);
        x.textAlign = 'right'; x.fillText('PRAGA VERMELHA RPG', W - M, H - 90); x.textAlign = 'left';
        x.globalAlpha = 1;
        return arquivo(c, `${nome}-${i + 1}.jpg`);
    }

    async function story(icone) {
        const c = document.createElement('canvas'); c.width = 1080; c.height = 1920;
        const x = c.getContext('2d');
        x.fillStyle = VERMELHO; x.fillRect(0, 0, 1080, 1920); x.translate(0, 420);
        x.strokeStyle = CREME; x.lineWidth = 28; x.lineCap = 'round'; x.lineJoin = 'round';
        for (const d of ICONES[icone]) x.stroke(new Path2D(d));
        return arquivo(c, `${icone}.jpg`);
    }

    // story de divulgação: o slide de capa do post, centralizado no 9:16
    async function storyDoPost(nome) {
        const capa = await slide(POSTS[nome][0], 0, POSTS[nome].length, nome);
        const bm = await createImageBitmap(capa);
        const c = document.createElement('canvas'); c.width = 1080; c.height = 1920;
        const x = c.getContext('2d');
        x.fillStyle = VERMELHO; x.fillRect(0, 0, 1080, 1920); x.drawImage(bm, 0, 285);
        return arquivo(c, `${nome}-story.jpg`);
    }

    window.PV = {
        POSTS,
        slides: nome => Promise.all(POSTS[nome].map((s, i) => slide(s, i, POSTS[nome].length, nome))),
        story, storyDoPost,
    };
})();
