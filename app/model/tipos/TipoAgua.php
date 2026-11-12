<?php

// Tipo Água. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoAgua
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasAgua(): array
    {
        return ['grass', 'electric'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasAgua(): array
    {
        return ['steel', 'fire', 'water', 'ice'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesAgua(): array
    {
        return [];
    }
}
