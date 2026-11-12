<?php

// Tipo Pedra. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoPedra
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasPedra(): array
    {
        return ['fighting', 'ground', 'steel', 'water', 'grass'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasPedra(): array
    {
        return ['normal', 'flying', 'poison', 'fire'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesPedra(): array
    {
        return [];
    }
}
