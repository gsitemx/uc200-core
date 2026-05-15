<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuditService;
use PDO;
use Throwable;

final class PbxController extends Controller
{
    public function dashboard(Request $request): string
    {
        return view('pbx/dashboard', [
            'title' => 'PBX Core',
            'flash' => Session::flash('success'),
            'cards' => [
                ['label' => 'Extensiones', 'value' => $this->count('ps_endpoints'), 'hint' => 'Endpoints SIP preparados para Realtime'],
                ['label' => 'Transports', 'value' => $this->count('ps_transports'), 'hint' => 'UDP/TCP/TLS/WS/WSS configurables'],
                ['label' => 'Registradas', 'value' => $this->countByStatus('registered'), 'hint' => 'Estado SIP preparado para AMI/ARI futuro'],
                ['label' => 'Presencia', 'value' => $this->countPresenceReady(), 'hint' => 'Estructura lista para presencia'],
            ],
            'extensions' => $this->extensionRows(),
        ]);
    }

    public function extensions(Request $request): string
    {
        return view('pbx/extensions/index', [
            'title' => 'Extensiones SIP',
            'extensions' => $this->extensionRows(),
            'flash' => Session::flash('success'),
        ]);
    }

    public function createExtension(Request $request): string
    {
        return view('pbx/extensions/form', [
            'title' => 'Nueva extension SIP',
            'extension' => [],
            'companies' => $this->companies(),
            'transports' => $this->transportRows(),
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => '/pbx/extensions/store',
            'mode' => 'create',
        ]);
    }

    public function storeExtension(Request $request): void
    {
        $data = $this->extensionPayload($request);
        $errors = $this->validateExtension($data, true);

        if ($errors !== []) {
            $this->back('/pbx/extensions/create', $errors, $data);
        }

        $db = $this->db();
        $realtimeId = $this->realtimeId($data['company_id'], $data['extension']);

        if ($this->endpointExists($db, $realtimeId)) {
            $this->back('/pbx/extensions/create', ['extension' => 'La extension ya existe para esta empresa.'], $data);
        }

        try {
            $db->beginTransaction();
            $password = $this->sipPassword();
            $this->insertAor($db, $realtimeId, $data);
            $this->insertAuth($db, $realtimeId, $data, $password);
            $this->insertEndpoint($db, $realtimeId, $data);
            $db->commit();

            (new AuditService($db))->record('pbx.extension.created', 'ps_endpoints', null, [
                'endpoint' => $realtimeId,
                'extension' => $data['extension'],
            ], (int) $data['company_id']);

            Session::flash('success', 'Extension creada. Password SIP: ' . $password);
            redirect('/pbx/extensions');
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $this->back('/pbx/extensions/create', ['general' => 'No se pudo crear la extension.'], $data);
        }
    }

    public function editExtension(Request $request): string
    {
        $extension = $this->extensionFromRequest($request);

        return view('pbx/extensions/form', [
            'title' => 'Editar extension SIP',
            'extension' => $extension,
            'companies' => $this->companies(),
            'transports' => $this->transportRows(),
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => '/pbx/extensions/update',
            'mode' => 'edit',
        ]);
    }

