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

namespace O2System\Parser\Engines;

// ------------------------------------------------------------------------

use O2System\Parser\Abstracts\AbstractEngine;
use O2System\Psr\Parser\ParserEngineInterface;

/**
 * Class Mustaches
 *
 * @package O2System\Parser\Engines
 */
class Moustache extends AbstractEngine implements ParserEngineInterface
{
    /**
     * Mustaches File Extensions
     *
     * @var array
     */
    protected $extensions = [
        '.php',
        '.htm',
        '.html',
        '.moustache.php',
        '.phtml',
    ];

    /**
     * Blade Config
     *
     * @var array
     */
    private $config = [
        'allowPhpGlobals'   => true,
        'allowPhpFunctions' => true,
        'allowPhpConstants' => true,
    ];

    // ------------------------------------------------------------------------

    /**
     * Moustache::__construct
     *
     * @param array $config
     */
    public function __construct ( array $config = [ ] )
    {
        $this->config = array_merge( $this->config, $config );
    }

    // ------------------------------------------------------------------------

    public function parseString ( $string, array $vars = [ ] )
    {
        if ( $this->config[ 'allowPhpGlobals' ] === false ) {
            $string = str_replace(
                [
                    '{{$GLOBALS}}',
                    '{{$GLOBALS[%%]}}',
                    '{{$_SERVER}}',
                    '{{$_SERVER[%%]}}',
                    '{{$_GET}}',
                    '{{$_GET[%%]}}',
                    '{{$_POST}}',
                    '{{$_POST[%%]}}',
                    '{{$_FILES}}',
                    '{{$_FILES[%%]}}',
                    '{{$_COOKIE}}',
                    '{{$_COOKIE[%%]}}',
                    '{{$_SESSION}}',
                    '{{$_SESSION[%%]}}',
                    '{{$_REQUEST}}',
                    '{{$_REQUEST[%%]}}',
                    '{{$_ENV}}',
                    '{{$_ENV[%%]}}',
                ],
                '',
                $string
            );
        }

        // php logical codes
        $logicalCodes = [
            '{{if(%%)}}'     => '<?php if(\1): ?>',
            '{{elseif(%%)}}' => '<?php elseif(\1): ?>',
            '{{/if}}'        => '<?php endif; ?>',
            '{{else}}'       => '<?php else: ?>',
        ];

        // php loop codes
        $loopCodes = [
            '{{for(%%)}}'     => '<?php for(\1): ?>',
            '{{/for}}'        => '<?php endfor; ?>',
            '{{foreach(%%)}}' => '<?php foreach(\1): ?>',
            '{{/foreach}}'    => '<?php endforeach; ?>',
            '{{while(%%)}}'   => '<?php while(\1): ?>',
            '{{/while}}'      => '<?php endwhile; ?>',
            '{{continue}}'    => '<?php continue; ?>',
            '{{break}}'       => '<?php break; ?>',
        ];

        // php function codes
        $functionsCodes = [ ];
        if ( $this->config[ 'allowPhpFunctions' ] === false ) {
            $functionsCodes = [
                '{{%%(%%)}}' => '',
            ];
        } elseif ( is_array( $this->config[ 'allowPhpFunctions' ] ) AND count(
                                                                            $this->config[ 'allowPhpFunctions' ]
                                                                        ) > 0
        ) {
            foreach ( $this->config[ 'allowPhpFunctions' ] as $function_name ) {
                $functionsCodes[ '{{' . $function_name . '(%%)}}' ] = '<?php echo ' . $function_name . '(\1); ?>';
            }
        } else {
            $functionsCodes = [
                '{{%%()}}' => '<?php echo \1(); ?>',
                '{{%%(%%)}}' => '<?php echo \1(\2); ?>',
            ];
        }

        // php variables codes
        $variablesCodes = [
            '{{$%%->%%(%%)}}' => '<?php echo $\1->\2(\3); ?>',
            '{{$%%->%%}}'     => '<?php echo $\1->\2; ?>',
            '{{$%%[%%]}}'     => '<?php echo $\1[\'\2\']; ?>',
            '{{$%%.%%}}'      => '<?php echo $\1[\'\2\']; ?>',
            '{{$%% = %%}}'    => '<?php $\1 = \2; ?>',
            '{{$%%++}}'       => '<?php $\1++; ?>',
            '{{$%%--}}'       => '<?php $\1--; ?>',
            '{{$%%}}'         => '<?php echo $\1; ?>',
            '{{/*}}'          => '<?php /*',
            '{{*/}}'          => '*/ ?>',
            '{{ %% OR %% }}'  => '<?php echo ( empty(\1) ? \'\2\' : $\1 ); ?>',
            '{{!!$%%!!}}'     => '<?php echo htmlentities($\1, ENT_HTML5); ?>',
            '{{--%%--}}'      => '',
        ];

        if ( $this->config[ 'allowPhpConstants' ] === true ) {
            $constantsVariables = get_defined_constants( true );

            if ( ! empty( $constantsVariables[ 'user' ] ) ) {
                foreach ( $constantsVariables[ 'user' ] as $constant => $value ) {
                    $variablesCodes[ '{{' . $constant . '}}' ] = '<?php echo ' . $constant . '; ?>';
                }
            }
        }

        $phpCodes = array_merge( $logicalCodes, $loopCodes, $variablesCodes, $functionsCodes );

        $patterns = $replace = [ ];
        foreach ( $phpCodes as $tplCode => $phpCode ) {
            $patterns[] = '#' . str_replace( '%%', '(.+)', preg_quote( $tplCode, '#' ) ) . '#U';
            $replace[] = $phpCode;
        }

        /*replace our pseudo language in template with php code*/
        $string = preg_replace( $patterns, $replace, $string );

        extract( $vars );

        /*
         * Buffer the output
         *
         * We buffer the output for two reasons:
         * 1. Speed. You get a significant speed boost.
         * 2. So that the final rendered template can be post-processed by
         *  the output class. Why do we need post processing? For one thing,
         *  in order to show the elapsed page load time. Unless we can
         *  intercept the content right before it's sent to the browser and
         *  then stop the timer it won't be accurate.
         */
        ob_start();

        echo eval( '?>' . preg_replace( '/;*\s*\?>/', '; ?>', $string ) );

        $output = ob_get_contents();
        @ob_end_clean();

        return $output;
    }
}