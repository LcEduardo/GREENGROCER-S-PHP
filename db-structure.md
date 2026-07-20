# Estrutura do Banco de Dados — Sistema de Vendas de Frutas e Legumes

Documento gerado a partir do schema implementado em `database/migrations/Version20260718143822.php`. Reflete os nomes de tabela/coluna, tipos e relacionamentos realmente criados pela migration.

---

## 1. users
Tabela única para clientes e administradores.

| Campo | Tipo/Observação |
|---|---|
| **id** (PK) | INTEGER, autoincrement |
| name | VARCHAR(50) |
| email | VARCHAR(255), **UNIQUE** |
| password | VARCHAR(255) — dimensionado para hash Argon2id (~97 chars), não bcrypt |
| type | SMALLINT, CHECK IN (0, 1) — `0` (adm) / `1` (cliente), default `1` |
| address | VARCHAR(255), opcional |
| phone | VARCHAR(20), opcional |
| created_at | DATETIME_IMMUTABLE |

---

## 2. categories
| Campo | Tipo/Observação |
|---|---|
| **id** (PK) | INTEGER, autoincrement |
| name | VARCHAR(100), **UNIQUE** |

---

## 3. suppliers
| Campo | Tipo/Observação |
|---|---|
| **id** (PK) | INTEGER, autoincrement |
| name | VARCHAR(150) |
| cnpj | VARCHAR(14), opcional, **UNIQUE** — só dígitos, sem pontuação |
| address | VARCHAR(255), opcional |
| phone | VARCHAR(20), opcional |

---

## 4. payment_methods
Formas de pagamento (`TIPO_PAG` no documento original).

| Campo | Tipo/Observação |
|---|---|
| **id** (PK) | INTEGER, autoincrement |
| name | VARCHAR(50), **UNIQUE** |
| active | BOOLEAN, default `true` — desativa em vez de apagar, para não quebrar vendas antigas |

---

## 5. products
| Campo | Tipo/Observação |
|---|---|
| **id** (PK) | INTEGER, autoincrement |
| name | VARCHAR(150) |
| category_id (FK) | → `categories.id`, `ON DELETE RESTRICT` |
| unit | VARCHAR(10) — unidade de medida (`kg`, `un`, `mc`...) |
| sale_price | DECIMAL(10,2) |
| cost_price | DECIMAL(10,2), default `0` |
| stock_quantity | DECIMAL(10,3), default `0` |
| ean | VARCHAR(13), opcional, **UNIQUE** |
| active | BOOLEAN, default `true` |
| image | VARCHAR(255), opcional |
| created_at | DATETIME_IMMUTABLE |

---

## 6. purchases
Compras junto ao fornecedor — a entrada de estoque (`ORDERS` no documento original).

| Campo | Tipo/Observação |
|---|---|
| **id** (PK) | INTEGER, autoincrement |
| supplier_id (FK) | → `suppliers.id`, `ON DELETE RESTRICT` — compra é documento histórico |
| user_id (FK) | → `users.id`, `ON DELETE RESTRICT` — admin que lançou a entrada |
| purchased_at | DATETIME_IMMUTABLE |
| total_value | DECIMAL(10,2) |
| notes | TEXT, opcional |

---

## 7. purchase_items
Itens da compra (`ITENS_ORDERS` no documento original).

| Campo | Tipo/Observação |
|---|---|
| **id** (PK) | INTEGER, autoincrement |
| purchase_id (FK) | → `purchases.id`, `ON DELETE CASCADE` |
| product_id (FK) | → `products.id`, `ON DELETE RESTRICT` |
| quantity | DECIMAL(10,3) |
| cost_value | DECIMAL(10,2) — custo unitário pago nesta compra, congelado |
| total_value | DECIMAL(10,2) |

---

## 8. cart
Carrinho do cliente (`CARRINHO` no documento original) — uma linha por produto, agrupado por `user_id`.

