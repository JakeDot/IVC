import express from 'express';
import path from 'path';
import { getPhp, processIrcCommand, mongoDb } from './php_engine.js';
getPhp().then(() => console.log('PHP WASM Engine loaded and initialized.')).catch(console.error);


const app = express();
app.use(express.json());

const port = process.env.PORT || 3000;
const host = '0.0.0.0';

if (port !== 3000) {
    try { mongoDb.collection('chanserv_channels').deleteOne({ channel_name: '#c' }); } catch(e) {}
}

import crypto from 'crypto';

// Auto-populate GUIDs for legacy records (MongoDB)
try {
    const channels = mongoDb.collection('chanserv_channels').find({ $or: [{ guid: { $exists: false } }, { guid: '' }] });
    for (const c of channels) {
        const newGuid = crypto.randomUUID();
        mongoDb.collection('chanserv_channels').updateOne({ channel_name: c.channel_name }, { $set: { guid: newGuid } });
        mongoDb.collection('object_aliases').insertOne({ alias_name: c.channel_name, target_guid: newGuid, object_type: '#' });
    }
} catch (e) { console.error('Mongo channel GUID migration error:', e); }

try {
    const nicks = mongoDb.collection('nameserv_nicks').find({ $or: [{ guid: { $exists: false } }, { guid: '' }] });
    for (const n of nicks) {
        const newGuid = crypto.randomUUID();
        mongoDb.collection('nameserv_nicks').updateOne({ nickname: n.nickname }, { $set: { guid: newGuid } });
        const aliasName = '@' + n.nickname;
        mongoDb.collection('object_aliases').insertOne({ alias_name: aliasName, target_guid: newGuid, object_type: '@' });
    }
} catch (e) { console.error('Mongo nick GUID migration error:', e); }

// Resolve an alias to its GUID. If it doesn't exist, create it if it's a channel or return the alias.
function resolveAliasToGuid(alias) {
    if (!alias) return null;
    
    // If it's already a GUID format, just return it
    if (/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/.test(alias)) {
        return alias;
    }
    
    try {
        const row = mongoDb.collection('object_aliases').findOne({ alias_name: { $regex: new RegExp(`^${alias}$`, 'i') } });
        if (row && row.target_guid) {
            return row.target_guid;
        }
        
        // Auto-provision un-registered channels on the fly if it starts with #
        if (alias.startsWith('#')) {
            const newGuid = crypto.randomUUID();
            mongoDb.collection('object_aliases').insertOne({ alias_name: alias, target_guid: newGuid, object_type: '#' });
            return newGuid;
        }
        
        // Auto-provision users
        if (alias.startsWith('@')) {
            const newGuid = crypto.randomUUID();
            mongoDb.collection('object_aliases').insertOne({ alias_name: alias, target_guid: newGuid, object_type: '@' });
            return newGuid;
        }

        // Auto-provision services
        if (alias.startsWith('$')) {
            const newGuid = crypto.randomUUID();
            mongoDb.collection('object_aliases').insertOne({ alias_name: alias, target_guid: newGuid, object_type: '$' });
            return newGuid;
        }

    } catch (e) {
        console.error("Alias resolution error", e);
    }
    
    return alias; // fallback to the string if something fails
}

// In-memory data
const rooms = new Map(); // guid -> { peers: Map<clientId, { response: res }> }

app.get('/api/config.php', (req, res) => {
    res.json({ stun: [], turn: [] });
});

app.get('/api/stats.php', (req, res) => {
    res.json({ db_status: 'Mocked', active_rooms: rooms.size });
});

