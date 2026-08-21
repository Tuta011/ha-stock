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
| BUSCAR MATERIAL
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        mo.*,

        o.nome AS obra_nome,
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


if ($material['obra_status'] !== 'ativa') {

    die(
        'Não é possível retirar materiais de uma obra inativa.'
    );
}


/*
|--------------------------------------------------------------------------
| TIPOS
|--------------------------------------------------------------------------
*/

$unidade =
    strtolower(
        trim($material['unidade'])
    );

$ehVidro =
    $unidade === 'vidro';

$ehEmbalagem =
    in_array(
        $unidade,
        [
            'pacote',
            'caixa'
        ],
        true
    );

$usaQuantidadeInteira =
    in_array(
        $unidade,
        [
            'un',
            'pacote',
            'caixa',
            'vidro'
        ],
        true
    );


/*
|--------------------------------------------------------------------------
| BUSCAR ENTRADAS E SAÍDAS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        COALESCE(
            SUM(
                CASE
                    WHEN tipo = 'entrada'
                        THEN quantidade
                    ELSE 0
                END
            ),
            0
        ) AS entradas,

        COALESCE(
            SUM(
                CASE
                    WHEN tipo = 'saida'
                        THEN quantidade
                    ELSE 0
                END
            ),
            0
        ) AS saidas

    FROM movimentacoes_materiais_obra

    WHERE material_obra_id = ?
");

$stmt->execute([
    $materialId
]);

$movimentacoes =
    $stmt->fetch();


$totalEntradas =
    (float) $movimentacoes['entradas'];

$totalSaidas =
    (float) $movimentacoes['saidas'];

$saldoAtual =
    $totalEntradas -
    $totalSaidas;


/*
|--------------------------------------------------------------------------
| ÁREA RESTANTE DO VIDRO
|--------------------------------------------------------------------------
*/

$areaUnitariaVidro =
    $ehVidro
        ? (float) (
            $material['vidro_area_unitaria']
            ?? 0
        )
        : 0;

$areaSaldoVidro =
    $ehVidro
        ? $areaUnitariaVidro * $saldoAtual
        : 0;


/*
|--------------------------------------------------------------------------
| VARIÁVEIS
|--------------------------------------------------------------------------
*/

$erro = '';


