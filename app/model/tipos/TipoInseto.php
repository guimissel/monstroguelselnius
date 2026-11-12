<?php

// Tipo Inseto. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoInseto
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasInseto(): array
    {
        return ['flying', 'rock', 'fire'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasInseto(): array
    {
        return ['fighting', 'ground', 'grass'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesInseto(): array
    {
        return [];
    }
}
