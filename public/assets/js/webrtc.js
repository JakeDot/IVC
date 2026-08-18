/**
 * Fortress / IVC WebRTC Client & Multi-Tab IRC Infrastructure
 * High-Availability Multi-Peer WebRTC Mesh with Audio Speaking Detection & Talking-User First Sorting.
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

    // Helper: Normalize channel name
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
    // openTabs[channelId] = { id, key, nick, peerConnections: {}, dataChannels: {}, remoteStreams: {}, peerNicks: {}, speakingStates: {}, audioAnalyzers: {}, localStream, sseSource, messages: [], peers: [], unreadCount: 0, topic: '' }
    const openTabs = {};
    let activeTabId = null;
    let statsInterval = null;

<<<<<<< HEAD
    // QUOTESERV Subscription & Periodic Quotes
    let isQuoteSubscribed = false;
    let quoteInterval = null;

    function startQuoteDeliveryTimer() {
        if (quoteInterval) return;
        quoteInterval = setInterval(async () => {
            if (!isQuoteSubscribed || !activeTabId || activeTabId === '#stats') return;
            try {
                const res = await fetch('/api/irc.php?action=random_quote');
                const data = await res.json();
                if (data.status === 'ok' && data.quote) {
                    addMessageToTab(activeTabId, {
                        sender: 'QUOTESERV',
                        text: `[Periodic Quote #${data.quote.id}] "${data.quote.quote_text}" — ${data.quote.created_by}`,
                        type: 'bot'
                    });
                }
            } catch (err) {
                console.error('Error fetching periodic quote:', err);
            }
        }, 30000);
=======
    // Shared AudioContext for volume analysis
    let audioContext = null;

    function getAudioContext() {
        if (!audioContext) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                audioContext = new AudioContextClass();
            }
        }
        if (audioContext && audioContext.state === 'suspended') {
            audioContext.resume().catch(() => {});
        }
        return audioContext;
>>>>>>> origin/master
    }

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
    const videoGrid = document.getElementById('video-grid');

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
    const btnAttachFile = document.getElementById('btn-attach-file');
    const chatFileInput = document.getElementById('chat-file-input');

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

    // Window Hashchange Listener
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

    // User interaction listener to un-suspend AudioContext
    window.addEventListener('click', () => {
        getAudioContext();
    }, { once: true });

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

    if (btnAttachFile && chatFileInput) {
        btnAttachFile.addEventListener('click', () => chatFileInput.click());
        chatFileInput.addEventListener('change', handleShareFileSelect);
    }

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
            peerConnections: {}, // peerClientId -> RTCPeerConnection
            dataChannels: {},    // peerClientId -> RTCDataChannel
            remoteStreams: {},   // peerClientId -> MediaStream
            peerNicks: {},       // peerClientId -> nickname
            speakingStates: {},  // 'local' or peerClientId -> boolean
            audioAnalyzers: {},  // 'local' or peerClientId -> analyzer obj
            localStream: null,
            sseSource: null,
            sseReconnectTimer: null,
            healthCheckInterval: null,
            messages: [],
            peers: [],
            unreadCount: 0,
            topic: isStats ? 'IRC Connection Stats & WebRTC Telemetry' : `Welcome to ${channelId}`,
            isAudioMuted: false,
            isVideoMuted: false,
            isScreenSharing: false
        };

        if (!isStats) {
            openTabs[channelId].messages.push({
                sender: 'SYSTEM',
                text: `Joined channel ${channelId}. Direct P2P encrypted chat active. Type /help for IRC service commands or /theme <name> to change themes.`,
                type: 'system'
            });

            initRoomSession(channelId, key);
            loadChannelSharedFiles(channelId, key);
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

        const tab = openTabs[channelId];

        if (!tab.isStats) {
            sendSignal(channelId, { type: 'leave', room: channelId, client: myClientId, key: tab.key });

            if (tab.sseReconnectTimer) clearTimeout(tab.sseReconnectTimer);
            if (tab.healthCheckInterval) clearInterval(tab.healthCheckInterval);
            if (tab.sseSource) tab.sseSource.close();

            // Clean audio analyzers
            Object.values(tab.audioAnalyzers).forEach(a => {
                if (a && a.intervalId) clearInterval(a.intervalId);
            });

            // Clean peer connections
            Object.values(tab.peerConnections).forEach(pc => pc && pc.close());

            if (tab.localStream) {
                tab.localStream.getTracks().forEach(track => track.stop());
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

        // Render Video Grid
        renderVideoGrid(channelId);
    }

    function renderChatMessages(tab) {
        chatMessages.innerHTML = '';
        tab.messages.forEach(msg => {
            if (msg.type === 'system') {
                const div = document.createElement('div');
                div.className = 'system-message';
                div.textContent = msg.text;
                chatMessages.appendChild(div);
            } else if (msg.type === 'file') {
                const msgDiv = document.createElement('div');
                msgDiv.className = `chat-msg ${msg.isSelf ? 'self' : 'peer'}`;

                const senderTag = document.createElement('div');
                senderTag.className = 'sender-tag';
                senderTag.textContent = msg.sender || msg.sharerNick || 'Anonymous';

                const fileCard = document.createElement('div');
                fileCard.className = 'file-card';

                const iconDiv = document.createElement('div');
                iconDiv.className = 'file-card-icon';
                iconDiv.textContent = getFileIcon(msg.fileName, msg.fileType);

                const infoDiv = document.createElement('div');
                infoDiv.className = 'file-card-info';

                const nameDiv = document.createElement('div');
                nameDiv.className = 'file-card-name';
                nameDiv.textContent = msg.fileName;

                const metaDiv = document.createElement('div');
                metaDiv.className = 'file-card-meta';
                const sizeStr = formatFileSize(msg.fileSize);
                const isReady = !!localSharedFilesMap[msg.fileId];
                if (msg.cloudLink) {
                    metaDiv.textContent = `${sizeStr} • ☁️ Cloud Shared`;
                } else {
                    metaDiv.textContent = `${sizeStr} • ${isReady ? '🔒 Local Ready' : '📡 On Sharer'}`;
                }

                const dlBtn = document.createElement('button');
                dlBtn.className = 'btn-file-dl';
                if (isReady) {
                    dlBtn.textContent = '💾 Save / DL';
                } else if (msg.cloudLink) {
                    dlBtn.textContent = '☁️ Open Link';
                } else {
                    dlBtn.textContent = '📥 Request File';
                }

                dlBtn.addEventListener('click', () => {
                    handleFileDownloadOrRequest(tab.id, msg.fileId, msg.sharerClientId, msg.fileName, msg.fileType, msg.cloudLink, dlBtn);
                });

                infoDiv.appendChild(nameDiv);
                infoDiv.appendChild(metaDiv);
                fileCard.appendChild(iconDiv);
                fileCard.appendChild(infoDiv);
                fileCard.appendChild(dlBtn);

                msgDiv.appendChild(senderTag);
                msgDiv.appendChild(fileCard);
                chatMessages.appendChild(msgDiv);
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
        const isSelfTalking = !!tab.speakingStates['local'];
        selfLi.innerHTML = `<span class="op-tag">@</span> ${myNickname} (You) ${isSelfTalking ? '<span class="talking-dot" title="Speaking"></span>' : ''}`;
        userList.appendChild(selfLi);

        // Add remote peers
        tab.peers.forEach(peerId => {
            const li = document.createElement('li');
            li.className = 'user-item';
            const nick = tab.peerNicks[peerId] || peerId;
            const isPeerTalking = !!tab.speakingStates[peerId];
            li.innerHTML = `<span>👤</span> ${nick} ${isPeerTalking ? '<span class="talking-dot" title="Speaking"></span>' : ''}`;
            userList.appendChild(li);
        });
    }

    /**
     * DEDICATED USER INTERFACE: Render & Sort Video Grid with Talking Users First
     */
    function renderVideoGrid(channelId) {
        if (activeTabId !== channelId) return;

        const tab = openTabs[channelId];
        if (!tab) return;

        // Build array of active participants
        const participants = [];

        // 1. Local user
        participants.push({
            id: 'local',
            nick: `${myNickname} (You)`,
            isLocal: true,
            stream: tab.localStream,
            isTalking: !!tab.speakingStates['local'],
            hasVideo: !!(tab.localStream && tab.localStream.getVideoTracks().some(t => t.enabled))
        });

        // 2. Remote peers
        tab.peers.forEach(peerId => {
            const stream = tab.remoteStreams[peerId] || null;
            const nick = tab.peerNicks[peerId] || peerId;
            const isTalking = !!tab.speakingStates[peerId];
            const hasVideo = !!(stream && stream.getVideoTracks().some(t => t.enabled));

            participants.push({
                id: peerId,
                nick: nick,
                isLocal: false,
                stream: stream,
                isTalking: isTalking,
                hasVideo: hasVideo
            });
        });

        // Sort participants: Show Talking Users First!
        participants.sort((a, b) => {
            if (a.isTalking !== b.isTalking) {
                return b.isTalking ? 1 : -1; // Talking users first
            }
            if (a.hasVideo !== b.hasVideo) {
                return b.hasVideo ? 1 : -1; // Active video streams next
            }
            if (a.isLocal !== b.isLocal) {
                return a.isLocal ? -1 : 1;  // Local user
            }
            return a.nick.localeCompare(b.nick);
        });

        // Clear existing video grid
        videoGrid.innerHTML = '';

        participants.forEach(p => {
            const wrapper = document.createElement('div');
            wrapper.className = `video-wrapper ${p.isLocal ? 'local-wrapper' : 'remote-wrapper'} ${p.isTalking ? 'talking' : ''}`;
            wrapper.setAttribute('data-peer-id', p.id);

            // Talking Badge
            if (p.isTalking) {
                const badge = document.createElement('div');
                badge.className = 'talking-badge';
                badge.innerHTML = '🗣️ Speaking';
                wrapper.appendChild(badge);
            }

            // Video Element
            const videoEl = document.createElement('video');
            videoEl.autoplay = true;
            videoEl.playsInline = true;
            if (p.isLocal) videoEl.muted = true;

            if (p.stream) {
                videoEl.srcObject = p.stream;
                wrapper.appendChild(videoEl);
            } else {
                // Placeholder
                const placeholder = document.createElement('div');
                placeholder.className = 'video-placeholder';
                placeholder.innerHTML = `
                    <div class="pulse-ring"></div>
                    <span class="placeholder-icon">👤</span>
                    <p>Connecting audio & video stream...</p>
                `;
                wrapper.appendChild(placeholder);
            }

            // Label
            const label = document.createElement('div');
            label.className = 'video-label';
            label.innerHTML = `<span class="dot ${p.hasVideo || p.stream ? 'live-dot' : ''}"></span> <span>${p.nick}</span>`;
            wrapper.appendChild(label);

            videoGrid.appendChild(wrapper);
        });
    }

    /**
     * Real-Time Web Audio API Volume Analysis for Active Speaker Detection
     */
    function setupAudioAnalyzer(stream, peerId, channelId) {
        const tab = openTabs[channelId];
        if (!tab || !stream) return;

        const audioTracks = stream.getAudioTracks();
        if (audioTracks.length === 0) return;

        // Clean existing analyzer for this peer
        if (tab.audioAnalyzers[peerId]) {
            clearInterval(tab.audioAnalyzers[peerId].intervalId);
            delete tab.audioAnalyzers[peerId];
        }

        const ctx = getAudioContext();
        if (!ctx) return;

        try {
            const source = ctx.createMediaStreamSource(stream);
            const analyser = ctx.createAnalyser();
            analyser.fftSize = 512;
            analyser.smoothingTimeConstant = 0.4;
            source.connect(analyser);

            const buffer = new Float32Array(analyser.fftSize);
            let silenceTimer = null;

            const intervalId = setInterval(() => {
                if (!openTabs[channelId]) {
                    clearInterval(intervalId);
                    return;
                }

                analyser.getFloatTimeDomainData(buffer);
                let sum = 0;
                for (let i = 0; i < buffer.length; i++) {
                    sum += buffer[i] * buffer[i];
                }
                const rms = Math.sqrt(sum / buffer.length);

                // Threshold RMS level ~ 0.025 for speech detection
                const isSpeakingNow = rms > 0.025;

                if (isSpeakingNow) {
                    if (silenceTimer) {
                        clearTimeout(silenceTimer);
                        silenceTimer = null;
                    }
                    if (!tab.speakingStates[peerId]) {
                        tab.speakingStates[peerId] = true;
                        if (activeTabId === channelId) {
                            renderVideoGrid(channelId);
                            renderUserList(tab);
                        }
                    }
                } else if (tab.speakingStates[peerId] && !silenceTimer) {
                    // Debounce / hysteresis: wait 450ms before clearing talking state
                    silenceTimer = setTimeout(() => {
                        tab.speakingStates[peerId] = false;
                        silenceTimer = null;
                        if (activeTabId === channelId) {
                            renderVideoGrid(channelId);
                            renderUserList(tab);
                        }
                    }, 450);
                }
            }, 100);

            tab.audioAnalyzers[peerId] = { context: ctx, analyser, intervalId };
        } catch (err) {
            console.warn('Could not initialize audio analyzer for peer:', peerId, err);
        }
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
            setupAudioAnalyzer(tab.localStream, 'local', channelId);
        } catch (err) {
            console.warn('Camera/Mic permission warning for channel', channelId, err);
        }

        initSignaling(channelId);
        startHealthMonitoring(channelId);
    }

    /**
     * Initialize SSE Signaling with Automatic Reconnection & High Availability
     */
    function initSignaling(channelId) {
        const tab = openTabs[channelId];
        if (!tab) return;

        if (tab.sseSource) {
            tab.sseSource.close();
        }

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

        tab.sseSource.onerror = () => {
            console.warn(`SSE Connection lost for channel ${channelId}. Retrying in 3 seconds...`);
            if (tab.sseSource) tab.sseSource.close();
            if (tab.sseReconnectTimer) clearTimeout(tab.sseReconnectTimer);
            tab.sseReconnectTimer = setTimeout(() => {
                if (openTabs[channelId]) {
                    initSignaling(channelId);
                }
            }, 3000);
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
     * Periodic WebRTC Connection Health Monitor
     */
    function startHealthMonitoring(channelId) {
        const tab = openTabs[channelId];
        if (!tab) return;

        tab.healthCheckInterval = setInterval(() => {
            if (!openTabs[channelId]) return;

            // Ping active peers to maintain channel presence in RAM
            sendSignal(channelId, {
                type: 'ping',
                room: channelId,
                client: myClientId,
                nickname: myNickname,
                key: tab.key
            });

            // Inspect RTC connections for failed state recovery
            for (const [peerId, pc] of Object.entries(tab.peerConnections)) {
                if (pc && (pc.connectionState === 'failed' || pc.iceConnectionState === 'failed')) {
                    console.warn(`Peer Connection with ${peerId} failed. Triggering ICE restart offer...`);
                    createOffer(channelId, peerId, { iceRestart: true });
                }
            }
        }, 10000);
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

        const peerId = signal.sender;

        switch (signal.type) {
            case 'peer-joined':
            case 'ping':
                if (peerId) {
                    tab.peerNicks[peerId] = signal.nickname || peerId;
                    if (!tab.peers.includes(peerId)) {
                        tab.peers.push(peerId);
                        addMessageToTab(channelId, {
                            sender: 'SYSTEM',
                            text: `Peer "${tab.peerNicks[peerId]}" joined ${channelId}.`,
                            type: 'system'
                        });
                    }

                    if (signal.type === 'peer-joined') {
                        createPeerConnection(channelId, peerId);
                        createOffer(channelId, peerId);
                    }

                    if (activeTabId === channelId) {
                        renderVideoGrid(channelId);
                        renderUserList(tab);
                    }
                }
                break;

            case 'offer':
                tab.peerNicks[peerId] = signal.nickname || peerId;
                if (!tab.peers.includes(peerId)) {
                    tab.peers.push(peerId);
                }

                createPeerConnection(channelId, peerId);
                const pc = tab.peerConnections[peerId];
                if (pc) {
                    await pc.setRemoteDescription(new RTCSessionDescription(signal.sdp));
                    const answer = await pc.createAnswer();
                    await pc.setLocalDescription(answer);

                    sendSignal(channelId, {
                        type: 'answer',
                        room: channelId,
                        client: myClientId,
                        target: peerId,
                        nickname: myNickname,
                        key: tab.key,
                        sdp: answer
                    });
                }

                if (activeTabId === channelId) {
                    renderVideoGrid(channelId);
                    renderUserList(tab);
                }
                break;

            case 'answer':
                if (tab.peerConnections[peerId]) {
                    await tab.peerConnections[peerId].setRemoteDescription(new RTCSessionDescription(signal.sdp));
                }
                break;

            case 'ice-candidate':
                if (tab.peerConnections[peerId] && signal.candidate) {
                    try {
                        await tab.peerConnections[peerId].addIceCandidate(new RTCIceCandidate(signal.candidate));
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

            case 'file-shared':
                if (signal.fileId && signal.encrypted_metadata) {
                    const meta = await decryptMetadataE2EE(signal.encrypted_metadata, channelId, tab.key);
                    if (meta && !tab.messages.some(m => m.type === 'file' && m.fileId === signal.fileId)) {
                        addMessageToTab(channelId, {
                            type: 'file',
                            fileId: signal.fileId,
                            sharerClientId: signal.sharer_client_id || signal.sender,
                            sharerNick: meta.sharerNick || signal.sender,
                            fileName: meta.fileName || meta.name || 'Shared File',
                            fileSize: meta.fileSize || meta.size || 0,
                            fileType: meta.fileType || meta.type || '',
                            cloudLink: meta.cloudLink || signal.cloud_link || null,
                            sender: meta.sharerNick || signal.sender,
                            isSelf: (signal.sharer_client_id || signal.sender) === myClientId,
                            createdAt: signal.created_at || Math.floor(Date.now() / 1000)
                        });
                    }
                }
                break;

            case 'file-request':
                if (signal.fileId && (signal.target === myClientId || !signal.target)) {
                    const localFile = localSharedFilesMap[signal.fileId];
                    if (localFile && localFile.blob) {
                        const reader = new FileReader();
                        reader.onload = () => {
                            sendSignal(channelId, {
                                type: 'file-response',
                                room: channelId,
                                client: myClientId,
                                target: signal.requesterId || signal.sender,
                                fileId: signal.fileId,
                                fileName: localFile.metadata.name || localFile.file.name,
                                fileType: localFile.metadata.type || localFile.file.type,
                                dataUrl: reader.result,
                                key: tab.key
                            });
                        };
                        reader.readAsDataURL(localFile.blob);
                    }
                }
                break;

            case 'file-response':
                if (signal.fileId && signal.dataUrl && (signal.target === myClientId || !signal.target)) {
                    try {
                        const blobRes = await fetch(signal.dataUrl);
                        const blob = await blobRes.blob();
                        localSharedFilesMap[signal.fileId] = {
                            blob: blob,
                            file: blob,
                            metadata: {
                                id: signal.fileId,
                                name: signal.fileName || 'download',
                                type: signal.fileType || blob.type
                            }
                        };

                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = signal.fileName || 'download';
                        a.click();
                        setTimeout(() => URL.revokeObjectURL(url), 1000);

                        if (activeTabId === channelId) {
                            renderChatMessages(tab);
                        }
                    } catch (e) {
                        console.error('Failed to process file response signal:', e);
                    }
                }
                break;

            case 'peer-left':
                removePeerFromTab(channelId, peerId);
                break;
        }
    }

    function removePeerFromTab(channelId, peerId) {
        const tab = openTabs[channelId];
        if (!tab) return;

        const nick = tab.peerNicks[peerId] || peerId;

        if (tab.peerConnections[peerId]) {
            tab.peerConnections[peerId].close();
            delete tab.peerConnections[peerId];
        }

        if (tab.audioAnalyzers[peerId]) {
            clearInterval(tab.audioAnalyzers[peerId].intervalId);
            delete tab.audioAnalyzers[peerId];
        }

        delete tab.remoteStreams[peerId];
        delete tab.peerNicks[peerId];
        delete tab.speakingStates[peerId];
        tab.peers = tab.peers.filter(p => p !== peerId);

        addMessageToTab(channelId, {
            sender: 'SYSTEM',
            text: `Peer "${nick}" disconnected.`,
            type: 'system'
        });

        if (activeTabId === channelId) {
            renderVideoGrid(channelId);
            renderUserList(tab);
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

    /**
     * Create Independent RTCPeerConnection for a Specific Peer in Channel Mesh
     */
    function createPeerConnection(channelId, peerId) {
        const tab = openTabs[channelId];
        if (!tab || tab.peerConnections[peerId]) return;

        const pc = new RTCPeerConnection(rtcConfig);
        tab.peerConnections[peerId] = pc;

        if (tab.localStream) {
            tab.localStream.getTracks().forEach(track => {
                pc.addTrack(track, tab.localStream);
            });
        }

        pc.ontrack = (event) => {
            if (event.streams && event.streams[0]) {
                tab.remoteStreams[peerId] = event.streams[0];
                setupAudioAnalyzer(event.streams[0], peerId, channelId);

                if (activeTabId === channelId) {
                    renderVideoGrid(channelId);
                }
            }
        };

        pc.onicecandidate = (event) => {
            if (event.candidate) {
                sendSignal(channelId, {
                    type: 'ice-candidate',
                    room: channelId,
                    client: myClientId,
                    target: peerId,
                    key: tab.key,
                    candidate: event.candidate
                });
            }
        };

        const dc = pc.createDataChannel('fortress-chat');
        tab.dataChannels[peerId] = dc;
        setupDataChannel(channelId, peerId, dc);

        pc.ondatachannel = (event) => {
            tab.dataChannels[peerId] = event.channel;
            setupDataChannel(channelId, peerId, event.channel);
        };
    }

    async function createOffer(channelId, peerId, options = {}) {
        const tab = openTabs[channelId];
        if (!tab || !tab.peerConnections[peerId]) return;

        try {
            const pc = tab.peerConnections[peerId];
            const offer = await pc.createOffer(options);
            await pc.setLocalDescription(offer);

            sendSignal(channelId, {
                type: 'offer',
                room: channelId,
                client: myClientId,
                target: peerId,
                nickname: myNickname,
                key: tab.key,
                sdp: offer
            });
        } catch (err) {
            console.error(`Error creating offer for peer ${peerId}:`, err);
        }
    }

    function setupDataChannel(channelId, peerId, channel) {
        const tab = openTabs[channelId];
        if (!tab) return;

        channel.onmessage = async (event) => {
            if (typeof event.data === 'string' && event.data.trim().startsWith('{')) {
                try {
                    const parsed = JSON.parse(event.data);
                    if (parsed.type === 'file-request' && parsed.fileId) {
                        const localFile = localSharedFilesMap[parsed.fileId];
                        if (localFile && localFile.blob) {
                            const reader = new FileReader();
                            reader.onload = () => {
                                if (channel.readyState === 'open') {
                                    channel.send(JSON.stringify({
                                        type: 'file-response',
                                        fileId: parsed.fileId,
                                        fileName: localFile.metadata.name || localFile.file.name,
                                        fileType: localFile.metadata.type || localFile.file.type,
                                        dataUrl: reader.result
                                    }));
                                }
                            };
                            reader.readAsDataURL(localFile.blob);
                        }
                        return;
                    }

                    if (parsed.type === 'file-response' && parsed.fileId && parsed.dataUrl) {
                        try {
                            const blobRes = await fetch(parsed.dataUrl);
                            const blob = await blobRes.blob();
                            localSharedFilesMap[parsed.fileId] = {
                                blob: blob,
                                file: blob,
                                metadata: {
                                    id: parsed.fileId,
                                    name: parsed.fileName || 'download',
                                    type: parsed.fileType || blob.type
                                }
                            };

                            const url = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = parsed.fileName || 'download';
                            a.click();
                            setTimeout(() => URL.revokeObjectURL(url), 1000);

                            if (activeTabId === channelId) {
                                renderChatMessages(tab);
                            }
                        } catch (e) {
                            console.error('Error handling DataChannel file response:', e);
                        }
                        return;
                    }
                } catch (e) {
                    // Normal text message
                }
            }

            const nick = tab.peerNicks[peerId] || peerId;
            addMessageToTab(channelId, {
                sender: nick,
                text: event.data,
                type: 'peer'
            });
        };

        channel.onopen = () => {
            addMessageToTab(channelId, {
                sender: 'SYSTEM',
                text: `Encrypted DataChannel active with ${tab.peerNicks[peerId] || peerId}.`,
                type: 'system'
            });
        };
    }

    /* ==========================================================================
       E2EE FILE SHARING & PERMANENT CHAT HISTORY PROTOCOL
       ========================================================================== */

    const localSharedFilesMap = {}; // fileId -> { blob, file, metadata }

    function formatFileSize(bytes) {
        if (!bytes || isNaN(bytes)) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    function getFileIcon(fileName = '', fileType = '') {
        const ext = (fileName || '').split('.').pop().toLowerCase();
        if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext) || (fileType && fileType.startsWith('image/'))) return '🖼️';
        if (['mp4', 'webm', 'mov', 'avi'].includes(ext) || (fileType && fileType.startsWith('video/'))) return '🎥';
        if (['mp3', 'wav', 'ogg', 'flac'].includes(ext) || (fileType && fileType.startsWith('audio/'))) return '🎵';
        if (['pdf', 'doc', 'docx', 'txt', 'md', 'json'].includes(ext)) return '📄';
        if (['zip', 'tar', 'gz', '7z', 'rar'].includes(ext)) return '📦';
        return '📁';
    }

    async function deriveE2EEKey(channelId, channelPasskey = '') {
        const secret = `IVC-E2EE-METADATA:${channelId}:${channelPasskey || 'default-room-salt'}`;
        const enc = new TextEncoder();
        const keyData = enc.encode(secret);
        const hash = await crypto.subtle.digest('SHA-256', keyData);
        return crypto.subtle.importKey(
            'raw',
            hash,
            { name: 'AES-GCM' },
            false,
            ['encrypt', 'decrypt']
        );
    }

    async function encryptMetadataE2EE(metadataObj, channelId, channelPasskey = '') {
        try {
            const cryptoKey = await deriveE2EEKey(channelId, channelPasskey);
            const iv = crypto.getRandomValues(new Uint8Array(12));
            const enc = new TextEncoder();
            const jsonStr = JSON.stringify(metadataObj);
            const ciphertext = await crypto.subtle.encrypt(
                { name: 'AES-GCM', iv: iv },
                cryptoKey,
                enc.encode(jsonStr)
            );
            const combined = new Uint8Array(iv.length + ciphertext.byteLength);
            combined.set(iv, 0);
            combined.set(new Uint8Array(ciphertext), iv.length);
            return btoa(String.fromCharCode(...combined));
        } catch (err) {
            console.error('Metadata encryption error:', err);
            return btoa(JSON.stringify(metadataObj));
        }
    }

    async function decryptMetadataE2EE(encryptedBase64, channelId, channelPasskey = '') {
        try {
            const combined = Uint8Array.from(atob(encryptedBase64), c => c.charCodeAt(0));
            if (combined.length <= 12) {
                return JSON.parse(atob(encryptedBase64));
            }
            const iv = combined.slice(0, 12);
            const ciphertext = combined.slice(12);
            const cryptoKey = await deriveE2EEKey(channelId, channelPasskey);
            const decrypted = await crypto.subtle.decrypt(
                { name: 'AES-GCM', iv: iv },
                cryptoKey,
                ciphertext
            );
            const dec = new TextDecoder();
            return JSON.parse(dec.decode(decrypted));
        } catch (err) {
            try {
                return JSON.parse(atob(encryptedBase64));
            } catch (e) {
                console.error('Metadata decryption error:', err);
                return null;
            }
        }
    }

    async function loadChannelSharedFiles(channelId, key = '') {
        const tab = openTabs[channelId];
        if (!tab) return;

        try {
            const res = await fetch(`/api/files.php?channel=${encodeURIComponent(channelId)}`);
            const data = await res.json();
            if (data.status === 'ok' && Array.isArray(data.files)) {
                let added = false;
                for (const fileRecord of data.files) {
                    if (tab.messages.some(m => m.type === 'file' && m.fileId === fileRecord.id)) {
                        continue;
                    }

                    const meta = await decryptMetadataE2EE(fileRecord.encrypted_metadata, channelId, key);
                    if (meta) {
                        tab.messages.push({
                            type: 'file',
                            fileId: fileRecord.id,
                            sharerClientId: fileRecord.sharer_client_id,
                            sharerNick: meta.sharerNick || fileRecord.sharer_client_id,
                            fileName: meta.fileName || meta.name || 'Shared File',
                            fileSize: meta.fileSize || meta.size || 0,
                            fileType: meta.fileType || meta.type || 'application/octet-stream',
                            cloudLink: meta.cloudLink || fileRecord.cloud_link || null,
                            sender: meta.sharerNick || fileRecord.sharer_client_id,
                            isSelf: fileRecord.sharer_client_id === myClientId,
                            createdAt: fileRecord.created_at
                        });
                        added = true;
                    }
                }

                if (added) {
                    tab.messages.sort((a, b) => (a.createdAt || 0) - (b.createdAt || 0));
                    if (activeTabId === channelId) {
                        renderChatMessages(tab);
                    }
                }
            }
        } catch (err) {
            console.error('Failed to load shared files for channel:', err);
        }
    }

    async function handleShareFileSelect(e) {
        const file = e.target.files[0];
        if (!file) return;
        chatFileInput.value = '';

        if (!activeTabId || activeTabId === '#stats') {
            alert('Please select an active channel to share files.');
            return;
        }

        const tab = openTabs[activeTabId];
        if (!tab) return;

        const cloudPrompt = prompt('Optional cloud URL/link if hosted externally (e.g. Nextcloud, S3, Drive link), or leave empty for pure P2P share:');
        const cloudLink = cloudPrompt ? cloudPrompt.trim() : null;

        const fileId = 'file_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8);

        localSharedFilesMap[fileId] = {
            file: file,
            blob: file,
            metadata: {
                id: fileId,
                name: file.name,
                size: file.size,
                type: file.type,
                cloudLink: cloudLink,
                sharerClientId: myClientId,
                sharerNick: myNickname,
                createdAt: Math.floor(Date.now() / 1000)
            }
        };

        const metadataObj = {
            id: fileId,
            fileName: file.name,
            fileSize: file.size,
            fileType: file.type,
            cloudLink: cloudLink,
            sharerNick: myNickname,
            sharerClientId: myClientId,
            createdAt: Math.floor(Date.now() / 1000)
        };

        const encryptedMeta = await encryptMetadataE2EE(metadataObj, activeTabId, tab.key);

        try {
            await fetch('/api/files.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.FORTRESS_CSRF_TOKEN || ''
                },
                body: JSON.stringify({
                    id: fileId,
                    channel: activeTabId,
                    sharer_client_id: myClientId,
                    encrypted_metadata: encryptedMeta,
                    cloud_link: cloudLink,
                    created_at: metadataObj.createdAt
                })
            });
        } catch (err) {
            console.error('Error persisting E2EE file metadata:', err);
        }

        const fileMsg = {
            type: 'file',
            fileId: fileId,
            sharerClientId: myClientId,
            sharerNick: myNickname,
            fileName: file.name,
            fileSize: file.size,
            fileType: file.type,
            cloudLink: cloudLink,
            sender: myNickname,
            isSelf: true,
            createdAt: metadataObj.createdAt
        };

        addMessageToTab(activeTabId, fileMsg);

        sendSignal(activeTabId, {
            type: 'file-shared',
            room: activeTabId,
            client: myClientId,
            fileId: fileId,
            sharer_client_id: myClientId,
            encrypted_metadata: encryptedMeta,
            cloud_link: cloudLink,
            key: tab.key
        });
    }

    function handleFileDownloadOrRequest(channelId, fileId, sharerClientId, fileName, fileType, cloudLink, btnElement) {
        if (localSharedFilesMap[fileId] && localSharedFilesMap[fileId].blob) {
            const url = URL.createObjectURL(localSharedFilesMap[fileId].blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = fileName || 'download';
            a.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            return;
        }

        if (cloudLink) {
            window.open(cloudLink, '_blank', 'noopener,noreferrer');
            return;
        }

        if (btnElement) {
            btnElement.textContent = '🔄 Requesting...';
            btnElement.disabled = true;
        }

        const tab = openTabs[channelId];
        const key = tab ? tab.key : '';

        sendSignal(channelId, {
            type: 'file-request',
            room: channelId,
            client: myClientId,
            fileId: fileId,
            target: sharerClientId,
            requesterId: myClientId,
            requesterNick: myNickname,
            key: key
        });

        if (tab && tab.dataChannels) {
            Object.values(tab.dataChannels).forEach(dc => {
                if (dc.readyState === 'open') {
                    dc.send(JSON.stringify({
                        type: 'file-request',
                        fileId: fileId,
                        requesterId: myClientId,
                        requesterNick: myNickname
                    }));
                }
            });
        }
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
                        tab.topic = data.response;
                        channelTopicBar.textContent = `Topic: ${data.response}`;
                    }
                    if (data.service === 'QUOTESERV') {
                        const lower = text.toLowerCase();
                        if ((lower.includes('sub') || lower.includes('subscribe')) && !lower.includes('unsub')) {
                            isQuoteSubscribed = true;
                            startQuoteDeliveryTimer();
                        } else if (lower.includes('unsub') || lower.includes('unsubscribe')) {
                            isQuoteSubscribed = false;
                        }
                    }
                }
            } catch (err) {
                console.error('Error sending IRC command:', err);
            }
            return;
        }

        // Broadcast message to all peer DataChannels in room
        Object.values(tab.dataChannels).forEach(dc => {
            if (dc && dc.readyState === 'open') {
                dc.send(text);
            }
        });

        // Always transmit via signaling fallback so messages propagate in RAM
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
        let activePeerCount = 0;
        let rtcState = 'Disconnected';

        const currentActive = openTabs[activeTabId];
        if (currentActive) {
            activePeerCount = currentActive.peers.length;
            const pcs = Object.values(currentActive.peerConnections);
            if (pcs.length > 0) {
                rtcState = pcs[0].connectionState || pcs[0].iceConnectionState || 'Connected';
            }
        }

        clientStatsContent.innerHTML = `
            <div class="stats-row"><span class="stats-label">Your Client ID:</span><span class="stats-value">${myClientId}</span></div>
            <div class="stats-row"><span class="stats-label">Your Nickname:</span><span class="stats-value">${myNickname}</span></div>
            <div class="stats-row"><span class="stats-label">Open Channel Tabs:</span><span class="stats-value">${openTabsCount}</span></div>
            <div class="stats-row"><span class="stats-label">Current Active Tab:</span><span class="stats-value">${activeTabId || 'None'}</span></div>
            <div class="stats-row"><span class="stats-label">Active Channel Peers:</span><span class="stats-value">${activePeerCount}</span></div>
            <div class="stats-row"><span class="stats-label">WebRTC Peer Connection State:</span><span class="stats-value">${rtcState}</span></div>
            <div class="stats-row"><span class="stats-label">DataChannel Encryption:</span><span class="stats-value" style="color: #10b981;">AES-GCM (P2P Direct Mesh)</span></div>
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
            if (activeTabId) renderVideoGrid(activeTabId);
            btnToggleCam.setAttribute('aria-pressed', tab.isVideoMuted ? 'true' : 'false');
            const labelText = tab.isVideoMuted ? 'Turn On Camera' : 'Turn Off Camera';
            btnToggleCam.setAttribute('aria-label', labelText);
            btnToggleCam.setAttribute('title', labelText);
        }
    }

    async function toggleScreenShare() {
        if (!activeTabId || activeTabId === '#stats') return;
        const tab = openTabs[activeTabId];
        if (!tab) return;

        if (!tab.isScreenSharing) {
            try {
                const screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
                const screenTrack = screenStream.getVideoTracks()[0];

                Object.values(tab.peerConnections).forEach(pc => {
                    const sender = pc.getSenders().find(s => s.track && s.track.kind === 'video');
                    if (sender) sender.replaceTrack(screenTrack);
                });

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
        Object.values(tab.peerConnections).forEach(pc => {
            const sender = pc.getSenders().find(s => s.track && s.track.kind === 'video');
            if (sender && videoTrack) sender.replaceTrack(videoTrack);
        });
        tab.isScreenSharing = false;
        btnShareScreen.classList.remove('off');
        btnShareScreen.setAttribute('aria-pressed', 'false');
        btnShareScreen.setAttribute('aria-label', 'Share Screen');
        btnShareScreen.setAttribute('title', 'Share Screen');
    }
})();
