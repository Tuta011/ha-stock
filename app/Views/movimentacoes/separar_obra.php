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

$clienteSelecionado = $_POST['cliente_id'] ?? '';
$obraSelecionada = $_POST['obra_id'] ?? '';


/*
|--------------------------------------------------------------------------
| FORMATAR QUANTIDADE
|--------------------------------------------------------------------------
*/

function formatarQuantidade($quantidade, $unidade)
{
    $quantidade = (float) $quantidade;

    if (strtolower(trim($unidade)) === 'un') {

        return number_format(
            $quantidade,
            0,
            ',',
            '.'
        );
    }

    return number_format(
        $quantidade,
        2,
        ',',
        '.'
    );
}


/*
|--------------------------------------------------------------------------
| PRODUTOS ATIVOS + SALDO GERAL
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        p.id,
        p.codigo,
        p.nome,
        p.unidade,

        COALESCE(
            SUM(
                CASE

                    WHEN m.tipo_estoque = 'geral'
                         AND m.tipo = 'entrada'
                        THEN m.quantidade

                    WHEN m.tipo_estoque = 'geral'
                         AND m.tipo = 'saida'
                        THEN -m.quantidade

                    ELSE 0

                END
            ),
            0
        ) AS saldo_geral

    FROM produtos p

    LEFT JOIN movimentacoes m
        ON m.produto_id = p.id

    WHERE p.ativo = 1

    GROUP BY
        p.id,
        p.codigo,
        p.nome,
        p.unidade

    ORDER BY
        p.nome ASC
");

$produtos = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| CLIENTES ATIVOS
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
| OBRAS ATIVAS
|--------------------------------------------------------------------------
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
| PROCESSAR
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $produtoId =
        $_POST['produto_id'] ?? '';

    $quantidade =
        $_POST['quantidade'] ?? '';

    $clienteId =
        $_POST['cliente_id'] ?? '';

    $obraId =
        $_POST['obra_id'] ?? '';

    $data =
        $_POST['data'] ?? '';

    $responsavel =
        trim($_POST['responsavel'] ?? '');

    $observacao =
        trim($_POST['observacao'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | VALIDAÇÕES BÁSICAS
    |--------------------------------------------------------------------------
    */

    if (
        $produtoId === '' ||
        $quantidade === '' ||
        $clienteId === '' ||
        $obraId === '' ||
        $data === ''
    ) {

        $erro =
            'Preencha todos os campos obrigatórios.';

    } elseif ((float) $quantidade <= 0) {

        $erro =
            'A quantidade deve ser maior que zero.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | VALIDAR PRODUTO
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    unidade

                FROM produtos

                WHERE
                    id = ?
                    AND ativo = 1

                LIMIT 1
            ");

            $stmt->execute([
                $produtoId
            ]);

            $produtoBanco =
                $stmt->fetch();


            if (!$produtoBanco) {

                throw new Exception(
                    'Produto inválido.'
                );
            }


            $unidadeProduto =
                $produtoBanco['unidade'];

            $quantidadeTransferencia =
                (float) $quantidade;


            /*
            |--------------------------------------------------------------------------
            | BLOQUEAR DECIMAL PARA PRODUTOS EM UNIDADE
            |--------------------------------------------------------------------------
            */

            if (
                strtolower(trim($unidadeProduto)) === 'un' &&
                floor($quantidadeTransferencia) != $quantidadeTransferencia
            ) {

                throw new Exception(
                    'Produtos em unidade devem usar quantidades inteiras.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDAR DATA E HORÁRIO
            |--------------------------------------------------------------------------
            */

            $dataObjeto =
                DateTime::createFromFormat(
                    'Y-m-d\TH:i',
                    $data
                );


            if (!$dataObjeto) {

                throw new Exception(
                    'Informe uma data e horário válidos.'
                );
            }


            $dataBanco =
                $dataObjeto->format(
                    'Y-m-d H:i:s'
                );


            /*
            |--------------------------------------------------------------------------
            | VALIDAR OBRA
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id

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


            if (!$stmt->fetchColumn()) {

                throw new Exception(
                    'A obra selecionada não pertence ao cliente informado.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | INICIAR TRANSAÇÃO
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | CALCULAR ESTOQUE GERAL DISPONÍVEL
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(
                        SUM(
                            CASE

                                WHEN tipo_estoque = 'geral'
                                     AND tipo = 'entrada'
                                    THEN quantidade

                                WHEN tipo_estoque = 'geral'
                                     AND tipo = 'saida'
                                    THEN -quantidade

                                ELSE 0

                            END
                        ),
                        0
                    ) AS saldo_geral

                FROM movimentacoes

                WHERE produto_id = ?
            ");

            $stmt->execute([
                $produtoId
            ]);

            $saldoGeral =
                (float) $stmt->fetchColumn();


            /*
            |--------------------------------------------------------------------------
            | VALIDAR SALDO
            |--------------------------------------------------------------------------
            */

            if (
                $quantidadeTransferencia >
                $saldoGeral
            ) {

                throw new Exception(
                    'Estoque geral insuficiente. Disponível: ' .
                    formatarQuantidade(
                        $saldoGeral,
                        $unidadeProduto
                    ) .
                    ' ' .
                    $unidadeProduto
                );
            }


            /*
            |--------------------------------------------------------------------------
            | 1 - SAÍDA DO ESTOQUE GERAL
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO movimentacoes (
                    produto_id,
                    tipo_estoque,
                    obra_id,
                    tipo,
                    quantidade,
                    responsavel,
                    destino,
                    observacao,
                    data_movimentacao
                )
                VALUES (
                    ?,
                    'geral',
                    NULL,
                    'saida',
                    ?,
                    ?,
                    'obra',
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $produtoId,
                $quantidadeTransferencia,
                $responsavel ?: null,
                $observacao
                    ?: 'Separação de material para obra',
                $dataBanco
            ]);


            /*
            |--------------------------------------------------------------------------
            | 2 - ENTRADA NA OBRA
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO movimentacoes (
                    produto_id,
                    tipo_estoque,
                    obra_id,
                    tipo,
                    quantidade,
                    responsavel,
                    destino,
                    observacao,
                    data_movimentacao
                )
                VALUES (
                    ?,
                    'obra',
                    ?,
                    'entrada',
                    ?,
                    ?,
                    'obra',
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $produtoId,
                $obraId,
                $quantidadeTransferencia,
                $responsavel ?: null,
                $observacao
                    ?: 'Material separado do estoque geral para obra',
                $dataBanco
            ]);


            /*
            |--------------------------------------------------------------------------
            | CONFIRMAR TRANSAÇÃO
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | SUCESSO
            |--------------------------------------------------------------------------
            */

            header(
                'Location: ../obras/detalhes.php?id=' .
                urlencode($obraId) .
                '&separado=sucesso'
            );

            exit;

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erro =
                'Erro ao separar material para a obra.';

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erro =
                $e->getMessage();
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


    <!-- CABEÇALHO -->

    <div class="page-header">

        <div>

            <h1>
                Separar para obra
            </h1>

            <p>
                Transfira material do estoque geral para uma obra específica.
            </p>

        </div>


        <a
            href="../produtos/index.php"
            class="btn-secondary"
        >

            <i class="bi bi-arrow-left"></i>

            Voltar

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

        <form
            method="POST"
            id="separarObraForm"
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

                        <option
                            value=""
                            data-unidade=""
                            data-saldo="0"
                        >
                            Selecione o produto
                        </option>


                        <?php foreach ($produtos as $produto): ?>

                            <?php

                            $produtoAtual =
                                $_POST['produto_id']
                                ?? $produtoSelecionado;

                            ?>

                            <option
                                value="<?= $produto['id'] ?>"

                                data-unidade="<?= htmlspecialchars(
                                    $produto['unidade']
                                ) ?>"

                                data-saldo="<?= htmlspecialchars(
                                    $produto['saldo_geral']
                                ) ?>"

                                <?= $produtoAtual == $produto['id']
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    $produto['codigo']
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    $produto['nome']
                                ) ?>

                                (Disponível:

                                <?= formatarQuantidade(
                                    $produto['saldo_geral'],
                                    $produto['unidade']
                                ) ?>

                                <?= htmlspecialchars(
                                    $produto['unidade']
                                ) ?>)

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
                        placeholder="Ex: 20"
                        value="<?= htmlspecialchars(
                            $_POST['quantidade'] ?? ''
                        ) ?>"
                        required
                    >

                    <small
                        id="quantityHelp"
                        class="form-help"
                    ></small>

                </div>


                <!-- DATA E HORÁRIO -->

                <div class="form-group">

                    <label for="data">
                        Data e horário *
                    </label>

                    <input
                        type="datetime-local"
                        id="data"
                        name="data"
                        value="<?= htmlspecialchars(
                            $_POST['data']
                            ?? date('Y-m-d\TH:i')
                        ) ?>"
                        required
                    >

                </div>


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

                            <option
                                value="<?= $cliente['id'] ?>"

                                <?= $clienteSelecionado == $cliente['id']
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


                <!-- OBRA -->

                <div class="form-group">

                    <label for="obra_id">
                        Obra *
                    </label>

                    <select
                        id="obra_id"
                        name="obra_id"
                        required
                    >

                        <option value="">
                            Selecione primeiro o cliente
                        </option>


                        <?php foreach ($obras as $obra): ?>

                            <option
                                value="<?= $obra['id'] ?>"

                                data-cliente="<?= $obra['cliente_id'] ?>"

                                <?= $obraSelecionada == $obra['id']
                                    ? 'selected'
                                    : '' ?>
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


                <!-- RESPONSÁVEL -->

                <div class="form-group full">

                    <label for="responsavel">
                        Responsável
                    </label>

                    <input
                        type="text"
                        id="responsavel"
                        name="responsavel"
                        placeholder="Nome do responsável"
                        value="<?= htmlspecialchars(
                            $_POST['responsavel'] ?? ''
                        ) ?>"
                    >

                </div>


                <!-- INFORMAÇÃO -->

                <div class="form-group full">

                    <div class="stock-exit-notice">

                        <i class="bi bi-arrow-left-right"></i>

                        <div>

                            <strong>
                                Transferência de estoque
                            </strong>

                            <span>
                                O material será retirado do estoque geral e reservado para a obra.
                                O total físico do produto não será alterado.
                            </span>

                        </div>

                    </div>

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
                        placeholder="Informações sobre a separação..."
                    ><?= htmlspecialchars(
                        $_POST['observacao'] ?? ''
                    ) ?></textarea>

                </div>


            </div>


            <!-- AÇÕES -->

            <div class="form-actions">

                <a
                    href="../produtos/index.php"
                    class="btn-secondary"
                >
                    Cancelar
                </a>


                <button
                    type="submit"
                    class="btn-primary"
                >

                    <i class="bi bi-buildings"></i>

                    Separar para obra

                </button>

            </div>

        </form>

    </div>


