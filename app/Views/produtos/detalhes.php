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

        /* ESTOQUE GERAL */
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

        /* RESERVADO / SEPARADO PARA OBRAS */
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
        ) AS saldo_obras,

        /* TOTAL FÍSICO */
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
        ) AS saldo_total

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

$saldoGeral = (float) $produto['saldo_geral'];

$saldoObras = (float) $produto['saldo_obras'];

$saldoTotal = (float) $produto['saldo_total'];

$estoqueMinimo = (float) $produto['estoque_minimo'];

/*
|--------------------------------------------------------------------------
| ESTOQUE BAIXO
|--------------------------------------------------------------------------
|
| O estoque mínimo deve considerar somente o material disponível
| no estoque geral.
|
*/

$estoqueBaixo = $saldoGeral <= $estoqueMinimo;


/*
|--------------------------------------------------------------------------
| HISTÓRICO DE MOVIMENTAÇÕES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        m.id,
        m.tipo,
        m.tipo_estoque,
        m.obra_id,
        m.quantidade,
        m.fornecedor,
        m.responsavel,
        m.destino,
        m.observacao,
        m.data_movimentacao,

        o.nome AS obra_nome,
        o.codigo AS obra_codigo,

        c.nome AS cliente_nome

    FROM movimentacoes m

    LEFT JOIN obras o
        ON o.id = m.obra_id

    LEFT JOIN clientes c
        ON c.id = o.cliente_id

    WHERE m.produto_id = ?

    ORDER BY
        m.data_movimentacao DESC,
        m.id DESC
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

            <!-- SEPARAR -->
             
            <a
                href="../movimentacoes/separar_obra.php?produto_id=<?= $produto['id'] ?>"
                class="btn-secondary">

                <i class="bi bi-buildings"></i>

                Separar para obra

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


        <!-- ESTOQUE GERAL -->

        <div class="product-detail-card highlight">

            <div class="detail-card-icon">

                <i class="bi bi-box-seam"></i>

            </div>

            <div>

                <span>
                    Estoque geral
                </span>

                <strong>

                    <?= number_format(
                        $saldoGeral,
                        2,
                        ',',
                        '.'
                    ) ?>

                    <?= htmlspecialchars($produto['unidade']) ?>

                </strong>

            </div>

        </div>



        <!-- RESERVADO EM OBRAS -->

        <div class="product-detail-card">

            <div class="detail-card-icon">

                <i class="bi bi-buildings"></i>

            </div>

            <div>

                <span>
                    Reservado em obras
                </span>

                <strong>

                    <?= number_format(
                        $saldoObras,
                        2,
                        ',',
                        '.'
                    ) ?>

                    <?= htmlspecialchars($produto['unidade']) ?>

                </strong>

            </div>

        </div>



        <!-- TOTAL FÍSICO -->

        <div class="product-detail-card">

            <div class="detail-card-icon">

                <i class="bi bi-boxes"></i>

            </div>

            <div>

                <span>
                    Total físico
                </span>

                <strong>

                    <?= number_format(
                        $saldoTotal,
                        2,
                        ',',
                        '.'
                    ) ?>

                    <?= htmlspecialchars($produto['unidade']) ?>

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

                        <th>Destino</th>

                        <th>Origem / Responsável</th>

                        <th>Data</th>

                        <th>Observação</th>

                    </tr>

                </thead>


                <tbody>


                    <?php if (empty($movimentacoes)): ?>


                        <tr>

                            <td
                                colspan="6"
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

                                <!-- DESTINO -->

                                <td>

                                    <?php if ($movimentacao['tipo_estoque'] === 'obra'): ?>

                                        <?php if ($movimentacao['obra_id']): ?>

                                            <a
                                                href="../obras/detalhes.php?id=<?= $movimentacao['obra_id'] ?>"
                                                class="product-history-work">

                                                <div class="product-history-work-icon">

                                                    <i class="bi bi-buildings"></i>

                                                </div>


                                                <div>

                                                    <strong>

                                                        <?= htmlspecialchars(
                                                            $movimentacao['obra_nome']
                                                                ?: 'Obra'
                                                        ) ?>

                                                    </strong>


                                                    <?php if ($movimentacao['cliente_nome']): ?>

                                                        <small>

                                                            <?= htmlspecialchars(
                                                                $movimentacao['cliente_nome']
                                                            ) ?>

                                                        </small>

                                                    <?php endif; ?>

                                                </div>

                                            </a>

                                        <?php else: ?>

                                            <span class="history-work-warning">

                                                <i class="bi bi-exclamation-triangle"></i>

                                                Obra não informada

                                            </span>

                                        <?php endif; ?>


                                    <?php elseif ($entrada): ?>

                                        <span class="history-general-stock">

                                            <i class="bi bi-box-seam"></i>

                                            Almoxarifado

                                        </span>


                                    <?php else: ?>

                                        <span class="history-general-stock">

                                            <i class="bi bi-arrow-right"></i>

                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    $movimentacao['destino']
                                                        ?: 'Não informado'
                                                )
                                            ) ?>

                                        </span>

                                    <?php endif; ?>

                                </td>



                                <!-- ORIGEM / RESPONSÁVEL -->

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