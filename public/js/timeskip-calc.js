/**
 * Contas do time skip na tela: as mesmas de App\Support\TimeSkipCalc::calcular (lá elas valem para gravar).
 * cfg: regras do mestre (config/timeskip.php); r: escolhas do jogador.
 */
(function (raiz) {
    function calcular(cfg, r) {
        cfg = cfg || {}; r = r || {};
        const ramo = String(r.ramo || '');
        const nomeRamo = {};
        (cfg.ramos || []).forEach(x => { nomeRamo[x.id] = x.nome; });
        const totais = {};
        const extra = { escolhas: 0, salario: 0, salarios: 0, dinheiro: 0 };
        const erros = [];
        const aplicar = (efeitos, n) => {
            (efeitos || []).forEach(ef => {
                let ganho = Math.floor(n / Math.max(1, +ef.a_cada || 1)) * (+ef.valor || 0);
                if (+ef.limite > 0) ganho = Math.min(ganho, +ef.limite);
                if (!ganho) return;
                if (ef.tipo) extra[ef.tipo] = (extra[ef.tipo] || 0) + ganho;
                else totais[ef.rotulo] = (totais[ef.rotulo] || 0) + ganho;
            });
        };
        aplicar(cfg.bonus_efeitos, 1);

        let usadas = 0;
        let fator = +(cfg.fator ?? 100);
        (cfg.atividades || []).forEach(a => {
            const n = +((r.atividades || {})[a.id] || 0);
            if (n <= 0) return;
            if (a.max > 0 && n > a.max) erros.push(`${a.nome}: no máximo ${a.max} vez(es).`);
            if (a.ramo && a.ramo !== ramo) erros.push(`${a.nome} exige ${nomeRamo[a.ramo] || a.ramo}.`);
            usadas += n * (+a.custo || 1);
            if (a.fator > 0) fator = Math.max(fator, +a.fator);
            aplicar(a.efeitos, n);
        });

        let gasto = 0;
        (cfg.propriedades || []).forEach(p => {
            const n = +((r.propriedades || {})[p.id] || 0);
            if (n <= 0) return;
            if (p.max > 0 && n > p.max) erros.push(`${p.nome}: no máximo ${p.max} marco(s).`);
            if (p.ramo && p.ramo !== ramo) erros.push(`${p.nome} exige ${nomeRamo[p.ramo] || p.ramo}.`);
            gasto += n * (+p.preco || 0);
            aplicar(p.efeitos, n);
        });

        const escolhas = (+cfg.escolhas || 0) + Math.floor(extra.escolhas);
        if (usadas > escolhas) erros.push(`Escolhas demais: ${usadas} de ${escolhas}.`);
        const salarioMensal = (+r.salario || 0) + extra.salario;
        const renda = Math.round(salarioMensal * (+cfg.meses || 0) * fator / 100 + salarioMensal * extra.salarios + extra.dinheiro);
        const saldo = (+r.dinheiro_inicial || 0) + renda - gasto;
        if (saldo < 0) erros.push('Dinheiro insuficiente para as propriedades.');
        const r2 = v => Math.round(v * 100) / 100;
        Object.keys(totais).forEach(k => { totais[k] = r2(totais[k]); });

        return { escolhas, usadas, fator, salario_mensal: r2(salarioMensal), salarios_extra: r2(extra.salarios), renda, gasto, saldo, totais, erros };
    }

    const api = { calcular };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else raiz.TimeSkipCalc = api;
})(this);
