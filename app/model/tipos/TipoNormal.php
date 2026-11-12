<?php

// Tipo Normal. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoNormal
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasNormal(): array
    {
        return ['fighting'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasNormal(): array
    {
        return [];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesNormal(): array
    {
        return ['ghost'];
    }
}
