<?php

require_once '../../Config/database.php';


/*
|--------------------------------------------------------------------------
| FILTROS DE DATA
|--------------------------------------------------------------------------
*/

$dataInicial = $_GET['data_inicial'] ?? '';
$dataFinal = $_GET['data_final'] ?? '';


/*
|--------------------------------------------------------------------------
| MONTA FILTRO SQL
|--------------------------------------------------------------------------
*/

$filtroData = '';
$parametros = [];

if ($dataInicial !== '') {

    $filtroData .= "
        AND DATE(data_movimentacao) >= :data_inicial
    ";

    $parametros['data_inicial'] = $dataInicial;
}

if ($dataFinal !== '') {

    $filtroData .= "
        AND DATE(data_movimentacao) <= :data_final
    ";

    $parametros['data_final'] = $dataFinal;
}


/*
|--------------------------------------------------------------------------
| TOTAL DE ENTRADAS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        COALESCE(SUM(quantidade), 0)

    FROM movimentacoes

    WHERE tipo = 'entrada'

    $filtroData
";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);

$totalEntradas = (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| TOTAL DE SAÍDAS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        COALESCE(SUM(quantidade), 0)

    FROM movimentacoes

    WHERE tipo = 'saida'

    $filtroData
";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);

$totalSaidas = (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| PRODUTOS MOVIMENTADOS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        COUNT(DISTINCT produto_id)

    FROM movimentacoes

    WHERE 1 = 1

    $filtroData
";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);

$produtosMovimentados = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| PRODUTOS COM ESTOQUE BAIXO
|--------------------------------------------------------------------------
|
| Esse card continua mostrando o estoque atual.
| Ele não depende do período escolhido.
|
*/

$stmt = $pdo->query("
    SELECT COUNT(*)

    FROM (

        SELECT
            p.id,
            p.estoque_minimo,

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
            ) AS saldo

        FROM produtos p

        LEFT JOIN movimentacoes m
            ON m.produto_id = p.id

        WHERE p.ativo = 1

        GROUP BY
            p.id,
            p.estoque_minimo

        HAVING saldo <= p.estoque_minimo

    ) AS estoque
");

$produtosEstoqueBaixo = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| MOVIMENTAÇÕES DO PERÍODO
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        m.id,
        m.tipo,
        m.quantidade,
        m.data_movimentacao,

        p.codigo,
        p.nome AS produto,
        p.unidade

    FROM movimentacoes m

    INNER JOIN produtos p
        ON p.id = m.produto_id

    WHERE 1 = 1
";

$parametrosMovimentacoes = [];

if ($dataInicial !== '') {

    $sql .= "
        AND DATE(m.data_movimentacao) >= :data_inicial
    ";

    $parametrosMovimentacoes['data_inicial'] = $dataInicial;
}

if ($dataFinal !== '') {

    $sql .= "
        AND DATE(m.data_movimentacao) <= :data_final
    ";

    $parametrosMovimentacoes['data_final'] = $dataFinal;
}

$sql .= "
    ORDER BY
        m.data_movimentacao DESC,
        m.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametrosMovimentacoes);

$movimentacoesPeriodo = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| PRODUTOS MAIS CONSUMIDOS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.id,
        p.codigo,
        p.nome,
        p.unidade,

        COALESCE(
            SUM(m.quantidade),
            0
        ) AS total_saida

    FROM movimentacoes m

    INNER JOIN produtos p
        ON p.id = m.produto_id

    WHERE m.tipo = 'saida'
";

$parametrosMaisConsumidos = [];

if ($dataInicial !== '') {

    $sql .= "
        AND DATE(m.data_movimentacao) >= :data_inicial
    ";

    $parametrosMaisConsumidos['data_inicial'] = $dataInicial;
}

if ($dataFinal !== '') {

    $sql .= "
        AND DATE(m.data_movimentacao) <= :data_final
    ";

    $parametrosMaisConsumidos['data_final'] = $dataFinal;
}

$sql .= "
    GROUP BY
        p.id,
        p.codigo,
        p.nome,
        p.unidade

    ORDER BY
        total_saida DESC,
        p.nome ASC

    LIMIT 5
";

$stmt = $pdo->prepare($sql);

$stmt->execute($parametrosMaisConsumidos);

