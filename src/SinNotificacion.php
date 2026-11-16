<?php
declare(strict_types=1);

namespace Aida;

/** Por ahora no se avisa a nadie: la consulta queda en el backoffice (Consultas) con el chip de pendientes. */
final class SinNotificacion implements NotificadorConsultas
{
    public function nueva(array $consulta): void
    {
    }
}
