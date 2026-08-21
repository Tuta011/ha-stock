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
| BUSCAR DADOS DA OBRA
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.codigo,
        o.nome,
        o.endereco,
        o.cidade,
        o.observacoes,
        o.status,
        o.created_at,

        c.id AS cliente_id,
        c.nome AS cliente_nome,
        c.telefone AS cliente_telefone,
        c.email AS cliente_email

    FROM obras o

    INNER JOIN clientes c
        ON c.id = o.cliente_id

    WHERE o.id = :obra_id

    LIMIT 1
");

$stmt->execute([
    'obra_id' => $obraId
]);

$obra = $stmt->fetch();


if (!$obra) {
    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| MATERIAIS VINDOS DO ESTOQUE GERAL
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.id AS produto_id,
        p.codigo,
        p.nome,
        p.unidade,

        COALESCE(
            SUM(
                CASE
                    WHEN m.tipo = 'entrada'
                        THEN m.quantidade
                    ELSE 0
                END
            ),
            0
        ) AS total_entradas,

        COALESCE(
            SUM(
                CASE
                    WHEN m.tipo = 'saida'
                        THEN m.quantidade
                    ELSE 0
                END
            ),
            0
        ) AS total_saidas,

        COALESCE(
            SUM(
                CASE
                    WHEN m.tipo = 'entrada'
                        THEN m.quantidade

                    WHEN m.tipo = 'saida'
                        THEN -m.quantidade

                    ELSE 0
                END
            ),
            0
        ) AS saldo

    FROM movimentacoes m

    INNER JOIN produtos p
        ON p.id = m.produto_id

    WHERE
        m.tipo_estoque = 'obra'
        AND m.obra_id = :obra_id

    GROUP BY
        p.id,
        p.codigo,
        p.nome,
        p.unidade

    ORDER BY
        p.nome ASC
");

$stmt->execute([
    'obra_id' => $obraId
]);

$materiaisEstoque = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| MATERIAIS ADICIONADOS DIRETAMENTE NA OBRA
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        mo.id,
        mo.codigo,
        mo.nome,
        mo.unidade,
        mo.quantidade,
        mo.quantidade_por_embalagem,
        mo.quantidade_total,
        mo.observacao,
        mo.data_entrada,

        mo.vidro_tipo,
        mo.vidro_espessura,
        mo.vidro_descricao,
        mo.vidro_largura,
        mo.vidro_altura,
        mo.vidro_quantidade_pecas,
        mo.vidro_area_unitaria,
        mo.vidro_area_total,

        COALESCE(
            SUM(
                CASE
                    WHEN mm.tipo = 'entrada'
                        THEN mm.quantidade
                    ELSE 0
                END
            ),
            0
        ) AS total_entradas,

        COALESCE(
            SUM(
                CASE
                    WHEN mm.tipo = 'saida'
                        THEN mm.quantidade
                    ELSE 0
                END
            ),
            0
        ) AS total_saidas,

        COALESCE(
            SUM(
                CASE
                    WHEN mm.tipo = 'entrada'
                        THEN mm.quantidade

                    WHEN mm.tipo = 'saida'
                        THEN -mm.quantidade

                    ELSE 0
                END
            ),
            0
        ) AS saldo

    FROM materiais_obra mo

    LEFT JOIN movimentacoes_materiais_obra mm
        ON mm.material_obra_id = mo.id

    WHERE mo.obra_id = :obra_id

    GROUP BY
        mo.id,
        mo.codigo,
        mo.nome,
        mo.unidade,
        mo.quantidade,
        mo.quantidade_por_embalagem,
        mo.quantidade_total,
        mo.observacao,
        mo.data_entrada,
        mo.vidro_tipo,
        mo.vidro_espessura,
        mo.vidro_descricao,
        mo.vidro_largura,
        mo.vidro_altura,
        mo.vidro_quantidade_pecas,
        mo.vidro_area_unitaria,
        mo.vidro_area_total

    ORDER BY
        mo.data_entrada DESC,
        mo.id DESC
");

$stmt->execute([
    'obra_id' => $obraId
]);

$materiaisDiretos = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| TOTAIS
|--------------------------------------------------------------------------
*/

$totalProdutosEstoque =
    count($materiaisEstoque);

$totalProdutosDiretos =
    count($materiaisDiretos);

$totalProdutos =
    $totalProdutosEstoque +
    $totalProdutosDiretos;


$totalEntradas = 0;
$totalSaidas = 0;
$totalSaldo = 0;


/*
|--------------------------------------------------------------------------
| TOTAIS DO MATERIAL VINDO DO ESTOQUE
|--------------------------------------------------------------------------
*/

foreach ($materiaisEstoque as $material) {

    $totalEntradas +=
        (float) $material['total_entradas'];

    $totalSaidas +=
        (float) $material['total_saidas'];

    $totalSaldo +=
        (float) $material['saldo'];
}


/*
|--------------------------------------------------------------------------
| TOTAIS DOS MATERIAIS DIRETOS
|--------------------------------------------------------------------------
*/

$totalEntradasDiretas = 0;
$totalSaidasDiretas = 0;
$totalSaldoDireto = 0;

foreach ($materiaisDiretos as $material) {

    $totalEntradasDiretas +=
        (float) $material['total_entradas'];

    $totalSaidasDiretas +=
        (float) $material['total_saidas'];

    $totalSaldoDireto +=
        (float) $material['saldo'];
}


/*
|--------------------------------------------------------------------------
| TOTAL GERAL DA OBRA
|--------------------------------------------------------------------------
*/

$totalRecebidoObra =
    $totalEntradas +
    $totalEntradasDiretas;

$totalRetiradoObra =
    $totalSaidas +
    $totalSaidasDiretas;

$totalSaldoObra =
    $totalSaldo +
    $totalSaldoDireto;

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$statusLabel = match ($obra['status']) {

    'ativa' => 'Ativa',

    'finalizada' => 'Finalizada',

    'cancelada' => 'Cancelada',

    default => ucfirst($obra['status'])
};


/*
|--------------------------------------------------------------------------
| FUNÇÃO PARA FORMATAR QUANTIDADE
|--------------------------------------------------------------------------
*/

function formatarQuantidadeObra(
    $quantidade,
    $unidade
) {

    $quantidade =
        (float) $quantidade;

    $unidade =
        strtolower(
            trim($unidade)
        );


    if (
        in_array(
            $unidade,
            [
                'un',
                'pacote',
                'caixa',
                'vidro'
            ],
            true
        )
    ) {

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


include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>


<main class="content">


    <!-- =========================
         MENSAGEM DE SUCESSO
    ========================== -->

    <?php if (
        isset($_GET['material']) &&
        $_GET['material'] === 'adicionado'
    ): ?>

        <div class="alert-success">

            <i class="bi bi-check-circle"></i>

            Material adicionado à obra com sucesso!

        </div>

    <?php endif; ?>



    <!-- =========================
         CABEÇALHO
    ========================== -->

    <div class="page-header">

        <div>

            <a
                href="index.php"
                class="clear-filter"
                style="
                    display:inline-flex;
                    margin-bottom:10px;
                ">

                <i class="bi bi-arrow-left"></i>

                Voltar para obras

            </a>


            <h1>

                <?= htmlspecialchars(
                    $obra['nome']
                ) ?>

            </h1>


            <p>

                <?php if ($obra['codigo']): ?>

                    <?= htmlspecialchars(
                        $obra['codigo']
                    ) ?>

                    &bull;

                <?php endif; ?>


                <?= htmlspecialchars(
                    $obra['cliente_nome']
                ) ?>

            </p>

        </div>


        <span
            class="
                obra-status
                obra-status-<?= htmlspecialchars(
                                $obra['status']
                            ) ?>
            ">

            <?= htmlspecialchars(
                $statusLabel
            ) ?>

        </span>

    </div>



    <!-- =========================
         RESUMO
    ========================== -->

    <section class="obra-summary-grid">


        <!-- PRODUTOS -->

        <div class="obra-summary-card">

            <div class="obra-summary-icon">

                <i class="bi bi-box-seam"></i>

            </div>


            <div>

                <span>
                    Produtos da obra
                </span>

                <strong>
                    <?= $totalProdutos ?>
                </strong>

            </div>

        </div>



        <!-- RECEBIDO -->

        <div class="obra-summary-card">

            <div class="obra-summary-icon">

                <i class="bi bi-arrow-down-circle"></i>

            </div>


            <div>

                <span>
                    Total recebido
                </span>

                <strong>

                    <?= formatarQuantidadeObra(
                        $totalRecebidoObra,
                        'un'
                    ) ?>

                </strong>

            </div>

        </div>



        <!-- RETIRADO -->

        <div class="obra-summary-card">

            <div class="obra-summary-icon">

                <i class="bi bi-arrow-up-circle"></i>

            </div>


            <div>

                <span>
                    Total retirado
                </span>

                <strong>

                    <?= formatarQuantidadeObra(
                        $totalRetiradoObra,
                        'un'
                    ) ?>

                </strong>

            </div>

        </div>



        <!-- SALDO -->

        <div class="obra-summary-card">

            <div class="obra-summary-icon">

                <i class="bi bi-lock"></i>

            </div>


            <div>

                <span>
                    Saldo na obra
                </span>

                <strong>

                    <?= formatarQuantidadeObra(
                        $totalSaldoObra,
                        'un'
                    ) ?>

                </strong>

            </div>

        </div>


    </section>



    <!-- =========================
         INFORMAÇÕES DA OBRA
    ========================== -->

    <section class="obra-details-card">

        <div class="obra-section-header">

            <div>

                <h2>
                    Informações da obra
                </h2>

                <p>
                    Dados cadastrados da obra e do cliente.
                </p>

            </div>

        </div>


        <div class="obra-info-grid">


            <div class="obra-info-item">

                <span>
                    Cliente
                </span>

                <strong>

                    <?= htmlspecialchars(
                        $obra['cliente_nome']
                    ) ?>

                </strong>

            </div>



            <div class="obra-info-item">

                <span>
                    Código
                </span>

                <strong>

                    <?= htmlspecialchars(
                        $obra['codigo']
                            ?: '-'
                    ) ?>

                </strong>

            </div>



            <div class="obra-info-item">

                <span>
                    Cidade
                </span>

                <strong>

                    <?= htmlspecialchars(
                        $obra['cidade']
                            ?: '-'
                    ) ?>

                </strong>

            </div>



            <div class="obra-info-item">

                <span>
                    Endereço
                </span>

                <strong>

                    <?= htmlspecialchars(
                        $obra['endereco']
                            ?: '-'
                    ) ?>

                </strong>

            </div>



            <div class="obra-info-item">

                <span>
                    Telefone do cliente
                </span>

                <strong>

                    <?= htmlspecialchars(
                        $obra['cliente_telefone']
                            ?: '-'
                    ) ?>

                </strong>

            </div>



            <div class="obra-info-item">

                <span>
                    E-mail do cliente
                </span>

                <strong>

                    <?= htmlspecialchars(
                        $obra['cliente_email']
                            ?: '-'
                    ) ?>

                </strong>

            </div>


        </div>


        <?php if ($obra['observacoes']): ?>

            <div class="obra-observacoes">

                <span>
                    Observações
                </span>

                <p>

                    <?= nl2br(
                        htmlspecialchars(
                            $obra['observacoes']
                        )
                    ) ?>

                </p>

            </div>

        <?php endif; ?>


    </section>



    <!-- =========================
         MATERIAIS
    ========================== -->

    <section class="obra-details-card">


        <div class="obra-section-header">

            <div>

                <div class="obra-materials-header">

                    <div>
                        <h2>Materiais da obra</h2>

                        <p>
                            Materiais separados do estoque e materiais recebidos diretamente para esta obra.
                        </p>
                    </div>

                    <?php if ($obra['status'] === 'ativa'): ?>

                        <a
                            href="adicionar_material.php?obra_id=<?= $obraId ?>"
                            class="btn-add-material">
                            <i class="bi bi-plus-lg"></i>
                            Adicionar material
                        </a>

                    <?php endif; ?>

                </div>

            </div>


            <?php if ($obra['status'] === 'ativa'): ?>

                

            <?php endif; ?>

        </div>



        <!-- =========================
             MATERIAIS DO ESTOQUE GERAL
        ========================== -->

        <?php if (!empty($materiaisEstoque)): ?>

            <div class="obra-material-group-title">

                <div>

                    <i class="bi bi-arrow-left-right"></i>

                    <strong>
                        Separados do estoque geral
                    </strong>

                </div>

                <span>
                    <?= count($materiaisEstoque) ?>
                    produto(s)
                </span>

            </div>


            <div class="obra-materials-table">

                <table>

                    <thead>

                        <tr>

                            <th>Produto</th>

                            <th>Entrada</th>

                            <th>Saída</th>

                            <th>Saldo</th>

                            <th>Unidade</th>

                            <th>Origem</th>

                            <th>Ação</th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach (
                            $materiaisEstoque
                            as $material
                        ): ?>


                            <?php

                            $saldo =
                                (float) $material['saldo'];

                            ?>


                            <tr>


                                <!-- PRODUTO -->

                                <td>

                                    <div class="table-product">

                                        <div class="product-icon">

                                            <i class="bi bi-box"></i>

                                        </div>


                                        <div class="movement-product-info">

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $material['nome']
                                                ) ?>

                                            </strong>

                                            <small>

                                                <?= htmlspecialchars(
                                                    $material['codigo']
                                                ) ?>

                                            </small>

                                        </div>

                                    </div>

                                </td>



                                <!-- ENTRADA -->

                                <td class="movement-quantity entrada">

                                    +

                                    <?= formatarQuantidadeObra(
                                        $material['total_entradas'],
                                        $material['unidade']
                                    ) ?>

                                </td>



                                <!-- SAÍDA -->

                                <td class="movement-quantity saida">

                                    -

                                    <?= formatarQuantidadeObra(
                                        $material['total_saidas'],
                                        $material['unidade']
                                    ) ?>

                                </td>



                                <!-- SALDO -->

                                <td>

                                    <strong
                                        class="<?= $saldo <= 0
                                                    ? 'stock-low'
                                                    : '' ?>">

                                        <?= formatarQuantidadeObra(
                                            $saldo,
                                            $material['unidade']
                                        ) ?>

                                    </strong>

                                </td>



                                <!-- UNIDADE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $material['unidade']
                                    ) ?>

                                </td>



                                <!-- ORIGEM -->

                                <td>

                                    <span class="obra-origin-badge stock">

                                        <i class="bi bi-box-seam"></i>

                                        Estoque geral

                                    </span>

                                </td>



                                <!-- AÇÃO -->

                                <td>

                                    <a
                                        href="../produtos/detalhes.php?id=<?= $material['produto_id'] ?>"
                                        class="movement-detail-button"
                                        title="Ver produto">

                                        <i class="bi bi-eye"></i>

                                    </a>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>

                </table>

            </div>

        <?php endif; ?>



        <!-- =========================
     MATERIAIS DIRETOS
========================== -->

        <?php if (!empty($materiaisDiretos)): ?>

            <div class="obra-material-group-title direct">

                <div>

                    <i class="bi bi-truck"></i>

                    <strong>
                        Recebidos diretamente para a obra
                    </strong>

                </div>

                <span>
                    <?= count($materiaisDiretos) ?>
                    item(ns)
                </span>

            </div>


            <div class="obra-materials-table">

                <table>

                    <thead>

                        <tr>

                            <th>Produto</th>

                            <th>Entrada</th>

                            <th>Saída</th>

                            <th>Saldo</th>

                            <th>Detalhes</th>

                            <th>Origem</th>

                            <th>Ações</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach (
                            $materiaisDiretos
                            as $material
                        ): ?>

                            <?php

                            $unidade =
                                strtolower(
                                    trim(
                                        $material['unidade']
                                    )
                                );

                            $embalagem =
                                $unidade === 'pacote' ||
                                $unidade === 'caixa';

                            $vidro =
                                $unidade === 'vidro';

                            $saldoDireto =
                                (float) $material['saldo'];

                            ?>


                            <tr>


                                <!-- PRODUTO -->

                                <td>

                                    <div class="table-product">

                                        <div class="product-icon">

                                            <?php if ($vidro): ?>
                                            <i class="bi bi-grid-3x3"></i>
                                        <?php else: ?>
                                            <i class="bi bi-box"></i>
                                        <?php endif; ?>

                                        </div>


                                        <div class="movement-product-info">

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $material['nome']
                                                ) ?>

                                            </strong>


                                            <small>

                                                <?= htmlspecialchars(
                                                    $material['codigo']
                                                        ?: 'Sem código'
                                                ) ?>

                                            </small>


                                            <?php if ($vidro): ?>

                                                <small class="glass-material-subtitle">
                                                    <?= htmlspecialchars(
                                                        $material['vidro_tipo']
                                                            ?: 'Vidro'
                                                    ) ?>

                                                    <?php if ($material['vidro_espessura'] !== null): ?>
                                                        • <?= number_format(
                                                            (float) $material['vidro_espessura'],
                                                            0,
                                                            ',',
                                                            '.'
                                                        ) ?> mm
                                                    <?php endif; ?>
                                                </small>

                                                <?php if ($material['vidro_descricao']): ?>
                                                    <small class="glass-material-description">
                                                        <?= htmlspecialchars(
                                                            $material['vidro_descricao']
                                                        ) ?>
                                                    </small>
                                                <?php endif; ?>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                </td>


                                <!-- ENTRADA -->

                                <td class="movement-quantity entrada">

                                    +

                                    <?= formatarQuantidadeObra(
                                        $material['total_entradas'],
                                        ($embalagem || $vidro)
                                            ? 'un'
                                            : $material['unidade']
                                    ) ?>

                                    <?php if ($vidro): ?>

                                        peça(s)

                                    <?php elseif ($embalagem): ?>

                                        un

                                    <?php else: ?>

                                        <?= htmlspecialchars(
                                            $material['unidade']
                                        ) ?>

                                    <?php endif; ?>

                                </td>


                                <!-- SAÍDA -->

                                <td class="movement-quantity saida">

                                    -

                                    <?= formatarQuantidadeObra(
                                        $material['total_saidas'],
                                        ($embalagem || $vidro)
                                            ? 'un'
                                            : $material['unidade']
                                    ) ?>

                                    <?php if ($vidro): ?>

                                        peça(s)

                                    <?php elseif ($embalagem): ?>

                                        un

                                    <?php else: ?>

                                        <?= htmlspecialchars(
                                            $material['unidade']
                                        ) ?>

                                    <?php endif; ?>

                                </td>


                                <!-- SALDO -->

                                <td>

                                    <strong
                                        class="<?= $saldoDireto <= 0
                                                    ? 'stock-low'
                                                    : '' ?>">

                                        <?= formatarQuantidadeObra(
                                            $saldoDireto,
                                            ($embalagem || $vidro)
                                                ? 'un'
                                                : $material['unidade']
                                        ) ?>

                                        <?php if ($vidro): ?>

                                            peça(s)

                                        <?php elseif ($embalagem): ?>

                                            un

                                        <?php else: ?>

                                            <?= htmlspecialchars(
                                                $material['unidade']
                                            ) ?>

                                        <?php endif; ?>

                                    </strong>

                                </td>


                                <!-- EMBALAGEM -->

                                <td>

                                    <?php if ($vidro): ?>

                                        <?php
                                        $areaUnitariaVidro =
                                            (float) ($material['vidro_area_unitaria'] ?? 0);

                                        $areaSaldoVidro =
                                            $areaUnitariaVidro * $saldoDireto;
                                        ?>

                                        <div class="glass-material-details">

                                            <div class="glass-material-measure">
                                                <i class="bi bi-arrows-angle-expand"></i>

                                                <strong>
                                                    <?= number_format(
                                                        (float) $material['vidro_largura'],
                                                        0,
                                                        ',',
                                                        '.'
                                                    ) ?>
                                                    ×
                                                    <?= number_format(
                                                        (float) $material['vidro_altura'],
                                                        0,
                                                        ',',
                                                        '.'
                                                    ) ?> mm
                                                </strong>
                                            </div>

                                            <small>
                                                Saldo:
                                                <strong>
                                                    <?= number_format(
                                                        $saldoDireto,
                                                        0,
                                                        ',',
                                                        '.'
                                                    ) ?> peça(s)
                                                </strong>
                                                •
                                                <strong>
                                                    <?= number_format(
                                                        $areaSaldoVidro,
                                                        2,
                                                        ',',
                                                        '.'
                                                    ) ?> m²
                                                </strong>
                                            </small>

                                        </div>

                                    <?php elseif ($embalagem): ?>

                                        <div class="movement-product-info">

                                            <strong>

                                                <?= formatarQuantidadeObra(
                                                    $material['quantidade'],
                                                    $material['unidade']
                                                ) ?>

                                                <?= htmlspecialchars(
                                                    $material['unidade']
                                                ) ?>(s)

                                            </strong>

                                            <small>

                                                <?= number_format(
                                                    $material['quantidade_por_embalagem'],
                                                    0,
                                                    ',',
                                                    '.'
                                                ) ?>

                                                itens por embalagem

                                            </small>

                                        </div>

                                    <?php else: ?>

                                        <span class="stock-zero">
                                            -
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- ORIGEM -->

                                <td>

                                    <span class="obra-origin-badge direct">

                                        <i class="bi bi-truck"></i>

                                        Entrada direta

                                    </span>

                                </td>


                                <!-- AÇÕES -->

                                <td>

                                    <div
                                        style="
                                    display:flex;
                                    align-items:center;
                                    gap:7px;
                                ">

                                        <!-- EDITAR -->

                                        <?php if (
                                            $obra['status'] === 'ativa'
                                        ): ?>

                                            <a
                                                href="editar_material.php?id=<?= $material['id'] ?>"
                                                class="movement-detail-button"
                                                title="Editar material">

                                                <i class="bi bi-pencil"></i>

                                            </a>


                                            <!-- DAR SAÍDA -->

                                            <?php if (
                                                $saldoDireto > 0
                                            ): ?>

                                                <a
                                                    href="saida_material.php?id=<?= $material['id'] ?>"
                                                    class="movement-detail-button"
                                                    title="Dar saída">

                                                    <i class="bi bi-box-arrow-up"></i>

                                                </a>

                                            <?php else: ?>

                                                <span
                                                    class="movement-detail-button"
                                                    title="Material sem saldo"
                                                    style="
                                                opacity:.35;
                                                cursor:not-allowed;
                                            ">

                                                    <i class="bi bi-box-arrow-up"></i>

                                                </span>

                                            <?php endif; ?>


                                            <!-- EXCLUIR -->

                                            <form
                                                action="excluir_material.php"
                                                method="POST"
                                                style="margin:0;"
                                                class="delete-material-form">

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= $material['id'] ?>">

                                                <button
                                                    type="button"
                                                    class="movement-detail-button material-delete-button open-delete-modal"
                                                    title="Excluir material"
                                                    data-material-id="<?= $material['id'] ?>"
                                                    data-material-name="<?= htmlspecialchars(
                                                                            $material['nome'],
                                                                            ENT_QUOTES
                                                                        ) ?>">

                                                    <i class="bi bi-trash"></i>

                                                </button>

                                            </form>

                                        <?php else: ?>

                                            <span class="stock-zero">
                                                -
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </td>


                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

        <?php if (
            isset($_GET['material']) &&
            $_GET['material'] === 'editado'
        ): ?>

            <div class="alert-success">

                <i class="bi bi-check-circle"></i>

                Material atualizado com sucesso!

            </div>

        <?php endif; ?>


        <?php if (
            isset($_GET['material']) &&
            $_GET['material'] === 'saida'
        ): ?>

            <div class="alert-success">

                <i class="bi bi-check-circle"></i>

                Saída do material registrada com sucesso!

            </div>

        <?php endif; ?>


        <?php if (
            isset($_GET['material']) &&
            $_GET['material'] === 'excluido'
        ): ?>

            <div class="alert-success">

                <i class="bi bi-check-circle"></i>

                Material excluído da obra com sucesso!

            </div>

        <?php endif; ?>


        <?php if (
            isset($_GET['material']) &&
            $_GET['material'] === 'erro_excluir'
        ): ?>

            <div class="alert-error">

                <i class="bi bi-exclamation-circle"></i>

                Não foi possível excluir o material.

            </div>

        <?php endif; ?>



        <!-- =========================
             NENHUM MATERIAL
        ========================== -->

        <?php if (
            empty($materiaisEstoque) &&
            empty($materiaisDiretos)
        ): ?>

            <div class="empty-table">

                <i class="bi bi-box"></i>

                Nenhum material cadastrado para esta obra.

            </div>

        <?php endif; ?>


    </section>


    <!-- =========================
     MODAL EXCLUIR MATERIAL
