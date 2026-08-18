## Purchases

O pedido de compra deve ter uma tela inicial para exibir a lista de todos os pedidos presentes na tabela `purchases`. Nessa tela haverá um botão `Novo Pedido`. 

Ao clicar nesse botão, vamos para tela onde adicionaremos os produtos que queremos atualizar o estoque. Atenção: apenas produtos ativos (`p.active = true`) podem ser adicionados, independente da quantidade em estoque atual.. Cada item é referenciado em ``purchase_items pi``. 

Temos a possibilidade de escolher o fornecedor. Ainda não existe uma tela para cadastrarmos um supplier, mas no banco de dados tem dados já cadastrados para serem selecionados

Necessário para um pedido de compra:

- Fornecedor
- >= 1 item adicionado
- A quantidade que está sendo adicionada no item do pedido não pode ser zero ou negativa.

O item pode sim ser adicionado sem valor de custo. Pois pode ser usado para reajuste de estoque. 

Após tudo estiver completo, é só clicar no botão `Salvar`; por migration precisamos criar uma coluna em ``purchases`` denominada `status_id` (int: 1 - finalizado 0 - não finalizado). Ao salvar criará uma linha daquele pedido na tabela ``purchases``, que nós permite entrar para editar o pedido pela tela inicial, status = 0. Tem a opção de `Finalizar` direto sem salvar ``status = 1`` .

Com `status_id = 0` podemos trocar fornecedor, deletar e adicionar itens e mudar quantidade e valor.

Após **finalizar** é que os produtos irão ser atualizado na tabela `products p`. Após finalizado não podemos editar o pedido, por hora não vamos ter reabertura de pedido de compra (será feito pelo banco mesmo). 

Colunas afetadas:

- `cost_price`-> pode substituir, não vamos trabalhar com custo médio ainda.
- Soma ``pi.quantity`` + `p.stock_quantity`

Além disso, é preciso criar uma movimentação na tabela `stock_movements`. E implementar uma nova coluna `description` por migration. Essa coluna é importante pois ao finalizar o pedido de compra será armazenado a seguinte informação: *'Entrada de produto pelo pedido `id`'*. Para linhas já utilizadas no banco aplique uma descrição genérica. 

Atenção: é uma movimentação para cada item presente em ``purchase_items pi``. Ao finalizar, você atualiza `products` (N produtos) e insere N linhas em `stock_movements`. Isso precisa estar tudo dentro de uma transação — se algo falhar no meio, nada deve ser persistido. Vale um requisito técnico explícito, não só um teste.

## Testes

- Não permitir fornecedor null
- Garantir que pedido com status = 0 não crie movimentação e afete o estoque
- Não permitir adicionar produto inativo ao pedido.
- Não permitir salvar/editar pedido com `status = 1`.
- Quantidade do item deve ser maior que zero
- Não pode criar um pedido de compra sem item adicionado
- Garantir que aceita o valor de custo zerado
- Que ao finalizar o pedido de compra o estoque é atualizado
- Que ao finalizar o pedido de compra é criado uma movimentação de entrada
- Que todos os pedidos criados aparecem na tela inicial. 
- Não permitir finalizar um pedido já finalizado. 
- Atualização correta quando o mesmo produto aparece mais de uma vez no pedido (soma as quantidade).