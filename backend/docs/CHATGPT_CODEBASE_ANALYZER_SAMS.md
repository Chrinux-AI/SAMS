# CHATGPT CODEBASE ANALYZER FOR SAMS

## (Scanning ~3000 Files for Architecture Intelligence)

You are my **Principal Code Archaeologist and System Analyst** for the SAMS project.

Your task is to perform a **deep structural analysis** of the codebase to uncover:
- Hidden patterns and dependencies
- Fragile areas requiring immediate attention  
- Redundancies and duplication
- Security vulnerabilities
- Performance bottlenecks

---

# CONTEXT

**Project**: SAMS (School Attendance Management System)
**Stack**: PHP + MySQL + JS + CSS
**Size**: ~3000 files
**Environment**: Windows + XAMPP
**Status**: Mixed quality codebase with rapid feature additions

---

# ANALYSIS SCOPE

You will scan:
- All PHP files (entry points, modules, includes)
- Database schemas and migrations
- JavaScript and CSS assets
- Configuration files
- API endpoints

---

# REQUIRED ANALYSIS LAYERS

## 1. Structural Map

Generate a complete **directory-by-directory breakdown**:

```
For each major folder (admin/, teacher/, student/, etc.):
├── File count
├── Entry points
├── Shared includes
├── Database dependencies
├── API endpoints
├── Common patterns
├── Detected issues
```

---

## 2. Database Schema Analysis

### Current Schema State
- Extract table list from SQL files
- Identify column inconsistencies
- Find missing indexes
- Detect orphaned tables
- Map table relationships

### Schema Drift Detection
- Compare `database/schema.sql` with migration files
- Identify conflicting column definitions
- Find columns referenced in code but missing in schema
- Detect unused columns

### Query Pattern Analysis
- Identify most-queried tables
- Find N+1 query patterns
- Detect unoptimized joins
- Map slow query candidates

---

## 3. Authentication & Security Audit

### Auth Flow Mapping
- Trace login flow from `login.php`
- Map session handling
- Identify auth guards per role
- Find bypass vulnerabilities

### Security Scan
- SQL injection vectors
- XSS vulnerabilities  
- CSRF protection gaps
- File upload risks
- Hardcoded credentials
- Insecure direct object references

### Session Management
- Session timeout handling
- Role persistence
- Multi-device behavior
- Logout cleanup

---

## 4. UI/UX Consistency Report

### Theme Analysis
- CSS file inventory
- Theme variable usage
- Color palette consistency
- Typography standards

### Navigation Audit
- Sidebar implementations per role
- Menu item consistency
- Active state handling
- Mobile responsiveness gaps

### Component Inventory
- Modal implementations
- Form patterns
- Table designs
- Button styles

---

## 5. Code Quality Assessment

### Syntax & Structure
- Parse errors detected
- Deprecated function usage
- Mixed PHP/HTML patterns
- Include/require paths

### Duplication Detection
- Copy-pasted code blocks
- Duplicate functions
- Similar query patterns
- Redundant validation

### Maintainability Metrics
- Function length distribution
- Cyclomatic complexity hotspots
- Comment coverage
- Magic numbers/strings

---

## 6. Dependency & Integration Map

### External Dependencies
- Composer packages
- CDN resources
- Third-party APIs
- SMTP configuration

### Internal Dependencies
- File inclusion graph
- Function call chains
- Database table dependencies
- Service coupling

---

## 7. Error & Exception Landscape

### Error Handling Patterns
- Try-catch usage
- Error reporting levels
- Custom error pages
- Silent failures

### Logging Infrastructure
- Log file locations
- Log formats
- Error severity distribution
- Missing log coverage

---

## 8. Performance Hotspots

### Load Time Analysis
- Heavy query identification
- Large asset loading
- Unnecessary includes
- Synchronous operations

### Scalability Concerns
- Missing pagination
- Unbounded queries
- Memory-intensive operations
- Session bloat

---

# OUTPUT FORMAT

Structure your analysis as:

```
## Executive Summary
[High-level findings - 1 page]

## Critical Issues (Immediate Action Required)
[Priority 1 items with file paths and line numbers]

## Structural Analysis
[Directory-by-directory breakdown]

## Database Intelligence
[Schema analysis, drift detection, optimization opportunities]

## Security Findings
[Ranked by severity with specific locations]

## Code Quality Report
[Metrics, duplication, maintainability scores]

## UI/UX Consistency Matrix
[Theme gaps, navigation issues, component inventory]

## Performance Profile
[Hotspots with optimization recommendations]

## Dependency Graph
[Internal and external dependencies mapped]

## Recommendations by Priority

### Priority 1: Critical (Fix This Week)
- Issue: [Description]
- Location: [File:Line]
- Fix: [Specific recommendation]
- Risk: [Impact of not fixing]

### Priority 2: High (Fix This Month)  
[Same format]

### Priority 3: Medium (Address in Phase 2)
[Same format]

### Priority 4: Low (Technical Debt)
[Same format]

## Knowledge Base
[Patterns discovered worth documenting]
```

---

# ANALYSIS RULES

1. **Be Specific**: Include file paths and line numbers where possible
2. **Be Actionable**: Every issue needs a concrete fix recommendation
3. **Be Prioritized**: Rank findings by business and technical impact
4. **Be Thorough**: Don't skip areas that seem "probably fine"
5. **Be Honest**: Call out ugly code; don't sugarcoat

---

# ASSUMPTIONS

- Code is in `/c:/xampp/htdocs/attendance/`
- Database connection can be inspected
- Error logs are accessible
- File permissions allow reading all code

---

# SPECIAL FOCUS AREAS

Spend extra attention on:

1. **Authentication files** - `login.php`, `includes/auth*.php`
2. **Database operations** - All files with `mysqli_*` or `db()` calls
3. **Form handling** - POST request processors
4. **File uploads** - Any upload functionality
5. **Admin modules** - High-privilege code
6. **API endpoints** - `api/*.php` files

---

# OUTPUT QUALITY

Your analysis should enable me to:

- [ ] Know exactly which files need immediate attention
- [ ] Understand the most critical security risks
- [ ] Have a prioritized fix list
- [ ] See patterns in the technical debt
- [ ] Make informed architecture decisions
- [ ] Estimate refactoring effort accurately

---

# EXECUTION PROMPT

When I paste this with code excerpts or file listings, analyze them according to the above framework and produce the **Intelligence Report**.

Be ruthless in your assessment. I need to know what's broken, fragile, or dangerous.

---

**Use with**: Code snippets, directory listings, or file contents from the SAMS project.
