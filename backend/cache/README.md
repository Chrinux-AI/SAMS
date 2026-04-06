# ⚡ Cache Folder

## Purpose

Performance optimization through caching frequently accessed data.

## Structure

```
cache/
├── redis/      # Redis cache files
└── sessions/   # Session data
```

## Cache Types

- **Redis**: Query results, API responses
- **File**: Static content, compiled templates
- **Session**: User session data

## Configuration

- **TTL**: 3600s (1 hour) for queries
- **Driver**: Redis (primary), File (fallback)
- **Max Size**: 256MB

## Usage Example

```php
$cache = new Cache();
$data = $cache->remember('user_stats', 3600, function() {
    return db()->fetchAll("SELECT...");
});
```

## Clearing Cache

```bash
# All cache
php scripts/clear_cache.php

# Specific key
php scripts/clear_cache.php user_stats
```

## Security

- No sensitive data cached unencrypted
- Automatic purge on logout
- CSRF tokens not cached

**Last Updated:** November 24, 2025
