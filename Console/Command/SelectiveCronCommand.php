<?php
declare(strict_types=1);

namespace Blackbird\SelectiveCron\Console\Command;

use Blackbird\SelectiveCron\Service\CronService;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SelectiveCronCommand extends Command
{
    public function __construct(
        protected readonly CronService $cronService
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('cron:run:selective')
            ->setDescription('Runs only selected cron jobs');
        
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $output->writeln('<info>Running selective cron jobs...</info>');
            $this->cronService->executeSelectedCronJobs();
            $output->writeln('<info>Selective cron jobs completed.</info>');
            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }
    }
}
