<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;
use PDO;
use Throwable;

final class PbxController extends Controller
{
    public function dashboard(Request $request): string
    {
        if (! $this->hasRequiredTables()) {
            return $this->setupView();
        }

        return view('pbx/dashboard', [
            'title' => __('modules.pbx'),
            'flash' => Session::flash('success'),
            'cards' => [
                ['label' => __('modules.extensions'), 'value' => $this->count('ps_endpoints'), 'hint' => __('pbx.extensions_hint')],
                ['label' => __('modules.transports'), 'value' => $this->count('ps_transports'), 'hint' => __('pbx.transports_hint')],
                ['label' => __('modules.recordings'), 'value' => $this->count('pbx_recordings'), 'hint' => __('pbx.recordings_hint')],
                ['label' => 'Ring Groups', 'value' => $this->count('pbx_ring_groups'), 'hint' => 'Distribucion interna por tenant.'],
                ['label' => 'SIP Trunks', 'value' => $this->count('pbx_sip_trunks'), 'hint' => 'Troncales realtime PJSIP.'],
                ['label' => __('fields.status'), 'value' => $this->countByStatus('registered'), 'hint' => __('pbx.registered_hint')],
                ['label' => 'Presencia', 'value' => $this->countPresenceReady(), 'hint' => __('pbx.presence_hint')],
            ],
            'flowModules' => [
                ['label' => 'Ring Groups', 'href' => '/pbx/ring-groups', 'hint' => 'Estrategias ringall, hunt y failover.'],
                ['label' => 'IVR', 'href' => '/pbx/ivrs', 'hint' => 'Menus multinivel con audio y acciones.'],
                ['label' => 'SIP Trunks', 'href' => '/pbx/trunks', 'hint' => 'Registro outbound, codecs, NAT y qualify.'],
                ['label' => 'Inbound Routes', 'href' => '/pbx/inbound-routes', 'hint' => 'Ruteo DID/CID hacia destinos internos.'],
                ['label' => 'Outbound Routes', 'href' => '/pbx/outbound-routes', 'hint' => 'Patrones, permisos y secuencia de troncales.'],
                ['label' => 'Queues / Call Center', 'href' => '/call-center', 'hint' => 'Agentes, SLA, wallboard, overflow y supervisor.'],
                ['label' => 'Web Softphone', 'href' => '/softphone', 'hint' => 'SIP.js, WSS, DTLS, ICE y presencia web.'],
            ],
            'extensions' => $this->extensionRows(),
        ]);
    }

    public function extensions(Request $request): string
    {
        if (! $this->hasRequiredTables()) {
            return $this->setupView();
        }

        return view('pbx/extensions/index', [
            'title' => __('modules.extensions'),
            'extensions' => $this->extensionRows(),
            'flash' => Session::flash('success'),
        ]);
    }

