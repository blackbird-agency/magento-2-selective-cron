<?php
/**
 * Blackbird Selective Cron
 *
 * NOTICE OF LICENSE
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to support@bird.eu so we can send you a copy immediately.
 *
 * @category        Blackbird
 * @package         Blackbird_SelectiveCron
 * @copyright       Copyright (c) Blackbird (https://black.bird.eu)
 * @author          Perrine Vebrugghe (hello@bird.eu)
 * @license         MIT
 * @support         https://github.com/blackbird-agency/magento-2-selective-cron/issues/new
 */

declare(strict_types=1);


\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'Blackbird_SelectiveCron',
    __DIR__
);