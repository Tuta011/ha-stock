<?php

require_once '../../Config/database.php';

$erro = '';


/*
|--------------------------------------------------------------------------
| CADASTRAR CLIENTE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = trim($_POST['nome'] ?? '');
    $tipoPessoa = $_POST['tipo_pessoa'] ?? 'fisica';
    $cpfCnpj = trim($_POST['cpf_cnpj'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if ($nome === '') {

        $erro = 'Informe o nome do cliente.';

    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO clientes (
                    nome,
                    tipo_pessoa,
                    cpf_cnpj,
                    telefone,
                    email,
                    observacoes
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $nome,
                $tipoPessoa,
                $cpfCnpj ?: null,
                $telefone ?: null,
                $email ?: null,
                $observacoes ?: null
            ]);

            header('Location: index.php?sucesso=1');
            exit;

        } catch (PDOException $e) {

            $erro = 'Erro ao cadastrar cliente.';

        }

    }

}


/*
|--------------------------------------------------------------------------
| BUSCAR CLIENTES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        nome,
        tipo_pessoa,
        cpf_cnpj,
        telefone,
        email,
        ativo,
        created_at

    FROM clientes

    WHERE ativo = 1

    ORDER BY nome ASC
");

$clientes = $stmt->fetchAll();


include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>

<main class="content">

    <div class="page-header">

        <div>
            <h1>Clientes</h1>
            <p>Gerencie os clientes e suas futuras obras.</p>
        </div>

    </div>


    <?php if (isset($_GET['sucesso'])): ?>

        <div class="alert-success">
            <i class="bi bi-check-circle"></i>
            Cliente cadastrado com sucesso!
        </div>

    <?php endif; ?>


    <?php if ($erro): ?>

        <div class="alert-error">
            <i class="bi bi-exclamation-circle"></i>
            <?= htmlspecialchars($erro) ?>
        </div>

    <?php endif; ?>


    <div class="clients-layout">

        <!-- CADASTRO -->

        <section class="client-form-card">

            <h2>Novo cliente</h2>

            <p>
                Cadastre o cliente antes de criar uma obra.
            </p>


            <form method="POST">

                <div class="form-group">

                    <label for="nome">
                        Nome / Razão social *
                    </label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        placeholder="Ex: João da Silva"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="tipo_pessoa">
                        Tipo
                    </label>

                    <select
                        id="tipo_pessoa"
                        name="tipo_pessoa"
                    >

                        <option value="fisica">
                            Pessoa física
                        </option>

                        <option value="juridica">
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
                        placeholder="Opcional"
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
                        placeholder="(00) 00000-0000"
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
                        placeholder="cliente@email.com"
                    >

                </div>


                <div class="form-group">

                    <label for="observacoes">
                        Observações
                    </label>

                    <textarea
                        id="observacoes"
                        name="observacoes"
                        rows="4"
                        placeholder="Informações adicionais..."
                    ></textarea>

                </div>


                <button
                    type="submit"
                    class="btn-primary client-submit"
                >
                    <i class="bi bi-plus-lg"></i>
                    Cadastrar cliente
                </button>

            </form>

        </section>


        <!-- LISTAGEM -->

        <section class="clients-card">

            <div class="clients-card-header">

                <div>
                    <h2>Clientes cadastrados</h2>

                    <p>
                        <?= count($clientes) ?>
                        cliente(s)
                    </p>
                </div>

            </div>


            <div class="clients-table">

                <table>

                    <thead>

                        <tr>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Telefone</th>
                            <th>E-mail</th>
                            <th>Cadastro</th>
                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($clientes)): ?>

                        <tr>

                            <td
                                colspan="5"
                                class="empty-table"
                            >
                                Nenhum cliente cadastrado.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($clientes as $cliente): ?>

                            <tr>

                                <td>

                                    <div class="client-name">

                                        <div class="client-icon">
                                            <i class="bi bi-person"></i>
                                        </div>

                                        <div>

                                            <strong>
                                                <?= htmlspecialchars($cliente['nome']) ?>
                                            </strong>

                                            <?php if ($cliente['cpf_cnpj']): ?>

                                                <small>
                                                    <?= htmlspecialchars($cliente['cpf_cnpj']) ?>
                                                </small>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                </td>


                                <td>

                                    <?=
                                        $cliente['tipo_pessoa'] === 'juridica'
                                            ? 'Pessoa jurídica'
                                            : 'Pessoa física'
                                    ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $cliente['telefone'] ?: '-'
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $cliente['email'] ?: '-'
                                    ) ?>

                                </td>


                                <td>

                                    <?= date(
                                        'd/m/Y',
                                        strtotime($cliente['created_at'])
                                    ) ?>

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