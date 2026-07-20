<?php

declare(strict_types=1);

namespace User\Greengrocers\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schema inicial do sistema de vendas de hortifruti.
 *
 * Montado com o Schema Builder do DBAL (e não SQL cru) para que o mesmo arquivo
 * rode tanto no SQLite quanto no Postgres — ver a seção "Migrations" do README.
 *
 * Duas convenções que valem para o schema inteiro:
 *
 * - DINHEIRO é DECIMAL(10,2), nunca float. Float não representa 0,10 exatamente,
 *   e somar centavos errados num sistema de vendas é dívida garantida.
 * - QUANTIDADE é DECIMAL(10,3), nunca inteiro. É hortifruti: vende-se 0,5 kg de
 *   tomate. Quantidade inteira não conseguiria representar a venda mais comum.
 */
final class Version20260718143822 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schema inicial: usuários, produtos, compras, carrinho, vendas e movimentações de estoque.';
    }

    public function up(Schema $schema): void
    {
        $this->createUsers($schema);
        $this->createCategories($schema);
        $this->createSuppliers($schema);
        $this->createPaymentMethods($schema);
        $this->createProducts($schema);
        $this->createPurchases($schema);
        $this->createPurchaseItems($schema);
        $this->createCart($schema);
        $this->createSales($schema);
        $this->createSaleItems($schema);
        $this->createStockMovements($schema);
    }

    public function down(Schema $schema): void
    {
        // Ordem inversa da criação: uma tabela não pode cair antes de quem a referencia.
        $schema->dropTable('stock_movements');
        $schema->dropTable('sale_items');
        $schema->dropTable('sales');
        $schema->dropTable('cart');
        $schema->dropTable('purchase_items');
        $schema->dropTable('purchases');
        $schema->dropTable('products');
        $schema->dropTable('payment_methods');
        $schema->dropTable('suppliers');
        $schema->dropTable('categories');
        $schema->dropTable('users');
    }

    /** Clientes e administradores na mesma tabela, separados pela coluna `type`. */
    private function createUsers(Schema $schema): void
    {
        $table = $schema->createTable('users');

        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 50]);
        $table->addColumn('email', Types::STRING, ['length' => 255]);

        // Argon2id gera hash de ~97 caracteres. O VARCHAR(60) que se vê por aí é
        // dimensionado para bcrypt e truncaria o hash — quebrando o login em silêncio.
        $table->addColumn('password', Types::STRING, ['length' => 255]);

        // 0 = admin, 1 = cliente. O CHECK impede que um terceiro valor entre por engano.
        $table->addColumn('type', Types::SMALLINT, [
            'columnDefinition' => 'SMALLINT NOT NULL DEFAULT 1 CHECK (type IN (0, 1))',
        ]);

        $table->addColumn('address', Types::STRING, ['length' => 255, 'notnull' => false]);
        $table->addColumn('phone', Types::STRING, ['length' => 20, 'notnull' => false]);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);

        $table->setPrimaryKey(['id']);

        // O e-mail identifica o usuário no login: duplicata tornaria o login ambíguo.
        $table->addUniqueIndex(['email'], 'uniq_users_email');
    }

    private function createCategories(Schema $schema): void
    {
        $table = $schema->createTable('categories');

        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 100]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['name'], 'uniq_categories_name');
    }

    private function createSuppliers(Schema $schema): void
    {
        $table = $schema->createTable('suppliers');

        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 150]);

        // Só os 14 dígitos, sem pontuação — formatação é responsabilidade da View.
        $table->addColumn('cnpj', Types::STRING, ['length' => 14, 'notnull' => false]);

        $table->addColumn('address', Types::STRING, ['length' => 255, 'notnull' => false]);
        $table->addColumn('phone', Types::STRING, ['length' => 20, 'notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['cnpj'], 'uniq_suppliers_cnpj');
    }

    /** Formas de pagamento (TIPO_PAG no documento original). */
    private function createPaymentMethods(Schema $schema): void
    {
        $table = $schema->createTable('payment_methods');

        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 50]);

        // Desativar em vez de apagar: vendas antigas continuam apontando para a forma
        // de pagamento usada na época, mesmo que ela não seja mais oferecida.
        $table->addColumn('active', Types::BOOLEAN, ['default' => true]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['name'], 'uniq_payment_methods_name');
    }

    private function createProducts(Schema $schema): void
    {
        $table = $schema->createTable('products');

        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('name', Types::STRING, ['length' => 150]);
        $table->addColumn('category_id', Types::INTEGER);

        // Unidade de medida: 'kg', 'un', 'mc' (maço)...
        $table->addColumn('unit', Types::STRING, ['length' => 10]);

        $table->addColumn('sale_price', Types::DECIMAL, ['precision' => 10, 'scale' => 2]);
        $table->addColumn('cost_price', Types::DECIMAL, ['precision' => 10, 'scale' => 2, 'default' => 0]);
        $table->addColumn('stock_quantity', Types::DECIMAL, ['precision' => 10, 'scale' => 3, 'default' => 0]);

        // EAN-13. Nem todo hortifruti a granel tem código de barras, por isso opcional.
        $table->addColumn('ean', Types::STRING, ['length' => 13, 'notnull' => false]);

        $table->addColumn('active', Types::BOOLEAN, ['default' => true]);
        $table->addColumn('image', Types::STRING, ['length' => 255, 'notnull' => false]);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['ean'], 'uniq_products_ean');
        $table->addIndex(['category_id'], 'idx_products_category');

        // RESTRICT: apagar uma categoria que ainda tem produtos deixaria os produtos
        // órfãos. O admin precisa reclassificar os produtos antes.
        $table->addForeignKeyConstraint('categories', ['category_id'], ['id'], ['onDelete' => 'RESTRICT']);
    }

    /** Compras junto ao fornecedor — a entrada de estoque (ORDERS no documento). */
    private function createPurchases(Schema $schema): void
    {
        $table = $schema->createTable('purchases');

        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('supplier_id', Types::INTEGER);

        // O admin que lançou a entrada.
        $table->addColumn('user_id', Types::INTEGER);

        $table->addColumn('purchased_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('total_value', Types::DECIMAL, ['precision' => 10, 'scale' => 2]);
        $table->addColumn('notes', Types::TEXT, ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['supplier_id'], 'idx_purchases_supplier');
        $table->addIndex(['user_id'], 'idx_purchases_user');

        // RESTRICT nos dois: uma compra é documento fiscal/histórico. Apagar o
        // fornecedor ou o admin não pode dissolver o registro da compra.
        $table->addForeignKeyConstraint('suppliers', ['supplier_id'], ['id'], ['onDelete' => 'RESTRICT']);
        $table->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'RESTRICT']);
    }

    private function createPurchaseItems(Schema $schema): void
    {
        $table = $schema->createTable('purchase_items');

        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('purchase_id', Types::INTEGER);
        $table->addColumn('product_id', Types::INTEGER);

        $table->addColumn('quantity', Types::DECIMAL, ['precision' => 10, 'scale' => 3]);

        // Custo unitário pago nesta compra, congelado. É o que alimenta o custo médio.
        $table->addColumn('cost_value', Types::DECIMAL, ['precision' => 10, 'scale' => 2]);

        $table->addColumn('total_value', Types::DECIMAL, ['precision' => 10, 'scale' => 2]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['purchase_id'], 'idx_purchase_items_purchase');
        $table->addIndex(['product_id'], 'idx_purchase_items_product');

        // CASCADE: o item não existe sem a compra que o contém.
        $table->addForeignKeyConstraint('purchases', ['purchase_id'], ['id'], ['onDelete' => 'CASCADE']);
        $table->addForeignKeyConstraint('products', ['product_id'], ['id'], ['onDelete' => 'RESTRICT']);
    }

    /** Carrinho do cliente: uma linha por produto, agrupado pelo `user_id`. */
    private function createCart(Schema $schema): void
    {
        $table = $schema->createTable('cart');

        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('user_id', Types::INTEGER);
        $table->addColumn('product_id', Types::INTEGER);
        $table->addColumn('quantity', Types::DECIMAL, ['precision' => 10, 'scale' => 3]);
        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE);

        $table->setPrimaryKey(['id']);

        // Uma linha por produto por cliente: permite somar a quantidade num upsert
        // em vez de acumular linhas repetidas do mesmo produto.
        $table->addUniqueIndex(['user_id', 'product_id'], 'uniq_cart_user_product');

        // CASCADE nos dois: carrinho é estado transitório, não histórico. Sumiu o
        // cliente ou o produto, a linha do carrinho não tem mais sentido.
        $table->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);
        $table->addForeignKeyConstraint('products', ['product_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    /** Pedido de venda ao cliente. */
    private function createSales(Schema $schema): void
    {
        $table = $schema->createTable('sales');

        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('user_id', Types::INTEGER);

        // O documento marcava STATUS como FK, mas sem tabela correspondente. Como o
        // conjunto de valores é fechado e raramente muda, um CHECK resolve sem exigir
        // JOIN em toda consulta de pedido.
        $table->addColumn('status', Types::STRING, [
            'columnDefinition' => "VARCHAR(20) NOT NULL DEFAULT 'pendente' CHECK (status IN "
                . "('pendente', 'confirmado', 'separando', 'enviado', 'entregue', 'cancelado'))",
        ]);

        // Cópia do endereço no momento do pedido: se o cliente mudar de casa depois,
        // o pedido antigo continua mostrando para onde foi realmente entregue.
        $table->addColumn('delivery_address', Types::STRING, ['length' => 255]);

        $table->addColumn('payment_method_id', Types::INTEGER);
        $table->addColumn('total_value', Types::DECIMAL, ['precision' => 10, 'scale' => 2]);
        $table->addColumn('ordered_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('notes', Types::TEXT, ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['user_id'], 'idx_sales_user');
        $table->addIndex(['payment_method_id'], 'idx_sales_payment_method');
        $table->addIndex(['status'], 'idx_sales_status');

        $table->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'RESTRICT']);
        $table->addForeignKeyConstraint('payment_methods', ['payment_method_id'], ['id'], ['onDelete' => 'RESTRICT']);
    }

    private function createSaleItems(Schema $schema): void
    {
        $table = $schema->createTable('sale_items');

        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('sale_id', Types::INTEGER);
        $table->addColumn('product_id', Types::INTEGER);
        $table->addColumn('quantity', Types::DECIMAL, ['precision' => 10, 'scale' => 3]);

        // Preço CONGELADO no momento da venda. Se o preço do produto subir amanhã, a
        // nota fiscal de ontem não pode mudar junto.
        $table->addColumn('unit_price', Types::DECIMAL, ['precision' => 10, 'scale' => 2]);

        $table->addColumn('subtotal', Types::DECIMAL, ['precision' => 10, 'scale' => 2]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['sale_id'], 'idx_sale_items_sale');
        $table->addIndex(['product_id'], 'idx_sale_items_product');

        $table->addForeignKeyConstraint('sales', ['sale_id'], ['id'], ['onDelete' => 'CASCADE']);
        $table->addForeignKeyConstraint('products', ['product_id'], ['id'], ['onDelete' => 'RESTRICT']);
    }

    /** Livro-caixa de auditoria: todo movimento de estoque passa por aqui. */
    private function createStockMovements(Schema $schema): void
    {
        $table = $schema->createTable('stock_movements');

        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('product_id', Types::INTEGER);

        $table->addColumn('type', Types::STRING, [
            'columnDefinition' => "VARCHAR(10) NOT NULL CHECK (type IN ('in', 'out', 'adjustment'))",
        ]);

        // Assinada: ajuste de inventário pode ser negativo (perda, quebra).
        $table->addColumn('quantity', Types::DECIMAL, ['precision' => 10, 'scale' => 3]);

        $table->addColumn('reference_type', Types::STRING, [
            'columnDefinition' => "VARCHAR(20) NOT NULL CHECK (reference_type IN "
                . "('purchase', 'sale', 'manual_adjustment'))",
        ]);

        // Referência POLIMÓRFICA: aponta para `purchases` ou `sales` conforme o
        // reference_type. Sem FK física — banco relacional não faz FK condicional.
        // A integridade fica com a aplicação. Nulo quando é ajuste manual.
        $table->addColumn('reference_id', Types::INTEGER, ['notnull' => false]);

        $table->addColumn('moved_at', Types::DATETIME_IMMUTABLE);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['product_id'], 'idx_stock_movements_product');
        $table->addIndex(['reference_type', 'reference_id'], 'idx_stock_movements_reference');

        // RESTRICT: a auditoria de estoque não pode evaporar junto com o produto.
        $table->addForeignKeyConstraint('products', ['product_id'], ['id'], ['onDelete' => 'RESTRICT']);
    }
}
