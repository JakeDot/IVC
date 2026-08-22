import { describe, it, expect, beforeAll, afterAll } from '@jest/globals';
import { spawn, ChildProcess } from 'child_process';
import * as http from 'http';

describe('IRC Channel Modes Test: +k', () => {
  let serverProcess: ChildProcess;
  const PORT = 18082;

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
          'Content-Length': data.length
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

  it('should allow C1 to join #c normally initially', async () => {
    const res = await fetchGet('/api/signal.php?room=%23c&client=C1&mode=sse');
    expect(res.status).toBe(200);
  });

  it('should allow C1 to set +k=key on #c', async () => {
    const res = await fetchPost('/api/irc.php', {
        channel: '#c',
        sender: 'C1',
        text: '/mode #c +k=key'
    });
    expect(res.status).toBe(200);
    const body = JSON.parse(res.data);
    expect(body.response).toContain('+k=key');
  });

  it('should deny C4 from joining #c without the key', async () => {
    const res = await fetchGet('/api/signal.php?room=%23c&client=C4&mode=sse');
    expect(res.status).toBe(475);
  });

  it('should allow C5 to join #c with the correct key', async () => {
    const res = await fetchGet('/api/signal.php?room=%23c%2Bk=key&client=C5&mode=sse');
    expect(res.status).toBe(200);
  });
});
