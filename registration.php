<?php
/**
 * @author Roberto Ballesteros <heartub.api@gmail.com>
 * @package CartCloneGraphQl - Clone a new guest cart in Magento 2
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Heartub_CartCloneGraphQl',
    __DIR__
);
