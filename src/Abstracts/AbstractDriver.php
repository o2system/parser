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

namespace O2System\Parser\Abstracts;

// ------------------------------------------------------------------------

use O2System\Psr\Parser\ParserDriverInterface;

/**
 * Class AbstractDriver
 *
 * @package O2System\Parser\Abstracts
 */
abstract class AbstractDriver implements ParserDriverInterface
{
    /**
     * AbstractDriver::$config
     *
     * Driver Config
     *
     * @var array
     */
    protected $config = [
        'allowPhpScripts'   => true,
        'allowPhpGlobals'   => true,
        'allowPhpFunctions' => true,
        'allowPhpConstants' => true,
    ];

    /**
     * AbstractDriver::$engine
     *
     * Driver Engine
     *
     * @var object
     */
    protected $engine;

    /**
     * AbstractDriver::$string
     *
     * Driver Raw String
     *
     * @var string
     */
    protected $string;

    // ------------------------------------------------------------------------

    /**
     * AbstractDriver::loadFile
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

        if (is_file($filePath)) {
            return $this->loadString(file_get_contents($filePath));
        }

        return false;
    }

    // ------------------------------------------------------------------------

    /**
     * AbstractDriver::loadString
     *
     * @param string $string
     *
     * @return bool
     */
    public function loadString($string)
    {
        $this->string = htmlspecialchars_decode($string);

        if ($this->config[ 'allowPhpScripts' ] === false) {
            $this->string = preg_replace(
                '/<\\?.*(\\?>|$)/Us',
                '',
                str_replace('<?=', '<?php echo ', $this->string)
            );
        }

        return (bool)empty($this->string);
    }

    // ------------------------------------------------------------------------

    /**
     * AbstractDriver::isInitialize
     *
     * @return bool
     */
    public function isInitialize()
    {
        return (bool)(empty($this->engine) ? false : true);
    }

    // --------------------------------------------------------------------------------------

    /**
     * AbstractDriver::initialize
     *
     * @param array $config
     *
     * @return static
     */
    abstract public function initialize(array $config);

    // ------------------------------------------------------------------------

    /**
     * AbstractDriver::getEngine
     *
     * @return object
     */
    public function &getEngine()
    {
        return $this->engine;
    }

    // ------------------------------------------------------------------------

    /**
     * AbstractDriver::setEngine
     *
     * @param object $engine
     *
     * @return bool
     */
    public function setEngine($engine)
    {
        if ($this->isValidEngine($engine)) {
            $this->engine =& $engine;

            return true;
        }

        return false;
    }

    // ------------------------------------------------------------------------

    /**
     * AbstractDriver::isValidEngine
     *
     * @param object $engine
     *
     * @return mixed
     */
    abstract protected function isValidEngine($engine);

    // ------------------------------------------------------------------------

    /**
     * AbstractDriver::__call
     *
     * @param string  $method
     * @param array   $arguments
     *
     * @return mixed|null
     */
    public function __call($method, array $arguments = [])
    {
        if (method_exists($this, $method)) {
            return call_user_func_array([&$this, $method], $arguments);
        } elseif (method_exists($this->engine, $method)) {
            return call_user_func_array([&$this->engine, $method], $arguments);
        }

        return null;
    }

    // ------------------------------------------------------------------------

    /**
     * AbstractDriver::isSupported
     *
     * @return bool
     */
    abstract public function isSupported();
}