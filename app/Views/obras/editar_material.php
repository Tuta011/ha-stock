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

        mo.vidro_tipo,
        mo.vidro_espessura,
        mo.vidro_descricao,
        mo.vidro_largura,
        mo.vidro_altura,
        mo.vidro_quantidade_pecas,
        mo.vidro_area_unitaria,
        mo.vidro_area_total,

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
| OBRA ATIVA
|--------------------------------------------------------------------------
*/

if ($material['obra_status'] !== 'ativa') {

    die(
        'Não é possível editar materiais de uma obra inativa.'
    );
}


/*
|--------------------------------------------------------------------------
| TOTAL DE SAÍDAS
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
        )

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
    | VIDRO
    |--------------------------------------------------------------------------
    */

    $vidroTipo =
        trim($_POST['vidro_tipo'] ?? '');

    $vidroEspessura =
        $_POST['vidro_espessura'] ?? '';

    $vidroDescricao =
        trim($_POST['vidro_descricao'] ?? '');

    $vidroLargura =
        $_POST['vidro_largura'] ?? '';

    $vidroAltura =
        $_POST['vidro_altura'] ?? '';

    $vidroQuantidadePecas =
        $_POST['vidro_quantidade_pecas'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | UNIDADES
    |--------------------------------------------------------------------------
    */

    $unidadesPermitidas = [
        'un',
        'pacote',
        'caixa',
        'metro',
        'kg',
        'litro',
        'vidro'
    ];


    /*
    |--------------------------------------------------------------------------
    | VALIDAÇÕES GERAIS
    |--------------------------------------------------------------------------
    */

    if (
        $nome === '' ||
        $unidade === '' ||
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
        $unidade !== 'vidro' &&
        $quantidade === ''
    ) {

        $erro =
            'Informe a quantidade.';

    } elseif (
        $unidade !== 'vidro' &&
        (
            !is_numeric($quantidade) ||
            (float) $quantidade <= 0
        )
    ) {

        $erro =
            'Informe uma quantidade válida.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | QUANTIDADE PADRÃO
            |--------------------------------------------------------------------------
            */

            $quantidadeFloat =
                $unidade === 'vidro'
                    ? 0
                    : (float) $quantidade;


            /*
            |--------------------------------------------------------------------------
            | UNIDADES INTEIRAS
            |--------------------------------------------------------------------------
            */

            if (
                $unidade !== 'vidro' &&
                in_array(
                    $unidade,
                    [
                        'un',
                        'pacote',
                        'caixa'
                    ],
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
            | RESET CAMPOS VIDRO
            |--------------------------------------------------------------------------
            */

            $vidroTipoBanco =
                null;

            $vidroEspessuraBanco =
                null;

            $vidroDescricaoBanco =
                null;

            $vidroLarguraBanco =
                null;

            $vidroAlturaBanco =
                null;

            $vidroQuantidadePecasBanco =
                null;

            $vidroAreaUnitaria =
                null;

            $vidroAreaTotal =
                null;


            /*
            |--------------------------------------------------------------------------
            | VIDRO
            |--------------------------------------------------------------------------
            */

            if ($unidade === 'vidro') {

                if ($vidroTipo === '') {

                    throw new Exception(
                        'Informe o tipo do vidro.'
                    );
                }


                if (
                    $vidroEspessura === '' ||
                    !is_numeric($vidroEspessura) ||
                    (float) $vidroEspessura <= 0
                ) {

                    throw new Exception(
                        'Informe uma espessura válida.'
                    );
                }


                if (
                    $vidroLargura === '' ||
                    !is_numeric($vidroLargura) ||
                    (float) $vidroLargura <= 0
                ) {

                    throw new Exception(
                        'Informe uma largura válida.'
                    );
                }


                if (
                    $vidroAltura === '' ||
                    !is_numeric($vidroAltura) ||
                    (float) $vidroAltura <= 0
                ) {

                    throw new Exception(
                        'Informe uma altura válida.'
                    );
                }


                if (
                    $vidroQuantidadePecas === '' ||
                    !is_numeric($vidroQuantidadePecas) ||
                    (int) $vidroQuantidadePecas <= 0 ||
                    floor(
                        (float) $vidroQuantidadePecas
                    ) !=
                    (float) $vidroQuantidadePecas
                ) {

                    throw new Exception(
                        'Informe uma quantidade válida de peças.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | CONVERSÕES
                |--------------------------------------------------------------------------
                */

                $vidroTipoBanco =
                    $vidroTipo;

                $vidroEspessuraBanco =
                    (float) $vidroEspessura;

                $vidroDescricaoBanco =
                    $vidroDescricao ?: null;

                $vidroLarguraBanco =
                    (float) $vidroLargura;

                $vidroAlturaBanco =
                    (float) $vidroAltura;

                $vidroQuantidadePecasBanco =
                    (int) $vidroQuantidadePecas;


                /*
                |--------------------------------------------------------------------------
                | ÁREA
                |--------------------------------------------------------------------------
                */

                $vidroAreaUnitaria =
                    (
                        $vidroLarguraBanco / 1000
                    ) *
                    (
                        $vidroAlturaBanco / 1000
                    );


                $vidroAreaTotal =
                    $vidroAreaUnitaria *
                    $vidroQuantidadePecasBanco;


                /*
                |--------------------------------------------------------------------------
                | CONTROLE POR PEÇAS
                |--------------------------------------------------------------------------
                */

                $quantidadeFloat =
                    $vidroQuantidadePecasBanco;

                $quantidadePorEmbalagem =
                    null;

                $quantidadeTotalNova =
                    $vidroQuantidadePecasBanco;


            /*
            |--------------------------------------------------------------------------
            | PACOTE / CAIXA
            |--------------------------------------------------------------------------
            */

            } elseif (
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


            /*
            |--------------------------------------------------------------------------
            | DEMAIS
            |--------------------------------------------------------------------------
            */

            } else {

                $quantidadePorEmbalagem =
                    null;

                $quantidadeTotalNova =
                    $quantidadeFloat;
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDAR SAÍDAS EXISTENTES
            |--------------------------------------------------------------------------
            */

            if (
                $quantidadeTotalNova <
                $totalSaidas
            ) {

                throw new Exception(
                    'A nova quantidade não pode ser menor que o total já retirado deste material.'
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

                    vidro_tipo = ?,
                    vidro_espessura = ?,
                    vidro_descricao = ?,
                    vidro_largura = ?,
                    vidro_altura = ?,
                    vidro_quantidade_pecas = ?,
                    vidro_area_unitaria = ?,
                    vidro_area_total = ?,

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

                $vidroTipoBanco,

                $vidroEspessuraBanco,

                $vidroDescricaoBanco,

                $vidroLarguraBanco,

                $vidroAlturaBanco,

                $vidroQuantidadePecasBanco,

                $vidroAreaUnitaria,

                $vidroAreaTotal,

                $quantidadeFloat,

                $quantidadePorEmbalagem,

                $quantidadeTotalNova,

                $observacao ?: null,

                $dataBanco,

                $materialId

            ]);


            /*
            |--------------------------------------------------------------------------
            | MOVIMENTAÇÃO DE ENTRADA ORIGINAL
            |--------------------------------------------------------------------------
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

                    $materialId,

                    $quantidadeTotalNova,

                    $unidade === 'vidro'
                        ? 'Entrada inicial de vidro na obra'
                        : 'Entrada inicial do material na obra',

                    $dataBanco

                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | COMMIT
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
                urlencode(
                    $material['obra_id']
                ) .
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
| VIDRO
|--------------------------------------------------------------------------
*/

$valorVidroTipo =
    $_POST['vidro_tipo']
    ?? $material['vidro_tipo']
    ?? '';

$valorVidroEspessura =
    $_POST['vidro_espessura']
    ?? $material['vidro_espessura']
    ?? '';

$valorVidroDescricao =
    $_POST['vidro_descricao']
    ?? $material['vidro_descricao']
    ?? '';

$valorVidroLargura =
    $_POST['vidro_largura']
    ?? $material['vidro_largura']
    ?? '';

$valorVidroAltura =
    $_POST['vidro_altura']
    ?? $material['vidro_altura']
    ?? '';

$valorVidroQuantidadePecas =
    $_POST['vidro_quantidade_pecas']
    ?? $material['vidro_quantidade_pecas']
    ?? '';


include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>


<main class="content">


    <!-- CABEÇALHO -->

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


    <!-- ERRO -->

    <?php if ($erro): ?>

        <div class="alert-error">

            <i class="bi bi-exclamation-circle"></i>

            <?= htmlspecialchars(
                $erro
            ) ?>

        </div>

    <?php endif; ?>


    <!-- AVISO -->

    <?php if ($totalSaidas > 0): ?>

        <div class="stock-exit-notice">

            <i class="bi bi-info-circle"></i>

            <div>

                <strong>
                    Este material já possui saídas
                </strong>

                <span>

                    Total retirado:

                    <?= number_format(
                        $totalSaidas,
                        0,
                        ',',
                        '.'
                    ) ?>

                    <?=
                        strtolower(
                            $material['unidade']
                        ) === 'vidro'
                            ? ' peça(s)'
                            : ' un'
                    ?>.

                </span>

            </div>

        </div>

    <?php endif; ?>


    <!-- FORM -->

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

                        <?php

                        $unidades = [
                            'un' => 'Unidade',
                            'pacote' => 'Pacote',
                            'caixa' => 'Caixa',
                            'metro' => 'Metro',
                            'kg' => 'Kg',
                            'litro' => 'Litro',
                            'vidro' => 'Vidro'
                        ];

                        ?>

                        <?php foreach (
                            $unidades as
                            $valor => $texto
                        ): ?>

                            <option
                                value="<?= $valor ?>"
                                <?= $valorUnidade === $valor
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= $texto ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- QUANTIDADE NORMAL -->

                <div
                    class="form-group"
                    id="quantidadeNormalField"
                >

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



                <!-- =========================
                     VIDRO
                ========================== -->

                <div
                    class="form-group full"
                    id="vidroFields"
                    style="display:none;"
                >

                    <div class="glass-form-card">


                        <div class="glass-form-header">

                            <div class="glass-form-icon">

                                <i class="bi bi-grid-3x3"></i>

                            </div>


                            <div>

                                <strong>
                                    Dados do vidro
                                </strong>

                                <span>
                                    Edite o tipo, espessura, medidas e quantidade de peças.
                                </span>

                            </div>

                        </div>


                        <div class="glass-form-grid">


                            <!-- TIPO -->

                            <div class="form-group">

                                <label for="vidro_tipo">
                                    Tipo do vidro *
                                </label>

                                <select
                                    id="vidro_tipo"
                                    name="vidro_tipo"
                                >

                                    <option value="">
                                        Selecione
                                    </option>

                                    <?php

                                    $tiposVidro = [
                                        'Temperado incolor',
                                        'Temperado fumê',
                                        'Temperado verde',

                                        'Comum incolor',
                                        'Comum fumê',
                                        'Comum verde',

                                        'Laminado incolor',
                                        'Laminado fumê',
                                        'Laminado verde'
                                    ];

                                    ?>

                                    <?php foreach (
                                        $tiposVidro as $tipo
                                    ): ?>

                                        <option
                                            value="<?= htmlspecialchars(
                                                $tipo
                                            ) ?>"
                                            <?= $valorVidroTipo === $tipo
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= htmlspecialchars(
                                                $tipo
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- ESPESSURA -->

                            <div class="form-group">

                                <label for="vidro_espessura">
                                    Espessura *
                                </label>

                                <select
                                    id="vidro_espessura"
                                    name="vidro_espessura"
                                >

                                    <option value="">
                                        Selecione
                                    </option>

                                    <?php

                                    $espessuras = [
                                        4,
                                        6,
                                        8,
                                        10,
                                        12
                                    ];

                                    ?>

                                    <?php foreach (
                                        $espessuras as $espessura
                                    ): ?>

                                        <option
                                            value="<?= $espessura ?>"
                                            <?= (string) $valorVidroEspessura
                                                ===
                                                (string) $espessura
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= $espessura ?> mm
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- DESCRIÇÃO -->

                            <div class="form-group full">

                                <label for="vidro_descricao">
                                    Descrição / identificação
                                </label>

                                <input
                                    type="text"
                                    id="vidro_descricao"
                                    name="vidro_descricao"
                                    value="<?= htmlspecialchars(
                                        $valorVidroDescricao
                                    ) ?>"
                                    placeholder="Ex: P1, J2..."
                                >

                            </div>


                            <!-- LARGURA -->

                            <div class="form-group">

                                <label for="vidro_largura">
                                    Largura *
                                </label>

                                <div class="glass-measure-input">

                                    <input
                                        type="number"
                                        id="vidro_largura"
                                        name="vidro_largura"
                                        min="1"
                                        step="0.01"
                                        value="<?= htmlspecialchars(
                                            $valorVidroLargura
                                        ) ?>"
                                    >

                                    <span>
                                        mm
                                    </span>

                                </div>

                            </div>


                            <!-- ALTURA -->

                            <div class="form-group">

                                <label for="vidro_altura">
                                    Altura *
                                </label>

                                <div class="glass-measure-input">

                                    <input
                                        type="number"
                                        id="vidro_altura"
                                        name="vidro_altura"
                                        min="1"
                                        step="0.01"
                                        value="<?= htmlspecialchars(
                                            $valorVidroAltura
                                        ) ?>"
                                    >

                                    <span>
                                        mm
                                    </span>

                                </div>

                            </div>


                            <!-- PEÇAS -->

                            <div class="form-group">

                                <label for="vidro_quantidade_pecas">
                                    Quantidade de peças *
                                </label>

                                <input
                                    type="number"
                                    id="vidro_quantidade_pecas"
                                    name="vidro_quantidade_pecas"
                                    min="1"
                                    step="1"
                                    value="<?= htmlspecialchars(
                                        $valorVidroQuantidadePecas
                                    ) ?>"
                                >

                            </div>


                            <!-- CÁLCULO -->

                            <div class="glass-calculation">

                                <div>

                                    <span>
                                        Área por peça
                                    </span>

                                    <strong id="vidroAreaUnitaria">
                                        0,00 m²
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Área total
                                    </span>

                                    <strong id="vidroAreaTotal">
                                        0,00 m²
                                    </strong>

                                </div>

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
                    ><?= htmlspecialchars(
                        $valorObservacao
                    ) ?></textarea>

                </div>


            </div>


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

        const quantidadeNormalField =
            document.getElementById(
                'quantidadeNormalField'
            );

        const quantidadeLabel =
            document.getElementById(
                'quantidadeLabel'
            );


        /*
        | EMBALAGEM
        */

        const embalagemFields =
            document.getElementById(
                'embalagemFields'
            );

        const quantidadePorEmbalagem =
            document.getElementById(
                'quantidade_por_embalagem'
            );

        const embalagemTexto =
            document.getElementById(
                'embalagemTexto'
            );

        const totalItens =
            document.getElementById(
                'totalItens'
            );


        /*
        | VIDRO
        */

        const vidroFields =
            document.getElementById(
                'vidroFields'
            );

        const vidroTipo =
            document.getElementById(
                'vidro_tipo'
            );

        const vidroEspessura =
            document.getElementById(
                'vidro_espessura'
            );

        const vidroLargura =
            document.getElementById(
                'vidro_largura'
            );

        const vidroAltura =
            document.getElementById(
                'vidro_altura'
            );

        const vidroQuantidadePecas =
            document.getElementById(
                'vidro_quantidade_pecas'
            );

        const vidroAreaUnitaria =
            document.getElementById(
                'vidroAreaUnitaria'
            );

        const vidroAreaTotal =
            document.getElementById(
                'vidroAreaTotal'
            );


        /*
        |--------------------------------------------------------------------------
        | UNIDADE
        |--------------------------------------------------------------------------
        */

        function atualizarUnidade() {

            const valor =
                unidade.value;

            const embalagem =
                valor === 'pacote' ||
                valor === 'caixa';

            const vidro =
                valor === 'vidro';


            /*
            | RESET
            */

            embalagemFields.style.display =
                'none';

            vidroFields.style.display =
                'none';

            quantidadeNormalField.style.display =
                'block';


            quantidade.required =
                true;

            quantidadePorEmbalagem.required =
                false;

            vidroTipo.required =
                false;

            vidroEspessura.required =
                false;

            vidroLargura.required =
                false;

            vidroAltura.required =
                false;

            vidroQuantidadePecas.required =
                false;


            /*
            | VIDRO
            */

            if (vidro) {

                vidroFields.style.display =
                    'block';

                quantidadeNormalField.style.display =
                    'none';

                quantidade.required =
                    false;

                vidroTipo.required =
                    true;

                vidroEspessura.required =
                    true;

                vidroLargura.required =
                    true;

                vidroAltura.required =
                    true;

                vidroQuantidadePecas.required =
                    true;


            /*
            | PACOTE / CAIXA
            */

            } else if (embalagem) {

                embalagemFields.style.display =
                    'block';

                quantidade.step =
                    '1';

                quantidade.min =
                    '1';

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

                quantidade.step =
                    '1';

                quantidade.min =
                    '1';

                quantidadeLabel.textContent =
                    'Quantidade de unidades *';


            /*
            | OUTRAS
            */

            } else {

                quantidade.step =
                    '0.01';

                quantidade.min =
                    '0.01';

                quantidadeLabel.textContent =
                    'Quantidade *';
            }


            atualizarTotal();

            calcularVidro();
        }


        /*
        |--------------------------------------------------------------------------
        | TOTAL EMBALAGEM
        |--------------------------------------------------------------------------
        */

        function atualizarTotal() {

            if (
                unidade.value !== 'pacote' &&
                unidade.value !== 'caixa'
            ) {

                totalItens.textContent =
                    '0 un';

                return;
            }


            const quantidadePacotes =
                parseInt(
                    quantidade.value || '0',
                    10
                );

            const itens =
                parseInt(
                    quantidadePorEmbalagem.value
                    || '0',
                    10
                );


            const total =
                quantidadePacotes *
                itens;


            totalItens.textContent =
                total.toLocaleString(
                    'pt-BR'
                ) +
                ' un';
        }


        /*
        |--------------------------------------------------------------------------
        | VIDRO
        |--------------------------------------------------------------------------
        */

        function calcularVidro() {

            if (
                unidade.value !== 'vidro'
            ) {

                vidroAreaUnitaria.textContent =
                    '0,00 m²';

                vidroAreaTotal.textContent =
                    '0,00 m²';

                return;
            }


            const largura =
                parseFloat(
                    vidroLargura.value || '0'
                );

            const altura =
                parseFloat(
                    vidroAltura.value || '0'
                );

            const pecas =
                parseInt(
                    vidroQuantidadePecas.value
                    || '0',
                    10
                );


            const areaUnitaria =
                (largura / 1000) *
                (altura / 1000);


            const areaTotal =
                areaUnitaria *
                pecas;


            vidroAreaUnitaria.textContent =
                areaUnitaria.toLocaleString(
                    'pt-BR',
                    {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 4
                    }
                ) +
                ' m²';


            vidroAreaTotal.textContent =
                areaTotal.toLocaleString(
                    'pt-BR',
                    {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 4
                    }
                ) +
                ' m²';
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


        vidroLargura.addEventListener(
            'input',
            calcularVidro
        );


        vidroAltura.addEventListener(
            'input',
            calcularVidro
        );


        vidroQuantidadePecas.addEventListener(
            'input',
            calcularVidro
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