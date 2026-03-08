<?php
// Funções utilitárias centralizadas para assinaturas e planos.
require_once __DIR__ . '/../vendor/autoload.php';

if (!function_exists('resolvePlanType')) {
    function resolvePlanType(array $plan): string
    {
        $cycle = strtolower($plan['billing_cycle'] ?? '');
        if ($cycle === 'monthly') {
            return 'mensal';
        }
        if ($cycle === 'annual') {
            return 'anual';
        }
        if ($cycle === 'demo') {
            return 'trial';
        }
        return $plan['name'] ?? 'plano';
    }
}

/**
 * Garante que exista um registro em clients_users para o usuário.
 */
function ensureClientForUser(PDO $pdo, int $userId, string $name, string $email): array
{
    $stmt = $pdo->prepare("SELECT * FROM clients_users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $client = $stmt->fetch();
    if ($client) {
        return $client;
    }

    $insert = $pdo->prepare("
        INSERT INTO clients_users (name, email, role, user_id, razao_social, fantasia, cpf_cnpj, inscricao_estadual, logradouro, numero, bairro, cep, cidade, pais)
        VALUES (?, ?, 'cliente', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->execute([$name, $email, $userId, $name, null, null, null, null, null, null, null, null, 'Brasil']);

    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/**
 * Retorna a assinatura ativa (status paid e não expirada) mais recente.
 */
function getActiveSubscription(PDO $pdo, int $clientId): ?array
{
    $sql = "
        SELECT s.*, p.name AS plan_name, p.duration_days, p.billing_cycle, p.price
        FROM subscriptions s
        INNER JOIN plans p ON p.id = s.plan_id
        WHERE s.client_user_id = ?
          AND s.status IN ('paid', 'trial')
          AND s.expires_at >= NOW()
        ORDER BY s.expires_at DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$clientId]);
    $sub = $stmt->fetch();
    return $sub ?: null;
}

/**
 * Busca um plano por id.
 */
function getPlan(PDO $pdo, int $planId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? LIMIT 1");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();
    return $plan ?: null;
}

/**
 * Atualiza plano atual do cliente.
 */
function updateCurrentPlan(PDO $pdo, int $clientId, int $planId): void
{
    $stmt = $pdo->prepare("UPDATE clients_users SET current_plan_id = ? WHERE id = ?");
    $stmt->execute([$planId, $clientId]);
}

/**
 * Marca uso do trial gratuito.
 */
function markFreeTrialUsed(PDO $pdo, int $clientId): void
{
    $stmt = $pdo->prepare("UPDATE clients_users SET free_trial_used = 1 WHERE id = ?");
    $stmt->execute([$clientId]);
}
