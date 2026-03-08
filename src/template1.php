<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
require __DIR__ . '/db.php';
require __DIR__ . '/payments/helpers.php';

// Apenas clientes precisam de assinatura; superadmin tem acesso livre.
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
    <title>Template 01 - Clássico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/public/favicon.ico">
    <style>
        :root {
            --cartaz-width: 360px;
            --cartaz-height: 540px;
            --cartaz-padding-top: 28px;
            --cartaz-padding-side: 20px;
            --cartaz-padding-bottom: 22px;
            --rodape-height: 48px;
            --t1-color-title: #ff0000;
            --t1-color-bg: #ffed00;
            --t1-color-rodape-text: #111111;
            --t1-color-border: #e00000;
            --t1-color-price: #e60000;
        }
        * { box-sizing: border-box; }
        body { background: #f3f6f8; font-family: 'Montserrat', sans-serif; }
        .sidebar {
            background: #111927;
            color: #e9eef7;
            min-height: 100vh;
            padding: 22px 18px;
            box-shadow: 6px 0 22px rgba(0,0,0,0.2);
        }
        .brand { font-weight: 800; letter-spacing: 0.4px; color: #ffd43b; }
        .control-panel {
            background: #1f2532;
            color: #fff;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 14px 40px rgba(0,0,0,0.18);
        }
        .cartaz {
            width: var(--cartaz-width);
            min-height: var(--cartaz-height);
            height: var(--cartaz-height);
            border-radius: 10px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.14);
            overflow: hidden;
            margin: 0 auto;
            background: var(--t1-color-bg, #ffed00);
            border: 12px solid var(--t1-color-border, #e00000);
            display: flex;
            flex-direction: column;
            padding: var(--cartaz-padding-top) var(--cartaz-padding-side) var(--cartaz-padding-bottom);
            position: relative;
        }
        .cartaz .cabecalho { text-align: center; font-weight: 800; letter-spacing: 0.5px; margin-bottom: 6px; font-size: var(--t1-title-size-active, var(--t1-title-size-base, 38px)); display: flex; align-items: center; justify-content: center; color: var(--t1-color-title, #ff0000); }
        .cartaz .conteudo {
            flex: 1;
            width: 100%;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            gap: 14px;
            padding-top: 40px;
        }
        .descricao { font-size: var(--t1-desc-size-active, var(--t1-desc-size-base, 34px)); font-weight: 700; line-height: 1.2; min-height: 80px; display: flex; align-items: center; justify-content: center; color: #111; text-align: center; margin-top: 4px; }
        .preco {
            font-size: var(--t1-price-size-active, var(--t1-price-size-base, 150px));
            font-weight: 800;
            color: var(--t1-color-price, #e60000);
            line-height: 0.9;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            width: 100%;
            text-align: center;
            gap: 6px;
        }
        .preco .currency {
            font-size: calc(var(--t1-price-size-active, var(--t1-price-size-base, 150px)) * 0.15);
            position: relative;
            top: calc(var(--t1-price-size-active, var(--t1-price-size-base, 150px)) * -0.08);
            font-weight: 700;
        }
        .preco .inteiro { line-height: 0.9; }
        .preco .cents {
            vertical-align: top;
            font-size: calc(var(--t1-price-size-active, var(--t1-price-size-base, 150px)) * 0.19);
            position: relative;
            top: calc(var(--t1-price-size-active, var(--t1-price-size-base, 150px)) * -0.05);
        }
        .preco { margin-top: 40px; }
        .rodape {
            position: absolute;
            left: var(--cartaz-padding-side);
            right: var(--cartaz-padding-side);
            bottom: var(--cartaz-padding-bottom);
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            border-top: 6px solid var(--t1-color-border, #e00000);
            background: var(--t1-color-bg, #ffed00);
            color: var(--t1-color-rodape-text, #111111);
            min-height: var(--rodape-height);
            display: flex;
            align-items: center;
            justify-content: center;
            width: calc(100% - (var(--cartaz-padding-side) * 2));
        }
        .swatch-label { font-size: 13px; color: #d5d8e2; }
        .btn-font { border-radius: 10px; font-weight: 700; }
        .canvas-area { display: flex; }
        @page { size: 210mm 297mm; margin: 0; }
        @media print {
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
            :root { --print-scale: 1.2; }
            :root {
                --cartaz-width: 210mm;
                --cartaz-height: 297mm;
                --cartaz-padding-top: 22px;
                --cartaz-padding-side: 18px;
                --cartaz-padding-bottom: 20px;
                --rodape-height: 48px;
            }
            html, body { margin: 0; padding: 0; width: 210mm; height: 297mm; background: #fff; }
            .no-print { display: none !important; }
            .canvas-area { width: 210mm; height: 297mm; margin: 0 auto; padding: 0; display: flex; justify-content: center; align-items: flex-start; }
            .print-area { width: 210mm; min-height: 297mm; height: 297mm; display: flex; justify-content: center; align-items: flex-start; margin: 0 auto; }
            .cartaz {
                box-shadow: none;
                margin: 0 auto;
                width: 210mm !important;
                height: 297mm !important;
                min-height: 297mm !important;
                max-height: 297mm !important;
                overflow: hidden;
                background: var(--t1-color-bg, #ffed00) !important;
                border-color: var(--t1-color-border, #e00000) !important;
            }
            .cartaz .cabecalho { margin-bottom: 6px; font-size: calc(var(--t1-title-size-base, 38px) * 2.4) !important; line-height: 1.1 !important; }
            .cartaz .conteudo {
                padding-top: 12px;
                gap: 10px;
                align-items: center;
                justify-content: center;
                display: grid;
                grid-template-rows: auto auto 1fr;
                width: 100%;
                justify-items: center;
            }
            .cartaz .conteudo .descricao { align-self: start; margin-top: 50px !important; }
            .cartaz .conteudo .preco { align-self: end; justify-self: center; margin-top: 150px !important; margin-bottom: 0; }
            .descricao { font-size: calc(var(--t1-desc-size-base, 34px) * 2.2) !important; min-height: 80px; line-height: 1.1 !important; }
            .cartaz .conteudo .descricao { align-self: center !important; justify-content: center !important; text-align: center !important; }
            .preco {
                margin-top: 0;
                margin-bottom: 0;
                font-size: calc(var(--t1-price-size-base, 150px) * 3) !important;
                
            }
            .preco .currency {
                font-size: calc(var(--t1-price-size-base, 150px) * 0.15 * 3) !important;
                top: calc(var(--t1-price-size-base, 150px) * -0.08 * 3);
                
            }
            .preco .cents {
                font-size: calc(var(--t1-price-size-base, 150px) * 0.19 * 3) !important;
                top: calc(var(--t1-price-size-base, 150px) * -0.05 * 3);
               
            }
            .rodape { font-size: calc(var(--t1-rodape-size-base, 14px) * 3) !important; }
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
                            <h5 class="mb-0 fw-bold">Template 01 - Clássico</h5>
                            <button class="btn btn-sm btn-danger" type="button" onclick="window.location.reload()">Limpar</button>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Título</label>
                            <div class="input-group">
                                <select id="t1Titulo" class="form-select text-uppercase fw-bold">
                                    <option>PROMOÇÃO</option>
                                    <option>OFERTA</option>
                                    <option>DIA DA FERA</option>
                                    <option>PREÇO</option>
                                    <option>QUEIMA</option>
                                    <option>EXCLUSIVO</option>
                                    <option>SUPER OFERTÃO</option>
                                    <option>AÇOUGUE</option>
                                    <option>HORTIFRUTE</option>
                                    <option>PADARIA</option>
                                    <option>IOGURTE</option>
                                    <option>CARNES</option>
                                </select>
                                <button class="btn btn-light btn-font" type="button" id="t1FontMinus">A-</button>
                                <button class="btn btn-light btn-font" type="button" id="t1FontPlus">A+</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <div class="input-group">
                                <textarea id="t1Descricao" class="form-control" rows="2" placeholder="Ex: Arroz 5Kg"></textarea>
                                <button class="btn btn-light btn-font" type="button" id="t1DescMinus">A-</button>
                                <button class="btn btn-light btn-font" type="button" id="t1DescPlus">A+</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Preço</label>
                            <div class="input-group">
                                <input id="t1Preco" class="form-control" placeholder="Ex: 29,99">
                                <button class="btn btn-light btn-font" type="button" id="t1PrecoMinus">A-</button>
                                <button class="btn btn-light btn-font" type="button" id="t1PrecoPlus">A+</button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Rodapé</label>
                            <input id="t1Rodape" class="form-control" placeholder="Ex: Oferta válida até...">
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
                                <input type="color" id="t1CorTitulo" class="form-control form-control-color" value="#ff0000">
                            </div>
                            <div class="col-6">
                                <label class="swatch-label">Fundo do Cartaz</label>
                                <input type="color" id="t1CorFundo" class="form-control form-control-color" value="#ffed00">
                            </div>
                            <div class="col-6">
                                <label class="swatch-label">Texto do Rodapé</label>
                                <input type="color" id="t1CorRodapeTexto" class="form-control form-control-color" value="#111111">
                            </div>
                            <div class="col-6">
                                <label class="swatch-label">Borda do Cartaz</label>
                                <input type="color" id="t1CorBorda" class="form-control form-control-color" value="#e00000">
                            </div>
                            <div class="col-6">
                                <label class="swatch-label">Cor do Preço</label>
                                <input type="color" id="t1CorPreco" class="form-control form-control-color" value="#e60000">
                            </div>
                        </div>

                        <div class="mt-4 d-grid gap-2">
                            <button class="btn btn-warning fw-bold" onclick="window.print()">Imprimir</button>
                            <button id="t1ExportPng" class="btn btn-outline-primary fw-bold" type="button">Salvar PNG</button>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7 d-flex justify-content-center align-items-start print-area">
                    <div class="cartaz" id="t1Preview" style="--t1-color-bg: #ffed00; --t1-color-border: #e00000; --t1-color-title: #ff0000; --t1-color-rodape-text: #111111; --t1-color-price: #e60000;">
                        <div class="cabecalho" id="t1Header" style="font-size: 32px;">OFERTA</div>
                        <div class="conteudo">
                            <div class="descricao" id="t1DescricaoPreview">DESCRIÇÃO DO PRODUTO</div>
                            <div class="preco" id="t1PrecoPreview">
                                <span class="currency">R$</span>
                                <span class="inteiro" id="t1PrecoInteiro">0</span><span class="cents" id="t1PrecoCentavos">,00</span>
                            </div>
                        </div>
                        <div class="rodape" id="t1RodapePreview">APROVEITE</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" crossorigin="anonymous"></script>
<script>
    const tituloSelect = document.getElementById('t1Titulo');
    const header = document.getElementById('t1Header');
    const descricaoInput = document.getElementById('t1Descricao');
    const precoInput = document.getElementById('t1Preco');
    const rodapeInput = document.getElementById('t1Rodape');
    const descricaoPreview = document.getElementById('t1DescricaoPreview');
    const precoInteiro = document.getElementById('t1PrecoInteiro');
    const precoCentavos = document.getElementById('t1PrecoCentavos');
    const rodapePreview = document.getElementById('t1RodapePreview');
    const preview = document.getElementById('t1Preview');
    const exportButton = document.getElementById('t1ExportPng');
    const COLOR_STORAGE_KEY = 'cartaz_t1_colors';

    const corTitulo = document.getElementById('t1CorTitulo');
    const corFundo = document.getElementById('t1CorFundo');
    const corRodapeTexto = document.getElementById('t1CorRodapeTexto');
    const corBorda = document.getElementById('t1CorBorda');
    const corPreco = document.getElementById('t1CorPreco');

    let tituloFontSize = 38;
    let descFontSize = 34;
    let priceFontSize = 150;

    const updateTitulo = () => {
        const valor = tituloSelect.value.toUpperCase();
        header.textContent = valor;
        preview.style.setProperty('--t1-color-title', corTitulo.value);
        header.style.fontSize = tituloFontSize + 'px';
        preview.style.setProperty('--t1-title-size-base', `${tituloFontSize}px`);
    };

    const updateDescricao = () => {
        const valor = descricaoInput.value || 'DESCRIÇÃO DO PRODUTO';
        descricaoPreview.textContent = valor.toUpperCase();
        descricaoPreview.style.fontSize = descFontSize + 'px';
        preview.style.setProperty('--t1-desc-size-base', `${descFontSize}px`);
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
        preview.style.setProperty('--t1-color-price', corPreco.value);
        document.getElementById('t1PrecoPreview').style.fontSize = priceFontSize + 'px';
        preview.style.setProperty('--t1-price-size-base', `${priceFontSize}px`);
    };

    const updateRodape = () => {
        const valor = rodapeInput.value || 'APROVEITE';
        rodapePreview.textContent = valor;
        preview.style.setProperty('--t1-color-rodape-text', corRodapeTexto.value);
        preview.style.setProperty('--t1-color-bg', corFundo.value);
        preview.style.setProperty('--t1-color-border', corBorda.value);
        const baseSize = rodapePreview.style.fontSize || '14px';
        preview.style.setProperty('--t1-rodape-size-base', baseSize);
    };

    const updateFundo = () => {
        preview.style.setProperty('--t1-color-bg', corFundo.value);
        preview.style.setProperty('--t1-color-border', corBorda.value);
        rodapePreview.style.background = corFundo.value;
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
        corTitulo.value = '#ff0000';
        corFundo.value = '#ffed00';
        corRodapeTexto.value = '#111111';
        corBorda.value = '#e00000';
        corPreco.value = '#e60000';
        tituloFontSize = 38;
        descFontSize = 34;
        priceFontSize = 150;
        updateTitulo();
        updateRodape();
        updateFundo();
        updatePreco();
        updateDescricao();
    };

    const saveColors = () => {
        const payload = {
            corTitulo: corTitulo.value,
            corFundo: corFundo.value,
            corRodapeTexto: corRodapeTexto.value,
            corBorda: corBorda.value,
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
            if (data.corFundo) corFundo.value = data.corFundo;
            if (data.corRodapeTexto) corRodapeTexto.value = data.corRodapeTexto;
            if (data.corBorda) corBorda.value = data.corBorda;
            if (data.corPreco) corPreco.value = data.corPreco;
            tituloFontSize = data.tituloFontSize || tituloFontSize;
            descFontSize = data.descFontSize || descFontSize;
            priceFontSize = data.priceFontSize || priceFontSize;
        } catch (e) {
            console.warn('Falha ao carregar cores salvas', e);
        }
    };

    document.getElementById('t1FontPlus').addEventListener('click', () => {
        tituloFontSize = Math.min(tituloFontSize + 2, 64);
        updateTitulo();
    });
    document.getElementById('t1FontMinus').addEventListener('click', () => {
        tituloFontSize = Math.max(tituloFontSize - 2, 18);
        updateTitulo();
    });

    tituloSelect.addEventListener('change', updateTitulo);
    corTitulo.addEventListener('input', updateTitulo);

    descricaoInput.addEventListener('input', updateDescricao);
    precoInput.addEventListener('input', updatePreco);
    rodapeInput.addEventListener('input', updateRodape);

    corFundo.addEventListener('input', updateFundo);
    corRodapeTexto.addEventListener('input', updateRodape);
    corBorda.addEventListener('input', () => { updateRodape(); updateFundo(); });
    corPreco.addEventListener('input', updatePreco);

    document.getElementById('t1DescPlus').addEventListener('click', () => {
        descFontSize = Math.min(descFontSize + 2, 40);
        updateDescricao();
    });
    document.getElementById('t1DescMinus').addEventListener('click', () => {
        descFontSize = Math.max(descFontSize - 2, 16);
        updateDescricao();
    });
    document.getElementById('t1PrecoPlus').addEventListener('click', () => {
        priceFontSize = Math.min(priceFontSize + 4, 170);
        updatePreco();
    });
    document.getElementById('t1PrecoMinus').addEventListener('click', () => {
        priceFontSize = Math.max(priceFontSize - 4, 50);
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
