<?php

require_once '../../Config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        m.id,
        m.tipo,
        m.quantidade,
        m.fornecedor,
        m.documento,
        m.responsavel,
        m.destino,
        m.observacao,
        m.data_movimentacao,
        m.created_at,

        p.id AS produto_id,
        p.codigo,
        p.nome AS produto,
        p.unidade

    FROM movimentacoes m

    INNER JOIN produtos p
        ON p.id = m.produto_id

    WHERE m.id = ?
");

$stmt->execute([$id]);

$movimentacao = $stmt->fetch();

if (!$movimentacao) {
    header('Location: index.php');
    exit;
}

$entrada = $movimentacao['tipo'] === 'entrada';

include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>

<main class="content">

    <div class="page-header movimentacoes-header">

        <div>

            <a href="index.php" class="product-back">
                <i class="bi bi-arrow-left"></i>
                Voltar para movimentações
            </a>

            <h1>Detalhes da movimentação</h1>

            <p>
                Consulte as informações completas deste registro.
            </p>

        </div>

        <a
            href="../produtos/detalhes.php?id=<?= $movimentacao['produto_id'] ?>"
            class="btn-secondary"
        >
            <i class="bi bi-box"></i>
            Ver produto
        </a>

    </div>


    <section class="movement-detail-grid">

        <!-- TIPO -->

        <div class="movement-detail-card">

            <span>Tipo</span>

            <?php if ($entrada): ?>

                <strong class="detail-entry">
                    <i class="bi bi-arrow-down-circle"></i>
                    Entrada
                </strong>

            <?php else: ?>

                <strong class="detail-exit">
                    <i class="bi bi-arrow-up-circle"></i>
                    Saída
                </strong>

            <?php endif; ?>

        </div>


        <!-- QUANTIDADE -->

        <div class="movement-detail-card">

            <span>Quantidade</span>

            <strong class="<?= $entrada ? 'detail-entry' : 'detail-exit' ?>">

                <?= $entrada ? '+' : '-' ?>

                <?= number_format(
                    $movimentacao['quantidade'],
                    2,
                    ',',
                    '.'
                ) ?>

                <?= htmlspecialchars($movimentacao['unidade']) ?>

            </strong>

        </div>


        <!-- DATA -->

        <div class="movement-detail-card">

            <span>Data da movimentação</span>

            <strong>

                <?= date(
                    'd/m/Y',
                    strtotime($movimentacao['data_movimentacao'])
                ) ?>

            </strong>

        </div>


        <!-- REGISTRO -->

        <div class="movement-detail-card">

            <span>Registrado em</span>

            <strong>

                <?= date(
                    'd/m/Y H:i',
                    strtotime($movimentacao['created_at'])
                ) ?>

            </strong>

        </div>

    </section>


    <section class="movement-detail-section">

        <div class="product-section-header">

            <div>
                <h2>Produto</h2>
                <p>Material relacionado à movimentação.</p>
            </div>

        </div>


        <div class="product-info-grid">

            <div class="product-info-item">

                <span>Código</span>

                <strong>
                    <?= htmlspecialchars($movimentacao['codigo']) ?>
                </strong>

            </div>


            <div class="product-info-item">

                <span>Produto</span>

                <strong>
                    <?= htmlspecialchars($movimentacao['produto']) ?>
                </strong>

            </div>


            <div class="product-info-item">

                <span>Unidade</span>

                <strong>
                    <?= htmlspecialchars($movimentacao['unidade']) ?>
                </strong>

            </div>

        </div>

    </section>


    <section class="movement-detail-section">

        <div class="product-section-header">

            <div>

                <?php if ($entrada): ?>

                    <h2>Informações da entrada</h2>
                    <p>Origem do material recebido.</p>

                <?php else: ?>

                    <h2>Informações da saída</h2>
                    <p>Responsável e destino do material.</p>

                <?php endif; ?>

            </div>

        </div>


        <div class="product-info-grid">


            <?php if ($entrada): ?>

                <div class="product-info-item">

                    <span>Fornecedor</span>

                    <strong>
                        <?= htmlspecialchars(
                            $movimentacao['fornecedor'] ?: 'Não informado'
                        ) ?>
                    </strong>

                </div>


                <div class="product-info-item">

                    <span>Documento / Nota</span>

                    <strong>
                        <?= htmlspecialchars(
                            $movimentacao['documento'] ?: 'Não informado'
                        ) ?>
                    </strong>

                </div>


                <div class="product-info-item">

                    <span>Destino</span>

                    <strong>Almoxarifado</strong>

                </div>

            <?php else: ?>

                <div class="product-info-item">

                    <span>Retirado por</span>

                    <strong>
                        <?= htmlspecialchars(
                            $movimentacao['responsavel'] ?: 'Não informado'
                        ) ?>
                    </strong>

                </div>


                <div class="product-info-item">

                    <span>Destino</span>

                    <strong>
                        <?= htmlspecialchars(
                            ucfirst($movimentacao['destino'] ?: 'Não informado')
                        ) ?>
                    </strong>

                </div>

            <?php endif; ?>


        </div>


        <div class="movement-observation">

            <span>Observação</span>

            <p>
                <?= nl2br(
                    htmlspecialchars(
                        $movimentacao['observacao']
                        ?: 'Nenhuma observação registrada.'
                    )
                ) ?>
            </p>

        </div>

    </section>

</main>

<?php include '../../Includes/footer.php'; ?>