/*
|--------------------------------------------------------------------------
| PROCESSAR SAÍDA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $quantidade =
        $_POST['quantidade'] ?? '';

    $data =
        $_POST['data'] ?? '';

    $observacao =
        trim(
            $_POST['observacao']
            ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | VALIDAÇÕES
    |--------------------------------------------------------------------------
    */

    if (
        $quantidade === '' ||
        $data === ''
    ) {

        $erro =
            'Preencha todos os campos obrigatórios.';

    } elseif (
        !is_numeric($quantidade) ||
        (float) $quantidade <= 0
    ) {

        $erro =
            'Informe uma quantidade válida.';

    } else {

        try {

            $quantidadeSaida =
                (float) $quantidade;


            /*
            |--------------------------------------------------------------------------
            | INTEIROS
            |--------------------------------------------------------------------------
            */

            if (
                $usaQuantidadeInteira &&
                floor($quantidadeSaida)
                    != $quantidadeSaida
            ) {

                throw new Exception(
                    $ehVidro
                        ? 'A saída de vidro deve ser informada em peças inteiras.'
                        : 'Este material aceita somente quantidades inteiras.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | BLOQUEAR SAÍDA MAIOR QUE SALDO
            |--------------------------------------------------------------------------
            */

            if (
                $quantidadeSaida >
                $saldoAtual
            ) {

                $saldoTexto =
                    number_format(
                        $saldoAtual,
                        $usaQuantidadeInteira
                            ? 0
                            : 2,
                        ',',
                        '.'
                    );


                throw new Exception(
                    $ehVidro
                        ? 'Quantidade maior que o saldo disponível. Saldo atual: ' .
                            $saldoTexto .
                            ' peça(s).'
                        : 'Quantidade maior que o saldo disponível. Saldo atual: ' .
                            $saldoTexto
                );
            }


            /*
            |--------------------------------------------------------------------------
            | DATA
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
            | OBSERVAÇÃO DA MOVIMENTAÇÃO
            |--------------------------------------------------------------------------
            */

            $observacaoMovimentacao =
                $observacao ?: null;


            if (
                $ehVidro &&
                !$observacaoMovimentacao
            ) {

                $observacaoMovimentacao =
                    'Saída de vidro da obra';
            }


            /*
            |--------------------------------------------------------------------------
            | REGISTRAR SAÍDA
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
                    'saida',
                    ?,
                    ?,
                    ?
                )
            ");


            $stmt->execute([
                $materialId,
                $quantidadeSaida,
                $observacaoMovimentacao,
                $dataBanco
            ]);


            /*
            |--------------------------------------------------------------------------
            | REDIRECIONAR
            |--------------------------------------------------------------------------
            */

            header(
                'Location: detalhes.php?id=' .
                urlencode(
                    $material['obra_id']
                ) .
                '&material=saida'
            );

            exit;


        } catch (PDOException $e) {

            $erro =
                'Erro ao registrar saída do material.';


        } catch (Exception $e) {

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

            <a
                href="detalhes.php?id=<?= $material['obra_id'] ?>"
                class="clear-filter"
                style="
                    display:inline-flex;
                    margin-bottom:10px;
                "
            >

                <i class="bi bi-arrow-left"></i>

                Voltar para obra

            </a>


            <h1>
                Dar saída
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

    </div>


    <!-- ERRO -->

    <?php if ($erro): ?>

        <div class="alert-error">

            <i class="bi bi-exclamation-circle"></i>

            <?= htmlspecialchars(
                $erro
            ) ?>

        </div>

    <?php endif; ?>


    <section class="work-stock-exit-card">


        <!-- =========================
             RESUMO DO MATERIAL
        ========================== -->

        <div class="work-stock-product-header">

            <div class="work-stock-product-main">


                <!-- ÍCONE -->

                <div class="work-stock-product-icon">

                    <?php if ($ehVidro): ?>

                        <i class="bi bi-grid-3x3"></i>

                    <?php else: ?>

                        <i class="bi bi-box-seam"></i>

                    <?php endif; ?>

                </div>


                <!-- DADOS -->

                <div>

                    <span class="work-stock-label">

                        <?= $ehVidro
                            ? 'Vidro'
                            : 'Material' ?>

                    </span>


                    <h2>

                        <?= htmlspecialchars(
                            $material['nome']
                        ) ?>

                    </h2>


                    <span class="work-stock-code">

                        <?= htmlspecialchars(
                            $material['codigo']
                            ?: 'Sem código'
                        ) ?>

                    </span>

                </div>

            </div>


            <!-- SALDO -->

            <div class="work-stock-balance">

                <span>
                    Saldo disponível
                </span>


                <strong>

                    <?= number_format(
                        $saldoAtual,
                        $usaQuantidadeInteira
                            ? 0
                            : 2,
                        ',',
                        '.'
                    ) ?>


                    <?php if ($ehVidro): ?>

                        peça(s)

                    <?php elseif ($ehEmbalagem): ?>

                        un

                    <?php else: ?>

                        <?= htmlspecialchars(
                            $material['unidade']
                        ) ?>

                    <?php endif; ?>

                </strong>


                <?php if ($ehVidro): ?>

                    <small
                        style="
                            display:block;
                            margin-top:4px;
                            color:#8b93a1;
                            font-size:10px;
                        "
                    >

                        <?= number_format(
                            $areaSaldoVidro,
                            2,
                            ',',
                            '.'
                        ) ?>

                        m² restantes

                    </small>

                <?php endif; ?>

            </div>

        </div>



        <!-- =========================
             DADOS DO VIDRO
        ========================== -->

        <?php if ($ehVidro): ?>

            <div class="glass-exit-details">


                <!-- TIPO -->

                <div class="glass-exit-detail">

                    <span>
                        Tipo
                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            $material['vidro_tipo']
                            ?: '-'
                        ) ?>

                    </strong>

                </div>


                <!-- ESPESSURA -->

                <div class="glass-exit-detail">

                    <span>
                        Espessura
                    </span>

                    <strong>

                        <?= number_format(
                            (float) $material[
                                'vidro_espessura'
                            ],
                            0,
                            ',',
                            '.'
                        ) ?>

                        mm

                    </strong>

                </div>


                <!-- MEDIDA -->

                <div class="glass-exit-detail">

                    <span>
                        Medida
                    </span>

                    <strong>

                        <?= number_format(
                            (float) $material[
                                'vidro_largura'
                            ],
                            0,
                            ',',
                            '.'
                        ) ?>

                        ×

                        <?= number_format(
                            (float) $material[
                                'vidro_altura'
                            ],
                            0,
                            ',',
                            '.'
                        ) ?>

                        mm

                    </strong>

                </div>


                <!-- ÁREA POR PEÇA -->

                <div class="glass-exit-detail">

                    <span>
                        Área por peça
                    </span>

                    <strong>

                        <?= number_format(
                            $areaUnitariaVidro,
                            2,
                            ',',
                            '.'
                        ) ?>

                        m²

                    </strong>

                </div>


                <!-- DESCRIÇÃO -->

                <?php if (
                    !empty(
                        $material[
                            'vidro_descricao'
                        ]
                    )
                ): ?>

                    <div class="glass-exit-detail full">

                        <span>
                            Descrição / identificação
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $material[
                                    'vidro_descricao'
                                ]
                            ) ?>

                        </strong>

                    </div>

                <?php endif; ?>


            </div>

        <?php endif; ?>



        <!-- =========================
             INFO EMBALAGEM
        ========================== -->

        <?php if ($ehEmbalagem): ?>

            <div class="work-stock-info-box">

                <div class="work-stock-info-icon">

                    <i class="bi bi-info-circle"></i>

                </div>


                <div>

                    <strong>
                        Saída controlada por unidade
                    </strong>


                    <p>

                        Este material entrou como

                        <b>

                            <?= number_format(
                                $material['quantidade'],
                                0,
                                ',',
                                '.'
                            ) ?>

                            <?= htmlspecialchars(
                                $material['unidade']
                            ) ?>(s)

                        </b>

                        com

                        <b>

                            <?= number_format(
                                $material[
                                    'quantidade_por_embalagem'
                                ],
                                0,
                                ',',
                                '.'
                            ) ?>

                            itens por embalagem.

                        </b>


                        A retirada será registrada pela quantidade de itens.

                    </p>

                </div>

            </div>

        <?php endif; ?>



        <!-- =========================
             INFO VIDRO
        ========================== -->

        <?php if ($ehVidro): ?>

            <div class="work-stock-info-box">

                <div class="work-stock-info-icon">

                    <i class="bi bi-info-circle"></i>

                </div>


                <div>

                    <strong>
                        Saída controlada por peça
                    </strong>


                    <p>

                        Cada retirada deste vidro será registrada pela
                        quantidade de peças.

                        As medidas, espessura e tipo do vidro permanecem
                        vinculados ao material.

                    </p>

                </div>

            </div>

        <?php endif; ?>



        <!-- =========================
             FORMULÁRIO
        ========================== -->

        <form
            method="POST"
            class="work-stock-exit-form"
        >

            <div class="work-stock-exit-grid">


                <!-- QUANTIDADE -->

                <div class="form-group">

                    <label for="quantidade">

                        <?php if ($ehVidro): ?>

                            Quantidade de peças para retirada *

                        <?php else: ?>

                            Quantidade para retirada *

                        <?php endif; ?>

                    </label>


                    <div class="work-stock-input-wrapper">

                        <i class="bi bi-box-arrow-up"></i>


                        <input
                            type="number"
                            id="quantidade"
                            name="quantidade"

                            min="<?= $usaQuantidadeInteira
                                ? '1'
                                : '0.01' ?>"

                            step="<?= $usaQuantidadeInteira
                                ? '1'
                                : '0.01' ?>"

                            max="<?= htmlspecialchars(
                                $saldoAtual
                            ) ?>"

                            placeholder="<?= $ehVidro
                                ? 'Ex: 2'
                                : 'Ex: 10' ?>"

                            value="<?= htmlspecialchars(
                                $_POST['quantidade']
                                ?? ''
                            ) ?>"

                            required
                        >

                    </div>


                    <small class="work-stock-field-help">

                        Máximo disponível:

                        <?= number_format(
                            $saldoAtual,
                            $usaQuantidadeInteira
                                ? 0
                                : 2,
                            ',',
                            '.'
                        ) ?>


                        <?php if ($ehVidro): ?>

                            peça(s)

                        <?php elseif ($ehEmbalagem): ?>

                            un

                        <?php else: ?>

                            <?= htmlspecialchars(
                                $material['unidade']
                            ) ?>

                        <?php endif; ?>

                    </small>

                </div>


                <!-- DATA -->

                <div class="form-group">

                    <label for="data">
                        Data e horário *
                    </label>


                    <div class="work-stock-input-wrapper">

                        <i class="bi bi-calendar3"></i>


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

                        placeholder="<?= $ehVidro
                            ? 'Ex: Vidro enviado para instalação da P1...'
                            : 'Ex: Material utilizado na instalação...' ?>"
                    ><?= htmlspecialchars(
                        $_POST['observacao']
                        ?? ''
                    ) ?></textarea>

                </div>

            </div>


            <!-- AÇÕES -->

            <div class="work-stock-exit-actions">

                <a
                    href="detalhes.php?id=<?= $material['obra_id'] ?>"
                    class="btn-secondary"
                >

                    Cancelar

                </a>


                <button
                    type="submit"
                    class="btn-work-stock-exit"
                    <?= $saldoAtual <= 0
                        ? 'disabled'
                        : '' ?>
                >

                    <i class="bi bi-box-arrow-up"></i>

                    <?= $ehVidro
                        ? 'Registrar saída das peças'
                        : 'Registrar saída' ?>

                </button>

            </div>

        </form>

    </section>

</main>


<?php include '../../Includes/footer.php'; ?>