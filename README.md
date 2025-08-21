# Selective Cron Module for Magento 2

[![Latest Stable Version](https://img.shields.io/packagist/v/blackbird/selective-cron.svg?style=flat-square)](https://packagist.org/packages/blackbird/selective-cron)
[![License: MIT](https://img.shields.io/github/license/blackbird-agency/magento-2-selective-cron.svg?style=flat-square)](./LICENSE)

The Selective Cron module allows you to selectively execute only specific cron jobs. This is useful for debugging, testing, or when you want to run only certain cron jobs without affecting others.

## Features
- Configuration in the admin panel to select which cron jobs to execute
- Custom command line interface similar to `cron:run` to execute only selected cron jobs
- Dedicated database table to track scheduled cron jobs
- Automatic execution of selected cron jobs at their scheduled times
- Continuous scheduling of new job instances according to their own periodicity
- Dedicated logging for selective cron execution

## Requirements

- PHP >= 8.1
- Magento >= 2.4.4

## Setup

### Get the package

**Zip Package:**

Unzip the package in app/code/Blackbird/SelectiveCron, from the root of your Magento instance.

**Composer Package:**

```
composer require blackbird/selective-cron
```

### Install the module

Go to your Magento root, then run the following Magento command:

```
php bin/magento setup:upgrade
```

**If you are in production mode, do not forget to recompile and redeploy the static resources, or to use the `--keep-generated` option.**

## Configuration
1. Go to Stores > Configuration > Advanced > System > Cron (Selective Cron)
2. Select the cron jobs you want to execute
3. Save the configuration

When you save the configuration, the module will automatically:
- Clear the existing selective cron schedule
- Create new schedule entries for the selected cron jobs

## Usage
The module provides multiple ways to execute and manage the selected cron jobs:

### Automatic Execution
The module includes two cron jobs for automatic execution:

1. **Execution Cron Job**: Runs every minute to check for and execute any scheduled selective cron jobs that are due to run. This ensures that your selected cron jobs run at their scheduled times without manual intervention.

2. **Scheduling Cron Job**: Runs every 15 minutes to check for jobs that need new instances and adds them to the schedule table. This ensures that new instances of selected cron jobs are continuously added according to their own periodicity.

The scheduling mechanism:
- Calculates the next run time based on each job's cron expression
- Ensures that there's always at least one pending instance for each selected job
- Schedules new instances ahead of time (up to 1 hour in advance)
- Respects each job's individual schedule (e.g., hourly, daily, weekly)

### Manual Execution
To manually run the selected cron jobs, use the following command:
```bash
bin/magento cron:run:selective
```

This command will:
1. Check if there are any pending jobs in the selective cron schedule
2. Execute any jobs that are due to run
3. Update the job status in the schedule table

### System Crontab Integration
The module provides commands to install and remove the selective cron job from the system crontab:

#### Installing in System Crontab
To install the selective cron job in the system crontab, use the following command:
```bash
bin/magento cron:install:selective
```

This command will:
1. Check if the selective cron functionality is enabled in the configuration
2. Add a crontab entry to run `bin/magento cron:run:selective` every minute
3. The entry will only be added if the selective cron functionality is enabled

If crontab entries already exist, you can use the `--force` option to overwrite them:
```bash
bin/magento cron:install:selective --force
```

#### Removing from System Crontab
To remove the selective cron job from the system crontab, use the following command:
```bash
bin/magento cron:remove:selective
```

This command will:
1. Remove only the selective cron entries from the system crontab
2. Leave other Magento cron entries intact

## Database
The module creates a `selective_cron_schedule` table that mirrors Magento's `cron_schedule` table. This table stores information about scheduled selective cron jobs, including:
- Job code
- Status (pending, running, success, error, missed)
- Scheduled time
- Execution time
- Completion time
- Error messages (if any)

## Logging
Logs are stored in `var/log/selective_cron.log`

## Support

- If you have any issue with this code, feel free to [open an issue](https://github.com/blackbird-agency/magento-2-selective-cron/issues/new).
- If you want to contribute to this project, feel free to [create a pull request](https://github.com/blackbird-agency/magento-2-selective-cron/compare).

## Contact

For further information, contact us:

- by email: hello@bird.eu
- or by form: [https://black.bird.eu/en/contacts/](https://black.bird.eu/contacts/)

## Authors

- [**Perrine Verbrugghe**](https://github.com/perrine-blackbird) - *Maintainer* -
- [**Blackbird Team**](https://github.com/blackbird-agency) - *Contributor* -

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

***That's all folks!***