app.get('/api/data.php', (req, res) => {
    const target = req.query.target || '$server';
    const subobjectRaw = req.query.subobject || 'data';
    const client = req.query.client || '';
    
    // Check if the user is explicitly requesting +d permission: `data+d`
    const isRequestingD = subobjectRaw.includes('+d');
    
    let objectModesStr = '';
    const targetGuid = resolveAliasToGuid(target);

    try {
        const mDoc = mongoDb.collection('chanserv_channels').findOne({
            $or: [{ guid: targetGuid }, { channel_name: { $regex: new RegExp(`^${target.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\$&')}$`, 'i') } }]
        });
        if (mDoc && mDoc.modes) objectModesStr = mDoc.modes;
    } catch (e) {}
    
    const isOptedOut = objectModesStr.includes('-d');
    const isOptedIn = objectModesStr.includes('+d');
    
    let userHasAuth = false;
    if (client) {
        try {
            const mUser = mongoDb.collection('nameserv_nicks').findOne({ nickname: { $regex: new RegExp(`^${client}$`, 'i') } });
            if (mUser && mUser.modes) {
                const p = parseChannelModes(mUser.modes);
                if (p['+d']) {
                    userHasAuth = true;
                }
            }
        } catch (e) {}
    }
    if (isOptedOut) {
        return res.status(403).json({ error: '403 Opted Out: Object opted out of Δdata sharing (-d)', code: 403 });
    }
    
    if (!userHasAuth && !isOptedIn && !isRequestingD) {
        return res.status(403).json({ error: '403 Access Denied: Requires +d user mode or +d object mode', code: 403 });
    }
    
    if (isRequestingD) {
        if (!userHasAuth && !isOptedIn) {
            if (target.startsWith('@')) {
                // Prompt to target user
                if (rooms.has(targetGuid)) {
                    const peers = rooms.get(targetGuid).peers;
                    peers.forEach((peerData) => {
                        peerData.res.write(`data: ${JSON.stringify({
                            sender: 'SYSTEM',
                            type: 'system',
                            text: `User ${client} is requesting permission to view your telemetry (∆data+d). Type /mode ${target} +d to grant.`
                        })}\n\n`);
                    });
                }
                return res.status(403).json({ error: `403 Access Pending: Requested permission from ${target}. Awaiting approval.`, code: 403 });
            } else {
                return res.status(403).json({ error: `403 Access Denied: Services or channels automatically deny permission requests from unauthorized users.`, code: 403 });
            }
        }
    }
    
    res.json({
        parent_object: target,
        symbol: '∆',
        subobject: 'data',
        timestamp: Date.now() / 1000,
        metrics: {
            active_nodes: Math.floor(Math.random() * 50) + 5,
            peer_mesh_connections: Math.floor(Math.random() * 500),
            bandwidth_kbps: Math.floor(Math.random() * 50000),
            latency_ms: Math.floor(Math.random() * 80) + 10,
            health_score: 95 + Math.floor(Math.random() * 5),
            memory_usage_mb: Math.floor(Math.random() * 4096)
        }
    });
});

app.post('/api/ai-analytics', (req, res) => {
    res.writeHead(200, {
        'Content-Type': 'text/event-stream',
        'Cache-Control': 'no-cache',
        'Connection': 'keep-alive'
    });
    
    const { target, query, telemetry } = req.body || {};
    
    const thoughts = [
        "Analyzing telemetry data for target: " + (target || 'unknown'),
        "Checking active nodes and peer mesh topology...",
        "Evaluating throughput and bandwidth patterns.",
        "Correlating latency spikes with memory usage..."
    ];
    
    let thoughtIdx = 0;
    const thoughtInterval = setInterval(() => {
        if (thoughtIdx < thoughts.length) {
            res.write(`data: ${JSON.stringify({ type: 'THOUGHT', content: thoughts[thoughtIdx] })}\n\n`);
            thoughtIdx++;
        } else {
            clearInterval(thoughtInterval);
            
            // Send final response
            let responseText = "Based on the telemetry for `" + (target || 'the object') + "`, the network is operating normally.";
            if (query && query.toLowerCase().includes('anomaly')) {
                responseText += "\n\n**Anomaly Detection:** No significant anomalies detected. Memory usage and peer latency are within expected thresholds.";
            } else if (query) {
                responseText += "\n\n**Insight on query:** '" + query + "'\nThe metrics show a robust mesh topology with no immediate bottlenecks.";
            } else {
                responseText += "\n\n**Executive Summary:**\n- **Health Score**: Excellent\n- **Throughput**: Stable\n- **Recommendation**: Continue standard monitoring.";
            }
            
            res.write(`data: ${JSON.stringify({ type: 'FINAL_RESPONSE', content: responseText })}\n\n`);
            
            // Send suggestions
            const suggestions = [
                { text: "Detect anomalies" },
                { text: "Channel bandwidth breakdown" },
                { text: "Predict future load" }
            ];
            res.write(`data: ${JSON.stringify({ type: 'SUGGESTION', items: suggestions })}\n\n`);
            res.write(`data: [DONE]\n\n`);
            res.end();
        }
    }, 500);
    
    req.on('close', () => {
        clearInterval(thoughtInterval);
    });
});


