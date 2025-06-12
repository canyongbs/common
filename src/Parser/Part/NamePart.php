<?php

namespace CanyonGBS\Common\Parser\Part;

abstract class NamePart extends AbstractPart
{
    /**
     * camelcase the lastname
     *
     * @return string
     */
    public function normalize(): string
    {
        return $this->camelcase($this->getValue());
    }
}