========================= -->

    <div
        class="delete-modal-overlay"
        id="deleteMaterialModal">

        <div class="delete-modal">

            <button
                type="button"
                class="delete-modal-close"
                id="closeDeleteModal">
                <i class="bi bi-x-lg"></i>
            </button>


            <div class="delete-modal-icon">

                <i class="bi bi-trash3"></i>

            </div>


            <h2>
                Excluir material da obra?
            </h2>


            <p class="delete-modal-description">

                Deseja realmente excluir

                <strong id="deleteMaterialName">
                    este material
                </strong>

                da obra?

                <br>

                Todo o histórico de entradas e saídas deste item
                também será excluído.

            </p>


            <div class="delete-modal-warning">

                <i class="bi bi-exclamation-triangle"></i>

                <div>

                    <strong>
                        Atenção
                    </strong>

                    <span>
                        Esta ação não pode ser desfeita.
                    </span>

                </div>

            </div>


            <div class="delete-modal-actions">

                <button
                    type="button"
                    class="btn-secondary"
                    id="cancelDeleteModal">
                    Cancelar
                </button>


                <button
                    type="button"
                    class="btn-delete-confirm"
                    id="confirmDeleteMaterial">

                    <i class="bi bi-trash3"></i>

                    Sim, excluir material

                </button>

            </div>

        </div>

    </div>

