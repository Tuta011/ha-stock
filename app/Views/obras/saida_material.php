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
| BUSCAR MATERIAL
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        mo.*,

        o.nome AS obra_nome,
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

$stmt->execute([$materialId]);

$material = $stmt->fetch();

if (!$material) {
    die('Material não encontrado.');
}


if ($material['obra_status'] !== 'ativa') {
    die('Não é possível retirar materiais de uma obra inativa.');
}


/*
|--------------------------------------------------------------------------
| BUSCAR ENTRADAS E SAÍDAS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        COALESCE(
            SUM(
                CASE
                    WHEN tipo = 'entrada'
                        THEN quantidade
                    ELSE 0
                END
            ),
            0
        ) AS entradas,

        COALESCE(
            SUM(
                CASE
                    WHEN tipo = 'saida'
                        THEN quantidade
                    ELSE 0
                END
            ),
            0
        ) AS saidas

    FROM movimentacoes_materiais_obra

    WHERE material_obra_id = ?
");

$stmt->execute([$materialId]);

$movimentacoes = $stmt->fetch();

$totalEntradas =
    (float) $movimentacoes['entradas'];

$totalSaidas =
    (float) $movimentacoes['saidas'];

$saldoAtual =
    $totalEntradas - $totalSaidas;


/*
|--------------------------------------------------------------------------
| VARIÁVEIS
|--------------------------------------------------------------------------
*/

$erro = '';


