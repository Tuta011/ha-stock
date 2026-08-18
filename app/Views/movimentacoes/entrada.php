<?php

require_once '../../Config/database.php';


/*
|--------------------------------------------------------------------------
| PRODUTO VINDO PELA URL
|--------------------------------------------------------------------------
*/

$produtoSelecionado = filter_input(
    INPUT_GET,
    'produto_id',
    FILTER_VALIDATE_INT
);

if (!$produtoSelecionado) {
    $produtoSelecionado = '';
}


/*
|--------------------------------------------------------------------------
| VARIÁVEIS
|--------------------------------------------------------------------------
*/

$erro = '';

$tipoEstoqueSelecionado = $_POST['tipo_estoque'] ?? 'geral';
$clienteSelecionado = $_POST['cliente_id'] ?? '';
$obraSelecionada = $_POST['obra_id'] ?? '';


/*
|--------------------------------------------------------------------------
| BUSCAR PRODUTOS ATIVOS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        codigo,
        nome

    FROM produtos

    WHERE ativo = 1

    ORDER BY nome ASC
");

$produtos = $stmt->fetchAll();


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
| BUSCAR OBRAS ATIVAS
|--------------------------------------------------------------------------
|
| Vamos carregar as obras junto com o cliente.
| O JavaScript filtrará as obras quando o cliente for escolhido.
|
*/

