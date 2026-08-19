<?php

require_once '../../Config/database.php';


/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

$busca = trim($_GET['busca'] ?? '');
$categoriaId = $_GET['categoria'] ?? '';
$status = $_GET['status'] ?? '';


/*
|--------------------------------------------------------------------------
| BUSCAR CATEGORIAS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT id, nome
    FROM categorias
    ORDER BY nome
");

$categorias = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| BUSCAR PRODUTOS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.id,
        p.codigo,
        p.nome,
        p.unidade,
        p.estoque_minimo,
        p.localizacao,

        c.nome AS categoria,


        /*
        |--------------------------------------------------------------------------
        | SALDO DISPONÍVEL - ESTOQUE GERAL
        |--------------------------------------------------------------------------
        */

        COALESCE(
            SUM(
                CASE

                    WHEN m.tipo_estoque = 'geral'
                         AND m.tipo = 'entrada'
                        THEN m.quantidade

                    WHEN m.tipo_estoque = 'geral'
                         AND m.tipo = 'saida'
                        THEN -m.quantidade

                    ELSE 0

                END
            ),
            0
        ) AS saldo_geral,


        /*
        |--------------------------------------------------------------------------
        | SALDO RESERVADO - OBRAS
        |--------------------------------------------------------------------------
        */

        COALESCE(
            SUM(
                CASE

                    WHEN m.tipo_estoque = 'obra'
                         AND m.tipo = 'entrada'
                        THEN m.quantidade

                    WHEN m.tipo_estoque = 'obra'
                         AND m.tipo = 'saida'
                        THEN -m.quantidade

                    ELSE 0

                END
            ),
            0
        ) AS saldo_reservado,


        /*
        |--------------------------------------------------------------------------
        | SALDO FÍSICO TOTAL
        |--------------------------------------------------------------------------
        */

        COALESCE(
            SUM(
                CASE

                    WHEN m.tipo = 'entrada'
                        THEN m.quantidade

                    WHEN m.tipo = 'saida'
                        THEN -m.quantidade

                    ELSE 0

                END
            ),
            0
        ) AS saldo_fisico


    FROM produtos p


    LEFT JOIN categorias c
        ON c.id = p.categoria_id


    LEFT JOIN movimentacoes m
        ON m.produto_id = p.id


    WHERE p.ativo = 1
";


/*
|--------------------------------------------------------------------------
| PARÂMETROS
|--------------------------------------------------------------------------
*/

$parametros = [];


/*
|--------------------------------------------------------------------------
| PESQUISA
|--------------------------------------------------------------------------
*/

if ($busca !== '') {

    $sql .= "
        AND (
            p.nome LIKE :busca_nome
            OR p.codigo LIKE :busca_codigo
        )
    ";

    $parametros['busca_nome'] =
        '%' . $busca . '%';

    $parametros['busca_codigo'] =
        '%' . $busca . '%';
}


/*
|--------------------------------------------------------------------------
| CATEGORIA
|--------------------------------------------------------------------------
*/

if ($categoriaId !== '') {

    $sql .= "
        AND p.categoria_id = :categoria
    ";

    $parametros['categoria'] =
        $categoriaId;
}


/*
|--------------------------------------------------------------------------
| AGRUPAMENTO
|--------------------------------------------------------------------------
*/

$sql .= "
    GROUP BY
        p.id,
        p.codigo,
        p.nome,
        p.unidade,
        p.estoque_minimo,
        p.localizacao,
        c.nome
";


/*
|--------------------------------------------------------------------------
| FILTRO DE ESTOQUE
|--------------------------------------------------------------------------
|
| O estoque baixo considera somente o material disponível
| no estoque geral.
|
*/

if ($status === 'baixo') {

    $sql .= "
        HAVING saldo_geral <= p.estoque_minimo
    ";

} elseif ($status === 'normal') {

    $sql .= "
        HAVING saldo_geral > p.estoque_minimo
    ";
}


