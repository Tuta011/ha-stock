<?php

require_once '../../Config/database.php';


/*
|--------------------------------------------------------------------------
| ID DO MATERIAL
|--------------------------------------------------------------------------
*/

$materialId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$materialId) {
    die('Material inválido.');
}


/*
|--------------------------------------------------------------------------
| BUSCAR MATERIAL + OBRA
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        mo.id,
        mo.obra_id,
        mo.codigo,
        mo.nome,
        mo.unidade,
        mo.quantidade,
        mo.quantidade_por_embalagem,
        mo.quantidade_total,
        mo.observacao,
        mo.data_entrada,

        o.nome AS obra_nome,
        o.codigo AS obra_codigo,
        o.status AS obra_status,

        c.nome AS cliente_nome

    FROM materiais_obra mo

    INNER JOIN obras o
        ON o.id = mo.obra_id

    INNER JOIN clientes c
        ON c.id = o.cliente_id

    WHERE mo.id = ?

    LIMIT 1
");

$stmt->execute([
    $materialId
]);

$material = $stmt->fetch();


if (!$material) {
    die('Material não encontrado.');
}


/*
|--------------------------------------------------------------------------
| BLOQUEAR EDIÇÃO EM OBRA INATIVA
|--------------------------------------------------------------------------
*/

if ($material['obra_status'] !== 'ativa') {
    die('Não é possível editar materiais de uma obra inativa.');
}


/*
|--------------------------------------------------------------------------
| TOTAL DE SAÍDAS JÁ REGISTRADAS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        COALESCE(
            SUM(
                CASE
                    WHEN tipo = 'saida'
                        THEN quantidade
                    ELSE 0
                END
            ),
            0
        ) AS total_saidas

    FROM movimentacoes_materiais_obra

    WHERE material_obra_id = ?
");

$stmt->execute([
    $materialId
]);

$totalSaidas =
    (float) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| VARIÁVEIS
|--------------------------------------------------------------------------
*/

$erro = '';


