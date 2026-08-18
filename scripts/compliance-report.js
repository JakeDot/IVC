import fs from 'fs';

console.log('Generating compliance report...');

const headersFile = 'src/Security/SecurityHeaders.php';
if (fs.existsSync(headersFile)) {
    const content = fs.readFileSync(headersFile, 'utf8');
    if (content.includes('Content-Security-Policy') && content.includes('Strict-Transport-Security')) {
        console.log('✅ Security headers compliance verified.');
    } else {
        console.error('❌ Missing critical security headers in ' + headersFile);
        process.exit(1);
    }
} else {
    console.error('❌ SecurityHeaders.php not found!');
    process.exit(1);
}
