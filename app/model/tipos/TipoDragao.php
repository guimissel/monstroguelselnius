<?php

// Tipo Dragão. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoDragao
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasDragao(): array
    {
        return ['ice', 'dragon', 'fairy'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasDragao(): array
    {
        return ['fire', 'water', 'grass', 'electric'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesDragao(): array
    {
        return [];
    }
}
