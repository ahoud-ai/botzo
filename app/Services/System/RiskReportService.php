<?php

namespace App\Services\System;

use Symfony\Component\Process\Process;

class RiskReportService
{
    public function build(array $readinessReport): array
    {
        $axes = (array) ($readinessReport['axes'] ?? []);

        $testReliabilityAxis = $this->axis($axes, 'test_reliability');
        $cleanlinessAxis = $this->axis($axes, 'cleanliness');
        $scalabilityAxis = $this->axis($axes, 'scalability');
        $docsParityAxis = $this->axis($axes, 'docs_code_parity');
        $operationalSecurityAxis = $this->axis($axes, 'operational_security');

        $worktreeDirty = $this->isWorktreeDirty();

        $testProbability = $testReliabilityAxis['status'] === 'skipped'
            ? 50.0
            : max(5.0, 100.0 - (float) $testReliabilityAxis['score']);
        $cleanlinessProbability = max(5.0, 100.0 - (float) $cleanlinessAxis['score']);
        $scalabilityProbability = max(5.0, 100.0 - (float) $scalabilityAxis['score']);
        $docsProbability = max(5.0, 100.0 - (float) $docsParityAxis['score']);
        $securityProbability = $operationalSecurityAxis['status'] === 'skipped'
            ? 50.0
            : max(5.0, 100.0 - (float) $operationalSecurityAxis['score']);

        $risks = [
            $this->buildRisk(
                'flaky_test_suite',
                'Suite instability and order-dependent tests.',
                $testProbability,
                85.0
            ),
            $this->buildRisk(
                'hotspot_maintainability',
                'Large hotspots increase regression risk during change.',
                $cleanlinessProbability,
                80.0
            ),
            $this->buildRisk(
                'non_scale_ready_profile',
                'Runtime profile is not fully scale-ready.',
                $scalabilityProbability,
                90.0
            ),
            $this->buildRisk(
                'documentation_drift',
                'Documentation claims drift from executable code state.',
                $docsProbability,
                45.0
            ),
            $this->buildRisk(
                'dirty_release_workspace',
                'Large uncommitted workspace increases release uncertainty.',
                $worktreeDirty ? 70.0 : 15.0,
                70.0
            ),
            $this->buildRisk(
                'monitoring_and_security_gaps',
                'Security and operational gates are not fully hardened.',
                $securityProbability,
                75.0
            ),
        ];

        $maxRiskScore = round(
            collect($risks)->max(static fn (array $risk): float => (float) $risk['risk_score']) ?: 0.0,
            2
        );
        $averageRiskScore = round(
            collect($risks)->avg(static fn (array $risk): float => (float) $risk['risk_score']) ?: 0.0,
            2
        );

        return [
            'status' => $maxRiskScore <= 40.0 ? 'acceptable' : 'action_required',
            'overall_readiness_score' => (float) ($readinessReport['overall_score'] ?? 0.0),
            'max_risk_score' => $maxRiskScore,
            'average_risk_score' => $averageRiskScore,
            'worktree_dirty' => $worktreeDirty,
            'risks' => $risks,
            'timestamp' => now()->toISOString(),
        ];
    }

    private function buildRisk(string $id, string $title, float $probability, float $impact): array
    {
        $riskScore = round(($probability * $impact) / 100.0, 2);

        return [
            'id' => $id,
            'title' => $title,
            'probability' => round($probability, 2),
            'impact' => round($impact, 2),
            'risk_score' => $riskScore,
            'severity' => $this->severity($riskScore),
        ];
    }

    private function axis(array $axes, string $key): array
    {
        $score = $axes[$key]['score'] ?? null;
        $status = (string) ($axes[$key]['status'] ?? 'missing');

        return [
            'score' => is_numeric($score) ? (float) $score : 0.0,
            'status' => $status,
        ];
    }

    private function severity(float $riskScore): string
    {
        if ($riskScore >= 60.0) {
            return 'critical';
        }

        if ($riskScore >= 40.0) {
            return 'high';
        }

        if ($riskScore >= 20.0) {
            return 'medium';
        }

        return 'low';
    }

    private function isWorktreeDirty(): bool
    {
        try {
            $process = new Process(['git', 'status', '--porcelain'], base_path());
            $process->setTimeout(60);
            $process->run();

            if ($process->getExitCode() !== 0) {
                return true;
            }

            return trim($process->getOutput()) !== '';
        } catch (\Throwable $exception) {
            return true;
        }
    }
}
