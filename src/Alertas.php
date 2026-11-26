<?php
declare(strict_types=1);

namespace Aida;

/**
 * Catálogo de alertas para el operador del backoffice.
 *
 * Cada incidencia que generan la importación y el escaneo tiene acá su título, una leyenda
 * en lenguaje llano y qué se espera que haga el operador. Las que tienen `valida = true`
 * quedan pendientes hasta que alguien las revise y las confirme (incidencia.resuelta).
 *
 * El backoffice muestra estas leyendas en el panel de alertas y en la ficha del registro afectado.
 */
final class Alertas
{
    /** @var array<string,array{titulo:string,leyenda:string,accion:string,valida:bool}> */
    public const CATALOGO = [
        // --- Importación del Excel ---
        'REGISTRO_DEL_BACKOFFICE' => [
            'titulo'  => 'Registro cargado a mano que ahora llegó en el Excel',
            'leyenda' => 'Este registro se creó desde el backoffice y la última planilla trae uno con el mismo número. No se modificó nada.',
            'accion'  => 'Comparar las dos versiones y decidir cuál vale. Si vale la del Excel, pasar el registro a “origen Excel”.',
            'valida'  => true,
        ],
        'REGISTRO_DADO_DE_BAJA' => [
            'titulo'  => 'Registro dado de baja que sigue en el Excel',
            'leyenda' => 'Se borró desde el backoffice pero la planilla lo sigue trayendo. No se volvió a crear.',
            'accion'  => 'Confirmar la baja (y pedir al equipo que lo quite de la planilla) o restaurarlo.',
            'valida'  => true,
        ],
        'CAJA_DADA_DE_BAJA' => [
            'titulo'  => 'Caja dada de baja que sigue en el Excel',
            'leyenda' => 'La caja se borró desde el backoffice y la planilla tiene su pestaña. No se importó.',
            'accion'  => 'Confirmar la baja o restaurar la caja.',
            'valida'  => true,
        ],
        'REGISTRO_FUERA_DEL_EXCEL' => [
            'titulo'  => 'Registro que ya no está en el Excel',
            'leyenda' => 'Estaba cargado y la última planilla ya no lo trae. No se borró.',
            'accion'  => 'Averiguar si lo quitaron a propósito. Si es así, darlo de baja desde la ficha.',
            'valida'  => true,
        ],
        'REGISTRO_DUPLICADO' => [
            'titulo'  => 'Número de registro repetido en el Excel',
            'leyenda' => 'La planilla tiene dos filas con el mismo número de registro. Se tomó la primera.',
            'accion'  => 'Revisar la segunda fila y pedir la corrección al equipo de digitalización.',
            'valida'  => true,
        ],
        'ACCESO_A_REVISAR' => [
            'titulo'  => 'Acceso sin definir',
            'leyenda' => 'La columna PRIVADO dice “VERLO”. Mientras tanto el registro no se publica como público ni como restringido.',
            'accion'  => 'Elegir Público o Restringido desde la ficha.',
            'valida'  => true,
        ],
        'PRIVADO_CON_TEXTO' => [
            'titulo'  => 'Texto suelto en la columna PRIVADO',
            'leyenda' => 'La columna PRIVADO tiene un texto que no es un valor de acceso. Se cargó como público.',
            'accion'  => 'Confirmar que es público o marcarlo como restringido.',
            'valida'  => true,
        ],
        'NOMENCLATURA_DISTINTA' => [
            'titulo'  => 'Nomenclatura distinta en el Excel',
            'leyenda' => 'La columna “Nombre de archivo digital” no coincide con caja y registro. Se usó caja + registro.',
            'accion'  => 'Avisar al equipo para que la corrija. No afecta la carga.',
            'valida'  => false,
        ],
        'NOMENCLATURA_ILEGIBLE' => [
            'titulo'  => 'Nomenclatura con formato incorrecto',
            'leyenda' => 'La columna “Nombre de archivo digital” no tiene la forma C#R#.',
            'accion'  => 'Avisar al equipo para que la corrija. No afecta la carga.',
            'valida'  => false,
        ],
        'NOMENCLATURA_REPETIDA' => [
            'titulo'  => 'Nomenclatura repetida en el Excel',
            'leyenda' => 'Dos registros tienen el mismo “Nombre de archivo digital”.',
            'accion'  => 'Avisar al equipo. No afecta la carga.',
            'valida'  => false,
        ],
        'FILA_SIN_NUMERO' => [
            'titulo'  => 'Fila sin número de registro',
            'leyenda' => 'Una fila tiene datos pero no número de registro, así que no se importó.',
            'accion'  => 'Revisar la planilla. Si es un registro real, pedir que le asignen número o cargarlo a mano.',
            'valida'  => true,
        ],
        'CAJA_DISTINTA'            => ['titulo' => 'Número de caja distinto en la pestaña', 'leyenda' => 'La pestaña dice una caja y la columna A otra. Se usó la de la pestaña.', 'accion' => 'Confirmar cuál es la correcta.', 'valida' => true],
        'COLUMNA_REPETIDA'         => ['titulo' => 'Columna repetida', 'leyenda' => 'La pestaña tiene dos columnas con el mismo título. Se usó la primera.', 'accion' => 'Avisar al equipo.', 'valida' => false],
        'COLUMNAS_INVERTIDAS'      => ['titulo' => 'Columnas con el título cambiado', 'leyenda' => 'Los títulos de “Digitalización” y “Nombre de archivo” están invertidos; se leyeron por contenido.', 'accion' => 'Ninguna.', 'valida' => false],
        'TEXTO_RECORTADO'          => ['titulo' => 'Texto recortado', 'leyenda' => 'Un campo superaba el largo permitido y se recortó.', 'accion' => 'Revisar el campo en la ficha y completarlo a mano si hace falta.', 'valida' => true],
        'VOCABULARIO_TEXTO_LARGO'  => ['titulo' => 'Texto largo en Tipo o Descriptor', 'leyenda' => 'Parece un resumen pegado en la columna equivocada. No se cargó como término.', 'accion' => 'Revisar el registro y asignarle el tipo o descriptor correcto.', 'valida' => true],
        'ANIO_RANGO_SUPUESTO'      => ['titulo' => 'Años con barra tomados como rango', 'leyenda' => 'La columna Año trae dos años no seguidos separados por barra (“1982 /1986”). Se toman como rango (el registro aparece en todos los años del medio), marcado “sin confirmar”.', 'accion' => 'Confirmar con el equipo si es un rango o solo esos dos años, y corregir el año en la ficha si hace falta.', 'valida' => false],
        'ANIO_NO_INTERPRETADO'     => ['titulo' => 'Año que no se pudo leer', 'leyenda' => 'La columna Año tiene un texto que no es un año, un rango ni una fecha (“Posterior a 1990…”, “Año XXII”). El registro queda sin año para el filtro; el texto se muestra igual.', 'accion' => 'Si corresponde un año, pedirle al equipo que lo escriba en la planilla (por ejemplo “1990?” si es dudoso).', 'valida' => false],

        // --- Reglas de curaduría de vocabularios aplicadas por la importación (src/Curaduria.php) ---
        'VOCAB_MOVIDO' => [
            'titulo'  => 'Término movido al otro vocabulario',
            'leyenda' => 'La planilla trae en la columna Tipo un término que se curó como descriptor (o al revés). Se cargó en el vocabulario correcto, sin crear un término nuevo.',
            'accion'  => 'Ninguna. Si conviene, avisar al equipo de digitalización para que lo escriba en la columna correcta (ver Vocabularios → Cambios de curaduría).',
            'valida'  => false,
        ],
        'VOCAB_FUSIONADO' => [
            'titulo'  => 'Término fusionado en otro',
            'leyenda' => 'La planilla trae un término que se fusionó con otro. Se cargó el término en que se fusionó.',
            'accion'  => 'Ninguna. Se puede pasar al equipo la lista de cambios de curaduría para que usen el término vigente.',
            'valida'  => false,
        ],
        'VOCAB_RENOMBRADO' => [
            'titulo'  => 'Término con la grafía anterior',
            'leyenda' => 'La planilla trae la grafía que tenía un término antes de renombrarlo. Se cargó el término con su nombre actual.',
            'accion'  => 'Ninguna. Se puede avisar al equipo cuál es la grafía vigente.',
            'valida'  => false,
        ],
        'VOCAB_DESCARTADO' => [
            'titulo'  => 'Término descartado que sigue en la planilla',
            'leyenda' => 'La planilla trae un término que se descartó en la curaduría (una letra suelta, un texto pegado). Se ignoró; los demás tipos y descriptores del registro se cargaron igual.',
            'accion'  => 'Pedirle al equipo que lo borre de la planilla o lo reemplace por el término que corresponda.',
            'valida'  => false,
        ],
        'CORREGIDO_EN_PLANILLA' => [
            'titulo'  => 'Corrección hecha también en la planilla',
            'leyenda' => 'Había campos corregidos a mano en el backoffice (o con la corrección masiva) y la planilla ahora trae el mismo valor. '
                       . 'Si el valor es idéntico, el campo se desbloquea y vuelve a tomarse del Excel. Si solo cambian mayúsculas o espacios, '
                       . 'se conserva la versión de la base y el campo sigue bloqueado (lo indica la lista).',
            'accion'  => 'Ninguna. Los campos desbloqueados se toman de la planilla en las próximas importaciones.',
            'valida'  => false,
        ],
        'CAJA_VACIA'               =>['titulo' => 'Pestaña sin registros', 'leyenda' => 'La pestaña de la caja no tiene filas con datos.', 'accion' => 'Ninguna, salvo que la caja debería tener contenido.', 'valida' => false],

        // --- Escaneo de archivos ---
        'ARCHIVO_DESAPARECIDO' => [
            'titulo'  => 'Archivo que ya no está en la carpeta',
            'leyenda' => 'Estaba asociado al registro y en el último escaneo no apareció. El registro pasó a “pendiente”.',
            'accion'  => 'Verificar si lo movieron o renombraron. Si lo borraron a propósito, confirmar.',
            'valida'  => true,
        ],
        'ARCHIVO_SIN_REGISTRO' => [
            'titulo'  => 'Archivo sin registro',
            'leyenda' => 'Hay un archivo con nombre C#R# pero ese registro no existe en la base.',
            'accion'  => 'Importar el Excel actualizado o dar de alta el registro a mano.',
            'valida'  => true,
        ],
        'ARCHIVO_DE_UNA_BAJA' => [
            'titulo'  => 'Archivo de un registro dado de baja',
            'leyenda' => 'El archivo sigue en la carpeta pero su registro o su caja se dieron de baja. No se asoció.',
            'accion'  => 'Confirmar la baja o restaurar el registro.',
            'valida'  => true,
        ],
        'ARCHIVO_EN_OTRA_CAJA'     => ['titulo' => 'Archivo en la carpeta de otra caja', 'leyenda' => 'El nombre del archivo indica una caja y está guardado en la carpeta de otra.', 'accion' => 'Moverlo a la carpeta correcta.', 'valida' => true],
        'NOMBRE_NO_RECONOCIDO'     => ['titulo' => 'Archivo con nombre no reconocido', 'leyenda' => 'El nombre no tiene la forma C#R#, así que no se pudo asociar.', 'accion' => 'Renombrarlo o asociarlo a mano desde la ficha.', 'valida' => true],
        'FORMATO_NO_RECONOCIDO'    => ['titulo' => 'Formato de archivo no admitido', 'leyenda' => 'La extensión no está entre las admitidas.', 'accion' => 'Convertirlo a un formato admitido.', 'valida' => true],
        'EXCEL_DICE_NO_DIGITALIZADO' => ['titulo' => 'Hay archivo pero el Excel dice “no digitalizado”', 'leyenda' => 'El archivo existe y se publicó; la planilla está desactualizada.', 'accion' => 'Avisar al equipo para que actualice la planilla.', 'valida' => false],
        'PENDIENTE_DE_SUBIR'       => ['titulo' => 'Digitalizado según el Excel, archivo pendiente', 'leyenda' => 'La planilla lo marca digitalizado pero el archivo todavía no está en la carpeta.', 'accion' => 'Ninguna: se asocia solo cuando lo suban.', 'valida' => false],

        // --- Origen Drive (src/Archivos/Drive.php, docs/decisiones.md 16/11/2026) ---
        'DRIVE_RESTRINGIDO_COMPARTIDO' => [
            'titulo'  => 'Documento restringido compartido en Drive',
            'leyenda' => 'El registro es restringido (o de acceso a revisar) y en el Drive del cliente el archivo está compartido con “cualquiera con el enlace”. '
                       . 'El sitio no lo muestra, pero quien tenga el enlace de Drive puede abrirlo.',
            'accion'  => 'Pedirle al cliente que en Drive le saque “cualquiera con el enlace” (Compartir → Acceso general → Restringido) y volver a cargar el listado. '
                       . 'Si en realidad es público, cambiar el acceso en la ficha.',
            'valida'  => true,
        ],
        'DRIVE_PUBLICO_SIN_COMPARTIR' => [
            'titulo'  => 'Documento público sin compartir en Drive',
            'leyenda' => 'El registro es público y está publicado, pero en Drive el archivo no está compartido con “cualquiera con el enlace”: el visor de la ficha '
                       . 'va a pedir iniciar sesión en Google y la descarga no va a andar.',
            'accion'  => 'Pedirle al cliente que lo comparta con “cualquiera con el enlace” (lector) y volver a cargar el listado.',
            'valida'  => false,
        ],
        'DRIVE_SIN_ID' => [
            'titulo'  => 'Archivo sin id de Drive',
            'leyenda' => 'El listado de Drive trae el archivo pero no su id. Cuenta como digitalizado, pero el sitio no lo puede mostrar ni descargar.',
            'accion'  => 'Completar el id en el listado (columna drive_id) y volver a cargarlo.',
            'valida'  => false,
        ],
        'LISTADO_LINEA_INVALIDA' => [
            'titulo'  => 'Línea del listado de Drive que no se pudo leer',
            'leyenda' => 'Una línea del listado tiene una ruta, un id o un peso que no son válidos. Se salteó; si el archivo ya estaba, pasa a “no presente”.',
            'accion'  => 'Corregir esa línea del listado y volver a cargarlo.',
            'valida'  => false,
        ],
    ];

    public static function requiereValidacion(string $codigo, string $nivel): bool
    {
        return self::CATALOGO[$codigo]['valida'] ?? ($nivel === 'error');
    }

    /** @return array{titulo:string,leyenda:string,accion:string,valida:bool} */
    public static function de(string $codigo): array
    {
        return self::CATALOGO[$codigo] ?? ['titulo' => $codigo, 'leyenda' => '', 'accion' => '', 'valida' => true];
    }
}
