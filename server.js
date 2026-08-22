import express from 'express';
import path from 'path';
import net from 'net';
import fs from 'fs';
import os from 'os';
import crypto from 'crypto';
import { getPhp, processIrcCommand, mongoDb } from './php_engine.js';
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
    } catch (e) { }

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

const defaultVhost = process.env.SERVER_VHOST || os.hostname() || 'localhost';

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
const serverHost = hostObj.vhost;
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
                    
                    let isFirst = false;
                    if (!rooms.has(channel) || rooms.get(channel).peers.size === 0) {
                        isFirst = true;
                    }

                    // Add to rooms map to bridge SSE
                    if (!rooms.has(channel)) rooms.set(channel, { peers: new Map() });
                    rooms.get(channel).peers.set(`irc-${clientState.nick}`, {
                        write: (dataStr) => {
                            try {
                                const data = JSON.parse(dataStr.replace(/^data:\s*/, '').trim());
                                if (data.sender !== clientState.nick && data.message) {
                                    send(`:${data.sender}!user@localhost PRIVMSG ${channel} :${data.message}`);
                                }
                            } catch (e) { }
                        }
                    });

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
                            send(`:${channel} MODE ${channel} +q ${clientState.nick}`);
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
                        rooms.get(channel).peers.delete(`irc-${clientState.nick}`);
                    }
                    send(`:${clientState.nick}!${clientState.user}@localhost PART ${channel}`);
                });
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
                        const payload = { type: 'chat', sender: clientState.nick, message: msg };
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
                    } catch (e) { }
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
                    } catch (e) { }
                }
            } else if (cmd === 'QUIT') {
                socket.end();
            } else {
                // Fallback for unknown IRC commands, might be raw slash commands (e.g., NICKSERV, CHANSERV, etc)
                try {
                    const formattedMsg = `/${cmd.toLowerCase()} ${parts.slice(1).join(' ')}`;
                    const result = await processIrcCommand(clientState.nick, '', formattedMsg);
                    if (result && result.response) {
                        const serviceName = result.service || cmd;
                        send(`:${serviceName}!service@localhost PRIVMSG ${clientState.nick} :${result.response}`);
                    }
                } catch (e) { }
            }
        }
    });

    socket.on('close', () => {
        for (const chan of clientState.channels) {
            if (rooms.has(chan)) {
                rooms.get(chan).peers.delete(`irc-${clientState.nick}`);
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