</main>

<script>
    document.addEventListener(
        'DOMContentLoaded',
        function() {

            const modal =
                document.getElementById(
                    'deleteMaterialModal'
                );

            const materialName =
                document.getElementById(
                    'deleteMaterialName'
                );

            const confirmButton =
                document.getElementById(
                    'confirmDeleteMaterial'
                );

            const cancelButton =
                document.getElementById(
                    'cancelDeleteModal'
                );

            const closeButton =
                document.getElementById(
                    'closeDeleteModal'
                );

            const openButtons =
                document.querySelectorAll(
                    '.open-delete-modal'
                );


            let formToSubmit = null;


            /*
            |--------------------------------------------------------------------------
            | ABRIR MODAL
            |--------------------------------------------------------------------------
            */

            openButtons.forEach(
                function(button) {

                    button.addEventListener(
                        'click',
                        function() {

                            formToSubmit =
                                this.closest(
                                    '.delete-material-form'
                                );

                            const nome =
                                this.dataset.materialName;

                            materialName.textContent =
                                nome || 'este material';

                            modal.classList.add(
                                'active'
                            );

                            document.body.style.overflow =
                                'hidden';

                        }
                    );

                }
            );


            /*
            |--------------------------------------------------------------------------
            | FECHAR
            |--------------------------------------------------------------------------
            */

            function fecharModal() {

                modal.classList.remove(
                    'active'
                );

                document.body.style.overflow =
                    '';

                formToSubmit = null;
            }


            cancelButton.addEventListener(
                'click',
                fecharModal
            );

            closeButton.addEventListener(
                'click',
                fecharModal
            );


            /*
            |--------------------------------------------------------------------------
            | CLICAR FORA
            |--------------------------------------------------------------------------
            */

            modal.addEventListener(
                'click',
                function(event) {

                    if (event.target === modal) {
                        fecharModal();
                    }

                }
            );


            /*
            |--------------------------------------------------------------------------
            | ESC
            |--------------------------------------------------------------------------
            */

            document.addEventListener(
                'keydown',
                function(event) {

                    if (
                        event.key === 'Escape' &&
                        modal.classList.contains(
                            'active'
                        )
                    ) {

                        fecharModal();
                    }

                }
            );


            /*
            |--------------------------------------------------------------------------
            | CONFIRMAR EXCLUSÃO
            |--------------------------------------------------------------------------
            */

            confirmButton.addEventListener(
                'click',
                function() {

                    if (!formToSubmit) {
                        return;
                    }

                    confirmButton.disabled =
                        true;

                    confirmButton.innerHTML =
                        '<i class="bi bi-hourglass-split"></i> Excluindo...';

                    formToSubmit.submit();

                }
            );

        }
    );
</script>


<?php include '../../Includes/footer.php'; ?>