'use strict';

// Initialize Nickname input
nicknameInput.value = myNickname;

btnRandomName.addEventListener('click', () => {
    myNickname = generateAnonymousName();
    nicknameInput.value = myNickname;
});

// Initialize Theme System & Tabs on startup
initThemeSystem();

async function initApp() {
    try {
        const res = await fetch('/api/config.php');
        const config = await res.json();
        if (config.csrfToken) window.FORTRESS_CSRF_TOKEN = config.csrfToken;
        if (config.urlRoom) window.FORTRESS_PRELOAD_ROOM = config.urlRoom;
    } catch (e) {
        console.error("Failed to load initial config", e);
    }

    // Sidebar Tabs Toggle
    if (tabNicks && tabGallery) {
        tabNicks.addEventListener('click', () => {
            tabNicks.classList.add('active');
            tabGallery.classList.remove('active');
            userListSidebar.classList.remove('hidden');
            gallerySidebar.classList.add('hidden');
        });
        tabGallery.addEventListener('click', () => {
            tabGallery.classList.add('active');
            tabNicks.classList.remove('active');
            gallerySidebar.classList.remove('hidden');
            userListSidebar.classList.add('hidden');

            // Re-render gallery when tab is switched to ensure it's up to date
            if (activeTabId && openTabs[activeTabId]) {
                renderGallery(openTabs[activeTabId]);
            }
        });
    }

    const initialChan = parseChannelFromUrl();
    openTab(initialChan, false);
    openTab('#stats', false); // Always include #stats room tab
    switchToTab(initialChan);

    // Set the room input value if there's a preloaded room
    if (window.FORTRESS_PRELOAD_ROOM) {
        roomInput.value = window.FORTRESS_PRELOAD_ROOM;
    }
}

document.addEventListener('DOMContentLoaded', initApp);

// Navigation Listener (handles both hash and path changes from browser history)
window.addEventListener('popstate', () => {
    const chan = parseChannelFromUrl() || '#';
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

btnJoinCreateRoom.addEventListener('click', async () => {
    let chan = roomInput.value.trim();
    if (!chan) {
        chan = '#room-' + Math.random().toString(36).substring(2, 8);
        roomInput.value = chan;
    } else {
        chan = normalizeChannel(chan);
    }
    myNickname = nicknameInput.value.trim() || myNickname;
    const chanKey = keyInput.value.trim();
    await openTab(chan, true, chanKey);
    performIrcServiceCommands(chan, nickPasswordInput.value, chanKey, !!chanKey);
});

btnCopyLink.addEventListener('click', async () => {
    shareUrlInput.select();
    try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(shareUrlInput.value);
        } else {
            document.execCommand('copy');
        }
        btnCopyLink.textContent = '✅ Copied!';
        btnCopyLink.setAttribute('aria-label', 'Channel link copied to clipboard');
        setTimeout(() => {
            btnCopyLink.textContent = '📋 Copy Channel Link';
            btnCopyLink.setAttribute('aria-label', 'Copy Channel Link');
        }, 2000);
    } catch (err) {
        btnCopyLink.textContent = '❌ Failed to copy';
        btnCopyLink.setAttribute('aria-label', 'Failed to copy channel link');
        setTimeout(() => {
            btnCopyLink.textContent = '📋 Copy Channel Link';
            btnCopyLink.setAttribute('aria-label', 'Copy Channel Link');
        }, 2000);
    }
});

btnQrLink.addEventListener('click', () => {
    if (qrCodeContainer.style.display === 'block') {
        qrCodeContainer.style.display = 'none';
        btnQrLink.textContent = '📱 QR Code';
    } else {
        qrCodeContainer.innerHTML = '';
        let ivcUri = '';
        if (typeof formatObjectUri === 'function' && activeTabId) {
            ivcUri = formatObjectUri(activeTabId, window.location.host);
        } else {
            ivcUri = shareUrlInput.value;
        }
        new QRCode(qrCodeContainer, {
            text: ivcUri,
            width: 256,
            height: 256,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
        qrCodeContainer.style.display = 'block';
        btnQrLink.textContent = '📱 Hide QR';
    }
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
