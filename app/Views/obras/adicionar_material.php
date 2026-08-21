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
    | DADOS DO VIDRO
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
    | UNIDADES PERMITIDAS
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
            | QUANTIDADE NORMAL
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
            | VALORES PADRÃO DO VIDRO
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
                        'Informe uma espessura válida para o vidro.'
                    );
                }


                if (
                    $vidroLargura === '' ||
                    !is_numeric($vidroLargura) ||
                    (float) $vidroLargura <= 0
                ) {

                    throw new Exception(
                        'Informe uma largura válida para o vidro.'
                    );
                }


                if (
                    $vidroAltura === '' ||
                    !is_numeric($vidroAltura) ||
                    (float) $vidroAltura <= 0
                ) {

                    throw new Exception(
                        'Informe uma altura válida para o vidro.'
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
                | CONVERTER
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
                | CALCULAR ÁREA
                |--------------------------------------------------------------------------
                |
                | Entrada das medidas em milímetros.
                |
                */

                $larguraMetros =
                    $vidroLarguraBanco / 1000;

                $alturaMetros =
                    $vidroAlturaBanco / 1000;


                $vidroAreaUnitaria =
                    $larguraMetros *
                    $alturaMetros;


                $vidroAreaTotal =
                    $vidroAreaUnitaria *
                    $vidroQuantidadePecasBanco;


                /*
                |--------------------------------------------------------------------------
                | ESTOQUE DO VIDRO = PEÇAS
                |--------------------------------------------------------------------------
                */

                $quantidadeFloat =
                    $vidroQuantidadePecasBanco;

                $quantidadePorEmbalagem =
                    null;

                $quantidadeTotal =
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


                $quantidadeTotal =
                    $quantidadeFloat *
                    $quantidadePorEmbalagem;


                /*
            |--------------------------------------------------------------------------
            | OUTRAS UNIDADES
            |--------------------------------------------------------------------------
            */
            } else {

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
            | TRANSAÇÃO
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | INSERIR MATERIAL
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO materiais_obra (

                    obra_id,
                    codigo,
                    nome,
                    unidade,

                    vidro_tipo,
                    vidro_espessura,
                    vidro_descricao,
                    vidro_largura,
                    vidro_altura,
                    vidro_quantidade_pecas,
                    vidro_area_unitaria,
                    vidro_area_total,

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

                $quantidadeTotal,

                $observacao ?: null,

                $dataBanco

            ]);


            /*
            |--------------------------------------------------------------------------
            | ID DO MATERIAL
            |--------------------------------------------------------------------------
            */

            $materialObraId =
                $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | MOVIMENTAÇÃO INICIAL
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

                $unidade === 'vidro'
                    ? 'Entrada inicial de vidro na obra'
                    : 'Entrada inicial do material na obra',

                $dataBanco

            ]);


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | REDIRECIONAR
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


                <!-- PRODUTO -->

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


                        <option
                            value="vidro"
                            <?= ($_POST['unidade'] ?? '') === 'vidro'
                                ? 'selected'
                                : '' ?>>
                            Vidro
                        </option>

                    </select>

                </div>


                <!-- QUANTIDADE NORMAL -->

                <div
                    class="form-group"
                    id="quantidadeNormalField">

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
                                ) ?>">

                </div>


                <!-- =========================
                     EMBALAGEM
                ========================== -->

                <div
                    class="form-group full"
                    id="embalagemFields"
                    style="display:none;">

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
                        style="margin-top:16px;">

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
                                            $_POST['quantidade_por_embalagem'] ?? ''
                                        ) ?>">

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
                                ">

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
                    style="display:none;">

                    <div class="glass-form-card">


                        <!-- HEADER -->

                        <div class="glass-form-header">

                            <div class="glass-form-icon">

                                <i class="bi bi-grid-3x3"></i>

                            </div>


                            <div>

                                <strong>
                                    Dados do vidro
                                </strong>

                                <span>
                                    Informe o tipo, espessura, medidas e quantidade de peças recebidas.
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
                                    name="vidro_tipo">

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
                                        $tiposVidro as $tipoVidro
                                    ): ?>

                                        <option
                                            value="<?= htmlspecialchars(
                                                        $tipoVidro
                                                    ) ?>"

                                            <?= (
                                                $_POST['vidro_tipo']
                                                ?? ''
                                            ) === $tipoVidro
                                                ? 'selected'
                                                : '' ?>>

                                            <?= htmlspecialchars(
                                                $tipoVidro
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
                                    name="vidro_espessura">

                                    <option value="">
                                        Selecione
                                    </option>


                                    <?php

                                    $espessurasVidro = [
                                        4,
                                        6,
                                        8,
                                        10,
                                        12
                                    ];

                                    ?>


                                    <?php foreach (
                                        $espessurasVidro as $espessura
                                    ): ?>

                                        <option
                                            value="<?= $espessura ?>"

                                            <?= (string) ($_POST['vidro_espessura'] ?? '') === (string) $espessura
                                                ? 'selected'
                                                : '' ?>>

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
                                    placeholder="Ex: P1, J2, Porta da sala..."
                                    value="<?= htmlspecialchars(
                                                $_POST['vidro_descricao']
                                                    ?? ''
                                            ) ?>">

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
                                        placeholder="Ex: 1200"
                                        value="<?= htmlspecialchars(
                                                    $_POST['vidro_largura']
                                                        ?? ''
                                                ) ?>">

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
                                        placeholder="Ex: 2100"
                                        value="<?= htmlspecialchars(
                                                    $_POST['vidro_altura']
                                                        ?? ''
                                                ) ?>">

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
                                    placeholder="Ex: 2"
                                    value="<?= htmlspecialchars(
                                                $_POST['vidro_quantidade_pecas'] ?? ''
                                            ) ?>">

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

            /*
            |--------------------------------------------------------------------------
            | ELEMENTOS GERAIS
            |--------------------------------------------------------------------------
            */

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
            |--------------------------------------------------------------------------
            | EMBALAGEM
            |--------------------------------------------------------------------------
            */

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
            | VIDRO
            |--------------------------------------------------------------------------
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
            | ATUALIZAR UNIDADE
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
                |--------------------------------------------------------------------------
                | RESET
                |--------------------------------------------------------------------------
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
                |--------------------------------------------------------------------------
                | VIDRO
                |--------------------------------------------------------------------------
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
                    |--------------------------------------------------------------------------
                    | PACOTE / CAIXA
                    |--------------------------------------------------------------------------
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
                    |--------------------------------------------------------------------------
                    | UNIDADE
                    |--------------------------------------------------------------------------
                    */

                } else if (valor === 'un') {

                    quantidade.step =
                        '1';

                    quantidade.min =
                        '1';

                    quantidadeLabel.textContent =
                        'Quantidade de unidades *';


                    /*
                    |--------------------------------------------------------------------------
                    | OUTRAS UNIDADES
                    |--------------------------------------------------------------------------
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

                const valor =
                    unidade.value;


                if (
                    valor !== 'pacote' &&
                    valor !== 'caixa'
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

                const itens =
                    parseInt(
                        quantidadePorEmbalagem.value ||
                        '0',
                        10
                    );


                const total =
                    qtd * itens;


                totalItens.textContent =
                    total.toLocaleString(
                        'pt-BR'
                    ) +
                    ' un';
            }


            /*
            |--------------------------------------------------------------------------
            | CALCULAR VIDRO
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
                        vidroQuantidadePecas.value ||
                        '0',
                        10
                    );


                const larguraMetros =
                    largura / 1000;

                const alturaMetros =
                    altura / 1000;


                const areaUnitaria =
                    larguraMetros *
                    alturaMetros;


                const areaTotal =
                    areaUnitaria *
                    pecas;


                vidroAreaUnitaria.textContent =
                    areaUnitaria.toLocaleString(
                        'pt-BR', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 4
                        }
                    ) +
                    ' m²';


                vidroAreaTotal.textContent =
                    areaTotal.toLocaleString(
                        'pt-BR', {
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