<?php
declare(strict_types=1);

namespace Blackbird\SelectiveCron\Console\Command;

use Blackbird\SelectiveCron\Api\Enums\CommandOptionsEnum;
use Blackbird\SelectiveCron\Model\Config;
use Magento\Framework\Console\Cli;
use Magento\Framework\Crontab\CrontabManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for installing selective cron job in crontab
 */
class InstallSelectiveCronCommand extends Command
{

    public function __construct(
        protected readonly CrontabManagerInterface $crontabManager,
        protected readonly Config $config
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('cron:install:selective')
            ->setDescription('Generates and installs crontab for selective cron jobs')
            ->addOption(CommandOptionsEnum::FORCE->value, 'f', InputOption::VALUE_NONE, 'Force install tasks');

        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->config->isEnabled()) {
            $output->writeln('<info>Selective Cron is disabled. No tasks were installed.</info>');
            return Cli::RETURN_SUCCESS;
        }

        if ($this->crontabManager->getTasks() && !$input->getOption(CommandOptionsEnum::FORCE->value)) {
            $output->writeln('<error>Crontab has already been generated and saved</error>');
            return Cli::RETURN_FAILURE;
        }

        try {
            $tasks = [
                [
                    'expression' => '* * * * *',
                    'command' => '{magentoRoot}bin/magento cron:run:selective  >> {magentoLog}magento.selective-cron.log 2>&1',
                    'optional' => false
                ]
            ];

            $this->crontabManager->saveTasks($tasks);
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }

        $output->writeln('<info>Selective cron tasks have been generated and saved</info>');

        return Cli::RETURN_SUCCESS;
    }
}
