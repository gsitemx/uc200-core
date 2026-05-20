'use strict';

const { endpointFrom } = require('./presence');

function createQueueStore() {
  const queues = new Map();

  const tenantQueue = (companyId) => {
    if (!queues.has(companyId)) {
      queues.set(companyId, new Map());
    }

    return queues.get(companyId);
  };

  const ensureQueue = (companyId, queueName) => {
    const map = tenantQueue(companyId);
    if (!map.has(queueName)) {
      map.set(queueName, {
        company_id: companyId,
        queue: queueName,
        waiting: 0,
        active_calls: 0,
        agents_online: 0,
        updated_at: new Date().toISOString(),
      });
    }

    return map.get(queueName);
  };

  const handle = (event) => {
    const output = [];
    const eventName = String(event.Event || '');
    if (![
      'QueueCallerJoin',
      'QueueCallerLeave',
      'AgentConnect',
      'AgentComplete',
    ].includes(eventName)) {
      return output;
    }

    const endpoint = endpointFrom(event.Channel || event.Interface || '');
    if (!endpoint) return output;

    const queue = ensureQueue(endpoint.companyId, String(event.Queue || 'default'));
    if (eventName === 'QueueCallerJoin') queue.waiting += 1;
    if (eventName === 'QueueCallerLeave') queue.waiting = Math.max(0, queue.waiting - 1);
    if (eventName === 'AgentConnect') {
      queue.active_calls += 1;
      queue.agents_online = Math.max(queue.agents_online, 1);
    }
    if (eventName === 'AgentComplete') queue.active_calls = Math.max(0, queue.active_calls - 1);
    queue.updated_at = new Date().toISOString();
    output.push({ ...queue });
    return output;
  };

  return {
    handle,
    snapshot(companyId) {
      return Array.from((queues.get(companyId) || new Map()).values());
    },
    stats(companyId) {
      const rows = this.snapshot(companyId);
      return {
        queues_waiting: rows.reduce((carry, row) => carry + Number(row.waiting || 0), 0),
        active_calls: rows.reduce((carry, row) => carry + Number(row.active_calls || 0), 0),
        agents_online: rows.reduce((carry, row) => carry + Number(row.agents_online || 0), 0),
      };
    },
  };
}

module.exports = { createQueueStore };
