<?php

final class ColumnTimeline
{
    public function after(string $point): self
    {
        return $this;
    }
}

$timeline = new ColumnTimeline();
$timeline->after('first_name');
