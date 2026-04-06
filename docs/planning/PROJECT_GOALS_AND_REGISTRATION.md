# Platform Registration & Geography Documentation

## Goals & Mindset

1. **Dynamic Context & Geography**: The project is being actively built with a dynamic, localized approach. While the creator is currently in Nigeria, the platform is **not** hardcoded to one country.
   - **Configurable Settings**: During school registration, the region, country, and currency (e.g., NGN ₦, USD $, etc.) will be selectable.
   - **Localization**: Date formatting, currencies, and regional settings will adapt to the school's configured country. The system must support internationalization naturally.
2. **"School-First" Registration (Tenant Onboarding Workflow)**:
   - An individual _does not_ register as a "Teacher" or "Accountant" directly on the main site without a school.
   - **Step 1**: The Institution (School) must register first to create their "Tenant" namespace on our platform and select their regional settings.
   - **Step 2**: The owner/admin gets access to the admin portal/panel.
   - **Step 3**: The owner/admin uses their portal to _register and provision_ personal roles (e.g. creating staff, accountants, teachers, and students) for their school.
