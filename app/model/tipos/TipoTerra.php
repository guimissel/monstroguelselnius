<?php

// Tipo Terra. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoTerra
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasTerra(): array
    {
        return ['water', 'grass', 'ice'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasTerra(): array
    {
        return ['poison', 'rock'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesTerra(): array
    {
        return ['electric'];
    }
}
