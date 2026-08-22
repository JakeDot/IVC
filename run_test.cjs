const { spawn } = require('child_process');
const cp = spawn('node', ['server.js'], { env: { ...process.env, PORT: '18082' } });
cp.stdout.on('data', d => process.stdout.write(d));
cp.stderr.on('data', d => process.stderr.write(d));
setTimeout(() => {
    cp.kill();
    process.exit(0);
}, 5000);
