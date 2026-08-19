# 🛡️ Security static/dynamic analysis strategy

Our security strategy enforces best practices and End-to-End Encryption (E2EE) across the entire application stack:

## 1. Static Analysis (Static Application Security Testing - SAST)
- **Dependency Auditing**: Regular execution of `npm audit` to detect known vulnerabilities in Node dependencies.
- **Code Linting & Security Rules**: Execution of ESLint with strict security rules to identify insecure coding patterns.
- **Threat Scanning**: Automated scans using grep and custom Node.js scripts (`scripts/threat-scan.js`) to find hardcoded secrets or unencrypted PII.
- **Compliance Checks**: Ensuring all PHP files enforce headers like CSP, HSTS, X-Frame-Options via `scripts/compliance-report.js`.

## 2. Dynamic Analysis (Dynamic Application Security Testing - DAST)
- **Penetration Testing**: Automated tests (`tests/penetration/`) targeting API endpoints to ensure input sanitization and protection against SQL injection and XSS.
- **Backend Testing**: Rigorous assertion testing (`php tests/WebRtcSiteTest.php`) ensuring API boundaries, rate-limiting, and stateless E2EE delivery.

This strategy guarantees the integrity of The Fortress IT Security Framework.
