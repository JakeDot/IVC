import { describe, it, expect, beforeAll, afterAll } from '@jest/globals';
import { spawn, ChildProcess } from 'child_process';
import * as http from 'http';

describe('IRC Penetration Tests', () => {
  let serverProcess: ChildProcess;

  beforeAll((done) => {
    serverProcess = spawn('node', ['server.js'], { env: { ...process.env, PORT: '18081' } });
    setTimeout(done, 1500);
  });

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
        port: 18081,
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

  it('should process IRC service commands correctly', async () => {
    const payload = {
      channel: '#fortress',
      sender: '@ai.studio',
      text: '/msg NAMESERV REGISTER secret'
    };

    const res = await fetchPost('/api/irc.php', payload);
    const body = JSON.parse(res.data);

    expect(res.status).toBe(200);
    
    expect(body.is_service_command).toBe(true);
    expect(body.service).toBe('NAMESERV');
  });

  it('should auto-ident and standardize username in user@domain format and allow custom domain substitution', async () => {
    // 1. Test /ident without args (defaults to <anonymous> or domain)
    const res1 = await fetchPost('/api/irc.php', {
      channel: '#fortress',
      sender: '@ai.studio',
      text: '/ident'
    });
    const body1 = JSON.parse(res1.data);
    expect(res1.status).toBe(200);
    expect(body1.is_service_command).toBe(true);
    expect(body1.response).toContain('IDENT:');

    // 2. Test /msg NICKSERV SET §domain=custom.test.com
    const res2 = await fetchPost('/api/irc.php', {
      channel: '#fortress',
      sender: '@ai.studio',
      text: '/msg NICKSERV SET §domain=custom.test.com'
    });
    const body2 = JSON.parse(res2.data);
    expect(res2.status).toBe(200);
    expect(body2.response).toContain('§domain set to \'custom.test.com\'');

    // 3. Test /whois showing domain and standardized username
    const res3 = await fetchPost('/api/irc.php', {
      channel: '#fortress',
      sender: '@ai.studio',
      text: '/whois @ai.studio'
    });
    const body3 = JSON.parse(res3.data);
    expect(res3.status).toBe(200);
    expect(body3.response).toContain('Domain: custom.test.com');
    expect(body3.response).toContain('§domain: custom.test.com');
    expect(body3.response).toContain('Standardized Username: @ai.studio@custom.test.com');

    // 4. Test /ident with custom user@domain substitution
    const res4 = await fetchPost('/api/irc.php', {
      channel: '#fortress',
      sender: '@ai.studio',
      text: '/ident testuser@cyber.domain.org'
    });
    const body4 = JSON.parse(res4.data);
    expect(res4.status).toBe(200);
    expect(body4.response).toContain('testuser@cyber.domain.org');

    // 5. Test /who showing standardized format
    const res5 = await fetchPost('/api/irc.php', {
      channel: '#fortress',
      sender: '@ai.studio',
      text: '/who testuser'
    });
    const body5 = JSON.parse(res5.data);
    expect(res5.status).toBe(200);
    expect(body5.response).toContain('testuser@cyber.domain.org');
  });
});
