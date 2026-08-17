/**
 * Fortress / IVC WebRTC Client Implementation
 * Supporting Anonymous Joining, Room URL scheme (<site>/<room>), Optional Passkeys, Media Streams, Screen Share, DataChannel Chat, and SSE Signaling
 */

(() => {
    'use strict';

    // Anonymous Name Generator Lists
    const ADJECTIVES = ['Crypto', 'Cyber', 'Silent', 'Shadow', 'Fortress', 'Quantum', 'Stealth', 'Hyper', 'Neon', 'Matrix', 'Ghost', 'Vector'];
    const ANIMALS = ['Fox', 'Owl', 'Wolf', 'Falcon', 'Panther', 'Eagle', 'Hawk', 'Raven', 'Panda', 'Tiger', 'Viper', 'Lynx'];

    function generateAnonymousName() {
        const adj = ADJECTIVES[Math.floor(Math.random() * ADJECTIVES.length)];
        const anim = ANIMALS[Math.floor(Math.random() * ANIMALS.length)];
        const num = Math.floor(100 + Math.random() * 900);
        return `${adj} ${anim} #${num}`;
    }

    // State Variables
    let localStream = null;
    let peerConnection = null;
    let dataChannel = null;
    let sseSource = null;
    let currentRoom = null;
    let currentRoomKey = null;
    let myNickname = generateAnonymousName();
    let remoteNickname = 'Remote Peer';
    let myClientId = 'peer-' + Math.random().toString(36).substring(2, 11);
    let isAudioMuted = false;
    let isVideoMuted = false;
    let isScreenSharing = false;

    // WebRTC STUN Server configuration
    const rtcConfig = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' }
        ]
    };

    // DOM Elements
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
    const chatPanel = document.getElementById('chat-panel');
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

    const chatMessages = document.getElementById('chat-messages');
    const chatInput = document.getElementById('chat-input');
    const btnSendChat = document.getElementById('btn-send-chat');

    // Init Nickname
    nicknameInput.value = myNickname;

    btnRandomName.addEventListener('click', () => {
        myNickname = generateAnonymousName();
        nicknameInput.value = myNickname;
    });

    // Auto-fill room and key from URL if present (<site>/<room> or ?room=...)
    const urlParams = new URLSearchParams(window.location.search);
    const initialRoom = window.FORTRESS_PRELOAD_ROOM || urlParams.get('room');
    const initialKey = urlParams.get('key');
    if (initialRoom) {
        roomInput.value = initialRoom;
    }
    if (initialKey) {
        keyInput.value = initialKey;
    }

    // Event Listeners
    btnCreateRoom.addEventListener('click', () => {
        const randomRoom = 'room-' + Math.random().toString(36).substring(2, 8);
        roomInput.value = randomRoom;
        startRoomSession(randomRoom, keyInput.value.trim());
    });

    btnJoinRoom.addEventListener('click', () => {
        const roomId = roomInput.value.trim();
        const key = keyInput.value.trim();
        if (!roomId) {
            alert('Please enter or create a room ID.');
            return;
        }
        startRoomSession(roomId, key);
    });

    btnCopyLink.addEventListener('click', () => {
        shareUrlInput.select();
        navigator.clipboard.writeText(shareUrlInput.value);
        btnCopyLink.textContent = '✅ Copied!';
        setTimeout(() => { btnCopyLink.textContent = '📋 Copy Room Link'; }, 2000);
    });

    btnToggleMic.addEventListener('click', toggleMicrophone);
    btnToggleCam.addEventListener('click', toggleCamera);
    btnShareScreen.addEventListener('click', toggleScreenShare);
    btnLeaveCall.addEventListener('click', leaveCall);

    btnSendChat.addEventListener('click', sendChatMessage);
    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendChatMessage();
    });

    /**
     * Start WebRTC Room Session
     */
    async function startRoomSession(roomId, key) {
        currentRoom = roomId;
        currentRoomKey = key || '';
        myNickname = nicknameInput.value.trim() || generateAnonymousName();
        localNameLabel.textContent = `${myNickname} (You)`;

        updateShareLink(roomId, currentRoomKey);

        try {
            // Get local audio and video media stream
            localStream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: true
            });
            localVideo.srcObject = localStream;
        } catch (err) {
            console.warn('Camera/Mic permission warning:', err);
            alert('Could not access camera/microphone. Continuing with signaling/chat session.');
        }

        // Show Video Stage & Chat
        videoStage.classList.remove('hidden');
        chatPanel.classList.remove('hidden');
        roomShareSection.classList.remove('hidden');

        // Update browser URL bar cleanly to <site>/<room>
        if (window.history && window.history.pushState) {
            const newUrl = currentRoomKey
                ? `${window.location.origin}/${encodeURIComponent(roomId)}?key=${encodeURIComponent(currentRoomKey)}`
                : `${window.location.origin}/${encodeURIComponent(roomId)}`;
            window.history.pushState({ room: roomId }, '', newUrl);
        }

        // Init SSE Signaling Connection
        initSignaling(roomId);
    }

    /**
     * Initialize Signaling Server via SSE
     */
    function initSignaling(roomId) {
        const sseUrl = `/api/signal.php?room=${encodeURIComponent(roomId)}&client=${encodeURIComponent(myClientId)}&mode=sse`;
        sseSource = new EventSource(sseUrl);

        sseSource.onmessage = async (event) => {
            if (!event.data || event.data.trim() === '') return;
            try {
                const signal = JSON.parse(event.data);
                handleIncomingSignal(signal);
            } catch (err) {
                console.error('Error parsing signaling message:', err);
            }
        };

        sseSource.onerror = () => {
            console.warn('Signaling SSE reconnecting...');
        };

        // Send initial join signal
        sendSignal({
            type: 'join',
            room: currentRoom,
            client: myClientId,
            nickname: myNickname,
            key: currentRoomKey
        });
    }

    /**
     * Handle WebRTC Negotiation Signaling Messages
     */
    async function handleIncomingSignal(signal) {
        if (signal.sender === myClientId) return;

        // If a room key is configured, strictly verify key match
        if (currentRoomKey && (signal.key || '') !== currentRoomKey) {
            console.warn('Received signal with mismatched room key');
            return;
        }

        switch (signal.type) {
            case 'peer-joined':
                remoteNickname = signal.nickname || 'Remote Peer';
                remoteNameLabel.textContent = remoteNickname;
                addSystemMessage(`Anonymous peer "${remoteNickname}" joined the room.`);
                // Create PeerConnection & Offer
                createPeerConnection();
                createOffer();
                break;

            case 'offer':
                remoteNickname = signal.nickname || remoteNickname;
                remoteNameLabel.textContent = remoteNickname;
                createPeerConnection();
                await peerConnection.setRemoteDescription(new RTCSessionDescription(signal.sdp));
                const answer = await peerConnection.createAnswer();
                await peerConnection.setLocalDescription(answer);
                sendSignal({
                    type: 'answer',
                    room: currentRoom,
                    client: myClientId,
                    nickname: myNickname,
                    key: currentRoomKey,
                    sdp: answer
                });
                break;

            case 'answer':
                if (peerConnection) {
                    await peerConnection.setRemoteDescription(new RTCSessionDescription(signal.sdp));
                }
                break;

            case 'ice-candidate':
                if (peerConnection && signal.candidate) {
                    try {
                        await peerConnection.addIceCandidate(new RTCIceCandidate(signal.candidate));
                    } catch (e) {
                        console.error('Error adding ICE candidate:', e);
                    }
                }
                break;

            case 'peer-left':
                addSystemMessage(`Peer "${remoteNickname}" disconnected.`);
                resetRemoteVideo();
                break;
        }
    }

    /**
     * Send signaling payload to PHP backend
     */
    async function sendSignal(payload) {
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
            console.error('Failed to dispatch signal:', err);
        }
    }

    /**
     * Create RTCPeerConnection and bind event handlers
     */
    function createPeerConnection() {
        if (peerConnection) return;

        peerConnection = new RTCPeerConnection(rtcConfig);

        // Add local tracks to peer connection
        if (localStream) {
            localStream.getTracks().forEach(track => {
                peerConnection.addTrack(track, localStream);
            });
        }

        // Handle remote tracks
        peerConnection.ontrack = (event) => {
            if (event.streams && event.streams[0]) {
                remoteVideo.srcObject = event.streams[0];
                remotePlaceholder.classList.add('hidden');
                remoteStatusText.textContent = 'Connected';
            }
        };

        // ICE candidate callback
        peerConnection.onicecandidate = (event) => {
            if (event.candidate) {
                sendSignal({
                    type: 'ice-candidate',
                    room: currentRoom,
                    client: myClientId,
                    key: currentRoomKey,
                    candidate: event.candidate
                });
            }
        };

        // WebRTC DataChannel setup for E2E P2P Chat
        dataChannel = peerConnection.createDataChannel('fortress-chat');
        setupDataChannel(dataChannel);

        peerConnection.ondatachannel = (event) => {
            setupDataChannel(event.channel);
        };
    }

    /**
     * Create SDP Offer
     */
    async function createOffer() {
        if (!peerConnection) return;
        try {
            const offer = await peerConnection.createOffer();
            await peerConnection.setLocalDescription(offer);
            sendSignal({
                type: 'offer',
                room: currentRoom,
                client: myClientId,
                nickname: myNickname,
                key: currentRoomKey,
                sdp: offer
            });
        } catch (err) {
            console.error('Error creating offer:', err);
        }
    }

    /**
     * Bind DataChannel event listeners
     */
    function setupDataChannel(channel) {
        channel.onmessage = (event) => {
            addChatMessage(event.data, remoteNickname, 'peer');
        };
        channel.onopen = () => {
            addSystemMessage('Encrypted DataChannel connected.');
        };
    }

    /**
     * Send encrypted text via DataChannel
     */
    function sendChatMessage() {
        const text = chatInput.value.trim();
        if (!text) return;

        if (dataChannel && dataChannel.readyState === 'open') {
            dataChannel.send(text);
            addChatMessage(text, myNickname, 'self');
            chatInput.value = '';
        } else {
            addSystemMessage('DataChannel not connected yet. Message not sent.');
        }
    }

    function addChatMessage(msg, sender, type) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `chat-msg ${type}`;

        const senderTag = document.createElement('div');
        senderTag.className = 'sender-tag';
        senderTag.textContent = sender;

        const content = document.createElement('div');
        content.textContent = msg;

        msgDiv.appendChild(senderTag);
        msgDiv.appendChild(content);

        chatMessages.appendChild(msgDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function addSystemMessage(msg) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'system-message';
        msgDiv.textContent = msg;
        chatMessages.appendChild(msgDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    /**
     * Media Controls Toggles
     */
    function toggleMicrophone() {
        if (!localStream) return;
        const audioTrack = localStream.getAudioTracks()[0];
        if (audioTrack) {
            isAudioMuted = !isAudioMuted;
            audioTrack.enabled = !isAudioMuted;
            btnToggleMic.classList.toggle('off', isAudioMuted);
            btnToggleMic.querySelector('.icon').textContent = isAudioMuted ? '🔇' : '🎙️';
        }
    }

    function toggleCamera() {
        if (!localStream) return;
        const videoTrack = localStream.getVideoTracks()[0];
        if (videoTrack) {
            isVideoMuted = !isVideoMuted;
            videoTrack.enabled = !isVideoMuted;
            btnToggleCam.classList.toggle('off', isVideoMuted);
            btnToggleCam.querySelector('.icon').textContent = isVideoMuted ? '📷' : '📹';
        }
    }

    async function toggleScreenShare() {
        if (!peerConnection) return;

        if (!isScreenSharing) {
            try {
                const screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
                const screenTrack = screenStream.getVideoTracks()[0];

                const sender = peerConnection.getSenders().find(s => s.track && s.track.kind === 'video');
                if (sender) {
                    sender.replaceTrack(screenTrack);
                }

                localVideo.srcObject = screenStream;
                isScreenSharing = true;
                btnShareScreen.classList.add('off');

                screenTrack.onended = () => {
                    stopScreenSharing();
                };
            } catch (err) {
                console.warn('Screen sharing cancelled or failed:', err);
            }
        } else {
            stopScreenSharing();
        }
    }

    function stopScreenSharing() {
        if (!isScreenSharing || !localStream) return;
        const videoTrack = localStream.getVideoTracks()[0];
        const sender = peerConnection.getSenders().find(s => s.track && s.track.kind === 'video');
        if (sender && videoTrack) {
            sender.replaceTrack(videoTrack);
        }
        localVideo.srcObject = localStream;
        isScreenSharing = false;
        btnShareScreen.classList.remove('off');
    }

    function leaveCall() {
        if (currentRoom) {
            sendSignal({ type: 'leave', room: currentRoom, client: myClientId, key: currentRoomKey });
        }
        if (sseSource) sseSource.close();
        if (peerConnection) peerConnection.close();
        if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
        }

        videoStage.classList.add('hidden');
        chatPanel.classList.add('hidden');
        roomShareSection.classList.add('hidden');
    }

    function resetRemoteVideo() {
        remoteVideo.srcObject = null;
        remotePlaceholder.classList.remove('hidden');
        remoteStatusText.textContent = 'Peer disconnected. Waiting for peer...';
        if (peerConnection) {
            peerConnection.close();
            peerConnection = null;
        }
    }

    function updateShareLink(roomId, key) {
        let fullUrl = `${window.location.origin}/${encodeURIComponent(roomId)}`;
        if (key) {
            fullUrl += `?key=${encodeURIComponent(key)}`;
        }
        shareUrlInput.value = fullUrl;
    }
})();
