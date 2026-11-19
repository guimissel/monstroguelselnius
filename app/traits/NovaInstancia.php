<?php

trait NovaInstancia {
    public static function NovaInstancia (): static
    {
        return new static ();
    }
}