<?php

namespace PicoAuth\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Optional logger trait.
 * By using this trait, a class can be injected with a logger instance.
 */
trait LoggerTrait
{

    /**
     * Logger instance.
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Sets logger.
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Gets logger.
     * If the logger instance is not set beforehand, it is set to NullLogger and returned.
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        if (!$this->logger) {
            $this->logger = new NullLogger();
        }
        return $this->logger;
    }
}
