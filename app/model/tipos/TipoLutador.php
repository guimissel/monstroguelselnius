<?php

// Tipo Lutador. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoLutador
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasLutador(): array
    {
        return ['flying', 'psychic', 'fairy'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasLutador(): array
    {
        return ['rock', 'bug', 'dark'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesLutador(): array
    {
        return [];
    }
}
