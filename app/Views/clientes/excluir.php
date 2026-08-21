<?php

require_once '../../Config/database.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: index.php');
    exit;
}


$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);


if (!$id) {

    header('Location: index.php');
    exit;
}


try {

    /*
    |--------------------------------------------------------------------------
    | VERIFICAR OBRAS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM obras
        WHERE cliente_id = ?
    ");

    $stmt->execute([$id]);

    $totalObras =
        (int) $stmt->fetchColumn();


    if ($totalObras > 0) {

        header(
            'Location: index.php?erro=possui_obras'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | EXCLUIR CLIENTE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        DELETE FROM clientes
        WHERE id = ?
    ");

    $stmt->execute([$id]);


    header(
        'Location: index.php?excluido=1'
    );

    exit;


} catch (PDOException $e) {

    header(
        'Location: index.php?erro=excluir'
    );

    exit;
}