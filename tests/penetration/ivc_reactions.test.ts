import { describe, it, expect, beforeAll, afterAll } from '@jest/globals';
import { spawn, ChildProcess } from 'child_process';
import * as http from 'http';
import * as fs from 'fs';

describe('IVC Reactions: ❤️, <emoji>, HEART & ivc://objectΔreactions Metadata', () => {
  let serverProcess: ChildProcess;
  const PORT = 18095;

  beforeAll((done) => {
    try {
      if (fs.existsSync(`./data/mongodb_store_${PORT}.json`)) {
        fs.unlinkSync(`./data/mongodb_store_${PORT}.json`);
      }
    } catch (e) {}
    serverProcess = spawn('node', ['server.js'], { env: { ...process.env, PORT: String(PORT) } });
    let ready = false;
    serverProcess.stdout?.on('data', (d) => {
      if (d.toString().includes('PHP WASM Engine loaded and initialized') && !ready) {
        ready = true;
        done();
      }
    });
    setTimeout(() => { if (!ready) { ready = true; done(); } }, 15000);
  }, 20000);

  afterAll((done) => {
    try {
      if (fs.existsSync(`./data/mongodb_store_${PORT}.json`)) {
        fs.unlinkSync(`./data/mongodb_store_${PORT}.json`);
      }
    } catch (e) {}
    if (serverProcess) {
      serverProcess.on('exit', () => done());
      serverProcess.kill();
    } else {
      done();
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
      });
      req.on('error', reject);
      req.end();
    });
  };

  const fetchPut = (path: string, bodyObj?: any) => {
    return new Promise<{ status: number, data: string }>((resolve, reject) => {
      const data = bodyObj ? JSON.stringify(bodyObj) : '';
      const req = http.request({
        hostname: '127.0.0.1',
        port: PORT,
        path: path,
        method: 'PUT',
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
      if (data) req.write(data);
      req.end();
    });
  };

  it('should apply ❤️ reaction to addressable object ivc://object/:comment-101', async () => {
    const res = await fetchPost('/api/irc.php', {
      channel: '#lobby',
      sender: 'Alice',
      text: '❤️ ivc://object/:comment-101'
    });

    expect(res.status).toBe(200);
    const body = JSON.parse(res.data);
    expect(body.is_service_command).toBe(true);
    expect(body.service).toBe('REACTSERV');
    expect(body.reaction).toBe('❤️');
    expect(body.object).toBe('ivc://object/:comment-101');
    expect(body.reactions_uri).toContain('ivc://object/:comment-101Δreactions');
    expect(body.data.total_count).toBe(1);
    expect(body.data.reactions['❤️'].count).toBe(1);
    expect(body.data.reactions['❤️'].users).toContain('Alice');
  });

  it('should apply HEART reaction keyword to addressable object', async () => {
    const res = await fetchPost('/api/irc.php', {
      channel: '#lobby',
      sender: 'Bob',
      text: 'HEART ivc://object/:comment-101'
    });

    expect(res.status).toBe(200);
    const body = JSON.parse(res.data);
    expect(body.is_service_command).toBe(true);
    expect(body.reaction).toBe('❤️');
    expect(body.data.total_count).toBe(2);
    expect(body.data.reactions['❤️'].count).toBe(2);
    expect(body.data.reactions['❤️'].users).toEqual(expect.arrayContaining(['Alice', 'Bob']));
  });

  it('should support other Unicode emojis (🔥, 👍, 🎉)', async () => {
    // Charlie reacts with 🔥
    let res = await fetchPost('/api/irc.php', {
      channel: '#lobby',
      sender: 'Charlie',
      text: '🔥 ivc://object/:comment-101'
    });
    expect(res.status).toBe(200);
    let body = JSON.parse(res.data);
    expect(body.reaction).toBe('🔥');
    expect(body.data.total_count).toBe(3);

    // Dave reacts with 👍
    res = await fetchPost('/api/irc.php', {
      channel: '#lobby',
      sender: 'Dave',
      text: '👍 ivc://object/:comment-101'
    });
    expect(res.status).toBe(200);
    body = JSON.parse(res.data);
    expect(body.reaction).toBe('👍');
    expect(body.data.total_count).toBe(4);

    // Eve reacts to a different object with 🎉
    res = await fetchPost('/api/irc.php', {
      channel: '#lobby',
      sender: 'Eve',
      text: '🎉 ivc://object/:comment-202'
    });
    expect(res.status).toBe(200);
    body = JSON.parse(res.data);
    expect(body.reaction).toBe('🎉');
    expect(body.object).toBe('ivc://object/:comment-202');
    expect(body.data.total_count).toBe(1);
  });

  it('should query object reactions metadata via GET /api/reactions.php', async () => {
    const res = await fetchGet('/api/reactions.php?uri=ivc://object/:comment-101%CE%94reactions');
    expect(res.status).toBe(200);
    const body = JSON.parse(res.data);
    expect(body.status).toBe('ok');
    expect(body.object).toBe('ivc://object/:comment-101');
    expect(body.total_count).toBe(4);
    expect(body.reactions['❤️'].count).toBe(2);
    expect(body.reactions['🔥'].count).toBe(1);
    expect(body.reactions['👍'].count).toBe(1);
  });

  it('should query object reactions via /msg REACTSERV LIST', async () => {
    const res = await fetchPost('/api/irc.php', {
      channel: '#lobby',
      sender: 'Alice',
      text: '/msg REACTSERV LIST ivc://object/:comment-101'
    });

    expect(res.status).toBe(200);
    const body = JSON.parse(res.data);
    expect(body.is_service_command).toBe(true);
    expect(body.service).toBe('REACTSERV');
    expect(body.response).toContain('Total: 4');
    expect(body.response).toContain('❤️: 2');
    expect(body.response).toContain('🔥: 1');
    expect(body.response).toContain('👍: 1');
  });

  it('should support /react and /heart command shortcuts', async () => {
    const res = await fetchPost('/api/irc.php', {
      channel: '#lobby',
      sender: 'Frank',
      text: '/react 🚀 ivc://object/:comment-101'
    });

    expect(res.status).toBe(200);
    const body = JSON.parse(res.data);
    expect(body.reaction).toBe('🚀');
    expect(body.data.total_count).toBe(5);
    expect(body.data.reactions['🚀'].count).toBe(1);
    expect(body.data.reactions['🚀'].users).toContain('Frank');
  });

  it('should support native HTTP PUT reaction to ivc://objectΔreactions/<emoji> returning { count: count }', async () => {
    // 1. PUT to ivc://object/:comment-303Δreactions/❤️
    let res = await fetchPut('/ivc://object/:comment-303%CE%94reactions/%E2%9D%A4%EF%B8%8F', { sender: 'Grace' });
    expect(res.status).toBe(200);
    let body = JSON.parse(res.data);
    expect(body.count).toBe(1);

    // 2. PUT again from another user to the same object & emoji -> count becomes 2
    res = await fetchPut('/ivc://object/:comment-303%CE%94reactions/%E2%9D%A4%EF%B8%8F', { sender: 'Heidi' });
    expect(res.status).toBe(200);
    body = JSON.parse(res.data);
    expect(body.count).toBe(2);

    // 3. PUT with different emoji e.g. 🔥 -> count for that emoji is 1
    res = await fetchPut('/ivc://object/:comment-303%CE%94reactions/%F0%9F%94%A5', { sender: 'Ivan' });
    expect(res.status).toBe(200);
    body = JSON.parse(res.data);
    expect(body.count).toBe(1);

    // 4. PUT via /api/reactions.php query parameter uri=ivc://object/:comment-303Δreactions/👍
    res = await fetchPut('/api/reactions.php?uri=ivc://object/:comment-303%CE%94reactions/%F0%9F%91%8D', { sender: 'Judy' });
    expect(res.status).toBe(200);
    body = JSON.parse(res.data);
    expect(body.count).toBe(1);
    expect(body.redirect).toContain('ivc://object/:comment-303Δreactions=');
  });

  it('should support network-level comments ivc://:comment-id and ivc://£:comment-id with automatic redirect', async () => {
    // 1. React to network-level comment ivc://:comment-505
    let res = await fetchPut('/ivc://:comment-505%CE%94reactions/%E2%9D%A4%EF%B8%8F', { sender: 'Grace' });
    expect(res.status).toBe(200);
    let body = JSON.parse(res.data);
    expect(body.count).toBe(1);

    // 2. React to network-level comment using optional £ symbol ivc://£:comment-505 -> shares reactions
    res = await fetchPut('/ivc://%C2%A3:comment-505%CE%94reactions/%E2%9D%A4%EF%B8%8F', { sender: 'Heidi' });
    expect(res.status).toBe(200);
    body = JSON.parse(res.data);
    expect(body.count).toBe(2);

    // 3. React with 🔥 to network comment
    res = await fetchPut('/ivc://:comment-505%CE%94reactions/%F0%9F%94%A5', { sender: 'Ivan' });
    expect(res.status).toBe(200);
    body = JSON.parse(res.data);
    expect(body.count).toBe(1);

    // 4. Query via GET with redirect=true -> HTTP 302 Redirect to compact encoded representation
    const reqGet = await new Promise<{ status: number, location: string }>((resolve, reject) => {
      const req = http.request({
        hostname: '127.0.0.1',
        port: PORT,
        path: '/api/reactions.php?uri=ivc://:comment-505&redirect=true',
        method: 'GET'
      }, (res) => {
        resolve({
          status: res.statusCode || 500,
          location: res.headers.location || ''
        });
      });
      req.on('error', reject);
      req.end();
    });

    expect(reqGet.status).toBe(302);
    expect(decodeURI(reqGet.location)).toBe('ivc://:comment-505Δreactions={"❤️":2,"🔥":1}');

    // 5. Query via GET /ivc://:comment-505 direct navigation -> HTTP 302 Redirect
    const directGet = await new Promise<{ status: number, location: string }>((resolve, reject) => {
      const req = http.request({
        hostname: '127.0.0.1',
        port: PORT,
        path: '/ivc://:comment-505',
        method: 'GET'
      }, (res) => {
        resolve({
          status: res.statusCode || 500,
          location: res.headers.location || ''
        });
      });
      req.on('error', reject);
      req.end();
    });

    expect(directGet.status).toBe(302);
    expect(decodeURI(directGet.location)).toBe('ivc://:comment-505Δreactions={"❤️":2,"🔥":1}');
  });
});
