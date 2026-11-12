<?php

// Tipo Fada. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoFada
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasFada(): array
    {
        return ['poison', 'steel'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasFada(): array
    {
        return ['fighting', 'bug', 'dark'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesFada(): array
    {
        return ['dragon'];
    }
}
