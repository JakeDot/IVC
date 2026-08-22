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

function parseModeFlagsJS(modeStr) {
    if (!modeStr) return {};
    return {
        m: modeStr.includes('m'),
        v: modeStr.includes('v'),
        V: modeStr.includes('V'),
        o: modeStr.includes('o'),
        O: modeStr.includes('O'),
        a: modeStr.includes('a'),
        A: modeStr.includes('A'),
        e: modeStr.includes('e'),
        d: modeStr.includes('d'),
        k: modeStr.includes('k'),
        r: modeStr.includes('r') || modeStr.includes('R'),
        R: modeStr.includes('r') || modeStr.includes('R'),
        i: modeStr.includes('i') || modeStr.includes('I'),
        I: modeStr.includes('i') || modeStr.includes('I'),
        s: modeStr.includes('s') || modeStr.includes('S'),
        n: modeStr.includes('n'),
        N: modeStr.includes('N'),
        t: modeStr.includes('t'),
        raw: modeStr.includes('raw'),
        deltaModes: modeStr.includes('Δmodes') || modeStr.includes('deltamodes') || modeStr.includes('∆')
    };
}

function parseSubobjects(input) {
    if (!input) {
        return { baseTarget: '', subobjects: [], props: {}, events: {} };
    }
    input = String(input).trim();

    let firstSubPos = null;
    const match = input.match(/[§∆Δ]/u);
    if (match) {
        firstSubPos = match.index;
    }

    if (firstSubPos === null) {
        return { baseTarget: input, subobjects: [], props: {}, events: {} };
    }

    const baseTarget = input.substring(0, firstSubPos);
    const subStr = input.substring(firstSubPos);

    const tokens = subStr.split(/([§∆Δ])/u).filter(Boolean);

    const subobjects = [];
    const props = {};
    const events = {};

    for (let i = 0; i < tokens.length; i += 2) {
        const symbol = tokens[i];
        const segment = tokens[i + 1] || '';

        if (symbol !== '§' && symbol !== '∆' && symbol !== 'Δ') continue;

        const type = (symbol === '§') ? 'property' : 'event';
        let name = '';
        let value = 'true';
        let modes = '';

        let match;
        if ((match = segment.match(/^([a-zA-Z0-9_\-\.\/]+)(?:\+([a-zA-Z0-9_]+))?=(.*?)([\+\-][a-zA-Z0-9_\-\+]+)?$/))) {
            name = match[1];
            modes = match[2] ? '+' + match[2] : (match[4] || '');
            value = match[3];
        } else if ((match = segment.match(/^([a-zA-Z0-9_\-\.\/]+)=(.*?)([\+\-][a-zA-Z0-9_\-\+]+)?$/))) {
            name = match[1];
            value = match[2];
            modes = match[3] || '';
        } else if ((match = segment.match(/^([a-zA-Z0-9_\-\.\/]+)([\+\-][a-zA-Z0-9_\-\+]+)?$/))) {
            name = match[1];
            modes = match[2] || '';
            value = 'true';
        } else {
            name = segment;
        }

        const modeFlags = parseModeFlagsJS(modes);

        const subItem = {
            symbol,
            type,
            name,
            value,
            modes,
            modeFlags
        };

        subobjects.push(subItem);

        const dictItem = {
            value,
            modes,
            modeFlags
        };

        if (type === 'property') {
            props[name] = dictItem;
        } else {
            events[name] = dictItem;
        }
    }

    return { baseTarget, subobjects, props, events };
}

