<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use PDO;

final class LicenseService
{
    private const CACHE_TTL = 300;

    public function __construct(private readonly PDO $db)
    {
    }

    public function activeLicenseForCompany(int $companyId): ?array
    {
        $cacheKey = 'license_cache_' . $companyId;
        $cached = Session::get($cacheKey);

        if (is_array($cached) && ($cached['expires_cache_at'] ?? 0) > time()) {
            return $cached['license'];
        }

        $this->expireOldLicenses();

        $statement = $this->db->prepare(
            'SELECT cl.*, p.name AS plan_name, p.slug AS plan_slug
             FROM company_licenses cl
             INNER JOIN plans p ON p.id = cl.plan_id
             WHERE cl.company_id = :company_id
               AND cl.status = "active"
               AND cl.deleted_at IS NULL
               AND p.deleted_at IS NULL
               AND (cl.expires_at IS NULL OR cl.expires_at >= NOW())
             ORDER BY cl.expires_at IS NULL DESC, cl.expires_at DESC, cl.id DESC
             LIMIT 1'
        );
        $statement->execute(['company_id' => $companyId]);
        $license = $statement->fetch();
        $license = $license === false ? null : $license;

        Session::put($cacheKey, [
            'expires_cache_at' => time() + self::CACHE_TTL,
            'license' => $license,
        ]);

        return $license;
    }

    public function hasFeature(int $companyId, string $featureSlug): bool
    {
        $license = $this->activeLicenseForCompany($companyId);

        if ($license === null) {
            return false;
        }

        $statement = $this->db->prepare(
            'SELECT pf.id
             FROM plan_features pf
             INNER JOIN features f ON f.id = pf.feature_id
             WHERE pf.plan_id = :plan_id
               AND f.slug = :feature_slug
               AND pf.is_enabled = 1
               AND pf.deleted_at IS NULL
               AND f.is_active = 1
               AND f.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'plan_id' => (int) $license['plan_id'],
            'feature_slug' => $featureSlug,
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function limit(int $companyId, string $limitKey): ?int
    {
        $license = $this->activeLicenseForCompany($companyId);

        if ($license === null) {
            return null;
        }

        $statement = $this->db->prepare(
            'SELECT limit_value
             FROM license_limits
             WHERE company_license_id = :license_id
               AND limit_key = :limit_key
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'license_id' => (int) $license['id'],
            'limit_key' => $limitKey,
        ]);
        $value = $statement->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    public function clearCache(?int $companyId = null): void
    {
        if ($companyId !== null) {
            Session::forget('license_cache_' . $companyId);
        }
    }

    public function expireOldLicenses(): int
    {
        $statement = $this->db->prepare(
            'UPDATE company_licenses
             SET status = "expired"
             WHERE status = "active"
               AND expires_at IS NOT NULL
               AND expires_at < NOW()
               AND deleted_at IS NULL'
        );
        $statement->execute();

        return $statement->rowCount();
    }
}
