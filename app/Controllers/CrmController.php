<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\ApiTokenService;
use App\Services\AuditService;
use App\Services\CallLogService;
use App\Services\PermissionService;
use App\Services\PbxOriginateService;
use PDO;

final class CrmController extends Controller
{
    private array $sources = ['manual', 'microsoft', 'google', 'whatsapp', 'api'];

    public function index(Request $request): string
    {
        return view('crm/index', [
            'title' => 'CRM',
            'contacts' => $this->contacts((string) $request->input('q', '')),
            'accounts' => $this->accounts((string) $request->input('q', '')),
            'stats' => $this->stats(),
            'activity' => $this->callLogService()->activityRows($this->tenantCompanyId()),
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
            'query' => (string) $request->input('q', ''),
        ]);
    }

    public function contactsIndex(Request $request): string
    {
        return view('crm/contacts', [
            'title' => 'Contactos CRM',
            'contacts' => $this->contacts((string) $request->input('q', '')),
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
            'query' => (string) $request->input('q', ''),
        ]);
    }

    public function activityIndex(Request $request): string
    {
        return view('crm/activity', [
            'title' => 'Actividad CRM',
            'calls' => $this->callLogService()->activityRows($this->tenantCompanyId()),
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]);
    }

    public function createContact(Request $request): string
    {
        return $this->contactForm([], '/crm/contacts/store', 'create');
    }

    public function editContact(Request $request): string
    {
        return $this->contactForm($this->contactFromRequest($request), '/crm/contacts/update', 'edit');
    }

    public function showContact(Request $request): string
    {
        $contact = $this->contactFromRequest($request);

        return view('crm/contact-show', [
            'title' => 'Contacto',
            'contact' => $contact,
            'activities' => $this->activities('contact_id', (int) $contact['id']),
            'callSummary' => $this->callLogService()->contactCallSummary((int) $contact['company_id'], (int) $contact['id']),
            'callLogs' => $this->callLogService()->contactCalls((int) $contact['company_id'], (int) $contact['id']),
            'recordings' => $this->relatedRecordings($contact),
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]);
    }

    public function storeContact(Request $request): void
    {
        $data = $this->contactPayload($request);
        $errors = $this->validateContact($data);
        if ($errors !== []) {
            $this->back('/crm/contacts/create', $errors, $data);
        }

        $this->db()->prepare(
            'INSERT INTO crm_contacts
             (uuid, company_id, account_id, related_extension_id, related_did, full_name, organization, job_title, email,
              mobile_phone, office_phone, tags, notes, source, external_provider, external_id, sync_enabled, status)
             VALUES
             (:uuid, :company_id, :account_id, :related_extension_id, :related_did, :full_name, :organization, :job_title, :email,
              :mobile_phone, :office_phone, :tags, :notes, :source, :external_provider, :external_id, :sync_enabled, :status)'
        )->execute(['uuid' => uuid()] + $data);

        (new AuditService($this->db()))->record('crm.contact.created', 'crm_contacts', null, ['name' => $data['full_name']], (int) $data['company_id']);
        Session::flash('success', 'Contacto creado correctamente.');
        redirect('/crm');
    }

    public function updateContact(Request $request): void
    {
        $contact = $this->contactFromRequest($request);
        $data = $this->contactPayload($request);
        $data['company_id'] = (int) $contact['company_id'];
        $errors = $this->validateContact($data);
        if ($errors !== []) {
            $this->back('/crm/contacts/edit?id=' . $contact['uuid'], $errors, $data);
        }
        unset($data['company_id']);

        $this->db()->prepare(
            'UPDATE crm_contacts
             SET account_id = :account_id, related_extension_id = :related_extension_id, related_did = :related_did, full_name = :full_name,
                 organization = :organization, job_title = :job_title, email = :email, mobile_phone = :mobile_phone,
                 office_phone = :office_phone, tags = :tags, notes = :notes, source = :source,
                 external_provider = :external_provider, external_id = :external_id, sync_enabled = :sync_enabled, status = :status
             WHERE id = :id'
        )->execute($data + ['id' => (int) $contact['id']]);

        (new AuditService($this->db()))->record('crm.contact.updated', 'crm_contacts', (int) $contact['id'], ['name' => $data['full_name']], (int) $contact['company_id']);
        Session::flash('success', 'Contacto actualizado correctamente.');
        redirect('/crm/contacts/show?id=' . $contact['uuid']);
    }

