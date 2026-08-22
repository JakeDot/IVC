import express from 'express';
import path from 'path';
import { getPhp, processIrcCommand, mongoDb, db } from './php_engine.js';
getPhp().then(() => console.log('PHP WASM Engine loaded and initialized.')).catch(console.error);


const app = express();
app.use(express.json());

const port = 3000;
const host = '0.0.0.0';

if (port !== 3000) {
    try { db.prepare("DELETE FROM chanserv_channels WHERE channel_name = '#c'").run(); } catch(e) {}
    try { mongoDb.collection('chanserv_channels').deleteOne({ channel_name: '#c' }); } catch(e) {}
}

// In-memory data
const rooms = new Map(); // roomId -> { peers: Map<clientId, { response: res }> }

app.get('/api/config.php', (req, res) => {
    res.json({ stun: [], turn: [] });
});

app.get('/api/stats.php', (req, res) => {
    res.json({ db_status: 'Mocked', active_rooms: rooms.size });
});

function getUserIdentification(nickname) {
    const cleanNick = (nickname || '').split('@')[0].split(':')[0].trim();
    if (!cleanNick) return { registered: false, identified: false };
    try {
        const uRow = db.prepare('SELECT nickname, is_identified FROM nameserv_nicks WHERE LOWER(nickname) = LOWER(?)').get(cleanNick);
        if (uRow) {
            return {
                registered: true,
                identified: uRow.is_identified === 1 || uRow.is_identified === true
            };
        }
        if (mongoDb) {
            const mNick = mongoDb.collection('nameserv_nicks').findOne({ nickname: { $regex: new RegExp(`^${cleanNick}$`, 'i') } });
            if (mNick) {
                return {
                    registered: true,
                    identified: mNick.is_identified === 1 || mNick.is_identified === true
                };
            }
        }
    } catch (e) {}
    return { registered: false, identified: false };
}

