<?php 

namespace Zakirkun\Jett;

use Zakirkun\Jett\Database\Connection;

/**
 * Summary of Jett
 * @author Zakirkun
 * @version 1.0
 * @since 2025-02-14
 */
class Jett {
    public function __construct(array $config = []) {
        if (!empty($config)) {
            Connection::setConfig($config);
        }
    }

    public static function configure(array $config): void {
        Connection::setConfig($config);
    }
}
