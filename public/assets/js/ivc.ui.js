'use strict';

// DOM Elements - App Components
const tabsBar = document.getElementById('tabs-bar');
const btnOpenNewTab = document.getElementById('btn-open-new-tab');

const roomLobby = document.getElementById('room-lobby');
const roomInput = document.getElementById('room-input');
const keyInput = document.getElementById('key-input');
const nicknameInput = document.getElementById('nickname-input');
const nickPasswordInput = document.getElementById('nick-password-input');
const btnRandomName = document.getElementById('btn-random-name');
<<<<<<< HEAD
const btnCreateRoom = document.getElementById('btn-create-room');
const btnJoinRoom = document.getElementById('btn-join-room');
=======
const btnJoinCreateRoom = document.getElementById('btn-join-create-room');
>>>>>>> f79f4cf (local state jakedot@petar-vivo)

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

// Sidebar Tabs & Panels
const tabNicks = document.getElementById('tab-nicks');
const tabGallery = document.getElementById('tab-gallery');
const userListSidebar = document.getElementById('user-list-sidebar');
const gallerySidebar = document.getElementById('gallery-sidebar');
const mediaGallery = document.getElementById('media-gallery');

const userList = document.getElementById('user-list');
const chatInput = document.getElementById('chat-input');
const btnSendChat = document.getElementById('btn-send-chat');
const btnAttachFile = document.getElementById('btn-attach-file');
const chatFileInput = document.getElementById('chat-file-input');

const statsStage = document.getElementById('stats-stage');
const btnRefreshStats = document.getElementById('btn-refresh-stats');
const serverStatsContent = document.getElementById('server-stats-content');
const clientStatsContent = document.getElementById('client-stats-content');

<<<<<<< HEAD
=======
// DOM Elements - Data Stage
window.dataStage = document.getElementById('data-stage');
const dataStageTitle = document.getElementById('data-stage-title');
const dataStageModeBadge = document.getElementById('data-stage-mode-badge');
const dataStageOptOutBanner = document.getElementById('data-stage-opt-out-banner');
const dataStageContent = document.getElementById('data-stage-content');
const kpiNodes = document.getElementById('kpi-nodes');
const kpiPeers = document.getElementById('kpi-peers');
const kpiBandwidth = document.getElementById('kpi-bandwidth');
const kpiHealth = document.getElementById('kpi-health');
const btnRefreshData = document.getElementById('btn-refresh-data');
const aiChatHistory = document.getElementById('ai-chat-history');
const aiSuggestions = document.getElementById('ai-suggestions');
const aiChatInput = document.getElementById('ai-chat-input');
const btnSendAiQuery = document.getElementById('btn-send-ai-query');

let activeDataTelemetryTarget = null;
let aiChatAbortController = null;

window.loadDataTelemetry = async function(tabId) {
    const parts = tabId.split('∆');
    activeDataTelemetryTarget = parts[0];
    const subobjectRaw = parts.length > 1 ? parts[1] : 'data';

    dataStageTitle.textContent = `📡 Data View: ${activeDataTelemetryTarget}`;
    dataStageModeBadge.textContent = 'Loading...';
    
    try {
        const res = await fetch(`/api/data.php?target=${encodeURIComponent(activeDataTelemetryTarget)}&subobject=${encodeURIComponent(subobjectRaw)}&client=${encodeURIComponent(myNickname)}`);
        const data = await res.json();
        
        if (!res.ok || data.error) {
            dataStageModeBadge.textContent = 'Restricted';
            dataStageModeBadge.className = 'badge bg-danger';
            dataStageContent.classList.add('hidden');
            dataStageOptOutBanner.classList.remove('hidden');
            if (data.error) {
                dataStageOptOutBanner.innerHTML = `<strong>Access Denied:</strong> ${data.error}`;
            }
            return;
        }
        
        dataStageModeBadge.textContent = 'Live (+d)';
        dataStageModeBadge.className = 'badge bg-success';
        dataStageContent.classList.remove('hidden');
        dataStageOptOutBanner.classList.add('hidden');
        
        // Update KPIs
        kpiNodes.textContent = data.metrics.active_nodes;
        kpiPeers.textContent = data.metrics.peer_mesh_connections;
        kpiBandwidth.textContent = (data.metrics.bandwidth_kbps / 1000).toFixed(1) + ' Mbps';
        kpiHealth.textContent = data.metrics.health_score + '%';
        
    } catch (e) {
        dataStageModeBadge.textContent = 'Error';
    }
};

