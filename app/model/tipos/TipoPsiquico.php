<?php

// Tipo Psíquico. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoPsiquico
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasPsiquico(): array
    {
        return ['bug', 'ghost', 'dark'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasPsiquico(): array
    {
        return ['fighting', 'psychic'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesPsiquico(): array
    {
        return [];
    }
}
