'use strict';

/**
 * Fortress / IVC WebRTC Client & Multi-Tab IRC Infrastructure
 * High-Availability Multi-Peer WebRTC Mesh with Audio Speaking Detection & Talking-User First Sorting.
 * Supports #channel hash navigation, multi-tab room sessions, #stats connection stats, NAMESERV/CHANSERV integration,
 * server management with /connect and /disconnect commands, and terminal/multi-theme support.
 */

// Theme Management Constants
const STORAGE_ACTIVE_THEME = 'ivc_theme_active';
const STORAGE_CUSTOM_THEMES = 'ivc_custom_themes';
const BUILTIN_THEMES = ['dark', 'light', 'halloween', 'console', 'christmas'];

// Server Management Tracking
const connectedServers = {};

function parseServerUri(uri) {
    if (!uri) return null;
    uri = uri.trim();
    const match = uri.match(/^(https|ivc|irc):\/\/([^\/:#?]+)(?::(\d+))?(?:[\/#](.*))?$/i);
    if (!match) return null;

    const protocol = match[1].toUpperCase();
    const host = match[2].toLowerCase();
    const defaultPorts = { HTTPS: 443, IVC: 8080, IRC: 6667 };
    const port = match[3] ? parseInt(match[3], 10) : (defaultPorts[protocol] || 443);
    let channelRaw = match[4] || '#lobby';

    if (channelRaw.includes('#')) {
        channelRaw = '#' + channelRaw.split('#').pop();
    } else if (channelRaw && !channelRaw.startsWith('#')) {
        channelRaw = '#' + channelRaw;
    }

    const channel = normalizeChannel(channelRaw);

    return {
        protocol,
        host,
        port,
        channel,
        serverKey: `${host}:${port}`,
        uri
    };
}

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
}

// WebRTC STUN Server configuration
const rtcConfig = {
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' }
    ]
};

const localSharedFilesMap = {};