    public function deleteContact(Request $request): void
    {
        $contact = $this->contactFromRequest($request);
        $this->db()->prepare('UPDATE crm_contacts SET deleted_at = NOW(), status = "inactive" WHERE id = :id')->execute(['id' => (int) $contact['id']]);
        (new AuditService($this->db()))->record('crm.contact.deleted', 'crm_contacts', (int) $contact['id'], ['name' => $contact['full_name']], (int) $contact['company_id']);
        Session::flash('success', 'Contacto eliminado correctamente.');
        redirect('/crm');
    }

    public function createAccount(Request $request): string
    {
        return $this->accountForm([], '/crm/accounts/store', 'create');
    }

    public function editAccount(Request $request): string
    {
        return $this->accountForm($this->accountFromRequest($request), '/crm/accounts/update', 'edit');
    }

    public function showAccount(Request $request): string
    {
        $account = $this->accountFromRequest($request);

        return view('crm/account-show', [
            'title' => 'Cuenta CRM',
            'account' => $account,
            'contacts' => $this->contactsForAccount((int) $account['id']),
            'activities' => $this->activities('account_id', (int) $account['id']),
            'flash' => Session::flash('success'),
        ]);
    }

    public function storeAccount(Request $request): void
    {
        $data = $this->accountPayload($request);
        $errors = $this->validateAccount($data);
        if ($errors !== []) {
            $this->back('/crm/accounts/create', $errors, $data);
        }

        $this->db()->prepare(
            'INSERT INTO crm_accounts
             (uuid, company_id, trade_name, legal_name, tax_id, primary_email, primary_phone, website, address, notes,
              external_provider, external_id, sync_enabled, status)
             VALUES
             (:uuid, :company_id, :trade_name, :legal_name, :tax_id, :primary_email, :primary_phone, :website, :address, :notes,
              :external_provider, :external_id, :sync_enabled, :status)'
        )->execute(['uuid' => uuid()] + $data);

        (new AuditService($this->db()))->record('crm.account.created', 'crm_accounts', null, ['name' => $data['trade_name']], (int) $data['company_id']);
        Session::flash('success', 'Cuenta creada correctamente.');
        redirect('/crm');
    }

    public function updateAccount(Request $request): void
    {
        $account = $this->accountFromRequest($request);
        $data = $this->accountPayload($request);
        $data['company_id'] = (int) $account['company_id'];
        $errors = $this->validateAccount($data);
        if ($errors !== []) {
            $this->back('/crm/accounts/edit?id=' . $account['uuid'], $errors, $data);
        }
        unset($data['company_id']);

        $this->db()->prepare(
            'UPDATE crm_accounts
             SET trade_name = :trade_name, legal_name = :legal_name, tax_id = :tax_id, primary_email = :primary_email,
                 primary_phone = :primary_phone, website = :website, address = :address, notes = :notes,
                 external_provider = :external_provider, external_id = :external_id, sync_enabled = :sync_enabled, status = :status
             WHERE id = :id'
        )->execute($data + ['id' => (int) $account['id']]);

        (new AuditService($this->db()))->record('crm.account.updated', 'crm_accounts', (int) $account['id'], ['name' => $data['trade_name']], (int) $account['company_id']);
        Session::flash('success', 'Cuenta actualizada correctamente.');
        redirect('/crm/accounts/show?id=' . $account['uuid']);
    }

    public function deleteAccount(Request $request): void
    {
        $account = $this->accountFromRequest($request);
        $this->db()->prepare('UPDATE crm_accounts SET deleted_at = NOW(), status = "inactive" WHERE id = :id')->execute(['id' => (int) $account['id']]);
        (new AuditService($this->db()))->record('crm.account.deleted', 'crm_accounts', (int) $account['id'], ['name' => $account['trade_name']], (int) $account['company_id']);
        Session::flash('success', 'Cuenta eliminada correctamente.');
        redirect('/crm');
    }

