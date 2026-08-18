/* Linhas de item do pedido de compra.
 *
 * O formulário manda a lista FINAL de itens num POST só — não há endpoint por
 * item, nem pedido meio-salvo no servidor enquanto o admin monta a lista. Este
 * arquivo só cuida do que é de tela: clonar linha, remover linha e mostrar as
 * contas enquanto se digita.
 *
 * Os subtotais aqui são CONFORTO, não verdade: quem calcula o que vai para o
 * banco é o PurchaseItem::totalValue() no servidor. */

(function () {
    const form = document.getElementById('gg-purchase-form');

    if (form === null) {
        return;
    }

    const lista = document.getElementById('gg-items-list');
    const vazio = document.getElementById('gg-items-empty');
    const total = document.getElementById('gg-items-total');
    const modelo = document.getElementById('gg-item-template');
    const botaoAdicionar = document.getElementById('gg-add-item');

    /* Continua de onde as linhas já desenhadas pararam: o índice só precisa ser
       único dentro do POST, então nunca é reaproveitado — remover a linha 1 de
       três deixa 0 e 2, e o PHP lê os dois do mesmo jeito. */
    let proximoIndice = lista.querySelectorAll('.gg-item-row').length;

    const dinheiro = (valor) =>
        'R$ ' + valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    /* "2,5" e "2.5" são a mesma coisa para quem digita no teclado brasileiro —
       o servidor faz a mesma troca ao ler o POST. */
    const numero = (texto) => {
        const valor = parseFloat((texto || '').replace(',', '.'));

        return Number.isFinite(valor) ? valor : 0;
    };

    function recalcular() {
        let somaDoPedido = 0;

        lista.querySelectorAll('.gg-item-row').forEach((linha) => {
            const subtotal =
                numero(linha.querySelector('.gg-js-quantity').value) *
                numero(linha.querySelector('.gg-js-cost').value);

            linha.querySelector('.gg-js-subtotal').textContent = dinheiro(subtotal);
            somaDoPedido += subtotal;
        });

        total.textContent = dinheiro(somaDoPedido);
        vazio.style.display = lista.querySelector('.gg-item-row') === null ? '' : 'none';
    }

    if (botaoAdicionar !== null) {
        botaoAdicionar.addEventListener('click', () => {
            const linha = modelo.content.firstElementChild.cloneNode(true);

            /* O modelo vem com items[0][...]; sem reescrever, duas linhas
               disputariam o mesmo índice e uma sobrescreveria a outra no POST. */
            linha.querySelectorAll('[name]').forEach((campo) => {
                campo.name = campo.name.replace(/items\[\d+\]/, 'items[' + proximoIndice + ']');
            });

            proximoIndice += 1;
            lista.appendChild(linha);
            recalcular();
            linha.querySelector('select').focus();
        });
    }

    /* Um ouvinte na lista inteira, e não um por linha: linha clonada depois
       funciona sem precisar registrar nada nova. */
    lista.addEventListener('click', (evento) => {
        if (evento.target.classList.contains('gg-js-remove')) {
            evento.target.closest('.gg-item-row').remove();
            recalcular();
        }
    });

    lista.addEventListener('input', recalcular);

    recalcular();
})();
