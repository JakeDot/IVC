import { describe, it, expect, beforeAll, afterAll } from '@jest/globals';
import { spawn, ChildProcess } from 'child_process';
import * as http from 'http';
// @ts-ignore
import Database from 'better-sqlite3';

describe('IRC Channel Modes & Role Hierarchy: +i, +v, +a, +AON, Roles (+v/+o/+a/+n)', () => {
  let serverProcess: ChildProcess;
  const PORT = 18090;

  beforeAll((done) => {
    try {
      const db = new Database('./data/ivc_irc.sqlite');
      db.prepare("DELETE FROM chanserv_channels WHERE channel_name IN ('#modechan', '#ident-channel')").run();
      db.prepare("DELETE FROM channel_users WHERE channel_name IN ('#modechan', '#ident-channel')").run();
      db.prepare("DELETE FROM nameserv_nicks WHERE nickname IN ('AliceOwner', 'BobUser', 'CharlieUser', 'DaveUnreg', 'DaveUnvoiced')").run();
      db.close();
    } catch(e) {}
    serverProcess = spawn('node', ['server.js'], { env: { ...process.env, PORT: String(PORT) } });
    setTimeout(done, 6000);
  }, 10000);

  afterAll(() => {
    if (serverProcess) {
      serverProcess.kill();
    }
  });

  const fetchPost = (path: string, bodyObj: any) => {
    return new Promise<{ status: number, data: string }>((resolve, reject) => {
      const data = JSON.stringify(bodyObj);
      const req = http.request({
        hostname: '127.0.0.1',
        port: PORT,
        path: path,
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Content-Length': Buffer.byteLength(data)
        }
      }, (res) => {
        let body = '';
        res.on('data', d => body += d);
        res.on('end', () => resolve({ status: res.statusCode || 500, data: body }));
      });
      req.on('error', reject);
      req.write(data);
      req.end();
    });
  };

  const fetchGet = (path: string) => {
    return new Promise<{ status: number, data: string }>((resolve, reject) => {
      const req = http.request({
        hostname: '127.0.0.1',
        port: PORT,
        path: path,
        method: 'GET'
      }, (res) => {
        let body = '';
        res.on('data', d => body += d);
        res.on('end', () => resolve({ status: res.statusCode || 500, data: body }));
        if (path.includes('mode=sse')) {
            req.destroy();
            resolve({ status: res.statusCode || 500, data: '' });
        }
      });
      req.on('error', reject);
      req.end();
    });
  };

  it('should register users and channels for testing', async () => {
    // Register Alice
    let res = await fetchPost('/api/irc.php', {
      channel: '#lobby',
      sender: 'AliceOwner',
      text: '/register Pass123 alice@example.com'
    });
    expect(res.status).toBe(200);

    // Register Bob
    res = await fetchPost('/api/irc.php', {
      channel: '#lobby',
      sender: 'BobUser',
      text: '/register Pass123 bob@example.com'
    });
    expect(res.status).toBe(200);

    // Register Charlie
    res = await fetchPost('/api/irc.php', {
      channel: '#lobby',
      sender: 'CharlieUser',
      text: '/register Pass123 charlie@example.com'
    });
    expect(res.status).toBe(200);

    // Alice registers channel #modechan
    res = await fetchPost('/api/irc.php', {
      channel: '#modechan',
      sender: 'AliceOwner',
      text: '/msg CHANSERV REGISTER #modechan'
    });
    expect(res.status).toBe(200);
    const body = JSON.parse(res.data);
    expect(body.response).toContain('registered');
  });

  it('should enforce +i (IDENTified only) mode', async () => {
    // Set +i on #ident-channel
    let res = await fetchPost('/api/irc.php', {
      channel: '#ident-channel',
      sender: 'AliceOwner',
      text: '/mode #ident-channel +i'
    });
    expect(res.status).toBe(200);

    // Unregistered Dave tries to join -> 477
    let joinRes = await fetchGet('/api/signal.php?room=%23ident-channel&client=DaveUnreg&mode=sse');
    expect(joinRes.status).toBe(477);

    // AliceOwner identifies
    res = await fetchPost('/api/irc.php', {
      channel: '#lobby',
      sender: 'AliceOwner',
      text: '/msg NAMESERV IDENTIFY Pass123'
    });
    expect(res.status).toBe(200);

    // AliceOwner now joins -> 200
    joinRes = await fetchGet('/api/signal.php?room=%23ident-channel&client=AliceOwner&mode=sse');
    expect(joinRes.status).toBe(200);
  });

  it('should manage roles (+v, +a, +n) and role hierarchy', async () => {
    // Alice promotes Bob to Voice (+v)
    let res = await fetchPost('/api/irc.php', {
      channel: '#modechan',
      sender: 'AliceOwner',
      text: '/voice BobUser #modechan'
    });
    expect(res.status).toBe(200);
    let body = JSON.parse(res.data);
    expect(body.response).toContain('+v');

    // Alice promotes Charlie to Admin (+a)
    res = await fetchPost('/api/irc.php', {
      channel: '#modechan',
      sender: 'AliceOwner',
      text: '/admin CharlieUser #modechan'
    });
    expect(res.status).toBe(200);
    body = JSON.parse(res.data);
    expect(body.response).toContain('+a');
  });

  it('should enforce +v (video-only: text messages blocked for non-+v users) mode', async () => {
    // Set +v on #modechan
    let res = await fetchPost('/api/irc.php', {
      channel: '#modechan',
      sender: 'AliceOwner',
      text: '/mode #modechan +v'
    });
    expect(res.status).toBe(200);

    // DaveUnvoiced sends chat message via signal.php -> 403
    let sigRes = await fetchPost('/api/signal.php', {
      room: '#modechan',
      client: 'DaveUnvoiced',
      type: 'chat',
      message: 'Hello everyone'
    });
    expect(sigRes.status).toBe(403);

    // BobUser (who has +v) sends chat message -> 200
    sigRes = await fetchPost('/api/signal.php', {
      room: '#modechan',
      client: 'BobUser',
      type: 'chat',
      message: 'Hello from voiced user'
    });
    expect(sigRes.status).toBe(200);
  });

  it('should enforce +A mode (requires at least +a channel admin)', async () => {
    // Set +A on #admin-room
    let res = await fetchPost('/api/irc.php', {
      channel: '#modechan',
      sender: 'AliceOwner',
      text: '/mode #modechan +A'
    });
    expect(res.status).toBe(200);

    // Bob (Voice only) tries to join -> 473
    let joinRes = await fetchGet('/api/signal.php?room=%23modechan&client=BobUser&mode=sse');
    expect(joinRes.status).toBe(473);

    // Charlie (Admin) tries to join -> 200
    joinRes = await fetchGet('/api/signal.php?room=%23modechan&client=CharlieUser&mode=sse');
    expect(joinRes.status).toBe(200);

    // Alice (Owner / NetAdmin) joins -> 200 (hierarchy allows NetAdmin for +A)
    joinRes = await fetchGet('/api/signal.php?room=%23modechan&client=AliceOwner&mode=sse');
    expect(joinRes.status).toBe(200);
  });

  it('should enforce +O mode (requires at least +o channel operator)', async () => {
    // Remove +A, Set +O on #modechan
    let res = await fetchPost('/api/irc.php', {
      channel: '#modechan',
      sender: 'AliceOwner',
      text: '/mode #modechan -A+O'
    });
    expect(res.status).toBe(200);

    // Dave (no role) tries to join -> 473
    let joinRes = await fetchGet('/api/signal.php?room=%23modechan&client=DaveUnvoiced&mode=sse');
    expect(joinRes.status).toBe(473);

    // Charlie (Admin > Op) joins -> 200
    joinRes = await fetchGet('/api/signal.php?room=%23modechan&client=CharlieUser&mode=sse');
    expect(joinRes.status).toBe(200);
  });

  it('should enforce +N mode (requires at least +n network admin / owner)', async () => {
    // Set +N on #modechan
    let res = await fetchPost('/api/irc.php', {
      channel: '#modechan',
      sender: 'AliceOwner',
      text: '/mode #modechan -O+N'
    });
    expect(res.status).toBe(200);

    // Charlie (Admin only, not NetAdmin) tries to join -> 473
    let joinRes = await fetchGet('/api/signal.php?room=%23modechan&client=CharlieUser&mode=sse');
    expect(joinRes.status).toBe(473);

    // Alice (Owner) joins -> 200
    joinRes = await fetchGet('/api/signal.php?room=%23modechan&client=AliceOwner&mode=sse');
    expect(joinRes.status).toBe(200);
  });
});
