// Intercepta os forms de /cart/add (vitrine) e /cart/decrease (dentro do
// painel do carrinho) e troca o submit normal (navega a página inteira) por
// fetch(): o servidor responde com o carrinho em JSON (ver
// CartController::cartData()) e a gente redesenha só o contador na nav e o
// miolo do painel.
//
// Delegado no document (em vez de um listener por form) porque os forms de
// /cart/decrease são recriados a cada renderCart() — um listener direto
// morreria junto com o form antigo.
document.addEventListener('submit', function (event) {
    const form = event.target;
    const path = new URL(form.action).pathname;

    if (path !== '/cart/add' && path !== '/cart/decrease') {
        return;
    }

    event.preventDefault();
    submitCartForm(form);
});

async function submitCartForm(form) {
    let response;

    try {
        response = await fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'fetch' },
            body: new FormData(form),
        });
    } catch (erro) {
        // Falha de rede: sem carrinho atualizado pra mostrar, então cai no
        // comportamento de antes do AJAX (navegação normal).
        window.location.href = '/';
        return;
    }

    // Guard::isLoggedIn() falhou no servidor — manda pro login, igual o
    // header('Location: /login') fazia antes do AJAX existir.
    if (response.status === 401) {
        window.location.href = '/login';
        return;
    }

    if (!response.ok) {
        window.location.href = '/';
        return;
    }

    renderCart(await response.json());
}

function renderCart(carrinho) {
    // Badge redonda sobre o ícone da sacola (contagem) + preço total ao lado
    // — ver templates/layout/default.php pro mesmo markup no primeiro load.
    const contador = document.getElementById('cart-count');
    if (contador) {
        contador.textContent = carrinho.count;
    }

    const total = document.getElementById('cart-total');
    if (total) {
        total.textContent = carrinho.total;
    }

    const corpo = document.getElementById('cart-panel-body');
    if (corpo) {
        corpo.innerHTML = '';

        if (carrinho.items.length === 0) {
            corpo.append(criarEstadoVazio());
        } else {
            const lista = document.createElement('ul');
            lista.className = 'cart-panel__list';
            carrinho.items.forEach((item) => lista.append(criarItem(item)));
            corpo.append(lista);
        }
    }

    atualizarRodapeCarrinho(carrinho);
}

// Rodapé (total + "Finalizar compra") some com o carrinho vazio, igual ao
// mock — mesma condição que templates/layout/default.php usa no primeiro
// paint (`empty($itensCarrinho)`).
function atualizarRodapeCarrinho(carrinho) {
    const rodape = document.getElementById('cart-panel-footer');
    if (!rodape) {
        return;
    }

    rodape.style.display = carrinho.items.length === 0 ? 'none' : '';

    const contagem = document.getElementById('cart-panel-footer-count');
    if (contagem) {
        contagem.textContent = carrinho.count;
    }

    const valorTotal = document.getElementById('cart-panel-footer-total');
    if (valorTotal) {
        valorTotal.textContent = carrinho.total;
    }
}

function criarEstadoVazio() {
    const vazio = document.createElement('div');
    vazio.className = 'cart-panel__empty';
    vazio.innerHTML = `
        <p class="cart-panel__empty-icon">🧺</p>
        <p class="cart-panel__empty-title">Your basket is empty</p>
        <p class="cart-panel__empty-sub">Add some fresh produce to get started.</p>
    `;
    return vazio;
}

// Monta o <li> igual ao que o PHP gera em templates/layout/default.php —
// via createElement/append (não innerHTML) pra item.name nunca virar HTML.
function criarItem(item) {
    const li = document.createElement('li');
    li.className = 'cart-panel__item';

    const info = document.createElement('div');
    info.className = 'cart-panel__item-info';

    const nome = document.createElement('span');
    nome.className = 'cart-panel__item-name';
    nome.textContent = item.name;
    info.append(nome);

    // priceLabel vem nulo quando o produto foi removido (ver
    // CartController::cartData()) — sem preço, não tem "cada" pra mostrar.
    if (item.priceLabel) {
        const preco = document.createElement('span');
        preco.className = 'cart-panel__item-price';
        preco.textContent = `${item.priceLabel} cada`;
        info.append(preco);
    }

    li.append(info);

    const stepper = document.createElement('div');
    stepper.className = 'cart-panel__item-stepper';
    stepper.append(criarFormQuantidade('/cart/decrease', item.productId, '–'));

    const quantidade = document.createElement('span');
    quantidade.className = 'cart-panel__item-qty';
    quantidade.textContent = item.quantity;
    stepper.append(quantidade);

    stepper.append(criarFormQuantidade('/cart/add', item.productId, '+', '1'));
    li.append(stepper);

    if (item.lineLabel) {
        const totalLinha = document.createElement('span');
        totalLinha.className = 'cart-panel__item-total';
        totalLinha.textContent = item.lineLabel;
        li.append(totalLinha);
    }

    return li;
}

// quantidade: valor fixo mandado no form (ex.: "1" pro +) — quando omitido,
// o form só manda productId (caso do -, que sempre tira 1 no servidor).
function criarFormQuantidade(action, productId, rotulo, quantidade) {
    const form = document.createElement('form');
    form.action = action;
    form.method = 'post';
    form.style.display = 'inline';

    const inputProduto = document.createElement('input');
    inputProduto.type = 'hidden';
    inputProduto.name = 'productId';
    inputProduto.value = productId;
    form.append(inputProduto);

    if (quantidade !== undefined) {
        const inputQuantidade = document.createElement('input');
        inputQuantidade.type = 'hidden';
        inputQuantidade.name = 'quantity';
        inputQuantidade.value = quantidade;
        form.append(inputQuantidade);
    }

    const botao = document.createElement('button');
    botao.type = 'submit';
    botao.textContent = rotulo;
    form.append(botao);

    return form;
}
