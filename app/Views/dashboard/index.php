<?php

require_once '../../Config/database.php';


/*
|--------------------------------------------------------------------------
| TOTAL DE PRODUTOS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM produtos
    WHERE ativo = 1
");

$totalProdutos = $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| TOTAL DE ENTRADAS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM movimentacoes
    WHERE tipo = 'entrada'
");

$totalEntradas = $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| TOTAL DE SAÍDAS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM movimentacoes
    WHERE tipo = 'saida'
");

$totalSaidas = $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| QUANTIDADE DE PRODUTOS COM ESTOQUE BAIXO
|--------------------------------------------------------------------------
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

$estoqueBaixo = $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| ÚLTIMAS MOVIMENTAÇÕES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
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

    ORDER BY
        m.data_movimentacao DESC,
        m.id DESC

    LIMIT 5
");

$ultimasMovimentacoes = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| LISTA DE PRODUTOS COM ESTOQUE BAIXO
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        p.id,
        p.codigo,
        p.nome,
        p.unidade,
        p.estoque_minimo,
        p.localizacao,

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
        p.codigo,
        p.nome,
        p.unidade,
        p.estoque_minimo,
        p.localizacao

    HAVING saldo <= p.estoque_minimo

    ORDER BY
        CASE
            WHEN p.estoque_minimo > 0
                THEN saldo / p.estoque_minimo
            ELSE 999
        END ASC,
        saldo ASC,
        p.nome ASC

    LIMIT 10
");

$produtosEstoqueBaixo = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| INCLUDES
|--------------------------------------------------------------------------
*/

include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>