/*
|--------------------------------------------------------------------------
| PROCESSAR FORMULÁRIO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $codigo =
        strtoupper(
            trim($_POST['codigo'] ?? '')
        );

    $nome =
        trim($_POST['nome'] ?? '');

    $unidade =
        strtolower(
            trim($_POST['unidade'] ?? '')
        );

    $quantidade =
        $_POST['quantidade'] ?? '';

    $quantidadePorEmbalagem =
        $_POST['quantidade_por_embalagem'] ?? '';

    $data =
        $_POST['data'] ?? '';

    $observacao =
        trim($_POST['observacao'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | UNIDADES PERMITIDAS
    |--------------------------------------------------------------------------
    */

    $unidadesPermitidas = [
        'un',
        'pacote',
        'caixa',
        'metro',
        'kg',
        'litro'
    ];


    /*
    |--------------------------------------------------------------------------
    | VALIDAÇÕES
    |--------------------------------------------------------------------------
    */

    if (
        $nome === '' ||
        $unidade === '' ||
        $quantidade === '' ||
        $data === ''
    ) {

        $erro =
            'Preencha todos os campos obrigatórios.';

    } elseif (
        !in_array(
            $unidade,
            $unidadesPermitidas,
            true
        )
    ) {

        $erro =
            'Unidade inválida.';

    } elseif (
        !is_numeric($quantidade) ||
        (float) $quantidade <= 0
    ) {

        $erro =
            'Informe uma quantidade válida.';

    } else {

        try {

            $quantidadeFloat =
                (float) $quantidade;


            /*
            |--------------------------------------------------------------------------
            | UNIDADES INTEIRAS
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $unidade,
                    [
                        'un',
                        'pacote',
                        'caixa'
                    ],
                    true
                ) &&
                floor($quantidadeFloat) !=
                $quantidadeFloat
            ) {

                throw new Exception(
                    'Essa unidade aceita somente quantidades inteiras.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CALCULAR QUANTIDADE TOTAL
            |--------------------------------------------------------------------------
            */

            if (
                $unidade === 'pacote' ||
                $unidade === 'caixa'
            ) {

                if (
                    $quantidadePorEmbalagem === '' ||
                    !is_numeric(
                        $quantidadePorEmbalagem
                    ) ||
                    (int) $quantidadePorEmbalagem <= 0
                ) {

                    throw new Exception(
                        'Informe quantos itens existem em cada embalagem.'
                    );
                }


                $quantidadePorEmbalagem =
                    (int) $quantidadePorEmbalagem;


                $quantidadeTotalNova =
                    $quantidadeFloat *
                    $quantidadePorEmbalagem;

            } else {

                $quantidadePorEmbalagem =
                    null;

                $quantidadeTotalNova =
                    $quantidadeFloat;
            }


            /*
            |--------------------------------------------------------------------------
            | NÃO DEIXAR SALDO NEGATIVO
            |--------------------------------------------------------------------------
            */

            if (
                $quantidadeTotalNova <
                $totalSaidas
            ) {

                throw new Exception(
                    'A nova quantidade total não pode ser menor do que o total já retirado deste material.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDAR DATA
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
            | TRANSAÇÃO
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | ATUALIZAR MATERIAL
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE materiais_obra

                SET
                    codigo = ?,
                    nome = ?,
                    unidade = ?,
                    quantidade = ?,
                    quantidade_por_embalagem = ?,
                    quantidade_total = ?,
                    observacao = ?,
                    data_entrada = ?

                WHERE id = ?
            ");

            $stmt->execute([
                $codigo ?: null,
                $nome,
                $unidade,
                $quantidadeFloat,
                $quantidadePorEmbalagem,
                $quantidadeTotalNova,
                $observacao ?: null,
                $dataBanco,
                $materialId
            ]);


            /*
            |--------------------------------------------------------------------------
            | ATUALIZAR MOVIMENTAÇÃO INICIAL
            |--------------------------------------------------------------------------
            |
            | Como a entrada inicial representa a quantidade total original
            | desse material, ela precisa acompanhar a edição.
            |
            */

            $stmt = $pdo->prepare("
                SELECT id

                FROM movimentacoes_materiais_obra

                WHERE
                    material_obra_id = ?
                    AND tipo = 'entrada'

                ORDER BY id ASC

                LIMIT 1
            ");

            $stmt->execute([
                $materialId
            ]);

            $movimentacaoEntradaId =
                $stmt->fetchColumn();


            if ($movimentacaoEntradaId) {

                $stmt = $pdo->prepare("
                    UPDATE movimentacoes_materiais_obra

                    SET
                        quantidade = ?,
                        data_movimentacao = ?

                    WHERE id = ?
                ");

                $stmt->execute([
                    $quantidadeTotalNova,
                    $dataBanco,
                    $movimentacaoEntradaId
                ]);

            } else {

                /*
                 * Segurança para materiais antigos sem entrada inicial.
                 */

                $stmt = $pdo->prepare("
                    INSERT INTO movimentacoes_materiais_obra (
                        material_obra_id,
                        tipo,
                        quantidade,
                        observacao,
                        data_movimentacao
                    )
                    VALUES (
                        ?,
                        'entrada',
                        ?,
                        'Entrada inicial do material na obra',
                        ?
                    )
                ");

                $stmt->execute([
                    $materialId,
                    $quantidadeTotalNova,
                    $dataBanco
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | CONFIRMAR
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | SUCESSO
            |--------------------------------------------------------------------------
            */

            header(
                'Location: detalhes.php?id=' .
                urlencode($material['obra_id']) .
                '&material=editado'
            );

            exit;

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erro =
                'Erro ao atualizar material.';

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
| VALORES DO FORMULÁRIO
|--------------------------------------------------------------------------
*/

$valorCodigo =
    $_POST['codigo']
    ?? $material['codigo']
    ?? '';

$valorNome =
    $_POST['nome']
    ?? $material['nome'];

$valorUnidade =
    $_POST['unidade']
    ?? $material['unidade'];

$valorQuantidade =
    $_POST['quantidade']
    ?? $material['quantidade'];

$valorQuantidadePorEmbalagem =
    $_POST['quantidade_por_embalagem']
    ?? $material['quantidade_por_embalagem']
    ?? '';

$valorData =
    $_POST['data']
    ?? date(
        'Y-m-d\TH:i',
        strtotime(
            $material['data_entrada']
        )
    );

$valorObservacao =
    $_POST['observacao']
    ?? $material['observacao']
    ?? '';


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
                Editar material
            </h1>

            <p>

                <?= htmlspecialchars(
                    $material['cliente_nome']
                ) ?>

                —

                <?= htmlspecialchars(
                    $material['obra_nome']
                ) ?>

            </p>

        </div>


        <a
            href="detalhes.php?id=<?= $material['obra_id'] ?>"
            class="btn-secondary"
        >

            <i class="bi bi-arrow-left"></i>

            Voltar para obra

        </a>

    </div>


    <!-- =========================
         ERRO
    ========================== -->

    <?php if ($erro): ?>

        <div class="alert-error">

            <i class="bi bi-exclamation-circle"></i>

            <?= htmlspecialchars(
                $erro
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =========================
         AVISO DE SAÍDAS
    ========================== -->

    <?php if ($totalSaidas > 0): ?>

        <div class="stock-exit-notice">

            <i class="bi bi-info-circle"></i>

            <div>

                <strong>
                    Este material já possui saídas
                </strong>

                <span>

                    Total já retirado:

                    <?= number_format(
                        $totalSaidas,
                        2,
                        ',',
                        '.'
                    ) ?>

                    un.

                    A quantidade total não poderá ficar abaixo desse valor.

                </span>

            </div>

        </div>

    <?php endif; ?>


    <!-- =========================
         FORMULÁRIO
    ========================== -->

    <div class="form-card">

        <form
            method="POST"
            id="editarMaterialForm"
        >

            <div class="form-grid">


                <!-- CÓDIGO -->

                <div class="form-group">

                    <label for="codigo">
                        Código
                    </label>

                    <input
                        type="text"
                        id="codigo"
                        name="codigo"
                        placeholder="Ex: FIT-CRE-BR-LG"
                        value="<?= htmlspecialchars(
                            $valorCodigo
                        ) ?>"
                    >

                </div>


                <!-- PRODUTO -->

                <div class="form-group">

                    <label for="nome">
                        Produto *
                    </label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        value="<?= htmlspecialchars(
                            $valorNome
                        ) ?>"
                        required
                    >

                </div>


                <!-- UNIDADE -->

                <div class="form-group">

                    <label for="unidade">
                        Unidade *
                    </label>

                    <select
                        id="unidade"
                        name="unidade"
                        required
                    >

                        <option value="">
                            Selecione
                        </option>


                        <option
                            value="un"
                            <?= $valorUnidade === 'un'
                                ? 'selected'
                                : '' ?>
                        >
                            Unidade
                        </option>


                        <option
                            value="pacote"
                            <?= $valorUnidade === 'pacote'
                                ? 'selected'
                                : '' ?>
                        >
                            Pacote
                        </option>


                        <option
                            value="caixa"
                            <?= $valorUnidade === 'caixa'
                                ? 'selected'
                                : '' ?>
                        >
                            Caixa
                        </option>


                        <option
                            value="metro"
                            <?= $valorUnidade === 'metro'
                                ? 'selected'
                                : '' ?>
                        >
                            Metro
                        </option>


                        <option
                            value="kg"
                            <?= $valorUnidade === 'kg'
                                ? 'selected'
                                : '' ?>
                        >
                            Kg
                        </option>


                        <option
                            value="litro"
                            <?= $valorUnidade === 'litro'
                                ? 'selected'
                                : '' ?>
                        >
                            Litro
                        </option>

                    </select>

                </div>


                <!-- QUANTIDADE -->

                <div class="form-group">

                    <label
                        for="quantidade"
                        id="quantidadeLabel"
                    >
                        Quantidade *
                    </label>

                    <input
                        type="number"
                        id="quantidade"
                        name="quantidade"
                        min="0.01"
                        step="0.01"
                        value="<?= htmlspecialchars(
                            $valorQuantidade
                        ) ?>"
                        required
                    >

                </div>


                <!-- EMBALAGEM -->

                <div
                    class="form-group full"
                    id="embalagemFields"
                    style="display:none;"
                >

                    <div class="work-destination-header">

                        <i class="bi bi-box-seam"></i>

                        <div>

                            <strong>
                                Conteúdo da embalagem
                            </strong>

                            <span id="embalagemTexto">
                                Informe os itens por embalagem.
                            </span>

                        </div>

                    </div>


                    <div
                        class="work-destination-grid"
                        style="margin-top:16px;"
                    >


                        <div class="form-group">

                            <label for="quantidade_por_embalagem">
                                Itens por embalagem *
                            </label>

                            <input
                                type="number"
                                id="quantidade_por_embalagem"
                                name="quantidade_por_embalagem"
                                min="1"
                                step="1"
                                value="<?= htmlspecialchars(
                                    $valorQuantidadePorEmbalagem
                                ) ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Total de itens
                            </label>

                            <div
                                id="totalItens"
                                style="
                                    min-height:46px;
                                    display:flex;
                                    align-items:center;
                                    font-size:22px;
                                    font-weight:700;
                                "
                            >
                                0 un
                            </div>

                        </div>


                    </div>

                </div>


                <!-- DATA -->

                <div class="form-group full">

                    <label for="data">
                        Data e horário da entrada *
                    </label>

                    <input
                        type="datetime-local"
                        id="data"
                        name="data"
                        value="<?= htmlspecialchars(
                            $valorData
                        ) ?>"
                        required
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
                        placeholder="Opcional"
                    ><?= htmlspecialchars(
                        $valorObservacao
                    ) ?></textarea>

                </div>


            </div>


            <!-- =========================
                 AÇÕES
            ========================== -->

            <div class="form-actions">

                <a
                    href="detalhes.php?id=<?= $material['obra_id'] ?>"
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


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const unidade =
            document.getElementById(
                'unidade'
            );

        const quantidade =
            document.getElementById(
                'quantidade'
            );

        const quantidadeLabel =
            document.getElementById(
                'quantidadeLabel'
            );

        const embalagemFields =
            document.getElementById(
                'embalagemFields'
            );

        const quantidadePorEmbalagem =
            document.getElementById(
                'quantidade_por_embalagem'
            );

        const totalItens =
            document.getElementById(
                'totalItens'
            );

        const embalagemTexto =
            document.getElementById(
                'embalagemTexto'
            );


        /*
        |--------------------------------------------------------------------------
        | ATUALIZAR UNIDADE
        |--------------------------------------------------------------------------
        */

        function atualizarUnidade() {

            const valor =
                unidade.value;

            const embalagem =
                valor === 'pacote' ||
                valor === 'caixa';


            if (embalagem) {

                embalagemFields.style.display =
                    'block';

                quantidade.step = '1';
                quantidade.min = '1';

                quantidadePorEmbalagem.required =
                    true;


                if (valor === 'pacote') {

                    quantidadeLabel.textContent =
                        'Quantidade de pacotes *';

                    embalagemTexto.textContent =
                        'Informe quantos itens existem em cada pacote.';

                } else {

                    quantidadeLabel.textContent =
                        'Quantidade de caixas *';

                    embalagemTexto.textContent =
                        'Informe quantos itens existem em cada caixa.';
                }


            } else if (valor === 'un') {

                embalagemFields.style.display =
                    'none';

                quantidade.step = '1';
                quantidade.min = '1';

                quantidadePorEmbalagem.required =
                    false;

                quantidadeLabel.textContent =
                    'Quantidade de unidades *';


            } else {

                embalagemFields.style.display =
                    'none';

                quantidade.step = '0.01';
                quantidade.min = '0.01';

                quantidadePorEmbalagem.required =
                    false;

                quantidadeLabel.textContent =
                    'Quantidade *';
            }


            atualizarTotal();
        }


        /*
        |--------------------------------------------------------------------------
        | TOTAL
        |--------------------------------------------------------------------------
        */

        function atualizarTotal() {

            const valorUnidade =
                unidade.value;


            if (
                valorUnidade !== 'pacote' &&
                valorUnidade !== 'caixa'
            ) {

                totalItens.textContent =
                    '0 un';

                return;
            }


            const qtd =
                parseInt(
                    quantidade.value || '0',
                    10
                );


            const porEmbalagem =
                parseInt(
                    quantidadePorEmbalagem.value
                    || '0',
                    10
                );


            const total =
                qtd * porEmbalagem;


            totalItens.textContent =
                total.toLocaleString(
                    'pt-BR'
                ) +
                ' un';
        }


        /*
        |--------------------------------------------------------------------------
        | EVENTOS
        |--------------------------------------------------------------------------
        */

        unidade.addEventListener(
            'change',
            atualizarUnidade
        );

        quantidade.addEventListener(
            'input',
            atualizarTotal
        );

        quantidadePorEmbalagem.addEventListener(
            'input',
            atualizarTotal
        );


        /*
        |--------------------------------------------------------------------------
        | INICIAL
        |--------------------------------------------------------------------------
        */

        atualizarUnidade();

    }
);

</script>


<?php include '../../Includes/footer.php'; ?>