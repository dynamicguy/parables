<?php
class Parables_Application_Resource_Doctrine
    extends Zend_Application_Resource_ResourceAbstract
{
    /**
     * @var array
     */
     protected $_resources = array();

    /**
     * Defined by Zend_Application_Resource_Resource
     *
     * @return  void
     * @throws  Zend_Application_Resource_Exception
     */
    public function init()
    {
        if (1 !== (int) substr(Doctrine::VERSION, 0, 1)) {
            require_once 'Zend/Application/Resource/Exception.php';
            throw new Zend_Application_Resource_Exception('Support is limited to Doctrine 1.x.');
        }

        $options = $this->getOptions();

        if (array_key_exists('paths', $options)) {
            $this->_initPaths($options['paths']);
        }

        if (array_key_exists('manager', $options)) {
            $this->_initManager($options['manager']);
        }

        if (array_key_exists('connections', $options)) {
            $this->_initConnections($options['connections']);
        }

        return $this->_resources;
    }

    /**
     * Initialize Doctrine paths
     *
     * @param   array $options
     * @return  void
     */
    protected function _initPaths(array $options)
    {
        $this->_resources['paths'] = array();
        foreach ($options as $key => $value) {
            if (!is_array($value)) {
                require_once 'Zend/Application/Resource/Exception.php';
                throw new Zend_Application_Resource_Exception('Invalid paths settings.');
            }

            foreach ($value as $subKey => $subVal) {
                if (!empty($subVal)) {
                    $path = realpath($subVal);

                    if (!is_dir($path)) {
                        require_once 'Zend/Application/Resource/Exception.php';
                        throw new Zend_Application_Resource_Exception("$subVal does not exist");
                    }

                    $this->_resources['paths'][$key][$subKey] = $path;
                }
            }
        }
    }

    /**
     * Initialize the Doctrine_Manager
     *
     * @param   array $options
     * @return  void
     */
    protected function _initManager(array $options)
    {
        if (array_key_exists('attributes', $options)) {
            $manager = Doctrine_Manager::getInstance();
            $this->_setAttributes($manager, $options['attributes']);
        }
    }

    /**
     * Initialize Doctrine connections
     *
     * @param   array $options
     * @return  void
     * @throws  Zend_Application_Resource_Exception
     */
    protected function _initConnections(array $options)
    {
        $this->_resources['connections'] = array();
        $manager = Doctrine_Manager::getInstance();

        foreach($options as $key => $value) {
            if ((!is_array($value)) || (!array_key_exists('dsn', $value))) {
                require_once 'Zend/Application/Resource/Exception.php';
                throw new Zend_Application_Resource_Exception("Invalid DSN for connection $key.");
            }

            $dsn = (is_array($value['dsn']))
                ? $this->_buildDsnFromArray($value['dsn'])
                : $value['dsn'];

            $conn = $manager->openConnection($dsn, $key);
            $this->_resources['connections'][] = $key;

            if (array_key_exists('attributes', $value)) {
                $this->_setAttributes($conn, $value['attributes']);
            }

            if (array_key_exists('listeners', $value)) {
                $this->_setConnectionListeners($conn, $value['listeners']);
            }
        }
    }

    /**
     * Set attributes of a Doctrine_Configurable instance
     *
     * @param   Doctrine_Configurable $object
     * @param   array $attributes
     * @return  void
     * @throws  Zend_Application_Resource_Exception
     */
    protected function _setAttributes(Doctrine_Configurable $object, array $attributes)
    {
        $reflect = new ReflectionClass('Doctrine');
        $doctrineConstants = $reflect->getConstants();

        $attributes = array_change_key_case($attributes, CASE_UPPER);
        foreach ($attributes as $key => $value) {
            if (!array_key_exists($key, $doctrineConstants)) {
                require_once 'Zend/Application/Resource/Exception.php';
                throw new Zend_Application_Resource_Exception("$key is not a valid attribute.");
            }

            $attrIdx = $doctrineConstants[$key];
            $attrVal = $value;

            if ((Doctrine::ATTR_RESULT_CACHE == $attrIdx) || (Doctrine::ATTR_QUERY_CACHE == $attrIdx)) {
                $attrVal = $this->_getCache($value);
            } else {
                if (is_string($value)) {
                    $value = strtoupper($value);
                    if (array_key_exists($value, $doctrineConstants)) {
                        $attrVal = $doctrineConstants[$value];
                    }
                }
            }

            $object->setAttribute($attrIdx, $attrVal);
        }
    }

    /**
     * Retrieve a Doctrine_Cache instance
     *
     * @param   array $options
     * @return  Doctrine_Cache
     * @throws  Zend_Application_Resource_Exception
     */
    protected function _getCache(array $options)
    {
        if (!array_key_exists('class', $options)) {
            require_once 'Zend/Application/Resource/Exception.php';
            throw new Zend_Application_Resource_Exception('Missing class option.');
        }

        $class = $options['class'];
        if (!class_exists($class)) {
            require_once 'Zend/Application/Resource/Exception.php';
            throw new Zend_Application_Resource_Exception("$class does not exist.");
        }

        $cacheOptions = array();
        if ((is_array($options['options'])) && (array_key_exists('options', $options))) {
            $cacheOptions = $options['options'];
        }

        return new $class($cacheOptions);
    }

    /**
     * Set connection listeners
     *
     * @param   Doctrine_Connection_Common $conn
     * @param   array $options
     * @return  void
     * @throws  Zend_Application_Resource_Exception
     */
    protected function _setConnectionListeners(Doctrine_Connection_Common $conn, array $options)
    {
        foreach ($options as $alias => $class) {
            if (!class_exists($class)) {
                require_once 'Zend/Application/Resource/Exception.php';
                throw new Zend_Application_Resource_Exception("$class does not exist.");
            }

            $conn->addListener(new $class(), $alias);
        }
    }

    /**
     * Build the DSN string
     *
     * @param   array $dsn
     * @return  string
     */
    protected function _buildDsnFromArray(array $dsn)
    {
        $options = null;
        if (array_key_exists('options', $dsn)) {
            $options = http_build_query($dsn['options']);
        }

        return sprintf('%s://%s:%s@%s/%s?%s',
            $dsn['adapter'],
            $dsn['user'],
            $dsn['pass'],
            $dsn['hostspec'],
            $dsn['database'],
            $options);
    }
}
