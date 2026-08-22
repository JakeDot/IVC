import { execSync } from 'child_process';

console.log('Starting full project security audit...');
try {
    const auditRes = execSync('npm audit --audit-level=moderate').toString();
    console.log(auditRes);
    console.log('✅ npm audit passed.');
} catch (e) {
    console.error('❌ npm audit failed or found vulnerabilities!');
    console.error(e.stdout ? e.stdout.toString() : e.message);
    process.exit(1);
}
