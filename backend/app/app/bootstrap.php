<?php

/**
 * Service Loader — Autoloads all app/ service classes (Phase-2 + Phase-3).
 * Include this file once after config.php to make all services available.
 *
 * Phase-2 Services:
 *  - Security Gateway (InputSanitizer, XSSGuard, SQLInjectionGuard, AuditLogger, RateLimiterService, SecurityGateway)
 *  - Event System (EventDispatcher, SystemEvents, EventListeners, Broadcaster)
 *  - AI Router (CoreAIService + role services + AIRouter)
 *  - Notifications (NotificationService)
 *  - Messaging (MessageService)
 *  - Profiles (ProfileService, AvatarPolicy)
 *  - Updates (UpdatesService)
 *  - Performance (CacheService, APIThrottle)
 *
 * Phase-3 Security Architecture:
 *  - Behavioral Monitoring (BehaviorMonitor)
 *  - AI Anomaly Detection (SecurityAI)
 *  - Automated Defense (AutoDefense)
 *  - Session Intelligence (SessionIntelligence)
 *  - Admin Forensics (AdminForensics)
 *  - API Security (ApiSecurityMiddleware)
 *  - Row-Level Security (PolicyGuard)
 *  - AI Prompt Firewall (PromptFirewall)
 *  - Security Event Bus (SecurityEventBus)
 *  - Backup Integrity (BackupVerifierAI)
 *  - Attack Simulation (SecuritySimulator)
 */

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));

$appDir = BASE_PATH . '/app';

