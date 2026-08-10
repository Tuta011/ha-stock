<?php

require_once '../../Config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}


$stmt = $pdo->prepare("
    UPDATE produtos
    SET ativo = 0
    WHERE id = ?
");

$stmt->execute([$id]);


header('Location: index.php?desativado=1');
exit;