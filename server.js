import express from 'express';
import path from 'path';
import { getPhp, processIrcCommand, mongoDb } from './php_engine.js';
getPhp().then(() => console.log('PHP WASM Engine loaded and initialized.')).catch(console.error);


const app = express();
app.use(express.json());

const port = 3000;
const host = '0.0.0.0';

if (port !== 3000) {
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
        if (mongoDb) {
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
        if (mongoDb) {
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
        let rows = [];
        if (mongoDb) {
            rows = mongoDb.collection('shared_files').find({ channel_name: { $regex: new RegExp(`^${baseRoom}$`, 'i') } });
        }
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