    public function addNote(Request $request): void
    {
        $contactId = (string) $request->input('contact_id', '');
        $accountId = (string) $request->input('account_id', '');
        $contact = $contactId !== '' ? $this->contactFromUuid($contactId) : null;
        $account = $accountId !== '' ? $this->accountFromUuid($accountId) : null;
        $companyId = (int) (($contact['company_id'] ?? null) ?: ($account['company_id'] ?? Session::get('company_id')));

        $this->activity($companyId, $contact['id'] ?? null, $account['id'] ?? null, 'note', trim((string) $request->input('subject', 'Nota')), (string) $request->input('body', ''));
        Session::flash('success', 'Nota agregada.');
        redirect($contact !== null ? '/crm/contacts/show?id=' . $contact['uuid'] : '/crm/accounts/show?id=' . ($account['uuid'] ?? ''));
    }

    public function clickToCall(Request $request): void
    {
        $contact = $this->contactFromRequest($request);
        $userId = (int) Session::get('user_id', 0);
        if (! $this->hasPermission($userId, 'crm.call') || ! $this->hasPermission($userId, 'pbx.originate')) {
            Session::flash('error', 'Tu cuenta no tiene permisos para originar llamadas CRM.');
            redirect('/crm/contacts/show?id=' . $contact['uuid']);
        }
        if ($this->rateLimited('crm-originate:' . $userId, 12, 60)) {
            Session::flash('error', 'Se alcanzo el limite temporal de click-to-call para este usuario.');
            redirect('/crm/contacts/show?id=' . $contact['uuid']);
        }

        $number = $this->callableNumber($contact);
        if ($number === '') {
            Session::flash('error', 'El contacto no tiene movil, oficina, DID ni extension relacionada para llamar.');
            redirect('/crm/contacts/show?id=' . $contact['uuid']);
        }
        $extension = $this->userExtension((int) $contact['company_id']);
        $result = $extension === null
            ? ['message' => 'No se encontro extension vinculada al usuario logueado.']
            : (new PbxOriginateService($this->db()))->request((int) $contact['company_id'], (string) $extension['id'], $number, [
                'contact_uuid' => $contact['uuid'],
                'requested_by_user_id' => $userId,
                'source' => 'crm',
            ]);
        $subject = $extension === null ? 'Click-to-call pendiente' : 'Click-to-call solicitado';
        $body = $extension === null
            ? 'No se encontro extension vinculada al usuario logueado.'
            : (string) ($result['message'] ?? 'Solicitud de llamada registrada.');

        $this->activity((int) $contact['company_id'], (int) $contact['id'], $contact['account_id'] ? (int) $contact['account_id'] : null, 'call', $subject, $body, 'outbound');
        (new AuditService($this->db()))->record('crm.contact.click_to_call', 'crm_contacts', (int) $contact['id'], ['number' => $number, 'simulated' => $result['simulated'] ?? false, 'queued' => $result['queued'] ?? false, 'origin_extension' => $extension['id'] ?? null], (int) $contact['company_id']);
        Session::flash('success', $body);
        redirect('/crm/contacts/show?id=' . $contact['uuid']);
    }

    private function contactForm(array $contact, string $action, string $mode): string
    {
        return view('crm/contact-form', [
            'title' => $mode === 'create' ? 'Nuevo contacto' : 'Editar contacto',
            'contact' => $contact,
            'companies' => $this->companies(),
            'accounts' => $this->accounts(),
            'extensions' => $this->extensions((int) ($contact['company_id'] ?? Session::get('company_id', 0))),
            'sources' => $this->sources,
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => $action,
            'mode' => $mode,
        ]);
    }

    private function accountForm(array $account, string $action, string $mode): string
    {
        return view('crm/account-form', [
            'title' => $mode === 'create' ? 'Nueva cuenta CRM' : 'Editar cuenta CRM',
            'account' => $account,
            'companies' => $this->companies(),
            'sources' => $this->sources,
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => $action,
            'mode' => $mode,
        ]);
    }

