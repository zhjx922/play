<?php

class Base {
    public static function exception($message) {
        throw new Exception($message);
    }
}