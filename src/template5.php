<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
require __DIR__ . '/db.php';
require __DIR__ . '/payments/helpers.php';

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['t3Image'])) {
    $file = $_FILES['t3Image'];
    $uploadMsg = '';
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        if (!in_array($file['type'], $allowed)) {
            $uploadMsg = 'Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WEBP.';
        } else {
            $uploadDirPath = __DIR__ . '/../public/img/uploads';
            if (!is_dir($uploadDirPath)) {
                mkdir($uploadDirPath, 0755, true);
            }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $base = pathinfo($file['name'], PATHINFO_FILENAME);
            $safeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', $base);
            try {
                $rand = bin2hex(random_bytes(4));
            } catch (Exception $e) {
                $rand = substr(md5(uniqid('', true)), 0, 8);
            }
            $filename = time() . '_' . $rand . '_' . $safeBase . '.' . $ext;
            $target = $uploadDirPath . '/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $uploadMsg = 'Imagem enviada com sucesso.';
            } else {
                $uploadMsg = 'Falha ao mover o arquivo para destino.';
            }
        }
    } else {
        $uploadMsg = 'Erro no upload (código: ' . $file['error'] . ').';
    }
    header('Location: template5.php?uploadmsg=' . urlencode($uploadMsg));
    exit;
}

$role = $_SESSION['user']['role'] ?? '';
if ($role === 'cliente') {
    $client = ensureClientForUser($pdo, (int)$_SESSION['user']['id'], $_SESSION['user']['name'] ?? '', $_SESSION['user']['email'] ?? '');
    $activeSubscription = getActiveSubscription($pdo, (int)$client['id']);
    if (!$activeSubscription) {
        header('Location: select_plan.php?expired=1');
        exit;
    }
}

