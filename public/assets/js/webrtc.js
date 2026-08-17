/**
 * Fortress / IVC WebRTC Client & Multi-Tab IRC Infrastructure
 * Supports #channel hash navigation, multi-tab room sessions, #stats connection stats, NAMESERV/CHANSERV integration,
 * and comprehensive theme support (light, dark, halloween, console, christmas, + user-defined custom themes).
 */

(() => {
    'use strict';

    // Theme Management Constants
    const STORAGE_ACTIVE_THEME = 'ivc_theme_active';
    const STORAGE_CUSTOM_THEMES = 'ivc_custom_themes';
    const BUILTIN_THEMES = ['dark', 'light', 'halloween', 'console', 'christmas'];

    // Anonymous Name Generator
    const ADJECTIVES = ['Crypto', 'Cyber', 'Silent', 'Shadow', 'Fortress', 'Quantum', 'Stealth', 'Hyper', 'Neon', 'Matrix', 'Ghost', 'Vector'];
    const ANIMALS = ['Fox', 'Owl', 'Wolf', 'Falcon', 'Panther', 'Eagle', 'Hawk', 'Raven', 'Panda', 'Tiger', 'Viper', 'Lynx'];

    function generateAnonymousName() {
        const adj = ADJECTIVES[Math.floor(Math.random() * ADJECTIVES.length)];
        const anim = ANIMALS[Math.floor(Math.random() * ANIMALS.length)];
        const num = Math.floor(100 + Math.random() * 900);
        return `${adj}${anim}#${num}`;
    }

    // Helper: Normalize channel name to start with # and support grouped subchats (#room/sub-room)
    function normalizeChannel(name) {
        if (!name) return '#lobby';
        name = name.trim();
        if (name === 'stats' || name === '#stats') return '#stats';
        if (!name.startsWith('#') && !name.startsWith('&')) {
            name = '#' + name;
        }
        return name.replace(/[^a-zA-Z0-9\-_#&\/]/g, '').replace(/\/+/g, '/').replace(/\/$/, '');
    }

    // Global Client ID
    const myClientId = 'peer-' + Math.random().toString(36).substring(2, 11);
    let myNickname = generateAnonymousName();

    // Multi-Tab State
    const openTabs = {};
    let activeTabId = null;
    let statsInterval = null;

    // WebRTC STUN Server configuration
    const rtcConfig = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' }
        ]
    };

    // DOM Elements - App Components
    const tabsBar = document.getElementById('tabs-bar');
    const btnOpenNewTab = document.getElementById('btn-open-new-tab');

    const roomLobby = document.getElementById('room-lobby');
    const roomInput = document.getElementById('room-input');
    const keyInput = document.getElementById('key-input');
    const nicknameInput = document.getElementById('nickname-input');
    const btnRandomName = document.getElementById('btn-random-name');
    const btnCreateRoom = document.getElementById('btn-create-room');
    const btnJoinRoom = document.getElementById('btn-join-room');

    const roomShareSection = document.getElementById('room-share-section');
    const shareUrlInput = document.getElementById('share-url');
    const btnCopyLink = document.getElementById('btn-copy-link');

    const videoStage = document.getElementById('video-stage');
    const localVideo = document.getElementById('local-video');
    const remoteVideo = document.getElementById('remote-video');
    const localNameLabel = document.getElementById('local-name-label');
    const remoteNameLabel = document.getElementById('remote-name-label');
    const remotePlaceholder = document.getElementById('remote-placeholder');
    const remoteStatusText = document.getElementById('remote-status-text');

    const btnToggleMic = document.getElementById('btn-toggle-mic');
    const btnToggleCam = document.getElementById('btn-toggle-cam');
    const btnShareScreen = document.getElementById('btn-share-screen');
    const btnLeaveCall = document.getElementById('btn-leave-call');

    const chatPanel = document.getElementById('chat-panel');
    const chatChannelTitle = document.getElementById('chat-channel-title');
    const channelTopicBar = document.getElementById('channel-topic-bar');
    const chatMessages = document.getElementById('chat-messages');
    const userList = document.getElementById('user-list');
    const chatInput = document.getElementById('chat-input');
    const btnSendChat = document.getElementById('btn-send-chat');

    const statsStage = document.getElementById('stats-stage');
    const btnRefreshStats = document.getElementById('btn-refresh-stats');
    const serverStatsContent = document.getElementById('server-stats-content');
    const clientStatsContent = document.getElementById('client-stats-content');

    // DOM Elements - Theme Controls
    const themeSelect = document.getElementById('theme-select');
    const btnThemeModal = document.getElementById('btn-theme-modal');
    const themeModal = document.getElementById('theme-modal');
    const btnCloseThemeModal = document.getElementById('btn-close-theme-modal');
    const customThemeForm = document.getElementById('custom-theme-form');

    const themeNameInput = document.getElementById('theme-name-input');
    const themeBgColor = document.getElementById('theme-bg-color');
    const themeBgText = document.getElementById('theme-bg-text');
    const themeCardBgColor = document.getElementById('theme-card-bg-color');
    const themeCardBgText = document.getElementById('theme-card-bg-text');
    const themeCardBorderColor = document.getElementById('theme-card-border-color');
    const themeCardBorderText = document.getElementById('theme-card-border-text');
    const themePrimaryColor = document.getElementById('theme-primary-color');
    const themePrimaryText = document.getElementById('theme-primary-text');
    const themeTextBrightColor = document.getElementById('theme-text-bright-color');
    const themeTextBrightText = document.getElementById('theme-text-bright-text');
    const themeTextMutedColor = document.getElementById('theme-text-muted-color');
    const themeTextMutedText = document.getElementById('theme-text-muted-text');
    const themeFontFamily = document.getElementById('theme-font-family');

    const btnPreviewTheme = document.getElementById('btn-preview-theme');
    const savedThemesContainer = document.getElementById('saved-themes-container');
    const btnExportThemes = document.getElementById('btn-export-themes');
    const btnImportThemes = document.getElementById('btn-import-themes');
    const importThemeFile = document.getElementById('import-theme-file');

    let editingCustomThemeId = null;

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

    /* ==========================================================================
       INITIALIZATION & MULTI-TAB SESSION LOGIC
       ========================================================================== */

    // Initialize Nickname input
    nicknameInput.value = myNickname;

    btnRandomName.addEventListener('click', () => {
        myNickname = generateAnonymousName();
        nicknameInput.value = myNickname;
    });

    // Parse initial channel from location hash or preload
    function parseChannelFromUrl() {
        let hash = window.location.hash.trim();
        if (hash.startsWith('#')) {
            return normalizeChannel(hash);
        }
        if (window.FORTRESS_PRELOAD_ROOM) {
            return normalizeChannel(window.FORTRESS_PRELOAD_ROOM);
        }
        return '#lobby';
    }

    // Initialize Theme System & Tabs on startup
    initThemeSystem();

    const initialChan = parseChannelFromUrl();
    openTab(initialChan, false);
    openTab('#stats', false); // Always include #stats room tab
    switchToTab(initialChan);

    // Window Hashchange Listener for IRC #room hash navigation
    window.addEventListener('hashchange', () => {
        const chan = normalizeChannel(window.location.hash);
        if (chan && chan !== activeTabId) {
            if (!openTabs[chan]) {
                openTab(chan, true);
            } else {
                switchToTab(chan);
            }
        }
    });

    // UI Event Listeners
    btnOpenNewTab.addEventListener('click', () => {
        roomLobby.classList.remove('hidden');
        window.scrollTo({ top: roomLobby.offsetTop, behavior: 'smooth' });
    });

    btnCreateRoom.addEventListener('click', () => {
        const randChan = '#room-' + Math.random().toString(36).substring(2, 8);
        roomInput.value = randChan;
        openTab(randChan, true, keyInput.value.trim());
    });

    btnJoinRoom.addEventListener('click', () => {
        const chan = normalizeChannel(roomInput.value.trim());
        if (!chan) {
            alert('Please enter a channel name (e.g. #general).');
            return;
        }
        openTab(chan, true, keyInput.value.trim());
    });

    btnCopyLink.addEventListener('click', () => {
        shareUrlInput.select();
        navigator.clipboard.writeText(shareUrlInput.value);
        btnCopyLink.textContent = '✅ Copied!';
        setTimeout(() => { btnCopyLink.textContent = '📋 Copy Channel Link'; }, 2000);
    });

    btnToggleMic.addEventListener('click', toggleMicrophone);
    btnToggleCam.addEventListener('click', toggleCamera);
    btnShareScreen.addEventListener('click', toggleScreenShare);
    btnLeaveCall.addEventListener('click', () => {
        if (activeTabId && activeTabId !== '#stats') {
            closeTab(activeTabId);
        }
    });

    btnSendChat.addEventListener('click', handleChatSubmit);
    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') handleChatSubmit();
    });

    btnRefreshStats.addEventListener('click', loadConnectionStats);

    /**
     * Open or Join a Channel Tab
     */
    async function openTab(channelId, switchImmediately = true, key = '') {
        channelId = normalizeChannel(channelId);

        if (openTabs[channelId]) {
            if (switchImmediately) switchToTab(channelId);
            return;
        }

        const isStats = (channelId === '#stats');

        openTabs[channelId] = {
            id: channelId,
            key: key,
            isStats: isStats,
            nick: myNickname,
            peerConnection: null,
            localStream: null,
            dataChannel: null,
            sseSource: null,
            messages: [],
            peers: [],
            unreadCount: 0,
            topic: isStats ? 'IRC Connection Stats & WebRTC Telemetry' : `Welcome to ${channelId}`,
            isAudioMuted: false,
            isVideoMuted: false,
            isScreenSharing: false,
            remoteNick: 'Remote Peer'
        };

        if (!isStats) {
            // Add initial system welcome message
            openTabs[channelId].messages.push({
                sender: 'SYSTEM',
                text: `Joined channel ${channelId}. Direct P2P encrypted chat active. Type /help for IRC service commands or /theme <name> to change themes.`,
                type: 'system'
            });

            // Start media stream & WebRTC signaling for room
            initRoomSession(channelId, key);
        }

        renderTabsNav();

        if (switchImmediately) {
            switchToTab(channelId);
        }
    }

    /**
     * Switch Active Tab
     */
    function switchToTab(channelId) {
        if (!openTabs[channelId]) return;

        activeTabId = channelId;
        openTabs[channelId].unreadCount = 0;

        // Update URL hash
        if (window.location.hash !== channelId) {
            window.location.hash = channelId;
        }

        renderTabsNav();

        if (channelId === '#stats') {
            videoStage.classList.add('hidden');
            chatPanel.classList.add('hidden');
            roomLobby.classList.add('hidden');
            statsStage.classList.remove('hidden');

            loadConnectionStats();
            if (!statsInterval) {
                statsInterval = setInterval(loadConnectionStats, 2000);
            }
        } else {
            if (statsInterval) {
                clearInterval(statsInterval);
                statsInterval = null;
            }
            statsStage.classList.add('hidden');

            videoStage.classList.remove('hidden');
            chatPanel.classList.remove('hidden');
            roomLobby.classList.add('hidden');

            updateTabUI(channelId);
        }
    }

    /**
     * Close a Tab
     */
    function closeTab(channelId) {
        if (!openTabs[channelId]) return;

        const tabData = openTabs[channelId];

        if (!tabData.isStats) {
            // Send leave signal
            sendSignal(channelId, { type: 'leave', room: channelId, client: myClientId, key: tabData.key });

            if (tabData.sseSource) tabData.sseSource.close();
            if (tabData.peerConnection) tabData.peerConnection.close();
            if (tabData.localStream) {
                tabData.localStream.getTracks().forEach(track => track.stop());
            }
        }

        delete openTabs[channelId];

        const remainingTabIds = Object.keys(openTabs);
        if (remainingTabIds.length === 0) {
            openTab('#lobby', true);
        } else {
            const nextTab = remainingTabIds.includes(activeTabId) ? activeTabId : remainingTabIds[0];
            switchToTab(nextTab);
        }
    }

    /**
     * Render Top Tab Bar
     */
    function renderTabsNav() {
        tabsBar.innerHTML = '';

        for (const [chanId, tab] of Object.entries(openTabs)) {
            const tabEl = document.createElement('div');
            tabEl.className = `room-tab ${chanId === activeTabId ? 'active' : ''}`;

            const icon = tab.isStats ? '📊 ' : '#';
            const cleanTitle = tab.isStats ? 'Connection Stats' : chanId.replace(/^#/, '');

            tabEl.innerHTML = `<span>${icon}${cleanTitle}</span>`;

            if (tab.unreadCount > 0) {
                const badge = document.createElement('span');
                badge.className = 'unread-badge';
                badge.textContent = tab.unreadCount;
                tabEl.appendChild(badge);
            }

            if (!tab.isStats && Object.keys(openTabs).length > 1) {
                const closeBtn = document.createElement('span');
                closeBtn.className = 'close-tab';
                closeBtn.textContent = '×';
                closeBtn.title = 'Close Channel';
                closeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    closeTab(chanId);
                });
                tabEl.appendChild(closeBtn);
            }

            tabEl.addEventListener('click', () => switchToTab(chanId));
            tabsBar.appendChild(tabEl);
        }
    }

    /**
     * Update UI for active non-stats channel
     */
    function updateTabUI(channelId) {
        const tab = openTabs[channelId];
        if (!tab) return;

        chatChannelTitle.textContent = `💬 ${channelId} (P2P DataChannel Chat)`;
        channelTopicBar.textContent = `Topic: ${tab.topic || 'Welcome to IVC IRC WebRTC!'}`;
        localNameLabel.textContent = `${tab.nick || myNickname} (You)`;
        remoteNameLabel.textContent = tab.remoteNick || 'Remote Peer';

        // Update Share link
        let shareUrl = `${window.location.origin}/#${encodeURIComponent(channelId.replace(/^#/, ''))}`;
        if (tab.key) {
            shareUrl += `?key=${encodeURIComponent(tab.key)}`;
        }
        shareUrlInput.value = shareUrl;
        roomShareSection.classList.remove('hidden');

        // Render Chat Messages
        renderChatMessages(tab);

        // Render User List
        renderUserList(tab);
    }

    function renderChatMessages(tab) {
        chatMessages.innerHTML = '';
        tab.messages.forEach(msg => {
            if (msg.type === 'system') {
                const div = document.createElement('div');
                div.className = 'system-message';
                div.textContent = msg.text;
                chatMessages.appendChild(div);
            } else {
                const msgDiv = document.createElement('div');
                msgDiv.className = `chat-msg ${msg.type}`;

                const senderTag = document.createElement('div');
                senderTag.className = 'sender-tag';
                senderTag.textContent = msg.sender;

                const content = document.createElement('div');
                content.textContent = msg.text;

                msgDiv.appendChild(senderTag);
                msgDiv.appendChild(content);
                chatMessages.appendChild(msgDiv);
            }
        });
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function renderUserList(tab) {
        userList.innerHTML = '';

        // Add local user
        const selfLi = document.createElement('li');
        selfLi.className = 'user-item';
        selfLi.innerHTML = `<span class="op-tag">@</span> ${myNickname} (You)`;
        userList.appendChild(selfLi);

        // Add remote peers
        tab.peers.forEach(peerId => {
            const li = document.createElement('li');
            li.className = 'user-item';
            const name = (peerId === tab.remoteNick || peerId === tab.remoteClientId) ? tab.remoteNick : peerId;
            li.innerHTML = `<span>👤</span> ${name}`;
            userList.appendChild(li);
        });
    }

    /**
     * Start WebRTC Session for Channel
     */
    async function initRoomSession(channelId, key) {
        const tab = openTabs[channelId];
        if (!tab) return;

        try {
            tab.localStream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: true
            });
            if (activeTabId === channelId) {
                localVideo.srcObject = tab.localStream;
            }
        } catch (err) {
            console.warn('Camera/Mic permission warning for channel', channelId, err);
        }

        initSignaling(channelId);
    }

    /**
     * Initialize SSE Signaling for Channel
     */
    function initSignaling(channelId) {
        const tab = openTabs[channelId];
        if (!tab) return;

        const sseUrl = `/api/signal.php?room=${encodeURIComponent(channelId)}&client=${encodeURIComponent(myClientId)}&mode=sse`;
        tab.sseSource = new EventSource(sseUrl);

        tab.sseSource.onmessage = async (event) => {
            if (!event.data || event.data.trim() === '') return;
            try {
                const signal = JSON.parse(event.data);
                handleIncomingSignal(channelId, signal);
            } catch (err) {
                console.error('Error parsing signaling message:', err);
            }
        };

        sendSignal(channelId, {
            type: 'join',
            room: channelId,
            client: myClientId,
            nickname: myNickname,
            key: tab.key
        });
    }

    /**
     * Handle Incoming WebRTC & Chat Signals
     */
    async function handleIncomingSignal(channelId, signal) {
        const tab = openTabs[channelId];
        if (!tab || signal.sender === myClientId) return;

        if (tab.key && (signal.key || '') !== tab.key) {
            console.warn('Mismatched key for channel signal');
            return;
        }

        switch (signal.type) {
            case 'peer-joined':
                tab.remoteNick = signal.nickname || 'Remote Peer';
                tab.remoteClientId = signal.sender;
                if (!tab.peers.includes(signal.sender)) {
                    tab.peers.push(signal.sender);
                }

                addMessageToTab(channelId, {
                    sender: 'SYSTEM',
                    text: `Peer "${tab.remoteNick}" joined ${channelId}.`,
                    type: 'system'
                });

                createPeerConnection(channelId);
                createOffer(channelId);
                if (activeTabId === channelId) updateTabUI(channelId);
                break;

            case 'offer':
                tab.remoteNick = signal.nickname || tab.remoteNick;
                tab.remoteClientId = signal.sender;
                if (!tab.peers.includes(signal.sender)) {
                    tab.peers.push(signal.sender);
                }

                createPeerConnection(channelId);
                await tab.peerConnection.setRemoteDescription(new RTCSessionDescription(signal.sdp));
                const answer = await tab.peerConnection.createAnswer();
                await tab.peerConnection.setLocalDescription(answer);

                sendSignal(channelId, {
                    type: 'answer',
                    room: channelId,
                    client: myClientId,
                    nickname: myNickname,
                    key: tab.key,
                    sdp: answer
                });

                if (activeTabId === channelId) updateTabUI(channelId);
                break;

            case 'answer':
                if (tab.peerConnection) {
                    await tab.peerConnection.setRemoteDescription(new RTCSessionDescription(signal.sdp));
                }
                break;

            case 'ice-candidate':
                if (tab.peerConnection && signal.candidate) {
                    try {
                        await tab.peerConnection.addIceCandidate(new RTCIceCandidate(signal.candidate));
                    } catch (e) {
                        console.error('Error adding ICE candidate:', e);
                    }
                }
                break;

            case 'chat':
                const msgType = signal.is_bot ? 'bot' : 'peer';
                let senderName = signal.sender || 'Peer';
                if (signal.super_room && signal.super_room !== channelId) {
                    senderName = `[${signal.super_room}] ${senderName}`;
                } else if (signal.room && signal.room !== channelId) {
                    senderName = `[${signal.room}] ${senderName}`;
                }
                addMessageToTab(channelId, {
                    sender: senderName,
                    text: signal.message || signal.text,
                    type: msgType
                });
                break;

            case 'peer-left':
                tab.peers = tab.peers.filter(p => p !== signal.sender);
                addMessageToTab(channelId, {
                    sender: 'SYSTEM',
                    text: `Peer "${tab.remoteNick}" disconnected.`,
                    type: 'system'
                });
                if (activeTabId === channelId) {
                    resetRemoteVideo();
                    updateTabUI(channelId);
                }
                break;
        }
    }

    /**
     * Helper to append message to tab and trigger unread counter
     */
    function addMessageToTab(channelId, msg) {
        const tab = openTabs[channelId];
        if (!tab) return;

        tab.messages.push(msg);

        if (activeTabId === channelId) {
            renderChatMessages(tab);
        } else {
            tab.unreadCount = (tab.unreadCount || 0) + 1;
            renderTabsNav();
        }
    }

    async function sendSignal(channelId, payload) {
        try {
            await fetch('/api/signal.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.FORTRESS_CSRF_TOKEN || ''
                },
                body: JSON.stringify(payload)
            });
        } catch (err) {
            console.error('Failed to send signal:', err);
        }
    }

    function createPeerConnection(channelId) {
        const tab = openTabs[channelId];
        if (!tab || tab.peerConnection) return;

        tab.peerConnection = new RTCPeerConnection(rtcConfig);

        if (tab.localStream) {
            tab.localStream.getTracks().forEach(track => {
                tab.peerConnection.addTrack(track, tab.localStream);
            });
        }

        tab.peerConnection.ontrack = (event) => {
            if (event.streams && event.streams[0]) {
                if (activeTabId === channelId) {
                    remoteVideo.srcObject = event.streams[0];
                    remotePlaceholder.classList.add('hidden');
                    remoteStatusText.textContent = 'Connected';
                }
            }
        };

        tab.peerConnection.onicecandidate = (event) => {
            if (event.candidate) {
                sendSignal(channelId, {
                    type: 'ice-candidate',
                    room: channelId,
                    client: myClientId,
                    key: tab.key,
                    candidate: event.candidate
                });
            }
        };

        tab.dataChannel = tab.peerConnection.createDataChannel('fortress-chat');
        setupDataChannel(channelId, tab.dataChannel);

        tab.peerConnection.ondatachannel = (event) => {
            setupDataChannel(channelId, event.channel);
        };
    }

    async function createOffer(channelId) {
        const tab = openTabs[channelId];
        if (!tab || !tab.peerConnection) return;

        try {
            const offer = await tab.peerConnection.createOffer();
            await tab.peerConnection.setLocalDescription(offer);
            sendSignal(channelId, {
                type: 'offer',
                room: channelId,
                client: myClientId,
                nickname: myNickname,
                key: tab.key,
                sdp: offer
            });
        } catch (err) {
            console.error('Error creating offer:', err);
        }
    }

    function setupDataChannel(channelId, channel) {
        const tab = openTabs[channelId];
        if (!tab) return;

        channel.onmessage = (event) => {
            addMessageToTab(channelId, {
                sender: tab.remoteNick,
                text: event.data,
                type: 'peer'
            });
        };

        channel.onopen = () => {
            addMessageToTab(channelId, {
                sender: 'SYSTEM',
                text: 'Encrypted DataChannel connected.',
                type: 'system'
            });
        };
    }

    /**
     * Submit Chat or IRC Command
     */
    async function handleChatSubmit() {
        const text = chatInput.value.trim();
        if (!text || !activeTabId || activeTabId === '#stats') return;

        const tab = openTabs[activeTabId];
        chatInput.value = '';

        // Check if message is the IRC /theme command
        if (text.startsWith('/theme')) {
            addMessageToTab(activeTabId, {
                sender: myNickname,
                text: text,
                type: 'self'
            });

            const parts = text.split(/\s+/);
            const arg = (parts[1] || '').toLowerCase();

            if (!arg || arg === 'list' || arg === 'help') {
                const customThemes = getCustomThemes();
                const customNames = Object.values(customThemes).map(t => t.name).join(', ');
                const customListStr = customNames ? ` Custom: [${customNames}]` : '';
                addMessageToTab(activeTabId, {
                    sender: 'THEMESERV',
                    text: `Available themes: dark, light, halloween, console, christmas.${customListStr}. Usage: /theme <name> or /theme custom`,
                    type: 'bot'
                });
                return;
            }

            if (arg === 'custom' || arg === 'create') {
                openThemeModal();
                addMessageToTab(activeTabId, {
                    sender: 'THEMESERV',
                    text: 'Opened Custom Theme Creator dialog.',
                    type: 'bot'
                });
                return;
            }

            if (arg === 'reset') {
                applyTheme('dark');
                addMessageToTab(activeTabId, {
                    sender: 'THEMESERV',
                    text: 'Theme reset to default Dark theme.',
                    type: 'bot'
                });
                return;
            }

            // Check built-in or custom theme names/ids
            if (BUILTIN_THEMES.includes(arg)) {
                applyTheme(arg);
                addMessageToTab(activeTabId, {
                    sender: 'THEMESERV',
                    text: `Switched theme to "${arg}".`,
                    type: 'bot'
                });
                return;
            }

            const customThemes = getCustomThemes();
            const matchedCustomId = Object.keys(customThemes).find(id =>
                id === arg || customThemes[id].name.toLowerCase() === arg
            );

            if (matchedCustomId) {
                applyTheme(matchedCustomId);
                addMessageToTab(activeTabId, {
                    sender: 'THEMESERV',
                    text: `Switched theme to custom theme "${customThemes[matchedCustomId].name}".`,
                    type: 'bot'
                });
                return;
            }

            addMessageToTab(activeTabId, {
                sender: 'THEMESERV',
                text: `Unknown theme "${arg}". Available built-in themes: dark, light, halloween, console, christmas.`,
                type: 'bot'
            });
            return;
        }

        // Check if message is an IRC Service command (starts with /)
        if (text.startsWith('/')) {
            addMessageToTab(activeTabId, {
                sender: myNickname,
                text: text,
                type: 'self'
            });

            try {
                const res = await fetch('/api/irc.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.FORTRESS_CSRF_TOKEN || ''
                    },
                    body: JSON.stringify({
                        sender: myNickname,
                        channel: activeTabId,
                        text: text
                    })
                });
                const data = await res.json();
                if (data.status === 'ok' && data.is_service_command) {
                    addMessageToTab(activeTabId, {
                        sender: data.service,
                        text: data.response,
                        type: 'bot'
                    });
                    if (data.service === 'CHANSERV' && text.toLowerCase().includes('topic')) {
                        // Refresh channel topic
                        tab.topic = data.response;
                        channelTopicBar.textContent = `Topic: ${data.response}`;
                    }
                    return;
                }
            } catch (err) {
                console.error('Error sending IRC command:', err);
            }
        }

        // Standard Chat Message via DataChannel & Signaling Fallback
        if (tab.dataChannel && tab.dataChannel.readyState === 'open') {
            tab.dataChannel.send(text);
        }
        // Always transmit via signaling so super-room messages propagate to subrooms in RAM
        sendSignal(activeTabId, {
            type: 'chat',
            room: activeTabId,
            client: myClientId,
            nickname: myNickname,
            message: text
        });

        addMessageToTab(activeTabId, {
            sender: myNickname,
            text: text,
            type: 'self'
        });
    }

    /**
     * Load & Render Realtime Stats for #stats Room
     */
    async function loadConnectionStats() {
        try {
            const res = await fetch('/api/stats.php');
            const json = await res.json();

            if (json.status === 'ok' && json.stats) {
                const st = json.stats;

                serverStatsContent.innerHTML = `
                    <div class="stats-row"><span class="stats-label">Network Name:</span><span class="stats-value">${st.network_settings.network_name}</span></div>
                    <div class="stats-row"><span class="stats-label">IRC Server Host:</span><span class="stats-value">${st.network_settings.server_name}</span></div>
                    <div class="stats-row"><span class="stats-label">PHP Version:</span><span class="stats-value">${st.php_version}</span></div>
                    <div class="stats-row"><span class="stats-label">Database Status:</span><span class="stats-value" style="color: #10b981;">${st.database.status} (${st.database.driver.toUpperCase()})</span></div>
                    <div class="stats-row"><span class="stats-label">Registered Channels:</span><span class="stats-value">${st.database.registered_channels}</span></div>
                    <div class="stats-row"><span class="stats-label">Registered Nicks:</span><span class="stats-value">${st.database.registered_nicks}</span></div>
                    <div class="stats-row"><span class="stats-label">Active RAM Channels:</span><span class="stats-value">${st.signaling.active_rooms_count}</span></div>
                    <div class="stats-row"><span class="stats-label">Total Connected Clients:</span><span class="stats-value">${st.signaling.total_clients_count}</span></div>
                    <div class="stats-row"><span class="stats-label">Server Memory Usage:</span><span class="stats-value">${st.memory_usage_mb} MB (Peak: ${st.memory_peak_mb} MB)</span></div>
                    <div class="stats-row"><span class="stats-label">Server Time:</span><span class="stats-value">${st.server_time}</span></div>
                `;
            }
        } catch (err) {
            serverStatsContent.innerHTML = `<p style="color: var(--danger-color);">Failed to fetch server stats: ${err.message}</p>`;
        }

        // Gather WebRTC Client Telemetry Metrics
        const openTabsCount = Object.keys(openTabs).length;
        let rtcState = 'Disconnected';
        let rtt = 'N/A';

        const currentActive = openTabs[activeTabId];
        if (currentActive && currentActive.peerConnection) {
            rtcState = currentActive.peerConnection.connectionState || currentActive.peerConnection.iceConnectionState || 'Connecting';

            try {
                const stats = await currentActive.peerConnection.getStats();
                stats.forEach(report => {
                    if (report.type === 'candidate-pair' && report.currentRoundTripTime !== undefined) {
                        rtt = Math.round(report.currentRoundTripTime * 1000) + ' ms';
                    }
                });
            } catch (e) {}
        }

        clientStatsContent.innerHTML = `
            <div class="stats-row"><span class="stats-label">Your Client ID:</span><span class="stats-value">${myClientId}</span></div>
            <div class="stats-row"><span class="stats-label">Your Nickname:</span><span class="stats-value">${myNickname}</span></div>
            <div class="stats-row"><span class="stats-label">Open Channel Tabs:</span><span class="stats-value">${openTabsCount}</span></div>
            <div class="stats-row"><span class="stats-label">Current Active Tab:</span><span class="stats-value">${activeTabId || 'None'}</span></div>
            <div class="stats-row"><span class="stats-label">WebRTC Peer Connection State:</span><span class="stats-value">${rtcState}</span></div>
            <div class="stats-row"><span class="stats-label">WebRTC Round-Trip Time (RTT):</span><span class="stats-value">${rtt}</span></div>
            <div class="stats-row"><span class="stats-label">DataChannel Encryption:</span><span class="stats-value" style="color: #10b981;">AES-GCM (P2P Direct)</span></div>
            <div class="stats-row"><span class="stats-label">Signaling Mode:</span><span class="stats-value">Server-Sent Events (SSE)</span></div>
        `;
    }

    // Media Controls
    function toggleMicrophone() {
        if (!activeTabId || activeTabId === '#stats') return;
        const tab = openTabs[activeTabId];
        if (!tab || !tab.localStream) return;

        const track = tab.localStream.getAudioTracks()[0];
        if (track) {
            tab.isAudioMuted = !tab.isAudioMuted;
            track.enabled = !tab.isAudioMuted;
            btnToggleMic.classList.toggle('off', tab.isAudioMuted);
            btnToggleMic.querySelector('.icon').textContent = tab.isAudioMuted ? '🔇' : '🎙️';
            btnToggleMic.setAttribute('aria-pressed', tab.isAudioMuted ? 'true' : 'false');
            const labelText = tab.isAudioMuted ? 'Unmute Microphone' : 'Mute Microphone';
            btnToggleMic.setAttribute('aria-label', labelText);
            btnToggleMic.setAttribute('title', labelText);
        }
    }

    function toggleCamera() {
        if (!activeTabId || activeTabId === '#stats') return;
        const tab = openTabs[activeTabId];
        if (!tab || !tab.localStream) return;

        const track = tab.localStream.getVideoTracks()[0];
        if (track) {
            tab.isVideoMuted = !tab.isVideoMuted;
            track.enabled = !tab.isVideoMuted;
            btnToggleCam.classList.toggle('off', tab.isVideoMuted);
            btnToggleCam.querySelector('.icon').textContent = tab.isVideoMuted ? '📷' : '📹';
            btnToggleCam.setAttribute('aria-pressed', tab.isVideoMuted ? 'true' : 'false');
            const labelText = tab.isVideoMuted ? 'Turn On Camera' : 'Turn Off Camera';
            btnToggleCam.setAttribute('aria-label', labelText);
            btnToggleCam.setAttribute('title', labelText);
        }
    }

    async function toggleScreenShare() {
        if (!activeTabId || activeTabId === '#stats') return;
        const tab = openTabs[activeTabId];
        if (!tab || !tab.peerConnection) return;

        if (!tab.isScreenSharing) {
            try {
                const screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
                const screenTrack = screenStream.getVideoTracks()[0];

                const sender = tab.peerConnection.getSenders().find(s => s.track && s.track.kind === 'video');
                if (sender) sender.replaceTrack(screenTrack);

                localVideo.srcObject = screenStream;
                tab.isScreenSharing = true;
                btnShareScreen.classList.add('off');
                btnShareScreen.setAttribute('aria-pressed', 'true');
                btnShareScreen.setAttribute('aria-label', 'Stop Sharing Screen');
                btnShareScreen.setAttribute('title', 'Stop Sharing Screen');

                screenTrack.onended = () => {
                    stopScreenSharing(tab);
                };
            } catch (err) {
                console.warn('Screen sharing cancelled:', err);
            }
        } else {
            stopScreenSharing(tab);
        }
    }

    function stopScreenSharing(tab) {
        if (!tab || !tab.isScreenSharing || !tab.localStream) return;
        const videoTrack = tab.localStream.getVideoTracks()[0];
        const sender = tab.peerConnection.getSenders().find(s => s.track && s.track.kind === 'video');
        if (sender && videoTrack) sender.replaceTrack(videoTrack);
        localVideo.srcObject = tab.localStream;
        tab.isScreenSharing = false;
        btnShareScreen.classList.remove('off');
        btnShareScreen.setAttribute('aria-pressed', 'false');
        btnShareScreen.setAttribute('aria-label', 'Share Screen');
        btnShareScreen.setAttribute('title', 'Share Screen');
    }

    function resetRemoteVideo() {
        remoteVideo.srcObject = null;
        remotePlaceholder.classList.remove('hidden');
        remoteStatusText.textContent = 'Peer disconnected. Waiting for peer...';
    }
})();
