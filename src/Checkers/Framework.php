<?php

namespace MajidAlaeinia\Health\Checkers;

use MajidAlaeinia\Health\Support\Result;

class Framework extends Base
{
    /**
     * @return Result
     */
    public function check()
    {
        return $this->makeHealthyResult();
    }
}
