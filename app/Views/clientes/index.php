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

    } elseif (
        !in_array(
            $tipoPessoa,
            ['fisica', 'juridica'],
            true
        )
    ) {

        $erro = 'Tipo de pessoa inválido.';

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
        c.id,
        c.nome,
        c.tipo_pessoa,
        c.cpf_cnpj,
        c.telefone,
        c.email,
        c.ativo,
        c.created_at,

        COUNT(o.id) AS total_obras

    FROM clientes c

    LEFT JOIN obras o
        ON o.cliente_id = c.id

    WHERE c.ativo = 1

    GROUP BY
        c.id,
        c.nome,
        c.tipo_pessoa,
        c.cpf_cnpj,
        c.telefone,
        c.email,
        c.ativo,
        c.created_at

    ORDER BY c.nome ASC
");

$clientes = $stmt->fetchAll();


include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>

<main class="content">

    <!-- CABEÇALHO -->

    <div class="page-header">

        <div>

            <h1>Clientes</h1>

            <p>
                Gerencie os clientes e suas futuras obras.
            </p>

        </div>

    </div>


    <!-- SUCESSO CADASTRO -->

    <?php if (isset($_GET['sucesso'])): ?>

        <div class="alert-success">

            <i class="bi bi-check-circle"></i>

            Cliente cadastrado com sucesso!

        </div>

    <?php endif; ?>


    <!-- SUCESSO EDIÇÃO -->

    <?php if (isset($_GET['editado'])): ?>

        <div class="alert-success">

            <i class="bi bi-check-circle"></i>

            Cliente atualizado com sucesso!

        </div>

    <?php endif; ?>


    <!-- SUCESSO EXCLUSÃO -->

    <?php if (isset($_GET['excluido'])): ?>

        <div class="alert-success">

            <i class="bi bi-check-circle"></i>

            Cliente excluído com sucesso!

        </div>

    <?php endif; ?>


    <!-- CLIENTE COM OBRAS -->

    <?php if (
        isset($_GET['erro']) &&
        $_GET['erro'] === 'possui_obras'
    ): ?>

        <div class="alert-error">

            <i class="bi bi-exclamation-circle"></i>

            Não é possível excluir um cliente que possui obras cadastradas.

        </div>

    <?php endif; ?>


    <!-- ERRO EXCLUSÃO -->

    <?php if (
        isset($_GET['erro']) &&
        $_GET['erro'] === 'excluir'
    ): ?>

        <div class="alert-error">

            <i class="bi bi-exclamation-circle"></i>

            Não foi possível excluir o cliente.

        </div>

    <?php endif; ?>


    <!-- ERRO CADASTRO -->

    <?php if ($erro): ?>

        <div class="alert-error">

            <i class="bi bi-exclamation-circle"></i>

            <?= htmlspecialchars($erro) ?>

        </div>

    <?php endif; ?>


    <div class="clients-layout">


        <!-- =========================
             CADASTRO
        ========================== -->

        <section class="client-form-card">

            <h2>
                Novo cliente
            </h2>

            <p>
                Cadastre o cliente antes de criar uma obra.
            </p>


            <form method="POST">

                <!-- NOME -->

                <div class="form-group">

                    <label for="nome">
                        Nome / Razão social *
                    </label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        placeholder="Ex: João da Silva"
                        value="<?= htmlspecialchars(
                            $_POST['nome'] ?? ''
                        ) ?>"
                        required
                    >

                </div>


                <!-- TIPO -->

                <div class="form-group">

                    <label for="tipo_pessoa">
                        Tipo
                    </label>

                    <select
                        id="tipo_pessoa"
                        name="tipo_pessoa"
                    >

                        <option
                            value="fisica"
                            <?= ($_POST['tipo_pessoa'] ?? 'fisica') === 'fisica'
                                ? 'selected'
                                : '' ?>
                        >
                            Pessoa física
                        </option>


                        <option
                            value="juridica"
                            <?= ($_POST['tipo_pessoa'] ?? '') === 'juridica'
                                ? 'selected'
                                : '' ?>
                        >
                            Pessoa jurídica
                        </option>

                    </select>

                </div>


                <!-- CPF / CNPJ -->

                <div class="form-group">

                    <label for="cpf_cnpj">
                        CPF / CNPJ
                    </label>

                    <input
                        type="text"
                        id="cpf_cnpj"
                        name="cpf_cnpj"
                        placeholder="Opcional"
                        value="<?= htmlspecialchars(
                            $_POST['cpf_cnpj'] ?? ''
                        ) ?>"
                    >

                </div>


                <!-- TELEFONE -->

                <div class="form-group">

                    <label for="telefone">
                        Telefone
                    </label>

                    <input
                        type="text"
                        id="telefone"
                        name="telefone"
                        placeholder="(00) 00000-0000"
                        value="<?= htmlspecialchars(
                            $_POST['telefone'] ?? ''
                        ) ?>"
                    >

                </div>


                <!-- E-MAIL -->

                <div class="form-group">

                    <label for="email">
                        E-mail
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="cliente@email.com"
                        value="<?= htmlspecialchars(
                            $_POST['email'] ?? ''
                        ) ?>"
                    >

                </div>


                <!-- OBSERVAÇÕES -->

                <div class="form-group">

                    <label for="observacoes">
                        Observações
                    </label>

                    <textarea
                        id="observacoes"
                        name="observacoes"
                        rows="4"
                        placeholder="Informações adicionais..."
                    ><?= htmlspecialchars(
                        $_POST['observacoes'] ?? ''
                    ) ?></textarea>

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



        <!-- =========================
             LISTAGEM
        ========================== -->

        <section class="clients-card">

            <div class="clients-card-header">

                <div>

                    <h2>
                        Clientes cadastrados
                    </h2>

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
                            <th>Ações</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($clientes)): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="empty-table"
                            >

                                Nenhum cliente cadastrado.

                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach ($clientes as $cliente): ?>

                            <tr>


                                <!-- CLIENTE -->

                                <td>

                                    <div class="client-name">

                                        <div class="client-icon">

                                            <i class="bi bi-person"></i>

                                        </div>


                                        <div>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $cliente['nome']
                                                ) ?>

                                            </strong>


                                            <?php if ($cliente['cpf_cnpj']): ?>

                                                <small>

                                                    <?= htmlspecialchars(
                                                        $cliente['cpf_cnpj']
                                                    ) ?>

                                                </small>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                </td>


                                <!-- TIPO -->

                                <td>

                                    <?= $cliente['tipo_pessoa'] === 'juridica'
                                        ? 'Pessoa jurídica'
                                        : 'Pessoa física' ?>

                                </td>


                                <!-- TELEFONE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $cliente['telefone'] ?: '-'
                                    ) ?>

                                </td>


                                <!-- E-MAIL -->

                                <td>

                                    <?= htmlspecialchars(
                                        $cliente['email'] ?: '-'
                                    ) ?>

                                </td>


                                <!-- CADASTRO -->

                                <td>

                                    <?= date(
                                        'd/m/Y',
                                        strtotime(
                                            $cliente['created_at']
                                        )
                                    ) ?>

                                </td>


                                <!-- AÇÕES -->

                                <td>

                                    <div class="client-actions">


                                        <!-- EDITAR -->

                                        <a
                                            href="editar.php?id=<?= $cliente['id'] ?>"
                                            class="movement-detail-button"
                                            title="Editar cliente"
                                        >

                                            <i class="bi bi-pencil"></i>

                                        </a>


                                        <!-- EXCLUIR -->

                                        <button
                                            type="button"
                                            class="movement-detail-button client-delete-button open-client-delete-modal"
                                            title="Excluir cliente"

                                            data-client-id="<?= $cliente['id'] ?>"

                                            data-client-name="<?= htmlspecialchars(
                                                $cliente['nome'],
                                                ENT_QUOTES
                                            ) ?>"

                                            data-client-obras="<?= (int) $cliente['total_obras'] ?>"
                                        >

                                            <i class="bi bi-trash"></i>

                                        </button>


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



    <!-- =========================
         MODAL EXCLUIR CLIENTE
    ========================== -->

    <div
        class="delete-modal-overlay"
        id="deleteClientModal"
    >

        <div class="delete-modal">


            <!-- FECHAR -->

            <button
                type="button"
                class="delete-modal-close"
                id="closeClientDeleteModal"
            >

                <i class="bi bi-x-lg"></i>

            </button>


            <!-- ÍCONE -->

            <div class="delete-modal-icon">

                <i class="bi bi-trash3"></i>

            </div>


            <!-- TÍTULO -->

            <h2>
                Excluir cliente?
            </h2>


            <!-- TEXTO -->

            <p class="delete-modal-description">

                Deseja realmente excluir

                <strong id="deleteClientName">
                    este cliente
                </strong>?

            </p>


            <!-- AVISO -->

            <div
                class="delete-modal-warning"
                id="clientDeleteWarning"
            >

                <i class="bi bi-exclamation-triangle"></i>


                <div>

                    <strong>
                        Atenção
                    </strong>

                    <span id="clientDeleteWarningText">
                        Esta ação não pode ser desfeita.
                    </span>

                </div>

            </div>


            <!-- FORM -->

            <form
                action="excluir.php"
                method="POST"
                id="deleteClientForm"
            >

                <input
                    type="hidden"
                    name="id"
                    id="deleteClientId"
                >


                <div class="delete-modal-actions">


                    <button
                        type="button"
                        class="btn-secondary"
                        id="cancelClientDeleteModal"
                    >

                        Cancelar

                    </button>


                    <button
                        type="submit"
                        class="btn-delete-confirm"
                        id="confirmClientDelete"
                    >

                        <i class="bi bi-trash3"></i>

                        Sim, excluir cliente

                    </button>


                </div>

            </form>

        </div>

    </div>