if (btnRefreshData) {
    btnRefreshData.addEventListener('click', () => {
        if (activeDataTelemetryTarget) window.loadDataTelemetry(activeDataTelemetryTarget + '∆data');
    });
}

async function sendAiDataQuery(queryStr = null) {
    const query = queryStr || aiChatInput.value.trim();
    if (!query || !activeDataTelemetryTarget) return;
    
    aiChatInput.value = '';
    
    const userMsg = document.createElement('div');
    userMsg.className = 'ai-msg user-msg';
    userMsg.style.alignSelf = 'flex-end';
    userMsg.style.background = 'var(--primary-color)';
    userMsg.style.padding = '8px 12px';
    userMsg.style.borderRadius = '12px';
    userMsg.style.color = '#fff';
    userMsg.textContent = query;
    aiChatHistory.appendChild(userMsg);
    aiChatHistory.scrollTop = aiChatHistory.scrollHeight;
    
    const aiMsgContainer = document.createElement('div');
    aiMsgContainer.className = 'ai-msg ai-response';
    aiMsgContainer.style.background = 'var(--card-bg)';
    aiMsgContainer.style.border = '1px solid var(--card-border)';
    aiMsgContainer.style.padding = '10px 14px';
    aiMsgContainer.style.borderRadius = '12px';
    
    const thoughtsDiv = document.createElement('div');
    thoughtsDiv.className = 'ai-thoughts';
    thoughtsDiv.style.fontSize = '0.85rem';
    thoughtsDiv.style.color = 'var(--text-muted)';
    thoughtsDiv.style.fontStyle = 'italic';
    thoughtsDiv.style.marginBottom = '8px';
    
    const responseDiv = document.createElement('div');
    responseDiv.className = 'ai-final-response';
    responseDiv.style.color = 'var(--text-bright)';
    
    aiMsgContainer.appendChild(thoughtsDiv);
    aiMsgContainer.appendChild(responseDiv);
    aiChatHistory.appendChild(aiMsgContainer);
    aiChatHistory.scrollTop = aiChatHistory.scrollHeight;
    
    if (aiChatAbortController) aiChatAbortController.abort();
    aiChatAbortController = new AbortController();
    
    try {
        const res = await fetch('/api/ai-analytics', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ target: activeDataTelemetryTarget, query }),
            signal: aiChatAbortController.signal
        });
        
        const reader = res.body.getReader();
        const decoder = new TextDecoder();
        
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            
            const chunk = decoder.decode(value);
            const lines = chunk.split('\n');
            for (const line of lines) {
                if (line.startsWith('data: ')) {
                    const dataStr = line.substring(6).trim();
                    if (dataStr === '[DONE]') break;
                    if (!dataStr) continue;
                    
                    try {
                        const parsed = JSON.parse(dataStr);
                        if (parsed.type === 'THOUGHT') {
                            thoughtsDiv.innerHTML += `<div>💭 ${parsed.content}</div>`;
                        } else if (parsed.type === 'FINAL_RESPONSE') {
                            // Convert simple markdown-like syntax
                            let html = parsed.content
                                .replace(/\\n/g, '<br>')
                                .replace(/\n/g, '<br>')
                                .replace(/\\*\\*(.*?)\\*\\*/g, '<strong>$1</strong>')
                                .replace(/`(.*?)`/g, '<code>$1</code>');
                            responseDiv.innerHTML = html;
                        } else if (parsed.type === 'SUGGESTION') {
                            aiSuggestions.innerHTML = '';
                            parsed.items.forEach(item => {
                                const btn = document.createElement('button');
                                btn.className = 'btn btn-secondary btn-sm';
                                btn.textContent = item.text;
                                btn.onclick = () => sendAiDataQuery(item.text);
                                aiSuggestions.appendChild(btn);
                            });
                        }
                        aiChatHistory.scrollTop = aiChatHistory.scrollHeight;
                    } catch (e) {}
                }
            }
        }
    } catch (e) {
        if (e.name !== 'AbortError') {
            responseDiv.innerHTML = '<span style="color:var(--danger-color)">Error communicating with AI Data Explorer.</span>';
        }
    }
}

