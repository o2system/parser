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

namespace O2System\Parser\Registries;

/**
 * Class Config
 *
 * @package O2System\Parser\Metadata
 */
class Config extends \O2System\Kernel\Registries\Config
{
    public function __construct ( array $config = [ ] )
    {
        $defaultConfig = [
            'driver'       => 'moustache',
            'phpScripts'   => true,
            'phpFunctions' => true,
            'phpConstants' => true,
            'phpGlobals'   => true,
        ];

        $config = array_merge( $defaultConfig, $config );

        parent::__construct( $config );
    }
}