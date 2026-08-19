'use strict';

/* ==========================================================================
   THEME MANAGEMENT & CUSTOM USER THEMES
   ========================================================================== */

function getCustomThemes() {
    try {
        const json = localStorage.getItem(STORAGE_CUSTOM_THEMES);
        return json ? JSON.parse(json) : {};
    } catch (err) {
        console.error('Error reading custom themes from storage:', err);
        return {};
    }
}

function saveCustomThemes(themesObj) {
    try {
        localStorage.setItem(STORAGE_CUSTOM_THEMES, JSON.stringify(themesObj));
    } catch (err) {
        console.error('Error saving custom themes to storage:', err);
    }
}

function populateThemeDropdown() {
    // Retain built-in options
    themeSelect.innerHTML = `
        <option value="dark">🌙 Dark (Default)</option>
        <option value="light">☀️ Light</option>
        <option value="halloween">🎃 Halloween</option>
        <option value="console">📟 Console</option>
        <option value="christmas">🎄 Christmas</option>
    `;

    const customThemes = getCustomThemes();
    const customIds = Object.keys(customThemes);

    if (customIds.length > 0) {
        const optGroup = document.createElement('optgroup');
        optGroup.label = '--- Custom Themes ---';
        customIds.forEach(id => {
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = `✨ ${customThemes[id].name}`;
            optGroup.appendChild(opt);
        });
        themeSelect.appendChild(optGroup);
    }

    const manageOpt = document.createElement('option');
    manageOpt.value = 'manage-custom';
    manageOpt.style.fontWeight = 'bold';
    manageOpt.textContent = '➕ Custom Themes...';
    themeSelect.appendChild(manageOpt);
}

function applyTheme(themeId, customThemeData = null) {
    const root = document.documentElement;

    // Reset dynamic inline CSS variable overrides
    root.style.removeProperty('--bg-dark');
    root.style.removeProperty('--bg-gradient');
    root.style.removeProperty('--card-bg');
    root.style.removeProperty('--card-border');
    root.style.removeProperty('--input-bg');
    root.style.removeProperty('--primary-color');
    root.style.removeProperty('--primary-hover');
    root.style.removeProperty('--secondary-color');
    root.style.removeProperty('--secondary-hover');
    root.style.removeProperty('--text-bright');
    root.style.removeProperty('--text-muted');
    root.style.removeProperty('--font-family');
    root.style.removeProperty('--box-shadow');

    if (BUILTIN_THEMES.includes(themeId)) {
        root.setAttribute('data-theme', themeId);
        localStorage.setItem(STORAGE_ACTIVE_THEME, themeId);
        themeSelect.value = themeId;
        return;
    }

    // Custom Theme
    const customThemes = getCustomThemes();
    const data = customThemeData || customThemes[themeId];

    if (data) {
        root.setAttribute('data-theme', 'custom');

        root.style.setProperty('--bg-dark', data.bg || '#0f172a');
        root.style.setProperty('--bg-gradient', data.bg.includes('gradient') ? data.bg : `radial-gradient(circle at top right, ${data.bg}, #000000 80%)`);
        root.style.setProperty('--card-bg', data.cardBg || 'rgba(30, 41, 59, 0.75)');
        root.style.setProperty('--card-border', data.cardBorder || 'rgba(255, 255, 255, 0.1)');
        root.style.setProperty('--input-bg', data.cardBg ? data.cardBg : 'rgba(15, 23, 42, 0.6)');
        root.style.setProperty('--primary-color', data.primary || '#3b82f6');
        root.style.setProperty('--primary-hover', data.primary || '#2563eb');
        root.style.setProperty('--text-bright', data.textBright || '#f8fafc');
        root.style.setProperty('--text-muted', data.textMuted || '#94a3b8');
        root.style.setProperty('--font-family', data.fontFamily || 'system-ui, -apple-system, sans-serif');

        if (!customThemeData) {
            localStorage.setItem(STORAGE_ACTIVE_THEME, themeId);
            themeSelect.value = themeId;
        }
    } else {
        // Fallback to dark theme if requested theme ID does not exist
        root.setAttribute('data-theme', 'dark');
        localStorage.setItem(STORAGE_ACTIVE_THEME, 'dark');
        themeSelect.value = 'dark';
    }
}

