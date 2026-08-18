<?php

require_once '../../Config/database.php';

$erro = '';


/*
|--------------------------------------------------------------------------
| BUSCAR CLIENTES ATIVOS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT id, nome
    FROM clientes
    WHERE ativo = 1
    ORDER BY nome ASC
");

$clientes = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| CADASTRAR OBRA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $clienteId = $_POST['cliente_id'] ?? '';
    $codigo = trim($_POST['codigo'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if (
        $clienteId === '' ||
        $nome === ''
    ) {

        $erro = 'Informe o cliente e o nome da obra.';

    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO obras (
                    cliente_id,
                    codigo,
                    nome,
                    endereco,
                    cidade,
                    observacoes
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $clienteId,
                $codigo ?: null,
                $nome,
                $endereco ?: null,
                $cidade ?: null,
                $observacoes ?: null
            ]);

            header('Location: index.php?sucesso=1');
            exit;

        } catch (PDOException $e) {

            $erro = 'Erro ao cadastrar obra.';

        }

    }

}


/*
|--------------------------------------------------------------------------
| BUSCAR OBRAS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        o.id,
        o.codigo,
        o.nome,
        o.endereco,
        o.cidade,
        o.status,
        o.created_at,

        c.nome AS cliente

    FROM obras o

    INNER JOIN clientes c
        ON c.id = o.cliente_id

    ORDER BY
        o.status = 'ativa' DESC,
        c.nome ASC,
        o.nome ASC
");

$obras = $stmt->fetchAll();


include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>


<main class="content">

    <div class="page-header">

        <div>
            <h1>Obras</h1>
            <p>Gerencie as obras e os materiais destinados a cada cliente.</p>
        </div>

    </div>


    <?php if (isset($_GET['sucesso'])): ?>

        <div class="alert-success">

            <i class="bi bi-check-circle"></i>

            Obra cadastrada com sucesso!

        </div>

    <?php endif; ?>


    <?php if ($erro): ?>

        <div class="alert-error">

            <i class="bi bi-exclamation-circle"></i>

            <?= htmlspecialchars($erro) ?>

        </div>

    <?php endif; ?>


    <div class="works-layout">


        <!-- =========================
             CADASTRO
        ========================== -->

        <section class="work-form-card">

            <h2>Nova obra</h2>

            <p>
                Vincule uma nova obra a um cliente cadastrado.
            </p>


            <form method="POST">


                <!-- CLIENTE -->

                <div class="form-group">

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

                            <option value="<?= $cliente['id'] ?>">

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
                        placeholder="Ex: Residência Alphaville"
                        required
                    >

                </div>


                <!-- ENDEREÇO -->

                <div class="form-group">

                    <label for="endereco">
                        Endereço
                    </label>

                    <input
                        type="text"
                        id="endereco"
                        name="endereco"
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
                        placeholder="Ex: São Paulo"
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
                        placeholder="Informações adicionais sobre a obra..."
                    ></textarea>

                </div>


                <button
                    type="submit"
                    class="btn-primary work-submit"
                >

                    <i class="bi bi-plus-lg"></i>

                    Cadastrar obra

                </button>

            </form>

        </section>


        <!-- =========================
             LISTAGEM
        ========================== -->

        <section class="works-card">

            <div class="works-card-header">

                <div>

                    <h2>Obras cadastradas</h2>

                    <p>
                        <?= count($obras) ?>
                        obra(s)
                    </p>

                </div>

            </div>


            <div class="works-table">

                <table>

                    <thead>

                        <tr>
                            <th>Obra</th>
                            <th>Cliente</th>
                            <th>Cidade</th>
                            <th>Status</th>
                            <th>Cadastro</th>
                        </tr>

                    </thead>


                    <tbody>


                    <?php if (empty($obras)): ?>

                        <tr>

                            <td
                                colspan="5"
                                class="empty-table"
                            >

                                Nenhuma obra cadastrada.

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach ($obras as $obra): ?>

                            <tr>


                                <!-- OBRA -->

                                <td>

                                    <div class="work-name">

                                        <div class="work-icon">

                                            <i class="bi bi-building"></i>

                                        </div>


                                        <div>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $obra['nome']
                                                ) ?>

                                            </strong>


                                            <?php if ($obra['codigo']): ?>

                                                <small>

                                                    <?= htmlspecialchars(
                                                        $obra['codigo']
                                                    ) ?>

                                                </small>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                </td>


                                <!-- CLIENTE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $obra['cliente']
                                    ) ?>

                                </td>


                                <!-- CIDADE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $obra['cidade'] ?: '-'
                                    ) ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php if ($obra['status'] === 'ativa'): ?>

                                        <span class="work-status active">
                                            Ativa
                                        </span>

                                    <?php elseif ($obra['status'] === 'finalizada'): ?>

                                        <span class="work-status finished">
                                            Finalizada
                                        </span>

                                    <?php else: ?>

                                        <span class="work-status cancelled">
                                            Cancelada
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- CADASTRO -->

                                <td>

                                    <?= date(
                                        'd/m/Y',
                                        strtotime($obra['created_at'])
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