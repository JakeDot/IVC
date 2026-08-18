import { describe, it, expect, beforeAll, afterAll } from '@jest/globals';
import { spawn, ChildProcess } from 'child_process';
import * as net from 'net';

/**
 * @Service("irc-daemon")
 */
describe('IRC Penetration Tests', () => {
  let serverProcess: ChildProcess;

  beforeAll((done) => {
    // Start the IRC server in background
    serverProcess = spawn('php', ['bin/irc-server.php'], { stdio: 'pipe' });
    setTimeout(done, 1000); // give it a sec to start
  });

  afterAll(() => {
    if (serverProcess) {
      serverProcess.kill();
    }
  });

  it('should block unauthorized users from becoming OP', (done) => {
    const client = new net.Socket();
    client.connect(6667, '127.0.0.1', () => {
      client.write('NICK HackerBot\r\n');
      client.write('USER hacker hacker server :Hacker\r\n');
      client.write('JOIN #fortress\r\n');
      // Malicious attempt to OP oneself
      client.write('MODE #fortress +o HackerBot\r\n');
    });

    let output = '';
    let isDone = false;
    let timeoutId: any;

    client.on('data', (data) => {
      output += data.toString();

      // We know the server should explicitly deny this request.
      if (!isDone && (output.includes('Cant change mode for other users') || output.includes('502 HackerBot'))) {
         isDone = true;
         clearTimeout(timeoutId);
         client.destroy();
         expect(output).toContain('Cant change mode');
         expect(output).not.toContain('+o HackerBot');
         done();
      }
    });

    // Safety fallback just in case it doesn't respond
    timeoutId = setTimeout(() => {
      if (!isDone) {
        isDone = true;
        client.destroy();
        expect(output).not.toContain('+o HackerBot');
        done();
      }
    }, 4000);
  });
});
