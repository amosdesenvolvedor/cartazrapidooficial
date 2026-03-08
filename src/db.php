<?php
// Carrega variáveis do .env local, se existir (para ambientes sem suporte automático).
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$k, $v] = array_pad(explode('=', $line, 2), 2, null);
        if ($k !== null && $v !== null && !getenv($k)) {
            putenv("$k=$v");
        }
    }
}

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_DATABASE') ?: 'cartaz';
$dbUser = getenv('DB_USERNAME') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Erro ao conectar ao banco: ' . $e->getMessage());
}

// Cria tabela se não existir.
$pdo->exec("
    CREATE TABLE IF NOT EXISTS clients_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NULL UNIQUE,
        role VARCHAR(60) DEFAULT 'usuario',
        user_id INT NULL,
        razao_social VARCHAR(180) NULL,
        fantasia VARCHAR(180) NULL,
        cpf_cnpj VARCHAR(32) NULL,
        inscricao_estadual VARCHAR(64) NULL,
        logradouro VARCHAR(180) NULL,
        numero VARCHAR(32) NULL,
        bairro VARCHAR(120) NULL,
        cep VARCHAR(20) NULL,
        cidade VARCHAR(120) NULL,
        pais VARCHAR(80) NULL,
        current_plan_id INT NULL,
        free_trial_used TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Ajustes de coluna em clients_users para bases existentes.
$clientCols = [
    'user_id' => "ALTER TABLE clients_users ADD COLUMN user_id INT NULL AFTER role",
    'razao_social' => "ALTER TABLE clients_users ADD COLUMN razao_social VARCHAR(180) NULL AFTER user_id",
    'fantasia' => "ALTER TABLE clients_users ADD COLUMN fantasia VARCHAR(180) NULL AFTER razao_social",
    'cpf_cnpj' => "ALTER TABLE clients_users ADD COLUMN cpf_cnpj VARCHAR(32) NULL AFTER fantasia",
    'inscricao_estadual' => "ALTER TABLE clients_users ADD COLUMN inscricao_estadual VARCHAR(64) NULL AFTER cpf_cnpj",
    'logradouro' => "ALTER TABLE clients_users ADD COLUMN logradouro VARCHAR(180) NULL AFTER inscricao_estadual",
    'numero' => "ALTER TABLE clients_users ADD COLUMN numero VARCHAR(32) NULL AFTER logradouro",
    'bairro' => "ALTER TABLE clients_users ADD COLUMN bairro VARCHAR(120) NULL AFTER numero",
    'cep' => "ALTER TABLE clients_users ADD COLUMN cep VARCHAR(20) NULL AFTER bairro",
    'cidade' => "ALTER TABLE clients_users ADD COLUMN cidade VARCHAR(120) NULL AFTER cep",
    'pais' => "ALTER TABLE clients_users ADD COLUMN pais VARCHAR(80) NULL AFTER cidade",
    'current_plan_id' => "ALTER TABLE clients_users ADD COLUMN current_plan_id INT NULL AFTER pais",
    'free_trial_used' => "ALTER TABLE clients_users ADD COLUMN free_trial_used TINYINT(1) NOT NULL DEFAULT 0 AFTER current_plan_id",
    'updated_at' => "ALTER TABLE clients_users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
];
foreach ($clientCols as $col => $alter) {
    $exists = $pdo->query("SHOW COLUMNS FROM clients_users LIKE '$col'")->fetch();
    if (!$exists) {
        $pdo->exec($alter);
    }
}
// Ajusta email para permitir NULL e manter UNIQUE (MySQL permite múltiplos NULLs).
$emailNull = $pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients_users' AND COLUMN_NAME = 'email'")->fetchColumn();
if ($emailNull && $emailNull !== 'YES') {
    $pdo->exec("ALTER TABLE clients_users MODIFY email VARCHAR(150) NULL");
}
// Remove UNIQUE em clients_users.email para permitir múltiplos cadastros com o mesmo e-mail de acesso.
$emailUnique = $pdo->query("SHOW INDEX FROM clients_users WHERE Column_name = 'email' AND Non_unique = 0")->fetch();
if ($emailUnique && !empty($emailUnique['Key_name'])) {
    $idx = $emailUnique['Key_name'];
    try {
        $pdo->exec("ALTER TABLE clients_users DROP INDEX `$idx`");
    } catch (PDOException $e) {
        // ignora se já removido
    }
}

// Tabela de usuários do sistema (superadmin e clientes).
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        slug VARCHAR(160) NOT NULL UNIQUE,
        role ENUM('superadmin','cliente') NOT NULL DEFAULT 'cliente',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS template5_backgrounds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_slug VARCHAR(160) NOT NULL DEFAULT '',
        name VARCHAR(120) NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_t5_bg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
$hasSlugColumn = $pdo->query("SHOW COLUMNS FROM template5_backgrounds LIKE 'user_slug'")->fetch();
if (!$hasSlugColumn) {
    $pdo->exec("ALTER TABLE template5_backgrounds ADD COLUMN user_slug VARCHAR(160) NOT NULL AFTER user_id");
}

// Garante coluna slug para bases antigas.
$hasSlug = $pdo->query("SHOW COLUMNS FROM users LIKE 'slug'")->fetch();
if (!$hasSlug) {
    // Primeiro adiciona coluna permitindo NULL para evitar conflito em tabelas existentes.
    $pdo->exec("ALTER TABLE users ADD COLUMN slug VARCHAR(160) NULL AFTER password_hash");
}

// Gera slugs para registros antigos sem slug.
if (!$hasSlug) {
    $slugStmt = $pdo->query("SELECT id, name FROM users WHERE slug IS NULL OR slug = ''");
    $rows = $slugStmt->fetchAll();
    if ($rows) {
        $slugify = function ($text) {
            $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
            $text = strtolower(trim($text));
            $text = preg_replace('/[^a-z0-9]+/', '-', $text);
            $text = trim($text, '-');
            return $text ?: 'user';
        };
        foreach ($rows as $row) {
            $base = $slugify($row['name']);
            $slug = $base;
            $suffix = 1;
            while (true) {
                $check = $pdo->prepare("SELECT id FROM users WHERE slug = ? LIMIT 1");
                $check->execute([$slug]);
                $existsId = $check->fetchColumn();
                if (!$existsId || (int)$existsId === (int)$row['id']) {
                    break;
                }
                $slug = $base . '-' . $suffix++;
            }
            $update = $pdo->prepare("UPDATE users SET slug = ? WHERE id = ?");
            $update->execute([$slug, $row['id']]);
        }
    }
    // Depois reforça NOT NULL + UNIQUE.
    $pdo->exec("ALTER TABLE users MODIFY slug VARCHAR(160) NOT NULL UNIQUE");
}

// Cria um superadmin padrão se não existir.
$seedEmail = 'superadmin@cartaz.local';
$seedExists = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$seedExists->execute([$seedEmail]);
if (!$seedExists->fetchColumn()) {
    $hash = password_hash('cartaz_pass', PASSWORD_DEFAULT);
    $slug = 'superadmin';
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, slug, role) VALUES (?, ?, ?, ?, 'superadmin')");
    $stmt->execute(['Super Admin', $seedEmail, $hash, $slug]);
}

