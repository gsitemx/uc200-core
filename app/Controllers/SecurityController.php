<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\ApiTokenService;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\SecurityCenterService;
use RuntimeException;

final class SecurityController extends Controller
{
    public function index(Request $request): string
    {
        return $this->renderSection('overview', 'Seguridad PBX/SIP');
    }

    public function attacks(Request $request): string
    {
        return $this->renderSection('attacks', 'Ataques SIP');
    }

    public function bans(Request $request): string
    {
        return $this->renderSection('bans', 'IPs bloqueadas');
    }

    public function fail2ban(Request $request): string
    {
        return $this->renderSection('fail2ban', 'Fail2Ban');
    }

    public function firewall(Request $request): string
    {
        return $this->renderSection('firewall', 'Firewall');
    }

    public function whitelist(Request $request): string
    {
        return $this->renderSection('whitelist', 'Whitelist');
    }

    public function blacklist(Request $request): string
    {
        return $this->renderSection('blacklist', 'Blacklist');
    }

    public function saveSettings(Request $request): void
    {
        $this->authorizeManage();
        $service = $this->service();
        if (! $service->ready()) {
            Session::flash('error', 'Faltan las tablas de seguridad. Ejecuta la migracion correspondiente.');
            redirect('/security');
        }

        $companyId = $this->scopeCompanyId($request);
        $service->saveSettings($companyId, (int) Session::get('user_id'), [
            'jail_name' => (string) $request->input('jail_name', 'uc200-asterisk'),
            'maxretry' => (string) $request->input('maxretry', '10'),
            'findtime' => (string) $request->input('findtime', '300'),
            'bantime' => (string) $request->input('bantime', '3600'),
            'enabled' => (string) $request->input('enabled', '0'),
            'logpath' => (string) $request->input('logpath', '/var/log/asterisk/messages'),
            'window_minutes' => (string) $request->input('window_minutes', '5'),
            'register_flood_threshold' => (string) $request->input('register_flood_threshold', '10'),
            'multi_extension_threshold' => (string) $request->input('multi_extension_threshold', '3'),
            'event_retention_days' => (string) $request->input('event_retention_days', '7'),
        ]);

        (new AuditService($this->db()))->record('security.settings.updated', 'security_settings', null, [
            'company_id' => $companyId,
        ], $companyId);

        Session::flash('success', 'Configuracion de seguridad guardada.');
        redirect('/security/fail2ban');
    }

    public function ban(Request $request): void
    {
        $this->authorizeManage();
        $this->performIpAction($request, 'ban');
    }

    public function unban(Request $request): void
    {
        $this->authorizeManage();
        $this->performIpAction($request, 'unban');
    }

    public function firewallBlock(Request $request): void
    {
        $this->authorizeManage();
        $this->performIpAction($request, 'firewall_block');
    }

    public function firewallUnblock(Request $request): void
    {
        $this->authorizeManage();
        $this->performIpAction($request, 'firewall_unblock');
    }

    public function persistFirewall(Request $request): void
    {
        $this->authorizeManage();
        $service = $this->service();
        $companyId = $this->scopeCompanyId($request);
        $result = $service->persistFirewallRules($companyId, (int) Session::get('user_id'));

        (new AuditService($this->db()))->record('security.firewall.persist', 'security_bans', null, [
            'ok' => $result['ok'],
        ], $companyId);

        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('/security/firewall');
    }

    public function restartFail2ban(Request $request): void
    {
        $this->authorizeManage();
        $service = $this->service();
        $companyId = $this->scopeCompanyId($request);
        $result = $service->restartFail2ban($companyId, (int) Session::get('user_id'));

        (new AuditService($this->db()))->record('security.fail2ban.restart', 'security_bans', null, [
            'ok' => $result['ok'],
        ], $companyId);

        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('/security/fail2ban');
    }

    public function reloadFail2ban(Request $request): void
    {
        $this->authorizeManage();
        $service = $this->service();
        $companyId = $this->scopeCompanyId($request);
        $result = $service->reloadFail2ban($companyId, (int) Session::get('user_id'));

        (new AuditService($this->db()))->record('security.fail2ban.reload', 'security_bans', null, [
            'ok' => $result['ok'],
        ], $companyId);

        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('/security/fail2ban');
    }

