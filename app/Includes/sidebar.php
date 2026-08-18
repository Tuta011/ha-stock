<?php

$currentUrl = $_SERVER['REQUEST_URI'];

?>

<aside class="sidebar">

    <div class="logo">

        <img
            src="/ha-stock/public/assets/img/logo.png"
            alt="AA House">

        <h2>HA Stock</h2>

    </div>


    <ul>

        <!-- DASHBOARD -->
        <li>
            <a
                href="/ha-stock/app/Views/dashboard/index.php"
                class="<?= strpos($currentUrl, '/dashboard/') !== false ? 'active' : '' ?>">
                <i class="bi bi-grid-fill"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- CLIENTES -->
        <li>
            <a
                href="/ha-stock/app/Views/clientes/index.php"
                class="menu-item <?= str_contains($_SERVER['REQUEST_URI'], '/clientes/') ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Clientes</span>
            </a>
        </li>

        <!-- OBRAS -->
        <li>
            <a
                href="/ha-stock/app/Views/obras/index.php"
                class="menu-item <?= str_contains($_SERVER['REQUEST_URI'], '/obras/') ? 'active' : '' ?>">
                <i class="bi bi-buildings"></i>
                <span>Obras</span>
            </a>
        </li>

        <!-- PRODUTOS -->
        <li>
            <a
                href="/ha-stock/app/Views/produtos/index.php"
                class="<?= strpos($currentUrl, '/produtos/') !== false ? 'active' : '' ?>">
                <i class="bi bi-box"></i>
                <span>Produtos</span>
            </a>
        </li>


        <!-- MOVIMENTAÇÕES -->
        <li>
            <a
                href="/ha-stock/app/Views/movimentacoes/index.php"
                class="<?= strpos($currentUrl, '/movimentacoes/') !== false ? 'active' : '' ?>">
                <i class="bi bi-arrow-left-right"></i>
                <span>Movimentações</span>
            </a>
        </li>


        <!-- CATEGORIAS -->
        <li>
            <a
                href="/ha-stock/app/Views/categorias/index.php"
                class="<?= strpos($currentUrl, '/categorias/') !== false ? 'active' : '' ?>">
                <i class="bi bi-tags"></i>
                <span>Categorias</span>
            </a>
        </li>


        <!-- RELATÓRIOS -->
        <li>
            <a
                href="/ha-stock/app/Views/relatorios/index.php"
                class="menu-item">
                <i class="bi bi-bar-chart"></i>
                <span>Relatórios</span>
            </a>
        </li>

    </ul>

</aside>