$produtosMaisConsumidos = $stmt->fetchAll();


include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>


<main class="content">


    <!-- =========================
         CABEÇALHO
    ========================== -->

    <div class="page-header">

        <div>

            <h1>Relatórios</h1>

            <p>
                Consulte e analise as movimentações do almoxarifado.
            </p>

        </div>

    </div>


    <!-- =========================
         FILTRO POR PERÍODO
    ========================== -->

    <section class="reports-filter-card">

        <form
            method="GET"
            action="index.php"
            class="reports-filter"
        >

            <div class="reports-filter-title">

                <i class="bi bi-calendar3"></i>

                <div>

                    <strong>
                        Período do relatório
                    </strong>

                    <span>
                        Selecione as datas para filtrar os dados.
                    </span>

                </div>

            </div>


            <div class="reports-filter-fields">


                <!-- DATA INICIAL -->

                <div class="reports-date-field">

                    <label for="data_inicial">
                        Data inicial
                    </label>

                    <input
                        type="date"
                        id="data_inicial"
                        name="data_inicial"
                        value="<?= htmlspecialchars($dataInicial) ?>"
                    >

                </div>


                <!-- DATA FINAL -->

                <div class="reports-date-field">

                    <label for="data_final">
                        Data final
                    </label>

                    <input
                        type="date"
                        id="data_final"
                        name="data_final"
                        value="<?= htmlspecialchars($dataFinal) ?>"
                    >

                </div>


                <!-- FILTRAR -->

                <button
                    type="submit"
                    class="filter-button reports-filter-button"
                >

                    <i class="bi bi-funnel"></i>

                    Filtrar

                </button>


                <!-- LIMPAR -->

                <?php if (
                    $dataInicial !== '' ||
                    $dataFinal !== ''
                ): ?>

                    <a
                        href="index.php"
                        class="clear-filter reports-clear-filter"
                    >

                        <i class="bi bi-x-lg"></i>

                        Limpar

                    </a>

                <?php endif; ?>


            </div>

        </form>

    </section>


    <!-- =========================
         CARDS
    ========================== -->

    <section class="reports-summary">


        <!-- ENTRADAS -->

        <div class="report-card">

            <div class="report-card-icon entry">

                <i class="bi bi-arrow-down-circle"></i>

            </div>


            <div class="report-card-info">

                <span>
                    Total de entradas
                </span>

                <strong>

                    <?= number_format(
                        $totalEntradas,
                        2,
                        ',',
                        '.'
                    ) ?>

                </strong>

                <small>
                    Quantidade no período
                </small>

            </div>

        </div>


        <!-- SAÍDAS -->

        <div class="report-card">

            <div class="report-card-icon exit">

                <i class="bi bi-arrow-up-circle"></i>

            </div>


            <div class="report-card-info">

                <span>
                    Total de saídas
                </span>

                <strong>

                    <?= number_format(
                        $totalSaidas,
                        2,
                        ',',
                        '.'
                    ) ?>

                </strong>

                <small>
                    Quantidade no período
                </small>

            </div>

        </div>


        <!-- PRODUTOS MOVIMENTADOS -->

        <div class="report-card">

            <div class="report-card-icon products">

                <i class="bi bi-box-seam"></i>

            </div>


            <div class="report-card-info">

                <span>
                    Produtos movimentados
                </span>

                <strong>
                    <?= $produtosMovimentados ?>
                </strong>

                <small>
                    Produtos diferentes
                </small>

            </div>

        </div>


        <!-- ESTOQUE BAIXO -->

        <div class="report-card">

            <div class="report-card-icon warning">

                <i class="bi bi-exclamation-triangle"></i>

            </div>


            <div class="report-card-info">

                <span>
                    Estoque baixo
                </span>

                <strong>
                    <?= $produtosEstoqueBaixo ?>
                </strong>

                <small>
                    Situação atual
                </small>

            </div>

        </div>


    </section>


    <!-- =========================
         MOVIMENTAÇÕES DO PERÍODO
    ========================== -->

    <section class="reports-table-card">


        <div class="reports-section-header">

            <div>

                <h2>
                    Movimentações do período
                </h2>

                <p>

                    <?php if (
                        $dataInicial !== '' ||
                        $dataFinal !== ''
                    ): ?>

                        Resultados de acordo com o período selecionado.

                    <?php else: ?>

                        Todas as movimentações registradas.

                    <?php endif; ?>

                </p>

            </div>


            <span class="reports-result-count">

                <?= count($movimentacoesPeriodo) ?>

                registro(s)

            </span>

        </div>


        <div class="table-container">

            <table>

                <thead>

                    <tr>
                        <th>Data</th>
                        <th>Produto</th>
                        <th>Tipo</th>
                        <th>Quantidade</th>
                    </tr>

                </thead>


                <tbody>


                <?php if (empty($movimentacoesPeriodo)): ?>

                    <tr>

                        <td
                            colspan="4"
                            class="empty-table"
                        >

                            <i class="bi bi-search"></i>

                            Nenhuma movimentação encontrada no período.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($movimentacoesPeriodo as $movimentacao): ?>


                        <?php

                        $entrada =
                            $movimentacao['tipo'] === 'entrada';

                        ?>


                        <tr>


                            <!-- DATA -->

                            <td>

                                <?= date(
                                    'd/m/Y',
                                    strtotime(
                                        $movimentacao['data_movimentacao']
                                    )
                                ) ?>

                            </td>


                            <!-- PRODUTO -->

                            <td>

                                <div class="report-product">

                                    <div class="report-product-icon">

                                        <i class="bi bi-box"></i>

                                    </div>


                                    <div>

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


                            <!-- TIPO -->

                            <td>

                                <?php if ($entrada): ?>

                                    <span class="movement-status movement-entry">

                                        Entrada

                                    </span>

                                <?php else: ?>

                                    <span class="movement-status movement-exit">

                                        Saída

                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- QUANTIDADE -->

                            <td>

                                <strong
                                    class="
                                        report-quantity
                                        <?= $entrada
                                            ? 'entry'
                                            : 'exit' ?>
                                    "
                                >

                                    <?= $entrada ? '+' : '-' ?>

                                    <?= number_format(
                                        $movimentacao['quantidade'],
                                        2,
                                        ',',
                                        '.'
                                    ) ?>

                                    <?= htmlspecialchars(
                                        $movimentacao['unidade']
                                    ) ?>

                                </strong>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>

            </table>

        </div>


    </section>

    <!-- =========================
     PRODUTOS MAIS CONSUMIDOS