function getUserIdentification(nickname) {
    const cleanNick = (nickname || '').split('@')[0].split(':')[0].trim();
    if (!cleanNick) return { registered: false, identified: false };
    try {
        const mNick = mongoDb.collection('nameserv_nicks').findOne({ nickname: { $regex: new RegExp(`^${cleanNick}$`, 'i') } });
        if (mNick) {
            return {
                registered: true,
                identified: mNick.is_identified === 1 || mNick.is_identified === true
            };
        }
    } catch (e) {}
    return { registered: false, identified: false };
}

function getUserChannelRole(channel, nickname) {
    const baseChan = (channel || '').split('+')[0];
    const cleanNick = (nickname || '').split('@')[0].split(':')[0].trim();
    if (!baseChan || !cleanNick) return 'NONE';

    try {
        const targetGuid = resolveAliasToGuid(baseChan);
        
        // Find channel owner
        const mChan = mongoDb.collection('chanserv_channels').findOne({
            $or: [{ guid: targetGuid }, { channel_name: { $regex: new RegExp(`^${baseChan.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\$&')}$`, 'i') } }]
        });
        if (mChan && mChan.owner_nick && mChan.owner_nick.toLowerCase() === cleanNick.toLowerCase()) {
            return 'NETADMIN';
        }
        
        // Check user role
        const mUser = mongoDb.collection('channel_users').findOne({
            channel_name: { $regex: new RegExp(`^${baseChan}$`, 'i') }, // fallback if channel_users not migrated yet
            nickname: { $regex: new RegExp(`^${cleanNick}$`, 'i') }
        });
        if (mUser && mUser.role) {
            return mUser.role.toUpperCase();
        }
    } catch (e) {}
    return 'MEMBER';
}

function parseChannelModes(modeStr) {
    const flags = {};
    if (!modeStr) return flags;
    let modifier = '+'; // can be '+', '-', '0', '='
    for (let i = 0; i < modeStr.length; i++) {
        const c = modeStr[i];
        if (c === '+' || c === '-' || c === '0' || c === '=') { 
            modifier = c; 
        } else {
            if (modifier === '+') {
                flags[c] = true;
                flags['+' + c] = true;
            } else if (modifier === '-') {
                flags[c] = false;
                flags['-' + c] = true;
            } else if (modifier === '0' || modifier === '=') {
                delete flags[c];
                delete flags['+' + c];
                delete flags['-' + c];
                flags['0' + c] = true;
            }
        }
    }
    return flags;
}

function roleSatisfies(userRole, requiredRole) {
    const hierarchy = {
        'NONE': 0,
        'MEMBER': 10,
        'VOICE': 20,
        'OP': 30,
        'OPERATOR': 30,
        'ADMIN': 40,
        'NETADMIN': 50,
        'OWNER': 50
    };
    return (hierarchy[userRole] || 0) >= (hierarchy[requiredRole] || 0);
}