<main class="content">


    <!-- =========================
         CABEÇALHO
    ========================== -->

    <div class="page-header">

        <div>

            <h1>Dashboard</h1>

            <p>
                Visão geral do seu almoxarifado.
            </p>

        </div>

    </div>


    <!-- =========================
         CARDS
    ========================== -->

    <section class="dashboard-cards">


        <!-- TOTAL PRODUTOS -->

        <div class="dashboard-card">

            <div class="card-icon">
                <i class="bi bi-box-seam"></i>
            </div>

            <div class="card-info">

                <span>
                    Total de produtos
                </span>

                <strong>
                    <?= $totalProdutos ?>
                </strong>

            </div>

        </div>


        <!-- ESTOQUE BAIXO -->

        <div class="dashboard-card warning">

            <div class="card-icon">
                <i class="bi bi-exclamation-triangle"></i>
            </div>

            <div class="card-info">

                <span>
                    Estoque baixo
                </span>

                <strong>
                    <?= $estoqueBaixo ?>
                </strong>

            </div>

        </div>


        <!-- ENTRADAS -->

        <div class="dashboard-card">

            <div class="card-icon">
                <i class="bi bi-box-arrow-in-down"></i>
            </div>

            <div class="card-info">

                <span>
                    Entradas
                </span>

                <strong>
                    <?= $totalEntradas ?>
                </strong>

            </div>

        </div>


        <!-- SAÍDAS -->

        <div class="dashboard-card">

            <div class="card-icon">
                <i class="bi bi-box-arrow-up"></i>
            </div>

            <div class="card-info">

                <span>
                    Saídas
                </span>

                <strong>
                    <?= $totalSaidas ?>
                </strong>

            </div>

        </div>


    </section>


    <!-- =========================
         ÚLTIMAS MOVIMENTAÇÕES
    ========================== -->

    <section class="dashboard-section">


        <div class="section-header">

            <div>

                <h2>
                    Últimas movimentações
                </h2>

                <p>
                    Movimentações recentes do almoxarifado.
                </p>

            </div>


            <a
                href="/HA-Stock/app/Views/movimentacoes/index.php"
                class="view-all"
            >

                Ver todas

                <i class="bi bi-arrow-right"></i>

            </a>

        </div>


        <div class="table-container">

            <table>

                <thead>

                    <tr>
                        <th>Produto</th>
                        <th>Tipo</th>
                        <th>Quantidade</th>
                        <th>Data</th>
                    </tr>

                </thead>


                <tbody>


                <?php if (empty($ultimasMovimentacoes)): ?>


                    <tr>

                        <td
                            colspan="4"
                            class="empty-dashboard"
                        >
                            Nenhuma movimentação registrada.
                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($ultimasMovimentacoes as $movimentacao): ?>


                        <?php

                        $entrada =
                            $movimentacao['tipo'] === 'entrada';

                        ?>


                        <tr>


                            <!-- PRODUTO -->

                            <td>

                                <div class="product-name">

                                    <i class="bi bi-box"></i>

                                    <div class="dashboard-product-info">

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

                                    <span class="badge badge-entrada">
                                        Entrada
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-saida">
                                        Saída
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- QUANTIDADE -->

                            <td>

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

                            </td>


                            <!-- DATA -->

                            <td>

                                <?= date(
                                    'd/m/Y',
                                    strtotime(
                                        $movimentacao['data_movimentacao']
                                    )
                                ) ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>

            </table>

        </div>


    </section>


    <!-- =========================
         PRODUTOS COM ESTOQUE BAIXO
    ========================== -->

    <section class="dashboard-section stock-alert-section">


        <div class="section-header">

            <div>

                <h2>
                    Produtos com estoque baixo
                </h2>

                <p>
                    Materiais que precisam de atenção.
                </p>

            </div>


            <a
                href="/HA-Stock/app/Views/produtos/index.php"
                class="view-all"
            >

                Ver produtos

                <i class="bi bi-arrow-right"></i>

            </a>

        </div>


        <div class="table-container">

            <table>

                <thead>

                    <tr>
                        <th>Produto</th>
                        <th>Saldo atual</th>
                        <th>Estoque mínimo</th>
                        <th>Localização</th>
                        <th>Status</th>
                        <th>Ação</th>
                    </tr>

                </thead>


                <tbody>


                <?php if (empty($produtosEstoqueBaixo)): ?>


                    <tr>

                        <td
                            colspan="6"
                            class="empty-dashboard"
                        >

                            <i class="bi bi-check-circle"></i>

                            Nenhum produto com estoque baixo.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($produtosEstoqueBaixo as $produto): ?>


                        <?php

                        $saldoProduto =
                            (float) $produto['saldo'];

                        $minimoProduto =
                            (float) $produto['estoque_minimo'];


                        $critico = false;


                        if ($minimoProduto > 0) {

                            $critico =
                                $saldoProduto <=
                                ($minimoProduto * 0.5);

                        }

                        ?>


                        <tr>


                            <!-- PRODUTO -->

                            <td>

                                <div class="product-name">

                                    <i class="bi bi-box"></i>

                                    <div class="dashboard-product-info">

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

                            </td>


                            <!-- SALDO ATUAL -->

                            <td>

                                <strong class="stock-alert-value">

                                    <?= number_format(
                                        $saldoProduto,
                                        2,
                                        ',',
                                        '.'
                                    ) ?>

                                    <?= htmlspecialchars(
                                        $produto['unidade']
                                    ) ?>

                                </strong>

                            </td>


                            <!-- ESTOQUE MÍNIMO -->

                            <td>

                                <?= number_format(
                                    $minimoProduto,
                                    2,
                                    ',',
                                    '.'
                                ) ?>

                                <?= htmlspecialchars(
                                    $produto['unidade']
                                ) ?>

                            </td>


                            <!-- LOCALIZAÇÃO -->

                            <td>

                                <div class="stock-location">

                                    <i class="bi bi-geo-alt"></i>

                                    <?= htmlspecialchars(
                                        $produto['localizacao']
                                        ?: 'Não informada'
                                    ) ?>

                                </div>

                            </td>


                            <!-- STATUS -->

                            <td>


                                <?php if ($critico): ?>


                                    <span
                                        class="
                                            stock-alert-badge
                                            critical
                                        "
                                    >

                                        <i class="bi bi-exclamation-octagon"></i>

                                        Crítico

                                    </span>


                                <?php else: ?>


                                    <span class="stock-alert-badge">

                                        <i class="bi bi-exclamation-triangle"></i>

                                        Estoque baixo

                                    </span>


                                <?php endif; ?>


                            </td>


                            <!-- AÇÃO -->

                            <td>

                                <a
                                    href="/HA-Stock/app/Views/produtos/detalhes.php?id=<?= $produto['id'] ?>"
                                    class="stock-view-button"
                                    title="Ver produto"
                                >

                                    <i class="bi bi-eye"></i>

                                </a>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>

            </table>

        </div>


    </section>


</main>


<?php include '../../Includes/footer.php'; ?>