<?php

// Tipo Aço. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoAco
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasAco(): array
    {
        return ['fighting', 'ground', 'fire'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasAco(): array
    {
        return ['normal', 'flying', 'rock', 'bug', 'steel', 'grass', 'psychic', 'ice', 'dragon', 'fairy'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesAco(): array
    {
        return ['poison'];
    }
}
