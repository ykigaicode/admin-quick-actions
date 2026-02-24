/* Admin Quick Actions by YkigaiCode */
(function () {
  "use strict";

  const RECENTS_KEY = "ykigaicode_aqa_recent_v1";
  const RECENTS_MAX = 8;

  const state = {
    isOpen: false,
    selectedIndex: 0,
    items: [],
    lastFetchAt: 0,
    debounceTimer: null,
    elements: {},
    dynamicMenuItems: [],
  };

  function isMac() {
    return /Mac|iPhone|iPad|iPod/i.test(navigator.platform);
  }

  function isK(e) {
    const codeIsK = (e.code === "KeyK");
    const keyIsK = (String(e.key || "").toLowerCase() === "k");
    return codeIsK || keyIsK;
  }

  function matchesHotkey(e) {
    const cmdK = isMac() && e.metaKey && isK(e);
    const ctrlShiftK = !isMac() && e.ctrlKey && e.shiftKey && isK(e);
    const altK = !isMac() && e.altKey && isK(e);
    const ctrlSpace = !isMac() && e.ctrlKey && (e.code === "Space" || e.key === " ");
    const ctrlKAttempt = !isMac() && e.ctrlKey && !e.shiftKey && isK(e); // may be reserved by browser
    return cmdK || ctrlShiftK || altK || ctrlSpace || ctrlKAttempt;
  }

  function escapeHtml(s) {
    return String(s)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function safeJSONParse(raw, fallback) {
    try { return JSON.parse(raw); } catch (e) { return fallback; }
  }

  function getRecents() {
    const raw = localStorage.getItem(RECENTS_KEY);
    const arr = safeJSONParse(raw, []);
    if (!Array.isArray(arr)) return [];
    return arr
      .filter(it => it && typeof it === "object" && typeof it.url === "string" && typeof it.title === "string")
      .slice(0, RECENTS_MAX);
  }

  function addRecent(item) {
    if (!item || !item.url) return;
    const recents = getRecents();
    const next = [item, ...recents.filter(r => r.url !== item.url)].slice(0, RECENTS_MAX);
    localStorage.setItem(RECENTS_KEY, JSON.stringify(next));
  }

  function buildAdminMenuItems() {
    const items = [];
    const menu = document.getElementById("adminmenu");
    if (!menu) return items;

    const links = menu.querySelectorAll("a[href]");
    links.forEach(a => {
      const href = a.getAttribute("href") || "";
      if (!href || href === "#" || href.startsWith("javascript:")) return;

      // Resolve relative wp-admin links robustly
      let url = href;
      if (!href.startsWith("http")) {
        if (href.startsWith("/")) {
          url = window.location.origin + href;
        } else if (href.startsWith("admin.php") || href.startsWith("edit.php") || href.startsWith("post-new.php") || href.startsWith("upload.php") || href.startsWith("themes.php") || href.startsWith("plugins.php") || href.startsWith("options-") || href.startsWith("nav-menus.php") || href.startsWith("customize.php") || href.startsWith("users.php") || href.startsWith("profile.php") || href.startsWith("update-core.php") || href.startsWith("tools.php")) {
          url = window.location.origin + "/wp-admin/" + href;
        } else {
          // fallback: treat as relative to current admin path
          const base = window.location.href.split("?")[0];
          url = base.replace(/\/[^\/]*$/, "/") + href;
        }
      }

      const text = (a.textContent || "").replace(/\s+/g, " ").trim();
      if (!text) return;

      items.push({
        type: "Admin",
        title: text,
        url,
        icon: "🧭",
        meta: "Admin Menu • by YkigaiCode",
        _keywords: (text + " admin menu").toLowerCase(),
      });
    });

    const dedup = new Map();
    items.forEach(it => { if (!dedup.has(it.url)) dedup.set(it.url, it); });
    return Array.from(dedup.values()).slice(0, 150);
  }

  function createModal() {
    const wrap = document.createElement("div");
    wrap.className = "ykigaicode-aqa";
    wrap.setAttribute("aria-hidden", "true");
    wrap.innerHTML = `
      <div class="ykigaicode-aqa__backdrop" data-aqa-close></div>
      <div class="ykigaicode-aqa__panel" role="dialog" aria-modal="true" aria-label="Admin Quick Actions by YkigaiCode">
        <div class="ykigaicode-aqa__header">
          <div class="ykigaicode-aqa__title">
            <span>${escapeHtml(YKIGAI_AQA?.strings?.title || "Admin Quick Actions")}</span>
            <small>${escapeHtml(YKIGAI_AQA?.strings?.subtitle || "by YkigaiCode")}</small>
          </div>
          <div class="ykigaicode-aqa__hint">${escapeHtml(YKIGAI_AQA?.strings?.hint || "")}</div>
        </div>

        <div class="ykigaicode-aqa__search">
          <input type="text" class="ykigaicode-aqa__input" placeholder="${escapeHtml(YKIGAI_AQA?.strings?.placeholder || "")}" autocomplete="off" spellcheck="false" />
          <button type="button" class="ykigaicode-aqa__close" aria-label="Close" data-aqa-close>Esc</button>
        </div>

        <div class="ykigaicode-aqa__results" role="listbox" aria-label="Results">
          <div class="ykigaicode-aqa__empty">${escapeHtml(YKIGAI_AQA?.strings?.empty || "No results.")}</div>
          <ul class="ykigaicode-aqa__list"></ul>
        </div>

        <div class="ykigaicode-aqa__footer">
          <span>Enter open • Cmd/Ctrl+Enter new tab • ↑↓ navigate • Esc close</span>
          <span>${escapeHtml(YKIGAI_AQA?.pluginBy || "by YkigaiCode")}</span>
        </div>
      </div>
    `;
    document.body.appendChild(wrap);

    state.elements.wrap = wrap;
    state.elements.input = wrap.querySelector(".ykigaicode-aqa__input");
    state.elements.list = wrap.querySelector(".ykigaicode-aqa__list");
    state.elements.empty = wrap.querySelector(".ykigaicode-aqa__empty");

    wrap.addEventListener("click", (e) => {
      const t = e.target;
      if (t && t.getAttribute && t.getAttribute("data-aqa-close") !== null) close();
    });

    wrap.addEventListener("keydown", (e) => {
      if (!state.isOpen) return;

      if (e.key === "Escape") { e.preventDefault(); close(); return; }
      if (e.key === "ArrowDown") { e.preventDefault(); move(1); return; }
      if (e.key === "ArrowUp") { e.preventDefault(); move(-1); return; }
      if (e.key === "Enter") {
        e.preventDefault();
        openSelected({ newTab: (e.metaKey || e.ctrlKey) });
        return;
      }
    });

    state.elements.input.addEventListener("input", () => {
      const q = state.elements.input.value || "";
      debouncedSearch(q);
    });
  }

  function open() {
    if (!state.elements.wrap) createModal();
    if (state.isOpen) return;

    state.dynamicMenuItems = buildAdminMenuItems();

    state.isOpen = true;
    state.selectedIndex = 0;
    state.elements.wrap.classList.add("is-open");
    state.elements.wrap.setAttribute("aria-hidden", "false");

    renderItems(composeInitialItems());

    setTimeout(() => {
      state.elements.input.focus();
      state.elements.input.select();
    }, 0);
  }

  function close() {
    if (!state.elements.wrap) return;
    state.isOpen = false;
    state.elements.wrap.classList.remove("is-open");
    state.elements.wrap.setAttribute("aria-hidden", "true");
  }

  function move(delta) {
    if (!state.items.length) return;
    state.selectedIndex = Math.max(0, Math.min(state.items.length - 1, state.selectedIndex + delta));
    updateSelection();
    scrollSelectedIntoView();
  }

  function scrollSelectedIntoView() {
    const el = state.elements.list.querySelector(`[data-index="${state.selectedIndex}"]`);
    if (el) el.scrollIntoView({ block: "nearest" });
  }

  function updateSelection() {
    const nodes = state.elements.list.querySelectorAll("li");
    nodes.forEach((n) => n.classList.remove("is-selected"));
    const selected = state.elements.list.querySelector(`[data-index="${state.selectedIndex}"]`);
    if (selected) selected.classList.add("is-selected");
  }

  function openSelected({ newTab } = { newTab: false }) {
    const item = state.items[state.selectedIndex];
    if (!item || !item.url) return;

    addRecent({ title: item.title, url: item.url, icon: item.icon || "🕘" });

    if (newTab) window.open(item.url, "_blank", "noopener,noreferrer");
    else window.location.href = item.url;
  }

  function renderItems(items) {
    state.items = Array.isArray(items) ? items : [];
    state.selectedIndex = 0;

    state.elements.list.innerHTML = "";
    if (!state.items.length) { state.elements.empty.style.display = "block"; return; }
    state.elements.empty.style.display = "none";

    const frag = document.createDocumentFragment();
    state.items.forEach((item, idx) => {
      const li = document.createElement("li");
      li.className = "ykigaicode-aqa__item";
      li.setAttribute("role", "option");
      li.setAttribute("data-index", String(idx));
      li.innerHTML = `
        <span class="ykigaicode-aqa__icon" aria-hidden="true">${escapeHtml(item.icon || "⚡")}</span>
        <span class="ykigaicode-aqa__main">
          <span class="ykigaicode-aqa__item-title">${escapeHtml(item.title || "")}</span>
          <span class="ykigaicode-aqa__item-meta">${escapeHtml(item.meta || item.type || "")}</span>
        </span>
        <span class="ykigaicode-aqa__type">${escapeHtml(item.type || "")}</span>
      `;

      li.addEventListener("mouseenter", () => { state.selectedIndex = idx; updateSelection(); });
      li.addEventListener("click", () => { state.selectedIndex = idx; openSelected({ newTab: false }); });

      frag.appendChild(li);
    });

    state.elements.list.appendChild(frag);
    updateSelection();
  }

  function debouncedSearch(q) {
    clearTimeout(state.debounceTimer);
    state.debounceTimer = setTimeout(() => search(q), 180);
  }

  function sectionHeader(title) {
    return { type: "—", title: title, url: "", icon: "•", meta: "by YkigaiCode", _isHeader: true };
  }

  function actionItemsFromConfig(q) {
    const needle = (q || "").trim().toLowerCase();
    const actions = YKIGAI_AQA?.actions || [];
    const mapped = actions.map(a => ({
      type: "Action",
      title: a.label,
      url: a.url,
      icon: a.icon || "⚡",
      meta: "by YkigaiCode",
      _keywords: (a.label + " " + (a.keywords || []).join(" ")).toLowerCase(),
    }));

    if (!needle) return mapped;
    return mapped.filter(a => a._keywords.includes(needle));
  }

  function adminMenuMatches(q) {
    const needle = (q || "").trim().toLowerCase();
    if (!needle) return state.dynamicMenuItems.slice(0, 25);
    return state.dynamicMenuItems.filter(it => (it._keywords || "").includes(needle)).slice(0, 25);
  }

  function recentMatches(q) {
    const needle = (q || "").trim().toLowerCase();
    const recents = getRecents().map(r => ({
      type: "Recent",
      title: r.title,
      url: r.url,
      icon: r.icon || "🕘",
      meta: "Recent • by YkigaiCode",
      _keywords: (r.title + " recent").toLowerCase(),
    }));
    if (!needle) return recents;
    return recents.filter(r => r._keywords.includes(needle));
  }

  function composeInitialItems() {
    const recents = recentMatches("");
    const actions = actionItemsFromConfig("");
    const admin = adminMenuMatches("");

    // Add simple grouping headers if there are recents
    const out = [];
    if (recents.length) {
      out.push({ type: "Recent", title: YKIGAI_AQA?.strings?.recents || "Recent items", url: "", icon: "🕘", meta: "by YkigaiCode", _isHeader: true });
      out.push(...recents);
    }
    out.push({ type: "Action", title: YKIGAI_AQA?.strings?.actions || "Quick actions", url: "", icon: "⚡", meta: "by YkigaiCode", _isHeader: true });
    out.push(...actions.slice(0, 20));

    out.push({ type: "Admin", title: YKIGAI_AQA?.strings?.admin || "Admin menu", url: "", icon: "🧭", meta: "by YkigaiCode", _isHeader: true });
    out.push(...admin.slice(0, 20));

    return out.slice(0, 60);
  }

  async function search(q) {
    const query = (q || "").trim();

    // local matches immediately
    const recents = recentMatches(query);
    const actions = actionItemsFromConfig(query);
    const admin = adminMenuMatches(query);

    const local = [];
    if (recents.length) {
      local.push({ type: "Recent", title: YKIGAI_AQA?.strings?.recents || "Recent items", url: "", icon: "🕘", meta: "by YkigaiCode", _isHeader: true });
      local.push(...recents);
    }
    if (actions.length) {
      local.push({ type: "Action", title: YKIGAI_AQA?.strings?.actions || "Quick actions", url: "", icon: "⚡", meta: "by YkigaiCode", _isHeader: true });
      local.push(...actions);
    }
    if (admin.length) {
      local.push({ type: "Admin", title: YKIGAI_AQA?.strings?.admin || "Admin menu", url: "", icon: "🧭", meta: "by YkigaiCode", _isHeader: true });
      local.push(...admin);
    }

    // Render local first (without headers clicking)
    renderItems(local.filter(it => !it._isHeader).slice(0, 50));

    if (!query) return;

    const now = Date.now();
    if (now - state.lastFetchAt < 200) return;
    state.lastFetchAt = now;

    const form = new FormData();
    form.append("action", "ykigaicode_aqa_search");
    form.append("nonce", YKIGAI_AQA?.nonce || "");
    form.append("q", query);

    try {
      const res = await fetch(YKIGAI_AQA?.ajaxUrl || "", { method: "POST", credentials: "same-origin", body: form });
      const data = await res.json();

      if ((state.elements.input.value || "").trim() !== query) return;
      if (!data || !data.success || !data.data) return;

      const serverItems = (data.data.results || []).map(r => ({
        type: r.type || "Result",
        title: r.title || "",
        url: r.url || "",
        icon: r.icon || "⚡",
        meta: r.meta || "by YkigaiCode",
      }));

      const merged = [...recents, ...admin, ...serverItems];

      const seen = new Set();
      const final = [];
      for (const it of merged) {
        if (!it.url || seen.has(it.url)) continue;
        seen.add(it.url);
        final.push(it);
      }

      renderItems(final.slice(0, 50));
    } catch (e) {
      // silent
    }
  }

  // Global hotkey (capture to beat browser handlers when possible)
  document.addEventListener("keydown", (e) => {
    const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : "";
    const isTypingField = tag === "input" || tag === "textarea" || (e.target && e.target.isContentEditable);

    if (!state.isOpen && isTypingField) {
      if (!matchesHotkey(e)) return;
    }

    if (matchesHotkey(e)) {
      e.preventDefault();
      e.stopPropagation();
      open();
    } else if (state.isOpen && e.key === "Escape") {
      e.preventDefault();
      close();
    }
  }, true);

  // Buttons with id on dashboard page & widget
  document.addEventListener("click", (e) => {
    const t = e.target;
    if (t && t.id === "ykigaicode-aqa-open") {
      e.preventDefault();
      open();
    }
  });
})();
