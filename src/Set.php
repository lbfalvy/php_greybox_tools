<?php

/** Imitate a log(n) set with an associative array */
class Set {
    private $array = array();

    public function __construct($initial_values = []) {
        foreach ($initial_values as $value) $this->add($value);
    }

    public function add($value) { $this->array[$value] = true; }
    public function remove($value) { unset($this->array[$value]); }
    public function includes($value) { key_exists($value, $this->array); }
}
