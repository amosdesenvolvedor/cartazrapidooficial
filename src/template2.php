<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
require __DIR__ . '/db.php';
require __DIR__ . '/payments/helpers.php';

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
    <title>Template 02 - Curvas Sem o Contorno</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background: #f3f6f8; font-family: 'Montserrat', sans-serif; }
        .sidebar { background: #111927; color: #e9eef7; min-height: 100vh; padding: 22px 18px; box-shadow: 6px 0 22px rgba(0,0,0,0.2); }
        .brand { font-weight: 800; letter-spacing: 0.4px; color: #ffd43b; }
        .control-panel { background: #1f2532; color: #fff; border-radius: 14px; padding: 18px; box-shadow: 0 14px 40px rgba(0,0,0,0.18); }
        .cartaz { width: 360px; min-height: 560px; border-radius: 10px; box-shadow: 0 12px 30px rgba(0,0,0,0.14); overflow: hidden; margin: 0 auto; position: relative; background: #ffe700; border: 12px solid #d81616; }
        .cabecalho-curve { background: #d81616; color: #fff; padding: 22px 18px 28px; text-align: center; font-weight: 800; letter-spacing: 0.6px; border-radius: 0 0 60% 60%; font-size: var(--t2-title-size, 32px); }
        .conteudo { padding: 24px 20px 24px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 14px; }
        .descricao { font-size: var(--t2-desc-size, 34px); font-weight: 700; line-height: 1.2; min-height: 100px; display: flex; align-items: center; justify-content: center; color: #111; text-align: center; }
        .preco { font-size: var(--t2-price-size, 150px); font-weight: 800; color: #d81616; line-height: 0.9; display: flex; align-items: flex-start; justify-content: center; gap: 6px; margin-top: 60px; }
        .preco .currency { font-size: 22px; position: relative; top: -12px; font-weight: 700; }
        .preco .inteiro { line-height: 0.9; }
        .preco .cents { vertical-align: top; font-size: 32px; position: relative; top: -8px; }
        .rodape-curve { background: #d81616; color: #fff; padding: 12px 18px 10px; text-align: center; font-weight: 700; border-radius: 60% 60% 0 0; position: absolute; bottom: 0; left: 0; right: 0; border-top: 6px solid #b60000; font-size: var(--t2-rodape-size, 14px); }
        .swatch-label { font-size: 13px; color: #d5d8e2; }
        .btn-font { border-radius: 10px; font-weight: 700; }
        .canvas-area { display: flex; }
        @page { size: 210mm 297mm; margin: 0; }
        @media print {
            html, body { margin: 0; padding: 0; width: 210mm; height: 297mm; background: #fff; }
            .no-print { display: none !important; }
            .canvas-area { width: 210mm; height: 297mm; margin: 0 auto; padding: 0; justify-content: center; align-items: flex-start; }
            .print-area { width: 210mm; height: 297mm; min-height: 297mm; display: flex; justify-content: center; align-items: flex-start; margin: 0 auto; }
            .cartaz {
                width: 210mm !important;
                height: 297mm !important;
                min-height: 297mm !important;
                box-shadow: none;
                margin: 0 auto;
                overflow: hidden;
            }
            .cabecalho-curve { font-size: calc(var(--t2-title-size, 32px) * 2.5) !important; }
            .descricao { font-size: calc(var(--t2-desc-size, 34px) * 2) !important; }
            .preco { font-size: calc(var(--t2-price-size, 150px) * 3) !important; }
            .preco { margin-top: 150px !important; }
            .preco .currency { font-size: calc(var(--t2-price-size, 150px) * 0.15 * 3) !important; top: calc(var(--t2-price-size, 150px) * -0.08 * 3); }
            .preco .cents { font-size: calc(var(--t2-price-size, 150px) * 0.19 * 3) !important; top: calc(var(--t2-price-size, 150px) * -0.05 * 3); }
            .rodape-curve { font-size: calc(var(--t2-rodape-size, 14px) * 2) !important; }
        }
    </style>
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
                <div class="fw-bold">Demo</div>
                <div class="text-muted small">Escolha um modelo</div>
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
        </div>
        <div class="col-lg-9 col-md-8 py-4 canvas-area">
            <div class="row g-4">
                <div class="col-lg-5 no-print">
                    <div class="control-panel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 fw-bold">Template 02 - Curvas</h5>
                            <button class="btn btn-sm btn-danger" type="button" onclick="window.location.reload()">Limpar</button>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Título</label>
                            <div class="input-group">
                                <select id="t2Titulo" class="form-select text-uppercase fw-bold">
                                    <option>OFERTA</option>
                                    <option>PROMOÇÃO</option>
                                    <option>QUEIMA</option>
                                    <option>EXCLUSIVO</option>
                                    <option>SUPER OFERTÃO</option>
                                    <option>AÇOUGUE</option>
                                    <option>HORTIFRUTE</option>
                                    <option>PADARIA</option>
                                    <option>CARNES</option>
                                </select>
                                <button class="btn btn-light btn-font" type="button" id="t2FontMinus">A-</button>
                                <button class="btn btn-light btn-font" type="button" id="t2FontPlus">A+</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <div class="input-group">
                                <textarea id="t2Descricao" class="form-control" rows="2" placeholder="Ex: Produto X 1Kg"></textarea>
                                <button class="btn btn-light btn-font" type="button" id="t2DescMinus">A-</button>
                                <button class="btn btn-light btn-font" type="button" id="t2DescPlus">A+</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Preço</label>
                            <div class="input-group">
                                <input id="t2Preco" class="form-control" placeholder="Ex: 19,90">
                                <button class="btn btn-light btn-font" type="button" id="t2PrecoMinus">A-</button>
                                <button class="btn btn-light btn-font" type="button" id="t2PrecoPlus">A+</button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Rodapé</label>
                            <input id="t2Rodape" class="form-control" placeholder="Ex: Aproveite!">
                        </div>

                        <div class="mb-2 d-flex align-items-center justify-content-between gap-2">
                            <span class="swatch-label">Cores</span>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-success" type="button" onclick="saveColors()">Salvar cores</button>
                                <button class="btn btn-sm btn-outline-warning" type="button" onclick="resetCores()">Resetar</button>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="swatch-label">Texto do Título</label>
                                <input type="color" id="t2CorTitulo" class="form-control form-control-color" value="#ffffff">
                            </div>
                            <div class="col-6">
                                <label class="swatch-label">Fundo do Cartaz</label>
                                <input type="color" id="t2CorFundo" class="form-control form-control-color" value="#ffe700">
                            </div>
                            <div class="col-6">
                                <label class="swatch-label">Curvas/Contorno</label>
                                <input type="color" id="t2CorCurvas" class="form-control form-control-color" value="#d81616">
                            </div>
                            <div class="col-6">
                                <label class="swatch-label">Texto do Rodapé</label>
                                <input type="color" id="t2CorRodapeTexto" class="form-control form-control-color" value="#ffffff">
                            </div>
                            <div class="col-6">
                                <label class="swatch-label">Cor do Preço</label>
                                <input type="color" id="t2CorPreco" class="form-control form-control-color" value="#d81616">
                            </div>
                        </div>

                        <div class="mt-4 d-grid gap-2">
                            <button class="btn btn-warning fw-bold" onclick="window.print()">Imprimir</button>
                            <button id="t2ExportPng" class="btn btn-outline-primary fw-bold" type="button">Salvar PNG</button>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7 d-flex justify-content-center align-items-start print-area">
                    <div class="cartaz" id="t2Preview">
                        <div class="cabecalho-curve" id="t2Header" style="font-size: 32px;">OFERTA</div>
                        <div class="conteudo">
                            <div class="descricao" id="t2DescricaoPreview">DESCRIÇÃO DO PRODUTO</div>
                            <div class="preco" id="t2PrecoPreview">
                                <span class="currency">R$</span>
                                <span id="t2PrecoInteiro">0</span><span class="cents" id="t2PrecoCentavos">,00</span>
                            </div>
                        </div>
                        <div class="rodape-curve" id="t2RodapePreview">APROVEITE</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" crossorigin="anonymous"></script>
<script>
    const tituloSelect = document.getElementById('t2Titulo');
    const header = document.getElementById('t2Header');
    const descricaoInput = document.getElementById('t2Descricao');
    const precoInput = document.getElementById('t2Preco');
    const rodapeInput = document.getElementById('t2Rodape');
    const descricaoPreview = document.getElementById('t2DescricaoPreview');
    const precoInteiro = document.getElementById('t2PrecoInteiro');
    const precoCentavos = document.getElementById('t2PrecoCentavos');
    const rodapePreview = document.getElementById('t2RodapePreview');
    const preview = document.getElementById('t2Preview');
    const exportButton = document.getElementById('t2ExportPng');

    const corTitulo = document.getElementById('t2CorTitulo');
    const corCurvas = document.getElementById('t2CorCurvas');
    const corFundo = document.getElementById('t2CorFundo');
    const corRodapeTexto = document.getElementById('t2CorRodapeTexto');
    const corPreco = document.getElementById('t2CorPreco');

    const COLOR_STORAGE_KEY = 'cartaz_t2_colors';

    let tituloFontSize = 38;
    let descFontSize = 34;
    let priceFontSize = 150;

    const updateTitulo = () => {
        const valor = (tituloSelect.value || '').toUpperCase();
        header.textContent = valor || 'OFERTA';
        header.style.color = corTitulo.value;
        header.style.fontSize = tituloFontSize + 'px';
        header.style.background = corCurvas.value;
        header.style.setProperty('--t2-title-size', `${tituloFontSize}px`);
    };

    const updateDescricao = () => {
        const valor = descricaoInput.value || 'DESCRIÇÃO DO PRODUTO';
        descricaoPreview.textContent = valor.toUpperCase();
        descricaoPreview.style.fontSize = descFontSize + 'px';
        preview.style.setProperty('--t2-desc-size', `${descFontSize}px`);
    };

    const updatePreco = () => {
        let valor = precoInput.value.replace('R$', '').trim().replace(',', '.');
        const numero = parseFloat(valor);
        if (isNaN(numero)) {
            precoInteiro.textContent = '0';
            precoCentavos.textContent = ',00';
            return;
        }
        const partes = numero.toFixed(2).split('.');
        precoInteiro.textContent = partes[0];
        precoCentavos.textContent = ',' + partes[1];
        document.getElementById('t2PrecoPreview').style.color = corPreco.value;
        document.getElementById('t2PrecoPreview').style.fontSize = priceFontSize + 'px';
        preview.style.setProperty('--t2-price-size', `${priceFontSize}px`);
    };

    const updateRodape = () => {
        const valor = rodapeInput.value || 'APROVEITE';
        rodapePreview.textContent = valor;
        rodapePreview.style.color = corRodapeTexto.value;
        rodapePreview.style.background = corCurvas.value;
    };

    const updateFundo = () => {
        preview.style.background = corFundo.value;
        preview.style.borderColor = corCurvas.value;
        rodapePreview.style.borderTopColor = corCurvas.value;
    };

    const exportCartazAsPng = async () => {
        if (!exportButton) return;
        if (typeof html2canvas !== 'function') {
            alert('Biblioteca de exportação indisponível.');
            return;
        }
        exportButton.disabled = true;
        const originalLabel = exportButton.textContent;
        exportButton.textContent = 'Gerando PNG...';
        try {
            const canvas = await html2canvas(preview, {
                scale: 2,
                backgroundColor: null,
                useCORS: true
            });
            const link = document.createElement('a');
            link.href = canvas.toDataURL('image/png');
            link.download = 'cartaz.png';
            link.click();
        } catch (error) {
            console.error('Erro ao gerar PNG', error);
            alert('Não foi possível salvar o cartaz. Tente novamente.');
        } finally {
            exportButton.disabled = false;
            exportButton.textContent = originalLabel;
        }
    };

    const resetCores = () => {
        corTitulo.value = '#ffffff';
        corFundo.value = '#ffe700';
        corRodapeTexto.value = '#ffffff';
        corCurvas.value = '#d81616';
        corPreco.value = '#d81616';
        tituloFontSize = 38;
        descFontSize = 34;
        priceFontSize = 150;
        updateTitulo();
        updateDescricao();
        updatePreco();
        updateRodape();
        updateFundo();
    };

    const saveColors = () => {
        const payload = {
            corTitulo: corTitulo.value,
            corCurvas: corCurvas.value,
            corFundo: corFundo.value,
            corRodapeTexto: corRodapeTexto.value,
            corPreco: corPreco.value,
            tituloFontSize,
            descFontSize,
            priceFontSize
        };
        localStorage.setItem(COLOR_STORAGE_KEY, JSON.stringify(payload));
        alert('Cores e tamanhos salvos para este template.');
    };

    const applySavedColors = () => {
        const saved = localStorage.getItem(COLOR_STORAGE_KEY);
        if (!saved) return;
        try {
            const data = JSON.parse(saved);
            if (data.corTitulo) corTitulo.value = data.corTitulo;
            if (data.corCurvas) corCurvas.value = data.corCurvas;
            if (data.corFundo) corFundo.value = data.corFundo;
            if (data.corRodapeTexto) corRodapeTexto.value = data.corRodapeTexto;
            if (data.corPreco) corPreco.value = data.corPreco;
            tituloFontSize = data.tituloFontSize || tituloFontSize;
            descFontSize = data.descFontSize || descFontSize;
            priceFontSize = data.priceFontSize || priceFontSize;
        } catch (e) {
            console.warn('Falha ao carregar cores salvas', e);
        }
    };

    document.getElementById('t2FontPlus').addEventListener('click', () => {
        tituloFontSize = Math.min(tituloFontSize + 2, 64);
        updateTitulo();
    });
    document.getElementById('t2FontMinus').addEventListener('click', () => {
        tituloFontSize = Math.max(tituloFontSize - 2, 18);
        updateTitulo();
    });

    tituloSelect.addEventListener('change', updateTitulo);
    corTitulo.addEventListener('input', updateTitulo);
    corCurvas.addEventListener('input', () => { updateTitulo(); updateRodape(); updateFundo(); });

    descricaoInput.addEventListener('input', updateDescricao);
    precoInput.addEventListener('input', updatePreco);
    rodapeInput.addEventListener('input', updateRodape);

    corFundo.addEventListener('input', updateFundo);
    corRodapeTexto.addEventListener('input', updateRodape);
    corPreco.addEventListener('input', updatePreco);

    document.getElementById('t2DescPlus').addEventListener('click', () => {
        descFontSize = Math.min(descFontSize + 2, 60);
        updateDescricao();
    });
    document.getElementById('t2DescMinus').addEventListener('click', () => {
        descFontSize = Math.max(descFontSize - 2, 18);
        updateDescricao();
    });
    document.getElementById('t2PrecoPlus').addEventListener('click', () => {
        priceFontSize = Math.min(priceFontSize + 4, 200);
        updatePreco();
    });
    document.getElementById('t2PrecoMinus').addEventListener('click', () => {
        priceFontSize = Math.max(priceFontSize - 4, 80);
        updatePreco();
    });

    if (exportButton) {
        exportButton.addEventListener('click', exportCartazAsPng);
    }

    applySavedColors();
    updateTitulo();
    updateDescricao();
    updatePreco();
    updateRodape();
    updateFundo();
</script>
</body>
</html>
