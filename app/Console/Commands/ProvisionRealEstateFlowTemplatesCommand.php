<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\AutomationFlows\AutomationFlowRealEstateTemplateProvisioner;
use Illuminate\Console\Command;

class ProvisionRealEstateFlowTemplatesCommand extends Command
{
    protected $signature = 'flowbuilder:provision-real-estate-templates
        {organization : Organization id, uuid, or exact name}
        {--user-id= : Explicit user id to own the created flows}
        {--publish : Publish the created templates immediately}
        {--replace : Replace existing templates with the same names}';

    protected $description = 'Provision three real-estate Flow Builder starter templates for an organization.';

    public function __construct(
        private readonly AutomationFlowRealEstateTemplateProvisioner $provisioner,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $organization = $this->resolveOrganization((string) $this->argument('organization'));

        if (!$organization) {
            $this->components->error('Organization not found.');

            return self::FAILURE;
        }

        $report = $this->provisioner->provision(
            $organization,
            $this->option('user-id') ? (int) $this->option('user-id') : null,
            (bool) $this->option('replace'),
            (bool) $this->option('publish'),
        );

        $this->components->info('Real-estate starter templates provisioned.');
        $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->newLine();
        $this->components->warn('Starter templates intentionally skip send_email because the current runtime still depends on contact.email plus confirmed SMTP credentials per account.');

        return self::SUCCESS;
    }

    private function resolveOrganization(string $identifier): ?Organization
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        if (ctype_digit($identifier)) {
            return Organization::query()->find((int) $identifier);
        }

        return Organization::query()
            ->where('uuid', $identifier)
            ->orWhere('name', $identifier)
            ->first();
    }
}
