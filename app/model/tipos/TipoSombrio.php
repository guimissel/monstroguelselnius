<?php

// Tipo Sombrio. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoSombrio
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasSombrio(): array
    {
        return ['fighting', 'bug', 'fairy'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasSombrio(): array
    {
        return ['ghost', 'dark'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesSombrio(): array
    {
        return ['psychic'];
    }
}
