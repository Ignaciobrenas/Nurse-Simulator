<?php

namespace App\Ci;

class CiFailureExample
{
    public function broken(): string
    {
        // Error de sintaxis intencionado: falta el punto y coma
        return 'ci-failure'
    }
}
