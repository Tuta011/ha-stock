<?php

require_once '../../Config/database.php';


/*
|--------------------------------------------------------------------------
| ID DO PRODUTO
|--------------------------------------------------------------------------
*/

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| BUSCAR PRODUTO + SALDO
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.codigo,
        p.nome,
        p.unidade,
        p.estoque_minimo,
        p.localizacao,
        p.observacoes,
        p.ativo,
        p.created_at,

        c.nome AS categoria,

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

    LEFT JOIN categorias c
        ON c.id = p.categoria_id

    LEFT JOIN movimentacoes m
        ON m.produto_id = p.id

    WHERE p.id = ?

    GROUP BY
        p.id,
        p.codigo,
        p.nome,
        p.unidade,
        p.estoque_minimo,
        p.localizacao,
        p.observacoes,
        p.ativo,
        p.created_at,
        c.nome
");

$stmt->execute([$id]);

$produto = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| PRODUTO NÃO ENCONTRADO
|--------------------------------------------------------------------------
*/

if (!$produto) {
    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| SALDO / STATUS
|--------------------------------------------------------------------------
*/

$saldo = (float) $produto['saldo'];

$estoqueMinimo =
    (float) $produto['estoque_minimo'];

$estoqueBaixo =
    $saldo <= $estoqueMinimo;


/*
|--------------------------------------------------------------------------
| HISTÓRICO DE MOVIMENTAÇÕES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        tipo,
        quantidade,
        observacao,
        data_movimentacao

    FROM movimentacoes

    WHERE produto_id = ?

    ORDER BY
        data_movimentacao DESC,
        id DESC
");

$stmt->execute([$id]);

$movimentacoes = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| TOTAL DE MOVIMENTAÇÕES
|--------------------------------------------------------------------------
*/

$totalMovimentacoes = count($movimentacoes);


include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>


<main class="content">


    <!-- =========================
         CABEÇALHO
    ========================== -->

    <div class="page-header products-header">

        <div>

            <a
                href="index.php"
                class="product-back">
                <i class="bi bi-arrow-left"></i>
                Voltar para produtos
            </a>

            <h1>
                <?= htmlspecialchars($produto['nome']) ?>
            </h1>

            <p>
                Detalhes e histórico do produto.
            </p>

        </div>


        <div class="product-detail-header-actions">

            <!-- ENTRADA -->

            <!-- ENTRADA -->

            <a
                href="../movimentacoes/entrada.php?produto_id=<?= $produto['id'] ?>"
                class="btn-movement btn-entry">
                <i class="bi bi-box-arrow-in-down"></i>
                Entrada
            </a>


            <!-- SAÍDA -->

            <a
                href="../movimentacoes/saida.php?produto_id=<?= $produto['id'] ?>"
                class="btn-movement btn-exit">
                <i class="bi bi-box-arrow-up"></i>
                Saída
            </a>


            <!-- EDITAR -->

            <a
                href="editar.php?id=<?= $produto['id'] ?>"
                class="btn-secondary">
                <i class="bi bi-pencil"></i>
                Editar
            </a>

        </div>

    </div>


    <!-- =========================
         RESUMO
    ========================== -->

    <section class="product-detail-grid">


        <!-- SALDO -->

        <div class="product-detail-card highlight">

            <div class="detail-card-icon">

                <i class="bi bi-box-seam"></i>

            </div>

            <div>

                <span>
                    Saldo atual
                </span>

                <strong>
                    <?= number_format(
                        $saldo,
                        2,
                        ',',
                        '.'
                    ) ?>

                    <?= htmlspecialchars(
                        $produto['unidade']
                    ) ?>
                </strong>

            </div>

        </div>


        <!-- ESTOQUE MÍNIMO -->

        <div class="product-detail-card">

            <div class="detail-card-icon">

                <i class="bi bi-speedometer2"></i>

            </div>

            <div>

                <span>
                    Estoque mínimo
                </span>

                <strong>

                    <?= number_format(
                        $estoqueMinimo,
                        2,
                        ',',
                        '.'
                    ) ?>

                    <?= htmlspecialchars(
                        $produto['unidade']
                    ) ?>

                </strong>

            </div>

        </div>


        <!-- STATUS -->

        <div class="product-detail-card">

            <div class="detail-card-icon">

                <?php if ($estoqueBaixo): ?>

                    <i class="bi bi-exclamation-triangle"></i>

                <?php else: ?>

                    <i class="bi bi-check-circle"></i>

                <?php endif; ?>

            </div>

            <div>

                <span>
                    Status
                </span>


                <?php if ($estoqueBaixo): ?>

                    <strong class="detail-status-low">
                        Estoque baixo
                    </strong>

                <?php else: ?>

                    <strong class="detail-status-ok">
                        Estoque normal
                    </strong>

                <?php endif; ?>

            </div>

        </div>


        <!-- MOVIMENTAÇÕES -->

        <div class="product-detail-card">

            <div class="detail-card-icon">

                <i class="bi bi-arrow-left-right"></i>

            </div>

            <div>

                <span>
                    Movimentações
                </span>

                <strong>
                    <?= $totalMovimentacoes ?>
                </strong>

            </div>

        </div>


    </section>


    <!-- =========================
         INFORMAÇÕES
    ========================== -->

    <section class="product-info-card">


        <div class="product-section-header">

            <div>

                <h2>
                    Informações do produto
                </h2>

                <p>
                    Dados cadastrados no almoxarifado.
                </p>

            </div>

        </div>


        <div class="product-info-grid">


            <div class="product-info-item">

                <span>
                    Código
                </span>

                <strong>
                    <?= htmlspecialchars(
                        $produto['codigo']
                    ) ?>
                </strong>

            </div>


            <div class="product-info-item">

                <span>
                    Categoria
                </span>

                <strong>
                    <?= htmlspecialchars(
                        $produto['categoria'] ?? '-'
                    ) ?>
                </strong>

            </div>


            <div class="product-info-item">

                <span>
                    Unidade
                </span>

                <strong>
                    <?= htmlspecialchars(
                        $produto['unidade']
                    ) ?>
                </strong>

            </div>


            <div class="product-info-item">

                <span>
                    Localização
                </span>

                <strong>

                    <?= htmlspecialchars(
                        $produto['localizacao']
                            ?: 'Não informada'
                    ) ?>

                </strong>

            </div>


            <div class="product-info-item">

                <span>
                    Cadastrado em
                </span>

                <strong>

                    <?= date(
                        'd/m/Y',
                        strtotime(
                            $produto['created_at']
                        )
                    ) ?>

                </strong>

            </div>


            <div class="product-info-item">

                <span>
                    Situação
                </span>

                <strong>

                    <?= $produto['ativo']
                        ? 'Ativo'
                        : 'Inativo' ?>

                </strong>

            </div>


        </div>


        <?php if (!empty($produto['observacoes'])): ?>

            <div class="product-observation">

                <span>
                    Observações
                </span>

                <p>
                    <?= nl2br(
                        htmlspecialchars(
                            $produto['observacoes']
                        )
                    ) ?>
                </p>

            </div>

        <?php endif; ?>


    </section>


    <!-- =========================
         HISTÓRICO
    ========================== -->

    <section class="product-history-card">


        <div class="product-section-header">

            <div>

                <h2>
                    Histórico de movimentações
                </h2>

                <p>
                    Entradas e saídas registradas para este produto.
                </p>

            </div>

        </div>


        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>Tipo</th>

                        <th>Quantidade</th>

                        <th>Data</th>

                        <th>Observação</th>

                    </tr>

                </thead>


                <tbody>


                    <?php if (empty($movimentacoes)): ?>


                        <tr>

                            <td
                                colspan="4"
                                class="empty-products">

                                <i class="bi bi-clock-history"></i>

                                Nenhuma movimentação registrada.

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach ($movimentacoes as $movimentacao): ?>


                            <?php

                            $entrada =
                                $movimentacao['tipo']
                                === 'entrada';

                            ?>


                            <tr>


                                <!-- TIPO -->

                                <td>

                                    <?php if ($entrada): ?>

                                        <span class="badge badge-entrada">

                                            <i class="bi bi-arrow-down"></i>

                                            Entrada

                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-saida">

                                            <i class="bi bi-arrow-up"></i>

                                            Saída

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- QUANTIDADE -->

                                <td>

                                    <strong
                                        class="
                                        <?= $entrada
                                            ? 'movement-entry'
                                            : 'movement-exit' ?>
                                    ">

                                        <?= $entrada ? '+' : '-' ?>

                                        <?= number_format(
                                            $movimentacao['quantidade'],
                                            2,
                                            ',',
                                            '.'
                                        ) ?>

                                        <?= htmlspecialchars(
                                            $produto['unidade']
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- DATA -->

                                <td>

                                    <?= date(
                                        'd/m/Y H:i',
                                        strtotime(
                                            $movimentacao['data_movimentacao']
                                        )
                                    ) ?>

                                </td>


                                <!-- OBSERVAÇÃO -->

                                <td>

                                    <?= htmlspecialchars(
                                        $movimentacao['observacao'] ?? '-'
                                    ) ?>

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