    public function updateExtension(Request $request): void
    {
        $extension = $this->extensionFromRequest($request);
        $data = $this->extensionPayload($request);
        $errors = $this->validateExtension($data, false);

        if ($errors !== []) {
            $this->back('/pbx/extensions/edit?id=' . $extension['uuid'], $errors, $data);
        }

        $realtimeId = $extension['id'];
        $db = $this->db();
        $db->prepare(
            'UPDATE ps_aors
             SET max_contacts = :max_contacts, qualify_frequency = :qualify_frequency, status = :aor_status
             WHERE id = :id'
        )->execute([
            'id' => $realtimeId,
            'max_contacts' => $data['max_contacts'],
            'qualify_frequency' => $data['qualify_frequency'],
            'aor_status' => $data['status'] === 'active' ? 'active' : 'inactive',
        ]);
        $db->prepare(
            'UPDATE ps_auths
             SET username = :username, password = COALESCE(NULLIF(:password, ""), password), status = :auth_status
             WHERE id = :id'
        )->execute([
            'id' => $realtimeId,
            'username' => $data['extension'],
            'password' => $data['sip_password'],
            'auth_status' => $data['status'] === 'active' ? 'active' : 'inactive',
        ]);
        $db->prepare(
            'UPDATE ps_endpoints
             SET transport = :transport, context = :context, disallow = :disallow, allow = :allow,
                 callerid = :callerid, mailboxes = :mailboxes, status = :status
             WHERE id = :id'
        )->execute([
            'id' => $realtimeId,
            'transport' => $data['transport'] !== '' ? $data['transport'] : null,
            'context' => $data['context'],
            'disallow' => $data['disallow'],
            'allow' => $data['allow'],
            'callerid' => $data['callerid'] !== '' ? $data['callerid'] : null,
            'mailboxes' => $data['mailboxes'] !== '' ? $data['mailboxes'] : null,
            'status' => $data['status'],
        ]);

        (new AuditService($db))->record('pbx.extension.updated', 'ps_endpoints', null, ['endpoint' => $realtimeId], (int) $extension['company_id']);
        Session::flash('success', 'Extension actualizada correctamente.');
        redirect('/pbx/extensions');
    }

    public function deleteExtension(Request $request): void
    {
        $extension = $this->extensionFromRequest($request);
        $db = $this->db();
        foreach (['ps_endpoints', 'ps_auths', 'ps_aors'] as $table) {
            $db->prepare('UPDATE ' . $table . ' SET deleted_at = NOW(), status = "inactive" WHERE id = :id')->execute(['id' => $extension['id']]);
        }
        (new AuditService($db))->record('pbx.extension.deleted', 'ps_endpoints', null, ['endpoint' => $extension['id']], (int) $extension['company_id']);
        Session::flash('success', 'Extension eliminada correctamente.');
        redirect('/pbx/extensions');
    }

    public function transports(Request $request): string
    {
        return view('pbx/transports/index', [
            'title' => 'SIP Transports',
            'transports' => $this->transportRows(),
            'flash' => Session::flash('success'),
        ]);
    }

    public function createTransport(Request $request): string
    {
        return view('pbx/transports/form', [
            'title' => 'Nuevo transport',
            'transport' => [],
            'companies' => $this->companies(),
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => '/pbx/transports/store',
            'mode' => 'create',
        ]);
    }

    public function storeTransport(Request $request): void
    {
        $data = $this->transportPayload($request);
        $errors = $this->validateTransport($data);

        if ($errors !== []) {
            $this->back('/pbx/transports/create', $errors, $data);
        }

        $statement = $this->db()->prepare(
            'INSERT INTO ps_transports (id, uuid, company_id, protocol, bind, local_net, external_media_address, external_signaling_address, allow_reload, status)
             VALUES (:id, :uuid, :company_id, :protocol, :bind, :local_net, :external_media_address, :external_signaling_address, :allow_reload, :status)'
        );
        $statement->execute($this->transportParams($data) + ['uuid' => uuid()]);
        (new AuditService($this->db()))->record('pbx.transport.created', 'ps_transports', null, ['transport' => $data['id']], $data['company_id'] > 0 ? $data['company_id'] : null);

        Session::flash('success', 'Transport creado correctamente.');
        redirect('/pbx/transports');
    }

    public function editTransport(Request $request): string
    {
        $transport = $this->transportFromRequest($request);

        return view('pbx/transports/form', [
            'title' => 'Editar transport',
            'transport' => $transport,
            'companies' => $this->companies(),
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => '/pbx/transports/update',
            'mode' => 'edit',
        ]);
    }

