<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class ApiQueryService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function paginate(array $config, array $query, ?int $companyId, bool $superAdmin): array
    {
        $page = max(1, min(1000, (int) ($query['page'] ?? 1)));
        $perPage = max(1, min(100, (int) ($query['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;
        $params = [];
        $where = [$config['alias'] . '.deleted_at IS NULL'];

        if (($config['tenant'] ?? true) && ! $superAdmin) {
            $tenantColumn = $config['tenant_column'] ?? $config['alias'] . '.company_id';
            $where[] = $tenantColumn . ' = :company_id';
            $params['company_id'] = $companyId;
        }

        if (! empty($query['status']) && in_array('status', $config['filterable'], true)) {
            $where[] = $config['alias'] . '.status = :status';
            $params['status'] = substr((string) $query['status'], 0, 40);
        }

        if (! empty($query['q']) && $config['search'] !== []) {
            $search = [];
            foreach ($config['search'] as $column) {
                $search[] = $column . ' LIKE :q';
            }
            $where[] = '(' . implode(' OR ', $search) . ')';
            $params['q'] = '%' . substr((string) $query['q'], 0, 120) . '%';
        }

        $sort = (string) ($query['sort'] ?? $config['default_sort']);
        $direction = strtolower((string) ($query['direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        if (! in_array($sort, $config['sortable'], true)) {
            $sort = $config['default_sort'];
        }

        $from = $config['from'];
        $whereSql = implode(' AND ', $where);
        $count = $this->db->prepare('SELECT COUNT(*) FROM ' . $from . ' WHERE ' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $sql = 'SELECT ' . implode(', ', $config['select']) . ' FROM ' . $from . ' WHERE ' . $whereSql
            . ' ORDER BY ' . $sort . ' ' . $direction . ' LIMIT :limit OFFSET :offset';
        $statement = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => array_map(fn (array $row): array => $this->transform($row, $config['hidden'] ?? []), $statement->fetchAll()),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
                'sort' => $sort,
                'direction' => strtolower($direction),
            ],
        ];
    }

    private function transform(array $row, array $hidden): array
    {
        foreach ($hidden as $field) {
            unset($row[$field]);
        }

        return $row;
    }
}
