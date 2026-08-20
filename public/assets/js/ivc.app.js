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
            tabNicks.setAttribute('aria-selected', 'true');
            tabGallery.classList.remove('active');
            tabGallery.setAttribute('aria-selected', 'false');
            userListSidebar.classList.remove('hidden');
            gallerySidebar.classList.add('hidden');
        });
        tabGallery.addEventListener('click', () => {
            tabGallery.classList.add('active');
            tabGallery.setAttribute('aria-selected', 'true');
            tabNicks.classList.remove('active');
            tabNicks.setAttribute('aria-selected', 'false');
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

btnCreateRoom.addEventListener('click', async () => {
    const randChan = '#room-' + Math.random().toString(36).substring(2, 8);
    roomInput.value = randChan;
    myNickname = nicknameInput.value.trim() || myNickname;
    await openTab(randChan, true, keyInput.value.trim());
    performIrcServiceCommands(randChan, nickPasswordInput.value, keyInput.value.trim(), true);
});

btnJoinRoom.addEventListener('click', async () => {
    const chan = normalizeChannel(roomInput.value.trim());
    if (!chan) {
        alert('Please enter a channel name (e.g. #general).');
        return;
    }
    myNickname = nicknameInput.value.trim() || myNickname;
    await openTab(chan, true, keyInput.value.trim());
    performIrcServiceCommands(chan, nickPasswordInput.value, keyInput.value.trim(), false);
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
