<?php

// Tipo Elétrico. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoEletrico
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasEletrico(): array
    {
        return ['ground'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasEletrico(): array
    {
        return ['flying', 'steel', 'electric'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesEletrico(): array
    {
        return [];
    }
}