// Autoload function — loads class files from app/ subdirectories
spl_autoload_register(function (string $class) use ($appDir) {
  $map = [
    // Security
    'SecurityGateway'    => '/Security/SecurityGateway.php',
    'InputSanitizer'     => '/Security/InputSanitizer.php',
    'XSSGuard'           => '/Security/XSSGuard.php',
    'SQLInjectionGuard'  => '/Security/SQLInjectionGuard.php',
    'AuditLogger'        => '/Security/AuditLogger.php',
    'RateLimiterService' => '/Security/RateLimiterService.php',
    // Events
    'EventDispatcher'    => '/Events/EventDispatcher.php',
    'SystemEvents'       => '/Events/SystemEvents.php',
    'EventListeners'     => '/Events/EventListeners.php',
    'Broadcaster'        => '/Events/Broadcaster.php',
    // AI
    'CoreAIService'      => '/AI/CoreAIService.php',
    'AdminAIService'     => '/AI/AdminAIService.php',
    'TeacherAIService'   => '/AI/TeacherAIService.php',
    'StudentAIService'   => '/AI/StudentAIService.php',
    'ParentAIService'    => '/AI/ParentAIService.php',
    'PublicAIService'    => '/AI/PublicAIService.php',
    'AIRouter'           => '/AI/AIRouter.php',
    // Notifications
    'NotificationService' => '/Notifications/NotificationService.php',
    // Messaging
    'MessageService'     => '/Messaging/MessageService.php',
    // Profiles
    'ProfileService'     => '/Profiles/ProfileService.php',
    'AvatarPolicy'       => '/Profiles/AvatarPolicy.php',
    // Updates
    'UpdatesService'     => '/Updates/UpdatesService.php',
    // Services
    'CacheService'       => '/Services/CacheService.php',
    'APIThrottle'        => '/Services/APIThrottle.php',
    // Phase-3 Security Architecture
    'BehaviorMonitor'       => '/Security/BehaviorMonitor.php',
    'SecurityAI'            => '/AI/SecurityAI.php',
    'AutoDefense'           => '/Security/AutoDefense.php',
    'SessionIntelligence'   => '/Security/SessionIntelligence.php',
    'AdminForensics'        => '/Security/AdminForensics.php',
    'ApiSecurityMiddleware'  => '/Security/ApiSecurityMiddleware.php',
    'PolicyGuard'           => '/Security/PolicyGuard.php',
    'PromptFirewall'        => '/Security/PromptFirewall.php',
    'SecurityEventBus'      => '/Security/SecurityEventBus.php',
    'BackupVerifierAI'      => '/AI/BackupVerifierAI.php',
    'SecuritySimulator'     => '/Security/SecuritySimulator.php',
    // Phase-4 Enterprise Architecture
    'ThemeManager'          => '/UI/ThemeManager.php',
    'ErrorHandler'          => '/Core/ErrorHandler.php',
    'Policy'                => '/Core/Policy.php',
    // Phase-5 Autonomous Architecture
    'AutoSyncEngine'        => '/Core/AutoSyncEngine.php',
    'QueryValidator'        => '/Core/QueryValidator.php',
    'DataConsistencyGuard'  => '/Security/DataConsistencyGuard.php',
    'ClassRepository'       => '/Repositories/ClassRepository.php',
    'ClassService'          => '/Services/ClassService.php',
    'ClassController'       => '/Controllers/ClassController.php',
    'AdminEditGuarantee'    => '/Core/AdminEditGuarantee.php',
    'LandingContentService' => '/Services/LandingContentService.php',
    // Phase-6 Autonomous Fix Loop
    'ErrorCollector'        => '/Core/ErrorCollector.php',
    'SystemScanner'         => '/Core/SystemScanner.php',
    'AutoRepairEngine'      => '/Core/AutoRepairEngine.php',
    'ValidationRunner'      => '/Core/ValidationRunner.php',
    'HealthReporter'        => '/Core/HealthReporter.php',
    'AutonomousFixLoop'     => '/Core/AutonomousFixLoop.php',
    // Phase-7 Autonomous DevOps
    'ResourceMonitor'       => '/DevOps/ResourceMonitor.php',
    'PerformanceOptimizer'  => '/DevOps/PerformanceOptimizer.php',
    'DatabaseOptimizer'     => '/DevOps/DatabaseOptimizer.php',
    'SecurityHardener'      => '/DevOps/SecurityHardener.php',
    'DeploymentGuard'       => '/DevOps/DeploymentGuard.php',
    'DriftController'       => '/DevOps/DriftController.php',
    'IncidentResponder'     => '/DevOps/IncidentResponder.php',
    'DevOpsKernel'          => '/DevOps/DevOpsKernel.php',
    // Phase-8 Platform Intelligence Layer
    'KnowledgeGraph'        => '/Platform/KnowledgeGraph.php',
    'ContextEngine'         => '/Platform/ContextEngine.php',
    'BehaviorAnalyzer'      => '/Platform/BehaviorAnalyzer.php',
    'PredictionEngine'      => '/Platform/PredictionEngine.php',
    'DecisionEngine'        => '/Platform/DecisionEngine.php',
    'WorkflowOrchestrator'  => '/Platform/WorkflowOrchestrator.php',
    'SmartAPI'              => '/Platform/SmartAPI.php',
    'DeviceBridge'          => '/Platform/DeviceBridge.php',
    'IntelligenceKernel'    => '/Platform/IntelligenceKernel.php',
    // Phase-9 Cognitive Institution Mode
    'InstitutionalMemory'   => '/Cognitive/InstitutionalMemory.php',
    'EthicalGuard'          => '/Cognitive/EthicalGuard.php',
    'InstitutionalModel'    => '/Cognitive/InstitutionalModel.php',
    'PolicyEngine'          => '/Cognitive/PolicyEngine.php',
    'AcademicReasoner'      => '/Cognitive/AcademicReasoner.php',
    'AdaptiveLearningEngine' => '/Cognitive/AdaptiveLearningEngine.php',
    'HumanInteractionModel' => '/Cognitive/HumanInteractionModel.php',
    'InsightGenerator'      => '/Cognitive/InsightGenerator.php',
    'CognitiveKernel'       => '/Cognitive/CognitiveKernel.php',
    // Phase-10 Autonomous Educational Ecosystem
    'TrustBoundary'         => '/Ecosystem/TrustBoundary.php',
    'ConsensusGuard'        => '/Ecosystem/ConsensusGuard.php',
    'TenantOrchestrator'    => '/Ecosystem/TenantOrchestrator.php',
    'FederationEngine'      => '/Ecosystem/FederationEngine.php',
    'KnowledgeExchange'     => '/Ecosystem/KnowledgeExchange.php',
    'DeploymentManager'     => '/Ecosystem/DeploymentManager.php',
    'EcosystemAnalytics'    => '/Ecosystem/EcosystemAnalytics.php',
    'EcosystemKernel'       => '/Ecosystem/EcosystemKernel.php',
    'EventBus'              => '/Events/EventBus.php',
    // Phase-11 Self-Healing Platform Architecture
    'HealingKernel'             => '/SelfHealing/HealingKernel.php',
    'FaultDetector'             => '/SelfHealing/FaultDetector.php',
    'SelfHealingRepairEngine'   => '/SelfHealing/AutoRepairEngine.php',
    'RouteRepairer'             => '/SelfHealing/RouteRepairer.php',
    'SchemaRepairer'            => '/SelfHealing/SchemaRepairer.php',
    'UIIntegrityChecker'        => '/SelfHealing/UIIntegrityChecker.php',
    'ServiceRestarter'          => '/SelfHealing/ServiceRestarter.php',
    'CacheSynchronizer'         => '/SelfHealing/CacheSynchronizer.php',
    'IntegrityVerifier'         => '/SelfHealing/IntegrityVerifier.php',
    'HealingMemory'             => '/SelfHealing/HealingMemory.php',
    // Phase-12 Autonomous School Operating System (ASOS)
    'OSKernel'              => '/OS/OSKernel.php',
    'ProcessScheduler'      => '/OS/ProcessScheduler.php',
    'IdentityCore'          => '/OS/IdentityCore.php',
    'InstitutionalState'    => '/OS/InstitutionalState.php',
    'AcademicRuntime'       => '/OS/AcademicRuntime.php',
    'CommunicationOS'       => '/OS/CommunicationOS.php',
    'AutomationEngine'      => '/OS/AutomationEngine.php',
    'DeviceIntegration'     => '/OS/DeviceIntegration.php',
    'PolicyRuntime'         => '/OS/PolicyRuntime.php',
    'ResourceManager'       => '/OS/ResourceManager.php',
    // Phase-11 Autonomous Command Intelligence (ACI)
    'CommandBrain'          => '/ACI/CommandBrain.php',
    'SystemObserver'        => '/ACI/SystemObserver.php',
    'CommandPredictor'      => '/ACI/CommandPredictor.php',
    'ACIDecisionEngine'     => '/ACI/ACIDecisionEngine.php',
    'RecommendationEngine'  => '/ACI/RecommendationEngine.php',
    'AutoCommander'         => '/ACI/AutoCommander.php',
    'RiskAnalyzer'          => '/ACI/RiskAnalyzer.php',
    'LearningMemory'        => '/ACI/LearningMemory.php',
    'NavigationGuardian'    => '/ACI/NavigationGuardian.php',
    'CommandAPI'            => '/ACI/CommandAPI.php',
    // Autonomous Institutional Consciousness (AIC)
    'InstitutionBrain'              => '/AIC/InstitutionBrain.php',
    'AttendanceInsights'            => '/AIC/AttendanceInsights.php',
    'WorkloadBalancer'              => '/AIC/WorkloadBalancer.php',
    'StudentEngagementAI'           => '/AIC/StudentEngagementAI.php',
    'AcademicPredictor'             => '/AIC/AcademicPredictor.php',
    'InstitutionalBehaviorAnalyzer' => '/AIC/InstitutionalBehaviorAnalyzer.php',
    'PolicyAdvisor'                 => '/AIC/PolicyAdvisor.php',
    // Services
    'AiDocumentationService'  => '/services/AiDocumentationService.php',
    'AiExtractionService'     => '/services/AiExtractionService.php',
    'BackupService'           => '/services/BackupService.php',
    'ThemeService'            => '/services/ThemeService.php',
    'JsonMemoryCache'         => '/services/JsonMemoryCache.php',
    // Controllers
    'AiAdminController'       => '/controllers/AiAdminController.php',
    'DocumentationController' => '/controllers/DocumentationController.php',
    // Middleware & Operational Flow
    'RequestPipeline'         => '/middleware/RequestPipeline.php',
    'RedirectProtection'      => '/middleware/redirect.php',
    'FailureContainment'      => '/Core/FailureContainment.php',
    'SystemHealthScore'       => '/Core/SystemHealthScore.php',
    // Governance & Execution Blueprint
    'GovernanceEngine'        => '/Core/GovernanceEngine.php',
    'ValidationPipeline'      => '/Core/ValidationPipeline.php',
    'SystemLogger'            => '/Core/SystemLogger.php',
  ];

  // Chat module classes (outside app/ — loaded from modules/)
  $chatMap = [
    'Conversations'      => '/modules/chat/Conversations.php',
    'MessageQueue'        => '/modules/chat/MessageQueue.php',
    'PresenceService'     => '/modules/chat/PresenceService.php',
    'MediaHandler'        => '/modules/chat/MediaHandler.php',
    'NotificationBridge'  => '/modules/chat/NotificationBridge.php',
    'RealtimeGateway'     => '/modules/chat/RealtimeGateway.php',
  ];

  if (isset($chatMap[$class])) {
    $file = BASE_PATH . $chatMap[$class];
    if (is_file($file)) {
      require_once $file;
    }
    return;
  }

  if (isset($map[$class])) {
    $file = $appDir . $map[$class];
    if (is_file($file)) {
      require_once $file;
    }
  }
});

// Register default event listeners on first load
try {
  require_once $appDir . '/Events/EventDispatcher.php';
  require_once $appDir . '/Events/SystemEvents.php';
  require_once $appDir . '/Events/EventListeners.php';
  EventListeners::register();
} catch (\Throwable $e) {
  error_log("Phase2 service loader: Event registration failed: " . $e->getMessage());
}

// Initialize Phase-3 Security Event Bus
try {
  require_once $appDir . '/Security/SecurityEventBus.php';
  SecurityEventBus::init();
} catch (\Throwable $e) {
  error_log("Phase3 service loader: SecurityEventBus init failed: " . $e->getMessage());
}

// Initialize Phase-4 Enterprise Error Handler
try {
  require_once $appDir . '/Core/ErrorHandler.php';
  ErrorHandler::register();
} catch (\Throwable $e) {
  error_log("Phase4 service loader: ErrorHandler init failed: " . $e->getMessage());
}