| Campo | Tipo/Observação |
|---|---|
| **id** (PK) | INTEGER, autoincrement |
| user_id (FK) | → `users.id`, `ON DELETE CASCADE` |
| product_id (FK) | → `products.id`, `ON DELETE CASCADE` |
| quantity | DECIMAL(10,3) |
| updated_at | DATETIME_IMMUTABLE |

> ✅ `UNIQUE (user_id, product_id)` já implementado (`uniq_cart_user_product`) — permite upsert em vez de linhas duplicadas do mesmo produto.

---

## 9. sales
Pedido de venda ao cliente.

| Campo | Tipo/Observação |
|---|---|
| **id** (PK) | INTEGER, autoincrement |
| user_id (FK) | → `users.id`, `ON DELETE RESTRICT` |
| status | VARCHAR(20), CHECK IN (`pendente`, `confirmado`, `separando`, `enviado`, `entregue`, `cancelado`), default `pendente` |
| delivery_address | VARCHAR(255) — cópia do endereço no momento do pedido |
| payment_method_id (FK) | → `payment_methods.id`, `ON DELETE RESTRICT` |
| total_value | DECIMAL(10,2) |
| ordered_at | DATETIME_IMMUTABLE |
| notes | TEXT, opcional |

---

## 10. sale_items
Itens da venda (`ITENS_SALES` no documento original).

| Campo | Tipo/Observação |
|---|---|
| **id** (PK) | INTEGER, autoincrement |
| sale_id (FK) | → `sales.id`, `ON DELETE CASCADE` |
| product_id (FK) | → `products.id`, `ON DELETE RESTRICT` |
| quantity | DECIMAL(10,3) |
| unit_price | DECIMAL(10,2) — preço congelado no momento da venda |
| subtotal | DECIMAL(10,2) |

---

## 11. stock_movements
Livro-caixa de auditoria (`MOVIMENTACOES_ESTOQUE` no documento original) — todo movimento de estoque passa por aqui.

| Campo | Tipo/Observação |
|---|---|
| **id** (PK) | INTEGER, autoincrement |
| product_id (FK) | → `products.id`, `ON DELETE RESTRICT` |
| type | VARCHAR(10), CHECK IN (`in`, `out`, `adjustment`) |
| quantity | DECIMAL(10,3) — assinada, pode ser negativa (perda, quebra) |
| reference_type | VARCHAR(20), CHECK IN (`purchase`, `sale`, `manual_adjustment`) |
| reference_id | INTEGER, opcional — id de `purchases` ou `sales`, conforme `reference_type`; nulo em ajuste manual |
| moved_at | DATETIME_IMMUTABLE |

> ⚠️ `reference_id` é referência **polimórfica** (aponta para `purchases` ou `sales`) — sem FK física, pois banco relacional não impõe FK condicional. A integridade fica com a camada de aplicação.

---

## Relacionamentos (resumo)

- `products.category_id` → `categories.id` (RESTRICT)
- `cart.user_id` → `users.id` (CASCADE)
- `cart.product_id` → `products.id` (CASCADE)
- `purchases.supplier_id` → `suppliers.id` (RESTRICT)
- `purchases.user_id` → `users.id` (RESTRICT, admin)
- `purchase_items.purchase_id` → `purchases.id` (CASCADE)
- `purchase_items.product_id` → `products.id` (RESTRICT)
- `sales.user_id` → `users.id` (RESTRICT, cliente)
- `sales.payment_method_id` → `payment_methods.id` (RESTRICT)
- `sale_items.sale_id` → `sales.id` (CASCADE)
- `sale_items.product_id` → `products.id` (RESTRICT)
- `stock_movements.product_id` → `products.id` (RESTRICT)
- `stock_movements.reference_id` → `purchases.id` **ou** `sales.id` (dependendo do `reference_type`, sem FK física)

---

## Pontos em aberto

- **Momento da baixa de estoque** — definir se a baixa em `stock_movements`/`products.stock_quantity` ocorre no momento do pedido (`sales`) ou apenas após confirmação manual do pagamento pelo admin. Ainda não resolvido em schema nem em código.
