<?php

require_once '../../Config/database.php';


/*
|--------------------------------------------------------------------------
| OBRA VINDO PELA URL
|--------------------------------------------------------------------------
*/

$obraId = filter_input(
    INPUT_GET,
    'obra_id',
    FILTER_VALIDATE_INT
);

if (!$obraId) {
    die('Obra inválida.');
}


/*
|--------------------------------------------------------------------------
| BUSCAR OBRA
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.codigo,
        o.nome,
        o.status,
        c.nome AS cliente

    FROM obras o

    INNER JOIN clientes c
        ON c.id = o.cliente_id

    WHERE o.id = ?

    LIMIT 1
");

$stmt->execute([$obraId]);

$obra = $stmt->fetch();


if (!$obra) {
    die('Obra não encontrada.');
}


if ($obra['status'] !== 'ativa') {
    die('Não é possível adicionar material a uma obra inativa.');
}


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
            | UNIDADES QUE NÃO ACEITAM DECIMAL
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $unidade,
                    ['un', 'pacote', 'caixa'],
                    true
                ) &&
                floor($quantidadeFloat)
                != $quantidadeFloat
            ) {

                throw new Exception(
                    'Essa unidade aceita somente quantidades inteiras.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | PACOTE / CAIXA
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


                $quantidadeTotal =
                    $quantidadeFloat *
                    $quantidadePorEmbalagem;
            } else {

                /*
                 * Produto sem embalagem.
                 */

                $quantidadePorEmbalagem =
                    null;

                $quantidadeTotal =
                    $quantidadeFloat;
            }


            /*
            |--------------------------------------------------------------------------
            | DATA E HORÁRIO
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
| INSERIR MATERIAL NA OBRA
|--------------------------------------------------------------------------
*/

            $stmt = $pdo->prepare("
    INSERT INTO materiais_obra (
        obra_id,
        codigo,
        nome,
        unidade,
        quantidade,
        quantidade_por_embalagem,
        quantidade_total,
        observacao,
        data_entrada
    )
    VALUES (
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?
    )
");

            $stmt->execute([
                $obraId,
                $codigo ?: null,
                $nome,
                $unidade,
                $quantidadeFloat,
                $quantidadePorEmbalagem,
                $quantidadeTotal,
                $observacao ?: null,
                $dataBanco
            ]);


            /*
|--------------------------------------------------------------------------
| PEGAR ID DO MATERIAL CRIADO
|--------------------------------------------------------------------------
*/

            $materialObraId =
                $pdo->lastInsertId();


            /*
|--------------------------------------------------------------------------
| REGISTRAR ENTRADA INICIAL NO HISTÓRICO
|--------------------------------------------------------------------------
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
        ?,
        ?
    )
");

            $stmt->execute([
                $materialObraId,
                $quantidadeTotal,
                'Entrada inicial do material na obra',
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
                'Location: detalhes.php?id=' .
                    urlencode($obraId) .
                    '&material=adicionado'
            );

            exit;
        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erro =
                'Erro ao adicionar material à obra.';
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
                Adicionar material
            </h1>

            <p>
                <?= htmlspecialchars(
                    $obra['cliente']
                ) ?>

                —

                <?= htmlspecialchars(
                    $obra['nome']
                ) ?>
            </p>

        </div>


        <a
            href="detalhes.php?id=<?= $obraId ?>"
            class="btn-secondary">

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

        <form
            method="POST"
            id="materialObraForm">

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
                                    $_POST['codigo'] ?? ''
                                ) ?>">

                </div>


                <!-- NOME -->

                <div class="form-group">

                    <label for="nome">
                        Produto *
                    </label>

                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        placeholder="Ex: Fita Crepe Branca Larga"
                        value="<?= htmlspecialchars(
                                    $_POST['nome'] ?? ''
                                ) ?>"
                        required>

                </div>


                <!-- UNIDADE -->

                <div class="form-group">

                    <label for="unidade">
                        Unidade *
                    </label>

                    <select
                        id="unidade"
                        name="unidade"
                        required>

                        <option value="">
                            Selecione
                        </option>

                        <option
                            value="un"
                            <?= ($_POST['unidade'] ?? '') === 'un'
                                ? 'selected'
                                : '' ?>>
                            Unidade
                        </option>

                        <option
                            value="pacote"
                            <?= ($_POST['unidade'] ?? '') === 'pacote'
                                ? 'selected'
                                : '' ?>>
                            Pacote
                        </option>

                        <option
                            value="caixa"
                            <?= ($_POST['unidade'] ?? '') === 'caixa'
                                ? 'selected'
                                : '' ?>>
                            Caixa
                        </option>

                        <option
                            value="metro"
                            <?= ($_POST['unidade'] ?? '') === 'metro'
                                ? 'selected'
                                : '' ?>>
                            Metro
                        </option>

                        <option
                            value="kg"
                            <?= ($_POST['unidade'] ?? '') === 'kg'
                                ? 'selected'
                                : '' ?>>
                            Kg
                        </option>

                        <option
                            value="litro"
                            <?= ($_POST['unidade'] ?? '') === 'litro'
                                ? 'selected'
                                : '' ?>>
                            Litro
                        </option>

                    </select>

                </div>


                <!-- QUANTIDADE -->

                <div class="form-group">

                    <label
                        for="quantidade"
                        id="quantidadeLabel">
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
                                    $_POST['quantidade'] ?? ''
                                ) ?>"
                        required>

                </div>


                <!-- ITENS POR EMBALAGEM -->

                <div
                    class="form-group full"
                    id="embalagemFields"
                    style="display: none;">

                    <div class="work-destination-header">

                        <i class="bi bi-box-seam"></i>

                        <div>

                            <strong>
                                Conteúdo da embalagem
                            </strong>

                            <span id="embalagemTexto">
                                Informe quantos itens existem em cada embalagem.
                            </span>

                        </div>

                    </div>


                    <div
                        class="work-destination-grid"
                        style="margin-top: 16px;">

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
                                placeholder="Ex: 100"
                                value="<?= htmlspecialchars(
                                            $_POST['quantidade_por_embalagem']
                                                ?? ''
                                        ) ?>">

                        </div>


                        <!-- TOTAL -->

                        <div class="form-group">

                            <label>
                                Total de itens
                            </label>

                            <div
                                id="totalItens"
                                style="
                                    min-height: 46px;
                                    display: flex;
                                    align-items: center;
                                    font-size: 22px;
                                    font-weight: 700;
                                ">
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
                                    $_POST['data']
                                        ?? date('Y-m-d\TH:i')
                                ) ?>"
                        required>

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
                        placeholder="Opcional"><?= htmlspecialchars(
                                                    $_POST['observacao'] ?? ''
                                                ) ?></textarea>

                </div>


            </div>


            <!-- AÇÕES -->

            <div class="form-actions">

                <a
                    href="detalhes.php?id=<?= $obraId ?>"
                    class="btn-secondary">
                    Cancelar
                </a>


                <button
                    type="submit"
                    class="btn-primary">

                    <i class="bi bi-plus-lg"></i>

                    Adicionar material

                </button>

            </div>

        </form>

    </div>


</main>


<script>
    document.addEventListener(
        'DOMContentLoaded',
        function() {

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


                /*
                | PACOTE / CAIXA
                */

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


                    /*
                    | UNIDADE
                    */

                } else if (valor === 'un') {

                    embalagemFields.style.display =
                        'none';

                    quantidade.step = '1';
                    quantidade.min = '1';

                    quantidadePorEmbalagem.required =
                        false;

                    quantidadeLabel.textContent =
                        'Quantidade de unidades *';


                    /*
                    | METRO / KG / LITRO
                    */

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
            | CALCULAR TOTAL
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
                        quantidadePorEmbalagem.value ||
                        '0',
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