<?php

require_once '../../Config/database.php';


/*
|--------------------------------------------------------------------------
| ACEITAR SOMENTE POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| ID DO MATERIAL
|--------------------------------------------------------------------------
*/

$materialId = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (!$materialId) {

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| BUSCAR MATERIAL
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        mo.id,
        mo.obra_id,
        mo.nome,
        o.status AS obra_status

    FROM materiais_obra mo

    INNER JOIN obras o
        ON o.id = mo.obra_id

    WHERE mo.id = ?

    LIMIT 1
");

$stmt->execute([
    $materialId
]);

$material = $stmt->fetch();


if (!$material) {

    header('Location: index.php');
    exit;
}


$obraId = (int) $material['obra_id'];


/*
|--------------------------------------------------------------------------
| BLOQUEAR EXCLUSÃO EM OBRA INATIVA
|--------------------------------------------------------------------------
*/

if ($material['obra_status'] !== 'ativa') {

    header(
        'Location: detalhes.php?id=' .
        $obraId .
        '&material=erro_excluir'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| EXCLUIR
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | EXCLUIR MATERIAL
    |--------------------------------------------------------------------------
    |
    | As movimentações serão apagadas automaticamente porque criamos
    | movimentacoes_materiais_obra com ON DELETE CASCADE.
    |
    */

    $stmt = $pdo->prepare("
        DELETE FROM materiais_obra
        WHERE id = ?
    ");

    $stmt->execute([
        $materialId
    ]);


    if ($stmt->rowCount() !== 1) {

        throw new Exception(
            'Não foi possível excluir o material.'
        );
    }


    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | SUCESSO
    |--------------------------------------------------------------------------
    */

    header(
        'Location: detalhes.php?id=' .
        $obraId .
        '&material=excluido'
    );

    exit;


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    header(
        'Location: detalhes.php?id=' .
        $obraId .
        '&material=erro_excluir'
    );

    exit;
}