/*
|--------------------------------------------------------------------------
| ORDENAÇÃO
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY p.nome ASC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($parametros);

$produtos = $stmt->fetchAll();


include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>


<main class="content">


    <!-- =========================
         MENSAGENS
    ========================== -->

    <?php if (isset($_GET['editado'])): ?>

        <div class="alert-success">

            <i class="bi bi-check-circle"></i>

            Produto atualizado com sucesso!

        </div>

    <?php endif; ?>


    <?php if (isset($_GET['desativado'])): ?>

        <div class="alert-success">

            <i class="bi bi-check-circle"></i>

            Produto desativado com sucesso!

        </div>

    <?php endif; ?>



    <!-- =========================
         CABEÇALHO
    ========================== -->

    <div class="page-header products-header">

        <div>

            <h1>
                Produtos
            </h1>

            <p>
                Gerencie os materiais do almoxarifado.
            </p>

        </div>


        <a
            href="novo.php"
            class="btn-primary"
        >

            <i class="bi bi-plus-lg"></i>

            Novo produto

        </a>

    </div>



    <!-- =========================
         FILTROS
    ========================== -->

    <form
        method="GET"
        action="index.php"
        class="products-filters"
    >


        <!-- PESQUISA -->

        <div class="product-search">

            <i class="bi bi-search"></i>

            <input
                type="text"
                name="busca"
                placeholder="Pesquisar produto..."
                value="<?= htmlspecialchars($busca) ?>"
            >

        </div>



        <!-- CATEGORIA -->

        <select name="categoria">

            <option value="">
                Todas as categorias
            </option>


            <?php foreach ($categorias as $categoria): ?>

                <option
                    value="<?= $categoria['id'] ?>"
                    <?= $categoriaId == $categoria['id']
                        ? 'selected'
                        : '' ?>
                >

                    <?= htmlspecialchars(
                        $categoria['nome']
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>



        <!-- STATUS -->

        <select name="status">

            <option value="">
                Todos os estoques
            </option>


            <option
                value="normal"
                <?= $status === 'normal'
                    ? 'selected'
                    : '' ?>
            >
                Estoque normal
            </option>


            <option
                value="baixo"
                <?= $status === 'baixo'
                    ? 'selected'
                    : '' ?>
            >
                Estoque baixo
            </option>

        </select>



        <!-- FILTRAR -->

        <button
            type="submit"
            class="filter-button"
        >

            <i class="bi bi-funnel"></i>

            Filtrar

        </button>



        <!-- LIMPAR -->

        <?php if (
            $busca !== '' ||
            $categoriaId !== '' ||
            $status !== ''
        ): ?>

            <a
                href="index.php"
                class="clear-filter"
            >

                <i class="bi bi-x-lg"></i>

                Limpar

            </a>

        <?php endif; ?>


    </form>



    <!-- =========================
         TABELA
    ========================== -->

    <section class="products-table">

        <table>

            <thead>

                <tr>

                    <th>Código</th>

                    <th>Produto</th>

                    <th>Categoria</th>

                    <th>
                        Disponível
                    </th>

                    <th>
                        Reservado
                    </th>

                    <th>
                        Físico
                    </th>

                    <th>
                        Unidade
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Ações
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php if (empty($produtos)): ?>


                <tr>

                    <td
                        colspan="9"
                        class="empty-products"
                    >

                        <i class="bi bi-search"></i>

                        Nenhum produto encontrado.

                    </td>

                </tr>


            <?php else: ?>


                <?php foreach ($produtos as $produto): ?>


                    <?php

                    /*
                    |--------------------------------------------------------------------------
                    | SALDOS
                    |--------------------------------------------------------------------------
                    */

                    $saldoGeral =
                        (float) $produto['saldo_geral'];

                    $saldoReservado =
                        (float) $produto['saldo_reservado'];

                    $saldoFisico =
                        (float) $produto['saldo_fisico'];


                    $estoqueMinimo =
                        (float) $produto['estoque_minimo'];


                    /*
                    |--------------------------------------------------------------------------
                    | STATUS
                    |--------------------------------------------------------------------------
                    */

                    $estoqueBaixo =
                        $saldoGeral <= $estoqueMinimo;

                    ?>


                    <tr>


                        <!-- =========================
                             CÓDIGO
                        ========================== -->

                        <td class="product-code">

                            <?= htmlspecialchars(
                                $produto['codigo']
                            ) ?>

                        </td>



                        <!-- =========================
                             PRODUTO
                        ========================== -->

                        <td>

                            <div class="table-product">

                                <div class="product-icon">

                                    <i class="bi bi-box"></i>

                                </div>


                                <span>

                                    <?= htmlspecialchars(
                                        $produto['nome']
                                    ) ?>

                                </span>

                            </div>

                        </td>



                        <!-- =========================
                             CATEGORIA
                        ========================== -->

                        <td>

                            <?= htmlspecialchars(
                                $produto['categoria']
                                ?? '-'
                            ) ?>

                        </td>



                        <!-- =========================
                             DISPONÍVEL
                        ========================== -->

                        <td
                            class="
                                stock-value
                                <?= $estoqueBaixo
                                    ? 'stock-low'
                                    : '' ?>
                            "
                        >

                            <strong>

                                <?= number_format(
                                    $saldoGeral,
                                    2,
                                    ',',
                                    '.'
                                ) ?>

                            </strong>

                        </td>



                        <!-- =========================
                             RESERVADO
                        ========================== -->

                        <td>

                            <?php if ($saldoReservado > 0): ?>

                                <span class="stock-reserved">

                                    <i class="bi bi-lock-fill"></i>

                                    <?= number_format(
                                        $saldoReservado,
                                        2,
                                        ',',
                                        '.'
                                    ) ?>

                                </span>

                            <?php else: ?>

                                <span class="stock-zero">

                                    0,00

                                </span>

                            <?php endif; ?>

                        </td>



                        <!-- =========================
                             FÍSICO
                        ========================== -->

                        <td class="stock-physical">

                            <?= number_format(
                                $saldoFisico,
                                2,
                                ',',
                                '.'
                            ) ?>

                        </td>



                        <!-- =========================
                             UNIDADE
                        ========================== -->

                        <td>

                            <?= htmlspecialchars(
                                $produto['unidade']
                            ) ?>

                        </td>



                        <!-- =========================
                             STATUS
                        ========================== -->

                        <td>


                            <?php if ($estoqueBaixo): ?>


                                <span
                                    class="
                                        stock-status
                                        status-low
                                    "
                                >

                                    Estoque baixo

                                </span>


                            <?php else: ?>


                                <span
                                    class="
                                        stock-status
                                        status-ok
                                    "
                                >

                                    Estoque normal

                                </span>


                            <?php endif; ?>


                        </td>



                        <!-- =========================
                             AÇÕES
                        ========================== -->

                        <td>

                            <div class="product-actions">


                                <!-- VISUALIZAR -->

                                <a
                                    href="detalhes.php?id=<?= $produto['id'] ?>"
                                    class="action-link view"
                                    title="Ver detalhes"
                                >

                                    <i class="bi bi-eye"></i>

                                </a>



                                <!-- EDITAR -->

                                <a
                                    href="editar.php?id=<?= $produto['id'] ?>"
                                    class="action-link edit"
                                    title="Editar produto"
                                >

                                    <i class="bi bi-pencil"></i>

                                </a>



                                <!-- DESATIVAR -->

                                <a
                                    href="desativar.php?id=<?= $produto['id'] ?>"
                                    class="action-link delete"
                                    title="Desativar produto"
                                    onclick="return confirm('Deseja realmente desativar este produto?');"
                                >

                                    <i class="bi bi-eye-slash"></i>

                                </a>


                            </div>

                        </td>


                    </tr>


                <?php endforeach; ?>


            <?php endif; ?>


            </tbody>

        </table>

    </section>


</main>


<?php include '../../Includes/footer.php'; ?>