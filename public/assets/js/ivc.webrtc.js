'use strict';

function parseChannelFromUrl() {
    let hash = window.location.hash.trim();
    if (hash.startsWith('#')) {
        return normalizeChannel(hash);
    }
    const params = new URLSearchParams(window.location.search);
    if (params.has('uri')) {
        const parsed = parseServerUri(params.get('uri'));
        if (parsed && parsed.channel) return parsed.channel;
    }
    if (params.has('room')) {
        return normalizeChannel(params.get('room'));
    }
    const pathSegments = window.location.pathname.split('/').filter(p => p.length > 0 && !p.includes('.'));
    if (pathSegments.length > 0 && pathSegments[0] !== 'api') {
        return normalizeChannel(pathSegments[0]);
    }
    if (window.FORTRESS_PRELOAD_ROOM) {
        return normalizeChannel(window.FORTRESS_PRELOAD_ROOM);
    }
    return '#lobby';
}

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
            let chatText = signal.message || signal.text;
            if (typeof decompressTextMessage === 'function') {
                chatText = decompressTextMessage(chatText);
            }
            addMessageToTab(channelId, {
                sender: senderName,
                text: chatText,
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
                        renderGallery(tab);
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

function addMessageToTab(channelId, msg) {
    const tab = openTabs[channelId];
    if (!tab) return;

    tab.messages.push(msg);

    if (activeTabId === channelId) {
        renderChatMessages(tab);
        if (msg.type === 'file') {
            renderGallery(tab);
        }
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
        throw new Error('E2EE Failed');
    }
}

async function decryptMetadataE2EE(encryptedBase64, channelId, channelPasskey = '') {
    try {
        const combined = Uint8Array.from(atob(encryptedBase64), c => c.charCodeAt(0));
        if (combined.length <= 12) {
            throw new Error('E2EE Failed');
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
        console.error('Metadata decryption error:', err);
        return null;
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
                        renderGallery(tab);
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

async function handleChatSubmit() {
    const text = chatInput.value.trim();
    if (!text || !activeTabId || activeTabId === '#stats') return;

    const tab = openTabs[activeTabId];
    chatInput.value = '';

    // Check if message is /connect command
    if (text.startsWith('/connect')) {
        addMessageToTab(activeTabId, {
            sender: myNickname,
            text: text,
            type: 'self'
        });

        const parts = text.split(/\s+/);
        const uri = parts[1] || '';

        if (!uri) {
            const keys = Object.keys(connectedServers);
            let resp = 'SERVERSERV: Usage: /connect <URI> (Supported protocols: https://, ivc://, irc://)';
            if (keys.length > 0) {
                const listStr = keys.map(k => `${connectedServers[k].protocol}://${k} (Channel: ${connectedServers[k].channel})`).join(', ');
                resp += ` | Active Connections: [${listStr}]`;
            }
            addMessageToTab(activeTabId, {
                sender: 'SERVERSERV',
                text: resp,
                type: 'bot'
            });
            return;
        }

        const parsed = parseServerUri(uri);
        if (!parsed) {
            addMessageToTab(activeTabId, {
                sender: 'SERVERSERV',
                text: 'SERVERSERV: Invalid URI format. Supported protocols are https://, ivc://, and irc:// (e.g. https://server.com/#channel)',
                type: 'bot'
            });
            return;
        }

        connectedServers[parsed.serverKey] = {
            host: parsed.host,
            port: parsed.port,
            protocol: parsed.protocol,
            channel: parsed.channel,
            uri: parsed.uri,
            connectedAt: Date.now()
        };

        openTab(parsed.channel, true);

        addMessageToTab(parsed.channel, {
            sender: 'SERVERSERV',
            text: `SERVERSERV: Connected to server '${parsed.host}:${parsed.port}' via ${parsed.protocol} (Channel: ${parsed.channel}).`,
            type: 'bot'
        });
        return;
    }

    // Check if message is /disconnect command
    if (text.startsWith('/disconnect')) {
        addMessageToTab(activeTabId, {
            sender: myNickname,
            text: text,
            type: 'self'
        });

        const parts = text.split(/\s+/);
        const target = parts[1] || '';

        if (target) {
            const parsed = parseServerUri(target);
            const key = parsed ? parsed.serverKey : target;
            if (connectedServers[key]) {
                delete connectedServers[key];
            }
            addMessageToTab(activeTabId, {
                sender: 'SERVERSERV',
                text: `SERVERSERV: Disconnected from server '${target}'.`,
                type: 'bot'
            });
        } else {
            Object.keys(connectedServers).forEach(k => delete connectedServers[k]);
            addMessageToTab(activeTabId, {
                sender: 'SERVERSERV',
                text: 'SERVERSERV: Disconnected from active server connection.',
                type: 'bot'
            });
        }
        return;
    }

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
                return;
            }
        } catch (err) {
            console.error('Error sending IRC command:', err);
        }
    }

    // Bit-compress message for WebRTC transmission
    const compressedPayload = (typeof compressTextMessage === 'function') ? compressTextMessage(text) : text;

    // Broadcast bit-compressed message to all peer DataChannels in room
    Object.values(tab.dataChannels).forEach(dc => {
        if (dc && dc.readyState === 'open') {
            dc.send(compressedPayload);
        }
    });

    // Transmit bit-compressed payload via signaling fallback
    sendSignal(activeTabId, {
        type: 'chat',
        room: activeTabId,
        client: myClientId,
        nickname: myNickname,
        message: compressedPayload
    });

    addMessageToTab(activeTabId, {
        sender: myNickname,
        text: text,
        type: 'self'
    });
}

async function sendIrcCommand(channel, text) {
    try {
        const res = await fetch('/api/irc.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.FORTRESS_CSRF_TOKEN || ''
            },
            body: JSON.stringify({
                sender: myNickname,
                channel: channel,
                text: text,
                broadcast: false
            })
        });
        return await res.json();
    } catch (err) {
        console.error('Error sending IRC command:', err);
        return null;
    }
}

