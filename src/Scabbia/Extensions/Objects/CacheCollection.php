<?php
/**
 * Scabbia Framework Version 1.1
 * http://larukedi.github.com/Scabbia-Framework/
 * Eser Ozvataf, eser@sent.com
 */

namespace Scabbia\Extensions\Objects;

use Scabbia\Extensions\Objects\Collection;

/**
 * Objects Extension: CacheCollection Class
 *
 * @package Scabbia
 * @subpackage Objects
 * @version 1.1.0
 */
class CacheCollection extends Collection
{
    /**
     * @ignore
     */
    public $_queue = array();
    /**
     * @ignore
     */
    public $_prefetch;
    /**
     * @ignore
     */
    public $_update;


    /**
     * @ignore
     */
    public function __construct(/* callable */ $uUpdate, /* callable */ $uPrefetch = null)
    {
        parent::__construct();

        $this->_update = $uUpdate;
        $this->_prefetch = $uPrefetch;
    }

    /**
     * @ignore
     */
    public function enqueue($uKey)
    {
        foreach ((array)$uKey as $tKey) {
            if (in_array($tKey, $this->_queue, true) || $this->keyExists($tKey)) {
                continue;
            }

            $this->_queue[] = $uKey;
        }
    }

    /**
     * @ignore
     */
    public function prefetch()
    {
        if (is_null($this->_prefetch)) {
            return;
        }

        $this->_items += call_user_func($this->_prefetch);
    }

    /**
     * @ignore
     */
    public function update($uArray = null)
    {
        if (is_null($this->_update)) {
            return;
        }

        if (count($this->_queue) == 0) {
            return;
        }

        $this->_items += call_user_func($this->_update, $this->_queue);
        $this->_queue = array();

        if (!is_null($uArray)) {
            return $this->getRange($this->_items);
        }

        return null;
    }

    /**
     * @ignore
     */
    public function updateRange(array $uArray = null)
    {
        $this->enqueue($uArray);
        return $this->update($uArray);
    }
}
