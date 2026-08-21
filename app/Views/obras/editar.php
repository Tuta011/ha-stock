<?php

require_once '../../Config/database.php';


/*
|--------------------------------------------------------------------------
| ID DA OBRA
|--------------------------------------------------------------------------
*/

$obraId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$obraId) {

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| BUSCAR OBRA
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        cliente_id,
        codigo,
        nome,
        endereco,
        cidade,
        observacoes,
        status

    FROM obras

    WHERE id = ?

    LIMIT 1
");

$stmt->execute([
    $obraId
]);

$obra = $stmt->fetch();


if (!$obra) {

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| BUSCAR CLIENTES ATIVOS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        nome

    FROM clientes

    WHERE ativo = 1

    ORDER BY nome ASC
");

$clientes = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| ERRO
|--------------------------------------------------------------------------
*/

$erro = '';


/*
|--------------------------------------------------------------------------
| ATUALIZAR OBRA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $clienteId =
        filter_input(
            INPUT_POST,
            'cliente_id',
            FILTER_VALIDATE_INT
        );

    $codigo =
        trim($_POST['codigo'] ?? '');

    $nome =
        trim($_POST['nome'] ?? '');

    $endereco =
        trim($_POST['endereco'] ?? '');

    $cidade =
        trim($_POST['cidade'] ?? '');

    $observacoes =
        trim($_POST['observacoes'] ?? '');

    $status =
        $_POST['status'] ?? 'ativa';


    /*
    |--------------------------------------------------------------------------
    | VALIDAÇÕES
    |--------------------------------------------------------------------------
    */

    if (!$clienteId || $nome === '') {

        $erro =
            'Informe o cliente e o nome da obra.';

    } elseif (
        !in_array(
            $status,
            [
                'ativa',
                'finalizada',
                'cancelada'
            ],
            true
        )
    ) {

        $erro =
            'Status da obra inválido.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | VERIFICAR CLIENTE
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id

                FROM clientes

                WHERE id = ?
                  AND ativo = 1

                LIMIT 1
            ");

            $stmt->execute([
                $clienteId
            ]);


            if (!$stmt->fetchColumn()) {

                $erro =
                    'O cliente selecionado não existe ou está inativo.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | UPDATE
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    UPDATE obras

                    SET
                        cliente_id = ?,
                        codigo = ?,
                        nome = ?,
                        endereco = ?,
                        cidade = ?,
                        observacoes = ?,
                        status = ?

                    WHERE id = ?
                ");

                $stmt->execute([
                    $clienteId,
                    $codigo ?: null,
                    $nome,
                    $endereco ?: null,
                    $cidade ?: null,
                    $observacoes ?: null,
                    $status,
                    $obraId
                ]);


                /*
                |--------------------------------------------------------------------------
                | SUCESSO
                |--------------------------------------------------------------------------
                */

                header(
                    'Location: index.php?editado=1'
                );

                exit;
            }

        } catch (PDOException $e) {

            $erro =
                'Erro ao atualizar a obra.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| VALORES DO FORMULÁRIO
|--------------------------------------------------------------------------
*/

$clienteAtual =
    $_POST['cliente_id']
    ?? $obra['cliente_id'];

$codigoAtual =
    $_POST['codigo']
    ?? $obra['codigo']
    ?? '';

$nomeAtual =
    $_POST['nome']
    ?? $obra['nome'];

$enderecoAtual =
    $_POST['endereco']
    ?? $obra['endereco']
    ?? '';

$cidadeAtual =
    $_POST['cidade']
    ?? $obra['cidade']
    ?? '';

$observacoesAtuais =
    $_POST['observacoes']
    ?? $obra['observacoes']
    ?? '';

$statusAtual =
    $_POST['status']
    ?? $obra['status'];


include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>


<main class="content">


    <!-- CABEÇALHO -->

    <div class="page-header">

        <div>

            <h1>
                Editar obra
            </h1>

            <p>
                Atualize as informações da obra.
            </p>

        </div>


        <a
            href="detalhes.php?id=<?= $obraId ?>"
            class="btn-secondary"
        >

            <i class="bi bi-arrow-left"></i>

            Voltar para obra

        </a>

    </div>


    <!-- ERRO -->

    <?php if ($erro): ?>

        <div class="alert-error">

            <i class="bi bi-exclamation-circle"></i>

            <?= htmlspecialchars($erro) ?>

        </div>

    <?php endif; ?>


    <!-- FORMULÁRIO -->

    <div class="form-card">

        <form method="POST">

            <div class="form-grid">


                <!-- CLIENTE -->

                <div class="form-group full">

                    <label for="cliente_id">
                        Cliente *
                    </label>


                    <select
                        id="cliente_id"
                        name="cliente_id"
                        required
                    >

                        <option value="">
                            Selecione o cliente
                        </option>


                        <?php foreach ($clientes as $cliente): ?>

                            <option
                                value="<?= $cliente['id'] ?>"

                                <?= (string) $clienteAtual ===
                                    (string) $cliente['id']
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    $cliente['nome']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- CÓDIGO -->

                <div class="form-group">

                    <label for="codigo">
                        Código da obra
                    </label>

                    <input
                        type="text"
                        id="codigo"
                        name="codigo"
                        value="<?= htmlspecialchars(
                            $codigoAtual
                        ) ?>"
                        placeholder="Ex: OBR001"
                    >

                </div>


                <!-- NOME -->

                <div class="form-group">

                    <label for="nome">
                        Nome da obra *
                    </label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        value="<?= htmlspecialchars(
                            $nomeAtual
                        ) ?>"
                        placeholder="Ex: Residência Alphaville"
                        required
                    >

                </div>


                <!-- ENDEREÇO -->

                <div class="form-group full">

                    <label for="endereco">
                        Endereço
                    </label>

                    <input
                        type="text"
                        id="endereco"
                        name="endereco"
                        value="<?= htmlspecialchars(
                            $enderecoAtual
                        ) ?>"
                        placeholder="Rua, número, bairro..."
                    >

                </div>


                <!-- CIDADE -->

                <div class="form-group">

                    <label for="cidade">
                        Cidade
                    </label>

                    <input
                        type="text"
                        id="cidade"
                        name="cidade"
                        value="<?= htmlspecialchars(
                            $cidadeAtual
                        ) ?>"
                        placeholder="Ex: São Paulo"
                    >

                </div>


                <!-- STATUS -->

                <div class="form-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option
                            value="ativa"
                            <?= $statusAtual === 'ativa'
                                ? 'selected'
                                : '' ?>
                        >
                            Ativa
                        </option>


                        <option
                            value="finalizada"
                            <?= $statusAtual === 'finalizada'
                                ? 'selected'
                                : '' ?>
                        >
                            Finalizada
                        </option>


                        <option
                            value="cancelada"
                            <?= $statusAtual === 'cancelada'
                                ? 'selected'
                                : '' ?>
                        >
                            Cancelada
                        </option>

                    </select>

                </div>


                <!-- OBSERVAÇÕES -->

                <div class="form-group full">

                    <label for="observacoes">
                        Observações
                    </label>

                    <textarea
                        id="observacoes"
                        name="observacoes"
                        rows="5"
                        placeholder="Informações adicionais sobre a obra..."
                    ><?= htmlspecialchars(
                        $observacoesAtuais
                    ) ?></textarea>

                </div>

            </div>


            <!-- AÇÕES -->

            <div class="form-actions">

                <a
                    href="detalhes.php?id=<?= $obraId ?>"
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