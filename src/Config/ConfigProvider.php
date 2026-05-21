<?php

namespace Igancev\WorkReporter\Config;

interface ConfigProvider
{
    /**
     * @throws ConfigException
     */
    public function getConfig(): Config;
}