if (btnSendAiQuery) {
    btnSendAiQuery.addEventListener('click', () => sendAiDataQuery());
    aiChatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendAiDataQuery();
        }
    });
}


>>>>>>> f79f4cf (local state jakedot@petar-vivo)
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

// Store active object URLs to prevent memory leaks
const activeMediaUrls = new Set();



function renderTabsNav() {
    tabsBar.innerHTML = '';
    tabsBar.setAttribute('role', 'tablist');
    tabsBar.setAttribute('aria-label', 'IRC Channels');

    for (const [chanId, tab] of Object.entries(openTabs)) {
        const tabEl = document.createElement('div');
        const isActive = chanId === activeTabId;
        tabEl.className = `room-tab ${isActive ? 'active' : ''}`;
        tabEl.setAttribute('role', 'tab');
        tabEl.setAttribute('tabindex', '0');
        tabEl.setAttribute('aria-selected', isActive ? 'true' : 'false');

<<<<<<< HEAD
        const icon = tab.isStats ? '📊 ' : '#';
        const cleanTitle = tab.isStats ? 'Connection Stats' : chanId.replace(/^#/, '');
=======
        const icon = tab.isStats ? '📊 ' : '';
        let displayName = tab.name || tab.alias || chanId;
        if (window.objectNames && window.objectNames[chanId]) displayName = window.objectNames[chanId];
        if (window.objectAliases && window.objectAliases[chanId]) displayName = window.objectAliases[chanId];
        const cleanTitle = tab.isStats ? 'Connection Stats' : displayName;
>>>>>>> f79f4cf (local state jakedot@petar-vivo)

        tabEl.setAttribute('aria-label', `Channel ${cleanTitle}`);
        tabEl.setAttribute('title', `Switch to ${cleanTitle}`);

        tabEl.innerHTML = `<span>${icon}${cleanTitle}</span>`;

        if (tab.unreadCount > 0) {
            const badge = document.createElement('span');
            badge.className = 'unread-badge';
            badge.textContent = tab.unreadCount;
            badge.setAttribute('aria-label', `${tab.unreadCount} unread messages`);
            tabEl.appendChild(badge);
        }

        if (!tab.isStats && Object.keys(openTabs).length > 1) {
            const closeBtn = document.createElement('span');
            closeBtn.className = 'close-tab';
            closeBtn.textContent = '×';
<<<<<<< HEAD
            closeBtn.title = 'Close Channel';
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                closeTab(chanId);
=======
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
            closeBtn.setAttribute('role', 'button');
            closeBtn.setAttribute('tabindex', '0');
            closeBtn.setAttribute('aria-label', `Close channel ${cleanTitle}`);
            closeBtn.setAttribute('title', `Close ${cleanTitle}`);

            const handleClose = (e) => {
                e.stopPropagation();
                closeTab(chanId);
            };

            closeBtn.addEventListener('click', handleClose);
            closeBtn.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handleClose(e);
                }
            });
            tabEl.appendChild(closeBtn);
        }

        tabEl.addEventListener('click', () => switchToTab(chanId));
        tabEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                switchToTab(chanId);
            }
        });
        tabsBar.appendChild(tabEl);
    }
}

