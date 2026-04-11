<?php

namespace App\Console\Commands;

use App\Services\AntiBot\ShadowMetricsReviewReportBuilder;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ShadowReviewAntiBotMetrics extends Command
{
    protected $signature = 'anti-bot:shadow-review
                            {--hours=24 : Rolling lookback window in hours}
                            {--json : Emit the review report as JSON only}';

    protected $description = 'Build the anti-bot shadow metrics review report for login and two-factor surfaces';

    public function __construct(
        private readonly ShadowMetricsReviewReportBuilder $reportBuilder,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $report = $this->reportBuilder->buildRecent($hours);

        if ((bool) $this->option('json')) {
            $this->output->write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return CommandAlias::SUCCESS;
        }

        $this->info('Anti-bot shadow metrics review');
        $this->line("Lookback window: {$hours}h");
        $this->line('Conclusion: '.$report['final_conclusion']);

        return CommandAlias::SUCCESS;
    }
}