</main>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const clienteSelect =
            document.getElementById(
                'cliente_id'
            );

        const obraSelect =
            document.getElementById(
                'obra_id'
            );

        const produtoSelect =
            document.getElementById(
                'produto_id'
            );

        const quantidadeInput =
            document.getElementById(
                'quantidade'
            );

        const quantityHelp =
            document.getElementById(
                'quantityHelp'
            );


        /*
        |--------------------------------------------------------------------------
        | QUANTIDADE POR UNIDADE
        |--------------------------------------------------------------------------
        */

        function atualizarQuantidade() {

            const option =
                produtoSelect.options[
                    produtoSelect.selectedIndex
                ];


            if (!option) {
                return;
            }


            const unidadeOriginal =
                option.dataset.unidade || '';

            const unidade =
                unidadeOriginal
                .trim()
                .toLowerCase();

            const saldo =
                parseFloat(
                    option.dataset.saldo || '0'
                );


            /*
            | PRODUTO EM UNIDADE
            */

            if (unidade === 'un') {

                quantidadeInput.step = '1';
                quantidadeInput.min = '1';

                quantidadeInput.max =
                    Math.floor(saldo);


                quantityHelp.textContent =
                    'Somente números inteiros. Disponível: ' +
                    Math.floor(saldo) +
                    ' un';


            /*
            | PRODUTO DECIMAL
            */

            } else if (unidade !== '') {

                quantidadeInput.step = '0.01';
                quantidadeInput.min = '0.01';

                quantidadeInput.max =
                    saldo.toFixed(2);


                quantityHelp.textContent =
                    'Disponível: ' +
                    saldo.toLocaleString(
                        'pt-BR',
                        {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }
                    ) +
                    ' ' +
                    unidadeOriginal;


            /*
            | NENHUM PRODUTO
            */

            } else {

                quantidadeInput.removeAttribute(
                    'max'
                );

                quantidadeInput.step =
                    '0.01';

                quantidadeInput.min =
                    '0.01';

                quantityHelp.textContent = '';
            }

        }


        produtoSelect.addEventListener(
            'change',
            atualizarQuantidade
        );


        /*
        |--------------------------------------------------------------------------
        | GUARDAR OBRAS
        |--------------------------------------------------------------------------
        */

        const obras = Array.from(
            obraSelect.querySelectorAll(
                'option[data-cliente]'
            )
        ).map(function (option) {

            return {

                value:
                    option.value,

                cliente:
                    option.dataset.cliente,

                texto:
                    option.textContent.trim()

            };

        });


        /*
        |--------------------------------------------------------------------------
        | ATUALIZAR OBRAS
        |--------------------------------------------------------------------------
        */

        function atualizarObras(
            clienteId,
            obraSelecionada = ''
        ) {

            obraSelect.innerHTML = '';


            const placeholder =
                document.createElement(
                    'option'
                );

            placeholder.value = '';


            if (clienteId === '') {

                placeholder.textContent =
                    'Selecione primeiro o cliente';

            } else {

                placeholder.textContent =
                    'Selecione a obra';
            }


            obraSelect.appendChild(
                placeholder
            );


            if (clienteId === '') {

                obraSelect.disabled = true;

                return;
            }


            obraSelect.disabled = false;


            const obrasCliente =
                obras.filter(
                    function (obra) {

                        return (
                            obra.cliente ===
                            clienteId
                        );

                    }
                );


            obrasCliente.forEach(
                function (obra) {

                    const option =
                        document.createElement(
                            'option'
                        );

                    option.value =
                        obra.value;

                    option.textContent =
                        obra.texto;


                    if (
                        String(obra.value) ===
                        String(obraSelecionada)
                    ) {

                        option.selected = true;
                    }


                    obraSelect.appendChild(
                        option
                    );

                }
            );


            if (obrasCliente.length === 0) {

                placeholder.textContent =
                    'Nenhuma obra ativa para este cliente';
            }

        }


        /*
        |--------------------------------------------------------------------------
        | ALTERAR CLIENTE
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
        | ESTADO INICIAL
        |--------------------------------------------------------------------------
        */

        atualizarObras(
            clienteSelect.value,
            <?= json_encode(
                (string) $obraSelecionada
            ) ?>
        );


        atualizarQuantidade();

    }
);

</script>


<?php include '../../Includes/footer.php'; ?>