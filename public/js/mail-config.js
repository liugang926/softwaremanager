// Softwaremanager - group mail targets UI helpers
(function() {
  function $(sel, root) { return (root||document).querySelector(sel); }
  function $all(sel, root) { return Array.from((root||document).querySelectorAll(sel)); }

  function init() {
    const container = document.getElementById('sm-mail-config');
    if (!container) return;

    // Toggle JSON editors visibility based on advanced switch
    const advToggle = $('#sm-advanced-toggle');
    const advBlocks = $all('.sm-advanced-block');
    if (advToggle) {
      const apply = () => {
        const on = advToggle.checked;
        advBlocks.forEach(el => el.classList.toggle('sm-hidden', !on));
      };
      advToggle.addEventListener('change', apply);
      apply();
    }

    // New click-based selection system
    const form = $('#sm-add-form');
    if (!form) return;
    const usersSel   = $('#sm-users');
    const groupsSel  = $('#sm-groups');
    const profilesSel= $('#sm-profiles');
    const emailsInp  = $('#sm-emails');
    const recipients = $('#sm-recipients-json');
    const options    = $('#sm-options-json');
    const targetGroupHidden = document.getElementById('sm-target-group-id');

    const onlyOnViolation = $('#sm-opt-only');
    const threshold       = $('#sm-opt-thres');
    const merge           = $('#sm-opt-merge');
    const scope           = $('#sm-opt-scope');

    // Selection state
    const selectedItems = {
      users: new Set(),
      groups: new Set(),
      profiles: new Set(),
      emails: new Set()
    };

    function getMultiValues(sel) {
      return $all('option:checked', sel).map(o => parseInt(o.value, 10)).filter(n => Number.isFinite(n));
    }

    function buildRecipientsJSON() {
      const emails = (emailsInp.value || '').split(',').map(s => s.trim()).filter(Boolean);
      // Update emails set
      selectedItems.emails.clear();
      emails.forEach(email => selectedItems.emails.add(email));
      
      const obj = {
        users: Array.from(selectedItems.users),
        groups: Array.from(selectedItems.groups), // recipients groups (multi)
        profiles: Array.from(selectedItems.profiles),
        emails: Array.from(selectedItems.emails)
      };
      recipients.value = JSON.stringify(obj);
      
      // Sync hidden selects
      syncHiddenSelects();
      renderPickedAreas();
      updateTargetGroupPrimary();
    }

    function syncHiddenSelects() {
      // Update hidden select elements for form submission
      if (usersSel) {
        Array.from(usersSel.options).forEach(opt => {
          opt.selected = selectedItems.users.has(parseInt(opt.value));
        });
      }
      if (groupsSel) {
        Array.from(groupsSel.options).forEach(opt => {
          opt.selected = selectedItems.groups.has(parseInt(opt.value));
        });
      }
      if (profilesSel) {
        Array.from(profilesSel.options).forEach(opt => {
          opt.selected = selectedItems.profiles.has(parseInt(opt.value));
        });
      }
    }

    // Target group primary selection (first selected from target UI)
    function updateTargetGroupPrimary() {
      if (!targetGroupHidden) return;
      // Prefer the first pill in target-picked area; if none, keep existing or zero
      const picked = document.querySelectorAll('#sm-target-groups-picked .sm-pill');
      if (picked.length > 0) {
        const val = picked[0].getAttribute('data-value');
        if (val) targetGroupHidden.value = parseInt(val, 10) || 0;
      }
    }

    function buildOptionsJSON() {
      const obj = {
        only_on_violation: !!onlyOnViolation.checked,
        threshold_unmanaged: parseInt(threshold.value || '0', 10) || 0,
        merge: !!merge.checked,
        scope: scope.value || 'both',
        attach_csv: false
      };
      options.value = JSON.stringify(obj);
    }

    // Render picked items in individual areas
    function renderPickedAreas() {
      renderPickedAreaToken('users', 'sm-users-picked', 'sm-pill-user', 'sm-users-tokenbox', 'sm-users-filter');
      renderPickedAreaToken('groups', 'sm-groups-picked', 'sm-pill-group', 'sm-groups-tokenbox', 'sm-groups-filter');
      renderPickedAreaToken('profiles', 'sm-profiles-picked', 'sm-pill-profile', 'sm-profiles-tokenbox', 'sm-profiles-filter');
      renderPickedArea('emails', 'sm-emails-picked', 'sm-pill-email');
    }

    function renderPickedArea(type, containerId, pillClass) {
      const container = document.getElementById(containerId);
      if (!container) return;
      
      const items = Array.from(selectedItems[type]);
      if (items.length === 0) {
        container.innerHTML = '<em style="color:#9ca3af; font-size:11px;">无选择</em>';
        return;
      }
      
      const pills = items.map(value => {
        let label = value;
        if (type !== 'emails') {
          // Get label from option list
          const optionItem = document.querySelector(`[data-type="${type.slice(0,-1)}"][data-value="${value}"]`);
          if (optionItem) {
            label = optionItem.dataset.label || optionItem.textContent;
          }
        }
        return `<span class="sm-pill ${pillClass} sm-pill-removable" data-type="${type}" data-value="${value}" title="点击移除">${label} <span class="sm-pill-remove">×</span></span>`;
      });
      
      container.innerHTML = pills.join(' ');
      
      // Add click handlers for removal
      $all('.sm-pill-removable', container).forEach(pill => {
        pill.addEventListener('click', (e) => {
          e.preventDefault();
          const type = pill.dataset.type;
          const value = pill.dataset.value;
          removeItem(type, value);
        });
      });
    }

    function renderPickedAreaToken(type, containerId, pillClass, tokenboxId, inputId) {
      const picked = document.getElementById(containerId);
      const tokenBox = document.getElementById(tokenboxId);
      const inputEl = document.getElementById(inputId);
      if (!tokenBox || !inputEl) { renderPickedArea(type, containerId, pillClass); return; }
      const items = Array.from(selectedItems[type] || []);
      tokenBox.innerHTML = '';
      if (items.length) {
        items.forEach(v => {
          let label = String(v);
          const listId = (type === 'groups') ? 'sm-groups-list' : (type === 'profiles' ? 'sm-profiles-list' : 'sm-users-list');
          const list = document.getElementById(listId);
          const node = list ? list.querySelector(`.sm-option-item[data-value="${v}"]`) : null;
          if (node) label = node.getAttribute('data-label') || node.textContent.trim();
          tokenBox.insertAdjacentHTML('beforeend', `<span class="sm-pill ${pillClass}" data-value="${v}">${label} <span class="sm-pill-remove">×</span></span>`);
        });
      }
      tokenBox.appendChild(inputEl);
      // keep legacy picked updated (hidden)
      if (picked) {
        picked.innerHTML = '';
        items.forEach(v => picked.insertAdjacentHTML('beforeend', `<span class="sm-pill ${pillClass}" data-value="${v}">${v}</span>`));
      }
    }

    function removeItem(type, value) {
      if (type === 'emails') {
        selectedItems.emails.delete(value);
        // Update input field
        const emails = Array.from(selectedItems.emails);
        emailsInp.value = emails.join(', ');
      } else {
        const numValue = parseInt(value);
        selectedItems[type].delete(numValue);
        // Update visual state
        const optionItem = document.querySelector(`[data-type="${type.slice(0,-1)}"][data-value="${value}"]`);
        if (optionItem) {
          optionItem.classList.remove('selected');
        }
      }
      buildRecipientsJSON();
    }

    // Initialize click-based selection
    function initializeClickSelection() {
      // Load initial selected state from hidden selects
      if (usersSel) {
        Array.from(usersSel.options).forEach(opt => {
          if (opt.selected) {
            selectedItems.users.add(parseInt(opt.value));
          }
        });
      }
      if (groupsSel) {
        Array.from(groupsSel.options).forEach(opt => {
          if (opt.selected) {
            selectedItems.groups.add(parseInt(opt.value));
          }
        });
      }
      if (profilesSel) {
        Array.from(profilesSel.options).forEach(opt => {
          if (opt.selected) {
            selectedItems.profiles.add(parseInt(opt.value));
          }
        });
      }
      
      // Update visual state
      updateOptionItemsVisualState();
      
      // Add click handlers to option items
      $all('.sm-option-item').forEach(item => {
        item.addEventListener('click', (e) => {
          e.preventDefault();
          const type = item.dataset.type;
          const value = parseInt(item.dataset.value);
          const setKey = type + 's';
          
          if (selectedItems[setKey].has(value)) {
            selectedItems[setKey].delete(value);
            item.classList.remove('selected');
          } else {
            selectedItems[setKey].add(value);
            item.classList.add('selected');
          }
          
          // 清空对应输入框以移除索引字符，并重置过滤
          const inputId = type === 'user' ? 'sm-users-filter' : (type === 'group' ? 'sm-groups-filter' : 'sm-profiles-filter');
          const inputEl = document.getElementById(inputId);
          if (inputEl) {
            inputEl.value = '';
            // 显示全部项
            const listId = type === 'user' ? 'sm-users-list' : (type === 'group' ? 'sm-groups-list' : 'sm-profiles-list');
            const listEl = document.getElementById(listId);
            if (listEl) $all('.sm-option-item', listEl).forEach(i => i.style.display = 'block');
            setTimeout(() => inputEl.focus(), 0);
          }

          buildRecipientsJSON();
        });
      });
    }

    function updateOptionItemsVisualState() {
      $all('.sm-option-item').forEach(item => {
        const type = item.dataset.type;
        const value = parseInt(item.dataset.value);
        const setKey = type + 's';
        
        if (selectedItems[setKey] && selectedItems[setKey].has(value)) {
          item.classList.add('selected');
        } else {
          item.classList.remove('selected');
        }
      });
    }

    // Filter support for option lists
    function attachListFilter(listId, filterId, tokenboxId) {
      const list = document.getElementById(listId);
      const filter = document.getElementById(filterId);
      const tokenbox = tokenboxId ? document.getElementById(tokenboxId) : null;
      if (!list || !filter) return;

      // show list on focus/typing
      function openList() { list.classList.add('open'); filter.classList.add('open'); }
      function closeList() { list.classList.remove('open'); filter.classList.remove('open'); }

      function applyFilter() {
        const query = (filter.value || '').toLowerCase();
        $all('.sm-option-item', list).forEach(item => {
          const text = (item.textContent || '').toLowerCase();
          // 避免与 updateOptionItemsVisualState 冲突，先不改 class，只设置 display
          if (!query) {
            item.style.display = 'block';
          } else {
            item.style.display = text.includes(query) ? 'block' : 'none';
          }
        });
      }

      filter.addEventListener('focus', () => {
        openList();
        applyFilter();
      });
      // 兼容中文输入法：input/keyup/compositionend 都触发
      let composing = false;
      filter.addEventListener('compositionstart', () => { composing = true; });
      filter.addEventListener('compositionend', () => { composing = false; openList(); applyFilter(); });
      filter.addEventListener('input', () => { if (composing) return; openList(); applyFilter(); });
      filter.addEventListener('keyup', () => { if (composing) return; openList(); applyFilter(); });

      if (tokenbox) {
        tokenbox.addEventListener('click', () => { openList(); filter.focus(); });
      }

      // hide list when clicking outside
      document.addEventListener('click', (e) => {
        const inside = list.contains(e.target) || filter.contains(e.target);
        if (!inside) closeList();
      });

      // keep open when hovering list
      list.addEventListener('mouseenter', openList);
      filter.addEventListener('blur', () => {
        setTimeout(() => {
          if (!list.matches(':hover')) closeList();
        }, 120);
      });
    }

    attachListFilter('sm-users-list', 'sm-users-filter', 'sm-users-tokenbox');
    attachListFilter('sm-groups-list', 'sm-groups-filter', 'sm-groups-tokenbox');
    attachListFilter('sm-profiles-list', 'sm-profiles-filter', 'sm-profiles-tokenbox');
    attachListFilter('sm-target-groups-list', 'sm-target-groups-filter', 'sm-target-groups-tokenbox');

    // Wire up
    emailsInp && emailsInp.addEventListener('input', buildRecipientsJSON);
    [onlyOnViolation, threshold, merge, scope].forEach(el => el && el.addEventListener('change', buildOptionsJSON));

    // Initial setup
    initializeClickSelection();
    buildRecipientsJSON();
    buildOptionsJSON();

    // target group multi picker (for batch processing; not for view filtering)
    (function initTargetGroupPicker() {
      const list = document.getElementById('sm-target-groups-list');
      const picked = document.getElementById('sm-target-groups-picked');
      if (!list || !picked) return;
      const targetSelected = new Set();
      Array.from(list.querySelectorAll('.sm-option-item.selected')).forEach(el => {
        const v = parseInt(el.getAttribute('data-value'), 10); if (v) targetSelected.add(v);
      });
      // Also seed from hidden JSON value for reliability
      const tgHidden = document.getElementById('sm-target-groups-json');
      try {
        const pre = tgHidden && tgHidden.value ? JSON.parse(tgHidden.value) : [];
        if (Array.isArray(pre)) { pre.forEach(v => { v = parseInt(v,10); if (v) targetSelected.add(v); }); }
      } catch(_) {}
      const currentVal = parseInt(targetGroupHidden && targetGroupHidden.value || '0', 10) || 0;
      if (currentVal) targetSelected.add(currentVal);

      function render() {
        picked.innerHTML = '';
        const tokenBox = document.getElementById('sm-target-groups-tokenbox');
        if (tokenBox) {
          // 清除 tokenBox 中的现有 token，仅保留输入框
          const inputEl = document.getElementById('sm-target-groups-filter');
          tokenBox.innerHTML = '';
          // 先渲染 tokens
          if (targetSelected.size === 0) {
            // 保持空，仅显示输入框
          } else {
            targetSelected.forEach(v => {
              const item = list.querySelector(`.sm-option-item[data-value="${v}"]`);
              const label = item ? (item.getAttribute('data-label') || item.textContent.trim()) : ('#'+v);
              tokenBox.insertAdjacentHTML('beforeend', `<span class="sm-pill sm-pill-group" data-value="${v}">${label} <span class="sm-pill-remove">×</span></span>`);
            });
          }
          // 再放回输入框
          if (inputEl) tokenBox.appendChild(inputEl);
        } else {
          // fallback: 渲染到 picked 区域
          if (targetSelected.size === 0) {
            picked.innerHTML = '<em style="color:#9ca3af; font-size:11px;">无选择</em>';
          } else {
            targetSelected.forEach(v => {
              const item = list.querySelector(`.sm-option-item[data-value="${v}"]`);
              const label = item ? (item.getAttribute('data-label') || item.textContent.trim()) : ('#'+v);
              picked.insertAdjacentHTML('beforeend', `<span class="sm-pill sm-pill-group" data-value="${v}">${label} <span class="sm-pill-remove">×</span></span>`);
            });
          }
        }
        Array.from(list.querySelectorAll('.sm-option-item')).forEach(el => {
          const v = parseInt(el.getAttribute('data-value'), 10);
          el.classList.toggle('selected', targetSelected.has(v));
        });
        const first = targetSelected.values().next().value || 0;
        if (targetGroupHidden) targetGroupHidden.value = first;
        const tgHidden = document.getElementById('sm-target-groups-json');
        if (tgHidden) tgHidden.value = JSON.stringify(Array.from(targetSelected));
      }

      list.addEventListener('click', (e) => {
        const item = e.target.closest('.sm-option-item'); if (!item) return;
        const val = parseInt(item.getAttribute('data-value'), 10) || 0; if (!val) return;
        if (targetSelected.has(val)) targetSelected.delete(val); else targetSelected.add(val);
        render();
      });

      // remove token on click (tokenbox or fallback picked)
      const removeHandler = (e) => {
        const pill = e.target.closest('.sm-pill'); if (!pill) return;
        const val = parseInt(pill.getAttribute('data-value'), 10) || 0;
        if (targetSelected.has(val)) targetSelected.delete(val);
        render();
      };
      picked.addEventListener('click', removeHandler);
      const tokenBoxEl = document.getElementById('sm-target-groups-tokenbox');
      if (tokenBoxEl) tokenBoxEl.addEventListener('click', removeHandler);

      // generic token remove for recipients areas
      const genericRemove = (e) => {
        const pill = e.target.closest('.sm-pill'); if (!pill) return;
        const val = parseInt(pill.getAttribute('data-value'), 10) || 0;
        if (pill.classList.contains('sm-pill-user')) { selectedItems.users.delete(val); }
        else if (pill.classList.contains('sm-pill-group')) { selectedItems.groups.delete(val); }
        else if (pill.classList.contains('sm-pill-profile')) { selectedItems.profiles.delete(val); }
        buildRecipientsJSON();
      };
      const tbUsers = document.getElementById('sm-users-tokenbox'); if (tbUsers) tbUsers.addEventListener('click', genericRemove);
      const tbGroups = document.getElementById('sm-groups-tokenbox'); if (tbGroups) tbGroups.addEventListener('click', genericRemove);
      const tbProfiles = document.getElementById('sm-profiles-tokenbox'); if (tbProfiles) tbProfiles.addEventListener('click', genericRemove);

      render();
    })();

    // Modal wiring moved here
    const overlay   = $('#sm-modal');
    const openAdd   = $('#sm-open-modal-add');
    const openEdit  = $('#sm-open-modal-edit');
    const closeBtn  = $('#sm-close-modal');
    const titleEl   = $('#sm-modal-title');
    const modalEl   = overlay ? overlay.querySelector('.sm-modal') : null;
    function open(){
      if (!overlay) return;
      overlay.classList.add('show');
      if (modalEl) {
        // 初始位置置于屏幕中心
        const rect = modalEl.getBoundingClientRect();
        const vw = window.innerWidth, vh = window.innerHeight;
        const left = (vw - rect.width) / 2; const top = (vh - rect.height) / 2;
        modalEl.style.left = Math.max(10, left) + 'px';
        modalEl.style.top  = Math.max(10, top)  + 'px';
        modalEl.style.transform = 'none';
      }
    }
    function close(){ 
      if (overlay) overlay.classList.remove('show');
      // If we're in edit mode (URL contains edit_id), redirect to clean page
      if (window.location.search.includes('edit_id=')) {
        window.location.href = window.location.pathname + '#sm-target-list';
      }
    }
    function resetToAdd(){
      try {
        if (!form) return;
        const action = form.querySelector('input[name=action]');
        if (action) action.value = 'add';
        const idField = form.querySelector('input[name=id]');
        if (idField && idField.parentNode) idField.parentNode.removeChild(idField);
        
        // Clear selections
        selectedItems.users.clear();
        selectedItems.groups.clear();
        selectedItems.profiles.clear();
        selectedItems.emails.clear();
        
        // Reset visual state
        $all('.sm-option-item').forEach(item => item.classList.remove('selected'));
        
        // Reset form fields
        if (emailsInp) emailsInp.value='';
        if (onlyOnViolation) onlyOnViolation.checked = true;
        if (threshold) threshold.value = '0';
        if (merge) merge.checked = true;
        if (scope) scope.value = 'both';
        const ia = form.querySelector('input[name=is_active]'); if (ia) ia.checked = true;
        
        // Clear filters
        $all('.sm-filter').forEach(filter => filter.value = '');
        $all('.sm-option-item').forEach(item => item.style.display = 'block');
        
        buildRecipientsJSON();
        buildOptionsJSON();
      } catch(e) {}
    }
    if (openAdd)  openAdd.addEventListener('click', e => { e.preventDefault(); resetToAdd(); if (titleEl) titleEl.textContent = 'Add group mail target'; open(); });
    if (openEdit) openEdit.addEventListener('click', e => { e.preventDefault(); if (titleEl) titleEl.textContent = 'Edit group mail target'; open(); });
    if (closeBtn) closeBtn.addEventListener('click', () => close());
    if (overlay)  overlay.addEventListener('click', e => { if (e.target === overlay) close(); });

    // 拖拽移动
    (function enableDrag(){
      if (!overlay || !modalEl) return;
      const header = modalEl.querySelector('.sm-modal-header');
      if (!header) return;
      let isDown = false; let startX=0, startY=0, startLeft=0, startTop=0;
      const onMouseDown = (e) => {
        isDown = true; document.body.style.userSelect = 'none';
        const rect = modalEl.getBoundingClientRect();
        startLeft = rect.left; startTop = rect.top; startX = e.clientX; startY = e.clientY;
      };
      const onMouseMove = (e) => {
        if (!isDown) return;
        const dx = e.clientX - startX; const dy = e.clientY - startY;
        let newLeft = startLeft + dx; let newTop = startTop + dy;
        // 边界限制
        const vw = window.innerWidth, vh = window.innerHeight;
        const rect = modalEl.getBoundingClientRect();
        newLeft = Math.min(Math.max(0, newLeft), vw - rect.width);
        newTop  = Math.min(Math.max(0, newTop ), vh - rect.height);
        modalEl.style.left = newLeft + 'px'; modalEl.style.top = newTop + 'px'; modalEl.style.transform = 'none';
      };
      const onMouseUp = () => { isDown = false; document.body.style.userSelect = ''; };
      header.addEventListener('mousedown', onMouseDown);
      window.addEventListener('mousemove', onMouseMove);
      window.addEventListener('mouseup', onMouseUp);
    })();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

// List filtering and search functionality
(function() {
  function initListControls() {
    const searchInput = document.getElementById('sm-search');
    const entityFilter = document.getElementById('sm-filter-entity');
    const statusFilter = document.getElementById('sm-filter-status');
    const visibleCount = document.getElementById('sm-visible-count');
    const totalCount = document.getElementById('sm-total-count');
    const rows = document.querySelectorAll('.sm-list-row');

    if (!searchInput || !entityFilter || !statusFilter || !visibleCount || !totalCount) {
      return;
    }

    // Update counts
    function updateCounts() {
      const visible = document.querySelectorAll('.sm-list-row:not([style*="display: none"])').length;
      const total = rows.length;
      visibleCount.textContent = visible;
      totalCount.textContent = total;
    }

    // Filter function
    function filterRows() {
      const searchTerm = searchInput.value.toLowerCase();
      const entityId = entityFilter.value;
      const status = statusFilter.value;

      rows.forEach(row => {
        let show = true;

        // Search filter
        if (searchTerm) {
          const searchData = row.dataset.search || '';
          if (!searchData.includes(searchTerm)) {
            show = false;
          }
        }

        // Entity filter
        if (entityId && row.dataset.entityId !== entityId) {
          show = false;
        }

        // Status filter
        if (status !== '' && row.dataset.status !== status) {
          show = false;
        }

        row.style.display = show ? '' : 'none';
      });

      updateCounts();
    }

    // Event listeners
    searchInput.addEventListener('input', filterRows);
    entityFilter.addEventListener('change', filterRows);
    statusFilter.addEventListener('change', filterRows);

    // Initial count
    updateCounts();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initListControls);
  } else {
    initListControls();
  }
})();


