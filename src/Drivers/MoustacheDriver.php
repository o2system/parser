<?php
/**
 * This file is part of the O2System PHP Framework package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author         Steeve Andrian Salim
 * @copyright      Copyright (c) Steeve Andrian Salim
 */
// ------------------------------------------------------------------------

namespace O2System\Parser\Drivers;

// ------------------------------------------------------------------------

use O2System\Parser\Abstracts\AbstractDriver;
use O2System\Parser\Engines\Moustache;

/**
 * Class MoustacheDriver
 *
 * @package O2System\Parser\Drivers
 */
class MoustacheDriver extends AbstractDriver
{
    /**
     * MoustacheDriver::initialize
     *
     * @param array $config
     *
     * @return static
     */
    public function initialize( array $config )
    {
        if ( empty( $this->engine ) ) {
            $this->engine = new Moustache( $config );
        }

        return $this;
    }

    // ------------------------------------------------------------------------

    /**
     * MoustacheDriver::parse
     *
     * @param array $vars Variable to be parsed.
     *
     * @return string
     */
    public function parse( array $vars = [] )
    {
        return $this->engine->parseString( $this->string, $vars );
    }

    // ------------------------------------------------------------------------

    /**
     * MoustacheDriver::isSupported
     *
     * Checks if this template engine is supported on this system.
     *
     * @return bool
     */
    public function isSupported()
    {
        if ( class_exists( '\O2System\Parser\Engines\Moustache' ) ) {
            return true;
        }

        return false;
    }

    // ------------------------------------------------------------------------

    /**
     * MoustacheDriver::isValidEngine
     *
     * Checks if is a valid Object Engine.
     *
     * @param object $engine Engine Object Resource.
     *
     * @return bool
     */
    protected function isValidEngine( $engine )
    {
        if ( $engine instanceof Moustache ) {
            return true;
        }

        return false;
    }
}