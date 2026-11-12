<?php

// Tipo Voador. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoVoador
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasVoador(): array
    {
        return ['rock', 'electric', 'ice'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasVoador(): array
    {
        return ['fighting', 'bug', 'grass'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesVoador(): array
    {
        return ['ground'];
    }
}
