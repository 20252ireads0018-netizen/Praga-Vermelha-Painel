/**
 * Campanhas: botão no cabeçalho de cada card (.camp-card) que recolhe e expande o conteúdo.
 * - Recolher só põe a classe .camp-card-recolhido no card; o CSS esconde tudo menos o cabeçalho,
 *   então os scripts que preenchem as seções continuam achando os mesmos elementos.
 * - A escolha fica lembrada por seção no localStorage (chave = id do card ou título do h2).
 */
(function () {
    const app = document.getElementById('camp-app');
    if (!app) return;

    const PREFIXO = 'camp-recolhido:';

    function ler(chave) {
        try { return localStorage.getItem(PREFIXO + chave) === '1'; } catch (e) { return false; }
    }

    function gravar(chave, recolhido) {
        try {
            if (recolhido) localStorage.setItem(PREFIXO + chave, '1');
            else localStorage.removeItem(PREFIXO + chave);
        } catch (e) { /* navegador sem armazenamento: só não lembra */ }
    }

    app.querySelectorAll('.camp-card').forEach(function (card, i) {
        const cab = card.querySelector(':scope > .camp-card-cab');
        if (!cab || cab.querySelector('.js-card-recolher')) return;

        const h2 = cab.querySelector('h2');
        const titulo = h2 ? h2.textContent.trim() : '';
        const chave = card.id || titulo || ('card-' + i);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'camp-card-recolher js-card-recolher';
        btn.innerHTML = '<span aria-hidden="true">▾</span>';

        function aplicar(recolhido) {
            card.classList.toggle('camp-card-recolhido', recolhido);
            btn.setAttribute('aria-expanded', recolhido ? 'false' : 'true');
            const acao = recolhido ? 'Expandir' : 'Recolher';
            btn.setAttribute('aria-label', titulo ? acao + ' ' + titulo : acao + ' seção');
            btn.title = recolhido ? 'Expandir' : 'Recolher';
        }

        btn.addEventListener('click', function () {
            const recolhido = !card.classList.contains('camp-card-recolhido');
            aplicar(recolhido);
            gravar(chave, recolhido);
        });

        aplicar(ler(chave));
        cab.appendChild(btn);
    });
})();
