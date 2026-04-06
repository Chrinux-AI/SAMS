# SAMS Blockchain + Biometric Roadmap

## Vision

Build a tamper-evident school operations platform where critical actions (attendance check-ins, fee postings, approvals, and audit events) are verifiable, privacy-aware, and operationally practical.

## What We Want to Achieve

- Add **blockchain-backed audit integrity** for high-value records.
- Support **fingerprint-based identity capture** for check-in and sensitive approvals.
- Enforce **session timeout policies** per role for stronger account safety.
- Keep performance and usability high for daily school workflows.

## Scope for Full Project Adoption

### Phase 1 — Foundation

- Define ledger event schema for:
  - attendance events
  - financial transactions
  - approval actions
  - user/role security changes
- Add hash-chain proof model (record hash + previous hash + timestamp + actor).
- Keep source of truth in primary database; use blockchain layer for verification trail.

### Phase 2 — Fingerprint Enablement

- Add fingerprint enrollment + verification flow for supported roles.
- Bind biometric template references (never raw images) to secure identity records.
- Introduce fallback authentication flow (PIN/OTP) for unavailable devices.

### Phase 3 — Session Security

- Enforce inactivity timeout profiles:
  - Admin/Finance: 10–15 min
  - Teacher/Staff: 20–30 min
  - Student/Parent: 30–45 min
- Add warning banner before timeout and safe session restore UX.

### Phase 4 — Blockchain Verification UI

- Provide verification screen for any ledgered event:
  - event ID
  - hash proof
  - block reference / chain index
  - verification status
- Surface verification in accountant audit and admin compliance reports.

## Key Principles

- **Privacy first**: biometric templates encrypted at rest and in transit.
- **Interoperability**: modular adapter design (private chain, consortium chain, or hash-anchor service).
- **Progressive rollout**: start with financial + attendance modules before full-system adoption.
- **Operational fallback**: system remains functional even if blockchain service is degraded.

## Immediate Action Items

1. Capture project goals and ideas in the accountant goals page.
2. Define technical RFC for blockchain adapter interface.
3. Add pilot module: financial transaction hash logging.
4. Validate fingerprint device compatibility matrix.
5. Configure role-based timeout policy in production settings.
