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

const menuUserId = document.body?.dataset.userId || 'guest';
const menuSectionKey = (id) => `uc200-menu-section:${menuUserId}:${id}`;
const menuFavoritesKey = `uc200-menu-favorites:${menuUserId}`;

const readMenuFavorites = () => {
  try {
    const parsed = JSON.parse(localStorage.getItem(menuFavoritesKey) || '[]');
    return Array.isArray(parsed) ? parsed.filter((value) => typeof value === 'string') : [];
  } catch (error) {
    return [];
  }
};

const writeMenuFavorites = (favorites) => {
  localStorage.setItem(menuFavoritesKey, JSON.stringify(favorites));
};

const syncMenuFavorites = () => {
  const favorites = readMenuFavorites();
  document.querySelectorAll('[data-menu-item-row]').forEach((row) => {
    const active = favorites.includes(row.dataset.menuItemId || '');
    row.querySelectorAll('[data-menu-favorite-toggle]').forEach((button) => {
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  });
};

const buildFavoriteSection = () => {
  const favoritesSection = document.querySelector('[data-menu-favorites-section]');
  const favoritesList = document.querySelector('[data-menu-favorites-list]');
  if (!favoritesSection || !favoritesList) return;

  favoritesList.innerHTML = '';
  const favorites = readMenuFavorites();

  favorites.forEach((id) => {
    const source = document.querySelector(`[data-menu-item-row][data-menu-item-source="1"][data-menu-item-id="${id}"]`);
    if (!source) return;
    const clone = source.cloneNode(true);
    clone.dataset.menuItemSource = '0';
    clone.classList.add('is-favorite-clone');
    favoritesList.appendChild(clone);
  });

  favoritesSection.hidden = favoritesList.children.length === 0;
  syncMenuFavorites();
};

document.addEventListener('click', (event) => {
  const toggle = event.target.closest('[data-menu-section-toggle]');
  if (toggle) {
    const section = toggle.closest('[data-menu-section]');
    if (section) {
      section.classList.toggle('collapsed');
      const expanded = !section.classList.contains('collapsed');
      toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      localStorage.setItem(menuSectionKey(section.dataset.menuSection), expanded ? 'open' : 'collapsed');
    }
    return;
  }

  const favorite = event.target.closest('[data-menu-favorite-toggle]');
  if (!favorite) return;

  event.preventDefault();
  event.stopPropagation();
  const row = favorite.closest('[data-menu-item-row]');
  if (!row) return;

  const id = row.dataset.menuItemId;
  if (!id) return;

  const favorites = readMenuFavorites();
  const next = favorites.includes(id)
    ? favorites.filter((value) => value !== id)
    : [...favorites, id];

  writeMenuFavorites(next);
  buildFavoriteSection();
});

document.querySelectorAll('[data-menu-section]').forEach((section) => {
  const toggle = section.querySelector('[data-menu-section-toggle]');
  const stored = localStorage.getItem(menuSectionKey(section.dataset.menuSection));
  const defaultOpen = section.dataset.defaultOpen === 'true' || section.classList.contains('active');
  const expanded = stored ? stored === 'open' : defaultOpen;

  section.classList.toggle('collapsed', !expanded);
  toggle?.setAttribute('aria-expanded', expanded ? 'true' : 'false');
});

document.querySelectorAll('[data-menu-search]').forEach((input) => {
  input.addEventListener('input', () => {
    const query = input.value.trim().toLowerCase();

    document.querySelectorAll('[data-menu-section]').forEach((section) => {
      let visibleRows = 0;

      section.querySelectorAll('[data-menu-item-row]').forEach((row) => {
        const searchable = (row.dataset.menuSearchText || '').toLowerCase();
        const visible = query === '' || searchable.includes(query);
        row.hidden = !visible;
        if (visible) visibleRows += 1;
      });

      section.hidden = visibleRows === 0;
    });
  });
});

buildFavoriteSection();
syncMenuFavorites();

document.querySelectorAll('[data-voicemail-toggle]').forEach((select) => {
  select.addEventListener('change', () => {
    const mailbox = select.closest('form')?.querySelector('[data-mailbox-input]');
    if (mailbox && select.value === 'no') mailbox.value = '';
  });
});

const provisioningCatalog = window.uc200ProvisioningCatalog || null;
const provisioningBrands = window.uc200ProvisioningBrands || null;
const provisioningSelected = window.uc200ProvisioningSelected || null;

const updateProvisioningModels = (form) => {
  if (!form || !provisioningCatalog || !provisioningBrands) return;

  const brandSelect = form.querySelector('[data-provisioning-brand]');
  const modelSelect = form.querySelector('[data-provisioning-model]');
  const instructionsList = form.querySelector('[data-provisioning-instructions]');
  const notes = form.querySelector('[data-provisioning-notes]');

  if (!brandSelect || !modelSelect) return;

  const vendor = brandSelect.value;
  const models = provisioningCatalog[vendor] || [];
  const selected = modelSelect.dataset.selectedValue || modelSelect.value || provisioningSelected?.model || '';

  modelSelect.innerHTML = '';
  models.forEach((model, index) => {
    const option = document.createElement('option');
    option.value = model.value;
    option.textContent = model.label;
    option.selected = selected ? selected === model.value : index === 0;
    modelSelect.appendChild(option);
  });

  const activeModel = models.find((model) => model.value === modelSelect.value) || models[0] || null;
  if (activeModel && notes) {
    notes.textContent = activeModel.notes || '';
  }

  if (instructionsList) {
    instructionsList.innerHTML = '';
    (provisioningBrands[vendor]?.instructions || []).forEach((instruction) => {
      const item = document.createElement('li');
      item.textContent = instruction;
      instructionsList.appendChild(item);
    });
  }
};

const updateProvisioningExtensions = (form) => {
  const companySelect = form?.querySelector('[data-provisioning-company]');
  const extensionSelect = form?.querySelector('[data-provisioning-extension]');

  if (!companySelect || !extensionSelect) return;

  const companyId = companySelect.value;
  Array.from(extensionSelect.options).forEach((option, index) => {
    if (index === 0) {
      option.hidden = false;
      return;
    }

    const optionCompanyId = option.dataset.companyId || '';
    option.hidden = companyId !== '0' && companyId !== '' && optionCompanyId !== companyId;
  });

  const selected = extensionSelect.selectedOptions[0];
  if (selected?.hidden) {
    extensionSelect.value = '';
  }
};

document.querySelectorAll('form').forEach((form) => {
  const brandSelect = form.querySelector('[data-provisioning-brand]');
  const modelSelect = form.querySelector('[data-provisioning-model]');
  const companySelect = form.querySelector('[data-provisioning-company]');

  if (!brandSelect || !modelSelect || !provisioningCatalog) return;

  modelSelect.dataset.selectedValue = provisioningSelected?.model || modelSelect.value || '';
  updateProvisioningModels(form);
  updateProvisioningExtensions(form);

  brandSelect.addEventListener('change', () => {
    modelSelect.dataset.selectedValue = '';
    updateProvisioningModels(form);
  });

  companySelect?.addEventListener('change', () => {
    updateProvisioningExtensions(form);
  });
});