// Tabela de planos
$pdo->exec("
    CREATE TABLE IF NOT EXISTS plans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL UNIQUE,
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        duration_days INT NOT NULL,
        billing_cycle ENUM('demo','monthly','annual') NOT NULL DEFAULT 'monthly',
        description VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Tabela de assinaturas (pagamentos recorrentes)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_user_id INT NOT NULL,
        plan_id INT NOT NULL,
        mp_preference_id VARCHAR(120) NOT NULL,
        mp_payment_id VARCHAR(120) NULL,
        preapproval_id VARCHAR(120) NULL,
        plan_type VARCHAR(50) NULL,
        status ENUM('pending','paid','cancelled','expired','trial','authorized','paused') NOT NULL DEFAULT 'pending',
        payment_method ENUM('pix','boleto','credit_card') NULL,
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        started_at DATETIME NULL,
        expires_at DATETIME NULL,
        trial_ends_at DATETIME NULL,
        next_payment_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_client (client_user_id),
        INDEX idx_plan (plan_id),
        INDEX idx_pref (mp_preference_id),
        INDEX idx_payment (mp_payment_id),
        INDEX idx_preapproval (preapproval_id),
        INDEX idx_plan_type (plan_type),
        CONSTRAINT fk_sub_client FOREIGN KEY (client_user_id) REFERENCES clients_users(id) ON DELETE CASCADE,
        CONSTRAINT fk_sub_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$subscriptionCols = [
    'preapproval_id' => "ALTER TABLE subscriptions ADD COLUMN preapproval_id VARCHAR(120) NULL AFTER mp_payment_id",
    'plan_type' => "ALTER TABLE subscriptions ADD COLUMN plan_type VARCHAR(50) NULL AFTER preapproval_id",
    'status' => "ALTER TABLE subscriptions MODIFY status ENUM('pending','paid','cancelled','expired','trial','authorized','paused') NOT NULL DEFAULT 'pending'",
    'trial_ends_at' => "ALTER TABLE subscriptions ADD COLUMN trial_ends_at DATETIME NULL AFTER expires_at",
    'next_payment_at' => "ALTER TABLE subscriptions ADD COLUMN next_payment_at DATETIME NULL AFTER trial_ends_at",
];
foreach ($subscriptionCols as $col => $alter) {
    $exists = $pdo->query("SHOW COLUMNS FROM subscriptions LIKE '$col'")->fetch();
    if (!$exists) {
        $pdo->exec($alter);
    } elseif ($col === 'status') {
        try {
            $pdo->exec($alter);
        } catch (PDOException $e) {
            // Already has the enum values
        }
    }
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS payment_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notification_id VARCHAR(255) NOT NULL UNIQUE,
        subscription_id INT NULL,
        payment_id VARCHAR(120) NOT NULL,
        preference_id VARCHAR(120) NULL,
        topic VARCHAR(64) NOT NULL DEFAULT 'payment',
        payload JSON NOT NULL,
        status ENUM('received','processed','skipped') NOT NULL DEFAULT 'received',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_payment_notifications_payment (payment_id),
        INDEX idx_payment_notifications_subscription (subscription_id),
        CONSTRAINT fk_payment_notifications_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Semeia planos oficiais do módulo
$defaultPlans = [
    ['Plano Gratuito 10 dias', 0.00, 10, 'demo', 'Período gratuito único de 10 dias'],
    ['Plano Mensal', 79.90, 30, 'monthly', 'Renovação mensal, aceita PIX, boleto e cartão'],
    ['Plano Anual 12x', 358.80, 365, 'annual', 'R$ 29,90/mês no cartão de crédito (parcelamento pode gerar acréscimos)'],
];
foreach ($defaultPlans as [$pName, $pPrice, $pDays, $pCycle, $pDesc]) {
    $ins = $pdo->prepare("
        INSERT INTO plans (name, price, duration_days, billing_cycle, description)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE price = VALUES(price), duration_days = VALUES(duration_days), billing_cycle = VALUES(billing_cycle), description = VALUES(description)
    ");
    $ins->execute([$pName, $pPrice, $pDays, $pCycle, $pDesc]);
}

// Remove tabelas financeiras caso existam (feature descontinuada)
$pdo->exec("DROP TABLE IF EXISTS finance_payments");