app.get('/api/signal.php', (req, res) => {
    const rawRoom = req.query.room;
    const clientId = req.query.client;
    const mode = req.query.mode;

    if (!rawRoom || !clientId) return res.status(400).json({ error: 'Missing room or client' });

    const baseRoom = rawRoom.split('+')[0];
    const keyMatch = rawRoom.match(/\+k=([^&+]+)/) || (req.query.key ? [null, req.query.key] : null);
    const providedKey = keyMatch ? keyMatch[1] : '';

    // Check channel modes & key
    try {
        let channelModes = '';
        const targetGuid = resolveAliasToGuid(baseRoom);
        const mDoc = mongoDb.collection('chanserv_channels').findOne({
            $or: [{ guid: targetGuid }, { channel_name: { $regex: new RegExp(`^${baseRoom.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\$&')}$`, 'i') } }]
        });
        if (mDoc && mDoc.modes) channelModes = mDoc.modes;

        if (channelModes) {
            const km = channelModes.match(/(?:\+)?k=([^&+]+)/);
            if (km) {
                const requiredKey = km[1];
                if (providedKey !== requiredKey) {
                    return res.status(475).json({ error: 'Cannot join channel (+k) - Key required' });
                }
            }
        }

        const roomModes = rawRoom.includes('+') || rawRoom.includes('-') ? rawRoom.substring(rawRoom.search(/[+-]/)) : '';
        const parsedModes = parseChannelModes(channelModes + roomModes);

        const isRegisteredOnly = !!parsedModes['r'];
        const isIdentifiedOnly = !!parsedModes['i'];
        const isAdminOnly = !!parsedModes['A'];
        const isOpOnly = !!parsedModes['O'];
        const isNetAdminOnly = !!parsedModes['N'];

        const ident = getUserIdentification(clientId);
        if (isRegisteredOnly && !ident.registered && !ident.identified) {
            return res.status(477).json({ error: 'Cannot join channel (+r) - Registered nick required' });
        }
        if (isIdentifiedOnly && !ident.identified) {
            return res.status(477).json({ error: 'Cannot join channel (+i) - Identified nick required' });
        }

        const userRole = getUserChannelRole(baseRoom, clientId);
        if (isAdminOnly && !roleSatisfies(userRole, 'ADMIN')) {
            return res.status(473).json({ error: 'Cannot join channel (+A) - Channel admin (+a) status required' });
        }
        if (isOpOnly && !roleSatisfies(userRole, 'OP')) {
            return res.status(473).json({ error: 'Cannot join channel (+O) - Channel operator (+o) status required' });
        }
        if (isNetAdminOnly && !roleSatisfies(userRole, 'NETADMIN')) {
            return res.status(473).json({ error: 'Cannot join channel (+N) - Network admin / owner (+n) status required' });
        }
    } catch (e) {}

    if (mode === 'sse') {
        res.writeHead(200, {
            'Content-Type': 'text/event-stream',
            'Cache-Control': 'no-cache',
            'Connection': 'keep-alive'
        });
        res.write(': connected\n\n');
        
        const targetGuid = resolveAliasToGuid(baseRoom);
        if (!rooms.has(targetGuid)) {
            rooms.set(targetGuid, { peers: new Map() });
        }
        rooms.get(targetGuid).peers.set(clientId, { res: res, alias: baseRoom });
        
        req.on('close', () => {
            if (rooms.has(targetGuid)) {
                rooms.get(targetGuid).peers.delete(clientId);
            }
        });
    } else {
        res.json({ status: 'ok', peers: [], messages: [] });
    }
});

// Rate Limiting
const rateLimits = new Map();