function formatObjectUri(objData, host = '$me') {
    if (!objData) return `ivc://${host}/object`;

    if (typeof objData === 'string') {
        objData = objData.trim();
        if (objData.startsWith('ivc://')) return objData;
        if (objData.includes(' ') || objData.includes(':')) {
            const clean = objData.replace(/^\{|\}$/g, '');
            const parts = clean.split(/\s+/);
            const objName = parts[0];
            const kv = parts.slice(1).join(' ');
            if (kv) {
                const kvParts = kv.split(':', 2);
                const propName = kvParts[0].trim();
                const propVal = (kvParts[1] !== undefined ? kvParts[1] : 'true').trim();
                return `ivc://${host}/${objName}§${propName}=${propVal}`;
            }
            return `ivc://${host}/${objName}`;
        }
        return `ivc://${host}/${objData}`;
    }

    let baseObject = objData.object || objData.name || null;
    let props = objData;

    if (!baseObject) {
        const keys = Object.keys(objData);
        if (keys.length === 1 && typeof objData[keys[0]] === 'object' && objData[keys[0]] !== null) {
            baseObject = keys[0];
            props = objData[keys[0]];
        } else {
            baseObject = 'object';
        }
    }

    let subStr = '';
    const reservedKeys = new Set(['object', 'name', 'host', 'protocol', 'scheme', 'subobjects', 'props', 'events', 'uri', 'asObject']);

    for (const [k, v] of Object.entries(props)) {
        if (reservedKeys.has(k)) continue;

        let symbol = '§';
        let keyName = String(k);

        if (keyName.startsWith('§')) {
            symbol = '§';
            keyName = keyName.substring(1);
        } else if (keyName.startsWith('∆')) {
            symbol = '∆';
            keyName = keyName.substring(1);
        }

        let valStr = 'true';
        let modeStr = '';

        if (v && typeof v === 'object') {
            valStr = v.value !== undefined ? String(v.value) : 'true';
            modeStr = v.modes ? String(v.modes) : '';
        } else if (v !== undefined) {
            valStr = String(v);
        }

        subStr += `${symbol}${keyName}=${valStr}${modeStr}`;
    }

    return `ivc://${host}/${baseObject}${subStr}`;
}

