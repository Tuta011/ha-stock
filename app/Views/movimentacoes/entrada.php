<?php

require_once '../../Config/database.php';

$erro = '';
$sucesso = '';

/* Buscar produtos ativos */
$stmt = $pdo->query("
    SELECT id, codigo, nome
    FROM produtos
    WHERE ativo = 1
    ORDER BY nome
");

$produtos = $stmt->fetchAll();


/* Processar formulário */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $produtoId = $_POST['produto_id'] ?? '';
    $quantidade = $_POST['quantidade'] ?? '';
    $data = $_POST['data'] ?? '';
    $fornecedor = trim($_POST['fornecedor'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    $observacao = trim($_POST['observacao'] ?? '');

    if (
        $produtoId === '' ||
        $quantidade === '' ||
        $data === ''
    ) {

        $erro = 'Preencha os campos obrigatórios.';
    } elseif ((float) $quantidade <= 0) {

        $erro = 'A quantidade deve ser maior que zero.';
    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO movimentacoes (
                    produto_id,
                    tipo,
                    quantidade,
                    fornecedor,
                    documento,
                    observacao,
                    data_movimentacao
                )
                VALUES (?, 'entrada', ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $produtoId,
                $quantidade,
                $fornecedor ?: null,
                $documento ?: null,
                $observacao ?: null,
                $data
            ]);

            header('Location: index.php?entrada=sucesso');
            exit;
        } catch (PDOException $e) {

            $erro = 'Erro ao registrar entrada.';
        }
    }
}

include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>
<main class="content">

    <div class="page-header">

        <div>
            <h1>Nova entrada</h1>
            <p>Registre a entrada de material no almoxarifado.</p>
        </div>

        <a href="index.php" class="btn-secondary">
            <i class="bi bi-arrow-left"></i>
            Voltar
        </a>

    </div>


    <div class="form-card">

        <form action="#" method="POST">

            <div class="form-grid">

                <div class="form-group full">

                    <label for="produto_id">Produto *</label>

                    <select
                        id="produto_id"
                        name="produto_id"
                        required>

                        <option value="">
                            Selecione o produto
                        </option>

                        <?php foreach ($produtos as $produto): ?>

                            <option value="<?= $produto['id'] ?>">

                                <?= htmlspecialchars($produto['codigo']) ?>
                                -
                                <?= htmlspecialchars($produto['nome']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="quantidade">Quantidade *</label>

                    <input
                        type="number"
                        id="quantidade"
                        name="quantidade"
                        min="0.01"
                        step="0.01"
                        placeholder="Ex: 50"
                        required>

                </div>


                <div class="form-group">

                    <label for="data">Data *</label>

                    <input
                        type="date"
                        id="data"
                        name="data"
                        required>

                </div>


                <div class="form-group">

                    <label for="fornecedor">Fornecedor</label>

                    <input
                        type="text"
                        id="fornecedor"
                        name="fornecedor"
                        placeholder="Ex: Fornecedor ABC">

                </div>


                <div class="form-group">

                    <label for="documento">Nota / Documento</label>

                    <input
                        type="text"
                        id="documento"
                        name="documento"
                        placeholder="Ex: NF 12584">

                </div>


                <div class="form-group full">

                    <label for="observacao">Observação</label>

                    <textarea
                        id="observacao"
                        name="observacao"
                        rows="4"
                        placeholder="Informações adicionais sobre a entrada..."></textarea>

                </div>

            </div>


            <div class="form-actions">

                <a href="index.php" class="btn-secondary">
                    Cancelar
                </a>

                <button type="submit" class="btn-entry">
                    <i class="bi bi-arrow-down-circle"></i>
                    Registrar entrada
                </button>

            </div>

        </form>

    </div>

</main>

<?php include '../../Includes/footer.php'; ?>