========================== -->

<section class="reports-ranking-card">

    <div class="reports-section-header">

        <div>

            <h2>
                Produtos mais consumidos
            </h2>

            <p>
                Materiais com maior volume de saída no período.
            </p>

        </div>

        <i class="bi bi-trophy reports-ranking-title-icon"></i>

    </div>


    <div class="reports-ranking-list">

        <?php if (empty($produtosMaisConsumidos)): ?>

            <div class="reports-ranking-empty">

                <i class="bi bi-box"></i>

                <span>
                    Nenhuma saída registrada no período.
                </span>

            </div>

        <?php else: ?>

            <?php foreach ($produtosMaisConsumidos as $indice => $produto): ?>

                <a
                    href="/ha-stock/app/Views/produtos/detalhes.php?id=<?= $produto['id'] ?>"
                    class="reports-ranking-item"
                >

                    <!-- POSIÇÃO -->

                    <div class="reports-ranking-position">

                        <?= $indice + 1 ?>

                    </div>


                    <!-- PRODUTO -->

                    <div class="reports-ranking-product">

                        <div class="reports-ranking-icon">

                            <i class="bi bi-box"></i>

                        </div>

                        <div>

                            <strong>

                                <?= htmlspecialchars(
                                    $produto['nome']
                                ) ?>

                            </strong>

                            <small>

                                <?= htmlspecialchars(
                                    $produto['codigo']
                                ) ?>

                            </small>

                        </div>

                    </div>


                    <!-- QUANTIDADE -->

                    <div class="reports-ranking-value">

                        <strong>

                            <?= number_format(
                                $produto['total_saida'],
                                2,
                                ',',
                                '.'
                            ) ?>

                            <?= htmlspecialchars(
                                $produto['unidade']
                            ) ?>

                        </strong>

                        <span>
                            consumido
                        </span>

                    </div>


                    <!-- SETA -->

                    <i class="bi bi-chevron-right"></i>

                </a>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</section>


</main>


<?php include '../../Includes/footer.php'; ?>