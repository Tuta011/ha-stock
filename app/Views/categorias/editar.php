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
| BUSCAR CATEGORIA
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, nome
    FROM categorias
    WHERE id = ?
");

$stmt->execute([$id]);

$categoria = $stmt->fetch();

if (!$categoria) {
    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| ATUALIZAR
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = trim($_POST['nome'] ?? '');

    if ($nome === '') {

        $erro = 'Informe o nome da categoria.';

    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE categorias
                SET nome = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $nome,
                $id
            ]);

            header('Location: index.php?editado=1');
            exit;

        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {

                $erro = 'Já existe uma categoria com esse nome.';

            } else {

                $erro = 'Erro ao atualizar categoria.';

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
            <h1>Editar categoria</h1>
            <p>Altere as informações da categoria.</p>
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


    <section class="category-form-card">

        <h2>Informações da categoria</h2>

        <p>
            Altere o nome da categoria abaixo.
        </p>


        <form method="POST">

            <div class="form-group">

                <label for="nome">
                    Nome da categoria *
                </label>

                <input
                    type="text"
                    id="nome"
                    name="nome"
                    value="<?= htmlspecialchars($categoria['nome']) ?>"
                    required
                >

            </div>


            <div class="category-edit-actions">

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

    </section>

</main>


<?php include '../../Includes/footer.php'; ?>