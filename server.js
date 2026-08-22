import express from 'express';
import path from 'path';
import net from 'net';
import fs from 'fs';
import os from 'os';
import crypto from 'crypto';
import { getPhp, processIrcCommand, mongoDb } from './php_engine.js';
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const bitbuffer = require('./public/assets/js/ivc.bitbuffer.js');
const { decompressTextMessage } = bitbuffer || {};
getPhp().then(() => console.log('PHP WASM Engine loaded and initialized.')).catch(console.error);


const app = express();
app.use(express.json());

const port = 3000;
const host = '0.0.0.0';

if (port !== 3000) {
    try { mongoDb.collection('chanserv_channels').deleteOne({ channel_name: '#c' }); } catch (e) { }
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
    } catch (e) { }
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
    } catch (e) { }
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

app.all('/api/signal.php', (req, res) => {
    const rawRoom = req.query.room || (req.body && req.body.room);
    const clientId = req.query.client || (req.body && (req.body.client || req.body.sender));
    const mode = req.query.mode;
    const type = (req.body && req.body.type) || req.query.type;
    const roomId = rawRoom;

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

            const isChatMessage = type === 'chat' || !!(req.body && (req.body.message || req.body.text));
            if (isVoiceRestricted && isChatMessage && !roleSatisfies(userRole, 'VOICE')) {
                return res.status(403).json({ error: 'Cannot send text message to channel (+v/+m) - Voice (+v) or operator (+o) required' });
            }
        }
    } catch (e) { }

    if (mode === 'sse') {
        res.writeHead(200, {
            'Content-Type': 'text/event-stream',
            'Cache-Control': 'no-cache',
            'Connection': 'keep-alive'
        });
        res.write(': connected\n\n');
        
        if (!rooms.has(roomId)) rooms.set(roomId, { peers: new Map() });
        rooms.get(roomId).peers.set(clientId, res);
        
        req.on('close', () => {
            if (rooms.has(roomId)) {
                rooms.get(roomId).peers.delete(clientId);
            }
        });
        return;
    }

    if (type === 'join' && req.body.nickname) {
        if (!rooms.has(roomId)) rooms.set(roomId, { peers: new Map() });
        const peer = rooms.get(roomId).peers.get(clientId);
        if (peer) {
            peer.nickname = req.body.nickname;
            
            // Send existing web users to the new user
            rooms.get(roomId).peers.forEach((otherPeer, otherId) => {
                if (otherId !== clientId && !otherId.startsWith('irc-')) {
                    const rRole = getUserChannelRole(baseRoom, otherPeer.nickname || otherId);
                    let prefix = '';
                    if (rRole === 'OWNER' || rRole === 'NETADMIN') prefix = '~';
                    else if (rRole === 'ADMIN') prefix = '&';
                    else if (rRole === 'OPERATOR' || rRole === 'OP') prefix = '@';
                    else if (rRole === 'VOICE') prefix = '+';
                    peer.write(`data: ${JSON.stringify({ type: 'join', sender: otherId, nickname: otherPeer.nickname || otherId, role: rRole, prefix: prefix })}\n\n`);
                }
            });
            // Send existing IRC users to the new user
            for (const [ircId, ircClient] of ircClients.entries()) {
                if (ircClient.registered && ircClient.channels.has(baseRoom)) {
                    const rRole = getUserChannelRole(baseRoom, ircClient.nick);
                    let prefix = '';
                    if (rRole === 'OWNER' || rRole === 'NETADMIN') prefix = '~';
                    else if (rRole === 'ADMIN') prefix = '&';
                    else if (rRole === 'OPERATOR' || rRole === 'OP') prefix = '@';
                    else if (rRole === 'VOICE') prefix = '+';
                    peer.write(`data: ${JSON.stringify({ type: 'join', sender: `irc-${ircClient.nick}`, nickname: ircClient.nick, role: rRole, prefix: prefix })}\n\n`);
                }
            }
        }
    }

    if (type === 'leave') {
        if (rooms.has(roomId)) {
            rooms.get(roomId).peers.delete(clientId);
        }
        return res.json({ status: 'left' });
    }

    let senderRole = 'MEMBER';
    try {
        if (clientId) {
            senderRole = getUserChannelRole(baseRoom, clientId);
        }
    } catch (e) {}
    
    const broadcastPayload = { ...req.body, role: senderRole };

    // Broadcast to Web peers
    if (rooms.has(roomId)) {
        rooms.get(roomId).peers.forEach((peerRes, peerId) => {
            if (peerId !== clientId) {
                peerRes.write(`data: ${JSON.stringify(broadcastPayload)}\n\n`);
            }
        });
    }

    // Bridge to IRC clients
    const nick = req.body.nickname || req.body.sender || clientId;
    if (type === 'join') {
        for (const [ircId, ircClient] of ircClients.entries()) {
            if (ircClient.registered && ircClient.channels.has(baseRoom)) {
                ircClient.socket.write(`:${nick}!${clientId}@web.client JOIN ${baseRoom}\r\n`);
            }
        }
        
        // Bootstrap Web client with existing IRC clients
        const peerRes = rooms.has(roomId) ? rooms.get(roomId).peers.get(clientId) : null;
        if (peerRes) {
            for (const [ircId, ircClient] of ircClients.entries()) {
                if (ircClient.registered && ircClient.channels.has(baseRoom)) {
                    const ircRole = getUserChannelRole(baseRoom, ircClient.nick);
                    let prefix = '';
                    if (ircRole === 'OWNER' || ircRole === 'NETADMIN') prefix = '~';
                    else if (ircRole === 'ADMIN') prefix = '&';
                    else if (ircRole === 'OPERATOR' || ircRole === 'OP') prefix = '@';
                    else if (ircRole === 'VOICE') prefix = '+';
                    
                    peerRes.write(`data: ${JSON.stringify({ type: 'join', nickname: ircClient.nick, sender: ircId, role: ircRole, prefix: prefix })}\n\n`);
                }
            }
        }
    } else if (type === 'leave') {
        for (const [ircId, ircClient] of ircClients.entries()) {
            if (ircClient.registered && ircClient.channels.has(baseRoom)) {
                ircClient.socket.write(`:${nick}!${clientId}@web.client PART ${baseRoom}\r\n`);
            }
        }
    } else if (type === 'chat') {
        for (const [ircId, ircClient] of ircClients.entries()) {
            if (ircClient.registered && ircClient.channels.has(baseRoom)) {
                ircClient.socket.write(`:${nick}!${clientId}@web.client PRIVMSG ${baseRoom} :${req.body.message || req.body.text}\r\n`);
            }
        }
    } else if (type === 'file') {
        const fileUrl = `https://${req.headers.host || 'localhost'}/file/${req.body.fileId}`;
        for (const [ircId, ircClient] of ircClients.entries()) {
            if (ircClient.registered && ircClient.channels.has(baseRoom)) {
                ircClient.socket.write(`:${nick}!${clientId}@web.client NOTICE ${baseRoom} :Shared a file: ${req.body.fileName} - ${fileUrl}\r\n`);
            }
        }
    } else if (type === 'whois') {
        const targetNick = req.body.target;
        let found = false;
        let html = '';
        
        for (const [ircId, ircClient] of ircClients.entries()) {
            if (ircClient.registered && ircClient.nick === targetNick) {
                found = true;
                const chans = Array.from(ircClient.channels).map(chan => {
                    const role = getUserChannelRole(chan, targetNick);
                    let prefix = '';
                    if (role === 'OWNER' || role === 'NETADMIN') prefix = '~';
                    else if (role === 'ADMIN') prefix = '&';
                    else if (role === 'OPERATOR' || role === 'OP') prefix = '@';
                    else if (role === 'VOICE') prefix = '+';
                    return prefix + chan;
                }).join(' ');
                
                html = `
                    <div style="padding: 20px; font-family: monospace; color: var(--text-color);">
                        <h2 style="margin-top: 0;">WHOIS: ${targetNick}</h2>
                        <p><strong>User:</strong> ${ircClient.user || targetNick}</p>
                        <p><strong>Host:</strong> localhost</p>
                        <p><strong>Server:</strong> IVC Server</p>
                        <p><strong>Channels:</strong> ${chans}</p>
                        <p><strong>Client Type:</strong> IRC Native Client</p>
                    </div>
                `;
                break;
            }
        }
        
        if (!found) {
            const webChans = [];
            for (const [chan, room] of rooms.entries()) {
                for (const [peerId, peer] of room.peers.entries()) {
                    if (!peerId.startsWith('irc-') && (peer.nickname === targetNick || peerId === targetNick)) {
                        found = true;
                        if (!webChans.includes(chan)) {
                            const role = getUserChannelRole(chan, targetNick);
                            let prefix = '';
                            if (role === 'OWNER' || role === 'NETADMIN') prefix = '~';
                            else if (role === 'ADMIN') prefix = '&';
                            else if (role === 'OPERATOR' || role === 'OP') prefix = '@';
                            else if (role === 'VOICE') prefix = '+';
                            webChans.push(prefix + chan);
                        }
                    }
                }
            }
            if (found) {
                html = `
                    <div style="padding: 20px; font-family: monospace; color: var(--text-color);">
                        <h2 style="margin-top: 0;">WHOIS: ${targetNick}</h2>
                        <p><strong>User:</strong> ${targetNick}</p>
                        <p><strong>Host:</strong> web.client</p>
                        <p><strong>Server:</strong> IVC Server</p>
                        <p><strong>Channels:</strong> ${webChans.join(' ')}</p>
                        <p><strong>Client Type:</strong> WebRTC Client</p>
                    </div>
                `;
            }
        }

        if (found) {
            const peerRes = rooms.has(roomId) ? rooms.get(roomId).peers.get(clientId) : null;
            if (peerRes) {
                peerRes.write(`data: ${JSON.stringify({ type: 'whois_response', target: targetNick, html: html })}\n\n`);
            }
            return res.json({ status: 'sent', role: senderRole });
        }
    }

    res.json({ status: 'sent', role: senderRole });
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
    } catch (e) { }

    try {
        let rows = [];
        if (mongoDb) {
            rows = mongoDb.collection('shared_files').find({ channel_name: { $regex: new RegExp(`^${baseRoom}$`, 'i') } });
        }
        return res.json({ status: 'ok', channel: baseRoom, files: rows });
    } catch (e) {
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

// --- IRC TCP Server ---
const ircClients = new Map();
let serverVersion = 'IVC-IRC/1.0';
try {
    const pkg = JSON.parse(fs.readFileSync('./package.json', 'utf8'));
    serverVersion = `IVC-IRC/${pkg.version}`;
} catch (e) { }

const defaultVhost = process.env.SERVER_VHOST || (os.hostname() ? `${os.hostname()}.ivc.cx` : 'localhost.ivc.cx');

let meAlias = mongoDb.collection('object_aliases').findOne({ alias_name: '$me' });
let serverGuid;

if (!meAlias) {
    serverGuid = crypto.randomUUID();
    mongoDb.collection('object_aliases').insertOne({
        alias_name: '$me',
        target_guid: serverGuid,
        object_type: '$'
    });
} else {
    serverGuid = meAlias.target_guid;
}

let hostObj = mongoDb.collection('ivc_objects').findOne({ guid: serverGuid });
if (!hostObj) {
    mongoDb.collection('ivc_objects').insertOne({ guid: serverGuid, vhost: defaultVhost });
} else if (!hostObj.vhost) {
    mongoDb.collection('ivc_objects').updateOne({ guid: serverGuid }, { $set: { vhost: defaultVhost } });
}
hostObj = mongoDb.collection('ivc_objects').findOne({ guid: serverGuid });

const ivcObjects = new Map();
ivcObjects.set('ivc://$me', hostObj);
const serverHost = hostObj.vhost.includes('.') ? hostObj.vhost : `${hostObj.vhost}.ivc.cx`;
const serverCreated = new Date().toUTCString();
const networkUri = process.env.NETWORK_URI || 'ivc://ivc.cx';

const ircServer = net.createServer((socket) => {
    console.log('IRC client connected');
    let buffer = '';
    const clientState = { nick: null, user: null, registered: false, channels: new Set() };
    ircClients.set(socket, clientState);

    const send = (msg) => {
        if (!socket.destroyed) socket.write(msg + '\r\n');
    };

    const sendWelcome = () => {
        send(`:${serverHost} 001 ${clientState.nick} :Welcome to the IVC IRC Network`);
        send(`:${serverHost} 002 ${clientState.nick} :Your host is ${serverHost}, running version ${serverVersion}`);
        send(`:${serverHost} 003 ${clientState.nick} :This server was started on ${serverCreated}`);
        send(`:${serverHost} 004 ${clientState.nick} ${serverHost} ${serverVersion} iowZ abeIikmMnoOqQRsStVv`);
        send(`:${serverHost} 005 ${clientState.nick} CHANTYPES=#&@£$ PREFIX=(qaohv)~&@%+ CHANMODES=b,k,l,imnpst NICKLEN=30 TOPICLEN=307 NETWORK=IVC :are supported by this server`);
        send(`:${serverHost} 006 ${clientState.nick} :Network ${networkUri}`);
        send(`:${serverHost} 376 ${clientState.nick} :End of /MOTD command.`);
    };

    socket.on('data', async (data) => {
        buffer += data.toString();
        const lines = buffer.split(/\r?\n/);
        buffer = lines.pop(); // keep incomplete line

        for (const line of lines) {
            if (!line.trim()) continue;
            console.log('IRC IN:', line);
            const parts = line.split(' ');
            const cmd = parts[0].toUpperCase();

            if (cmd === 'CAP') {
                send('CAP * LS :');
            } else if (cmd === 'NICK') {
                clientState.nick = parts[1];
                if (clientState.user && !clientState.registered) {
                    clientState.registered = true;
                    sendWelcome();
                }
            } else if (cmd === 'USER') {
                clientState.user = parts[1];
                if (clientState.nick && clientState.user) {
                    sendWelcome();
                    
                    if (clientState.identified) {
                        const cleanNick = (clientState.nick || '').split('@')[0].split(':')[0].trim();
                        const mNick = mongoDb.collection('nameserv_nicks').findOne({ nickname: { $regex: new RegExp(`^${cleanNick}$`, 'i') } });
                        if (mNick && mNick.saved_channels && mNick.saved_channels.length > 0) {
                            send(`:NameServ!service@localhost NOTICE ${clientState.nick} :You have saved channels. To rejoin, type: /join ${mNick.saved_channels.join(',')}`);
                        }
                    }
                }
            } else if (cmd === 'PING') {
                send(`PONG :${parts.slice(1).join(' ')}`);
            } else if (cmd === 'JOIN') {
                const channels = parts[1].split(',');
                channels.forEach(channel => {
                    clientState.channels.add(channel);

                    if (clientState.identified) {
                        const cleanNick = (clientState.nick || '').split('@')[0].split(':')[0].trim();
                        const mNick = mongoDb.collection('nameserv_nicks').findOne({ nickname: { $regex: new RegExp(`^${cleanNick}$`, 'i') } });
                        if (mNick) {
                            const chans = new Set(mNick.saved_channels || []);
                            chans.add(channel);
                            mongoDb.collection('nameserv_nicks').updateOne({ nickname: mNick.nickname }, { $set: { saved_channels: Array.from(chans) } });
                        }
                    }

                    send(`:${clientState.nick}!${clientState.user}@localhost JOIN ${channel}`);
                    for (const [s, c] of ircClients.entries()) {
                        if (c.registered && c.channels.has(channel) && s !== socket) {
                            s.write(`:${clientState.nick}!${clientState.user}@localhost JOIN ${channel}\r\n`);
                        }
                    }

                    let topic = null;
                    if (typeof mongoDb !== 'undefined' && mongoDb) {
                        try {
                            const mChan = mongoDb.collection('chanserv_channels').findOne({ channel: { $regex: new RegExp(`^${channel}$`, 'i') } });
                            if (mChan && mChan.description) topic = mChan.description;
                        } catch (e) {}
                    }
                    if (topic) {
                        send(`:${serverHost} 332 ${clientState.nick} ${channel} :${topic}`);
                    } else {
                        send(`:${serverHost} 331 ${clientState.nick} ${channel} :No topic is set`);
                    }
                    
                    let isFirst = false;
                    if (!rooms.has(channel) || rooms.get(channel).peers.size === 0) {
                        isFirst = true;
                    }

                    // Add channel if doesn't exist
                    if (!rooms.has(channel)) rooms.set(channel, { peers: new Map() });

                    // Broadcast to Web users that IRC user joined
                    const ircRole = getUserChannelRole(channel, clientState.nick);
                    let rolePrefix = '';
                    if (ircRole === 'OWNER' || ircRole === 'NETADMIN') rolePrefix = '~';
                    else if (ircRole === 'ADMIN') rolePrefix = '&';
                    else if (ircRole === 'OPERATOR' || ircRole === 'OP') rolePrefix = '@';
                    else if (ircRole === 'VOICE') rolePrefix = '+';

                    const joinMsg = JSON.stringify({ type: 'join', sender: `irc-${clientState.nick}`, nickname: clientState.nick, prefix: rolePrefix });
                    rooms.get(channel).peers.forEach((peer, peerId) => {
                        if (!peerId.startsWith('irc-')) {
                            peer.write(`data: ${joinMsg}\n\n`);
                        }
                    });

                    // Synthesize RPL_NAMREPLY
                    const names = [];
                    // Add Web clients
                    for (const [pid, peer] of rooms.get(channel).peers.entries()) {
                        if (!pid.startsWith('irc-')) {
                            const webNick = peer.nickname || pid;
                            const webRole = getUserChannelRole(channel, webNick);
                            let webPrefix = '';
                            if (webRole === 'OWNER' || webRole === 'NETADMIN') webPrefix = '~';
                            else if (webRole === 'ADMIN') webPrefix = '&';
                            else if (webRole === 'OPERATOR' || webRole === 'OP') webPrefix = '@';
                            else if (webRole === 'VOICE') webPrefix = '+';
                            names.push(webPrefix + webNick);
                        }
                    }
                    // Add IRC clients
                    for (const [ircId, ircClient] of ircClients.entries()) {
                        if (ircClient.registered && ircClient.channels.has(channel)) {
                            const iRole = getUserChannelRole(channel, ircClient.nick);
                            let iPrefix = '';
                            if (iRole === 'OWNER' || iRole === 'NETADMIN') iPrefix = '~';
                            else if (iRole === 'ADMIN') iPrefix = '&';
                            else if (iRole === 'OPERATOR' || iRole === 'OP') iPrefix = '@';
                            else if (iRole === 'VOICE') iPrefix = '+';
                            names.push(iPrefix + ircClient.nick);
                        }
                    }
                    send(`:${serverHost} 353 ${clientState.nick} = ${channel} :${names.join(' ')}`);
                    send(`:${serverHost} 366 ${clientState.nick} ${channel} :End of /NAMES list`);

                    if (isFirst) {
                        let giveFounder = true;
                        if (typeof mongoDb !== 'undefined' && mongoDb) {
                            try {
                                const chanReg = mongoDb.collection('chanserv_channels').findOne({ channel: { $regex: new RegExp(`^${channel}$`, 'i') } });
                                if (chanReg && chanReg.founder && chanReg.founder.toLowerCase() !== clientState.nick.toLowerCase()) {
                                    giveFounder = false;
                                }
                            } catch (e) {}
                        }
                        if (giveFounder) {
                            send(`:${serverHost} MODE ${channel} +q ${clientState.nick}`);
                        }
                    }
                });
            } else if (cmd === 'PART') {
                const channels = parts[1].split(',');
                channels.forEach(channel => {
                    clientState.channels.delete(channel);
                    
                    if (clientState.identified) {
                        const cleanNick = (clientState.nick || '').split('@')[0].split(':')[0].trim();
                        const mNick = mongoDb.collection('nameserv_nicks').findOne({ nickname: { $regex: new RegExp(`^${cleanNick}$`, 'i') } });
                        if (mNick) {
                            const chans = new Set(mNick.saved_channels || []);
                            chans.delete(channel);
                            mongoDb.collection('nameserv_nicks').updateOne({ nickname: mNick.nickname }, { $set: { saved_channels: Array.from(chans) } });
                        }
                    }

                    if (rooms.has(channel)) {
                        const partMsg = JSON.stringify({ type: 'leave', sender: `irc-${clientState.nick}`, nickname: clientState.nick });
                        rooms.get(channel).peers.forEach((peer, peerId) => {
                            if (!peerId.startsWith('irc-')) {
                                peer.write(`data: ${partMsg}\n\n`);
                            }
                        });
                    }
                    send(`:${clientState.nick}!${clientState.user}@localhost PART ${channel}`);
                });
            } else if (cmd === 'LIST') {
                send(`:${serverHost} 321 ${clientState.nick} Channel :Users  Name`);
                
                const allChannels = new Map(); // channelName -> { users: count, topic: string }
                
                for (const [chan, room] of rooms.entries()) {
                    if (!allChannels.has(chan)) allChannels.set(chan, { users: 0, topic: '' });
                    let webCount = 0;
                    for (const peerId of room.peers.keys()) {
                        if (!peerId.startsWith('irc-')) webCount++;
                    }
                    allChannels.get(chan).users += webCount;
                }
                
                for (const c of ircClients.values()) {
                    if (c.registered) {
                        for (const chan of c.channels) {
                            if (!allChannels.has(chan)) allChannels.set(chan, { users: 0, topic: '' });
                            allChannels.get(chan).users++;
                        }
                    }
                }
                
                if (!allChannels.has('$service')) allChannels.set('$service', { users: 0, topic: 'Network Services' });
                if (!allChannels.has('&opers')) allChannels.set('&opers', { users: 0, topic: 'Network Operators' });
                
                if (typeof mongoDb !== 'undefined' && mongoDb) {
                    try {
                        const registeredChans = mongoDb.collection('chanserv_channels').find({});
                        for (const rc of registeredChans) {
                            const cname = rc.channel || rc.channel_name;
                            if (cname && !allChannels.has(cname)) {
                                allChannels.set(cname, { users: 0, topic: rc.description || '' });
                            } else if (cname && allChannels.has(cname) && rc.description) {
                                allChannels.get(cname).topic = rc.description;
                            }
                        }
                    } catch (e) {}
                }
                
                for (const [chan, info] of allChannels.entries()) {
                    send(`:${serverHost} 322 ${clientState.nick} ${chan} ${info.users} :${info.topic}`);
                }
                
                send(`:${serverHost} 323 ${clientState.nick} :End of /LIST`);
            } else if (cmd === 'PRIVMSG') {
                const target = parts[1];
                const msg = parts.slice(2).join(' ').replace(/^:/, '');

                const isChannel = ['#', '&', '@', '£', '$'].includes(target.charAt(0));
                if (isChannel) {
                    // Channel message
                    // Broadcast to TCP
                    for (const [s, c] of ircClients.entries()) {
                        if (c.registered && c.channels.has(target) && s !== socket) {
                            s.write(`:${clientState.nick}!${clientState.user}@localhost PRIVMSG ${target} :${msg}\r\n`);
                        }
                    }
                    // Broadcast to SSE
                    if (rooms.has(target)) {
                        const senderRole = getUserChannelRole(target, clientState.nick);
                        const payload = { type: 'chat', sender: clientState.nick, message: msg, role: senderRole };
                        rooms.get(target).peers.forEach((peerRes, peerId) => {
                            if (!peerId.startsWith('irc-')) {
                                peerRes.write(`data: ${JSON.stringify(payload)}\n\n`);
                            }
                        });
                    }
                    // Pass to backend for commands
                    try {
                        const result = await processIrcCommand(clientState.nick, target, msg);
                        if (result && result.response) {
                            send(`:${result.service || 'service'}!service@localhost PRIVMSG ${target} :${result.response}`);
                        }
                    } catch (e) { console.error("Irc command channel error:", e); }
                } else {
                    // PM to service or user
                    // Broadcast to TCP if target is a real user
                    for (const [s, c] of ircClients.entries()) {
                        if (c.registered && c.nick === target && s !== socket) {
                            s.write(`:${clientState.nick}!${clientState.user}@localhost PRIVMSG ${target} :${msg}\r\n`);
                        }
                    }
                    try {
                        // Pretend it's a /msg for processIrcCommand
                        const formattedMsg = `/msg ${target} ${msg}`;
                        const result = await processIrcCommand(clientState.nick, target, formattedMsg);
                        if (result && result.response) {
                            const serviceName = result.service || target;
                            send(`:${serviceName}!service@localhost PRIVMSG ${clientState.nick} :${result.response}`);
                        }
                    } catch (e) { console.error("Irc command PM error:", e); }
                }
            } else if (cmd === 'WHOIS') {
                const targetNick = parts[1];
                let found = false;
                
                // Check IRC clients
                for (const c of ircClients.values()) {
                    if (c.registered && c.nick === targetNick) {
                        found = true;
                        send(`:${serverHost} 311 ${clientState.nick} ${targetNick} ${c.user || targetNick} localhost * :${c.user || targetNick}`);
                        
                        const chans = Array.from(c.channels).map(chan => {
                            const role = getUserChannelRole(chan, targetNick);
                            let prefix = '';
                            if (role === 'OWNER' || role === 'NETADMIN') prefix = '~';
                            else if (role === 'ADMIN') prefix = '&';
                            else if (role === 'OPERATOR' || role === 'OP') prefix = '@';
                            else if (role === 'VOICE') prefix = '+';
                            return prefix + chan;
                        }).join(' ');
                        
                        if (chans) {
                            send(`:${serverHost} 319 ${clientState.nick} ${targetNick} :${chans}`);
                        }
                        
                        send(`:${serverHost} 312 ${clientState.nick} ${targetNick} ${serverHost} :IVC Server`);
                        break;
                    }
                }
                
                // Check Web clients if not found
                if (!found) {
                    const webChans = [];
                    for (const [chan, room] of rooms.entries()) {
                        for (const [peerId, peer] of room.peers.entries()) {
                            if (!peerId.startsWith('irc-') && (peer.nickname === targetNick || peerId === targetNick)) {
                                found = true;
                                if (!webChans.includes(chan)) {
                                    const role = getUserChannelRole(chan, targetNick);
                                    let prefix = '';
                                    if (role === 'OWNER' || role === 'NETADMIN') prefix = '~';
                                    else if (role === 'ADMIN') prefix = '&';
                                    else if (role === 'OPERATOR' || role === 'OP') prefix = '@';
                                    else if (role === 'VOICE') prefix = '+';
                                    webChans.push(prefix + chan);
                                }
                            }
                        }
                    }
                    if (found) {
                        send(`:${serverHost} 311 ${clientState.nick} ${targetNick} ${targetNick} web.client * :Web User`);
                        if (webChans.length > 0) {
                            send(`:${serverHost} 319 ${clientState.nick} ${targetNick} :${webChans.join(' ')}`);
                        }
                        send(`:${serverHost} 312 ${clientState.nick} ${targetNick} ${serverHost} :IVC Server`);
                    }
                }
                
                if (found) {
                    send(`:${serverHost} 318 ${clientState.nick} ${targetNick} :End of /WHOIS list`);
                } else {
                    send(`:${serverHost} 401 ${clientState.nick} ${targetNick} :No such nick/channel`);
                }
            } else if (cmd === 'QUIT') {
                socket.end();
            } else {
                // Fallback for unknown IRC commands, might be raw slash commands (e.g., NICKSERV, CHANSERV, etc)
                try {
                    const formattedMsg = `/${cmd.toLowerCase()} ${parts.slice(1).join(' ')}`;
                    const result = await processIrcCommand(clientState.nick, '', formattedMsg);
                    if (result && result.response) {
                        if (cmd === 'MODE' && result.response.startsWith('Modes for')) {
                            const targetChan = parts[1];
                            const modesMatch = result.response.match(/Modes for [^:]+: (.*)/);
                            const modes = modesMatch ? modesMatch[1] : '+t';
                            send(`:${serverHost} 324 ${clientState.nick} ${targetChan} ${modes}`);
                        } else {
                            const serviceName = result.service || cmd;
                            send(`:${serviceName}!service@localhost PRIVMSG ${clientState.nick} :${result.response}`);
                        }
                    }
                    if (result && result.mode_broadcast && result.channel) {
                        const broadcastMsg = `:${clientState.nick}!${clientState.user}@localhost MODE ${result.channel} ${result.mode_broadcast}\r\n`;
                        for (const [s, c] of ircClients.entries()) {
                            if (c.registered && c.channels.has(result.channel)) {
                                s.write(broadcastMsg);
                            }
                        }
                        
                        // Also broadcast mode to web clients
                        if (rooms.has(result.channel)) {
                            const modeMsg = JSON.stringify({ type: 'chat', sender: 'SYSTEM', nickname: 'SYSTEM', message: `* MODE ${result.channel} ${result.mode_broadcast} by ${clientState.nick}` });
                            rooms.get(result.channel).peers.forEach((peer, peerId) => {
                                if (!peerId.startsWith('irc-')) {
                                    peer.write(`data: ${modeMsg}\n\n`);
                                }
                            });
                        }
                    }
                } catch (e) { }
            }
        }
    });

    socket.on('close', () => {
        for (const chan of clientState.channels) {
            if (rooms.has(chan)) {
                const partMsg = JSON.stringify({ type: 'leave', sender: `irc-${clientState.nick}`, nickname: clientState.nick });
                rooms.get(chan).peers.forEach((peer, peerId) => {
                    if (!peerId.startsWith('irc-')) {
                        peer.write(`data: ${partMsg}\n\n`);
                    }
                });
            }
        }
        ircClients.delete(socket);
        console.log('IRC client disconnected');
    });

    socket.on('error', (err) => console.log('IRC Error:', err.message));
});

ircServer.listen(6667, host, () => {
    console.log(`IRC Server listening on port 6667`);
});