</main>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const modal =
            document.getElementById(
                'deleteClientModal'
            );

        const clientName =
            document.getElementById(
                'deleteClientName'
            );

        const clientId =
            document.getElementById(
                'deleteClientId'
            );

        const warningText =
            document.getElementById(
                'clientDeleteWarningText'
            );

        const confirmButton =
            document.getElementById(
                'confirmClientDelete'
            );

        const cancelButton =
            document.getElementById(
                'cancelClientDeleteModal'
            );

        const closeButton =
            document.getElementById(
                'closeClientDeleteModal'
            );

        const buttons =
            document.querySelectorAll(
                '.open-client-delete-modal'
            );


        /*
        |--------------------------------------------------------------------------
        | FECHAR MODAL
        |--------------------------------------------------------------------------
        */

        function fecharModal() {

            modal.classList.remove(
                'active'
            );

            document.body.style.overflow =
                '';
        }


        /*
        |--------------------------------------------------------------------------
        | ABRIR MODAL
        |--------------------------------------------------------------------------
        */

        buttons.forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    function () {

                        const id =
                            this.dataset.clientId;

                        const nome =
                            this.dataset.clientName;

                        const obras =
                            parseInt(
                                this.dataset.clientObras
                                || '0',
                                10
                            );


                        clientId.value =
                            id;

                        clientName.textContent =
                            nome;


                        /*
                        | CLIENTE POSSUI OBRAS
                        */

                        if (obras > 0) {

                            warningText.textContent =
                                'Este cliente possui ' +
                                obras +
                                ' obra(s) vinculada(s) e não pode ser excluído.';

                            confirmButton.disabled =
                                true;

                            confirmButton.innerHTML =
                                '<i class="bi bi-lock"></i> Cliente possui obras';


                        /*
                        | PODE EXCLUIR
                        */

                        } else {

                            warningText.textContent =
                                'Esta ação não pode ser desfeita.';

                            confirmButton.disabled =
                                false;

                            confirmButton.innerHTML =
                                '<i class="bi bi-trash3"></i> Sim, excluir cliente';
                        }


                        modal.classList.add(
                            'active'
                        );

                        document.body.style.overflow =
                            'hidden';

                    }
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | CANCELAR
        |--------------------------------------------------------------------------
        */

        cancelButton.addEventListener(
            'click',
            fecharModal
        );


        /*
        |--------------------------------------------------------------------------
        | X
        |--------------------------------------------------------------------------
        */

        closeButton.addEventListener(
            'click',
            fecharModal
        );


        /*
        |--------------------------------------------------------------------------
        | CLICAR FORA
        |--------------------------------------------------------------------------
        */

        modal.addEventListener(
            'click',
            function (event) {

                if (event.target === modal) {

                    fecharModal();
                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | ESC
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Escape' &&
                    modal.classList.contains(
                        'active'
                    )
                ) {

                    fecharModal();
                }

            }
        );

    }
);

</script>


<?php include '../../Includes/footer.php'; ?>