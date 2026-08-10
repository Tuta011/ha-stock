<?php

require_once '../../Config/database.php';

$erro = '';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| BUSCAR CATEGORIAS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT id, nome
    FROM categorias
    ORDER BY nome
");

$categorias = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| BUSCAR PRODUTO
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM produtos
    WHERE id = ?
      AND ativo = 1
");

$stmt->execute([$id]);

$produto = $stmt->fetch();

if (!$produto) {
    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| ATUALIZAR PRODUTO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codigo = trim($_POST['codigo'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $categoriaId = $_POST['categoria_id'] ?? '';
    $unidade = trim($_POST['unidade'] ?? '');
    $estoqueMinimo = $_POST['estoque_minimo'] ?? '';
    $localizacao = trim($_POST['localizacao'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if (
        $codigo === '' ||
        $nome === '' ||
        $categoriaId === '' ||
        $unidade === '' ||
        $estoqueMinimo === ''
    ) {

        $erro = 'Preencha todos os campos obrigatórios.';

    } elseif ((float) $estoqueMinimo < 0) {

        $erro = 'O estoque mínimo não pode ser negativo.';

    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE produtos

                SET
                    codigo = ?,
                    nome = ?,
                    categoria_id = ?,
                    unidade = ?,
                    estoque_minimo = ?,
                    localizacao = ?,
                    observacoes = ?

                WHERE id = ?
            ");

            $stmt->execute([
                $codigo,
                $nome,
                $categoriaId,
                $unidade,
                $estoqueMinimo,
                $localizacao ?: null,
                $observacoes ?: null,
                $id
            ]);

            header('Location: index.php?editado=1');
            exit;

        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {

                $erro = 'Já existe outro produto com esse código.';

            } else {

                $erro = 'Erro ao atualizar o produto.';

            }

        }

    }

}


include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>


<main class="content">

    <div class="page-header">

        <div>

            <h1>Editar produto</h1>

            <p>
                Altere as informações do material.
            </p>

        </div>


        <a
            href="index.php"
            class="btn-secondary"
        >
            <i class="bi bi-arrow-left"></i>
            Voltar
        </a>

    </div>


    <?php if ($erro): ?>

        <div class="alert-error">

            <i class="bi bi-exclamation-circle"></i>

            <?= htmlspecialchars($erro) ?>

        </div>

    <?php endif; ?>


    <div class="form-card">

        <form method="POST">

            <div class="form-grid">


                <!-- CÓDIGO -->

                <div class="form-group">

                    <label for="codigo">
                        Código *
                    </label>

                    <input
                        type="text"
                        id="codigo"
                        name="codigo"
                        value="<?= htmlspecialchars($produto['codigo']) ?>"
                        required
                    >

                </div>


                <!-- NOME -->

                <div class="form-group">

                    <label for="nome">
                        Nome do produto *
                    </label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        value="<?= htmlspecialchars($produto['nome']) ?>"
                        required
                    >

                </div>


                <!-- CATEGORIA -->

                <div class="form-group">

                    <label for="categoria_id">
                        Categoria *
                    </label>

                    <select
                        id="categoria_id"
                        name="categoria_id"
                        required
                    >

                        <option value="">
                            Selecione
                        </option>

                        <?php foreach ($categorias as $categoria): ?>

                            <option
                                value="<?= $categoria['id'] ?>"
                                <?= $categoria['id'] == $produto['categoria_id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= htmlspecialchars($categoria['nome']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- UNIDADE -->

                <div class="form-group">

                    <label for="unidade">
                        Unidade *
                    </label>

                    <select
                        id="unidade"
                        name="unidade"
                        required
                    >

                        <?php

                        $unidades = [
                            'un' => 'Unidade (un)',
                            'm'  => 'Metro (m)',
                            'kg' => 'Quilograma (kg)',
                            'cx' => 'Caixa (cx)',
                            'pct' => 'Pacote (pct)'
                        ];

                        ?>

                        <?php foreach ($unidades as $valor => $descricao): ?>

                            <option
                                value="<?= $valor ?>"
                                <?= $produto['unidade'] === $valor
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= $descricao ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- ESTOQUE MÍNIMO -->

                <div class="form-group">

                    <label for="estoque_minimo">
                        Estoque mínimo *
                    </label>

                    <input
                        type="number"
                        id="estoque_minimo"
                        name="estoque_minimo"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars($produto['estoque_minimo']) ?>"
                        required
                    >

                </div>


                <!-- LOCALIZAÇÃO -->

                <div class="form-group">

                    <label for="localizacao">
                        Localização
                    </label>

                    <input
                        type="text"
                        id="localizacao"
                        name="localizacao"
                        value="<?= htmlspecialchars($produto['localizacao'] ?? '') ?>"
                        placeholder="Ex: Estante A - Gaveta 03"
                    >

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
                    ><?= htmlspecialchars($produto['observacoes'] ?? '') ?></textarea>

                </div>


            </div>


            <div class="form-actions">

                <a
                    href="index.php"
                    class="btn-secondary"
                >
                    Cancelar
                </a>


                <button
                    type="submit"
                    class="btn-primary"
                >
                    <i class="bi bi-check-lg"></i>

                    Salvar alterações
                </button>

            </div>

        </form>

    </div>

</main>


<?php include '../../Includes/footer.php'; ?>