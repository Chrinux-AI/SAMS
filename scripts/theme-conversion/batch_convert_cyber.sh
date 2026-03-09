#!/bin/bash
# Batch convert all admin pages to Cyberpunk UI

cd /opt/lampp/htdocs/attendance/admin

# Backup old files
mkdir -p _old_ui_backup
for file in classes.php reports.php analytics.php settings.php announcements.php timetable.php communication.php facilities.php users.php; do
    [ -f "$file" ] && cp "$file" "_old_ui_backup/$file"
done

echo "Backup complete. Files saved to _old_ui_backup/"
