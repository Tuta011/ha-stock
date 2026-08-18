<?php

require_once '../../Config/database.php';


/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

$busca = trim($_GET['busca'] ?? '');
$categoriaId = $_GET['categoria'] ?? '';
$status = $_GET['status'] ?? '';


/*
|--------------------------------------------------------------------------
| BUSCAR CATEGORIAS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT id, nome
    FROM categorias
    ORDER BY nome
");

$categorias = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| BUSCAR PRODUTOS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.id,
        p.codigo,
        p.nome,
        p.unidade,
        p.estoque_minimo,
        p.localizacao,
        c.nome AS categoria,

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

    FROM produtos p

    LEFT JOIN categorias c
        ON c.id = p.categoria_id

    LEFT JOIN movimentacoes m
        ON m.produto_id = p.id

    WHERE p.ativo = 1
";


/*
|--------------------------------------------------------------------------
| PARÂMETROS
|--------------------------------------------------------------------------
*/

$parametros = [];


/* PESQUISA POR NOME OU CÓDIGO */

if ($busca !== '') {

    $sql .= "
        AND (
            p.nome LIKE :busca
            OR p.codigo LIKE :busca
        )
    ";

    $parametros['busca'] = '%' . $busca . '%';
}


/* FILTRO POR CATEGORIA */

if ($categoriaId !== '') {

    $sql .= "
        AND p.categoria_id = :categoria
    ";

    $parametros['categoria'] = $categoriaId;
}


/*
|--------------------------------------------------------------------------
| AGRUPAMENTO
|--------------------------------------------------------------------------
*/

$sql .= "
    GROUP BY
        p.id,
        p.codigo,
        p.nome,
        p.unidade,
        p.estoque_minimo,
        p.localizacao,
        c.nome
";


/*
|--------------------------------------------------------------------------
| FILTRO DE ESTOQUE
|--------------------------------------------------------------------------
*/

if ($status === 'baixo') {

    $sql .= "
        HAVING saldo <= p.estoque_minimo
    ";
} elseif ($status === 'normal') {

    $sql .= "
        HAVING saldo > p.estoque_minimo
    ";
}


