#!/bin/bash

# Fix teacher messages
sed -i "s/db()->query(\"SELECT DISTINCT u.id/db()->fetchAll(\"SELECT DISTINCT u.id/g" teacher/messages.php
sed -i "s/db()->query(\"SELECT id, full_name/db()->fetchAll(\"SELECT id, full_name/g" teacher/messages.php
sed -i "s/db()->query(\"SELECT m.\*, u.full_name/db()->fetchAll(\"SELECT m.*, u.full_name/g" teacher/messages.php
sed -i 's/) ?: \[\];/, \[\$user_id\]) ?: \[\];/g' teacher/messages.php
sed -i 's/\$admin = db()->fetchAll/\$admins = db()->fetchAll/g' teacher/messages.php
sed -i 's/\$all_users = array_merge(\$admin,/\$all_users = array_merge(\$admins,/g' teacher/messages.php

# Fix student messages
sed -i "s/db()->query(\"SELECT DISTINCT u.id/db()->fetchAll(\"SELECT DISTINCT u.id/g" student/messages.php
sed -i "s/db()->query(\"SELECT id, full_name/db()->fetchAll(\"SELECT id, full_name/g" student/messages.php
sed -i "s/db()->query(\"SELECT m.\*, u.full_name/db()->fetchAll(\"SELECT m.*, u.full_name/g" student/messages.php
sed -i 's/) ?: \[\];/, \[\$user_id\]) ?: \[\];/g' student/messages.php
sed -i 's/\$admin = db()->fetchAll/\$admins = db()->fetchAll/g' student/messages.php
sed -i 's/\$all_users = array_merge(\$admin,/\$all_users = array_merge(\$admins,/g' student/messages.php

# Fix parent communication  
sed -i "s/db()->query(\"SELECT DISTINCT u.id/db()->fetchAll(\"SELECT DISTINCT u.id/g" parent/communication.php
sed -i "s/db()->query(\"SELECT id, full_name/db()->fetchAll(\"SELECT id, full_name/g" parent/communication.php
sed -i "s/db()->query(\"SELECT m.\*, u.full_name/db()->fetchAll(\"SELECT m.*, u.full_name/g" parent/communication.php
sed -i 's/) ?: \[\];/, \[\$user_id\]) ?: \[\];/g' parent/communication.php
sed -i 's/\$admin = db()->fetchAll/\$admins = db()->fetchAll/g' parent/communication.php
sed -i 's/\$all_users = array_merge(\$admin,/\$all_users = array_merge(\$admins,/g' parent/communication.php

echo "All messaging pages fixed!"