app.post('/api/signal.php', (req, res) => {
    const ip = req.ip || req.socket?.remoteAddress || 'unknown';
    const now = Date.now();
    const rate = rateLimits.get(ip) || { count: 0, time: now };
    
    if (now - rate.time > 60000) {
        rate.count = 0;
        rate.time = now;
    }
    
    rate.count++;
    rateLimits.set(ip, rate);
    
    if (rate.count > 100) {
        return res.status(429).json({ error: 'Too many requests' });
    }

    const { room: roomId, client: clientId, type } = req.body;
    
    // Basic validation
    if (!roomId || typeof roomId !== 'string' || roomId.match(/[<>]/)) {
        return res.status(400).json({ error: 'Invalid or malformed signal payload' });
    }

    const baseRoom = roomId.split('+')[0];
    try {
        let channelModes = '';
        const targetGuid = resolveAliasToGuid(baseRoom);
        const mDoc = mongoDb.collection('chanserv_channels').findOne({
            $or: [{ guid: targetGuid }, { channel_name: { $regex: new RegExp(`^${baseRoom.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\$&')}$`, 'i') } }]
        });
        if (mDoc && mDoc.modes) channelModes = mDoc.modes;

        const roomModes = roomId.includes('+') || roomId.includes('-') ? roomId.substring(roomId.search(/[+-]/)) : '';
        const parsedModes = parseChannelModes(channelModes + roomModes);

        const isRegisteredOnly = !!parsedModes['r'];
        const isIdentifiedOnly = !!parsedModes['i'];
        const isAdminOnly = !!parsedModes['A'];
        const isOpOnly = !!parsedModes['O'];
        const isNetAdminOnly = !!parsedModes['N'];
        const isVoiceRestricted = !!(parsedModes['v'] || parsedModes['m']);

        if (clientId) {
            const ident = getUserIdentification(clientId);
            if (isRegisteredOnly && !ident.registered && !ident.identified) {
                return res.status(477).json({ error: 'Cannot send signal to channel (+r) - Registered nick required' });
            }
            if (isIdentifiedOnly && !ident.identified) {
                return res.status(477).json({ error: 'Cannot send signal to channel (+i) - Identified nick required' });
            }

            const userRole = getUserChannelRole(baseRoom, clientId);
            if (isAdminOnly && !roleSatisfies(userRole, 'ADMIN')) {
                return res.status(473).json({ error: 'Cannot send signal to channel (+A) - Channel admin (+a) status required' });
            }
            if (isOpOnly && !roleSatisfies(userRole, 'OP')) {
                return res.status(473).json({ error: 'Cannot send signal to channel (+O) - Channel operator (+o) status required' });
            }
            if (isNetAdminOnly && !roleSatisfies(userRole, 'NETADMIN')) {
                return res.status(473).json({ error: 'Cannot send signal to channel (+N) - Network admin / owner (+n) status required' });
            }

            const isChatMessage = type === 'chat' || !!req.body.message || !!req.body.text;
            if (isVoiceRestricted && isChatMessage && !roleSatisfies(userRole, 'VOICE')) {
                return res.status(403).json({ error: 'Cannot send text message to channel (+v/+m) - Voice (+v) or operator (+o) required' });
            }
        }
    } catch (e) {}
    
    if (type === 'leave') {
        const targetGuid = resolveAliasToGuid(roomId);
        if (rooms.has(targetGuid)) {
            rooms.get(targetGuid).peers.delete(clientId);
        }
        return res.json({ status: 'left' });
    }
    
    // Broadcast
    const broadcastPayload = { ...req.body };
    if (!broadcastPayload.sender && clientId) {
        broadcastPayload.sender = clientId;
    }

    const targetGuid = resolveAliasToGuid(roomId);
    if (rooms.has(targetGuid)) {
        rooms.get(targetGuid).peers.forEach((peerData, peerId) => {
            if (peerId !== clientId) {
                const tailoredPayload = { ...broadcastPayload, room: peerData.alias };
                peerData.res.write(`data: ${JSON.stringify(tailoredPayload)}\n\n`);
            }
        });
    }
    res.json({ status: 'sent' });
});

app.post('/api/irc.php', async (req, res) => {
    const { channel, sender, text } = req.body;
    
    try {
        const result = await processIrcCommand(sender, channel, text);
        if (result) {
            res.json(result);
        } else {
            res.json({ status: 'ignored' });
        }
    } catch (e) {
        console.error("IRC Processing Error:", e);
        res.status(500).json({ error: e.message });
    }
});