    public function createExtension(Request $request): string
    {
        if (! $this->hasRequiredTables()) {
            return $this->setupView();
        }

        return view('pbx/extensions/form', [
            'title' => __('actions.new_extension'),
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
        $this->redirectIfTablesMissing();

        $data = $this->extensionPayload($request);
        $errors = $this->validateExtension($data, true);

        if ($errors !== []) {
            $this->back('/pbx/extensions/create', $errors, $data);
        }

        $db = $this->db();
        $internalEndpointId = $this->internalEndpointId($data['company_id'], $data['extension_number']);
        $internalAorId = $this->internalAorId($data['company_id'], $data['extension_number']);
        $authUsername = $this->authUsername();
        $internalAuthId = $this->internalAuthId($authUsername);
        $authPassword = $this->sipPassword();

        if ($this->extensionExists($db, (int) $data['company_id'], $data['extension_number'])) {
            $this->back('/pbx/extensions/create', ['extension' => 'La extension ya existe para esta empresa.'], $data);
        }
        if ($data['email'] !== '' && $this->extensionEmailExists($db, (int) $data['company_id'], $data['email'])) {
            $this->back('/pbx/extensions/create', ['email' => 'El email ya esta asignado a otra extension de esta empresa.'], $data);
        }

        try {
            $db->beginTransaction();
            $this->insertAor($db, $internalAorId, $data);
            $this->insertAuth($db, $internalAuthId, $data, $authUsername, $authPassword);
            $this->insertEndpoint($db, $internalEndpointId, $internalAuthId, $internalAorId, $data, $authUsername, $authPassword);
            $db->commit();

            (new AuditService($db))->record('pbx.extension.created', 'ps_endpoints', null, [
                'endpoint' => $internalEndpointId,
                'extension' => $data['extension_number'],
                'auth_username' => $authUsername,
            ], (int) $data['company_id']);

            Session::flash('success', 'Extension creada. Auth User: ' . $authUsername . ' / Password SIP: ' . $authPassword);
            redirect('/pbx/extensions');
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('UC200 PBX extension create failed: ' . $exception->getMessage());
            $this->back('/pbx/extensions/create', ['general' => 'No se pudo crear la extension.'], $data);
        }
    }

    public function editExtension(Request $request): string
    {
        if (! $this->hasRequiredTables()) {
            return $this->setupView();
        }

        $extension = $this->extensionFromRequest($request);

        return view('pbx/extensions/form', [
            'title' => __('actions.edit') . ' ' . strtolower(__('fields.extension')),
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
        $this->redirectIfTablesMissing();

        $extension = $this->extensionFromRequest($request);
        $data = $this->extensionPayload($request);
        $data['company_id'] = (int) $extension['company_id'];
        $errors = $this->validateExtension($data, false);

        if ($errors !== []) {
            $this->back('/pbx/extensions/edit?id=' . $extension['uuid'], $errors, $data);
        }

        $db = $this->db();
        if ($this->extensionExists($db, (int) $extension['company_id'], $data['extension_number'], (string) $extension['uuid'])) {
            $this->back('/pbx/extensions/edit?id=' . $extension['uuid'], ['extension' => 'La extension ya existe para esta empresa.'], $data);
        }
        if ($data['email'] !== '' && $this->extensionEmailExists($db, (int) $extension['company_id'], $data['email'], (string) $extension['uuid'])) {
            $this->back('/pbx/extensions/edit?id=' . $extension['uuid'], ['email' => 'El email ya esta asignado a otra extension de esta empresa.'], $data);
        }

        $internalEndpointId = $extension['id'];
        $internalAuthId = $extension['auth'];
        $internalAorId = $extension['aors'];
        $newEndpointId = $this->internalEndpointId((int) $extension['company_id'], $data['extension_number']);
        $newAorId = $this->internalAorId((int) $extension['company_id'], $data['extension_number']);

        try {
            $db->beginTransaction();
            $db->prepare(
                'UPDATE ps_aors
                 SET id = :new_id, max_contacts = :max_contacts,
                     qualify_frequency = :qualify_frequency, status = :aor_status
                 WHERE id = :old_id'
            )->execute([
                'new_id' => $newAorId,
                'old_id' => $internalAorId,
                'max_contacts' => $data['max_contacts'],
                'qualify_frequency' => $data['qualify_frequency'],
                'aor_status' => $data['status'] === 'active' ? 'active' : 'inactive',
            ]);
            $db->prepare(
                'UPDATE ps_auths
                 SET auth_type = :auth_type, realm = :realm, status = :auth_status
                 WHERE id = :id'
            )->execute([
                'id' => $internalAuthId,
                'auth_type' => $this->authType(),
                'realm' => $this->sipRealm(),
                'auth_status' => $data['status'] === 'active' ? 'active' : 'inactive',
            ]);
            $db->prepare(
                'UPDATE ps_endpoints
                 SET id = :new_id, extension_number = :extension_number,
                     display_name = :display_name, email = :email, contact_email = :contact_email, device_type = :device_type,
                     internal_endpoint_id = :internal_endpoint_id, internal_aor_id = :internal_aor_id,
                     aors = :aors, identify_by = :identify_by, transport = :transport, context = :context,
                     webrtc = :webrtc, media_encryption = :media_encryption, dtls_auto_generate_cert = :dtls_auto_generate_cert,
                     ice_support = :ice_support, use_avpf = :use_avpf, rtcp_mux = :rtcp_mux,
                     disallow = :disallow, allow = :allow, callerid = :callerid,
                     recording_enabled = :recording_enabled, voicemail_enabled = :voicemail_enabled,
                     direct_media = :direct_media, disable_direct_media_on_nat = :disable_direct_media_on_nat,
                     force_rport = :force_rport, rewrite_contact = :rewrite_contact, rtp_symmetric = :rtp_symmetric,
                     mailboxes = :mailboxes, status = :status
                 WHERE id = :old_id'
            )->execute([
                'new_id' => $newEndpointId,
                'old_id' => $internalEndpointId,
                'extension_number' => $data['extension_number'],
                'display_name' => $data['display_name'] !== '' ? $data['display_name'] : null,
                'email' => $data['email'] !== '' ? $data['email'] : null,
                'contact_email' => $data['email'] !== '' ? $data['email'] : null,
                'device_type' => $data['device_type'],
                'internal_endpoint_id' => $newEndpointId,
                'internal_aor_id' => $newAorId,
                'aors' => $newAorId,
                'identify_by' => $this->endpointIdentifyBy(),
                'transport' => $data['transport'] !== '' ? $data['transport'] : null,
                'context' => $data['context'],
                'webrtc' => $data['webrtc'],
                'media_encryption' => $data['media_encryption'],
                'dtls_auto_generate_cert' => $data['dtls_auto_generate_cert'],
                'ice_support' => $data['ice_support'],
                'use_avpf' => $data['use_avpf'],
                'rtcp_mux' => $data['rtcp_mux'],
                'disallow' => $data['disallow'],
                'allow' => $data['allow'],
                'callerid' => $data['callerid'] !== '' ? $data['callerid'] : null,
                'recording_enabled' => $data['recording_enabled'],
                'voicemail_enabled' => $data['voicemail_enabled'],
                'direct_media' => $data['direct_media'],
                'disable_direct_media_on_nat' => $data['disable_direct_media_on_nat'],
                'force_rport' => $data['force_rport'],
                'rewrite_contact' => $data['rewrite_contact'],
                'rtp_symmetric' => $data['rtp_symmetric'],
                'mailboxes' => $data['mailboxes'] !== '' ? $data['mailboxes'] : null,
                'status' => $data['status'],
            ]);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $this->back('/pbx/extensions/edit?id=' . $extension['uuid'], ['general' => 'No se pudo actualizar la extension.'], $data);
        }

        (new AuditService($db))->record('pbx.extension.updated', 'ps_endpoints', null, [
            'endpoint' => $newEndpointId,
            'previous_endpoint' => $internalEndpointId,
            'extension' => $data['extension_number'],
        ], (int) $extension['company_id']);
        Session::flash('success', 'Extension actualizada correctamente.');
        redirect('/pbx/extensions');
    }

    public function regenerateExtensionCredentials(Request $request): void
    {
        $this->redirectIfTablesMissing();

        $extension = $this->extensionFromRequest($request);
        $authUsername = $this->authUsername();
        $authPassword = $this->sipPassword();
        $internalAuthId = $this->internalAuthId($authUsername);
        $db = $this->db();

        try {
            $db->beginTransaction();
            $db->prepare(
                'UPDATE ps_auths
                 SET id = :new_id, auth_username = :auth_username, auth_password = :auth_password,
                     username = :username, password = :password, auth_type = :auth_type, realm = :realm
                 WHERE id = :old_id'
            )->execute([
                'new_id' => $internalAuthId,
                'old_id' => $extension['auth'],
                'auth_username' => $authUsername,
                'auth_password' => $authPassword,
                'username' => $authUsername,
                'password' => $authPassword,
                'auth_type' => $this->authType(),
                'realm' => $this->sipRealm(),
            ]);
            $db->prepare(
                'UPDATE ps_endpoints
                 SET auth = :auth_id, internal_auth_id = :internal_auth_id,
                     auth_username = :auth_username, auth_password = :auth_password,
                     identify_by = :identify_by
                 WHERE id = :endpoint_id'
            )->execute([
                'endpoint_id' => $extension['id'],
                'auth_id' => $internalAuthId,
                'internal_auth_id' => $internalAuthId,
                'auth_username' => $authUsername,
                'auth_password' => $authPassword,
                'identify_by' => $this->endpointIdentifyBy(),
            ]);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            Session::flash('error', 'No se pudieron regenerar las credenciales SIP.');
            redirect('/pbx/extensions');
        }

        (new AuditService($db))->record('pbx.extension.credentials_regenerated', 'ps_endpoints', null, [
            'endpoint' => $extension['id'],
            'extension' => $extension['extension_number'],
            'auth_username' => $authUsername,
        ], (int) $extension['company_id']);

        Session::flash('success', 'Credenciales regeneradas. Auth User: ' . $authUsername . ' / Password SIP: ' . $authPassword);
        redirect('/pbx/extensions');
    }

    public function deleteExtension(Request $request): void
    {
        $this->redirectIfTablesMissing();

        $extension = $this->extensionFromRequest($request);
        $db = $this->db();
        $db->prepare('UPDATE ps_endpoints SET deleted_at = NOW(), status = "inactive" WHERE id = :id')->execute(['id' => $extension['id']]);
        $db->prepare('UPDATE ps_auths SET deleted_at = NOW(), status = "inactive" WHERE id = :id')->execute(['id' => $extension['auth']]);
        $db->prepare('UPDATE ps_aors SET deleted_at = NOW(), status = "inactive" WHERE id = :id')->execute(['id' => $extension['aors']]);
        (new AuditService($db))->record('pbx.extension.deleted', 'ps_endpoints', null, ['endpoint' => $extension['id']], (int) $extension['company_id']);
        Session::flash('success', 'Extension eliminada correctamente.');
        redirect('/pbx/extensions');
    }

    public function transports(Request $request): string
    {
        if (! $this->hasRequiredTables()) {
            return $this->setupView();
        }

        return view('pbx/transports/index', [
            'title' => 'SIP Transports',
            'transports' => $this->transportRows(),
            'flash' => Session::flash('success'),
        ]);
    }

    public function createTransport(Request $request): string
    {
        if (! $this->hasRequiredTables()) {
            return $this->setupView();
        }

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
        $this->redirectIfTablesMissing();

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
        if (! $this->hasRequiredTables()) {
            return $this->setupView();
        }

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
        $this->redirectIfTablesMissing();

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
        $this->redirectIfTablesMissing();

        $transport = $this->transportFromRequest($request);
        $this->db()->prepare('UPDATE ps_transports SET deleted_at = NOW(), status = "inactive" WHERE id = :id')->execute(['id' => $transport['id']]);
        (new AuditService($this->db()))->record('pbx.transport.deleted', 'ps_transports', null, ['transport' => $transport['id']], $transport['company_id'] !== null ? (int) $transport['company_id'] : null);
        Session::flash('success', 'Transport eliminado correctamente.');
        redirect('/pbx/transports');
    }

    public function realtime(Request $request): string
    {
        if (! $this->hasRequiredTables()) {
            return $this->setupView();
        }

        return view('pbx/realtime', [
            'title' => 'PJSIP Realtime',
            'endpoints' => $this->extensionRows(),
            'auths' => $this->tableRows('ps_auths'),
            'aors' => $this->tableRows('ps_aors'),
        ]);
    }

    public function recordings(Request $request): string
    {
        if (! $this->hasRequiredTables()) {
            return $this->setupView();
        }

        $filters = $this->recordingFilters($request);

        return view('pbx/recordings/index', [
            'title' => __('modules.recordings'),
            'recordings' => $this->recordingRows($filters),
            'companies' => $this->companies(),
            'filters' => $filters,
            'flash' => Session::flash('success'),
        ]);
    }

    public function playRecording(Request $request): Response
    {
        return $this->recordingResponse($this->recordingFromRequest($request), false);
    }

    public function downloadRecording(Request $request): Response
    {
        return $this->recordingResponse($this->recordingFromRequest($request), true);
    }

    public function ringGroups(Request $request): string { return $this->flowIndex('ring_groups'); }
    public function createRingGroup(Request $request): string { return $this->flowForm('ring_groups'); }
    public function storeRingGroup(Request $request): void { $this->flowStore($request, 'ring_groups'); }
    public function editRingGroup(Request $request): string { return $this->flowForm('ring_groups', $this->flowFromRequest($request, 'ring_groups')); }
    public function updateRingGroup(Request $request): void { $this->flowUpdate($request, 'ring_groups'); }
    public function deleteRingGroup(Request $request): void { $this->flowDelete($request, 'ring_groups'); }

    public function ivrs(Request $request): string { return $this->flowIndex('ivrs'); }
    public function createIvr(Request $request): string { return $this->flowForm('ivrs'); }
    public function storeIvr(Request $request): void { $this->flowStore($request, 'ivrs'); }
    public function editIvr(Request $request): string { return $this->flowForm('ivrs', $this->flowFromRequest($request, 'ivrs')); }
    public function updateIvr(Request $request): void { $this->flowUpdate($request, 'ivrs'); }
    public function deleteIvr(Request $request): void { $this->flowDelete($request, 'ivrs'); }

    public function trunks(Request $request): string { return $this->flowIndex('trunks'); }
    public function createTrunk(Request $request): string { return $this->flowForm('trunks'); }
    public function storeTrunk(Request $request): void { $this->flowStore($request, 'trunks'); }
    public function editTrunk(Request $request): string { return $this->flowForm('trunks', $this->flowFromRequest($request, 'trunks')); }
    public function updateTrunk(Request $request): void { $this->flowUpdate($request, 'trunks'); }
    public function deleteTrunk(Request $request): void { $this->flowDelete($request, 'trunks'); }

    public function inboundRoutes(Request $request): string { return $this->flowIndex('inbound_routes'); }
    public function createInboundRoute(Request $request): string { return $this->flowForm('inbound_routes'); }
    public function storeInboundRoute(Request $request): void { $this->flowStore($request, 'inbound_routes'); }
    public function editInboundRoute(Request $request): string { return $this->flowForm('inbound_routes', $this->flowFromRequest($request, 'inbound_routes')); }
    public function updateInboundRoute(Request $request): void { $this->flowUpdate($request, 'inbound_routes'); }
    public function deleteInboundRoute(Request $request): void { $this->flowDelete($request, 'inbound_routes'); }

    public function outboundRoutes(Request $request): string { return $this->flowIndex('outbound_routes'); }
    public function createOutboundRoute(Request $request): string { return $this->flowForm('outbound_routes'); }
    public function storeOutboundRoute(Request $request): void { $this->flowStore($request, 'outbound_routes'); }
    public function editOutboundRoute(Request $request): string { return $this->flowForm('outbound_routes', $this->flowFromRequest($request, 'outbound_routes')); }
    public function updateOutboundRoute(Request $request): void { $this->flowUpdate($request, 'outbound_routes'); }
    public function deleteOutboundRoute(Request $request): void { $this->flowDelete($request, 'outbound_routes'); }

    private function count(string $table): int
    {
        if (has_role('super-admin')) {
            return (int) $this->db()->query('SELECT COUNT(*) FROM ' . $table . ' WHERE deleted_at IS NULL')->fetchColumn();
        }

        $sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE deleted_at IS NULL AND company_id = :company_id';
        $params = ['company_id' => (int) Session::get('company_id')];

        if ($table === 'ps_transports') {
            $sql = 'SELECT COUNT(*) FROM ps_transports WHERE deleted_at IS NULL AND (company_id = :company_id OR company_id IS NULL)';
        }

        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    private function flowIndex(string $key): string
    {
        if (! $this->hasRequiredTables()) {
            return $this->setupView();
        }

        $config = $this->flowConfig($key);

        return view('pbx/flows/index', [
            'title' => $config['title'],
            'config' => $config,
            'rows' => $this->flowRows($key),
            'flash' => Session::flash('success'),
        ]);
    }

    private function flowForm(string $key, array $row = []): string
    {
        if (! $this->hasRequiredTables()) {
            return $this->setupView();
        }

        $config = $this->flowConfig($key);

        return view('pbx/flows/form', [
            'title' => ($row === [] ? 'Nuevo ' : 'Editar ') . $config['singular'],
            'config' => $config,
            'row' => $row,
            'companies' => $this->companies(),
            'errors' => Session::flash('errors') ?? [],
            'old' => Session::flash('old') ?? [],
            'action' => $row === [] ? $config['base'] . '/store' : $config['base'] . '/update',
            'mode' => $row === [] ? 'create' : 'edit',
        ]);
    }

    private function flowStore(Request $request, string $key): void
    {
        $this->redirectIfTablesMissing();

        $config = $this->flowConfig($key);
        $data = $this->flowPayload($request, $config);
        $errors = $this->validateFlow($data, $config);

        if ($errors !== []) {
            $this->back($config['base'] . '/create', $errors, $data);
        }

        $columns = array_keys($data);
        $sql = 'INSERT INTO ' . $config['table'] . ' (uuid, ' . implode(', ', $columns) . ') VALUES (:uuid, :' . implode(', :', $columns) . ')';
        $this->db()->prepare($sql)->execute(['uuid' => uuid()] + $data);
        (new AuditService($this->db()))->record('pbx.' . $key . '.created', $config['table'], null, ['name' => $data['name']], (int) $data['company_id']);

        Session::flash('success', $config['singular'] . ' creado correctamente.');
        redirect($config['base']);
    }

    private function flowUpdate(Request $request, string $key): void
    {
        $this->redirectIfTablesMissing();

        $config = $this->flowConfig($key);
        $row = $this->flowFromRequest($request, $key);
        $data = $this->flowPayload($request, $config);
        $data['company_id'] = (int) $row['company_id'];
        $errors = $this->validateFlow($data, $config);

        if ($errors !== []) {
            $this->back($config['base'] . '/edit?id=' . $row['uuid'], $errors, $data);
        }

        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = $column . ' = :' . $column;
        }

        $this->db()->prepare('UPDATE ' . $config['table'] . ' SET ' . implode(', ', $sets) . ' WHERE id = :id')
            ->execute($data + ['id' => (int) $row['id']]);
        (new AuditService($this->db()))->record('pbx.' . $key . '.updated', $config['table'], (int) $row['id'], ['name' => $data['name']], (int) $data['company_id']);

        Session::flash('success', $config['singular'] . ' actualizado correctamente.');
        redirect($config['base']);
    }

    private function flowDelete(Request $request, string $key): void
    {
        $this->redirectIfTablesMissing();

        $config = $this->flowConfig($key);
        $row = $this->flowFromRequest($request, $key);
        $this->db()->prepare('UPDATE ' . $config['table'] . ' SET deleted_at = NOW(), status = "inactive" WHERE id = :id')
            ->execute(['id' => (int) $row['id']]);
        (new AuditService($this->db()))->record('pbx.' . $key . '.deleted', $config['table'], (int) $row['id'], ['name' => $row['name']], (int) $row['company_id']);

        Session::flash('success', $config['singular'] . ' eliminado correctamente.');
        redirect($config['base']);
    }

    private function flowRows(string $key): array
    {
        $config = $this->flowConfig($key);
        $sql = 'SELECT f.*, c.name AS company_name FROM ' . $config['table'] . ' f INNER JOIN companies c ON c.id = f.company_id WHERE f.deleted_at IS NULL';
        $params = [];

        if (! has_role('super-admin')) {
            $sql .= ' AND f.company_id = :company_id';
            $params['company_id'] = (int) Session::get('company_id');
        }

        $sql .= ' ORDER BY c.name, f.name';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function flowFromRequest(Request $request, string $key): array
    {
        $config = $this->flowConfig($key);
        $sql = 'SELECT * FROM ' . $config['table'] . ' WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1';
        $params = ['uuid' => (string) $request->input('id', '')];

        if (! has_role('super-admin')) {
            $sql = 'SELECT * FROM ' . $config['table'] . ' WHERE uuid = :uuid AND company_id = :company_id AND deleted_at IS NULL LIMIT 1';
            $params['company_id'] = (int) Session::get('company_id');
        }

        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        if ($row !== false) {
            return $row;
        }

        http_response_code(404);
        echo view('errors/404', ['path' => $config['base']]);
        exit;
    }

    private function flowPayload(Request $request, array $config): array
    {
        $data = [
            'company_id' => has_role('super-admin') ? (int) $request->input('company_id', 0) : (int) Session::get('company_id'),
            'name' => trim((string) $request->input('name')),
            'status' => (string) $request->input('status', 'active'),
        ];

        foreach ($config['fields'] as $field => $meta) {
            $value = trim((string) $request->input($field, (string) ($meta['default'] ?? '')));
            $data[$field] = $meta['type'] === 'int' ? max(0, (int) $value) : $value;
        }

        return $data;
    }

    private function validateFlow(array $data, array $config): array
    {
        $errors = [];

        if ($data['company_id'] <= 0) {
            $errors['company_id'] = 'Selecciona una empresa.';
        }

        if ($data['name'] === '') {
            $errors['name'] = 'El nombre es obligatorio.';
        }

        if (! in_array($data['status'], ['active', 'inactive'], true)) {
            $errors['status'] = 'Estado no valido.';
        }

        foreach ($config['fields'] as $field => $meta) {
            if (($meta['required'] ?? false) && (string) $data[$field] === '') {
                $errors[$field] = 'Campo obligatorio.';
            }

            if (isset($meta['options']) && $data[$field] !== '' && ! in_array((string) $data[$field], $meta['options'], true)) {
                $errors[$field] = 'Valor no valido.';
            }

            if (($meta['format'] ?? '') === 'json' && (string) $data[$field] !== '') {
                json_decode((string) $data[$field], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[$field] = 'JSON invalido.';
                }
            }
        }

        return $errors;
    }

    private function flowConfig(string $key): array
    {
        $configs = [
            'ring_groups' => [
                'table' => 'pbx_ring_groups', 'base' => '/pbx/ring-groups', 'title' => 'Ring Groups', 'singular' => 'Ring Group',
                'summary' => ['extension', 'strategy', 'timeout_seconds', 'failover_destination_type'],
                'fields' => [
                    'extension' => ['label' => 'Extension', 'required' => true, 'type' => 'string'],
                    'strategy' => ['label' => 'Estrategia', 'required' => true, 'type' => 'string', 'default' => 'ringall', 'options' => ['ringall', 'hunt', 'memoryhunt', 'leastrecent', 'fewestcalls', 'random']],
                    'timeout_seconds' => ['label' => 'Timeout', 'type' => 'int', 'default' => 30],
                    'members' => ['label' => 'Miembros', 'type' => 'string'],
                    'failover_destination_type' => ['label' => 'Failover tipo', 'type' => 'string'],
                    'failover_destination_id' => ['label' => 'Failover destino', 'type' => 'string'],
                ],
            ],
            'ivrs' => [
                'table' => 'pbx_ivrs', 'base' => '/pbx/ivrs', 'title' => 'IVR', 'singular' => 'IVR',
                'summary' => ['extension', 'prompt_file', 'digit_timeout', 'invalid_retries'],
                'fields' => [
                    'extension' => ['label' => 'Extension', 'type' => 'string'],
                    'prompt_file' => ['label' => 'Audio prompt', 'type' => 'string'],
                    'digit_timeout' => ['label' => 'Digit timeout', 'type' => 'int', 'default' => 5],
                    'invalid_retries' => ['label' => 'Intentos invalidos', 'type' => 'int', 'default' => 3],
                    'options_json' => ['label' => 'Opciones JSON', 'type' => 'string', 'default' => '{}', 'format' => 'json'],
                    'failover_destination_type' => ['label' => 'Failover tipo', 'type' => 'string'],
                    'failover_destination_id' => ['label' => 'Failover destino', 'type' => 'string'],
                ],
            ],
            'trunks' => [
                'table' => 'pbx_sip_trunks', 'base' => '/pbx/trunks', 'title' => 'SIP Trunks', 'singular' => 'SIP Trunk',
                'summary' => ['host', 'username', 'transport', 'codecs'],
                'fields' => [
                    'host' => ['label' => 'Host', 'required' => true, 'type' => 'string'],
                    'username' => ['label' => 'Usuario', 'type' => 'string'],
                    'password' => ['label' => 'Password', 'type' => 'string'],
                    'transport' => ['label' => 'Transport', 'type' => 'string', 'default' => 'transport-udp'],
                    'codecs' => ['label' => 'Codecs', 'type' => 'string', 'default' => 'ulaw,alaw'],
                    'qualify_frequency' => ['label' => 'Qualify', 'type' => 'int', 'default' => 60],
                    'outbound_registration' => ['label' => 'Outbound registration', 'type' => 'string', 'default' => 'no', 'options' => ['yes', 'no']],
                    'inbound_auth' => ['label' => 'Inbound auth', 'type' => 'string', 'default' => 'yes', 'options' => ['yes', 'no']],
                    'nat_mode' => ['label' => 'NAT', 'type' => 'string', 'default' => 'yes', 'options' => ['yes', 'no']],
                ],
            ],
            'inbound_routes' => [
                'table' => 'pbx_inbound_routes', 'base' => '/pbx/inbound-routes', 'title' => 'Inbound Routes', 'singular' => 'Inbound Route',
                'summary' => ['did_pattern', 'cid_filter', 'destination_type', 'destination_id'],
                'fields' => [
                    'did_pattern' => ['label' => 'DID', 'required' => true, 'type' => 'string'],
                    'cid_filter' => ['label' => 'CID filter', 'type' => 'string'],
                    'destination_type' => ['label' => 'Destino tipo', 'required' => true, 'type' => 'string'],
                    'destination_id' => ['label' => 'Destino', 'required' => true, 'type' => 'string'],
                    'failover_destination_type' => ['label' => 'Failover tipo', 'type' => 'string'],
                    'failover_destination_id' => ['label' => 'Failover destino', 'type' => 'string'],
                ],
            ],
            'outbound_routes' => [
                'table' => 'pbx_outbound_routes', 'base' => '/pbx/outbound-routes', 'title' => 'Outbound Routes', 'singular' => 'Outbound Route',
                'summary' => ['dial_pattern', 'prepend', 'strip_digits', 'trunk_sequence'],
                'fields' => [
                    'dial_pattern' => ['label' => 'Patron', 'required' => true, 'type' => 'string'],
                    'prepend' => ['label' => 'Prepend', 'type' => 'string'],
                    'strip_digits' => ['label' => 'Remove', 'type' => 'int', 'default' => 0],
                    'trunk_sequence' => ['label' => 'Trunks', 'type' => 'string'],
                    'permission_role' => ['label' => 'Permiso', 'type' => 'string'],
                    'emergency' => ['label' => 'Emergencia', 'type' => 'string', 'default' => 'no', 'options' => ['yes', 'no']],
                ],
            ],
        ];

        return $configs[$key];
    }

    private function hasRequiredTables(): bool
    {
        return $this->missingTables() === [] && $this->missingColumns() === [];
    }

    private function missingTables(): array
    {
        $required = [
            'ps_endpoints',
            'ps_auths',
            'ps_aors',
            'ps_transports',
            'pbx_recordings',
            'pbx_ring_groups',
            'pbx_ivrs',
            'pbx_sip_trunks',
            'pbx_inbound_routes',
            'pbx_outbound_routes',
        ];
        $missing = [];

        foreach ($required as $table) {
            $statement = $this->db()->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table'
            );
            $statement->execute(['table' => $table]);

            if ((int) $statement->fetchColumn() === 0) {
                $missing[] = $table;
            }
        }

        return $missing;
    }

    private function setupView(): string
    {
        return view('pbx/setup', [
            'title' => 'PBX Core setup',
            'missingTables' => $this->missingTables(),
            'missingColumns' => $this->missingColumns(),
        ]);
    }

    private function missingColumns(): array
    {
        $required = [
            'ps_aors' => [
                'uuid',
                'company_id',
                'max_contacts',
                'qualify_frequency',
                'status',
                'created_at',
                'updated_at',
                'deleted_at',
            ],
            'ps_auths' => [
                'uuid',
                'company_id',
                'auth_username',
                'auth_password',
                'auth_type',
                'username',
                'password',
                'realm',
                'status',
                'created_at',
                'updated_at',
                'deleted_at',
            ],
            'ps_endpoints' => [
                'uuid',
                'company_id',
                'extension_number',
                'display_name',
                'email',
                'contact_email',
                'device_type',
                'auth_username',
                'auth_password',
                'internal_endpoint_id',
                'internal_auth_id',
                'internal_aor_id',
                'transport',
                'aors',
                'auth',
                'identify_by',
                'context',
                'disallow',
                'allow',
                'direct_media',
                'disable_direct_media_on_nat',
                'force_rport',
                'rewrite_contact',
                'rtp_symmetric',
                'callerid',
                'recording_enabled',
                'voicemail_enabled',
                'mailboxes',
                'status',
                'presence_status',
                'sip_status',
                'created_at',
                'updated_at',
                'deleted_at',
            ],
            'ps_transports' => [
                'uuid',
                'company_id',
                'protocol',
                'bind',
                'local_net',
                'external_media_address',
                'external_signaling_address',
                'allow_reload',
                'status',
                'created_at',
                'updated_at',
                'deleted_at',
            ],
            'pbx_recordings' => [
                'uuid',
                'company_id',
                'direction',
                'caller',
                'callee',
                'started_at',
                'ended_at',
                'duration_seconds',
                'file_path',
                'uniqueid',
                'linkedid',
                'status',
                'created_at',
                'updated_at',
                'deleted_at',
            ],
            'pbx_ring_groups' => [
                'uuid',
                'company_id',
                'name',
                'extension',
                'strategy',
                'timeout_seconds',
                'members',
                'failover_destination_type',
                'failover_destination_id',
                'status',
                'created_at',
                'updated_at',
                'deleted_at',
            ],
            'pbx_ivrs' => [
                'uuid',
                'company_id',
                'name',
                'extension',
                'prompt_file',
                'digit_timeout',
                'invalid_retries',
                'options_json',
                'failover_destination_type',
                'failover_destination_id',
                'status',
                'created_at',
                'updated_at',
                'deleted_at',
            ],
            'pbx_sip_trunks' => [
                'uuid',
                'company_id',
                'name',
                'host',
                'username',
                'password',
                'transport',
                'codecs',
                'qualify_frequency',
                'outbound_registration',
                'inbound_auth',
                'nat_mode',
                'status',
                'created_at',
                'updated_at',
                'deleted_at',
            ],
            'pbx_inbound_routes' => [
                'uuid',
                'company_id',
                'name',
                'did_pattern',
                'cid_filter',
                'destination_type',
                'destination_id',
                'failover_destination_type',
                'failover_destination_id',
                'status',
                'created_at',
                'updated_at',
                'deleted_at',
            ],
            'pbx_outbound_routes' => [
                'uuid',
                'company_id',
                'name',
                'dial_pattern',
                'prepend',
                'strip_digits',
                'trunk_sequence',
                'permission_role',
                'emergency',
                'status',
                'created_at',
                'updated_at',
                'deleted_at',
            ],
        ];
        $missing = [];

        foreach ($required as $table => $columns) {
            foreach ($columns as $column) {
                $statement = $this->db()->prepare(
                    'SELECT COUNT(*)
                     FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = :table
                       AND COLUMN_NAME = :column'
                );
                $statement->execute(['table' => $table, 'column' => $column]);

                if ((int) $statement->fetchColumn() === 0) {
                    $missing[] = $table . '.' . $column;
                }
            }
        }

        return $missing;
    }

    private function redirectIfTablesMissing(): void
    {
        if ($this->hasRequiredTables()) {
            return;
        }

        Session::flash('error', 'PBX Core requiere ejecutar las migraciones PBX, seguridad, grabaciones y call-flow.');
        redirect('/pbx');
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
            'SELECT e.*, c.name AS company_name,
                    a.username, a.password, a.auth_username AS auth_user, a.auth_password AS auth_secret,
                    ao.max_contacts, ao.qualify_frequency
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

    private function recordingRows(array $filters): array
    {
        $sql =
            'SELECT r.*, c.name AS company_name
             FROM pbx_recordings r
             INNER JOIN companies c ON c.id = r.company_id
             WHERE r.deleted_at IS NULL';
        $params = [];

        if (! has_role('super-admin')) {
            $sql .= ' AND r.company_id = :tenant_company_id';
            $params['tenant_company_id'] = (int) Session::get('company_id');
        } elseif ($filters['company_id'] > 0) {
            $sql .= ' AND r.company_id = :company_id';
            $params['company_id'] = $filters['company_id'];
        }

        if ($filters['direction'] !== '') {
            $sql .= ' AND r.direction = :direction';
            $params['direction'] = $filters['direction'];
        }

        if ($filters['q'] !== '') {
            $sql .= ' AND (r.caller LIKE :q OR r.callee LIKE :q OR r.uniqueid LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        if ($filters['date_from'] !== '') {
            $sql .= ' AND r.started_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if ($filters['date_to'] !== '') {
            $sql .= ' AND r.started_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $sql .= ' ORDER BY r.started_at DESC LIMIT 250';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    private function recordingFilters(Request $request): array
    {
        $direction = (string) $request->input('direction', '');

        return [
            'q' => trim((string) $request->input('q', '')),
            'direction' => in_array($direction, ['internal', 'inbound', 'outbound'], true) ? $direction : '',
            'company_id' => (int) $request->input('company_id', 0),
            'date_from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('date_from', '')) ? (string) $request->input('date_from') : '',
            'date_to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('date_to', '')) ? (string) $request->input('date_to') : '',
        ];
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

        $auth = $this->db()->prepare('SELECT username, password, auth_username AS auth_user, auth_password AS auth_secret FROM ps_auths WHERE id = :id LIMIT 1');
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

    private function recordingFromRequest(Request $request): array
    {
        $sql = 'SELECT * FROM pbx_recordings WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1';
        $params = ['uuid' => (string) $request->input('id', '')];

        if (! has_role('super-admin')) {
            $sql = 'SELECT * FROM pbx_recordings WHERE uuid = :uuid AND company_id = :company_id AND deleted_at IS NULL LIMIT 1';
            $params['company_id'] = (int) Session::get('company_id');
        }

        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        $recording = $statement->fetch();

        return $recording !== false ? $recording : [];
    }

    private function recordingResponse(array $recording, bool $download): Response
    {
        $path = (string) ($recording['file_path'] ?? '');

        $realPath = $path !== '' ? realpath($path) : false;
        $recordingsBase = realpath('/recordings');

        if (
            $recording === []
            || $realPath === false
            || $recordingsBase === false
            || ! str_starts_with($realPath, $recordingsBase . DIRECTORY_SEPARATOR)
            || ! is_file($realPath)
        ) {
            return new Response(view('errors/404', ['path' => '/pbx/recordings']), 404);
        }

        $filename = basename($realPath);
        $headers = [
            'Content-Type' => str_ends_with(strtolower($filename), '.wav') ? 'audio/wav' : 'audio/mpeg',
            'Content-Length' => (string) filesize($realPath),
            'Accept-Ranges' => 'bytes',
        ];

        if ($download) {
            $headers['Content-Disposition'] = 'attachment; filename="' . addslashes($filename) . '"';
        }

        return new Response((string) file_get_contents($realPath), 200, $headers);
    }

    private function extensionPayload(Request $request): array
    {
        $companyId = (int) $request->input('company_id', Session::get('company_id') ?? 0);
        $extension = trim((string) $request->input('extension_number', (string) $request->input('extension')));
        $displayName = trim((string) $request->input('display_name'));
        $email = strtolower(trim((string) $request->input('email', (string) $request->input('contact_email'))));
        $deviceType = (string) $request->input('device_type', 'external_softphone');
        if (! in_array($deviceType, ['external_softphone', 'webrtc', 'ip_phone'], true)) {
            $deviceType = 'external_softphone';
        }
        $callerid = trim((string) $request->input('callerid'));
        $transport = trim((string) $request->input('transport'));
        $allow = trim((string) $request->input('allow', $deviceType === 'webrtc' ? 'opus,ulaw,alaw' : 'ulaw,alaw'));
        if ($deviceType === 'webrtc' && $transport === '') {
            $transport = 'transport-wss';
        }
        $voicemailEnabled = $this->yesNo($request->input('voicemail_enabled', 'no'));
        $mailboxes = trim((string) $request->input('mailboxes', $extension !== '' ? $extension . '@default' : ''));
        if ($voicemailEnabled === 'no') {
            $mailboxes = '';
        }

        return [
            'company_id' => $companyId,
            'extension_number' => $extension,
            'display_name' => $displayName,
            'email' => $email,
            'contact_email' => $email,
            'device_type' => $deviceType,
            'transport' => $transport,
            'context' => trim((string) $request->input('context')) !== '' ? trim((string) $request->input('context')) : 'contexto_principal',
            'callerid' => $callerid !== '' ? $callerid : '"' . ($displayName !== '' ? $displayName : $extension) . '" <' . $extension . '>',
            'recording_enabled' => $this->yesNo($request->input('recording_enabled', 'no')),
            'voicemail_enabled' => $voicemailEnabled,
            'mailboxes' => $mailboxes,
            'disallow' => trim((string) $request->input('disallow', 'all')),
            'allow' => $allow,
            'webrtc' => $deviceType === 'webrtc' ? 'yes' : 'no',
            'media_encryption' => $deviceType === 'webrtc' ? 'dtls' : null,
            'dtls_auto_generate_cert' => $deviceType === 'webrtc' ? 'yes' : 'no',
            'ice_support' => $deviceType === 'webrtc' ? 'yes' : 'no',
            'use_avpf' => $deviceType === 'webrtc' ? 'yes' : 'no',
            'rtcp_mux' => $deviceType === 'webrtc' ? 'yes' : 'no',
            'direct_media' => $this->yesNo($request->input('direct_media', 'no')),
            'disable_direct_media_on_nat' => $this->yesNo($request->input('disable_direct_media_on_nat', 'yes')),
            'force_rport' => $this->yesNo($request->input('force_rport', 'yes')),
            'rewrite_contact' => $this->yesNo($request->input('rewrite_contact', 'yes')),
            'rtp_symmetric' => $this->yesNo($request->input('rtp_symmetric', 'yes')),
            'max_contacts' => (int) $request->input('max_contacts', 1),
            'qualify_frequency' => (int) $request->input('qualify_frequency', 60),
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

        if (! preg_match('/^[0-9]{2,12}$/', $data['extension_number'])) {
            $errors['extension'] = 'La extension debe tener solo digitos, entre 2 y 12 caracteres.';
        }

        if ($data['context'] === '' || ! preg_match('/^[a-zA-Z0-9_-]{3,80}$/', $data['context'])) {
            $errors['context'] = 'El contexto solo puede usar letras, numeros, guion y guion bajo.';
        }

        if ($data['email'] !== '' && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email no valido.';
        }

        if (! in_array($data['status'], ['active', 'inactive', 'suspended'], true)) {
            $errors['status'] = 'Estado no valido.';
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
            . ' ON DUPLICATE KEY UPDATE
                company_id = VALUES(company_id),
                max_contacts = VALUES(max_contacts),
                qualify_frequency = VALUES(qualify_frequency),
                status = VALUES(status),
                deleted_at = NULL'
        )->execute([
            'id' => $id,
            'uuid' => uuid(),
            'company_id' => $data['company_id'],
            'max_contacts' => max(1, $data['max_contacts']),
            'qualify_frequency' => max(0, $data['qualify_frequency']),
            'status' => $data['status'] === 'active' ? 'active' : 'inactive',
        ]);
    }

    private function insertAuth(PDO $db, string $id, array $data, string $authUsername, string $authPassword): void
    {
        $db->prepare(
            'INSERT INTO ps_auths (id, uuid, company_id, auth_username, auth_password, auth_type, username, password, realm, status)
             VALUES (:id, :uuid, :company_id, :auth_username, :auth_password, :auth_type, :username, :password, :realm, :status)'
        )->execute([
            'id' => $id,
            'uuid' => uuid(),
            'company_id' => $data['company_id'],
            'auth_username' => $authUsername,
            'auth_password' => $authPassword,
            'auth_type' => $this->authType(),
            'username' => $authUsername,
            'password' => $authPassword,
            'realm' => $this->sipRealm(),
            'status' => $data['status'] === 'active' ? 'active' : 'inactive',
        ]);
    }

    private function insertEndpoint(PDO $db, string $id, string $authId, string $aorId, array $data, string $authUsername, string $authPassword): void
    {
        $db->prepare(
            'INSERT INTO ps_endpoints (id, uuid, company_id, extension_number, display_name, email, contact_email, device_type,
                 auth_username, auth_password, webrtc, media_encryption, dtls_auto_generate_cert, ice_support, use_avpf, rtcp_mux,
                 internal_endpoint_id, internal_auth_id, internal_aor_id, transport, aors, auth, context,
                 identify_by, disallow, allow, direct_media, disable_direct_media_on_nat,
                 force_rport, rewrite_contact, rtp_symmetric, callerid, recording_enabled, voicemail_enabled, mailboxes, status)
             VALUES (:id, :uuid, :company_id, :extension_number, :display_name, :email, :contact_email, :device_type,
                 :auth_username, :auth_password, :webrtc, :media_encryption, :dtls_auto_generate_cert, :ice_support, :use_avpf, :rtcp_mux,
                 :internal_endpoint_id, :internal_auth_id, :internal_aor_id, :transport, :aors, :auth, :context,
                 :identify_by, :disallow, :allow, :direct_media, :disable_direct_media_on_nat,
                 :force_rport, :rewrite_contact, :rtp_symmetric, :callerid, :recording_enabled, :voicemail_enabled, :mailboxes, :status)'
            . ' ON DUPLICATE KEY UPDATE
                company_id = VALUES(company_id),
                extension_number = VALUES(extension_number),
                display_name = VALUES(display_name),
                email = VALUES(email),
                contact_email = VALUES(contact_email),
                device_type = VALUES(device_type),
                auth_username = VALUES(auth_username),
                auth_password = VALUES(auth_password),
                webrtc = VALUES(webrtc),
                media_encryption = VALUES(media_encryption),
                dtls_auto_generate_cert = VALUES(dtls_auto_generate_cert),
                ice_support = VALUES(ice_support),
                use_avpf = VALUES(use_avpf),
                rtcp_mux = VALUES(rtcp_mux),
                internal_endpoint_id = VALUES(internal_endpoint_id),
                internal_auth_id = VALUES(internal_auth_id),
                internal_aor_id = VALUES(internal_aor_id),
                transport = VALUES(transport),
                aors = VALUES(aors),
                auth = VALUES(auth),
                identify_by = VALUES(identify_by),
                context = VALUES(context),
                disallow = VALUES(disallow),
                allow = VALUES(allow),
                direct_media = VALUES(direct_media),
                disable_direct_media_on_nat = VALUES(disable_direct_media_on_nat),
                force_rport = VALUES(force_rport),
                rewrite_contact = VALUES(rewrite_contact),
                rtp_symmetric = VALUES(rtp_symmetric),
                callerid = VALUES(callerid),
                recording_enabled = VALUES(recording_enabled),
                voicemail_enabled = VALUES(voicemail_enabled),
                mailboxes = VALUES(mailboxes),
                status = VALUES(status),
                deleted_at = NULL'
        )->execute([
            'id' => $id,
            'uuid' => uuid(),
            'company_id' => $data['company_id'],
            'extension_number' => $data['extension_number'],
            'display_name' => $data['display_name'] !== '' ? $data['display_name'] : null,
            'email' => $data['email'] !== '' ? $data['email'] : null,
            'contact_email' => $data['email'] !== '' ? $data['email'] : null,
            'device_type' => $data['device_type'],
            'auth_username' => $authUsername,
            'auth_password' => $authPassword,
            'webrtc' => $data['webrtc'],
            'media_encryption' => $data['media_encryption'],
            'dtls_auto_generate_cert' => $data['dtls_auto_generate_cert'],
            'ice_support' => $data['ice_support'],
            'use_avpf' => $data['use_avpf'],
            'rtcp_mux' => $data['rtcp_mux'],
            'internal_endpoint_id' => $id,
            'internal_auth_id' => $authId,
            'internal_aor_id' => $aorId,
            'transport' => $data['transport'] !== '' ? $data['transport'] : null,
            'aors' => $aorId,
            'auth' => $authId,
            'context' => $data['context'],
            'identify_by' => $this->endpointIdentifyBy(),
            'disallow' => $data['disallow'] !== '' ? $data['disallow'] : 'all',
            'allow' => $data['allow'] !== '' ? $data['allow'] : 'ulaw,alaw',
            'direct_media' => $data['direct_media'],
            'disable_direct_media_on_nat' => $data['disable_direct_media_on_nat'],
            'force_rport' => $data['force_rport'],
            'rewrite_contact' => $data['rewrite_contact'],
            'rtp_symmetric' => $data['rtp_symmetric'],
            'callerid' => $data['callerid'] !== '' ? $data['callerid'] : null,
            'recording_enabled' => $data['recording_enabled'],
            'voicemail_enabled' => $data['voicemail_enabled'],
            'mailboxes' => $data['mailboxes'] !== '' ? $data['mailboxes'] : null,
            'status' => $data['status'],
        ]);
    }

    private function extensionExists(PDO $db, int $companyId, string $extensionNumber, ?string $excludeUuid = null): bool
    {
        $sql =
            'SELECT COUNT(*)
             FROM ps_endpoints
             WHERE company_id = :company_id
               AND extension_number = :extension_number
               AND deleted_at IS NULL';
        $params = [
            'company_id' => $companyId,
            'extension_number' => $extensionNumber,
        ];

        if ($excludeUuid !== null) {
            $sql .= ' AND uuid <> :exclude_uuid';
            $params['exclude_uuid'] = $excludeUuid;
        }

        $statement = $db->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    private function extensionEmailExists(PDO $db, int $companyId, string $email, ?string $excludeUuid = null): bool
    {
        $sql =
            'SELECT COUNT(*)
             FROM ps_endpoints
             WHERE company_id = :company_id
               AND email = :email
               AND deleted_at IS NULL';
        $params = [
            'company_id' => $companyId,
            'email' => strtolower($email),
        ];

        if ($excludeUuid !== null) {
            $sql .= ' AND uuid <> :exclude_uuid';
            $params['exclude_uuid'] = $excludeUuid;
        }

        $statement = $db->prepare($sql);
        $statement->execute($params);

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

    private function internalEndpointId(int $companyId, string $extension): string
    {
        return 'tenant_' . $companyId . '_' . $extension;
    }

    private function internalAorId(int $companyId, string $extension): string
    {
        return $this->internalEndpointId($companyId, $extension);
    }

    private function internalAuthId(string $authUsername): string
    {
        return 'auth_' . $authUsername;
    }

    private function endpointIdentifyBy(): string
    {
        return 'auth_username';
    }

    private function authType(): string
    {
        return 'digest';
    }

    private function sipRealm(): string
    {
        return 'asterisk';
    }

    private function authUsername(): string
    {
        do {
            $username = 'u' . rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'AZ'), '=');
            $username = preg_replace('/[^A-Za-z0-9]/', '', $username) ?: bin2hex(random_bytes(8));
            $exists = $this->authUsernameExists($username);
        } while ($exists);

        return substr($username, 0, 32);
    }

    private function authUsernameExists(string $username): bool
    {
        $statement = $this->db()->prepare(
            'SELECT (
                SELECT COUNT(*)
                FROM ps_auths
                WHERE auth_username = :auth_username
                   OR username = :username
             ) + (
                SELECT COUNT(*)
                FROM ps_endpoints
                WHERE auth_username = :endpoint_auth_username
             )'
        );
        $statement->execute([
            'auth_username' => $username,
            'username' => $username,
            'endpoint_auth_username' => $username,
        ]);

        return (int) $statement->fetchColumn() > 0;
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

    private function yesNo(mixed $value): string
    {
        return in_array((string) $value, ['1', 'yes', 'true', 'on'], true) ? 'yes' : 'no';
    }

    private function back(string $path, array $errors, array $old): void
    {
        Session::flash('errors', $errors);
        Session::flash('old', $old);
        redirect($path);
    }
}
