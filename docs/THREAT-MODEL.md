# 🚨 Threat Model

## Attack Surface

The Fortress addresses the following attack vectors:

1. **Network Attacks** — MITM, DDoS, port scanning
2. **Cryptographic Attacks** — Key extraction, timing attacks
3. **Authentication Bypass** — Session hijacking, credential stuffing
4. **Data Breaches** — Encryption bypass, memory dumps
5. **Supply Chain** — Malicious dependencies, version confusion
6. **Insider Threats** — Unauthorized access, data exfiltration
7. **Zero-Days** — Unknown vulnerabilities (limited protection)

## Mitigation Strategy

The Fortress implements defense-in-depth with multiple layers protecting against each vector.

See feature/the-hardener branch for detailed threat analysis.
