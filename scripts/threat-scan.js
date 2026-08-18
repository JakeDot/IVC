import fs from 'fs';
import { execSync } from 'child_process';

console.log('Initiating static threat scan...');

try {
    const result = execSync('grep -rniE "(password|secret)[ ]*=[ ]*[\'\\"][^\'\\"]+[\'\\"]" src/ public/ || true').toString();
    if (result.trim() !== '') {
        console.warn('⚠️ WARNING: Potential hardcoded secrets found:\n' + result);
    } else {
        console.log('✅ No hardcoded secrets detected.');
    }
} catch (e) {
    console.error('Error running scan', e);
}
console.log('Threat scan complete.');
