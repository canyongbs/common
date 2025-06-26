<?php

function ($a, $b) {
    // no typehint for $a and $b
    return $a + $b;
};

$acceptsClosure = function (callable $closure) {
    return $closure(1, 2);
};

$acceptsClosure(function ($c, $d) {
    return $c + $d;
});
