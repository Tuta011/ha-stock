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
| BUSCAR PRODUTOS ATIVOS + SALDO GERAL
|--------------------------------------------------------------------------
|
| IMPORTANTE:
| Aqui consideramos somente tipo_estoque = 'geral'.
| Material reservado em obra não pode ser retirado por esta tela.
|
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
| PROCESSAR SAÍDA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $produtoId =
        $_POST['produto_id'] ?? '';

    $quantidade =
        $_POST['quantidade'] ?? '';

    $data =
        $_POST['data'] ?? '';

    $responsavel =
        trim($_POST['responsavel'] ?? '');

    $destino =
        $_POST['destino'] ?? '';

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
        $data === '' ||
        $responsavel === '' ||
        $destino === ''
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

            $quantidadeSaida =
                (float) $quantidade;


            /*
            |--------------------------------------------------------------------------
            | BLOQUEAR DECIMAL PARA UNIDADE
            |--------------------------------------------------------------------------
            */

            if (
                strtolower(trim($unidadeProduto)) === 'un' &&
                floor($quantidadeSaida) != $quantidadeSaida
            ) {

                throw new Exception(
                    'Produtos em unidade devem usar quantidades inteiras.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDAR DATA / HORÁRIO
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
            | INICIAR TRANSAÇÃO
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | BUSCAR SALDO DISPONÍVEL DO ESTOQUE GERAL
            |--------------------------------------------------------------------------
            |
            | Material de obra não entra neste cálculo.
            |
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

            $saldoAtual =
                (float) $stmt->fetchColumn();


            /*
            |--------------------------------------------------------------------------
            | BLOQUEAR SALDO NEGATIVO
            |--------------------------------------------------------------------------
            */

            if ($quantidadeSaida > $saldoAtual) {

                throw new Exception(
                    'Estoque geral insuficiente. Saldo disponível: ' .
                    formatarQuantidade(
                        $saldoAtual,
                        $unidadeProduto
                    ) .
                    ' ' .
                    $unidadeProduto
                );
            }


            /*
            |--------------------------------------------------------------------------
            | REGISTRAR SAÍDA
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
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $produtoId,
                $quantidadeSaida,
                $responsavel,
                $destino,
                $observacao ?: null,
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
                'Location: index.php?saida=sucesso'
            );

            exit;

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erro =
                'Erro ao registrar saída.';

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


    <!-- =========================
         CABEÇALHO
    ========================== -->

    <div class="page-header">

        <div>

            <h1>
                Nova saída
            </h1>

            <p>
                Registre a retirada de material do estoque geral.
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
            method="POST"
            id="saidaForm"
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
                        placeholder="Ex: 10"
                        value="<?= htmlspecialchars(
                            $_POST['quantidade']
                            ?? ''
                        ) ?>"
                        required
                    >


                    <small
                        id="quantityHelp"
                        class="form-help"
                    ></small>

                </div>


                <!-- DATA / HORÁRIO -->

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


                <!-- RESPONSÁVEL -->

                <div class="form-group">

                    <label for="responsavel">
                        Retirado por *
                    </label>

                    <input
                        type="text"
                        id="responsavel"
                        name="responsavel"
                        placeholder="Nome do funcionário"
                        value="<?= htmlspecialchars(
                            $_POST['responsavel']
                            ?? ''
                        ) ?>"
                        required
                    >

                </div>


                <!-- DESTINO -->

                <div class="form-group">

                    <label for="destino">
                        Destino *
                    </label>

                    <select
                        id="destino"
                        name="destino"
                        required
                    >

                        <option value="">
                            Selecione o destino
                        </option>


                        <option
                            value="producao"
                            <?= ($_POST['destino'] ?? '') === 'producao'
                                ? 'selected'
                                : '' ?>
                        >
                            Produção
                        </option>


                        <option
                            value="instalacao"
                            <?= ($_POST['destino'] ?? '') === 'instalacao'
                                ? 'selected'
                                : '' ?>
                        >
                            Instalação
                        </option>


                        <option
                            value="manutencao"
                            <?= ($_POST['destino'] ?? '') === 'manutencao'
                                ? 'selected'
                                : '' ?>
                        >
                            Manutenção
                        </option>


                        <option
                            value="outro"
                            <?= ($_POST['destino'] ?? '') === 'outro'
                                ? 'selected'
                                : '' ?>
                        >
                            Outro
                        </option>

                    </select>

                </div>


                <!-- AVISO -->

                <div class="form-group full">

                    <div class="stock-exit-notice">

                        <i class="bi bi-info-circle"></i>

                        <div>

                            <strong>
                                Saída do estoque geral
                            </strong>

                            <span>
                                Materiais já separados para obras não estão disponíveis nesta operação.
                                Para reservar material para uma obra, utilize "Separar para obra".
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
                        placeholder="Informações adicionais sobre a saída..."
                    ><?= htmlspecialchars(
                        $_POST['observacao']
                        ?? ''
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
                    class="btn-exit"
                >

                    <i class="bi bi-arrow-up-circle"></i>

                    Registrar saída

                </button>

            </div>

        </form>

    </div>


</main>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

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
        | AJUSTAR QUANTIDADE CONFORME UNIDADE
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


            const unidade =
                (
                    option.dataset.unidade
                    || ''
                )
                .trim()
                .toLowerCase();


            const saldo =
                parseFloat(
                    option.dataset.saldo
                    || '0'
                );


            /*
            |--------------------------------------------------------------------------
            | PRODUTO EM UNIDADE
            |--------------------------------------------------------------------------
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
            |--------------------------------------------------------------------------
            | PRODUTO DECIMAL
            |--------------------------------------------------------------------------
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
                    option.dataset.unidade;


            /*
            |--------------------------------------------------------------------------
            | NENHUM PRODUTO
            |--------------------------------------------------------------------------
            */

            } else {

                quantidadeInput.removeAttribute(
                    'max'
                );

                quantidadeInput.step =
                    '0.01';

                quantidadeInput.min =
                    '0.01';

                quantityHelp.textContent =
                    '';
            }

        }


        produtoSelect.addEventListener(
            'change',
            atualizarQuantidade
        );


        /*
        |--------------------------------------------------------------------------
        | ESTADO INICIAL
        |--------------------------------------------------------------------------
        */

        atualizarQuantidade();

    }
);

</script>


<?php include '../../Includes/footer.php'; ?>