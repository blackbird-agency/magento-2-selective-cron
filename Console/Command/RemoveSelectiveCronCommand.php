<?php
declare(strict_types=1);

namespace Blackbird\SelectiveCron\Console\Command;

use Magento\Framework\Console\Cli;
use Magento\Framework\Crontab\CrontabManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for removing selective cron job from crontab
 */
class RemoveSelectiveCronCommand extends Command
{
    public function __construct(
        protected readonly CrontabManagerInterface $crontabManager
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('cron:remove:selective')
            ->setDescription('Removes selective cron tasks from crontab');

        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            // Get current tasks
            $currentTasks = $this->crontabManager->getTasks();
            
            // Filter out selective cron tasks
            $tasksToKeep = [];
            foreach ($currentTasks as $task) {
                if (strpos($task, 'bin/magento cron:run:selective') === false) {
                    $tasksToKeep[] = $task;
                }
            }
            
            // If there are tasks to keep, save them; otherwise, remove all tasks
            if (!empty($tasksToKeep)) {
                // Convert tasks to the format expected by saveTasks
                $formattedTasks = [];
                foreach ($tasksToKeep as $task) {
                    // Extract expression and command from the task
                    $parts = preg_split('/\s+/', $task, 6);
                    if (count($parts) >= 6) {
                        $expression = implode(' ', array_slice($parts, 0, 5));
                        $command = $parts[5];
                        $formattedTasks[] = [
                            'expression' => $expression,
                            'command' => $command,
                            'optional' => false
                        ];
                    }
                }
                
                $this->crontabManager->saveTasks($formattedTasks);
            } else {
                $this->crontabManager->removeTasks();
            }
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }

        $output->writeln('<info>Selective cron tasks have been removed from crontab</info>');

        return Cli::RETURN_SUCCESS;
    }
}
