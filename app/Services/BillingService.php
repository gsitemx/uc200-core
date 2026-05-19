<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class BillingService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function suspendExpiredTenants(int $graceDays = 7): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', strtotime('-' . max(0, $graceDays) . ' days'));
        $statement = $this->db->prepare(
            'UPDATE companies c
             INNER JOIN billing_subscriptions s ON s.company_id = c.id
             SET c.status = "suspended", s.status = "past_due"
             WHERE s.status IN ("active", "trialing")
               AND s.current_period_end < :cutoff
               AND s.deleted_at IS NULL
               AND c.deleted_at IS NULL'
        );
        $statement->execute(['cutoff' => $cutoff]);

        return $statement->rowCount();
    }

    public function metrics(?int $resellerId = null): array
    {
        return [
            'resellers' => $this->countResellers($resellerId),
            'subscriptions' => $this->count('billing_subscriptions', $resellerId),
            'open_invoices' => $this->countInvoices('open', $resellerId),
            'mrr' => $this->mrr($resellerId),
            'usage_records' => $this->count('billing_usage_records', $resellerId),
        ];
    }

    public function recordUsage(int $companyId, string $metric, float $quantity, ?int $resellerId = null, ?string $periodStart = null, ?string $periodEnd = null): void
    {
        $this->db->prepare(
            'INSERT INTO billing_usage_records (uuid, reseller_id, company_id, metric, quantity, period_start, period_end)
             VALUES (:uuid, :reseller_id, :company_id, :metric, :quantity, :period_start, :period_end)'
        )->execute([
            'uuid' => uuid(),
            'reseller_id' => $resellerId,
            'company_id' => $companyId,
            'metric' => $metric,
            'quantity' => $quantity,
            'period_start' => $periodStart ?? gmdate('Y-m-01 00:00:00'),
            'period_end' => $periodEnd ?? gmdate('Y-m-t 23:59:59'),
        ]);
    }

    private function count(string $table, ?int $resellerId): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE deleted_at IS NULL';
        $params = [];

        if ($resellerId !== null) {
            $sql .= ' AND reseller_id = :reseller_id';
            $params['reseller_id'] = $resellerId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    private function countResellers(?int $resellerId): int
    {
        $sql = 'SELECT COUNT(*) FROM resellers WHERE deleted_at IS NULL';
        $params = [];

        if ($resellerId !== null) {
            $sql .= ' AND (id = :reseller_id OR parent_reseller_id = :reseller_id)';
            $params['reseller_id'] = $resellerId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    private function countInvoices(string $status, ?int $resellerId): int
    {
        $sql = 'SELECT COUNT(*) FROM billing_invoices WHERE status = :status AND deleted_at IS NULL';
        $params = ['status' => $status];

        if ($resellerId !== null) {
            $sql .= ' AND reseller_id = :reseller_id';
            $params['reseller_id'] = $resellerId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    private function mrr(?int $resellerId): string
    {
        $sql = 'SELECT COALESCE(SUM(amount), 0) FROM billing_subscriptions WHERE status = "active" AND billing_period = "monthly" AND deleted_at IS NULL';
        $params = [];

        if ($resellerId !== null) {
            $sql .= ' AND reseller_id = :reseller_id';
            $params['reseller_id'] = $resellerId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return number_format((float) $statement->fetchColumn(), 2, '.', '');
    }
}
