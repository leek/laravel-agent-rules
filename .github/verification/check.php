<?php

declare(strict_types=1);

namespace Tests {
    use Illuminate\Foundation\Application;

    class TestCase extends \Illuminate\Foundation\Testing\TestCase
    {
        public function initialize(): void
        {
            $this->app = $this->createApplication();
        }

        public function createApplication(): Application
        {
            return $GLOBALS['app'];
        }
    }
}
