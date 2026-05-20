<?php

declare(strict_types=1);

$paths = [
    'app/Modules',
    'app/Console/Commands/SystemPrepareTestingDatabaseCommand.php',
    'app/Console/Commands/SystemTestSafetyCheckCommand.php',
    'app/Services/System/TestingDatabasePreparationService.php',
    'app/Services/AutomationFlows/AutomationFlowAssetService.php',
    'app/Services/WhatsappService.php',
    'app/Http/Controllers/Admin/UtilityController.php',
    'app/Http/Controllers/Admin/PaymentGatewayController.php',
    'app/Http/Controllers/Admin/SettingController.php',
    'app/Http/Controllers/User/AutomationFlowController.php',
    'app/Providers/AppServiceProvider.php',
    'app/Providers/MailConfigServiceProvider.php',
    'app/Providers/RecaptchaServiceProvider.php',
    'app/Models/AutomationFlow.php',
    'app/Models/AutomationFlowAsset.php',
    'config/automation_flows.php',
    'config/platform.php',
    'config/architecture.php',
    'routes/api',
    'routes/api.php',
    'routes/web',
    'routes/web.php',
    'scripts/check-repo-hygiene.php',
    'tests/bootstrap.php',
    'tests/TestCase.php',
    'tests/Feature/AutomationFlowAssetAccessTest.php',
    'tests/Feature/ArchitectureEnvBoundaryTest.php',
    'tests/Feature/ArchitectureBudgetGuardTest.php',
    'tests/Feature/RouteContractSnapshotTest.php',
    'tests/Feature/SystemPrepareTestingDatabaseCommandTest.php',
    'tests/Unit/WhatsappAccountInspectionServiceTest.php',
];

$projectRoot = dirname(__DIR__);
$command = array_merge(
    [PHP_BINARY, $projectRoot.'/vendor/bin/pint', '--test'],
    $paths
);

$descriptorSpec = [
    0 => STDIN,
    1 => STDOUT,
    2 => STDERR,
];

$process = proc_open($command, $descriptorSpec, $pipes, $projectRoot);
if (! is_resource($process)) {
    fwrite(STDERR, "Unable to start Pint foundation lint.\n");
    exit(1);
}

$exitCode = proc_close($process);
exit(is_int($exitCode) ? $exitCode : 1);