/*
|--------------------------------------------------------------------------
| ORDENAÇÃO
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY p.nome ASC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($parametros);

$produtos = $stmt->fetchAll();


include '../../Includes/header.php';
include '../../Includes/sidebar.php';

?>

<main class="content">

    <!-- MENSAGENS -->

    <?php if (isset($_GET['editado'])): ?>

        <div class="alert-success">
            <i class="bi bi-check-circle"></i>
            Produto atualizado com sucesso!
        </div>

    <?php endif; ?>


    <?php if (isset($_GET['desativado'])): ?>

        <div class="alert-success">
            <i class="bi bi-check-circle"></i>
            Produto desativado com sucesso!
        </div>

    <?php endif; ?>


    <!-- =========================
         CABEÇALHO
    ========================== -->

    <div class="page-header products-header">

        <div>
            <h1>Produtos</h1>
            <p>Gerencie os materiais do almoxarifado.</p>
        </div>

        <a href="novo.php" class="btn-primary">
            <i class="bi bi-plus-lg"></i>
            Novo produto
        </a>

    </div>


    <!-- =========================
         FILTROS
    ========================== -->

    <form
        method="GET"
        action="index.php"
        class="products-filters">

        <!-- PESQUISA -->

        <div class="product-search">

            <i class="bi bi-search"></i>

            <input
                type="text"
                name="busca"
                placeholder="Pesquisar produto..."
                value="<?= htmlspecialchars($busca) ?>">

        </div>


        <!-- CATEGORIA -->

        <select name="categoria">

            <option value="">
                Todas as categorias
            </option>

            <?php foreach ($categorias as $categoria): ?>

                <option
                    value="<?= $categoria['id'] ?>"
                    <?= $categoriaId == $categoria['id']
                        ? 'selected'
                        : '' ?>>
                    <?= htmlspecialchars($categoria['nome']) ?>
                </option>

            <?php endforeach; ?>

        </select>


        <!-- STATUS DO ESTOQUE -->

        <select name="status">

            <option value="">
                Todos os estoques
            </option>

            <option
                value="normal"
                <?= $status === 'normal' ? 'selected' : '' ?>>
                Estoque normal
            </option>

            <option
                value="baixo"
                <?= $status === 'baixo' ? 'selected' : '' ?>>
                Estoque baixo
            </option>

        </select>


        <!-- BOTÃO FILTRAR -->

        <button
            type="submit"
            class="filter-button">
            <i class="bi bi-funnel"></i>
            Filtrar
        </button>


        <!-- BOTÃO LIMPAR -->

        <?php if (
            $busca !== '' ||
            $categoriaId !== '' ||
            $status !== ''
        ): ?>

            <a
                href="index.php"
                class="clear-filter">
                <i class="bi bi-x-lg"></i>
                Limpar
            </a>

        <?php endif; ?>

    </form>


    <!-- =========================
         TABELA
    ========================== -->

    <section class="products-table">

        <table>

            <thead>

                <tr>
                    <th>Código</th>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th>Saldo</th>
                    <th>Unidade</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>

            </thead>


            <tbody>


                <?php if (empty($produtos)): ?>


                    <!-- NENHUM PRODUTO -->

                    <tr>

                        <td
                            colspan="7"
                            class="empty-products">

                            <i class="bi bi-search"></i>

                            Nenhum produto encontrado.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($produtos as $produto): ?>


                        <?php

                        $saldo = (float) $produto['saldo'];

                        $estoqueMinimo =
                            (float) $produto['estoque_minimo'];

                        $estoqueBaixo =
                            $saldo <= $estoqueMinimo;

                        ?>


                        <tr>


                            <!-- CÓDIGO -->

                            <td class="product-code">

                                <?= htmlspecialchars(
                                    $produto['codigo']
                                ) ?>

                            </td>


                            <!-- PRODUTO -->

                            <td>

                                <div class="table-product">

                                    <div class="product-icon">

                                        <i class="bi bi-box"></i>

                                    </div>


                                    <span>

                                        <?= htmlspecialchars(
                                            $produto['nome']
                                        ) ?>

                                    </span>

                                </div>

                            </td>


                            <!-- CATEGORIA -->

                            <td>

                                <?= htmlspecialchars(
                                    $produto['categoria'] ?? '-'
                                ) ?>

                            </td>


                            <!-- SALDO -->

                            <td
                                class="
                                stock-value
                                <?= $estoqueBaixo
                                    ? 'stock-low'
                                    : '' ?>
                            ">

                                <?= number_format(
                                    $saldo,
                                    2,
                                    ',',
                                    '.'
                                ) ?>

                            </td>


                            <!-- UNIDADE -->

                            <td>

                                <?= htmlspecialchars(
                                    $produto['unidade']
                                ) ?>

                            </td>


                            <!-- STATUS -->

                            <td>


                                <?php if ($estoqueBaixo): ?>


                                    <span
                                        class="
                                        stock-status
                                        status-low
                                    ">

                                        Estoque baixo

                                    </span>


                                <?php else: ?>


                                    <span
                                        class="
                                        stock-status
                                        status-ok
                                    ">

                                        Estoque normal

                                    </span>


                                <?php endif; ?>


                            </td>


                            <!-- AÇÕES -->

                            <td>

                                <div class="product-actions">

                                    <!-- VISUALIZAR -->

                                    <a
                                        href="detalhes.php?id=<?= $produto['id'] ?>"
                                        class="action-link view"
                                        title="Ver detalhes">
                                        <i class="bi bi-eye"></i>
                                    </a>


                                    <!-- EDITAR -->

                                    <a
                                        href="editar.php?id=<?= $produto['id'] ?>"
                                        class="action-link edit"
                                        title="Editar produto">
                                        <i class="bi bi-pencil"></i>
                                    </a>


                                    <!-- DESATIVAR -->

                                    <a
                                        href="desativar.php?id=<?= $produto['id'] ?>"
                                        class="action-link delete"
                                        title="Desativar produto"
                                        onclick="return confirm('Deseja realmente desativar este produto?');">
                                        <i class="bi bi-eye-slash"></i>
                                    </a>

                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


            </tbody>

        </table>

    </section>


</main>


<?php include '../../Includes/footer.php'; ?>