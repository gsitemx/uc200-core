<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class CallLogService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function ingestFromCdr(array $cdr): ?array
    {
        $src = trim((string) ($cdr['src'] ?? ''));
        $dst = trim((string) ($cdr['dst'] ?? ''));
        $uniqueId = trim((string) ($cdr['uniqueid'] ?? ''));

        if ($src === '' || $dst === '' || $uniqueId === '') {
            return null;
        }

        $srcExtension = $this->extensionByValue(null, $src);
        $dstExtension = $this->extensionByValue($srcExtension['company_id'] ?? null, $dst);
        $companyId = (int) (($srcExtension['company_id'] ?? null) ?: ($dstExtension['company_id'] ?? null) ?: 0);

        if ($companyId <= 0) {
            return null;
        }

        $direction = 'internal';
        $extensionId = null;
        $contact = null;

        if ($srcExtension !== null && $dstExtension === null) {
            $direction = 'outbound';
            $extensionId = (string) $srcExtension['id'];
            $contact = $this->findContactByNumber($companyId, $dst);
        } elseif ($srcExtension === null && $dstExtension !== null) {
            $direction = 'inbound';
            $extensionId = (string) $dstExtension['id'];
            $contact = $this->findContactByNumber($companyId, $src);
        } elseif ($srcExtension !== null && $dstExtension !== null) {
            $direction = 'internal';
            $extensionId = (string) $srcExtension['id'];
            $contact = $this->findContactByExtension($companyId, (string) $dstExtension['id'])
                ?? $this->findContactByExtension($companyId, (string) $srcExtension['id']);
        } else {
            $contact = $this->findContactByNumber($companyId, $src) ?? $this->findContactByNumber($companyId, $dst);
        }

        $payload = [
            'uuid' => uuid(),
            'company_id' => $companyId,
            'extension_id' => $extensionId,
            'contact_id' => $contact['id'] ?? null,
            'direction' => $direction,
            'src' => $src,
            'dst' => $dst,
            'start_time' => $this->dateTimeValue($cdr['start'] ?? $cdr['calldate'] ?? null),
            'answer_time' => $this->dateTimeValue($cdr['answer'] ?? null),
            'end_time' => $this->dateTimeValue($cdr['end'] ?? null),
            'duration' => max(0, (int) ($cdr['duration'] ?? 0)),
            'billsec' => max(0, (int) ($cdr['billsec'] ?? 0)),
            'disposition' => strtoupper(trim((string) ($cdr['disposition'] ?? 'NO ANSWER'))),
            'recording_path' => $this->nullableString($cdr['recording_path'] ?? $cdr['recordingfile'] ?? null),
            'uniqueid' => $uniqueId,
            'linkedid' => $this->nullableString($cdr['linkedid'] ?? null),
            'raw_payload' => json_encode($cdr, JSON_THROW_ON_ERROR),
        ];

        $this->db->prepare(
            'INSERT INTO crm_call_logs
             (uuid, company_id, extension_id, contact_id, direction, src, dst, start_time, answer_time, end_time, duration, billsec, disposition, recording_path, uniqueid, linkedid, raw_payload)
             VALUES
             (:uuid, :company_id, :extension_id, :contact_id, :direction, :src, :dst, :start_time, :answer_time, :end_time, :duration, :billsec, :disposition, :recording_path, :uniqueid, :linkedid, :raw_payload)
             ON DUPLICATE KEY UPDATE
                extension_id = VALUES(extension_id),
                contact_id = VALUES(contact_id),
                direction = VALUES(direction),
                src = VALUES(src),
                dst = VALUES(dst),
                start_time = VALUES(start_time),
                answer_time = VALUES(answer_time),
                end_time = VALUES(end_time),
                duration = VALUES(duration),
                billsec = VALUES(billsec),
                disposition = VALUES(disposition),
                recording_path = VALUES(recording_path),
                linkedid = VALUES(linkedid),
                raw_payload = VALUES(raw_payload),
                updated_at = NOW()'
        )->execute($payload);

        return $payload;
    }

    public function dashboardMetrics(?int $companyId = null): array
    {
        return [
            'active_calls' => $this->countCalls($companyId, 'end_time IS NULL'),
            'calls_today' => $this->countCalls($companyId, 'DATE(start_time) = CURDATE()'),
            'missed_calls' => $this->countCalls($companyId, 'DATE(start_time) = CURDATE() AND disposition IN ("NO ANSWER", "BUSY", "FAILED", "CANCEL")'),
            'registered_extensions' => $this->countEndpoints($companyId, 'sip_status = "registered"'),
            'agents_online' => $this->countAgentsOnline($companyId),
        ];
    }

    public function callerLookup(int $companyId, string $number): ?array
    {
        $contact = $this->findContactByNumber($companyId, $number);
        if ($contact === null) {
            return null;
        }

        $contact['last_interaction_at'] = $this->lastInteractionForContact((int) $contact['company_id'], (int) $contact['id']);
        $contact['linked_extension_status'] = $this->extensionStatusLabel($contact['sip_status'] ?? null, $contact['presence_status'] ?? null);

        return $contact;
    }

    public function lastInteractionForContact(int $companyId, int $contactId): ?string
    {
        return $this->lastInteractionAt($companyId, $contactId);
    }

    public function contactCalls(int $companyId, int $contactId, int $limit = 100): array
    {
        $statement = $this->db->prepare(
            'SELECT cl.*, e.extension_number, e.display_name
             FROM crm_call_logs cl
             LEFT JOIN ps_endpoints e ON e.id = cl.extension_id
             WHERE cl.company_id = :company_id AND cl.contact_id = :contact_id AND cl.deleted_at IS NULL
             ORDER BY cl.start_time DESC
             LIMIT ' . max(1, $limit)
        );
        $statement->execute(['company_id' => $companyId, 'contact_id' => $contactId]);

        return $statement->fetchAll();
    }

    public function contactCallSummary(int $companyId, int $contactId): array
    {
        $statement = $this->db->prepare(
            'SELECT
                COUNT(*) AS total_calls,
                SUM(CASE WHEN direction = "inbound" THEN 1 ELSE 0 END) AS inbound_calls,
                SUM(CASE WHEN direction = "outbound" THEN 1 ELSE 0 END) AS outbound_calls,
                SUM(CASE WHEN disposition IN ("NO ANSWER", "BUSY", "FAILED", "CANCEL") THEN 1 ELSE 0 END) AS missed_calls,
                COALESCE(SUM(billsec), 0) AS total_billsec
             FROM crm_call_logs
             WHERE company_id = :company_id AND contact_id = :contact_id AND deleted_at IS NULL'
        );
        $statement->execute(['company_id' => $companyId, 'contact_id' => $contactId]);
        $summary = $statement->fetch();

        return is_array($summary) ? $summary : [
            'total_calls' => 0,
            'inbound_calls' => 0,
            'outbound_calls' => 0,
            'missed_calls' => 0,
            'total_billsec' => 0,
        ];
    }

    public function activityRows(?int $companyId = null, int $limit = 150): array
    {
        $sql =
            'SELECT cl.*, ct.uuid AS contact_uuid, ct.full_name AS contact_name, ct.tags,
                    ca.trade_name AS account_name, e.extension_number, e.display_name
             FROM crm_call_logs cl
             LEFT JOIN crm_contacts ct ON ct.id = cl.contact_id
             LEFT JOIN crm_accounts ca ON ca.id = ct.account_id
             LEFT JOIN ps_endpoints e ON e.id = cl.extension_id
             WHERE cl.deleted_at IS NULL';
        $params = [];

        if ($companyId !== null) {
            $sql .= ' AND cl.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $sql .= ' ORDER BY cl.start_time DESC LIMIT ' . max(1, $limit);
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function syncRecentFromCdr(int $limit = 200): int
    {
        if (! $this->tableExists('cdr')) {
            return 0;
        }

        $statement = $this->db->query(
            'SELECT calldate, src, dst, dcontext, channel, dstchannel, lastapp, lastdata, duration, billsec, disposition, amaflags, accountcode, uniqueid, linkedid, sequence
             FROM cdr
             ORDER BY calldate DESC
             LIMIT ' . max(1, $limit)
        );

        $count = 0;
        foreach ($statement->fetchAll() as $row) {
            if ($this->ingestFromCdr($row) !== null) {
                $count++;
            }
        }

        return $count;
    }

    public function extensionStatusLabel(?string $sipStatus, ?string $presenceStatus): string
    {
        $presence = strtolower((string) $presenceStatus);
        $sip = strtolower((string) $sipStatus);

        if (in_array($presence, ['busy', 'ringing'], true)) {
            return 'En llamada';
        }

        if (in_array($presence, ['available', 'online'], true)) {
            return 'Disponible';
        }

        if ($sip === 'registered' || $sip === 'reachable') {
            return 'Registrado';
        }

        return 'Offline';
    }

    private function countCalls(?int $companyId, string $condition): int
    {
        $sql = 'SELECT COUNT(*) FROM crm_call_logs WHERE deleted_at IS NULL AND ' . $condition;
        $params = [];

        if ($companyId !== null) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    private function countEndpoints(?int $companyId, string $condition): int
    {
        $sql = 'SELECT COUNT(*) FROM ps_endpoints WHERE deleted_at IS NULL AND ' . $condition;
        $params = [];

        if ($companyId !== null) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    private function countAgentsOnline(?int $companyId): int
    {
        if ($this->tableExists('pbx_queue_agent_states')) {
            $sql = 'SELECT COUNT(DISTINCT endpoint_id) FROM pbx_queue_agent_states WHERE state IN ("online", "available", "busy", "ringing")';
            $params = [];

            if ($companyId !== null) {
                $sql .= ' AND company_id = :company_id';
                $params['company_id'] = $companyId;
            }

            $statement = $this->db->prepare($sql);
            $statement->execute($params);

            return (int) $statement->fetchColumn();
        }

        return $this->countEndpoints($companyId, 'presence_status IN ("online", "available", "busy", "ringing")');
    }

    private function lastInteractionAt(int $companyId, int $contactId): ?string
    {
        $statement = $this->db->prepare(
            'SELECT MAX(event_time) FROM (
                SELECT occurred_at AS event_time FROM crm_activities WHERE company_id = :company_id AND contact_id = :contact_id AND deleted_at IS NULL
                UNION ALL
                SELECT start_time AS event_time FROM crm_call_logs WHERE company_id = :company_id AND contact_id = :contact_id AND deleted_at IS NULL
             ) interactions'
        );
        $statement->execute(['company_id' => $companyId, 'contact_id' => $contactId]);
        $value = $statement->fetchColumn();

        return $value !== false ? (string) $value : null;
    }

    private function findContactByNumber(int $companyId, string $number): ?array
    {
        $digits = $this->normalizePhone($number);
        if ($digits === '') {
            return null;
        }

        $statement = $this->db->prepare(
            'SELECT ct.*, ca.trade_name AS account_name, e.extension_number, e.display_name, e.sip_status, e.presence_status
             FROM crm_contacts ct
             LEFT JOIN crm_accounts ca ON ca.id = ct.account_id AND ca.deleted_at IS NULL
             LEFT JOIN ps_endpoints e ON e.id = ct.related_extension_id AND e.deleted_at IS NULL
             WHERE ct.company_id = :company_id AND ct.deleted_at IS NULL
               AND (
                    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(ct.mobile_phone, ""), " ", ""), "-", ""), "(", ""), ")", ""), "+", "") = :digits
                    OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(ct.office_phone, ""), " ", ""), "-", ""), "(", ""), ")", ""), "+", "") = :digits
                    OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(ct.related_did, ""), " ", ""), "-", ""), "(", ""), ")", ""), "+", "") = :digits
               )
             ORDER BY ct.id DESC
             LIMIT 1'
        );
        $statement->execute(['company_id' => $companyId, 'digits' => $digits]);
        $contact = $statement->fetch();

        return is_array($contact) ? $contact : null;
    }

    private function findContactByExtension(int $companyId, string $extensionId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT ct.*, ca.trade_name AS account_name, e.extension_number, e.display_name, e.sip_status, e.presence_status
             FROM crm_contacts ct
             LEFT JOIN crm_accounts ca ON ca.id = ct.account_id AND ca.deleted_at IS NULL
             LEFT JOIN ps_endpoints e ON e.id = ct.related_extension_id AND e.deleted_at IS NULL
             WHERE ct.company_id = :company_id AND ct.related_extension_id = :extension_id AND ct.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['company_id' => $companyId, 'extension_id' => $extensionId]);
        $contact = $statement->fetch();

        return is_array($contact) ? $contact : null;
    }

    private function extensionByValue(?int $companyId, string $value): ?array
    {
        $sql = 'SELECT id, company_id, extension_number, display_name, sip_status, presence_status
                FROM ps_endpoints
                WHERE deleted_at IS NULL AND (id = :value OR extension_number = :value)';
        $params = ['value' => $value];

        if ($companyId !== null) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $sql .= ' ORDER BY company_id ASC, extension_number ASC LIMIT 1';
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $extension = $statement->fetch();

        return is_array($extension) ? $extension : null;
    }

    private function normalizePhone(string $number): string
    {
        return preg_replace('/\D+/', '', $number) ?? '';
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private function dateTimeValue(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name');
        $statement->execute(['table_name' => $table]);

        return (int) $statement->fetchColumn() > 0;
    }
}