function getUserChannelRole(channel, nickname) {
    const baseChan = (channel || '').split('+')[0];
    const cleanNick = (nickname || '').split('@')[0].split(':')[0].trim();
    if (!baseChan || !cleanNick) return 'NONE';

    try {
        const cRow = db.prepare('SELECT owner_nick FROM chanserv_channels WHERE LOWER(channel_name) = LOWER(?)').get(baseChan);
        if (cRow && cRow.owner_nick && cRow.owner_nick.toLowerCase() === cleanNick.toLowerCase()) {
            return 'NETADMIN';
        }
        const uRow = db.prepare('SELECT role FROM channel_users WHERE LOWER(channel_name) = LOWER(?) AND LOWER(nickname) = LOWER(?) ORDER BY added_at DESC LIMIT 1').get(baseChan, cleanNick);
        if (uRow && uRow.role) {
            return uRow.role.toUpperCase();
        }
        if (mongoDb) {
            const mChan = mongoDb.collection('chanserv_channels').findOne({ channel_name: { $regex: new RegExp(`^${baseChan}$`, 'i') } });
            if (mChan && mChan.owner_nick && mChan.owner_nick.toLowerCase() === cleanNick.toLowerCase()) {
                return 'NETADMIN';
            }
            const mUser = mongoDb.collection('channel_users').findOne({
                channel_name: { $regex: new RegExp(`^${baseChan}$`, 'i') },
                nickname: { $regex: new RegExp(`^${cleanNick}$`, 'i') }
            });
            if (mUser && mUser.role) {
                return mUser.role.toUpperCase();
            }
        }
    } catch (e) {}
    return 'MEMBER';
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
        const row = db.prepare('SELECT modes FROM chanserv_channels WHERE LOWER(channel_name) = LOWER(?)').get(baseRoom);
        if (row && row.modes) {
            channelModes = row.modes;
        } else {
            const mDoc = mongoDb.collection('chanserv_channels').findOne({ channel_name: { $regex: new RegExp(`^${baseRoom}$`, 'i') } });
            if (mDoc && mDoc.modes) channelModes = mDoc.modes;
        }

        if (channelModes) {
            const km = channelModes.match(/(?:\+)?k=([^&+]+)/);
            if (km) {
                const requiredKey = km[1];
                if (providedKey !== requiredKey) {
                    return res.status(475).json({ error: 'Cannot join channel (+k) - Key required' });
                }
            }
        }

        const isRegisteredOnly = !!(rawRoom.match(/(?:\+)[a-zA-Z0-9_\$Δ\-]*r/i) || (channelModes && channelModes.match(/(?:\+|^)[a-zA-Z0-9_\$Δ\-]*r/i)));
        const isIdentifiedOnly = !!(rawRoom.match(/(?:\+)[a-zA-Z0-9_\$Δ\-]*i/i) || (channelModes && channelModes.match(/(?:\+|^)[a-zA-Z0-9_\$Δ\-]*i/i)));
        const isAdminOnly = !!(rawRoom.match(/(?:\+)[a-zA-Z0-9_\$Δ\-]*A/) || (channelModes && channelModes.match(/(?:\+|^)[a-zA-Z0-9_\$Δ\-]*A/)));
        const isOpOnly = !!(rawRoom.match(/(?:\+)[a-zA-Z0-9_\$Δ\-]*O/) || (channelModes && channelModes.match(/(?:\+|^)[a-zA-Z0-9_\$Δ\-]*O/)));
        const isNetAdminOnly = !!(rawRoom.match(/(?:\+)[a-zA-Z0-9_\$Δ\-]*N/) || (channelModes && channelModes.match(/(?:\+|^)[a-zA-Z0-9_\$Δ\-]*N/)));

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
        
        if (!rooms.has(baseRoom)) {
            rooms.set(baseRoom, { peers: new Map() });
        }
        rooms.get(baseRoom).peers.set(clientId, res);
        
        req.on('close', () => {
            if (rooms.has(baseRoom)) {
                rooms.get(baseRoom).peers.delete(clientId);
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
        const row = db.prepare('SELECT modes FROM chanserv_channels WHERE LOWER(channel_name) = LOWER(?)').get(baseRoom);
        if (row && row.modes) {
            channelModes = row.modes;
        } else if (mongoDb) {
            const mDoc = mongoDb.collection('chanserv_channels').findOne({ channel_name: { $regex: new RegExp(`^${baseRoom}$`, 'i') } });
            if (mDoc && mDoc.modes) channelModes = mDoc.modes;
        }

        const isRegisteredOnly = !!(roomId.match(/(?:\+)[a-zA-Z0-9_\$Δ\-]*r/i) || (channelModes && channelModes.match(/(?:\+|^)[a-zA-Z0-9_\$Δ\-]*r/i)));
        const isIdentifiedOnly = !!(roomId.match(/(?:\+)[a-zA-Z0-9_\$Δ\-]*i/i) || (channelModes && channelModes.match(/(?:\+|^)[a-zA-Z0-9_\$Δ\-]*i/i)));
        const isAdminOnly = !!(roomId.match(/(?:\+)[a-zA-Z0-9_\$Δ\-]*A/) || (channelModes && channelModes.match(/(?:\+|^)[a-zA-Z0-9_\$Δ\-]*A/)));
        const isOpOnly = !!(roomId.match(/(?:\+)[a-zA-Z0-9_\$Δ\-]*O/) || (channelModes && channelModes.match(/(?:\+|^)[a-zA-Z0-9_\$Δ\-]*O/)));
        const isNetAdminOnly = !!(roomId.match(/(?:\+)[a-zA-Z0-9_\$Δ\-]*N/) || (channelModes && channelModes.match(/(?:\+|^)[a-zA-Z0-9_\$Δ\-]*N/)));
        const isVoiceRestricted = !!(roomId.match(/(?:\+)[a-zA-Z0-9_\$Δ\-]*[vm]/i) || (channelModes && channelModes.match(/(?:\+|^)[a-zA-Z0-9_\$Δ\-]*[vm]/i)));

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
        if (rooms.has(roomId)) {
            rooms.get(roomId).peers.delete(clientId);
        }
        return res.json({ status: 'left' });
    }
    
    // Broadcast
    const broadcastPayload = { ...req.body };
    if (!broadcastPayload.sender && clientId) {
        broadcastPayload.sender = clientId;
    }

    if (rooms.has(roomId)) {
        rooms.get(roomId).peers.forEach((peerRes, peerId) => {
            if (peerId !== clientId) {
                peerRes.write(`data: ${JSON.stringify(broadcastPayload)}\n\n`);
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

    try {
        let channelModes = '';
        const row = db.prepare('SELECT modes FROM chanserv_channels WHERE LOWER(channel_name) = LOWER(?)').get(baseRoom);
        if (row && row.modes) channelModes = row.modes;
        else if (mongoDb) {
            const mDoc = mongoDb.collection('chanserv_channels').findOne({ channel_name: { $regex: new RegExp(`^${baseRoom}$`, 'i') } });
            if (mDoc && mDoc.modes) channelModes = mDoc.modes;
        }

        const isRegisteredOnly = !!(rawChannel.match(/(?:\+)[a-zA-Z0-9_\$Δ\-]*r/i) || (channelModes && channelModes.match(/(?:\+|^)[a-zA-Z0-9_\$Δ\-]*r/i)));
        const isIdentifiedOnly = !!(rawChannel.match(/(?:\+)[a-zA-Z0-9_\$Δ\-]*i/i) || (channelModes && channelModes.match(/(?:\+|^)[a-zA-Z0-9_\$Δ\-]*i/i)));
        const isAdminOnly = !!(rawChannel.match(/(?:\+)[a-zA-Z0-9_\$Δ\-]*A/) || (channelModes && channelModes.match(/(?:\+|^)[a-zA-Z0-9_\$Δ\-]*A/)));
        const isOpOnly = !!(rawChannel.match(/(?:\+)[a-zA-Z0-9_\$Δ\-]*O/) || (channelModes && channelModes.match(/(?:\+|^)[a-zA-Z0-9_\$Δ\-]*O/)));
        const isNetAdminOnly = !!(rawChannel.match(/(?:\+)[a-zA-Z0-9_\$Δ\-]*N/) || (channelModes && channelModes.match(/(?:\+|^)[a-zA-Z0-9_\$Δ\-]*N/)));

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
        const rows = db.prepare('SELECT id, channel_name, sharer_client_id, encrypted_metadata, cloud_link, created_at FROM shared_files WHERE LOWER(channel_name) = LOWER(?)').all(baseRoom);
        return res.json({ status: 'ok', channel: baseRoom, files: rows });
    } catch(e) {
        return res.json({ status: 'ok', channel: baseRoom, files: [] });
    }
});
app.post('/api/files.php', (req, res) => {
    res.json({ success: true });
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