function updateTabUI(channelId) {
    const tab = openTabs[channelId];
    if (!tab) return;

    chatChannelTitle.textContent = `💬 ${channelId} (P2P DataChannel Chat)`;
    channelTopicBar.textContent = `Topic: ${tab.topic || 'Welcome to IVC IRC WebRTC!'}`;

    // Update Share link
<<<<<<< HEAD
    let shareUrl = `${window.location.origin}/#${encodeURIComponent(channelId.replace(/^#/, ''))}`;
=======
    let shareUrl = '';
    if (channelId.startsWith('#')) {
        shareUrl = `${window.location.origin}/#${encodeURIComponent(channelId.substring(1))}`;
    } else {
        shareUrl = `${window.location.origin}/${encodeURIComponent(channelId)}`;
    }
    
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    if (tab.key) {
        shareUrl += `?key=${encodeURIComponent(tab.key)}`;
    }
    shareUrlInput.value = shareUrl;
    roomShareSection.classList.remove('hidden');

    // Render Chat Messages
    renderChatMessages(tab);

    // Render User List
    renderUserList(tab);

        // Render Gallery
        renderGallery(tab);

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
<<<<<<< HEAD
            senderTag.textContent = msg.sender || msg.sharerNick || 'Anonymous';
=======
            
            let finalSender = msg.sender || msg.sharerNick || 'Anonymous';
            const senderId = msg.senderId || msg.sharerClientId;
            if (senderId) {
                if (window.objectNames && window.objectNames[senderId]) finalSender = window.objectNames[senderId];
                else if (window.objectAliases && window.objectAliases[senderId]) finalSender = window.objectAliases[senderId];
                else if (tab && tab.peerNicks && tab.peerNicks[senderId]) finalSender = tab.peerNicks[senderId];
            }
            
            senderTag.textContent = finalSender;
>>>>>>> f79f4cf (local state jakedot@petar-vivo)

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

                // Add inline media preview if local blob exists and is media
                if (isReady && localSharedFilesMap[msg.fileId].blob) {
                    const localFile = localSharedFilesMap[msg.fileId];
                    const fileType = localFile.metadata.type || msg.fileType || '';
                    if (fileType.startsWith('image/') || fileType.startsWith('video/')) {
                        const previewDiv = document.createElement('div');
                        previewDiv.className = 'file-preview';

                        const mediaUrl = URL.createObjectURL(localFile.blob);
                        if (fileType.startsWith('image/')) {
                            const img = document.createElement('img');
                            img.src = mediaUrl;
                            previewDiv.appendChild(img);
                        } else if (fileType.startsWith('video/')) {
                            const video = document.createElement('video');
                            video.src = mediaUrl;
                            video.controls = true;
                            previewDiv.appendChild(video);
                        }
                        msgDiv.appendChild(previewDiv);
                    }
                }

            chatMessages.appendChild(msgDiv);
        } else {
            const msgDiv = document.createElement('div');
            msgDiv.className = `chat-msg ${msg.type}`;

            const senderTag = document.createElement('div');
            senderTag.className = 'sender-tag';
<<<<<<< HEAD
            senderTag.textContent = msg.sender;
=======
            
            let finalSender = msg.sender;
            const senderId = msg.senderId;
            if (senderId) {
                if (window.objectNames && window.objectNames[senderId]) finalSender = window.objectNames[senderId];
                else if (window.objectAliases && window.objectAliases[senderId]) finalSender = window.objectAliases[senderId];
                else if (tab && tab.peerNicks && tab.peerNicks[senderId]) finalSender = tab.peerNicks[senderId];
            }
            
            senderTag.textContent = finalSender;
>>>>>>> f79f4cf (local state jakedot@petar-vivo)

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
<<<<<<< HEAD
    selfLi.innerHTML = `<span class="op-tag">@</span> ${myNickname} (You) ${isSelfTalking ? '<span class="talking-dot" title="Speaking"></span>' : ''}`;
=======
    let myDisplayNick = myNickname;
    if (window.objectAliases && window.objectAliases[myClientId]) myDisplayNick = window.objectAliases[myClientId];
    selfLi.innerHTML = `<span class="op-tag">@</span> ${myDisplayNick} (You) ${isSelfTalking ? '<span class="talking-dot" title="Speaking"></span>' : ''}`;
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    userList.appendChild(selfLi);

    // Add remote peers
    tab.peers.forEach(peerId => {
        const li = document.createElement('li');
        li.className = 'user-item';
<<<<<<< HEAD
        const nick = tab.peerNicks[peerId] || peerId;
=======
        let nick = tab.peerNicks[peerId] || peerId;
        if (window.objectNames && window.objectNames[peerId]) nick = window.objectNames[peerId];
        if (window.objectAliases && window.objectAliases[peerId]) nick = window.objectAliases[peerId];
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
        const isPeerTalking = !!tab.speakingStates[peerId];
        li.innerHTML = `<span>👤</span> ${nick} ${isPeerTalking ? '<span class="talking-dot" title="Speaking"></span>' : ''}`;
        userList.appendChild(li);
    });
}

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
function renderGallery(tab) {
    if (!mediaGallery) return;

    // Revoke previously created object URLs
    activeMediaUrls.forEach(url => URL.revokeObjectURL(url));
    activeMediaUrls.clear();

    mediaGallery.innerHTML = '';

    const mediaMessages = tab.messages.filter(msg => {
        const fileType = msg.fileType || '';
        return msg.type === 'file' && (fileType.startsWith('image/') || fileType.startsWith('video/'));
    });

    if (mediaMessages.length === 0) {
        mediaGallery.innerHTML = '<div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; padding: 10px;">No media shared yet.</div>';
        return;
    }

    mediaMessages.forEach(msg => {
        const fileType = msg.fileType || '';
        const itemDiv = document.createElement('div');
        itemDiv.className = 'gallery-item';

        const isReady = !!localSharedFilesMap[msg.fileId];

        if (isReady && localSharedFilesMap[msg.fileId].blob) {
            const localFile = localSharedFilesMap[msg.fileId];
            const mediaUrl = URL.createObjectURL(localFile.blob);
            activeMediaUrls.add(mediaUrl);

            if (fileType.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = mediaUrl;
                itemDiv.appendChild(img);
            } else if (fileType.startsWith('video/')) {
                const video = document.createElement('video');
                video.src = mediaUrl;
                itemDiv.appendChild(video);

                const playIcon = document.createElement('div');
                playIcon.className = 'play-icon';
                playIcon.innerHTML = '▶';
                itemDiv.appendChild(playIcon);
            }

            itemDiv.addEventListener('click', () => {
                const a = document.createElement('a');
                a.href = mediaUrl;
                a.download = msg.fileName || 'media';
                a.click();
            });
        } else {
            const placeholderIcon = document.createElement('div');
            placeholderIcon.style.fontSize = '2rem';
            placeholderIcon.innerHTML = fileType.startsWith('image/') ? '🖼️' : '🎥';
            itemDiv.appendChild(placeholderIcon);

            const statusText = document.createElement('div');
            statusText.style.position = 'absolute';
            statusText.style.bottom = '4px';
            statusText.style.fontSize = '0.65rem';
            statusText.style.background = 'rgba(0,0,0,0.6)';
            statusText.style.padding = '2px 4px';
            statusText.style.borderRadius = '4px';
            statusText.innerHTML = msg.cloudLink ? '☁️ Cloud' : '📥 Click to DL';
            itemDiv.appendChild(statusText);

            itemDiv.addEventListener('click', () => {
                handleFileDownloadOrRequest(tab.id, msg.fileId, msg.sharerClientId, msg.fileName, msg.fileType, msg.cloudLink, null);
            });
        }

        mediaGallery.appendChild(itemDiv);
    });
}