    private function contacts(string $query = ''): array
    {
        $sql =
            'SELECT ct.*, ca.trade_name AS account_name, e.extension_number, e.display_name AS extension_display_name,
                    e.sip_status, e.presence_status, c.name AS company_name
             FROM crm_contacts ct
             INNER JOIN companies c ON c.id = ct.company_id
             LEFT JOIN crm_accounts ca ON ca.id = ct.account_id AND ca.deleted_at IS NULL
             LEFT JOIN ps_endpoints e ON e.id = ct.related_extension_id
             WHERE ct.deleted_at IS NULL';
        $params = [];
        $this->tenantSql($sql, $params, 'ct.company_id');
        if ($query !== '') {
            $sql .= ' AND (ct.full_name LIKE :q OR ct.email LIKE :q OR ct.mobile_phone LIKE :q OR ct.office_phone LIKE :q OR ct.related_did LIKE :q OR ct.tags LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }
        $sql .= ' ORDER BY ct.full_name LIMIT 200';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        $contacts = $statement->fetchAll();

        return array_map(function (array $contact): array {
            $contact['linked_status'] = $this->callLogService()->extensionStatusLabel($contact['sip_status'] ?? null, $contact['presence_status'] ?? null);
            $contact['last_interaction_at'] = $this->callLogService()->lastInteractionForContact((int) $contact['company_id'], (int) $contact['id']);

            return $contact;
        }, $contacts);
    }

    private function accounts(string $query = ''): array
    {
        $sql = 'SELECT a.*, c.name AS company_name FROM crm_accounts a INNER JOIN companies c ON c.id = a.company_id WHERE a.deleted_at IS NULL';
        $params = [];
        $this->tenantSql($sql, $params, 'a.company_id');
        if ($query !== '') {
            $sql .= ' AND (a.trade_name LIKE :q OR a.legal_name LIKE :q OR a.primary_email LIKE :q OR a.primary_phone LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }
        $sql .= ' ORDER BY a.trade_name LIMIT 200';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function stats(): array
    {
        return ['contacts' => count($this->contacts()), 'accounts' => count($this->accounts())];
    }

    private function contactPayload(Request $request): array
    {
        $companyId = has_role('super-admin') ? (int) $request->input('company_id', 0) : (int) Session::get('company_id');

        return [
            'company_id' => $companyId,
            'account_id' => (int) $request->input('account_id', 0) > 0 ? (int) $request->input('account_id') : null,
            'related_extension_id' => trim((string) $request->input('related_extension_id')) !== '' ? trim((string) $request->input('related_extension_id')) : null,
            'related_did' => trim((string) $request->input('related_did')) ?: null,
            'full_name' => trim((string) $request->input('full_name')),
            'organization' => trim((string) $request->input('organization')) ?: null,
            'job_title' => trim((string) $request->input('job_title')) ?: null,
            'email' => strtolower(trim((string) $request->input('email'))) ?: null,
            'mobile_phone' => trim((string) $request->input('mobile_phone')) ?: null,
            'office_phone' => trim((string) $request->input('office_phone')) ?: null,
            'tags' => trim((string) $request->input('tags')) ?: null,
            'notes' => trim((string) $request->input('notes')) ?: null,
            'source' => $this->source((string) $request->input('source', 'manual')),
            'external_provider' => $this->source((string) $request->input('external_provider', (string) $request->input('source', 'manual'))),
            'external_id' => trim((string) $request->input('external_id')) ?: null,
            'sync_enabled' => (string) $request->input('sync_enabled', '0') === '1' ? 1 : 0,
            'status' => in_array((string) $request->input('status', 'active'), ['active', 'inactive'], true) ? (string) $request->input('status', 'active') : 'active',
        ];
    }

    private function accountPayload(Request $request): array
    {
        $companyId = has_role('super-admin') ? (int) $request->input('company_id', 0) : (int) Session::get('company_id');

        return [
            'company_id' => $companyId,
            'trade_name' => trim((string) $request->input('trade_name')),
            'legal_name' => trim((string) $request->input('legal_name')) ?: null,
            'tax_id' => trim((string) $request->input('tax_id')) ?: null,
            'primary_email' => strtolower(trim((string) $request->input('primary_email'))) ?: null,
            'primary_phone' => trim((string) $request->input('primary_phone')) ?: null,
            'website' => trim((string) $request->input('website')) ?: null,
            'address' => trim((string) $request->input('address')) ?: null,
            'notes' => trim((string) $request->input('notes')) ?: null,
            'external_provider' => $this->source((string) $request->input('external_provider', 'manual')),
            'external_id' => trim((string) $request->input('external_id')) ?: null,
            'sync_enabled' => (string) $request->input('sync_enabled', '0') === '1' ? 1 : 0,
            'status' => in_array((string) $request->input('status', 'active'), ['active', 'inactive'], true) ? (string) $request->input('status', 'active') : 'active',
        ];
    }

    private function validateContact(array $data): array
    {
        $errors = [];
        if ($data['company_id'] <= 0) $errors['company_id'] = 'Selecciona una empresa.';
        if ($data['full_name'] === '') $errors['full_name'] = 'El nombre es obligatorio.';
        if ($data['email'] !== null && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email invalido.';

        return $errors;
    }

    private function validateAccount(array $data): array
    {
        $errors = [];
        if ($data['company_id'] <= 0) $errors['company_id'] = 'Selecciona una empresa.';
        if ($data['trade_name'] === '') $errors['trade_name'] = 'El nombre comercial es obligatorio.';
        if ($data['primary_email'] !== null && ! filter_var($data['primary_email'], FILTER_VALIDATE_EMAIL)) $errors['primary_email'] = 'Email invalido.';

        return $errors;
    }

    private function contactFromRequest(Request $request): array
    {
        return $this->contactFromUuid((string) $request->input('id'));
    }

    private function contactFromUuid(string $uuid): array
    {
        $sql =
            'SELECT ct.*, ca.trade_name AS account_name, e.extension_number, e.display_name AS extension_display_name,
                    e.sip_status, e.presence_status
             FROM crm_contacts ct
             LEFT JOIN crm_accounts ca ON ca.id = ct.account_id
             LEFT JOIN ps_endpoints e ON e.id = ct.related_extension_id
             WHERE ct.uuid = :uuid AND ct.deleted_at IS NULL';
        $params = ['uuid' => $uuid];
        $this->tenantSql($sql, $params, 'ct.company_id');
        $statement = $this->db()->prepare($sql . ' LIMIT 1');
        $statement->execute($params);
        $contact = $statement->fetch();
        if (! is_array($contact)) redirect('/crm');

        $contact['linked_status'] = $this->callLogService()->extensionStatusLabel($contact['sip_status'] ?? null, $contact['presence_status'] ?? null);
        $contact['last_interaction_at'] = $this->callLogService()->lastInteractionForContact((int) $contact['company_id'], (int) $contact['id']);

        return $contact;
    }

    private function accountFromRequest(Request $request): array
    {
        return $this->accountFromUuid((string) $request->input('id'));
    }

    private function accountFromUuid(string $uuid): array
    {
        $sql = 'SELECT * FROM crm_accounts WHERE uuid = :uuid AND deleted_at IS NULL';
        $params = ['uuid' => $uuid];
        $this->tenantSql($sql, $params, 'company_id');
        $statement = $this->db()->prepare($sql . ' LIMIT 1');
        $statement->execute($params);
        $account = $statement->fetch();
        if (! is_array($account)) redirect('/crm');

        return $account;
    }

    private function contactsForAccount(int $accountId): array
    {
        $statement = $this->db()->prepare(
            'SELECT ct.*, e.extension_number, e.display_name AS extension_display_name, e.sip_status, e.presence_status
             FROM crm_contacts ct
             LEFT JOIN ps_endpoints e ON e.id = ct.related_extension_id
             WHERE ct.account_id = :account_id AND ct.deleted_at IS NULL
             ORDER BY ct.full_name'
        );
        $statement->execute(['account_id' => $accountId]);
        $contacts = $statement->fetchAll();

        return array_map(function (array $contact): array {
            $contact['linked_status'] = $this->callLogService()->extensionStatusLabel($contact['sip_status'] ?? null, $contact['presence_status'] ?? null);

            return $contact;
        }, $contacts);
    }

    private function activities(string $column, int $id): array
    {
        $statement = $this->db()->prepare('SELECT * FROM crm_activities WHERE ' . $column . ' = :id AND deleted_at IS NULL ORDER BY occurred_at DESC LIMIT 100');
        $statement->execute(['id' => $id]);

        return $statement->fetchAll();
    }

    private function relatedRecordings(array $contact): array
    {
        $numbers = array_values(array_filter([(string) ($contact['mobile_phone'] ?? ''), (string) ($contact['office_phone'] ?? ''), (string) ($contact['related_did'] ?? '')]));
        if ($numbers === []) return [];
        $statement = $this->db()->prepare(
            'SELECT * FROM pbx_recordings
             WHERE company_id = :company_id AND deleted_at IS NULL
               AND (caller IN (:caller_1, :caller_2, :caller_3) OR callee IN (:callee_1, :callee_2, :callee_3))
             ORDER BY started_at DESC LIMIT 50'
        );
        $first = $numbers[0] ?? '';
        $second = $numbers[1] ?? $first;
        $third = $numbers[2] ?? $second;
        $statement->execute([
            'company_id' => (int) $contact['company_id'],
            'caller_1' => $first,
            'caller_2' => $second,
            'caller_3' => $third,
            'callee_1' => $first,
            'callee_2' => $second,
            'callee_3' => $third,
        ]);

        return $statement->fetchAll();
    }

    private function activity(int $companyId, ?int $contactId, ?int $accountId, string $type, string $subject, string $body, string $direction = 'none'): void
    {
        $this->db()->prepare(
            'INSERT INTO crm_activities (uuid, company_id, contact_id, account_id, user_id, activity_type, subject, body, direction, occurred_at)
             VALUES (:uuid, :company_id, :contact_id, :account_id, :user_id, :activity_type, :subject, :body, :direction, NOW())'
        )->execute([
            'uuid' => uuid(),
            'company_id' => $companyId,
            'contact_id' => $contactId,
            'account_id' => $accountId,
            'user_id' => Session::get('user_id'),
            'activity_type' => $type,
            'subject' => $subject !== '' ? $subject : 'Actividad',
            'body' => $body !== '' ? $body : null,
            'direction' => $direction,
        ]);
    }

    private function userExtension(int $companyId): ?array
    {
        $statement = $this->db()->prepare(
            'SELECT e.extension_number, e.id
             FROM ps_endpoints e
             INNER JOIN users u ON u.email = e.email AND u.company_id = e.company_id
             WHERE u.id = :user_id AND e.company_id = :company_id AND e.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['user_id' => Session::get('user_id'), 'company_id' => $companyId]);
        $extension = $statement->fetch();

        return is_array($extension) ? $extension : null;
    }

    private function callableNumber(array $contact): string
    {
        return (string) (($contact['mobile_phone'] ?? '') ?: (($contact['office_phone'] ?? '') ?: ($contact['related_did'] ?? '')));
    }

    private function callLogService(): CallLogService
    {
        return new CallLogService($this->db());
    }

    private function tenantCompanyId(): ?int
    {
        return has_role('super-admin') ? null : (int) Session::get('company_id');
    }

    private function hasPermission(int $userId, string $permission): bool
    {
        return has_role('super-admin') || (new PermissionService($this->db()))->userHasPermission($userId, $permission);
    }

    private function rateLimited(string $key, int $limit, int $windowSeconds): bool
    {
        return (new ApiTokenService($this->db()))->hitRateLimit($key, $limit, $windowSeconds);
    }

    private function companies(): array
    {
        if (! has_role('super-admin')) {
            return [['id' => (int) Session::get('company_id'), 'name' => (string) Session::get('company_name')]];
        }

        return $this->db()->query('SELECT id, name FROM companies WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
    }

    private function extensions(int $companyId): array
    {
        $statement = $this->db()->prepare('SELECT id, extension_number, display_name FROM ps_endpoints WHERE company_id = :company_id AND deleted_at IS NULL ORDER BY extension_number');
        $statement->execute(['company_id' => $companyId]);

        return $statement->fetchAll();
    }

    private function source(string $source): string
    {
        return in_array($source, $this->sources, true) ? $source : 'manual';
    }

    private function tenantSql(string &$sql, array &$params, string $column): void
    {
        if (! has_role('super-admin')) {
            $sql .= ' AND ' . $column . ' = :tenant_company_id';
            $params['tenant_company_id'] = (int) Session::get('company_id');
        }
    }

    private function back(string $path, array $errors, array $old): void
    {
        Session::flash('errors', $errors);
        Session::flash('old', $old);
        redirect($path);
    }
}
