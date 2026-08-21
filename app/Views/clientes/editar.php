<?php

require_once '../../Config/database.php';


$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {
    header('Location: index.php');
    exit;
}


$stmt = $pdo->prepare("
    SELECT *
    FROM clientes
    WHERE id = ?
      AND ativo = 1
    LIMIT 1
");

$stmt->execute([$id]);

$cliente = $stmt->fetch();


if (!$cliente) {
    header('Location: index.php');
    exit;
}


$erro = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome =
        trim($_POST['nome'] ?? '');

    $tipoPessoa =
        $_POST['tipo_pessoa'] ?? 'fisica';

    $cpfCnpj =
        trim($_POST['cpf_cnpj'] ?? '');

    $telefone =
        trim($_POST['telefone'] ?? '');

    $email =
        trim($_POST['email'] ?? '');

    $observacoes =
        trim($_POST['observacoes'] ?? '');


    if ($nome === '') {

        $erro =
            'Informe o nome do cliente.';

    } elseif (
        !in_array(
            $tipoPessoa,
            ['fisica', 'juridica'],
            true
        )
    ) {

        $erro =
            'Tipo de pessoa inválido.';

    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE clientes

                SET
                    nome = ?,
                    tipo_pessoa = ?,
                    cpf_cnpj = ?,
                    telefone = ?,
                    email = ?,
                    observacoes = ?

                WHERE id = ?
            ");

            $stmt->execute([
                $nome,
                $tipoPessoa,
                $cpfCnpj ?: null,
                $telefone ?: null,
                $email ?: null,
                $observacoes ?: null,
                $id
            ]);


            header(
                'Location: index.php?editado=1'
            );

            exit;

        } catch (PDOException $e) {

            $erro =
                'Erro ao atualizar cliente.';
        }
    }
}


include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>


<main class="content">

    <div class="page-header">

        <div>

            <h1>
                Editar cliente
            </h1>

            <p>
                Atualize os dados cadastrados do cliente.
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


                <div class="form-group full">

                    <label for="nome">
                        Nome / Razão social *
                    </label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        value="<?= htmlspecialchars(
                            $_POST['nome']
                            ?? $cliente['nome']
                        ) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="tipo_pessoa">
                        Tipo
                    </label>

                    <?php
                    $tipoAtual =
                        $_POST['tipo_pessoa']
                        ?? $cliente['tipo_pessoa'];
                    ?>

                    <select
                        id="tipo_pessoa"
                        name="tipo_pessoa"
                    >

                        <option
                            value="fisica"
                            <?= $tipoAtual === 'fisica'
                                ? 'selected'
                                : '' ?>
                        >
                            Pessoa física
                        </option>

                        <option
                            value="juridica"
                            <?= $tipoAtual === 'juridica'
                                ? 'selected'
                                : '' ?>
                        >
                            Pessoa jurídica
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label for="cpf_cnpj">
                        CPF / CNPJ
                    </label>

                    <input
                        type="text"
                        id="cpf_cnpj"
                        name="cpf_cnpj"
                        value="<?= htmlspecialchars(
                            $_POST['cpf_cnpj']
                            ?? $cliente['cpf_cnpj']
                            ?? ''
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="telefone">
                        Telefone
                    </label>

                    <input
                        type="text"
                        id="telefone"
                        name="telefone"
                        value="<?= htmlspecialchars(
                            $_POST['telefone']
                            ?? $cliente['telefone']
                            ?? ''
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="email">
                        E-mail
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars(
                            $_POST['email']
                            ?? $cliente['email']
                            ?? ''
                        ) ?>"
                    >

                </div>


                <div class="form-group full">

                    <label for="observacoes">
                        Observações
                    </label>

                    <textarea
                        id="observacoes"
                        name="observacoes"
                        rows="4"
                    ><?= htmlspecialchars(
                        $_POST['observacoes']
                        ?? $cliente['observacoes']
                        ?? ''
                    ) ?></textarea>

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