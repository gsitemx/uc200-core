<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\CallLogService;
use PDO;

final class SyncAsteriskCdrJob
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function handle(array $rows = []): int
    {
        $service = new CallLogService($this->db);
        $count = 0;

        foreach ($rows as $row) {
            if ($service->ingestFromCdr($row) !== null) {
                $count++;
            }
        }

        return $count;
    }
}
