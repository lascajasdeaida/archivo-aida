<?php
declare(strict_types=1);

namespace Aida;

/**
 * Punto de extensión: qué hacer cuando llega una consulta nueva (mandar un correo al archivo, a quien pidió, etc.).
 * Se implementa cuando el cliente defina quién responde. Recibe la fila completa de `consulta` (con datos personales):
 * no registrar esos datos en logs.
 */
interface NotificadorConsultas
{
    public function nueva(array $consulta): void;
}
