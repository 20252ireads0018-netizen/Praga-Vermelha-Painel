// Posts de 09/11/2026 do cronograma (Painel Praga Vermelha). Roda na página do Instagram depois do gerador de 04/11
// (window.PV, guardado em localStorage.__pv): acrescenta os carrosséis do dia e o formato de meme.
// Uso: eval(localStorage.__pv); eval(localStorage.__pv09); PV.slides('tecnica09') → File[]; PV.memes() → File[].
(() => {
    Object.assign(PV.POSTS, {
        tecnica09: [
            { k: 'TÉCNICA DO DIA', t: 'IMOBILIZAR ALVO', b: ['Maldição: Curso Forçado'], capa: true },
            { k: 'A LÓGICA', t: 'IMBUIR MITIGAÇÃO', b: [
                'Você imbui a maldição no alvo, mas mitiga todas as rotas comuns que ele poderia seguir.',
                'Sem nenhum curso possível, ele não sai do lugar.'] },
            { k: 'NA FICHA', t: 'IMOBILIZAR ALVO', b: [
                'Custo: 15 de aura.',
                'Nível de maldição 6, com Imbuir e Mitigar maldição.',
                'Ação principal: teste de Maldição contra a resiliência do alvo.'] },
            { k: 'EFEITO', t: 'PRESO NO LUGAR', b: [
                'O alvo não se move até vencer o teste, que pode repetir todo início de turno.',
                'Enquanto isso, os testes físicos dele têm desvantagem.',
                '+7 de aura: +1 de desvantagem. Acumulativo.'] },
            { k: 'SUA VEZ', t: 'QUEM VOCÊ PRENDERIA?', b: ['Comenta aqui embaixo.', 'Livro do sistema: link na bio.'] },
        ],
        tema09: [
            { k: 'PRAGA VERMELHA', t: '6 TIPOS DE MALDIÇÃO', b: ['Toda maldição machuca alguém. A pergunta é quem.'], capa: true },
            { k: 'SENSITIVA E RACIAL', t: 'O CORPO E OS SENTIDOS', b: [
                'Sensitiva: muda como você vê, ouve e sente o mundo. Ex.: Visão Astral.',
                'Racial: altera o DNA e te afasta do humano. Ex.: Vampirismo, Licantropia.'] },
            { k: 'PESSOAL E IMPESSOAL', t: 'QUEM SOFRE', b: [
                'Pessoal: prejudica só quem carrega. Ex.: Ladrão de Dor.',
                'Impessoal: prejudica quem está em volta. Ex.: Maldição do Pesar, 5 Chamas.'] },
            { k: 'BENÉFICA E MUNDIAL', t: 'O PREÇO E O MUNDO', b: [
                'Benéfica: ajuda os outros às custas de quem carrega. Ex.: Assimilação Empática.',
                'Mundial: muda o ambiente em volta. Ex.: Atração Elétrica, Paradoxo.'] },
            { k: 'SUA VEZ', t: 'QUAL SERIA A SUA?', b: [
                'Uma maldição pode ter mais de um tipo: Ladrão de Dor é pessoal e benéfica.',
                'Conta nos comentários.'] },
        ],
        ferramenta09: [
            { k: 'FERRAMENTA', t: 'GLADIO', b: ['O card game que roda dentro da sua campanha'], capa: true },
            { k: 'A MESA', t: 'ATÉ 6 JOGADORES', b: [
                '40 de vida para cada um.',
                'Mão inicial de 5 cartas e até 10 de energia.',
                'Jogue contra a sua mesa ou contra o bot.'] },
            { k: 'AS CARTAS', t: 'LENDAS, FEITIÇOS E MAIS', b: [
                '4 tipos: Lenda, Feitiço, Encantamento e Equipamento.',
                '24 palavras-chave: Pressa, Voar, Toque mortal, Imortal, Bushido…',
                'O mestre cria cartas, decks e playmats próprios.'] },
            { k: 'A APOSTA', t: '7 SHOTS E VOCÊ CAI', b: [
                'A partida pode valer o dinheiro da ficha, com bolão para quem assiste.',
                'Quando uma carta manda, você toma shot. No 7º, está fora.'] },
            { k: 'COMECE AGORA', t: 'ABRA O GLADIO', b: ['Dentro de qualquer campanha, de graça.', 'praga-vermelha.websight.com.br'] },
        ],
    });

    // Memes para o público jovem: setup em cima, punchline num bloco escuro.
    const MEMES = [
        { setup: 'O jogador, todo confiante:', fala: '"Declaro Amplificar e Mitigar."', punch: 'O livro: não faz nada.\nMas cobra a aura.' },
        { setup: 'Ninguém:\n\nO jogador às 3 da manhã:', fala: '"E se eu reverter o meu imbuir?"', punch: 'Amplificação Reversa.\nO mestre: …' },
    ];
    const VERMELHO = '#b62525', CREME = '#f3ece0', SANS = '"Segoe UI", "Helvetica Neue", Arial, sans-serif';
    async function meme(m, i, total) {
        const W = 1080, H = 1350, M = 96;
        const c = document.createElement('canvas'); c.width = W; c.height = H; const x = c.getContext('2d');
        x.fillStyle = VERMELHO; x.fillRect(0, 0, W, H); x.fillStyle = CREME;
        x.globalAlpha = .75; x.font = `600 30px ${SANS}`; x.letterSpacing = '4px'; x.fillText('MEME DO SISTEMA', M, 150);
        x.textAlign = 'right'; x.fillText(`${i + 1}/${total}`, W - M, 150); x.textAlign = 'left'; x.globalAlpha = 1; x.letterSpacing = '0px';
        let y = 300; x.font = `600 54px ${SANS}`;
        for (const l of m.setup.split('\n')) { x.fillText(l, M, y); y += 70; }
        y += 30; x.font = `700 64px CinzelX`;
        for (const l of quebrar(x, m.fala, W - 2 * M)) { x.fillText(l, M, y); y += 80; }
        y += 60; x.fillStyle = '#1a0d0d'; x.beginPath(); x.roundRect(M - 20, y - 70, W - 2 * M + 40, 120 + m.punch.split('\n').length * 80, 28); x.fill();
        x.fillStyle = CREME; x.font = `700 58px ${SANS}`; y += 10;
        for (const l of m.punch.split('\n')) { x.fillText(l, M + 20, y); y += 80; }
        x.globalAlpha = .7; x.font = `600 28px ${SANS}`; x.fillText('@editoravermelha_01', M, H - 90);
        x.textAlign = 'right'; x.fillText('PRAGA VERMELHA RPG', W - M, H - 90);
        return new Promise(r => c.toBlob(b => r(new File([b], `meme-${i + 1}.jpg`, { type: 'image/jpeg' })), 'image/jpeg', .95));
    }
    function quebrar(x, texto, largura) {
        const linhas = []; let atual = '';
        for (const p of texto.split(' ')) { const t = atual ? atual + ' ' + p : p; if (x.measureText(t).width > largura && atual) { linhas.push(atual); atual = p; } else atual = t; }
        if (atual) linhas.push(atual); return linhas;
    }
    PV.memes = async () => { await document.fonts.load('700 64px CinzelX'); return Promise.all(MEMES.map((m, i) => meme(m, i, MEMES.length))); };
})();
