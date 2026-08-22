import { describe, it, expect, beforeAll, afterAll } from '@jest/globals';
import { spawn, ChildProcess } from 'child_process';
import * as http from 'http';

describe('IRC Object & Channel Modes Test: +r (Registered-Only Access)', () => {
  let serverProcess: ChildProcess;
  const PORT = 18083;

  beforeAll((done) => {
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

  it('should register a nickname RegUser via /register command', async () => {
    const res = await fetchPost('/api/irc.php', {
      channel: '#general',
      sender: 'RegUser',
      text: '/register SecretPass123 reg@example.com'
    });
    expect(res.status).toBe(200);
    const body = JSON.parse(res.data);
    expect(body.response).toContain('NAMESERV');
    expect(body.response).toContain('registered');
  });

  it('should set +r mode on #secure-vault', async () => {
    const res = await fetchPost('/api/irc.php', {
      channel: '#secure-vault',
      sender: 'RegUser',
      text: '/mode #secure-vault +r'
    });
    expect(res.status).toBe(200);
    const body = JSON.parse(res.data);
    expect(body.response).toContain('+r');
  });

  it('should deny UnregUser from accessing +r channel #secure-vault', async () => {
    const res = await fetchGet('/api/signal.php?room=%23secure-vault&client=UnregUser&mode=sse');
    expect(res.status).toBe(477);
  });

  it('should deny UnregUser from accessing any +r tagged object or URI room', async () => {
    const res = await fetchGet('/api/signal.php?room=%23custom-object%2Br&client=UnregUser&mode=sse');
    expect(res.status).toBe(477);
  });

  it('should allow RegUser to join +r channel #secure-vault', async () => {
    const res = await fetchGet('/api/signal.php?room=%23secure-vault&client=RegUser&mode=sse');
    expect(res.status).toBe(200);
  });

  it('should allow RegUser to join any +r tagged object room', async () => {
    const res = await fetchGet('/api/signal.php?room=%23custom-object%2Br&client=RegUser&mode=sse');
    expect(res.status).toBe(200);
  });

  it('should deny UnregUser from accessing files on +r channel', async () => {
    const res = await fetchGet('/api/files.php?channel=%23secure-vault&client=UnregUser');
    expect(res.status).toBe(477);
  });

  it('should allow RegUser to access files on +r channel', async () => {
    const res = await fetchGet('/api/files.php?channel=%23secure-vault&client=RegUser');
    expect(res.status).toBe(200);
  });
});
