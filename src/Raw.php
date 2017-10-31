<?php


namespace DB;


class Raw
{
    private $data = null;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function get()
    {
        return $this->data;
    }
}