function initThemeSystem() {
    populateThemeDropdown();
    const activeTheme = localStorage.getItem(STORAGE_ACTIVE_THEME) || 'dark';
    applyTheme(activeTheme);

    // Bind Theme Selector Dropdown
    themeSelect.addEventListener('change', (e) => {
        const val = e.target.value;
        if (val === 'manage-custom') {
            openThemeModal();
            // Restore selector to current active theme
            themeSelect.value = localStorage.getItem(STORAGE_ACTIVE_THEME) || 'dark';
        } else {
            applyTheme(val);
        }
    });

    // Sync Color Picker with Text Inputs
    syncColorAndText(themeBgColor, themeBgText);
    syncColorAndText(themeCardBgColor, themeCardBgText);
    syncColorAndText(themeCardBorderColor, themeCardBorderText);
    syncColorAndText(themePrimaryColor, themePrimaryText);
    syncColorAndText(themeTextBrightColor, themeTextBrightText);
    syncColorAndText(themeTextMutedColor, themeTextMutedText);

    btnThemeModal.addEventListener('click', openThemeModal);
    btnCloseThemeModal.addEventListener('click', closeThemeModal);

    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !themeModal.classList.contains('hidden')) {
            closeThemeModal();
        }
    });

    btnPreviewTheme.addEventListener('click', () => {
        const previewData = getFormDataAsThemeData();
        applyTheme('custom', previewData);
    });

    customThemeForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const themeData = getFormDataAsThemeData();

        if (!themeData.name) {
            alert('Please provide a theme name.');
            return;
        }

        const customThemes = getCustomThemes();
        const themeId = editingCustomThemeId || ('custom-' + Date.now());

        customThemes[themeId] = themeData;
        saveCustomThemes(customThemes);

        populateThemeDropdown();
        applyTheme(themeId);
        renderSavedThemesList();

        editingCustomThemeId = null;
        customThemeForm.reset();
        alert(`Theme "${themeData.name}" saved successfully!`);
    });

    btnExportThemes.addEventListener('click', exportCustomThemes);
    btnImportThemes.addEventListener('click', () => importThemeFile.click());
    importThemeFile.addEventListener('change', handleImportThemesFile);
}

function syncColorAndText(colorEl, textEl) {
    colorEl.addEventListener('input', () => { textEl.value = colorEl.value; });
    textEl.addEventListener('change', () => {
        if (/^#[0-9A-F]{6}$/i.test(textEl.value.trim())) {
            colorEl.value = textEl.value.trim();
        }
    });
}

function getFormDataAsThemeData() {
    return {
        name: themeNameInput.value.trim(),
        bg: themeBgText.value.trim() || themeBgColor.value,
        cardBg: themeCardBgText.value.trim() || themeCardBgColor.value,
        cardBorder: themeCardBorderText.value.trim() || themeCardBorderColor.value,
        primary: themePrimaryText.value.trim() || themePrimaryColor.value,
        textBright: themeTextBrightText.value.trim() || themeTextBrightColor.value,
        textMuted: themeTextMutedText.value.trim() || themeTextMutedColor.value,
        fontFamily: themeFontFamily.value
    };
}

function openThemeModal() {
    renderSavedThemesList();
    themeModal.classList.remove('hidden');
    if (themeNameInput) {
        themeNameInput.focus();
    }
}

function closeThemeModal() {
    themeModal.classList.add('hidden');
    editingCustomThemeId = null;
    // Re-apply saved active theme if user was previewing
    const activeTheme = localStorage.getItem(STORAGE_ACTIVE_THEME) || 'dark';
    applyTheme(activeTheme);
}