function parseObjectFromUri(uri) {
    const parsedServer = parseServerUri(uri);
    if (!parsedServer) {
        const subParsed = parseSubobjects(uri);
        const baseObj = subParsed.baseTarget.replace(/^#/, '');
        const asObject = { object: baseObj };
        for (const [k, item] of Object.entries(subParsed.props)) {
            asObject[k] = item.value;
        }
        for (const [k, item] of Object.entries(subParsed.events)) {
            asObject[`∆${k}`] = item.value;
        }
        return {
            scheme: 'ivc',
            host: '$me',
            object: baseObj,
            uri,
            subobjects: subParsed.subobjects,
            props: subParsed.props,
            events: subParsed.events,
            asObject
        };
    }

    const baseObj = parsedServer.channel.replace(/^#/, '');
    const asObject = { object: baseObj };

    for (const [k, item] of Object.entries(parsedServer.props || {})) {
        asObject[k] = item.value;
    }
    for (const [k, item] of Object.entries(parsedServer.events || {})) {
        asObject[`∆${k}`] = item.value;
    }

    return {
        scheme: (parsedServer.protocol || 'ivc').toLowerCase(),
        host: parsedServer.host,
        object: baseObj,
        uri,
        subobjects: parsedServer.subobjects || [],
        props: parsedServer.props || {},
        events: parsedServer.events || {},
        asObject
    };
}

function applySubobjectModes(subobj, modeChange) {
    if (!subobj) return subobj;
    let currentModes = subobj.modes || '';
    let add = true;

    for (const char of modeChange) {
        if (char === '+') {
            add = true;
        } else if (char === '-') {
            add = false;
        } else if (/[a-zA-Z]/.test(char)) {
            if (add && !currentModes.includes(char)) {
                currentModes += char;
            } else if (!add && currentModes.includes(char)) {
                currentModes = currentModes.replace(char, '');
            }
        }
    }

    const cleanModes = currentModes.replace(/\+/g, '');
    currentModes = cleanModes ? '+' + cleanModes : '';

    subobj.modes = currentModes;
    subobj.modeFlags = parseModeFlagsJS(currentModes);
    return subobj;
}

function generateTraceStream(parentObject, extraData = {}) {
    const timestamp = Date.now() / 1000;
    const traceId = 'tr-' + Math.random().toString(36).substring(2, 14);

    return {
        trace_id: traceId,
        parent_object: parentObject,
        timestamp,
        formatted_time: new Date(timestamp * 1000).toISOString(),
        status: extraData.status || 'active',
        data_stream: Object.assign({
            event: 'trace_init',
            parent: parentObject,
            origin: '$me',
            state: 'monitored'
        }, extraData)
    };
}

function attachTraceSubobject(objData, tracePayload = null, host = '$me') {
    if (tracePayload === null) {
        const parentName = typeof objData === 'string' ? objData : (objData.object || 'object');
        const traceData = generateTraceStream(parentName);
        tracePayload = `${traceData.trace_id}:active`;
    } else if (typeof tracePayload === 'object' && tracePayload !== null) {
        tracePayload = JSON.stringify(tracePayload);
    }

    let targetObj = {};
    if (typeof objData === 'string') {
        const parsed = parseObjectFromUri(objData);
        targetObj = parsed.asObject || {};
    } else if (typeof objData === 'object' && objData !== null) {
        targetObj = Object.assign({}, objData);
    } else {
        targetObj = { object: 'object' };
    }

    targetObj['∆trace'] = String(tracePayload);
    return formatObjectUri(targetObj, host);
}

function getTraceDataStream(objUriOrData) {
    let events = {};
    let parentObject = 'object';

    if (typeof objUriOrData === 'string') {
        const parsed = parseObjectFromUri(objUriOrData);
        events = parsed.events || {};
        parentObject = parsed.object || 'object';
    } else if (typeof objUriOrData === 'object' && objUriOrData !== null) {
        if (objUriOrData.events) {
            events = objUriOrData.events;
            parentObject = objUriOrData.object || 'object';
        } else {
            const parsed = parseObjectFromUri(formatObjectUri(objUriOrData));
            events = parsed.events || {};
            parentObject = parsed.object || (objUriOrData.object || 'object');
        }
    } else {
        return null;
    }

    if (!events.trace) {
        return null;
    }

    const traceItem = events.trace;
    const rawTraceVal = traceItem.value || '';

    let decodedPayload = null;
    if (rawTraceVal.startsWith('{') || rawTraceVal.startsWith('[')) {
        try {
            decodedPayload = JSON.parse(rawTraceVal);
        } catch (e) {
            decodedPayload = null;
        }
    }

    return {
        parent_object: parentObject,
        symbol: '∆',
        subobject: 'trace',
        raw_value: rawTraceVal,
        modes: traceItem.modes || '',
        mode_flags: traceItem.modeFlags || {},
        stream_details: decodedPayload || {
            trace_payload: rawTraceVal,
            parent: parentObject,
            active: true
        }
    };
}

function parseServerUri(uri) {
    if (!uri) return null;
    uri = uri.trim();
    const match = uri.match(/^(https|ivc(?:-[a-zA-Z0-9_-]+)?|irc):\/\/([^\/:#?]*)(?::(\d+))?(?:[\/#](.*))?$/i);
    if (!match) return null;

    const protocol = match[1].toUpperCase();
    const host = match[2].toLowerCase();
    const defaultPorts = { HTTPS: 443, IVC: 8080, IRC: 6667 };

    let defaultPort = defaultPorts[protocol] || 443;
    if (protocol.startsWith('IVC-')) {
        defaultPort = 8080;
    }
    const port = match[3] ? parseInt(match[3], 10) : defaultPort;

    let channelRaw = match[4] || '';

    const subParsed = parseSubobjects(channelRaw);
    let baseChanRaw = subParsed.baseTarget || '';
    
    if (host === '' && baseChanRaw === '') {
        baseChanRaw = '£';
    } else if (baseChanRaw === '') {
        baseChanRaw = '#';
    }

    if (baseChanRaw.includes('#')) {
        baseChanRaw = '#' + baseChanRaw.split('#').pop();
    } else if (baseChanRaw && !/^[#&$£@!+~%]/.test(baseChanRaw)) {
        baseChanRaw = '#' + baseChanRaw;
    }

    const channel = normalizeChannel(baseChanRaw);

    return {
        protocol,
        host,
        port,
        channel,
        serverKey: `${host}:${port}`,
        uri,
        subobjects: subParsed.subobjects,
        props: subParsed.props,
        events: subParsed.events
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
    if (!name) return '#';
    name = name.trim();
    if (name === 'stats' || name === '#stats') return '#stats';
    if (!/^[#&$£@!+~%]/.test(name)) {
        name = '#' + name;
    }
    return name.replace(/[^a-zA-Z0-9\-_#&$£@!+~%\/]/g, '').replace(/\/+/g, '/').replace(/\/$/, '');
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
