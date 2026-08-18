<?php

/*
|--------------------------------------------------------------------------
| ALERTAS DE ESTOQUE
|--------------------------------------------------------------------------
*/

$quantidadeAlertas = 0;
$alertasEstoque = [];


/*
|--------------------------------------------------------------------------
| BUSCAR ALERTAS
|--------------------------------------------------------------------------
*/

if (isset($pdo)) {

    /*
    |--------------------------------------------------------------------------
    | TOTAL REAL DE PRODUTOS COM ESTOQUE BAIXO
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM (

            SELECT
                p.id,
                p.estoque_minimo,

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

            LEFT JOIN movimentacoes m
                ON m.produto_id = p.id

            WHERE p.ativo = 1

            GROUP BY
                p.id,
                p.estoque_minimo

            HAVING saldo <= p.estoque_minimo

        ) AS alertas
    ");

    $quantidadeAlertas = (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | 5 PRODUTOS MAIS CRÍTICOS PARA O DROPDOWN
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            p.id,
            p.codigo,
            p.nome,
            p.unidade,
            p.estoque_minimo,

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

        LEFT JOIN movimentacoes m
            ON m.produto_id = p.id

        WHERE p.ativo = 1

        GROUP BY
            p.id,
            p.codigo,
            p.nome,
            p.unidade,
            p.estoque_minimo

        HAVING saldo <= p.estoque_minimo

        ORDER BY
            CASE
                WHEN p.estoque_minimo > 0
                    THEN saldo / p.estoque_minimo
                ELSE 999
            END ASC,

            saldo ASC,
            p.nome ASC

        LIMIT 5
    ");

    $alertasEstoque = $stmt->fetchAll();
}

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>HA Stock</title>


    <!-- =========================
         GOOGLE FONTS
    ========================== -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <!-- =========================
         BOOTSTRAP ICONS
    ========================== -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- =========================
         CSS
    ========================== -->

    <link
        rel="stylesheet"
        href="/ha-stock/public/assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="/ha-stock/public/assets/css/produto-form.css"
    >

</head>


<body>


<!-- =========================
     TOPBAR
========================== -->

<header class="topbar">


    <!-- =========================
         PESQUISA
    ========================== -->

    <div class="search-box">

        <i class="bi bi-search"></i>

        <input
            type="text"
            placeholder="Pesquisar produto..."
        >

    </div>


    <!-- =========================
         AÇÕES DO HEADER
    ========================== -->

    <div class="topbar-actions">


        <!-- =========================
             NOTIFICAÇÕES
        ========================== -->

        <div class="notification-wrapper">


            <!-- SINO -->

            <button
                type="button"
                class="notification-button"
                id="notificationButton"
                title="Alertas de estoque"
                aria-label="Abrir alertas de estoque"
            >

                <i class="bi bi-bell"></i>


                <?php if ($quantidadeAlertas > 0): ?>

                    <span class="notification-badge">

                        <?= $quantidadeAlertas ?>

                    </span>

                <?php endif; ?>


            </button>


            <!-- =========================
                 DROPDOWN
            ========================== -->

            <div
                class="notification-dropdown"
                id="notificationDropdown"
            >


                <!-- CABEÇALHO -->

                <div class="notification-dropdown-header">

                    <strong>
                        Alertas de estoque
                    </strong>

                    <span>

                        <?php if ($quantidadeAlertas === 1): ?>

                            1 produto precisa de atenção

                        <?php else: ?>

                            <?= $quantidadeAlertas ?>
                            produtos precisam de atenção

                        <?php endif; ?>

                    </span>

                </div>


                <!-- =========================
                     LISTA
                ========================== -->

                <div class="notification-list">


                    <?php if (empty($alertasEstoque)): ?>


                        <!-- SEM ALERTAS -->

                        <div class="notification-empty">

                            <i class="bi bi-check-circle"></i>

                            <span>
                                Nenhum produto com estoque baixo.
                            </span>

                        </div>


                    <?php else: ?>


                        <?php foreach ($alertasEstoque as $alerta): ?>


                            <?php

                            $saldoAlerta =
                                (float) $alerta['saldo'];

                            $minimoAlerta =
                                (float) $alerta['estoque_minimo'];


                            /*
                            |--------------------------------------------------------------------------
                            | PRODUTO CRÍTICO
                            |--------------------------------------------------------------------------
                            |
                            | Crítico quando o saldo estiver em 50%
                            | ou menos do estoque mínimo.
                            |
                            */

                            $critico =
                                $minimoAlerta > 0 &&
                                $saldoAlerta <=
                                ($minimoAlerta * 0.5);

                            ?>


                            <a
                                href="/ha-stock/app/Views/produtos/detalhes.php?id=<?= $alerta['id'] ?>"
                                class="notification-item"
                            >


                                <!-- ÍCONE -->

                                <div
                                    class="
                                        notification-item-icon
                                        <?= $critico
                                            ? 'critical'
                                            : '' ?>
                                    "
                                >


                                    <?php if ($critico): ?>

                                        <i class="bi bi-exclamation-octagon"></i>

                                    <?php else: ?>

                                        <i class="bi bi-exclamation-triangle"></i>

                                    <?php endif; ?>


                                </div>


                                <!-- INFORMAÇÕES -->

                                <div class="notification-item-info">

                                    <strong>

                                        <?= htmlspecialchars(
                                            $alerta['nome']
                                        ) ?>

                                    </strong>


                                    <span>

                                        <?= htmlspecialchars(
                                            $alerta['codigo']
                                        ) ?>

                                        &bull;

                                        Saldo:

                                        <?= number_format(
                                            $saldoAlerta,
                                            2,
                                            ',',
                                            '.'
                                        ) ?>

                                        <?= htmlspecialchars(
                                            $alerta['unidade']
                                        ) ?>

                                    </span>


                                    <small>

                                        Mínimo:

                                        <?= number_format(
                                            $minimoAlerta,
                                            2,
                                            ',',
                                            '.'
                                        ) ?>

                                        <?= htmlspecialchars(
                                            $alerta['unidade']
                                        ) ?>

                                        <?php if ($critico): ?>

                                            &bull; Crítico

                                        <?php else: ?>

                                            &bull; Estoque baixo

                                        <?php endif; ?>

                                    </small>

                                </div>


                                <!-- SETA -->

                                <i class="bi bi-chevron-right"></i>


                            </a>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>


                <!-- =========================
                     RODAPÉ
                ========================== -->

                <?php if ($quantidadeAlertas > 0): ?>

                    <div class="notification-dropdown-footer">

                        <a
                            href="/ha-stock/app/Views/produtos/index.php?status=baixo"
                        >

                            Ver todos os produtos com estoque baixo

                            <i class="bi bi-arrow-right"></i>

                        </a>

                    </div>

                <?php endif; ?>


            </div>


        </div>


        <!-- =========================
             USUÁRIO
        ========================== -->

        <div class="user-profile">


            <div class="user-avatar">
                S
            </div>


            <div class="user-info">

                <strong>
                    Silas
                </strong>

                <span>
                    Almoxarifado
                </span>

            </div>


            <i class="bi bi-chevron-down"></i>


        </div>


    </div>


</header>


<!-- =========================
     JAVASCRIPT
     NOTIFICAÇÕES
========================== -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const button =
        document.getElementById('notificationButton');

    const dropdown =
        document.getElementById('notificationDropdown');


    /*
    |--------------------------------------------------------------------------
    | VERIFICAR ELEMENTOS
    |--------------------------------------------------------------------------
    */

    if (!button || !dropdown) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | ABRIR / FECHAR DROPDOWN
    |--------------------------------------------------------------------------
    */

    button.addEventListener('click', function (event) {

        event.preventDefault();

        event.stopPropagation();

        dropdown.classList.toggle('active');

    });


    /*
    |--------------------------------------------------------------------------
    | NÃO FECHAR AO CLICAR DENTRO
    |--------------------------------------------------------------------------
    */

    dropdown.addEventListener('click', function (event) {

        event.stopPropagation();

    });


    /*
    |--------------------------------------------------------------------------
    | FECHAR CLICANDO FORA
    |--------------------------------------------------------------------------
    */

    document.addEventListener('click', function () {

        dropdown.classList.remove('active');

    });


    /*
    |--------------------------------------------------------------------------
    | FECHAR COM ESC
    |--------------------------------------------------------------------------
    */

    document.addEventListener('keydown', function (event) {

        if (event.key === 'Escape') {

            dropdown.classList.remove('active');

        }

    });

});

</script>