app.get('/api/files.php', (req, res) => {
    const rawChannel = req.query.channel || req.query.room || '';
    const client = req.query.client || req.query.user || '';
    if (!rawChannel) {
        return res.json({ status: 'ok', channel: '', files: [] });
    }
    const baseRoom = rawChannel.split('+')[0];
    const targetGuid = resolveAliasToGuid(baseRoom);

    try {
        let channelModes = '';
        const mDoc = mongoDb.collection('chanserv_channels').findOne({
            $or: [{ guid: targetGuid }, { channel_name: { $regex: new RegExp(`^${baseRoom.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\$&')}$`, 'i') } }]
        });
        if (mDoc && mDoc.modes) channelModes = mDoc.modes;

        const roomModes = rawChannel.includes('+') || rawChannel.includes('-') ? rawChannel.substring(rawChannel.search(/[+-]/)) : '';
        const parsedModes = parseChannelModes(channelModes + roomModes);

        const isRegisteredOnly = !!parsedModes['r'];
        const isIdentifiedOnly = !!parsedModes['i'];
        const isAdminOnly = !!parsedModes['A'];
        const isOpOnly = !!parsedModes['O'];
        const isNetAdminOnly = !!parsedModes['N'];

        const ident = getUserIdentification(client);
        if (isRegisteredOnly && !ident.registered && !ident.identified) {
            return res.status(477).json({ error: 'Cannot access files (+r) - Registered nick required' });
        }
        if (isIdentifiedOnly && !ident.identified) {
            return res.status(477).json({ error: 'Cannot access files (+i) - Identified nick required' });
        }

        const userRole = getUserChannelRole(baseRoom, client);
        if (isAdminOnly && !roleSatisfies(userRole, 'ADMIN')) {
            return res.status(473).json({ error: 'Cannot access files (+A) - Channel admin (+a) status required' });
        }
        if (isOpOnly && !roleSatisfies(userRole, 'OP')) {
            return res.status(473).json({ error: 'Cannot access files (+O) - Channel operator (+o) status required' });
        }
        if (isNetAdminOnly && !roleSatisfies(userRole, 'NETADMIN')) {
            return res.status(473).json({ error: 'Cannot access files (+N) - Network admin / owner (+n) status required' });
        }
    } catch (e) {}

    try {
        const rows = mongoDb.collection('shared_files').find({ channel_guid: targetGuid });
        return res.json({ status: 'ok', channel: baseRoom, files: rows });
    } catch(e) {
        return res.json({ status: 'ok', channel: baseRoom, files: [] });
    }
});
const DELTA_SYMBOL = 'Δ';
const DELTA_SYMBOL_ALT = '∆';
const SECTION_SYMBOL = '§';
const DELTA_SYMBOLS = [DELTA_SYMBOL, DELTA_SYMBOL_ALT];

function getExtendedReactionsUri(cleanObj, summary) {
    const counts = {};
    if (summary) {
        for (const k in summary) {
            const c = typeof summary[k] === 'object' ? (summary[k].count || 0) : summary[k];
            if (c > 0) counts[k] = c;
        }
    }
    if (Object.keys(counts).length > 0) {
        return `${cleanObj}${DELTA_SYMBOL}reactions=${JSON.stringify(counts)}`;
    }
    return `${cleanObj}${DELTA_SYMBOL}reactions`;
}

function getObjectReactions(rawObj) {
    let cleanObj = (rawObj || '').replace(/[Δ∆]reactions(?:=.*)?$/i, '').trim();
    const equivalents = [cleanObj];

    // Network comments: ivc://:comment-id <-> ivc://£:comment-id
    const mPound = cleanObj.match(/^ivc:\/\/£:(.+)$/);
    if (mPound) {
        equivalents.push(`ivc://:${mPound[1]}`);
    } else {
        const mColon = cleanObj.match(/^ivc:\/\/:(.+)$/);
        if (mColon) {
            equivalents.push(`ivc://£:${mColon[1]}`);
        }
    }

    const coll = mongoDb.collection('object_reactions');
    const docs = [];
    const seen = new Set();
    for (const eq of equivalents) {
        const found = coll.find({ object_uri: eq });
        for (const doc of found) {
            const key = `${doc.emoji}:${doc.sender_nick}`;
            if (!seen.has(key)) {
                seen.add(key);
                docs.push(doc);
            }
        }
    }

    const summary = {};
    let totalCount = 0;
    for (const doc of docs) {
        const em = doc.emoji;
        const nick = doc.sender_nick;
        if (!summary[em]) {
            summary[em] = { count: 0, users: [] };
        }
        summary[em].count++;
        if (!summary[em].users.includes(nick)) {
            summary[em].users.push(nick);
        }
        totalCount++;
    }

    const extendedUri = getExtendedReactionsUri(cleanObj, summary);
    return {
        cleanObj,
        summary,
        totalCount,
        extendedUri
    };
}

