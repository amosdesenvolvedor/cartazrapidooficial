<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
require __DIR__ . '/db.php';
require __DIR__ . '/payments/helpers.php';

// Apenas clientes precisam de assinatura ativa; superadmin tem acesso livre.
$role = $_SESSION['user']['role'] ?? '';
if ($role === 'cliente') {
    $client = ensureClientForUser($pdo, (int)$_SESSION['user']['id'], $_SESSION['user']['name'] ?? '', $_SESSION['user']['email'] ?? '');
    $activeSubscription = getActiveSubscription($pdo, (int)$client['id']);
    if (!$activeSubscription) {
        header('Location: select_plan.php?expired=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Template 04 - Aviso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="icon" type="image/x-icon" href="/public/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --title-size: 46px;
            --desc-size: 34px;
            --border-color: #d81616;
        }
        * { box-sizing: border-box; }
        body { background: #f3f6f8; font-family: 'Montserrat', sans-serif; }
        .sidebar { background: #111927; color: #e9eef7; min-height: 100vh; padding: 22px 18px; box-shadow: 6px 0 22px rgba(0,0,0,0.2); }
        .brand { font-weight: 800; letter-spacing: 0.4px; color: #ffd43b; }
        .control-panel { background: #1f2532; color: #fff; border-radius: 14px; padding: 18px; box-shadow: 0 14px 40px rgba(0,0,0,0.18); }
        .cartaz {
            width: 360px;
            min-height: 560px;
            height: auto;
            background: #fff;
            border: 10px solid var(--border-color);
            border-radius: 12px;
            margin: 0 auto;
            box-shadow: 0 16px 32px rgba(0,0,0,0.16);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 32px 20px;
            text-align: center;
        }
        .cartaz.landscape {
            width: 560px;
            min-height: 360px;
            height: auto;
        }
        .aviso-tipo {
            font-size: var(--title-size);
            font-weight: 800;
            margin-bottom: 12px;
            text-transform: uppercase;
        }
        .aviso-desc {
            font-size: var(--desc-size);
            font-weight: 700;
            line-height: 1.3;
        }
        .canvas-area { display: flex; }
        @page { size: 210mm 297mm; margin: 0 !important; }
        @media print {
            html, body { margin: 0 !important; padding: 0 !important; background: #fff; width: 210mm !important; height: 297mm !important; }
            .no-print { display: none !important; }
            .container-fluid, .row, .col-lg-9, .col-md-8, .col-lg-3, .col-md-4 { margin: 0 !important; padding: 0 !important; width: 100% !important; height: auto !important; }
            .canvas-area { width: 210mm !important; height: 297mm !important; margin: 0 auto !important; padding: 0 !important; display: flex !important; justify-content: center !important; align-items: flex-start !important; }
            .print-area { width: 210mm !important; height: 297mm !important; min-height: 297mm !important; display: flex !important; justify-content: center !important; align-items: flex-start !important; margin: 0 auto !important; padding: 0 !important; }
            .cartaz {
                box-sizing: border-box !important;
                width: 210mm !important;
                max-width: 210mm !important;
                height: auto !important;
                max-height: 297mm !important;
                min-height: 0 !important;
                box-shadow: none !important;
                margin: 0 auto 0 auto !important;
                overflow: hidden !important;
                border: 10px solid var(--border-color) !important;
                padding: 10mm 10mm !important;
                page-break-inside: avoid !important;
            }
            .aviso-tipo { font-size: calc(var(--title-size) * 2) !important; }
            .aviso-desc { font-size: calc(var(--desc-size) * 3) !important; margin-top: 50px !important; }
        }
    </style>
    <style id="print-orientation"></style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-3 col-md-4 sidebar no-print">
            <div class="d-flex align-items-center mb-4">
                <div>
                    <div class="brand">Cartaz Rápido</div>
                    <div class="text-muted small">Menu de templates</div>
                </div>
                <a href="logout.php" class="ms-auto btn btn-sm btn-outline-danger">Sair</a>
            </div>
            <div class="mb-4">
                <div class="small text-uppercase text-muted">Usuário</div>
                <div class="fw-bold"><?= htmlspecialchars($_SESSION['user']['name'] ?? 'Usuário') ?></div>
                <div class="text-muted small">Escolha um modelo</div>
            </div>
             <div class="list-group">
                    <a class="list-group-item list-group-item-action active" aria-current="true" href="index.php">Home</a>
                    <a class="list-group-item list-group-item-action" href="template1.php">Modelo 01 - Clássico</a>
                    <a class="list-group-item list-group-item-action" href="template2.php">Modelo 02 - Curvas</a>
                    <a class="list-group-item list-group-item-action" href="template3.php">Modelo 03 - Curvas Sem Contorno</a>
                    <a class="list-group-item list-group-item-action" href="template4.php">Modelo 04 - Aviso</a>
                    <a class="list-group-item list-group-item-action" href="template5.php">Modelo 05 - Curvas Fundo Personalizado</a>
                     <a class="list-group-item list-group-item-action" href="template6.php">Modelo 06 - Encards Personalizado</a>
                    <?php if (($_SESSION['user']['role'] ?? '') === 'superadmin'): ?>
                        <a class="list-group-item list-group-item-action" href="admin.php">Painel Admin</a>
                    <?php endif; ?>
                </div>
        </div>
        <div class="col-lg-9 col-md-8 py-4 canvas-area">
            <div class="row g-4">
                <div class="col-lg-5 no-print">
                    <div class="control-panel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 fw-bold">Template 04 - Aviso</h5>
                            <button class="btn btn-sm btn-danger" type="button" onclick="window.location.reload()">Limpar</button>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Aviso</label>
                            <select id="avisoTipo" class="form-select text-uppercase fw-bold">
                                <option>Importante</option>
                                <option>Urgente</option>
                                <option>Aviso</option>
                                <option>Atenção</option>
                            </select>
                            <div class="d-flex gap-2 mt-2">
                                <button class="btn btn-light btn-sm" type="button" id="titleMinus">A-</button>
                                <button class="btn btn-light btn-sm" type="button" id="titlePlus">A+</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <textarea id="avisoDesc" class="form-control" rows="4" placeholder="Digite a mensagem do aviso"></textarea>
                            <div class="d-flex gap-2 mt-2">
                                <button class="btn btn-light btn-sm" type="button" id="descMinus">A-</button>
                                <button class="btn btn-light btn-sm" type="button" id="descPlus">A+</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contorno da folha</label>
                            <input type="color" id="borderColor" class="form-control form-control-color" value="#d81616">
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-success" type="button" onclick="window.print()">Imprimir</button>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="print-area d-flex justify-content-center">
                        <div id="cartaz" class="cartaz">
                            <div id="previewTitulo" class="aviso-tipo">Importante</div>
                            <div id="previewDesc" class="aviso-desc">Digite a mensagem do aviso</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    const titulo = document.getElementById('previewTitulo');
    const desc = document.getElementById('previewDesc');
    const selTipo = document.getElementById('avisoTipo');
    const cartaz = document.getElementById('cartaz');
    const borderColor = document.getElementById('borderColor');
    const stylePrint = document.getElementById('print-orientation');

    selTipo.addEventListener('change', () => {
        titulo.textContent = selTipo.value;
    });

    document.getElementById('avisoDesc').addEventListener('input', (e) => {
        desc.textContent = e.target.value || 'Digite a mensagem do aviso';
    });

    let titleSize = 46;
    let descSize = 34;
    const applySizes = () => {
        document.documentElement.style.setProperty('--title-size', titleSize + 'px');
        document.documentElement.style.setProperty('--desc-size', descSize + 'px');
    };
    document.getElementById('titlePlus').onclick = () => { titleSize += 2; applySizes(); };
    document.getElementById('titleMinus').onclick = () => { titleSize = Math.max(16, titleSize - 2); applySizes(); };
    document.getElementById('descPlus').onclick = () => { descSize += 2; applySizes(); };
    document.getElementById('descMinus').onclick = () => { descSize = Math.max(14, descSize - 2); applySizes(); };
    applySizes();

    borderColor.addEventListener('input', () => {
        document.documentElement.style.setProperty('--border-color', borderColor.value);
    });

    // Orientação fixa em landscape no print (@page).
</script>
</body>
</html>