function renderSavedThemesList() {
    const customThemes = getCustomThemes();
    const ids = Object.keys(customThemes);

    if (ids.length === 0) {
        savedThemesContainer.innerHTML = '<p class="subtitle" style="font-size: 0.85rem;">No custom themes saved yet. Create one above!</p>';
        return;
    }

    savedThemesContainer.innerHTML = '';
    ids.forEach(id => {
        const theme = customThemes[id];
        const item = document.createElement('div');
        item.className = 'custom-theme-item';

        item.innerHTML = `
            <div>
                <strong>✨ ${theme.name}</strong>
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                    Primary: <span style="color: ${theme.primary}; font-weight: bold;">■</span> |
                    Bg: <span style="color: ${theme.textBright}; font-weight: bold;">■</span>
                </div>
            </div>
            <div class="theme-actions">
                <button class="btn btn-primary btn-sm btn-apply-theme" type="button">Apply</button>
                <button class="btn btn-secondary btn-sm btn-edit-theme" type="button">Edit</button>
                <button class="btn btn-danger btn-sm btn-delete-theme" type="button">Delete</button>
            </div>
        `;

        item.querySelector('.btn-apply-theme').addEventListener('click', () => {
            applyTheme(id);
            closeThemeModal();
        });

        item.querySelector('.btn-edit-theme').addEventListener('click', () => {
            editingCustomThemeId = id;
            themeNameInput.value = theme.name;

            themeBgText.value = theme.bg;
            if (/^#[0-9A-F]{6}$/i.test(theme.bg)) themeBgColor.value = theme.bg;

            themeCardBgText.value = theme.cardBg;
            if (/^#[0-9A-F]{6}$/i.test(theme.cardBg)) themeCardBgColor.value = theme.cardBg;

            themeCardBorderText.value = theme.cardBorder;
            if (/^#[0-9A-F]{6}$/i.test(theme.cardBorder)) themeCardBorderColor.value = theme.cardBorder;

            themePrimaryText.value = theme.primary;
            if (/^#[0-9A-F]{6}$/i.test(theme.primary)) themePrimaryColor.value = theme.primary;

            themeTextBrightText.value = theme.textBright;
            if (/^#[0-9A-F]{6}$/i.test(theme.textBright)) themeTextBrightColor.value = theme.textBright;

            themeTextMutedText.value = theme.textMuted;
            if (/^#[0-9A-F]{6}$/i.test(theme.textMuted)) themeTextMutedColor.value = theme.textMuted;

            if (theme.fontFamily) themeFontFamily.value = theme.fontFamily;
        });

        item.querySelector('.btn-delete-theme').addEventListener('click', () => {
            if (confirm(`Delete custom theme "${theme.name}"?`)) {
                delete customThemes[id];
                saveCustomThemes(customThemes);
                populateThemeDropdown();
                renderSavedThemesList();

                if (localStorage.getItem(STORAGE_ACTIVE_THEME) === id) {
                    applyTheme('dark');
                }
            }
        });

        savedThemesContainer.appendChild(item);
    });
}

function exportCustomThemes() {
    const customThemes = getCustomThemes();
    const blob = new Blob([JSON.stringify(customThemes, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `ivc-custom-themes-${Date.now()}.json`;
    a.click();
    URL.revokeObjectURL(url);
}

function handleImportThemesFile(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (event) => {
        try {
            const importedObj = JSON.parse(event.target.result);
            if (typeof importedObj !== 'object' || importedObj === null) {
                throw new Error('Invalid JSON structure');
            }

            const existingThemes = getCustomThemes();
            let importedCount = 0;

            Object.keys(importedObj).forEach(key => {
                const theme = importedObj[key];
                if (theme && theme.name) {
                    const newKey = key.startsWith('custom-') ? key : ('custom-' + Math.random().toString(36).substring(2, 9));
                    existingThemes[newKey] = theme;
                    importedCount++;
                }
            });

            saveCustomThemes(existingThemes);
            populateThemeDropdown();
            renderSavedThemesList();
            alert(`Successfully imported ${importedCount} custom theme(s)!`);
        } catch (err) {
            alert('Failed to import themes JSON: ' + err.message);
        }
    };
    reader.readAsText(file);
}
