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
<<<<<<< HEAD
    return {
        m: modeStr.includes('m'),
        v: modeStr.includes('v'),
        o: modeStr.includes('o'),
        e: modeStr.includes('e'),
        d: modeStr.includes('d'),
        raw: modeStr.includes('raw'),
        deltaModes: modeStr.includes('Δmodes') || modeStr.includes('deltamodes') || modeStr.includes('∆')
=======
    
    const statePlus = {};
    const stateMinus = {};
    let op = '+';
    for (const char of modeStr) {
        if (char === '+' || char === '-' || char === '0') {
            op = char;
        } else if (/[a-zA-Z]/.test(char)) {
            if (op === '+') statePlus[char] = true;
            else if (op === '-') stateMinus[char] = true;
            else if (op === '0') { delete statePlus[char]; delete stateMinus[char]; }
        }
    }

    return {
        m: statePlus['m'] === true,
        v: statePlus['v'] === true,
        V: statePlus['V'] === true,
        o: statePlus['o'] === true,
        O: statePlus['O'] === true,
        a: statePlus['a'] === true,
        A: statePlus['A'] === true,
        e: statePlus['e'] === true,
        d: statePlus['d'] === true,
        optOutD: stateMinus['d'] === true,
        k: statePlus['k'] === true,
        r: statePlus['r'] === true || statePlus['R'] === true,
        R: statePlus['r'] === true || statePlus['R'] === true,
        i: statePlus['i'] === true || statePlus['I'] === true,
        I: statePlus['i'] === true || statePlus['I'] === true,
        s: statePlus['s'] === true || statePlus['S'] === true,
        n: statePlus['n'] === true,
        N: statePlus['N'] === true,
        t: statePlus['t'] === true,
        raw: modeStr.includes('raw'),
        deltaModes: modeStr.includes('Δmodes') || modeStr.includes('deltamodes') || modeStr.includes('∆'),
        statePlus,
        stateMinus
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
    };
}

function parseQueryStringJS(queryStr, queryParams, searchKeys) {
    if (!queryStr) return;
    queryStr = String(queryStr).trim();
    if (!queryStr) return;

    const pairs = queryStr.split('&');
    for (const pair of pairs) {
        if (!pair) continue;
        const kv = pair.split('=', 2);
        const key = decodeURIComponent(kv[0].trim());
        const val = kv[1] !== undefined ? decodeURIComponent(kv[1].trim()) : 'true';

        if (queryParams[key] === undefined) {
            queryParams[key] = val;
        } else {
            if (!Array.isArray(queryParams[key])) {
                queryParams[key] = [queryParams[key], val];
            } else {
                queryParams[key].push(val);
            }
        }

        if (key === 'search') {
            searchKeys.push(val);
        }
    }
}

