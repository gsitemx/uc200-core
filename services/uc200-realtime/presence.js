'use strict';

function endpointFrom(value) {
  const text = String(value || '');
  const match = text.match(/tenant_(\d+)_([A-Za-z0-9_-]+)/);
  if (!match) return null;

  return {
    companyId: Number(match[1]),
    endpointId: `tenant_${match[1]}_${match[2]}`,
    extensionNumber: String(match[2]),
  };
}

function createPresenceStore({ log }) {
  const presence = new Map();
  const activeCalls = new Map();

  const tenantMap = (companyId) => {
    if (!presence.has(companyId)) {
      presence.set(companyId, new Map());
    }

    return presence.get(companyId);
  };

  const setPresence = (companyId, endpointId, status, extra = {}) => {
    tenantMap(companyId).set(endpointId, {
      company_id: companyId,
      endpoint_id: endpointId,
      extension_number: extra.extensionNumber || endpointId.split('_').slice(-1)[0],
      status,
      updated_at: new Date().toISOString(),
    });

    return tenantMap(companyId).get(endpointId);
  };

  const rememberCall = (key, payload) => {
    activeCalls.set(key, payload);
    return payload;
  };

  const endCall = (key, extra = {}) => {
    const current = activeCalls.get(key);
    if (!current) return null;
    const payload = { ...current, ...extra, state: 'ended', updated_at: new Date().toISOString() };
    activeCalls.delete(key);
    return payload;
  };

  const handle = (event) => {
    const output = {
      extension_status: [],
      active_call: [],
      call_popup: [],
      stats_tenants: new Set(),
    };

    const eventName = String(event.Event || '');
    if (eventName === '') return output;

    if (eventName === 'PeerStatus' || eventName === 'ExtensionStatus') {
      const endpoint = endpointFrom(event.Peer || event.ObjectName || event.Exten || event.Channel || '');
      if (!endpoint) return output;
      const rawStatus = String(event.PeerStatus || event.StatusText || event.Status || '').toLowerCase();
      const status = ['registered', 'reachable', 'ok', '1', 'available'].includes(rawStatus)
        ? 'available'
        : ['ringing', 'ring'].includes(rawStatus)
          ? 'ringing'
          : ['busy', 'inuse', '8'].includes(rawStatus)
            ? 'busy'
            : 'offline';
      output.extension_status.push(setPresence(endpoint.companyId, endpoint.endpointId, status, endpoint));
      output.stats_tenants.add(endpoint.companyId);
      return output;
    }

    if (eventName === 'DialBegin') {
      const origin = endpointFrom(event.Channel || '');
      if (!origin) return output;
      const key = String(event.Linkedid || event.Uniqueid || `${origin.endpointId}:${Date.now()}`);
      const payload = rememberCall(key, {
        company_id: origin.companyId,
        endpoint_id: origin.endpointId,
        extension_number: origin.extensionNumber,
        channel: String(event.Channel || ''),
        destination_channel: String(event.DestChannel || ''),
        remote_number: String(event.DialString || event.Destination || event.DestCallerIDNum || ''),
        direction: 'outbound',
        state: 'ringing',
        linkedid: key,
        uniqueid: String(event.Uniqueid || ''),
        started_at: new Date().toISOString(),
      });
      output.extension_status.push(setPresence(origin.companyId, origin.endpointId, 'ringing', origin));
      output.active_call.push(payload);
      output.call_popup.push(payload);
      output.stats_tenants.add(origin.companyId);
      return output;
    }

    if (eventName === 'BridgeEnter' || eventName === 'AgentConnect') {
      const endpoint = endpointFrom(event.Channel || event.DestChannel || '');
      if (!endpoint) return output;
      const key = String(event.Linkedid || event.Uniqueid || `${endpoint.endpointId}:${Date.now()}`);
      const payload = rememberCall(key, {
        ...(activeCalls.get(key) || {}),
        company_id: endpoint.companyId,
        endpoint_id: endpoint.endpointId,
        extension_number: endpoint.extensionNumber,
        channel: String(event.Channel || ''),
        destination_channel: String(event.DestChannel || ''),
        remote_number: String(event.CallerIDNum || event.ConnectedLineNum || ''),
        direction: 'active',
        state: 'active',
        linkedid: key,
        uniqueid: String(event.Uniqueid || ''),
        answered_at: new Date().toISOString(),
      });
      output.extension_status.push(setPresence(endpoint.companyId, endpoint.endpointId, 'busy', endpoint));
      output.active_call.push(payload);
      output.stats_tenants.add(endpoint.companyId);
      return output;
    }

    if (eventName === 'BridgeLeave' || eventName === 'DialEnd' || eventName === 'Hangup' || eventName === 'AgentComplete') {
      const endpoint = endpointFrom(event.Channel || event.DestChannel || '');
      if (!endpoint) return output;
      const key = String(event.Linkedid || event.Uniqueid || '');
      const payload = endCall(key, {
        company_id: endpoint.companyId,
        endpoint_id: endpoint.endpointId,
        extension_number: endpoint.extensionNumber,
        channel: String(event.Channel || ''),
        destination_channel: String(event.DestChannel || ''),
        ended_at: new Date().toISOString(),
      });
      output.extension_status.push(setPresence(endpoint.companyId, endpoint.endpointId, 'available', endpoint));
      if (payload) {
        output.active_call.push(payload);
      }
      output.stats_tenants.add(endpoint.companyId);
      return output;
    }

    if (eventName === 'Newchannel') {
      const endpoint = endpointFrom(event.Channel || '');
      if (!endpoint) return output;
      output.extension_status.push(setPresence(endpoint.companyId, endpoint.endpointId, 'ringing', endpoint));
      output.stats_tenants.add(endpoint.companyId);
    }

    return output;
  };

  return {
    handle,
    snapshot(companyId) {
      return Array.from((presence.get(companyId) || new Map()).values());
    },
    stats(companyId) {
      const rows = Array.from((presence.get(companyId) || new Map()).values());
      return {
        extensions_online: rows.filter((row) => row.status !== 'offline').length,
        active_calls: Array.from(activeCalls.values()).filter((row) => row.company_id === companyId && row.state !== 'ended').length,
      };
    },
  };
}

module.exports = { createPresenceStore, endpointFrom };
