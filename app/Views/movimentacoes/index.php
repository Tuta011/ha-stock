<?php

require_once '../../Config/database.php';


/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

$busca = trim($_GET['busca'] ?? '');
$tipo = $_GET['tipo'] ?? '';
$tipoEstoque = $_GET['tipo_estoque'] ?? '';


/*
|--------------------------------------------------------------------------
| CONSULTA BASE
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        m.id,
        m.tipo,
        m.tipo_estoque,
        m.obra_id,
        m.quantidade,
        m.fornecedor,
        m.documento,
        m.responsavel,
        m.destino,
        m.observacao,
        m.data_movimentacao,
        m.created_at,

        p.codigo,
        p.nome AS produto,
        p.unidade,

        o.codigo AS obra_codigo,
        o.nome AS obra_nome,

        c.nome AS cliente_nome

    FROM movimentacoes m

    INNER JOIN produtos p
        ON p.id = m.produto_id

    LEFT JOIN obras o
        ON o.id = m.obra_id

    LEFT JOIN clientes c
        ON c.id = o.cliente_id

    WHERE 1 = 1
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
            p.nome LIKE :busca
            OR p.codigo LIKE :busca
            OR m.responsavel LIKE :busca
            OR m.fornecedor LIKE :busca
            OR m.destino LIKE :busca
            OR o.nome LIKE :busca
            OR o.codigo LIKE :busca
            OR c.nome LIKE :busca
        )
    ";

    $parametros['busca'] = '%' . $busca . '%';
}


/*
|--------------------------------------------------------------------------
| FILTRO POR TIPO
|--------------------------------------------------------------------------
*/

if ($tipo === 'entrada' || $tipo === 'saida') {

    $sql .= "
        AND m.tipo = :tipo
    ";

    $parametros['tipo'] = $tipo;
}


/*
|--------------------------------------------------------------------------
| FILTRO POR TIPO DE ESTOQUE
|--------------------------------------------------------------------------
*/

if (
    $tipoEstoque === 'geral' ||
    $tipoEstoque === 'obra'
) {

    $sql .= "
        AND m.tipo_estoque = :tipo_estoque
    ";

    $parametros['tipo_estoque'] = $tipoEstoque;
}


