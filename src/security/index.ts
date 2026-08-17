/**
 * The Fortress - Security-Hardened Project Template
 * 
 * This is the main entry point for all security modules.
 * Each module provides enterprise-grade security capabilities.
 * 
 * Reference implementation: https://github.com/JakeDot/AiNBot/blob/feature/the-hardener/docs/BRANCH-9-THE-HARDENER.md
 */

// Network Security
export * from './network/FirewallManager';
export * from './network/TLSManager';
export * from './network/OfflineMode';

// Data Encryption
export * from './crypto/EncryptionVault';
export * from './crypto/MemoryGuard';
export * from './crypto/TransitEncryption';

// Authentication & Authorization
export * from './auth/AdvancedAuthManager';
export * from './auth/RBACEngine';
export * from './auth/SessionManager';

// Audit & Compliance
export * from './audit/ImmutableAuditLog';
export * from './audit/ComplianceEngine';
export * from './audit/ForensicsCollector';

// Threat Detection
export * from './threat/AnomalyDetector';
export * from './threat/IntrusionDetection';
export * from './threat/VulnerabilityScanner';

// Supply Chain Security
export * from './supply/DependencyValidator';
export * from './supply/SBOMGenerator';
export * from './supply/LockfileIntegrity';

// Incident Response
export * from './incident/AlertingSystem';
export * from './incident/ContainmentManager';
export * from './incident/RecoveryPlan';

// Configuration & Policies
export * from './config/SecurityPolicy';
export * from './config/HardeningProfiles';
