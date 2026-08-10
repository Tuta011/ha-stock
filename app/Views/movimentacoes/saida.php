<?php

require_once '../../Config/database.php';

$erro = '';

/* Buscar produtos ativos com saldo atual */
$stmt = $pdo->query("
    SELECT
        p.id,
        p.codigo,
        p.nome,
        p.unidade,

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
        p.unidade

    ORDER BY p.nome
");

$produtos = $stmt->fetchAll();


/* Processar saída */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $produtoId = $_POST['produto_id'] ?? '';
    $quantidade = $_POST['quantidade'] ?? '';
    $data = $_POST['data'] ?? '';
    $responsavel = trim($_POST['responsavel'] ?? '');
    $destino = $_POST['destino'] ?? '';
    $observacao = trim($_POST['observacao'] ?? '');

    if (
        $produtoId === '' ||
        $quantidade === '' ||
        $data === '' ||
        $responsavel === '' ||
        $destino === ''
    ) {

        $erro = 'Preencha todos os campos obrigatórios.';
    } elseif ((float) $quantidade <= 0) {

        $erro = 'A quantidade deve ser maior que zero.';
    } else {

        try {

            /* Buscar saldo atual do produto */
            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(
                        SUM(
                            CASE
                                WHEN tipo = 'entrada'
                                    THEN quantidade

                                WHEN tipo = 'saida'
                                    THEN -quantidade

                                ELSE 0
                            END
                        ),
                        0
                    ) AS saldo

                FROM movimentacoes

                WHERE produto_id = ?
            ");

            $stmt->execute([$produtoId]);

            $saldoAtual = (float) $stmt->fetchColumn();

            $quantidadeSaida = (float) $quantidade;


            /* Bloquear saída maior que o saldo */
            if ($quantidadeSaida > $saldoAtual) {

                $erro =
                    'Estoque insuficiente. Saldo disponível: ' .
                    number_format($saldoAtual, 2, ',', '.');
            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO movimentacoes (
                        produto_id,
                        tipo,
                        quantidade,
                        responsavel,
                        destino,
                        observacao,
                        data_movimentacao
                    )
                    VALUES (?, 'saida', ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $produtoId,
                    $quantidadeSaida,
                    $responsavel,
                    $destino,
                    $observacao ?: null,
                    $data
                ]);

                header('Location: index.php?saida=sucesso');
                exit;
            }
        } catch (PDOException $e) {

            $erro = 'Erro ao registrar saída.';
        }
    }
}

include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>

<main class="content">
    
    <?php if ($erro): ?>

    <div class="alert-error">
        <?= htmlspecialchars($erro) ?>
    </div>

<?php endif; ?>

    <div class="page-header">

        <div>
            <h1>Nova saída</h1>
            <p>Registre a retirada de material do almoxarifado.</p>
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

                                (Saldo:
                                <?= number_format($produto['saldo'], 2, ',', '.') ?>
                                <?= htmlspecialchars($produto['unidade']) ?>)

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
                        placeholder="Ex: 10"
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
                    <label for="responsavel">Retirado por *</label>

                    <input
                        type="text"
                        id="responsavel"
                        name="responsavel"
                        placeholder="Nome do funcionário"
                        required>
                </div>

                <div class="form-group">
                    <label for="destino">Destino *</label>

                    <select id="destino" name="destino" required>
                        <option value="">Selecione o destino</option>
                        <option value="producao">Produção</option>
                        <option value="obra">Obra</option>
                        <option value="instalacao">Instalação</option>
                        <option value="manutencao">Manutenção</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>

                <div class="form-group full">
                    <label for="observacao">Observação</label>

                    <textarea
                        id="observacao"
                        name="observacao"
                        rows="4"
                        placeholder="Ex: Material destinado à obra X..."></textarea>
                </div>

            </div>

            <div class="form-actions">

                <a href="index.php" class="btn-secondary">
                    Cancelar
                </a>

                <button type="submit" class="btn-exit">
                    <i class="bi bi-arrow-up-circle"></i>
                    Registrar saída
                </button>

            </div>

        </form>

    </div>

</main>

<?php include '../../Includes/footer.php'; ?>