app.get('/api/reactions.php', (req, res) => {
    let rawObj = req.query.object || req.query.uri || req.query.target || '';
    if (!rawObj) {
        return res.json({ status: 'ok', object: '', reactions: {}, total_count: 0 });
    }
    try {
        const { cleanObj, summary, totalCount, extendedUri } = getObjectReactions(rawObj);

        res.setHeader('Location', encodeURI(extendedUri));

        if (req.query.redirect === '1' || req.query.redirect === 'true' || req.query.mode === 'redirect') {
            return res.redirect(302, encodeURI(extendedUri));
        }

        res.json({
            status: 'ok',
            object: cleanObj,
            reactions: summary,
            total_count: totalCount,
            reactions_uri: extendedUri,
            redirect: extendedUri,
            redirect_uri: extendedUri
        });
    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

app.post('/api/reactions.php', (req, res) => {
    const { object: rawObj, emoji, sender } = req.body || {};
    if (!rawObj) {
        return res.status(400).json({ error: 'Missing target object' });
    }
    const cleanObj = rawObj.replace(/[Δ∆]reactions(?:=.*)?$/i, '').trim();
    const em = (emoji || '❤️').trim();
    const nick = (sender || 'anonymous').trim();
    try {
        const coll = mongoDb.collection('object_reactions');
        const existing = coll.findOne({ object_uri: cleanObj, emoji: em, sender_nick: nick });
        if (existing) {
            coll.updateOne({ object_uri: cleanObj, emoji: em, sender_nick: nick }, { $set: { reacted_at: Math.floor(Date.now() / 1000) } });
        } else {
            coll.insertOne({ object_uri: cleanObj, emoji: em, sender_nick: nick, reacted_at: Math.floor(Date.now() / 1000) });
        }

        const { summary, totalCount, extendedUri } = getObjectReactions(cleanObj);
        res.setHeader('Location', encodeURI(extendedUri));
        res.json({
            status: 'ok',
            object: cleanObj,
            reaction: em,
            reactions: summary,
            total_count: totalCount,
            reactions_uri: extendedUri,
            redirect: extendedUri,
            redirect_uri: extendedUri
        });
    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

app.put('/api/reactions.php', (req, res) => {
    let targetUri = req.query.uri || req.query.object || req.body?.uri || req.body?.object || '';
    let emoji = req.query.emoji || req.body?.emoji || '';
    const sender = (req.body?.sender || req.query?.client || req.headers['x-user'] || 'anonymous').trim();

    if (targetUri) {
        const decoded = decodeURIComponent(targetUri).replace(/^\/+/, '');
        const match = decoded.match(/^(.*?)[Δ∆]reactions\/(.+)$/);
        if (match) {
            targetUri = match[1];
            if (!emoji) {
                emoji = match[2];
            }
        }
    }

    if (!targetUri) {
        return res.status(400).json({ error: 'Missing target object URI' });
    }

    const cleanObj = targetUri.replace(/[Δ∆]reactions(?:=.*)?$/i, '').trim();
    if (!emoji) emoji = '❤️';
    emoji = emoji.split('?')[0].trim();

    try {
        const coll = mongoDb.collection('object_reactions');
        const existing = coll.findOne({ object_uri: cleanObj, emoji: emoji, sender_nick: sender });
        if (existing) {
            coll.updateOne({ object_uri: cleanObj, emoji: emoji, sender_nick: sender }, { $set: { reacted_at: Math.floor(Date.now() / 1000) } });
        } else {
            coll.insertOne({ object_uri: cleanObj, emoji: emoji, sender_nick: sender, reacted_at: Math.floor(Date.now() / 1000) });
        }

        const { summary, totalCount, extendedUri } = getObjectReactions(cleanObj);
        const count = summary[emoji]?.count || 0;
        res.setHeader('Location', encodeURI(extendedUri));
        return res.json({
            count: count,
            redirect: extendedUri,
            redirect_uri: extendedUri,
            reactions_uri: extendedUri
        });
    } catch (e) {
        return res.status(500).json({ error: e.message });
    }
});

// Native HTTP PUT reaction support for ivc://objectΔreactions/<emoji> & comment URLs
app.use((req, res, next) => {
    if (req.method !== 'PUT') return next();

    const rawUrl = decodeURIComponent(req.originalUrl || req.url);
    const targetUri = req.query.uri || req.query.object || req.body?.uri || req.body?.object || rawUrl;
    const cleanTarget = targetUri.replace(/^\/+/, '');

    const match = cleanTarget.match(/^(.*?)[Δ∆]reactions\/(.+)$/);
    if (match) {
        let objectUri = match[1].trim();
        let emojiRaw = match[2].trim();
        if (emojiRaw.includes('?')) {
            emojiRaw = emojiRaw.split('?')[0];
        }
        const emoji = (emojiRaw || '❤️').trim();
        const sender = (req.body?.sender || req.query?.client || req.headers['x-user'] || 'anonymous').trim();

        try {
            const coll = mongoDb.collection('object_reactions');
            const existing = coll.findOne({ object_uri: objectUri, emoji: emoji, sender_nick: sender });
            if (existing) {
                coll.updateOne({ object_uri: objectUri, emoji: emoji, sender_nick: sender }, { $set: { reacted_at: Math.floor(Date.now() / 1000) } });
            } else {
                coll.insertOne({ object_uri: objectUri, emoji: emoji, sender_nick: sender, reacted_at: Math.floor(Date.now() / 1000) });
            }

            const { summary, totalCount, extendedUri } = getObjectReactions(objectUri);
            const count = summary[emoji]?.count || 0;
            res.setHeader('Location', encodeURI(extendedUri));
            return res.json({
                count: count,
                redirect: extendedUri,
                redirect_uri: extendedUri,
                reactions_uri: extendedUri
            });
        } catch (e) {
            return res.status(500).json({ error: e.message });
        }
    }

    next();
});

// Direct comment navigation / redirect to extended reaction representation
app.use((req, res, next) => {
    if (req.method !== 'GET') return next();
    const rawUrl = decodeURIComponent(req.originalUrl || req.url);
    const cleanTarget = rawUrl.replace(/^\/+/, '');
    if (cleanTarget.startsWith('ivc://:') || cleanTarget.startsWith('ivc://£:') || cleanTarget.match(/^ivc:\/\/[^/]+\/:/)) {
        const cleanObj = cleanTarget.replace(/[Δ∆]reactions(?:=.*)?$/i, '').trim();
        const { summary, totalCount, extendedUri } = getObjectReactions(cleanObj);
        res.setHeader('Location', encodeURI(extendedUri));
        if (req.headers.accept?.includes('application/json')) {
            return res.json({
                status: 'ok',
                object: cleanObj,
                reactions: summary,
                total_count: totalCount,
                redirect: extendedUri,
                redirect_uri: extendedUri,
                reactions_uri: extendedUri
            });
        }
        return res.redirect(302, encodeURI(extendedUri));
    }
    next();
});

// Fallback all API missing to 501
app.use('/api', (req, res) => {
    res.status(501).json({ error: 'Not yet migrated' });
});

app.use(express.static('public'));

// Fallback for SPA client-side routing
app.use((req, res) => {
    res.sendFile(path.resolve('public/index.html'));
});

app.listen(port, host, () => {
    console.log(`Server listening on port ${port}`);
});
