# Cart

A ideia é que o carrinho não seja uma outra tela, mas sim um modal que fica na parte direita da tela. Onde os intens serão adicionanos pela vitrine. No carrinho teremos as seguintes opções:

- Adicionar item
- Alterar quantidade do item
- Checkout (finalizar compra)

O carrinho ficará na nav. Se o usuário adicionar um item é preciso consultar o banco para pegar a quantidade total de intes adicionados no carrionho e exibir esse número na nav. Caso o usuário não esteja logado, ele será redirecionado para a tela de login para adicionar o item no carrinho. Caso ele não tenha cadastro, ele será redirecionado para a tela de cadastro. Na nav também será necessário exibir o valor total do carrinho. 

Dentro do carrinho caro não haja item, deverá ser exibido a mensagem: 
```
                🧺
        Your basket is empty
Add some fresh produce to get started.
```

Adicionando o item você verá ele na lista de itens com a quantidade e o preço total do item. Abaixo da lista de itens, será exibido o valor total do carrinho. O botão de checkout será exibido abaixo do valor total do carrinho. Mas só se existir items no carrinho. Caso não haja itens, o botão de checkout não será exibido.

Dentro do carrinho, o usuário poderá alterar a quantidade do item. Caso ele altere a quantidade para 0, o item será removido do carrinho. Caso ele altere a quantidade para um número maior que 0, o valor total do item será atualizado e o valor total do carrinho também será atualizado.

Para evitar que um duplo clique na hora de diminuir a quantidade do item quebre a aplicação, a IA sugeriu verificar se a consulta encontrou o item no carrinho: o `fetch()` do PDO retorna `false` quando não encontra nenhuma linha (não tem relação com o duplo clique em si, é assim que o PDO sinaliza "não achei nada"). Se não encontrou, o método encerra sem fazer nada, evitando o erro de tentar acessar um campo num valor booleano.