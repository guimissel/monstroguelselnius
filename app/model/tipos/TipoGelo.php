<?php

// Tipo Gelo. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoGelo
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasGelo(): array
    {
        return ['fighting', 'rock', 'steel', 'fire'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasGelo(): array
    {
        return ['ice'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesGelo(): array
    {
        return [];
    }
}
