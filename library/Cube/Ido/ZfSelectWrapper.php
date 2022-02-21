<?php

namespace Icinga\Module\Cube\Ido;

/**
 * Since version 1.1.0 we're using the monitoring module's queries as the cubes' base queries.
 * Before, the host object table was available using the alias 'o'. Now it's 'ho'.
 * Without this wrapper, the action link hook provided by the director would fail because it relies on the alias 'o'.
 */
class ZfSelectWrapper
{
    /** @var \Zend_Db_Select */
    protected $select;

    public function __construct(\Zend_Db_Select $select)
    {
        $this->select = $select;
    }

    /**
     * Get the underlying Zend_Db_Select query
     *
     * @return \Zend_Db_Select
     */
    public function unwrap()
    {
        return $this->select;
    }

    /**
     * {@see \Zend_Db_Select::reset()}
     */
    public function reset($part = null)
    {
        $this->select->reset($part);

        return $this;
    }

    /**
     * {@see \Zend_Db_Select::columns()}
     */
    public function columns($cols = '*', $correlationName = null)
    {
        if (is_array($cols)) {
            foreach ($cols as $alias => &$col) {
                if (substr($col, 0, 2) === 'o.') {
                    $col = 'ho.' . substr($col, 2);
                }
            }
        }

        return $this->select->columns($cols, $correlationName);
    }

    /**
     * Proxy Zend_Db_Select method calls
     *
     * @param string $name      The name of the method to call
     * @param array  $arguments Arguments for the method to call
     *
     * @return mixed
     *
     * @throws \BadMethodCallException If the called method does not exist
     */
    public function __call($name, array $arguments)
    {
        if (! method_exists($this->select, $name)) {
            $class = get_class($this);
            $message = "Call to undefined method $class::$name";

            throw new \BadMethodCallException($message);
        }

        return call_user_func_array([$this->select, $name], $arguments);
    }
}
