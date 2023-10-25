<?php

namespace test_beta;

use framework\Application;

class test_betaApplication extends Application
{
    public function __construct()
    {
        config_path("test_beta/config/app");
        parent::__construct();
    }

    public function boot()
    {
    }
}