    public function storeWhitelist(Request $request): void
    {
        $this->authorizeManage();
        $service = $this->service();
        $companyId = $this->scopeCompanyId($request);

        try {
            $result = $service->addWhitelist(
                $companyId,
                (int) Session::get('user_id'),
                (string) $request->input('ip', ''),
                trim((string) $request->input('reason', 'Whitelist manual'))
            );
            (new AuditService($this->db()))->record('security.whitelist.added', 'security_ip_whitelist', null, [
                'ip' => (string) $request->input('ip', ''),
            ], $companyId);
            Session::flash('success', $result['message']);
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        redirect('/security/whitelist');
    }

    public function deleteWhitelist(Request $request): void
    {
        $this->authorizeManage();
        $service = $this->service();
        $companyId = $this->scopeCompanyId($request);

        try {
            $result = $service->removeWhitelist($companyId, (int) Session::get('user_id'), (string) $request->input('id', ''));
            (new AuditService($this->db()))->record('security.whitelist.removed', 'security_ip_whitelist', null, [
                'uuid' => (string) $request->input('id', ''),
            ], $companyId);
            Session::flash('success', $result['message']);
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        redirect('/security/whitelist');
    }

    public function storeBlacklist(Request $request): void
    {
        $this->authorizeManage();
        $service = $this->service();
        $companyId = $this->scopeCompanyId($request);

        try {
            $result = $service->addBlacklist(
                $companyId,
                (int) Session::get('user_id'),
                (string) $request->input('ip', ''),
                trim((string) $request->input('reason', 'Blacklist manual'))
            );
            (new AuditService($this->db()))->record('security.blacklist.added', 'security_ip_blacklist', null, [
                'ip' => (string) $request->input('ip', ''),
            ], $companyId);
            Session::flash('success', $result['message']);
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        redirect('/security/blacklist');
    }

    public function deleteBlacklist(Request $request): void
    {
        $this->authorizeManage();
        $service = $this->service();
        $companyId = $this->scopeCompanyId($request);

        try {
            $result = $service->removeBlacklist($companyId, (int) Session::get('user_id'), (string) $request->input('id', ''));
            (new AuditService($this->db()))->record('security.blacklist.removed', 'security_ip_blacklist', null, [
                'uuid' => (string) $request->input('id', ''),
            ], $companyId);
            Session::flash('success', $result['message']);
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        redirect('/security/blacklist');
    }

    private function renderSection(string $section, string $title): string
    {
        $service = $this->service();
        if (! $service->ready()) {
            return view('pbx/setup', [
                'title' => $title,
                'missingTables' => ['security_events', 'security_bans', 'security_ip_whitelist', 'security_ip_blacklist', 'security_settings'],
            ]);
        }

        return view('security/index', [
            'title' => $title,
            'section' => $section,
            'dashboard' => $service->dashboard($this->scopeCompanyId()),
            'canManage' => $this->hasPermission((int) Session::get('user_id'), 'security.manage'),
            'isSuperAdmin' => has_role('super-admin'),
            'flash' => Session::flash('success'),
            'error' => Session::flash('error'),
        ]);
    }

    private function performIpAction(Request $request, string $action): void
    {
        $service = $this->service();
        $companyId = $this->scopeCompanyId($request);
        $ip = (string) $request->input('ip', '');
        $reason = trim((string) $request->input('reason', 'Manual action'));

        if ($this->rateLimited('security:' . $action . ':' . (int) Session::get('user_id'), 20, 60)) {
            Session::flash('error', 'Se alcanzo el limite temporal de acciones de seguridad.');
            redirect($this->redirectTargetFor($action));
        }

        try {
            $result = match ($action) {
                'ban' => $service->banIp($companyId, (int) Session::get('user_id'), $ip, $reason),
                'unban' => $service->unbanIp($companyId, (int) Session::get('user_id'), $ip, $reason),
                'firewall_block' => $service->blockFirewallIp($companyId, (int) Session::get('user_id'), $ip, $reason),
                'firewall_unblock' => $service->unblockFirewallIp($companyId, (int) Session::get('user_id'), $ip, $reason),
                default => ['ok' => false, 'message' => 'Unsupported action.'],
            };

            (new AuditService($this->db()))->record('security.' . $action, 'security_bans', null, [
                'ip' => $ip,
                'reason' => $reason,
                'ok' => $result['ok'] ?? false,
            ], $companyId);

            Session::flash(($result['ok'] ?? false) ? 'success' : 'error', (string) ($result['message'] ?? 'No se pudo completar la accion.'));
        } catch (RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
        }

        redirect($this->redirectTargetFor($action));
    }

    private function redirectTargetFor(string $action): string
    {
        return match ($action) {
            'ban', 'unban' => '/security/bans',
            'firewall_block', 'firewall_unblock' => '/security/firewall',
            default => '/security',
        };
    }

    private function scopeCompanyId(?Request $request = null): ?int
    {
        if (! has_role('super-admin')) {
            $companyId = (int) Session::get('company_id', 0);
            return $companyId > 0 ? $companyId : null;
        }

        $requested = $request !== null ? (int) $request->input('company_id', 0) : 0;
        return $requested > 0 ? $requested : null;
    }

    private function authorizeManage(): void
    {
        $userId = (int) Session::get('user_id', 0);
        if (! $this->hasPermission($userId, 'security.manage')) {
            Session::flash('error', 'No tienes permisos para administrar el Security Center.');
            redirect('/security');
        }
    }

    private function hasPermission(int $userId, string $permission): bool
    {
        return has_role('super-admin') || (new PermissionService($this->db()))->userHasPermission($userId, $permission);
    }

    private function rateLimited(string $key, int $limit, int $windowSeconds): bool
    {
        return (new ApiTokenService($this->db()))->hitRateLimit($key, $limit, $windowSeconds);
    }

    private function service(): SecurityCenterService
    {
        return new SecurityCenterService($this->db());
    }
}
