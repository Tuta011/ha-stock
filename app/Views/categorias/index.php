<?php

require_once '../../Config/database.php';

$erro = '';

/* CADASTRAR CATEGORIA */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = trim($_POST['nome'] ?? '');

    if ($nome === '') {

        $erro = 'Informe o nome da categoria.';
    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO categorias (nome)
                VALUES (?)
            ");

            $stmt->execute([$nome]);

            header('Location: index.php?sucesso=1');
            exit;
        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {

                $erro = 'Essa categoria já está cadastrada.';
            } else {

                $erro = 'Erro ao cadastrar categoria.';
            }
        }
    }
}


/* BUSCAR CATEGORIAS */
$stmt = $pdo->query("
    SELECT
        c.id,
        c.nome,
        c.created_at,
        COUNT(p.id) AS total_produtos

    FROM categorias c

    LEFT JOIN produtos p
        ON p.categoria_id = c.id
        AND p.ativo = 1

    GROUP BY
        c.id,
        c.nome,
        c.created_at

    ORDER BY c.nome
");

$categorias = $stmt->fetchAll();


include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>

<main class="content">

    <div class="page-header">

        <div>
            <h1>Categorias</h1>
            <p>Organize os materiais do almoxarifado.</p>
        </div>

    </div>


    <?php if (isset($_GET['sucesso'])): ?>

        <div class="alert-success">
            <i class="bi bi-check-circle"></i>

            Categoria cadastrada com sucesso!
        </div>

    <?php endif; ?>


    <?php if ($erro): ?>

        <div class="alert-error">
            <?= htmlspecialchars($erro) ?>
        </div>

    <?php endif; ?>


    <div class="categories-layout">

        <!-- CADASTRO -->

        <section class="category-form-card">

            <h2>Nova categoria</h2>

            <p>
                Cadastre uma categoria para organizar seus produtos.
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
                        placeholder="Ex: Parafusos"
                        required>

                </div>


                <button
                    type="submit"
                    class="btn-primary category-submit">
                    <i class="bi bi-plus-lg"></i>

                    Cadastrar categoria
                </button>

            </form>

        </section>


        <!-- LISTAGEM -->

        <section class="categories-card">

            <div class="categories-card-header">

                <div>
                    <h2>Categorias cadastradas</h2>

                    <p>
                        <?= count($categorias) ?>
                        categoria(s)
                    </p>
                </div>

            </div>


            <div class="categories-table">

                <table>

                    <thead>

                        <tr>
                            <th>Categoria</th>
                            <th>Produtos</th>
                            <th>Cadastro</th>
                            <th>Ações</th>
                        </tr>

                    </thead>


                    <tbody>

                        <?php if (empty($categorias)): ?>

                            <tr>

                                <td
                                    colspan="4"
                                    class="empty-table">
                                    Nenhuma categoria cadastrada.
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($categorias as $categoria): ?>

                                <tr>

                                    <td>

                                        <div class="category-name">

                                            <div class="category-icon">
                                                <i class="bi bi-tag"></i>
                                            </div>

                                            <strong>
                                                <?= htmlspecialchars($categoria['nome']) ?>
                                            </strong>

                                        </div>

                                    </td>


                                    <td>

                                        <span class="category-count">

                                            <?= $categoria['total_produtos'] ?>

                                            produto(s)

                                        </span>

                                    </td>


                                    <td>

                                        <?= date(
                                            'd/m/Y',
                                            strtotime($categoria['created_at'])
                                        ) ?>

                                    </td>
                                    
                                    <td>

                                        <div class="category-actions">

                                            <!-- EDITAR -->

                                            <a
                                                href="editar.php?id=<?= $categoria['id'] ?>"
                                                class="category-action edit"
                                                title="Editar categoria">
                                                <i class="bi bi-pencil"></i>
                                            </a>


                                            <!-- EXCLUIR -->

                                            <a
                                                href="excluir.php?id=<?= $categoria['id'] ?>"
                                                class="category-action delete"
                                                title="Excluir categoria"
                                                onclick="return confirm('Deseja realmente excluir esta categoria?');">
                                                <i class="bi bi-trash"></i>
                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </div>

</main>

<?php include '../../Includes/footer.php'; ?>