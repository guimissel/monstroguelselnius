<?php

// Tipo Fantasma. Quem usa esta trait ganha as fraquezas, resistencias e imunidades do tipo.
trait TipoFantasma
{
    // tipos de golpe que causam o dobro de dano num pokemon deste tipo
    public function fraquezasFantasma(): array
    {
        return ['ghost', 'dark'];
    }

    // tipos de golpe que causam metade do dano
    public function resistenciasFantasma(): array
    {
        return ['poison', 'bug'];
    }

    // tipos de golpe que nao causam dano nenhum
    public function imunidadesFantasma(): array
    {
        return ['normal', 'fighting'];
    }
}
