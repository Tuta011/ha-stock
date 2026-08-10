<?php

require_once '../../Config/database.php';

$stmt = $pdo->query("
    SELECT id, nome
    FROM categorias
    ORDER BY nome
");

$categorias = $stmt->fetchAll();

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codigo = trim($_POST['codigo'] ?? '');
    $produto = trim($_POST['produto'] ?? '');
    $categoriaId = $_POST['categoria_id'] ?? '';
    $unidade = $_POST['unidade'] ?? '';
    $estoqueMinimo = $_POST['estoque_minimo'] ?? 0;
    $localizacao = trim($_POST['localizacao'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if (
        $codigo === '' ||
        $produto === '' ||
        $categoriaId === '' ||
        $unidade === ''
    ) {
        $erro = 'Preencha todos os campos obrigatórios.';
    } else {

        try {

            // Cadastra produto
            $stmt = $pdo->prepare("
                INSERT INTO produtos (
                    codigo,
                    nome,
                    categoria_id,
                    unidade,
                    estoque_minimo,
                    localizacao,
                    observacoes
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $codigo,
                $produto,
                $categoriaId,
                $unidade,
                $estoqueMinimo,
                $localizacao ?: null,
                $observacoes ?: null
            ]);


            header('Location: index.php?sucesso=1');
            exit;
        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {
                $erro = 'Já existe um produto com esse código.';
            } else {
                $erro = 'Erro ao cadastrar produto.';
            }
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
            <h1>Novo produto</h1>
            <p>Cadastre um novo material no almoxarifado.</p>
        </div>

        <a href="index.php" class="btn-secondary">
            ← Voltar
        </a>

    </div>


    <div class="form-card">

        <form action="#" method="POST">

            <div class="form-grid">

                <!-- CÓDIGO -->
                <div class="form-group">

                    <label for="codigo">
                        Código
                    </label>

                    <input
                        type="text"
                        id="codigo"
                        name="codigo"
                        placeholder="Ex: PAR001"
                        required>

                </div>


                <!-- PRODUTO -->
                <div class="form-group">

                    <label for="produto">
                        Nome do produto
                    </label>

                    <input
                        type="text"
                        id="produto"
                        name="produto"
                        placeholder="Ex: Parafuso 4.2"
                        required>

                </div>


                <!-- CATEGORIA -->
                <div class="form-group">

                    <label for="categoria">
                        Categoria
                    </label>

                    <select
                        id="categoria_id"
                        name="categoria_id"
                        required>

                        <option value="">
                            Selecione uma categoria
                        </option>

                        <?php foreach ($categorias as $categoria): ?>

                            <option value="<?= $categoria['id'] ?>">

                                <?= htmlspecialchars($categoria['nome']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- UNIDADE -->
                <div class="form-group">

                    <label for="unidade">
                        Unidade
                    </label>

                    <select id="unidade" name="unidade" required>

                        <option value="">
                            Selecione a unidade
                        </option>

                        <option value="un">
                            Unidade (un.)
                        </option>

                        <option value="m">
                            Metro (m)
                        </option>

                        <option value="kg">
                            Quilograma (kg)
                        </option>

                        <option value="cx">
                            Caixa (cx)
                        </option>

                        <option value="pc">
                            Peça (pc)
                        </option>

                    </select>

                </div>


                <!-- ESTOQUE MÍNIMO -->
                <div class="form-group">

                    <label for="estoque_minimo">
                        Estoque mínimo
                    </label>

                    <input
                        type="number"
                        id="estoque_minimo"
                        name="estoque_minimo"
                        placeholder="Ex: 10"
                        min="0">

                </div>


                <!-- LOCALIZAÇÃO -->
                <div class="form-group full">

                    <label for="localizacao">
                        Localização no almoxarifado
                    </label>

                    <input
                        type="text"
                        id="localizacao"
                        name="localizacao"
                        placeholder="Ex: Estante A - Gaveta 03">

                    <small>
                        Informe onde o material fica armazenado.
                    </small>

                </div>


                <!-- OBSERVAÇÕES -->
                <div class="form-group full">

                    <label for="observacoes">
                        Observações
                    </label>

                    <textarea
                        id="observacoes"
                        name="observacoes"
                        rows="4"
                        placeholder="Informações adicionais sobre o produto..."></textarea>

                </div>

            </div>


            <div class="form-actions">

                <a href="index.php" class="btn-secondary">
                    Cancelar
                </a>

                <button type="submit" class="btn-primary">
                    + Cadastrar produto
                </button>

            </div>

        </form>

    </div>

</main>