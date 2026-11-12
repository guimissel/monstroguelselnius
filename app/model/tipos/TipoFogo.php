<?php

// Tipo Fogo. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoFogo
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasFogo(): array
    {
        return ['ground', 'rock', 'water'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasFogo(): array
    {
        return ['bug', 'steel', 'fire', 'grass', 'ice', 'fairy'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesFogo(): array
    {
        return [];
    }
}