/*
|--------------------------------------------------------------------------
| PROCESSAR SAÍDA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $quantidade =
        $_POST['quantidade'] ?? '';

    $data =
        $_POST['data'] ?? '';

    $observacao =
        trim($_POST['observacao'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | VALIDAÇÕES
    |--------------------------------------------------------------------------
    */

    if (
        $quantidade === '' ||
        $data === ''
    ) {

        $erro =
            'Preencha todos os campos obrigatórios.';

    } elseif (
        !is_numeric($quantidade) ||
        (float) $quantidade <= 0
    ) {

        $erro =
            'Informe uma quantidade válida.';

    } else {

        try {

            $quantidadeSaida =
                (float) $quantidade;


            /*
            |--------------------------------------------------------------------------
            | UNIDADES INTEIRAS
            |--------------------------------------------------------------------------
            |
            | Materiais em pacote/caixa são controlados pelo total de itens.
            | Por isso a saída também será em itens.
            |
            */

            if (
                in_array(
                    strtolower($material['unidade']),
                    ['un', 'pacote', 'caixa'],
                    true
                ) &&
                floor($quantidadeSaida)
                    != $quantidadeSaida
            ) {

                throw new Exception(
                    'Este material aceita somente quantidades inteiras.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | BLOQUEAR SAÍDA MAIOR QUE SALDO
            |--------------------------------------------------------------------------
            */

            if ($quantidadeSaida > $saldoAtual) {

                throw new Exception(
                    'Quantidade maior que o saldo disponível. Saldo atual: ' .
                    number_format(
                        $saldoAtual,
                        2,
                        ',',
                        '.'
                    )
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
            | REGISTRAR SAÍDA
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
                    'saida',
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $materialId,
                $quantidadeSaida,
                $observacao ?: null,
                $dataBanco
            ]);


            /*
            |--------------------------------------------------------------------------
            | REDIRECIONAR
            |--------------------------------------------------------------------------
            */

            header(
                'Location: detalhes.php?id=' .
                urlencode($material['obra_id']) .
                '&material=saida'
            );

            exit;

        } catch (PDOException $e) {

            $erro =
                'Erro ao registrar saída do material.';

        } catch (Exception $e) {

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

    <div class="page-header">

        <div>

            <h1>
                Dar saída
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


    <?php if ($erro): ?>

        <div class="alert-error">

            <i class="bi bi-exclamation-circle"></i>

            <?= htmlspecialchars($erro) ?>

        </div>

    <?php endif; ?>


    <!-- MATERIAL -->

    <div class="form-card">

        <div
            style="
                display:flex;
                justify-content:space-between;
                gap:20px;
                margin-bottom:25px;
            "
        >

            <div>

                <span
                    style="
                        display:block;
                        font-size:12px;
                        color:#8b93a1;
                        margin-bottom:5px;
                    "
                >
                    Material
                </span>

                <strong
                    style="
                        font-size:18px;
                    "
                >
                    <?= htmlspecialchars(
                        $material['nome']
                    ) ?>
                </strong>

                <div
                    style="
                        margin-top:4px;
                        font-size:12px;
                        color:#8b93a1;
                    "
                >

                    <?= htmlspecialchars(
                        $material['codigo']
                        ?: 'Sem código'
                    ) ?>

                </div>

            </div>


            <div>

                <span
                    style="
                        display:block;
                        font-size:12px;
                        color:#8b93a1;
                        margin-bottom:5px;
                    "
                >
                    Saldo disponível
                </span>

                <strong
                    style="
                        font-size:24px;
                    "
                >

                    <?= number_format(
                        $saldoAtual,
                        in_array(
                            strtolower(
                                $material['unidade']
                            ),
                            ['un', 'pacote', 'caixa'],
                            true
                        )
                            ? 0
                            : 2,
                        ',',
                        '.'
                    ) ?>

                    <?php if (
                        in_array(
                            strtolower(
                                $material['unidade']
                            ),
                            ['pacote', 'caixa'],
                            true
                        )
                    ): ?>

                        un

                    <?php else: ?>

                        <?= htmlspecialchars(
                            $material['unidade']
                        ) ?>

                    <?php endif; ?>

                </strong>

            </div>

        </div>


        <?php if (
            in_array(
                strtolower(
                    $material['unidade']
                ),
                ['pacote', 'caixa'],
                true
            )
        ): ?>

            <div class="stock-exit-notice">

                <i class="bi bi-info-circle"></i>

                <div>

                    <strong>
                        Saída por unidade
                    </strong>

                    <span>

                        Este material entrou como

                        <?= number_format(
                            $material['quantidade'],
                            0,
                            ',',
                            '.'
                        ) ?>

                        <?= htmlspecialchars(
                            $material['unidade']
                        ) ?>(s)

                        com

                        <?= number_format(
                            $material[
                                'quantidade_por_embalagem'
                            ],
                            0,
                            ',',
                            '.'
                        ) ?>

                        itens por embalagem.

                        As retiradas serão registradas pela quantidade de itens.

                    </span>

                </div>

            </div>

        <?php endif; ?>


        <form method="POST">

            <div class="form-grid">


                <!-- QUANTIDADE -->

                <div class="form-group">

                    <label for="quantidade">

                        Quantidade para retirada *

                    </label>

                    <input
                        type="number"
                        id="quantidade"
                        name="quantidade"
                        min="<?= in_array(
                            strtolower(
                                $material['unidade']
                            ),
                            ['un', 'pacote', 'caixa'],
                            true
                        )
                            ? '1'
                            : '0.01' ?>"
                        step="<?= in_array(
                            strtolower(
                                $material['unidade']
                            ),
                            ['un', 'pacote', 'caixa'],
                            true
                        )
                            ? '1'
                            : '0.01' ?>"
                        max="<?= htmlspecialchars(
                            $saldoAtual
                        ) ?>"
                        placeholder="Ex: 10"
                        value="<?= htmlspecialchars(
                            $_POST['quantidade']
                            ?? ''
                        ) ?>"
                        required
                    >

                </div>


                <!-- DATA -->

                <div class="form-group">

                    <label for="data">

                        Data e horário *

                    </label>

                    <input
                        type="datetime-local"
                        id="data"
                        name="data"
                        value="<?= htmlspecialchars(
                            $_POST['data']
                            ?? date('Y-m-d\TH:i')
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
                        placeholder="Ex: Material utilizado na instalação..."
                    ><?= htmlspecialchars(
                        $_POST['observacao']
                        ?? ''
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
                    class="btn-exit"
                    <?= $saldoAtual <= 0
                        ? 'disabled'
                        : '' ?>
                >

                    <i class="bi bi-arrow-up-circle"></i>

                    Registrar saída

                </button>

            </div>

        </form>

    </div>

</main>


<?php include '../../Includes/footer.php'; ?>