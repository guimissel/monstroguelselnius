<?php

// Tipo Veneno. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoVeneno
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasVeneno(): array
    {
        return ['ground', 'psychic'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasVeneno(): array
    {
        return ['fighting', 'poison', 'bug', 'grass', 'fairy'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesVeneno(): array
    {
        return [];
    }
}
