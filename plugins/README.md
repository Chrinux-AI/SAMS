# 🔌 Plugins Folder

## Purpose

Extensible features and custom modules for SAMS.

## Structure

```
plugins/
├── attendance-kiosk/     # Self-service kiosk mode
├── biometric-auth/       # Fingerprint/facial recognition
├── sms-notifications/    # SMS gateway integration
├── parent-portal/        # Enhanced parent features
└── analytics-pro/        # Advanced analytics
```

## Plugin Architecture

Each plugin should contain:

- `plugin.json` - Metadata (name, version, dependencies)
- `init.php` - Initialization logic
- `README.md` - Documentation
- `assets/` - Plugin-specific resources
- `views/` - UI templates

## Creating a Plugin

```php
// plugin.json
{
    "name": "Attendance Kiosk",
    "version": "1.0.0",
    "requires": "SAMS >= 2.0.0",
    "author": "Your Name"
}

// init.php
function activate_plugin() {
    // Setup logic
}
```

## Installation

1. Upload plugin folder to `/plugins/`
2. Navigate to Admin → Plugins
3. Click "Activate"

## Best Practices

- Follow SAMS coding standards
- Use namespaces to avoid conflicts
- Provide uninstall hooks
- Document all dependencies

**Last Updated:** November 24, 2025
