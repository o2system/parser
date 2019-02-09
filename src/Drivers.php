<?php
/**
 * This file is part of the O2System Framework package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author         Steeve Andrian Salim
 * @copyright      Copyright (c) Steeve Andrian Salim
 */

// ------------------------------------------------------------------------

namespace O2System\Parser;

// ------------------------------------------------------------------------

use O2System\Psr\Parser\ParserDriverInterface;
use O2System\Psr\Parser\ParserEngineInterface;
use O2System\Psr\Patterns\Structural\Provider\AbstractProvider;
use O2System\Psr\Patterns\Structural\Provider\ValidationInterface;

/**
 * Class Drivers
 *
 * @package O2System\Parser
 */
class Drivers extends AbstractProvider implements ValidationInterface
{
    /**
     * Drivers::$config
     * 
     * Compiler Config
     *
     * @var DataStructures\Config
     */
    private $config;

    /**
     * Drivers::$sourceFilePath
     *
     * Compiler Source File Path
     *
     * @var string
     */
    private $sourceFilePath;

    /**
     * Drivers::$sourceFileDirectory
     *
     * Compiler Source File Directory
     *
     * @var string
     */
    private $sourceFileDirectory;

    /**
     * Drivers::$sourceString
     *
     * Compiler Source String
     *
     * @var string
     */
    private $sourceString;

    /**
     * Drivers::$vars
     *
     * Compiler Vars
     *
     * @var array
     */
    private $vars = [];

    // ------------------------------------------------------------------------

