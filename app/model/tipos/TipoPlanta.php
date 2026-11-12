<?php

// Tipo Planta. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoPlanta
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasPlanta(): array
    {
        return ['flying', 'poison', 'bug', 'fire', 'ice'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasPlanta(): array
    {
        return ['ground', 'water', 'grass', 'electric'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesPlanta(): array
    {
        return [];
    }
}
