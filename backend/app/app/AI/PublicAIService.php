<?php

require_once __DIR__ . '/CoreAIService.php';

/**
 * Public AI Service — Stateless visitor assistant.
 * Operates inside a Restricted Knowledge Boundary (RKB).
 * NO data access. Marketing + help docs + onboarding only.
 */
class PublicAIService extends CoreAIService
{
  /** Curated knowledge base — the ONLY information this AI can reference. */
  private array $knowledgeBase = [
    'about' => [
      'patterns' => ['what is sams', 'about', 'what does', 'tell me about', 'purpose', 'overview'],
      'response' => "SAMS (School Attendance Management System) is a comprehensive platform for managing school operations — attendance tracking, grade management, communication, and more. It serves administrators, teachers, students, and parents with role-specific dashboards.",
    ],
    'features' => [
      'patterns' => ['features', 'what can', 'capabilities', 'functions', 'modules'],
      'response' => "Key Features:\n• Real-time attendance tracking (biometric & manual)\n• Grade & exam management\n• Parent-teacher communication\n• Library management system\n• AI-powered analytics & chatbots\n• Notice & announcement system\n• Multi-role dashboards (Admin, Teacher, Student, Parent)\n• Transport & hostel management\n• Financial tracking (Bursar & Accountant modules)",
    ],
    'register' => [
      'patterns' => ['register', 'sign up', 'create account', 'join', 'enroll', 'get started'],
      'response' => "To get started with SAMS:\n1. Click 'Register' on the homepage\n2. Fill in your name, email, and preferred role\n3. Create a secure password (min 12 characters)\n4. Verify your email address\n5. Wait for admin approval\n\nOnce approved, you'll have full access to your role's dashboard.",
    ],
    'login' => [
      'patterns' => ['login', 'sign in', 'log in', 'access', 'forgot password', 'reset password', 'can\'t login'],
      'response' => "Login Help:\n• Go to the Login page and enter your email and password\n• If you forgot your password, click 'Forgot Password' to reset it via email\n• After 5 failed attempts, your account locks for 5 minutes\n• Sessions timeout after 20 minutes of inactivity (10 minutes for admins)\n\nContact your school administrator if you need further help.",
    ],
    'roles' => [
      'patterns' => ['roles', 'who can use', 'user types', 'admin', 'teacher role', 'student role', 'parent role'],
      'response' => "SAMS supports multiple roles:\n• **Admin** — Full system control, user management, analytics\n• **Teacher** — Attendance marking, grades, resources\n• **Student** — View attendance, grades, library access\n• **Parent** — Monitor children, book meetings, view reports\n• **Librarian** — Book management, lending, fines\n• **Bursar** — Fee collection, financial reports\n• **Accountant** — Ledger, payroll, budgets\n• **Transport** — Vehicle & route management\n• **Forum Moderator** — Community moderation",
    ],
    'security' => [
      'patterns' => ['security', 'safe', 'privacy', 'secure', 'data protection', 'encryption'],
      'response' => "SAMS takes security seriously:\n• Encrypted passwords (bcrypt)\n• Session timeouts with auto-logout\n• CSRF protection on all forms\n• Rate limiting on login attempts\n• Role-based access control\n• Security headers on all pages\n• Audit logging for admin actions\n\nYour data is protected by industry-standard practices.",
    ],
    'contact' => [
      'patterns' => ['contact', 'support', 'help', 'email', 'phone', 'reach'],
      'response' => "Need help? Here's how to reach us:\n• Contact your school's IT administrator\n• Use the 'Forgot Password' feature for account issues\n• Check the school notice board for updates\n• For technical issues, reach out to your system administrator",
    ],
  ];

  public function __construct()
  {
    parent::__construct('guest');

    $this->allowedTopics = [
      'about',
      'features',
      'register',
      'login',
      'roles',
      'security',
      'help',
      'contact',
      'onboarding',
      'pricing',
      'demo',
    ];

    $this->blockedTopics = [
      'database',
      'api',
      'endpoints',
      'source code',
      'server',
      'credentials',
      'passwords',
      'admin panel',
      'sql',
      'config',
      'internal',
      'authentication logic',
      'encryption keys',
      'environment variables',
      'file system',
      'users table',
    ];

    $this->systemPrompt = 'You are the SAMS public assistant for website visitors. '
      . 'You ONLY answer questions about: the system purpose, features, how to register, '
      . 'how to login, available roles, and security overview. You never discuss internal '
      . 'systems, database structures, API endpoints, or technical implementation.';

    $this->maxTokens = 300;
    $this->rateLimitPerMinute = 10;
  }

  protected function generateResponse(string $message, array $context): string
  {
    $lower = mb_strtolower($message);

    // Match against curated knowledge base
    foreach ($this->knowledgeBase as $entry) {
      foreach ($entry['patterns'] as $pattern) {
        if (str_contains($lower, $pattern)) {
          return $entry['response'];
        }
      }
    }

    // Greeting
    if (preg_match('/^(hi|hello|hey|good\s*(morning|afternoon|evening))$/i', trim($message))) {
      return "Hello! Welcome to SAMS — the School Attendance Management System.\n\nI can help you with:\n• What SAMS does\n• System features\n• How to register\n• Login help\n• User roles\n• Security info\n\nWhat would you like to know?";
    }

    return "I'm the SAMS visitor assistant. I can tell you about:\n• System overview & features\n• How to register or login\n• Available user roles\n• Security measures\n• Contact information\n\nPlease ask about any of these topics!";
  }

  /**
   * Get suggested follow-up prompts for the chat widget.
   */
  public function getSuggestions(): array
  {
    return [
      'What is SAMS?',
      'How do I register?',
      'What features are available?',
      'What roles does SAMS support?',
      'Is my data secure?',
    ];
  }
}