$stmt = $pdo->query("
    SELECT
        o.id,
        o.cliente_id,
        o.codigo,
        o.nome,
        c.nome AS cliente

    FROM obras o

    INNER JOIN clientes c
        ON c.id = o.cliente_id

    WHERE
        o.status = 'ativa'
        AND c.ativo = 1

    ORDER BY
        c.nome ASC,
        o.nome ASC
");

$obras = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| PROCESSAR FORMULÁRIO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $produtoId = $_POST['produto_id'] ?? '';
    $quantidade = $_POST['quantidade'] ?? '';
    $data = $_POST['data'] ?? '';

    $tipoEstoque = $_POST['tipo_estoque'] ?? 'geral';

    $clienteId = $_POST['cliente_id'] ?? '';
    $obraId = $_POST['obra_id'] ?? '';

    $fornecedor = trim($_POST['fornecedor'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    $observacao = trim($_POST['observacao'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | VALIDAÇÕES BÁSICAS
    |--------------------------------------------------------------------------
    */

    if (
        $produtoId === '' ||
        $quantidade === '' ||
        $data === ''
    ) {

        $erro = 'Preencha os campos obrigatórios.';

    } elseif ((float) $quantidade <= 0) {

        $erro = 'A quantidade deve ser maior que zero.';

    } elseif (
        $tipoEstoque !== 'geral' &&
        $tipoEstoque !== 'obra'
    ) {

        $erro = 'Selecione um destino válido para o material.';

    } elseif (
        $tipoEstoque === 'obra' &&
        ($clienteId === '' || $obraId === '')
    ) {

        $erro = 'Selecione o cliente e a obra.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | VALIDAR OBRA
            |--------------------------------------------------------------------------
            |
            | Não vamos confiar apenas no JavaScript.
            | O PHP confirma que a obra realmente pertence ao cliente escolhido.
            |
            */

            if ($tipoEstoque === 'obra') {

                $stmt = $pdo->prepare("
                    SELECT
                        id

                    FROM obras

                    WHERE
                        id = ?
                        AND cliente_id = ?
                        AND status = 'ativa'

                    LIMIT 1
                ");

                $stmt->execute([
                    $obraId,
                    $clienteId
                ]);

                $obraValida = $stmt->fetchColumn();

                if (!$obraValida) {

                    throw new Exception(
                        'A obra selecionada não pertence ao cliente informado.'
                    );
                }

            } else {

                /*
                 * Estoque geral nunca deve ter obra vinculada.
                 */

                $obraId = null;
            }


            /*
            |--------------------------------------------------------------------------
            | REGISTRAR ENTRADA
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO movimentacoes (
                    produto_id,
                    tipo_estoque,
                    obra_id,
                    tipo,
                    quantidade,
                    fornecedor,
                    documento,
                    observacao,
                    data_movimentacao
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    'entrada',
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $produtoId,
                $tipoEstoque,
                $obraId,
                $quantidade,
                $fornecedor ?: null,
                $documento ?: null,
                $observacao ?: null,
                $data
            ]);


            /*
            |--------------------------------------------------------------------------
            | SUCESSO
            |--------------------------------------------------------------------------
            */

            header('Location: index.php?entrada=sucesso');
            exit;

        } catch (Exception $e) {

            $erro = $e->getMessage();

        } catch (PDOException $e) {

            $erro = 'Erro ao registrar entrada.';

        }

    }
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>


<main class="content">


    <!-- =========================
         CABEÇALHO
    ========================== -->

    <div class="page-header">

        <div>

            <h1>Nova entrada</h1>

            <p>
                Registre a entrada de material no almoxarifado.
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


    <!-- =========================
         ERRO
    ========================== -->

    <?php if ($erro): ?>

        <div class="alert-error">

            <i class="bi bi-exclamation-circle"></i>

            <?= htmlspecialchars($erro) ?>

        </div>

    <?php endif; ?>


    <!-- =========================
         FORMULÁRIO
    ========================== -->

    <div class="form-card">

        <form
            action=""
            method="POST"
            id="entradaForm"
        >

            <div class="form-grid">


                <!-- PRODUTO -->

                <div class="form-group full">

                    <label for="produto_id">
                        Produto *
                    </label>

                    <select
                        id="produto_id"
                        name="produto_id"
                        required
                    >

                        <option value="">
                            Selecione o produto
                        </option>


                        <?php foreach ($produtos as $produto): ?>

                            <option
                                value="<?= $produto['id'] ?>"

                                <?=
                                    (
                                        ($_POST['produto_id'] ?? $produtoSelecionado)
                                        == $produto['id']
                                    )
                                        ? 'selected'
                                        : ''
                                ?>
                            >

                                <?= htmlspecialchars($produto['codigo']) ?>

                                -

                                <?= htmlspecialchars($produto['nome']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- QUANTIDADE -->

                <div class="form-group">

                    <label for="quantidade">
                        Quantidade *
                    </label>

                    <input
                        type="number"
                        id="quantidade"
                        name="quantidade"
                        min="0.01"
                        step="0.01"
                        placeholder="Ex: 50"
                        value="<?= htmlspecialchars($_POST['quantidade'] ?? '') ?>"
                        required
                    >

                </div>


                <!-- DATA -->

                <div class="form-group">

                    <label for="data">
                        Data *
                    </label>

                    <input
                        type="date"
                        id="data"
                        name="data"
                        value="<?= htmlspecialchars(
                            $_POST['data'] ?? date('Y-m-d')
                        ) ?>"
                        required
                    >

                </div>


                <!-- =========================
                     DESTINO DO MATERIAL
                ========================== -->

                <div class="form-group full">

                    <label>
                        Destino do material *
                    </label>


                    <div class="stock-destination-options">


                        <!-- ESTOQUE GERAL -->

                        <label class="stock-destination-card">

                            <input
                                type="radio"
                                name="tipo_estoque"
                                value="geral"

                                <?=
                                    $tipoEstoqueSelecionado === 'geral'
                                        ? 'checked'
                                        : ''
                                ?>
                            >

                            <div class="stock-destination-content">

                                <div class="stock-destination-icon general">

                                    <i class="bi bi-box-seam"></i>

                                </div>


                                <div>

                                    <strong>
                                        Estoque geral
                                    </strong>

                                    <span>
                                        Material disponível para uso geral.
                                    </span>

                                </div>

                            </div>

                        </label>


                        <!-- MATERIAL DE OBRA -->

                        <label class="stock-destination-card">

                            <input
                                type="radio"
                                name="tipo_estoque"
                                value="obra"

                                <?=
                                    $tipoEstoqueSelecionado === 'obra'
                                        ? 'checked'
                                        : ''
                                ?>
                            >

                            <div class="stock-destination-content">

                                <div class="stock-destination-icon work">

                                    <i class="bi bi-buildings"></i>

                                </div>


                                <div>

                                    <strong>
                                        Obra / Cliente
                                    </strong>

                                    <span>
                                        Material reservado para uma obra específica.
                                    </span>

                                </div>

                            </div>

                        </label>


                    </div>

                </div>


                <!-- =========================
                     CLIENTE / OBRA
                ========================== -->

                <div
                    class="work-destination-fields full"
                    id="workDestinationFields"
                >

                    <div class="work-destination-header">

                        <i class="bi bi-building-check"></i>

                        <div>

                            <strong>
                                Identificação da obra
                            </strong>

                            <span>
                                Escolha o cliente e depois a obra.
                            </span>

                        </div>

                    </div>


                    <div class="work-destination-grid">


                        <!-- CLIENTE -->

                        <div class="form-group">

                            <label for="cliente_id">
                                Cliente *
                            </label>

                            <select
                                id="cliente_id"
                                name="cliente_id"
                            >

                                <option value="">
                                    Selecione o cliente
                                </option>


                                <?php foreach ($clientes as $cliente): ?>

                                    <option
                                        value="<?= $cliente['id'] ?>"

                                        <?=
                                            $clienteSelecionado == $cliente['id']
                                                ? 'selected'
                                                : ''
                                        ?>
                                    >

                                        <?= htmlspecialchars(
                                            $cliente['nome']
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- OBRA -->

                        <div class="form-group">

                            <label for="obra_id">
                                Obra *
                            </label>

                            <select
                                id="obra_id"
                                name="obra_id"
                            >

                                <option value="">
                                    Selecione primeiro o cliente
                                </option>


                                <?php foreach ($obras as $obra): ?>

                                    <option
                                        value="<?= $obra['id'] ?>"

                                        data-cliente="<?= $obra['cliente_id'] ?>"

                                        <?=
                                            $obraSelecionada == $obra['id']
                                                ? 'selected'
                                                : ''
                                        ?>
                                    >

                                        <?php if ($obra['codigo']): ?>

                                            <?= htmlspecialchars(
                                                $obra['codigo']
                                            ) ?>

                                            -

                                        <?php endif; ?>


                                        <?= htmlspecialchars(
                                            $obra['nome']
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                    </div>

                </div>


                <!-- FORNECEDOR -->

                <div class="form-group">

                    <label for="fornecedor">
                        Fornecedor
                    </label>

                    <input
                        type="text"
                        id="fornecedor"
                        name="fornecedor"
                        placeholder="Ex: Fornecedor ABC"
                        value="<?= htmlspecialchars(
                            $_POST['fornecedor'] ?? ''
                        ) ?>"
                    >

                </div>


                <!-- DOCUMENTO -->

                <div class="form-group">

                    <label for="documento">
                        Nota / Documento
                    </label>

                    <input
                        type="text"
                        id="documento"
                        name="documento"
                        placeholder="Ex: NF 12584"
                        value="<?= htmlspecialchars(
                            $_POST['documento'] ?? ''
                        ) ?>"
                    >

                </div>


                <!-- OBSERVAÇÃO -->

                <div class="form-group full">

                    <label for="observacao">
                        Observação
                    </label>

                    <textarea
                        id="observacao"
                        name="observacao"
                        rows="4"
                        placeholder="Informações adicionais sobre a entrada..."
                    ><?= htmlspecialchars(
                        $_POST['observacao'] ?? ''
                    ) ?></textarea>

                </div>


            </div>


            <!-- =========================
                 AÇÕES
            ========================== -->

            <div class="form-actions">

                <a
                    href="index.php"
                    class="btn-secondary"
                >
                    Cancelar
                </a>


                <button
                    type="submit"
                    class="btn-entry"
                >

                    <i class="bi bi-arrow-down-circle"></i>

                    Registrar entrada

                </button>

            </div>

        </form>

    </div>

</main>


<!-- =========================
     CLIENTE → OBRA
========================== -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const tipoEstoque =
        document.querySelectorAll(
            'input[name="tipo_estoque"]'
        );

    const workFields =
        document.getElementById(
            'workDestinationFields'
        );

    const clienteSelect =
        document.getElementById(
            'cliente_id'
        );

    const obraSelect =
        document.getElementById(
            'obra_id'
        );


    /*
    |--------------------------------------------------------------------------
    | GUARDAR TODAS AS OBRAS
    |--------------------------------------------------------------------------
    */

    const obras = Array.from(
        obraSelect.querySelectorAll(
            'option[data-cliente]'
        )
    ).map(function (option) {

        return {
            value: option.value,
            cliente: option.dataset.cliente,
            texto: option.textContent.trim()
        };

    });


    /*
    |--------------------------------------------------------------------------
    | MOSTRAR / ESCONDER CAMPOS DE OBRA
    |--------------------------------------------------------------------------
    */

    function atualizarTipoEstoque() {

        const selecionado =
            document.querySelector(
                'input[name="tipo_estoque"]:checked'
            );

        if (!selecionado) {
            return;
        }


        if (selecionado.value === 'obra') {

            workFields.classList.add('active');

            clienteSelect.required = true;
            obraSelect.required = true;

        } else {

            workFields.classList.remove('active');

            clienteSelect.required = false;
            obraSelect.required = false;

            clienteSelect.value = '';

            atualizarObras('');

        }

    }


    /*
    |--------------------------------------------------------------------------
    | FILTRAR OBRAS PELO CLIENTE
    |--------------------------------------------------------------------------
    */

    function atualizarObras(
        clienteId,
        obraSelecionada = ''
    ) {

        obraSelect.innerHTML = '';


        const placeholder =
            document.createElement('option');

        placeholder.value = '';


        if (clienteId === '') {

            placeholder.textContent =
                'Selecione primeiro o cliente';

        } else {

            placeholder.textContent =
                'Selecione a obra';

        }


        obraSelect.appendChild(placeholder);


        if (clienteId === '') {

            obraSelect.disabled = true;

            return;
        }


        obraSelect.disabled = false;


        const obrasCliente =
            obras.filter(function (obra) {

                return obra.cliente === clienteId;

            });


        obrasCliente.forEach(function (obra) {

            const option =
                document.createElement('option');

            option.value = obra.value;

            option.textContent = obra.texto;


            if (
                String(obra.value) ===
                String(obraSelecionada)
            ) {

                option.selected = true;

            }


            obraSelect.appendChild(option);

        });


        /*
         * Caso o cliente ainda não tenha obra ativa.
         */

        if (obrasCliente.length === 0) {

            placeholder.textContent =
                'Nenhuma obra ativa para este cliente';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | EVENTO CLIENTE
    |--------------------------------------------------------------------------
    */

    clienteSelect.addEventListener(
        'change',
        function () {

            atualizarObras(
                this.value
            );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | EVENTO DESTINO
    |--------------------------------------------------------------------------
    */

    tipoEstoque.forEach(function (radio) {

        radio.addEventListener(
            'change',
            atualizarTipoEstoque
        );

    });


    /*
    |--------------------------------------------------------------------------
    | ESTADO INICIAL
    |--------------------------------------------------------------------------
    */

    const clienteInicial =
        clienteSelect.value;

    const obraInicial =
        <?= json_encode(
            (string) $obraSelecionada
        ) ?>;


    atualizarObras(
        clienteInicial,
        obraInicial
    );

    atualizarTipoEstoque();

});

</script>


<?php include '../../Includes/footer.php'; ?>