    /**
     * Drivers::__construct
     *
     * @param DataStructures\Config $config
     */
    public function __construct(DataStructures\Config $config)
    {
        language()
            ->addFilePath(__DIR__ . DIRECTORY_SEPARATOR)
            ->loadFile('parser');

        $this->config = $config;

        if ($this->config->offsetExists('driver')) {
            $this->loadDriver($this->config->driver, $this->config->getArrayCopy());
        }

        if ($this->config->offsetExists('drivers')) {
            foreach ($this->config->drivers as $driver => $config) {
                if (is_string($driver)) {
                    $this->loadDriver($driver, $config);
                } else {
                    $this->loadDriver($config);
                }
            }
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Drivers::loadDriver
     *
     * @param string  $driverOffset
     * @param array   $config
     *
     * @return bool
     */
    public function loadDriver($driverOffset, array $config = [])
    {
        $driverClassName = '\O2System\Parser\Drivers\\' . ucfirst($driverOffset) . 'Driver';

        if (class_exists($driverClassName)) {
            if (isset($config[ 'engine' ])) {
                unset($config[ 'engine' ]);
            }

            $this->register((new $driverClassName())->initialize($config), $driverOffset);

            return $this->__isset($driverOffset);
        }

        return false;
    }

    // ------------------------------------------------------------------------

    /**
     * Drivers::addDriver
     *
     * @param \O2System\Parser\Abstracts\AbstractDriver $driver
     * @param string|null                               $driverOffset
     *
     * @return bool
     */
    public function addDriver(Abstracts\AbstractDriver $driver, $driverOffset = null)
    {
        $driverOffset = (empty($driverOffset) ? get_class_name($driver) : $driverOffset);
        $driverOffset = strtolower($driverOffset);

        if ($this->config->offsetExists($driverOffset)) {
            $config = $this->config[ $driverOffset ];
        } else {
            $config = $this->config->getArrayCopy();
        }

        if (isset($config[ 'engine' ])) {
            unset($config[ 'engine' ]);
        }

        if ($driver->isInitialize()) {
            $this->register($driver, $driverOffset);
        } else {
            $this->register($driver->initialize($config), $driverOffset);
        }

        return $this->__isset($driverOffset);
    }

    // ------------------------------------------------------------------------

    /**
     * Drivers::getSourceString
     *
     * @return string
     */
    public function getSourceString()
    {
        return $this->sourceString;
    }

    // ------------------------------------------------------------------------

    /**
     * Drivers::loadFile
     *
     * @param string $filePath
     *
     * @return bool
     */
    public function loadFile($filePath)
    {
        if ($filePath instanceof \SplFileInfo) {
            $filePath = $filePath->getRealPath();
        }

        if (isset($this->sourceFileDirectory)) {
            if (is_file($this->sourceFileDirectory . $filePath)) {
                $filePath = $this->sourceFileDirectory . $filePath;
            }
        }

        if (is_file($filePath)) {
            $this->sourceFilePath = realpath($filePath);
            $this->sourceFileDirectory = pathinfo($this->sourceFilePath, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR;

            return $this->loadString(file_get_contents($filePath));
        }

        return false;
    }

    // ------------------------------------------------------------------------

    /**
     * Drivers::loadString
     *
     * @param string $string
     *
     * @return bool
     */
    public function loadString($string)
    {
        $this->sourceString = $string;

        if ($this->config->allowPhpScripts === false) {
            $this->sourceString = preg_replace(
                '/<\\?.*(\\?>|$)/Us',
                '',
                str_replace('<?=', '<?php echo ', $this->sourceString)
            );
        }

        $this->sourceString = str_replace(
            [
                '__DIR__',
                '__FILE__',
            ],
            [
                "'" . $this->getSourceFileDirectory() . "'",
                "'" . $this->getSourceFilePath() . "'",
            ],
            $this->sourceString
        );

        return empty($this->sourceString);
    }

    // ------------------------------------------------------------------------

    /**
     * Drivers::getSourceFileDirectory
     *
     * @return string
     */
    public function getSourceFileDirectory()
    {
        return $this->sourceFileDirectory;
    }

    // ------------------------------------------------------------------------

    /**
     * Drivers::getSourceFilePath
     *
     * @return string
     */
    public function getSourceFilePath()
    {
        return $this->sourceFilePath;
    }

    // ------------------------------------------------------------------------

    /**
     * Drivers::parse
     *
     * @param array $vars
     *
     * @return bool|string Returns FALSE if failed.
     */
    public function parse(array $vars = [])
    {
        $output = $this->parsePhp($vars);

        foreach ($this->getIterator() as $driverName => $driverEngine) {
            if ($driverEngine instanceof ParserDriverInterface) {
                if ($driverEngine->isSupported() === false) {
                    continue;
                }

                $engine =& $driverEngine->getEngine();

                if ($engine instanceof ParserEngineInterface) {
                    $engine->addFilePath($this->sourceFileDirectory);
                }

                $driverEngine->loadString($output);
                $output = $driverEngine->parse($this->vars);
            }
        }

        return $output;
    }

    // ------------------------------------------------------------------------

    /**
     * Drivers::parsePhp
     *
     * @param array $vars
     *
     * @return bool|string Returns FALSE if failed
     */
    public function parsePhp(array $vars = [])
    {
        $this->loadVars($vars);

        extract($this->vars);

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

        echo eval('?>' . str_replace([';?>', ')?>', ');?>'], ['; ?>', '); ?>', '); ?>'], $this->sourceString));

        $output = ob_get_contents();
        @ob_end_clean();

        $lastError = error_get_last();

        if (is_array($lastError)) {
            $this->errorFilePath = $this->getSourceFilePath();
        }

        return $output;
    }

    // ------------------------------------------------------------------------

    /**
     * Drivers::loadVars
     *
     * @param array $vars
     *
     * @return bool
     */
    public function loadVars(array $vars)
    {
        $this->vars = array_merge($this->vars, $vars);

        return (bool)empty($this->vars);
    }

    // ------------------------------------------------------------------------

    /**
     * Drivers::isValid
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function validate($value)
    {
        if ($value instanceof ParserDriverInterface) {
            return true;
        }

        return false;
    }

    // ------------------------------------------------------------------------

    /**
     * Drivers::validateSyntax
     *
     * Check the syntax of some PHP code.
     *
     * @param string $sourceCode PHP code to check.
     *
     * @return bool|array If false, then check was successful, otherwise an array(message,line) of errors is
     *                       returned.
     */
    function validateSyntax($sourceCode)
    {
        if ( ! defined("CR")) {
            define("CR", "\r");
        }
        if ( ! defined("LF")) {
            define("LF", "\n");
        }
        if ( ! defined("CRLF")) {
            define("CRLF", "\r\n");
        }
        $braces = 0;
        $inString = 0;
        foreach (token_get_all('<?php ' . $sourceCode) as $token) {
            if (is_array($token)) {
                switch ($token[ 0 ]) {
                    case T_CURLY_OPEN:
                    case T_DOLLAR_OPEN_CURLY_BRACES:
                    case T_START_HEREDOC:
                        ++$inString;
                        break;
                    case T_END_HEREDOC:
                        --$inString;
                        break;
                }
            } else {
                if ($inString & 1) {
                    switch ($token) {
                        case '`':
                        case '\'':
                        case '"':
                            --$inString;
                            break;
                    }
                } else {
                    switch ($token) {
                        case '`':
                        case '\'':
                        case '"':
                            ++$inString;
                            break;
                        case '{':
                            ++$braces;
                            break;
                        case '}':
                            if ($inString) {
                                --$inString;
                            } else {
                                --$braces;
                                if ($braces < 0) {
                                    break 2;
                                }
                            }
                            break;
                    }
                }
            }
        }
        $inString = @ini_set('log_errors', false);
        $token = @ini_set('display_errors', true);
        ob_start();
        $sourceCode = substr($sourceCode, strlen('<?php '));
        $braces || $sourceCode = "if(0){{$sourceCode}\n}";
        if (eval($sourceCode) === false) {
            if ($braces) {
                $braces = PHP_INT_MAX;
            } else {
                false !== strpos($sourceCode, CR) && $sourceCode = strtr(str_replace(CRLF, LF, $sourceCode), CR, LF);
                $braces = substr_count($sourceCode, LF);
            }
            $sourceCode = ob_get_clean();
            $sourceCode = strip_tags($sourceCode);
            if (preg_match("'syntax error, (.+) in .+ on line (\d+)$'s", $sourceCode, $sourceCode)) {
                $sourceCode[ 2 ] = (int)$sourceCode[ 2 ];
                $sourceCode = $sourceCode[ 2 ] <= $braces
                    ? [$sourceCode[ 1 ], $sourceCode[ 2 ]]
                    : ['unexpected $end' . substr($sourceCode[ 1 ], 14), $braces];
            } else {
                $sourceCode = ['syntax error', 0];
            }
        } else {
            ob_end_clean();
            $sourceCode = false;
        }
        @ini_set('display_errors', $token);
        @ini_set('log_errors', $inString);

        return $sourceCode;
    }
}