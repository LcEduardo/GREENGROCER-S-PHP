<?php

declare(strict_types=1);

namespace User\Greengrocers\View;

class View
{
    private string $templatesPath;

    public function __construct(?string $templatesPath = null)
    {
        // Por padrão aponta para a pasta templates/ na raiz do projeto
        $this->templatesPath = $templatesPath ?? __DIR__ . '/../../templates';
    }

    /**
     * Renderiza um template .php injetando os dados e o encaixa no layout.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): void
    {
        // As chaves do array viram variáveis: ['title' => 'x'] -> $title = 'x'
        extract($data);

        // Captura o HTML gerado pelo template numa variável ($content)
        ob_start();
        require "{$this->templatesPath}/{$template}.php";
        $content = ob_get_clean();

        // Executa o layout, que injeta $content dentro do <body>
        require "{$this->templatesPath}/layout/default.php";
    }

    /**
     * Renderiza um template SEM o layout e DEVOLVE o HTML em vez de imprimir.
     *
     * É o que permite um pedaço de tela ter uma origem só. A lista de
     * movimentações de estoque chega por dois caminhos — o primeiro paint, que
     * vem dentro da página inteira, e cada rolagem seguinte, que volta por
     * fetch — e os dois passam por aqui. Sem isso, as linhas da tabela seriam
     * escritas duas vezes: uma em PHP e outra em JavaScript, para se
     * desencontrarem no primeiro campo novo.
     *
     * @param array<string, mixed> $data
     */
    public function partial(string $template, array $data = []): string
    {
        extract($data);

        ob_start();
        require "{$this->templatesPath}/{$template}.php";

        return ob_get_clean();
    }
}
