<?php

/**
 * Enterprise Permission Policies
 *
 * Central role → permission mapping for feature-level authorization.
 * Use with Policy::can($role, 'permission') throughout the application.
 *
 * Format: 'role' => ['permission1', 'permission2', ...]
 * Supports wildcards: 'admin' => ['*'] grants everything.
 */

return [

  'admin' => ['*'], // superuser — all permissions

  'teacher' => [
    'attendance.view',
    'attendance.mark',
    'attendance.edit_own',
    'attendance.export',
    'classes.view',
    'classes.manage_own',
    'students.view',
    'students.notes',
    'grades.view',
    'grades.manage',
    'grades.export',
    'notices.view',
    'notices.create',
    'messages.send',
    'messages.view',
    'reports.class',
    'reports.attendance',
    'profile.edit',
    'forum.post',
    'forum.moderate_own',
    'ai.suggestions',
  ],

  'student' => [
    'attendance.view_own',
    'grades.view_own',
    'notices.view',
    'messages.send',
    'messages.view',
    'profile.edit',
    'forum.post',
    'library.borrow',
    'library.view',
    'ai.chat',
  ],

  'parent' => [
    'attendance.view_children',
    'grades.view_children',
    'notices.view',
    'messages.send',
    'messages.view',
    'profile.edit',
    'reports.child',
    'ai.chat',
  ],

  'librarian' => [
    'library.view',
    'library.manage',
    'library.borrow',
    'library.return',
    'library.reports',
    'students.view',
    'notices.view',
    'notices.create',
    'messages.send',
    'messages.view',
    'profile.edit',
  ],

  'bursar' => [
    'finance.view',
    'finance.manage',
    'finance.invoices',
    'finance.payments',
    'finance.reports',
    'students.view',
    'notices.view',
    'notices.create',
    'messages.send',
    'messages.view',
    'profile.edit',
  ],

  'accountant' => [
    'finance.view',
    'finance.ledger',
    'finance.reports',
    'finance.budget',
    'finance.payroll',
    'finance.tax',
    'notices.view',
    'messages.send',
    'messages.view',
    'profile.edit',
  ],

  'transport' => [
    'transport.view',
    'transport.manage',
    'transport.routes',
    'transport.drivers',
    'transport.reports',
    'students.view',
    'notices.view',
    'messages.send',
    'messages.view',
    'profile.edit',
  ],

  'forum-moderator' => [
    'forum.view',
    'forum.post',
    'forum.moderate',
    'forum.delete',
    'forum.ban_user',
    'notices.view',
    'messages.send',
    'messages.view',
    'profile.edit',
  ],

];
