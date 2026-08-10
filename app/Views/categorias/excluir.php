<?php

require_once '../../Config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| VERIFICAR SE EXISTEM PRODUTOS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM produtos
    WHERE categoria_id = ?
");

$stmt->execute([$id]);

$totalProdutos = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| BLOQUEAR EXCLUSÃO
|--------------------------------------------------------------------------
*/

if ($totalProdutos > 0) {

    header('Location: index.php?erro=possui_produtos');
    exit;

}


/*
|--------------------------------------------------------------------------
| EXCLUIR CATEGORIA
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    DELETE FROM categorias
    WHERE id = ?
");

$stmt->execute([$id]);


header('Location: index.php?excluido=1');
exit;