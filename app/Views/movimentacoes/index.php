<?php

require_once '../../Config/database.php';

$stmt = $pdo->query("
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

        p.codigo,
        p.nome AS produto,
        p.unidade

    FROM movimentacoes m

    INNER JOIN produtos p
        ON p.id = m.produto_id

    ORDER BY
        m.data_movimentacao DESC,
        m.id DESC
");

$movimentacoes = $stmt->fetchAll();

include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>

<main class="content">
    <?php if (isset($_GET['entrada']) && $_GET['entrada'] === 'sucesso'): ?>

        <div class="alert-success">
            <i class="bi bi-check-circle"></i>
            Entrada registrada com sucesso!
        </div>

    <?php endif; ?>


    <?php if (isset($_GET['saida']) && $_GET['saida'] === 'sucesso'): ?>

        <div class="alert-success">
            <i class="bi bi-check-circle"></i>
            Saída registrada com sucesso!
        </div>

    <?php endif; ?>

    <div class="page-header movimentacoes-header">

        <div>
            <h1>Movimentações</h1>
            <p>Acompanhe entradas e saídas do almoxarifado.</p>
        </div>

        <div class="movimentacoes-actions">

            <a href="entrada.php" class="btn-secondary">
                <i class="bi bi-arrow-down-circle"></i>
                Nova entrada
            </a>

            <a href="saida.php" class="btn-primary">
                <i class="bi bi-arrow-up-circle"></i>
                Nova saída
            </a>

        </div>

    </div>


    <section class="movimentacoes-filtros">

        <div class="product-search">

            <i class="bi bi-search"></i>

            <input
                type="text"
                placeholder="Pesquisar movimentação...">

        </div>

        <select>
            <option value="">Todos os tipos</option>
            <option value="entrada">Entradas</option>
            <option value="saida">Saídas</option>
        </select>

    </section>


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
                        <td colspan="7" class="empty-table">
                            Nenhuma movimentação registrada.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($movimentacoes as $movimentacao): ?>

                        <?php

                        $entrada = $movimentacao['tipo'] === 'entrada';

                        $quantidade = number_format(
                            $movimentacao['quantidade'],
                            2,
                            ',',
                            '.'
                        );

                        ?>

                        <tr>

                            <!-- PRODUTO -->
                            <td>

                                <div class="table-product">

                                    <div class="product-icon">
                                        <i class="bi bi-box"></i>
                                    </div>

                                    <div class="movement-product-info">

                                        <strong>
                                            <?= htmlspecialchars($movimentacao['produto']) ?>
                                        </strong>

                                        <small>
                                            <?= htmlspecialchars($movimentacao['codigo']) ?>
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
                            <td
                                class="movement-quantity
                    <?= $entrada ? 'entrada' : 'saida' ?>">

                                <?= $entrada ? '+' : '-' ?>

                                <?= $quantidade ?>

                                <?= htmlspecialchars($movimentacao['unidade']) ?>

                            </td>


                            <!-- RESPONSÁVEL -->
                            <td>

                                <?php if ($entrada): ?>

                                    <?= htmlspecialchars(
                                        $movimentacao['fornecedor'] ?: '-'
                                    ) ?>

                                <?php else: ?>

                                    <?= htmlspecialchars(
                                        $movimentacao['responsavel'] ?: '-'
                                    ) ?>

                                <?php endif; ?>

                            </td>


                            <!-- DESTINO -->
                            <td>

                                <?php if ($entrada): ?>

                                    Almoxarifado

                                <?php else: ?>

                                    <?= htmlspecialchars(
                                        ucfirst($movimentacao['destino'] ?: '-')
                                    ) ?>

                                <?php endif; ?>

                            </td>


                            <!-- DATA -->
                            <td>

                                <?= date(
                                    'd/m/Y',
                                    strtotime($movimentacao['data_movimentacao'])
                                ) ?>

                            </td>


                            <!-- AÇÕES -->
                            <td>

                                <button
                                    class="action-button"
                                    title="Detalhes">
                                    <i class="bi bi-three-dots"></i>
                                </button>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </section>

</main>

<?php include '../../Includes/footer.php'; ?>