<?php

namespace CanyonGBS\Common\Parser;

interface LanguageInterface
{
    /**
     * @return array<string>
     */
    public function getSuffixes(): array;

    /**
     * @return array<string>
     */
    public function getLastnamePrefixes(): array;

    /**
     * @return array<string>
     */
    public function getSalutations(): array;
}