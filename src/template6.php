<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
require __DIR__ . '/db.php';
require __DIR__ . '/payments/helpers.php';

$role = $_SESSION['user']['role'] ?? '';
$userId = (int)$_SESSION['user']['id'];

// Simulação de verificação de assinatura
if ($role === 'cliente') {
    $client = ensureClientForUser($pdo, $userId, $_SESSION['user']['name'] ?? '', $_SESSION['user']['email'] ?? '');
    if (!getActiveSubscription($pdo, (int)$client['id'])) {
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
    <title>Template 06 - Gerador de Encards</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        body { background: #f0f2f5; font-family: 'Montserrat', sans-serif; overflow-x: hidden; }
        
        /* Sidebar Fixa */
        .sidebar { background: #111927; color: #e9eef7; min-height: 100vh; padding: 22px 18px; box-shadow: 6px 0 22px rgba(0,0,0,0.2); }
        .brand { font-weight: 800; letter-spacing: 0.4px; color: #ffd43b; }
        
        /* Painel de Controle (Centro) */
        .control-panel { background: #1f2532; color: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); height: fit-content; }
        .control-label { font-size: 0.75rem; text-transform: uppercase; color: #9ba5c6; font-weight: 700; margin-bottom: 5px; display: block; }
        .upload-box { background: #2a3142; border: 2px dashed #424b61; border-radius: 8px; padding: 10px; margin-bottom: 15px; }
        .control-panel .btn-outline-light { min-width: 38px; }
        .control-panel input[type="color"] { height: 40px; padding: 0; }

        /* Área do Cartaz (Direita) */
        .canvas-area { display: flex; justify-content: center; align-items: flex-start; padding: 20px; }
        .encard-v6 { 
            width: 400px; height: 560px; background: #fff; position: relative; 
            overflow: hidden; display: flex; flex-direction: column;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3); border-radius: 10px;
            background-size: cover !important; background-position: center !important;
        }

        /* Estilo Interno do Cartaz */
        .v6-header { height: 90px; background: #d81616; display: flex; align-items: center; padding: 0 15px; color: #fff; gap: 10px; }
        .v6-logo-slot { width: 70px; height: 70px; background: rgba(255,255,255,0.2); border: 2px solid #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .v6-logo-slot img { max-width: 90%; max-height: 90%; object-fit: contain; }
        .v6-title { flex: 1; font-size: 24px; font-weight: 900; text-transform: uppercase; line-height: 1; text-align: center; }

        .v6-body { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: space-between; padding: 15px; z-index: 2; }
        .v6-prod-img { max-width: 250px; max-height: 250px; object-fit: contain; transition: 0.3s; }
        .v6-description { font-size: 24px; font-weight: 800; color: #111; text-align: center; text-transform: uppercase; line-height: 1.1; }
        
        .v6-price-circle { 
            background: #ffd31c; width: 150px; height: 150px; border-radius: 50%; 
            border: 5px solid #d81616; display: flex; flex-direction: column; 
            align-items: center; justify-content: center; color: #d81616;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1); margin-bottom: 10px;
        }

        @media print {
            @page { size: A4; margin: 0; }
            html, body {
                margin: 0;
                padding: 0;
                width: 210mm;
                height: 297mm;
            }
            .no-print { display: none !important; }
            body { background: #fff; }
            .canvas-area { padding: 0; }
            .encard-v6 {
                box-shadow: none;
                border-radius: 0;
                width: 210mm;
                height: 297mm;
            }
            .v6-header {
                height: 180px;
            }
            .v6-title {
                font-size: 48px;
                transform: scale(2);
                transform-origin: center;
                display: inline-block;
            }
            .v6-logo-slot {
                width: 140px;
                height: 140px;
            }
            .v6-logo-slot img {
                width: 120px;
                height: 120px;
            }
            .v6-prod-img{
                transform: scale(2);
                transform-origin: center;
                display: inline-block;
                

            }
            .v6-description{
                margin-top:-140px;
                margin-bottom:-20px;
                transform: scale(2);
                transform-origin: center;
                display: inline-block;
            }
            .v6-price-circle{
            background: #ffd31c; width: 150px; height: 150px; border-radius: 50%; 
            border: 5px solid #d81616; display: flex; flex-direction: column; 
            align-items: center; justify-content: center; color: #d81616;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1); margin-bottom: 10px;

                transform: scale(2);
                transform-origin: center;
                display: inline-block;
            }
            #viewPriceSymbol{
                 margin-left:60px;
            }
            #viewPrice{
                margin-left:3px;
            }
           
            #viewUnit{
                margin-left:45px;
            }
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
                <div class="fw-bold"><?= htmlspecialchars($_SESSION['user']['name'] ?? 'Demo') ?></div>
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

        <div class="col-lg-4 col-md-4 py-4 no-print">
            <div class="control-panel">
                <h5 class="mb-4 fw-bold text-warning">Configurações do Cartaz</h5>
                
                <div class="mb-3">
                    <label class="control-label">Cor do cabeçalho</label>
                    <input type="color" id="headerColor" class="form-control form-control-sm" value="#d81616">
                </div>
                <div class="mb-3">
                    <label class="control-label d-flex justify-content-between align-items-center">
                        <span>Título da Promoção</span>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-light" id="titleSmaller">A-</button>
                            <button type="button" class="btn btn-sm btn-outline-light" id="titleBigger">A+</button>
                        </div>
                    </label>
                    <input type="text" id="inTitle" class="form-control form-control-sm" value="OFERTA ESPECIAL">
                    <input type="color" id="titleColor" class="form-control form-control-sm mt-2 p-1" value="#ffffff">
                </div>

                <div class="upload-box">
                    <label class="control-label">Logo da Empresa</label>
                    <input type="file" id="upLogo" class="form-control form-control-sm" accept="image/*">
                </div>

                <div class="upload-box">
                    <label class="control-label">Imagem do Produto</label>
                    <input type="file" id="upProduct" class="form-control form-control-sm" accept="image/*">
                </div>

                <div class="mb-3">
                    <label class="control-label d-flex justify-content-between align-items-center">
                        <span>Descrição do Produto</span>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-light" id="descSmaller">A-</button>
                            <button type="button" class="btn btn-sm btn-outline-light" id="descBigger">A+</button>
                        </div>
                    </label>
                    <textarea id="inDesc" class="form-control form-control-sm" rows="2">PRODUTO DE EXEMPLO 1KG</textarea>
                    <input type="color" id="descColor" class="form-control form-control-sm mt-2 p-1" value="#111111">
                </div>

                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="control-label d-flex justify-content-between align-items-center">
                            <span>Preço</span>
                            <div>
                                <button type="button" id="priceSmaller" class="btn btn-sm btn-outline-light">A-</button>
                                <button type="button" id="priceBigger" class="btn btn-sm btn-outline-light">A+</button>
                            </div>
                        </label>
                        <input type="text" id="inPrice" class="form-control fw-bold text-danger" value="9,99">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="control-label">Unidade</label>
                        <input type="text" id="inUnit" class="form-control" value="UN/KG">
                    </div>
                </div>

                <div class="upload-box">
                    <label class="control-label">Fundo do Cartaz</label>
                    <input type="file" id="upBg" class="form-control form-control-sm" accept="image/*">
                </div>

                <div class="d-grid gap-2 mt-4">
                    <button class="btn btn-warning fw-bold" onclick="window.print()">IMPRIMIR</button>
                    <button class="btn btn-primary fw-bold" id="btnPng">BAIXAR PNG</button>
                </div>
            </div>
        </div>

        <div class="col-lg-5 col-md-4 canvas-area">
            <div class="encard-v6" id="cartazV6">
                <div class="v6-header">
                    <div class="v6-logo-slot">
                        <img id="viewLogo" src="https://via.placeholder.com/100?text=LOGO" alt="Logo">
                    </div>
                    <div class="v6-title" id="viewTitle">OFERTA ESPECIAL</div>
                </div>

                <div class="v6-body">
                    <img id="viewProd" src="https://via.placeholder.com/300?text=PRODUTO" class="v6-prod-img">
                    <div class="v6-description" id="viewDesc">PRODUTO DE EXEMPLO 1KG</div>
                    
                    <div class="v6-price-circle">
                        <small id="viewPriceSymbol" style="font-size: 18px; font-weight: 700;">R$</small>
                        <div id="viewPrice" style="font-size: 60px; font-weight: 900; line-height: 1;">9,99</div>
                        <small id="viewUnit" style="font-size: 16px; font-weight: 800;">UN/KG</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
    // Mapeamento de inputs e views
    const inputs = {
        title: document.getElementById('inTitle'),
        desc: document.getElementById('inDesc'),
        price: document.getElementById('inPrice'),
        unit: document.getElementById('inUnit'),
        upLogo: document.getElementById('upLogo'),
        upProd: document.getElementById('upProduct'),
        upBg: document.getElementById('upBg')
    };

    const colorInputs = {
        title: document.getElementById('titleColor'),
        desc: document.getElementById('descColor'),
        header: document.getElementById('headerColor')
    };

    const sizeControls = {
        titleSmaller: document.getElementById('titleSmaller'),
        titleBigger: document.getElementById('titleBigger'),
        descSmaller: document.getElementById('descSmaller'),
        descBigger: document.getElementById('descBigger')
    };

    const views = {
        title: document.getElementById('viewTitle'),
        desc: document.getElementById('viewDesc'),
        price: document.getElementById('viewPrice'),
        unit: document.getElementById('viewUnit'),
        logo: document.getElementById('viewLogo'),
        prod: document.getElementById('viewProd'),
        cartaz: document.getElementById('cartazV6')
    };
    const headerView = document.querySelector('.v6-header');
    const priceSizeControls = {
        smaller: document.getElementById('priceSmaller'),
        bigger: document.getElementById('priceBigger')
    };

    // Atualização de textos em tempo real
    inputs.title.addEventListener('input', () => views.title.innerText = inputs.title.value.toUpperCase());
    inputs.desc.addEventListener('input', () => views.desc.innerText = inputs.desc.value.toUpperCase());
    inputs.unit.addEventListener('input', () => views.unit.innerText = inputs.unit.value.toUpperCase());
    colorInputs.title.addEventListener('input', () => views.title.style.color = colorInputs.title.value);
    colorInputs.desc.addEventListener('input', () => views.desc.style.color = colorInputs.desc.value);

    let titleFontSize = 24;
    let descFontSize = 24;
    let priceFontSize = 60;

    const updateTitleSize = () => {
        views.title.style.fontSize = `${titleFontSize}px`;
    };

    const updateDescSize = () => {
        views.desc.style.fontSize = `${descFontSize}px`;
    };

    sizeControls.titleSmaller.addEventListener('click', () => {
        titleFontSize = Math.max(16, titleFontSize - 2);
        updateTitleSize();
    });
    sizeControls.titleBigger.addEventListener('click', () => {
        titleFontSize = Math.min(42, titleFontSize + 2);
        updateTitleSize();
    });
    sizeControls.descSmaller.addEventListener('click', () => {
        descFontSize = Math.max(14, descFontSize - 2);
        updateDescSize();
    });
    sizeControls.descBigger.addEventListener('click', () => {
        descFontSize = Math.min(40, descFontSize + 2);
        updateDescSize();
    });
    updateTitleSize();
    updateDescSize();
    colorInputs.header.addEventListener('input', () => {
        headerView.style.background = colorInputs.header.value;
    });
    const formatPrice = (value) => {
        const cleaned = value.replace(/[^\d,\.]/g, '').replace(',', '.');
        const number = parseFloat(cleaned);
        if (isNaN(number)) {
            return { main: '0', cents: ',00' };
        }
        const parts = number.toFixed(2).split('.');
        return { main: parts[0], cents: `,${parts[1]}` };
    };

    function updatePriceDisplay() {
        const parts = formatPrice(inputs.price.value);
        views.price.innerHTML = `<strong>${parts.main}</strong>${parts.cents}`;
        views.price.style.fontSize = `${priceFontSize}px`;
    };

    priceSizeControls.smaller.addEventListener('click', () => {
        priceFontSize = Math.max(36, priceFontSize - 2);
        updatePriceDisplay();
    });
    priceSizeControls.bigger.addEventListener('click', () => {
        priceFontSize = Math.min(120, priceFontSize + 2);
        updatePriceDisplay();
    });
    updatePriceDisplay();
    inputs.price.addEventListener('input', () => {
        views.price.innerText = inputs.price.value;
        updatePriceDisplay();
    });

    // Função de Preview de Imagem (Logo, Produto e Fundo)
    function setupPreview(inputEl, viewEl, isBg = false) {
        inputEl.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    if (isBg) {
                        viewEl.style.backgroundImage = `url('${e.target.result}')`;
                    } else {
                        viewEl.src = e.target.result;
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }

    setupPreview(inputs.upLogo, views.logo);
    setupPreview(inputs.upProd, views.prod);
    setupPreview(inputs.upBg, views.cartaz, true);

    // Download PNG
    document.getElementById('btnPng').addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;
        btn.innerText = "GERANDO...";

        html2canvas(views.cartaz, {
            scale: 2,
            useCORS: true,
            backgroundColor: null
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = `cartaz-${Date.now()}.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();
            btn.disabled = false;
            btn.innerText = "BAIXAR PNG";
        });
    });
</script>
</body>
</html>