async function performIrcServiceCommands(channel, password, roomKey, isCreate) {
    if (password) {
        const regRes = await sendIrcCommand('#lobby', `/msg NAMESERV REGISTER ${password}`);
        if (regRes && regRes.status === 'error' && regRes.response.includes('already registered')) {
            await sendIrcCommand('#lobby', `/msg NAMESERV IDENTIFY ${password}`);
        }
    }
    if (isCreate) {
        await sendIrcCommand(channel, '/msg CHANSERV REGISTER');
        if (roomKey) {
            await sendIrcCommand(channel, `/mode ${channel} +k ${roomKey}`);
        }
    }
}
function setupDataChannel(channelId, peerId, channel) {
    const tab = openTabs[channelId];
    if (!tab) return;

    channel.onmessage = async (event) => {
        let rawMessage = event.data;
        if (typeof decompressTextMessage === 'function') {
            rawMessage = decompressTextMessage(rawMessage);
        }

        if (typeof event.data === 'string' && event.data.trim().startsWith('{')) {
            try {
                const parsed = JSON.parse(event.data);
                if (parsed.__bc) {
                    // Handled by decompressTextMessage above
                } else
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
                            renderGallery(tab);
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
            text: rawMessage,
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


async function sendIrcCommand(channel, text) {
    try {
        const res = await fetch('/api/irc.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.FORTRESS_CSRF_TOKEN || ''
            },
            body: JSON.stringify({
                sender: myNickname,
                channel: channel,
                text: text,
                broadcast: false
            })
        });
        return await res.json();
    } catch (err) {
        console.error('Error sending IRC command:', err);
        return null;
    }
}

async function performIrcServiceCommands(channelId, nickPassword, chanKey, isCreate) {
    if (nickPassword) {
        let res = await sendIrcCommand('NICKSERV', `IDENTIFY ${nickPassword}`);
        if (res && res.response) {
            if (res.response.includes('is not registered')) {
                res = await sendIrcCommand('NICKSERV', `REGISTER ${nickPassword}`);
            }
            addMessageToTab(channelId, {
                sender: 'NICKSERV',
                text: res.response,
                type: 'bot'
            });
        }
    }
    if (isCreate && chanKey) {
        let res = await sendIrcCommand('CHANSERV', `REGISTER ${channelId} ${chanKey}`);
        if (res && res.response) {
            addMessageToTab(channelId, {
                sender: 'CHANSERV',
                text: res.response,
                type: 'bot'
            });
        }
    }
}