function parseSubobjects(input) {
    if (!input) {
        return { baseTarget: '', subobjects: [], props: {}, events: {}, query: '', queryParams: {}, search: [] };
    }
    input = String(input).trim();

    let queryStr = '';
    const queryParams = {};
    const searchKeys = [];

    const qPos = input.indexOf('?');
    if (qPos !== -1) {
        queryStr = input.substring(qPos + 1);
        input = input.substring(0, qPos);
        parseQueryStringJS(queryStr, queryParams, searchKeys);
    }

    const posSec = input.indexOf('§');
<<<<<<< HEAD
    const posDelta = input.indexOf('∆');
=======
    const posDelta1 = input.indexOf('∆');
    const posDelta2 = input.indexOf('Δ');
    const posDelta = (posDelta1 !== -1 && posDelta2 !== -1)
        ? Math.min(posDelta1, posDelta2)
        : (posDelta1 !== -1 ? posDelta1 : posDelta2);
>>>>>>> f79f4cf (local state jakedot@petar-vivo)

    let firstSubPos = null;
    if (posSec !== -1 && posDelta !== -1) {
        firstSubPos = Math.min(posSec, posDelta);
    } else if (posSec !== -1) {
        firstSubPos = posSec;
    } else if (posDelta !== -1) {
        firstSubPos = posDelta;
    }

    if (firstSubPos === null) {
        return { baseTarget: input, subobjects: [], props: {}, events: {}, query: queryStr, queryParams, search: searchKeys };
    }

    const baseTarget = input.substring(0, firstSubPos);
    const subStr = input.substring(firstSubPos);

<<<<<<< HEAD
    const tokens = subStr.split(/([§∆])/g).filter(Boolean);
=======
    const tokens = subStr.split(/([§∆Δ])/g).filter(Boolean);
>>>>>>> f79f4cf (local state jakedot@petar-vivo)

    const subobjects = [];
    const props = {};
    const events = {};

    for (let i = 0; i < tokens.length; i += 2) {
        const symbol = tokens[i];
        const segment = tokens[i + 1] || '';

<<<<<<< HEAD
        if (symbol !== '§' && symbol !== '∆') continue;
=======
        if (symbol !== '§' && symbol !== '∆' && symbol !== 'Δ') continue;
>>>>>>> f79f4cf (local state jakedot@petar-vivo)

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
<<<<<<< HEAD
        } else if ((match = segment.match(/^([a-zA-Z0-9_\-\.\/]+)([\+\-][a-zA-Z0-9_\-\+]+)?$/))) {
=======
        } else if ((match = segment.match(/^([a-zA-Z0-9_\-\.\/]+)([\+\-0][a-zA-Z0-9_\-\+0=]+)?$/))) {
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
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

    return { baseTarget, subobjects, props, events, query: queryStr, queryParams, search: searchKeys };
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
    let queryPart = '';
    const reservedKeys = new Set(['object', 'name', 'host', 'protocol', 'scheme', 'subobjects', 'props', 'events', 'uri', 'asObject', 'query', 'queryParams', 'query_params', 'search']);

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

    if (props.search && Array.isArray(props.search)) {
        const qPairs = props.search.map(sKey => `search=${encodeURIComponent(String(sKey))}`);
        queryPart = '?' + qPairs.join('&');
    } else if (props.queryParams || props.query_params) {
        const qObj = props.queryParams || props.query_params;
        const qPairs = [];
        for (const [qk, qv] of Object.entries(qObj)) {
            if (Array.isArray(qv)) {
                for (const subV of qv) {
                    qPairs.push(`${encodeURIComponent(String(qk))}=${encodeURIComponent(String(subV))}`);
                }
            } else {
                qPairs.push(`${encodeURIComponent(String(qk))}=${encodeURIComponent(String(qv))}`);
            }
        }
        queryPart = '?' + qPairs.join('&');
    } else if (props.query && typeof props.query === 'string') {
        queryPart = '?' + props.query.replace(/^\?/, '');
    }

    return `ivc://${host}/${baseObject}${subStr}${queryPart}`;
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
        if (subParsed.search && subParsed.search.length > 0) {
            asObject.search = subParsed.search;
        }
        if (subParsed.queryParams && Object.keys(subParsed.queryParams).length > 0) {
            asObject.queryParams = subParsed.queryParams;
            asObject.query_params = subParsed.queryParams;
        }
        return {
            scheme: 'ivc',
            host: '$me',
            object: baseObj,
            uri,
            subobjects: subParsed.subobjects,
            props: subParsed.props,
            events: subParsed.events,
            query: subParsed.query || '',
            queryParams: subParsed.queryParams || {},
            query_params: subParsed.queryParams || {},
            search: subParsed.search || [],
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
    if (parsedServer.search && parsedServer.search.length > 0) {
        asObject.search = parsedServer.search;
    }
    if (parsedServer.queryParams && Object.keys(parsedServer.queryParams).length > 0) {
        asObject.queryParams = parsedServer.queryParams;
        asObject.query_params = parsedServer.queryParams;
    }

    return {
        scheme: (parsedServer.protocol || 'ivc').toLowerCase(),
        host: parsedServer.host,
        object: baseObj,
        uri,
        subobjects: parsedServer.subobjects || [],
        props: parsedServer.props || {},
        events: parsedServer.events || {},
        query: parsedServer.query || '',
        queryParams: parsedServer.queryParams || {},
        query_params: parsedServer.queryParams || {},
        search: parsedServer.search || [],
        asObject
    };
}

function applySubobjectModes(subobj, modeChange) {
    if (!subobj) return subobj;
    let currentModes = subobj.modes || '';
<<<<<<< HEAD
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

=======
    
    const statePlus = {};
    const stateMinus = {};
    let op = '+';
    for (const char of currentModes) {
        if (char === '+' || char === '-') op = char;
        else if (/[a-zA-Z]/.test(char)) {
            if (op === '+') statePlus[char] = true;
            else if (op === '-') stateMinus[char] = true;
        }
    }
    
    op = '+';
    let i = 0;
    while (i < modeChange.length) {
        const char = modeChange[i];
        if (char === '+' || char === '-' || char === '0') {
            op = char;
        } else if (/[a-zA-Z]/.test(char)) {
            if (i + 1 < modeChange.length && modeChange[i+1] === '=') {
                delete statePlus[char];
                delete stateMinus[char];
                i++;
            } else {
                if (op === '+') { statePlus[char] = true; if (char !== 'd') delete stateMinus[char]; }
                else if (op === '-') { stateMinus[char] = true; if (char !== 'd') delete statePlus[char]; }
                else if (op === '0') { delete statePlus[char]; delete stateMinus[char]; }
            }
        }
        i++;
    }
    
    let plus = '';
    let minus = '';
    for (const k of Object.keys(statePlus)) { if (statePlus[k]) plus += k; }
    for (const k of Object.keys(stateMinus)) { if (stateMinus[k]) minus += k; }
    
    let res = '';
    if (plus) res += '+' + plus;
    if (minus) res += '-' + minus;
    
    subobj.modes = res;
    subobj.modeFlags = parseModeFlagsJS(res);
    return subobj;
}

function generateDataStream(parentObject, options = {}) {
    const timestamp = Date.now() / 1000;
    return {
        timestamp,
        parent_object: parentObject,
        metrics: {
            active_nodes: Math.floor(Math.random() * 50) + 1,
            peer_mesh_connections: Math.floor(Math.random() * 200),
            bandwidth_kbps: Math.floor(Math.random() * 10000),
            latency_ms: Math.floor(Math.random() * 150),
            health_score: 98,
            memory_usage_mb: Math.floor(Math.random() * 1024)
        },
        ...options
    };
}

function attachDataSubobject(objData, dataPayload = null, host = '$me') {
    if (dataPayload === null) {
        const parentName = typeof objData === 'string' ? objData : (objData.object || 'object');
        dataPayload = generateDataStream(parentName);
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
    targetObj['∆data'] = typeof dataPayload === 'string' ? dataPayload : JSON.stringify(dataPayload);
    return formatObjectUri(targetObj, host);
}

function getDataViewStream(objUriOrData, userModes = {}) {
    let events = {};
    let parentObject = 'object';
    let objectModes = {};

    if (typeof objUriOrData === 'string') {
        const parsed = parseObjectFromUri(objUriOrData);
        events = parsed.events || {};
        parentObject = parsed.object || 'object';
        const rawModes = parsed.props.modes ? parsed.props.modes.value : '';
        objectModes = parseModeFlagsJS(rawModes);
    } else if (typeof objUriOrData === 'object' && objUriOrData !== null) {
        events = objUriOrData.events || {};
        parentObject = objUriOrData.object || 'object';
        objectModes = objUriOrData.modes ? parseModeFlagsJS(objUriOrData.modes) : {};
    }

    if (!events.data) return null;

    const dataItem = events.data;
    const itemModes = dataItem.modeFlags || {};

    // Authorization: User has +d, OR Object explicitly has +d (opt-in to sharing)
    if (!userModes.d && !objectModes.d && !itemModes.d) {
        return { error: '403 Access Denied: Requires +d user mode or +d object mode', code: 403 };
    }

    // Opt-Out: Object or item explicitly has -d (blocks sharing)
    if (objectModes.optOutD || itemModes.optOutD) {
        return { error: '403 Opted Out: Object opted out of Δdata sharing', code: 403 };
    }

    let rawVal = dataItem.value || '';
    let decoded = null;
    if (rawVal.startsWith('{') || rawVal.startsWith('[')) {
        try { decoded = JSON.parse(rawVal); } catch(e) {}
    }

    return {
        parent_object: parentObject,
        symbol: '∆',
        subobject: 'data',
        raw_value: rawVal,
        modes: dataItem.modes || '',
        mode_flags: itemModes,
        stream_details: decoded || { payload: rawVal }
    };
}

>>>>>>> f79f4cf (local state jakedot@petar-vivo)
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
    const match = uri.match(/^(https|ivc(?:-[a-zA-Z0-9_-]+)?|irc):\/\/([^\/:#?]+)(?::(\d+))?(?:[\/#](.*))?$/i);
    if (!match) return null;

    const protocol = match[1].toUpperCase();
    const host = match[2].toLowerCase();
    const defaultPorts = { HTTPS: 443, IVC: 8080, IRC: 6667 };

    let defaultPort = defaultPorts[protocol] || 443;
    if (protocol.startsWith('IVC-')) {
        defaultPort = 8080;
    }
    const port = match[3] ? parseInt(match[3], 10) : defaultPort;

<<<<<<< HEAD
    let channelRaw = (match[4] || '#lobby').replace(/^\/+/, '');
=======
<<<<<<< HEAD
    let channelRaw = match[4] || '#lobby';
>>>>>>> 3242792 (merge??)

    const subParsed = parseSubobjects(channelRaw);
    let baseChanRaw = subParsed.baseTarget || '#lobby';
=======
    let channelRaw = match[4] || '#';

    const subParsed = parseSubobjects(channelRaw);
    let baseChanRaw = subParsed.baseTarget || '#';
>>>>>>> f79f4cf (local state jakedot@petar-vivo)

    if (baseChanRaw.includes('#')) {
        baseChanRaw = '#' + baseChanRaw.split('#').pop();
    } else if (baseChanRaw && !baseChanRaw.startsWith('#')) {
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
        events: subParsed.events,
        query: subParsed.query || '',
        queryParams: subParsed.queryParams || {},
        query_params: subParsed.queryParams || {},
        search: subParsed.search || []
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
<<<<<<< HEAD
    if (!name) return '#lobby';
    name = name.trim();
    if (name === 'stats' || name === '#stats') return '#stats';
    if (!name.startsWith('#') && !name.startsWith('&')) {
        name = '#' + name;
    }
    return name.replace(/[^a-zA-Z0-9\-_#&\/]/g, '').replace(/\/+/g, '/').replace(/\/$/, '');
=======
    if (!name) return '#';
    name = name.trim();
    if (name === 'stats' || name === '#stats') return '#stats';
    if (!/^[#&$£@!+~%]/.test(name)) {
        name = '#' + name;
    }
    return name.replace(/[^a-zA-Z0-9\-_#&$£@!+~%\/]/g, '').replace(/\/+/g, '/').replace(/\/$/, '');
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
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

<<<<<<< HEAD
const localSharedFilesMap = {};
=======
const DELTA_SYMBOL = 'Δ';
const DELTA_SYMBOL_ALT = '∆';
const SECTION_SYMBOL = '§';
const DELTA_SYMBOLS = [DELTA_SYMBOL, DELTA_SYMBOL_ALT];

const localSharedFilesMap = {};

function formatReactionsUri(objectUri, reactions = null) {
    if (!objectUri) return `ivc://$me/object${DELTA_SYMBOL}reactions`;
    let base = String(objectUri).trim().replace(/[Δ∆]reactions(?:=.*)?$/i, '').replace(/\/+$/, '');
    if (reactions && typeof reactions === 'object' && Object.keys(reactions).length > 0) {
        const counts = {};
        for (const [em, info] of Object.entries(reactions)) {
            const c = typeof info === 'object' ? (info.count || 1) : Number(info);
            if (c > 0) counts[em] = c;
        }
        if (Object.keys(counts).length > 0) {
            return `${base}${DELTA_SYMBOL}reactions=${JSON.stringify(counts)}`;
        }
    }
    return `${base}${DELTA_SYMBOL}reactions`;
}

function getRedirectUri(objectUri, reactions = null) {
    return formatReactionsUri(objectUri, reactions);
}
>>>>>>> f79f4cf (local state jakedot@petar-vivo)