    public function updateTransport(Request $request): void
    {
        $transport = $this->transportFromRequest($request);
        $data = $this->transportPayload($request);
        $data['id'] = $transport['id'];
        $errors = $this->validateTransport($data);

        if ($errors !== []) {
            $this->back('/pbx/transports/edit?id=' . $transport['uuid'], $errors, $data);
        }

        $statement = $this->db()->prepare(
            'UPDATE ps_transports
             SET company_id = :company_id, protocol = :protocol, bind = :bind, local_net = :local_net,
                 external_media_address = :external_media_address, external_signaling_address = :external_signaling_address,
                 allow_reload = :allow_reload, status = :status
             WHERE id = :id'
        );
        $statement->execute($this->transportParams($data));
        (new AuditService($this->db()))->record('pbx.transport.updated', 'ps_transports', null, ['transport' => $transport['id']], $data['company_id'] > 0 ? $data['company_id'] : null);

        Session::flash('success', 'Transport actualizado correctamente.');
        redirect('/pbx/transports');
    }

    public function deleteTransport(Request $request): void
    {
        $transport = $this->transportFromRequest($request);
        $this->db()->prepare('UPDATE ps_transports SET deleted_at = NOW(), status = "inactive" WHERE id = :id')->execute(['id' => $transport['id']]);
        (new AuditService($this->db()))->record('pbx.transport.deleted', 'ps_transports', null, ['transport' => $transport['id']], $transport['company_id'] !== null ? (int) $transport['company_id'] : null);
        Session::flash('success', 'Transport eliminado correctamente.');
        redirect('/pbx/transports');
    }

    public function realtime(Request $request): string
    {
        return view('pbx/realtime', [
            'title' => 'PJSIP Realtime',
            'endpoints' => $this->extensionRows(),
            'auths' => $this->tableRows('ps_auths'),
            'aors' => $this->tableRows('ps_aors'),
        ]);
    }

    private function count(string $table): int
    {
        return (int) $this->db()->query('SELECT COUNT(*) FROM ' . $table . ' WHERE deleted_at IS NULL')->fetchColumn();
    }

    private function countByStatus(string $status): int
    {
        $statement = $this->db()->prepare('SELECT COUNT(*) FROM ps_endpoints WHERE sip_status = :status AND deleted_at IS NULL');
        $statement->execute(['status' => $status]);

        return (int) $statement->fetchColumn();
    }

    private function countPresenceReady(): int
    {
        return (int) $this->db()->query('SELECT COUNT(*) FROM ps_endpoints WHERE presence_status IS NOT NULL AND deleted_at IS NULL')->fetchColumn();
    }