// Gather saved images (URL)
$uploadDirUrl = '/public/img/uploads';
$uploadDirPath = __DIR__ . '/../public/img/uploads';
$images = [];
if (is_dir($uploadDirPath)) {
    $files = scandir($uploadDirPath);
    foreach ($files as $f) {
        if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp'])) {
            $images[] = $uploadDirUrl . '/' . $f;
        }
    }
    // sort newest first
    rsort($images);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Template 05 - Curvas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="icon" type="image/x-icon" href="/public/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        body { background: #f3f6f8; font-family: 'Montserrat', sans-serif; }
        .sidebar { background: #111927; color: #e9eef7; min-height: 100vh; padding: 22px 18px; box-shadow: 6px 0 22px rgba(0,0,0,0.2); }
        .brand { font-weight: 800; letter-spacing: 0.4px; color: #ffd43b; }
        .control-panel { background: #1f2532; color: #fff; border-radius: 14px; padding: 18px; box-shadow: 0 14px 40px rgba(0,0,0,0.18); }
        .cartaz { width: 360px; min-height: 560px; border-radius: 10px; box-shadow: 0 12px 30px rgba(0,0,0,0.14); overflow: hidden; margin: 0 auto; position: relative; background: #ffe700; background-size: cover !important; background-position: center center !important; background-repeat: no-repeat !important; border: none !important; border-color: transparent !important; }
        .cabecalho-curve { background: #d81616; color: #fff; padding: 22px 18px 28px; text-align: center; font-weight: 800; letter-spacing: 0.6px; border-radius: 0 0 60% 60%; font-size: var(--t3-title-size, 32px); }
        .conteudo { padding: 24px 20px 24px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 14px; }
        .descricao { font-size: var(--t3-desc-size, 34px); font-weight: 700; line-height: 1.2; min-height: 100px; display: flex; align-items: center; justify-content: center; text-align: center; 
            text-shadow: 0 0 8px rgba(255,255,255,.8), 0 0 18px rgba(0,0,0,.75); }
        .preco { font-size: var(--t3-price-size, 150px); font-weight: 800; line-height: 0.9; display: flex; align-items: flex-start; justify-content: center; gap: 6px; margin-top: 60px; 
            text-shadow: 0 0 12px rgba(255,255,255,.65), 0 0 18px rgba(0,0,0,.6); }
        .preco .currency { font-size: 22px; position: relative; top: -12px; font-weight: 700; }
        .preco .inteiro { line-height: 0.9; }
        .preco .cents { vertical-align: top; font-size: 32px; position: relative; top: -8px; }
        .rodape-curve { background: #d81616; color: #fff; padding: 12px 18px 10px; text-align: center; font-weight: 700; border-radius: 60% 60% 0 0; position: absolute; bottom: 0; left: 0; right: 0; border-top: 6px solid #b60000; font-size: var(--t3-rodape-size, 14px); }
        .swatch-label { font-size: 13px; color: #d5d8e2; }
        .btn-font { border-radius: 10px; font-weight: 700; }
        .canvas-area { display: flex; }
        .saved-thumb { width: 80px; height: 60px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 2px solid transparent; }
        .saved-thumb.selected { border-color: #ffd43b; }
        .backgrounds-panel { background: #1a1f2c; border-radius: 12px; padding: 14px; margin-top: 18px; }
        .backgrounds-grid { display: flex; flex-wrap: wrap; gap: 8px; min-height: 88px; }
        .backgrounds-item { border: none; background: transparent; display: flex; flex-direction: column; align-items: center; padding: 0; border-radius: 8px; cursor: pointer; transition: transform .15s ease, box-shadow .15s ease; width: 90px; position: relative; }
        .backgrounds-item.selected .saved-thumb { border-color: #ffd43b; }
        .backgrounds-item:hover { transform: translateY(-2px); }
        .backgrounds-item span { font-size: 11px; max-width: 90px; color: #e9eef7; text-align: center; }
        .backgrounds-panel form .form-control { background: #0f1522; color: #f3f6f8; border-color: #2f3648; }
        .backgrounds-item .backgrounds-user { font-size: 10px; color: #9ba5c6; text-transform: lowercase; }
        .backgrounds-delete { position: absolute; top: -6px; right: -6px; width: 18px; height: 18px; border-radius: 50%; font-size: 12px; padding: 0; border: none; background: rgba(220, 60, 60, 0.85); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .backgrounds-delete.hidden { display: none; }
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
                border: none !important;
                border-color: transparent !important;
                background-size: 100% 100% !important;
                background-position: center center !important;
                background-repeat: no-repeat !important;
            }
            .cabecalho-curve { font-size: calc(var(--t3-title-size, 32px) * 2.5) !important; }
            .descricao { font-size: calc(var(--t3-desc-size, 34px) * 2) !important; }
            .preco { font-size: calc(var(--t3-price-size, 150px) * 3) !important; }
            .preco { margin-top: 150px !important; }
            .preco .currency { font-size: calc(var(--t3-price-size, 150px) * 0.15 * 3) !important; top: calc(var(--t3-price-size, 150px) * -0.08 * 3); }
            .preco .cents { font-size: calc(var(--t3-price-size, 150px) * 0.19 * 3) !important; top: calc(var(--t3-price-size, 150px) * -0.05 * 3); }
            .rodape-curve { font-size: calc(var(--t3-rodape-size, 14px) * 2) !important; }
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
                            <h5 class="mb-0 fw-bold">Template 03 - Curvas Sem Contorno</h5>
                            <button class="btn btn-sm btn-danger" type="button" onclick="window.location.reload()">Limpar</button>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Título</label>
                            <div class="input-group">
                                <select id="t3Titulo" class="form-select text-uppercase fw-bold">
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
                                <button class="btn btn-light btn-font" type="button" id="t3FontMinus">A-</button>
                                <button class="btn btn-light btn-font" type="button" id="t3FontPlus">A+</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição</label>
                            <div class="input-group">
                                <textarea id="t3Descricao" class="form-control" rows="2" placeholder="Ex: Produto X 1Kg"></textarea>
                                <button class="btn btn-light btn-font" type="button" id="t3DescMinus">A-</button>
                                <button class="btn btn-light btn-font" type="button" id="t3DescPlus">A+</button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Preço</label>
                            <div class="input-group">
                                <input id="t3Preco" class="form-control" placeholder="Ex: 19,90">
                                <button class="btn btn-light btn-font" type="button" id="t3PrecoMinus">A-</button>
                                <button class="btn btn-light btn-font" type="button" id="t3PrecoPlus">A+</button>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Rodapé</label>
                            <input id="t3Rodape" class="form-control" placeholder="Ex: Aproveite!">
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
                                <input type="color" id="t3CorTitulo" class="form-control form-control-color" value="#ffffff">
                            </div>
                            <div class="col-6">
                                <label class="swatch-label">Faixa (Título/Rodapé)</label>
                                <input type="color" id="t3CorFaixa" class="form-control form-control-color" value="#d81616">
                            </div>
                            <div class="col-6">
                                <label class="swatch-label">Fundo do Cartaz</label>
                                <input type="color" id="t3CorFundo" class="form-control form-control-color" value="#ffe700">
                            </div>
                            <div class="col-6">
                                <label class="swatch-label">Texto do Rodapé</label>
                                <input type="color" id="t3CorRodapeTexto" class="form-control form-control-color" value="#ffffff">
                            </div>
                            <div class="col-6">
                                <label class="swatch-label">Cor da Descrição</label>
                                <input type="color" id="t3CorDescricao" class="form-control form-control-color" value="#111111">
                            </div>
                            <div class="col-6">
                                <label class="swatch-label">Cor do Preço</label>
                                <input type="color" id="t3CorPreco" class="form-control form-control-color" value="#d81616">
                            </div>
                        </div>

                        <div class="backgrounds-panel">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-uppercase small text-muted">Fundos Template 5</div>
                                    <div class="fw-bold">Fundo personalizado</div>
                                </div>
                                <button id="t3ShowBackgroundForm" type="button" class="btn btn-sm btn-outline-light">Adicionar Fundo</button>
                            </div>
                            <div id="t3BackgroundsList" class="backgrounds-grid mt-3">
                                <div class="text-muted small">Nenhum fundo cadastrado ainda.</div>
                            </div>
                            <form id="t3BackgroundForm" class="mt-3 d-none" enctype="multipart/form-data">
                                <div class="mb-2">
                                    <input id="t3BackgroundName" class="form-control form-control-sm" type="text" name="name" placeholder="Nome do fundo" required>
                                </div>
                                <div class="mb-2">
                                    <input id="t3BackgroundFile" class="form-control form-control-sm" type="file" name="background" accept="image/png,image/jpeg" required>
                                </div>
                                <div class="d-flex gap-2">
                                    <button id="t3BackgroundCancel" type="button" class="btn btn-sm btn-outline-secondary">Cancelar</button>
                                    <button id="t3BackgroundSubmit" type="submit" class="btn btn-sm btn-primary">Enviar</button>
                                </div>
                            </form>
                        </div>

                        <div class="mt-4 d-grid gap-2">
                            <button class="btn btn-warning fw-bold" onclick="window.print()">Imprimir</button>
                            <button id="t3ExportPng" class="btn btn-outline-primary fw-bold" type="button">Salvar PNG</button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 d-flex justify-content-center align-items-start print-area">
                    <div class="cartaz" id="t3Preview">
                        <div class="cabecalho-curve" id="t3Header" style="font-size: 32px;">OFERTA</div>
                        <div class="conteudo">
                            <div class="descricao" id="t3DescricaoPreview">DESCRIÇÃO DO PRODUTO</div>
                            <div class="preco" id="t3PrecoPreview">
                                <span class="currency">R$</span>
                                <span id="t3PrecoInteiro">0</span><span class="cents" id="t3PrecoCentavos">,00</span>
                            </div>
                        </div>
                        <div class="rodape-curve" id="t3RodapePreview">APROVEITE</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" crossorigin="anonymous"></script>
<script>
    const tituloSelect = document.getElementById('t3Titulo');
    const header = document.getElementById('t3Header');
    const descricaoInput = document.getElementById('t3Descricao');
    const precoInput = document.getElementById('t3Preco');
    const rodapeInput = document.getElementById('t3Rodape');
    const descricaoPreview = document.getElementById('t3DescricaoPreview');
    const precoInteiro = document.getElementById('t3PrecoInteiro');
    const precoCentavos = document.getElementById('t3PrecoCentavos');
    const rodapePreview = document.getElementById('t3RodapePreview');
    const preview = document.getElementById('t3Preview');
    const precoPreview = document.getElementById('t3PrecoPreview');

    const corTitulo = document.getElementById('t3CorTitulo');
    const corFaixa = document.getElementById('t3CorFaixa');
    const corFundo = document.getElementById('t3CorFundo');
    const corRodapeTexto = document.getElementById('t3CorRodapeTexto');
    const corDescricao = document.getElementById('t3CorDescricao');
    const corPreco = document.getElementById('t3CorPreco');
    const exportButton = document.getElementById('t3ExportPng');
    const backgroundList = document.getElementById('t3BackgroundsList');
    const backgroundToggle = document.getElementById('t3ShowBackgroundForm');
    const backgroundForm = document.getElementById('t3BackgroundForm');
    const backgroundNameInput = document.getElementById('t3BackgroundName');
    const backgroundFileInput = document.getElementById('t3BackgroundFile');
    const backgroundCancel = document.getElementById('t3BackgroundCancel');

    const COLOR_STORAGE_KEY = 'cartaz_t3_colors';
    const BG_STORAGE_KEY = 'cartaz_t3_background';

    let tituloFontSize = 38;
    let descFontSize = 34;
    let priceFontSize = 150;
    let selectedBackground = null;

    const updateTitulo = () => {
        const valor = (tituloSelect.value || '').toUpperCase();
        header.textContent = valor || 'OFERTA';
        header.style.color = corTitulo.value;
        header.style.fontSize = tituloFontSize + 'px';
        header.style.background = corFaixa.value;
        header.style.setProperty('--t3-title-size', `${tituloFontSize}px`);
    };

    const updateDescricao = () => {
        const valor = descricaoInput.value || 'DESCRIÇÃO DO PRODUTO';
        descricaoPreview.textContent = valor.toUpperCase();
        descricaoPreview.style.fontSize = descFontSize + 'px';
        descricaoPreview.style.color = corDescricao.value;
        preview.style.setProperty('--t3-desc-size', `${descFontSize}px`);
    };

    const updatePreco = () => {
        precoPreview.style.color = corPreco.value;
        precoPreview.style.fontSize = priceFontSize + 'px';
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
        preview.style.setProperty('--t3-price-size', `${priceFontSize}px`);
    };

    const updateRodape = () => {
        const valor = rodapeInput.value || 'APROVEITE';
        rodapePreview.textContent = valor;
        rodapePreview.style.color = corRodapeTexto.value;
        rodapePreview.style.background = corFaixa.value;
    };

    const updateFundo = () => {
        preview.style.backgroundColor = corFundo.value;
        preview.style.border = 'none';
        preview.style.borderColor = 'transparent';
        rodapePreview.style.borderTopColor = corFaixa.value;
        if (!selectedBackground) {
            preview.style.backgroundImage = '';
            preview.style.backgroundRepeat = '';
            preview.style.backgroundSize = '';
            preview.style.backgroundPosition = '';
        }
    };

    const highlightBackgroundThumb = (id) => {
        if (!backgroundList) return;
        backgroundList.querySelectorAll('.backgrounds-item').forEach(item => {
            if (id && item.dataset.id === String(id)) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
        });
    };

    const applyBackground = (bg, options = { persist: true }) => {
        selectedBackground = bg && bg.url ? { id: bg.id, url: bg.url, name: bg.name } : null;
        if (!selectedBackground) {
            preview.style.backgroundImage = '';
            preview.style.backgroundRepeat = '';
            preview.style.backgroundSize = '';
            preview.style.backgroundPosition = '';
            if (options.persist !== false) {
                localStorage.removeItem(BG_STORAGE_KEY);
            }
        } else {
            preview.style.backgroundImage = `url('${selectedBackground.url}')`;
        preview.style.backgroundRepeat = 'no-repeat';
        preview.style.backgroundSize = 'cover';
        preview.style.backgroundPosition = 'center center';
            if (options.persist !== false) {
                localStorage.setItem(BG_STORAGE_KEY, JSON.stringify(selectedBackground));
            }
        }
        highlightBackgroundThumb(selectedBackground?.id);
    };

    const deleteBackground = async (id) => {
        try {
            const response = await fetch(`template5_fundos.php?id=${encodeURIComponent(id)}`, {
                method: 'DELETE'
            });
            await readJsonOrText(response);
            await fetchBackgrounds();
            if (selectedBackground?.id === id) {
                applyBackground(null);
            }
        } catch (error) {
            alert(error.message || 'Não foi possível remover o fundo.');
        }
    };

    const renderBackgrounds = (items) => {
        if (!backgroundList) return;
        const listItems = Array.isArray(items) ? items : [];
        backgroundList.innerHTML = '';
        if (!listItems.length) {
            backgroundList.innerHTML = '<div class="text-muted small">Nenhum fundo cadastrado ainda.</div>';
            return;
        }
        listItems.forEach(item => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'backgrounds-item';
            button.dataset.id = item.id;
            button.title = item.name;
            const img = document.createElement('img');
            img.className = 'saved-thumb';
            img.src = item.url;
            img.alt = item.name;
            button.appendChild(img);
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'backgrounds-delete';
            deleteBtn.innerHTML = '&times;';
            if (!item.canDelete) {
                deleteBtn.classList.add('hidden');
            }
            deleteBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                deleteBackground(item.id);
            });
            button.appendChild(deleteBtn);
            const label = document.createElement('span');
            label.textContent = item.name;
            button.appendChild(label);
            if (item.user_slug) {
                const ownerTag = document.createElement('span');
                ownerTag.className = 'backgrounds-user';
                ownerTag.textContent = item.user_slug;
                button.appendChild(ownerTag);
            }
            button.addEventListener('click', () => applyBackground(item));
            backgroundList.appendChild(button);
        });
        if (selectedBackground?.id) {
            highlightBackgroundThumb(selectedBackground.id);
        }
    };

    const readJsonOrText = async (response) => {
        const text = await response.text();
        const contentType = response.headers.get('content-type') || '';
        let data = null;
        if (contentType.includes('application/json')) {
            try {
                data = text ? JSON.parse(text) : null;
            } catch (err) {
                data = { error: text };
            }
        } else if (text.trim()) {
            data = { error: text };
        }
        if (!response.ok) {
            const cleaned = data && data.error ? data.error.replace(/<[^>]+>/g, '').trim() : '';
            throw new Error(cleaned || `Erro ${response.status}`);
        }
        return data;
    };

    const fetchBackgrounds = async () => {
        if (!backgroundList) return;
        try {
            const response = await fetch('template5_fundos.php');
            const payload = await readJsonOrText(response);
            renderBackgrounds(Array.isArray(payload) ? payload : []);
        } catch (error) {
            console.error('Erro ao carregar fundos', error);
            backgroundList.innerHTML = '<div class="text-muted small">Não foi possível carregar os fundos.</div>';
        }
    };

    const showBackgroundForm = () => {
        if (!backgroundForm) return;
        backgroundForm.classList.remove('d-none');
        backgroundNameInput?.focus();
    };

    const hideBackgroundForm = () => {
        if (!backgroundForm) return;
        backgroundForm.classList.add('d-none');
        if (backgroundFileInput) {
            backgroundFileInput.value = '';
        }
    };

    const restoreSavedBackground = () => {
        const saved = localStorage.getItem(BG_STORAGE_KEY);
        if (!saved) return;
        try {
            const parsed = JSON.parse(saved);
            if (parsed?.url) {
                selectedBackground = parsed;
                applyBackground(parsed, { persist: false });
            }
        } catch (error) {
            console.warn('Falha ao restaurar fundo salvo', error);
        }
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
        corFaixa.value = '#d81616';
        corFundo.value = '#ffe700';
        corRodapeTexto.value = '#ffffff';
        corDescricao.value = '#111111';
        corPreco.value = '#d81616';
        tituloFontSize = 38;
        descFontSize = 34;
        priceFontSize = 150;
        applyBackground(null, { persist: false });
        hideBackgroundForm();
        updateTitulo();
        updateDescricao();
        updatePreco();
        updateRodape();
        updateFundo();
    };

    const saveColors = () => {
        const payload = {
            corTitulo: corTitulo.value,
            corFaixa: corFaixa.value,
            corFundo: corFundo.value,
            corRodapeTexto: corRodapeTexto.value,
            corDescricao: corDescricao.value,
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
            if (data.corFaixa) corFaixa.value = data.corFaixa;
            if (data.corFundo) corFundo.value = data.corFundo;
            if (data.corRodapeTexto) corRodapeTexto.value = data.corRodapeTexto;
            if (data.corDescricao) corDescricao.value = data.corDescricao;
            if (data.corPreco) corPreco.value = data.corPreco;
            tituloFontSize = data.tituloFontSize || tituloFontSize;
            descFontSize = data.descFontSize || descFontSize;
            priceFontSize = data.priceFontSize || priceFontSize;
        } catch (e) {
            console.warn('Falha ao carregar cores salvas', e);
        }
    };

    document.getElementById('t3FontPlus').addEventListener('click', () => {
        tituloFontSize = Math.min(tituloFontSize + 2, 64);
        updateTitulo();
    });
    document.getElementById('t3FontMinus').addEventListener('click', () => {
        tituloFontSize = Math.max(tituloFontSize - 2, 18);
        updateTitulo();
    });

    tituloSelect.addEventListener('change', updateTitulo);
    corTitulo.addEventListener('input', updateTitulo);
    corFaixa.addEventListener('input', () => { updateTitulo(); updateRodape(); });

    descricaoInput.addEventListener('input', updateDescricao);
    precoInput.addEventListener('input', updatePreco);
    rodapeInput.addEventListener('input', updateRodape);

    corFundo.addEventListener('input', updateFundo);
    corRodapeTexto.addEventListener('input', updateRodape);
    corDescricao.addEventListener('input', updateDescricao);
    corPreco.addEventListener('input', updatePreco);

    backgroundToggle?.addEventListener('click', () => {
        if (!backgroundForm) return;
        if (backgroundForm.classList.contains('d-none')) {
            showBackgroundForm();
        } else {
            hideBackgroundForm();
        }
    });
    backgroundCancel?.addEventListener('click', () => hideBackgroundForm());

    backgroundForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!backgroundNameInput || !backgroundFileInput || !backgroundFileInput.files.length) {
            alert('Informe o nome e o arquivo do fundo.');
            return;
        }
        const submitBtn = document.getElementById('t3BackgroundSubmit');
        const originalText = submitBtn?.textContent || 'Enviar';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Enviando...';
        }
        const formData = new FormData();
        formData.append('name', backgroundNameInput.value.trim());
        formData.append('background', backgroundFileInput.files[0]);
        try {
            const response = await fetch('template5_fundos.php', {
                method: 'POST',
                body: formData
            });
            const payload = await readJsonOrText(response);
            if (!payload || payload.error) {
                const message = payload?.error || 'Não foi possível cadastrar o fundo.';
                throw new Error(message);
            }
            hideBackgroundForm();
            if (backgroundNameInput) {
                backgroundNameInput.value = '';
            }
            if (backgroundFileInput) {
                backgroundFileInput.value = '';
            }
            await fetchBackgrounds();
            applyBackground(payload);
        } catch (error) {
            console.error(error);
            alert(error.message || 'Não foi possível cadastrar o fundo.');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    });

    document.getElementById('t3DescPlus').addEventListener('click', () => {
        descFontSize = Math.min(descFontSize + 2, 60);
        updateDescricao();
    });
    document.getElementById('t3DescMinus').addEventListener('click', () => {
        descFontSize = Math.max(descFontSize - 2, 18);
        updateDescricao();
    });
    document.getElementById('t3PrecoPlus').addEventListener('click', () => {
        priceFontSize = Math.min(priceFontSize + 4, 200);
        updatePreco();
    });
    document.getElementById('t3PrecoMinus').addEventListener('click', () => {
        priceFontSize = Math.max(priceFontSize - 4, 80);
        updatePreco();
    });

    if (exportButton) {
        exportButton.addEventListener('click', exportCartazAsPng);
    }

    // Apply saved colors and background on load
    applySavedColors();
    updateTitulo();
    updateDescricao();
    updatePreco();
    updateRodape();
    updateFundo();

    restoreSavedBackground();
    fetchBackgrounds();
</script>
</body>
</html>
