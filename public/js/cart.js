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
    const contador = document.getElementById('cart-count');
    if (contador) {
        contador.textContent = `Carrinho (${carrinho.count})`;
    }

    const corpo = document.getElementById('cart-panel-body');
    if (!corpo) {
        return;
    }

    corpo.innerHTML = '';

    if (carrinho.items.length === 0) {
        corpo.append(criarEstadoVazio());
        return;
    }

    const lista = document.createElement('ul');
    carrinho.items.forEach((item) => lista.append(criarItem(item)));
    corpo.append(lista);
}

function criarEstadoVazio() {
    const vazio = document.createElement('div');
    vazio.className = 'cart-panel__empty';
    vazio.innerHTML = `
        <p>🧺</p>
        <p>Your basket is empty</p>
        <p>Add some fresh produce to get started.</p>
    `;
    return vazio;
}

// Monta o <li> igual ao que o PHP gera em templates/layout/default.php —
// via createElement/append (não innerHTML) pra item.name nunca virar HTML.
function criarItem(item) {
    const li = document.createElement('li');
    li.append(`${item.name} — ${item.quantity} `);

    const form = document.createElement('form');
    form.action = '/cart/decrease';
    form.method = 'post';
    form.style.display = 'inline';

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'productId';
    input.value = item.productId;

    const botao = document.createElement('button');
    botao.type = 'submit';
    botao.textContent = '-';

    form.append(input, botao);
    li.append(form);

    return li;
}