    private function extensionRows(): array
    {
        $sql =
            'SELECT e.*, c.name AS company_name, a.username, a.password, ao.max_contacts, ao.qualify_frequency
             FROM ps_endpoints e
             INNER JOIN companies c ON c.id = e.company_id
             INNER JOIN ps_auths a ON a.id = e.auth
             INNER JOIN ps_aors ao ON ao.id = e.aors
             WHERE e.deleted_at IS NULL';
        $params = [];

        if (! has_role('super-admin')) {
            $sql .= ' AND e.company_id = :company_id';
            $params['company_id'] = (int) Session::get('company_id');
        }

        $sql .= ' ORDER BY c.name, e.id';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function transportRows(): array
    {
        $sql =
            'SELECT t.*, c.name AS company_name
             FROM ps_transports t
             LEFT JOIN companies c ON c.id = t.company_id
             WHERE t.deleted_at IS NULL';
        $params = [];

        if (! has_role('super-admin')) {
            $sql .= ' AND (t.company_id IS NULL OR t.company_id = :company_id)';
            $params['company_id'] = (int) Session::get('company_id');
        }

        $sql .= ' ORDER BY t.id';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function companies(): array
    {
        if (! has_role('super-admin')) {
            $statement = $this->db()->prepare('SELECT id, name FROM companies WHERE id = :id AND deleted_at IS NULL ORDER BY name');
            $statement->execute(['id' => (int) Session::get('company_id')]);

            return $statement->fetchAll();
        }

        return $this->db()->query('SELECT id, name FROM companies WHERE deleted_at IS NULL ORDER BY name')->fetchAll();
    }

    private function tableRows(string $table): array
    {
        return $this->db()->query('SELECT * FROM ' . $table . ' WHERE deleted_at IS NULL ORDER BY id')->fetchAll();
    }

    private function extensionFromRequest(Request $request): array
    {
        $statement = $this->db()->prepare('SELECT * FROM ps_endpoints WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1');
        $statement->execute(['uuid' => (string) $request->input('id', '')]);
        $endpoint = $statement->fetch();

        if ($endpoint === false || (! has_role('super-admin') && (int) $endpoint['company_id'] !== (int) Session::get('company_id'))) {
            http_response_code(404);
            echo view('errors/404', ['path' => '/pbx/extensions']);
            exit;
        }

        $auth = $this->db()->prepare('SELECT username, password FROM ps_auths WHERE id = :id LIMIT 1');
        $auth->execute(['id' => $endpoint['auth']]);
        $aor = $this->db()->prepare('SELECT max_contacts, qualify_frequency FROM ps_aors WHERE id = :id LIMIT 1');
        $aor->execute(['id' => $endpoint['aors']]);

        return array_merge($endpoint, $auth->fetch() ?: [], $aor->fetch() ?: []);
    }

    private function transportFromRequest(Request $request): array
    {
        $statement = $this->db()->prepare('SELECT * FROM ps_transports WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1');
        $statement->execute(['uuid' => (string) $request->input('id', '')]);
        $transport = $statement->fetch();

        if ($transport !== false) {
            return $transport;
        }

        http_response_code(404);
        echo view('errors/404', ['path' => '/pbx/transports']);
        exit;
    }

    private function extensionPayload(Request $request): array
    {
        $companyId = (int) $request->input('company_id', Session::get('company_id') ?? 0);
        $extension = trim((string) $request->input('extension'));

        return [
            'company_id' => $companyId,
            'extension' => $extension,
            'transport' => trim((string) $request->input('transport')),
            'context' => trim((string) $request->input('context')) !== '' ? trim((string) $request->input('context')) : $this->tenantContext($companyId),
            'callerid' => trim((string) $request->input('callerid')),
            'mailboxes' => trim((string) $request->input('mailboxes', $extension !== '' ? $extension . '@default' : '')),
            'disallow' => trim((string) $request->input('disallow', 'all')),
            'allow' => trim((string) $request->input('allow', 'ulaw,alaw')),
            'max_contacts' => (int) $request->input('max_contacts', 1),
            'qualify_frequency' => (int) $request->input('qualify_frequency', 60),
            'sip_password' => (string) $request->input('sip_password', ''),
            'status' => (string) $request->input('status', 'active'),
        ];
    }

    private function transportPayload(Request $request): array
    {
        return [
            'id' => $this->slug((string) $request->input('id')),
            'company_id' => (int) $request->input('company_id', 0),
            'protocol' => (string) $request->input('protocol', 'udp'),
            'bind' => trim((string) $request->input('bind', '0.0.0.0:5060')),
            'local_net' => trim((string) $request->input('local_net')),
            'external_media_address' => trim((string) $request->input('external_media_address')),
            'external_signaling_address' => trim((string) $request->input('external_signaling_address')),
            'allow_reload' => $request->input('allow_reload') === '1' ? 1 : 0,
            'status' => (string) $request->input('status', 'active'),
        ];
    }

    private function validateExtension(array $data, bool $creating): array
    {
        $errors = [];

        if ($data['company_id'] <= 0) {
            $errors['company_id'] = 'Selecciona una empresa.';
        }

        if (! preg_match('/^[0-9]{2,12}$/', $data['extension'])) {
            $errors['extension'] = 'La extension debe tener solo digitos, entre 2 y 12 caracteres.';
        }

        if ($data['context'] === '' || ! preg_match('/^[a-zA-Z0-9_-]{3,80}$/', $data['context'])) {
            $errors['context'] = 'El contexto solo puede usar letras, numeros, guion y guion bajo.';
        }

        if (! in_array($data['status'], ['active', 'inactive', 'suspended'], true)) {
            $errors['status'] = 'Estado no valido.';
        }

        if ($creating === false && $data['sip_password'] !== '' && strlen($data['sip_password']) < 16) {
            $errors['sip_password'] = 'La contrasena SIP debe tener al menos 16 caracteres.';
        }

        return $errors;
    }

    private function validateTransport(array $data): array
    {
        $errors = [];

        if ($data['id'] === '') {
            $errors['id'] = 'El identificador es obligatorio.';
        }

        if (! in_array($data['protocol'], ['udp', 'tcp', 'tls', 'ws', 'wss'], true)) {
            $errors['protocol'] = 'Protocolo no valido.';
        }

        if ($data['bind'] === '') {
            $errors['bind'] = 'Bind es obligatorio.';
        }

        if (! in_array($data['status'], ['active', 'inactive'], true)) {
            $errors['status'] = 'Estado no valido.';
        }

        return $errors;
    }

    private function insertAor(PDO $db, string $id, array $data): void
    {
        $db->prepare(
            'INSERT INTO ps_aors (id, uuid, company_id, max_contacts, qualify_frequency, status)
             VALUES (:id, :uuid, :company_id, :max_contacts, :qualify_frequency, :status)'
        )->execute([
            'id' => $id,
            'uuid' => uuid(),
            'company_id' => $data['company_id'],
            'max_contacts' => max(1, $data['max_contacts']),
            'qualify_frequency' => max(0, $data['qualify_frequency']),
            'status' => $data['status'] === 'active' ? 'active' : 'inactive',
        ]);
    }

    private function insertAuth(PDO $db, string $id, array $data, string $password): void
    {
        $db->prepare(
            'INSERT INTO ps_auths (id, uuid, company_id, username, password, status)
             VALUES (:id, :uuid, :company_id, :username, :password, :status)'
        )->execute([
            'id' => $id,
            'uuid' => uuid(),
            'company_id' => $data['company_id'],
            'username' => $data['extension'],
            'password' => $password,
            'status' => $data['status'] === 'active' ? 'active' : 'inactive',
        ]);
    }

    private function insertEndpoint(PDO $db, string $id, array $data): void
    {
        $db->prepare(
            'INSERT INTO ps_endpoints (id, uuid, company_id, transport, aors, auth, context, disallow, allow, callerid, mailboxes, status)
             VALUES (:id, :uuid, :company_id, :transport, :aors, :auth, :context, :disallow, :allow, :callerid, :mailboxes, :status)'
        )->execute([
            'id' => $id,
            'uuid' => uuid(),
            'company_id' => $data['company_id'],
            'transport' => $data['transport'] !== '' ? $data['transport'] : null,
            'aors' => $id,
            'auth' => $id,
            'context' => $data['context'],
            'disallow' => $data['disallow'] !== '' ? $data['disallow'] : 'all',
            'allow' => $data['allow'] !== '' ? $data['allow'] : 'ulaw,alaw',
            'callerid' => $data['callerid'] !== '' ? $data['callerid'] : null,
            'mailboxes' => $data['mailboxes'] !== '' ? $data['mailboxes'] : null,
            'status' => $data['status'],
        ]);
    }

    private function endpointExists(PDO $db, string $id): bool
    {
        $statement = $db->prepare('SELECT COUNT(*) FROM ps_endpoints WHERE id = :id AND deleted_at IS NULL');
        $statement->execute(['id' => $id]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function transportParams(array $data): array
    {
        return [
            'id' => $data['id'],
            'company_id' => $data['company_id'] > 0 ? $data['company_id'] : null,
            'protocol' => $data['protocol'],
            'bind' => $data['bind'],
            'local_net' => $data['local_net'] !== '' ? $data['local_net'] : null,
            'external_media_address' => $data['external_media_address'] !== '' ? $data['external_media_address'] : null,
            'external_signaling_address' => $data['external_signaling_address'] !== '' ? $data['external_signaling_address'] : null,
            'allow_reload' => $data['allow_reload'],
            'status' => $data['status'],
        ];
    }

    private function tenantContext(int $companyId): string
    {
        return 'tenant_' . $companyId;
    }

    private function realtimeId(int $companyId, string $extension): string
    {
        return $companyId . '-' . $extension;
    }

    private function sipPassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug) ?: '';

        return trim($slug, '-');
    }

    private function back(string $path, array $errors, array $old): void
    {
        Session::flash('errors', $errors);
        Session::flash('old', $old);
        redirect($path);
    }
}
