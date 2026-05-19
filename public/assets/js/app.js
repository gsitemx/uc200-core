document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
  button.addEventListener('click', () => {
    const current = document.documentElement.dataset.theme || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = next;
    localStorage.setItem('uc200-theme', next);
  });
});

const sidebar = document.getElementById('sidebar');
document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
  button.addEventListener('click', () => {
    sidebar?.classList.toggle('open');
  });
});

document.querySelectorAll('[data-table-search]').forEach((input) => {
  input.addEventListener('input', () => {
    const table = document.querySelector(input.dataset.tableSearch);
    const query = input.value.trim().toLowerCase();

    table?.querySelectorAll('tbody tr').forEach((row) => {
      row.hidden = query !== '' && !row.textContent.toLowerCase().includes(query);
    });
  });
});

document.querySelectorAll('[data-modal-open]').forEach((button) => {
  button.addEventListener('click', () => {
    const modal = document.querySelector(`[data-modal="${button.dataset.modalOpen}"]`);
    if (modal) {
      modal.hidden = false;
      modal.style.display = 'grid';
      modal.classList.add('is-open');
    }
  });
});

const closeModal = (modal) => {
  if (!modal) return;
  modal.classList.remove('is-open');
  modal.style.display = 'none';
  modal.hidden = true;
};

document.querySelectorAll('[data-modal-close]').forEach((button) => {
  button.addEventListener('click', () => {
    closeModal(button.closest('[data-modal]'));
  });
});

document.querySelectorAll('[data-modal]').forEach((modal) => {
  modal.addEventListener('click', (event) => {
    if (event.target === modal) closeModal(modal);
  });
});

document.querySelectorAll('[data-modal]').forEach((modal) => {
  if (!modal.classList.contains('is-open')) {
    modal.style.display = 'none';
    modal.hidden = true;
  }
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    document.querySelectorAll('[data-modal].is-open').forEach(closeModal);
  }
});

document.querySelectorAll('[data-secret-toggle]').forEach((button) => {
  button.addEventListener('click', () => {
    const scope = button.closest('[data-modal]') || button.closest('.field') || document;
    scope.querySelectorAll('[data-secret-field]').forEach((input) => {
      input.type = input.type === 'password' ? 'text' : 'password';
    });
  });
});

document.querySelectorAll('[data-copy-target="previous"]').forEach((button) => {
  button.addEventListener('click', async () => {
    const input = button.parentElement?.querySelector('[data-copy-value], input');
    if (input?.value) await navigator.clipboard?.writeText(input.value).catch(() => {});
  });
});

document.querySelectorAll('[data-copy-config]').forEach((button) => {
  button.addEventListener('click', async () => {
    const modal = button.closest('[data-modal]') || document;
    const text = modal.querySelector('[data-config-copy]')?.value || '';
    if (text) await navigator.clipboard?.writeText(text).catch(() => {});
  });
});

document.querySelectorAll('[data-device-type]').forEach((select) => {
  select.addEventListener('change', () => {
    const form = select.closest('form');
    const transport = form?.querySelector('[data-transport-select]');
    const codecs = form?.querySelector('[data-codecs-input]');
    if (select.value === 'webrtc') {
      if (transport && !transport.value) transport.value = 'transport-wss';
      if (codecs) codecs.value = 'opus,ulaw,alaw';
      return;
    }
    if (codecs && codecs.value === 'opus,ulaw,alaw') codecs.value = 'ulaw,alaw';
  });
});

document.querySelectorAll('[data-voicemail-toggle]').forEach((select) => {
  select.addEventListener('change', () => {
    const mailbox = select.closest('form')?.querySelector('[data-mailbox-input]');
    if (mailbox && select.value === 'no') mailbox.value = '';
  });
});