/*
|--------------------------------------------------------------------------
| ORDENAÇÃO
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        m.data_movimentacao DESC,
        m.id DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($parametros);

$movimentacoes = $stmt->fetchAll();


include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>


<main class="content">


    <!-- =========================
         MENSAGENS
    ========================== -->

    <?php if (
        isset($_GET['entrada']) &&
        $_GET['entrada'] === 'sucesso'
    ): ?>

        <div class="alert-success">

            <i class="bi bi-check-circle"></i>

            Entrada registrada com sucesso!

        </div>

    <?php endif; ?>


    <?php if (
        isset($_GET['saida']) &&
        $_GET['saida'] === 'sucesso'
    ): ?>

        <div class="alert-success">

            <i class="bi bi-check-circle"></i>

            Saída registrada com sucesso!

        </div>

    <?php endif; ?>



    <!-- =========================
         CABEÇALHO
    ========================== -->

    <div class="page-header movimentacoes-header">

        <div>

            <h1>Movimentações</h1>

            <p>
                Acompanhe entradas e saídas do almoxarifado.
            </p>

        </div>


        <div class="movimentacoes-actions">

            <a
                href="entrada.php"
                class="btn-secondary"
            >

                <i class="bi bi-arrow-down-circle"></i>

                Nova entrada

            </a>


            <a
                href="saida.php"
                class="btn-primary"
            >

                <i class="bi bi-arrow-up-circle"></i>

                Nova saída

            </a>

        </div>

    </div>



    <!-- =========================
         FILTROS
    ========================== -->

    <form
        method="GET"
        action="index.php"
        class="movimentacoes-filtros"
    >


        <!-- PESQUISA -->

        <div class="product-search">

            <i class="bi bi-search"></i>

            <input
                type="text"
                name="busca"
                placeholder="Pesquisar movimentação..."
                value="<?= htmlspecialchars($busca) ?>"
            >

        </div>



        <!-- TIPO -->

        <select name="tipo">

            <option value="">
                Todos os tipos
            </option>


            <option
                value="entrada"
                <?= $tipo === 'entrada'
                    ? 'selected'
                    : '' ?>
            >
                Entradas
            </option>


            <option
                value="saida"
                <?= $tipo === 'saida'
                    ? 'selected'
                    : '' ?>
            >
                Saídas
            </option>

        </select>



        <!-- TIPO DE ESTOQUE -->

        <select name="tipo_estoque">

            <option value="">
                Todos os estoques
            </option>


            <option
                value="geral"
                <?= $tipoEstoque === 'geral'
                    ? 'selected'
                    : '' ?>
            >
                Estoque geral
            </option>


            <option
                value="obra"
                <?= $tipoEstoque === 'obra'
                    ? 'selected'
                    : '' ?>
            >
                Material de obra
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
            $tipo !== '' ||
            $tipoEstoque !== ''
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

    <section class="movimentacoes-table">

        <table>

            <thead>

                <tr>

                    <th>Produto</th>

                    <th>Tipo</th>

                    <th>Quantidade</th>

                    <th>Origem / Responsável</th>

                    <th>Destino</th>

                    <th>Data</th>

                    <th>Ações</th>

                </tr>

            </thead>


            <tbody>


            <?php if (empty($movimentacoes)): ?>


                <tr>

                    <td
                        colspan="7"
                        class="empty-table"
                    >

                        <i class="bi bi-search"></i>

                        Nenhuma movimentação encontrada.

                    </td>

                </tr>


            <?php else: ?>


                <?php foreach ($movimentacoes as $movimentacao): ?>


                    <?php

                    $entrada =
                        $movimentacao['tipo'] === 'entrada';

                    $obra =
                        $movimentacao['tipo_estoque'] === 'obra';

                    $quantidade =
                        number_format(
                            $movimentacao['quantidade'],
                            2,
                            ',',
                            '.'
                        );

                    ?>


                    <tr>


                        <!-- =========================
                             PRODUTO
                        ========================== -->

                        <td>

                            <div class="table-product">

                                <div class="product-icon">

                                    <i class="bi bi-box"></i>

                                </div>


                                <div class="movement-product-info">

                                    <strong>

                                        <?= htmlspecialchars(
                                            $movimentacao['produto']
                                        ) ?>

                                    </strong>


                                    <small>

                                        <?= htmlspecialchars(
                                            $movimentacao['codigo']
                                        ) ?>

                                    </small>

                                </div>

                            </div>

                        </td>



                        <!-- =========================
                             TIPO
                        ========================== -->

                        <td>

                            <div class="movement-type-wrapper">


                                <?php if ($entrada): ?>


                                    <span
                                        class="
                                            movement-status
                                            movement-entry
                                        "
                                    >

                                        Entrada

                                    </span>


                                <?php else: ?>


                                    <span
                                        class="
                                            movement-status
                                            movement-exit
                                        "
                                    >

                                        Saída

                                    </span>


                                <?php endif; ?>



                                <?php if ($obra): ?>


                                    <span
                                        class="
                                            movement-stock
                                            movement-work
                                        "
                                    >

                                        <i class="bi bi-buildings"></i>

                                        Obra

                                    </span>


                                <?php else: ?>


                                    <span
                                        class="
                                            movement-stock
                                            movement-general
                                        "
                                    >

                                        Geral

                                    </span>


                                <?php endif; ?>


                            </div>

                        </td>



                        <!-- =========================
                             QUANTIDADE
                        ========================== -->

                        <td
                            class="
                                movement-quantity
                                <?= $entrada
                                    ? 'entrada'
                                    : 'saida' ?>
                            "
                        >

                            <?= $entrada ? '+' : '-' ?>

                            <?= $quantidade ?>

                            <?= htmlspecialchars(
                                $movimentacao['unidade']
                            ) ?>

                        </td>



                        <!-- =========================
                             ORIGEM / RESPONSÁVEL
                        ========================== -->

                        <td>


                            <?php if ($entrada): ?>


                                <?= htmlspecialchars(
                                    $movimentacao['fornecedor']
                                    ?: '-'
                                ) ?>


                            <?php else: ?>


                                <?= htmlspecialchars(
                                    $movimentacao['responsavel']
                                    ?: '-'
                                ) ?>


                            <?php endif; ?>


                        </td>



                        <!-- =========================
                             DESTINO
                        ========================== -->

                        <td>


                            <?php if ($obra): ?>


                                <?php if ($movimentacao['obra_id']): ?>


                                    <a
                                        href="../obras/detalhes.php?id=<?= $movimentacao['obra_id'] ?>"
                                        class="movement-work-link"
                                    >


                                        <div class="movement-work-icon">

                                            <i class="bi bi-buildings"></i>

                                        </div>


                                        <div>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $movimentacao['obra_nome']
                                                    ?: 'Obra'
                                                ) ?>

                                            </strong>


                                            <?php if (
                                                $movimentacao['cliente_nome']
                                            ): ?>

                                                <small>

                                                    <?= htmlspecialchars(
                                                        $movimentacao['cliente_nome']
                                                    ) ?>

                                                </small>

                                            <?php endif; ?>


                                        </div>

                                    </a>


                                <?php else: ?>


                                    <span class="movement-work-warning">

                                        <i class="bi bi-exclamation-triangle"></i>

                                        Obra não informada

                                    </span>


                                <?php endif; ?>


                            <?php elseif ($entrada): ?>


                                <span class="movement-general-destination">

                                    <i class="bi bi-box-seam"></i>

                                    Almoxarifado

                                </span>


                            <?php else: ?>


                                <?= htmlspecialchars(
                                    ucfirst(
                                        $movimentacao['destino']
                                        ?: '-'
                                    )
                                ) ?>


                            <?php endif; ?>


                        </td>



                        <!-- =========================
                             DATA
                        ========================== -->

                        <td>

                            <?= date(
                                'd/m/Y',
                                strtotime(
                                    $movimentacao['data_movimentacao']
                                )
                            ) ?>

                        </td>



                        <!-- =========================
                             AÇÕES
                        ========================== -->

                        <td>

                            <a
                                href="detalhes.php?id=<?= $movimentacao['id'] ?>"
                                class="movement-detail-button"
                                title="Ver detalhes"
                            >

                                <i class="bi bi-eye"></i>

                            </a>

                        </td>


                    </tr>


                <?php endforeach; ?>


            <?php endif; ?>


            </tbody>

        </table>

    </section>


</main>


<?php include '../../Includes/footer.php'; ?>